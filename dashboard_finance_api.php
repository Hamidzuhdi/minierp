<?php
session_start();
require_once 'config.php';
require_once 'finance_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Owner') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');
finance_ensure_default_accounts($conn);

$month = trim($_GET['month'] ?? '');
$month_esc = !empty($month) ? mysqli_real_escape_string($conn, $month) : '';
$month_filter = !empty($month_esc) ? "AND DATE_FORMAT(ft.tanggal, '%Y-%m') = '$month_esc'" : '';
$month_filter_invoice = !empty($month_esc) ? "AND DATE_FORMAT(i.tanggal, '%Y-%m') = '$month_esc'" : '';

// Summary from ledger (exclude internal transfer from net cashflow)
$qIn = "SELECT COALESCE(SUM(amount), 0) total FROM finance_transactions ft WHERE ft.direction = 'in' $month_filter";
$qOut = "SELECT COALESCE(SUM(amount), 0) total FROM finance_transactions ft WHERE ft.direction = 'out' $month_filter";
$qPo = "SELECT COALESCE(SUM(amount), 0) total FROM finance_transactions ft WHERE ft.direction = 'out' AND ft.category = 'OUT-PO' $month_filter";
$qOps = "SELECT COALESCE(SUM(amount), 0) total FROM finance_transactions ft WHERE ft.direction = 'out' AND ft.reference_type = 'operational' $month_filter";
$qSpkIn = "SELECT COALESCE(SUM(amount), 0) total FROM finance_transactions ft WHERE ft.direction = 'in' AND ft.reference_type = 'invoice' $month_filter";

$total_in = (float)mysqli_fetch_assoc(mysqli_query($conn, $qIn))['total'];
$total_out = (float)mysqli_fetch_assoc(mysqli_query($conn, $qOut))['total'];
$po_out = (float)mysqli_fetch_assoc(mysqli_query($conn, $qPo))['total'];
$ops_out = (float)mysqli_fetch_assoc(mysqli_query($conn, $qOps))['total'];
$spk_in = (float)mysqli_fetch_assoc(mysqli_query($conn, $qSpkIn))['total'];

// Sparepart realized profit (invoice Lunas) using snapshot selling price and HPP.
$has_hpp_col_res = mysqli_query($conn, "SHOW COLUMNS FROM spk_items LIKE 'hpp_satuan'");
$has_hpp_col = $has_hpp_col_res && mysqli_num_rows($has_hpp_col_res) > 0;

$qSpareRevenue = "SELECT COALESCE(SUM(si.qty * COALESCE(NULLIF(si.harga_satuan, 0), sp.harga_jual_default)), 0) total
                  FROM spk_items si
                  JOIN spareparts sp ON sp.id = si.sparepart_id
                  JOIN invoices i ON i.spk_id = si.spk_id
                  WHERE i.status_piutang = 'Lunas' $month_filter_invoice";

if ($has_hpp_col) {
    $qSpareHpp = "SELECT COALESCE(SUM(si.qty * si.hpp_satuan), 0) total
                  FROM spk_items si
                  JOIN invoices i ON i.spk_id = si.spk_id
                  WHERE i.status_piutang = 'Lunas' $month_filter_invoice";
} else {
    $qSpareHpp = "SELECT COALESCE(SUM(si.qty * sp.harga_beli_default), 0) total
                  FROM spk_items si
                  JOIN spareparts sp ON sp.id = si.sparepart_id
                  JOIN invoices i ON i.spk_id = si.spk_id
                  WHERE i.status_piutang = 'Lunas' $month_filter_invoice";
}

$spare_revenue = (float)mysqli_fetch_assoc(mysqli_query($conn, $qSpareRevenue))['total'];
$spare_hpp = (float)mysqli_fetch_assoc(mysqli_query($conn, $qSpareHpp))['total'];
$spare_profit = $spare_revenue - $spare_hpp;

$cashAcc = finance_get_account_by_code($conn, 'cash');
$bankAcc = finance_get_account_by_code($conn, 'bank');
$saldo_akhir = (float)($cashAcc['current_balance'] ?? 0) + (float)($bankAcc['current_balance'] ?? 0);

// Monthly trend (last 12 months)
$chart_months = [];
for ($i = 11; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i months"));
    $chart_months[$m] = [
        'bulan' => $m,
        'label' => date('M Y', strtotime($m . '-01')),
        'total_in' => 0,
        'total_out' => 0,
        'net' => 0,
    ];
}

$oldest_month = date('Y-m', strtotime('-11 months'));
$qMonthly = "SELECT DATE_FORMAT(ft.tanggal, '%Y-%m') bulan,
                    SUM(CASE WHEN ft.direction = 'in' THEN ft.amount ELSE 0 END) as total_in,
                    SUM(CASE WHEN ft.direction = 'out' THEN ft.amount ELSE 0 END) as total_out
             FROM finance_transactions ft
             WHERE DATE_FORMAT(ft.tanggal, '%Y-%m') >= '$oldest_month'
             GROUP BY DATE_FORMAT(ft.tanggal, '%Y-%m')";
$resMonthly = mysqli_query($conn, $qMonthly);
while ($row = mysqli_fetch_assoc($resMonthly)) {
    if (isset($chart_months[$row['bulan']])) {
        $chart_months[$row['bulan']]['total_in'] = (float)$row['total_in'];
        $chart_months[$row['bulan']]['total_out'] = (float)$row['total_out'];
        $chart_months[$row['bulan']]['net'] = (float)$row['total_in'] - (float)$row['total_out'];
    }
}

// Top expense categories (operational)
$qTopOps = "SELECT ft.category, COALESCE(ec.name, ft.category) as category_name, SUM(ft.amount) total_out
            FROM finance_transactions ft
            LEFT JOIN expense_categories ec ON ft.category = ec.code
            WHERE ft.direction = 'out' AND ft.reference_type = 'operational' $month_filter
            GROUP BY ft.category, ec.name
            ORDER BY total_out DESC
            LIMIT 10";
$top_categories = [];
$resTopOps = mysqli_query($conn, $qTopOps);
while ($row = mysqli_fetch_assoc($resTopOps)) {
    $top_categories[] = $row;
}

// Recent ledger transactions
$qRecent = "SELECT ft.tanggal, ft.direction, ft.category, ft.reference_type, ft.reference_id, ft.note, ft.amount,
                   fa.name as account_name, COALESCE(ec.name, '') as expense_category_name
            FROM finance_transactions ft
            JOIN finance_accounts fa ON ft.account_id = fa.id
            LEFT JOIN expense_categories ec ON ft.category = ec.code
            WHERE 1=1 $month_filter
            ORDER BY ft.id DESC
            LIMIT 12";
$recent = [];
$resRecent = mysqli_query($conn, $qRecent);
while ($row = mysqli_fetch_assoc($resRecent)) {
    $recent[] = $row;
}

echo json_encode([
    'success' => true,
    'summary' => [
        'total_in' => $total_in,
        'total_out' => $total_out,
        'po_out' => $po_out,
        'ops_out' => $ops_out,
        'spk_in' => $spk_in,
        'spare_revenue' => $spare_revenue,
        'spare_hpp' => $spare_hpp,
        'spare_profit' => $spare_profit,
        'net_cashflow' => $total_in - $total_out,
        'saldo_akhir' => $saldo_akhir,
    ],
    'monthly' => array_values($chart_months),
    'top_categories' => $top_categories,
    'recent' => $recent,
]);
?>
