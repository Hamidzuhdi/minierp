<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_role = $_SESSION['role'] ?? 'Admin';
if ($user_role !== 'Owner' && $user_role !== 'Admin') {
    die('Akses ditolak');
}

$page_title = 'Payment & Cashflow';
include '../header.php';
?>

<style>
.approval-scroll {
    max-height: 280px;
    overflow-y: auto;
}
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="fas fa-wallet"></i> Payment & Cashflow</h5>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><small>Saldo Cash</small><h5 id="sumCash">Rp 0</h5></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><small>Saldo Rekening</small><h5 id="sumBank">Rp 0</h5></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><small>Total Saldo</small><h5 id="sumTotal">Rp 0</h5></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><small>Cashflow Bulan Ini</small><h5 id="sumMonthFlow">Rp 0</h5></div></div></div>
    </div>

    <?php if ($user_role === 'Owner'): ?>
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <strong>Approval Payment (Pending)</strong>
                    <button class="btn btn-sm btn-outline-primary" type="button" onclick="loadPendingApprovals()">Refresh</button>
                </div>
                <div class="card-body">
                    <div class="table-responsive approval-scroll">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Akun</th>
                                    <th>Arah</th>
                                    <th>Kategori</th>
                                    <th>Ref</th>
                                    <th>Dibuat Oleh</th>
                                    <th>Catatan</th>
                                    <th class="text-end">Nominal</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="approvalTableBody"><tr><td colspan="9" class="text-center text-muted">Loading...</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><strong>Input Pengeluaran Operasional</strong></div>
                <div class="card-body">
                    <form id="operationalForm">
                        <div class="mb-2">
                            <label class="form-label">Tanggal</label>
                            <input type="date" class="form-control" id="op_tanggal" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Nama Biaya</label>
                            <input type="text" class="form-control" id="op_name" placeholder="PDAM / Listrik / ATK" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Kategori Pengeluaran</label>
                            <select class="form-select" id="op_category" required>
                                <option value="">-- Pilih Kategori --</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Nominal</label>
                            <input type="number" class="form-control" id="op_amount" min="1" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Sumber Dana</label>
                            <select class="form-select" id="op_account" required>
                                <option value="">-- Pilih --</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Catatan</label>
                            <textarea class="form-control" id="op_note" rows="2"></textarea>
                        </div>
                        <button class="btn btn-primary w-100" type="submit"><i class="fas fa-save"></i> Simpan Pengeluaran</button>
                    </form>
                </div>
            </div>

        </div>

        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><strong>Histori Mutasi</strong></div>
                <div class="card-body">
                    <div class="mb-2 d-flex gap-2 flex-wrap">
                        <button class="btn btn-sm btn-outline-secondary quick-range-btn" data-range="today" type="button" onclick="applyQuickRange('today')">Hari Ini</button>
                        <button class="btn btn-sm btn-outline-secondary quick-range-btn" data-range="week" type="button" onclick="applyQuickRange('week')">Minggu Ini</button>
                        <button class="btn btn-sm btn-outline-secondary quick-range-btn" data-range="month" type="button" onclick="applyQuickRange('month')">Bulan Ini</button>
                        <button class="btn btn-sm btn-outline-secondary quick-range-btn" data-range="all" type="button" onclick="applyQuickRange('all')">Semua</button>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-2"><input type="date" class="form-control form-control-sm" id="f_from"></div>
                        <div class="col-md-2"><input type="date" class="form-control form-control-sm" id="f_to"></div>
                        <div class="col-md-2"><select class="form-select form-select-sm" id="f_account"><option value="">Semua Akun</option></select></div>
                        <div class="col-md-2"><select class="form-select form-select-sm" id="f_direction"><option value="">Semua Arah</option><option value="in">Masuk</option><option value="out">Keluar</option><option value="transfer_in">Transfer Masuk</option><option value="transfer_out">Transfer Keluar</option></select></div>
                        <div class="col-md-2"><select class="form-select form-select-sm" id="f_category"><option value="">Semua Kategori</option><option value="IN-CUST-PAYMENT">IN-CUST-PAYMENT</option><option value="IN-CUST-SPAREPART">IN-CUST-SPAREPART</option><option value="OUT-PO">OUT-PO</option><option value="TRF-IN">TRF-IN</option><option value="TRF-OUT">TRF-OUT</option></select></div>
                        <div class="col-md-2"><input type="text" class="form-control form-control-sm" id="f_keyword" placeholder="Keyword"></div>
                        <div class="col-12 col-md-2 d-grid"><button class="btn btn-sm btn-outline-primary" onclick="loadTransactions()" type="button">Filter</button></div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Akun</th>
                                    <th>Arah</th>
                                    <th>Status</th>
                                    <th>Kategori</th>
                                    <th>Ref</th>
                                    <th>Dibuat Oleh</th>
                                    <th>Catatan</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody id="txTableBody"><tr><td colspan="9" class="text-center">Loading...</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="row g-3 mt-1">
                <div class="<?php echo ($user_role === 'Owner') ? 'col-md-6' : 'col-12'; ?>">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white"><strong>Transfer Antar Akun</strong></div>
                        <div class="card-body">
                            <form id="transferForm">
                                <div class="mb-2">
                                    <label class="form-label">Tanggal</label>
                                    <input type="date" class="form-control" id="tf_tanggal" required>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">Dari Akun</label>
                                    <select class="form-select" id="tf_from" required>
                                        <option value="">-- Pilih --</option>
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">Ke Akun</label>
                                    <select class="form-select" id="tf_to" required>
                                        <option value="">-- Pilih --</option>
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">Nominal</label>
                                    <input type="number" class="form-control" id="tf_amount" min="1" required>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">Catatan</label>
                                    <textarea class="form-control" id="tf_note" rows="2"></textarea>
                                </div>
                                <button class="btn btn-outline-primary w-100" type="submit"><i class="fas fa-exchange-alt"></i> Simpan Transfer</button>
                            </form>
                        </div>
                    </div>
                </div>

                <?php if ($user_role === 'Owner'): ?>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white"><strong>Master Kategori Pengeluaran</strong></div>
                        <div class="card-body">
                            <form id="catForm" class="row g-2 mb-2">
                                <input type="hidden" id="cat_id">
                                <div class="col-12"><input type="text" class="form-control form-control-sm" id="cat_code" placeholder="Kode (contoh EXP-LAIN)" required></div>
                                <div class="col-12"><input type="text" class="form-control form-control-sm" id="cat_name" placeholder="Nama kategori" required></div>
                                <div class="col-12"><input type="text" class="form-control form-control-sm" id="cat_desc" placeholder="Deskripsi (opsional)"></div>
                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="cat_status">
                                        <label class="form-check-label" for="cat_status">Biaya Tetap</label>
                                    </div>
                                    <small class="text-muted">ON = Biaya Tetap (1), OFF = Biaya Tidak Tetap (0)</small>
                                </div>
                                <div class="col-12 d-grid"><button class="btn btn-sm btn-primary" type="submit">Simpan Kategori</button></div>
                            </form>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead class="table-light"><tr><th>Kode</th><th>Nama</th><th>Tipe Beban</th><th>Aksi</th></tr></thead>
                                    <tbody id="catTableBody"><tr><td colspan="4" class="text-center text-muted">Loading...</td></tr></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function fmt(n){ return 'Rp ' + parseFloat(n || 0).toLocaleString('id-ID'); }
const isOwner = '<?php echo $user_role; ?>' === 'Owner';
let expenseCategoryMap = {};

$(document).ready(function(){
    const today = new Date().toISOString().split('T')[0];
    $('#op_tanggal').val(today);
    $('#tf_tanggal').val(today);
    applyQuickRange('month', false);
    loadAccounts();
    loadExpenseCategories();
    loadSummary();
    loadTransactions();
    if (isOwner) {
        loadPendingApprovals();
    }

    $('#operationalForm').on('submit', function(e){
        e.preventDefault();
        $.ajax({
            url: 'backend.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'create_operational_expense',
                tanggal: $('#op_tanggal').val(),
                expense_name: $('#op_name').val(),
                category_code: $('#op_category').val(),
                amount: $('#op_amount').val(),
                account_code: $('#op_account').val(),
                note: $('#op_note').val()
            },
            success: function(res){
                if (res.success){
                    showAlert('success', res.message);
                    $('#op_name').val('');
                    $('#op_category').val('');
                    $('#op_amount').val('');
                    $('#op_note').val('');
                    loadSummary();
                    loadAccounts();
                    loadTransactions();
                    if (isOwner) loadPendingApprovals();
                } else {
                    showAlert('danger', res.message);
                }
            }
        });
    });

    $('#transferForm').on('submit', function(e){
        e.preventDefault();
        $.ajax({
            url: 'backend.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'create_transfer',
                tanggal: $('#tf_tanggal').val(),
                from_account_code: $('#tf_from').val(),
                to_account_code: $('#tf_to').val(),
                amount: $('#tf_amount').val(),
                note: $('#tf_note').val()
            },
            success: function(res){
                if (res.success){
                    showAlert('success', res.message);
                    $('#tf_amount').val('');
                    $('#tf_note').val('');
                    loadSummary();
                    loadAccounts();
                    loadTransactions();
                    if (isOwner) loadPendingApprovals();
                } else {
                    showAlert('danger', res.message);
                }
            }
        });
    });

    $('#catForm').on('submit', function(e){
        if (!isOwner) return;
        e.preventDefault();
        const id = $('#cat_id').val();
        $.ajax({
            url: 'backend.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: id ? 'update_expense_category' : 'create_expense_category',
                id: id,
                code: $('#cat_code').val(),
                name: $('#cat_name').val(),
                description: $('#cat_desc').val(),
                status: $('#cat_status').is(':checked') ? 1 : 0
            },
            success: function(res){
                if (res.success) {
                    showAlert('success', res.message);
                    resetCategoryForm();
                    loadExpenseCategories();
                } else {
                    showAlert('danger', res.message);
                }
            }
        });
    });
});

function applyQuickRange(type, triggerLoad = true) {
    $('.quick-range-btn')
        .removeClass('btn-secondary')
        .addClass('btn-outline-secondary');
    $(`.quick-range-btn[data-range="${type}"]`)
        .removeClass('btn-outline-secondary')
        .addClass('btn-secondary');

    const now = new Date();
    const yyyy = now.getFullYear();
    const mm = String(now.getMonth() + 1).padStart(2, '0');
    const dd = String(now.getDate()).padStart(2, '0');
    const today = `${yyyy}-${mm}-${dd}`;

    if (type === 'today') {
        $('#f_from').val(today);
        $('#f_to').val(today);
    } else if (type === 'week') {
        const day = now.getDay();
        const diffToMonday = day === 0 ? -6 : 1 - day;
        const monday = new Date(now);
        monday.setDate(now.getDate() + diffToMonday);
        const mon = `${monday.getFullYear()}-${String(monday.getMonth() + 1).padStart(2, '0')}-${String(monday.getDate()).padStart(2, '0')}`;
        $('#f_from').val(mon);
        $('#f_to').val(today);
    } else if (type === 'month') {
        $('#f_from').val(`${yyyy}-${mm}-01`);
        $('#f_to').val(today);
    } else {
        $('#f_from').val('');
        $('#f_to').val('');
    }

    if (triggerLoad) {
        loadTransactions();
    }
}

function loadAccounts(){
    $.getJSON('backend.php?action=get_accounts', function(res){
        if (!res.success) return;
        let op = '<option value="">-- Pilih --</option>';
        let tfFrom = '<option value="">-- Pilih --</option>';
        let tfTo = '<option value="">-- Pilih --</option>';
        let filter = '<option value="">Semua Akun</option>';
        res.data.forEach(function(a){
            const label = a.name + ' (Saldo: ' + fmt(a.current_balance) + ')';
            op += `<option value="${a.code}">${label}</option>`;
            tfFrom += `<option value="${a.code}">${label}</option>`;
            tfTo += `<option value="${a.code}">${label}</option>`;
            filter += `<option value="${a.code}">${a.name}</option>`;
        });
        $('#op_account').html(op);
        $('#tf_from').html(tfFrom);
        $('#tf_to').html(tfTo);
        $('#f_account').html(filter);

        // Auto-pick default account to avoid empty account on submit.
        if (res.data.length > 0) {
            const firstCode = res.data[0].code;
            const secondCode = res.data.length > 1 ? res.data[1].code : firstCode;
            if (!$('#op_account').val()) {
                $('#op_account').val(firstCode);
            }
            if (!$('#tf_from').val()) {
                $('#tf_from').val(firstCode);
            }
            if (!$('#tf_to').val()) {
                $('#tf_to').val(secondCode);
            }
        }
    });
}

function loadExpenseCategories(){
    $.getJSON('backend.php?action=get_expense_categories', function(res){
        if (!res.success) return;
        let opCat = '<option value="">-- Pilih Kategori --</option>';
        let catHtml = '';
        expenseCategoryMap = {};
        res.data.forEach(function(c){
            expenseCategoryMap[c.id] = c;
            opCat += `<option value="${c.code}">${c.code}</option>`;
            const isFixed = parseInt(c.status, 10) === 1;
            catHtml += `<tr>
                <td>${c.code}</td>
                <td>${c.name}</td>
                <td>
                    <span class="badge ${isFixed ? 'bg-primary' : 'bg-secondary'}">${isFixed ? 'Beban Tetap' : 'Beban Tidak Tetap'}</span>
                    ${isOwner ? `<div class="form-check form-switch mt-1 mb-0">
                        <input class="form-check-input" type="checkbox" ${isFixed ? 'checked' : ''} onchange="toggleCategoryStatus(${c.id}, this.checked)">
                    </div>` : ''}
                </td>
                <td>
                    ${isOwner ? `<button type="button" class="btn btn-warning btn-sm me-1" onclick="editCategory(${c.id})"><i class="fas fa-edit"></i></button>
                    <button type="button" class="btn btn-danger btn-sm" onclick='deleteCategory(${c.id})'><i class="fas fa-trash"></i></button>` : '-'}
                </td>
            </tr>`;
        });
        if (!catHtml) catHtml = '<tr><td colspan="4" class="text-center text-muted">Belum ada kategori</td></tr>';
        $('#op_category').html(opCat);
        $('#catTableBody').html(catHtml);

        // Rebuild filter categories (dynamic + static)
        let fCat = '<option value="">Semua Kategori</option>' +
                   '<option value="IN-CUST-PAYMENT">IN-CUST-PAYMENT</option>' +
                   '<option value="IN-CUST-SPAREPART">IN-CUST-SPAREPART</option>' +
                   '<option value="OUT-PO">OUT-PO</option>' +
                   '<option value="TRF-IN">TRF-IN</option>' +
                   '<option value="TRF-OUT">TRF-OUT</option>';
        res.data.forEach(function(c){
            fCat += `<option value="${c.code}">${c.code}</option>`;
        });
        $('#f_category').html(fCat);
    });
}

function editCategory(categoryId){
    const c = expenseCategoryMap[categoryId];
    if (!c) return;
    $('#cat_id').val(c.id);
    $('#cat_code').val(c.code);
    $('#cat_name').val(c.name);
    $('#cat_desc').val(c.description || '');
    $('#cat_status').prop('checked', parseInt(c.status, 10) === 1);
}

function resetCategoryForm(){
    $('#cat_id').val('');
    $('#cat_code').val('');
    $('#cat_name').val('');
    $('#cat_desc').val('');
    $('#cat_status').prop('checked', false);
}

function toggleCategoryStatus(id, isChecked){
    if (!isOwner) return;
    $.ajax({
        url: 'backend.php',
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'toggle_expense_category_status',
            id: id,
            status: isChecked ? 1 : 0
        },
        success: function(res){
            if (res.success){
                loadExpenseCategories();
            } else {
                showAlert('danger', res.message || 'Gagal update status kategori');
                loadExpenseCategories();
            }
        },
        error: function(){
            showAlert('danger', 'Gagal update status kategori');
            loadExpenseCategories();
        }
    });
}

function deleteCategory(id){
    if (!confirm('Hapus kategori ini?')) return;
    $.ajax({
        url: 'backend.php',
        type: 'POST',
        dataType: 'json',
        data: { action: 'delete_expense_category', id: id },
        success: function(res){
            if (res.success){
                showAlert('success', res.message);
                resetCategoryForm();
                loadExpenseCategories();
            } else {
                showAlert('danger', res.message);
            }
        }
    });
}

function loadSummary(){
    $.getJSON('backend.php?action=summary', function(res){
        if (!res.success) return;
        const d = res.data;
        $('#sumCash').text(fmt(d.cash_balance));
        $('#sumBank').text(fmt(d.bank_balance));
        $('#sumTotal').text(fmt(d.total_balance));
        $('#sumMonthFlow').text(fmt(d.month_in - d.month_out));
    });
}

function loadTransactions(){
    $.ajax({
        url: 'backend.php',
        dataType: 'json',
        data: {
            action: 'read_transactions',
            from: $('#f_from').val(),
            to: $('#f_to').val(),
            account: $('#f_account').val(),
            direction: $('#f_direction').val(),
            category: $('#f_category').val(),
            keyword: $('#f_keyword').val()
        },
        success: function(res){
            if (!res.success) return;
            let html = '';
            if (!res.data.length){
                html = '<tr><td colspan="9" class="text-center text-muted">Belum ada data</td></tr>';
            } else {
                res.data.forEach(function(t){
                    const out = (t.direction === 'out' || t.direction === 'transfer_out');
                    const cls = out ? 'text-danger' : 'text-success';
                    const sign = out ? '-' : '+';
                    const dirLabelMap = {
                        in: 'Masuk',
                        out: 'Keluar',
                        transfer_in: 'Transfer Masuk',
                        transfer_out: 'Transfer Keluar'
                    };
                    let creatorLabel = '-';
                    if (t.created_by) {
                        const id = parseInt(t.created_by, 10);
                        const roleMap = { 1: 'Owner', 2: 'Admin' };
                        creatorLabel = roleMap[id] || ('User #' + id);
                        if (t.created_by_name) {
                            creatorLabel += ' (' + t.created_by_name + ')';
                        }
                    }

                    const status = t.status || 'approved';
                    const statusBadgeMap = {
                        approved: 'success',
                        pending: 'warning',
                        rejected: 'danger'
                    };
                    const statusBadge = `<span class="badge bg-${statusBadgeMap[status] || 'secondary'}">${status}</span>`;

                    html += `<tr>
                        <td>${t.tanggal}</td>
                        <td>${t.account_name}</td>
                        <td>${dirLabelMap[t.direction] || t.direction}</td>
                        <td>${statusBadge}</td>
                        <td>${t.category || '-'}</td>
                        <td>${(t.reference_type || '-')}${t.reference_id ? (' #' + t.reference_id) : ''}</td>
                        <td>${creatorLabel}</td>
                        <td>${t.note || '-'}</td>
                        <td class="text-end ${cls}">${sign}${fmt(t.amount)}</td>
                    </tr>`;
                });
            }
            $('#txTableBody').html(html);
        }
    });
}

function loadPendingApprovals() {
    if (!isOwner) return;
    $.getJSON('backend.php?action=read_pending_transactions', function(res) {
        if (!res.success) {
            $('#approvalTableBody').html('<tr><td colspan="9" class="text-center text-danger">Gagal memuat approval pending</td></tr>');
            return;
        }

        let html = '';
        if (!res.data.length) {
            html = '<tr><td colspan="9" class="text-center text-muted">Tidak ada transaksi pending</td></tr>';
        } else {
            const dirLabelMap = {
                in: 'Masuk',
                out: 'Keluar',
                transfer_in: 'Transfer Masuk',
                transfer_out: 'Transfer Keluar'
            };

            res.data.forEach(function(t) {
                const out = (t.direction === 'out' || t.direction === 'transfer_out');
                const cls = out ? 'text-danger' : 'text-success';
                const sign = out ? '-' : '+';
                const creator = t.created_by_name ? t.created_by_name : ('User #' + (t.created_by || '-'));

                html += `<tr>
                    <td>${t.tanggal}</td>
                    <td>${t.account_name || '-'}</td>
                    <td>${dirLabelMap[t.direction] || t.direction}</td>
                    <td>${t.category || '-'}</td>
                    <td>${(t.reference_type || '-')}${t.reference_id ? (' #' + t.reference_id) : ''}</td>
                    <td>${creator}</td>
                    <td>${t.note || '-'}</td>
                    <td class="text-end ${cls}">${sign}${fmt(t.amount)}</td>
                    <td>
                        <button class="btn btn-success btn-sm me-1" onclick="approveTransaction(${t.id})">Approve</button>
                        <button class="btn btn-outline-danger btn-sm" onclick="rejectTransaction(${t.id})">Reject</button>
                    </td>
                </tr>`;
            });
        }

        $('#approvalTableBody').html(html);
    });
}

function approveTransaction(id) {
    $.ajax({
        url: 'backend.php',
        type: 'POST',
        dataType: 'json',
        data: { action: 'approve_transaction', id: id },
        success: function(res) {
            if (res.success) {
                showAlert('success', res.message);
                loadPendingApprovals();
                loadSummary();
                loadAccounts();
                loadTransactions();
            } else {
                showAlert('danger', res.message || 'Gagal approve transaksi');
            }
        }
    });
}

function rejectTransaction(id) {
    $.ajax({
        url: 'backend.php',
        type: 'POST',
        dataType: 'json',
        data: { action: 'reject_transaction', id: id },
        success: function(res) {
            if (res.success) {
                showAlert('success', res.message);
                loadPendingApprovals();
                loadTransactions();
            } else {
                showAlert('danger', res.message || 'Gagal reject transaksi');
            }
        }
    });
}

function showAlert(type, message) {
    let alertHtml = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    $('.container-fluid').prepend(alertHtml);
    setTimeout(function(){ $('.alert').fadeOut(function(){ $(this).remove(); }); }, 3000);
}
</script>

<?php include '../footer.php'; ?>
