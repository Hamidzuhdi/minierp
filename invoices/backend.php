<?php
session_start();
require_once '../config.php';

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user_role = $_SESSION['role'] ?? 'Admin';

// CREATE INVOICE - Hanya Owner yang bisa buat invoice
if ($action === 'create_invoice') {
    if ($user_role !== 'Owner') {
        echo json_encode(['success' => false, 'message' => 'Hanya Owner yang bisa membuat invoice']);
        exit;
    }
    
    $spk_id = (int)$_POST['spk_id'];
    
    // Validasi SPK status harus "Sudah Cetak Invoice"
    $sql_check = "SELECT s.*, s.status_spk, c.name as customer_name, v.nomor_polisi
                  FROM spk s
                  JOIN customers c ON s.customer_id = c.id
                  JOIN vehicles v ON s.vehicle_id = v.id
                  WHERE s.id = $spk_id";
    $result = mysqli_query($conn, $sql_check);
    $spk = mysqli_fetch_assoc($result);
    
    if (!$spk) {
        echo json_encode(['success' => false, 'message' => 'SPK tidak ditemukan']);
        exit;
    }
    
    if ($spk['status_spk'] !== 'Sudah Cetak Invoice') {
        echo json_encode(['success' => false, 'message' => 'Invoice hanya bisa dibuat untuk SPK dengan status "Sudah Cetak Invoice"']);
        exit;
    }
    
    // Cek apakah SPK sudah punya invoice
    $check_invoice = mysqli_query($conn, "SELECT id FROM invoices WHERE spk_id = $spk_id");
    if (mysqli_num_rows($check_invoice) > 0) {
        echo json_encode(['success' => false, 'message' => 'SPK ini sudah memiliki invoice']);
        exit;
    }
    
    // Hitung total sparepart dari spk_items (menggunakan harga_jual_default)
    $sql_items = "SELECT SUM(si.qty * sp.harga_jual_default) as biaya_sparepart
                  FROM spk_items si
                  JOIN spareparts sp ON si.sparepart_id = sp.id
                  WHERE si.spk_id = $spk_id";
    $result_items = mysqli_query($conn, $sql_items);
    $items_data = mysqli_fetch_assoc($result_items);
    $biaya_sparepart = (float)($items_data['biaya_sparepart'] ?? 0);
    
    // Hitung total jasa dari spk_services
    $sql_services = "SELECT SUM(subtotal) as biaya_jasa
                     FROM spk_services
                     WHERE spk_id = $spk_id";
    $result_services = mysqli_query($conn, $sql_services);
    $services_data = mysqli_fetch_assoc($result_services);
    $biaya_jasa = (float)($services_data['biaya_jasa'] ?? 0);
    
    $total = $biaya_sparepart + $biaya_jasa;
    
    // Generate no_invoice unik
    $prefix = 'INV';
    $date_code = date('Ymd');
    $check = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM invoices WHERE DATE(tanggal) = CURDATE()");
    $row_count = mysqli_fetch_assoc($check);
    $urutan = $row_count['cnt'] + 1;
    $no_invoice = $prefix . '-' . $date_code . '-' . str_pad($urutan, 4, '0', STR_PAD_LEFT);
    
    // Insert invoice
    $sql = "INSERT INTO invoices (spk_id, no_invoice, tanggal, biaya_jasa, biaya_sparepart, total, status_piutang, metode_pembayaran, created_at)
            VALUES ($spk_id, '$no_invoice', CURDATE(), $biaya_jasa, $biaya_sparepart, $total, 'Belum Bayar', 'cash', NOW())";
    
    if (mysqli_query($conn, $sql)) {
        $invoice_id = mysqli_insert_id($conn);
        
        // Audit log
        $log_msg = "Invoice #{$no_invoice} dibuat untuk SPK #{$spk_id} - {$spk['customer_name']} ({$spk['nomor_polisi']})";
        $sql_log = "INSERT INTO audit_logs (user_id, action, target_table, target_id, description, created_at)
                    VALUES ({$_SESSION['user_id']}, 'CREATE', 'invoices', $invoice_id, '" . mysqli_real_escape_string($conn, $log_msg) . "', NOW())";
        mysqli_query($conn, $sql_log);
        
        echo json_encode(['success' => true, 'message' => 'Invoice berhasil dibuat', 'invoice_id' => $invoice_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal membuat invoice: ' . mysqli_error($conn)]);
    }
}

// READ - Ambil semua invoice
elseif ($action === 'read') {
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? '';
    
    $sql = "SELECT i.*, 
            s.kode_unik_reference as spk_code,
            c.name as customer_name, c.phone as customer_phone,
            v.nomor_polisi, v.merk, v.model
            FROM invoices i
            JOIN spk s ON i.spk_id = s.id
            JOIN customers c ON s.customer_id = c.id
            JOIN vehicles v ON s.vehicle_id = v.id";
    
    $conditions = [];
    
    if (!empty($search)) {
        $search = mysqli_real_escape_string($conn, $search);
        $conditions[] = "(i.id LIKE '%$search%' OR s.kode_unik_reference LIKE '%$search%' OR c.name LIKE '%$search%' OR v.nomor_polisi LIKE '%$search%')";
    }
    
    if (!empty($status)) {
        $status = mysqli_real_escape_string($conn, $status);
        $conditions[] = "i.status_piutang = '$status'";
    }
    
    if (count($conditions) > 0) {
        $sql .= " WHERE " . implode(' AND ', $conditions);
    }
    
    $sql .= " ORDER BY i.id DESC";
    
    $result = mysqli_query($conn, $sql);
    $invoices = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        // Hitung total pembayaran
        $sql_payments = "SELECT COALESCE(SUM(amount), 0) as total_paid FROM payments WHERE invoice_id = {$row['id']}";
        $result_payments = mysqli_query($conn, $sql_payments);
        $payments_data = mysqli_fetch_assoc($result_payments);
        $row['total_paid'] = (float)$payments_data['total_paid'];
        $row['sisa_piutang'] = $row['total'] - $row['total_paid'];
        
        $invoices[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $invoices]);
}

// READ ONE - Detail invoice dengan items dan payments
elseif ($action === 'read_one') {
    $id = (int)$_GET['id'];
    
    // Get invoice header
    $sql = "SELECT i.*, 
            s.kode_unik_reference as spk_code, s.tanggal as spk_tanggal, s.keluhan_customer,
            c.name as customer_name, c.phone as customer_phone, c.address as customer_address,
            v.nomor_polisi, v.merk, v.model, v.tahun
            FROM invoices i
            JOIN spk s ON i.spk_id = s.id
            JOIN customers c ON s.customer_id = c.id
            JOIN vehicles v ON s.vehicle_id = v.id
            WHERE i.id = $id";
    
    $result = mysqli_query($conn, $sql);
    
    if ($row = mysqli_fetch_assoc($result)) {
        // Get services
        $sql_services = "SELECT ss.*, sp.kode_jasa, sp.nama_jasa, sp.kategori
                         FROM spk_services ss
                         JOIN service_prices sp ON ss.service_price_id = sp.id
                         WHERE ss.spk_id = {$row['spk_id']}";
        $result_services = mysqli_query($conn, $sql_services);
        
        $services = [];
        while ($service = mysqli_fetch_assoc($result_services)) {
            $services[] = $service;
        }
        $row['services'] = $services;
        
        // Get sparepart items
        $sql_items = "SELECT si.*, sp.nama as sparepart_name, sp.satuan, sp.harga_jual_default,
                      (si.qty * sp.harga_jual_default) as subtotal
                      FROM spk_items si
                      JOIN spareparts sp ON si.sparepart_id = sp.id
                      WHERE si.spk_id = {$row['spk_id']}";
        $result_items = mysqli_query($conn, $sql_items);
        
        $items = [];
        while ($item = mysqli_fetch_assoc($result_items)) {
            $items[] = $item;
        }
        $row['items'] = $items;
        
        // Get payments history
        $sql_payments = "SELECT p.*
                        FROM payments p
                        WHERE p.invoice_id = $id
                        ORDER BY p.tanggal DESC, p.id DESC";
        $result_payments = mysqli_query($conn, $sql_payments);
        
        $payments = [];
        $total_paid = 0;
        while ($payment = mysqli_fetch_assoc($result_payments)) {
            $total_paid += (float)$payment['amount'];
            $payments[] = $payment;
        }
        $row['payments'] = $payments;
        $row['total_paid'] = $total_paid;
        $row['sisa_piutang'] = $row['total'] - $total_paid;
        
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invoice tidak ditemukan']);
    }
}

// CREATE PAYMENT - Input cicilan baru
elseif ($action === 'create_payment') {
    if ($user_role !== 'Owner') {
        echo json_encode(['success' => false, 'message' => 'Hanya Owner yang bisa input pembayaran']);
        exit;
    }
    
    $invoice_id = (int)$_POST['invoice_id'];
    $amount = (float)$_POST['amount'];
    $tanggal = $_POST['payment_date'];
    $method = mysqli_real_escape_string($conn, $_POST['payment_method']);
    $note = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');
    
    if ($amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Jumlah pembayaran harus lebih dari 0']);
        exit;
    }
    
    // Get invoice data
    $sql_invoice = "SELECT total, status_piutang FROM invoices WHERE id = $invoice_id";
    $result_invoice = mysqli_query($conn, $sql_invoice);
    $invoice = mysqli_fetch_assoc($result_invoice);
    
    if (!$invoice) {
        echo json_encode(['success' => false, 'message' => 'Invoice tidak ditemukan']);
        exit;
    }
    
    // Hitung total pembayaran sebelumnya
    $sql_paid = "SELECT COALESCE(SUM(amount), 0) as total_paid FROM payments WHERE invoice_id = $invoice_id";
    $result_paid = mysqli_query($conn, $sql_paid);
    $paid_data = mysqli_fetch_assoc($result_paid);
    $total_paid = (float)$paid_data['total_paid'];
    
    $sisa = $invoice['total'] - $total_paid;
    
    if ($amount > $sisa) {
        echo json_encode(['success' => false, 'message' => "Jumlah pembayaran melebihi sisa piutang (Rp " . number_format($sisa, 0, ',', '.') . ")"]);
        exit;
    }
    
    // Insert payment
    $sql = "INSERT INTO payments (invoice_id, amount, tanggal, method, note, created_at)
            VALUES ($invoice_id, $amount, '$tanggal', '$method', '$note', NOW())";
    
    if (mysqli_query($conn, $sql)) {
        $payment_id = mysqli_insert_id($conn);
        
        // Update total pembayaran
        $new_total_paid = $total_paid + $amount;
        $new_sisa = $invoice['total'] - $new_total_paid;
        
        // Update status piutang
        $new_status = 'Belum Bayar';
        $paid_at = 'NULL';
        
        if ($new_sisa <= 0) {
            $new_status = 'Lunas';
            $paid_at = "'$tanggal'";
        } elseif ($new_total_paid > 0) {
            $new_status = 'Sudah Dicicil';
        }
        
        $sql_update = "UPDATE invoices SET status_piutang = '$new_status', paid_at = $paid_at WHERE id = $invoice_id";
        mysqli_query($conn, $sql_update);
        
        // Audit log
        $log_msg = "Pembayaran Rp " . number_format($amount, 0, ',', '.') . " untuk Invoice #$invoice_id via $method";
        $sql_log = "INSERT INTO audit_logs (user_id, action, target_table, target_id, description, created_at)
                    VALUES ({$_SESSION['user_id']}, 'CREATE', 'payments', $payment_id, '" . mysqli_real_escape_string($conn, $log_msg) . "', NOW())";
        mysqli_query($conn, $sql_log);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Pembayaran berhasil dicatat',
            'new_status' => $new_status,
            'new_total_paid' => $new_total_paid,
            'new_sisa' => $new_sisa
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal mencatat pembayaran: ' . mysqli_error($conn)]);
    }
}

// DELETE PAYMENT - Hapus cicilan (hanya yang terakhir)
elseif ($action === 'delete_payment') {
    if ($user_role !== 'Owner') {
        echo json_encode(['success' => false, 'message' => 'Hanya Owner yang bisa hapus pembayaran']);
        exit;
    }
    
    $payment_id = (int)$_POST['payment_id'];
    
    // Get payment data
    $sql_payment = "SELECT invoice_id, amount FROM payments WHERE id = $payment_id";
    $result = mysqli_query($conn, $sql_payment);
    $payment = mysqli_fetch_assoc($result);
    
    if (!$payment) {
        echo json_encode(['success' => false, 'message' => 'Pembayaran tidak ditemukan']);
        exit;
    }
    
    $invoice_id = $payment['invoice_id'];
    $amount = (float)$payment['amount'];
    
    // Delete payment
    $sql = "DELETE FROM payments WHERE id = $payment_id";
    
    if (mysqli_query($conn, $sql)) {
        // Hitung ulang total pembayaran
        $sql_paid = "SELECT COALESCE(SUM(amount), 0) as total_paid FROM payments WHERE invoice_id = $invoice_id";
        $result_paid = mysqli_query($conn, $sql_paid);
        $paid_data = mysqli_fetch_assoc($result_paid);
        $total_paid = (float)$paid_data['total_paid'];
        
        // Get total
        $sql_invoice = "SELECT total FROM invoices WHERE id = $invoice_id";
        $result_invoice = mysqli_query($conn, $sql_invoice);
        $invoice = mysqli_fetch_assoc($result_invoice);
        
        $sisa = $invoice['total'] - $total_paid;
        
        // Update status piutang
        $new_status = 'Belum Bayar';
        if ($sisa <= 0) {
            $new_status = 'Lunas';
        } elseif ($total_paid > 0) {
            $new_status = 'Sudah Dicicil';
        }
        
        $sql_update = "UPDATE invoices SET status_piutang = '$new_status', paid_at = NULL WHERE id = $invoice_id";
        mysqli_query($conn, $sql_update);
        
        // Audit log
        $log_msg = "Pembayaran Rp " . number_format($amount, 0, ',', '.') . " dihapus dari Invoice #$invoice_id";
        $sql_log = "INSERT INTO audit_logs (user_id, action, target_table, target_id, description, created_at)
                    VALUES ({$_SESSION['user_id']}, 'DELETE', 'payments', $payment_id, '" . mysqli_real_escape_string($conn, $log_msg) . "', NOW())";
        mysqli_query($conn, $sql_log);
        
        echo json_encode(['success' => true, 'message' => 'Pembayaran berhasil dihapus']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus pembayaran: ' . mysqli_error($conn)]);
    }
}

// AUTO CREATE INVOICES - Create invoices for all SPK ready
elseif ($action === 'auto_create_invoices') {
    if ($user_role !== 'Owner') {
        echo json_encode(['success' => false, 'message' => 'Hanya Owner yang bisa membuat invoice']);
        exit;
    }
    
    // Get all SPK with status "Sudah Cetak Invoice" yang belum punya invoice
    $sql = "SELECT s.id FROM spk s
            WHERE s.status_spk = 'Sudah Cetak Invoice'
            AND NOT EXISTS (SELECT 1 FROM invoices i WHERE i.spk_id = s.id)";
    $result = mysqli_query($conn, $sql);
    
    $created_count = 0;
    $errors = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $spk_id = $row['id'];
        
        // Hitung total sparepart
        $sql_items = "SELECT SUM(si.qty * sp.harga_jual_default) as biaya_sparepart
                      FROM spk_items si
                      JOIN spareparts sp ON si.sparepart_id = sp.id
                      WHERE si.spk_id = $spk_id";
        $result_items = mysqli_query($conn, $sql_items);
        $items_data = mysqli_fetch_assoc($result_items);
        $biaya_sparepart = (float)($items_data['biaya_sparepart'] ?? 0);
        
        // Hitung total jasa
        $sql_services = "SELECT SUM(subtotal) as biaya_jasa
                         FROM spk_services
                         WHERE spk_id = $spk_id";
        $result_services = mysqli_query($conn, $sql_services);
        $services_data = mysqli_fetch_assoc($result_services);
        $biaya_jasa = (float)($services_data['biaya_jasa'] ?? 0);
        
        $total = $biaya_sparepart + $biaya_jasa;
        
        // Generate no_invoice unik
        $prefix = 'INV';
        $date_code = date('Ymd');
        $check = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM invoices WHERE DATE(tanggal) = CURDATE()");
        $row_count = mysqli_fetch_assoc($check);
        $urutan = $row_count['cnt'] + 1 + $created_count;
        $no_invoice = $prefix . '-' . $date_code . '-' . str_pad($urutan, 4, '0', STR_PAD_LEFT);
        
        // Insert invoice
        $sql_insert = "INSERT INTO invoices (spk_id, no_invoice, tanggal, biaya_jasa, biaya_sparepart, total, status_piutang, metode_pembayaran, created_at)
                VALUES ($spk_id, '$no_invoice', CURDATE(), $biaya_jasa, $biaya_sparepart, $total, 'Belum Bayar', 'cash', NOW())";
        
        if (mysqli_query($conn, $sql_insert)) {
            $created_count++;
        } else {
            $errors[] = "SPK ID $spk_id: " . mysqli_error($conn);
        }
    }
    
    if ($created_count > 0) {
        $msg = "Berhasil membuat $created_count invoice otomatis";
        if (count($errors) > 0) {
            $msg .= " (dengan " . count($errors) . " error)";
        }
        echo json_encode(['success' => true, 'message' => $msg, 'created' => $created_count, 'errors' => $errors]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Tidak ada SPK yang siap dibuatkan invoice atau semua sudah punya invoice']);
    }
}

// GET SPK READY FOR INVOICE - SPK yang bisa dibuatkan invoice
elseif ($action === 'get_spk_ready') {
    // SPK dengan status "Sudah Cetak Invoice" yang belum punya invoice
    $sql = "SELECT s.id, s.kode_unik_reference, s.tanggal,
            c.name as customer_name, v.nomor_polisi
            FROM spk s
            JOIN customers c ON s.customer_id = c.id
            JOIN vehicles v ON s.vehicle_id = v.id
            WHERE s.status_spk = 'Sudah Cetak Invoice'
            AND s.id NOT IN (SELECT spk_id FROM invoices)
            ORDER BY s.id DESC";
    
    $result = mysqli_query($conn, $sql);
    $spks = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $spks[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $spks]);
}

else {
    echo json_encode(['success' => false, 'message' => 'Action tidak valid']);
}
?>
