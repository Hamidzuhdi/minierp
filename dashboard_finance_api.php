<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Owner') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$month = trim($_GET['month'] ?? '');
$month_esc = !empty($month) ? mysqli_real_escape_string($conn, $month) : '';

$month_cond = !empty($month_esc) ? "AND DATE_FORMAT(i.tanggal, '%Y-%m') = '$month_esc'" : '';
$month_cond_pay = !empty($month_esc) ? "AND DATE_FORMAT(p.tanggal, '%Y-%m') = '$month_esc'" : '';

// Check if migration columns exist
$col_check = mysqli_query($conn, "SHOW COLUMNS FROM spk_items LIKE 'harga_satuan'");
$has_price_cols = mysqli_num_rows($col_check) > 0;

// =================== SUMMARY CARDS ===================

// 1. Total Revenue Jasa
$q = "SELECT COALESCE(SUM(ss.subtotal), 0) as total
      FROM spk_services ss
      JOIN invoices i ON i.spk_id = ss.spk_id
      WHERE i.status_piutang = 'Lunas' $month_cond";
$revenue_jasa = (float)mysqli_fetch_assoc(mysqli_query($conn, $q))['total'];

// 2. Total Revenue Sparepart
if ($has_price_cols) {
    $q = "SELECT COALESCE(SUM(COALESCE(NULLIF(si.harga_satuan, 0), sp.harga_jual_default) * si.qty), 0) as total
          FROM spk_items si
          JOIN spareparts sp ON si.sparepart_id = sp.id
          JOIN invoices i ON i.spk_id = si.spk_id
          WHERE i.status_piutang = 'Lunas' $month_cond";
} else {
    $q = "SELECT COALESCE(SUM(sp.harga_jual_default * si.qty), 0) as total
          FROM spk_items si
          JOIN spareparts sp ON si.sparepart_id = sp.id
          JOIN invoices i ON i.spk_id = si.spk_id
          WHERE i.status_piutang = 'Lunas' $month_cond";
}
$revenue_spare = (float)mysqli_fetch_assoc(mysqli_query($conn, $q))['total'];

// 3. Total HPP Sparepart
if ($has_price_cols) {
    $q = "SELECT COALESCE(SUM(si.hpp_satuan * si.qty), 0) as total
          FROM spk_items si
          JOIN invoices i ON i.spk_id = si.spk_id
          WHERE i.status_piutang = 'Lunas' $month_cond";
} else {
    $q = "SELECT COALESCE(SUM(sp.harga_beli_default * si.qty), 0) as total
          FROM spk_items si
          JOIN spareparts sp ON si.sparepart_id = sp.id
          JOIN invoices i ON i.spk_id = si.spk_id
          WHERE i.status_piutang = 'Lunas' $month_cond";
}
$hpp_spare = (float)mysqli_fetch_assoc(mysqli_query($conn, $q))['total'];

// 4. Profit Sparepart
$profit_spare = $revenue_spare - $hpp_spare;

// 5. Cashflow Masuk (from payments)
$q = "SELECT COALESCE(SUM(p.amount), 0) as total
      FROM payments p
      WHERE 1=1 $month_cond_pay";
$cashflow_masuk = (float)mysqli_fetch_assoc(mysqli_query($conn, $q))['total'];

// =================== MONTHLY CHART (last 12 months, always all-time) ===================
$chart_months = [];
for ($i = 11; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i months"));
    $chart_months[$m] = [
        'bulan' => $m,
        'label' => date('M Y', strtotime($m . '-01')),
        'revenue_jasa' => 0,
        'revenue_spare' => 0,
        'hpp_spare' => 0,
        'profit' => 0
    ];
}

$oldest_month = date('Y-m', strtotime('-11 months'));

// Monthly revenue jasa
$q = "SELECT DATE_FORMAT(i.tanggal, '%Y-%m') as bulan, COALESCE(SUM(ss.subtotal), 0) as total
      FROM spk_services ss
      JOIN invoices i ON i.spk_id = ss.spk_id
      WHERE i.status_piutang = 'Lunas'
      AND DATE_FORMAT(i.tanggal, '%Y-%m') >= '$oldest_month'
      GROUP BY DATE_FORMAT(i.tanggal, '%Y-%m')";
$res = mysqli_query($conn, $q);
while ($row = mysqli_fetch_assoc($res)) {
    if (isset($chart_months[$row['bulan']])) {
        $chart_months[$row['bulan']]['revenue_jasa'] = (float)$row['total'];
    }
}

// Monthly revenue & hpp sparepart
if ($has_price_cols) {
    $q = "SELECT DATE_FORMAT(i.tanggal, '%Y-%m') as bulan,
                 COALESCE(SUM(COALESCE(NULLIF(si.harga_satuan, 0), sp.harga_jual_default) * si.qty), 0) as revenue,
                 COALESCE(SUM(si.hpp_satuan * si.qty), 0) as hpp
          FROM spk_items si
          JOIN spareparts sp ON si.sparepart_id = sp.id
          JOIN invoices i ON i.spk_id = si.spk_id
          WHERE i.status_piutang = 'Lunas'
          AND DATE_FORMAT(i.tanggal, '%Y-%m') >= '$oldest_month'
          GROUP BY DATE_FORMAT(i.tanggal, '%Y-%m')";
} else {
    $q = "SELECT DATE_FORMAT(i.tanggal, '%Y-%m') as bulan,
                 COALESCE(SUM(sp.harga_jual_default * si.qty), 0) as revenue,
                 COALESCE(SUM(sp.harga_beli_default * si.qty), 0) as hpp
          FROM spk_items si
          JOIN spareparts sp ON si.sparepart_id = sp.id
          JOIN invoices i ON i.spk_id = si.spk_id
          WHERE i.status_piutang = 'Lunas'
          AND DATE_FORMAT(i.tanggal, '%Y-%m') >= '$oldest_month'
          GROUP BY DATE_FORMAT(i.tanggal, '%Y-%m')";
}
$res = mysqli_query($conn, $q);
while ($row = mysqli_fetch_assoc($res)) {
    if (isset($chart_months[$row['bulan']])) {
        $chart_months[$row['bulan']]['revenue_spare'] = (float)$row['revenue'];
        $chart_months[$row['bulan']]['hpp_spare'] = (float)$row['hpp'];
    }
}

// Calculate profit per month
foreach ($chart_months as &$m_data) {
    $m_data['profit'] = $m_data['revenue_jasa'] + $m_data['revenue_spare'] - $m_data['hpp_spare'];
}
unset($m_data);

// =================== TOP SPAREPART BY PROFIT ===================
if ($has_price_cols) {
    $q = "SELECT sp.kode_sparepart, sp.nama,
                 SUM(si.qty) as total_qty,
                 SUM(COALESCE(NULLIF(si.harga_satuan, 0), sp.harga_jual_default) * si.qty) as total_revenue,
                 SUM(si.hpp_satuan * si.qty) as total_hpp,
                 SUM((COALESCE(NULLIF(si.harga_satuan, 0), sp.harga_jual_default) - si.hpp_satuan) * si.qty) as total_profit
          FROM spk_items si
          JOIN spareparts sp ON si.sparepart_id = sp.id
          JOIN invoices i ON i.spk_id = si.spk_id
          WHERE i.status_piutang = 'Lunas' $month_cond
          GROUP BY si.sparepart_id, sp.kode_sparepart, sp.nama
          ORDER BY total_profit DESC
          LIMIT 10";
} else {
    $q = "SELECT sp.kode_sparepart, sp.nama,
                 SUM(si.qty) as total_qty,
                 SUM(sp.harga_jual_default * si.qty) as total_revenue,
                 SUM(sp.harga_beli_default * si.qty) as total_hpp,
                 SUM((sp.harga_jual_default - sp.harga_beli_default) * si.qty) as total_profit
          FROM spk_items si
          JOIN spareparts sp ON si.sparepart_id = sp.id
          JOIN invoices i ON i.spk_id = si.spk_id
          WHERE i.status_piutang = 'Lunas' $month_cond
          GROUP BY si.sparepart_id, sp.kode_sparepart, sp.nama
          ORDER BY total_profit DESC
          LIMIT 10";
}
$top_spareparts = [];
$res = mysqli_query($conn, $q);
while ($row = mysqli_fetch_assoc($res)) {
    $top_spareparts[] = $row;
}

// =================== RECENT TRANSACTIONS ===================
$cond_recent = !empty($month_esc) ? "WHERE DATE_FORMAT(i.tanggal, '%Y-%m') = '$month_esc'" : "";
$q = "SELECT i.id, i.tanggal, i.total, i.status_piutang,
             s.kode_unik_reference, c.name as customer_name, v.nomor_polisi, v.model
      FROM invoices i
      JOIN spk s ON i.spk_id = s.id
      JOIN customers c ON s.customer_id = c.id
      JOIN vehicles v ON s.vehicle_id = v.id
      $cond_recent
      ORDER BY i.id DESC LIMIT 10";
$recent = [];
$res = mysqli_query($conn, $q);
while ($row = mysqli_fetch_assoc($res)) {
    $recent[] = $row;
}

echo json_encode([
    'success' => true,
    'summary' => [
        'revenue_jasa'    => $revenue_jasa,
        'revenue_spare'   => $revenue_spare,
        'hpp_spare'       => $hpp_spare,
        'profit_spare'    => $profit_spare,
        'cashflow_masuk'  => $cashflow_masuk,
        'total_revenue'   => $revenue_jasa + $revenue_spare,
        'total_profit'    => $revenue_jasa + $profit_spare,
    ],
    'monthly'       => array_values($chart_months),
    'top_spareparts' => $top_spareparts,
    'recent'        => $recent,
]);
?>
