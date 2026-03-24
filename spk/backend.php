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

// Support both legacy `stok` and new `current_stock` column names.
$stock_col_check = mysqli_query($conn, "SHOW COLUMNS FROM spareparts LIKE 'current_stock'");
$sparepart_stock_col = (mysqli_num_rows($stock_col_check) > 0) ? 'current_stock' : 'stok';

// Ensure status_spk enum includes Dibatalkan.
$status_col_res = mysqli_query($conn, "SHOW COLUMNS FROM spk LIKE 'status_spk'");
if ($status_col_res && ($status_col = mysqli_fetch_assoc($status_col_res))) {
    $status_type = (string)($status_col['Type'] ?? '');
    if (stripos($status_type, "'Dibatalkan'") === false) {
        @mysqli_query(
            $conn,
            "ALTER TABLE spk MODIFY COLUMN status_spk ENUM('Menunggu Konfirmasi','Disetujui','Dalam Pengerjaan','Selesai','Dikirim ke Owner','Buat Invoice','Sudah Cetak Invoice','Dibatalkan') DEFAULT 'Menunggu Konfirmasi'"
        );
    }
}

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
    $vehicle_id = $_GET['vehicle_id'] ?? '';
    
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
    
    if (!empty($vehicle_id)) {
        $vehicle_id = (int)$vehicle_id;
        $conditions[] = "s.vehicle_id = $vehicle_id";
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
        // Check if harga_satuan column exists after migration
        $col_check = mysqli_query($conn, "SHOW COLUMNS FROM spk_items LIKE 'harga_satuan'");
        $has_price_cols = mysqli_num_rows($col_check) > 0;
        
        if ($has_price_cols) {
            $sql_items = "SELECT si.*, sp.nama as sparepart_name, sp.satuan, sp.harga_jual_default,
                          COALESCE(NULLIF(si.harga_satuan, 0), sp.harga_jual_default) as harga_satuan_eff,
                          (si.qty * COALESCE(NULLIF(si.harga_satuan, 0), sp.harga_jual_default)) as subtotal
                          FROM spk_items si
                          JOIN spareparts sp ON si.sparepart_id = sp.id
                          WHERE si.spk_id = $id";
        } else {
            $sql_items = "SELECT si.*, sp.nama as sparepart_name, sp.satuan, sp.harga_jual_default,
                          sp.harga_jual_default as harga_satuan_eff,
                          (si.qty * sp.harga_jual_default) as subtotal
                          FROM spk_items si
                          JOIN spareparts sp ON si.sparepart_id = sp.id
                          WHERE si.spk_id = $id";
        }
        $result_items = mysqli_query($conn, $sql_items);
        
        $items = [];
        while ($item = mysqli_fetch_assoc($result_items)) {
            $items[] = $item;
        }
        
        $row['items'] = $items;
        
        // Get SPK services
        $sql_services = "SELECT ss.*, sp.kode_jasa, sp.nama_jasa, sp.kategori
                         FROM spk_services ss
                         JOIN service_prices sp ON ss.service_price_id = sp.id
                         WHERE ss.spk_id = $id";
        $result_services = mysqli_query($conn, $sql_services);
        
        $services = [];
        while ($service = mysqli_fetch_assoc($result_services)) {
            $services[] = $service;
        }
        
        $row['services'] = $services;
        
        // Hitung total biaya jasa dari services
        $total_biaya_jasa = 0;
        foreach ($services as $svc) {
            $total_biaya_jasa += (float)($svc['subtotal'] ?? 0);
        }
        $row['biaya_jasa'] = $total_biaya_jasa;
        
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

// GET ALL VEHICLES FOR FILTER (with customer name)
elseif ($action === 'get_all_vehicles') {
    $sql = "SELECT v.id, v.nomor_polisi, v.merk, v.model, c.name as customer_name 
            FROM vehicles v
            JOIN customers c ON v.customer_id = c.id
            ORDER BY c.name ASC, v.nomor_polisi ASC";
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
    $saran_service = trim($_POST['saran_service']);
    
    $sql = "UPDATE spk SET 
            analisa_mekanik = '" . mysqli_real_escape_string($conn, $analisa_mekanik) . "',
            service_description = '" . mysqli_real_escape_string($conn, $service_description) . "',
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
    $valid_statuses = ['Menunggu Konfirmasi', 'Disetujui', 'Dalam Pengerjaan', 'Selesai', 'Dikirim ke Owner', 'Buat Invoice', 'Sudah Cetak Invoice', 'Dibatalkan'];
    if (!in_array($status, $valid_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Status tidak valid']);
        exit;
    }

    $spk_res = mysqli_query($conn, "SELECT status_spk FROM spk WHERE id = $id LIMIT 1");
    $spk_row = $spk_res ? mysqli_fetch_assoc($spk_res) : null;
    if (!$spk_row) {
        echo json_encode(['success' => false, 'message' => 'SPK tidak ditemukan']);
        exit;
    }

    $old_status = (string)($spk_row['status_spk'] ?? '');
    $target_status = mysqli_real_escape_string($conn, $status);

    // Jika dibatalkan, kembalikan stok sekali (idempotent) saat transisi ke Dibatalkan.
    if ($old_status !== 'Dibatalkan' && $status === 'Dibatalkan') {
        $restore_res = mysqli_query($conn, "SELECT sparepart_id, SUM(qty) as qty_total FROM spk_items WHERE spk_id = $id GROUP BY sparepart_id");
        if (!$restore_res) {
            echo json_encode(['success' => false, 'message' => 'Gagal membaca item SPK untuk pembatalan: ' . mysqli_error($conn)]);
            exit;
        }

        while ($it = mysqli_fetch_assoc($restore_res)) {
            $sparepart_id = (int)$it['sparepart_id'];
            $qty_total = (int)$it['qty_total'];
            mysqli_query($conn, "UPDATE spareparts SET {$sparepart_stock_col} = {$sparepart_stock_col} + $qty_total WHERE id = $sparepart_id");
        }
    }

    $sql = "UPDATE spk SET status_spk = '$target_status' WHERE id = $id";
    if (mysqli_query($conn, $sql)) {
        echo json_encode(['success' => true, 'message' => 'Status SPK berhasil diupdate']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal update status: ' . mysqli_error($conn)]);
    }
}

// ADD SERVICE TO SPK
elseif ($action === 'add_service') {
    $spk_id = (int)$_POST['spk_id'];
    $service_price_id = (int)$_POST['service_price_id'];
    $qty = (int)$_POST['qty'];
    $harga = (float)$_POST['harga'];
    
    $sql = "INSERT INTO spk_services (spk_id, service_price_id, qty, harga) 
            VALUES ($spk_id, $service_price_id, $qty, $harga)";
    
    if (mysqli_query($conn, $sql)) {
        echo json_encode(['success' => true, 'message' => 'Jasa service berhasil ditambahkan ke SPK']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menambahkan service: ' . mysqli_error($conn)]);
    }
}

// DELETE SERVICE FROM SPK
elseif ($action === 'delete_service') {
    $id = (int)$_POST['id'];
    
    $sql = "DELETE FROM spk_services WHERE id = $id";
    
    if (mysqli_query($conn, $sql)) {
        echo json_encode(['success' => true, 'message' => 'Service berhasil dihapus']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus service: ' . mysqli_error($conn)]);
    }
}

// GET ALL SPAREPARTS FOR DROPDOWN
elseif ($action === 'get_all_spareparts') {
    $sql = "SELECT id, kode_sparepart, nama, satuan, {$sparepart_stock_col} as stok, harga_jual_default 
            FROM spareparts 
            WHERE {$sparepart_stock_col} > 0
            ORDER BY nama ASC";
    $result = mysqli_query($conn, $sql);

    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'Gagal memuat sparepart: ' . mysqli_error($conn)]);
        exit;
    }
    
    $spareparts = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $spareparts[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $spareparts]);
}

// ADD SPAREPART TO SPK
elseif ($action === 'add_sparepart') {
    $spk_id = (int)$_POST['spk_id'];
    $sparepart_id = (int)$_POST['sparepart_id'];
    $qty = (int)$_POST['qty'];

    if ($spk_id <= 0 || $sparepart_id <= 0 || $qty <= 0) {
        echo json_encode(['success' => false, 'message' => 'Parameter tambah sparepart tidak valid']);
        exit;
    }

    $spk_exists = mysqli_query($conn, "SELECT id FROM spk WHERE id = $spk_id LIMIT 1");
    if (!$spk_exists || mysqli_num_rows($spk_exists) === 0) {
        echo json_encode(['success' => false, 'message' => 'SPK tidak ditemukan']);
        exit;
    }
    
    // Check stock availability + get price snapshot
    $check_stock = mysqli_query($conn, "SELECT {$sparepart_stock_col} as stok, harga_jual_default, harga_beli_default FROM spareparts WHERE id = $sparepart_id");
    $stock = mysqli_fetch_assoc($check_stock);

    if (!$stock) {
        echo json_encode(['success' => false, 'message' => 'Sparepart tidak ditemukan']);
        exit;
    }
    
    if ($stock['stok'] < $qty) {
        echo json_encode(['success' => false, 'message' => 'Stok tidak mencukupi. Stok tersedia: ' . $stock['stok']]);
        exit;
    }
    
    $harga_satuan = (float)$stock['harga_jual_default'];
    $hpp_satuan = (float)$stock['harga_beli_default'];
    
    // Check migration columns in spk_items and adapt insert query.
    $col_harga = mysqli_query($conn, "SHOW COLUMNS FROM spk_items LIKE 'harga_satuan'");
    $has_harga_col = $col_harga && mysqli_num_rows($col_harga) > 0;

    $col_hpp = mysqli_query($conn, "SHOW COLUMNS FROM spk_items LIKE 'hpp_satuan'");
    $has_hpp_col = $col_hpp && mysqli_num_rows($col_hpp) > 0;
    
    if ($has_harga_col && $has_hpp_col) {
        $sql = "INSERT INTO spk_items (spk_id, sparepart_id, qty, harga_satuan, hpp_satuan) 
                VALUES ($spk_id, $sparepart_id, $qty, $harga_satuan, $hpp_satuan)";
    } elseif ($has_harga_col) {
        $sql = "INSERT INTO spk_items (spk_id, sparepart_id, qty, harga_satuan) 
                VALUES ($spk_id, $sparepart_id, $qty, $harga_satuan)";
    } else {
        $sql = "INSERT INTO spk_items (spk_id, sparepart_id, qty) 
                VALUES ($spk_id, $sparepart_id, $qty)";
    }
    
    if (mysqli_query($conn, $sql)) {
        // Decrease stock immediately after item is added.
        mysqli_query($conn, "UPDATE spareparts SET {$sparepart_stock_col} = {$sparepart_stock_col} - $qty WHERE id = $sparepart_id");
        echo json_encode(['success' => true, 'message' => 'Sparepart berhasil ditambahkan ke SPK']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menambahkan sparepart: ' . mysqli_error($conn)]);
    }
}

// DELETE SPAREPART FROM SPK
elseif ($action === 'delete_sparepart') {
    $id = (int)$_POST['id'];
    
    // Get sparepart info to restore stock.
    $get_item = mysqli_query($conn, "SELECT sparepart_id, qty FROM spk_items WHERE id = $id");
    $item = mysqli_fetch_assoc($get_item);
    
    if ($item) {
        // Restore stock.
        mysqli_query($conn, "UPDATE spareparts SET {$sparepart_stock_col} = {$sparepart_stock_col} + {$item['qty']} WHERE id = {$item['sparepart_id']}");
        
        // Delete from spk_items
        $sql = "DELETE FROM spk_items WHERE id = $id";
        
        if (mysqli_query($conn, $sql)) {
            echo json_encode(['success' => true, 'message' => 'Sparepart berhasil dihapus']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menghapus sparepart: ' . mysqli_error($conn)]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan']);
    }
}

// DELETE SPK (hanya jika belum ada invoice)
elseif ($action === 'delete') {
    echo json_encode(['success' => false, 'message' => 'Hapus SPK dinonaktifkan. Gunakan status Dibatalkan.']);
}

else {
    echo json_encode(['success' => false, 'message' => 'Action tidak valid']);
}
?>
