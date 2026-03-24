<?php
session_start();
require_once '../config.php';
require_once '../finance_helper.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');
finance_ensure_default_accounts($conn);

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$userRole = $_SESSION['role'] ?? 'Admin';

if (!in_array($userRole, ['Owner', 'Admin'], true)) {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
    exit;
}

if ($action === 'get_accounts') {
    $res = mysqli_query($conn, "SELECT id, code, name, opening_balance, current_balance FROM finance_accounts WHERE is_active = 1 ORDER BY id ASC");
    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $rows[] = $r;
    }
    echo json_encode(['success' => true, 'data' => $rows]);
}

elseif ($action === 'get_expense_categories') {
    $res = mysqli_query($conn, "SELECT id, code, name, description, is_active FROM expense_categories WHERE is_active = 1 ORDER BY name ASC");
    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $rows[] = $r;
    }
    echo json_encode(['success' => true, 'data' => $rows]);
}

elseif ($action === 'get_operational_expense_categories') {
    $res = mysqli_query($conn, "
        SELECT DISTINCT oe.category_code as code,
               COALESCE(ec.name, oe.category_code) as name
        FROM operational_expenses oe
        LEFT JOIN expense_categories ec ON ec.code = oe.category_code
        WHERE oe.category_code IS NOT NULL AND oe.category_code <> ''
        ORDER BY oe.category_code ASC
    ");
    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $rows[] = $r;
    }
    echo json_encode(['success' => true, 'data' => $rows]);
}

elseif ($action === 'create_expense_category') {
    if ($userRole !== 'Owner') {
        echo json_encode(['success' => false, 'message' => 'Hanya Owner yang bisa menambah kategori']);
        exit;
    }
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    if ($code === '' || $name === '') {
        echo json_encode(['success' => false, 'message' => 'Kode dan nama kategori wajib diisi']);
        exit;
    }
    $sql = "INSERT INTO expense_categories (code, name, description, is_active)
            VALUES ('" . mysqli_real_escape_string($conn, $code) . "',
                    '" . mysqli_real_escape_string($conn, $name) . "',
                    '" . mysqli_real_escape_string($conn, $description) . "', 1)";
    if (mysqli_query($conn, $sql)) {
        echo json_encode(['success' => true, 'message' => 'Kategori pengeluaran berhasil ditambahkan']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menambah kategori: ' . mysqli_error($conn)]);
    }
}

elseif ($action === 'update_expense_category') {
    if ($userRole !== 'Owner') {
        echo json_encode(['success' => false, 'message' => 'Hanya Owner yang bisa mengubah kategori']);
        exit;
    }
    $id = (int)($_POST['id'] ?? 0);
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    if ($id <= 0 || $code === '' || $name === '') {
        echo json_encode(['success' => false, 'message' => 'Data kategori tidak valid']);
        exit;
    }
    $sql = "UPDATE expense_categories
            SET code = '" . mysqli_real_escape_string($conn, $code) . "',
                name = '" . mysqli_real_escape_string($conn, $name) . "',
                description = '" . mysqli_real_escape_string($conn, $description) . "'
            WHERE id = $id";
    if (mysqli_query($conn, $sql)) {
        echo json_encode(['success' => true, 'message' => 'Kategori pengeluaran berhasil diupdate']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal update kategori: ' . mysqli_error($conn)]);
    }
}

elseif ($action === 'delete_expense_category') {
    if ($userRole !== 'Owner') {
        echo json_encode(['success' => false, 'message' => 'Hanya Owner yang bisa menghapus kategori']);
        exit;
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID kategori tidak valid']);
        exit;
    }
    $sql = "UPDATE expense_categories SET is_active = 0 WHERE id = $id";
    if (mysqli_query($conn, $sql)) {
        echo json_encode(['success' => true, 'message' => 'Kategori pengeluaran berhasil dihapus']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus kategori']);
    }
}

elseif ($action === 'summary') {
    $cash = finance_get_account_by_code($conn, 'cash');
    $bank = finance_get_account_by_code($conn, 'bank');

    $today = date('Y-m-d');
    $month = date('Y-m');

    $qTodayIn = mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) as total FROM finance_transactions WHERE status = 'approved' AND direction IN ('in','transfer_in') AND tanggal = '$today'");
    $qTodayOut = mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) as total FROM finance_transactions WHERE status = 'approved' AND direction IN ('out','transfer_out') AND tanggal = '$today'");
    $qMonthIn = mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) as total FROM finance_transactions WHERE status = 'approved' AND direction IN ('in','transfer_in') AND DATE_FORMAT(tanggal, '%Y-%m') = '$month'");
    $qMonthOut = mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) as total FROM finance_transactions WHERE status = 'approved' AND direction IN ('out','transfer_out') AND DATE_FORMAT(tanggal, '%Y-%m') = '$month'");

    echo json_encode([
        'success' => true,
        'data' => [
            'cash_balance' => (float)($cash['current_balance'] ?? 0),
            'bank_balance' => (float)($bank['current_balance'] ?? 0),
            'total_balance' => (float)($cash['current_balance'] ?? 0) + (float)($bank['current_balance'] ?? 0),
            'today_in' => (float)mysqli_fetch_assoc($qTodayIn)['total'],
            'today_out' => (float)mysqli_fetch_assoc($qTodayOut)['total'],
            'month_in' => (float)mysqli_fetch_assoc($qMonthIn)['total'],
            'month_out' => (float)mysqli_fetch_assoc($qMonthOut)['total'],
        ]
    ]);
}

elseif ($action === 'read_transactions') {
    $from = $_GET['from'] ?? '';
    $to = $_GET['to'] ?? '';
    $account = $_GET['account'] ?? '';
    $direction = $_GET['direction'] ?? '';
    $category = $_GET['category'] ?? '';
    $keyword = trim($_GET['keyword'] ?? '');

          $sql = "SELECT ft.*, fa.code as account_code, fa.name as account_name, u.username as created_by_name,
                    uo.username as approved_by_name, ur.username as rejected_by_name,
               ec.name as expense_category_name
            FROM finance_transactions ft
            JOIN finance_accounts fa ON ft.account_id = fa.id
            LEFT JOIN users u ON ft.created_by = u.id
                LEFT JOIN users uo ON ft.approved_by = uo.id
                LEFT JOIN users ur ON ft.rejected_by = ur.id
            LEFT JOIN expense_categories ec ON ft.category = ec.code";

    $conds = [];
    if ($from !== '') {
        $conds[] = "ft.tanggal >= '" . mysqli_real_escape_string($conn, $from) . "'";
    }
    if ($to !== '') {
        $conds[] = "ft.tanggal <= '" . mysqli_real_escape_string($conn, $to) . "'";
    }
    if ($account !== '') {
        $accountEsc = mysqli_real_escape_string($conn, $account);
        $conds[] = "fa.code = '$accountEsc'";
    }
    if ($direction !== '') {
        $dirEsc = mysqli_real_escape_string($conn, $direction);
        $conds[] = "ft.direction = '$dirEsc'";
    }
    if ($category !== '') {
        $catEsc = mysqli_real_escape_string($conn, $category);
        $conds[] = "ft.category = '$catEsc'";
    }
    if ($keyword !== '') {
        $kwEsc = mysqli_real_escape_string($conn, $keyword);
        $conds[] = "(ft.note LIKE '%$kwEsc%' OR ft.reference_type LIKE '%$kwEsc%' OR ft.reference_id LIKE '%$kwEsc%')";
    }

    if (count($conds) > 0) {
        $sql .= " WHERE " . implode(' AND ', $conds);
    }

    $sql .= " ORDER BY ft.tanggal DESC, ft.id DESC LIMIT 300";

    $res = mysqli_query($conn, $sql);
    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $rows[] = $r;
    }

    echo json_encode(['success' => true, 'data' => $rows]);
}

elseif ($action === 'read_pending_transactions') {
    if ($userRole !== 'Owner') {
        echo json_encode(['success' => false, 'message' => 'Hanya Owner yang bisa melihat approval']);
        exit;
    }

    $sql = "SELECT ft.*, fa.name as account_name, u.username as created_by_name
            FROM finance_transactions ft
            JOIN finance_accounts fa ON ft.account_id = fa.id
            LEFT JOIN users u ON ft.created_by = u.id
            WHERE ft.status = 'pending'
            ORDER BY ft.created_at DESC, ft.id DESC
            LIMIT 300";
    $res = mysqli_query($conn, $sql);
    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $rows[] = $r;
    }

    echo json_encode(['success' => true, 'data' => $rows]);
}

elseif ($action === 'approve_transaction') {
    if ($userRole !== 'Owner') {
        echo json_encode(['success' => false, 'message' => 'Hanya Owner yang bisa approve']);
        exit;
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID transaksi tidak valid']);
        exit;
    }

    $result = finance_approve_transaction($conn, $id, (int)$_SESSION['user_id']);
    if ($result['success']) {
        echo json_encode(['success' => true, 'message' => 'Transaksi berhasil di-approve']);
    } else {
        echo json_encode(['success' => false, 'message' => $result['message'] ?? 'Gagal approve transaksi']);
    }
}

elseif ($action === 'reject_transaction') {
    if ($userRole !== 'Owner') {
        echo json_encode(['success' => false, 'message' => 'Hanya Owner yang bisa reject']);
        exit;
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID transaksi tidak valid']);
        exit;
    }

    $result = finance_reject_transaction($conn, $id, (int)$_SESSION['user_id']);
    if ($result['success']) {
        echo json_encode(['success' => true, 'message' => 'Transaksi berhasil di-reject']);
    } else {
        echo json_encode(['success' => false, 'message' => $result['message'] ?? 'Gagal reject transaksi']);
    }
}

elseif ($action === 'create_operational_expense') {
    $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
    $expenseName = trim($_POST['expense_name'] ?? '');
    $categoryCode = trim($_POST['category_code'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $accountCode = $_POST['account_code'] ?? '';
    $note = trim($_POST['note'] ?? '');

    if ($expenseName === '' || $categoryCode === '' || $amount <= 0 || $accountCode === '') {
        echo json_encode(['success' => false, 'message' => 'Data pengeluaran operasional tidak lengkap']);
        exit;
    }

    $qCat = mysqli_query($conn, "SELECT code, name FROM expense_categories WHERE code = '" . mysqli_real_escape_string($conn, $categoryCode) . "' AND is_active = 1 LIMIT 1");
    $cat = mysqli_fetch_assoc($qCat);
    if (!$cat) {
        echo json_encode(['success' => false, 'message' => 'Kategori pengeluaran tidak valid']);
        exit;
    }

    $account = finance_get_account_by_code($conn, $accountCode);
    if (!$account) {
        echo json_encode(['success' => false, 'message' => 'Akun sumber dana tidak valid']);
        exit;
    }

    $txStatus = ($userRole === 'Owner') ? 'approved' : 'pending';

    mysqli_begin_transaction($conn);
    try {
        $sql = "INSERT INTO operational_expenses (tanggal, expense_name, category_code, amount, account_id, note, created_by)
                VALUES ('" . mysqli_real_escape_string($conn, $tanggal) . "',
                        '" . mysqli_real_escape_string($conn, $expenseName) . "',
                        '" . mysqli_real_escape_string($conn, $cat['code']) . "',
                        $amount,
                        {$account['id']},
                        '" . mysqli_real_escape_string($conn, $note) . "',
                        " . (int)$_SESSION['user_id'] . ")";

        if (!mysqli_query($conn, $sql)) {
            throw new Exception('Gagal simpan operational expense: ' . mysqli_error($conn));
        }

        $expenseId = mysqli_insert_id($conn);

        $tx = finance_add_transaction(
            $conn,
            $tanggal,
            (int)$account['id'],
            'out',
            $cat['code'],
            $amount,
            'operational',
            $expenseId,
            $note !== '' ? $note : ('Pengeluaran ' . $cat['name'] . ': ' . $expenseName),
            (int)$_SESSION['user_id'],
            $txStatus
        );

        if (!$tx['success']) {
            throw new Exception($tx['message']);
        }

        mysqli_commit($conn);
        if ($txStatus === 'pending') {
            echo json_encode(['success' => true, 'message' => 'Pengeluaran operasional berhasil diajukan, menunggu approval Owner']);
        } else {
            echo json_encode(['success' => true, 'message' => 'Pengeluaran operasional berhasil dicatat']);
        }
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

elseif ($action === 'create_transfer') {
    $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
    $fromCode = $_POST['from_account_code'] ?? '';
    $toCode = $_POST['to_account_code'] ?? '';
    $amount = (float)($_POST['amount'] ?? 0);
    $note = trim($_POST['note'] ?? '');

    if ($fromCode === '' || $toCode === '' || $amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Data transfer tidak lengkap']);
        exit;
    }
    if ($fromCode === $toCode) {
        echo json_encode(['success' => false, 'message' => 'Akun asal dan tujuan tidak boleh sama']);
        exit;
    }

    $fromAcc = finance_get_account_by_code($conn, $fromCode);
    $toAcc = finance_get_account_by_code($conn, $toCode);
    if (!$fromAcc || !$toAcc) {
        echo json_encode(['success' => false, 'message' => 'Akun transfer tidak valid']);
        exit;
    }

    $txStatus = ($userRole === 'Owner') ? 'approved' : 'pending';

    mysqli_begin_transaction($conn);
    try {
        $out = finance_add_transaction(
            $conn,
            $tanggal,
            (int)$fromAcc['id'],
            'transfer_out',
            'TRF-OUT',
            $amount,
            'transfer',
            null,
            $note !== '' ? $note : ('Transfer ke ' . $toAcc['name']),
            (int)$_SESSION['user_id'],
            $txStatus
        );
        if (!$out['success']) {
            throw new Exception($out['message']);
        }

        $in = finance_add_transaction(
            $conn,
            $tanggal,
            (int)$toAcc['id'],
            'transfer_in',
            'TRF-IN',
            $amount,
            'transfer',
            null,
            $note !== '' ? $note : ('Transfer dari ' . $fromAcc['name']),
            (int)$_SESSION['user_id'],
            $txStatus
        );
        if (!$in['success']) {
            throw new Exception($in['message']);
        }

        mysqli_query($conn, "UPDATE finance_transactions SET reference_id = {$in['transaction_id']} WHERE id = {$out['transaction_id']}");
        mysqli_query($conn, "UPDATE finance_transactions SET reference_id = {$out['transaction_id']} WHERE id = {$in['transaction_id']}");

        mysqli_commit($conn);
        if ($txStatus === 'pending') {
            echo json_encode(['success' => true, 'message' => 'Transfer berhasil diajukan, menunggu approval Owner']);
        } else {
            echo json_encode(['success' => true, 'message' => 'Transfer antar akun berhasil']);
        }
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

else {
    echo json_encode(['success' => false, 'message' => 'Action tidak valid']);
}
?>
