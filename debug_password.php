<?php
/**
 * Debug Password Hash
 * Gunakan file ini untuk test password hash
 */

require_once 'config.php';

// Password yang ingin ditest
$test_passwords = ['admin123', 'password', 'admin', '123456'];

// Hash dari database
$hash_from_db = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Debug Password</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body class='bg-light'>
    <div class='container py-5'>
        <div class='card'>
            <div class='card-header bg-warning'>
                <h4 class='mb-0'>Debug Password Hash</h4>
            </div>
            <div class='card-body'>
                <h5>Hash dari Database:</h5>
                <code class='d-block bg-light p-2 mb-3'>$hash_from_db</code>
                
                <h5>Test Password:</h5>
                <table class='table table-bordered'>
                    <thead>
                        <tr>
                            <th>Password</th>
                            <th>Match?</th>
                        </tr>
                    </thead>
                    <tbody>
";

foreach ($test_passwords as $pwd) {
    $match = password_verify($pwd, $hash_from_db);
    $badge = $match ? "<span class='badge bg-success'>✓ COCOK</span>" : "<span class='badge bg-danger'>✗ TIDAK</span>";
    echo "<tr>
            <td><code>$pwd</code></td>
            <td>$badge</td>
          </tr>";
}

echo "
                    </tbody>
                </table>
                
                <hr>
                <h5>Generate Hash Baru untuk 'admin123':</h5>
";

$new_hash = password_hash('admin123', PASSWORD_DEFAULT);

echo "
                <code class='d-block bg-light p-2 mb-3'>$new_hash</code>
                
                <h5>Update Database:</h5>
                <p>Jalankan SQL ini di phpMyAdmin:</p>
                <pre class='bg-dark text-light p-3'><code>UPDATE users 
SET password_hash = '$new_hash' 
WHERE username = 'admin';</code></pre>
";

// Auto update jika mau
if (isset($_GET['auto_update']) && $_GET['auto_update'] == 'yes') {
    $sql = "UPDATE users SET password_hash = '$new_hash' WHERE username = 'admin'";
    if (mysqli_query($conn, $sql)) {
        echo "<div class='alert alert-success mt-3'>
                <strong>✓ Berhasil!</strong> Password sudah diupdate. Sekarang bisa login dengan:<br>
                Username: <code>admin</code><br>
                Password: <code>admin123</code>
              </div>
              <a href='login.php' class='btn btn-primary btn-lg'>Login Sekarang</a>";
    } else {
        echo "<div class='alert alert-danger mt-3'>Error: " . mysqli_error($conn) . "</div>";
    }
} else {
    echo "<div class='alert alert-info mt-3'>
            <strong>Atau klik tombol ini untuk auto-update:</strong>
          </div>
          <a href='?auto_update=yes' class='btn btn-warning btn-lg'>
            <i class='fas fa-magic'></i> Auto Update Password
          </a>";
}

echo "
            </div>
        </div>
    </div>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>
</body>
</html>
";
?>
