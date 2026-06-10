<?php
require_once 'includes/auth_check.php';
$currentPage = 'energy';
$pageTitle = 'Laporan Energi';

$db = getDB();

// ── Filters ──────────────────────────────────────────────────────────────────
$machine_id = isset($_GET['machine_id']) ? intval($_GET['machine_id']) : 0;
$date_from  = (isset($_GET['date_from']) && $_GET['date_from'] !== '')
              ? $_GET['date_from'] : date('Y-m-d', strtotime('-6 days'));
$date_to    = (isset($_GET['date_to']) && $_GET['date_to'] !== '')
              ? $_GET['date_to']   : date('Y-m-d');
$group_by   = (isset($_GET['group_by']) && in_array($_GET['group_by'], ['hour','day']))
              ? $_GET['group_by'] : 'day';

// Sanitise dates
$date_from = date('Y-m-d', strtotime($date_from));
$date_to   = date('Y-m-d', strtotime($date_to));

// ── Machine list ─────────────────────────────────────────────────────────────
$stmt = $db->query("SELECT id, name FROM machines ORDER BY name");
$machine_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Helper: machine WHERE clause ─────────────────────────────────────────────
$m_where = $machine_id > 0 ? "AND sr.machine_id = " . intval($machine_id) : "";

// ══════════════════════════════════════════════════════════════════════════════
// KPI 1 – Total Energy Today
// ══════════════════════════════════════════════════════════════════════════════
$today = date('Y-m-d');
$stmtToday = $db->prepare(
    "SELECT COALESCE(SUM(e_r + e_s + e_t), 0)
     FROM sensor_readings sr
     WHERE DATE(recorded_at) = :today {$m_where}"
);
$stmtToday->execute([':today' => $today]);
$kpi_today = round((float)$stmtToday->fetchColumn(), 2);

// ══════════════════════════════════════════════════════════════════════════════
// KPI 2 – Peak Hour (in the selected period)
// ══════════════════════════════════════════════════════════════════════════════
$stmtPeak = $db->prepare(
    "SELECT HOUR(recorded_at) AS hr,
            SUM(e_r + e_s + e_t) AS total
     FROM sensor_readings sr
     WHERE DATE(recorded_at) BETWEEN :df AND :dt {$m_where}
     GROUP BY HOUR(recorded_at)
     ORDER BY total DESC
     LIMIT 1"
);
$stmtPeak->execute([':df' => $date_from, ':dt' => $date_to]);
$peakRow      = $stmtPeak->fetch(PDO::FETCH_ASSOC);
$kpi_peak_hr  = $peakRow ? (int)$peakRow['hr'] : 0;
$kpi_peak_val = $peakRow ? round((float)$peakRow['total'], 2) : 0;

// ══════════════════════════════════════════════════════════════════════════════
// KPI 3 – Most Consuming Machine
// ══════════════════════════════════════════════════════════════════════════════
$stmtTop = $db->prepare(
    "SELECT m.name, SUM(sr.e_r + sr.e_s + sr.e_t) AS total
     FROM sensor_readings sr
     JOIN machines m ON m.id = sr.machine_id
     WHERE DATE(sr.recorded_at) BETWEEN :df AND :dt {$m_where}
     GROUP BY sr.machine_id, m.name
     ORDER BY total DESC
     LIMIT 1"
);
$stmtTop->execute([':df' => $date_from, ':dt' => $date_to]);
$topRow          = $stmtTop->fetch(PDO::FETCH_ASSOC);
$kpi_top_machine = $topRow ? $topRow['name'] : 'N/A';
$kpi_top_val     = $topRow ? round((float)$topRow['total'], 2) : 0;

// ══════════════════════════════════════════════════════════════════════════════
// KPI 4 – Avg Energy per Machine (period)
// ══════════════════════════════════════════════════════════════════════════════
$stmtAvg = $db->prepare(
    "SELECT AVG(machine_total) AS avg_val
     FROM (
         SELECT machine_id, SUM(e_r + e_s + e_t) AS machine_total
         FROM sensor_readings sr
         WHERE DATE(recorded_at) BETWEEN :df AND :dt {$m_where}
         GROUP BY machine_id
     ) t"
);
$stmtAvg->execute([':df' => $date_from, ':dt' => $date_to]);
$kpi_avg = round((float)$stmtAvg->fetchColumn(), 2);

// ══════════════════════════════════════════════════════════════════════════════
// Chart 1 – Stacked bar: energy per day per machine
// ══════════════════════════════════════════════════════════════════════════════
$stmtStack = $db->prepare(
    "SELECT DATE(sr.recorded_at) AS day,
            m.name AS machine_name,
            SUM(sr.e_r + sr.e_s + sr.e_t) AS total
     FROM sensor_readings sr
     JOIN machines m ON m.id = sr.machine_id
     WHERE DATE(sr.recorded_at) BETWEEN :df AND :dt {$m_where}
     GROUP BY DATE(sr.recorded_at), sr.machine_id, m.name
     ORDER BY day ASC"
);
$stmtStack->execute([':df' => $date_from, ':dt' => $date_to]);
$stackRows = $stmtStack->fetchAll(PDO::FETCH_ASSOC);

// Build labels (dates) and datasets
$stackDates    = [];
$stackMachines = [];
foreach ($stackRows as $r) {
    $stackDates[$r['day']] = true;
    $stackMachines[$r['machine_name']] = true;
}
$stackDates    = array_keys($stackDates);
$stackMachines = array_keys($stackMachines);

// Pivot
$stackPivot = [];
foreach ($stackRows as $r) {
    $stackPivot[$r['machine_name']][$r['day']] = (float)$r['total'];
}

// Hitung total per mesin → ambil top 10, sisanya jadi "Lainnya"
$machineTotals = [];
foreach ($stackMachines as $mName) {
    $machineTotals[$mName] = array_sum($stackPivot[$mName] ?? []);
}
arsort($machineTotals);
$TOP_N       = 10;
$top10       = array_slice(array_keys($machineTotals), 0, $TOP_N);
$othersNames = array_slice(array_keys($machineTotals), $TOP_N);

// Pivot "Lainnya" — jumlahkan semua mesin sisanya per hari
$othersPivot = [];
foreach ($othersNames as $mName) {
    foreach ($stackDates as $d) {
        $othersPivot[$d] = ($othersPivot[$d] ?? 0) + ($stackPivot[$mName][$d] ?? 0);
    }
}

$chartColors = [
    '#4e73df','#1cc88a','#36b9cc','#f6c23e','#e74a3b',
    '#2e59d9','#17a673','#2c9faf','#ff6b6b','#a29bfe'
];
$stackDatasets = [];
$ci = 0;

// Top 10 datasets
foreach ($top10 as $mName) {
    $data = [];
    foreach ($stackDates as $d) {
        $data[] = isset($stackPivot[$mName][$d]) ? round($stackPivot[$mName][$d], 2) : 0;
    }
    $color = $chartColors[$ci % count($chartColors)];
    $stackDatasets[] = [
        'label'           => $mName,
        'data'            => $data,
        'backgroundColor' => $color,
        'borderColor'     => $color,
        'borderWidth'     => 1,
    ];
    $ci++;
}

// Dataset "Lainnya" jika ada mesin selain top 10
if (!empty($othersNames)) {
    $othersData = [];
    foreach ($stackDates as $d) {
        $othersData[] = round($othersPivot[$d] ?? 0, 2);
    }
    $stackDatasets[] = [
        'label'           => 'Lainnya (' . count($othersNames) . ' mesin)',
        'data'            => $othersData,
        'backgroundColor' => '#b2bec3',
        'borderColor'     => '#b2bec3',
        'borderWidth'     => 1,
    ];
}

$chartStackLabels   = json_encode($stackDates);
$chartStackDatasets = json_encode($stackDatasets);

// ══════════════════════════════════════════════════════════════════════════════
// Chart 2 – Hourly distribution (avg energy by hour 0-23)
// ══════════════════════════════════════════════════════════════════════════════
$stmtHourly = $db->prepare(
    "SELECT HOUR(recorded_at) AS hr,
            AVG(e_r + e_s + e_t) AS avg_total
     FROM sensor_readings sr
     WHERE DATE(recorded_at) BETWEEN :df AND :dt {$m_where}
     GROUP BY HOUR(recorded_at)
     ORDER BY hr ASC"
);
$stmtHourly->execute([':df' => $date_from, ':dt' => $date_to]);
$hourlyRows = $stmtHourly->fetchAll(PDO::FETCH_ASSOC);

$hourlyMap = [];
foreach ($hourlyRows as $r) {
    $hourlyMap[(int)$r['hr']] = round((float)$r['avg_total'], 3);
}
$hourlyLabels = [];
$hourlyData   = [];
for ($h = 0; $h < 24; $h++) {
    $hourlyLabels[] = sprintf('%02d:00', $h);
    $hourlyData[]   = isset($hourlyMap[$h]) ? $hourlyMap[$h] : 0;
}
$chartHourlyLabels = json_encode($hourlyLabels);
$chartHourlyData   = json_encode($hourlyData);

// ══════════════════════════════════════════════════════════════════════════════
// Chart 3 – Per-machine comparison (horizontal bar)
// ══════════════════════════════════════════════════════════════════════════════
$stmtComp = $db->prepare(
    "SELECT m.name, SUM(sr.e_r + sr.e_s + sr.e_t) AS total
     FROM sensor_readings sr
     JOIN machines m ON m.id = sr.machine_id
     WHERE DATE(sr.recorded_at) BETWEEN :df AND :dt {$m_where}
     GROUP BY sr.machine_id, m.name
     ORDER BY total DESC"
);
$stmtComp->execute([':df' => $date_from, ':dt' => $date_to]);
$compRows = $stmtComp->fetchAll(PDO::FETCH_ASSOC);

$compLabels = [];
$compData   = [];
$compColors = [];
$ci2 = 0;
foreach ($compRows as $r) {
    $compLabels[] = $r['name'];
    $compData[]   = round((float)$r['total'], 2);
    $compColors[] = $chartColors[$ci2 % count($chartColors)];
    $ci2++;
}
$chartCompLabels = json_encode($compLabels);
$chartCompData   = json_encode($compData);
$chartCompColors = json_encode($compColors);

// ══════════════════════════════════════════════════════════════════════════════
// Phase Balance Cards
// ══════════════════════════════════════════════════════════════════════════════
$stmtPhase = $db->prepare(
    "SELECT AVG(v_r) AS avg_vr, AVG(v_s) AS avg_vs, AVG(v_t) AS avg_vt
     FROM sensor_readings sr
     WHERE DATE(recorded_at) BETWEEN :df AND :dt {$m_where}"
);
$stmtPhase->execute([':df' => $date_from, ':dt' => $date_to]);
$phaseRow = $stmtPhase->fetch(PDO::FETCH_ASSOC);

$avg_vr = $phaseRow ? round((float)$phaseRow['avg_vr'], 1) : 0;
$avg_vs = $phaseRow ? round((float)$phaseRow['avg_vs'], 1) : 0;
$avg_vt = $phaseRow ? round((float)$phaseRow['avg_vt'], 1) : 0;

function phaseImbalance($vr, $vs, $vt) {
    if ($vr == 0 && $vs == 0 && $vt == 0) return ['pct' => 0, 'badge' => 'secondary', 'label' => 'No Data'];
    $avg = ($vr + $vs + $vt) / 3;
    if ($avg == 0) return ['pct' => 0, 'badge' => 'secondary', 'label' => 'No Data'];
    $max = max($vr, $vs, $vt);
    $min = min($vr, $vs, $vt);
    $pct = round(($max - $min) / $avg * 100, 2);
    if ($pct < 2)      return ['pct' => $pct, 'badge' => 'success', 'label' => 'Balanced'];
    elseif ($pct <= 5) return ['pct' => $pct, 'badge' => 'warning', 'label' => 'Slight Imbalance'];
    else               return ['pct' => $pct, 'badge' => 'danger',  'label' => 'Unbalanced'];
}

$phaseStatus = phaseImbalance($avg_vr, $avg_vs, $avg_vt);

// ══════════════════════════════════════════════════════════════════════════════
// DataTable – Energy Detail
// ══════════════════════════════════════════════════════════════════════════════
$stmtDetail = $db->prepare(
    "SELECT DATE(sr.recorded_at) AS day,
            m.name AS machine_name,
            ROUND(SUM(sr.e_r), 3)    AS sum_er,
            ROUND(SUM(sr.e_s), 3)    AS sum_es,
            ROUND(SUM(sr.e_t), 3)    AS sum_et,
            ROUND(SUM(sr.e_r + sr.e_s + sr.e_t), 3) AS total,
            ROUND(AVG((sr.v_r + sr.v_s + sr.v_t) / 3), 1) AS avg_v,
            ROUND(MAX(GREATEST(sr.a_r, sr.a_s, sr.a_t)), 2) AS peak_a
     FROM sensor_readings sr
     JOIN machines m ON m.id = sr.machine_id
     WHERE DATE(sr.recorded_at) BETWEEN :df AND :dt {$m_where}
     GROUP BY DATE(sr.recorded_at), sr.machine_id, m.name
     ORDER BY day DESC, total DESC"
);
$stmtDetail->execute([':df' => $date_from, ':dt' => $date_to]);
$detailRows = $stmtDetail->fetchAll(PDO::FETCH_ASSOC);

// ══════════════════════════════════════════════════════════════════════════════
// Build Export CSV URL params
// ══════════════════════════════════════════════════════════════════════════════
$exportParams = http_build_query([
    'action'     => 'e_report',
    'export'     => 'csv',
    'from'       => $date_from,
    'to'         => $date_to,
    'machine_id' => $machine_id,
]);

// ── HTML ──────────────────────────────────────────────────────────────────────
require_once 'includes/header.php';
?>

<!-- Page Heading -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        <i class="fas fa-bolt text-warning mr-2"></i>Laporan Energi
    </h1>
    <button class="btn btn-sm btn-success shadow-sm" id="btnExportCsv">
        <i class="fas fa-file-csv fa-sm text-white-50 mr-1"></i> Export CSV
    </button>
</div>

<!-- ── FILTER BAR ─────────────────────────────────────────────────────────── -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-filter mr-1"></i>Filter</h6>
    </div>
    <div class="card-body">
        <form method="GET" action="energy.php" class="form-inline flex-wrap" id="filterForm">
            <div class="form-group mr-3 mb-2">
                <label class="mr-2 font-weight-bold">Mesin:</label>
                <select name="machine_id" class="form-control form-control-sm">
                    <option value="0" <?php echo $machine_id == 0 ? 'selected' : ''; ?>>-- Semua Mesin --</option>
                    <?php foreach ($machine_list as $m): ?>
                        <option value="<?php echo $m['id']; ?>" <?php echo $machine_id == $m['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($m['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group mr-3 mb-2">
                <label class="mr-2 font-weight-bold">Dari:</label>
                <input type="date" name="date_from" class="form-control form-control-sm"
                       value="<?php echo htmlspecialchars($date_from); ?>">
            </div>
            <div class="form-group mr-3 mb-2">
                <label class="mr-2 font-weight-bold">Sampai:</label>
                <input type="date" name="date_to" class="form-control form-control-sm"
                       value="<?php echo htmlspecialchars($date_to); ?>">
            </div>
            <div class="form-group mr-3 mb-2">
                <label class="mr-2 font-weight-bold">Group By:</label>
                <select name="group_by" class="form-control form-control-sm">
                    <option value="day"  <?php echo $group_by === 'day'  ? 'selected' : ''; ?>>Hari</option>
                    <option value="hour" <?php echo $group_by === 'hour' ? 'selected' : ''; ?>>Jam</option>
                </select>
            </div>
            <div class="form-group mb-2">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-search mr-1"></i>Terapkan
                </button>
                <a href="energy.php" class="btn btn-secondary btn-sm ml-2">
                    <i class="fas fa-redo mr-1"></i>Reset
                </a>
            </div>
        </form>
        <small class="text-muted">
            Periode: <strong><?php echo date('d M Y', strtotime($date_from)); ?></strong>
            s/d <strong><?php echo date('d M Y', strtotime($date_to)); ?></strong>
        </small>
    </div>
</div>

<!-- ── KPI CARDS ─────────────────────────────────────────────────────────── -->
<div class="row">
    <!-- KPI 1: Total Energy Today -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Total Energi Hari Ini
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($kpi_today, 2); ?> kWh
                        </div>
                        <div class="text-xs text-muted mt-1"><?php echo date('d M Y'); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-bolt fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- KPI 2: Peak Hour -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-danger shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                            Jam Puncak (Periode)
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo sprintf('%02d:00', $kpi_peak_hr); ?>
                        </div>
                        <div class="text-xs text-muted mt-1">
                            <?php echo number_format($kpi_peak_val, 2); ?> kWh total
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-clock fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- KPI 3: Most Consuming Machine -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Mesin Paling Boros
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo htmlspecialchars($kpi_top_machine); ?>
                        </div>
                        <div class="text-xs text-muted mt-1">
                            <?php echo number_format($kpi_top_val, 2); ?> kWh
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-cogs fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- KPI 4: Avg per Machine -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Rata-rata per Mesin
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($kpi_avg, 2); ?> kWh
                        </div>
                        <div class="text-xs text-muted mt-1">Dalam periode yang dipilih</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-chart-bar fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── MAIN STACKED BAR + HOURLY CHART ───────────────────────────────────── -->
<div class="row">
    <!-- Stacked Bar Chart -->
    <div class="col-xl-8 col-lg-7 mb-4">
        <div class="card shadow">
            <div class="card-header py-3 d-flex align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-chart-bar mr-1"></i>Energi per Hari per Mesin (kWh)
                </h6>
                <div class="d-flex align-items-center gap-2" style="gap:8px;">
                  <select id="stackMachineSelect" class="form-control form-control-sm" style="min-width:200px;max-width:300px;">
                    <option value="__top10__">— Top 10 Tertinggi —</option>
                    <?php foreach (array_keys($machineTotals) as $mName): ?>
                    <option value="<?= htmlspecialchars($mName) ?>"><?= htmlspecialchars($mName) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($stackDates)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-chart-bar fa-3x mb-3 d-block"></i>
                        Tidak ada data untuk periode ini.
                    </div>
                <?php else: ?>
                    <div class="chart-bar" style="position:relative; height:320px;">
                        <canvas id="chartStacked"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Hourly Distribution Chart -->
    <div class="col-xl-4 col-lg-5 mb-4">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-clock mr-1"></i>Distribusi Jam (Avg kWh)
                </h6>
            </div>
            <div class="card-body">
                <div style="position:relative; height:320px;">
                    <canvas id="chartHourly"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── PER-MACHINE COMPARISON + PHASE BALANCE ────────────────────────────── -->
<div class="row">
    <!-- Per-Machine Horizontal Bar -->
    <div class="col-xl-7 col-lg-7 mb-4">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-exchange-alt mr-1"></i>Perbandingan Konsumsi per Mesin
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($compLabels)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-chart-bar fa-3x mb-3 d-block"></i>
                        Tidak ada data untuk periode ini.
                    </div>
                <?php else: ?>
                    <div style="position:relative; min-height:200px; height:<?php echo max(200, count($compLabels) * 40); ?>px;">
                        <canvas id="chartComp"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Phase Balance Cards -->
    <div class="col-xl-5 col-lg-5 mb-4">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-project-diagram mr-1"></i>Keseimbangan Tegangan Fasa
                </h6>
            </div>
            <div class="card-body">
                <!-- Overall imbalance badge -->
                <div class="text-center mb-3">
                    <span class="badge badge-<?php echo $phaseStatus['badge']; ?> badge-pill px-3 py-2" style="font-size:0.9rem;">
                        <?php echo $phaseStatus['label']; ?>
                        &mdash; Imbalance: <?php echo $phaseStatus['pct']; ?>%
                    </span>
                </div>
                <div class="row">
                    <!-- V_R -->
                    <div class="col-md-4 mb-3">
                        <div class="card border-left-danger h-100 py-2 shadow-sm">
                            <div class="card-body text-center p-2">
                                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Fasa R (V_R)</div>
                                <div class="h4 font-weight-bold text-gray-800"><?php echo number_format($avg_vr, 1); ?> V</div>
                                <div class="text-xs text-muted">Rata-rata</div>
                                <?php
                                $vr_dev = ($avg_vr + $avg_vs + $avg_vt) > 0
                                    ? round(abs($avg_vr - ($avg_vr + $avg_vs + $avg_vt) / 3), 2) : 0;
                                ?>
                                <div class="text-xs mt-1">
                                    <span class="badge badge-<?php echo $vr_dev < 5 ? 'success' : 'warning'; ?>">
                                        &Delta;<?php echo $vr_dev; ?> V
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- V_S -->
                    <div class="col-md-4 mb-3">
                        <div class="card border-left-warning h-100 py-2 shadow-sm">
                            <div class="card-body text-center p-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Fasa S (V_S)</div>
                                <div class="h4 font-weight-bold text-gray-800"><?php echo number_format($avg_vs, 1); ?> V</div>
                                <div class="text-xs text-muted">Rata-rata</div>
                                <?php
                                $vs_dev = ($avg_vr + $avg_vs + $avg_vt) > 0
                                    ? round(abs($avg_vs - ($avg_vr + $avg_vs + $avg_vt) / 3), 2) : 0;
                                ?>
                                <div class="text-xs mt-1">
                                    <span class="badge badge-<?php echo $vs_dev < 5 ? 'success' : 'warning'; ?>">
                                        &Delta;<?php echo $vs_dev; ?> V
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- V_T -->
                    <div class="col-md-4 mb-3">
                        <div class="card border-left-primary h-100 py-2 shadow-sm">
                            <div class="card-body text-center p-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Fasa T (V_T)</div>
                                <div class="h4 font-weight-bold text-gray-800"><?php echo number_format($avg_vt, 1); ?> V</div>
                                <div class="text-xs text-muted">Rata-rata</div>
                                <?php
                                $vt_dev = ($avg_vr + $avg_vs + $avg_vt) > 0
                                    ? round(abs($avg_vt - ($avg_vr + $avg_vs + $avg_vt) / 3), 2) : 0;
                                ?>
                                <div class="text-xs mt-1">
                                    <span class="badge badge-<?php echo $vt_dev < 5 ? 'success' : 'warning'; ?>">
                                        &Delta;<?php echo $vt_dev; ?> V
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Legend -->
                <div class="text-xs text-muted mt-2">
                    <strong>Status:</strong>
                    <span class="badge badge-success ml-1">&lt;2% Balanced</span>
                    <span class="badge badge-warning ml-1">2–5% Slight Imbalance</span>
                    <span class="badge badge-danger ml-1">&gt;5% Unbalanced</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── ENERGY DETAIL DATATABLE ───────────────────────────────────────────── -->
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex align-items-center justify-content-between">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-table mr-1"></i>Detail Energi
        </h6>
        <button class="btn btn-sm btn-success" id="btnExportCsv2">
            <i class="fas fa-file-csv mr-1"></i>Export CSV
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover table-sm dataTable no-auto-init" id="energyDetailTable" width="100%">
                <thead class="thead-dark">
                    <tr>
                        <th>Tanggal</th>
                        <th>Mesin</th>
                        <th>E_R (kWh)</th>
                        <th>E_S (kWh)</th>
                        <th>E_T (kWh)</th>
                        <th>Total (kWh)</th>
                        <th>Avg Tegangan (V)</th>
                        <th>Peak Arus (A)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($detailRows)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted">Tidak ada data untuk periode ini.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($detailRows as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(date('d M Y', strtotime($row['day']))); ?></td>
                                <td><?php echo htmlspecialchars($row['machine_name']); ?></td>
                                <td class="text-right"><?php echo number_format($row['sum_er'], 3); ?></td>
                                <td class="text-right"><?php echo number_format($row['sum_es'], 3); ?></td>
                                <td class="text-right"><?php echo number_format($row['sum_et'], 3); ?></td>
                                <td class="text-right font-weight-bold"><?php echo number_format($row['total'], 3); ?></td>
                                <td class="text-right"><?php echo number_format($row['avg_v'], 1); ?></td>
                                <td class="text-right"><?php echo number_format($row['peak_a'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($detailRows)): ?>
                <tfoot>
                    <tr class="table-dark font-weight-bold">
                        <td class="font-weight-bold">TOTAL</td>
                        <td></td>
                        <td class="text-right"><?php echo number_format(array_sum(array_column($detailRows, 'sum_er')), 3); ?></td>
                        <td class="text-right"><?php echo number_format(array_sum(array_column($detailRows, 'sum_es')), 3); ?></td>
                        <td class="text-right"><?php echo number_format(array_sum(array_column($detailRows, 'sum_et')), 3); ?></td>
                        <td class="text-right"><?php echo number_format(array_sum(array_column($detailRows, 'total')), 3); ?></td>
                        <td></td>
                        <td></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<!-- ── ALL SCRIPTS AT BOTTOM ─────────────────────────────────────────────── -->
<script>
(function () {
    // ── Export CSV ────────────────────────────────────────────────────────────
    var exportUrl = 'api/reports.php?<?php echo $exportParams; ?>';
    document.getElementById('btnExportCsv').addEventListener('click', function () {
        window.location = exportUrl;
    });
    document.getElementById('btnExportCsv2').addEventListener('click', function () {
        window.location = exportUrl;
    });

    // ── Chart 1 – Stacked Bar ─────────────────────────────────────────────────
    var stackLabels    = <?php echo $chartStackLabels; ?>;
    var stackDatasets  = <?php echo $chartStackDatasets; ?>;  // top10 + lainnya (default)
    var allStackPivot  = <?php echo json_encode($stackPivot); ?>;
    var allStackDates  = <?php echo json_encode($stackDates); ?>;
    var chartColors    = ['#4e73df','#1cc88a','#36b9cc','#f6c23e','#e74a3b','#2e59d9','#17a673','#2c9faf','#ff6b6b','#a29bfe','#fd79a8','#00cec9'];

    if (document.getElementById('chartStacked') && stackLabels.length > 0) {
        var ctxStack = document.getElementById('chartStacked').getContext('2d');

        var stackChartOptions = {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                xAxes: [{ stacked: true, gridLines: { display: false }, ticks: { maxTicksLimit: 14, fontSize: 11 } }],
                yAxes: [{ stacked: true, ticks: { beginAtZero: true, fontSize: 11,
                    callback: function (v) { return v.toLocaleString() + ' kWh'; }
                }, gridLines: { color: 'rgba(0,0,0,.05)' } }]
            },
            tooltips: {
                mode: 'index', intersect: false,
                backgroundColor: 'rgba(17,24,39,.9)', titleFontSize: 12, bodyFontSize: 11,
                callbacks: {
                    title: function(items){ return 'Tanggal: ' + items[0].label; },
                    label: function(item, data){
                        if (item.yLabel === 0) return null;
                        return '  ' + data.datasets[item.datasetIndex].label + ': ' + item.yLabel.toFixed(1) + ' kWh';
                    },
                    footer: function(items){
                        var total = items.reduce(function(s,i){ return s + i.yLabel; }, 0);
                        return 'Total: ' + total.toFixed(1) + ' kWh';
                    }
                }
            },
            legend: { position: 'bottom', labels: { fontSize: 11, padding: 12, usePointStyle: true, pointStyle: 'rect' } },
            layout: { padding: { top: 8 } }
        };

        var stackChart = new Chart(ctxStack, {
            type: 'bar',
            data: { labels: stackLabels, datasets: stackDatasets },
            options: stackChartOptions
        });

        /* ── Dropdown handler ── */
        var sel = document.getElementById('stackMachineSelect');
        if (sel) {
            sel.addEventListener('change', function(){
                var val = this.value;
                var newDatasets;

                if (val === '__top10__') {
                    // Kembalikan ke top10 + lainnya
                    newDatasets = stackDatasets;
                } else {
                    // Tampilkan hanya mesin yang dipilih
                    var data = allStackDates.map(function(d){
                        return allStackPivot[val] && allStackPivot[val][d] ? +parseFloat(allStackPivot[val][d]).toFixed(2) : 0;
                    });
                    newDatasets = [{
                        label: val,
                        data: data,
                        backgroundColor: '#4e73df',
                        borderColor: '#4e73df',
                        borderWidth: 1
                    }];
                }

                stackChart.data.datasets = newDatasets;
                stackChart.update();
            });
        }
    }

    // ── Chart 2 – Hourly Distribution ────────────────────────────────────────
    var hourlyLabels = <?php echo $chartHourlyLabels; ?>;
    var hourlyData   = <?php echo $chartHourlyData; ?>;

    if (document.getElementById('chartHourly')) {
        var ctxHourly = document.getElementById('chartHourly').getContext('2d');
        new Chart(ctxHourly, {
            type: 'bar',
            data: {
                labels: hourlyLabels,
                datasets: [{
                    label: 'Avg kWh',
                    data: hourlyData,
                    backgroundColor: 'rgba(78, 115, 223, 0.7)',
                    borderColor: 'rgba(78, 115, 223, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    xAxes: [{
                        gridLines: { display: false },
                        ticks: { maxRotation: 45, minRotation: 45 }
                    }],
                    yAxes: [{
                        ticks: {
                            beginAtZero: true,
                            callback: function (v) { return v + ' kWh'; }
                        }
                    }]
                },
                tooltips: {
                    callbacks: {
                        label: function (item) {
                            return 'Avg: ' + parseFloat(item.yLabel).toFixed(3) + ' kWh';
                        }
                    }
                },
                legend: { display: false }
            }
        });
    }

    // ── Chart 3 – Per-Machine Horizontal Bar ──────────────────────────────────
    var compLabels = <?php echo $chartCompLabels; ?>;
    var compData   = <?php echo $chartCompData; ?>;
    var compColors = <?php echo $chartCompColors; ?>;

    if (document.getElementById('chartComp') && compLabels.length > 0) {
        var ctxComp = document.getElementById('chartComp').getContext('2d');
        new Chart(ctxComp, {
            type: 'horizontalBar',
            data: {
                labels: compLabels,
                datasets: [{
                    label: 'Total kWh',
                    data: compData,
                    backgroundColor: compColors,
                    borderColor: compColors,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    xAxes: [{
                        ticks: {
                            beginAtZero: true,
                            callback: function (v) { return v + ' kWh'; }
                        }
                    }],
                    yAxes: [{
                        gridLines: { display: false }
                    }]
                },
                tooltips: {
                    callbacks: {
                        label: function (item) {
                            return parseFloat(item.xLabel).toFixed(2) + ' kWh';
                        }
                    }
                },
                legend: { display: false }
            }
        });
    }

    // ── DataTable ─────────────────────────────────────────────────────────────
    $(document).ready(function () {
        safeDataTable('#energyDetailTable', {
            order: [[0, 'desc']],
            pageLength: 25,
            columnDefs: [{ targets: [2, 3, 4, 5, 6, 7], className: 'text-right' }]
        });
    });
})();
</script>
