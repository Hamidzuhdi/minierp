<?php
session_start();
require_once '../config.php';

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_role = $_SESSION['role'] ?? 'Admin';
$is_owner = ($user_role === 'Owner');

// Hanya Owner yang bisa akses halaman invoice
if (!$is_owner) {
    header('Location: ../dashboard.php');
    exit;
}

$page_title = "Invoice & Piutang";
include '../header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Data Invoice & Piutang</h5>
                    <?php if ($is_owner): ?>
                    <button class="btn btn-primary btn-sm" onclick="openCreateInvoiceModal()">
                        <i class="fas fa-plus"></i> Buat Invoice
                    </button>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="searchInput" placeholder="Cari invoice, SPK, customer, atau nomor polisi...">
                        </div>
                        <div class="col-md-6">
                            <select class="form-select" id="statusFilter">
                                <option value="">Semua Status</option>
                                <option value="Belum Bayar">Belum Bayar</option>
                                <option value="Sudah Dicicil">Sudah Dicicil</option>
                                <option value="Lunas">Lunas</option>
                            </select>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>SPK</th>
                                    <th>Customer</th>
                                    <th>Kendaraan</th>
                                    <th>Terbayar</th>
                                    <th>Sisa</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="invoiceTableBody">
                                <tr>
                                    <td colspan="8" class="text-center">Loading...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Create Invoice -->
<?php if ($is_owner): ?>
<div class="modal fade" id="createInvoiceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Buat Invoice dari SPK</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> <strong>Alur:</strong>
                    <ol class="mb-0 mt-2">
                        <li>Buat Invoice dari SPK yang statusnya <strong>"Sudah Cetak Invoice"</strong></li>
                        <li>Invoice akan masuk dengan status <strong>"Belum Bayar"</strong></li>
                        <li>Input pembayaran: Cash (langsung lunas) atau Cicilan (bertahap)</li>
                        <li>Status otomatis berubah: Belum Bayar → Sudah Dicicil → Lunas</li>
                    </ol>
                </div>
                <div class="mb-3">
                    <label for="spk_select" class="form-label">Pilih SPK *</label>
                    <select class="form-select" id="spk_select" required>
                        <option value="">-- Pilih SPK --</option>
                    </select>
                </div>
                <div id="spk_preview" style="display:none;">
                    <hr>
                    <h6>Preview Invoice</h6>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>SPK:</strong> <span id="preview_spk_code"></span><br>
                            <strong>Customer:</strong> <span id="preview_customer"></span>
                        </div>
                        <div class="col-md-6">
                            <strong>Kendaraan:</strong> <span id="preview_vehicle"></span>
                        </div>
                    </div>
                    
                    <h6 class="mt-3">Detail Sparepart & Jasa</h6>
                    <table class="table table-bordered table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Sparepart</th>
                                <th class="text-center" width="15%">Qty</th>
                                <th class="text-end" width="20%">Harga</th>
                                <th class="text-end" width="20%">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody id="preview_items">
                            <tr><td colspan="4" class="text-center">Loading...</td></tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-end"><strong>Total Sparepart:</strong></td>
                                <td class="text-end" id="preview_total_sparepart"><strong>Rp 0</strong></td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-end"><strong>Biaya Jasa:</strong></td>
                                <td class="text-end" id="preview_biaya_jasa"><strong>Rp 0</strong></td>
                            </tr>
                            <tr class="table-primary">
                                <td colspan="3" class="text-end"><strong>GRAND TOTAL:</strong></td>
                                <td class="text-end" id="preview_grand_total"><strong>Rp 0</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" onclick="createInvoice()" id="btnCreateInvoice" disabled>Buat Invoice</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal Detail Invoice -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Invoice</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailContent">
                <!-- Detail akan diisi via AJAX -->
            </div>
        </div>
    </div>
</div>

<!-- Modal Input Pembayaran -->
<?php if ($is_owner): ?>
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Input Pembayaran / Cicilan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="paymentForm">
                <div class="modal-body">
                    <input type="hidden" id="payment_invoice_id" name="invoice_id">
                    
                    <div class="alert alert-info" id="payment_info">
                        <strong>Sisa Piutang:</strong> <span id="payment_sisa">Rp 0</span><br>
                        <small class="text-muted">
                            <i class="fas fa-info-circle"></i> Input pembayaran sesuai yang diterima. 
                            Bayar penuh = Lunas, Bayar sebagian = Cicilan. Status otomatis terupdate.
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="amount" class="form-label">Jumlah Bayar *</label>
                        <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_date" class="form-label">Tanggal Bayar *</label>
                        <input type="date" class="form-control" id="payment_date" name="payment_date" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_method" class="form-label">Metode Pembayaran *</label>
                        <select class="form-select" id="payment_method" name="payment_method" required>
                            <option value="">-- Pilih Metode --</option>
                            <option value="Cash">Cash</option>
                            <option value="Transfer">Transfer</option>
                            <option value="Kartu Kredit">Kartu Kredit</option>
                            <option value="Kartu Debit">Kartu Debit</option>
                            <option value="E-Wallet">E-Wallet</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="notes" class="form-label">Catatan (Opsional)</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Pembayaran</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include '../footer.php'; ?>

<script>
const userRole = '<?php echo $user_role; ?>';
const isOwner = (userRole === 'Owner');
let currentInvoiceId = null;

$(document).ready(function() {
    loadInvoices();
    
    if (isOwner) {
        loadSPKReady();
        $('#payment_date').val(new Date().toISOString().split('T')[0]);
    }
    
    // Search dengan delay
    let searchTimeout;
    $('#searchInput').on('keyup', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            loadInvoices();
        }, 500);
    });
    
    // Filter status
    $('#statusFilter').on('change', function() {
        loadInvoices();
    });
    
    // SPK select change
    $('#spk_select').on('change', function() {
        let spkId = $(this).val();
        let selectedOption = $(this).find(':selected');
        
        if (spkId) {
            // Set basic info
            $('#preview_spk_code').text(selectedOption.data('code'));
            $('#preview_customer').text(selectedOption.data('customer'));
            $('#preview_vehicle').text(selectedOption.data('vehicle'));
            
            // Load detail SPK items
            loadSPKPreview(spkId);
            
            $('#spk_preview').show();
            $('#btnCreateInvoice').prop('disabled', false);
        } else {
            $('#spk_preview').hide();
            $('#btnCreateInvoice').prop('disabled', true);
        }
    });
});

// Load semua invoice
function loadInvoices() {
    let search = $('#searchInput').val();
    let status = $('#statusFilter').val();
    
    $.ajax({
        url: 'backend.php?action=read&search=' + encodeURIComponent(search) + '&status=' + encodeURIComponent(status),
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displayInvoices(response.data);
            } else {
                showAlert('danger', 'Gagal memuat data invoice');
            }
        }
    });
}

// Tampilkan data invoice di tabel
function displayInvoices(invoices) {
    let html = '';
    
    if (invoices.length === 0) {
        html = '<tr><td colspan="8" class="text-center">Belum ada data invoice</td></tr>';
    } else {
        invoices.forEach(function(inv) {
            let statusBadge = '';
            if (inv.status_piutang === 'Lunas') {
                statusBadge = '<span class="badge bg-success">Lunas</span>';
            } else if (inv.status_piutang === 'Sudah Dicicil') {
                statusBadge = '<span class="badge bg-warning">Sudah Dicicil</span>';
            } else {
                statusBadge = '<span class="badge bg-danger">Belum Bayar</span>';
            }
            
            html += `
                <tr>
                    <td>${inv.id}</td>
                    <td><small>${inv.spk_code}</small></td>
                    <td>${inv.customer_name}<br><small class="text-muted">${inv.customer_phone}</small></td>
                    <td><small>${inv.nomor_polisi}<br>${inv.merk} ${inv.model}</small></td>
                    <td class="text-success">Rp ${formatNumber(inv.total_paid)}</td>
                    <td class="text-danger">Rp ${formatNumber(inv.sisa_piutang)}</td>
                    <td>${statusBadge}</td>
                    <td>
                        <button class="btn btn-info btn-sm" onclick="viewDetail(${inv.id})" title="Detail">
                            <i class="fas fa-eye"></i>
                        </button>
                        ${inv.status_piutang !== 'Lunas' ? `
                        <button class="btn btn-success btn-sm" onclick="openPaymentModal(${inv.id}, ${inv.sisa_piutang})" title="Input Bayar">
                            <i class="fas fa-money-bill"></i>
                        </button>
                        ` : ''}
                    </td>
                </tr>
            `;
        });
    }
    
    $('#invoiceTableBody').html(html);
}

// Load preview detail SPK
function loadSPKPreview(spkId) {
    console.log('Loading SPK preview for ID:', spkId);
    $.ajax({
        url: '../spk/backend.php?action=read_one&id=' + spkId,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            console.log('SPK Preview response:', response);
            if (response.success) {
                let spk = response.data;
                let itemsHtml = '';
                let totalSparepart = 0;
                
                // Jasa Services
                if (spk.services && spk.services.length > 0) {
                    itemsHtml += '<tr><td colspan="4" class="table-secondary"><strong>Jasa Service</strong></td></tr>';
                    spk.services.forEach(function(svc) {
                        let subtotal = parseFloat(svc.subtotal);
                        totalSparepart += subtotal;
                        
                        itemsHtml += `
                            <tr>
                                <td>${svc.nama_jasa}</td>
                                <td class="text-center">${svc.qty}</td>
                                <td class="text-end">Rp ${formatNumber(svc.harga)}</td>
                                <td class="text-end">Rp ${formatNumber(subtotal)}</td>
                            </tr>
                        `;
                    });
                }
                
                // Spareparts
                if (spk.items && spk.items.length > 0) {
                    itemsHtml += '<tr><td colspan="4" class="table-secondary"><strong>Sparepart</strong></td></tr>';
                    spk.items.forEach(function(item) {
                        let subtotal = parseFloat(item.subtotal);
                        totalSparepart += subtotal;
                        
                        itemsHtml += `
                            <tr>
                                <td>${item.sparepart_name}</td>
                                <td class="text-center">${item.qty} ${item.satuan}</td>
                                <td class="text-end">Rp ${formatNumber(item.harga_jual_default)}</td>
                                <td class="text-end">Rp ${formatNumber(subtotal)}</td>
                            </tr>
                        `;
                    });
                }
                
                if (itemsHtml === '') {
                    itemsHtml = '<tr><td colspan="4" class="text-center text-muted">Tidak ada data</td></tr>';
                }
                
                let grandTotal = totalSparepart;
                
                $('#preview_items').html(itemsHtml);
                $('#preview_total_sparepart').html('<strong>Rp ' + formatNumber(totalSparepart) + '</strong>');
                $('#preview_biaya_jasa').html('<strong>Rp 0</strong>');
                $('#preview_grand_total').html('<strong>Rp ' + formatNumber(grandTotal) + '</strong>');
            } else {
                $('#preview_items').html('<tr><td colspan="4" class="text-center text-danger">Gagal memuat data</td></tr>');
            }
        },
        error: function() {
            $('#preview_items').html('<tr><td colspan="4" class="text-center text-danger">Error loading data</td></tr>');
        }
    });
}

// Load SPK yang siap dibuatkan invoice
function loadSPKReady() {
    $.ajax({
        url: 'backend.php?action=get_spk_ready',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            console.log('SPK Ready response:', response);
            if (response.success) {
                let options = '<option value="">-- Pilih SPK --</option>';
                response.data.forEach(function(spk) {
                    options += `<option value="${spk.id}" 
                                data-code="${spk.kode_unik_reference}"
                                data-customer="${spk.customer_name}"
                                data-vehicle="${spk.nomor_polisi}">
                                ${spk.kode_unik_reference} - ${spk.customer_name} (${spk.nomor_polisi})
                                </option>`;
                });
                $('#spk_select').html(options);
            } else {
                showAlert('warning', response.message || 'Tidak ada SPK yang siap dibuatkan invoice');
            }
        },
        error: function(xhr, status, error) {
            console.error('Load SPK Ready Error:', {xhr: xhr, status: status, error: error});
            showAlert('danger', 'Gagal memuat daftar SPK: ' + error);
        }
    });
}

// Open modal create invoice
function openCreateInvoiceModal() {
    loadSPKReady();
    $('#spk_preview').hide();
    $('#spk_select').val('');
    $('#btnCreateInvoice').prop('disabled', true);
    $('#createInvoiceModal').modal('show');
}

// Create invoice
function createInvoice() {
    let spk_id = $('#spk_select').val();
    
    if (!spk_id) {
        showAlert('warning', 'Pilih SPK terlebih dahulu');
        return;
    }
    
    if (!confirm('Yakin ingin membuat invoice untuk SPK ini?')) {
        return;
    }
    
    // Disable button to prevent double click
    $('#btnCreateInvoice').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Memproses...');
    
    $.ajax({
        url: 'backend.php',
        type: 'POST',
        data: {
            action: 'create_invoice',
            spk_id: spk_id
        },
        dataType: 'json',
        success: function(response) {
            console.log('Create invoice response:', response);
            if (response.success) {
                showAlert('success', response.message);
                $('#createInvoiceModal').modal('hide');
                loadInvoices();
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', {xhr: xhr, status: status, error: error});
            console.error('Response Text:', xhr.responseText);
            showAlert('danger', 'Terjadi kesalahan: ' + error + '. Periksa console untuk detail.');
        },
        complete: function() {
            // Re-enable button
            $('#btnCreateInvoice').prop('disabled', false).html('Buat Invoice');
        }
    });
}

// View detail invoice
function viewDetail(id) {
    $.ajax({
        url: 'backend.php?action=read_one&id=' + id,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displayInvoiceDetail(response.data);
            }
        }
    });
}

// Display invoice detail
function displayInvoiceDetail(inv) {
    let statusBadge = inv.status_piutang === 'Lunas' ? 'success' : (inv.status_piutang === 'Sudah Dicicil' ? 'warning' : 'danger');
    
    // Services table
    let servicesHtml = '';
    if (inv.services && inv.services.length > 0) {
        inv.services.forEach(function(svc) {
            servicesHtml += `
                <tr>
                    <td>${svc.nama_jasa}</td>
                    <td class="text-center">${svc.qty}x</td>
                    <td class="text-end">Rp ${formatNumber(svc.harga)}</td>
                    <td class="text-end">Rp ${formatNumber(svc.subtotal)}</td>
                </tr>
            `;
        });
    } else {
        servicesHtml = '<tr><td colspan="4" class="text-center text-muted">Tidak ada jasa service</td></tr>';
    }
    
    // Spareparts table
    let itemsHtml = '';
    if (inv.items && inv.items.length > 0) {
        inv.items.forEach(function(item) {
            itemsHtml += `
                <tr>
                    <td>${item.sparepart_name}</td>
                    <td class="text-center">${item.qty} ${item.satuan}</td>
                    <td class="text-end">Rp ${formatNumber(item.harga_jual_default)}</td>
                    <td class="text-end">Rp ${formatNumber(item.subtotal)}</td>
                </tr>
            `;
        });
    } else {
        itemsHtml = '<tr><td colspan="4" class="text-center text-muted">Tidak ada sparepart</td></tr>';
    }
    
    // Payments table
    let paymentsHtml = '';
    if (inv.payments.length > 0) {
        inv.payments.forEach(function(pay) {
            paymentsHtml += `
                <tr>
                    <td>${formatDate(pay.tanggal)}</td>
                    <td>Rp ${formatNumber(pay.amount)}</td>
                    <td>${pay.method}</td>
                    <td>${pay.note || '-'}</td>
                    <td>Owner</td>
                    <td>
                        <button class="btn btn-danger btn-sm" onclick="deletePayment(${pay.id}, ${inv.id})" title="Hapus">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        });
    } else {
        paymentsHtml = '<tr><td colspan="6" class="text-center text-muted">Belum ada pembayaran</td></tr>';
    }
    
    let html = `
        <div class="row mb-3">
            <div class="col-md-6">
                <strong>Invoice ID:</strong> #${inv.id}<br>
                <strong>SPK:</strong> ${inv.spk_code}<br>
                <strong>Tanggal SPK:</strong> ${formatDate(inv.spk_tanggal)}<br>
                <strong>Customer:</strong> ${inv.customer_name} (${inv.customer_phone})<br>
                <strong>Alamat:</strong> ${inv.customer_address || '-'}
            </div>
            <div class="col-md-6">
                <strong>Kendaraan:</strong> ${inv.nomor_polisi}<br>
                <strong>Merk/Model:</strong> ${inv.merk} ${inv.model} (${inv.tahun})<br>
                <strong>Keluhan:</strong> ${inv.keluhan_customer}<br>
                <strong>Status:</strong> <span class="badge bg-${statusBadge}">${inv.status_piutang}</span><br>
                ${inv.paid_at ? '<strong>Lunas pada:</strong> ' + formatDate(inv.paid_at) : ''}
            </div>
        </div>
        
        <hr>
        <h6>Detail Jasa Service</h6>
        <table class="table table-bordered table-sm">
            <thead class="table-light">
                <tr>
                    <th>Nama Jasa</th>
                    <th class="text-center" width="15%">Qty</th>
                    <th class="text-end" width="20%">Harga</th>
                    <th class="text-end" width="20%">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                ${servicesHtml}
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="text-end"><strong>Total Jasa Service:</strong></td>
                    <td class="text-end"><strong>Rp ${formatNumber(inv.biaya_jasa)}</strong></td>
                </tr>
            </tfoot>
        </table>
        
        <h6 class="mt-4">Detail Sparepart</h6>
        <table class="table table-bordered table-sm">
            <thead class="table-light">
                <tr>
                    <th>Nama Sparepart</th>
                    <th class="text-center" width="15%">Qty</th>
                    <th class="text-end" width="20%">Harga</th>
                    <th class="text-end" width="20%">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                ${itemsHtml}
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="text-end"><strong>Total Sparepart:</strong></td>
                    <td class="text-end"><strong>Rp ${formatNumber(inv.biaya_sparepart)}</strong></td>
                </tr>
            </tfoot>
        </table>
        
        <table class="table table-bordered table-sm">
            <tfoot>
                <tr class="table-primary">
                    <td colspan="3" class="text-end"><strong>GRAND TOTAL:</strong></td>
                    <td class="text-end" width="20%"><strong>Rp ${formatNumber(inv.total)}</strong></td>
                </tr>
            </tfoot>
        </table>
        
        <hr>
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0">Riwayat Pembayaran</h6>
            <button class="btn btn-success btn-sm" onclick="openPaymentModal(${inv.id}, ${inv.sisa_piutang})">
                <i class="fas fa-plus"></i> Input Bayar
            </button>
        </div>
        <table class="table table-bordered table-sm">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Jumlah</th>
                    <th>Metode</th>
                    <th>Catatan</th>
                    <th>Oleh</th>
                    <th width="10%">Aksi</th>
                </tr>
            </thead>
            <tbody>
                ${paymentsHtml}
            </tbody>
            <tfoot>
                <tr class="table-info">
                    <td><strong>Total Terbayar:</strong></td>
                    <td colspan="5"><strong>Rp ${formatNumber(inv.total_paid)}</strong></td>
                </tr>
                <tr class="table-${inv.sisa_piutang > 0 ? 'warning' : 'success'}">
                    <td><strong>Sisa Piutang:</strong></td>
                    <td colspan="5"><strong>Rp ${formatNumber(inv.sisa_piutang)}</strong></td>
                </tr>
            </tfoot>
        </table>
        
        <div class="text-muted mt-3">
            <small>Dibuat pada: ${formatDateTime(inv.created_at)}</small>
        </div>
    `;
    
    $('#detailContent').html(html);
    $('#detailModal').modal('show');
}

// Open payment modal
function openPaymentModal(invoiceId, sisaPiutang) {
    currentInvoiceId = invoiceId;
    $('#payment_invoice_id').val(invoiceId);
    $('#payment_sisa').text('Rp ' + formatNumber(sisaPiutang));
    $('#amount').attr('max', sisaPiutang).val('');
    $('#payment_date').val(new Date().toISOString().split('T')[0]);
    $('#payment_method').val('');
    $('#notes').val('');
    $('#paymentModal').modal('show');
}

// Submit payment form
$('#paymentForm').on('submit', function(e) {
    e.preventDefault();
    
    $.ajax({
        url: 'backend.php',
        type: 'POST',
        data: $(this).serialize() + '&action=create_payment',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                $('#paymentModal').modal('hide');
                loadInvoices();
                
                // Jika modal detail terbuka, refresh detail
                if ($('#detailModal').hasClass('show')) {
                    viewDetail(currentInvoiceId);
                }
            } else {
                showAlert('danger', response.message);
            }
        }
    });
});

// Delete payment
function deletePayment(paymentId, invoiceId) {
    if (!confirm('Yakin ingin menghapus pembayaran ini?')) {
        return;
    }
    
    $.ajax({
        url: 'backend.php',
        type: 'POST',
        data: {
            action: 'delete_payment',
            payment_id: paymentId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                loadInvoices();
                viewDetail(invoiceId);
            } else {
                showAlert('danger', response.message);
            }
        }
    });
}

// Helper functions
function formatNumber(num) {
    return parseFloat(num).toLocaleString('id-ID', {minimumFractionDigits: 0, maximumFractionDigits: 2});
}

function formatDate(date) {
    if (!date) return '-';
    let d = new Date(date);
    return d.toLocaleDateString('id-ID', {day: '2-digit', month: 'short', year: 'numeric'});
}

function formatDateTime(datetime) {
    if (!datetime) return '-';
    let d = new Date(datetime);
    return d.toLocaleDateString('id-ID', {day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit'});
}

function showAlert(type, message) {
    let alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    $('.container-fluid').prepend(alertHtml);
    
    setTimeout(function() {
        $('.alert').fadeOut(function() {
            $(this).remove();
        });
    }, 3000);
}
</script>
