<?php
// ============================================================
//  machine_detail.php  –  Full machine detail page
// ============================================================
require_once 'includes/auth_check.php';

$db = getDB();

// ── 1. Validate ?id param ────────────────────────────────────
$machineId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($machineId <= 0) {
    header('Location: machines.php');
    exit;
}

// ── 2. Fetch machine + production line ───────────────────────
$stmtM = $db->prepare("
    SELECT m.*, pl.name AS line_name
    FROM machines m
    LEFT JOIN production_lines pl ON pl.id = m.line_id
    WHERE m.id = ?
    LIMIT 1
");
$stmtM->execute([$machineId]);
$machine = $stmtM->fetch();

if (!$machine) {
    header('Location: machines.php');
    exit;
}

// ── 3. Latest sensor reading ─────────────────────────────────
$stmtS = $db->prepare("
    SELECT * FROM sensor_readings
    WHERE machine_id = ?
    ORDER BY recorded_at DESC
    LIMIT 1
");
$stmtS->execute([$machineId]);
$latestSensor = $stmtS->fetch();

// ── 4. Second-latest sensor reading (for trend arrows) ───────
$stmtS2 = $db->prepare("
    SELECT * FROM sensor_readings
    WHERE machine_id = ?
    ORDER BY recorded_at DESC
    LIMIT 1 OFFSET 1
");
$stmtS2->execute([$machineId]);
$prevSensor = $stmtS2->fetch();

// ── 5. Sensor thresholds ──────────────────────────────────────
$stmtTh = $db->prepare("
    SELECT * FROM sensor_thresholds
    WHERE machine_id = ? OR machine_id IS NULL
    ORDER BY machine_id DESC
");
$stmtTh->execute([$machineId]);
$thresholds = [];
foreach ($stmtTh->fetchAll() as $row) {
    $key = strtolower($row['sensor_key'] ?? $row['sensor_key'] ?? '');
    if ($key && !isset($thresholds[$key])) {
        $thresholds[$key] = $row;
    }
}

// ── 6. Latest OEE daily record ────────────────────────────────
$stmtOEE = $db->prepare("
    SELECT * FROM oee_daily
    WHERE machine_id = ?
    ORDER BY snap_date DESC, id DESC
    LIMIT 1
");
$stmtOEE->execute([$machineId]);
$oeeLatest = $stmtOEE->fetch();

// ── 7. OEE history last 30 days ───────────────────────────────
$stmtOEEHist = $db->prepare("
    SELECT snap_date,
           availability, performance, quality,
           ROUND(availability * performance * quality / 10000, 1) AS oee_pct
    FROM oee_daily
    WHERE machine_id = ?
    ORDER BY snap_date DESC
    LIMIT 30
");
$stmtOEEHist->execute([$machineId]);
$oeeHistory = array_reverse($stmtOEEHist->fetchAll());

// ── 8. 24h sensor readings (last 288 rows) ────────────────────
$stmtH = $db->prepare("
    SELECT recorded_at, v_r, v_s, v_t,
           a_r, a_s, a_t,
           f_r, f_s, f_t,
           e_r, e_s, e_t,
           temp_panel, hum_panel, source
    FROM sensor_readings
    WHERE machine_id = ?
    ORDER BY recorded_at DESC
    LIMIT 288
");
$stmtH->execute([$machineId]);
$sensorHistory24h = array_reverse($stmtH->fetchAll());

// ── 9. Recent 50 sensor readings for DataTable ────────────────
$stmtR = $db->prepare("
    SELECT recorded_at, v_r, v_s, v_t,
           a_r, a_s, a_t,
           f_r, f_s, f_t,
           e_r, e_s, e_t,
           temp_panel, hum_panel, source
    FROM sensor_readings
    WHERE machine_id = ?
    ORDER BY recorded_at DESC
    LIMIT 50
");
$stmtR->execute([$machineId]);
$recentReadings = $stmtR->fetchAll();

// ── 10. Recent 10 alerts ──────────────────────────────────────
$stmtAl = $db->prepare("
    SELECT created_at, sensor_key, sensor_value, severity, acknowledged
    FROM alerts
    WHERE machine_id = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$stmtAl->execute([$machineId]);
$recentAlerts = $stmtAl->fetchAll();

// ── Helper: threshold status ──────────────────────────────────
function sensorStatus(string $key, $value, array $thresholds): string {
    if ($value === null || $value === '') return 'secondary';
    $th = $thresholds[$key] ?? null;
    if (!$th) return 'secondary';
    $val = (float)$value;
    $critHi = isset($th['critical_high']) && $th['critical_high'] !== null ? (float)$th['critical_high'] : null;
    $warnHi = isset($th['warning_high'])  && $th['warning_high']  !== null ? (float)$th['warning_high']  : null;
    $critLo = isset($th['critical_low'])  && $th['critical_low']  !== null ? (float)$th['critical_low']  : null;
    $warnLo = isset($th['warning_low'])   && $th['warning_low']   !== null ? (float)$th['warning_low']   : null;
    if (($critHi !== null && $val >= $critHi) || ($critLo !== null && $val <= $critLo)) return 'danger';
    if (($warnHi !== null && $val >= $warnHi) || ($warnLo !== null && $val <= $warnLo)) return 'warning';
    return 'success';
}

function trendArrow($cur, $prev): string {
    if ($cur === null || $prev === null) return '';
    if ((float)$cur > (float)$prev) return '<span class="text-danger">&#8593;</span>';
    if ((float)$cur < (float)$prev) return '<span class="text-success">&#8595;</span>';
    return '<span class="text-secondary">&#8594;</span>';
}

function fmtVal($v, int $dec = 1): string {
    return ($v !== null && $v !== '') ? number_format((float)$v, $dec) : '-';
}

// ── OEE values ────────────────────────────────────────────────
$avail = $oeeLatest ? (float)($oeeLatest['availability'] ?? 0) : 0;
$perf  = $oeeLatest ? (float)($oeeLatest['performance']  ?? 0) : 0;
$qual  = $oeeLatest ? (float)($oeeLatest['quality']      ?? 0) : 0;
$oee   = ($avail > 0 || $perf > 0 || $qual > 0) ? round($avail * $perf * $qual / 10000, 1) : 0;

// ── Chart data as JSON ────────────────────────────────────────
$chartLabels  = [];
$chartVR = []; $chartVS = []; $chartVT = [];
$chartAR = []; $chartAS = []; $chartAT = [];
$chartFR = [];
$chartEnergy = [];
$chartTemp = []; $chartHum = [];

foreach ($sensorHistory24h as $r) {
    $chartLabels[] = date('H:i', strtotime($r['recorded_at']));
    $chartVR[]     = $r['v_r']       !== null ? (float)$r['v_r']       : null;
    $chartVS[]     = $r['v_s']       !== null ? (float)$r['v_s']       : null;
    $chartVT[]     = $r['v_t']       !== null ? (float)$r['v_t']       : null;
    $chartAR[]     = $r['a_r']       !== null ? (float)$r['a_r']       : null;
    $chartAS[]     = $r['a_s']       !== null ? (float)$r['a_s']       : null;
    $chartAT[]     = $r['a_t']       !== null ? (float)$r['a_t']       : null;
    $chartFR[]     = $r['f_r']       !== null ? (float)$r['f_r']       : null;
    $chartEnergy[] = ($r['e_r']+$r['e_s']+$r['e_t']) !== null ? (float)($r['e_r']+$r['e_s']+$r['e_t']): null;
    $chartTemp[]   = $r['temp_panel'] !== null ? (float)$r['temp_panel']: null;
    $chartHum[]    = $r['hum_panel']   !== null ? (float)$r['hum_panel']  : null;
}

$jsonLabels  = json_encode($chartLabels);
$jsonVR      = json_encode($chartVR);
$jsonVS      = json_encode($chartVS);
$jsonVT      = json_encode($chartVT);
$jsonAR      = json_encode($chartAR);
$jsonAS      = json_encode($chartAS);
$jsonAT      = json_encode($chartAT);
$jsonFR      = json_encode($chartFR);
$jsonEnergy  = json_encode($chartEnergy);
$jsonTemp    = json_encode($chartTemp);
$jsonHum     = json_encode($chartHum);

// OEE history chart data
$oeeChartLabels = json_encode(array_column($oeeHistory, 'snap_date'));
$oeeChartData   = json_encode(array_column($oeeHistory, 'oee_pct'));
$oeeAvailData   = json_encode(array_column($oeeHistory, 'availability'));
$oeePerfData    = json_encode(array_column($oeeHistory, 'performance'));
$oeeQualData    = json_encode(array_column($oeeHistory, 'quality'));

// ── Page meta ─────────────────────────────────────────────────
$currentPage = 'machine_detail';
$pageTitle   = 'Detail: ' . $machine['name'];

// ── Source badge ──────────────────────────────────────────────
$dataSource = $latestSensor['source'] ?? 'N/A';
$srcBadge = 'secondary';
if (stripos($dataSource, 'esp') !== false)                         $srcBadge = 'info';
elseif (stripos($dataSource, 'manual') !== false)                  $srcBadge = 'warning';
elseif (stripos($dataSource, 'mqtt') !== false
     || stripos($dataSource, 'live') !== false)                    $srcBadge = 'success';

include 'includes/header.php';
?>

<!-- ════════════════════════════════════════════════════════════
     PAGE HEADING
════════════════════════════════════════════════════════════ -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-microscope mr-2"></i><?= htmlspecialchars($machine['name']) ?>
        </h1>
        <div class="mt-1">
            <small class="text-muted">
                Model: <strong><?= htmlspecialchars($machine['model'] ?: '-') ?></strong>
                &nbsp;|&nbsp;
                Line: <strong><?= htmlspecialchars($machine['line_name'] ?: '-') ?></strong>
            </small>
            &nbsp;
            <span class="badge badge-<?= strtolower($machine['status']) === 'run' ? 'success' : 'danger' ?> ml-1"
                  data-machine-status>
                <?= strtoupper($machine['status']) ?>
            </span>
            <span class="badge badge-<?= $srcBadge ?> ml-1">
                <i class="fas fa-wifi fa-xs mr-1"></i><?= htmlspecialchars($dataSource) ?>
            </span>
            <?php if ($latestSensor): ?>
                <small class="text-muted ml-2">
                    <i class="fas fa-clock fa-xs mr-1"></i>
                    Last reading: <span id="lastReadingTs"><?= date('d/m/Y H:i:s', strtotime($latestSensor['recorded_at'])) ?></span>
                </small>
                <span id="mqttLiveDot" class="ml-2" title="Status MQTT"
                      style="display:inline-block;width:9px;height:9px;border-radius:50%;background:#ccc;vertical-align:middle;"></span>
                <small id="mqttLiveLabel" class="text-muted" style="font-size:.7rem;">checking...</small>
            <?php endif; ?>
        </div>
    </div>
    <div class="mt-2 mt-sm-0">
        <a href="machines.php" class="btn btn-secondary btn-sm mr-2">
            <i class="fas fa-arrow-left fa-sm mr-1"></i>Back
        </a>
        <button class="btn btn-success btn-sm"
            onclick="window.location='api/reports.php?action=oee_summary&machine_id=<?= $machineId ?>&export=csv'">
            <i class="fas fa-file-csv fa-sm mr-1"></i>Export Data
        </button>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════
     LIVE SENSOR CARDS
════════════════════════════════════════════════════════════ -->
<div class="card shadow mb-4">
    <div class="card-header py-2 d-flex align-items-center justify-content-between">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-bolt mr-2"></i>Sensor Live
        </h6>
        <small class="text-muted" id="lastRefreshTime">
            <?= $latestSensor ? 'Update: ' . date('H:i:s', strtotime($latestSensor['recorded_at'])) : 'No data' ?>
        </small>
    </div>
    <div class="card-body py-2">
        <div class="d-flex flex-wrap" id="sensorCardsRow">
<?php
$sensorDefs = [
    ['key' => 'v_r',        'label' => 'V R',        'unit' => 'V',  'th' => 'v_r',        'dec' => 1],
    ['key' => 'v_s',        'label' => 'V S',        'unit' => 'V',  'th' => 'v_s',        'dec' => 1],
    ['key' => 'v_t',        'label' => 'V T',        'unit' => 'V',  'th' => 'v_t',        'dec' => 1],
    ['key' => 'a_r',        'label' => 'A R',        'unit' => 'A',  'th' => 'a_r',        'dec' => 2],
    ['key' => 'a_s',        'label' => 'A S',        'unit' => 'A',  'th' => 'a_s',        'dec' => 2],
    ['key' => 'a_t',        'label' => 'A T',        'unit' => 'A',  'th' => 'a_t',        'dec' => 2],
    ['key' => 'f_r','label' => 'F R',        'unit' => 'Hz', 'th' => 'f_r','dec' => 1],
    ['key' => 'temp_panel', 'label' => 'Temp Panel', 'unit' => '°C', 'th' => 'temp_panel', 'dec' => 1],
    ['key' => 'hum_panel',   'label' => 'Humidity',   'unit' => '%',  'th' => 'hum_panel',   'dec' => 1],
];
$bgMap = ['success' => '#d4edda', 'warning' => '#fff3cd', 'danger' => '#f8d7da', 'secondary' => '#f8f9fa'];

foreach ($sensorDefs as $sd):
    $curVal   = $latestSensor[$sd['key']] ?? null;
    $prevVal  = $prevSensor[$sd['key']]   ?? null;
    $status   = sensorStatus($sd['th'], $curVal, $thresholds);
    $trend    = trendArrow($curVal, $prevVal);
    $displayed= fmtVal($curVal, $sd['dec']);
    $bg       = $bgMap[$status] ?? '#f8f9fa';
?>
            <div class="mr-2 mb-2" style="min-width:90px;max-width:115px;">
                <div class="border rounded p-2 text-center" style="background:<?= $bg ?>;" id="sc_<?= $sd['key'] ?>">
                    <div class="text-muted" style="font-size:0.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.4px;"><?= $sd['label'] ?></div>
                    <div style="font-size:1.35rem;font-weight:700;line-height:1.1;" class="text-gray-800 my-1" id="sv_<?= $sd['key'] ?>"><?= $displayed ?></div>
                    <div style="font-size:0.65rem;" class="text-muted"><?= $sd['unit'] ?> <?= $trend ?></div>
                    <span class="badge badge-<?= $status ?> mt-1" style="font-size:0.5rem;" id="sb_<?= $sd['key'] ?>"><?= ucfirst($status) ?></span>
                </div>
            </div>
<?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════
     OEE GAUGES
════════════════════════════════════════════════════════════ -->
<div class="row mb-4">
    <!-- Overall OEE -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card shadow h-100 border-left-primary">
            <div class="card-body text-center py-4">
                <div class="text-xs font-weight-bold text-primary text-uppercase mb-2">OEE Total</div>
                <?php $oeeColor = $oee >= 85 ? '#1cc88a' : ($oee >= 65 ? '#f6c23e' : '#e74a3b'); ?>
                <div style="position:relative;width:130px;height:130px;margin:0 auto 10px;">
                    <svg viewBox="0 0 36 36" style="width:130px;height:130px;transform:rotate(-90deg);">
                        <circle cx="18" cy="18" r="15.9" fill="none" stroke="#eee" stroke-width="3"/>
                        <circle cx="18" cy="18" r="15.9" fill="none" stroke="<?= $oeeColor ?>" stroke-width="3"
                            stroke-dasharray="<?= min($oee,100) ?> <?= 100-min($oee,100) ?>"
                            stroke-linecap="round"/>
                    </svg>
                    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;">
                        <div style="font-size:1.6rem;font-weight:700;color:<?= $oeeColor ?>;"><?= $oee ?>%</div>
                        <div style="font-size:0.6rem;color:#888;">OEE</div>
                    </div>
                </div>
                <?php if ($oeeLatest): ?>
                    <small class="text-muted"><?= htmlspecialchars($oeeLatest['snap_date'] ?? '') ?></small>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php foreach ([
        ['label' => 'Availability', 'val' => $avail, 'color' => '#4e73df'],
        ['label' => 'Performance',  'val' => $perf,  'color' => '#1cc88a'],
        ['label' => 'Quality',      'val' => $qual,  'color' => '#f6c23e'],
    ] as $g):
        $gv = min((float)$g['val'], 100);
    ?>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card shadow h-100">
            <div class="card-body text-center py-4">
                <div class="text-xs font-weight-bold text-uppercase mb-2" style="color:<?= $g['color'] ?>;"><?= $g['label'] ?></div>
                <div style="position:relative;width:110px;height:110px;margin:0 auto 10px;">
                    <svg viewBox="0 0 36 36" style="width:110px;height:110px;transform:rotate(-90deg);">
                        <circle cx="18" cy="18" r="15.9" fill="none" stroke="#eee" stroke-width="3"/>
                        <circle cx="18" cy="18" r="15.9" fill="none" stroke="<?= $g['color'] ?>" stroke-width="3"
                            stroke-dasharray="<?= $gv ?> <?= 100-$gv ?>"
                            stroke-linecap="round"/>
                    </svg>
                    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;">
                        <div style="font-size:1.3rem;font-weight:700;color:<?= $g['color'] ?>;"><?= $g['val'] ?>%</div>
                    </div>
                </div>
                <div class="progress" style="height:6px;">
                    <div class="progress-bar" role="progressbar"
                        style="width:<?= $gv ?>%;background:<?= $g['color'] ?>;"></div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ════════════════════════════════════════════════════════════
     CHART TABS (24h sensor data)
════════════════════════════════════════════════════════════ -->
<div class="card shadow mb-4">
    <div class="card-header py-2">
        <ul class="nav nav-tabs card-header-tabs" id="chartTabs" role="tablist">
            <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#chart-volt"   role="tab">Voltage</a></li>
            <li class="nav-item"><a class="nav-link"        data-toggle="tab" href="#chart-curr"   role="tab">Current</a></li>
            <li class="nav-item"><a class="nav-link"        data-toggle="tab" href="#chart-freq"   role="tab">Frequency</a></li>
            <li class="nav-item"><a class="nav-link"        data-toggle="tab" href="#chart-energy" role="tab">Energy</a></li>
            <li class="nav-item"><a class="nav-link"        data-toggle="tab" href="#chart-temp"   role="tab">Temperature</a></li>
        </ul>
    </div>
    <div class="card-body">
        <div class="tab-content" id="chartTabContent">
            <div class="tab-pane fade show active" id="chart-volt"   role="tabpanel"><canvas id="chartVoltage"   height="80"></canvas></div>
            <div class="tab-pane fade"             id="chart-curr"   role="tabpanel"><canvas id="chartCurrent"   height="80"></canvas></div>
            <div class="tab-pane fade"             id="chart-freq"   role="tabpanel"><canvas id="chartFrequency" height="80"></canvas></div>
            <div class="tab-pane fade"             id="chart-energy" role="tabpanel"><canvas id="chartEnergy"    height="80"></canvas></div>
            <div class="tab-pane fade"             id="chart-temp"   role="tabpanel"><canvas id="chartTemp"      height="80"></canvas></div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════
     OEE HISTORY BAR CHART (last 30 days)
════════════════════════════════════════════════════════════ -->
<div class="card shadow mb-4">
    <div class="card-header py-2">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-chart-bar mr-2"></i>OEE History – Last 30 Days
        </h6>
    </div>
    <div class="card-body">
        <canvas id="chartOEEHistory" height="60"></canvas>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════
     SENSOR HISTORY DATATABLE (last 50)
════════════════════════════════════════════════════════════ -->
<div class="card shadow mb-4">
    <div class="card-header py-2">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-table mr-2"></i>Sensor History (Last 50)
        </h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-sm" id="sensorHistoryTable" width="100%">
                <thead class="thead-light">
                    <tr>
                        <th>Time</th>
                        <th>V_R (V)</th><th>V_S (V)</th><th>V_T (V)</th>
                        <th>A_R (A)</th><th>A_S (A)</th><th>A_T (A)</th>
                        <th>Temp (°C)</th><th>Hum (%)</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentReadings as $row): ?>
                    <tr>
                        <td style="white-space:nowrap;"><?= date('d/m/Y H:i:s', strtotime($row['recorded_at'])) ?></td>
                        <td><?= fmtVal($row['v_r']) ?></td>
                        <td><?= fmtVal($row['v_s']) ?></td>
                        <td><?= fmtVal($row['v_t']) ?></td>
                        <td><?= fmtVal($row['a_r'], 2) ?></td>
                        <td><?= fmtVal($row['a_s'], 2) ?></td>
                        <td><?= fmtVal($row['a_t'], 2) ?></td>
                        <td><?= fmtVal($row['temp_panel']) ?></td>
                        <td><?= fmtVal($row['hum_panel']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════
     ALERTS DATATABLE (last 10)
════════════════════════════════════════════════════════════ -->
<div class="card shadow mb-4">
    <div class="card-header py-2">
        <h6 class="m-0 font-weight-bold text-danger">
            <i class="fas fa-bell mr-2"></i>Recent Alerts
        </h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-sm" id="alertsTable" width="100%">
                <thead class="thead-light">
                    <tr>
                        <th>Time</th>
                        <th>Sensor</th>
                        <th>Value</th>
                        <th>Severity</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($recentAlerts)): ?>
                    <tr><td colspan="5" class="text-center text-muted">No alerts</td></tr>
                <?php else: ?>
                <?php foreach ($recentAlerts as $al):
                    $sevBadge = 'secondary';
                    $sev = strtolower($al['severity'] ?? '');
                    if ($sev === 'critical')     $sevBadge = 'danger';
                    elseif ($sev === 'warning')  $sevBadge = 'warning';
                    elseif ($sev === 'info')     $sevBadge = 'info';
                    $ackStatus = $al['acknowledged']
                        ? '<span class="badge badge-success">Acknowledged</span>'
                        : '<span class="badge badge-danger">Unread</span>';
                ?>
                    <tr>
                        <td style="white-space:nowrap;"><?= date('d/m/Y H:i:s', strtotime($al['created_at'])) ?></td>
                        <td><?= htmlspecialchars($al['sensor_key'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($al['sensor_value'] ?? '-') ?></td>
                        <td><span class="badge badge-<?= $sevBadge ?>"><?= htmlspecialchars(ucfirst($al['severity'] ?? '-')) ?></span></td>
                        <td><?= $ackStatus ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Live indicator bar -->
<div style="position:fixed;top:0;left:0;right:0;height:3px;z-index:10000;">
    <div id="liveBar" style="height:3px;width:0%;background:linear-gradient(90deg,#1cc88a,#4e73df);transition:width .4s ease;"></div>
</div>
<!-- Toast container -->
<div aria-live="polite" aria-atomic="true"
     style="position:fixed;bottom:24px;right:24px;z-index:9999;min-width:280px;">
    <div id="toastContainer"></div>
</div>

<?php include 'includes/footer.php'; ?>

<!-- ════════════════════════════════════════════════════════════
     PAGE SCRIPTS  (after footer vendor scripts)
════════════════════════════════════════════════════════════ -->
<script>
$(document).ready(function () {

    var MACHINE_ID    = <?= $machineId ?>;
    var LIVE_INTERVAL = 5000;    // ms — poll interval saat MQTT aktif
    var MAX_POINTS    = 288;     // titik maksimum di chart rolling

    var thresholdJS = <?= json_encode($thresholds) ?>;
    var lastTS      = <?= json_encode($latestSensor['recorded_at'] ?? '') ?>;
    var isFetching  = false;

    // ── Progress bar helper ───────────────────────────────────
    function barPulse() {
        $('#liveBar').css('width','60%');
        setTimeout(function(){ $('#liveBar').css('width','0%'); }, 600);
    }

    // ── Toast helper ──────────────────────────────────────────
    function showToast(msg, type) {
        type = type || 'info';
        var colors = { danger:'#e74a3b', warning:'#f6c23e', info:'#4e73df', success:'#1cc88a' };
        var bg = colors[type] || '#333';
        var id = 'toast_' + Date.now();
        var html = '<div id="'+id+'" style="background:'+bg+';color:#fff;border-radius:6px;'
            + 'padding:10px 16px;margin-bottom:8px;font-size:.85rem;box-shadow:0 4px 12px rgba(0,0,0,.2);'
            + 'opacity:0;transition:opacity .3s;">'
            + msg + '</div>';
        $('#toastContainer').append(html);
        setTimeout(function(){ $('#'+id).css('opacity',1); }, 50);
        setTimeout(function(){ $('#'+id).css('opacity',0); setTimeout(function(){ $('#'+id).remove(); }, 400); }, 5000);
    }

    // ── Threshold calculator ──────────────────────────────────
    var badgeLabel = { success:'Normal', warning:'Warning', danger:'Critical', secondary:'N/A' };
    var cardBg     = { success:'#d4edda', warning:'#fff3cd', danger:'#f8d7da', secondary:'#f8f9fa' };
    var sensorKeys = ['v_r','v_s','v_t','a_r','a_s','a_t','f_r','temp_panel','hum_panel'];

    function calcStatus(key, val) {
        var th = thresholdJS[key];
        if (!th || val === null || val === undefined || val === '') return 'secondary';
        val = parseFloat(val);
        var cH = th.critical_high != null ? parseFloat(th.critical_high) : null;
        var wH = th.warning_high  != null ? parseFloat(th.warning_high)  : null;
        var cL = th.critical_low  != null ? parseFloat(th.critical_low)  : null;
        var wL = th.warning_low   != null ? parseFloat(th.warning_low)   : null;
        if ((cH !== null && val >= cH) || (cL !== null && val <= cL)) return 'danger';
        if ((wH !== null && val >= wH) || (wL !== null && val <= wL)) return 'warning';
        return 'success';
    }

    function decFor(k) { return (k==='a_r'||k==='a_s'||k==='a_t') ? 2 : 1; }

    // ── Update sensor cards ───────────────────────────────────
    function updateSensorCards(d) {
        if (!d) return;
        sensorKeys.forEach(function(k) {
            var raw  = (d[k] !== undefined && d[k] !== null) ? d[k] : null;
            var disp = (raw !== null && raw !== '') ? parseFloat(raw).toFixed(decFor(k)) : '-';
            var st   = calcStatus(k, raw);
            $('#sv_'+k).text(disp);
            $('#sb_'+k).removeClass('badge-success badge-warning badge-danger badge-secondary')
                       .addClass('badge-'+st).text(badgeLabel[st]);
            $('#sc_'+k).css('background', cardBg[st]);
        });
        if (d.recorded_at) {
            var ts = d.recorded_at.substr(11,8);
            $('#lastRefreshTime').text('Update: ' + ts);
        }
    }

    // ─── Charts ───────────────────────────────────────────────
    var baseOpts = {
        responsive: true, spanGaps: true,
        animation: { duration: 300 },
        legend: { position: 'bottom', labels: { boxWidth: 12, fontSize: 11 } },
        scales: {
            xAxes: [{ ticks: { maxTicksLimit: 12, maxRotation: 0, fontSize: 10 }, gridLines: { display: false } }],
            yAxes: [{ ticks: { fontSize: 10 }, gridLines: { color: 'rgba(0,0,0,.05)' } }]
        },
        tooltips: { mode: 'index', intersect: false }
    };
    function mo(extra) { return $.extend(true, {}, baseOpts, extra || {}); }

    var lbl = <?= $jsonLabels ?>;

    var chartVolt = new Chart(document.getElementById('chartVoltage').getContext('2d'), {
        type: 'line',
        data: { labels: lbl, datasets: [
            { label:'V_R', data:<?= $jsonVR ?>, borderColor:'#e74a3b', borderWidth:1.5, pointRadius:0, fill:false },
            { label:'V_S', data:<?= $jsonVS ?>, borderColor:'#4e73df', borderWidth:1.5, pointRadius:0, fill:false },
            { label:'V_T', data:<?= $jsonVT ?>, borderColor:'#1cc88a', borderWidth:1.5, pointRadius:0, fill:false }
        ]},
        options: mo({ scales:{ yAxes:[{ ticks:{ callback:function(v){return v+' V';} } }] } })
    });

    var chartCurr = new Chart(document.getElementById('chartCurrent').getContext('2d'), {
        type: 'line',
        data: { labels: lbl, datasets: [
            { label:'A_R', data:<?= $jsonAR ?>, borderColor:'#e74a3b', borderWidth:1.5, pointRadius:0, fill:false },
            { label:'A_S', data:<?= $jsonAS ?>, borderColor:'#4e73df', borderWidth:1.5, pointRadius:0, fill:false },
            { label:'A_T', data:<?= $jsonAT ?>, borderColor:'#1cc88a', borderWidth:1.5, pointRadius:0, fill:false }
        ]},
        options: mo({ scales:{ yAxes:[{ ticks:{ callback:function(v){return v+' A';} } }] } })
    });

    var chartFreq = new Chart(document.getElementById('chartFrequency').getContext('2d'), {
        type: 'line',
        data: { labels: lbl, datasets: [
            { label:'F_R', data:<?= $jsonFR ?>, borderColor:'#f6c23e', backgroundColor:'rgba(246,194,62,.08)', borderWidth:1.5, pointRadius:0, fill:true }
        ]},
        options: mo({ scales:{ yAxes:[{ ticks:{ callback:function(v){return v+' Hz';} } }] } })
    });

    var chartEnrg = new Chart(document.getElementById('chartEnergy').getContext('2d'), {
        type: 'line',
        data: { labels: lbl, datasets: [
            { label:'Energy (kWh)', data:<?= $jsonEnergy ?>, borderColor:'#36b9cc', backgroundColor:'rgba(54,185,204,.08)', borderWidth:1.5, pointRadius:0, fill:true }
        ]},
        options: mo({ scales:{ yAxes:[{ ticks:{ callback:function(v){return v+' kWh';} } }] } })
    });

    var chartTmp = new Chart(document.getElementById('chartTemp').getContext('2d'), {
        type: 'line',
        data: { labels: lbl, datasets: [
            { label:'Temp Panel (°C)', data:<?= $jsonTemp ?>, borderColor:'#e74a3b', backgroundColor:'rgba(231,74,59,.08)', borderWidth:1.5, pointRadius:0, fill:true },
            { label:'Humidity (%)',    data:<?= $jsonHum  ?>, borderColor:'#4e73df', borderWidth:1.5, pointRadius:0, fill:false }
        ]},
        options: mo()
    });

    new Chart(document.getElementById('chartOEEHistory').getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?= $oeeChartLabels ?>,
            datasets: [
                { label:'OEE %',          data:<?= $oeeChartData ?>, backgroundColor:'rgba(78,115,223,.75)',  borderColor:'#4e73df', borderWidth:1 },
                { label:'Availability %', data:<?= $oeeAvailData ?>, backgroundColor:'rgba(28,200,138,.55)',  borderColor:'#1cc88a', borderWidth:1 },
                { label:'Performance %',  data:<?= $oeePerfData  ?>, backgroundColor:'rgba(246,194,62,.55)', borderColor:'#f6c23e', borderWidth:1 },
                { label:'Quality %',      data:<?= $oeeQualData  ?>, backgroundColor:'rgba(231,74,59,.55)',  borderColor:'#e74a3b', borderWidth:1 }
            ]
        },
        options: {
            responsive: true,
            scales: {
                xAxes:[{ ticks:{ maxTicksLimit:15, maxRotation:45, fontSize:9 } }],
                yAxes:[{ ticks:{ min:0, max:100, callback:function(v){return v+'%';} } }]
            },
            legend: { position:'bottom', labels:{ boxWidth:12, fontSize:11 } },
            tooltips: { mode:'index', intersect:false }
        }
    });

    // ── Append satu data point ke semua chart (rolling window) ─
    function appendToCharts(row) {
        var t  = row.recorded_at ? row.recorded_at.substr(11,5) : '';
        var e  = ((parseFloat(row.e_r)||0) + (parseFloat(row.e_s)||0) + (parseFloat(row.e_t)||0));

        var charts = [chartVolt, chartCurr, chartFreq, chartEnrg, chartTmp];
        charts.forEach(function(c) {
            if (c.data.labels.length >= MAX_POINTS) c.data.labels.shift();
            c.data.labels.push(t);
        });

        function pushShift(c, dsIdx, val) {
            if (c.data.datasets[dsIdx].data.length >= MAX_POINTS) c.data.datasets[dsIdx].data.shift();
            c.data.datasets[dsIdx].data.push(val !== null && val !== '' ? parseFloat(val) : null);
        }

        // Voltage
        pushShift(chartVolt, 0, row.v_r); pushShift(chartVolt, 1, row.v_s); pushShift(chartVolt, 2, row.v_t);
        // Current
        pushShift(chartCurr, 0, row.a_r); pushShift(chartCurr, 1, row.a_s); pushShift(chartCurr, 2, row.a_t);
        // Frequency
        pushShift(chartFreq, 0, row.f_r);
        // Energy
        pushShift(chartEnrg, 0, e > 0 ? e : null);
        // Temp / Hum
        pushShift(chartTmp, 0, row.temp_panel); pushShift(chartTmp, 1, row.hum_panel);

        charts.forEach(function(c){ c.update(); });
    }

    // ── DataTables ────────────────────────────────────────────
    var dtSensor = safeDataTable('#sensorHistoryTable', { order:[[0,'desc']], pageLength:25 });
    var dtAlerts = safeDataTable('#alertsTable',        { order:[[0,'desc']], pageLength:10  });

    function prependSensorRow(row) {
        if (!dtSensor) return;
        function f(v, d){ return (v !== null && v !== '') ? parseFloat(v).toFixed(d||1) : '-'; }
        var dt = row.recorded_at || '';
        var disp = dt ? dt.substr(8,2)+'/'+dt.substr(5,2)+'/'+dt.substr(0,4)+' '+dt.substr(11,8) : '-';
        dtSensor.row.add([
            '<span style="white-space:nowrap;">'+disp+'</span>',
            f(row.v_r), f(row.v_s), f(row.v_t),
            f(row.a_r,2), f(row.a_s,2), f(row.a_t,2),
            f(row.temp_panel), f(row.hum_panel)
        ]).draw(false);
    }

    function prependAlertRow(al) {
        if (!dtAlerts) return;
        var sev   = (al.severity||'').toLowerCase();
        var bCls  = sev==='critical'?'danger': sev==='warning'?'warning': sev==='info'?'info':'secondary';
        var ackHtml = al.acknowledged
            ? '<span class="badge badge-success">Acknowledged</span>'
            : '<span class="badge badge-danger">Unread</span>';
        var dt = al.created_at||'';
        var disp = dt ? dt.substr(8,2)+'/'+dt.substr(5,2)+'/'+dt.substr(0,4)+' '+dt.substr(11,8) : '-';
        dtAlerts.row.add([
            '<span style="white-space:nowrap;">'+disp+'</span>',
            al.sensor_key||'-',
            al.sensor_value||'-',
            '<span class="badge badge-'+bCls+'">'+ucfirst(al.severity||'-')+'</span>',
            ackHtml
        ]).draw(false);
    }

    function ucfirst(s){ return s ? s.charAt(0).toUpperCase()+s.slice(1) : s; }

    // ── Main live poll ────────────────────────────────────────
    function doLive() {
        if (isFetching) return;
        isFetching = true;
        var params = { machine_id: MACHINE_ID };
        if (lastTS) params.since = lastTS;

        fetch('api/machine_live.php?' + new URLSearchParams(params), { cache:'no-store' })
            .then(function(r){ return r.ok ? r.json() : Promise.reject(r.status); })
            .then(function(d) {
                if (!d.success) return;
                barPulse();

                // Update sensor cards dengan data terbaru
                if (d.sensor) updateSensorCards(d.sensor);

                // Append titik-titik baru ke chart & tabel
                if (d.new_rows && d.new_rows.length) {
                    d.new_rows.forEach(function(row) {
                        appendToCharts(row);
                        prependSensorRow(row);
                    });
                    // Geser lastTS ke timestamp terbaru
                    lastTS = d.new_rows[d.new_rows.length - 1].recorded_at;
                }

                // Alert baru → toast + tambah ke tabel
                if (d.new_alerts && d.new_alerts.length) {
                    d.new_alerts.forEach(function(al) {
                        prependAlertRow(al);
                        var sev = (al.severity||'').toLowerCase();
                        var type = sev==='critical'?'danger': sev==='warning'?'warning':'info';
                        showToast('⚠ Alert: <strong>'+al.sensor_key+'</strong> = '+al.sensor_value
                            +' on this machine', type);
                    });
                }

                // Update status badge mesin
                if (d.status) {
                    var stBadge = document.querySelector('[data-machine-status]');
                    if (stBadge) {
                        stBadge.className = 'badge badge-' + (d.status==='run'?'success':'danger') + ' ml-1';
                        stBadge.textContent = d.status.toUpperCase();
                    }
                }
            })
            .catch(function(){ /* silent */ })
            .finally(function(){ isFetching = false; });
    }

    // ── MQTT status indicator ─────────────────────────────────
    function checkMqttStatus() {
        fetch('api/mqtt_status.php', { cache:'no-store' })
            .then(function(r){ return r.ok ? r.json() : null; })
            .then(function(d) {
                if (!d) return;
                var dot   = document.getElementById('mqttLiveDot');
                var label = document.getElementById('mqttLiveLabel');
                if (!dot || !label) return;
                if (d.live) {
                    dot.style.background   = '#1cc88a';
                    dot.style.boxShadow    = '0 0 6px #1cc88a';
                    label.textContent      = '● MQTT Live (' + d.seconds_ago + 's ago)';
                    label.style.color      = '#1cc88a';
                } else if (d.broker_ok) {
                    dot.style.background   = '#f6c23e';
                    dot.style.boxShadow    = 'none';
                    label.textContent      = '⚠ Broker OK, no data (' + d.seconds_ago + 's ago)';
                    label.style.color      = '#f6c23e';
                } else {
                    dot.style.background   = '#e74a3b';
                    dot.style.boxShadow    = 'none';
                    label.textContent      = '✕ Broker offline';
                    label.style.color      = '#e74a3b';
                }
            }).catch(function(){});
    }

    // ── Update last reading timestamp ────────────────────────
    var origUpdateSensorCards = updateSensorCards;
    updateSensorCards = function(d) {
        origUpdateSensorCards(d);
        if (d && d.recorded_at) {
            var el = document.getElementById('lastReadingTs');
            if (el) {
                var dt = d.recorded_at;
                el.textContent = dt.substr(8,2)+'/'+dt.substr(5,2)+'/'+dt.substr(0,4)+' '+dt.substr(11,8);
            }
        }
    };

    // Kick-off
    setInterval(doLive, LIVE_INTERVAL);
    setInterval(checkMqttStatus, 15000);
    doLive();           // langsung poll pertama kali
    checkMqttStatus();  // cek status MQTT saat halaman dimuat

}); // end document.ready
</script>
