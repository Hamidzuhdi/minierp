<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$page_title = "Barang Keluar Gudang";
$spk_id = $_GET['spk_id'] ?? '';
include '../header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-dolly"></i> Request Barang Keluar Gudang
                        <?php if ($spk_id): ?>
                            <span class="badge bg-info">SPK ID: <?= $spk_id ?></span>
                        <?php endif; ?>
                    </h5>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#requestModal" onclick="openAddModal()">
                        <i class="fas fa-plus"></i> Request Barang Keluar
                    </button>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <select class="form-select" id="statusFilter">
                            <option value="">Semua Status</option>
                            <option value="Pending">Pending</option>
                            <option value="Approved">Approved</option>
                            <option value="Rejected">Rejected</option>
                        </select>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>SPK</th>
                                    <th>Sparepart</th>
                                    <th>Qty</th>
                                    <th>Stock Tersedia</th>
                                    <th>Barcode Scan</th>
                                    <th>Request By</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="requestTableBody">
                                <tr><td colspan="9" class="text-center">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Request Barang Keluar -->
<div class="modal fade" id="requestModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Request Barang Keluar</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="requestForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="spk_id" id="spk_id" value="<?= $spk_id ?>">
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Scan barcode atau pilih sparepart manual
                    </div>
                    
                    <div class="mb-3">
                        <label for="scanned_barcode" class="form-label">
                            <i class="fas fa-barcode"></i> Scan Barcode
                        </label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="scanned_barcode" name="scanned_barcode" placeholder="Scan barcode di sini...">
                            <button type="button" class="btn btn-primary" onclick="searchByBarcode()">
                                <i class="fas fa-search"></i> Cari
                            </button>
                        </div>
                    </div>
                    
                    <hr>
                    <p class="text-center text-muted">ATAU</p>
                    <hr>
                    
                    <div class="mb-3">
                        <label for="sparepart_id" class="form-label">Pilih Sparepart</label>
                        <select class="form-select" id="sparepart_id" name="sparepart_id" required>
                            <option value="">-- Pilih Sparepart --</option>
                        </select>
                        <small class="text-muted" id="sparepartInfo"></small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="qty" class="form-label">Quantity *</label>
                        <input type="number" class="form-control" id="qty" name="qty" min="1" value="1" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="note" class="form-label">Catatan</label>
                        <textarea class="form-control" id="note" name="note" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Reject -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tolak Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="rejectForm">
                <div class="modal-body">
                    <input type="hidden" id="reject_id">
                    <div class="mb-3">
                        <label for="reject_note" class="form-label">Alasan Penolakan *</label>
                        <textarea class="form-control" id="reject_note" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Tolak Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../footer.php'; ?>

<script>
let spareparts = [];

$(document).ready(function() {
    loadRequests();
    loadSpareparts();
    
    $('#statusFilter').on('change', loadRequests);
    
    $('#sparepart_id').on('change', function() {
        let selectedOption = $(this).find(':selected');
        let stock = selectedOption.data('stock') || 0;
        let satuan = selectedOption.data('satuan') || '';
        
        if (stock > 0) {
            $('#sparepartInfo').html(`<i class="fas fa-info-circle text-info"></i> Stock tersedia: <strong>${stock} ${satuan}</strong>`);
            $('#qty').attr('max', stock);
        } else {
            $('#sparepartInfo').html('');
        }
    });
    
    // Auto focus ke barcode scanner
    $('#requestModal').on('shown.bs.modal', function() {
        $('#scanned_barcode').focus();
    });
});

function loadRequests() {
    let status = $('#statusFilter').val();
    let spkId = $('#spk_id').val();
    
    $.ajax({
        url: 'backend.php?action=read&status=' + status + '&spk_id=' + spkId,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displayRequests(response.data);
            }
        }
    });
}

function loadSpareparts() {
    $.ajax({
        url: 'backend.php?action=get_spareparts',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                spareparts = response.data;
                let options = '<option value="">-- Pilih Sparepart --</option>';
                response.data.forEach(function(sp) {
                    options += `<option value="${sp.id}" data-stock="${sp.current_stock}" data-satuan="${sp.satuan}">
                        ${sp.nama} (Stock: ${sp.current_stock} ${sp.satuan})
                    </option>`;
                });
                $('#sparepart_id').html(options);
            }
        }
    });
}

function displayRequests(requests) {
    let html = '';
    
    if (requests.length === 0) {
        html = '<tr><td colspan="9" class="text-center">Belum ada request barang keluar</td></tr>';
    } else {
        requests.forEach(function(req) {
            let statusBadge = '';
            if (req.status === 'Pending') {
                statusBadge = '<span class="badge bg-warning">Pending</span>';
            } else if (req.status === 'Approved') {
                statusBadge = '<span class="badge bg-success">Approved</span>';
            } else {
                statusBadge = '<span class="badge bg-danger">Rejected</span>';
            }
            
            let stockWarning = req.current_stock < req.qty ? '<span class="text-danger">⚠️</span>' : '';
            
            html += `
                <tr>
                    <td>${req.id}</td>
                    <td>${req.kode_unik_reference || '-'}</td>
                    <td>${req.sparepart_name}</td>
                    <td><strong>${req.qty}</strong> ${req.satuan}</td>
                    <td>${stockWarning} ${req.current_stock} ${req.satuan}</td>
                    <td>${req.scanned_barcode || '-'}</td>
                    <td>${req.requested_by_name || '-'}<br><small class="text-muted">${formatDateTime(req.created_at)}</small></td>
                    <td>${statusBadge}</td>
                    <td>
                        ${req.status === 'Pending' ? `
                        <button class="btn btn-success btn-sm" onclick="approveRequest(${req.id})" title="Approve">
                            <i class="fas fa-check"></i>
                        </button>
                        <button class="btn btn-danger btn-sm" onclick="openRejectModal(${req.id})" title="Reject">
                            <i class="fas fa-times"></i>
                        </button>
                        <button class="btn btn-secondary btn-sm" onclick="deleteRequest(${req.id})" title="Hapus">
                            <i class="fas fa-trash"></i>
                        </button>
                        ` : ''}
                        ${req.note ? `<br><small class="text-muted">${req.note}</small>` : ''}
                    </td>
                </tr>
            `;
        });
    }
    
    $('#requestTableBody').html(html);
}

function openAddModal() {
    $('#requestForm')[0].reset();
    $('#spk_id').val('<?= $spk_id ?>');
    $('#sparepartInfo').html('');
}

function searchByBarcode() {
    let barcode = $('#scanned_barcode').val().trim();
    
    if (!barcode) {
        showAlert('warning', 'Masukkan barcode terlebih dahulu');
        return;
    }
    
    $.ajax({
        url: 'backend.php?action=search_barcode&barcode=' + encodeURIComponent(barcode),
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                let sp = response.data;
                $('#sparepart_id').val(sp.id).trigger('change');
                showAlert('success', `Sparepart ditemukan: ${sp.nama}`);
            } else {
                showAlert('danger', response.message);
            }
        }
    });
}

$('#requestForm').on('submit', function(e) {
    e.preventDefault();
    
    $.ajax({
        url: 'backend.php',
        type: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                $('#requestModal').modal('hide');
                loadRequests();
            } else {
                showAlert('danger', response.message);
            }
        }
    });
});

function approveRequest(id) {
    if (confirm('Approve request ini? Stock akan berkurang dan sparepart akan ditambahkan ke SPK.')) {
        $.ajax({
            url: 'backend.php',
            type: 'POST',
            data: { action: 'approve', id: id },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('success', response.message);
                    loadRequests();
                } else {
                    showAlert('danger', response.message);
                }
            }
        });
    }
}

function openRejectModal(id) {
    $('#reject_id').val(id);
    $('#reject_note').val('');
    $('#rejectModal').modal('show');
}

$('#rejectForm').on('submit', function(e) {
    e.preventDefault();
    
    let id = $('#reject_id').val();
    let note = $('#reject_note').val();
    
    $.ajax({
        url: 'backend.php',
        type: 'POST',
        data: { action: 'reject', id: id, note: note },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                $('#rejectModal').modal('hide');
                loadRequests();
            } else {
                showAlert('danger', response.message);
            }
        }
    });
});

function deleteRequest(id) {
    if (confirm('Yakin ingin menghapus request ini?')) {
        $.ajax({
            url: 'backend.php',
            type: 'POST',
            data: { action: 'delete', id: id },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('success', response.message);
                    loadRequests();
                } else {
                    showAlert('danger', response.message);
                }
            }
        });
    }
}

function formatDateTime(datetime) {
    if (!datetime) return '-';
    let d = new Date(datetime);
    return d.toLocaleDateString('id-ID', {day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit'});
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
