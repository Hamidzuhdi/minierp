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

// CREATE - Tambah Customer Baru
if ($action === 'create') {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Nama customer harus diisi']);
        exit;
    }
    
    $sql = "INSERT INTO customers (name, phone, address) 
            VALUES ('" . mysqli_real_escape_string($conn, $name) . "',
                    '" . mysqli_real_escape_string($conn, $phone) . "',
                    '" . mysqli_real_escape_string($conn, $address) . "')";
    
    if (mysqli_query($conn, $sql)) {
        echo json_encode(['success' => true, 'message' => 'Customer berhasil ditambahkan']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menambahkan customer: ' . mysqli_error($conn)]);
    }
}

// READ - Ambil Semua Customer
elseif ($action === 'read') {
    $search = $_GET['search'] ?? '';
    
    $sql = "SELECT id, name, phone, address, created_at FROM customers";
    
    if (!empty($search)) {
        $search = mysqli_real_escape_string($conn, $search);
        $sql .= " WHERE name LIKE '%$search%' OR phone LIKE '%$search%'";
    }
    
    $sql .= " ORDER BY id DESC";
    
    $result = mysqli_query($conn, $sql);
    
    $customers = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $customers[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $customers]);
}

// READ ONE - Ambil Detail Customer
elseif ($action === 'read_one') {
    $id = (int)$_GET['id'];
    
    $sql = "SELECT id, name, phone, address, created_at FROM customers WHERE id = $id";
    $result = mysqli_query($conn, $sql);
    
    if ($row = mysqli_fetch_assoc($result)) {
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Customer tidak ditemukan']);
    }
}

// UPDATE - Edit Customer
elseif ($action === 'update') {
    $id = (int)$_POST['id'];
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Nama customer harus diisi']);
        exit;
    }
    
    $sql = "UPDATE customers SET 
            name = '" . mysqli_real_escape_string($conn, $name) . "',
            phone = '" . mysqli_real_escape_string($conn, $phone) . "',
            address = '" . mysqli_real_escape_string($conn, $address) . "'
            WHERE id = $id";
    
    if (mysqli_query($conn, $sql)) {
        echo json_encode(['success' => true, 'message' => 'Customer berhasil diupdate']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal mengupdate customer: ' . mysqli_error($conn)]);
    }
}

// DELETE - Hapus Customer
elseif ($action === 'delete') {
    $id = (int)$_POST['id'];
    
    // Cek apakah ada kendaraan yang terkait
    $check = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM vehicles WHERE customer_id = $id");
    $row = mysqli_fetch_assoc($check);
    
    if ($row['cnt'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Customer tidak dapat dihapus karena memiliki data kendaraan']);
        exit;
    }
    
    $sql = "DELETE FROM customers WHERE id = $id";
    
    if (mysqli_query($conn, $sql)) {
        echo json_encode(['success' => true, 'message' => 'Customer berhasil dihapus']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus customer: ' . mysqli_error($conn)]);
    }
}

else {
    echo json_encode(['success' => false, 'message' => 'Action tidak valid']);
}
?>
