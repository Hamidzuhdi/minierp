<?php
/**
 * File untuk membuat user admin default
 * Jalankan file ini sekali untuk membuat user admin
 * Akses: http://localhost/minierp/setup_admin.php
 */

require_once 'config.php';

// Data user admin
$username = 'admin';
$password = 'admin123';
$full_name = 'Administrator';
$role = 'Admin';

// Generate password hash
$password_hash = password_hash($password, PASSWORD_DEFAULT);

echo "<!DOCTYPE html>
<html lang='id'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Setup Admin User</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body class='bg-light'>
    <div class='container py-5'>
        <div class='row justify-content-center'>
            <div class='col-md-6'>
                <div class='card shadow'>
                    <div class='card-header bg-primary text-white'>
                        <h4 class='mb-0'>Setup Admin User</h4>
                    </div>
                    <div class='card-body'>
";

// Cek apakah user admin sudah ada
$check = mysqli_query($conn, "SELECT id FROM users WHERE username = 'admin'");

if (mysqli_num_rows($check) > 0) {
    echo "
        <div class='alert alert-warning'>
            <i class='fas fa-exclamation-triangle'></i> User admin sudah ada!
        </div>
        <p>Apakah Anda ingin reset password?</p>
    ";
    
    // Update password
    $update = mysqli_query($conn, "UPDATE users SET password_hash = '$password_hash' WHERE username = 'admin'");
    
    if ($update) {
        echo "
            <div class='alert alert-success'>
                <i class='fas fa-check-circle'></i> Password admin berhasil di-reset!
            </div>
        ";
    } else {
        echo "
            <div class='alert alert-danger'>
                <i class='fas fa-times-circle'></i> Gagal reset password: " . mysqli_error($conn) . "
            </div>
        ";
    }
} else {
    // Insert user baru
    $sql = "INSERT INTO users (username, password_hash, full_name, role) 
            VALUES ('$username', '$password_hash', '$full_name', '$role')";
    
    if (mysqli_query($conn, $sql)) {
        echo "
            <div class='alert alert-success'>
                <i class='fas fa-check-circle'></i> User admin berhasil dibuat!
            </div>
        ";
    } else {
        echo "
            <div class='alert alert-danger'>
                <i class='fas fa-times-circle'></i> Gagal membuat user: " . mysqli_error($conn) . "
            </div>
        ";
    }
}

echo "
                        <hr>
                        <h5>Informasi Login:</h5>
                        <table class='table table-bordered'>
                            <tr>
                                <th width='30%'>Username</th>
                                <td><code>$username</code></td>
                            </tr>
                            <tr>
                                <th>Password</th>
                                <td><code>$password</code></td>
                            </tr>
                            <tr>
                                <th>Role</th>
                                <td><span class='badge bg-primary'>$role</span></td>
                            </tr>
                        </table>
                        
                        <div class='d-grid gap-2'>
                            <a href='login.php' class='btn btn-primary btn-lg'>
                                <i class='fas fa-sign-in-alt'></i> Login Sekarang
                            </a>
                        </div>
                        
                        <hr>
                        <div class='alert alert-info mb-0'>
                            <small>
                                <strong>Info:</strong> Setelah berhasil login, Anda bisa hapus file <code>setup_admin.php</code> ini untuk keamanan.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>
</body>
</html>
";

// Tampilkan password hash untuk referensi
echo "
<!-- 
Password Hash Generated:
$password_hash

Jika ingin insert manual ke database:
INSERT INTO users (username, password_hash, full_name, role) 
VALUES ('$username', '$password_hash', '$full_name', '$role');
-->
";
?>
