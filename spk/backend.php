<?php
session_start();
require_once '../config.php';
require_once '../finance_helper.php';

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$currentUserRole = (string)($_SESSION['role'] ?? 'Admin');

finance_ensure_default_accounts($conn);

function spk_insert_discount_history(
    mysqli $conn,
    int $spkId,
    string $actionType,
    float $requestedAmount,
    float $approvedAmount,
    ?string $note,
    ?int $actedBy,
    ?string $actedRole
): bool {
    $actionEsc = mysqli_real_escape_string($conn, $actionType);
    $noteEsc = mysqli_real_escape_string($conn, (string)$note);
    $roleEsc = mysqli_real_escape_string($conn, (string)$actedRole);
    $actedBySql = $actedBy ? (string)$actedBy : 'NULL';

    $sql = "INSERT INTO spk_discount_histories
            (spk_id, action_type, requested_amount, approved_amount, note, acted_by, acted_role)
            VALUES
            ($spkId, '$actionEsc', $requestedAmount, $approvedAmount, '$noteEsc', $actedBySql, '$roleEsc')";

    return mysqli_query($conn, $sql) !== false;
}

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
    $customer_id = (int)($_POST['customer_id'] ?? 0);
    $vehicle_id = (int)$_POST['vehicle_id'];
    $tanggal = $_POST['tanggal'];
    $keluhan_customer = trim($_POST['keluhan_customer']);

    // Always bind customer to the selected vehicle owner to avoid mismatches.
    $vehicleCheck = mysqli_query($conn, "SELECT customer_id FROM vehicles WHERE id = $vehicle_id LIMIT 1");
    $vehicleRow = $vehicleCheck ? mysqli_fetch_assoc($vehicleCheck) : null;
    if (!$vehicleRow) {
        echo json_encode(['success' => false, 'message' => 'Kendaraan tidak ditemukan']);
        exit;
    }
    $customer_id = (int)($vehicleRow['customer_id'] ?? 0);
    if ($customer_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Customer untuk kendaraan tidak valid']);
        exit;
    }
    
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
    $discount_flow = $_GET['discount_flow'] ?? '';
    
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

    if (!empty($discount_flow)) {
        $discount_flow = mysqli_real_escape_string($conn, $discount_flow);
        if ($discount_flow === 'attention') {
            $conditions[] = "LOWER(COALESCE(s.discount_status, 'none')) IN ('pending', 'revision')";
        } elseif ($discount_flow === 'has_request') {
            $conditions[] = "COALESCE(s.discount_amount_requested, 0) > 0";
        }
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
    $sql = "SELECT
                MIN(c.id) as id,
                MIN(c.name) as name,
                MIN(c.phone) as phone,
                GROUP_CONCAT(c.id ORDER BY c.id ASC) as customer_ids
            FROM customers c
            GROUP BY LOWER(TRIM(c.name)), COALESCE(TRIM(c.phone), '')
            ORDER BY MIN(c.name) ASC";
    $result = mysqli_query($conn, $sql);
    
    $customers = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $customers[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $customers]);
}

// GET VEHICLES BY CUSTOMER
elseif ($action === 'get_vehicles') {
    $customer_ids_raw = trim((string)($_GET['customer_ids'] ?? $_GET['customer_id'] ?? ''));
    $idParts = array_filter(array_map('trim', explode(',', $customer_ids_raw)), function ($v) {
        return $v !== '';
    });

    $ids = [];
    foreach ($idParts as $part) {
        $id = (int)$part;
        if ($id > 0) {
            $ids[] = $id;
        }
    }
    $ids = array_values(array_unique($ids));

    if (count($ids) === 0) {
        echo json_encode(['success' => true, 'data' => []]);
        exit;
    }

    $idsSql = implode(',', $ids);
    $sql = "SELECT id, customer_id, nomor_polisi, merk, model, tahun
            FROM vehicles
            WHERE customer_id IN ($idsSql)
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

// SUBMIT DISCOUNT REQUEST (Admin)
elseif ($action === 'submit_discount') {
    if (!in_array($currentUserRole, ['Admin', 'Owner'], true)) {
        echo json_encode(['success' => false, 'message' => 'Role Anda tidak memiliki akses flow diskon']);
        exit;
    }

    $id = (int)($_POST['id'] ?? 0);
    $discountAmount = (float)($_POST['discount_amount_requested'] ?? 0);
    $discountReason = trim((string)($_POST['discount_reason'] ?? ''));

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'SPK tidak valid']);
        exit;
    }

    if ($discountAmount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Nominal diskon harus lebih dari 0']);
        exit;
    }

    if ($currentUserRole === 'Admin' && $discountReason === '') {
        echo json_encode(['success' => false, 'message' => 'Alasan diskon wajib diisi']);
        exit;
    }

    $spkRes = mysqli_query($conn, "SELECT id, kode_unik_reference, status_spk, discount_status, discount_finance_tx_id FROM spk WHERE id = $id LIMIT 1");
    $spk = $spkRes ? mysqli_fetch_assoc($spkRes) : null;
    if (!$spk) {
        echo json_encode(['success' => false, 'message' => 'SPK tidak ditemukan']);
        exit;
    }

    if (($spk['status_spk'] ?? '') === 'Dibatalkan') {
        echo json_encode(['success' => false, 'message' => 'SPK dibatalkan, diskon tidak dapat diajukan']);
        exit;
    }

    if (($spk['discount_status'] ?? '') === 'approved' && (int)($spk['discount_finance_tx_id'] ?? 0) > 0) {
        echo json_encode(['success' => false, 'message' => 'Diskon sudah di-ACC owner dan tercatat ke keuangan']);
        exit;
    }

    $reasonEsc = mysqli_real_escape_string($conn, $discountReason);

    mysqli_begin_transaction($conn);
    try {
        // Owner can input discount and directly auto-approve in one step.
        if ($currentUserRole === 'Owner') {
            $account = finance_get_account_by_code($conn, 'cash');
            if (!$account) {
                throw new Exception('Akun kas tidak ditemukan');
            }

            $tx = finance_add_transaction(
                $conn,
                date('Y-m-d'),
                (int)$account['id'],
                'out',
                'EXP-SALES-DISCOUNT',
                $discountAmount,
                'operational',
                $id,
                'Sales discount SPK #' . ($spk['kode_unik_reference'] ?? $id),
                $currentUserId,
                'approved'
            );
            if (!$tx['success']) {
                throw new Exception($tx['message']);
            }

            $txId = (int)($tx['transaction_id'] ?? 0);
            if ($txId <= 0) {
                throw new Exception('ID transaksi keuangan tidak valid saat auto-approve owner');
            }

            $ownerNoteAuto = $discountReason !== '' ? $discountReason : 'Auto-approve oleh Owner';
            $ownerNoteAutoEsc = mysqli_real_escape_string($conn, $ownerNoteAuto);

            $sqlUpdate = "UPDATE spk
                          SET discount_amount_requested = $discountAmount,
                              discount_amount_approved = $discountAmount,
                              discount_status = 'approved',
                              discount_reason = '$reasonEsc',
                              discount_owner_note = '$ownerNoteAutoEsc',
                              discount_requested_by = $currentUserId,
                              discount_requested_at = NOW(),
                              discount_reviewed_by = $currentUserId,
                              discount_reviewed_at = NOW(),
                              discount_finance_tx_id = $txId
                          WHERE id = $id";
            if (!mysqli_query($conn, $sqlUpdate)) {
                throw new Exception('Gagal menyimpan auto-approve diskon owner: ' . mysqli_error($conn));
            }

            if (!spk_insert_discount_history($conn, $id, 'approve', $discountAmount, $discountAmount, $ownerNoteAuto, $currentUserId, $currentUserRole)) {
                throw new Exception('Gagal menyimpan histori auto-approve owner: ' . mysqli_error($conn));
            }

            mysqli_commit($conn);
            echo json_encode(['success' => true, 'message' => 'Diskon langsung di-ACC Owner dan tercatat di keuangan']);
            exit;
        }

        $sqlUpdate = "UPDATE spk
                      SET discount_amount_requested = $discountAmount,
                          discount_amount_approved = 0,
                          discount_status = 'pending',
                          discount_reason = '$reasonEsc',
                          discount_owner_note = NULL,
                          discount_requested_by = $currentUserId,
                          discount_requested_at = NOW(),
                          discount_reviewed_by = NULL,
                          discount_reviewed_at = NULL
                      WHERE id = $id";
        if (!mysqli_query($conn, $sqlUpdate)) {
            throw new Exception('Gagal menyimpan pengajuan diskon: ' . mysqli_error($conn));
        }

        $historyAction = (($spk['discount_status'] ?? 'none') === 'none') ? 'submit' : 'resubmit';
        if (!spk_insert_discount_history($conn, $id, $historyAction, $discountAmount, 0, $discountReason, $currentUserId, $currentUserRole)) {
            throw new Exception('Gagal menyimpan histori diskon: ' . mysqli_error($conn));
        }

        mysqli_commit($conn);
        echo json_encode(['success' => true, 'message' => 'Pengajuan diskon berhasil dikirim ke Owner']);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// REVIEW DISCOUNT REQUEST (Owner)
elseif ($action === 'review_discount') {
    if ($currentUserRole !== 'Owner') {
        echo json_encode(['success' => false, 'message' => 'Hanya Owner yang dapat review diskon']);
        exit;
    }

    $id = (int)($_POST['id'] ?? 0);
    $decision = trim((string)($_POST['decision'] ?? ''));
    $ownerNote = trim((string)($_POST['discount_owner_note'] ?? ''));
    $approvedAmountRaw = $_POST['discount_amount_approved'] ?? ($_POST['discount_amount_requested'] ?? null);
    $approvedAmountInput = is_numeric($approvedAmountRaw) ? (float)$approvedAmountRaw : 0;

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'SPK tidak valid']);
        exit;
    }

    if (!in_array($decision, ['approve', 'revision', 'reject'], true)) {
        echo json_encode(['success' => false, 'message' => 'Keputusan review tidak valid']);
        exit;
    }

    $spkRes = mysqli_query($conn, "SELECT id, kode_unik_reference, discount_status, discount_amount_requested, discount_finance_tx_id FROM spk WHERE id = $id LIMIT 1");
    $spk = $spkRes ? mysqli_fetch_assoc($spkRes) : null;
    if (!$spk) {
        echo json_encode(['success' => false, 'message' => 'SPK tidak ditemukan']);
        exit;
    }

    $discountStatus = (string)($spk['discount_status'] ?? 'none');
    $requestedAmount = (float)($spk['discount_amount_requested'] ?? 0);

    if (!in_array($discountStatus, ['pending', 'revision'], true)) {
        echo json_encode(['success' => false, 'message' => 'SPK ini tidak memiliki pengajuan diskon aktif']);
        exit;
    }

    if ($requestedAmount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Nominal pengajuan diskon tidak valid']);
        exit;
    }

    $approvedAmount = $approvedAmountInput > 0 ? $approvedAmountInput : $requestedAmount;
    if ($approvedAmount < 0) {
        echo json_encode(['success' => false, 'message' => 'Nominal diskon disetujui tidak valid']);
        exit;
    }

    $ownerNoteEsc = mysqli_real_escape_string($conn, $ownerNote);

    mysqli_begin_transaction($conn);
    try {
        if ($decision === 'approve') {
            if ($approvedAmount <= 0) {
                throw new Exception('Nominal diskon approved harus lebih dari 0');
            }

            if ((int)($spk['discount_finance_tx_id'] ?? 0) > 0) {
                throw new Exception('Diskon SPK ini sudah pernah diposting ke keuangan');
            }

            $account = finance_get_account_by_code($conn, 'cash');
            if (!$account) {
                throw new Exception('Akun kas tidak ditemukan');
            }

            $tx = finance_add_transaction(
                $conn,
                date('Y-m-d'),
                (int)$account['id'],
                'out',
                'EXP-SALES-DISCOUNT',
                $approvedAmount,
                'operational',
                $id,
                'Sales discount SPK #' . ($spk['kode_unik_reference'] ?? $id),
                $currentUserId,
                'approved'
            );
            if (!$tx['success']) {
                throw new Exception($tx['message']);
            }

            $txId = (int)($tx['transaction_id'] ?? 0);
            if ($txId <= 0) {
                throw new Exception('ID transaksi keuangan tidak valid saat approval diskon');
            }
            $sqlUpdate = "UPDATE spk
                          SET discount_status = 'approved',
                              discount_amount_requested = $approvedAmount,
                              discount_amount_approved = $approvedAmount,
                              discount_owner_note = '$ownerNoteEsc',
                              discount_reviewed_by = $currentUserId,
                              discount_reviewed_at = NOW(),
                              discount_finance_tx_id = $txId
                          WHERE id = $id";
            if (!mysqli_query($conn, $sqlUpdate)) {
                throw new Exception('Gagal menyimpan approval diskon: ' . mysqli_error($conn));
            }

            if (!spk_insert_discount_history($conn, $id, 'approve', $requestedAmount, $approvedAmount, $ownerNote, $currentUserId, $currentUserRole)) {
                throw new Exception('Gagal menyimpan histori approval: ' . mysqli_error($conn));
            }

            mysqli_commit($conn);
            echo json_encode(['success' => true, 'message' => 'Diskon berhasil di-ACC dan tercatat di keuangan']);
            exit;
        }

        if ($decision === 'revision') {
            $suggestedAmount = $approvedAmount;
            $sqlUpdate = "UPDATE spk
                          SET discount_status = 'revision',
                              discount_amount_requested = $suggestedAmount,
                              discount_amount_approved = $suggestedAmount,
                              discount_owner_note = '$ownerNoteEsc',
                              discount_reviewed_by = $currentUserId,
                              discount_reviewed_at = NOW()
                          WHERE id = $id";
            if (!mysqli_query($conn, $sqlUpdate)) {
                throw new Exception('Gagal menyimpan status revisi diskon: ' . mysqli_error($conn));
            }

            if (!spk_insert_discount_history($conn, $id, 'revision', $requestedAmount, $suggestedAmount, $ownerNote, $currentUserId, $currentUserRole)) {
                throw new Exception('Gagal menyimpan histori revisi: ' . mysqli_error($conn));
            }

            mysqli_commit($conn);
            echo json_encode(['success' => true, 'message' => 'Permintaan revisi diskon berhasil dikirim ke Admin']);
            exit;
        }

        $sqlUpdate = "UPDATE spk
                      SET discount_status = 'rejected',
                          discount_amount_approved = 0,
                          discount_owner_note = '$ownerNoteEsc',
                          discount_reviewed_by = $currentUserId,
                          discount_reviewed_at = NOW()
                      WHERE id = $id";
        if (!mysqli_query($conn, $sqlUpdate)) {
            throw new Exception('Gagal menolak diskon: ' . mysqli_error($conn));
        }

        if (!spk_insert_discount_history($conn, $id, 'reject', $requestedAmount, 0, $ownerNote, $currentUserId, $currentUserRole)) {
            throw new Exception('Gagal menyimpan histori penolakan: ' . mysqli_error($conn));
        }

        mysqli_commit($conn);
        echo json_encode(['success' => true, 'message' => 'Pengajuan diskon ditolak']);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// GET DISCOUNT HISTORY
elseif ($action === 'get_discount_history') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'SPK tidak valid']);
        exit;
    }

    $sql = "SELECT h.*, u.username as acted_by_name
            FROM spk_discount_histories h
            LEFT JOIN users u ON u.id = h.acted_by
            WHERE h.spk_id = $id
            ORDER BY h.id DESC";
    $res = mysqli_query($conn, $sql);
    if (!$res) {
        echo json_encode(['success' => false, 'message' => 'Gagal memuat histori diskon: ' . mysqli_error($conn)]);
        exit;
    }

    $rows = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
    }

    echo json_encode(['success' => true, 'data' => $rows]);
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
