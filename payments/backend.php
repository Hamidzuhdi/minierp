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

elseif ($action === 'summary') {
    $cash = finance_get_account_by_code($conn, 'cash');
    $bank = finance_get_account_by_code($conn, 'bank');

    $today = date('Y-m-d');
    $month = date('Y-m');

    $qTodayIn = mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) as total FROM finance_transactions WHERE direction IN ('in','transfer_in') AND tanggal = '$today'");
    $qTodayOut = mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) as total FROM finance_transactions WHERE direction IN ('out','transfer_out') AND tanggal = '$today'");
    $qMonthIn = mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) as total FROM finance_transactions WHERE direction IN ('in','transfer_in') AND DATE_FORMAT(tanggal, '%Y-%m') = '$month'");
    $qMonthOut = mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) as total FROM finance_transactions WHERE direction IN ('out','transfer_out') AND DATE_FORMAT(tanggal, '%Y-%m') = '$month'");

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

    $sql = "SELECT ft.*, fa.code as account_code, fa.name as account_name, u.username as created_by_name
            FROM finance_transactions ft
            JOIN finance_accounts fa ON ft.account_id = fa.id
            LEFT JOIN users u ON ft.created_by = u.id";

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

elseif ($action === 'create_operational_expense') {
    $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
    $expenseName = trim($_POST['expense_name'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $accountCode = $_POST['account_code'] ?? '';
    $note = trim($_POST['note'] ?? '');

    if ($expenseName === '' || $amount <= 0 || $accountCode === '') {
        echo json_encode(['success' => false, 'message' => 'Data pengeluaran operasional tidak lengkap']);
        exit;
    }

    $account = finance_get_account_by_code($conn, $accountCode);
    if (!$account) {
        echo json_encode(['success' => false, 'message' => 'Akun sumber dana tidak valid']);
        exit;
    }

    mysqli_begin_transaction($conn);
    try {
        $tx = finance_add_transaction(
            $conn,
            $tanggal,
            (int)$account['id'],
            'out',
            'operational_expense_out',
            $amount,
            'operational',
            null,
            $note !== '' ? $note : ('Biaya operasional: ' . $expenseName),
            (int)$_SESSION['user_id']
        );

        if (!$tx['success']) {
            throw new Exception($tx['message']);
        }

        $sql = "INSERT INTO operational_expenses (tanggal, expense_name, amount, account_id, note, created_by)
                VALUES ('" . mysqli_real_escape_string($conn, $tanggal) . "',
                        '" . mysqli_real_escape_string($conn, $expenseName) . "',
                        $amount,
                        {$account['id']},
                        '" . mysqli_real_escape_string($conn, $note) . "',
                        " . (int)$_SESSION['user_id'] . ")";

        if (!mysqli_query($conn, $sql)) {
            throw new Exception('Gagal simpan operational expense: ' . mysqli_error($conn));
        }

        $expenseId = mysqli_insert_id($conn);
        mysqli_query($conn, "UPDATE finance_transactions SET reference_id = $expenseId WHERE id = {$tx['transaction_id']}");

        mysqli_commit($conn);
        echo json_encode(['success' => true, 'message' => 'Pengeluaran operasional berhasil dicatat']);
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

    mysqli_begin_transaction($conn);
    try {
        $out = finance_add_transaction(
            $conn,
            $tanggal,
            (int)$fromAcc['id'],
            'transfer_out',
            'transfer_out',
            $amount,
            'transfer',
            null,
            $note !== '' ? $note : ('Transfer ke ' . $toAcc['name']),
            (int)$_SESSION['user_id']
        );
        if (!$out['success']) {
            throw new Exception($out['message']);
        }

        $in = finance_add_transaction(
            $conn,
            $tanggal,
            (int)$toAcc['id'],
            'transfer_in',
            'transfer_in',
            $amount,
            'transfer',
            null,
            $note !== '' ? $note : ('Transfer dari ' . $fromAcc['name']),
            (int)$_SESSION['user_id']
        );
        if (!$in['success']) {
            throw new Exception($in['message']);
        }

        mysqli_query($conn, "UPDATE finance_transactions SET reference_id = {$in['transaction_id']} WHERE id = {$out['transaction_id']}");
        mysqli_query($conn, "UPDATE finance_transactions SET reference_id = {$out['transaction_id']} WHERE id = {$in['transaction_id']}");

        mysqli_commit($conn);
        echo json_encode(['success' => true, 'message' => 'Transfer antar akun berhasil']);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

else {
    echo json_encode(['success' => false, 'message' => 'Action tidak valid']);
}
?>
