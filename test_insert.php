<?php
require 'config.php';

// Create a test purchase first
$test_tanggal = date('Y-m-d');
mysqli_query($conn, "INSERT INTO purchases (tanggal, supplier, total, status, is_paid, created_by) VALUES ('$test_tanggal', 'TEST SUPPLIER', 0, 'Pending Approval', 'Belum Bayar', 1)");
$purchase_id = mysqli_insert_id($conn);

echo "Created test purchase ID: $purchase_id\n\n";

// Test data sama seperti yang dikirim frontend
$items = [
    ['sparepart_id' => 1, 'qty' => 1, 'harga_beli' => 500000],
    ['sparepart_id' => 2, 'qty' => 1, 'harga_beli' => 180000],
    ['sparepart_id' => 3, 'qty' => 1, 'harga_beli' => 50000],
    ['sparepart_id' => 4, 'qty' => 1, 'harga_beli' => 60000]
];

echo "=== TESTING INSERT ===\n\n";

foreach ($items as $index => $item) {
    $barcode = 'NULL';
    
    $sql = "INSERT INTO purchase_items (purchase_id, sparepart_id, qty, harga_beli, barcode) 
            VALUES ($purchase_id, 
                    " . (int)$item['sparepart_id'] . ",
                    " . (int)$item['qty'] . ",
                    " . (float)$item['harga_beli'] . ",
                    $barcode)";
    
    echo "Item #$index:\n";
    echo "  sparepart_id: {$item['sparepart_id']}\n";
    echo "  SQL: $sql\n";
    
    if (mysqli_query($conn, $sql)) {
        $insert_id = mysqli_insert_id($conn);
        echo "  ✓ SUCCESS - Insert ID: $insert_id\n";
        
        // Verify what was inserted
        $check = mysqli_query($conn, "SELECT * FROM purchase_items WHERE id = $insert_id");
        $row = mysqli_fetch_assoc($check);
        echo "  Verified in DB: sparepart_id = {$row['sparepart_id']}\n";
    } else {
        echo "  ✗ ERROR: " . mysqli_error($conn) . "\n";
    }
    echo "\n";
}

// Cleanup
mysqli_query($conn, "DELETE FROM purchase_items WHERE purchase_id = $purchase_id");
mysqli_query($conn, "DELETE FROM purchases WHERE id = $purchase_id");
echo "\nTest data cleaned up.\n";
?>
