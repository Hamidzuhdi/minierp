<?php
session_start();
require_once 'config.php';

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$page_title = "Dashboard";
include 'header.php';

// Ambil statistik
$stats = [];

// Total users
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM users");
$stats['users'] = mysqli_fetch_assoc($result)['total'];

// Total customers
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM customers");
$stats['customers'] = mysqli_fetch_assoc($result)['total'];

// Total vehicles
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM vehicles");
$stats['vehicles'] = mysqli_fetch_assoc($result)['total'];

// Total spareparts
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM spareparts");
$stats['spareparts'] = mysqli_fetch_assoc($result)['total'];

// Spareparts dengan stok menipis
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM spareparts WHERE current_stock <= min_stock");
$stats['low_stock'] = mysqli_fetch_assoc($result)['total'];
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2>Selamat Datang, <?php echo $_SESSION['full_name'] ?: $_SESSION['username']; ?>!</h2>
            <p class="text-muted">Role: <strong><?php echo $_SESSION['role']; ?></strong></p>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Total Users</h6>
                            <h2 class="mb-0"><?php echo $stats['users']; ?></h2>
                        </div>
                        <div class="bg-primary bg-opacity-10 rounded p-3">
                            <i class="fas fa-users fa-2x text-primary"></i>
                        </div>
                    </div>
                    <a href="users/index.php" class="btn btn-sm btn-outline-primary mt-3 w-100">
                        <i class="fas fa-arrow-right"></i> Lihat Detail
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Total Customers</h6>
                            <h2 class="mb-0"><?php echo $stats['customers']; ?></h2>
                        </div>
                        <div class="bg-success bg-opacity-10 rounded p-3">
                            <i class="fas fa-user-tie fa-2x text-success"></i>
                        </div>
                    </div>
                    <a href="customers/index.php" class="btn btn-sm btn-outline-success mt-3 w-100">
                        <i class="fas fa-arrow-right"></i> Lihat Detail
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Total Vehicles</h6>
                            <h2 class="mb-0"><?php echo $stats['vehicles']; ?></h2>
                        </div>
                        <div class="bg-info bg-opacity-10 rounded p-3">
                            <i class="fas fa-car fa-2x text-info"></i>
                        </div>
                    </div>
                    <a href="vehicles/index.php" class="btn btn-sm btn-outline-info mt-3 w-100">
                        <i class="fas fa-arrow-right"></i> Lihat Detail
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Total Spareparts</h6>
                            <h2 class="mb-0"><?php echo $stats['spareparts']; ?></h2>
                        </div>
                        <div class="bg-warning bg-opacity-10 rounded p-3">
                            <i class="fas fa-cog fa-2x text-warning"></i>
                        </div>
                    </div>
                    <a href="spareparts/index.php" class="btn btn-sm btn-outline-warning mt-3 w-100">
                        <i class="fas fa-arrow-right"></i> Lihat Detail
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Alert Stok Menipis -->
    <?php if ($stats['low_stock'] > 0): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-warning d-flex align-items-center" role="alert">
                <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                <div>
                    <h5 class="alert-heading mb-1">Peringatan Stok Menipis!</h5>
                    <p class="mb-0">
                        Terdapat <strong><?php echo $stats['low_stock']; ?> sparepart</strong> dengan stok di bawah minimum.
                        <a href="spareparts/index.php" class="alert-link">Lihat Detail</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Quick Links -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Quick Links</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <a href="users/index.php" class="btn btn-outline-primary w-100 p-3">
                                <i class="fas fa-users fa-2x d-block mb-2"></i>
                                Manajemen User
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="customers/index.php" class="btn btn-outline-success w-100 p-3">
                                <i class="fas fa-user-tie fa-2x d-block mb-2"></i>
                                Manajemen Customer
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="vehicles/index.php" class="btn btn-outline-info w-100 p-3">
                                <i class="fas fa-car fa-2x d-block mb-2"></i>
                                Manajemen Kendaraan
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="spareparts/index.php" class="btn btn-outline-warning w-100 p-3">
                                <i class="fas fa-cog fa-2x d-block mb-2"></i>
                                Manajemen Sparepart
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="purchases/index.php" class="btn btn-outline-secondary w-100 p-3">
                                <i class="fas fa-shopping-cart fa-2x d-block mb-2"></i>
                                Purchases
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="spk/index.php" class="btn btn-outline-dark w-100 p-3">
                                <i class="fas fa-clipboard-list fa-2x d-block mb-2"></i>
                                SPK
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="warehouse_out/index.php" class="btn btn-outline-primary w-100 p-3">
                                <i class="fas fa-dolly fa-2x d-block mb-2"></i>
                                Warehouse Out
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="invoices/index.php" class="btn btn-outline-danger w-100 p-3">
                                <i class="fas fa-file-invoice fa-2x d-block mb-2"></i>
                                Invoices
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
