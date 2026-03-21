<?php

function finance_ensure_default_accounts(mysqli $conn): void
{
    mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS finance_accounts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(20) NOT NULL UNIQUE,
            name VARCHAR(100) NOT NULL,
            opening_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
            current_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
    ");

    mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS finance_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tanggal DATE NOT NULL,
            account_id INT NOT NULL,
            direction ENUM('in','out','transfer_in','transfer_out') NOT NULL,
            category VARCHAR(50) NOT NULL,
            amount DECIMAL(14,2) NOT NULL,
            reference_type VARCHAR(30) NULL,
            reference_id INT NULL,
            note TEXT NULL,
            created_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_finance_tx_tanggal (tanggal),
            INDEX idx_finance_tx_account (account_id),
            INDEX idx_finance_tx_ref (reference_type, reference_id),
            CONSTRAINT fk_finance_tx_account FOREIGN KEY (account_id) REFERENCES finance_accounts(id)
        ) ENGINE=InnoDB
    ");

    mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS operational_expenses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tanggal DATE NOT NULL,
            expense_name VARCHAR(255) NOT NULL,
            category_code VARCHAR(30) NULL,
            amount DECIMAL(14,2) NOT NULL,
            account_id INT NOT NULL,
            note TEXT NULL,
            created_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_operational_tanggal (tanggal),
            INDEX idx_operational_account (account_id),
            CONSTRAINT fk_operational_account FOREIGN KEY (account_id) REFERENCES finance_accounts(id)
        ) ENGINE=InnoDB
    ");

    mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS expense_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(30) NOT NULL UNIQUE,
            name VARCHAR(120) NOT NULL,
            description TEXT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
    ");

    $colCheck = mysqli_query($conn, "SHOW COLUMNS FROM operational_expenses LIKE 'category_code'");
    if ($colCheck && mysqli_num_rows($colCheck) === 0) {
        mysqli_query($conn, "ALTER TABLE operational_expenses ADD COLUMN category_code VARCHAR(30) NULL AFTER expense_name");
    }

    mysqli_query($conn, "
        INSERT INTO finance_accounts (code, name, opening_balance, current_balance, is_active)
        SELECT 'cash', 'Kas Bengkel', 500000, 500000, 1
        WHERE NOT EXISTS (SELECT 1 FROM finance_accounts WHERE code = 'cash')
    ");

    mysqli_query($conn, "
        INSERT INTO finance_accounts (code, name, opening_balance, current_balance, is_active)
        SELECT 'bank', 'Rekening Bengkel', 12000000, 12000000, 1
        WHERE NOT EXISTS (SELECT 1 FROM finance_accounts WHERE code = 'bank')
    ");

    mysqli_query($conn, "
        INSERT INTO expense_categories (code, name, description, is_active)
        SELECT 'EXP-LISTRIK', 'Biaya Listrik', 'Tagihan listrik operasional bengkel', 1
        WHERE NOT EXISTS (SELECT 1 FROM expense_categories WHERE code = 'EXP-LISTRIK')
    ");

    mysqli_query($conn, "
        INSERT INTO expense_categories (code, name, description, is_active)
        SELECT 'EXP-PDAM', 'Biaya PDAM', 'Tagihan air operasional bengkel', 1
        WHERE NOT EXISTS (SELECT 1 FROM expense_categories WHERE code = 'EXP-PDAM')
    ");

    mysqli_query($conn, "
        INSERT INTO expense_categories (code, name, description, is_active)
        SELECT 'EXP-ATK', 'ATK & Kertas Nota', 'Pembelian alat tulis kantor dan nota', 1
        WHERE NOT EXISTS (SELECT 1 FROM expense_categories WHERE code = 'EXP-ATK')
    ");
}

function finance_get_account_by_code(mysqli $conn, string $code): ?array
{
    $code = mysqli_real_escape_string($conn, $code);
    $res = mysqli_query($conn, "SELECT * FROM finance_accounts WHERE code = '$code' AND is_active = 1 LIMIT 1");
    if (!$res) {
        return null;
    }
    $row = mysqli_fetch_assoc($res);
    return $row ?: null;
}

function finance_get_account_by_id(mysqli $conn, int $id): ?array
{
    $res = mysqli_query($conn, "SELECT * FROM finance_accounts WHERE id = $id AND is_active = 1 LIMIT 1");
    if (!$res) {
        return null;
    }
    $row = mysqli_fetch_assoc($res);
    return $row ?: null;
}

function finance_add_transaction(
    mysqli $conn,
    string $tanggal,
    int $accountId,
    string $direction,
    string $category,
    float $amount,
    ?string $referenceType,
    ?int $referenceId,
    ?string $note,
    ?int $createdBy
): array {
    $allowedDirections = ['in', 'out', 'transfer_in', 'transfer_out'];
    if (!in_array($direction, $allowedDirections, true)) {
        return ['success' => false, 'message' => 'Direction tidak valid'];
    }
    if ($amount <= 0) {
        return ['success' => false, 'message' => 'Amount harus lebih dari 0'];
    }

    $account = finance_get_account_by_id($conn, $accountId);
    if (!$account) {
        return ['success' => false, 'message' => 'Account tidak ditemukan'];
    }

    $isOut = in_array($direction, ['out', 'transfer_out'], true);
    $delta = $isOut ? -$amount : $amount;
    $newBalance = (float)$account['current_balance'] + $delta;

    if ($newBalance < 0) {
        return ['success' => false, 'message' => 'Saldo tidak mencukupi untuk transaksi ini'];
    }

    $categoryEsc = mysqli_real_escape_string($conn, $category);
    $noteEsc = mysqli_real_escape_string($conn, (string)$note);
    $referenceTypeSql = $referenceType ? ("'" . mysqli_real_escape_string($conn, $referenceType) . "'") : 'NULL';
    $referenceIdSql = $referenceId ? (string)$referenceId : 'NULL';
    $createdBySql = $createdBy ? (string)$createdBy : 'NULL';
    $directionEsc = mysqli_real_escape_string($conn, $direction);

    $sqlInsert = "INSERT INTO finance_transactions 
        (tanggal, account_id, direction, category, amount, reference_type, reference_id, note, created_by)
        VALUES
        ('" . mysqli_real_escape_string($conn, $tanggal) . "', $accountId, '$directionEsc', '$categoryEsc', $amount, $referenceTypeSql, $referenceIdSql, '$noteEsc', $createdBySql)";

    if (!mysqli_query($conn, $sqlInsert)) {
        return ['success' => false, 'message' => 'Gagal insert transaksi keuangan: ' . mysqli_error($conn)];
    }

    $sqlBalance = "UPDATE finance_accounts SET current_balance = $newBalance WHERE id = $accountId";
    if (!mysqli_query($conn, $sqlBalance)) {
        return ['success' => false, 'message' => 'Gagal update saldo akun: ' . mysqli_error($conn)];
    }

    return [
        'success' => true,
        'transaction_id' => mysqli_insert_id($conn),
        'new_balance' => $newBalance,
    ];
}

function finance_reverse_transaction(mysqli $conn, int $transactionId): array
{
    $res = mysqli_query($conn, "SELECT * FROM finance_transactions WHERE id = $transactionId LIMIT 1");
    if (!$res || mysqli_num_rows($res) === 0) {
        return ['success' => false, 'message' => 'Transaksi keuangan tidak ditemukan'];
    }

    $tx = mysqli_fetch_assoc($res);
    $account = finance_get_account_by_id($conn, (int)$tx['account_id']);
    if (!$account) {
        return ['success' => false, 'message' => 'Account transaksi tidak ditemukan'];
    }

    $amount = (float)$tx['amount'];
    $direction = $tx['direction'];
    $isOut = in_array($direction, ['out', 'transfer_out'], true);

    // reverse: if old was out then add back, else subtract
    $newBalance = $isOut
        ? ((float)$account['current_balance'] + $amount)
        : ((float)$account['current_balance'] - $amount);

    if ($newBalance < 0) {
        return ['success' => false, 'message' => 'Saldo tidak valid saat reverse transaksi'];
    }

    if (!mysqli_query($conn, "UPDATE finance_accounts SET current_balance = $newBalance WHERE id = {$tx['account_id']}")) {
        return ['success' => false, 'message' => 'Gagal rollback saldo: ' . mysqli_error($conn)];
    }

    if (!mysqli_query($conn, "DELETE FROM finance_transactions WHERE id = $transactionId")) {
        return ['success' => false, 'message' => 'Gagal hapus transaksi keuangan: ' . mysqli_error($conn)];
    }

    return ['success' => true, 'new_balance' => $newBalance];
}
