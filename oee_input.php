<?php
// ============================================================
//  oee_input.php — Input & Konfigurasi OEE
//  Operator-friendly: Jam Kerja · Target Plan · Produksi/Reject
// ============================================================
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth_check.php';

$pageTitle   = 'Input OEE';
$currentPage = 'oee_input';
$db = getDB();

$machines  = $db->query("SELECT id, name FROM machines ORDER BY name")->fetchAll();
$machineId = (int)($_GET['machine_id'] ?? ($machines[0]['id'] ?? 0));
$dateInput = $_GET['date'] ?? date('Y-m-d');

require_once __DIR__ . '/includes/header.php';
?>
<style>
/* ── Inline editable cells ────────────────────────────────── */
.editable { cursor:pointer; border-bottom:1px dashed #b0b8c8; min-width:40px; display:inline-block; }
.editable:hover { background:#fffbe6; border-radius:3px; }
.editable:focus { outline:2px solid #4e73df; border-bottom:none; border-radius:3px; background:#fff; padding:1px 4px; }
/* ── OEE donut labels ─────────────────────────────────────── */
.oee-wrap { position:relative; width:90px; height:90px; margin:0 auto 4px; }
.oee-canvas { width:90px; height:90px; }
.oee-label { position:absolute; top:50%; left:50%; transform:translate(-50%,-50%);
             text-align:center; pointer-events:none; }
.oee-val { font-size:1.15rem; font-weight:900; line-height:1; }
.oee-sub { font-size:.62rem; color:#aaa; }
/* ── Shift row colors ─────────────────────────────────────── */
tr.s1 td:first-child { border-left:3px solid #4e73df; }
tr.s2 td:first-child { border-left:3px solid #1cc88a; }
tr.s3 td:first-child { border-left:3px solid #f6c23e; }
tr.s4 td:first-child { border-left:3px solid #e74a3b; }
/* ── Saving indicator ─────────────────────────────────────── */
.saving { opacity:.5; pointer-events:none; }
.saved-flash { animation: savedFlash .6s; }
@keyframes savedFlash { 0%{background:#d4edda} 100%{background:transparent} }
/* ── Break rows ───────────────────────────────────────────── */
.break-row td { background:#fffbf0; font-size:.76rem; padding:3px 8px !important; }
.break-row td:first-child { border-left:3px solid #f6c23e !important; padding-left:20px !important; }
.break-badge { display:inline-block; background:#fff3cd; color:#856404;
               border:1px solid #ffc107; border-radius:10px;
               padding:1px 7px; font-size:.68rem; font-weight:600; }
.btn-add-break { font-size:.68rem; padding:1px 7px; border-radius:10px; }
</style>

<!-- ── Heading ───────────────────────────────────────────────── -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-3" style="gap:8px;">
    <div>
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-clipboard-list mr-2"></i>Input OEE</h1>
        <small class="text-muted">Atur jam kerja, target, dan produksi tanpa perlu administrator</small>
    </div>
    <div class="d-flex flex-wrap align-items-center" style="gap:6px;">
        <select id="selMachine" class="form-control form-control-sm" style="width:160px;">
            <?php foreach ($machines as $m): ?>
            <option value="<?= $m['id'] ?>" <?= $machineId==$m['id']?'selected':'' ?>>
                <?= htmlspecialchars($m['name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
        <input type="date" id="selDate" class="form-control form-control-sm" style="width:140px;"
               value="<?= htmlspecialchars($dateInput) ?>">
        <button class="btn btn-primary btn-sm" onclick="loadAll()">
            <i class="fas fa-sync-alt mr-1"></i>Muat
        </button>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!--  ROW 1 : OEE Summary (atas) -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="row mb-3" id="oeeRow">
    <!-- diisi JS -->
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!--  ROW 2 : 3 panel berdampingan -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="row">

    <!-- ── PANEL 1: Jam Kerja + Istirahat ────────────────────── -->
    <div class="col-lg-4 mb-4">
        <div class="card shadow h-100">
            <div class="card-header py-2 d-flex align-items-center justify-content-between"
                 style="background:#f0f3fa;">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-clock mr-1"></i>Jam Kerja
                    <small class="text-muted font-weight-normal ml-1" style="font-size:.7rem;">
                        (Availability)
                    </small>
                </h6>
                <button class="btn btn-sm btn-outline-primary" style="font-size:.75rem;"
                        onclick="openAddShift()">
                    <i class="fas fa-plus mr-1"></i>Tambah Shift
                </button>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0" id="tblShift">
                    <thead class="thead-light">
                        <tr>
                            <th style="width:28px;"></th>
                            <th>Nama Shift</th>
                            <th>Mulai</th>
                            <th>Selesai</th>
                            <th>Netto</th>
                            <th style="width:28px;"></th>
                        </tr>
                    </thead>
                    <tbody id="bodyShift">
                        <tr><td colspan="6" class="text-center text-muted py-3">Memuat…</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="card-footer py-1 px-3 text-muted" style="font-size:.72rem;">
                <i class="fas fa-info-circle mr-1"></i>
                Klik <i class="fas fa-coffee text-warning"></i> untuk kelola istirahat per shift ·
                Istirahat <strong>tidak</strong> mengurangi Availability
            </div>
        </div>
    </div>

    <!-- ── PANEL 2: Target Plan ────────────────────────────── -->
    <div class="col-lg-4 mb-4">
        <div class="card shadow h-100">
            <div class="card-header py-2" style="background:#f0f3fa;">
                <h6 class="m-0 font-weight-bold text-success">
                    <i class="fas fa-bullseye mr-1"></i>Target Produksi
                    <small class="text-muted font-weight-normal ml-1" style="font-size:.7rem;">
                        (Performance)
                    </small>
                </h6>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0" id="tblPlan">
                    <thead class="thead-light">
                        <tr>
                            <th>Shift</th>
                            <th>Default</th>
                            <th>Target Hari Ini <i class="fas fa-edit text-muted" style="font-size:.65rem;"></i></th>
                            <th>Aktual</th>
                            <th>Performa</th>
                        </tr>
                    </thead>
                    <tbody id="bodyPlan">
                        <tr><td colspan="5" class="text-center text-muted py-3">Memuat…</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="card-footer py-1 px-3 text-muted" style="font-size:.72rem;">
                <i class="fas fa-info-circle mr-1"></i>
                Klik kolom "Target Hari Ini" untuk mengubah target khusus hari ini
            </div>
        </div>
    </div>

    <!-- ── PANEL 3: Input Produksi & Reject ───────────────── -->
    <div class="col-lg-4 mb-4">
        <div class="card shadow h-100">
            <div class="card-header py-2" style="background:#f0f3fa;">
                <h6 class="m-0 font-weight-bold text-danger">
                    <i class="fas fa-boxes mr-1"></i>Produksi & Reject
                    <small class="text-muted font-weight-normal ml-1" style="font-size:.7rem;">
                        (Quality)
                    </small>
                </h6>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0" id="tblProd">
                    <thead class="thead-light">
                        <tr>
                            <th>Shift</th>
                            <th>Output <i class="fas fa-edit text-muted" style="font-size:.65rem;"></i></th>
                            <th>Reject <i class="fas fa-edit text-muted" style="font-size:.65rem;"></i></th>
                            <th>Good</th>
                            <th>Quality</th>
                        </tr>
                    </thead>
                    <tbody id="bodyProd">
                        <tr><td colspan="5" class="text-center text-muted py-3">Memuat…</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="card-footer py-1 px-3 text-muted" style="font-size:.72rem;">
                <i class="fas fa-info-circle mr-1"></i>
                Edit Output/Reject lalu <kbd>Enter</kbd> atau klik di luar — tersimpan otomatis
            </div>
        </div>
    </div>
</div>

<!-- ── Operator & Catatan ─────────────────────────────────────── -->
<div class="row mb-4">
    <div class="col-lg-6">
        <div class="card shadow">
            <div class="card-header py-2" style="background:#f0f3fa;">
                <h6 class="m-0 font-weight-bold text-secondary">
                    <i class="fas fa-user-hard-hat mr-1"></i>Operator & Catatan
                </h6>
            </div>
            <div class="card-body py-3">
                <div class="form-row">
                    <div class="col-md-5 mb-2">
                        <label class="small font-weight-bold">Shift</label>
                        <select id="noteShift" class="form-control form-control-sm">
                            <option value="">Pilih shift…</option>
                        </select>
                    </div>
                    <div class="col-md-7 mb-2">
                        <label class="small font-weight-bold">Nama Operator</label>
                        <input type="text" id="noteOperator" class="form-control form-control-sm"
                               placeholder="Nama operator shift ini">
                    </div>
                    <div class="col-12 mb-2">
                        <label class="small font-weight-bold">Catatan</label>
                        <textarea id="noteText" class="form-control form-control-sm" rows="2"
                                  placeholder="Catatan produksi, kendala, dll…"></textarea>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-secondary btn-sm" onclick="saveNotes()">
                            <i class="fas fa-save mr-1"></i>Simpan Catatan
                        </button>
                        <small id="noteStatus" class="ml-2 text-muted"></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow h-100">
            <div class="card-header py-2" style="background:#f0f3fa;">
                <h6 class="m-0 font-weight-bold text-secondary">
                    <i class="fas fa-history mr-1"></i>Riwayat Perubahan Hari Ini
                </h6>
            </div>
            <div class="card-body p-0">
                <div id="auditLog" style="max-height:130px;overflow-y:auto;font-size:.75rem;padding:8px 12px;">
                    <span class="text-muted">Belum ada perubahan sesi ini.</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════ -->
<!--  MODAL: Tambah / Edit Shift -->
<!-- ════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalShift" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title font-weight-bold" id="modalShiftTitle">Tambah Shift</h6>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="mShiftId">
                <div class="form-group mb-2">
                    <label class="small font-weight-bold">No. Shift</label>
                    <input type="number" id="mShiftNo" class="form-control form-control-sm"
                           min="1" max="4" placeholder="1">
                </div>
                <div class="form-group mb-2">
                    <label class="small font-weight-bold">Nama Shift</label>
                    <input type="text" id="mShiftName" class="form-control form-control-sm"
                           placeholder="Shift Pagi">
                </div>
                <div class="form-row mb-2">
                    <div class="col">
                        <label class="small font-weight-bold">Mulai</label>
                        <input type="time" id="mStart" class="form-control form-control-sm">
                    </div>
                    <div class="col">
                        <label class="small font-weight-bold">Selesai</label>
                        <input type="time" id="mEnd" class="form-control form-control-sm">
                    </div>
                </div>
                <div class="form-group mb-0">
                    <label class="small font-weight-bold">Target Default (pcs)</label>
                    <input type="number" id="mPlan" class="form-control form-control-sm"
                           min="0" placeholder="0">
                </div>
            </div>
            <div class="modal-footer py-2">
                <button class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                <button class="btn btn-primary btn-sm" onclick="saveShift()">
                    <i class="fas fa-save mr-1"></i>Simpan
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════ -->
<!--  MODAL: Jam Istirahat -->
<!-- ════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalBreak" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title font-weight-bold">
                    <i class="fas fa-coffee text-warning mr-1"></i>
                    Jam Istirahat — <span id="mBreakShiftName"></span>
                </h6>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body p-2">
                <input type="hidden" id="mBreakShiftNo">
                <!-- List istirahat -->
                <div id="breakList" class="mb-2"></div>
                <!-- Form tambah / edit -->
                <div id="breakForm" class="border rounded p-2" style="background:#fffbf0;">
                    <input type="hidden" id="mBreakId">
                    <div class="form-group mb-1">
                        <label class="small font-weight-bold mb-0">Nama Istirahat</label>
                        <input type="text" id="mBreakName" class="form-control form-control-sm"
                               placeholder="Istirahat Makan Siang">
                    </div>
                    <div class="form-row mb-1">
                        <div class="col">
                            <label class="small font-weight-bold mb-0">Mulai</label>
                            <input type="time" id="mBreakStart" class="form-control form-control-sm">
                        </div>
                        <div class="col">
                            <label class="small font-weight-bold mb-0">Selesai</label>
                            <input type="time" id="mBreakEnd" class="form-control form-control-sm">
                        </div>
                    </div>
                    <div class="d-flex" style="gap:4px;">
                        <button class="btn btn-warning btn-sm flex-fill" onclick="saveBreak()">
                            <i class="fas fa-save mr-1"></i>Simpan Istirahat
                        </button>
                        <button class="btn btn-light btn-sm" onclick="clearBreakForm()">
                            Batal
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2 d-flex justify-content-between align-items-center">
                <small class="text-muted">
                    <i class="fas fa-info-circle mr-1"></i>
                    Istirahat dikecualikan dari kalkulasi Availability
                </small>
                <button class="btn btn-secondary btn-sm" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
var MID  = <?= (int)$machineId ?>;
var DATE = '<?= htmlspecialchars($dateInput) ?>';
var _shifts = [];
var SCOLORS = ['','#4e73df','#1cc88a','#f6c23e','#e74a3b'];
var _auditLog = [];
var _donutChart = {};

$(document).ready(function(){
    $('#selMachine, #selDate').on('change', function(){
        MID  = parseInt($('#selMachine').val())||0;
        DATE = $('#selDate').val();
        loadAll();
    });
    loadAll();
});

function loadAll() {
    loadShiftConfig();
    loadDaySummary();
}

// ── 1. Shift config + break rows ────────────────────────────
function loadShiftConfig() {
    if (!MID) return;
    $.get('api/shift_config.php', {action:'list', machine_id:MID}, function(d) {
        if (!d.success) return;
        var tb = $('#bodyShift').empty();
        if (!d.data.length) {
            tb.append('<tr><td colspan="6" class="text-center text-muted py-2">Belum ada shift</td></tr>');
            return;
        }
        d.data.forEach(function(r) {
            // Durasi netto = shift - break
            var netMin  = parseFloat(r.net_minutes)||0;
            var brkMin  = parseInt(r.break_minutes)||0;
            var netStr  = Math.floor(netMin/60)+'j '+Math.round(netMin%60)+'m';
            var netHtml = '<span title="'+Math.floor((parseFloat(r.duration_min)||0)/60)+'j '
                         +Math.round((parseFloat(r.duration_min)||0)%60)+'m kotor">'+netStr+'</span>';
            if (brkMin>0) netHtml += ' <span class="text-warning" style="font-size:.65rem;" title="istirahat '+brkMin+' mnt dikecualikan">(-'+brkMin+'m)</span>';

            var row = $('<tr>').addClass('s'+r.shift_no)
                .append($('<td>').html(
                    '<button class="btn btn-xs '+(r.is_active?'btn-success':'btn-secondary')+
                    ' px-1 py-0" onclick="toggleShift('+r.id+')" title="Aktif/Nonaktif" '+
                    'style="font-size:.65rem;line-height:1.2;">'+
                    (r.is_active?'<i class="fas fa-check"></i>':'<i class="fas fa-times"></i>')+
                    '</button>'
                ))
                .append($('<td>').html(
                    '<span style="color:'+SCOLORS[r.shift_no||1]+';font-weight:700;">S'+r.shift_no+'</span> ' +
                    '<span class="editable" contenteditable="true" data-id="'+r.id+'" data-field="shift_name"'+
                    ' data-mid="'+MID+'" data-sno="'+r.shift_no+'" data-start="'+r.start_time+'" data-end="'+r.end_time+'" data-plan="'+r.plan_qty+'"'+
                    '>'+escH(r.shift_name)+'</span>'+
                    ' <button class="btn btn-xs btn-outline-warning btn-add-break px-1" '+
                    'onclick="openBreakModal('+r.shift_no+',\''+escH(r.shift_name)+'\')" '+
                    'title="Kelola jam istirahat"><i class="fas fa-coffee"></i></button>'
                ))
                .append($('<td>').html(
                    '<span class="editable" contenteditable="true" data-id="'+r.id+'" data-field="start_time"'+
                    ' data-mid="'+MID+'" data-sno="'+r.shift_no+'" data-start="'+r.start_time+'" data-end="'+r.end_time+'" data-plan="'+r.plan_qty+'"'+
                    '>'+r.start_time.substr(0,5)+'</span>'
                ))
                .append($('<td>').html(
                    '<span class="editable" contenteditable="true" data-id="'+r.id+'" data-field="end_time"'+
                    ' data-mid="'+MID+'" data-sno="'+r.shift_no+'" data-start="'+r.start_time+'" data-end="'+r.end_time+'" data-plan="'+r.plan_qty+'"'+
                    '>'+r.end_time.substr(0,5)+'</span>'
                ))
                .append($('<td style="font-size:.75rem;">').html(netHtml))
                .append($('<td>').html(
                    '<button class="btn btn-xs btn-outline-danger px-1 py-0" onclick="deleteShift('+r.id+',\''+escH(r.shift_name)+'\','+MID+')" '+
                    'style="font-size:.65rem;"><i class="fas fa-trash"></i></button>'
                ));
            tb.append(row);
        });
        bindInlineShift();
    });
}

function bindInlineShift() {
    $('#bodyShift .editable').off('blur keydown').on('keydown', function(e) {
        if (e.key==='Enter') { e.preventDefault(); $(this).blur(); }
        if (e.key==='Escape') { loadShiftConfig(); }
    }).on('blur', function() {
        var el = $(this), fld = el.data('field'), val = el.text().trim();
        var id = el.data('id'), mid = el.data('mid'), sno = el.data('sno');
        var payload = {
            action:'save', id:id, machine_id:mid, shift_no:sno,
            shift_name: fld==='shift_name' ? val : el.data('start'),
            start_time: fld==='start_time' ? val : el.data('start'),
            end_time:   fld==='end_time'   ? val : el.data('end'),
            plan_qty:   parseInt(el.data('plan'))||0
        };
        if (fld==='shift_name') payload.shift_name = val;
        apiPost('api/shift_config.php', payload, function(d) {
            if (d.success) { flash(el); addLog('Jam kerja diperbarui: '+val); loadDaySummary(); }
            else { alert(d.error); loadShiftConfig(); }
        });
    });
}

function toggleShift(id) {
    apiPost('api/shift_config.php', {action:'toggle', id:id, machine_id:MID}, function(d) {
        if (d.success) { loadShiftConfig(); loadDaySummary(); }
    });
}
function deleteShift(id, name, mid) {
    if (!confirm('Hapus shift "'+name+'"? Data produksi yang sudah ada tidak akan hilang.')) return;
    apiPost('api/shift_config.php', {action:'delete', id:id, machine_id:mid}, function(d) {
        if (d.success) { loadShiftConfig(); loadDaySummary(); addLog('Shift "'+name+'" dihapus'); }
    });
}
function openAddShift() {
    $('#mShiftId').val(''); $('#mShiftNo').val(''); $('#mShiftName').val('');
    $('#mStart').val(''); $('#mEnd').val(''); $('#mPlan').val('');
    $('#modalShiftTitle').text('Tambah Shift');
    $('#modalShift').modal('show');
}
function saveShift() {
    var payload = {
        action:'save', id:parseInt($('#mShiftId').val())||0,
        machine_id:MID, shift_no:parseInt($('#mShiftNo').val())||0,
        shift_name:$('#mShiftName').val().trim(),
        start_time:$('#mStart').val(), end_time:$('#mEnd').val(),
        plan_qty:parseInt($('#mPlan').val())||0
    };
    apiPost('api/shift_config.php', payload, function(d) {
        if (d.success) { $('#modalShift').modal('hide'); loadShiftConfig(); loadDaySummary(); addLog('Shift disimpan'); }
        else alert(d.error);
    });
}

// ── Istirahat modal ──────────────────────────────────────────
function openBreakModal(sno, sname) {
    $('#mBreakShiftNo').val(sno);
    $('#mBreakShiftName').text(sname);
    clearBreakForm();
    loadBreakList(sno);
    $('#modalBreak').modal('show');
}

function loadBreakList(sno) {
    if (!sno) sno = parseInt($('#mBreakShiftNo').val())||0;
    $.get('api/shift_config.php', {action:'breaks', machine_id:MID, shift_no:sno}, function(d) {
        var c = $('#breakList').empty();
        if (!d.success || !d.data.length) {
            c.html('<p class="text-muted mb-1" style="font-size:.75rem;">Belum ada jam istirahat.</p>');
            return;
        }
        d.data.forEach(function(b) {
            var dur = Math.round(parseFloat(b.duration_min));
            var row = $('<div class="d-flex align-items-center mb-1 p-1 rounded" '+
                        'style="background:'+(b.is_active?'#fff3cd':'#f8f9fa')+';border:1px solid '+(b.is_active?'#ffc107':'#dee2e6')+';">');
            row.append($('<div class="flex-fill" style="font-size:.78rem;">').html(
                '<strong>'+escH(b.break_name)+'</strong> '+
                '<span class="text-muted">'+b.start_time.substr(0,5)+'–'+b.end_time.substr(0,5)+
                ' ('+dur+' mnt)</span>'
            ));
            row.append($('<div class="d-flex" style="gap:3px;">').html(
                '<button class="btn btn-xs btn-outline-primary px-1 py-0" title="Edit" '+
                'onclick="editBreak('+b.id+',\''+escH(b.break_name)+'\',\''+b.start_time.substr(0,5)+'\',\''+b.end_time.substr(0,5)+'\')">'+
                '<i class="fas fa-edit"></i></button>'+
                '<button class="btn btn-xs '+(b.is_active?'btn-warning':'btn-secondary')+' px-1 py-0" '+
                'onclick="toggleBreak('+b.id+')" title="'+(b.is_active?'Nonaktifkan':'Aktifkan')+'">'+
                '<i class="fas fa-'+(b.is_active?'pause':'play')+'"></i></button>'+
                '<button class="btn btn-xs btn-outline-danger px-1 py-0" '+
                'onclick="deleteBreak('+b.id+')" title="Hapus">'+
                '<i class="fas fa-trash"></i></button>'
            ));
            c.append(row);
        });
    });
}

function editBreak(id, name, start, end) {
    $('#mBreakId').val(id);
    $('#mBreakName').val(name);
    $('#mBreakStart').val(start);
    $('#mBreakEnd').val(end);
}

function clearBreakForm() {
    $('#mBreakId').val('');
    $('#mBreakName').val('');
    $('#mBreakStart').val('');
    $('#mBreakEnd').val('');
}

function saveBreak() {
    var sno   = parseInt($('#mBreakShiftNo').val())||0;
    var name  = $('#mBreakName').val().trim() || 'Istirahat';
    var start = $('#mBreakStart').val();
    var end   = $('#mBreakEnd').val();
    if (!start||!end) { alert('Isi jam mulai dan selesai.'); return; }
    apiPost('api/shift_config.php', {
        action:'save_break', id:parseInt($('#mBreakId').val())||0,
        machine_id:MID, shift_no:sno, break_name:name, start_time:start, end_time:end
    }, function(d) {
        if (d.success) {
            clearBreakForm();
            loadBreakList(sno);
            loadShiftConfig();
            loadDaySummary();
            addLog('Istirahat disimpan: '+name+' '+start+'–'+end);
        } else alert(d.error);
    });
}

function toggleBreak(id) {
    apiPost('api/shift_config.php', {action:'toggle_break', id:id, machine_id:MID}, function(d) {
        if (d.success) { loadBreakList(parseInt($('#mBreakShiftNo').val())); loadShiftConfig(); loadDaySummary(); }
    });
}

function deleteBreak(id) {
    if (!confirm('Hapus jam istirahat ini?')) return;
    apiPost('api/shift_config.php', {action:'delete_break', id:id, machine_id:MID}, function(d) {
        if (d.success) { loadBreakList(parseInt($('#mBreakShiftNo').val())); loadShiftConfig(); loadDaySummary(); addLog('Istirahat dihapus'); }
    });
}

// ── 2 & 3. Day summary → Plan + Produksi + OEE ──────────────
function loadDaySummary() {
    if (!MID) return;
    $.get('api/shift_config.php', {action:'day_summary', machine_id:MID, date:DATE}, function(d) {
        if (!d.success) return;
        _shifts = d.data;
        renderPlanTable();
        renderProdTable();
        renderOEE();
        renderNoteShiftSelect();
    });
}

function renderPlanTable() {
    var tb = $('#bodyPlan').empty();
    if (!_shifts.length) { tb.append('<tr><td colspan="5" class="text-center text-muted py-2">—</td></tr>'); return; }
    _shifts.forEach(function(r) {
        var plan   = parseInt(r.plan_qty)||0;
        var actual = parseInt(r.total_out)||0;
        var perf   = plan > 0 ? Math.min(Math.round(actual/plan*100),999) : 0;
        var pc     = perf>=100?'success':perf>=75?'warning':'danger';
        tb.append($('<tr>').addClass('s'+r.shift_no)
            .append($('<td>').html(shiftBadge(r)))
            .append($('<td>').text(r.default_plan||0).addClass('text-muted').css('font-size','.78rem'))
            .append($('<td>').html(
                '<span class="editable plan-edit" contenteditable="true"'+
                ' data-sno="'+r.shift_no+'" style="font-weight:700;">'+plan+'</span>'
            ))
            .append($('<td>').text(actual))
            .append($('<td>').html('<span class="badge badge-'+pc+'">'+perf+'%</span>'))
        );
    });
    // Bind plan edit
    $('#bodyPlan .plan-edit').off('blur keydown').on('keydown', function(e) {
        if (e.key==='Enter') { e.preventDefault(); $(this).blur(); }
    }).on('blur', function() {
        var sno = $(this).data('sno'), val = parseInt($(this).text())||0;
        apiPost('api/shift_config.php', {action:'save_day_plan', machine_id:MID, date:DATE, shift_no:sno, plan_qty:val}, function(d) {
            if (d.success) { flash($('#bodyPlan .plan-edit[data-sno="'+sno+'"]')); addLog('Target S'+sno+' → '+val+' pcs'); loadDaySummary(); }
        });
    });
}

function renderProdTable() {
    var tb = $('#bodyProd').empty();
    if (!_shifts.length) { tb.append('<tr><td colspan="5" class="text-center text-muted py-2">—</td></tr>'); return; }
    _shifts.forEach(function(r) {
        var out  = parseInt(r.total_out)    || 0;
        var rej  = parseInt(r.total_reject) || 0;
        var good = Math.max(0, out - rej);
        var qual = out > 0 ? Math.round(good/out*100) : 0;
        var qc   = qual>=98?'success':qual>=90?'warning':'danger';
        tb.append($('<tr>').addClass('s'+r.shift_no)
            .append($('<td>').html(shiftBadge(r)))
            .append($('<td>').html(
                '<span class="editable prod-out" contenteditable="true"'+
                ' data-sno="'+r.shift_no+'" style="font-weight:700;">'+out+'</span>'
            ))
            .append($('<td>').html(
                '<span class="editable prod-rej" contenteditable="true"'+
                ' data-sno="'+r.shift_no+'" style="color:#e74a3b;font-weight:700;">'+rej+'</span>'
            ))
            .append($('<td class="text-success font-weight-bold">').text(good))
            .append($('<td>').html('<span class="badge badge-'+qc+'">'+qual+'%</span>'))
        );
    });
    // Bind out/reject edit
    function bindProd(sel, field) {
        $(sel).off('blur keydown').on('keydown', function(e) {
            if (e.key==='Enter') { e.preventDefault(); $(this).blur(); }
        }).on('blur', function() {
            var sno = $(this).data('sno');
            var outEl = $('#bodyProd .prod-out[data-sno="'+sno+'"]');
            var rejEl = $('#bodyProd .prod-rej[data-sno="'+sno+'"]');
            var out = parseInt(outEl.text())||0;
            var rej = parseInt(rejEl.text())||0;
            apiPost('api/shift_config.php', {
                action:'save_production', machine_id:MID, date:DATE, shift_no:sno,
                total_out:out, total_reject:rej
            }, function(d) {
                if (d.success) {
                    flash($(this));
                    addLog('Produksi S'+sno+': out='+out+' rej='+rej+' qual='+d.quality+'%');
                    loadDaySummary();
                }
            }.bind(this));
        });
    }
    bindProd('#bodyProd .prod-out', 'out');
    bindProd('#bodyProd .prod-rej', 'rej');
}

// ── OEE Summary Cards ────────────────────────────────────────
function renderOEE() {
    var row = $('#oeeRow').empty();
    if (!_shifts.length) return;

    _shifts.forEach(function(r, i) {
        var avail = parseFloat(r.availability)||0;
        var plan  = parseInt(r.plan_qty)||0;
        var out   = parseInt(r.total_out)||0;
        var rej   = parseInt(r.total_reject)||0;
        var good  = Math.max(0, out - rej);
        var perf  = plan>0 ? Math.min(out/plan*100, 100) : 0;
        var qual  = out>0  ? good/out*100 : 0;
        var oee   = avail>0&&perf>0&&qual>0 ? avail*perf*qual/10000 : 0;

        var card = $('<div class="col-xl-3 col-md-6 mb-3">').html(
            '<div class="card shadow" style="border-left:4px solid '+SCOLORS[r.shift_no]+';">'+
            '<div class="card-body py-3 px-3">'+
            '<div class="d-flex justify-content-between align-items-center mb-2">'+
            '<div><strong style="color:'+SCOLORS[r.shift_no]+';">'+escH(r.shift_name)+'</strong>'+
            '<br><small class="text-muted" style="font-size:.7rem;">'+
            r.start_time.substr(0,5)+' – '+r.end_time.substr(0,5)+'</small></div>'+
            oeeStatusBadge(oee)+
            '</div>'+
            '<div class="row no-gutters text-center">'+
            donutCol('A', avail, '#4e73df', 'oee-a-'+i)+
            donutCol('P', perf,  '#1cc88a', 'oee-p-'+i)+
            donutCol('Q', qual,  '#f6c23e', 'oee-q-'+i)+
            donutCol('OEE', oee, '#e74a3b', 'oee-o-'+i)+
            '</div></div></div>'
        );
        row.append(card);

        // Draw donuts setelah DOM ready
        setTimeout(function() {
            drawDonut('oee-a-'+i, avail, '#4e73df');
            drawDonut('oee-p-'+i, perf,  '#1cc88a');
            drawDonut('oee-q-'+i, qual,  '#f6c23e');
            drawDonut('oee-o-'+i, oee,   '#e74a3b');
        }, 0);
    });
}

function donutCol(lbl, val, clr, id) {
    return '<div class="col-3">'+
           '<div class="oee-wrap">'+
           '<canvas id="'+id+'" class="oee-canvas" width="90" height="90"></canvas>'+
           '<div class="oee-label">'+
           '<div class="oee-val" style="color:'+clr+';">'+Math.round(val)+'</div>'+
           '<div class="oee-sub">%</div>'+
           '</div></div>'+
           '<div style="font-size:.65rem;font-weight:700;color:'+clr+';">'+lbl+'</div>'+
           '</div>';
}

function drawDonut(id, val, clr) {
    var el = document.getElementById(id);
    if (!el || typeof Chart==='undefined') return;
    if (_donutChart[id]) { _donutChart[id].destroy(); }
    _donutChart[id] = new Chart(el.getContext('2d'), {
        type:'doughnut',
        data:{ datasets:[{ data:[val, Math.max(0,100-val)],
            backgroundColor:[clr,'#e9ecef'], borderWidth:0 }] },
        options:{ cutoutPercentage:72, animation:{duration:600},
            tooltips:{enabled:false}, legend:{display:false},
            hover:{mode:null} }
    });
}

function oeeStatusBadge(v) {
    var c = v>=85?'success':v>=60?'warning':v>0?'danger':'secondary';
    var l = v>=85?'Baik':v>=60?'Cukup':v>0?'Rendah':'N/A';
    return '<span class="badge badge-'+c+'" style="font-size:.78rem;">'+l+'</span>';
}

// ── Operator + Catatan ───────────────────────────────────────
function renderNoteShiftSelect() {
    var sel = $('#noteShift').empty().append('<option value="">Pilih shift…</option>');
    _shifts.forEach(function(r) {
        sel.append('<option value="'+r.shift_no+'">S'+r.shift_no+' '+escH(r.shift_name)+'</option>');
    });
}

function saveNotes() {
    var sno = parseInt($('#noteShift').val())||0;
    if (!sno) { alert('Pilih shift terlebih dahulu.'); return; }
    var op  = $('#noteOperator').val().trim();
    var txt = $('#noteText').val().trim();
    // Ambil data produksi saat ini
    var row = _shifts.find(function(r){ return r.shift_no===sno; }) || {};
    apiPost('api/shift_config.php', {
        action:'save_production', machine_id:MID, date:DATE, shift_no:sno,
        total_out:    parseInt(row.total_out)||0,
        total_reject: parseInt(row.total_reject)||0,
        operator_name:op, notes:txt
    }, function(d) {
        if (d.success) {
            $('#noteStatus').text('Tersimpan ✓').addClass('text-success');
            setTimeout(function(){ $('#noteStatus').text(''); }, 2000);
            addLog('Catatan S'+sno+' disimpan'+(op?' oleh '+op:''));
        }
    });
}

// ── Helpers ──────────────────────────────────────────────────
function shiftBadge(r) {
    return '<strong style="color:'+SCOLORS[r.shift_no]+';">S'+r.shift_no+'</strong>'+
           ' <small class="text-muted" style="font-size:.68rem;">'+escH(r.shift_name||'')+'</small>';
}

function flash(el) {
    if (!el || !el.length) return;
    el.addClass('saved-flash');
    setTimeout(function(){ el.removeClass('saved-flash'); }, 700);
}

function addLog(msg) {
    var t = new Date().toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
    _auditLog.unshift('<span class="text-muted">'+t+'</span> '+escH(msg));
    if (_auditLog.length > 20) _auditLog.pop();
    $('#auditLog').html(_auditLog.join('<br>'));
}

function escH(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function apiPost(url, data, cb) {
    $.ajax({ url:url, method:'POST', contentType:'application/json',
             data:JSON.stringify(data), dataType:'json',
             success:cb,
             error:function(){ alert('Gagal terhubung ke server.'); }
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
