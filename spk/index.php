<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$page_title = "Manajemen SPK";
include '../header.php';
?>

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
                                <option value="Dikirim ke Admin">Dikirim ke Admin</option>
                                <option value="Buat Invoice">Buat Invoice</option>
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
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Analisa & Estimasi Mekanik</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="analisaForm">
                <div class="modal-body">
                    <input type="hidden" id="analisa_spk_id" name="id">
                    <input type="hidden" name="action" value="update_analisa">
                    
                    <div class="mb-3">
                        <label for="analisa_mekanik" class="form-label">Analisa Mekanik</label>
                        <textarea class="form-control" id="analisa_mekanik" name="analisa_mekanik" rows="3" placeholder="Hasil pemeriksaan dan diagnosa..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="service_description" class="form-label">Deskripsi Service</label>
                        <textarea class="form-control" id="service_description" name="service_description" rows="3" placeholder="Pekerjaan yang akan dilakukan..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="biaya_jasa" class="form-label">Estimasi Biaya Jasa</label>
                        <input type="number" step="0.01" class="form-control" id="biaya_jasa" name="biaya_jasa" value="0" min="0">
                    </div>
                    
                    <div class="mb-3">
                        <label for="saran_service" class="form-label">Saran Service</label>
                        <textarea class="form-control" id="saran_service" name="saran_service" rows="2" placeholder="Saran untuk customer..."></textarea>
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
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                Status
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="javascript:void(0);" onclick="updateStatus(${spk.id}, 'Menunggu Konfirmasi')">Menunggu Konfirmasi</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0);" onclick="updateStatus(${spk.id}, 'Disetujui')">Disetujui</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0);" onclick="updateStatus(${spk.id}, 'Dalam Pengerjaan')">Dalam Pengerjaan</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0);" onclick="updateStatus(${spk.id}, 'Selesai')">Selesai</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0);" onclick="updateStatus(${spk.id}, 'Dikirim ke Admin')">Dikirim ke Admin</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0);" onclick="updateStatus(${spk.id}, 'Buat Invoice')">Buat Invoice</a></li>
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
        'Dikirim ke Admin': 'secondary',
        'Buat Invoice': 'danger'
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
                    
                    <h6>Sparepart yang Digunakan</h6>
                    <table class="table table-bordered table-sm">
                        <thead>
                            <tr><th>Nama Sparepart</th><th>Qty</th><th>Harga</th><th>Subtotal</th></tr>
                        </thead>
                        <tbody>
                `;
                
                let totalSparepart = 0;
                if (spk.items && spk.items.length > 0) {
                    spk.items.forEach(function(item) {
                        html += `
                            <tr>
                                <td>${item.sparepart_name}</td>
                                <td>${item.qty} ${item.satuan}</td>
                                <td>Rp ${formatNumber(item.harga_satuan)}</td>
                                <td>Rp ${formatNumber(item.subtotal)}</td>
                            </tr>
                        `;
                        totalSparepart += parseFloat(item.subtotal);
                    });
                } else {
                    html += '<tr><td colspan="4" class="text-center">Belum ada sparepart</td></tr>';
                }
                
                html += `
                        </tbody>
                        <tfoot>
                            <tr><th colspan="3">Total Sparepart:</th><th>Rp ${formatNumber(totalSparepart)}</th></tr>
                            <tr><th colspan="3">Biaya Jasa:</th><th>Rp ${formatNumber(spk.biaya_jasa)}</th></tr>
                            <tr><th colspan="3">GRAND TOTAL:</th><th><strong>Rp ${formatNumber(totalSparepart + parseFloat(spk.biaya_jasa))}</strong></th></tr>
                        </tfoot>
                    </table>
                    
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
                $('#biaya_jasa').val(spk.biaya_jasa || 0);
                $('#saran_service').val(spk.saran_service || '');
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
                } else {
                    showAlert('danger', response.message);
                }
            }
        });
    }
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
</script>
