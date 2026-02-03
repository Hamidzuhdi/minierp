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
    $supplier = trim($_POST['supplier'] ?? '');
    $tanggal = $_POST['tanggal'];
    $items_json = $_POST['items'];
    $items = json_decode($items_json, true);
    
    // Cek role user
    $user_role = $_SESSION['role'] ?? 'Admin';
    $is_owner = ($user_role === 'Owner');
    
    // Validasi: Owner wajib isi supplier
    if ($is_owner && empty($supplier)) {
        echo json_encode(['success' => false, 'message' => 'Supplier wajib diisi']);
        exit;
    }
    
    if (empty($tanggal) || empty($items)) {
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
        exit;
    }
    
    // Set supplier default jika kosong (dari Admin)
    if (empty($supplier)) {
        $supplier = 'Pending - Akan diisi Owner';
    }
    
    // Hitung total
    $total = 0;
    foreach ($items as &$item) {
        // Jika bukan owner, gunakan harga_beli_default dari database
        if (!$is_owner) {
            $sql_price = "SELECT harga_beli_default FROM spareparts WHERE id = " . (int)$item['sparepart_id'];
            $result_price = mysqli_query($conn, $sql_price);
            if ($row_price = mysqli_fetch_assoc($result_price)) {
                $item['harga_beli'] = (float)$row_price['harga_beli_default'];
            } else {
                $item['harga_beli'] = 0;
            }
        }
        $total += $item['qty'] * $item['harga_beli'];
    }
    unset($item); // CRITICAL: Break the reference to avoid bugs in next foreach
    
    
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
        foreach ($items as $index => $item) {
            $subtotal = $item['qty'] * $item['harga_beli'];
            $barcode = isset($item['barcode']) ? "'" . mysqli_real_escape_string($conn, $item['barcode']) . "'" : 'NULL';
            
            $sql_item = "INSERT INTO purchase_items (purchase_id, sparepart_id, qty, harga_beli, barcode) 
                        VALUES ($purchase_id, 
                                " . (int)$item['sparepart_id'] . ",
                                " . (int)$item['qty'] . ",
                                " . (float)$item['harga_beli'] . ",
                                $barcode)";
            
            if (!mysqli_query($conn, $sql_item)) {
                error_log("Purchase item insert error: " . mysqli_error($conn));
            }
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Purchase berhasil ditambahkan', 
            'purchase_id' => $purchase_id
        ]);
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

// UPDATE PURCHASE - Owner isi supplier dan harga
elseif ($action === 'update_purchase') {
    $purchase_id = (int)$_POST['purchase_id'];
    $supplier = trim($_POST['supplier']);
    
    // Cek role harus owner
    $user_role = $_SESSION['role'] ?? 'Admin';
    if ($user_role !== 'Owner') {
        echo json_encode(['success' => false, 'message' => 'Hanya Owner yang bisa update purchase ini']);
        exit;
    }
    
    if (empty($supplier)) {
        echo json_encode(['success' => false, 'message' => 'Supplier wajib diisi']);
        exit;
    }
    
    // Get items from POST
    $items = $_POST['items'] ?? [];
    if (empty($items)) {
        echo json_encode(['success' => false, 'message' => 'Items tidak boleh kosong']);
        exit;
    }
    
    // Hitung total baru
    $total = 0;
    foreach ($items as $item) {
        $total += (int)$item['qty'] * (float)$item['harga_beli'];
    }
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Update purchase header
        $sql_update = "UPDATE purchases SET 
                      supplier = '" . mysqli_real_escape_string($conn, $supplier) . "',
                      total = $total
                      WHERE id = $purchase_id";
        mysqli_query($conn, $sql_update);
        
        // Update purchase items harga
        foreach ($items as $item) {
            $sql_item = "UPDATE purchase_items SET 
                        harga_beli = " . (float)$item['harga_beli'] . "
                        WHERE purchase_id = $purchase_id 
                        AND sparepart_id = " . (int)$item['sparepart_id'];
            mysqli_query($conn, $sql_item);
        }
        
        mysqli_commit($conn);
        echo json_encode(['success' => true, 'message' => 'Purchase berhasil diupdate']);
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['success' => false, 'message' => 'Gagal update purchase: ' . $e->getMessage()]);
    }
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
            // APPROVE: Tambah stock dan update harga beli dengan moving average
            foreach ($items as $item) {
                // Get purchase item dengan harga beli
                $sql_get_price = "SELECT harga_beli FROM purchase_items 
                                  WHERE purchase_id = $id AND sparepart_id = " . (int)$item['sparepart_id'];
                $result_price = mysqli_query($conn, $sql_get_price);
                $price_data = mysqli_fetch_assoc($result_price);
                $harga_beli_baru = (float)$price_data['harga_beli'];
                
                // Get current stock dan harga beli lama
                $sql_get_current = "SELECT current_stock, harga_beli_default 
                                   FROM spareparts WHERE id = " . (int)$item['sparepart_id'];
                $result_current = mysqli_query($conn, $sql_get_current);
                $current_data = mysqli_fetch_assoc($result_current);
                $qty_lama = (int)$current_data['current_stock'];
                $harga_lama = (float)$current_data['harga_beli_default'];
                $qty_baru = (int)$item['qty'];
                
                // Hitung moving average
                // Formula: (qty_lama × harga_lama) + (qty_baru × harga_beli_baru) / (qty_lama + qty_baru)
                if (($qty_lama + $qty_baru) > 0) {
                    $moving_average = (($qty_lama * $harga_lama) + ($qty_baru * $harga_beli_baru)) / ($qty_lama + $qty_baru);
                } else {
                    $moving_average = $harga_beli_baru;
                }
                
                // Update stock dan harga beli
                $sql_stock = "UPDATE spareparts 
                             SET current_stock = current_stock + $qty_baru,
                                 harga_beli_default = $moving_average
                             WHERE id = " . (int)$item['sparepart_id'];
                mysqli_query($conn, $sql_stock);
            }
            $message = 'Purchase berhasil di-approve, stock dan harga beli telah diupdate';
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
