<?php
// Set timezone ke WIB (UTC+7)
date_default_timezone_set('Asia/Jakarta');

$host = "localhost";
$user = "u414500059_hcs"; 
$pass = "1209auy_*A";     
$db   = "u414500059_hcs";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

// Set timezone MySQL ke WIB
mysqli_query($conn, "SET time_zone = '+07:00'");
?>
