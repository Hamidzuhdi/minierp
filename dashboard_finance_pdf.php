<?php
session_start();
require_once 'config.php';
require_once 'finance_helper.php';
require_once 'vendor/autoload.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Owner') {
    die('Unauthorized');
}

finance_ensure_default_accounts($conn);

$month = trim($_GET['month'] ?? '');
$month_esc = !empty($month) ? mysqli_real_escape_string($conn, $month) : '';
$month_filter = !empty($month_esc) ? "AND DATE_FORMAT(ft.tanggal, '%Y-%m') = '$month_esc'" : '';
$month_filter_invoice = !empty($month_esc) ? "AND DATE_FORMAT(i.tanggal, '%Y-%m') = '$month_esc'" : '';

$qIn = "SELECT COALESCE(SUM(amount), 0) total FROM finance_transactions ft WHERE ft.direction = 'in' $month_filter";
$qOut = "SELECT COALESCE(SUM(amount), 0) total FROM finance_transactions ft WHERE ft.direction = 'out' $month_filter";
$qPo = "SELECT COALESCE(SUM(amount), 0) total FROM finance_transactions ft WHERE ft.direction = 'out' AND ft.category = 'OUT-PO' $month_filter";
$qOps = "SELECT COALESCE(SUM(amount), 0) total FROM finance_transactions ft WHERE ft.direction = 'out' AND ft.reference_type = 'operational' $month_filter";
$qSpk = "SELECT COALESCE(SUM(amount), 0) total FROM finance_transactions ft WHERE ft.direction = 'in' AND ft.reference_type = 'invoice' $month_filter";

$total_in = (float)mysqli_fetch_assoc(mysqli_query($conn, $qIn))['total'];
$total_out = (float)mysqli_fetch_assoc(mysqli_query($conn, $qOut))['total'];
$po_out = (float)mysqli_fetch_assoc(mysqli_query($conn, $qPo))['total'];
$ops_out = (float)mysqli_fetch_assoc(mysqli_query($conn, $qOps))['total'];
$spk_in = (float)mysqli_fetch_assoc(mysqli_query($conn, $qSpk))['total'];
$net = $total_in - $total_out;

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

$qRecent = "SELECT ft.tanggal, ft.direction, ft.category, ft.reference_type, ft.reference_id, ft.note, ft.amount,
                   fa.name as account_name, COALESCE(ec.name, '') as expense_category_name
            FROM finance_transactions ft
            JOIN finance_accounts fa ON ft.account_id = fa.id
            LEFT JOIN expense_categories ec ON ft.category = ec.code
            WHERE 1=1 $month_filter
            ORDER BY ft.id DESC
            LIMIT 40";
$recent = [];
$resRecent = mysqli_query($conn, $qRecent);
while ($row = mysqli_fetch_assoc($resRecent)) {
    $recent[] = $row;
}

function rupiah($value)
{
    return 'Rp ' . number_format((float)$value, 0, ',', '.');
}

function category_label($code, $expenseName)
{
    if (!empty($expenseName)) return $expenseName;
    $labels = [
        'OUT-PO' => 'Pengeluaran PO',
        'IN-CUST-SPAREPART' => 'Pemasukan SPK Sparepart',
        'TRF-IN' => 'Transfer Masuk',
        'TRF-OUT' => 'Transfer Keluar',
    ];
    return $labels[$code] ?? $code;
}

$period_label = !empty($month_esc)
    ? date('F Y', strtotime($month_esc . '-01'))
    : 'Semua Waktu';
$generated_at = date('d-m-Y H:i:s');

$html = '
<style>
body { font-family: sans-serif; font-size: 10pt; color: #222; }
h2 { margin: 0 0 6px 0; color: #0b5ed7; }
.small { color: #666; font-size: 9pt; }
.summary-grid { width: 100%; border-collapse: collapse; margin-top: 12px; }
.summary-grid td { border: 1px solid #ddd; padding: 8px; width: 33.33%; vertical-align: top; }
.summary-title { color: #666; font-size: 8.5pt; margin-bottom: 4px; }
.summary-value { font-size: 12pt; font-weight: bold; }
.section-title { margin-top: 14px; font-size: 11pt; font-weight: bold; color: #333; }
table { width: 100%; border-collapse: collapse; margin-top: 6px; }
th, td { border: 1px solid #ddd; padding: 6px; }
th { background: #f5f5f5; text-align: left; }
.text-right { text-align: right; }
.text-center { text-align: center; }
.in { color: #198754; font-weight: bold; }
.out { color: #dc3545; font-weight: bold; }
</style>

<h2>Laporan Keuangan</h2>
<div class="small">Periode: ' . htmlspecialchars($period_label) . '</div>
<div class="small">Dicetak oleh: ' . htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Owner') . '</div>
<div class="small">Generated at: ' . $generated_at . '</div>

<table class="summary-grid">
    <tr>
        <td>
            <div class="summary-title">Cashflow Masuk</div>
            <div class="summary-value">' . rupiah($total_in) . '</div>
        </td>
        <td>
            <div class="summary-title">Cashflow Keluar</div>
            <div class="summary-value">' . rupiah($total_out) . '</div>
        </td>
        <td>
            <div class="summary-title">Net Cashflow</div>
            <div class="summary-value">' . rupiah($net) . '</div>
        </td>
    </tr>
    <tr>
        <td>
            <div class="summary-title">Pengeluaran PO</div>
            <div class="summary-value">' . rupiah($po_out) . '</div>
        </td>
        <td>
            <div class="summary-title">Biaya Operasional</div>
            <div class="summary-value">' . rupiah($ops_out) . '</div>
        </td>
        <td>
            <div class="summary-title">Saldo Akhir (Cash + Bank)</div>
            <div class="summary-value">' . rupiah($saldo_akhir) . '</div>
        </td>
    </tr>
    <tr>
        <td>
            <div class="summary-title">Pendapatan Sparepart (Lunas)</div>
            <div class="summary-value">' . rupiah($spare_revenue) . '</div>
        </td>
        <td>
            <div class="summary-title">HPP Sparepart (Lunas)</div>
            <div class="summary-value">' . rupiah($spare_hpp) . '</div>
        </td>
        <td>
            <div class="summary-title">Profit Sparepart (Setelah HPP)</div>
            <div class="summary-value">' . rupiah($spare_profit) . '</div>
        </td>
    </tr>
</table>

<div class="section-title">Top Kategori Pengeluaran</div>
<table>
    <thead>
        <tr>
            <th style="width:10%">No</th>
            <th>Kategori</th>
            <th style="width:25%" class="text-right">Total</th>
        </tr>
    </thead>
    <tbody>';

if (count($top_categories) === 0) {
    $html .= '<tr><td colspan="3" class="text-center">Belum ada data</td></tr>';
} else {
    $no = 1;
    foreach ($top_categories as $row) {
        $html .= '<tr>
            <td class="text-center">' . $no++ . '</td>
            <td>' . htmlspecialchars(category_label($row['category'], $row['category_name'])) . '</td>
            <td class="text-right">' . rupiah($row['total_out']) . '</td>
        </tr>';
    }
}

$html .= '
    </tbody>
</table>

<div class="section-title">Transaksi Keuangan Terbaru</div>
<table>
    <thead>
        <tr>
            <th style="width:14%">Tanggal</th>
            <th style="width:16%">Akun</th>
            <th style="width:18%">Kategori</th>
            <th style="width:16%">Ref</th>
            <th style="width:10%">Arah</th>
            <th style="width:26%">Catatan / Nominal</th>
        </tr>
    </thead>
    <tbody>';

if (count($recent) === 0) {
    $html .= '<tr><td colspan="6" class="text-center">Belum ada data</td></tr>';
} else {
    foreach ($recent as $row) {
        $direction = $row['direction'] === 'in' ? 'Masuk' : 'Keluar';
        $directionClass = $row['direction'] === 'in' ? 'in' : 'out';
        $ref = ($row['reference_type'] ?: '-') . (!empty($row['reference_id']) ? ('#' . $row['reference_id']) : '');
        $note = trim((string)$row['note']);
        $noteText = $note !== '' ? htmlspecialchars($note) : '-';

        $html .= '<tr>
            <td>' . htmlspecialchars($row['tanggal']) . '</td>
            <td>' . htmlspecialchars($row['account_name']) . '</td>
            <td>' . htmlspecialchars(category_label($row['category'], $row['expense_category_name'])) . '</td>
            <td>' . htmlspecialchars($ref) . '</td>
            <td class="text-center ' . $directionClass . '">' . $direction . '</td>
            <td>
                <div>' . $noteText . '</div>
                <div class="text-right ' . $directionClass . '">' . rupiah($row['amount']) . '</div>
            </td>
        </tr>';
    }
}

$html .= '
    </tbody>
</table>';

$filename = 'laporan_keuangan_' . (!empty($month_esc) ? str_replace('-', '', $month_esc) : 'all') . '_' . date('Ymd_His') . '.pdf';

$mpdf = new \Mpdf\Mpdf([
    'format' => 'A4',
    'orientation' => 'P',
    'margin_top' => 12,
    'margin_bottom' => 12,
    'margin_left' => 10,
    'margin_right' => 10,
]);
$mpdf->WriteHTML($html);
$mpdf->Output($filename, 'I');
exit;
