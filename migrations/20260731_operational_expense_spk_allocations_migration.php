<?php
require_once __DIR__ . '/../config.php';

echo "=== Migration: Operational Expense SPK Allocations (untuk OPL / jasa pihak ketiga) ===\n";

$sql = "CREATE TABLE IF NOT EXISTS operational_expense_spk_allocations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    operational_expense_id INT NOT NULL,
    spk_id INT NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_opl_alloc_expense (operational_expense_id),
    INDEX idx_opl_alloc_spk (spk_id),
    CONSTRAINT fk_opl_alloc_expense FOREIGN KEY (operational_expense_id) REFERENCES operational_expenses(id) ON DELETE CASCADE,
    CONSTRAINT fk_opl_alloc_spk FOREIGN KEY (spk_id) REFERENCES spk(id)
) ENGINE=InnoDB";

if (mysqli_query($conn, $sql)) {
    echo "✓ Tabel operational_expense_spk_allocations siap.\n";
} else {
    echo "✗ FAILED: " . mysqli_error($conn) . "\n";
}

echo "DONE.\n";
?>
