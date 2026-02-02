<?php
require 'config.php';

$sql = "SELECT id FROM purchases ORDER BY id DESC LIMIT 1";
$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_assoc($result);
$purchase_id = $row['id'];

echo "Last purchase ID: $purchase_id\n\n";

$sql_items = "SELECT pi.id, pi.sparepart_id, s.nama, pi.qty, pi.harga_beli 
              FROM purchase_items pi 
              LEFT JOIN spareparts s ON pi.sparepart_id = s.id 
              WHERE pi.purchase_id = $purchase_id 
              ORDER BY pi.id";

$result_items = mysqli_query($conn, $sql_items);

while ($item = mysqli_fetch_assoc($result_items)) {
    printf("ID: %3d | Sparepart ID: %2d | Name: %-15s | Qty: %d | Price: %10.2f\n", 
           $item['id'], $item['sparepart_id'], $item['nama'], $item['qty'], $item['harga_beli']);
}
?>
