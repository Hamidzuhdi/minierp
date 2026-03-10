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
    
    <!-- ========================================================= -->
    <!-- FINANCIAL DASHBOARD (Owner Only) -->
    <!-- ========================================================= -->
    <?php if ($is_owner): ?>
    <div class="row mb-3 mt-5">
        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0"><i class="fas fa-chart-bar text-success"></i> Dashboard Keuangan</h5>
            <div class="d-flex align-items-center gap-2">
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
            </div>
        </div>
    </div>
    
    <!-- Stat Cards -->
    <div class="row g-3 mb-4" id="financeCardsRow">
        <div class="col-md-2 col-sm-4">
            <div class="card border-0 shadow-sm bg-primary text-white h-100">
                <div class="card-body p-3">
                    <div class="small mb-1">Revenue Jasa</div>
                    <div class="fw-bold" id="fcRevenueJasa">-</div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4">
            <div class="card border-0 shadow-sm bg-info text-white h-100">
                <div class="card-body p-3">
                    <div class="small mb-1">Revenue Sparepart</div>
                    <div class="fw-bold" id="fcRevenueSpare">-</div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4">
            <div class="card border-0 shadow-sm bg-warning text-dark h-100">
                <div class="card-body p-3">
                    <div class="small mb-1">HPP Sparepart</div>
                    <div class="fw-bold" id="fcHpp">-</div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4">
            <div class="card border-0 shadow-sm bg-success text-white h-100">
                <div class="card-body p-3">
                    <div class="small mb-1">Profit Sparepart</div>
                    <div class="fw-bold" id="fcProfit">-</div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4">
            <div class="card border-0 shadow-sm bg-secondary text-white h-100">
                <div class="card-body p-3">
                    <div class="small mb-1">Total Revenue</div>
                    <div class="fw-bold" id="fcTotalRevenue">-</div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4">
            <div class="card border-0 shadow-sm bg-dark text-white h-100">
                <div class="card-body p-3">
                    <div class="small mb-1">Cashflow Masuk</div>
                    <div class="fw-bold" id="fcCashflow">-</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts + Top Sparepart -->
    <div class="row g-3 mb-4">
        <div class="col-md-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">
                    <i class="fas fa-chart-line text-primary"></i> Revenue & Profit per Bulan (12 Bulan Terakhir)
                </div>
                <div class="card-body">
                    <canvas id="financeChart" height="120"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">
                    <i class="fas fa-trophy text-warning"></i> Top Sparepart by Profit
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr><th>Nama</th><th class="text-end">Profit</th></tr>
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
                    <i class="fas fa-history text-secondary"></i> Transaksi Invoice Terbaru
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Kode SPK</th>
                                    <th>Tanggal</th>
                                    <th>Customer</th>
                                    <th>Kendaraan</th>
                                    <th class="text-end">Total</th>
                                    <th>Status</th>
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
    
    function loadFinanceDashboard() {
        let month = $('#financeMonthFilter').val();
        $.ajax({
            url: 'dashboard_finance_api.php',
            data: { month: month },
            dataType: 'json',
            success: function(res) {
                if (!res.success) return;
                
                // Update cards
                $('#fcRevenueJasa').text(fcFormat(res.summary.revenue_jasa));
                $('#fcRevenueSpare').text(fcFormat(res.summary.revenue_spare));
                $('#fcHpp').text(fcFormat(res.summary.hpp_spare));
                $('#fcProfit').text(fcFormat(res.summary.profit_spare));
                $('#fcTotalRevenue').text(fcFormat(res.summary.total_revenue));
                $('#fcCashflow').text(fcFormat(res.summary.cashflow_masuk));
                
                // Update chart
                let labels = res.monthly.map(m => m.label);
                let dataRevJasa = res.monthly.map(m => m.revenue_jasa);
                let dataRevSpare = res.monthly.map(m => m.revenue_spare);
                let dataProfit = res.monthly.map(m => m.profit);
                
                if (financeChart) financeChart.destroy();
                financeChart = new Chart(document.getElementById('financeChart'), {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            { label: 'Revenue Jasa', data: dataRevJasa, backgroundColor: 'rgba(13,110,253,0.7)' },
                            { label: 'Revenue Sparepart', data: dataRevSpare, backgroundColor: 'rgba(13,202,240,0.7)' },
                            { label: 'Profit', data: dataProfit, type: 'line', borderColor: '#198754', backgroundColor: 'rgba(25,135,84,0.1)', tension: 0.3, fill: true }
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
                if (res.top_spareparts.length > 0) {
                    res.top_spareparts.forEach(function(sp, i) {
                        let profitColor = parseFloat(sp.total_profit) >= 0 ? 'text-success' : 'text-danger';
                        spHtml += `<tr>
                            <td><span class="badge bg-secondary me-1">${i+1}</span>${sp.nama}</td>
                            <td class="text-end ${profitColor} fw-bold">${fcFormat(sp.total_profit)}</td>
                        </tr>`;
                    });
                } else {
                    spHtml = '<tr><td colspan="2" class="text-center text-muted">Belum ada data</td></tr>';
                }
                $('#topSparepartTable').html(spHtml);
                
                // Update recent transactions table
                let rtHtml = '';
                const statusColors = { 'Lunas': 'success', 'Sudah Dicicil': 'warning', 'Belum Bayar': 'danger' };
                if (res.recent.length > 0) {
                    res.recent.forEach(function(t) {
                        let color = statusColors[t.status_piutang] || 'secondary';
                        rtHtml += `<tr>
                            <td><a href="spk/index.php">${t.kode_unik_reference}</a></td>
                            <td>${t.tanggal}</td>
                            <td>${t.customer_name}</td>
                            <td>${t.nomor_polisi} ${t.model || ''}</td>
                            <td class="text-end">${fcFormat(t.total)}</td>
                            <td><span class="badge bg-${color}">${t.status_piutang}</span></td>
                        </tr>`;
                    });
                } else {
                    rtHtml = '<tr><td colspan="6" class="text-center text-muted">Belum ada data</td></tr>';
                }
                $('#recentTransTable').html(rtHtml);
            }
        });
    }
    
    $(document).ready(function() {
        loadFinanceDashboard();
        $('#financeMonthFilter').on('change', loadFinanceDashboard);
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
