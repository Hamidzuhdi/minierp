<?php
require 'config.php';

if ($argc < 2) {
    echo "Usage: php check_purchase.php <purchase_id>\n";
    exit;
}

$purchase_id = (int)$argv[1];

echo "=== CHECKING PURCHASE #$purchase_id ===\n\n";

// Check items yang diterima di POST (dari audit trail jika ada)
$sql_items = "SELECT pi.id, pi.sparepart_id, pi.qty, pi.harga_beli, s.nama 
              FROM purchase_items pi
              JOIN spareparts s ON pi.sparepart_id = s.id
              WHERE pi.purchase_id = $purchase_id
              ORDER BY pi.id";

$result = mysqli_query($conn, $sql_items);

echo "Items in database:\n";
while ($row = mysqli_fetch_assoc($result)) {
    echo sprintf("ID: %3d | Sparepart ID: %2d | Name: %-20s | Qty: %d | Price: %.2f\n",
        $row['id'], 
        $row['sparepart_id'], 
        $row['nama'], 
        $row['qty'],
        $row['harga_beli']
    );
}
?>
