<?php
// ============================================================
//  api/dashboard_data.php — JSON feed untuk Dashboard AJAX
//  Optimised: no correlated subqueries, minimal queries
// ============================================================
require_once '../includes/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store');

$db = getDB();

// ── Tanggal OEE terbaru (1 query, di-cache di sini) ──────────
$latestOeeDate   = $db->query("SELECT MAX(snap_date) FROM oee_daily")->fetchColumn();
$latestSensorTS  = $db->query("SELECT MAX(recorded_at) FROM sensor_readings")->fetchColumn();

// ── KPI (1 query gabungan) ────────────────────────────────────
$kpiRow = $db->query("
    SELECT
        COUNT(*)                                          AS total,
        SUM(status = 'run')                               AS running,
        ROUND(
            (SELECT AVG(oee_score) FROM oee_daily
             WHERE snap_date = '$latestOeeDate'), 1)      AS avg_oee
    FROM machines
")->fetch();

$totalMachines   = (int)$kpiRow['total'];
$runningMachines = (int)$kpiRow['running'];
$kpiOee          = (float)$kpiRow['avg_oee'];

// Alert counts (1 query) ──────────────────────────────────────
$alertRows = $db->query("
    SELECT severity, COUNT(*) AS cnt
    FROM alerts WHERE acknowledged = 0
    GROUP BY severity
")->fetchAll();
$totalAlerts = $alertHigh = $alertMedium = $alertLow = 0;
foreach ($alertRows as $r) {
    $totalAlerts += $r['cnt'];
    match($r['severity']) {
        'high'   => $alertHigh   = (int)$r['cnt'],
        'medium' => $alertMedium = (int)$r['cnt'],
        'low'    => $alertLow    = (int)$r['cnt'],
        default  => null,
    };
}

// Maintenance due (1 query) ───────────────────────────────────
$maintenanceDue = (int)$db->query("
    SELECT COUNT(*) FROM maintenance_records
    WHERE maint_date BETWEEN CURDATE()
          AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
")->fetchColumn();

// ── Donut distribution (1 query) ─────────────────────────────
$distRow = $db->query("
    SELECT
        SUM(oee_score >= 85)                   AS hi,
        SUM(oee_score >= 60 AND oee_score < 85) AS mid,
        SUM(oee_score >  0  AND oee_score < 60) AS lo
    FROM oee_daily
    WHERE snap_date = '$latestOeeDate'
")->fetch();
$distHigh = (int)$distRow['hi'];
$distMid  = (int)$distRow['mid'];
$distLow  = (int)$distRow['lo'];

// ── Recent Alerts (1 query) ───────────────────────────────────
$recentAlerts = $db->query("
    SELECT a.severity, a.sensor_key, a.sensor_value,
           a.threshold_lo, a.threshold_hi, a.created_at,
           m.name AS machine_name
    FROM alerts a
    LEFT JOIN machines m ON m.id = a.machine_id
    WHERE a.acknowledged = 0
    ORDER BY a.created_at DESC
    LIMIT 6
")->fetchAll();
$now = time();
foreach ($recentAlerts as &$a) {
    $diff = $now - strtotime($a['created_at'] ?? 'now');
    $a['ago'] = $diff < 60 ? "{$diff}s ago"
        : ($diff < 3600  ? round($diff/60).'m ago'
        : ($diff < 86400 ? round($diff/3600).'h ago'
        :                  round($diff/86400).'d ago'));
}
unset($a);

// ── Upcoming Maintenance (1 query) ───────────────────────────
$upcomingMaint = $db->query("
    SELECT mr.type, mr.description, mr.technician, mr.maint_date,
           m.name AS machine_name
    FROM maintenance_records mr
    LEFT JOIN machines m ON m.id = mr.machine_id
    WHERE mr.maint_date >= CURDATE()
    ORDER BY mr.maint_date ASC
    LIMIT 6
")->fetchAll();

// ── Machine Table (skip jika ?notbl=1 → refresh cepat KPI saja) ─
$skipTable = isset($_GET['notbl']);
$machines  = [];
if (!$skipTable) $machines = $db->query("
    SELECT m.id, m.name, m.status,
           pl.name  AS line_name,
           d.oee_score, d.availability, d.performance, d.quality,
           sr.recorded_at AS last_reading
    FROM machines m
    LEFT JOIN production_lines pl ON pl.id = m.line_id
    LEFT JOIN (
        SELECT od.machine_id,
               od.oee_score, od.availability, od.performance, od.quality
        FROM oee_daily od
        INNER JOIN (
            SELECT machine_id, MAX(snap_date) AS mx
            FROM oee_daily GROUP BY machine_id
        ) lx ON lx.machine_id = od.machine_id AND lx.mx = od.snap_date
    ) d ON d.machine_id = m.id
    LEFT JOIN (
        SELECT machine_id, MAX(recorded_at) AS recorded_at
        FROM sensor_readings GROUP BY machine_id
    ) sr ON sr.machine_id = m.id
    ORDER BY pl.name, m.sort_order, m.name
")->fetchAll(); // end if(!$skipTable)

// ── JSON output ───────────────────────────────────────────────
echo json_encode([
    'ts'      => date('H:i:s'),
    'kpi'     => [
        'oee'             => $kpiOee,
        'running'         => $runningMachines,
        'total'           => $totalMachines,
        'stopped'         => $totalMachines - $runningMachines,
        'alerts_total'    => $totalAlerts,
        'alerts_high'     => $alertHigh,
        'alerts_medium'   => $alertMedium,
        'alerts_low'      => $alertLow,
        'maintenance_due' => $maintenanceDue,
    ],
    'donut'   => [$distLow, $distMid, $distHigh],
    'alerts'  => $recentAlerts,
    'maint'   => $upcomingMaint,
    'machines'=> $machines,
], JSON_UNESCAPED_UNICODE);
