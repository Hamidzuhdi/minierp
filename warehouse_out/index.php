<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$page_title = "Barang Keluar Gudang";
include '../header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-history"></i> History Barang Keluar dari Purchase</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="searchInput" placeholder="Cari ID purchase, supplier, atau pembuat...">
                        </div>
                        <div class="col-md-6">
                            <select class="form-select" id="paidFilter">
                                <option value="">Semua Status Bayar</option>
                                <option value="Belum Bayar">Belum Bayar</option>
                                <option value="Sudah Bayar">Sudah Bayar</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID Purchase</th>
                                    <th>Tanggal</th>
                                    <th>Supplier</th>
                                    <th>Jumlah Item</th>
                                    <th>Total PO</th>
                                    <th>Dibuat Oleh</th>
                                    <th>Status Purchase</th>
                                    <th>Status Bayar</th>
                                </tr>
                            </thead>
                            <tbody id="historyTableBody">
                                <tr><td colspan="8" class="text-center">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../footer.php'; ?>

<script>
$(document).ready(function() {
    loadPurchaseHistory();

    $('#paidFilter').on('change', loadPurchaseHistory);
    
    let searchTimeout;
    $('#searchInput').on('keyup', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(loadPurchaseHistory, 400);
    });
});

function loadPurchaseHistory() {
    let search = $('#searchInput').val();
    let isPaid = $('#paidFilter').val();
    
    $.ajax({
        url: 'backend.php?action=read&search=' + encodeURIComponent(search) + '&is_paid=' + encodeURIComponent(isPaid),
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                renderPurchaseHistory(response.data || []);
            } else {
                showAlert('danger', response.message || 'Gagal memuat history purchase');
            }
        },
        error: function() {
            showAlert('danger', 'Gagal memuat history purchase');
        }
    });
}

function renderPurchaseHistory(rows) {
    let html = '';
    
    if (rows.length === 0) {
        html = '<tr><td colspan="8" class="text-center">Belum ada history purchase</td></tr>';
    } else {
        rows.forEach(function(row) {
            const paidBadge = (row.is_paid === 'Sudah Bayar')
                ? '<span class="badge bg-success">Sudah Bayar</span>'
                : '<span class="badge bg-warning text-dark">Belum Bayar</span>';

            const purchaseStatusClass = row.status === 'Refund' ? 'bg-danger' : 'bg-info';
            const purchaseStatusBadge = `<span class="badge ${purchaseStatusClass}">${row.status || '-'}</span>`;

            const createdBy = row.created_by_name || '-';
            const createdRole = row.created_by_role ? ` <small class="text-muted">(${row.created_by_role})</small>` : '';
            const itemSummary = `${row.item_count || 0} item / ${row.qty_total || 0} qty`;
            const itemDetails = (row.item_detail || '').split(' | ').filter(Boolean).join('<br>');
            const itemDetailHtml = itemDetails ? `<br><small class="text-muted">${itemDetails}</small>` : '';
            
            html += `
                <tr>
                    <td>#${row.id}</td>
                    <td>${formatDate(row.tanggal)}</td>
                    <td>${row.supplier || '-'}</td>
                    <td>${itemSummary}${itemDetailHtml}</td>
                    <td><strong>${formatCurrency(row.total)}</strong></td>
                    <td>${createdBy}${createdRole}<br><small class="text-muted">${formatDateTime(row.created_at)}</small></td>
                    <td>${purchaseStatusBadge}</td>
                    <td>${paidBadge}${row.paid_at ? `<br><small class="text-muted">${formatDateTime(row.paid_at)}</small>` : ''}</td>
                </tr>
            `;
        });
    }
    
    $('#historyTableBody').html(html);
}

function formatDateTime(datetime) {
    if (!datetime) return '-';
    let d = new Date(datetime);
    return d.toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function formatDate(date) {
    if (!date) return '-';
    let d = new Date(date);
    return d.toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });
}

function formatCurrency(num) {
    const value = parseFloat(num || 0);
    return 'Rp ' + value.toLocaleString('id-ID', {minimumFractionDigits: 0, maximumFractionDigits: 2});
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
