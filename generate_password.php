<?php
/**
 * Helper untuk generate password hash
 * Gunakan file ini untuk membuat password hash baru
 */

// Ganti dengan password yang ingin di-hash
$password = 'admin123';

// Generate hash
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Password: $password\n";
echo "Hash: $hash\n";
echo "\n";
echo "Copy hash di atas untuk dimasukkan ke database\n";
?>
