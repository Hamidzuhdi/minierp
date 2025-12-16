<?php
session_start();
require_once '../config.php';

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$page_title = "Manajemen Customer";
include '../header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Data Customer</h5>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#customerModal" onclick="openAddModal()">
                        <i class="fas fa-plus"></i> Tambah Customer
                    </button>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <input type="text" class="form-control" id="searchInput" placeholder="Cari nama atau telepon...">
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="customerTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nama</th>
                                    <th>Telepon</th>
                                    <th>Alamat</th>
                                    <th>Dibuat</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="customerTableBody">
                                <tr>
                                    <td colspan="6" class="text-center">Loading...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Add/Edit Customer -->
<div class="modal fade" id="customerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="customerModalTitle">Tambah Customer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="customerForm">
                <div class="modal-body">
                    <input type="hidden" id="customerId" name="id">
                    <input type="hidden" id="formAction" name="action" value="create">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Nama Customer *</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">Telepon</label>
                        <input type="text" class="form-control" id="phone" name="phone">
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">Alamat</label>
                        <textarea class="form-control" id="address" name="address" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../footer.php'; ?>

<script>
// Load data saat halaman dimuat
$(document).ready(function() {
    loadCustomers();
    
    // Search dengan delay
    let searchTimeout;
    $('#searchInput').on('keyup', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            loadCustomers();
        }, 500);
    });
});

// Load semua customer
function loadCustomers() {
    let search = $('#searchInput').val();
    
    $.ajax({
        url: 'backend.php?action=read&search=' + encodeURIComponent(search),
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displayCustomers(response.data);
            } else {
                showAlert('danger', 'Gagal memuat data customer');
            }
        },
        error: function() {
            showAlert('danger', 'Terjadi kesalahan saat memuat data');
        }
    });
}

// Tampilkan data customer di tabel
function displayCustomers(customers) {
    let html = '';
    
    if (customers.length === 0) {
        html = '<tr><td colspan="6" class="text-center">Belum ada data customer</td></tr>';
    } else {
        customers.forEach(function(customer) {
            html += `
                <tr>
                    <td>${customer.id}</td>
                    <td>${customer.name}</td>
                    <td>${customer.phone || '-'}</td>
                    <td>${customer.address ? (customer.address.length > 50 ? customer.address.substring(0, 50) + '...' : customer.address) : '-'}</td>
                    <td>${formatDateTime(customer.created_at)}</td>
                    <td>
                        <button class="btn btn-warning btn-sm" onclick="editCustomer(${customer.id})">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-danger btn-sm" onclick="deleteCustomer(${customer.id}, '${customer.name.replace(/'/g, "\\'")}')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        });
    }
    
    $('#customerTableBody').html(html);
}

// Buka modal untuk tambah customer
function openAddModal() {
    $('#customerModalTitle').text('Tambah Customer');
    $('#customerForm')[0].reset();
    $('#customerId').val('');
    $('#formAction').val('create');
}

// Edit customer
function editCustomer(id) {
    $.ajax({
        url: 'backend.php?action=read_one&id=' + id,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                let customer = response.data;
                $('#customerModalTitle').text('Edit Customer');
                $('#customerId').val(customer.id);
                $('#name').val(customer.name);
                $('#phone').val(customer.phone);
                $('#address').val(customer.address);
                $('#formAction').val('update');
                $('#customerModal').modal('show');
            } else {
                showAlert('danger', response.message);
            }
        }
    });
}

// Hapus customer
function deleteCustomer(id, name) {
    if (confirm(`Yakin ingin menghapus customer "${name}"?`)) {
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
                    loadCustomers();
                } else {
                    showAlert('danger', response.message);
                }
            }
        });
    }
}

// Submit form (Create/Update)
$('#customerForm').on('submit', function(e) {
    e.preventDefault();
    
    let formData = $(this).serialize();
    
    $.ajax({
        url: 'backend.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                $('#customerModal').modal('hide');
                loadCustomers();
            } else {
                showAlert('danger', response.message);
            }
        }
    });
});

// Helper: Format datetime
function formatDateTime(datetime) {
    if (!datetime) return '-';
    let d = new Date(datetime);
    return d.toLocaleDateString('id-ID') + ' ' + d.toLocaleTimeString('id-ID', {hour: '2-digit', minute: '2-digit'});
}

// Helper: Show alert
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
