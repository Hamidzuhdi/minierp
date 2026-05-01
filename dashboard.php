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

// Cek sparepart dengan harga jual < harga beli
$price_alert_query = "SELECT id, kode_sparepart, nama, harga_beli_default, harga_jual_default 
                       FROM spareparts 
                       WHERE harga_jual_default < harga_beli_default 
                       ORDER BY nama";
$price_alert_result = mysqli_query($conn, $price_alert_query);
$price_alert_items = [];
while ($row = mysqli_fetch_assoc($price_alert_result)) {
    $price_alert_items[] = $row;
}
$price_alert_count = count($price_alert_items);

// =============================================
// 1. DASHBOARD OPERASIONAL (Admin & Owner)
// =============================================

// SPK Stats
$spk_menunggu = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM spk WHERE status_spk = 'Menunggu Konfirmasi'"))['total'];
$spk_pengerjaan = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM spk WHERE status_spk = 'Dalam Pengerjaan'"))['total'];
$spk_selesai = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM spk WHERE status_spk IN ('Selesai', 'Dikirim ke owner')"))['total'];

// Customer reminder 2 bulanan (SPK bukan dibatalkan)
$customer_reminder_count_query = "SELECT COUNT(DISTINCT s.customer_id) as total
                                                                    FROM spk s
                                                                    WHERE s.customer_id IS NOT NULL
                                                                        AND LOWER(COALESCE(s.status_spk, '')) <> 'dibatalkan'
                                                                        AND DATE_ADD(DATE(s.created_at), INTERVAL 2 MONTH) <= CURDATE()";
$customer_reminder_count = mysqli_fetch_assoc(mysqli_query($conn, $customer_reminder_count_query))['total'];
$sparepart_low_stock = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM spareparts WHERE current_stock <= min_stock"))['total'];

// Purchase Stats
$purchase_pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM purchases WHERE status = 'Pending Approval'"))['total'];

// =============================================
// 2. DASHBOARD OWNER (Owner Only)
// =============================================
if ($is_owner) {
    // Omzet bulan ini (dari invoice yang lunas)
    $current_month = date('Y-m');
    $omzet_query = "SELECT COALESCE(SUM(total), 0) as omzet
                    FROM invoices
                    WHERE DATE_FORMAT(tanggal, '%Y-%m') = '$current_month'
                      AND status_piutang = 'Lunas'
                      AND status_piutang <> 'Tidak_Aktif'";
    $omzet = mysqli_fetch_assoc(mysqli_query($conn, $omzet_query))['omzet'];
    
    // Total piutang aktif (invoice belum lunas)
    $piutang_query = "SELECT COALESCE(SUM(i.total - COALESCE(p.total_bayar, 0)), 0) as piutang 
                      FROM invoices i 
                      LEFT JOIN (SELECT invoice_id, SUM(amount) as total_bayar FROM payments GROUP BY invoice_id) p ON i.id = p.invoice_id 
                      WHERE i.status_piutang != 'Lunas'
                        AND i.status_piutang != 'Tidak_Aktif'";
    $piutang = mysqli_fetch_assoc(mysqli_query($conn, $piutang_query))['piutang'];
    
    // Total hutang di PO (purchase yang belum paid)
    $hutang_query = "SELECT COALESCE(SUM(total), 0) as hutang FROM purchases WHERE is_paid = 'Belum Bayar'";
    $hutang = mysqli_fetch_assoc(mysqli_query($conn, $hutang_query))['hutang'];
    
    // Cashflow bulan ini
    $cashflow_masuk_query = "SELECT COALESCE(SUM(p.amount), 0) as masuk
                             FROM payments p
                             JOIN invoices i ON i.id = p.invoice_id
                             WHERE DATE_FORMAT(p.tanggal, '%Y-%m') = '$current_month'
                               AND i.status_piutang <> 'Tidak_Aktif'";
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
    
    <!-- Notifikasi Harga Alert -->
    <?php if ($price_alert_count > 0): ?>
    <div class="row mb-4">
        <div class="col-12">
            <?php if ($is_owner): ?>
            <!-- Notifikasi Detail untuk Owner -->
            <div class="card border-danger shadow-sm">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Peringatan Harga Jual!</h5>
                </div>
                <div class="card-body">
                    <p><strong><?php echo $price_alert_count; ?> sparepart</strong> memiliki harga jual yang lebih rendah dari harga beli (rugi):</p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Kode</th>
                                    <th>Nama Sparepart</th>
                                    <th>Harga Beli</th>
                                    <th>Harga Jual</th>
                                    <th>Selisih</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($price_alert_items as $item): ?>
                                <tr>
                                    <td><?php echo $item['kode_sparepart']; ?></td>
                                    <td><?php echo $item['nama']; ?></td>
                                    <td>Rp <?php echo number_format($item['harga_beli_default'], 0, ',', '.'); ?></td>
                                    <td>Rp <?php echo number_format($item['harga_jual_default'], 0, ',', '.'); ?></td>
                                    <td class="text-danger fw-bold">-Rp <?php echo number_format($item['harga_beli_default'] - $item['harga_jual_default'], 0, ',', '.'); ?></td>
                                    <td><a href="spareparts/index.php?edit_id=<?php echo $item['id']; ?>" class="btn btn-danger btn-sm"><i class="fas fa-edit"></i> Perbaiki</a></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Notifikasi Simple untuk Admin -->
            <div class="card border-warning shadow-sm">
                <div class="card-header bg-warning">
                    <h6 class="mb-0"><i class="fas fa-exclamation-circle"></i> Perhatian</h6>
                </div>
                <div class="card-body">
                    <p class="mb-0">Ada <strong><?php echo $price_alert_count; ?> sparepart</strong> dengan harga jual lebih rendah dari harga beli. Silakan hubungi Owner untuk perbaikan harga.</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
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
            <a href="customers/index.php?reminder=1" class="text-decoration-none text-reset d-block h-100">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Reminder Customer (2 Bulan)</h6>
                                <h2 class="mb-0"><?php echo $customer_reminder_count; ?></h2>
                                <small class="text-primary">Klik untuk lihat detail customer</small>
                            </div>
                            <div class="bg-primary bg-opacity-10 rounded p-3">
                                <i class="fas fa-bell fa-2x text-primary"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
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
    
    <!-- ========================================================= -->
    <!-- FINANCIAL DASHBOARD (Owner Only) -->
    <!-- ========================================================= -->
    <?php if ($is_owner): ?>
    <div class="row mb-3 mt-5">
        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0"><i class="fas fa-chart-bar text-success"></i> Dashboard Keuangan</h5>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <label class="mb-0 fw-semibold">Filter Bulan:</label>
                <select id="financeMonthFilter" class="form-select form-select-sm" style="width:180px;">
                    <option value="">Semua Waktu</option>
                    <?php
                    for ($i = 0; $i < 24; $i++) {
                        $m = date('Y-m', strtotime("-$i months"));
                        echo '<option value="' . $m . '">' . date('F Y', strtotime($m . '-01')) . '</option>';
                    }
                    ?>
                </select>
                <a id="financePdfBtn" href="dashboard_finance_pdf.php" target="_blank" class="btn btn-sm btn-outline-danger">
                    <i class="fas fa-file-pdf"></i> Download PDF
                </a>
            </div>
        </div>
    </div>
    
    <!-- Stat Cards -->
    <div class="row g-3 mb-4" id="financeCardsRow">
        <div class="col-lg-3 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm bg-success text-white h-100">
                <div class="card-body p-3">
                    <div class="small mb-1">Cashflow Masuk</div>
                    <div class="fw-bold" id="fcTotalIn">-</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm bg-danger text-white h-100">
                <div class="card-body p-3">
                    <div class="small mb-1">Cashflow Keluar</div>
                    <div class="fw-bold" id="fcTotalOut">-</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm bg-info text-white h-100">
                <div class="card-body p-3">
                    <div class="small mb-1">Net Cashflow</div>
                    <div class="fw-bold" id="fcNetCashflow">-</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm bg-warning text-dark h-100">
                <div class="card-body p-3">
                    <div class="small mb-1">Sales Discount</div>
                    <div class="fw-bold" id="fcSalesDiscount">-</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm bg-warning text-dark h-100">
                <div class="card-body p-3">
                    <div class="small mb-1">Biaya HPP Sparepart</div>
                    <div class="fw-bold" id="fcSpareHpp">-</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm bg-success text-white h-100">
                <div class="card-body p-3">
                    <div class="small mb-1">Laba Kotor</div>
                    <div class="fw-bold" id="fcLabaKotorFormula">-</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm bg-dark text-white h-100">
                <div class="card-body p-3">
                    <div class="small mb-1">Total Beban Operasional</div>
                    <div class="fw-bold" id="fcTotalBebanOps">-</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm bg-info text-white h-100">
                <div class="card-body p-3">
                    <div class="small mb-1">Zakat 2.5%</div>
                    <div class="fw-bold" id="fcZakat">-</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm bg-success text-white h-100">
                <div class="card-body p-3">
                    <div class="small mb-1">Gross Profit </div>
                    <div class="fw-bold" id="fcGrossProfit">-</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm text-white h-100" style="background-color: #9c27b0;">
                <div class="card-body p-3">
                    <div class="small mb-1">Total Jasa Mekanik</div>
                    <div class="fw-bold" id="fcTotalJasaMekanik">-</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts + Top Sparepart -->
    <div class="row g-3 mb-4">
        <div class="col-md-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">
                    <i class="fas fa-chart-line text-primary"></i> Cashflow per Bulan (12 Bulan Terakhir)
                </div>
                <div class="card-body">
                    <canvas id="financeChart" height="120"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">
                    <i class="fas fa-tags text-warning"></i> Top Kategori Pengeluaran
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr><th>Kategori</th><th class="text-end">Total</th></tr>
                            </thead>
                            <tbody id="topSparepartTable">
                                <tr><td colspan="2" class="text-center text-muted py-3">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Transactions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">
                    <i class="fas fa-history text-secondary"></i> Transaksi Keuangan Terbaru
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Akun</th>
                                    <th>Kategori</th>
                                    <th>Ref</th>
                                    <th>Arah</th>
                                    <th class="text-end">Nominal</th>
                                </tr>
                            </thead>
                            <tbody id="recentTransTable">
                                <tr><td colspan="6" class="text-center text-muted py-3">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
    let financeChart = null;
    
    function fcFormat(num) {
        return 'Rp ' + parseFloat(num).toLocaleString('id-ID', {minimumFractionDigits: 0});
    }

    function financeCategoryLabel(code, expenseName) {
        const labels = {
            'OUT-PO': 'Pengeluaran PO',
            'IN-CUST-SPAREPART': 'Pemasukan SPK Sparepart',
            'IN-CUST-PAYMENT': 'Pembayaran Invoice Customer',
            'TRF-IN': 'Transfer Masuk',
            'TRF-OUT': 'Transfer Keluar'
        };
        if (expenseName) return expenseName;
        return labels[code] || code || '-';
    }

    function updateFinancePdfUrl() {
        let month = $('#financeMonthFilter').val() || '';
        let url = 'dashboard_finance_pdf.php';
        if (month) {
            url += '?month=' + encodeURIComponent(month);
        }
        $('#financePdfBtn').attr('href', url);
    }
    
    function loadFinanceDashboard() {
        let month = $('#financeMonthFilter').val();
        $.ajax({
            url: 'dashboard_finance_api.php',
            data: { month: month },
            dataType: 'json',
            success: function(res) {
                if (!res.success) return;
                
                // Update cards
                $('#fcTotalIn').text(fcFormat(res.summary.total_in));
                $('#fcTotalOut').text(fcFormat(res.summary.total_out));
                $('#fcNetCashflow').text(fcFormat(res.summary.net_cashflow));
                $('#fcSalesDiscount').text(fcFormat(res.summary.sales_discount));
                $('#fcSpareHpp').text(fcFormat(res.summary.spare_hpp));
                $('#fcLabaKotorFormula').text(fcFormat(res.summary.laba_kotor_formula));
                $('#fcZakat').text(fcFormat(res.summary.zakat));
                $('#fcGrossProfit').text(fcFormat(res.summary.gross_profit));
                $('#fcTotalJasaMekanik').text(fcFormat(res.summary.total_jasa_mekanik || 0));
                
                // Update chart
                let labels = res.monthly.map(m => m.label);
                let dataIn = res.monthly.map(m => m.total_in);
                let dataOut = res.monthly.map(m => m.total_out);
                let dataNet = res.monthly.map(m => m.net);
                
                if (financeChart) financeChart.destroy();
                financeChart = new Chart(document.getElementById('financeChart'), {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            { label: 'Cashflow Masuk', data: dataIn, backgroundColor: 'rgba(13,110,253,0.7)' },
                            { label: 'Cashflow Keluar', data: dataOut, backgroundColor: 'rgba(220,53,69,0.7)' },
                            { label: 'Net', data: dataNet, type: 'line', borderColor: '#198754', backgroundColor: 'rgba(25,135,84,0.1)', tension: 0.3, fill: true }
                        ]
                    },
                    options: {
                        responsive: true,
                        plugins: { legend: { position: 'top' } },
                        scales: {
                            y: { beginAtZero: true, ticks: { callback: v => 'Rp ' + v.toLocaleString('id-ID') } }
                        }
                    }
                });
                
                // Update top sparepart table
                let spHtml = '';
                if (res.top_categories.length > 0) {
                    res.top_categories.forEach(function(sp, i) {
                        spHtml += `<tr>
                            <td><span class="badge bg-secondary me-1">${i+1}</span>${financeCategoryLabel(sp.category, sp.category_name)}</td>
                            <td class="text-end text-danger fw-bold">${fcFormat(sp.total_out)}</td>
                        </tr>`;
                    });
                } else {
                    spHtml = '<tr><td colspan="2" class="text-center text-muted">Belum ada data</td></tr>';
                }
                $('#topSparepartTable').html(spHtml);
                
                // Update recent transactions table
                let rtHtml = '';
                if (res.recent.length > 0) {
                    res.recent.forEach(function(t) {
                        let directionBadge = t.direction === 'in' ? 'success' : 'danger';
                        let refText = (t.reference_type || '-') + (t.reference_id ? '#' + t.reference_id : '');
                        rtHtml += `<tr>
                            <td>${t.tanggal}</td>
                            <td>${t.account_name || '-'}</td>
                            <td>${financeCategoryLabel(t.category, t.expense_category_name)}</td>
                            <td>${refText}</td>
                            <td><span class="badge bg-${directionBadge}">${t.direction === 'in' ? 'Masuk' : 'Keluar'}</span></td>
                            <td class="text-end fw-bold ${t.direction === 'in' ? 'text-success' : 'text-danger'}">${fcFormat(t.amount)}</td>
                        </tr>`;
                    });
                } else {
                    rtHtml = '<tr><td colspan="6" class="text-center text-muted">Belum ada data</td></tr>';
                }
                $('#recentTransTable').html(rtHtml);

                updateFinancePdfUrl();
            }
        });
    }
    
    $(document).ready(function() {
        updateFinancePdfUrl();
        loadFinanceDashboard();
        $('#financeMonthFilter').on('change', function() {
            updateFinancePdfUrl();
            loadFinanceDashboard();
        });
    });
    </script>
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
                            <a href="payments/index.php" class="btn btn-outline-primary w-100 p-3">
                                <i class="fas fa-wallet fa-2x d-block mb-2"></i>
                                Payment
                            </a>
                        </div>
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
