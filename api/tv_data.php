<?php
// ============================================================
//  api/tv_data.php — JSON feed untuk TV Dashboard AJAX refresh
// ============================================================
require_once '../includes/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

$db = getDB();

// ── Mesin + data terbaru (JOIN derived tables, bukan correlated subquery) ──
$machines = $db->query("
    SELECT m.id, m.name, m.status, m.model,
           pl.name AS line_name,
           od.availability, od.performance, od.quality,
           ROUND(od.availability * od.performance * od.quality / 10000,1) AS oee_pct,
           sr.v_r, sr.a_r, sr.temp_panel, sr.hum_panel,
           sr.recorded_at AS sensor_at,
           vr.rms_overall, vr.status AS vib_status
    FROM machines m
    LEFT JOIN production_lines pl ON pl.id = m.line_id
    LEFT JOIN (
        SELECT od2.machine_id, od2.availability, od2.performance, od2.quality
        FROM oee_daily od2
        INNER JOIN (SELECT machine_id, MAX(snap_date) AS mx FROM oee_daily GROUP BY machine_id) lx
            ON lx.machine_id = od2.machine_id AND lx.mx = od2.snap_date
    ) od ON od.machine_id = m.id
    LEFT JOIN (
        SELECT sr2.machine_id, sr2.v_r, sr2.a_r, sr2.temp_panel, sr2.hum_panel, sr2.recorded_at
        FROM sensor_readings sr2
        INNER JOIN (SELECT machine_id, MAX(recorded_at) AS mx FROM sensor_readings GROUP BY machine_id) ls
            ON ls.machine_id = sr2.machine_id AND ls.mx = sr2.recorded_at
    ) sr ON sr.machine_id = m.id
    LEFT JOIN (
        SELECT vr2.machine_id, vr2.rms_overall, vr2.status
        FROM vibration_readings vr2
        INNER JOIN (SELECT machine_id, MAX(recorded_at) AS mx FROM vibration_readings GROUP BY machine_id) lv
            ON lv.machine_id = vr2.machine_id AND lv.mx = vr2.recorded_at
    ) vr ON vr.machine_id = m.id
    ORDER BY pl.name, m.sort_order, m.name
")->fetchAll();

$totalMachines = count($machines);
$machineRun    = count(array_filter($machines, fn($m) => $m['status'] === 'run'));
$oeeVals       = array_filter(array_column($machines,'oee_pct'), fn($v) => $v > 0);
$avgOEE        = $oeeVals ? round(array_sum($oeeVals)/count($oeeVals),1) : 0;
$oeeExcellent  = count(array_filter($oeeVals, fn($v) => $v >= 85));
$oeeGood       = count(array_filter($oeeVals, fn($v) => $v >= 65 && $v < 85));
$oeePoor       = count(array_filter($oeeVals, fn($v) => $v < 65 && $v > 0));

// ── Line stats ────────────────────────────────────────────────
$lineStats = [];
foreach ($machines as $m) {
    $ln = $m['line_name'] ?? 'Umum';
    if (!isset($lineStats[$ln])) $lineStats[$ln] = ['total'=>0,'run'=>0,'oee_sum'=>0,'oee_cnt'=>0,'avg_oee'=>0];
    $lineStats[$ln]['total']++;
    if ($m['status'] === 'run') $lineStats[$ln]['run']++;
    if ($m['oee_pct'] > 0) { $lineStats[$ln]['oee_sum'] += $m['oee_pct']; $lineStats[$ln]['oee_cnt']++; }
}
foreach ($lineStats as $k => &$ls) {
    $ls['avg_oee'] = $ls['oee_cnt'] ? round($ls['oee_sum']/$ls['oee_cnt'],1) : 0;
    $ls['name']    = $k;
    unset($ls['oee_sum'],$ls['oee_cnt']);
}
unset($ls);

// ── Alerts ───────────────────────────────────────────────────
$alertCount    = (int)$db->query("SELECT COUNT(*) FROM alerts WHERE acknowledged=0")->fetchColumn();
$alertCritical = (int)$db->query("SELECT COUNT(*) FROM alerts WHERE acknowledged=0 AND severity='critical'")->fetchColumn();

// ── Abnormal machines (JOIN derived tables) ───────────────────
$abnormal = $db->query("
    SELECT m.name, m.status, pl.name AS line_name,
           vr.rms_overall, vr.vib_status,
           vr.vib_x, vr.vib_y, vr.vib_z,
           sr.v_r, sr.v_s, sr.v_t, sr.a_r, sr.a_s, sr.a_t,
           sr.temp_panel, sr.hum_panel,
           ROUND(od.availability*od.performance*od.quality/10000,1) AS oee_pct
    FROM machines m
    LEFT JOIN production_lines pl ON pl.id = m.line_id
    LEFT JOIN (
        SELECT vr2.machine_id, vr2.rms_overall, vr2.status AS vib_status,
               vr2.sensor_1 AS vib_x, vr2.sensor_2 AS vib_y, vr2.sensor_3 AS vib_z
        FROM vibration_readings vr2
        INNER JOIN (SELECT machine_id, MAX(recorded_at) AS mx FROM vibration_readings GROUP BY machine_id) lv
            ON lv.machine_id = vr2.machine_id AND lv.mx = vr2.recorded_at
    ) vr ON vr.machine_id = m.id
    LEFT JOIN (
        SELECT sr2.machine_id, sr2.v_r, sr2.v_s, sr2.v_t,
               sr2.a_r, sr2.a_s, sr2.a_t, sr2.temp_panel, sr2.hum_panel
        FROM sensor_readings sr2
        INNER JOIN (SELECT machine_id, MAX(recorded_at) AS mx FROM sensor_readings GROUP BY machine_id) ls
            ON ls.machine_id = sr2.machine_id AND ls.mx = sr2.recorded_at
    ) sr ON sr.machine_id = m.id
    LEFT JOIN (
        SELECT od2.machine_id, od2.availability, od2.performance, od2.quality
        FROM oee_daily od2
        INNER JOIN (SELECT machine_id, MAX(snap_date) AS mx FROM oee_daily GROUP BY machine_id) lx
            ON lx.machine_id = od2.machine_id AND lx.mx = od2.snap_date
    ) od ON od.machine_id = m.id
    WHERE m.status = 'stop' OR vr.vib_status IN ('warning','critical')
    ORDER BY CASE vr.vib_status WHEN 'critical' THEN 1 WHEN 'warning' THEN 2 ELSE 3 END,
             CASE m.status WHEN 'stop' THEN 1 ELSE 2 END, m.name
")->fetchAll();

// ── OEE trend per mesin ───────────────────────────────────────
$dateRows = array_reverse($db->query("
    SELECT DISTINCT snap_date FROM oee_daily ORDER BY snap_date DESC LIMIT 14
")->fetchAll(PDO::FETCH_COLUMN));

$trendRaw = $db->query("
    SELECT machine_id, snap_date, ROUND(availability*performance*quality/10000,1) AS oee
    FROM oee_daily
    WHERE snap_date >= (SELECT MIN(snap_date) FROM (
        SELECT snap_date FROM oee_daily ORDER BY snap_date DESC LIMIT 14
    ) sub)
    ORDER BY machine_id, snap_date
")->fetchAll();

$trendMap = [];
foreach ($trendRaw as $r) $trendMap[$r['machine_id']][$r['snap_date']] = (float)$r['oee'];

$chartMachines = [];
foreach ($machines as $m) {
    if (!isset($trendMap[$m['id']])) continue;
    $pts = [];
    foreach ($dateRows as $d) $pts[] = $trendMap[$m['id']][$d] ?? null;
    if (!count(array_filter($pts, fn($v) => $v !== null))) continue;
    $chartMachines[] = ['id'=>$m['id'],'name'=>$m['name'],'line'=>$m['line_name']??'Umum','data'=>$pts,'oee'=>(float)($m['oee_pct']??0)];
}

echo json_encode([
    'ts'           => date('H:i:s'),
    'summary'      => [
        'total'        => $totalMachines,
        'run'          => $machineRun,
        'stop'         => $totalMachines - $machineRun,
        'avg_oee'      => $avgOEE,
        'oee_excellent'=> $oeeExcellent,
        'oee_good'     => $oeeGood,
        'oee_poor'     => $oeePoor,
        'alert_count'  => $alertCount,
        'alert_critical'=> $alertCritical,
    ],
    'machines'     => $machines,
    'line_stats'   => array_values($lineStats),
    'abnormal'     => $abnormal,
    'chart_labels' => array_map(fn($d) => date('d/m', strtotime($d)), $dateRows),
    'chart_machines'=> $chartMachines,
], JSON_UNESCAPED_UNICODE);
