<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// CREATE - Request Sparepart Keluar
if ($action === 'create') {
    $sparepart_id = (int)$_POST['sparepart_id'];
    $spk_id = isset($_POST['spk_id']) && !empty($_POST['spk_id']) ? (int)$_POST['spk_id'] : null;
    $qty = (int)$_POST['qty'];
    $scanned_barcode = trim($_POST['scanned_barcode']);
    $note = trim($_POST['note']);
    
    if (empty($sparepart_id) || $qty <= 0) {
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
        exit;
    }
    
    // Cek stock tersedia
    $check_stock = mysqli_query($conn, "SELECT current_stock, nama FROM spareparts WHERE id = $sparepart_id");
    $sparepart = mysqli_fetch_assoc($check_stock);
    
    if (!$sparepart) {
        echo json_encode(['success' => false, 'message' => 'Sparepart tidak ditemukan']);
        exit;
    }
    
    if ($sparepart['current_stock'] < $qty) {
        echo json_encode(['success' => false, 'message' => "Stock tidak cukup! Tersedia: {$sparepart['current_stock']}, Diminta: $qty"]);
        exit;
    }
    
    $sql = "INSERT INTO warehouse_out (sparepart_id, spk_id, qty, requested_by, scanned_barcode, status, note) 
            VALUES ($sparepart_id,
                    " . ($spk_id ? $spk_id : 'NULL') . ",
                    $qty,
                    " . $_SESSION['user_id'] . ",
                    " . (!empty($scanned_barcode) ? "'" . mysqli_real_escape_string($conn, $scanned_barcode) . "'" : 'NULL') . ",
                    'Pending',
                    '" . mysqli_real_escape_string($conn, $note) . "')";
    
    if (mysqli_query($conn, $sql)) {
        echo json_encode(['success' => true, 'message' => 'Request barang keluar berhasil dibuat']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal membuat request: ' . mysqli_error($conn)]);
    }
}

// READ - Ambil Semua Request
elseif ($action === 'read') {
    $status = $_GET['status'] ?? '';
    $spk_id = $_GET['spk_id'] ?? '';
    
    $sql = "SELECT wo.*, 
            sp.nama as sparepart_name, sp.satuan, sp.current_stock,
            u1.username as requested_by_name,
            u2.username as approved_by_name,
            s.kode_unik_reference
            FROM warehouse_out wo
            JOIN spareparts sp ON wo.sparepart_id = sp.id
            LEFT JOIN users u1 ON wo.requested_by = u1.id
            LEFT JOIN users u2 ON wo.approved_by = u2.id
            LEFT JOIN spk s ON wo.spk_id = s.id";
    
    $conditions = [];
    
    if (!empty($status)) {
        $status = mysqli_real_escape_string($conn, $status);
        $conditions[] = "wo.status = '$status'";
    }
    
    if (!empty($spk_id)) {
        $conditions[] = "wo.spk_id = " . (int)$spk_id;
    }
    
    if (count($conditions) > 0) {
        $sql .= " WHERE " . implode(' AND ', $conditions);
    }
    
    $sql .= " ORDER BY wo.id DESC";
    
    $result = mysqli_query($conn, $sql);
    
    $requests = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $requests[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $requests]);
}

// GET SPAREPARTS - Untuk dropdown
elseif ($action === 'get_spareparts') {
    $sql = "SELECT id, nama, barcode, satuan, current_stock, harga_jual_default 
            FROM spareparts 
            WHERE current_stock > 0 
            ORDER BY nama ASC";
    $result = mysqli_query($conn, $sql);
    
    $spareparts = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $spareparts[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $spareparts]);
}

// SEARCH BY BARCODE
elseif ($action === 'search_barcode') {
    $barcode = trim($_GET['barcode']);
    
    if (empty($barcode)) {
        echo json_encode(['success' => false, 'message' => 'Barcode tidak boleh kosong']);
        exit;
    }
    
    $sql = "SELECT id, nama, barcode, satuan, current_stock, harga_jual_default 
            FROM spareparts 
            WHERE barcode = '" . mysqli_real_escape_string($conn, $barcode) . "'";
    $result = mysqli_query($conn, $sql);
    
    if ($row = mysqli_fetch_assoc($result)) {
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Sparepart dengan barcode tersebut tidak ditemukan']);
    }
}

// APPROVE - Approve Request & Kurangi Stock & Tambah ke SPK Items
elseif ($action === 'approve') {
    $id = (int)$_POST['id'];
    
    mysqli_begin_transaction($conn);
    
    try {
        // Get request data
        $sql = "SELECT wo.*, sp.harga_jual_default 
                FROM warehouse_out wo
                JOIN spareparts sp ON wo.sparepart_id = sp.id
                WHERE wo.id = $id";
        $result = mysqli_query($conn, $sql);
        $request = mysqli_fetch_assoc($result);
        
        if (!$request) {
            throw new Exception('Request tidak ditemukan');
        }
        
        if ($request['status'] !== 'Pending') {
            throw new Exception('Request sudah diproses sebelumnya');
        }
        
        // Update warehouse_out status
        $sql_update = "UPDATE warehouse_out 
                      SET status = 'Approved',
                          approved_by = " . $_SESSION['user_id'] . ",
                          approved_at = NOW()
                      WHERE id = $id";
        mysqli_query($conn, $sql_update);
        
        // Kurangi stock
        $sql_stock = "UPDATE spareparts 
                     SET current_stock = current_stock - " . (int)$request['qty'] . "
                     WHERE id = " . (int)$request['sparepart_id'];
        mysqli_query($conn, $sql_stock);
        
        // Jika ada SPK, tambahkan ke spk_items
        if ($request['spk_id']) {
            // Cek apakah sudah ada di spk_items
            $check = mysqli_query($conn, "SELECT id, qty FROM spk_items 
                                          WHERE spk_id = {$request['spk_id']} 
                                          AND sparepart_id = {$request['sparepart_id']}");
            
            if (mysqli_num_rows($check) > 0) {
                // Update qty jika sudah ada
                $existing = mysqli_fetch_assoc($check);
                $new_qty = $existing['qty'] + $request['qty'];
                mysqli_query($conn, "UPDATE spk_items 
                                    SET qty = $new_qty 
                                    WHERE id = {$existing['id']}");
            } else {
                // Insert baru
                $sql_spk_item = "INSERT INTO spk_items (spk_id, sparepart_id, qty, harga_satuan)
                                VALUES ({$request['spk_id']},
                                        {$request['sparepart_id']},
                                        {$request['qty']},
                                        {$request['harga_jual_default']})";
                mysqli_query($conn, $sql_spk_item);
            }
        }
        
        mysqli_commit($conn);
        echo json_encode(['success' => true, 'message' => 'Request di-approve, stock berkurang, dan sparepart ditambahkan ke SPK']);
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

// REJECT - Tolak Request
elseif ($action === 'reject') {
    $id = (int)$_POST['id'];
    $note = trim($_POST['note']);
    
    $sql = "UPDATE warehouse_out 
            SET status = 'Rejected',
                approved_by = " . $_SESSION['user_id'] . ",
                approved_at = NOW(),
                note = CONCAT(note, ' | REJECTED: " . mysqli_real_escape_string($conn, $note) . "')
            WHERE id = $id";
    
    if (mysqli_query($conn, $sql)) {
        echo json_encode(['success' => true, 'message' => 'Request ditolak']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menolak request']);
    }
}

// DELETE - Hapus Request (hanya jika Pending)
elseif ($action === 'delete') {
    $id = (int)$_POST['id'];
    
    $check = mysqli_query($conn, "SELECT status FROM warehouse_out WHERE id = $id");
    $request = mysqli_fetch_assoc($check);
    
    if ($request['status'] !== 'Pending') {
        echo json_encode(['success' => false, 'message' => 'Hanya request Pending yang bisa dihapus']);
        exit;
    }
    
    $sql = "DELETE FROM warehouse_out WHERE id = $id";
    
    if (mysqli_query($conn, $sql)) {
        echo json_encode(['success' => true, 'message' => 'Request berhasil dihapus']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus request']);
    }
}

else {
    echo json_encode(['success' => false, 'message' => 'Action tidak valid']);
}
?>
