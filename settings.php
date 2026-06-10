<?php
require_once 'includes/config.php';
$requiredRole = 'admin';
require_once 'includes/auth_check.php';

$pageTitle  = 'Pengaturan';
$currentPage = 'settings';

// Load current system settings
$db = getDB();

// system_settings
$settingsRaw = [];
try {
    $rows = $db->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll();
    foreach ($rows as $r) $settingsRaw[$r['setting_key']] = $r['setting_value'];
} catch (Exception $e) {}

$siteName        = htmlspecialchars($settingsRaw['site_name']          ?? 'OEE Monitoring System');
$companyName     = htmlspecialchars($settingsRaw['company_name']        ?? 'PT. YADIN');
$retentionDays   = (int)($settingsRaw['data_retention_days']           ?? 90);
$refreshInterval = (int)($settingsRaw['auto_refresh_interval']         ?? 30);
$timezone        = htmlspecialchars($settingsRaw['timezone']            ?? 'Asia/Jakarta');
$mqttHost        = htmlspecialchars($settingsRaw['mqtt_host']           ?? 'localhost');
$mqttPort        = (int)($settingsRaw['mqtt_port']                     ?? 1883);
$mqttTopicPrefix = htmlspecialchars($settingsRaw['mqtt_topic_prefix']  ?? 'oee/');
$esp32Interval   = (int)($settingsRaw['esp32_data_interval']           ?? 5);
$vibWarn         = htmlspecialchars($settingsRaw['vibration_warning']   ?? '2.8');
$vibCrit         = htmlspecialchars($settingsRaw['vibration_critical']  ?? '7.1');

// Global OEE targets
$oeeTarget  = (int)($settingsRaw['oee_target']          ?? 85);
$availTarget= (int)($settingsRaw['availability_target'] ?? 90);
$perfTarget = (int)($settingsRaw['performance_target']  ?? 95);
$qualTarget = (int)($settingsRaw['quality_target']      ?? 99);

// Machines list
$machines = [];
try {
    $machines = $db->query("SELECT id, name FROM machines ORDER BY name")->fetchAll();
} catch (Exception $e) {}

// Per-machine OEE settings
$oeePerMachine = [];
try {
    $rows = $db->query("SELECT * FROM oee_settings")->fetchAll();
    foreach ($rows as $r) $oeePerMachine[$r['machine_id']] = $r;
} catch (Exception $e) {}

// Sensor thresholds
$thresholds = [];
try {
    $thresholds = $db->query("SELECT st.*, m.name as machine_name FROM sensor_thresholds st LEFT JOIN machines m ON m.id=st.machine_id ORDER BY st.machine_id, st.sensor_key")->fetchAll();
} catch (Exception $e) {}

// ESP32 devices
$esp32Devices = [];
try {
    $esp32Devices = $db->query("SELECT e.*, m.name as machine_name FROM esp32_devices e LEFT JOIN machines m ON m.id=e.machine_id ORDER BY e.device_id")->fetchAll();
} catch (Exception $e) {}

$timezones = ['Asia/Jakarta','Asia/Makassar','Asia/Jayapura','Asia/Singapore','UTC'];

require_once 'includes/header.php';
?>

<!-- Page Heading -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-cog mr-2"></i>Pengaturan Sistem</h1>
</div>

<!-- Toast notification -->
<div id="toastContainer" style="position:fixed;top:20px;right:20px;z-index:9999;min-width:280px;"></div>

<!-- Nav Tabs -->
<ul class="nav nav-tabs" id="settingsTabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" id="tab-umum"      data-toggle="tab" href="#pane-umum"      role="tab"><i class="fas fa-sliders-h mr-1"></i>Umum</a>
    </li>
    <li class="nav-item">
        <a class="nav-link"        id="tab-oee"       data-toggle="tab" href="#pane-oee"       role="tab"><i class="fas fa-bullseye mr-1"></i>Target OEE</a>
    </li>
    <li class="nav-item">
        <a class="nav-link"        id="tab-threshold" data-toggle="tab" href="#pane-threshold" role="tab"><i class="fas fa-thermometer-half mr-1"></i>Threshold Sensor</a>
    </li>
    <li class="nav-item">
        <a class="nav-link"        id="tab-vibrasi"   data-toggle="tab" href="#pane-vibrasi"   role="tab"><i class="fas fa-wave-square mr-1"></i>Vibrasi</a>
    </li>
    <li class="nav-item">
        <a class="nav-link"        id="tab-esp32"     data-toggle="tab" href="#pane-esp32"     role="tab"><i class="fas fa-broadcast-tower mr-1"></i>ESP32 &amp; MQTT</a>
    </li>
    <li class="nav-item">
        <a class="nav-link"        id="tab-database"  data-toggle="tab" href="#pane-database"  role="tab"><i class="fas fa-database mr-1"></i>Database</a>
    </li>
</ul>

<div class="tab-content border border-top-0 p-4 bg-white shadow-sm mb-4">

    <!-- ============================================================ TAB 1: UMUM -->
    <div class="tab-pane fade show active" id="pane-umum" role="tabpanel">
        <h5 class="text-primary mb-3"><i class="fas fa-sliders-h mr-2"></i>Pengaturan Umum</h5>
        <form id="formUmum">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="site_name">Nama Situs</label>
                        <input type="text" class="form-control" id="site_name" name="site_name" value="<?php echo $siteName; ?>" placeholder="OEE Monitoring System">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="company_name">Nama Perusahaan</label>
                        <input type="text" class="form-control" id="company_name" name="company_name" value="<?php echo $companyName; ?>" placeholder="PT. YADIN">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="data_retention_days">Retensi Data (hari)</label>
                        <input type="number" class="form-control" id="data_retention_days" name="data_retention_days" value="<?php echo $retentionDays; ?>" min="1" max="3650">
                        <small class="form-text text-muted">Data lebih lama dari nilai ini akan dibersihkan.</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="auto_refresh_interval">Interval Auto Refresh (detik)</label>
                        <input type="number" class="form-control" id="auto_refresh_interval" name="auto_refresh_interval" value="<?php echo $refreshInterval; ?>" min="5" max="3600">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="timezone">Zona Waktu</label>
                        <select class="form-control" id="timezone" name="timezone">
                            <?php foreach ($timezones as $tz): ?>
                            <option value="<?php echo $tz; ?>" <?php echo $timezone===$tz?'selected':''; ?>><?php echo $tz; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <button type="button" class="btn btn-primary" id="btnSaveUmum">
                <i class="fas fa-save mr-1"></i>Simpan Pengaturan Umum
            </button>
        </form>
    </div>

    <!-- ============================================================ TAB 2: TARGET OEE -->
    <div class="tab-pane fade" id="pane-oee" role="tabpanel">
        <h5 class="text-primary mb-3"><i class="fas fa-bullseye mr-2"></i>Target OEE Global</h5>
        <div class="row mb-4">
            <!-- Gauge preview -->
            <div class="col-md-3 text-center">
                <div id="gaugeCircle" style="
                    width:160px;height:160px;border-radius:50%;
                    background: conic-gradient(#4e73df <?php echo $oeeTarget; ?>%, #e0e0e0 0%);
                    display:inline-flex;align-items:center;justify-content:center;
                    margin-bottom:8px;position:relative;">
                    <div style="width:120px;height:120px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:700;color:#4e73df;">
                        <span id="gaugeVal"><?php echo $oeeTarget; ?></span>%
                    </div>
                </div>
                <p class="small text-muted">OEE Target Preview</p>
            </div>
            <!-- Sliders -->
            <div class="col-md-9">
                <?php
                $sliders = [
                    ['oee_target',          'Target OEE',       $oeeTarget,   '#4e73df'],
                    ['availability_target', 'Availability',     $availTarget, '#1cc88a'],
                    ['performance_target',  'Performance',      $perfTarget,  '#f6c23e'],
                    ['quality_target',      'Quality',          $qualTarget,  '#e74a3b'],
                ];
                foreach ($sliders as [$key, $label, $val, $color]):
                ?>
                <div class="form-group mb-2">
                    <label class="d-flex justify-content-between">
                        <span><?php echo $label; ?> %</span>
                        <input type="number" class="form-control form-control-sm text-right oee-num"
                               id="num_<?php echo $key; ?>" data-key="<?php echo $key; ?>"
                               value="<?php echo $val; ?>" min="0" max="100" style="width:70px;">
                    </label>
                    <input type="range" class="form-control-range oee-slider"
                           id="slider_<?php echo $key; ?>" data-key="<?php echo $key; ?>"
                           value="<?php echo $val; ?>" min="0" max="100"
                           style="accent-color:<?php echo $color; ?>;">
                </div>
                <?php endforeach; ?>
                <button type="button" class="btn btn-primary mt-2" id="btnSaveOeeGlobal">
                    <i class="fas fa-save mr-1"></i>Simpan Target Global
                </button>
            </div>
        </div>

        <!-- Per-machine OEE -->
        <h6 class="text-secondary mb-2"><i class="fas fa-cogs mr-1"></i>Target OEE Per Mesin</h6>
        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead class="thead-light">
                    <tr>
                        <th>Mesin</th>
                        <th>Availability %</th>
                        <th>Performance %</th>
                        <th>Quality %</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($machines as $m):
                    $mid = $m['id'];
                    $mo  = $oeePerMachine[$mid] ?? [];
                    $av  = (int)($mo['availability'] ?? $availTarget);
                    $pf  = (int)($mo['performance']  ?? $perfTarget);
                    $ql  = (int)($mo['quality']       ?? $qualTarget);
                ?>
                <tr data-machine-id="<?php echo $mid; ?>">
                    <td><?php echo htmlspecialchars($m['name']); ?></td>
                    <td><input type="number" class="form-control form-control-sm machine-avail" value="<?php echo $av; ?>" min="0" max="100"></td>
                    <td><input type="number" class="form-control form-control-sm machine-perf"  value="<?php echo $pf; ?>" min="0" max="100"></td>
                    <td><input type="number" class="form-control form-control-sm machine-qual"  value="<?php echo $ql; ?>" min="0" max="100"></td>
                    <td>
                        <button class="btn btn-sm btn-success btnSaveMachineOee" data-machine-id="<?php echo $mid; ?>">
                            <i class="fas fa-save"></i> Simpan
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($machines)): ?>
                <tr><td colspan="5" class="text-center text-muted">Tidak ada data mesin.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ============================================================ TAB 3: THRESHOLD SENSOR -->
    <div class="tab-pane fade" id="pane-threshold" role="tabpanel">
        <h5 class="text-primary mb-3"><i class="fas fa-thermometer-half mr-2"></i>Threshold Sensor</h5>
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="tblThreshold">
                <thead class="thead-light">
                    <tr>
                        <th>Mesin</th>
                        <th>Sensor Key</th>
                        <th>Batas Bawah (Lo)</th>
                        <th>Batas Atas (Hi)</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($thresholds as $t): ?>
                <tr data-thresh-id="<?php echo $t['id']; ?>" data-machine-id="<?php echo $t['machine_id']; ?>" data-sensor-key="<?php echo htmlspecialchars($t['sensor_key']); ?>">
                    <td><?php echo htmlspecialchars($t['machine_name'] ?? $t['machine_id']); ?></td>
                    <td class="td-sensor-key"><?php echo htmlspecialchars($t['sensor_key']); ?></td>
                    <td class="td-lo">
                        <span class="display-val"><?php echo htmlspecialchars($t['thresh_lo']); ?></span>
                        <input type="number" class="form-control form-control-sm input-lo d-none" value="<?php echo htmlspecialchars($t['thresh_lo']); ?>" step="0.01">
                    </td>
                    <td class="td-hi">
                        <span class="display-val"><?php echo htmlspecialchars($t['thresh_hi']); ?></span>
                        <input type="number" class="form-control form-control-sm input-hi d-none" value="<?php echo htmlspecialchars($t['thresh_hi']); ?>" step="0.01">
                    </td>
                    <td>
                        <button class="btn btn-sm btn-warning btn-edit-thresh"><i class="fas fa-edit"></i> Edit</button>
                        <button class="btn btn-sm btn-success btn-save-thresh d-none"><i class="fas fa-check"></i> Simpan</button>
                        <button class="btn btn-sm btn-secondary btn-cancel-thresh d-none"><i class="fas fa-times"></i></button>
                        <button class="btn btn-sm btn-outline-secondary btn-reset-thresh ml-1" data-machine-id="<?php echo $t['machine_id']; ?>">
                            <i class="fas fa-undo"></i> Reset
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($thresholds)): ?>
                <tr><td colspan="5" class="text-center text-muted">Tidak ada data threshold.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ============================================================ TAB 4: VIBRASI -->
    <div class="tab-pane fade" id="pane-vibrasi" role="tabpanel">
        <h5 class="text-primary mb-3"><i class="fas fa-wave-square mr-2"></i>Pengaturan Vibrasi</h5>
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="vibration_warning">Ambang Peringatan (mm/s)</label>
                    <input type="number" class="form-control" id="vibration_warning" name="vibration_warning" value="<?php echo $vibWarn; ?>" step="0.1" min="0">
                    <small class="form-text text-muted">Nilai default: 2.8 mm/s — zona peringatan dimulai di sini.</small>
                </div>
                <div class="form-group">
                    <label for="vibration_critical">Ambang Kritis (mm/s)</label>
                    <input type="number" class="form-control" id="vibration_critical" name="vibration_critical" value="<?php echo $vibCrit; ?>" step="0.1" min="0">
                    <small class="form-text text-muted">Nilai default: 7.1 mm/s — zona berbahaya di atas nilai ini.</small>
                </div>
                <button type="button" class="btn btn-primary" id="btnSaveVibrasi">
                    <i class="fas fa-save mr-1"></i>Simpan Pengaturan Vibrasi
                </button>
            </div>
            <div class="col-md-6">
                <label>Zona Vibrasi (0 – 15 mm/s)</label>
                <div id="vibZoneBar" style="width:100%;height:40px;border-radius:6px;overflow:hidden;position:relative;border:1px solid #ccc;"></div>
                <div class="d-flex justify-content-between mt-1 small text-muted">
                    <span>0</span><span>5</span><span>10</span><span>15 mm/s</span>
                </div>
                <div class="mt-3">
                    <span class="badge" style="background:#1cc88a;color:#fff;padding:6px 12px;">Aman</span>
                    <span class="badge" style="background:#f6c23e;color:#fff;padding:6px 12px;">Peringatan</span>
                    <span class="badge" style="background:#e74a3b;color:#fff;padding:6px 12px;">Kritis</span>
                </div>

                <!-- ISO 10816 Reference -->
                <h6 class="mt-4 text-secondary">Referensi ISO 10816</h6>
                <table class="table table-bordered table-sm small">
                    <thead class="thead-light">
                        <tr><th>Kelas</th><th>Deskripsi</th><th>Baik</th><th>Diterima</th><th>Peringatan</th><th>Tidak Diterima</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>I</td><td>Mesin kecil &lt;15 kW</td><td>&lt;0.71</td><td>0.71–1.8</td><td>1.8–4.5</td><td>&gt;4.5</td></tr>
                        <tr><td>II</td><td>Mesin medium 15–75 kW</td><td>&lt;1.12</td><td>1.12–2.8</td><td>2.8–7.1</td><td>&gt;7.1</td></tr>
                        <tr><td>III</td><td>Mesin besar kaku &gt;75 kW</td><td>&lt;1.8</td><td>1.8–4.5</td><td>4.5–11.2</td><td>&gt;11.2</td></tr>
                        <tr><td>IV</td><td>Mesin besar fleksibel &gt;75 kW</td><td>&lt;2.8</td><td>2.8–7.1</td><td>7.1–18</td><td>&gt;18</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ============================================================ TAB 5: ESP32 & MQTT -->
    <div class="tab-pane fade" id="pane-esp32" role="tabpanel">
        <h5 class="text-primary mb-3"><i class="fas fa-broadcast-tower mr-2"></i>Konfigurasi ESP32 &amp; MQTT</h5>
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="mqtt_host">MQTT Broker Host</label>
                    <input type="text" class="form-control" id="mqtt_host" name="mqtt_host" value="<?php echo $mqttHost; ?>" placeholder="localhost">
                </div>
                <div class="form-group">
                    <label for="mqtt_port">MQTT Port</label>
                    <input type="number" class="form-control" id="mqtt_port" name="mqtt_port" value="<?php echo $mqttPort; ?>" min="1" max="65535">
                </div>
                <div class="form-group">
                    <label for="mqtt_topic_prefix">Topic Prefix</label>
                    <input type="text" class="form-control" id="mqtt_topic_prefix" name="mqtt_topic_prefix" value="<?php echo $mqttTopicPrefix; ?>" placeholder="oee/">
                </div>
                <div class="form-group">
                    <label for="esp32_data_interval">Interval Data ESP32</label>
                    <select class="form-control" id="esp32_data_interval" name="esp32_data_interval">
                        <?php foreach ([1,3,5,10] as $sec): ?>
                        <option value="<?php echo $sec; ?>" <?php echo $esp32Interval==$sec?'selected':''; ?>><?php echo $sec; ?> detik</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="button" class="btn btn-primary mr-2" id="btnSaveEsp32">
                    <i class="fas fa-save mr-1"></i>Simpan
                </button>
                <button type="button" class="btn btn-info" id="btnTestKoneksi">
                    <i class="fas fa-plug mr-1"></i>Test Koneksi
                </button>
                <span id="koneksiResult" class="ml-2"></span>
            </div>
        </div>

        <!-- ESP32 Devices Table -->
        <h6 class="text-secondary mt-4 mb-2"><i class="fas fa-microchip mr-1"></i>Daftar Perangkat ESP32</h6>
        <div class="table-responsive">
            <table class="table table-bordered table-hover table-sm">
                <thead class="thead-light">
                    <tr>
                        <th>Device ID</th>
                        <th>Mesin</th>
                        <th>IP Address</th>
                        <th>Status</th>
                        <th>Terakhir Terlihat</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($esp32Devices as $dev): ?>
                <tr>
                    <td><?php echo htmlspecialchars($dev['device_id']); ?></td>
                    <td><?php echo htmlspecialchars($dev['machine_name'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($dev['ip_address'] ?? '-'); ?></td>
                    <td>
                        <?php
                        $st = strtolower($dev['status'] ?? 'unknown');
                        $badgeClass = $st === 'online' ? 'success' : ($st === 'offline' ? 'danger' : 'secondary');
                        ?>
                        <span class="badge badge-<?php echo $badgeClass; ?>"><?php echo htmlspecialchars(ucfirst($st)); ?></span>
                    </td>
                    <td><?php echo htmlspecialchars($dev['last_seen'] ?? '-'); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($esp32Devices)): ?>
                <tr><td colspan="5" class="text-center text-muted">Tidak ada perangkat ESP32 terdaftar.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ============================================================ TAB 6: DATABASE -->
    <div class="tab-pane fade" id="pane-database" role="tabpanel">
        <h5 class="text-primary mb-3"><i class="fas fa-database mr-2"></i>Manajemen Database</h5>
        <button type="button" class="btn btn-outline-primary mb-3" id="btnLoadDbStats">
            <i class="fas fa-sync-alt mr-1"></i>Muat Statistik DB
        </button>
        <div id="dbStatsContainer">
            <p class="text-muted">Klik "Muat Statistik DB" untuk menampilkan informasi.</p>
        </div>
        <hr>
        <h6 class="text-danger"><i class="fas fa-trash mr-1"></i>Bersihkan Data Lama</h6>
        <p class="text-muted small">Menghapus data sensor dan log yang lebih lama dari <strong id="retentionDisplay"><?php echo $retentionDays; ?></strong> hari (sesuai pengaturan retensi).</p>
        <button type="button" class="btn btn-danger" id="btnPurgeData">
            <i class="fas fa-broom mr-1"></i>Bersihkan Data Lama
        </button>
        <div id="purgeResult" class="mt-3"></div>
    </div>

</div><!-- /.tab-content -->

<?php require_once 'includes/footer.php'; ?>

<!-- ============================================================ SCRIPTS -->
<script>
$(document).ready(function () {

    /* ---- Toast helper ---- */
    function showToast(msg, type) {
        type = type || 'success';
        var colors = { success: '#1cc88a', danger: '#e74a3b', warning: '#f6c23e', info: '#36b9cc' };
        var id = 'toast_' + Date.now();
        var html = '<div id="' + id + '" style="background:' + (colors[type] || colors.info) + ';color:#fff;padding:12px 20px;border-radius:6px;margin-bottom:8px;box-shadow:0 2px 8px rgba(0,0,0,.2);font-size:.9rem;">' + msg + '</div>';
        $('#toastContainer').append(html);
        setTimeout(function () { $('#' + id).fadeOut(400, function () { $(this).remove(); }); }, 3500);
    }

    /* ---- Generic save helper ---- */
    function saveSettings(data, callback) {
        $.ajax({
            url: 'api/settings.php?action=save',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ settings: data }),
            success: function (res) {
                if (res && res.success) {
                    showToast(res.message || 'Pengaturan disimpan.', 'success');
                    if (callback) callback(res);
                } else {
                    showToast((res && res.message) ? res.message : 'Gagal menyimpan.', 'danger');
                }
            },
            error: function () {
                showToast('Terjadi kesalahan. Coba lagi.', 'danger');
            }
        });
    }

    /* ================================================================
       TAB 1: UMUM
    ================================================================ */
    $('#btnSaveUmum').on('click', function () {
        var data = {
            site_name:             $('#site_name').val(),
            company_name:          $('#company_name').val(),
            data_retention_days:   $('#data_retention_days').val(),
            auto_refresh_interval: $('#auto_refresh_interval').val(),
            timezone:              $('#timezone').val()
        };
        saveSettings(data, function () {
            $('#retentionDisplay').text($('#data_retention_days').val());
        });
    });

    /* ================================================================
       TAB 2: TARGET OEE — slider <-> number sync + gauge
    ================================================================ */
    function updateGauge(val) {
        val = Math.max(0, Math.min(100, parseInt(val) || 0));
        $('#gaugeCircle').css('background', 'conic-gradient(#4e73df ' + val + '%, #e0e0e0 0%)');
        $('#gaugeVal').text(val);
    }

    $('.oee-slider').on('input', function () {
        var key = $(this).data('key');
        var val = $(this).val();
        $('#num_' + key).val(val);
        if (key === 'oee_target') updateGauge(val);
    });

    $('.oee-num').on('input', function () {
        var key = $(this).data('key');
        var val = $(this).val();
        $('#slider_' + key).val(val);
        if (key === 'oee_target') updateGauge(val);
    });

    updateGauge($('#num_oee_target').val());

    $('#btnSaveOeeGlobal').on('click', function () {
        var data = {};
        $('.oee-num').each(function () {
            data[$(this).data('key')] = $(this).val();
        });
        saveSettings(data);
    });

    $(document).on('click', '.btnSaveMachineOee', function () {
        var machineId = $(this).data('machine-id');
        var row = $('tr[data-machine-id="' + machineId + '"]');
        var payload = {
            machine_id:   machineId,
            availability: row.find('.machine-avail').val(),
            performance:  row.find('.machine-perf').val(),
            quality:      row.find('.machine-qual').val()
        };
        $.ajax({
            url: 'api/settings.php?action=save_machine_oee',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            success: function (res) {
                if (res && res.success) {
                    showToast('Target mesin disimpan.', 'success');
                } else {
                    showToast('Gagal menyimpan target mesin.', 'danger');
                }
            },
            error: function () { showToast('Kesalahan saat menyimpan.', 'danger'); }
        });
    });

    /* ================================================================
       TAB 3: THRESHOLD SENSOR — inline edit
    ================================================================ */
    $(document).on('click', '.btn-edit-thresh', function () {
        var row = $(this).closest('tr');
        row.find('.display-val').addClass('d-none');
        row.find('.input-lo, .input-hi').removeClass('d-none');
        $(this).addClass('d-none');
        row.find('.btn-save-thresh, .btn-cancel-thresh').removeClass('d-none');
    });

    $(document).on('click', '.btn-cancel-thresh', function () {
        var row = $(this).closest('tr');
        row.find('.display-val').removeClass('d-none');
        row.find('.input-lo, .input-hi').addClass('d-none');
        $(this).addClass('d-none');
        row.find('.btn-save-thresh').addClass('d-none');
        row.find('.btn-edit-thresh').removeClass('d-none');
        row.find('.input-lo').val(row.find('.td-lo .display-val').text());
        row.find('.input-hi').val(row.find('.td-hi .display-val').text());
    });

    $(document).on('click', '.btn-save-thresh', function () {
        var row = $(this).closest('tr');
        var machineId = row.data('machine-id');
        var sensorKey = row.data('sensor-key');
        var lo = row.find('.input-lo').val();
        var hi = row.find('.input-hi').val();
        $.ajax({
            url: 'api/settings.php?action=update_threshold',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ machine_id: machineId, sensor_key: sensorKey, thresh_lo: lo, thresh_hi: hi }),
            success: function (res) {
                if (res && res.success) {
                    row.find('.td-lo .display-val').text(lo);
                    row.find('.td-hi .display-val').text(hi);
                    row.find('.display-val').removeClass('d-none');
                    row.find('.input-lo, .input-hi').addClass('d-none');
                    row.find('.btn-save-thresh, .btn-cancel-thresh').addClass('d-none');
                    row.find('.btn-edit-thresh').removeClass('d-none');
                    showToast('Threshold disimpan.', 'success');
                } else {
                    showToast('Gagal menyimpan threshold.', 'danger');
                }
            },
            error: function () { showToast('Kesalahan koneksi.', 'danger'); }
        });
    });

    $(document).on('click', '.btn-reset-thresh', function () {
        var machineId = $(this).data('machine-id');
        if (!confirm('Reset semua threshold untuk mesin ini ke nilai default?')) return;
        $.ajax({
            url: 'api/settings.php?action=reset_threshold',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ machine_id: machineId }),
            success: function (res) {
                if (res && res.success) {
                    showToast('Threshold direset ke default.', 'info');
                    location.reload();
                } else {
                    showToast('Gagal reset threshold.', 'danger');
                }
            },
            error: function () { showToast('Kesalahan koneksi.', 'danger'); }
        });
    });

    /* ================================================================
       TAB 4: VIBRASI — zone bar
    ================================================================ */
    function renderVibZone() {
        var maxVal  = 15;
        var warnVal = parseFloat($('#vibration_warning').val())  || 2.8;
        var critVal = parseFloat($('#vibration_critical').val()) || 7.1;
        warnVal = Math.min(warnVal, maxVal);
        critVal = Math.min(Math.max(critVal, warnVal), maxVal);
        var pWarn   = (warnVal / maxVal * 100).toFixed(1);
        var pBetween = ((critVal - warnVal) / maxVal * 100).toFixed(1);
        var pDanger = (100 - (critVal / maxVal * 100)).toFixed(1);
        var bar = '<div style="display:flex;height:100%;">';
        bar += '<div style="width:' + pWarn    + '%;background:#1cc88a;"></div>';
        bar += '<div style="width:' + pBetween + '%;background:#f6c23e;"></div>';
        bar += '<div style="width:' + pDanger  + '%;background:#e74a3b;"></div>';
        bar += '</div>';
        $('#vibZoneBar').html(bar);
    }

    $('#vibration_warning, #vibration_critical').on('input', renderVibZone);
    renderVibZone();

    $('#btnSaveVibrasi').on('click', function () {
        saveSettings({
            vibration_warning:  $('#vibration_warning').val(),
            vibration_critical: $('#vibration_critical').val()
        });
    });

    /* ================================================================
       TAB 5: ESP32 & MQTT
    ================================================================ */
    $('#btnSaveEsp32').on('click', function () {
        saveSettings({
            mqtt_host:           $('#mqtt_host').val(),
            mqtt_port:           $('#mqtt_port').val(),
            mqtt_topic_prefix:   $('#mqtt_topic_prefix').val(),
            esp32_data_interval: $('#esp32_data_interval').val()
        });
    });

    $('#btnTestKoneksi').on('click', function () {
        var $result = $('#koneksiResult');
        $result.html('<span class="badge badge-secondary">Menguji...</span>');
        $.get('api/esp32.php?action=status', function (res) {
            if (res && res.success) {
                $result.html('<span class="badge badge-success"><i class="fas fa-check mr-1"></i>Terhubung</span>');
            } else {
                var msg = (res && res.message) ? res.message : 'Tidak dapat terhubung';
                $result.html('<span class="badge badge-danger"><i class="fas fa-times mr-1"></i>' + msg + '</span>');
            }
        }).fail(function () {
            $result.html('<span class="badge badge-danger"><i class="fas fa-times mr-1"></i>Gagal</span>');
        });
    });

    /* ================================================================
       TAB 6: DATABASE
    ================================================================ */
    $('#btnLoadDbStats').on('click', function () {
        $('#dbStatsContainer').html('<p class="text-muted"><i class="fas fa-spinner fa-spin mr-1"></i>Memuat...</p>');
        $.get('api/settings.php?action=db_stats', function (res) {
            if (res && res.success && res.data) {
                var html = '<div class="table-responsive"><table class="table table-bordered table-sm" style="max-width:500px;">';
                html += '<thead class="thead-light"><tr><th>Tabel</th><th class="text-right">Jumlah Baris</th></tr></thead><tbody>';
                $.each(res.data, function (tbl, cnt) {
                    html += '<tr><td>' + $('<span>').text(tbl).html() + '</td><td class="text-right">' + parseInt(cnt).toLocaleString() + '</td></tr>';
                });
                html += '</tbody></table></div>';
                $('#dbStatsContainer').html(html);
            } else {
                $('#dbStatsContainer').html('<p class="text-danger">Gagal memuat statistik.</p>');
            }
        }).fail(function () {
            $('#dbStatsContainer').html('<p class="text-danger">Kesalahan koneksi.</p>');
        });
    });

    $('#btnPurgeData').on('click', function () {
        var retDays = parseInt($('#data_retention_days').val()) || parseInt($('#retentionDisplay').text()) || 90;
        if (!confirm('Hapus semua data yang lebih lama dari ' + retDays + ' hari? Tindakan ini tidak dapat dibatalkan.')) return;
        $('#purgeResult').html('<span class="text-muted"><i class="fas fa-spinner fa-spin mr-1"></i>Memproses...</span>');
        $.ajax({
            url: 'api/settings.php?action=purge_data',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ retention_days: retDays }),
            success: function (res) {
                if (res && res.success) {
                    var deleted = (res.deleted !== undefined) ? res.deleted : '?';
                    $('#purgeResult').html('<div class="alert alert-success"><i class="fas fa-check mr-1"></i>' + deleted + ' record berhasil dihapus.</div>');
                    showToast(deleted + ' record dihapus.', 'success');
                } else {
                    $('#purgeResult').html('<div class="alert alert-danger">' + ((res && res.message) ? res.message : 'Gagal membersihkan data.') + '</div>');
                }
            },
            error: function () {
                $('#purgeResult').html('<div class="alert alert-danger">Kesalahan koneksi.</div>');
            }
        });
    });

});
</script>
