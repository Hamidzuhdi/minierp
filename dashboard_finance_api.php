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
$qIn = "SELECT COALESCE(SUM(ft.amount), 0) total
                FROM finance_transactions ft
                LEFT JOIN invoices i ON ft.reference_type = 'invoice' AND ft.reference_id = i.id
                WHERE ft.direction = 'in'
                    AND (ft.reference_type <> 'invoice' OR COALESCE(i.status_piutang, '') <> 'Tidak_Aktif')
                    $month_filter";
$qOut = "SELECT COALESCE(SUM(amount), 0) total FROM finance_transactions ft WHERE ft.direction = 'out' $month_filter";
$qPo = "SELECT COALESCE(SUM(amount), 0) total FROM finance_transactions ft WHERE ft.direction = 'out' AND ft.category = 'OUT-PO' $month_filter";
$qOps = "SELECT COALESCE(SUM(amount), 0) total FROM finance_transactions ft WHERE ft.direction = 'out' AND ft.reference_type = 'operational' $month_filter";
$qSpkIn = "SELECT COALESCE(SUM(ft.amount), 0) total
                     FROM finance_transactions ft
                     JOIN invoices i ON i.id = ft.reference_id
                     WHERE ft.direction = 'in'
                         AND ft.reference_type = 'invoice'
                         AND COALESCE(i.status_piutang, '') <> 'Tidak_Aktif'
                         $month_filter";

$qSalesDiscount = "SELECT COALESCE(SUM(ft.amount), 0) total
                                    FROM finance_transactions ft
                                    LEFT JOIN expense_categories ec ON ec.code = ft.category
                                    WHERE ft.direction = 'out'
                                        AND ft.reference_type = 'operational'
                                        AND (
                                                UPPER(ft.category) = 'SALES-DISCOUNT'
                                                OR UPPER(ft.category) = 'EXP-SALES-DISCOUNT'
                                                OR LOWER(COALESCE(ec.name, '')) = 'sales discount'
                                        )
                                        $month_filter";

$qFixedExpense = "SELECT COALESCE(SUM(ft.amount), 0) total
                                    FROM finance_transactions ft
                                    JOIN expense_categories ec ON ec.code = ft.category
                                    WHERE ft.direction = 'out'
                                        AND ft.reference_type = 'operational'
                                        AND ec.is_active = 1
                                        AND ec.status = 1
                                        $month_filter";

$qVariableExpense = "SELECT COALESCE(SUM(ft.amount), 0) total
                                         FROM finance_transactions ft
                                         JOIN expense_categories ec ON ec.code = ft.category
                                         WHERE ft.direction = 'out'
                                             AND ft.reference_type = 'operational'
                                             AND ec.is_active = 1
                                             AND ec.status = 0
                                             $month_filter";

$total_in = (float)mysqli_fetch_assoc(mysqli_query($conn, $qIn))['total'];
$total_out = (float)mysqli_fetch_assoc(mysqli_query($conn, $qOut))['total'];
$po_out = (float)mysqli_fetch_assoc(mysqli_query($conn, $qPo))['total'];
$ops_out = (float)mysqli_fetch_assoc(mysqli_query($conn, $qOps))['total'];
$spk_in = (float)mysqli_fetch_assoc(mysqli_query($conn, $qSpkIn))['total'];
$sales_discount = (float)mysqli_fetch_assoc(mysqli_query($conn, $qSalesDiscount))['total'];
$fixed_expense_total = (float)mysqli_fetch_assoc(mysqli_query($conn, $qFixedExpense))['total'];
$variable_expense_total = (float)mysqli_fetch_assoc(mysqli_query($conn, $qVariableExpense))['total'];

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

// Total jasa mekanik (service) - untuk history/tracking saja
$spk_month_filter = !empty($month_esc) ? "AND DATE_FORMAT(s.created_at, '%Y-%m') = '$month_esc'" : '';
$qTotalJasa = "SELECT COALESCE(SUM(ss.qty * ss.harga), 0) total
               FROM spk_services ss
               JOIN spk s ON s.id = ss.spk_id
               WHERE s.status_spk IN ('Selesai', 'Dikirim ke Owner', 'Buat Invoice', 'Sudah Cetak Invoice')
               $spk_month_filter";
$total_jasa_mekanik = (float)mysqli_fetch_assoc(mysqli_query($conn, $qTotalJasa))['total'];

$laba_kotor_formula = $total_in - ($sales_discount + $spare_hpp);
$total_beban_operasional = $fixed_expense_total + $variable_expense_total;
$zakat = $laba_kotor_formula > 0 ? ($laba_kotor_formula * 0.025) : 0;
$gross_profit = $laba_kotor_formula - ($total_beban_operasional + $zakat);

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
                SUM(CASE
                       WHEN ft.direction = 'in'
                           AND (ft.reference_type <> 'invoice' OR COALESCE(i.status_piutang, '') <> 'Tidak_Aktif')
                       THEN ft.amount ELSE 0 END) as total_in,
                SUM(CASE WHEN ft.direction = 'out' THEN ft.amount ELSE 0 END) as total_out
           FROM finance_transactions ft
           LEFT JOIN invoices i ON ft.reference_type = 'invoice' AND ft.reference_id = i.id
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

$qFixedBreakdown = "SELECT ec.code as category, ec.name as category_name, SUM(ft.amount) total_out
                                        FROM finance_transactions ft
                                        JOIN expense_categories ec ON ec.code = ft.category
                                        WHERE ft.direction = 'out'
                                            AND ft.reference_type = 'operational'
                                            AND ec.is_active = 1
                                            AND ec.status = 1
                                            $month_filter
                                        GROUP BY ec.code, ec.name
                                        ORDER BY total_out DESC";
$fixed_categories = [];
$resFixedBreakdown = mysqli_query($conn, $qFixedBreakdown);
while ($row = mysqli_fetch_assoc($resFixedBreakdown)) {
        $fixed_categories[] = $row;
}

$qVariableBreakdown = "SELECT ec.code as category, ec.name as category_name, SUM(ft.amount) total_out
                                             FROM finance_transactions ft
                                             JOIN expense_categories ec ON ec.code = ft.category
                                             WHERE ft.direction = 'out'
                                                 AND ft.reference_type = 'operational'
                                                 AND ec.is_active = 1
                                                 AND ec.status = 0
                                                 $month_filter
                                             GROUP BY ec.code, ec.name
                                             ORDER BY total_out DESC";
$variable_categories = [];
$resVariableBreakdown = mysqli_query($conn, $qVariableBreakdown);
while ($row = mysqli_fetch_assoc($resVariableBreakdown)) {
        $variable_categories[] = $row;
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
        'sales_discount' => $sales_discount,
        'laba_kotor_formula' => $laba_kotor_formula,
        'fixed_expense_total' => $fixed_expense_total,
        'variable_expense_total' => $variable_expense_total,
        'total_beban_operasional' => $total_beban_operasional,
        'zakat' => $zakat,
        'gross_profit' => $gross_profit,
        'total_jasa_mekanik' => $total_jasa_mekanik,
        'net_cashflow' => $total_in - $total_out,
        'saldo_akhir' => $saldo_akhir,
    ],
    'monthly' => array_values($chart_months),
    'top_categories' => $top_categories,
    'fixed_categories' => $fixed_categories,
    'variable_categories' => $variable_categories,
    'recent' => $recent,
]);
?>
