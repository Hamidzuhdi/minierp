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

// CREATE - Tambah Purchase Baru
if ($action === 'create') {
    $supplier = trim($_POST['supplier']);
    $tanggal = $_POST['tanggal'];
    $items = json_decode($_POST['items'], true);
    
    if (empty($supplier) || empty($tanggal) || empty($items)) {
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
        exit;
    }
    
    // Hitung total
    $total = 0;
    foreach ($items as $item) {
        $total += $item['qty'] * $item['harga_beli'];
    }
    
    // Insert purchase header
    $sql = "INSERT INTO purchases (supplier, tanggal, total, status, is_paid, created_by) 
            VALUES ('" . mysqli_real_escape_string($conn, $supplier) . "',
                    '" . mysqli_real_escape_string($conn, $tanggal) . "',
                    $total,
                    'Pending Approval',
                    'Belum Bayar',
                    " . $_SESSION['user_id'] . ")";
    
    if (mysqli_query($conn, $sql)) {
        $purchase_id = mysqli_insert_id($conn);
        
        // Insert purchase items
        foreach ($items as $item) {
            $subtotal = $item['qty'] * $item['harga_beli'];
            $barcode = isset($item['barcode']) ? "'" . mysqli_real_escape_string($conn, $item['barcode']) . "'" : 'NULL';
            
            $sql_item = "INSERT INTO purchase_items (purchase_id, sparepart_id, qty, harga_beli, barcode) 
                        VALUES ($purchase_id, 
                                " . (int)$item['sparepart_id'] . ",
                                " . (int)$item['qty'] . ",
                                " . (float)$item['harga_beli'] . ",
                                $barcode)";
            mysqli_query($conn, $sql_item);
        }
        
        echo json_encode(['success' => true, 'message' => 'Purchase berhasil ditambahkan', 'purchase_id' => $purchase_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menambahkan purchase: ' . mysqli_error($conn)]);
    }
}

// READ - Ambil Semua Purchase
elseif ($action === 'read') {
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? '';
    
    $sql = "SELECT p.*, u.username as created_by_name 
            FROM purchases p
            LEFT JOIN users u ON p.created_by = u.id";
    
    $conditions = [];
    
    if (!empty($search)) {
        $search = mysqli_real_escape_string($conn, $search);
        $conditions[] = "(p.supplier LIKE '%$search%' OR p.id LIKE '%$search%')";
    }
    
    if (!empty($status)) {
        $status = mysqli_real_escape_string($conn, $status);
        $conditions[] = "p.status = '$status'";
    }
    
    if (count($conditions) > 0) {
        $sql .= " WHERE " . implode(' AND ', $conditions);
    }
    
    $sql .= " ORDER BY p.id DESC";
    
    $result = mysqli_query($conn, $sql);
    
    $purchases = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $purchases[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $purchases]);
}

// READ ONE - Ambil Detail Purchase dengan Items
elseif ($action === 'read_one') {
    $id = (int)$_GET['id'];
    
    // Get purchase header
    $sql = "SELECT p.*, u.username as created_by_name 
            FROM purchases p
            LEFT JOIN users u ON p.created_by = u.id
            WHERE p.id = $id";
    $result = mysqli_query($conn, $sql);
    
    if ($row = mysqli_fetch_assoc($result)) {
        // Get purchase items
        $sql_items = "SELECT pi.*, s.nama as sparepart_name, s.satuan
                      FROM purchase_items pi
                      JOIN spareparts s ON pi.sparepart_id = s.id
                      WHERE pi.purchase_id = $id";
        $result_items = mysqli_query($conn, $sql_items);
        
        $items = [];
        while ($item = mysqli_fetch_assoc($result_items)) {
            $items[] = $item;
        }
        
        $row['items'] = $items;
        
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Purchase tidak ditemukan']);
    }
}

// GET SPAREPARTS - Untuk dropdown
elseif ($action === 'get_spareparts') {
    $sql = "SELECT id, nama, satuan, harga_beli_default, current_stock FROM spareparts ORDER BY nama ASC";
    $result = mysqli_query($conn, $sql);
    
    $spareparts = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $spareparts[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $spareparts]);
}

// UPDATE STATUS - Approve/Refund dengan update stock
elseif ($action === 'update_status') {
    $id = (int)$_POST['id'];
    $new_status = $_POST['status'];
    
    // Validasi status
    if (!in_array($new_status, ['Pending Approval', 'Approved', 'Refund'])) {
        echo json_encode(['success' => false, 'message' => 'Status tidak valid']);
        exit;
    }
    
    // Get current purchase data
    $sql = "SELECT status FROM purchases WHERE id = $id";
    $result = mysqli_query($conn, $sql);
    $purchase = mysqli_fetch_assoc($result);
    $old_status = $purchase['status'];
    
    // Get purchase items
    $sql_items = "SELECT sparepart_id, qty FROM purchase_items WHERE purchase_id = $id";
    $result_items = mysqli_query($conn, $sql_items);
    $items = [];
    while ($item = mysqli_fetch_assoc($result_items)) {
        $items[] = $item;
    }
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Update purchase status
        $sql_update = "UPDATE purchases SET status = '" . mysqli_real_escape_string($conn, $new_status) . "' WHERE id = $id";
        mysqli_query($conn, $sql_update);
        
        // Update stock based on status change
        if ($new_status === 'Approved' && $old_status !== 'Approved') {
            // APPROVE: Tambah stock
            foreach ($items as $item) {
                $sql_stock = "UPDATE spareparts 
                             SET current_stock = current_stock + " . (int)$item['qty'] . " 
                             WHERE id = " . (int)$item['sparepart_id'];
                mysqli_query($conn, $sql_stock);
            }
            $message = 'Purchase berhasil di-approve dan stock telah ditambahkan';
        }
        elseif ($new_status === 'Refund' && $old_status === 'Approved') {
            // REFUND dari Approved: Kurangi stock
            foreach ($items as $item) {
                $sql_stock = "UPDATE spareparts 
                             SET current_stock = current_stock - " . (int)$item['qty'] . " 
                             WHERE id = " . (int)$item['sparepart_id'];
                mysqli_query($conn, $sql_stock);
            }
            $message = 'Purchase berhasil di-refund dan stock telah dikurangi';
        }
        elseif ($new_status === 'Pending Approval' && $old_status === 'Approved') {
            // Batalkan Approve: Kurangi stock
            foreach ($items as $item) {
                $sql_stock = "UPDATE spareparts 
                             SET current_stock = current_stock - " . (int)$item['qty'] . " 
                             WHERE id = " . (int)$item['sparepart_id'];
                mysqli_query($conn, $sql_stock);
            }
            $message = 'Status berhasil diubah dan stock telah disesuaikan';
        }
        else {
            $message = 'Status berhasil diupdate';
        }
        
        mysqli_commit($conn);
        echo json_encode(['success' => true, 'message' => $message]);
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['success' => false, 'message' => 'Gagal update status: ' . $e->getMessage()]);
    }
}

// UPDATE PAYMENT STATUS
elseif ($action === 'update_payment') {
    $id = (int)$_POST['id'];
    $is_paid = $_POST['is_paid'];
    
    $sql = "UPDATE purchases SET is_paid = '" . mysqli_real_escape_string($conn, $is_paid) . "' WHERE id = $id";
    
    if (mysqli_query($conn, $sql)) {
        echo json_encode(['success' => true, 'message' => 'Status pembayaran berhasil diupdate']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal update status pembayaran']);
    }
}

// DELETE - Hapus Purchase (hanya jika Pending)
elseif ($action === 'delete') {
    $id = (int)$_POST['id'];
    
    // Cek status
    $check = mysqli_query($conn, "SELECT status FROM purchases WHERE id = $id");
    $purchase = mysqli_fetch_assoc($check);
    
    if ($purchase['status'] === 'Approved') {
        echo json_encode(['success' => false, 'message' => 'Purchase yang sudah di-approve tidak dapat dihapus. Gunakan Refund.']);
        exit;
    }
    
    // Delete purchase items first
    mysqli_query($conn, "DELETE FROM purchase_items WHERE purchase_id = $id");
    
    // Delete purchase
    $sql = "DELETE FROM purchases WHERE id = $id";
    
    if (mysqli_query($conn, $sql)) {
        echo json_encode(['success' => true, 'message' => 'Purchase berhasil dihapus']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus purchase: ' . mysqli_error($conn)]);
    }
}

else {
    echo json_encode(['success' => false, 'message' => 'Action tidak valid']);
}
?>
