<?php
session_start();
require_once '../config.php';

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$page_title = "Manajemen Pembelian";
include '../header.php';

// Cek role untuk hide/show kolom harga
$user_role = $_SESSION['role'] ?? 'Admin';
$is_owner = ($user_role === 'Owner');
?>

<?php if (!$is_owner): ?>
<style>
    .price-column {
        display: none !important;
    }
</style>
<?php endif; ?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Data Pembelian (Purchase)</h5>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#purchaseModal" onclick="openAddModal()">
                        <i class="fas fa-plus"></i> Tambah Purchase
                    </button>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="searchInput" placeholder="Cari ID atau supplier...">
                        </div>
                        <div class="col-md-6">
                            <select class="form-select" id="statusFilter">
                                <option value="">Semua Status</option>
                                <option value="Pending Approval">Pending Approval</option>
                                <option value="Approved">Approved</option>
                                <option value="Refund">Refund</option>
                            </select>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tanggal</th>
                                    <th>Supplier</th>
                                    <th class="price-column">Total</th>
                                    <th>Status</th>
                                    <th>Pembayaran</th>
                                    <th>Dibuat Oleh</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="purchaseTableBody">
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

<!-- Modal Add Purchase -->
<div class="modal fade" id="purchaseModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Purchase</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="purchaseForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="supplier" class="form-label">Supplier <?php echo $is_owner ? '*' : '(Opsional)'; ?></label>
                            <input type="text" class="form-control" id="supplier" name="supplier" <?php echo $is_owner ? 'required' : ''; ?> placeholder="<?php echo $is_owner ? 'Nama supplier' : 'Akan diisi owner'; ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="tanggal" class="form-label">Tanggal *</label>
                            <input type="date" class="form-control" id="tanggal" name="tanggal" required>
                        </div>
                    </div>
                    
                    <hr>
                    <h6>Item Pembelian</h6>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered" id="itemsTable">
                            <thead>
                                <tr>
                                    <th width="40%">Sparepart</th>
                                    <th width="15%">Qty</th>
                                    <th width="20%" class="price-column">Harga Beli</th>
                                    <th width="20%" class="price-column">Subtotal</th>
                                    <th width="5%">
                                        <button type="button" class="btn btn-success btn-sm" onclick="addItemRow()">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="itemsTableBody">
                                <!-- Items akan ditambahkan via JS -->
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" class="text-end"><strong>TOTAL:</strong></td>
                                    <td colspan="2" class="price-column"><strong id="grandTotal">Rp 0</strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Purchase</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Detail Purchase -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Purchase</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailContent">
                <!-- Detail akan diisi via AJAX -->
            </div>
        </div>
    </div>
</div>

<!-- Modal Edit Purchase (Owner Only) -->
<?php if ($is_owner): ?>
<div class="modal fade" id="editPurchaseModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Purchase - Isi Supplier & Harga</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editPurchaseForm">
                <div class="modal-body">
                    <input type="hidden" id="edit_purchase_id" name="purchase_id">
                    <input type="hidden" name="action" value="update_purchase">
                    
                    <div class="mb-3">
                        <label for="edit_supplier" class="form-label">Supplier *</label>
                        <input type="text" class="form-control" id="edit_supplier" name="supplier" required>
                    </div>
                    
                    <hr>
                    <h6>Item Pembelian</h6>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Sparepart</th>
                                <th>Qty</th>
                                <th>Harga Beli</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody id="editItemsTableBody">
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-end"><strong>TOTAL:</strong></td>
                                <td><strong id="editGrandTotal">Rp 0</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Update Purchase</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include '../footer.php'; ?>

<script>
let spareparts = [];
let itemCounter = 0;
const userRole = '<?php echo $_SESSION['role'] ?? 'Admin'; ?>';
const isOwner = (userRole === 'Owner');

// Load data saat halaman dimuat
$(document).ready(function() {
    loadPurchases();
    loadSpareparts();
    
    // Set tanggal hari ini sebagai default
    $('#tanggal').val(new Date().toISOString().split('T')[0]);
    
    // Search dengan delay
    let searchTimeout;
    $('#searchInput').on('keyup', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            loadPurchases();
        }, 500);
    });
    
    // Filter status
    $('#statusFilter').on('change', function() {
        loadPurchases();
    });
});

// Load semua purchase
function loadPurchases() {
    let search = $('#searchInput').val();
    let status = $('#statusFilter').val();
    
    $.ajax({
        url: 'backend.php?action=read&search=' + encodeURIComponent(search) + '&status=' + encodeURIComponent(status),
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displayPurchases(response.data);
            } else {
                showAlert('danger', 'Gagal memuat data purchase');
            }
        }
    });
}

// Load spareparts untuk dropdown
function loadSpareparts() {
    $.ajax({
        url: 'backend.php?action=get_spareparts',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                spareparts = response.data;
            }
        }
    });
}

// Tampilkan data purchase di tabel
function displayPurchases(purchases) {
    let html = '';
    
    if (purchases.length === 0) {
        html = '<tr><td colspan="8" class="text-center">Belum ada data purchase</td></tr>';
    } else {
        purchases.forEach(function(p) {
            let statusBadge = '';
            if (p.status === 'Pending Approval') {
                statusBadge = '<span class="badge bg-warning">Pending Approval</span>';
            } else if (p.status === 'Approved') {
                statusBadge = '<span class="badge bg-success">Approved</span>';
            } else {
                statusBadge = '<span class="badge bg-danger">Refund</span>';
            }
            
            let paymentBadge = p.is_paid === 'Sudah Bayar' 
                ? '<span class="badge bg-success">Sudah Bayar</span>' 
                : '<span class="badge bg-secondary">Belum Bayar</span>';
            
            html += `
                <tr>
                    <td>${p.id}</td>
                    <td>${formatDate(p.tanggal)}</td>
                    <td>${p.supplier}</td>
                    <td class="price-column">Rp ${formatNumber(p.total)}</td>
                    <td>${statusBadge}</td>
                    <td>${paymentBadge}</td>
                    <td>${p.created_by_name || '-'}</td>
                    <td>
                        <button class="btn btn-info btn-sm" onclick="viewDetail(${p.id})" title="Detail">
                            <i class="fas fa-eye"></i>
                        </button>
                        ${isOwner && (p.supplier === 'Pending - Akan diisi Owner' || !p.supplier) ? `
                        <button class="btn btn-warning btn-sm" onclick="editPurchase(${p.id})" title="Isi Supplier & Harga">
                            <i class="fas fa-edit"></i> Isi Data
                        </button>
                        ` : ''}
                        ${isOwner && p.status === 'Pending Approval' ? `
                        <button class="btn btn-success btn-sm" onclick="updateStatus(${p.id}, 'Approved')" title="Approve">
                            <i class="fas fa-check"></i> Approve
                        </button>
                        ` : ''}
                        ${!isOwner && p.status === 'Pending Approval' ? `
                        <button class="btn btn-danger btn-sm" onclick="deletePurchase(${p.id})" title="Hapus">
                            <i class="fas fa-trash"></i>
                        </button>
                        ` : ''}
                        ${isOwner && p.status === 'Approved' && p.is_paid === 'Belum Bayar' ? `
                        <button class="btn btn-primary btn-sm" onclick="updatePayment(${p.id}, 'Sudah Bayar')" title="Tandai Sudah Bayar">
                            <i class="fas fa-money-bill"></i> Bayar
                        </button>
                        ` : ''}
                        ${isOwner && p.status === 'Approved' ? `
                        <button class="btn btn-warning btn-sm" onclick="updateStatus(${p.id}, 'Refund')" title="Refund">
                            <i class="fas fa-undo"></i>
                        </button>
                        ` : ''}
                    </td>
                </tr>
            `;
        });
    }
    
    $('#purchaseTableBody').html(html);
}

// Buka modal untuk tambah purchase
function openAddModal() {
    $('#purchaseForm')[0].reset();
    $('#tanggal').val(new Date().toISOString().split('T')[0]);
    $('#itemsTableBody').empty();
    itemCounter = 0;
    addItemRow();
}

// Tambah baris item
function addItemRow() {
    itemCounter++;
    
    let options = '<option value="">-- Pilih Sparepart --</option>';
    spareparts.forEach(function(sp) {
        options += `<option value="${sp.id}" data-price="${sp.harga_beli_default}" data-satuan="${sp.satuan}">${sp.nama} (${sp.satuan}) - Stock: ${sp.current_stock}</option>`;
    });
    
    let priceColumn = isOwner 
        ? `<td>
                <input type="number" step="0.01" class="form-control item-price" name="items[${itemCounter}][harga_beli]" min="0" value="0" required onchange="calculateSubtotal(${itemCounter})">
            </td>
            <td>
                <strong class="item-subtotal">Rp 0</strong>
            </td>`
        : `<td class="price-column">
                <input type="number" step="0.01" class="form-control item-price" name="items[${itemCounter}][harga_beli]" min="0" value="0" readonly>
            </td>
            <td class="price-column">
                <strong class="item-subtotal">Rp 0</strong>
            </td>`;
    
    let html = `
        <tr id="row${itemCounter}">
            <td>
                <select class="form-select sparepart-select" name="items[${itemCounter}][sparepart_id]" required onchange="updatePrice(${itemCounter})">
                    ${options}
                </select>
            </td>
            <td>
                <input type="number" class="form-control item-qty" name="items[${itemCounter}][qty]" min="1" value="1" required onchange="calculateSubtotal(${itemCounter})">
            </td>
            ${priceColumn}
            <td>
                <button type="button" class="btn btn-danger btn-sm" onclick="removeItemRow(${itemCounter})">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
    `;
    
    $('#itemsTableBody').append(html);
}

// Update harga saat sparepart dipilih
function updatePrice(rowId) {
    let select = $(`#row${rowId} .sparepart-select`);
    let price = select.find(':selected').data('price') || 0;
    $(`#row${rowId} .item-price`).val(price);
    calculateSubtotal(rowId);
}

// Hitung subtotal per item
function calculateSubtotal(rowId) {
    let qty = parseFloat($(`#row${rowId} .item-qty`).val()) || 0;
    let price = parseFloat($(`#row${rowId} .item-price`).val()) || 0;
    let subtotal = qty * price;
    
    $(`#row${rowId} .item-subtotal`).text('Rp ' + formatNumber(subtotal));
    calculateGrandTotal();
}

// Hitung grand total
function calculateGrandTotal() {
    let total = 0;
    $('.item-subtotal').each(function() {
        let text = $(this).text().replace('Rp ', '').replace(/,/g, '');
        total += parseFloat(text) || 0;
    });
    $('#grandTotal').text('Rp ' + formatNumber(total));
}

// Hapus baris item
function removeItemRow(rowId) {
    $(`#row${rowId}`).remove();
    calculateGrandTotal();
}

// Submit form purchase
$('#purchaseForm').on('submit', function(e) {
    e.preventDefault();
    
    // Kumpulkan data items
    let items = [];
    $('#itemsTableBody tr').each(function() {
        let row = $(this);
        let sparepartId = row.find('.sparepart-select').val();
        let qty = row.find('.item-qty').val();
        let price = row.find('.item-price').val();
        
        if (sparepartId && qty && price) {
            items.push({
                sparepart_id: sparepartId,
                qty: parseInt(qty),
                harga_beli: parseFloat(price)
            });
        }
    });
    
    if (items.length === 0) {
        showAlert('warning', 'Tambahkan minimal 1 item pembelian');
        return;
    }
    
    let formData = new FormData(this);
    formData.append('items', JSON.stringify(items));
    
    $.ajax({
        url: 'backend.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                $('#purchaseModal').modal('hide');
                loadPurchases();
            } else {
                showAlert('danger', response.message);
            }
        }
    });
});

// View detail purchase
function viewDetail(id) {
    $.ajax({
        url: 'backend.php?action=read_one&id=' + id,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                let p = response.data;
                let statusBadge = p.status === 'Approved' ? 'success' : (p.status === 'Refund' ? 'danger' : 'warning');
                
                let itemsHtml = '';
                let total = 0;
                p.items.forEach(function(item) {
                    if (isOwner) {
                        itemsHtml += `
                            <tr>
                                <td>${item.sparepart_name}</td>
                                <td>${item.qty} ${item.satuan}</td>
                                <td>Rp ${formatNumber(item.harga_beli)}</td>
                                <td>Rp ${formatNumber(item.subtotal)}</td>
                            </tr>
                        `;
                    } else {
                        itemsHtml += `
                            <tr>
                                <td>${item.sparepart_name}</td>
                                <td>${item.qty} ${item.satuan}</td>
                            </tr>
                        `;
                    }
                    total += parseFloat(item.subtotal);
                });
                
                let tableHeader = isOwner 
                    ? `<tr>
                        <th>Sparepart</th>
                        <th>Qty</th>
                        <th>Harga</th>
                        <th>Subtotal</th>
                    </tr>`
                    : `<tr>
                        <th>Sparepart</th>
                        <th>Qty</th>
                    </tr>`;
                
                let tableFooter = isOwner 
                    ? `<tfoot>
                        <tr>
                            <td colspan="3" class="text-end"><strong>TOTAL:</strong></td>
                            <td><strong>Rp ${formatNumber(total)}</strong></td>
                        </tr>
                    </tfoot>`
                    : '';
                
                let html = `
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>ID Purchase:</strong> ${p.id}<br>
                            <strong>Supplier:</strong> ${p.supplier}<br>
                            <strong>Tanggal:</strong> ${formatDate(p.tanggal)}
                        </div>
                        <div class="col-md-6">
                            <strong>Status:</strong> <span class="badge bg-${statusBadge}">${p.status}</span><br>
                            <strong>Pembayaran:</strong> ${p.is_paid}<br>
                            <strong>Dibuat oleh:</strong> ${p.created_by_name || '-'}
                        </div>
                    </div>
                    <hr>
                    <h6>Item Pembelian:</h6>
                    <table class="table table-bordered">
                        <thead>
                            ${tableHeader}
                        </thead>
                        <tbody>
                            ${itemsHtml}
                        </tbody>
                        ${tableFooter}
                    </table>
                `;
                
                $('#detailContent').html(html);
                $('#detailModal').modal('show');
            }
        }
    });
}

// Edit Purchase (Owner only) - Isi supplier dan harga
function editPurchase(id) {
    $.ajax({
        url: 'backend.php?action=read_one&id=' + id,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                let p = response.data;
                $('#edit_purchase_id').val(p.id);
                $('#edit_supplier').val(p.supplier === 'Pending - Akan diisi Owner' ? '' : p.supplier);
                
                let itemsHtml = '';
                let total = 0;
                p.items.forEach(function(item, index) {
                    let subtotal = item.qty * item.harga_beli;
                    total += subtotal;
                    itemsHtml += `
                        <tr>
                            <td>
                                ${item.sparepart_name}
                                <input type="hidden" name="items[${index}][sparepart_id]" value="${item.sparepart_id}">
                            </td>
                            <td>
                                ${item.qty}
                                <input type="hidden" name="items[${index}][qty]" value="${item.qty}">
                            </td>
                            <td>
                                <input type="number" step="0.01" class="form-control edit-item-price" name="items[${index}][harga_beli]" value="${item.harga_beli}" min="0" required onchange="calculateEditSubtotal()">
                            </td>
                            <td>
                                <strong class="edit-item-subtotal">Rp ${formatNumber(subtotal)}</strong>
                            </td>
                        </tr>
                    `;
                });
                
                $('#editItemsTableBody').html(itemsHtml);
                $('#editGrandTotal').text('Rp ' + formatNumber(total));
                $('#editPurchaseModal').modal('show');
            }
        }
    });
}

// Calculate subtotal di edit modal
function calculateEditSubtotal() {
    let total = 0;
    $('.edit-item-price').each(function(index) {
        let price = parseFloat($(this).val()) || 0;
        let qty = parseInt($('input[name="items[' + index + '][qty]"]').val()) || 0;
        let subtotal = qty * price;
        total += subtotal;
        $('.edit-item-subtotal').eq(index).text('Rp ' + formatNumber(subtotal));
    });
    $('#editGrandTotal').text('Rp ' + formatNumber(total));
}

// Submit edit purchase form
$('#editPurchaseForm').on('submit', function(e) {
    e.preventDefault();
    
    $.ajax({
        url: 'backend.php',
        type: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                $('#editPurchaseModal').modal('hide');
                loadPurchases();
            } else {
                showAlert('danger', response.message);
            }
        }
    });
});

// Update status (Approve/Refund)
function updateStatus(id, status) {
    let confirmMsg = status === 'Approved' 
        ? 'Approve purchase ini? Stock sparepart akan otomatis bertambah.' 
        : 'Refund purchase ini? Stock sparepart akan dikurangi kembali.';
    
    if (confirm(confirmMsg)) {
        $.ajax({
            url: 'backend.php',
            type: 'POST',
            data: {
                action: 'update_status',
                id: id,
                status: status
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('success', response.message);
                    loadPurchases();
                } else {
                    showAlert('danger', response.message);
                }
            }
        });
    }
}

// Update payment status
function updatePayment(id, isPaid) {
    $.ajax({
        url: 'backend.php',
        type: 'POST',
        data: {
            action: 'update_payment',
            id: id,
            is_paid: isPaid
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                loadPurchases();
            } else {
                showAlert('danger', response.message);
            }
        }
    });
}

// Hapus purchase
function deletePurchase(id) {
    if (confirm('Yakin ingin menghapus purchase ini?')) {
        $.ajax({
            url: 'backend.php',
            type: 'POST',
            data: {
                action: 'delete',
                id: id
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('success', response.message);
                    loadPurchases();
                } else {
                    showAlert('danger', response.message);
                }
            }
        });
    }
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
