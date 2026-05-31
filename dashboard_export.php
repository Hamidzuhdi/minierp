<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$export_type = $_GET['export_type'] ?? 'json'; // json or excel

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
    } else {
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
            if (is_numeric($value) && (strpos($col, 'total') !== false || strpos($col, 'harga') !== false || strpos($col, 'qty') !== false || strpos($col, 'sisa') !== false || strpos($col, 'sudah') !== false)) {
                $value = number_format($value, 0, ',', '.');
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
?>

