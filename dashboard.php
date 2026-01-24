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

$user_role = $_SESSION['role'];
$is_owner = ($user_role === 'Owner');

// =============================================
// 1. DASHBOARD OPERASIONAL (Admin & Owner)
// =============================================

// SPK Stats
$spk_menunggu = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM spk WHERE status_spk = 'Menunggu Konfirmasi'"))['total'];
$spk_pengerjaan = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM spk WHERE status_spk = 'Dalam Pengerjaan'"))['total'];
$spk_selesai = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM spk WHERE status_spk IN ('Selesai', 'Dikirim ke owner')"))['total'];

// Warehouse Stats
$warehouse_pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM warehouse_out WHERE status = 'Pending'"))['total'];
$sparepart_low_stock = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM spareparts WHERE current_stock <= min_stock"))['total'];

// Purchase Stats
$purchase_pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM purchases WHERE status = 'Pending Approval'"))['total'];

// =============================================
// 2. DASHBOARD OWNER (Owner Only)
// =============================================
if ($is_owner) {
    // Omzet bulan ini (dari invoice yang lunas)
    $current_month = date('Y-m');
    $omzet_query = "SELECT COALESCE(SUM(total), 0) as omzet FROM invoices WHERE DATE_FORMAT(tanggal, '%Y-%m') = '$current_month' AND status_piutang = 'Lunas'";
    $omzet = mysqli_fetch_assoc(mysqli_query($conn, $omzet_query))['omzet'];
    
    // Total piutang aktif (invoice belum lunas)
    $piutang_query = "SELECT COALESCE(SUM(i.total - COALESCE(p.total_bayar, 0)), 0) as piutang 
                      FROM invoices i 
                      LEFT JOIN (SELECT invoice_id, SUM(amount) as total_bayar FROM payments GROUP BY invoice_id) p ON i.id = p.invoice_id 
                      WHERE i.status_piutang != 'Lunas'";
    $piutang = mysqli_fetch_assoc(mysqli_query($conn, $piutang_query))['piutang'];
    
    // Total hutang di PO (purchase yang belum paid)
    $hutang_query = "SELECT COALESCE(SUM(total), 0) as hutang FROM purchases WHERE is_paid = 'Belum Bayar'";
    $hutang = mysqli_fetch_assoc(mysqli_query($conn, $hutang_query))['hutang'];
    
    // Cashflow bulan ini
    $cashflow_masuk_query = "SELECT COALESCE(SUM(amount), 0) as masuk FROM payments WHERE DATE_FORMAT(tanggal, '%Y-%m') = '$current_month'";
    $cashflow_masuk = mysqli_fetch_assoc(mysqli_query($conn, $cashflow_masuk_query))['masuk'];
    
    $cashflow_keluar_query = "SELECT COALESCE(SUM(total), 0) as keluar FROM purchases WHERE DATE_FORMAT(tanggal, '%Y-%m') = '$current_month' AND is_paid = 'Sudah Bayar'";
    $cashflow_keluar = mysqli_fetch_assoc(mysqli_query($conn, $cashflow_keluar_query))['keluar'];
    
    // Invoice Stats
    $invoice_belum_bayar = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM invoices WHERE status_piutang = 'Belum Bayar'"))['total'];
    $invoice_dicicil = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM invoices WHERE status_piutang = 'Sudah Dicicil'"))['total'];
    $invoice_lunas = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM invoices WHERE status_piutang = 'Lunas'"))['total'];
}
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h2>Selamat Datang, <?php echo $_SESSION['full_name'] ?: $_SESSION['username']; ?>!</h2>
            <p class="text-muted">Role: <strong><?php echo $user_role; ?></strong></p>
        </div>
    </div>
    
    <!-- ============================================= -->
    <!-- 1. DASHBOARD OPERASIONAL (Admin & Owner) -->
    <!-- ============================================= -->
    <div class="row mb-4">
        <div class="col-12">
            <h5 class="mb-3"><i class="fas fa-tachometer-alt"></i> Dashboard Operasional</h5>
        </div>
    </div>
    
    <!-- SPK Section -->
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">SPK Menunggu Konfirmasi</h6>
                            <h2 class="mb-0"><?php echo $spk_menunggu; ?></h2>
                        </div>
                        <div class="bg-warning bg-opacity-10 rounded p-3">
                            <i class="fas fa-clock fa-2x text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">SPK Dalam Pengerjaan</h6>
                            <h2 class="mb-0"><?php echo $spk_pengerjaan; ?></h2>
                        </div>
                        <div class="bg-info bg-opacity-10 rounded p-3">
                            <i class="fas fa-tools fa-2x text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">SPK Selesai (Siap Invoice)</h6>
                            <h2 class="mb-0"><?php echo $spk_selesai; ?></h2>
                        </div>
                        <div class="bg-success bg-opacity-10 rounded p-3">
                            <i class="fas fa-check-circle fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Warehouse & Purchase Section -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Request Barang Keluar</h6>
                            <h2 class="mb-0"><?php echo $warehouse_pending; ?></h2>
                        </div>
                        <div class="bg-primary bg-opacity-10 rounded p-3">
                            <i class="fas fa-dolly fa-2x text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Sparepart Stok Menipis</h6>
                            <h2 class="mb-0"><?php echo $sparepart_low_stock; ?></h2>
                        </div>
                        <div class="bg-danger bg-opacity-10 rounded p-3">
                            <i class="fas fa-exclamation-triangle fa-2x text-danger"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Purchase Pending Approval</h6>
                            <h2 class="mb-0"><?php echo $purchase_pending; ?></h2>
                        </div>
                        <div class="bg-secondary bg-opacity-10 rounded p-3">
                            <i class="fas fa-shopping-cart fa-2x text-secondary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($is_owner): ?>
    <!-- ============================================= -->
    <!-- 2. DASHBOARD OWNER (Owner Only) -->
    <!-- ============================================= -->
    <div class="row mb-4 mt-5">
        <div class="col-12">
            <h5 class="mb-3"><i class="fas fa-chart-line"></i> Dashboard Owner (Finansial)</h5>
        </div>
    </div>
    
    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-success text-white h-100">
                <div class="card-body">
                    <h6 class="mb-2">Omzet Bulan Ini</h6>
                    <h3 class="mb-0">Rp <?php echo number_format($omzet, 0, ',', '.'); ?></h3>
                    <small>Invoice Lunas</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-warning text-white h-100">
                <div class="card-body">
                    <h6 class="mb-2">Total Piutang Aktif</h6>
                    <h3 class="mb-0">Rp <?php echo number_format($piutang, 0, ',', '.'); ?></h3>
                    <small>Invoice Belum Lunas</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-danger text-white h-100">
                <div class="card-body">
                    <h6 class="mb-2">Total Hutang PO</h6>
                    <h3 class="mb-0">Rp <?php echo number_format($hutang, 0, ',', '.'); ?></h3>
                    <small>Purchase Belum Paid</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-info text-white h-100">
                <div class="card-body">
                    <h6 class="mb-2">Cashflow Bulan Ini</h6>
                    <h4 class="mb-0">Rp <?php echo number_format($cashflow_masuk - $cashflow_keluar, 0, ',', '.'); ?></h4>
                    <small>Masuk: Rp <?php echo number_format($cashflow_masuk, 0, ',', '.'); ?></small><br>
                    <small>Keluar: Rp <?php echo number_format($cashflow_keluar, 0, ',', '.'); ?></small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ============================================= -->
    <!-- 3. DASHBOARD INVOICE & PIUTANG (Owner Only) -->
    <!-- ============================================= -->
    <div class="row mb-4 mt-4">
        <div class="col-12">
            <h5 class="mb-3"><i class="fas fa-file-invoice-dollar"></i> Dashboard Invoice & Piutang</h5>
        </div>
    </div>
    
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm border-start border-danger border-4 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Invoice Belum Bayar</h6>
                            <h2 class="mb-0 text-danger"><?php echo $invoice_belum_bayar; ?></h2>
                        </div>
                        <div class="bg-danger bg-opacity-10 rounded p-3">
                            <i class="fas fa-file-invoice fa-2x text-danger"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm border-start border-warning border-4 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Invoice Dicicil</h6>
                            <h2 class="mb-0 text-warning"><?php echo $invoice_dicicil; ?></h2>
                        </div>
                        <div class="bg-warning bg-opacity-10 rounded p-3">
                            <i class="fas fa-hourglass-half fa-2x text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm border-start border-success border-4 h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Invoice Lunas</h6>
                            <h2 class="mb-0 text-success"><?php echo $invoice_lunas; ?></h2>
                        </div>
                        <div class="bg-success bg-opacity-10 rounded p-3">
                            <i class="fas fa-check-double fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Quick Access Links -->
    <div class="row mt-5">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-link"></i> Quick Access</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <a href="spk/index.php" class="btn btn-outline-primary w-100 p-3">
                                <i class="fas fa-clipboard-list fa-2x d-block mb-2"></i>
                                SPK
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="warehouse_out/index.php" class="btn btn-outline-info w-100 p-3">
                                <i class="fas fa-dolly fa-2x d-block mb-2"></i>
                                Warehouse Out
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="spareparts/index.php" class="btn btn-outline-warning w-100 p-3">
                                <i class="fas fa-cog fa-2x d-block mb-2"></i>
                                Spareparts
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="purchases/index.php" class="btn btn-outline-secondary w-100 p-3">
                                <i class="fas fa-shopping-cart fa-2x d-block mb-2"></i>
                                Purchases
                            </a>
                        </div>
                        <?php if ($is_owner): ?>
                        <div class="col-md-3">
                            <a href="invoices/index.php" class="btn btn-outline-danger w-100 p-3">
                                <i class="fas fa-file-invoice fa-2x d-block mb-2"></i>
                                Invoices
                            </a>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-3">
                            <a href="customers/index.php" class="btn btn-outline-success w-100 p-3">
                                <i class="fas fa-user-tie fa-2x d-block mb-2"></i>
                                Customers
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="vehicles/index.php" class="btn btn-outline-dark w-100 p-3">
                                <i class="fas fa-car fa-2x d-block mb-2"></i>
                                Vehicles
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
