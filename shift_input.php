<?php
// ============================================================
//  shift_input.php — Halaman input produksi per shift
//  Operator melihat progress OEE realtime dan input reject akhir shift
// ============================================================
require_once 'includes/auth_check.php';
$currentPage = 'shift_input';
$pageTitle   = 'Input Produksi Shift';

$db = getDB();

// Ambil semua mesin untuk dropdown
$machines = $db->query("SELECT id, name, status FROM machines ORDER BY name")->fetchAll();

include 'includes/header.php';
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="fas fa-clipboard-list text-primary mr-2"></i>Input Produksi Shift
        <small class="text-muted ml-2" style="font-size:.75rem;">— OEE Realtime dari Tower Lamp + Shift Data</small>
    </h1>
    <small id="syncTs" class="text-muted" style="font-size:.72rem;">—</small>
</div>

<!-- Pilih Mesin -->
<div class="card shadow mb-4">
    <div class="card-body py-3">
        <div class="row align-items-center">
            <div class="col-md-4">
                <label class="font-weight-bold mb-1">Pilih Mesin</label>
                <select id="machineSelect" class="form-control">
                    <option value="">— Pilih Mesin —</option>
                    <?php foreach ($machines as $m): ?>
                    <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?>
                        (<?= strtoupper($m['status']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 mt-3 mt-md-0">
                <label class="font-weight-bold mb-1">Target Produksi Shift</label>
                <div class="input-group">
                    <input type="number" id="planQtyInput" class="form-control" placeholder="0" min="0">
                    <div class="input-group-append">
                        <button class="btn btn-outline-primary" onclick="savePlan()">Set</button>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mt-3 mt-md-0" id="shiftInfoBox" style="display:none;">
                <label class="font-weight-bold mb-1">Shift Aktif</label>
                <div>
                    <span class="badge badge-primary" id="shiftName">—</span>
                    <small class="text-muted ml-2" id="shiftTime">—</small>
                </div>
            </div>
            <div class="col-md-2 mt-3 mt-md-0 text-right">
                <button class="btn btn-sm btn-light" onclick="loadShift()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
        </div>
    </div>
</div>

<div id="mainContent" style="display:none;">

<!-- OEE Realtime Cards -->
<div class="row mb-4" id="oeeCards">

    <!-- Availability -->
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Availability <small class="text-muted">(Tower Lamp)</small>
                        </div>
                        <div class="h4 mb-0 font-weight-bold text-gray-800">
                            <span id="rtAvail">—</span>%
                        </div>
                        <div class="progress mt-2" style="height:6px;">
                            <div id="rtAvailBar" class="progress-bar bg-success" style="width:0%"></div>
                        </div>
                        <small class="text-muted">
                            Run: <span id="rtRunMin">—</span>m |
                            Standby: <span id="rtStandbyMin">—</span>m |
                            Stop: <span id="rtStopMin">—</span>m
                        </small>
                    </div>
                    <div class="col-auto"><i class="fas fa-clock fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance -->
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Performance <small class="text-muted">(Run vs Standby)</small>
                        </div>
                        <div class="h4 mb-0 font-weight-bold text-gray-800">
                            <span id="rtPerf">—</span>%
                        </div>
                        <div class="progress mt-2" style="height:6px;">
                            <div id="rtPerfBar" class="progress-bar bg-warning" style="width:0%"></div>
                        </div>
                        <small class="text-muted">Mesin aktif beroperasi</small>
                    </div>
                    <div class="col-auto"><i class="fas fa-tachometer-alt fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quality -->
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Quality <small class="text-muted">(Input Operator)</small>
                        </div>
                        <div class="h4 mb-0 font-weight-bold text-gray-800">
                            <span id="rtQuality">—</span>%
                        </div>
                        <div class="progress mt-2" style="height:6px;">
                            <div id="rtQualityBar" class="progress-bar bg-info" style="width:0%"></div>
                        </div>
                        <small class="text-muted">
                            Out: <span id="rtTotalOut">0</span> |
                            Reject: <span id="rtReject">0</span> |
                            Good: <span id="rtGood">0</span>
                        </small>
                    </div>
                    <div class="col-auto"><i class="fas fa-check-circle fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- OEE Score -->
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            OEE Score Saat Ini
                        </div>
                        <div class="h3 mb-0 font-weight-bold">
                            <span id="rtOee" style="color:#4e73df;">—</span>%
                        </div>
                        <div class="progress mt-2" style="height:6px;">
                            <div id="rtOeeBar" class="progress-bar" style="width:0%"></div>
                        </div>
                        <small class="text-muted">A × P × Q / 10000</small>
                    </div>
                    <div class="col-auto"><i class="fas fa-chart-pie fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Status Tower Lamp Realtime -->
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex align-items-center justify-content-between">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-traffic-light mr-2"></i>Status Tower Lamp — Realtime
        </h6>
        <div id="machineStatusBadge" class="badge badge-secondary px-3 py-2" style="font-size:.85rem;">—</div>
    </div>
    <div class="card-body">
        <div class="row align-items-center">
            <!-- Lampu -->
            <div class="col-md-4 text-center">
                <div class="d-flex justify-content-center align-items-end" style="gap:16px;">
                    <div class="text-center">
                        <div id="lampRed" style="width:48px;height:48px;border-radius:50%;background:#e0e0e0;
                             margin:0 auto 6px;transition:background .4s,box-shadow .4s;"></div>
                        <small class="font-weight-bold" style="color:#dc3545;">STOP/EMG</small>
                    </div>
                    <div class="text-center">
                        <div id="lampYellow" style="width:48px;height:48px;border-radius:50%;background:#e0e0e0;
                             margin:0 auto 6px;transition:background .4s,box-shadow .4s;"></div>
                        <small class="font-weight-bold" style="color:#ffc107;">STANDBY</small>
                    </div>
                    <div class="text-center">
                        <div id="lampGreen" style="width:48px;height:48px;border-radius:50%;background:#e0e0e0;
                             margin:0 auto 6px;transition:background .4s,box-shadow .4s;"></div>
                        <small class="font-weight-bold" style="color:#28a745;">RUN</small>
                    </div>
                </div>
            </div>
            <!-- Durasi bar -->
            <div class="col-md-5">
                <label class="font-weight-bold small">Distribusi Waktu Shift</label>
                <div id="timeDistBar" class="d-flex" style="height:20px;border-radius:4px;overflow:hidden;">
                    <div id="tdRun"     style="background:#28a745;height:100%;transition:width .5s;" title="Run"></div>
                    <div id="tdStandby" style="background:#ffc107;height:100%;transition:width .5s;" title="Standby"></div>
                    <div id="tdStop"    style="background:#dc3545;height:100%;transition:width .5s;" title="Stop"></div>
                    <div style="background:#e0e0e0;flex:1;" title="Sisa"></div>
                </div>
                <div class="d-flex justify-content-between mt-1" style="font-size:.7rem;color:#6c757d;">
                    <span><i class="fas fa-circle text-success"></i> Run</span>
                    <span><i class="fas fa-circle text-warning"></i> Standby</span>
                    <span><i class="fas fa-circle text-danger"></i> Stop</span>
                    <span>Planned: <span id="tdPlanned">—</span>m</span>
                </div>
            </div>
            <!-- Info terakhir -->
            <div class="col-md-3 text-right">
                <small class="text-muted d-block">State terakhir</small>
                <strong id="lastStateTs" class="text-primary">—</strong>
                <small class="text-muted d-block mt-1">Data dari DS3231 RTC</small>
            </div>
        </div>
    </div>
</div>

<!-- Form Input Produksi Akhir Shift -->
<div class="card shadow mb-4" id="closeShiftCard">
    <div class="card-header py-3 d-flex align-items-center justify-content-between">
        <h6 class="m-0 font-weight-bold text-danger">
            <i class="fas fa-flag-checkered mr-2"></i>Tutup Shift & Input Reject
        </h6>
        <span id="shiftStatusBadge" class="badge badge-success px-2 py-1">Shift Aktif</span>
    </div>
    <div class="card-body">
        <div id="shiftClosedMsg" style="display:none;">
            <div class="alert alert-info mb-0">
                <i class="fas fa-info-circle mr-2"></i>Shift sudah ditutup. OEE telah tersimpan.
            </div>
        </div>
        <div id="shiftOpenForm">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="font-weight-bold">Total Produk Keluar</label>
                    <div class="input-group">
                        <input type="number" id="totalOutInput" class="form-control form-control-lg"
                               placeholder="0" min="0" value="0">
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" onclick="manualIncrement()">+1</button>
                        </div>
                    </div>
                    <small class="text-muted">Total barang keluar shift ini</small>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="font-weight-bold text-danger">Total Reject</label>
                    <input type="number" id="rejectInput" class="form-control form-control-lg"
                           placeholder="0" min="0" value="0">
                    <small class="text-muted">Diisi oleh operator akhir shift</small>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="font-weight-bold">Nama Operator</label>
                    <input type="text" id="operatorName" class="form-control" placeholder="Nama operator">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="font-weight-bold">Catatan</label>
                    <input type="text" id="closeNotes" class="form-control" placeholder="Opsional...">
                </div>
            </div>
            <div class="row align-items-center mt-2">
                <div class="col-md-6">
                    <!-- Preview OEE sebelum close -->
                    <div class="alert alert-light border mb-0 py-2" id="previewOee" style="display:none;">
                        <small class="font-weight-bold">Preview OEE jika ditutup sekarang:</small>
                        <div class="mt-1">
                            A: <strong id="prevA">—</strong>% ×
                            P: <strong id="prevP">—</strong>% ×
                            Q: <strong id="prevQ">—</strong>% =
                            OEE: <strong id="prevOee" class="text-primary">—</strong>%
                        </div>
                    </div>
                </div>
                <div class="col-md-6 text-right">
                    <button class="btn btn-outline-secondary mr-2" onclick="calcPreview()">
                        <i class="fas fa-calculator mr-1"></i>Preview OEE
                    </button>
                    <button class="btn btn-danger" onclick="confirmCloseShift()"
                            id="btnCloseShift">
                        <i class="fas fa-flag-checkered mr-1"></i>Tutup Shift & Simpan OEE
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

</div><!-- /mainContent -->

<!-- Sensor & Power Cards (dari ESP32) -->
<div id="sensorSection" style="display:none;">
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-microchip mr-2"></i>Data Sensor Realtime (SHT20 + PZEM)
                </h6>
                <small id="sensorTs" class="text-muted">—</small>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-2 col-6 mb-3">
                        <div class="small text-muted">Suhu SHT20</div>
                        <div class="h5 font-weight-bold text-danger" id="sEnvTemp">—</div>
                        <small class="text-muted">°C</small>
                    </div>
                    <div class="col-md-2 col-6 mb-3">
                        <div class="small text-muted">Kelembaban</div>
                        <div class="h5 font-weight-bold text-info" id="sEnvHum">—</div>
                        <small class="text-muted">%</small>
                    </div>
                    <div class="col-md-2 col-6 mb-3">
                        <div class="small text-muted">Tegangan PZEM</div>
                        <div class="h5 font-weight-bold text-primary" id="sPzemVolt">—</div>
                        <small class="text-muted">V</small>
                    </div>
                    <div class="col-md-2 col-6 mb-3">
                        <div class="small text-muted">Arus</div>
                        <div class="h5 font-weight-bold text-warning" id="sPzemCurr">—</div>
                        <small class="text-muted">A</small>
                    </div>
                    <div class="col-md-2 col-6 mb-3">
                        <div class="small text-muted">Daya</div>
                        <div class="h5 font-weight-bold text-success" id="sPzemPwr">—</div>
                        <small class="text-muted">W</small>
                    </div>
                    <div class="col-md-2 col-6 mb-3">
                        <div class="small text-muted">Power Factor</div>
                        <div class="h5 font-weight-bold" id="sPzemPf">—</div>
                        <small class="text-muted">PF</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<style>
.lamp-on-red    { background:#dc3545 !important; box-shadow:0 0 18px rgba(220,53,69,.8) !important; }
.lamp-on-yellow { background:#ffc107 !important; box-shadow:0 0 18px rgba(255,193,7,.8) !important; }
.lamp-on-green  { background:#28a745 !important; box-shadow:0 0 18px rgba(40,167,69,.8) !important; }
@keyframes blinkLamp { 0%,100%{opacity:1} 50%{opacity:.3} }
.lamp-blink { animation: blinkLamp .6s infinite; }
</style>

<script>
var currentMachineId = null;
var currentShiftData = null;
var liveTimer        = null;
var LIVE_INTERVAL    = 10000; // 10 detik

// ── Pilih mesin ───────────────────────────────────────────────
document.getElementById('machineSelect').addEventListener('change', function(){
    currentMachineId = this.value ? parseInt(this.value) : null;
    if (!currentMachineId) {
        document.getElementById('mainContent').style.display = 'none';
        document.getElementById('sensorSection').style.display = 'none';
        if (liveTimer) clearInterval(liveTimer);
        return;
    }
    loadShift();
    if (liveTimer) clearInterval(liveTimer);
    liveTimer = setInterval(loadShift, LIVE_INTERVAL);
});

// ── Load data shift aktif ─────────────────────────────────────
function loadShift() {
    if (!currentMachineId) return;
    fetch('api/shift_production.php?action=current&machine_id=' + currentMachineId, { cache:'no-store' })
        .then(r => r.json())
        .then(d => {
            var _upd = (d.shift && d.shift.updated_at) ? d.shift.updated_at.substr(11,8) : new Date().toLocaleTimeString();
            document.getElementById('syncTs').textContent = 'Update: ' + _upd;
            if (!d.success || !d.shift) {
                document.getElementById('mainContent').style.display = 'none';
                return;
            }
            document.getElementById('mainContent').style.display = '';
            currentShiftData = d.shift;
            updateShiftInfo(d.shift);
            updateOeeCards(d.oee_rt);
            updateLamps(null); // nanti dari state terakhir
            syncTotalOutInput(d.shift);
            loadLatestState();
            loadSensorData();
        })
        .catch(err => console.error('Shift load error:', err));
}

function updateShiftInfo(shift) {
    var names = ['','Shift Pagi','Shift Siang','Shift Malam'];
    document.getElementById('shiftName').textContent = names[shift.shift_no] || 'Shift '+shift.shift_no;
    document.getElementById('shiftTime').textContent = shift.shift_start + ' – ' + shift.shift_end;
    document.getElementById('shiftInfoBox').style.display = '';
    document.getElementById('planQtyInput').placeholder = shift.sp_plan || shift.plan_qty || '0';

    var closed = shift.is_closed == 1;
    document.getElementById('shiftStatusBadge').textContent = closed ? 'Shift Ditutup' : 'Shift Aktif';
    document.getElementById('shiftStatusBadge').className = 'badge px-2 py-1 ' + (closed ? 'badge-secondary' : 'badge-success');
    document.getElementById('shiftClosedMsg').style.display  = closed ? '' : 'none';
    document.getElementById('shiftOpenForm').style.display   = closed ? 'none' : '';
}

function syncTotalOutInput(shift) {
    var el = document.getElementById('totalOutInput');
    if (el && shift.total_out !== undefined) el.value = shift.total_out;
}

function updateOeeCards(oee) {
    if (!oee) return;
    setCard('rtAvail',       oee.availability, 'rtAvailBar');
    setCard('rtPerf',        oee.performance,  'rtPerfBar');
    setCard('rtQuality',     oee.quality,      'rtQualityBar');
    setCard('rtOee',         oee.oee,          'rtOeeBar');

    setText('rtRunMin',     oee.run_min);
    setText('rtStandbyMin', oee.standby_min);
    setText('rtStopMin',    oee.stop_min);
    setText('rtTotalOut',   oee.total_out);
    setText('rtReject',     oee.total_reject);
    setText('rtGood',       Math.max(0, (oee.total_out||0) - (oee.total_reject||0)));

    // Time distribution bar
    var planned = oee.planned_min || 480;
    setText('tdPlanned', planned);
    setBarPct('tdRun',     oee.run_min     / planned * 100);
    setBarPct('tdStandby', oee.standby_min / planned * 100);
    setBarPct('tdStop',    oee.stop_min    / planned * 100);

    // OEE bar color
    var oeeBar = document.getElementById('rtOeeBar');
    if (oeeBar) oeeBar.className = 'progress-bar ' + (oee.oee >= 85 ? 'bg-success' : oee.oee >= 65 ? 'bg-warning' : 'bg-danger');
}

function setCard(valId, val, barId) {
    var v = parseFloat(val) || 0;
    var el = document.getElementById(valId); if (el) el.textContent = v.toFixed(1);
    var bar = document.getElementById(barId); if (bar) bar.style.width = Math.min(v,100) + '%';
}
function setText(id, val) { var el = document.getElementById(id); if (el) el.textContent = val ?? '—'; }
function setBarPct(id, pct) { var el = document.getElementById(id); if (el) el.style.width = Math.min(pct,100) + '%'; }

// ── Load state tower lamp terakhir ────────────────────────────
function loadLatestState() {
    // Ambil dari machine_states via api sederhana
    fetch('api/machine_state.php?action=latest&machine_id=' + currentMachineId, { cache:'no-store' })
        .then(r => r.json()).then(d => {
            if (d.state) updateLamps(d.state, d.ts);
        }).catch(() => {});
}

function updateLamps(state, ts) {
    var lg = document.getElementById('lampGreen');
    var ly = document.getElementById('lampYellow');
    var lr = document.getElementById('lampRed');
    var badge = document.getElementById('machineStatusBadge');

    // Reset semua
    [lg, ly, lr].forEach(l => l.className = '');
    lg.style.background = ly.style.background = lr.style.background = '#e0e0e0';
    [lg, ly, lr].forEach(l => { l.style.boxShadow = 'none'; });

    if (!state) return;

    var configs = {
        'run':       { el: lg, cls: 'lamp-on-green',  badge: 'badge-success',   lbl: '● RUN' },
        'standby':   { el: ly, cls: 'lamp-on-yellow', badge: 'badge-warning',   lbl: '◑ STANDBY' },
        'stop':      { el: lr, cls: 'lamp-on-red',    badge: 'badge-secondary', lbl: '■ STOP' },
        'emergency': { el: lr, cls: 'lamp-on-red lamp-blink', badge: 'badge-danger', lbl: '⚠ EMERGENCY' },
    };
    var cfg = configs[state];
    if (cfg) {
        cfg.el.className = cfg.cls;
        badge.className  = 'badge px-3 py-2 ' + cfg.badge;
        badge.textContent = cfg.lbl;
        badge.style.fontSize = '.85rem';
    }
    if (ts) document.getElementById('lastStateTs').textContent = ts;
}

// ── Load data sensor (SHT20 + PZEM) dari sensor_readings ──────
function loadSensorData() {
    fetch('api/machine_live.php?machine_id=' + currentMachineId, { cache:'no-store' })
        .then(r => r.json())
        .then(d => {
            if (!d.sensor) return;
            var s = d.sensor;
            document.getElementById('sensorSection').style.display = '';
            document.getElementById('sensorTs').textContent = s.recorded_at ? s.recorded_at.substr(11,8) : '—';
            setT('sEnvTemp',  s.sht_temp,   val => val !== null ? parseFloat(val).toFixed(1) : '—');
            setT('sEnvHum',   s.sht_hum,    val => val !== null ? parseFloat(val).toFixed(1) : '—');
            setT('sPzemVolt', s.pzem_volt,  val => val !== null ? parseFloat(val).toFixed(1) : '—');
            setT('sPzemCurr', s.pzem_curr,  val => val !== null ? parseFloat(val).toFixed(2) : '—');
            setT('sPzemPwr',  s.pzem_pwr,   val => val !== null ? parseFloat(val).toFixed(0) : '—');
            setT('sPzemPf',   s.pzem_pf,    val => val !== null ? parseFloat(val).toFixed(3) : '—');
        })
        .catch(() => {});
}
function setT(id, val, fmt) {
    var el = document.getElementById(id);
    if (el) el.textContent = val !== null && val !== undefined ? (fmt ? fmt(val) : val) : '—';
}

// ── Set plan qty ──────────────────────────────────────────────
function savePlan() {
    if (!currentMachineId || !currentShiftData) return;
    var qty = parseInt(document.getElementById('planQtyInput').value) || 0;
    fetch('api/shift_production.php', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({
            action: 'set_plan',
            machine_id: currentMachineId,
            shift_no: currentShiftData.shift_no,
            plan_qty: qty
        })
    }).then(r => r.json()).then(d => {
        if (d.success) { showToast('Target tersimpan: ' + qty + ' pcs', 'success'); loadShift(); }
    });
}

// ── Manual increment ──────────────────────────────────────────
function manualIncrement() {
    if (!currentMachineId) return;
    var cur = parseInt(document.getElementById('totalOutInput').value) || 0;
    document.getElementById('totalOutInput').value = cur + 1;
}

// ── Preview OEE ───────────────────────────────────────────────
function calcPreview() {
    if (!currentShiftData) return;
    var totalOut  = parseInt(document.getElementById('totalOutInput').value) || 0;
    var reject    = parseInt(document.getElementById('rejectInput').value)   || 0;
    var good      = Math.max(0, totalOut - reject);
    var quality   = totalOut > 0 ? (good / totalOut * 100).toFixed(1) : '0';
    var avail = parseFloat(document.getElementById('rtAvail').textContent) || 0;
    var perf  = parseFloat(document.getElementById('rtPerf').textContent)  || 0;
    var oee   = (avail * perf * parseFloat(quality) / 10000).toFixed(1);
    document.getElementById('prevA').textContent   = avail;
    document.getElementById('prevP').textContent   = perf;
    document.getElementById('prevQ').textContent   = quality;
    document.getElementById('prevOee').textContent = oee;
    document.getElementById('previewOee').style.display = '';
}

// ── Tutup shift ───────────────────────────────────────────────
function confirmCloseShift() {
    if (!currentMachineId) return;
    var totalOut  = parseInt(document.getElementById('totalOutInput').value) || 0;
    var reject    = parseInt(document.getElementById('rejectInput').value)   || 0;
    var operator  = document.getElementById('operatorName').value.trim();
    var notes     = document.getElementById('closeNotes').value.trim();

    if (!operator) { alert('Nama operator harus diisi.'); return; }
    if (reject > totalOut) { alert('Reject tidak boleh lebih besar dari total produksi.'); return; }
    if (!confirm('Yakin menutup shift?\nTotal: ' + totalOut + ' | Reject: ' + reject + '\nShift tidak bisa dibuka kembali.')) return;

    // Step 1: simpan total_out, lalu Step 2: close shift (dirantai agar urutan terjamin)
    var btnClose = document.getElementById('btnCloseShift');
    if (btnClose) { btnClose.disabled = true; btnClose.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Menyimpan...'; }

    fetch('api/shift_production.php', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ action: 'set_total_out', machine_id: currentMachineId, total_out: totalOut })
    })
    .then(function() {
        return fetch('api/shift_production.php', {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({
                action: 'close',
                machine_id: currentMachineId,
                total_reject: reject,
                operator_name: operator,
                notes: notes,
            })
        });
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (btnClose) { btnClose.disabled = false; btnClose.innerHTML = '<i class="fas fa-flag-checkered mr-1"></i>Tutup Shift & Simpan OEE'; }
        if (d.success) {
            showToast('Shift ditutup! OEE: ' + d.oee + '%  (A:' + d.availability + ' P:' + d.performance + ' Q:' + d.quality + ')', 'success');
            loadShift();
        } else {
            alert('Gagal: ' + (d.error || 'Unknown error'));
        }
    })
    .catch(function(err) {
        if (btnClose) { btnClose.disabled = false; btnClose.innerHTML = '<i class="fas fa-flag-checkered mr-1"></i>Tutup Shift & Simpan OEE'; }
        alert('Error jaringan: ' + err);
    });
}

function showToast(msg, type) {
    var div = document.createElement('div');
    div.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:9999;min-width:280px;';
    div.innerHTML = '<div class="alert alert-' + (type==='success'?'success':'danger') + ' shadow-lg">'
                  + '<i class="fas fa-check-circle mr-2"></i>' + msg + '</div>';
    document.body.appendChild(div);
    setTimeout(() => div.remove(), 5000);
}
</script>

<?php include 'includes/footer.php'; ?>
