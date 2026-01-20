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

// CREATE - Buat SPK Baru
if ($action === 'create') {
    $customer_id = (int)$_POST['customer_id'];
    $vehicle_id = (int)$_POST['vehicle_id'];
    $tanggal = $_POST['tanggal'];
    $keluhan_customer = trim($_POST['keluhan_customer']);
    
    // Generate kode unik reference
    $prefix = 'SPK';
    $date_code = date('Ymd');
    
    // Cari nomor urut hari ini
    $check = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM spk WHERE DATE(tanggal) = '$tanggal'");
    $row = mysqli_fetch_assoc($check);
    $urutan = $row['cnt'] + 1;
    
    $kode_unik_reference = $prefix . '-' . $date_code . '-' . str_pad($urutan, 4, '0', STR_PAD_LEFT);
    
    $sql = "INSERT INTO spk (kode_unik_reference, customer_id, vehicle_id, tanggal, keluhan_customer, status_spk) 
            VALUES ('" . mysqli_real_escape_string($conn, $kode_unik_reference) . "',
                    $customer_id,
                    $vehicle_id,
                    '" . mysqli_real_escape_string($conn, $tanggal) . "',
                    '" . mysqli_real_escape_string($conn, $keluhan_customer) . "',
                    'Menunggu Konfirmasi')";
    
    if (mysqli_query($conn, $sql)) {
        $spk_id = mysqli_insert_id($conn);
        echo json_encode([
            'success' => true, 
            'message' => 'SPK berhasil dibuat',
            'kode_unik' => $kode_unik_reference,
            'spk_id' => $spk_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal membuat SPK: ' . mysqli_error($conn)]);
    }
}

// READ - Ambil Semua SPK
elseif ($action === 'read') {
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? '';
    
    $sql = "SELECT s.*, 
            c.name as customer_name, c.phone as customer_phone,
            v.nomor_polisi, v.merk, v.model
            FROM spk s
            JOIN customers c ON s.customer_id = c.id
            JOIN vehicles v ON s.vehicle_id = v.id";
    
    $conditions = [];
    
    if (!empty($search)) {
        $search = mysqli_real_escape_string($conn, $search);
        $conditions[] = "(s.kode_unik_reference LIKE '%$search%' OR c.name LIKE '%$search%' OR v.nomor_polisi LIKE '%$search%')";
    }
    
    if (!empty($status)) {
        $status = mysqli_real_escape_string($conn, $status);
        $conditions[] = "s.status_spk = '$status'";
    }
    
    if (count($conditions) > 0) {
        $sql .= " WHERE " . implode(' AND ', $conditions);
    }
    
    $sql .= " ORDER BY s.id DESC";
    
    $result = mysqli_query($conn, $sql);
    
    $spks = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $spks[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $spks]);
}

// READ ONE - Detail SPK dengan Items
elseif ($action === 'read_one') {
    $id = (int)$_GET['id'];
    
    $sql = "SELECT s.*, 
            c.name as customer_name, c.phone as customer_phone, c.address as customer_address,
            v.nomor_polisi, v.merk, v.model, v.tahun
            FROM spk s
            JOIN customers c ON s.customer_id = c.id
            JOIN vehicles v ON s.vehicle_id = v.id
            WHERE s.id = $id";
    
    $result = mysqli_query($conn, $sql);
    
    if ($row = mysqli_fetch_assoc($result)) {
        // Get SPK items
        $sql_items = "SELECT si.*, sp.nama as sparepart_name, sp.satuan, sp.harga_jual_default,
                      (si.qty * sp.harga_jual_default) as subtotal
                      FROM spk_items si
                      JOIN spareparts sp ON si.sparepart_id = sp.id
                      WHERE si.spk_id = $id";
        $result_items = mysqli_query($conn, $sql_items);
        
        $items = [];
        while ($item = mysqli_fetch_assoc($result_items)) {
            $items[] = $item;
        }
        
        $row['items'] = $items;
        
        // Get warehouse requests
        $sql_warehouse = "SELECT wo.*, sp.nama as sparepart_name, u.username as requested_by_name
                         FROM warehouse_out wo
                         JOIN spareparts sp ON wo.sparepart_id = sp.id
                         LEFT JOIN users u ON wo.requested_by = u.id
                         WHERE wo.spk_id = $id
                         ORDER BY wo.id DESC";
        $result_warehouse = mysqli_query($conn, $sql_warehouse);
        
        $warehouse_requests = [];
        while ($wr = mysqli_fetch_assoc($result_warehouse)) {
            $warehouse_requests[] = $wr;
        }
        
        $row['warehouse_requests'] = $warehouse_requests;
        
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'SPK tidak ditemukan']);
    }
}

// GET CUSTOMERS - Untuk dropdown
elseif ($action === 'get_customers') {
    $sql = "SELECT id, name, phone FROM customers ORDER BY name ASC";
    $result = mysqli_query($conn, $sql);
    
    $customers = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $customers[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $customers]);
}

// GET VEHICLES BY CUSTOMER
elseif ($action === 'get_vehicles') {
    $customer_id = (int)$_GET['customer_id'];
    
    $sql = "SELECT id, nomor_polisi, merk, model, tahun 
            FROM vehicles 
            WHERE customer_id = $customer_id 
            ORDER BY nomor_polisi ASC";
    $result = mysqli_query($conn, $sql);
    
    $vehicles = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $vehicles[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $vehicles]);
}

// UPDATE - Mekanik Input Analisa & Estimasi
elseif ($action === 'update_analisa') {
    $id = (int)$_POST['id'];
    $analisa_mekanik = trim($_POST['analisa_mekanik']);
    $service_description = trim($_POST['service_description']);
    $biaya_jasa = (float)$_POST['biaya_jasa'];
    $saran_service = trim($_POST['saran_service']);
    
    $sql = "UPDATE spk SET 
            analisa_mekanik = '" . mysqli_real_escape_string($conn, $analisa_mekanik) . "',
            service_description = '" . mysqli_real_escape_string($conn, $service_description) . "',
            biaya_jasa = $biaya_jasa,
            saran_service = '" . mysqli_real_escape_string($conn, $saran_service) . "'
            WHERE id = $id";
    
    if (mysqli_query($conn, $sql)) {
        echo json_encode(['success' => true, 'message' => 'Analisa & estimasi berhasil disimpan']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan: ' . mysqli_error($conn)]);
    }
}

// UPDATE STATUS SPK
elseif ($action === 'update_status') {
    $id = (int)$_POST['id'];
    $status = $_POST['status'];
    
    // Validasi status
    $valid_statuses = ['Menunggu Konfirmasi', 'Disetujui', 'Dalam Pengerjaan', 'Selesai', 'Dikirim ke owner', 'Buat Invoice', 'Sudah Cetak Invoice'];
    if (!in_array($status, $valid_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Status tidak valid']);
        exit;
    }
    
    $sql = "UPDATE spk SET status_spk = '" . mysqli_real_escape_string($conn, $status) . "' WHERE id = $id";
    
    if (mysqli_query($conn, $sql)) {
        echo json_encode(['success' => true, 'message' => 'Status SPK berhasil diupdate']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal update status: ' . mysqli_error($conn)]);
    }
}

// ADD SPAREPART TO SPK (dari warehouse approved)
elseif ($action === 'add_sparepart') {
    $spk_id = (int)$_POST['spk_id'];
    $sparepart_id = (int)$_POST['sparepart_id'];
    $qty = (int)$_POST['qty'];
    $harga_satuan = (float)$_POST['harga_satuan'];
    
    $sql = "INSERT INTO spk_items (spk_id, sparepart_id, qty, harga_satuan) 
            VALUES ($spk_id, $sparepart_id, $qty, $harga_satuan)";
    
    if (mysqli_query($conn, $sql)) {
        echo json_encode(['success' => true, 'message' => 'Sparepart berhasil ditambahkan ke SPK']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menambahkan sparepart: ' . mysqli_error($conn)]);
    }
}

// DELETE SPK (hanya jika belum ada invoice)
elseif ($action === 'delete') {
    $id = (int)$_POST['id'];
    
    // Cek apakah sudah ada invoice
    $check = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM invoices WHERE spk_id = $id");
    $row = mysqli_fetch_assoc($check);
    
    if ($row['cnt'] > 0) {
        echo json_encode(['success' => false, 'message' => 'SPK tidak dapat dihapus karena sudah ada invoice']);
        exit;
    }
    
    // Delete related data
    mysqli_query($conn, "DELETE FROM spk_items WHERE spk_id = $id");
    mysqli_query($conn, "DELETE FROM warehouse_out WHERE spk_id = $id");
    
    $sql = "DELETE FROM spk WHERE id = $id";
    
    if (mysqli_query($conn, $sql)) {
        echo json_encode(['success' => true, 'message' => 'SPK berhasil dihapus']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus SPK: ' . mysqli_error($conn)]);
    }
}

else {
    echo json_encode(['success' => false, 'message' => 'Action tidak valid']);
}
?>
