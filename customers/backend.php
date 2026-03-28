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
    
    // Data kendaraan (opsional)
    $nomor_polisi = !empty($_POST['nomor_polisi']) ? strtoupper(trim($_POST['nomor_polisi'])) : '';
    $merk = trim($_POST['merk'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $tahun = !empty($_POST['tahun']) ? (int)$_POST['tahun'] : null;
    $note = trim($_POST['note'] ?? '');
    
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Nama customer harus diisi']);
        exit;
    }
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Insert customer
        $sql = "INSERT INTO customers (name, phone, address) 
                VALUES ('" . mysqli_real_escape_string($conn, $name) . "',
                        '" . mysqli_real_escape_string($conn, $phone) . "',
                        '" . mysqli_real_escape_string($conn, $address) . "')";
        
        if (!mysqli_query($conn, $sql)) {
            throw new Exception('Gagal menambahkan customer: ' . mysqli_error($conn));
        }
        
        $customer_id = mysqli_insert_id($conn);
        
        // Insert vehicle jika ada nomor polisi
        if (!empty($nomor_polisi)) {
            // Cek nomor polisi sudah ada
            $check = mysqli_query($conn, "SELECT id FROM vehicles WHERE nomor_polisi = '" . mysqli_real_escape_string($conn, $nomor_polisi) . "'");
            if (mysqli_num_rows($check) > 0) {
                throw new Exception('Nomor polisi sudah terdaftar');
            }
            
            $sql_vehicle = "INSERT INTO vehicles (customer_id, nomor_polisi, merk, model, tahun, note) 
                            VALUES ($customer_id,
                                    '" . mysqli_real_escape_string($conn, $nomor_polisi) . "',
                                    '" . mysqli_real_escape_string($conn, $merk) . "',
                                    '" . mysqli_real_escape_string($conn, $model) . "',
                                    " . ($tahun ? $tahun : 'NULL') . ",
                                    '" . mysqli_real_escape_string($conn, $note) . "')";
            
            if (!mysqli_query($conn, $sql_vehicle)) {
                throw new Exception('Gagal menambahkan kendaraan: ' . mysqli_error($conn));
            }
        }
        
        mysqli_commit($conn);
        $message = 'Customer berhasil ditambahkan';
        if (!empty($nomor_polisi)) {
            $message .= ' beserta kendaraan';
        }
        echo json_encode(['success' => true, 'message' => $message]);
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// READ - Ambil Semua Customer
elseif ($action === 'read') {
    $search = $_GET['search'] ?? '';
    $reminder = $_GET['reminder'] ?? '0';
    
    $sql = "SELECT c.id, c.name, c.phone, c.address, c.created_at FROM customers c";
    $conditions = [];
    
    if (!empty($search)) {
        $search = mysqli_real_escape_string($conn, $search);
        $conditions[] = "(c.name LIKE '%$search%' OR c.phone LIKE '%$search%')";
    }

    if ($reminder === '1') {
        $conditions[] = "EXISTS (
            SELECT 1
            FROM spk s
            WHERE s.customer_id = c.id
              AND LOWER(COALESCE(s.status_spk, '')) <> 'dibatalkan'
              AND DATE_ADD(DATE(s.created_at), INTERVAL 2 MONTH) <= CURDATE()
        )";
    }

    if (count($conditions) > 0) {
        $sql .= " WHERE " . implode(' AND ', $conditions);
    }
    
    $sql .= " ORDER BY c.id DESC";
    
    $result = mysqli_query($conn, $sql);
    
    $customers = [];
    while ($row = mysqli_fetch_assoc($result)) {
        // Get vehicles for this customer
        $customer_id = $row['id'];
        $vehicles_sql = "SELECT id, nomor_polisi, merk, model, tahun FROM vehicles WHERE customer_id = $customer_id ORDER BY created_at DESC";
        $vehicles_result = mysqli_query($conn, $vehicles_sql);
        
        $vehicles = [];
        while ($vehicle = mysqli_fetch_assoc($vehicles_result)) {
            $vehicles[] = $vehicle;
        }
        
        $row['vehicles'] = $vehicles;
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

// READ ONE WITH VEHICLES - Ambil Detail Customer + Kendaraan
elseif ($action === 'read_one_with_vehicles') {
    $id = (int)$_GET['id'];
    
    // Get customer data
    $sql = "SELECT id, name, phone, address, created_at FROM customers WHERE id = $id";
    $result = mysqli_query($conn, $sql);
    
    if ($customer = mysqli_fetch_assoc($result)) {
        // Get vehicles
        $sql_vehicles = "SELECT id, nomor_polisi, merk, model, tahun, note FROM vehicles WHERE customer_id = $id ORDER BY created_at DESC";
        $result_vehicles = mysqli_query($conn, $sql_vehicles);
        
        $vehicles = [];
        while ($row = mysqli_fetch_assoc($result_vehicles)) {
            $vehicles[] = $row;
        }
        
        echo json_encode([
            'success' => true, 
            'data' => [
                'customer' => $customer,
                'vehicles' => $vehicles
            ]
        ]);
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

// ADD VEHICLE - Tambah Kendaraan untuk Customer Existing
elseif ($action === 'add_vehicle') {
    $customer_id = (int)$_POST['customer_id'];
    $nomor_polisi = strtoupper(trim($_POST['nomor_polisi']));
    $merk = trim($_POST['merk'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $tahun = !empty($_POST['tahun']) ? (int)$_POST['tahun'] : null;
    $note = trim($_POST['note'] ?? '');
    
    if (empty($nomor_polisi)) {
        echo json_encode(['success' => false, 'message' => 'Nomor polisi harus diisi']);
        exit;
    }
    
    // Cek nomor polisi sudah ada
    $check = mysqli_query($conn, "SELECT id FROM vehicles WHERE nomor_polisi = '" . mysqli_real_escape_string($conn, $nomor_polisi) . "'");
    if (mysqli_num_rows($check) > 0) {
        echo json_encode(['success' => false, 'message' => 'Nomor polisi sudah terdaftar']);
        exit;
    }
    
    $sql = "INSERT INTO vehicles (customer_id, nomor_polisi, merk, model, tahun, note) 
            VALUES ($customer_id,
                    '" . mysqli_real_escape_string($conn, $nomor_polisi) . "',
                    '" . mysqli_real_escape_string($conn, $merk) . "',
                    '" . mysqli_real_escape_string($conn, $model) . "',
                    " . ($tahun ? $tahun : 'NULL') . ",
                    '" . mysqli_real_escape_string($conn, $note) . "')";
    
    if (mysqli_query($conn, $sql)) {
        echo json_encode(['success' => true, 'message' => 'Kendaraan berhasil ditambahkan']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menambahkan kendaraan: ' . mysqli_error($conn)]);
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
