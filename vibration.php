<?php
// ============================================================
//  vibration.php — Monitor Vibrasi WitMotion WTV B02-485
//  4 sensor fisik terpisah, masing-masing mengukur 1 arah:
//    sensor_num=1 → Axis X
//    sensor_num=2 → Axis Y
//    sensor_num=3 → Axis Z
//    sensor_num=4 → Axis B
//  Data per sensor: rms_overall = nilai RMS arah tersebut
// ============================================================
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth_check.php';

$pageTitle   = 'Analisis Vibrasi';
$currentPage = 'vibration';
$db = getDB();

define('VIB_WARN', 2.8);
define('VIB_CRIT', 7.1);

function vStatus(float $v): string {
    if ($v >= VIB_CRIT) return 'critical';
    if ($v >= VIB_WARN) return 'warning';
    return 'normal';
}
function vBadge(string $s): string {
    $c = ['normal'=>'success','warning'=>'warning','critical'=>'danger'][$s] ?? 'secondary';
    $l = ['normal'=>'Normal','warning'=>'Warning','critical'=>'Critical'][$s] ?? $s;
    return "<span class=\"badge badge-{$c}\">{$l}</span>";
}

// Definisi 4 sensor fisik → axis
$sensorDef = [
    1 => ['axis'=>'X', 'color'=>'#4e73df', 'icon'=>'fas fa-arrows-alt-h',    'desc'=>'Horizontal'],
    2 => ['axis'=>'Y', 'color'=>'#1cc88a', 'icon'=>'fas fa-arrows-alt-v',    'desc'=>'Vertikal'],
    3 => ['axis'=>'Z', 'color'=>'#f6c23e', 'icon'=>'fas fa-expand-arrows-alt','desc'=>'Aksial'],
    4 => ['axis'=>'B', 'color'=>'#e74a3b', 'icon'=>'fas fa-dot-circle',       'desc'=>'Bantalan'],
];

// ── Filters ───────────────────────────────────────────────────
$machines  = $db->query("SELECT id, name, status FROM machines ORDER BY name")->fetchAll();
$machineId = isset($_GET['machine_id']) ? (int)$_GET['machine_id'] : 0;
if (!$machineId && !empty($machines)) $machineId = (int)$machines[0]['id'];
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-1 day'));
$dateTo   = $_GET['date_to']   ?? date('Y-m-d');

// ── Latest reading per sensor_num ─────────────────────────────
$latestSensors = [];
if ($machineId) {
    $lq = $db->prepare("
        SELECT vr.sensor_num, vr.sensor_1, vr.sensor_2, vr.sensor_3,
               vr.axis_b, vr.rms_overall, vr.status, vr.temp_sensor,
               vr.recorded_at, COALESCE(vs.sensor_label,'') AS sensor_label
        FROM vibration_readings vr
        INNER JOIN (
            SELECT machine_id, sensor_num, MAX(recorded_at) AS mx
            FROM vibration_readings
            WHERE machine_id = ?
            GROUP BY machine_id, sensor_num
        ) lx ON lx.machine_id = vr.machine_id
             AND lx.sensor_num = vr.sensor_num
             AND lx.mx         = vr.recorded_at
        LEFT JOIN vibration_sessions vs ON vs.id = vr.session_id
        ORDER BY vr.sensor_num
    ");
    $lq->execute([$machineId]);
    foreach ($lq->fetchAll() as $r) $latestSensors[(int)$r['sensor_num']] = $r;
}

// ── 24h history per sensor (untuk chart) ─────────────────────
$chartBySensor = [1=>[], 2=>[], 3=>[], 4=>[]];
if ($machineId) {
    $cq = $db->prepare("
        SELECT sensor_num, rms_overall, recorded_at
        FROM vibration_readings
        WHERE machine_id=? AND recorded_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY recorded_at ASC LIMIT 600
    ");
    $cq->execute([$machineId]);
    foreach ($cq->fetchAll() as $r) {
        $sn = (int)$r['sensor_num'];
        if (isset($chartBySensor[$sn])) $chartBySensor[$sn][] = $r;
    }
}

// ── Stats 24h per sensor ──────────────────────────────────────
$statsBySensor = [];
for ($i = 1; $i <= 4; $i++) {
    $d = $chartBySensor[$i];
    if (empty($d)) {
        $statsBySensor[$i] = ['avg'=>0,'max'=>0,'warn'=>0,'crit'=>0,'status'=>'normal'];
    } else {
        $vals = array_map(fn($r) => (float)$r['rms_overall'], $d);
        $mx = max($vals); $w = $c = 0;
        foreach ($vals as $v) { if ($v >= VIB_CRIT) $c++; elseif ($v >= VIB_WARN) $w++; }
        $statsBySensor[$i] = [
            'avg' => array_sum($vals)/count($vals),
            'max' => $mx, 'warn'=>$w, 'crit'=>$c, 'status'=>vStatus($mx)
        ];
    }
}

// ── History table ─────────────────────────────────────────────
$tableData = [];
if ($machineId) {
    $tq = $db->prepare("
        SELECT vr.*, COALESCE(vs.sensor_label,'') AS sensor_label
        FROM vibration_readings vr
        LEFT JOIN vibration_sessions vs ON vs.id = vr.session_id
        WHERE vr.machine_id=? AND DATE(vr.recorded_at) BETWEEN ? AND ?
        ORDER BY vr.recorded_at DESC LIMIT 500
    ");
    $tq->execute([$machineId, $dateFrom, $dateTo]);
    $tableData = $tq->fetchAll();
}

// ── Alert? ────────────────────────────────────────────────────
$anyAlert = false;
foreach ($statsBySensor as $s) { if ($s['status']!=='normal') { $anyAlert=true; break; } }

// ── Chart JSON ────────────────────────────────────────────────
$chartJson = [];
for ($i=1;$i<=4;$i++) {
    $chartJson[$i] = [
        'labels' => array_map(fn($r)=>substr($r['recorded_at'],11,5), $chartBySensor[$i]),
        'data'   => array_map(fn($r)=>round((float)$r['rms_overall'],3), $chartBySensor[$i]),
    ];
}

require_once __DIR__ . '/includes/header.php';
?>
<style>
.iso-track {
    width:100%; height:10px; border-radius:5px; position:relative;
    background:linear-gradient(to right,
        #1cc88a 0%,#1cc88a 28%,
        #f6c23e 28%,#f6c23e 71%,
        #e74a3b 71%,#e74a3b 100%);
}
.iso-marker {
    position:absolute; top:-5px; width:3px; height:20px;
    background:#222; border-radius:2px; transform:translateX(-50%); transition:left .4s;
}
.sensor-rms { font-size:2.4rem; font-weight:900; line-height:1; }
#liveTopBar { position:fixed;top:0;left:0;right:0;height:3px;z-index:10000;
    background:linear-gradient(90deg,#4e73df,#1cc88a);width:0%;transition:width .5s; }
</style>

<div id="liveTopBar"></div>

<!-- ── Heading ────────────────────────────────────────────────── -->
<div class="d-flex flex-wrap align-items-start justify-content-between mb-3" style="gap:10px;">
    <div>
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-wave-square mr-2"></i>Monitoring Vibrasi
        </h1>
        <small class="text-muted">
            4 Sensor WitMotion WTV B02-485 ·
            <span style="color:#4e73df;">■ S1=X</span>
            <span style="color:#1cc88a;" class="ml-1">■ S2=Y</span>
            <span style="color:#f6c23e;" class="ml-1">■ S3=Z</span>
            <span style="color:#e74a3b;" class="ml-1">■ S4=B</span>
            · ISO 10816
        </small>
    </div>
    <div class="d-flex flex-wrap align-items-center" style="gap:6px;">
        <form method="GET" id="filterForm" class="d-flex flex-wrap align-items-center" style="gap:6px;">
            <select name="machine_id" class="form-control form-control-sm" style="width:160px;"
                    onchange="this.form.submit()">
                <?php foreach ($machines as $m): ?>
                <option value="<?= $m['id'] ?>" <?= $machineId==$m['id']?'selected':'' ?>>
                    <?= htmlspecialchars($m['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="date_from" class="form-control form-control-sm" style="width:130px;"
                   value="<?= htmlspecialchars($dateFrom) ?>">
            <span class="text-muted small">s/d</span>
            <input type="date" name="date_to" class="form-control form-control-sm" style="width:130px;"
                   value="<?= htmlspecialchars($dateTo) ?>">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button>
        </form>
        <!-- Live controls -->
        <span id="liveDot" style="width:10px;height:10px;border-radius:50%;background:#6c757d;
              display:inline-block;transition:background .3s;flex-shrink:0;"></span>
        <small id="liveLabel" class="text-muted">Tidak aktif</small>
        <button id="btnLive" class="btn btn-sm btn-success" onclick="toggleLive()">
            <i class="fas fa-play mr-1"></i>Live
        </button>
    </div>
</div>

<?php if ($anyAlert): ?>
<div class="alert alert-danger alert-dismissible fade show mb-3" style="border-left:4px solid #e74a3b;">
    <i class="fas fa-exclamation-triangle mr-2"></i>
    <strong>Peringatan!</strong> Terdeteksi vibrasi di atas batas aman ISO 10816.
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
</div>
<?php endif; ?>

<!-- ISO reference -->
<div class="d-flex flex-wrap align-items-center mb-3" style="gap:8px;font-size:.8rem;">
    <span class="badge badge-success px-2 py-1">Normal &lt; 2.8 mm/s</span>
    <span class="badge badge-warning px-2 py-1">Warning 2.8 – 7.1 mm/s</span>
    <span class="badge badge-danger  px-2 py-1">Critical &gt; 7.1 mm/s</span>
    <span class="text-muted ml-1">RMS per sensor fisik</span>
</div>

<!-- ── 4 Sensor Cards ─────────────────────────────────────────── -->
<div class="row mb-4">
<?php foreach ($sensorDef as $sn => $def):
    $r   = $latestSensors[$sn] ?? null;
    $rms = $r ? (float)$r['rms_overall'] : null;
    $st  = $rms !== null ? vStatus($rms) : 'normal';
    $pct = $rms !== null ? min(100, round($rms/10*100,1)) : 0;
    $clr = $st==='critical'?'#e74a3b':($st==='warning'?'#f6c23e':'#1cc88a');
    $ss  = $statsBySensor[$sn];
    $hasData = $r !== null;
?>
<div class="col-xl-3 col-md-6 mb-3">
    <div class="card shadow h-100" id="card-s<?= $sn ?>"
         style="border-left:4px solid <?= $def['color'] ?>;">

        <!-- Card header -->
        <div class="card-header py-2 d-flex align-items-center justify-content-between"
             style="background:#f8f9fc;">
            <div>
                <i class="<?= $def['icon'] ?> mr-1" style="color:<?= $def['color'] ?>;"></i>
                <strong style="color:<?= $def['color'] ?>;">Sensor <?= $sn ?></strong>
                <span class="badge badge-light ml-1" style="font-size:.7rem;border:1px solid <?= $def['color'] ?>;color:<?= $def['color'] ?>;">
                    Axis <?= $def['axis'] ?>
                </span>
                <small class="text-muted ml-1" style="font-size:.68rem;"><?= $def['desc'] ?></small>
            </div>
            <span id="badge-s<?= $sn ?>" class="badge badge-<?= $st==='critical'?'danger':($st==='warning'?'warning':'success') ?>">
                <?= $hasData ? ucfirst($st) : '—' ?>
            </span>
        </div>

        <div class="card-body text-center py-3 px-3">
            <!-- RMS value -->
            <div id="rms-s<?= $sn ?>" class="sensor-rms mb-0"
                 style="color:<?= $hasData ? $clr : '#ccc' ?>;">
                <?= $hasData ? number_format($rms,2) : '—' ?>
            </div>
            <small class="text-muted">mm/s RMS</small>

            <!-- ISO bar -->
            <div class="mt-3 mb-1">
                <div class="iso-track">
                    <div class="iso-marker" id="mrk-s<?= $sn ?>" style="left:<?= $pct ?>%;"></div>
                    <div style="position:absolute;left:28%;top:0;bottom:0;width:2px;background:rgba(0,0,0,.2);"></div>
                    <div style="position:absolute;left:71%;top:0;bottom:0;width:2px;background:rgba(0,0,0,.2);"></div>
                </div>
                <div class="d-flex justify-content-between mt-1" style="font-size:.62rem;color:#adb5bd;">
                    <span>0</span><span>2.8</span><span>7.1</span><span>10+</span>
                </div>
            </div>

            <!-- Sub-axes (X/Y/Z dari sensor ini) -->
            <?php if ($hasData): ?>
            <div class="row no-gutters text-center mt-2 pt-2" style="font-size:.75rem;border-top:1px solid #f0f0f0;">
                <?php
                $subAxes = [
                    'X' => $r['sensor_1'],
                    'Y' => $r['sensor_2'],
                    'Z' => $r['sensor_3'],
                    'B' => $r['axis_b'],
                ];
                foreach ($subAxes as $axLbl => $axVal): ?>
                <div class="col-3">
                    <div class="text-muted" style="font-size:.62rem;"><?= $axLbl ?></div>
                    <div class="font-weight-bold" style="font-size:.78rem;">
                        <?= $axVal !== null ? number_format((float)$axVal,2) : '<span class="text-muted">—</span>' ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="text-muted mt-3" style="font-size:.78rem;">
                <i class="fas fa-satellite-dish mr-1"></i>Menunggu data dari ESP32<br>
                <code style="font-size:.7rem;">"sensor_num": <?= $sn ?></code>
            </div>
            <?php endif; ?>

            <hr class="my-2">
            <small id="ts-s<?= $sn ?>" class="text-muted" style="font-size:.7rem;">
                <?= $hasData ? date('d/m H:i:s', strtotime($r['recorded_at'])) : 'Belum ada data' ?>
            </small>
            <?php if ($hasData && $r['temp_sensor'] !== null): ?>
            <br><small id="tmp-s<?= $sn ?>" class="text-muted" style="font-size:.68rem;">
                <i class="fas fa-thermometer-half"></i> <?= number_format((float)$r['temp_sensor'],1) ?>°C
            </small>
            <?php else: ?>
            <small id="tmp-s<?= $sn ?>"></small>
            <?php endif; ?>
        </div>

        <!-- Stats footer -->
        <div class="card-footer py-1 px-3 d-flex justify-content-between" style="font-size:.7rem;background:#f8f9fc;">
            <span class="text-muted">avg <strong><?= number_format($ss['avg'],2) ?></strong></span>
            <span class="text-muted">max <strong><?= number_format($ss['max'],2) ?></strong></span>
            <span>
                <?php if ($ss['crit']>0): ?><span class="badge badge-danger"   style="font-size:.6rem;"><?= $ss['crit'] ?>×Crit</span><?php endif; ?>
                <?php if ($ss['warn']>0): ?><span class="badge badge-warning"  style="font-size:.6rem;"><?= $ss['warn'] ?>×Warn</span><?php endif; ?>
                <?php if ($ss['crit']==0&&$ss['warn']==0&&$ss['avg']>0): ?><span class="badge badge-success" style="font-size:.6rem;">OK</span><?php endif; ?>
            </span>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>

<!-- ── Trend Chart + Session + Summary ───────────────────────── -->
<div class="row mb-4">

    <!-- Chart -->
    <div class="col-lg-8 mb-3">
        <div class="card shadow">
            <div class="card-header py-2 d-flex align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-chart-line mr-1"></i>Tren RMS 24 Jam
                </h6>
                <div class="d-flex align-items-center" style="gap:6px;">
                    <?php foreach ($sensorDef as $sn => $def): ?>
                    <button class="btn btn-sm axis-toggle active" data-sn="<?= $sn ?>"
                            style="padding:2px 8px;font-size:.72rem;
                                   border:1px solid <?= $def['color'] ?>;
                                   color:<?= $def['color'] ?>;background:transparent;">
                        S<?= $sn ?>/<?= $def['axis'] ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="card-body" style="position:relative;height:300px;">
                <canvas id="vibChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Session + Summary -->
    <div class="col-lg-4 mb-3 d-flex flex-column" style="gap:12px;">

        <!-- Session -->
        <div class="card shadow">
            <div class="card-header py-2">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-record-vinyl mr-1"></i>Session Pengukuran
                </h6>
            </div>
            <div class="card-body py-3">
                <div class="form-row mb-2">
                    <div class="col-5">
                        <label class="small font-weight-bold mb-1">Sensor</label>
                        <select id="sessSensor" class="form-control form-control-sm">
                            <?php foreach ($sensorDef as $sn => $def): ?>
                            <option value="<?= $sn ?>">S<?= $sn ?> – Axis <?= $def['axis'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-7">
                        <label class="small font-weight-bold mb-1">Label Titik Ukur</label>
                        <input type="text" id="sessLabel" class="form-control form-control-sm"
                               placeholder="DE / NDE / Fan...">
                    </div>
                </div>
                <div class="d-flex mb-2" style="gap:6px;">
                    <button class="btn btn-primary btn-sm flex-fill" onclick="startSession()">
                        <i class="fas fa-play mr-1"></i>Start
                    </button>
                    <button class="btn btn-secondary btn-sm flex-fill" onclick="endSession()">
                        <i class="fas fa-stop mr-1"></i>End
                    </button>
                </div>
                <small class="text-muted d-block" id="sessStatus">Belum ada session aktif</small>
                <small class="text-muted" id="sessCount"></small>
            </div>
        </div>

        <!-- Summary -->
        <div class="card shadow flex-fill">
            <div class="card-header py-2">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-clipboard-check mr-1"></i>Ringkasan 24 Jam
                </h6>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0" style="font-size:.8rem;">
                    <thead class="thead-light">
                        <tr><th>Sensor</th><th>Avg</th><th>Max</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($sensorDef as $sn => $def): $ss = $statsBySensor[$sn]; ?>
                    <tr>
                        <td>
                            <strong style="color:<?= $def['color'] ?>;">S<?= $sn ?></strong>
                            <span class="text-muted ml-1" style="font-size:.72rem;">Axis <?= $def['axis'] ?></span>
                        </td>
                        <td><?= number_format($ss['avg'],3) ?></td>
                        <td><?= number_format($ss['max'],3) ?></td>
                        <td><?= vBadge($ss['status']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ── History Table ──────────────────────────────────────────── -->
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-table mr-1"></i>Riwayat Data
            <small class="text-muted font-weight-normal ml-2">
                <?= htmlspecialchars($dateFrom) ?> s/d <?= htmlspecialchars($dateTo) ?>
            </small>
        </h6>
        <small class="text-muted"><?= count($tableData) ?> baris</small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-sm mb-0" id="vibTable" width="100%">
                <thead class="thead-light">
                    <tr>
                        <th>Waktu</th>
                        <th>Sensor</th>
                        <th>Axis</th>
                        <th>RMS (mm/s)</th>
                        <th>X</th><th>Y</th><th>Z</th><th>B</th>
                        <th>Status</th>
                        <th>Label</th>
                        <th>Suhu</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($tableData)): ?>
                    <tr><td colspan="11" class="text-center text-muted py-3">Tidak ada data</td></tr>
                <?php else: foreach ($tableData as $row):
                    $sn2 = (int)$row['sensor_num'];
                    $rs  = $row['status'] ?: vStatus((float)$row['rms_overall']);
                    $rc  = $rs==='critical'?'table-danger':($rs==='warning'?'table-warning':'');
                    $axLabel = $sensorDef[$sn2]['axis'] ?? '?';
                    $axColor = $sensorDef[$sn2]['color'] ?? '#333';
                ?><tr class="<?= $rc ?>">
                    <td style="white-space:nowrap;font-size:.78rem;"><?= date('d/m/Y H:i:s', strtotime($row['recorded_at'])) ?></td>
                    <td><strong style="color:<?= $axColor ?>;">S<?= $sn2 ?></strong></td>
                    <td><span class="badge badge-light" style="border:1px solid <?= $axColor ?>;color:<?= $axColor ?>;font-size:.7rem;">Axis <?= $axLabel ?></span></td>
                    <td><strong><?= $row['rms_overall']!==null ? number_format((float)$row['rms_overall'],3) : '—' ?></strong></td>
                    <td><?= $row['sensor_1']!==null ? number_format((float)$row['sensor_1'],3) : '—' ?></td>
                    <td><?= $row['sensor_2']!==null ? number_format((float)$row['sensor_2'],3) : '—' ?></td>
                    <td><?= $row['sensor_3']!==null ? number_format((float)$row['sensor_3'],3) : '—' ?></td>
                    <td><?= $row['axis_b']  !==null ? number_format((float)$row['axis_b'],3)   : '—' ?></td>
                    <td><?= vBadge($rs) ?></td>
                    <td><small class="text-muted"><?= htmlspecialchars($row['sensor_label'] ?: '—') ?></small></td>
                    <td><?= $row['temp_sensor']!==null ? number_format((float)$row['temp_sensor'],1).'°C' : '—' ?></td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════ -->
<script>
var VIB_MID    = <?= (int)$machineId ?>;
var VIB_WARN_T = <?= VIB_WARN ?>;
var VIB_CRIT_T = <?= VIB_CRIT ?>;
var MAX_PTS    = 120;
var COLORS     = {1:'#4e73df',2:'#1cc88a',3:'#f6c23e',4:'#e74a3b'};
var AXIS_LABEL = {1:'S1/X',2:'S2/Y',3:'S3/Z',4:'S4/B'};

var initChart = <?= json_encode($chartJson, JSON_UNESCAPED_UNICODE) ?>;

var liveActive=false, liveTimer=null, _fetching=false;
var _sessId=null, _sessCount=0;
var _lastIds = <?= json_encode(array_map(fn($r)=>$r['id']??0, $latestSensors)) ?>;
var vibChart = null;

$(document).ready(function(){

    safeDataTable('#vibTable', {order:[[0,'desc']], pageLength:25});

    /* ── Build chart ────────────────────────────────────────── */
    var ctx = document.getElementById('vibChart');
    if (ctx && typeof Chart !== 'undefined') {
        var datasets = [];
        // Find longest label set for shared x-axis
        var baseLabels = [];
        for (var i=1;i<=4;i++) {
            if ((initChart[i]||{labels:[]}).labels.length > baseLabels.length)
                baseLabels = initChart[i].labels;
        }
        for (var i=1;i<=4;i++) {
            var cd = initChart[i] || {labels:[],data:[]};
            datasets.push({
                label: AXIS_LABEL[i], sensorNum: i,
                data: cd.data, borderColor: COLORS[i],
                backgroundColor: 'transparent',
                borderWidth:2, pointRadius:1, fill:false, spanGaps:true
            });
        }
        var n = baseLabels.length;
        datasets.push({label:'Warning (2.8)', data:Array(n).fill(VIB_WARN_T),
            borderColor:'#f6c23e', borderDash:[6,4], borderWidth:1, pointRadius:0, fill:false, spanGaps:true});
        datasets.push({label:'Critical (7.1)', data:Array(n).fill(VIB_CRIT_T),
            borderColor:'#e74a3b', borderDash:[6,4], borderWidth:1, pointRadius:0, fill:false, spanGaps:true});

        vibChart = new Chart(ctx.getContext('2d'), {
            type:'line',
            data:{ labels:baseLabels, datasets:datasets },
            options:{
                responsive:true, maintainAspectRatio:false, animation:{duration:200},
                scales:{
                    xAxes:[{ticks:{maxTicksLimit:10,fontSize:10}, gridLines:{display:false}}],
                    yAxes:[{ticks:{beginAtZero:true,fontSize:10},
                            scaleLabel:{display:true,labelString:'RMS (mm/s)'}}]
                },
                legend:{position:'bottom', labels:{fontSize:10,usePointStyle:true}},
                tooltips:{callbacks:{label:function(item,data){
                    return data.datasets[item.datasetIndex].label+': '+
                           parseFloat(item.yLabel).toFixed(3)+' mm/s';
                }}}
            }
        });
    }

    /* ── Toggle sensor visibility ───────────────────────────── */
    $('.axis-toggle').on('click', function(){
        var sn = parseInt($(this).data('sn'));
        var active = $(this).hasClass('active');
        $(this).toggleClass('active').css('opacity', active ? '.35':'1');
        if (!vibChart) return;
        vibChart.data.datasets.forEach(function(ds){
            if (ds.sensorNum === sn) ds.hidden = active;
        });
        vibChart.update();
    });
});

/* ── Live ────────────────────────────────────────────────────── */
function toggleLive() {
    if (!VIB_MID) return;
    liveActive = !liveActive;
    var btn=document.getElementById('btnLive'),
        dot=document.getElementById('liveDot'),
        lbl=document.getElementById('liveLabel');
    if (liveActive) {
        btn.innerHTML='<i class="fas fa-pause mr-1"></i>Pause';
        btn.className='btn btn-sm btn-warning';
        dot.style.background='#28a745';
        lbl.textContent='Live · 5d';
        liveTimer = setInterval(doLive, 5000);
        doLive();
    } else {
        btn.innerHTML='<i class="fas fa-play mr-1"></i>Live';
        btn.className='btn btn-sm btn-success';
        dot.style.background='#6c757d';
        lbl.textContent='Tidak aktif';
        clearInterval(liveTimer); liveTimer=null;
    }
}

function doLive() {
    if (!VIB_MID || _fetching) return;
    _fetching = true;
    // Ambil latest semua sensor sekaligus (sensor_num=0 = semua)
    fetch('api/vibration_portable.php?action=latest&machine_id='+VIB_MID+'&sensor_num=0', {cache:'no-store'})
        .then(function(r){ return r.ok ? r.json() : null; })
        .then(function(d){
            if (!d || !d.success) return;
            barPulse();
            d.data.forEach(function(row){
                var sn = row.sensor_num;
                updateCard(sn, row);
                if (!_lastIds[sn] || _lastIds[sn] != row.id) {
                    appendChartPoint(sn, row);
                    _lastIds[sn] = row.id;
                    if (_sessId) { _sessCount++; setTxt('sessCount', _sessCount+' pembacaan'); }
                }
            });
        })
        .catch(function(){})
        .finally(function(){ _fetching=false; });
}

function updateCard(sn, row) {
    var rms = parseFloat(row.rms_overall)||0;
    var st  = rms>=VIB_CRIT_T?'critical':rms>=VIB_WARN_T?'warning':'normal';
    var clr = st==='critical'?'#e74a3b':st==='warning'?'#f6c23e':'#1cc88a';

    setTxt('rms-s'+sn, rms>0?rms.toFixed(2):'—');
    setStyle('rms-s'+sn, 'color', clr);
    setMrk('mrk-s'+sn, rms);
    setBadge('badge-s'+sn, st);
    setTxt('ts-s'+sn, row.recorded_at ? row.recorded_at.substr(8,2)+'/'+row.recorded_at.substr(5,2)+' '+row.recorded_at.substr(11,8) : '—');
    if (row.temp_sensor!=null) setTxt('tmp-s'+sn, '🌡 '+parseFloat(row.temp_sensor).toFixed(1)+'°C');
}

function appendChartPoint(sn, row) {
    if (!vibChart) return;
    var t   = (row.recorded_at||'').substr(11,5);
    var rms = parseFloat(row.rms_overall)||0;
    var lb  = vibChart.data.labels;
    var ds  = vibChart.data.datasets;
    if (lb.length >= MAX_PTS) {
        lb.shift();
        ds.forEach(function(d){ if(d.data.length) d.data.shift(); });
    }
    lb.push(t);
    ds.forEach(function(d, i){
        if (d.sensorNum === sn) d.data.push(rms);
        else if (d.sensorNum && d.data.length < lb.length) d.data.push(null);
    });
    // threshold lines
    ds[4].data.push(VIB_WARN_T);
    ds[5].data.push(VIB_CRIT_T);
    vibChart.update();
}

/* ── Session ─────────────────────────────────────────────────── */
function startSession() {
    if (!VIB_MID) { alert('Pilih mesin.'); return; }
    var sn    = parseInt(document.getElementById('sessSensor').value)||1;
    var label = document.getElementById('sessLabel').value.trim();
    fetch('api/vibration_portable.php?action=session_start&machine_id='+VIB_MID+
          '&sensor_num='+sn+'&label='+encodeURIComponent(label))
        .then(function(r){return r.json();})
        .then(function(d){
            if (d.success){
                _sessId=d.session_id; _sessCount=0;
                setTxt('sessStatus','Session #'+d.session_id+' · S'+sn+' Axis '+['','X','Y','Z','B'][sn]+(label?' · '+label:''));
                setTxt('sessCount','0 pembacaan');
            }
        });
}
function endSession() {
    if (!_sessId){ alert('Tidak ada session aktif.'); return; }
    fetch('api/vibration_portable.php?action=session_end&session_id='+_sessId)
        .then(function(r){return r.json();})
        .then(function(d){
            if (d.success){
                var s=d.summary||{};
                setTxt('sessStatus','Session #'+_sessId+' selesai');
                setTxt('sessCount',(s.cnt||0)+' total | Avg: '+parseFloat(s.avg_rms||0).toFixed(3)+' | Max: '+parseFloat(s.max_rms||0).toFixed(3)+' mm/s');
                _sessId=null;
            }
        });
}

/* ── Helpers ─────────────────────────────────────────────────── */
function setTxt(id,v)    { var e=document.getElementById(id); if(e) e.textContent=v??''; }
function setStyle(id,p,v){ var e=document.getElementById(id); if(e) e.style[p]=v; }
function setMrk(id,v)    { var e=document.getElementById(id); if(e) e.style.left=Math.min(parseFloat(v)||0,10)/10*100+'%'; }
function setBadge(id,st) {
    var e=document.getElementById(id); if(!e) return;
    e.textContent=st.charAt(0).toUpperCase()+st.slice(1);
    e.className='badge badge-'+(st==='critical'?'danger':st==='warning'?'warning':'success');
}
function barPulse() {
    var b=document.getElementById('liveTopBar');
    if(b){ b.style.width='70%'; setTimeout(function(){b.style.width='0%';},700); }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
