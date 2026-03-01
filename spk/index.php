<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$page_title = "Manajemen SPK";
include '../header.php';

// Cek role untuk hide/show harga
$user_role = $_SESSION['role'] ?? 'Admin';
$is_owner = ($user_role === 'Owner');
?>

<?php if (!$is_owner): ?>
<style>
    .sparepart-price-column {
        display: none !important;
    }
</style>
<?php endif; ?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Surat Perintah Kerja (SPK)</h5>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#spkModal" onclick="openAddModal()">
                        <i class="fas fa-plus"></i> Buat SPK Baru
                    </button>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="searchInput" placeholder="Cari kode SPK, customer, atau nomor polisi...">
                        </div>
                        <div class="col-md-6">
                            <select class="form-select" id="statusFilter">
                                <option value="">Semua Status</option>
                                <option value="Menunggu Konfirmasi">Menunggu Konfirmasi</option>
                                <option value="Disetujui">Disetujui</option>
                                <option value="Dalam Pengerjaan">Dalam Pengerjaan</option>
                                <option value="Selesai">Selesai</option>
                                <option value="Dikirim ke Owner">Dikirim ke Owner</option>
                                <option value="Buat Invoice">Buat Invoice</option>
                                <option value="Sudah Cetak Invoice">Sudah Cetak Invoice</option>
                            </select>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Kode SPK</th>
                                    <th>Tanggal</th>
                                    <th>Customer</th>
                                    <th>Kendaraan</th>
                                    <th>Keluhan</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="spkTableBody">
                                <tr><td colspan="7" class="text-center">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Create SPK -->
<div class="modal fade" id="spkModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Buat SPK Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="spkForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="mb-3">
                        <label for="customer_id" class="form-label">Customer *</label>
                        <select class="form-select" id="customer_id" name="customer_id" required>
                            <option value="">-- Pilih Customer --</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="vehicle_id" class="form-label">Kendaraan *</label>
                        <select class="form-select" id="vehicle_id" name="vehicle_id" required disabled>
                            <option value="">-- Pilih Customer Dulu --</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tanggal" class="form-label">Tanggal *</label>
                        <input type="date" class="form-control" id="tanggal" name="tanggal" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="keluhan_customer" class="form-label">Keluhan Customer *</label>
                        <textarea class="form-control" id="keluhan_customer" name="keluhan_customer" rows="4" required placeholder="Jelaskan keluhan/masalah kendaraan..."></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Kode SPK akan di-generate otomatis saat disimpan.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Buat SPK</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Detail SPK -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail SPK</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailContent">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<!-- Modal Analisa Mekanik -->
<div class="modal fade" id="analisaModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Analisa & Estimasi Mekanik</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="analisaForm">
                <div class="modal-body">
                    <input type="hidden" id="analisa_spk_id" name="id">
                    <input type="hidden" name="action" value="update_analisa">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="analisa_mekanik" class="form-label">Analisa Mekanik</label>
                                <textarea class="form-control" id="analisa_mekanik" name="analisa_mekanik" rows="3" placeholder="Hasil pemeriksaan dan diagnosa..."></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="service_description" class="form-label">Deskripsi Service</label>
                                <textarea class="form-control" id="service_description" name="service_description" rows="3" placeholder="Pekerjaan yang akan dilakukan..."></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="saran_service" class="form-label">Saran Service</label>
                                <textarea class="form-control" id="saran_service" name="saran_service" rows="2" placeholder="Saran untuk customer..."></textarea>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <h6>Jasa Service</h6>
                            <div class="card mb-3">
                                <div class="card-body">
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <select class="form-select form-select-sm" id="service_select">
                                                <option value="">Pilih Jasa...</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <input type="number" class="form-control form-control-sm" id="service_qty" value="1" min="1" placeholder="Qty">
                                        </div>
                                        <div class="col-md-3">
                                            <button type="button" class="btn btn-primary btn-sm w-100" onclick="addServiceToSPK()">
                                                <i class="fas fa-plus"></i> Tambah
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Jasa</th>
                                        <th>Qty</th>
                                        <th>Harga</th>
                                        <th>Subtotal</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="serviceListTable">
                                    <tr><td colspan="5" class="text-center text-muted">Belum ada jasa</td></tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="3">Total Biaya Jasa:</th>
                                        <th colspan="2" id="totalServiceCost">Rp 0</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Analisa</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../footer.php'; ?>

<script>
const userRole = '<?php echo $_SESSION['role'] ?? 'Admin'; ?>';
const isOwner = (userRole === 'Owner');

$(document).ready(function() {
    loadSPKs();
    loadCustomers();
    
    $('#tanggal').val(new Date().toISOString().split('T')[0]);
    
    let searchTimeout;
    $('#searchInput').on('keyup', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(loadSPKs, 500);
    });
    
    $('#statusFilter').on('change', loadSPKs);
    
    // Load vehicles saat customer dipilih
    $('#customer_id').on('change', function() {
        let customerId = $(this).val();
        if (customerId) {
            loadVehicles(customerId);
        } else {
            $('#vehicle_id').html('<option value="">-- Pilih Customer Dulu --</option>').prop('disabled', true);
        }
    });
});

function loadSPKs() {
    let search = $('#searchInput').val();
    let status = $('#statusFilter').val();
    
    $.ajax({
        url: 'backend.php?action=read&search=' + encodeURIComponent(search) + '&status=' + encodeURIComponent(status),
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displaySPKs(response.data);
            }
        }
    });
}

function loadCustomers() {
    $.ajax({
        url: 'backend.php?action=get_customers',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                let options = '<option value="">-- Pilih Customer --</option>';
                response.data.forEach(function(c) {
                    options += `<option value="${c.id}">${c.name} ${c.phone ? '(' + c.phone + ')' : ''}</option>`;
                });
                $('#customer_id').html(options);
            }
        }
    });
}

function loadVehicles(customerId) {
    $.ajax({
        url: 'backend.php?action=get_vehicles&customer_id=' + customerId,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                let options = '<option value="">-- Pilih Kendaraan --</option>';
                response.data.forEach(function(v) {
                    options += `<option value="${v.id}">${v.nomor_polisi} - ${v.merk} ${v.model || ''}</option>`;
                });
                $('#vehicle_id').html(options).prop('disabled', false);
            }
        }
    });
}

function displaySPKs(spks) {
    let html = '';
    
    console.log('Total SPKs:', spks.length);
    
    if (spks.length === 0) {
        html = '<tr><td colspan="7" class="text-center">Belum ada data SPK</td></tr>';
    } else {
        spks.forEach(function(spk) {
            let statusBadge = getStatusBadge(spk.status_spk);
            let vehicle = `${spk.nomor_polisi} - ${spk.merk || ''} ${spk.model || ''}`;
            
            html += `
                <tr>
                    <td><strong>${spk.kode_unik_reference}</strong></td>
                    <td>${formatDate(spk.tanggal)}</td>
                    <td>${spk.customer_name}<br><small class="text-muted">${spk.customer_phone || ''}</small></td>
                    <td>${vehicle}</td>
                    <td>${spk.keluhan_customer ? (spk.keluhan_customer.substring(0, 50) + '...') : '-'}</td>
                    <td>${statusBadge}</td>
                    <td>
                        <button class="btn btn-info btn-sm" onclick="viewDetail(${spk.id})" title="Detail">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-warning btn-sm" onclick="openAnalisaModal(${spk.id})" title="Analisa Mekanik">
                            <i class="fas fa-wrench"></i>
                        </button>
                        ${isOwner && spk.status_spk === 'Dikirim ke Owner' ? `
                        <button class="btn btn-success btn-sm" onclick="createInvoiceFromSPK(${spk.id})" title="Buat Invoice & Cetak PDF">
                            <i class="fas fa-file-invoice"></i> Buat Invoice
                        </button>
                        ` : ''}
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                Status
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="javascript:void(0);" onclick="updateStatus(${spk.id}, 'Menunggu Konfirmasi')">Menunggu Konfirmasi</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0);" onclick="updateStatus(${spk.id}, 'Disetujui')">Disetujui</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0);" onclick="updateStatus(${spk.id}, 'Dalam Pengerjaan')">Dalam Pengerjaan</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0);" onclick="updateStatus(${spk.id}, 'Selesai')">Selesai</a></li>
                                ${!isOwner ? `<li><a class="dropdown-item" href="javascript:void(0);" onclick="updateStatus(${spk.id}, 'Dikirim ke Owner')">Dikirim ke Owner</a></li>` : ''}
                                ${isOwner ? `<li><a class="dropdown-item" href="javascript:void(0);" onclick="updateStatus(${spk.id}, 'Buat Invoice')">Buat Invoice</a></li>` : ''}
                                ${isOwner ? `<li><a class="dropdown-item" href="javascript:void(0);" onclick="updateStatus(${spk.id}, 'Sudah Cetak Invoice')">Sudah Cetak Invoice</a></li>` : ''}
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="javascript:void(0);" onclick="deleteSPK(${spk.id})">Hapus SPK</a></li>
                            </ul>
                        </div>
                    </td>
                </tr>
            `;
        });
    }
    
    $('#spkTableBody').html(html);
    
    // Re-initialize Bootstrap dropdowns after DOM update
    setTimeout(function() {
        try {
            const dropdowns = document.querySelectorAll('[data-bs-toggle="dropdown"]');
            dropdowns.forEach(function(dropdown) {
                if (!bootstrap.Dropdown.getInstance(dropdown)) {
                    new bootstrap.Dropdown(dropdown);
                }
            });
            console.log('Initialized', dropdowns.length, 'dropdowns');
        } catch (e) {
            console.error('Error initializing dropdowns:', e);
        }
    }, 200);
}

function getStatusBadge(status) {
    const badges = {
        'Menunggu Konfirmasi': 'warning',
        'Disetujui': 'info',
        'Dalam Pengerjaan': 'primary',
        'Selesai': 'success',
        'Dikirim ke Owner': 'secondary',
        'Buat Invoice': 'danger',
        'Sudah Cetak Invoice': 'dark'
    };
    return `<span class="badge bg-${badges[status] || 'secondary'}">${status}</span>`;
}

function openAddModal() {
    $('#spkForm')[0].reset();
    $('#tanggal').val(new Date().toISOString().split('T')[0]);
    $('#vehicle_id').html('<option value="">-- Pilih Customer Dulu --</option>').prop('disabled', true);
}

$('#spkForm').on('submit', function(e) {
    e.preventDefault();
    
    $.ajax({
        url: 'backend.php',
        type: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message + ' - Kode: ' + response.kode_unik);
                $('#spkModal').modal('hide');
                loadSPKs();
            } else {
                showAlert('danger', response.message);
            }
        }
    });
});

function viewDetail(id) {
    $.ajax({
        url: 'backend.php?action=read_one&id=' + id,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                let spk = response.data;
                let html = `
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6>Informasi SPK</h6>
                            <table class="table table-sm">
                                <tr><th width="40%">Kode SPK:</th><td><strong>${spk.kode_unik_reference}</strong></td></tr>
                                <tr><th>Tanggal:</th><td>${formatDate(spk.tanggal)}</td></tr>
                                <tr><th>Status:</th><td>${getStatusBadge(spk.status_spk)}</td></tr>
                                <tr><th>Customer:</th><td>${spk.customer_name}<br><small>${spk.customer_phone || ''}</small></td></tr>
                                <tr><th>Kendaraan:</th><td>${spk.nomor_polisi} - ${spk.merk} ${spk.model || ''} (${spk.tahun || '-'})</td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>Keluhan & Analisa</h6>
                            <table class="table table-sm">
                                <tr><th width="40%">Keluhan:</th><td>${spk.keluhan_customer || '-'}</td></tr>
                                <tr><th>Analisa:</th><td>${spk.analisa_mekanik || '-'}</td></tr>
                                <tr><th>Service:</th><td>${spk.service_description || '-'}</td></tr>
                                <tr><th>Biaya Jasa:</th><td><strong>Rp ${formatNumber(spk.biaya_jasa)}</strong></td></tr>
                                <tr><th>Saran:</th><td>${spk.saran_service || '-'}</td></tr>
                            </table>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <h6>Jasa Service</h6>
                    <table class="table table-bordered table-sm">
                        <thead>
                            <tr>
                                <th>Nama Jasa</th>
                                <th>Qty</th>
                                ${isOwner ? '<th>Harga</th><th>Subtotal</th>' : ''}
                            </tr>
                        </thead>
                        <tbody>
                `;
                
                let totalJasa = 0;
                if (spk.services && spk.services.length > 0) {
                    spk.services.forEach(function(svc) {
                        let subtotal = parseFloat(svc.subtotal) || 0;
                        if (isOwner) {
                            html += `
                                <tr>
                                    <td>${svc.nama_jasa}</td>
                                    <td>${svc.qty}</td>
                                    <td>Rp ${formatNumber(svc.harga)}</td>
                                    <td>Rp ${formatNumber(subtotal)}</td>
                                </tr>
                            `;
                        } else {
                            html += `
                                <tr>
                                    <td>${svc.nama_jasa}</td>
                                    <td>${svc.qty}</td>
                                </tr>
                            `;
                        }
                        totalJasa += subtotal;
                    });
                } else {
                    html += `<tr><td colspan="${isOwner ? '4' : '2'}" class="text-center">Belum ada jasa service</td></tr>`;
                }
                
                html += `
                        </tbody>
                    </table>
                    
                    <hr>
                    
                    <h6>Sparepart yang Digunakan</h6>
                    <table class="table table-bordered table-sm">
                        <thead>
                            <tr>
                                <th>Nama Sparepart</th>
                                <th>Qty</th>
                                ${isOwner ? '<th>Harga</th><th>Subtotal</th>' : ''}
                            </tr>
                        </thead>
                        <tbody>
                `;
                
                let totalSparepart = 0;
                if (spk.items && spk.items.length > 0) {
                    spk.items.forEach(function(item) {
                        let subtotal = parseFloat(item.subtotal) || 0;
                        if (isOwner) {
                            html += `
                                <tr>
                                    <td>${item.sparepart_name}</td>
                                    <td>${item.qty} ${item.satuan}</td>
                                    <td>Rp ${formatNumber(item.harga_jual_default)}</td>
                                    <td>Rp ${formatNumber(subtotal)}</td>
                                </tr>
                            `;
                        } else {
                            html += `
                                <tr>
                                    <td>${item.sparepart_name}</td>
                                    <td>${item.qty} ${item.satuan}</td>
                                </tr>
                            `;
                        }
                        totalSparepart += subtotal;
                    });
                } else {
                    html += `<tr><td colspan="${isOwner ? '4' : '2'}" class="text-center">Belum ada sparepart</td></tr>`;
                }
                
                html += `
                        </tbody>`;
                
                // Tampilkan footer hanya untuk Owner
                if (isOwner) {
                    html += `
                        <tfoot>
                            <tr><th colspan="3">Total Sparepart:</th><th>Rp ${formatNumber(totalSparepart)}</th></tr>
                            <tr><th colspan="3">Total Jasa Service:</th><th>Rp ${formatNumber(totalJasa)}</th></tr>
                            <tr><th colspan="3">GRAND TOTAL:</th><th><strong>Rp ${formatNumber(totalSparepart + totalJasa)}</strong></th></tr>
                        </tfoot>
                    `;
                }
                
                html += `
                    </table>
                    
                    <hr>
                    
                    <div class="mb-3">
                        <button class="btn btn-danger btn-lg" onclick="downloadInvoicePDF(${spk.id})">
                            <i class="fas fa-file-pdf"></i> Cetak / Download Invoice PDF
                        </button>
                        ${isOwner && spk.status_spk !== 'Sudah Cetak Invoice' ? `
                        <button class="btn btn-success btn-lg" onclick="createInvoiceFromSPK(${spk.id})">
                            <i class="fas fa-check-circle"></i> Cetak PDF & Tandai Selesai
                        </button>
                        ` : ''}
                    </div>
                    
                    <hr>
                    
                    <h6>Request Barang Keluar Gudang</h6>
                    <div class="mb-3">
                        <a href="../warehouse_out/index.php?spk_id=${spk.id}" class="btn btn-primary btn-sm">
                            <i class="fas fa-dolly"></i> Request Sparepart ke Gudang
                        </a>
                    </div>
                `;
                
                if (spk.warehouse_requests && spk.warehouse_requests.length > 0) {
                    html += `
                        <table class="table table-bordered table-sm">
                            <thead>
                                <tr><th>Sparepart</th><th>Qty</th><th>Status</th><th>Request By</th></tr>
                            </thead>
                            <tbody>
                    `;
                    spk.warehouse_requests.forEach(function(wr) {
                        let wrStatus = wr.status === 'Approved' ? 'success' : (wr.status === 'Rejected' ? 'danger' : 'warning');
                        html += `
                            <tr>
                                <td>${wr.sparepart_name}</td>
                                <td>${wr.qty}</td>
                                <td><span class="badge bg-${wrStatus}">${wr.status}</span></td>
                                <td>${wr.requested_by_name || '-'}</td>
                            </tr>
                        `;
                    });
                    html += '</tbody></table>';
                } else {
                    html += '<p class="text-muted">Belum ada request barang keluar</p>';
                }
                
                $('#detailContent').html(html);
                $('#detailModal').modal('show');
            }
        }
    });
}

function openAnalisaModal(id) {
    $.ajax({
        url: 'backend.php?action=read_one&id=' + id,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                let spk = response.data;
                $('#analisa_spk_id').val(spk.id);
                $('#analisa_mekanik').val(spk.analisa_mekanik || '');
                $('#service_description').val(spk.service_description || '');
                $('#saran_service').val(spk.saran_service || '');
                
                // Load services for this SPK
                loadSPKServices(spk.id);
                // Load available service prices
                loadServicePrices();
                
                $('#analisaModal').modal('show');
            }
        }
    });
}

$('#analisaForm').on('submit', function(e) {
    e.preventDefault();
    
    $.ajax({
        url: 'backend.php',
        type: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                $('#analisaModal').modal('hide');
                loadSPKs();
            } else {
                showAlert('danger', response.message);
            }
        }
    });
});

function updateStatus(id, status) {
    if (confirm('Update status SPK ke: ' + status + '?')) {
        $.ajax({
            url: 'backend.php',
            type: 'POST',
            data: { action: 'update_status', id: id, status: status },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('success', response.message);
                    loadSPKs();
                    
                    // Jika status diubah ke "Buat Invoice", langsung download PDF
                    if (status === 'Buat Invoice') {
                        setTimeout(function() {
                            downloadInvoicePDF(id);
                        }, 1000);
                    }
                } else {
                    showAlert('danger', response.message);
                }
            }
        });
    }
}

// Download Invoice PDF
function downloadInvoicePDF(spkId) {
    window.open('generate_invoice_pdf.php?spk_id=' + spkId, '_blank');
}

// Cetak Invoice PDF + Update status ke "Sudah Cetak Invoice"
function createInvoiceFromSPK(spkId) {
    if (!confirm('Cetak Invoice PDF dan tandai SPK ini sebagai "Sudah Cetak Invoice"?')) {
        return;
    }
    
    // Update status dulu
    $.ajax({
        url: 'backend.php',
        type: 'POST',
        data: {
            action: 'update_status',
            id: spkId,
            status: 'Sudah Cetak Invoice'
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', 'Status SPK diubah ke "Sudah Cetak Invoice". Membuka PDF...');
                loadSPKs();
                
                // Buka PDF setelah status berhasil diupdate
                setTimeout(function() {
                    downloadInvoicePDF(spkId);
                }, 500);
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function() {
            showAlert('danger', 'Gagal mengubah status. Silakan coba lagi.');
        }
    });
}

function deleteSPK(id) {
    if (confirm('Yakin ingin menghapus SPK ini?')) {
        $.ajax({
            url: 'backend.php',
            type: 'POST',
            data: { action: 'delete', id: id },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('success', response.message);
                    loadSPKs();
                } else {
                    showAlert('danger', response.message);
                }
            }
        });
    }
}

function formatNumber(num) {
    return parseFloat(num).toLocaleString('id-ID', {minimumFractionDigits: 0, maximumFractionDigits: 2});
}

function formatDate(date) {
    if (!date) return '-';
    let d = new Date(date);
    return d.toLocaleDateString('id-ID', {day: '2-digit', month: 'short', year: 'numeric'});
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
        $('.alert').fadeOut(function() { $(this).remove(); });
    }, 3000);
}

// ========== Service Management Functions ==========
let servicePricesCache = [];

function loadServicePrices() {
    $.ajax({
        url: '../services/backend.php?action=read_active',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                servicePricesCache = response.data;
                let options = '<option value="">Pilih Jasa...</option>';
                response.data.forEach(function(service) {
                    options += `<option value="${service.id}" data-price="${service.harga}">${service.kode_jasa} - ${service.nama_jasa} - Rp ${formatNumber(service.harga)}</option>`;
                });
                $('#service_select').html(options);
                
                // Initialize Select2 for searchable dropdown
                $('#service_select').select2({
                    theme: 'bootstrap-5',
                    placeholder: 'Cari by kode jasa atau nama jasa...',
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $('#analisaModal')
                });
            }
        }
    });
}

function loadSPKServices(spkId) {
    $.ajax({
        url: 'backend.php?action=read_one&id=' + spkId,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success && response.data.services) {
                displayServicesList(response.data.services);
            }
        }
    });
}

function displayServicesList(services) {
    let html = '';
    let total = 0;
    
    if (services && services.length > 0) {
        services.forEach(function(svc) {
            let subtotal = parseFloat(svc.subtotal) || 0;
            total += subtotal;
            html += `
                <tr>
                    <td>${svc.nama_jasa}</td>
                    <td>${svc.qty}</td>
                    <td>Rp ${formatNumber(svc.harga)}</td>
                    <td>Rp ${formatNumber(subtotal)}</td>
                    <td>
                        <button type="button" class="btn btn-danger btn-sm" onclick="deleteServiceFromSPK(${svc.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        });
    } else {
        html = '<tr><td colspan="5" class="text-center text-muted">Belum ada jasa</td></tr>';
    }
    
    $('#serviceListTable').html(html);
    $('#totalServiceCost').html('Rp ' + formatNumber(total));
}

function addServiceToSPK() {
    const spkId = $('#analisa_spk_id').val();
    const servicePriceId = $('#service_select').val();
    const qty = parseInt($('#service_qty').val()) || 1;
    
    if (!servicePriceId) {
        showAlert('warning', 'Pilih jasa terlebih dahulu');
        return;
    }
    
    const selectedOption = $('#service_select option:selected');
    const harga = parseFloat(selectedOption.data('price'));
    
    $.ajax({
        url: 'backend.php',
        type: 'POST',
        data: {
            action: 'add_service',
            spk_id: spkId,
            service_price_id: servicePriceId,
            qty: qty,
            harga: harga
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                loadSPKServices(spkId);
                $('#service_select').val('');
                $('#service_qty').val(1);
            } else {
                showAlert('danger', response.message);
            }
        }
    });
}

function deleteServiceFromSPK(serviceId) {
    if (!confirm('Hapus jasa ini?')) return;
    
    const spkId = $('#analisa_spk_id').val();
    
    $.ajax({
        url: 'backend.php',
        type: 'POST',
        data: {
            action: 'delete_service',
            id: serviceId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                loadSPKServices(spkId);
            } else {
                showAlert('danger', response.message);
            }
        }
    });
}
</script>
