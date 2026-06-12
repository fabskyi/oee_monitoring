<?php
require_once 'includes/auth_check.php';
$currentPage = 'esp32_config';
$pageTitle   = 'ESP32 Configuration';
require_once 'includes/header.php';
?>

<style>
/* ── Layout ─────────────────────────────────────────────────────────────── */
.ecfg-wrap          { display:flex; gap:18px; align-items:flex-start; }
.ecfg-sidebar       { width:270px; flex-shrink:0; }
.ecfg-main          { flex:1; min-width:0; }

/* ── Device card ─────────────────────────────────────────────────────────── */
.dev-card           { border-radius:12px; border:2px solid transparent; cursor:pointer;
                      transition:border-color .18s, box-shadow .18s; margin-bottom:10px;
                      background:#fff; box-shadow:0 1px 4px rgba(0,0,0,.07); }
.dev-card:hover     { border-color:#8B1A1A; box-shadow:0 3px 12px rgba(139,26,26,.12); }
.dev-card.active    { border-color:#8B1A1A; box-shadow:0 4px 16px rgba(139,26,26,.18); }
.dev-card .card-body{ padding:12px 14px; }
.dev-id             { font-weight:700; font-size:.82rem; color:#3a3b45; }
.dev-machine        { font-size:.7rem; color:#858796; margin-top:1px; }
.dev-status         { font-size:.65rem; font-weight:700; padding:2px 8px; border-radius:20px;
                      display:inline-block; }
.dev-status.online  { background:#d1fae5; color:#065f46; }
.dev-status.offline { background:#fee2e2; color:#991b1b; }
.dev-ip             { font-size:.66rem; color:#aab; font-family:monospace; }

/* ── Tabs ────────────────────────────────────────────────────────────────── */
.ecfg-tabs          { border-bottom:2px solid #e3e6f0; margin-bottom:18px; display:flex; gap:4px; flex-wrap:wrap; }
.ecfg-tab           { border:none; background:none; padding:8px 16px; font-size:.78rem; font-weight:600;
                      color:#858796; border-radius:8px 8px 0 0; cursor:pointer; transition:all .15s; }
.ecfg-tab:hover     { background:#f1f3f9; color:#3a3b45; }
.ecfg-tab.active    { background:#8B1A1A; color:#fff; }

.tab-pane           { display:none; }
.tab-pane.active    { display:block; }

/* ── Form ────────────────────────────────────────────────────────────────── */
.param-group-label  { font-size:.65rem; font-weight:800; text-transform:uppercase;
                      letter-spacing:.5px; color:#8B1A1A; margin:20px 0 8px;
                      padding-bottom:4px; border-bottom:1px solid #f0e0e0; }
.param-row          { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px; }
.param-row.full     { grid-template-columns:1fr; }
.param-label        { font-size:.75rem; font-weight:600; color:#4a4a5a; margin-bottom:3px; }
.param-input        { width:100%; border:1px solid #d1d3e2; border-radius:8px; padding:7px 10px;
                      font-size:.8rem; transition:border-color .15s; }
.param-input:focus  { outline:none; border-color:#8B1A1A; box-shadow:0 0 0 3px rgba(139,26,26,.1); }
.param-bool         { display:flex; align-items:center; gap:8px; margin-top:6px; }
.param-bool input   { width:18px; height:18px; cursor:pointer; accent-color:#8B1A1A; }
.param-bool label   { font-size:.8rem; color:#3a3b45; cursor:pointer; }

/* ── Firmware table ──────────────────────────────────────────────────────── */
.fw-table           { width:100%; font-size:.78rem; }
.fw-table th        { background:#f8f9fc; padding:8px 10px; font-weight:700;
                      color:#5a5c69; border-bottom:2px solid #e3e6f0; }
.fw-table td        { padding:8px 10px; border-bottom:1px solid #f1f3f5; vertical-align:middle; }
.fw-table tr:last-child td { border-bottom:none; }
.badge-active       { background:#d1fae5; color:#065f46; font-size:.62rem; font-weight:700;
                      padding:2px 8px; border-radius:20px; }
.badge-inactive     { background:#f1f3f9; color:#858796; font-size:.62rem; padding:2px 8px;
                      border-radius:20px; }

/* ── Custom param table ──────────────────────────────────────────────────── */
.custom-table       { width:100%; font-size:.78rem; margin-top:12px; }
.custom-table th    { background:#f8f9fc; padding:7px 10px; font-weight:700;
                      color:#5a5c69; border-bottom:2px solid #e3e6f0; }
.custom-table td    { padding:6px 10px; border-bottom:1px solid #f1f3f5; vertical-align:middle; }
.custom-table input { width:100%; border:1px solid #d1d3e2; border-radius:6px;
                      padding:4px 8px; font-size:.75rem; }

/* ── Buttons ─────────────────────────────────────────────────────────────── */
.btn-maroon         { background:#8B1A1A; color:#fff; border:none; padding:8px 20px;
                      border-radius:8px; font-size:.8rem; font-weight:700; cursor:pointer;
                      transition:background .15s; }
.btn-maroon:hover   { background:#6b1414; }
.btn-maroon:disabled{ opacity:.55; cursor:default; }
.btn-outline-maroon { background:#fff; color:#8B1A1A; border:1.5px solid #8B1A1A;
                      padding:7px 18px; border-radius:8px; font-size:.8rem; font-weight:700;
                      cursor:pointer; transition:all .15s; }
.btn-outline-maroon:hover { background:#8B1A1A; color:#fff; }

/* ── Empty state ─────────────────────────────────────────────────────────── */
.ecfg-empty         { text-align:center; padding:60px 20px; color:#b0b3c6; }
.ecfg-empty i       { font-size:3rem; margin-bottom:12px; display:block; }
.ecfg-empty p       { font-size:.82rem; }

/* ── Toast ───────────────────────────────────────────────────────────────── */
#ecfgToast          { position:fixed; bottom:24px; right:24px; z-index:9999;
                      background:#3a3b45; color:#fff; padding:12px 20px;
                      border-radius:10px; font-size:.8rem; font-weight:600;
                      box-shadow:0 4px 20px rgba(0,0,0,.25); pointer-events:none;
                      opacity:0; transform:translateY(10px); transition:all .25s; }
#ecfgToast.show     { opacity:1; transform:translateY(0); }
#ecfgToast.success  { background:#065f46; }
#ecfgToast.error    { background:#991b1b; }

/* ── Header row ──────────────────────────────────────────────────────────── */
.ecfg-header        { display:flex; align-items:center; gap:12px; margin-bottom:18px; }
.ecfg-dev-title     { font-size:1.05rem; font-weight:800; color:#3a3b45; }
.ecfg-dev-sub       { font-size:.72rem; color:#858796; }
</style>

<!-- Page Heading -->
<div class="d-sm-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0 text-gray-800 font-weight-bold">
        <i class="fas fa-microchip mr-2" style="color:#8B1A1A"></i>ESP32 Configuration
    </h1>
    <div class="d-flex" style="gap:8px;">
        <button class="btn-outline-maroon" onclick="applyToAll()" title="Push WiFi & MQTT ke semua device">
            <i class="fas fa-broadcast-tower mr-1"></i>Apply ke Semua
        </button>
        <button class="btn-maroon" onclick="openAddDevice()">
            <i class="fas fa-plus mr-1"></i>Tambah Device
        </button>
    </div>
</div>

<div class="ecfg-wrap">
    <!-- ── Sidebar: device list ────────────────────────────────────────────── -->
    <div class="ecfg-sidebar">
        <div class="card shadow-sm" style="border-radius:14px;border:none;">
            <div class="card-body p-2">
                <div style="padding:8px 10px 6px;font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.4px;color:#858796;">
                    <i class="fas fa-broadcast-tower mr-1"></i>Registered Devices
                </div>
                <div id="deviceList">
                    <div class="text-center py-4 text-muted" style="font-size:.78rem;">
                        <i class="fas fa-spinner fa-spin"></i> Memuat...
                    </div>
                </div>
                <div class="p-2 pt-1">
                    <button class="btn-outline-maroon w-100" onclick="loadDevices()" style="width:100%;font-size:.72rem;">
                        <i class="fas fa-sync-alt mr-1"></i>Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Main panel ──────────────────────────────────────────────────────── -->
    <div class="ecfg-main">
        <div class="card shadow-sm" style="border-radius:14px;border:none;min-height:520px;">
            <div class="card-body p-4" id="mainPanel">
                <div class="ecfg-empty">
                    <i class="fas fa-microchip"></i>
                    <p>Pilih device di sebelah kiri<br>untuk melihat & mengubah konfigurasinya.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Toast ─────────────────────────────────────────────────────────────────── -->
<div id="ecfgToast"></div>

<!-- ── Add Device Modal ───────────────────────────────────────────────────────── -->
<div class="modal fade" id="addDeviceModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
        <div class="modal-content" style="border-radius:16px;border:none;">
            <div class="modal-header" style="background:linear-gradient(135deg,#8B1A1A,#dc2626);border-radius:16px 16px 0 0;padding:16px 20px;">
                <h6 class="modal-title text-white font-weight-bold mb-0"><i class="fas fa-plus-circle mr-2"></i>Tambah ESP32 Device</h6>
                <button type="button" class="close text-white" data-dismiss="modal" style="opacity:.8;">&times;</button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="param-label">Device ID <span class="text-danger">*</span></label>
                    <input type="text" id="newDeviceId" class="param-input" placeholder="cth: ESP32-MACHINE-01" autocomplete="off">
                    <small class="text-muted" style="font-size:.68rem;">Harus unik, sesuai dengan firmware (tanpa spasi)</small>
                </div>
                <div class="mb-3">
                    <label class="param-label">Mesin (opsional)</label>
                    <select id="newMachineId" class="param-input">
                        <option value="">— Belum ditentukan —</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer" style="padding:12px 20px;gap:8px;">
                <button class="btn-outline-maroon" data-dismiss="modal">Batal</button>
                <button class="btn-maroon" onclick="submitAddDevice()">
                    <i class="fas fa-save mr-1"></i>Simpan
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── Upload Firmware Modal ─────────────────────────────────────────────────── -->
<div class="modal fade" id="fwUploadModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:440px;">
        <div class="modal-content" style="border-radius:16px;border:none;">
            <div class="modal-header" style="background:linear-gradient(135deg,#1a5f8B,#2563eb);border-radius:16px 16px 0 0;padding:16px 20px;">
                <h6 class="modal-title text-white font-weight-bold mb-0"><i class="fas fa-upload mr-2"></i>Upload Firmware (.bin)</h6>
                <button type="button" class="close text-white" data-dismiss="modal" style="opacity:.8;">&times;</button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="param-label">Versi Firmware <span class="text-danger">*</span></label>
                    <input type="text" id="fwVersion" class="param-input" placeholder="cth: v1.2.3">
                </div>
                <div class="mb-3">
                    <label class="param-label">File .bin <span class="text-danger">*</span></label>
                    <input type="file" id="fwFile" class="param-input" accept=".bin" style="padding:5px;">
                </div>
                <div class="mb-2">
                    <label class="param-label">Catatan</label>
                    <textarea id="fwNotes" class="param-input" rows="2" placeholder="Opsional: deskripsi perubahan"></textarea>
                </div>
                <small class="text-muted" style="font-size:.68rem;"><i class="fas fa-info-circle mr-1"></i>Maks 4 MB. Setelah upload, aktifkan firmware agar device update via OTA.</small>
            </div>
            <div class="modal-footer" style="padding:12px 20px;gap:8px;">
                <button class="btn-outline-maroon" data-dismiss="modal">Batal</button>
                <button class="btn-maroon" onclick="submitUploadFw()">
                    <i class="fas fa-upload mr-1"></i>Upload
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── Add Custom Param Modal ─────────────────────────────────────────────────── -->
<div class="modal fade" id="addParamModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:400px;">
        <div class="modal-content" style="border-radius:16px;border:none;">
            <div class="modal-header" style="background:linear-gradient(135deg,#065f46,#059669);border-radius:16px 16px 0 0;padding:14px 20px;">
                <h6 class="modal-title text-white font-weight-bold mb-0"><i class="fas fa-plus mr-2"></i>Custom Parameter</h6>
                <button type="button" class="close text-white" data-dismiss="modal" style="opacity:.8;">&times;</button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-2">
                    <label class="param-label">Key (nama variabel) <span class="text-danger">*</span></label>
                    <input type="text" id="cpKey" class="param-input" placeholder="cth: my_custom_param">
                </div>
                <div class="mb-2">
                    <label class="param-label">Label / Nama tampilan</label>
                    <input type="text" id="cpLabel" class="param-input" placeholder="cth: Parameter Custom">
                </div>
                <div class="mb-2">
                    <label class="param-label">Tipe</label>
                    <select id="cpType" class="param-input">
                        <option value="string">String</option>
                        <option value="int">Integer</option>
                        <option value="float">Float</option>
                        <option value="bool">Boolean</option>
                        <option value="password">Password</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="param-label">Nilai awal</label>
                    <input type="text" id="cpValue" class="param-input" placeholder="">
                </div>
            </div>
            <div class="modal-footer" style="padding:12px 20px;gap:8px;">
                <button class="btn-outline-maroon" data-dismiss="modal">Batal</button>
                <button class="btn-maroon" onclick="submitAddParam()">Tambah</button>
            </div>
        </div>
    </div>
</div>

<script>
var currentDevice = null;
var allParams     = [];
var machineList   = [];

// ── Toast helper ──────────────────────────────────────────────────────────────
function toast(msg, type) {
    type = type || 'success';
    var t = document.getElementById('ecfgToast');
    t.className = 'show ' + type;
    t.textContent = msg;
    setTimeout(function(){ t.className = ''; }, 3000);
}

// ── API wrapper ───────────────────────────────────────────────────────────────
function api(action, opts) {
    opts = opts || {};
    var url = 'api/esp32_config.php?action=' + action;
    if (opts.qs) url += '&' + opts.qs;
    var fetchOpts = { method: opts.method || 'GET' };
    if (opts.body) {
        fetchOpts.method = 'POST';
        fetchOpts.headers = {'Content-Type':'application/json'};
        fetchOpts.body = JSON.stringify(opts.body);
    }
    return fetch(url, fetchOpts).then(function(r){ return r.json(); });
}

// ── Load device list ──────────────────────────────────────────────────────────
function loadDevices() {
    api('list_devices').then(function(res) {
        var el = document.getElementById('deviceList');
        if (!res.success || !res.devices.length) {
            el.innerHTML = '<div class="text-center py-3 text-muted" style="font-size:.75rem;">Belum ada device terdaftar</div>';
            return;
        }
        var html = '';
        res.devices.forEach(function(d) {
            var isOnline = d.status === 'online';
            var active   = currentDevice && currentDevice.device_id === d.device_id ? 'active' : '';
            html += '<div class="dev-card ' + active + '" onclick="selectDevice(' + JSON.stringify(d).replace(/"/g,'&quot;') + ')">'
                  + '<div class="card-body">'
                  + '<div class="d-flex align-items-center justify-content-between mb-1">'
                  + '<div class="dev-id"><i class="fas fa-microchip mr-1" style="color:#8B1A1A;font-size:.7rem;"></i>' + esc(d.device_id) + '</div>'
                  + '<span class="dev-status ' + (isOnline?'online':'offline') + '">' + (isOnline?'Online':'Offline') + '</span>'
                  + '</div>'
                  + '<div class="dev-machine"><i class="fas fa-cog mr-1"></i>' + (d.machine_name ? esc(d.machine_name) : '— mesin tidak ditentukan —') + '</div>'
                  + (d.ip_address ? '<div class="dev-ip mt-1">' + esc(d.ip_address) + '</div>' : '')
                  + '</div></div>';
        });
        el.innerHTML = html;
    });
}

// ── Load machine list (for dropdown) ─────────────────────────────────────────
function loadMachines(cb) {
    if (machineList.length) { if (cb) cb(machineList); return; }
    api('list_machines').then(function(res) {
        if (res.success) machineList = res.machines;
        if (cb) cb(machineList);
    });
}

function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Select device → load config ───────────────────────────────────────────────
function selectDevice(d) {
    currentDevice = d;
    loadDevices(); // refresh active state
    document.getElementById('mainPanel').innerHTML = '<div class="text-center py-5 text-muted"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';
    Promise.all([
        api('get_config', {qs:'device_id='+encodeURIComponent(d.device_id)}),
        api('list_firmware', {qs:'device_id='+encodeURIComponent(d.device_id)})
    ]).then(function(results) {
        var cfgRes = results[0];
        var fwRes  = results[1];
        allParams = cfgRes.success ? cfgRes.params : [];
        renderMain(d, fwRes.firmware || []);
    });
}

// ── Render main panel ─────────────────────────────────────────────────────────
function renderMain(d, fwList) {
    var groups = { basic:'Dasar', network:'Jaringan', mqtt:'MQTT', sensor:'Sensor', ota:'OTA', custom:'Custom' };
    var tabs   = Object.keys(groups);

    var html = '<div class="ecfg-header">'
             + '<div><div class="ecfg-dev-title"><i class="fas fa-microchip mr-2" style="color:#8B1A1A"></i>' + esc(d.device_id) + '</div>'
             + '<div class="ecfg-dev-sub">'
             + (d.machine_name ? '<i class="fas fa-cog mr-1"></i>' + esc(d.machine_name) : 'Mesin belum ditentukan')
             + (d.ip_address ? ' &nbsp;·&nbsp; <i class="fas fa-network-wired mr-1"></i>' + esc(d.ip_address) : '')
             + (d.firmware_version ? ' &nbsp;·&nbsp; fw ' + esc(d.firmware_version) : '')
             + '</div></div>'
             + '<div class="ml-auto d-flex gap-2" style="gap:8px;">'
             + '<button class="btn-outline-maroon" onclick="openFwUpload()" title="Upload Firmware"><i class="fas fa-upload mr-1"></i>Firmware</button>'
             + '<div class="dropdown">'
             + '<button class="btn-outline-maroon dropdown-toggle" data-toggle="dropdown"><i class="fas fa-download mr-1"></i>Export</button>'
             + '<div class="dropdown-menu dropdown-menu-right">'
             + '<a class="dropdown-item" href="api/esp32_config.php?action=generate_config&device_id=' + encodeURIComponent(d.device_id) + '&format=json" target="_blank"><i class="fas fa-file-code mr-2"></i>Download JSON</a>'
             + '<a class="dropdown-item" href="api/esp32_config.php?action=generate_config&device_id=' + encodeURIComponent(d.device_id) + '&format=header" target="_blank"><i class="fas fa-file-alt mr-2"></i>Download .h Header</a>'
             + '</div></div>'
             + '<button class="btn btn-sm btn-outline-danger" onclick="deleteDevice()" title="Hapus Device" style="border-radius:8px;font-size:.75rem;"><i class="fas fa-trash-alt"></i></button>'
             + '</div></div>';

    // Tabs
    html += '<div class="ecfg-tabs">';
    tabs.forEach(function(g,i) {
        html += '<button class="ecfg-tab' + (i===0?' active':'') + '" onclick="switchTab(this,\'tab-'+g+'\')">' + groups[g] + '</button>';
    });
    html += '</div>';

    // Each tab pane
    tabs.forEach(function(g,i) {
        html += '<div class="tab-pane' + (i===0?' active':'') + '" id="tab-'+g+'">';
        if (g === 'custom') {
            html += renderCustomTab();
        } else if (g === 'ota') {
            html += renderOtaTab(fwList);
        } else {
            html += renderParamTab(g);
        }
        html += '</div>';
    });

    document.getElementById('mainPanel').innerHTML = html;
}

function renderParamTab(group) {
    var params = allParams.filter(function(p){ return p.param_group === group; });
    if (!params.length) return '<div class="text-muted py-3" style="font-size:.78rem;">Tidak ada parameter.</div>';

    var html = '<div class="param-row">';
    var count = 0;
    params.forEach(function(p) {
        var isPass = p.param_type === 'password';
        var isBool = p.param_type === 'bool';
        var isFull = (p.param_type === 'string' && p.param_key.includes('url')) || p.param_key.includes('description') || p.param_key.includes('topic');

        if (count > 0 && isFull) html += '</div><div class="param-row full">';
        else if (count > 0 && !isFull && count % 2 === 0) { html += '</div><div class="param-row">'; }

        html += '<div>';
        html += '<div class="param-label">' + esc(p.label || p.param_key) + '</div>';
        if (isBool) {
            html += '<div class="param-bool">'
                  + '<input type="checkbox" id="param_' + esc(p.param_key) + '" data-key="' + esc(p.param_key) + '" ' + (p.param_value=='1'?'checked':'') + '>'
                  + '<label for="param_' + esc(p.param_key) + '">' + (p.param_value=='1'?'Aktif':'Non-aktif') + '</label>'
                  + '</div>';
        } else {
            html += '<input type="' + (isPass?'password':'text') + '" class="param-input" '
                  + 'id="param_' + esc(p.param_key) + '" data-key="' + esc(p.param_key) + '" '
                  + 'value="' + esc(p.param_value) + '" placeholder="' + esc(p.label) + '">';
        }
        html += '</div>';
        count++;
    });
    html += '</div>';

    // MQTT tab: show auto-generated topic preview
    if (group === 'mqtt' && currentDevice) {
        var prefixParam = allParams.find(function(p){ return p.param_key==='mqtt_topic_prefix'; });
        var prefix = (prefixParam ? prefixParam.param_value : 'yadin/sensor').replace(/\/+$/,'');
        var fullTopic = prefix + '/' + currentDevice.device_id + '/data';
        html += '<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:.76rem;">'
              + '<span style="color:#166534;font-weight:700;"><i class="fas fa-route mr-1"></i>Topic MQTT (otomatis):</span>'
              + ' <code style="background:#dcfce7;padding:2px 8px;border-radius:4px;color:#15803d;font-size:.78rem;" id="topicPreview">'
              + esc(fullTopic) + '</code>'
              + '<span style="color:#6b7280;font-size:.68rem;margin-left:8px;">= prefix / device_id / data</span>'
              + '</div>';
    }

    html += '<div class="mt-3 d-flex" style="gap:10px;">'
          + '<button class="btn-maroon" onclick="saveTabMqtt(\'' + group + '\')">'
          + '<i class="fas fa-save mr-1"></i>Simpan ' + group.charAt(0).toUpperCase()+group.slice(1)
          + '</button></div>';
    return html;
}

function renderCustomTab() {
    var customs = allParams.filter(function(p){ return p.param_group==='custom'; });
    var html = '<p style="font-size:.76rem;color:#858796;margin-bottom:12px;">Parameter tambahan yang akan dimasukkan ke dalam config ESP32. Bebas menambah sesuai kebutuhan.</p>';
    if (customs.length) {
        html += '<table class="custom-table"><thead><tr><th>Key</th><th>Label</th><th>Tipe</th><th>Nilai</th><th></th></tr></thead><tbody>';
        customs.forEach(function(p) {
            html += '<tr>'
                  + '<td><code style="font-size:.72rem;">' + esc(p.param_key) + '</code></td>'
                  + '<td><input class="param-input" data-key="'+esc(p.param_key)+'" data-field="label" value="'+esc(p.label||'')+'"></td>'
                  + '<td><select class="param-input" data-key="'+esc(p.param_key)+'" data-field="type" style="padding:4px 6px;">'
                  + ['string','int','float','bool','password'].map(function(t){return '<option value="'+t+'"'+(p.param_type===t?' selected':'')+'>'+t+'</option>';}).join('')
                  + '</select></td>'
                  + '<td><input class="param-input" data-key="'+esc(p.param_key)+'" data-field="value" value="'+esc(p.param_value||'')+'"></td>'
                  + '<td><button class="btn btn-sm btn-outline-danger" style="border-radius:6px;padding:3px 8px;" onclick="deleteParam(\''+esc(p.param_key)+'\')"><i class="fas fa-times"></i></button></td>'
                  + '</tr>';
        });
        html += '</tbody></table>';
    } else {
        html += '<div class="text-muted py-2" style="font-size:.78rem;">Belum ada custom parameter.</div>';
    }
    html += '<div class="mt-3 d-flex" style="gap:8px;">'
          + '<button class="btn-maroon" onclick="saveCustomParams()"><i class="fas fa-save mr-1"></i>Simpan Custom</button>'
          + '<button class="btn-outline-maroon" onclick="$(\'#addParamModal\').modal(\'show\')"><i class="fas fa-plus mr-1"></i>Tambah Param</button>'
          + '</div>';
    return html;
}

function renderOtaTab(fwList) {
    var otaParams = allParams.filter(function(p){ return p.param_group==='ota'; });
    var html = '<div class="param-group-label">Pengaturan OTA</div>'
             + '<div class="param-row">';
    otaParams.forEach(function(p) {
        if (p.param_type === 'bool') {
            html += '<div><div class="param-label">'+esc(p.label)+'</div>'
                  + '<div class="param-bool"><input type="checkbox" id="param_'+esc(p.param_key)+'" data-key="'+esc(p.param_key)+'" '+(p.param_value=='1'?'checked':'')+'>'
                  + '<label for="param_'+esc(p.param_key)+'">'+(p.param_value=='1'?'Aktif':'Non-aktif')+'</label></div></div>';
        } else {
            html += '<div><div class="param-label">'+esc(p.label)+'</div>'
                  + '<input type="text" class="param-input" id="param_'+esc(p.param_key)+'" data-key="'+esc(p.param_key)+'" value="'+esc(p.param_value)+'" placeholder="'+esc(p.label)+'"></div>';
        }
    });
    html += '</div>';
    html += '<button class="btn-maroon mb-4" onclick="saveTab(\'ota\')"><i class="fas fa-save mr-1"></i>Simpan OTA</button>';

    html += '<div class="param-group-label">Daftar Firmware</div>';
    html += '<div class="mb-2"><button class="btn-outline-maroon" onclick="openFwUpload()" style="font-size:.74rem;"><i class="fas fa-upload mr-1"></i>Upload Firmware Baru</button></div>';

    if (fwList.length) {
        html += '<table class="fw-table"><thead><tr><th>Versi</th><th>File</th><th>Ukuran</th><th>Diupload</th><th>Status</th><th>Aksi</th></tr></thead><tbody>';
        fwList.forEach(function(f) {
            var size = f.file_size ? (f.file_size/1024).toFixed(1)+' KB' : '-';
            html += '<tr>'
                  + '<td><strong>'+esc(f.version)+'</strong></td>'
                  + '<td><code style="font-size:.7rem;">'+esc(f.filename)+'</code></td>'
                  + '<td>'+size+'</td>'
                  + '<td style="font-size:.72rem;">'+(f.uploader?esc(f.uploader):'?')+'<br><span style="color:#aab;">'+esc((f.uploaded_at||'').substring(0,16))+'</span></td>'
                  + '<td>' + (f.is_active ? '<span class="badge-active">Aktif</span>' : '<span class="badge-inactive">Draft</span>') + '</td>'
                  + '<td style="white-space:nowrap;">'
                  + (!f.is_active ? '<button class="btn btn-sm btn-success mr-1" style="border-radius:6px;font-size:.68rem;" onclick="activateFw('+f.id+')"><i class="fas fa-check mr-1"></i>Aktifkan</button>' : '')
                  + '<button class="btn btn-sm btn-outline-danger" style="border-radius:6px;font-size:.68rem;" onclick="deleteFw('+f.id+')"><i class="fas fa-trash-alt"></i></button>'
                  + '</td></tr>';
        });
        html += '</tbody></table>';
    } else {
        html += '<div class="text-muted" style="font-size:.78rem;">Belum ada firmware yang diupload.</div>';
    }
    return html;
}

// ── Tab switch ────────────────────────────────────────────────────────────────
function switchTab(btn, paneId) {
    document.querySelectorAll('.ecfg-tab').forEach(function(b){ b.classList.remove('active'); });
    document.querySelectorAll('.tab-pane').forEach(function(p){ p.classList.remove('active'); });
    btn.classList.add('active');
    var el = document.getElementById(paneId);
    if (el) el.classList.add('active');
}

// ── Collect & save tab params ─────────────────────────────────────────────────
function saveTab(group) {
    if (!currentDevice) return;
    var items = [];
    allParams.filter(function(p){ return p.param_group===group; }).forEach(function(p) {
        var inp = document.getElementById('param_'+p.param_key);
        if (!inp) return;
        var val = inp.type==='checkbox' ? (inp.checked?'1':'0') : inp.value;
        items.push({ param_key:p.param_key, param_value:val, param_type:p.param_type, param_group:group, label:p.label });
    });
    api('save_config', { body:{ device_id:currentDevice.device_id, params:items } }).then(function(res) {
        toast(res.message || (res.success?'Tersimpan':'Gagal'), res.success?'success':'error');
        if (res.success) {
            items.forEach(function(item){ var p = allParams.find(function(x){return x.param_key===item.param_key;}); if(p) p.param_value=item.param_value; });
        }
    });
}

// saveTab variant for MQTT — also updates topic preview after save
function saveTabMqtt(group) {
    if (!currentDevice) return;
    var items = [];
    allParams.filter(function(p){ return p.param_group===group; }).forEach(function(p) {
        var inp = document.getElementById('param_'+p.param_key);
        if (!inp) return;
        var val = inp.type==='checkbox' ? (inp.checked?'1':'0') : inp.value;
        items.push({ param_key:p.param_key, param_value:val, param_type:p.param_type, param_group:group, label:p.label });
    });
    api('save_config', { body:{ device_id:currentDevice.device_id, params:items } }).then(function(res) {
        toast(res.message || (res.success?'Tersimpan':'Gagal'), res.success?'success':'error');
        if (res.success) {
            items.forEach(function(item){ var p = allParams.find(function(x){return x.param_key===item.param_key;}); if(p) p.param_value=item.param_value; });
            // Refresh topic preview
            var prefixEl = document.getElementById('param_mqtt_topic_prefix');
            var preview  = document.getElementById('topicPreview');
            if (prefixEl && preview && currentDevice) {
                var prefix = prefixEl.value.replace(/\/+$/,'');
                preview.textContent = prefix + '/' + currentDevice.device_id + '/data';
            }
        }
    });
}

// ── Apply shared WiFi/MQTT defaults to ALL devices ────────────────────────────
function applyToAll() {
    if (!confirm('Push WiFi & MQTT default ke SEMUA device yang terdaftar?\n\n'
               + 'SSID: ONE-YADIN\nBroker: 192.168.183.143:1883\nTopic prefix: yadin/sensor\n\n'
               + 'Nama device & parameter lain tidak berubah.')) return;
    api('apply_defaults', { body:{} }).then(function(res) {
        toast(res.message || (res.success?'Diterapkan':'Gagal'), res.success?'success':'error');
        // Refresh current device view if open
        if (currentDevice) selectDevice(currentDevice);
    });
}

function saveCustomParams() {
    if (!currentDevice) return;
    var customs = allParams.filter(function(p){ return p.param_group==='custom'; });
    var items = [];
    customs.forEach(function(p) {
        var labelEl = document.querySelector('[data-key="'+p.param_key+'"][data-field="label"]');
        var typeEl  = document.querySelector('[data-key="'+p.param_key+'"][data-field="type"]');
        var valEl   = document.querySelector('[data-key="'+p.param_key+'"][data-field="value"]');
        items.push({
            param_key  : p.param_key,
            param_value: valEl   ? valEl.value   : p.param_value,
            param_type : typeEl  ? typeEl.value  : p.param_type,
            param_group: 'custom',
            label      : labelEl ? labelEl.value : p.label,
        });
    });
    if (!items.length) { toast('Tidak ada custom param untuk disimpan','error'); return; }
    api('save_config', { body:{ device_id:currentDevice.device_id, params:items } }).then(function(res) {
        toast(res.message || (res.success?'Tersimpan':'Gagal'), res.success?'success':'error');
    });
}

// ── Add / delete device ───────────────────────────────────────────────────────
function openAddDevice() {
    loadMachines(function(ms) {
        var sel = document.getElementById('newMachineId');
        sel.innerHTML = '<option value="">— Belum ditentukan —</option>';
        ms.forEach(function(m){ sel.innerHTML += '<option value="'+m.id+'">'+esc(m.name)+'</option>'; });
    });
    document.getElementById('newDeviceId').value = '';
    $('#addDeviceModal').modal('show');
}

function submitAddDevice() {
    var devId   = document.getElementById('newDeviceId').value.trim().replace(/\s+/g,'_');
    var machId  = document.getElementById('newMachineId').value;
    if (!devId) { toast('Device ID wajib diisi','error'); return; }
    api('add_device', { body:{ device_id:devId, machine_id:machId } }).then(function(res) {
        toast(res.message, res.success?'success':'error');
        if (res.success) { $('#addDeviceModal').modal('hide'); loadDevices(); }
    });
}

function deleteDevice() {
    if (!currentDevice) return;
    if (!confirm('Hapus device "'+currentDevice.device_id+'" beserta semua konfigurasinya? Tindakan ini tidak bisa dibatalkan.')) return;
    api('delete_device', { body:{ device_id:currentDevice.device_id } }).then(function(res) {
        toast(res.message, res.success?'success':'error');
        if (res.success) {
            currentDevice = null;
            document.getElementById('mainPanel').innerHTML = '<div class="ecfg-empty"><i class="fas fa-microchip"></i><p>Pilih device di sebelah kiri.</p></div>';
            loadDevices();
        }
    });
}

// ── Add / delete custom param ─────────────────────────────────────────────────
function submitAddParam() {
    if (!currentDevice) return;
    var key   = document.getElementById('cpKey').value.trim();
    var label = document.getElementById('cpLabel').value.trim();
    var type  = document.getElementById('cpType').value;
    var val   = document.getElementById('cpValue').value;
    if (!key) { toast('Key wajib diisi','error'); return; }
    api('add_param', { body:{ device_id:currentDevice.device_id, param_key:key, label:label||key, param_type:type, param_value:val, param_group:'custom' }}).then(function(res) {
        toast(res.message || (res.success?'Ditambahkan':'Gagal'), res.success?'success':'error');
        if (res.success) {
            $('#addParamModal').modal('hide');
            allParams.push({ param_group:'custom', param_key:res.param_key, label:label||key, param_type:type, param_value:val });
            var pane = document.getElementById('tab-custom');
            if (pane) pane.innerHTML = renderCustomTab();
        }
    });
}

function deleteParam(key) {
    if (!currentDevice) return;
    if (!confirm('Hapus parameter "'+key+'"?')) return;
    api('delete_param', { body:{ device_id:currentDevice.device_id, param_key:key } }).then(function(res) {
        toast(res.success?'Dihapus':'Gagal', res.success?'success':'error');
        if (res.success) {
            allParams = allParams.filter(function(p){ return p.param_key !== key; });
            var pane = document.getElementById('tab-custom');
            if (pane) pane.innerHTML = renderCustomTab();
        }
    });
}

// ── Firmware ──────────────────────────────────────────────────────────────────
function openFwUpload() {
    if (!currentDevice) return;
    document.getElementById('fwVersion').value = '';
    document.getElementById('fwNotes').value   = '';
    document.getElementById('fwFile').value    = '';
    $('#fwUploadModal').modal('show');
}

function submitUploadFw() {
    if (!currentDevice) return;
    var ver  = document.getElementById('fwVersion').value.trim();
    var file = document.getElementById('fwFile').files[0];
    if (!ver || !file) { toast('Versi & file wajib diisi','error'); return; }
    var fd = new FormData();
    fd.append('action','upload_firmware');
    fd.append('device_id', currentDevice.device_id);
    fd.append('version', ver);
    fd.append('notes', document.getElementById('fwNotes').value);
    fd.append('firmware', file);
    toast('Mengupload...','success');
    fetch('api/esp32_config.php?action=upload_firmware', { method:'POST', body:fd })
        .then(function(r){ return r.json(); })
        .then(function(res) {
            toast(res.message, res.success?'success':'error');
            if (res.success) { $('#fwUploadModal').modal('hide'); selectDevice(currentDevice); }
        });
}

function activateFw(fwId) {
    if (!currentDevice) return;
    if (!confirm('Aktifkan firmware ini? Device akan di-update saat cek OTA berikutnya.')) return;
    api('activate_firmware', { body:{ device_id:currentDevice.device_id, firmware_id:fwId } }).then(function(res) {
        toast(res.message, res.success?'success':'error');
        if (res.success) selectDevice(currentDevice);
    });
}

function deleteFw(fwId) {
    if (!confirm('Hapus firmware ini?')) return;
    api('delete_firmware', { body:{ firmware_id:fwId } }).then(function(res) {
        toast(res.success?'Firmware dihapus':'Gagal', res.success?'success':'error');
        if (res.success) selectDevice(currentDevice);
    });
}

// ── Live topic preview update when prefix field changes ───────────────────────
document.addEventListener('input', function(e) {
    if (e.target && e.target.id === 'param_mqtt_topic_prefix') {
        var preview = document.getElementById('topicPreview');
        if (preview && currentDevice) {
            var prefix = e.target.value.replace(/\/+$/,'');
            preview.textContent = prefix + '/' + currentDevice.device_id + '/data';
        }
    }
});

// ── Init ──────────────────────────────────────────────────────────────────────
loadDevices();
loadMachines();
</script>

<?php require_once 'includes/footer.php'; ?>
