<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';
$export_type = $_GET['export_type'] ?? 'json'; // json, excel, or pdf

// ===== 1. JASA MEKANIK (Service/Labor) =====
if ($action === 'get_jasa_mekanik') {
    $date_from = $_GET['date_from'] ?? date('Y-m-01');
    $date_to = $_GET['date_to'] ?? date('Y-m-t');
    $view_type = $_GET['view_type'] ?? 'detail'; // detail or all
    
    $date_from = mysqli_real_escape_string($conn, $date_from);
    $date_to = mysqli_real_escape_string($conn, $date_to);
    
    if ($view_type === 'all') {
        // Aggregate by spk_id
        $sql = "SELECT 
                    s.id as spk_id,
                    s.kode_unik_reference,
                    DATE_FORMAT(s.tanggal, '%d-%m-%Y') as tanggal,
                    c.name as customer_name,
                    v.nomor_polisi,
                    s.status_spk,
                    COALESCE(SUM(svc.subtotal), 0) as total_jasa
                FROM spk s
                LEFT JOIN customers c ON s.customer_id = c.id
                LEFT JOIN vehicles v ON s.vehicle_id = v.id
                LEFT JOIN spk_services svc ON s.id = svc.spk_id
                WHERE DATE(s.tanggal) BETWEEN '$date_from' AND '$date_to'
                  AND s.status_spk NOT IN ('Dibatalkan', 'Menunggu Konfirmasi')
                GROUP BY s.id, s.kode_unik_reference, s.tanggal, c.name, v.nomor_polisi, s.status_spk
                ORDER BY s.tanggal DESC";
    } else {
        // Detail view per service item
        $sql = "SELECT 
                    s.id as spk_id,
                    s.kode_unik_reference,
                    DATE_FORMAT(s.tanggal, '%d-%m-%Y') as tanggal,
                    c.name as customer_name,
                    v.nomor_polisi,
                    sp.nama_jasa as service_name,
                    svc.qty,
                    svc.harga,
                    COALESCE(svc.subtotal, 0) as total_jasa,
                    s.status_spk
                FROM spk s
                LEFT JOIN customers c ON s.customer_id = c.id
                LEFT JOIN vehicles v ON s.vehicle_id = v.id
                LEFT JOIN spk_services svc ON s.id = svc.spk_id
                LEFT JOIN service_prices sp ON svc.service_price_id = sp.id
                WHERE DATE(s.tanggal) BETWEEN '$date_from' AND '$date_to'
                  AND s.status_spk NOT IN ('Dibatalkan', 'Menunggu Konfirmasi')
                  AND svc.id IS NOT NULL
                ORDER BY s.tanggal DESC, s.id";
    }
    
    $result = mysqli_query($conn, $sql);
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    
    if ($export_type === 'excel') {
        exportToExcel($data, 'Jasa Mekanik', 'jasa_mekanik', $view_type === 'all' ? ['spk_id', 'kode_unik_reference', 'tanggal', 'customer_name', 'nomor_polisi', 'status_spk', 'total_jasa'] : ['spk_id', 'kode_unik_reference', 'tanggal', 'customer_name', 'nomor_polisi', 'service_name', 'qty', 'harga', 'total_jasa', 'status_spk']);
    } else {
        echo json_encode(['success' => true, 'data' => $data]);
    }
}

// ===== 2. BIAYA HPP SPAREPART =====
elseif ($action === 'get_biaya_hpp_sparepart') {
    $date_from = $_GET['date_from'] ?? date('Y-m-01');
    $date_to = $_GET['date_to'] ?? date('Y-m-t');
    
    $date_from = mysqli_real_escape_string($conn, $date_from);
    $date_to = mysqli_real_escape_string($conn, $date_to);
    
    $sql = "SELECT 
                s.id as spk_id,
                s.kode_unik_reference,
                DATE_FORMAT(s.tanggal, '%d-%m-%Y') as tanggal,
                c.name as customer_name,
                v.nomor_polisi,
                sp.kode_sparepart,
                sp.nama as sparepart_name,
                si.qty,
                si.hpp_satuan,
                (si.qty * COALESCE(si.hpp_satuan, sp.harga_beli_default, 0)) as hpp_total,
                s.status_spk
            FROM spk s
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN vehicles v ON s.vehicle_id = v.id
            LEFT JOIN spk_items si ON s.id = si.spk_id
            LEFT JOIN spareparts sp ON si.sparepart_id = sp.id
            WHERE DATE(s.tanggal) BETWEEN '$date_from' AND '$date_to'
              AND s.status_spk NOT IN ('Dibatalkan', 'Menunggu Konfirmasi')
              AND si.spk_id IS NOT NULL
            ORDER BY s.tanggal DESC";
    
    $result = mysqli_query($conn, $sql);
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    
    if ($export_type === 'excel') {
        exportToExcel($data, 'Biaya HPP Sparepart', 'hpp_sparepart', ['spk_id', 'kode_unik_reference', 'tanggal', 'customer_name', 'nomor_polisi', 'kode_sparepart', 'sparepart_name', 'qty', 'hpp_satuan', 'hpp_total', 'status_spk']);
    } else {
        echo json_encode(['success' => true, 'data' => $data]);
    }
}

// ===== 3. PIUTANG AKTIF (INVOICE BELUM LUNAS) =====
elseif ($action === 'get_piutang_aktif') {
    $date_from = $_GET['date_from'] ?? date('Y-m-01');
    $date_to = $_GET['date_to'] ?? date('Y-m-t');
    
    $date_from = mysqli_real_escape_string($conn, $date_from);
    $date_to = mysqli_real_escape_string($conn, $date_to);
    
    $sql = "SELECT 
                i.id as invoice_id,
                i.no_invoice,
                DATE_FORMAT(i.tanggal, '%d-%m-%Y') as tanggal,
                c.name as customer_name,
                i.total,
                COALESCE(p.total_bayar, 0) as sudah_bayar,
                (i.total - COALESCE(p.total_bayar, 0)) as sisa_piutang,
                i.status_piutang
            FROM invoices i
            JOIN spk s ON i.spk_id = s.id
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN (SELECT invoice_id, SUM(amount) as total_bayar FROM payments GROUP BY invoice_id) p ON i.id = p.invoice_id
            WHERE DATE(i.tanggal) BETWEEN '$date_from' AND '$date_to'
              AND i.status_piutang NOT IN ('Lunas', 'Tidak_Aktif')
            ORDER BY i.tanggal DESC";
    
    $result = mysqli_query($conn, $sql);
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    
    if ($export_type === 'excel') {
        exportToExcel($data, 'Piutang Aktif', 'piutang_aktif', ['invoice_id', 'no_invoice', 'tanggal', 'customer_name', 'total', 'sudah_bayar', 'sisa_piutang', 'status_piutang']);
    } elseif ($export_type === 'pdf') {
        require_once 'vendor/autoload.php';
        $html = '<h2>Invoice Belum Bayar</h2>';
        $html .= '<p>Tanggal Export: ' . date('d/m/Y H:i:s') . '</p>';
        $html .= '<table border="1" cellpadding="6" cellspacing="0" width="100%">';
        $html .= '<thead><tr style="background-color:#f0f0f0;"><th>ID Invoice</th><th>No Invoice</th><th>Tanggal</th><th>Customer</th><th>Total</th><th>Sudah Bayar</th><th>Sisa Piutang</th><th>Status</th></tr></thead>';
        $html .= '<tbody>';
        foreach ($data as $row) {
            $html .= '<tr>' .
                '<td>' . htmlspecialchars($row['invoice_id']) . '</td>' .
                '<td>' . htmlspecialchars($row['no_invoice']) . '</td>' .
                '<td>' . htmlspecialchars($row['tanggal']) . '</td>' .
                '<td>' . htmlspecialchars($row['customer_name']) . '</td>' .
                '<td>Rp ' . number_format((float)$row['total'], 0, ',', '.') . '</td>' .
                '<td>Rp ' . number_format((float)$row['sudah_bayar'], 0, ',', '.') . '</td>' .
                '<td>Rp ' . number_format((float)$row['sisa_piutang'], 0, ',', '.') . '</td>' .
                '<td>' . htmlspecialchars($row['status_piutang']) . '</td>' .
                '</tr>';
        }
        $html .= '</tbody></table>';

        $mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4', 'margin_left' => 10, 'margin_right' => 10, 'margin_top' => 10, 'margin_bottom' => 10]);
        $mpdf->WriteHTML($html);
        $filename = 'invoice_belum_bayar_' . date('Ymd_His') . '.pdf';
        $mpdf->Output($filename, 'D');
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $data]);
    }
}

// ===== HELPER FUNCTIONS =====

function exportToExcel($data, $sheet_name, $file_prefix, $columns) {
    if (empty($data)) {
        echo "No data to export";
        exit;
    }
    
    // Generate filename
    $filename = sanitizeFilename($file_prefix . '_' . date('YmdHis') . '.csv');
    
    // Start building CSV content
    $csv_lines = [];
    
    // Add headers
    $headers = [];
    foreach ($columns as $col) {
        $headers[] = convertColumnNameToLabel($col);
    }
    $csv_lines[] = csvEscapeLine($headers);
    
    // Add data rows
    foreach ($data as $row) {
        $csv_row = [];
        foreach ($columns as $col) {
            $value = $row[$col] ?? '';
            
            // Format numeric values
            if (is_numeric($value)) {
                $isMoneyCol = (strpos($col, 'total') !== false || strpos($col, 'harga') !== false || strpos($col, 'sisa') !== false || strpos($col, 'sudah') !== false || strpos($col, 'amount') !== false || strpos($col, 'hpp') !== false);
                $isQtyCol = (strpos($col, 'qty') !== false || strpos($col, 'stok') !== false || strpos($col, 'stock') !== false);
                if ($isMoneyCol) {
                    // Prefix "Rp" so Excel treats as text, not number (avoids 800.000 → 800 misparse)
                    $value = 'Rp ' . number_format((float)$value, 0, ',', '.');
                } elseif ($isQtyCol) {
                    $value = number_format((float)$value, 0, ',', '.');
                }
            }
            
            $csv_row[] = $value;
        }
        $csv_lines[] = csvEscapeLine($csv_row);
    }
    
    // Join lines
    $csv_content = "\xEF\xBB\xBF"; // UTF-8 BOM
    $csv_content .= implode("\r\n", $csv_lines);
    
    // Send headers for download
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . strlen($csv_content));
    
    echo $csv_content;
    exit;
}

function csvEscapeLine($fields) {
    $escaped = [];
    foreach ($fields as $field) {
        // Escape double quotes by doubling them
        $field = str_replace('"', '""', $field);
        // Quote fields that contain comma, newline, or quotes
        if (strpos($field, ',') !== false || strpos($field, '"') !== false || strpos($field, "\n") !== false) {
            $field = '"' . $field . '"';
        }
        $escaped[] = $field;
    }
    return implode(',', $escaped);
}

function sanitizeFilename($filename) {
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    return $filename;
}

function convertColumnNameToLabel($col) {
    // Convert snake_case or camelCase to readable label
    $col = str_replace(['_', 'Id', 'id'], ' ', $col);
    $col = ucwords($col);
    return $col;
}

// ===== HPP DETAIL (Keuntungan Sparepart) =====
if ($action === 'get_hpp_detail_json') {
    $month = trim($_GET['month'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        echo json_encode(['success' => false, 'data' => []]);
        exit;
    }
    $month_esc = mysqli_real_escape_string($conn, $month);

    // Per-sparepart profit summary for the month
    $sql = "SELECT
                sp.id, sp.nama as sparepart_name,
                SUM(si.qty) as total_qty,
                SUM(si.qty * si.hpp_satuan) as total_cost,
                SUM(si.qty * COALESCE(NULLIF(si.harga_satuan, 0), sp.harga_jual_default)) as total_revenue,
                SUM(si.qty * (COALESCE(NULLIF(si.harga_satuan, 0), sp.harga_jual_default) - si.hpp_satuan)) as total_profit
            FROM spk_items si
            JOIN spareparts sp ON si.sparepart_id = sp.id
            JOIN invoices i ON i.spk_id = si.spk_id
            WHERE i.status_piutang = 'Lunas'
              AND DATE_FORMAT(i.tanggal, '%Y-%m') = '$month_esc'
            GROUP BY sp.id, sp.nama
            ORDER BY total_profit DESC";

    $res = mysqli_query($conn, $sql);
    $data = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $data[] = $row;
    }
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

elseif ($action === 'get_hpp_detail_excel') {
    $month = trim($_GET['month'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        echo json_encode(['success' => false]);
        exit;
    }
    $month_esc = mysqli_real_escape_string($conn, $month);

    // Detailed list per SPK item
    $sql = "SELECT
                sp.nama as sparepart_name,
                s.kode_unik_reference as spk_code,
                si.qty,
                si.hpp_satuan as harga_beli,
                COALESCE(NULLIF(si.harga_satuan, 0), sp.harga_jual_default) as harga_jual,
                (si.qty * si.hpp_satuan) as total_cost,
                (si.qty * COALESCE(NULLIF(si.harga_satuan, 0), sp.harga_jual_default)) as total_revenue,
                (si.qty * (COALESCE(NULLIF(si.harga_satuan, 0), sp.harga_jual_default) - si.hpp_satuan)) as profit
            FROM spk_items si
            JOIN spareparts sp ON si.sparepart_id = sp.id
            JOIN spk s ON si.spk_id = s.id
            JOIN invoices i ON i.spk_id = s.id
            WHERE i.status_piutang = 'Lunas'
              AND DATE_FORMAT(i.tanggal, '%Y-%m') = '$month_esc'
            ORDER BY s.id DESC, sp.nama ASC";

    $res = mysqli_query($conn, $sql);

    $escapeCsvLine = function(array $fields): string {
        $escaped = array_map(function($f) {
            $f = str_replace('"', '""', (string)$f);
            if (preg_match('/[",\n]/', $f)) $f = '"' . $f . '"';
            return $f;
        }, $fields);
        return implode(',', $escaped);
    };

    $lines = [];
    $lines[] = $escapeCsvLine(['Sparepart', 'SPK', 'Qty', 'Harga Beli', 'Harga Jual', 'Total Cost', 'Total Revenue', 'Profit']);

    $totalProfit = 0;
    while ($row = mysqli_fetch_assoc($res)) {
        $totalProfit += (float)$row['profit'];
        $lines[] = $escapeCsvLine([
            $row['sparepart_name'],
            $row['spk_code'],
            (int)$row['qty'],
            'Rp ' . number_format((float)$row['harga_beli'], 0, ',', '.'),
            'Rp ' . number_format((float)$row['harga_jual'], 0, ',', '.'),
            'Rp ' . number_format((float)$row['total_cost'], 0, ',', '.'),
            'Rp ' . number_format((float)$row['total_revenue'], 0, ',', '.'),
            'Rp ' . number_format((float)$row['profit'], 0, ',', '.'),
        ]);
    }

    // Summary row
    $lines[] = '';
    $lines[] = $escapeCsvLine(['', '', '', '', 'TOTAL PROFIT:', '', '', 'Rp ' . number_format($totalProfit, 0, ',', '.')]);

    $csvContent = "\xEF\xBB\xBF" . implode("\r\n", $lines);
    $filename = 'keuntungan_sparepart_' . $month . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . strlen($csvContent));
    echo $csvContent;
    exit;
}
?>

