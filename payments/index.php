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

            <div class="card border-0 shadow-sm mt-3">
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
                        <div class="col-md-2"><select class="form-select form-select-sm" id="f_category"><option value="">Semua Kategori</option><option value="invoice_sparepart_in">Invoice Sparepart</option><option value="purchase_payment_out">Bayar Purchase</option><option value="operational_expense_out">Biaya Operasional</option><option value="transfer_in">Transfer Masuk</option><option value="transfer_out">Transfer Keluar</option></select></div>
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
                                    <th>Kategori</th>
                                    <th>Ref</th>
                                    <th>Catatan</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody id="txTableBody"><tr><td colspan="7" class="text-center">Loading...</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function fmt(n){ return 'Rp ' + parseFloat(n || 0).toLocaleString('id-ID'); }

$(document).ready(function(){
    const today = new Date().toISOString().split('T')[0];
    $('#op_tanggal').val(today);
    $('#tf_tanggal').val(today);
    applyQuickRange('month', false);
    loadAccounts();
    loadSummary();
    loadTransactions();

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
                amount: $('#op_amount').val(),
                account_code: $('#op_account').val(),
                note: $('#op_note').val()
            },
            success: function(res){
                if (res.success){
                    showAlert('success', res.message);
                    $('#op_name').val('');
                    $('#op_amount').val('');
                    $('#op_note').val('');
                    loadSummary();
                    loadAccounts();
                    loadTransactions();
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
                html = '<tr><td colspan="7" class="text-center text-muted">Belum ada data</td></tr>';
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
                    const catLabelMap = {
                        invoice_sparepart_in: 'Invoice Sparepart',
                        purchase_payment_out: 'Bayar Purchase',
                        operational_expense_out: 'Biaya Operasional',
                        transfer_in: 'Transfer Masuk',
                        transfer_out: 'Transfer Keluar'
                    };
                    html += `<tr>
                        <td>${t.tanggal}</td>
                        <td>${t.account_name}</td>
                        <td>${dirLabelMap[t.direction] || t.direction}</td>
                        <td>${catLabelMap[t.category] || t.category}</td>
                        <td>${(t.reference_type || '-')}${t.reference_id ? (' #' + t.reference_id) : ''}</td>
                        <td>${t.note || '-'}</td>
                        <td class="text-end ${cls}">${sign}${fmt(t.amount)}</td>
                    </tr>`;
                });
            }
            $('#txTableBody').html(html);
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
