<?php
require_once 'includes/auth_check.php';
$currentPage = 'dashboard';
$pageTitle = 'Dashboard';
$conn = new mysqli('localhost', 'root', '', 'oee_monitoring');

if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// ── KPI 1: OEE Today (latest snap_date) ──────────────────────────────────────
$kpiOee = 0;
$res = $conn->query("SELECT AVG(oee_score) as avg_oee FROM oee_daily WHERE snap_date = (SELECT MAX(snap_date) FROM oee_daily)");
if ($res && $row = $res->fetch_assoc()) {
    $kpiOee = round($row['avg_oee'] ?? 0, 1);
}

// ── KPI 2: Machines Running ───────────────────────────────────────────────────
$totalMachines = 0;
$runningMachines = 0;
$res = $conn->query("SELECT COUNT(*) as total, SUM(status='run') as running FROM machines");
if ($res && $row = $res->fetch_assoc()) {
    $totalMachines   = (int)$row['total'];
    $runningMachines = (int)$row['running'];
}

// ── KPI 3: Active Alerts ──────────────────────────────────────────────────────
$totalAlerts = 0;
$alertHigh = 0; $alertMedium = 0; $alertLow = 0;
$res = $conn->query("SELECT severity, COUNT(*) as cnt FROM alerts WHERE acknowledged=0 GROUP BY severity");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $totalAlerts += $row['cnt'];
        if ($row['severity'] === 'high')   $alertHigh   = $row['cnt'];
        if ($row['severity'] === 'medium') $alertMedium = $row['cnt'];
        if ($row['severity'] === 'low')    $alertLow    = $row['cnt'];
    }
}

// ── KPI 4: Maintenance Due next 7 days ───────────────────────────────────────
$maintenanceDue = 0;
$res = $conn->query("SELECT COUNT(*) as cnt FROM maintenance_records WHERE maint_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)");
if ($res && $row = $res->fetch_assoc()) {
    $maintenanceDue = (int)$row['cnt'];
}

// ── Trend chart: last 7 days from MAX(snap_date) ─────────────────────────────
$trendData   = [];
$trendLabels = [];
$machineNames = [];
$res = $conn->query("
    SELECT d.snap_date, d.machine_id, m.name AS machine_name, d.oee_score
    FROM oee_daily d
    JOIN machines m ON m.id = d.machine_id
    WHERE d.snap_date >= DATE_SUB((SELECT MAX(snap_date) FROM oee_daily), INTERVAL 6 DAY)
      AND d.snap_date <= (SELECT MAX(snap_date) FROM oee_daily)
    ORDER BY d.snap_date ASC, d.machine_id ASC
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $date = $row['snap_date'];
        $mid  = $row['machine_id'];
        if (!in_array($date, $trendLabels)) $trendLabels[] = $date;
        if (!isset($machineNames[$mid]))    $machineNames[$mid] = $row['machine_name'];
        $trendData[$mid][$date] = round($row['oee_score'], 1);
    }
}

// ── Donut: OEE distribution from MAX snap_date ───────────────────────────────
$distLow = 0; $distMid = 0; $distHigh = 0;
$res = $conn->query("SELECT oee_score FROM oee_daily WHERE snap_date = (SELECT MAX(snap_date) FROM oee_daily)");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $v = (float)$row['oee_score'];
        if ($v < 60)      $distLow++;
        elseif ($v <= 85) $distMid++;
        else              $distHigh++;
    }
}

// ── Machine table: join oee_daily on MAX snap_date per machine ────────────────
$machines = [];
$res = $conn->query("
    SELECT m.id, m.name, m.status,
           COALESCE(pl.name,'—') AS line_name,
           d.oee_score, d.availability, d.performance, d.quality, d.snap_date,
           (SELECT recorded_at FROM sensor_readings sr WHERE sr.machine_id = m.id ORDER BY sr.recorded_at DESC LIMIT 1) AS last_reading
    FROM machines m
    LEFT JOIN production_lines pl ON pl.id = m.line_id
    LEFT JOIN oee_daily d ON d.machine_id = m.id
        AND d.snap_date = (SELECT MAX(snap_date) FROM oee_daily WHERE machine_id = m.id)
    ORDER BY m.id ASC
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $machines[] = $row;
    }
}

// ── Recent Alerts (last 5) ────────────────────────────────────────────────────
$recentAlerts = [];
$res = $conn->query("
    SELECT a.*, m.name AS machine_name
    FROM alerts a
    LEFT JOIN machines m ON m.id = a.machine_id
    ORDER BY a.created_at DESC
    LIMIT 5
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $recentAlerts[] = $row;
    }
}

// ── Upcoming Maintenance (next 5) ────────────────────────────────────────────
$upcomingMaint = [];
$res = $conn->query("
    SELECT mr.*, m.name AS machine_name
    FROM maintenance_records mr
    LEFT JOIN machines m ON m.id = mr.machine_id
    ORDER BY mr.maint_date ASC
    LIMIT 5
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $upcomingMaint[] = $row;
    }
}

$conn->close();

// ── Build JS chart datasets ───────────────────────────────────────────────────
$jsLabels    = json_encode($trendLabels);
$jsTrendData = json_encode($trendData);      // [machine_id][date] = oee
$jsMachineNames = json_encode($machineNames); // [machine_id] = name
$chartColorsArr = ['#4e73df','#1cc88a','#e74a3b','#f6c23e','#2c9faf','#858796',
                   '#17a673','#f8961e','#2e59d9','#a29bfe','#fd79a8','#00cec9'];
$jsChartColors = json_encode($chartColorsArr);

// Build default datasets (first machine only, as initial display)
$firstMid   = array_key_first($machineNames ?? [null]);
$datasetsJs = [];
if ($firstMid) {
    $points = [];
    foreach ($trendLabels as $lbl) $points[] = isset($trendData[$firstMid][$lbl]) ? $trendData[$firstMid][$lbl] : 'null';
    $datasetsJs[] = '{"label":' . json_encode($machineNames[$firstMid]) . ',"data":[' . implode(',', $points) . '],'
        . '"borderColor":"#4e73df","backgroundColor":"rgba(78,115,223,.08)","fill":true,"tension":0.3,"spanGaps":true,"borderWidth":2,"pointRadius":3}';
}
$thresh = array_fill(0, count($trendLabels), 85);
$datasetsJs[] = '{"label":"Target (85%)","data":[' . implode(',', $thresh) . '],'
    . '"borderColor":"#858796","backgroundColor":"transparent",'
    . '"borderDash":[6,4],"fill":false,"tension":0,"pointRadius":0,"spanGaps":true}';
$jsDatasetsStr = '[' . implode(',', $datasetsJs) . ']';

include 'includes/header.php';
?>

<!-- Page Heading -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        Dashboard
        <small class="text-muted ml-2" style="font-size:0.75rem;">
            &mdash; update berikutnya dalam <span id="countdown-val">10</span>s
        </small>
    </h1>
    <div class="d-flex align-items-center">
        <!-- Indikator MQTT realtime -->
        <span id="mqttDot" title="Status MQTT"
              style="display:inline-block;width:9px;height:9px;border-radius:50%;
                     background:#858796;margin-right:5px;transition:background .4s;flex-shrink:0;"></span>
        <small id="mqttLabel" class="text-muted mr-3" style="font-size:.7rem;white-space:nowrap;">MQTT —</small>
        <!-- Indikator AJAX refresh -->
        <span id="syncDot" title="Status koneksi"
              style="display:inline-block;width:10px;height:10px;border-radius:50%;
                     background:#1cc88a;margin-right:8px;transition:background .4s;"></span>
        <small id="syncTs" class="text-muted mr-3" style="font-size:.72rem;">—</small>
        <button onclick="doRefresh(true)" class="btn btn-sm btn-primary shadow-sm">
            <i class="fas fa-sync-alt fa-sm text-white-50"></i> Refresh
        </button>
    </div>
</div>

<style>
@keyframes kpiFlash { 0%{background:#fffde7} 80%{background:#fffde7} 100%{background:transparent} }
.kpi-flash { animation: kpiFlash .8s ease; }
@keyframes rowHL { 0%{background:#e8f5e9} 100%{background:transparent} }
.row-hl { animation: rowHL 1s ease; }
@keyframes mqttPulse { 0%,100%{opacity:1} 50%{opacity:.3} }
.mqtt-live-dot { animation: mqttPulse 1.5s infinite; }
/* Highlight baris mesin saat status berubah */
@keyframes statusChange { 0%{background:#fff3cd} 100%{background:transparent} }
.status-changed { animation: statusChange 2s ease; }
/* Last reading: warna sesuai umur data */
.reading-fresh  { color:#1cc88a !important; }
.reading-stale  { color:#f6c23e !important; }
.reading-old    { color:#e74a3b !important; }
</style>

<!-- KPI Cards Row -->
<div class="row">

    <!-- OEE Score -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">OEE Score (Latest)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><span id="kpi-oee"><?php echo $kpiOee; ?></span>%</div>
                        <div class="progress mt-2" style="height:6px;">
                            <div id="kpi-oee-bar" class="progress-bar <?php echo $kpiOee >= 85 ? 'bg-success' : ($kpiOee >= 60 ? 'bg-warning' : 'bg-danger'); ?>"
                                 style="width:<?php echo min($kpiOee, 100); ?>%"></div>
                        </div>
                        <small class="text-muted">Target: 85%</small>
                    </div>
                    <div class="col-auto"><i class="fas fa-tachometer-alt fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Machines Running -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Machines Running</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <span id="kpi-running"><?php echo $runningMachines; ?></span> / <span id="kpi-total"><?php echo $totalMachines; ?></span>
                        </div>
                        <small class="text-muted"><span id="kpi-stopped"><?php echo $totalMachines - $runningMachines; ?></span> stopped</small>
                    </div>
                    <div class="col-auto"><i class="fas fa-cogs fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Alerts -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Active Alerts</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><span id="kpi-alerts"><?php echo $totalAlerts; ?></span></div>
                        <small class="text-muted" id="kpi-alerts-detail">
                            <?php if ($alertHigh):   ?><span class="badge badge-danger">High: <?php echo $alertHigh; ?></span> <?php endif; ?>
                            <?php if ($alertMedium): ?><span class="badge badge-warning">Med: <?php echo $alertMedium; ?></span> <?php endif; ?>
                            <?php if ($alertLow):    ?><span class="badge badge-secondary">Low: <?php echo $alertLow; ?></span><?php endif; ?>
                            <?php if (!$totalAlerts): ?><span class="text-success">None</span><?php endif; ?>
                        </small>
                    </div>
                    <div class="col-auto"><i class="fas fa-bell fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Maintenance Due -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Maintenance Due (7 days)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><span id="kpi-maint"><?php echo $maintenanceDue; ?></span></div>
                        <small class="text-muted">scheduled records</small>
                    </div>
                    <div class="col-auto"><i class="fas fa-wrench fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>

</div><!-- /KPI row -->

<!-- Charts Row -->
<div class="row align-items-stretch">

    <!-- OEE Trend Line Chart -->
    <div class="col-xl-8 col-lg-7 mb-4 d-flex flex-column">
        <div class="card shadow flex-fill">
            <div class="card-header py-3 d-flex align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">OEE Trend (Last 7 Days)</h6>
                <select id="trendMachineSelect" class="form-control form-control-sm" style="width:220px;">
                    <?php foreach ($machineNames as $mid => $mname): ?>
                    <option value="<?= $mid ?>"><?= htmlspecialchars($mname) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="card-body d-flex align-items-center">
                <canvas id="oeeLineChart" style="width:100%;"></canvas>
            </div>
        </div>
    </div>

    <!-- OEE Donut Distribution -->
    <div class="col-xl-4 col-lg-5 mb-4 d-flex flex-column">
        <div class="card shadow flex-fill">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">OEE Distribution (Latest)</h6>
            </div>
            <div class="card-body d-flex flex-column align-items-center justify-content-center">
                <canvas id="oeeDonutChart" style="max-width:260px;max-height:260px;"></canvas>
                <div class="mt-3 text-center small" id="donut-legend">
                    <span class="mr-2"><i class="fas fa-circle text-danger"></i> &lt;60%: <span id="dist-low"><?php echo $distLow; ?></span></span>
                    <span class="mr-2"><i class="fas fa-circle text-warning"></i> 60-85%: <span id="dist-mid"><?php echo $distMid; ?></span></span>
                    <span><i class="fas fa-circle text-success"></i> &gt;85%: <span id="dist-high"><?php echo $distHigh; ?></span></span>
                </div>
            </div>
        </div>
    </div>

</div><!-- /charts row -->

<!-- Machine Status Table -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Machine Status</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="machineTable" width="100%" cellspacing="0">
                <thead class="thead-light">
                    <tr>
                        <th>Mesin</th>
                        <th>Line</th>
                        <th>Status</th>
                        <th>OEE%</th>
                        <th>Availability%</th>
                        <th>Performance%</th>
                        <th>Quality%</th>
                        <th>Last Reading</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($machines as $m):
                    $oee   = round($m['oee_score']    ?? 0, 1);
                    $avail = round($m['availability'] ?? 0, 1);
                    $perf  = round($m['performance']  ?? 0, 1);
                    $qual  = round($m['quality']      ?? 0, 1);
                    $oeeColor = $oee >= 85 ? 'success' : ($oee >= 60 ? 'warning' : 'danger');
                    $statusBadge = $m['status'] === 'run'
                        ? '<span class="badge badge-success">Running</span>'
                        : '<span class="badge badge-secondary">Stopped</span>';
                    $lastRead = $m['last_reading']
                        ? date('d/m H:i', strtotime($m['last_reading']))
                        : '—';
                ?>
                    <tr data-mid="<?php echo (int)$m['id']; ?>">
                        <td><?php echo htmlspecialchars($m['name']); ?></td>
                        <td><?php echo htmlspecialchars($m['line_name']); ?></td>
                        <td><?php echo $statusBadge; ?></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <span class="mr-2" style="min-width:42px;"><?php echo $oee; ?>%</span>
                                <div class="progress flex-grow-1" style="height:8px;">
                                    <div class="progress-bar bg-<?php echo $oeeColor; ?>"
                                         style="width:<?php echo min($oee, 100); ?>%"></div>
                                </div>
                            </div>
                        </td>
                        <td><?php echo $avail; ?>%</td>
                        <td><?php echo $perf; ?>%</td>
                        <td><?php echo $qual; ?>%</td>
                        <td><small><?php echo $lastRead; ?></small></td>
                        <td>
                            <a href="machine_detail.php?id=<?php echo (int)$m['id']; ?>" class="btn btn-sm btn-info">
                                <i class="fas fa-eye"></i> Detail
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Alerts & Maintenance Row -->
<div class="row">

    <!-- Recent Alerts -->
    <div class="col-xl-6 col-lg-6 mb-4">
        <div class="card shadow">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Recent Alerts</h6>
                <a href="alerts.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush" id="alert-list">
                <?php if (empty($recentAlerts)): ?>
                    <li class="list-group-item text-center text-muted py-3">No active alerts</li>
                <?php else: foreach ($recentAlerts as $alert):
                    $sev = $alert['severity'];
                    $sevColor = $sev === 'high' ? 'danger' : ($sev === 'medium' ? 'warning' : 'secondary');
                    $ago = '—';
                    if (!empty($alert['created_at'])) {
                        $diff = time() - strtotime($alert['created_at']);
                        if ($diff < 60)      $ago = $diff . 's ago';
                        elseif ($diff < 3600) $ago = round($diff / 60) . 'm ago';
                        elseif ($diff < 86400) $ago = round($diff / 3600) . 'h ago';
                        else                  $ago = round($diff / 86400) . 'd ago';
                    }
                ?>
                    <li class="list-group-item d-flex justify-content-between align-items-start py-2">
                        <div>
                            <span class="badge badge-<?php echo $sevColor; ?> mr-1"><?php echo strtoupper($sev); ?></span>
                            <strong><?php echo htmlspecialchars($alert['machine_name'] ?? '—'); ?></strong>
                            &mdash; <?php echo htmlspecialchars($alert['sensor_key']); ?>
                            <br>
                            <small class="text-muted">
                                Value: <?php echo htmlspecialchars($alert['sensor_value']); ?>
                                &nbsp;(lo: <?php echo $alert['threshold_lo']; ?>, hi: <?php echo $alert['threshold_hi']; ?>)
                            </small>
                        </div>
                        <small class="text-muted text-nowrap ml-2"><?php echo $ago; ?></small>
                    </li>
                <?php endforeach; endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- Upcoming Maintenance -->
    <div class="col-xl-6 col-lg-6 mb-4">
        <div class="card shadow">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Upcoming Maintenance</h6>
                <a href="maintenance.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush" id="maint-list">
                <?php if (empty($upcomingMaint)): ?>
                    <li class="list-group-item text-center text-muted py-3">No upcoming maintenance</li>
                <?php else: foreach ($upcomingMaint as $mr):
                    $typeColor = match($mr['type'] ?? '') {
                        'preventive' => 'primary',
                        'corrective' => 'danger',
                        default      => 'info'
                    };
                ?>
                    <li class="list-group-item d-flex justify-content-between align-items-start py-2">
                        <div>
                            <span class="badge badge-<?php echo $typeColor; ?> mr-1">
                                <?php echo ucfirst(htmlspecialchars($mr['type'] ?? '')); ?>
                            </span>
                            <strong><?php echo htmlspecialchars($mr['machine_name'] ?? '—'); ?></strong>
                            <br>
                            <small class="text-muted">
                                <?php echo htmlspecialchars($mr['description'] ?? ''); ?>
                                <?php if (!empty($mr['technician'])): ?>&mdash; <?php echo htmlspecialchars($mr['technician']); ?><?php endif; ?>
                            </small>
                        </div>
                        <small class="text-nowrap ml-2">
                            <?php echo !empty($mr['maint_date']) ? date('d/m/Y', strtotime($mr['maint_date'])) : '—'; ?>
                        </small>
                    </li>
                <?php endforeach; endif; ?>
                </ul>
            </div>
        </div>
    </div>

</div><!-- /alerts+maintenance row -->

<script>
$(document).ready(function(){

    // ═══════════════════════════════════════════════════════════════════════════
    //  KONFIGURASI INTERVAL
    //  KPI_INTERVAL  : refresh KPI + Alert + Donut  (lebih sering, data ringan)
    //  TBL_INTERVAL  : refresh Machine Table        (lebih jarang, data berat)
    // ═══════════════════════════════════════════════════════════════════════════
    var KPI_INTERVAL = 10;   // detik — KPI, alert, donut
    var TBL_INTERVAL = 60;   // detik — machine table

    var countdown    = KPI_INTERVAL;
    var countdownTimer;
    var tableCounter = 0;    // hitung berapa kali KPI sudah refresh
    var tableEvery   = Math.round(TBL_INTERVAL / KPI_INTERVAL); // = 6

    var donutChart, machineTable;
    var isFetching = false;

    // ── Utiliti ───────────────────────────────────────────────────────────────
    function esc(s){ return $('<span>').text(s||'').html(); }

    function flash(el) {
        if (!el) return;
        el.classList.remove('kpi-flash');
        void el.offsetWidth;
        el.classList.add('kpi-flash');
    }

    function setSyncDot(state) {
        var dot = document.getElementById('syncDot');
        if (!dot) return;
        dot.style.background = { ok:'#1cc88a', busy:'#f6c23e', err:'#e74a3b' }[state] || '#1cc88a';
    }

    function setVal(id, val) {
        var el = document.getElementById(id);
        if (!el || el.textContent == val) return;
        el.textContent = val;
        flash(el);
    }

    // ── KPI cards ─────────────────────────────────────────────────────────────
    function updateKpi(kpi) {
        setVal('kpi-oee',     kpi.oee);
        setVal('kpi-running', kpi.running);
        setVal('kpi-total',   kpi.total);
        setVal('kpi-stopped', kpi.stopped);
        setVal('kpi-alerts',  kpi.alerts_total);
        setVal('kpi-maint',   kpi.maintenance_due);

        var bar = document.getElementById('kpi-oee-bar');
        if (bar) {
            bar.className = 'progress-bar ' + (kpi.oee >= 85 ? 'bg-success' : kpi.oee >= 60 ? 'bg-warning' : 'bg-danger');
            bar.style.width = Math.min(kpi.oee, 100) + '%';
        }
        var det = document.getElementById('kpi-alerts-detail');
        if (det) {
            var h = '';
            if (kpi.alerts_high)   h += '<span class="badge badge-danger">High: '   + kpi.alerts_high   + '</span> ';
            if (kpi.alerts_medium) h += '<span class="badge badge-warning">Med: '   + kpi.alerts_medium + '</span> ';
            if (kpi.alerts_low)    h += '<span class="badge badge-secondary">Low: ' + kpi.alerts_low    + '</span>';
            if (!kpi.alerts_total) h  = '<span class="text-success">None</span>';
            det.innerHTML = h;
        }
    }

    // ── Donut ─────────────────────────────────────────────────────────────────
    function updateDonut(data) {
        if (donutChart) { donutChart.data.datasets[0].data = data; donutChart.update(); }
        ['dist-low','dist-mid','dist-high'].forEach(function(id,i){
            var el = document.getElementById(id); if (el) el.textContent = data[i];
        });
    }

    // ── Alert list ────────────────────────────────────────────────────────────
    function updateAlertList(alerts) {
        var ul = document.getElementById('alert-list'); if (!ul) return;
        if (!alerts || !alerts.length) {
            ul.innerHTML = '<li class="list-group-item text-center text-muted py-3">No active alerts</li>'; return;
        }
        var sc = { high:'danger', medium:'warning', low:'secondary' };
        ul.innerHTML = alerts.map(function(a){
            return '<li class="list-group-item d-flex justify-content-between align-items-start py-2">'
                + '<div><span class="badge badge-'+(sc[a.severity]||'secondary')+' mr-1">'+a.severity.toUpperCase()+'</span>'
                + '<strong>'+esc(a.machine_name)+'</strong> &mdash; '+esc(a.sensor_key)
                + '<br><small class="text-muted">Value: '+esc(a.sensor_value)
                + ' &nbsp;(lo:'+a.threshold_lo+', hi:'+a.threshold_hi+')</small></div>'
                + '<small class="text-muted text-nowrap ml-2">'+(a.ago||'')+'</small></li>';
        }).join('');
    }

    // ── Maintenance list ──────────────────────────────────────────────────────
    function updateMaintList(maint) {
        var ul = document.getElementById('maint-list'); if (!ul) return;
        if (!maint || !maint.length) {
            ul.innerHTML = '<li class="list-group-item text-center text-muted py-3">No upcoming maintenance</li>'; return;
        }
        var tc = { preventive:'primary', corrective:'danger' };
        ul.innerHTML = maint.map(function(m){
            var dt = m.maint_date ? m.maint_date.substr(8,2)+'/'+m.maint_date.substr(5,2)+'/'+m.maint_date.substr(0,4) : '—';
            return '<li class="list-group-item d-flex justify-content-between align-items-start py-2">'
                + '<div><span class="badge badge-'+(tc[m.type]||'info')+' mr-1">'+esc(m.type)+'</span>'
                + '<strong>'+esc(m.machine_name)+'</strong>'
                + '<br><small class="text-muted">'+esc(m.description)+(m.technician?' &mdash; '+esc(m.technician):'')+'</small></div>'
                + '<small class="text-nowrap ml-2">'+dt+'</small></li>';
        }).join('');
    }

    // ── Machine table — update IN-PLACE, bukan clear+redraw ──────────────────
    // Hanya update cell yang berubah, paging tidak terganggu
    function updateMachineTable(machines) {
        if (!machineTable || !machines) return;
        var byId = {};
        machines.forEach(function(m){ byId[m.id] = m; });

        machineTable.rows().every(function(){
            var rowNode = this.node();
            var mid = rowNode ? rowNode.getAttribute('data-mid') : null;
            if (!mid || !byId[mid]) return;
            var m   = byId[mid];
            var oee = parseFloat(m.oee_score||0).toFixed(1);
            var cls = oee >= 85 ? 'success' : (oee >= 60 ? 'warning' : 'danger');

            // Status badge (col 2)
            var cell2 = rowNode.cells[2];
            if (cell2) cell2.innerHTML = m.status === 'run'
                ? '<span class="badge badge-success">Running</span>'
                : '<span class="badge badge-secondary">Stopped</span>';

            // OEE bar (col 3)
            var cell3 = rowNode.cells[3];
            if (cell3) cell3.innerHTML = '<div class="d-flex align-items-center">'
                + '<span class="mr-2" style="min-width:42px;">'+oee+'%</span>'
                + '<div class="progress flex-grow-1" style="height:8px;">'
                + '<div class="progress-bar bg-'+cls+'" style="width:'+Math.min(oee,100)+'%"></div>'
                + '</div></div>';

            // Availability / Performance / Quality (col 4,5,6)
            if (rowNode.cells[4]) rowNode.cells[4].textContent = parseFloat(m.availability||0).toFixed(1)+'%';
            if (rowNode.cells[5]) rowNode.cells[5].textContent = parseFloat(m.performance ||0).toFixed(1)+'%';
            if (rowNode.cells[6]) rowNode.cells[6].textContent = parseFloat(m.quality     ||0).toFixed(1)+'%';

            // Last reading (col 7)
            if (rowNode.cells[7]) {
                var lr = m.last_reading
                    ? m.last_reading.substr(8,2)+'/'+m.last_reading.substr(5,2)+' '+m.last_reading.substr(11,5)
                    : '—';
                rowNode.cells[7].innerHTML = '<small>'+lr+'</small>';
            }
        });
    }

    // ── AJAX fetch utama ──────────────────────────────────────────────────────
    function doFetch(withTable) {
        if (isFetching) return;
        isFetching = true;
        setSyncDot('busy');

        fetch('api/dashboard_data.php' + (withTable ? '' : '?notbl=1'), { cache:'no-store' })
            .then(function(r){ return r.ok ? r.json() : Promise.reject(r.status); })
            .then(function(d){
                setSyncDot('ok');
                var ts = document.getElementById('syncTs');
                if (ts) ts.textContent = 'Update: ' + (d.ts||'—');
                if (d.kpi)    updateKpi(d.kpi);
                if (d.donut)  updateDonut(d.donut);
                if (d.alerts) updateAlertList(d.alerts);
                if (d.maint)  updateMaintList(d.maint);
                if (withTable && d.machines) updateMachineTable(d.machines);
            })
            .catch(function(){ setSyncDot('err'); })
            .finally(function(){ isFetching = false; });
    }

    // ── Manual refresh (tombol) ───────────────────────────────────────────────
    window.doRefresh = function(manual) {
        tableCounter = 0;   // paksa refresh tabel juga
        doFetch(true);
    };

    // ── Countdown + interval ──────────────────────────────────────────────────
    countdown = KPI_INTERVAL;
    $('#countdown-val').text(countdown);
    countdownTimer = setInterval(function(){
        countdown--;
        $('#countdown-val').text(Math.max(0, countdown));
        if (countdown <= 0) {
            countdown = KPI_INTERVAL;
            tableCounter++;
            var withTable = (tableCounter >= tableEvery);
            if (withTable) tableCounter = 0;
            doFetch(withTable);
        }
    }, 1000);

    // ── DataTable: Machine Status ─────────────────────────────────────────────
    machineTable = $('#machineTable').DataTable({
        pageLength: 15,
        lengthMenu: [10, 15, 25, 50, 100],
        ordering: true,
        searching: true,
        info: true,
        paging: true,
        language: { url: 'vendor/datatables/i18n/id.json' },
        columnDefs: [{ orderable: false, targets: [3, 8] }]
    });

    // ── OEE Line Chart ────────────────────────────────────────────────────────
    var lineCtx      = document.getElementById('oeeLineChart').getContext('2d');
    var lineLabels   = <?php echo $jsLabels; ?>;
    var allTrendData = <?php echo $jsTrendData; ?>;     // {mid: {date: oee}}
    var machineNames = <?php echo $jsMachineNames; ?>;  // {mid: name}
    var chartColors  = <?php echo $jsChartColors; ?>;

    function buildDataset(mid, colorIdx) {
        var color = chartColors[colorIdx % chartColors.length];
        var pts   = lineLabels.map(function(d){ return allTrendData[mid] && allTrendData[mid][d] != null ? allTrendData[mid][d] : null; });
        return { label: machineNames[mid], data: pts, borderColor: color,
            backgroundColor: color + '18', fill: true, tension: 0.3,
            spanGaps: true, borderWidth: 2, pointRadius: 3, pointHoverRadius: 5 };
    }

    var threshDataset = { label: 'Target (85%)', data: lineLabels.map(function(){ return 85; }),
        borderColor: '#858796', backgroundColor: 'transparent',
        borderDash: [6,4], fill: false, tension: 0, pointRadius: 0, spanGaps: true };

    var lineChart = new Chart(lineCtx, {
        type: 'line',
        data: { labels: lineLabels, datasets: [<?php echo $jsDatasetsStr; ?>] },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            legend: {
                display: true, position: 'bottom',
                labels: { fontSize: 11, usePointStyle: true, padding: 12 }
            },
            tooltips: {
                mode: 'index', intersect: false,
                backgroundColor: 'rgba(17,24,39,.88)',
                callbacks: {
                    title: function(items){ var d=items[0].xLabel; if(!d)return d; var p=d.split('-'); return p[2]+'/'+p[1]+'/'+p[0]; },
                    label: function(item, data){ return '  '+data.datasets[item.datasetIndex].label+': '+(item.yLabel!==null?item.yLabel+'%':'N/A'); }
                }
            },
            scales: {
                xAxes: [{ gridLines:{ color:'rgba(0,0,0,.05)' }, ticks:{ fontSize:11,
                    callback: function(val){ if(!val)return val; var p=val.split('-'); return p[2]+'/'+p[1]; }
                }}],
                yAxes: [{ ticks:{ min:0,max:100,fontSize:11,callback:function(v){return v+'%';} },
                    gridLines:{ color:'rgba(0,0,0,.05)' }
                }]
            }
        }
    });

    // Dropdown handler
    var trendSel = document.getElementById('trendMachineSelect');
    if (trendSel) {
        trendSel.addEventListener('change', function(){
            var mid = this.value;
            lineChart.data.datasets = [buildDataset(mid, 0), threshDataset];
            lineChart.update();
        });
    }

    // ── OEE Donut Chart (Chart.js 2.x) ───────────────────────────────────────
    var donutCtx = document.getElementById('oeeDonutChart').getContext('2d');
    donutChart = new Chart(donutCtx, {
        type: 'doughnut',
        data: {
            labels: ['< 60%', '60-85%', '> 85%'],
            datasets: [{
                data: [<?php echo $distLow; ?>, <?php echo $distMid; ?>, <?php echo $distHigh; ?>],
                backgroundColor: ['#e74a3b', '#f6c23e', '#1cc88a'],
                hoverBackgroundColor: ['#c0392b', '#d4ac0d', '#17a673'],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            cutoutPercentage: 70,
            legend: { display: true, position: 'bottom', labels: { fontSize: 11 } },
            tooltips: {
                callbacks: {
                    label: function(item, data){
                        var lbl = data.labels[item.index];
                        var val = data.datasets[0].data[item.index];
                        return ' ' + lbl + ': ' + val + ' mesin';
                    }
                }
            }
        }
    });

    // ══════════════════════════════════════════════════════════════════════════
    //  MQTT REALTIME — Fast poll 5 detik
    //  Update: status badge mesin, last reading, KPI running/stopped,
    //          indikator MQTT Live di header
    // ══════════════════════════════════════════════════════════════════════════
    var LIVE_INTERVAL  = 5000; // ms — khusus MQTT realtime
    var isLiveFetching = false;
    var _prevRunning   = -1;   // untuk detect perubahan

    var mqttDotEl  = document.getElementById('mqttDot');
    var mqttLblEl  = document.getElementById('mqttLabel');

    function setMqttDot(state, label) {
        if (!mqttDotEl) return;
        var colors = { live:'#1cc88a', nodata:'#f6c23e', error:'#e74a3b', init:'#858796' };
        mqttDotEl.style.background = colors[state] || colors.init;
        mqttDotEl.classList.toggle('mqtt-live-dot', state === 'live');
        if (mqttLblEl) mqttLblEl.textContent = label;
    }

    function updateLiveStatus(data) {
        // ── KPI: Running / Stopped ──
        var newRunning = data.running;
        if (newRunning !== _prevRunning && _prevRunning !== -1) {
            // Ada perubahan status mesin — flash KPI
            setVal('kpi-running', newRunning);
            setVal('kpi-stopped', data.stopped);
        } else {
            // Update senyap tanpa flash kalau tidak berubah
            var rEl = document.getElementById('kpi-running');
            var sEl = document.getElementById('kpi-stopped');
            if (rEl) rEl.textContent = newRunning;
            if (sEl) sEl.textContent = data.stopped;
        }
        _prevRunning = newRunning;

        // ── MQTT indicator ──
        if (data.mqtt_live) {
            var ago = data.mqtt_secs < 60
                ? data.mqtt_secs + 'd lalu'
                : Math.round(data.mqtt_secs / 60) + 'm lalu';
            setMqttDot('live', 'MQTT Live · ' + (data.mqtt_last||'') );
        } else if (data.mqtt_secs < 9999) {
            setMqttDot('nodata', 'MQTT ' + Math.round(data.mqtt_secs/60) + 'm lalu');
        } else {
            setMqttDot('nodata', 'MQTT No Signal');
        }

        // ── Machine table: update status badge + last reading IN-PLACE ──
        if (!data.machines || !machineTable) return;
        data.machines.forEach(function(m) {
            // Cari baris di DOM (langsung, bukan via DataTable API agar cepat)
            var row = document.querySelector('#machineTable tbody tr[data-mid="' + m.id + '"]');
            if (!row) return;

            // Status badge (kolom 2, index 2)
            var cell2 = row.cells[2];
            if (cell2) {
                var isRun    = m.status === 'run';
                var newBadge = isRun
                    ? '<span class="badge badge-success">Running</span>'
                    : '<span class="badge badge-secondary">Stopped</span>';
                var curBadge = cell2.innerHTML.trim().replace(/\s+/g,' ');
                if (curBadge !== newBadge) {
                    cell2.innerHTML = newBadge;
                    // Highlight baris karena status berubah
                    row.classList.remove('status-changed');
                    void row.offsetWidth; // force reflow
                    row.classList.add('status-changed');
                }
            }

            // Last Reading (kolom 7, index 7)
            var cell7 = row.cells[7];
            if (cell7 && m.last_reading) {
                var lr = m.last_reading.substr(8,2)+'/'+m.last_reading.substr(5,2)
                       +' '+m.last_reading.substr(11,5);
                var secs = m.secs_ago !== null ? m.secs_ago : 99999;
                var cls  = secs <= 60 ? 'reading-fresh' : secs <= 300 ? 'reading-stale' : 'reading-old';
                cell7.innerHTML = '<small class="' + cls + '">' + lr + '</small>';
            } else if (cell7 && !m.last_reading) {
                cell7.innerHTML = '<small class="text-muted">—</small>';
            }
        });
    }

    function doLive() {
        if (isLiveFetching) return;
        isLiveFetching = true;
        fetch('api/dashboard_live.php', { cache: 'no-store' })
            .then(function(r) { return r.ok ? r.json() : Promise.reject(r.status); })
            .then(function(d) { updateLiveStatus(d); })
            .catch(function()  { setMqttDot('error', 'MQTT Error'); })
            .finally(function(){ isLiveFetching = false; });
    }

    // Jalankan setiap 5 detik + warm-up setelah 1 detik
    setInterval(doLive, LIVE_INTERVAL);
    setTimeout(doLive, 1000);
});
</script>

<?php include 'includes/footer.php'; ?>
