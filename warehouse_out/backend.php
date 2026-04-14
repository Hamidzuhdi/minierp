<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'read') {
    $search = trim((string)($_GET['search'] ?? ''));
    $is_paid = trim((string)($_GET['is_paid'] ?? ''));

    $sql = "SELECT
                p.id,
                p.tanggal,
                p.supplier,
                p.total,
                p.status,
                p.is_paid,
                p.paid_at,
                p.created_at,
                u.username AS created_by_name,
                u.role AS created_by_role,
                COALESCE(i.item_count, 0) AS item_count,
                COALESCE(i.qty_total, 0) AS qty_total,
                COALESCE(i.item_detail, '') AS item_detail
            FROM purchases p
            LEFT JOIN users u ON p.created_by = u.id
            LEFT JOIN (
                SELECT
                    pi.purchase_id,
                    COUNT(*) AS item_count,
                    SUM(pi.qty) AS qty_total,
                    GROUP_CONCAT(
                        CONCAT(
                            sp.nama,
                            ' x',
                            pi.qty,
                            IF(COALESCE(sp.satuan, '') <> '', CONCAT(' ', sp.satuan), '')
                        )
                        ORDER BY sp.nama ASC
                        SEPARATOR ' | '
                    ) AS item_detail
                FROM purchase_items pi
                JOIN spareparts sp ON sp.id = pi.sparepart_id
                GROUP BY pi.purchase_id
            ) i ON i.purchase_id = p.id";

    $conditions = [];
    $conditions[] = "(u.role IN ('Admin', 'Owner') OR p.created_by IS NULL)";

    if ($search !== '') {
        $searchEsc = mysqli_real_escape_string($conn, $search);
        $conditions[] = "(p.id LIKE '%$searchEsc%' OR p.supplier LIKE '%$searchEsc%' OR u.username LIKE '%$searchEsc%')";
    }

    if ($is_paid !== '') {
        $isPaidEsc = mysqli_real_escape_string($conn, $is_paid);
        $conditions[] = "p.is_paid = '$isPaidEsc'";
    }

    if (count($conditions) > 0) {
        $sql .= " WHERE " . implode(' AND ', $conditions);
    }

    $sql .= " ORDER BY p.id DESC";

    $result = mysqli_query($conn, $sql);
    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'Gagal memuat history purchase: ' . mysqli_error($conn)]);
        exit;
    }

    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }

    echo json_encode(['success' => true, 'data' => $rows]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Action tidak valid']);
?>
