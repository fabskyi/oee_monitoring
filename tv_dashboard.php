<?php
// ============================================================
//  tv_dashboard.php – Smart TV Wallboard
// ============================================================
require_once 'includes/config.php';
if (session_status() === PHP_SESSION_NONE)
  session_start();

$db = getDB();

// ── All machines + latest data ───────────────────────────────
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
    LEFT JOIN oee_daily od ON od.machine_id = m.id
        AND od.snap_date = (SELECT MAX(x.snap_date) FROM oee_daily x WHERE x.machine_id = m.id)
    LEFT JOIN sensor_readings sr ON sr.machine_id = m.id
        AND sr.recorded_at = (SELECT MAX(x.recorded_at) FROM sensor_readings x WHERE x.machine_id = m.id)
    LEFT JOIN vibration_readings vr ON vr.machine_id = m.id
        AND vr.recorded_at = (SELECT MAX(x.recorded_at) FROM vibration_readings x WHERE x.machine_id = m.id)
    ORDER BY pl.name, m.sort_order, m.name
")->fetchAll();

$totalMachines = count($machines);
$machineRun = count(array_filter($machines, fn($m) => $m['status'] === 'run'));
$machineStop = $totalMachines - $machineRun;

$oeeVals = array_filter(array_column($machines, 'oee_pct'), fn($v) => $v > 0);
$avgOEE = $oeeVals ? round(array_sum($oeeVals) / count($oeeVals), 1) : 0;
$oeeExcellent = count(array_filter($oeeVals, fn($v) => $v >= 85));
$oeeGood = count(array_filter($oeeVals, fn($v) => $v >= 65 && $v < 85));
$oeePoor = count(array_filter($oeeVals, fn($v) => $v < 65 && $v > 0));

// ── Breakdown by line ────────────────────────────────────────
$lineStats = [];
foreach ($machines as $m) {
  $ln = $m['line_name'] ?? 'General';
  if (!isset($lineStats[$ln]))
    $lineStats[$ln] = ['total' => 0, 'run' => 0, 'oee_sum' => 0, 'oee_cnt' => 0];
  $lineStats[$ln]['total']++;
  if ($m['status'] === 'run')
    $lineStats[$ln]['run']++;
  if ($m['oee_pct'] > 0) {
    $lineStats[$ln]['oee_sum'] += $m['oee_pct'];
    $lineStats[$ln]['oee_cnt']++;
  }
}
foreach ($lineStats as &$ls)
  $ls['avg_oee'] = $ls['oee_cnt'] ? round($ls['oee_sum'] / $ls['oee_cnt'], 1) : 0;
unset($ls);

// ── Alerts ───────────────────────────────────────────────────
$alertCount = (int) $db->query("SELECT COUNT(*) FROM alerts WHERE acknowledged=0")->fetchColumn();
$alertCritical = (int) $db->query("SELECT COUNT(*) FROM alerts WHERE acknowledged=0 AND severity='critical'")->fetchColumn();
$alertWarning = (int) $db->query("SELECT COUNT(*) FROM alerts WHERE acknowledged=0 AND severity='warning'")->fetchColumn();

$recentAlerts = $db->query("
    SELECT a.created_at, m.name AS machine_name,
           a.sensor_key, a.sensor_value, a.severity
    FROM alerts a LEFT JOIN machines m ON m.id = a.machine_id
    WHERE a.acknowledged=0
    ORDER BY a.severity DESC, a.created_at DESC LIMIT 8
")->fetchAll();

// Machines with abnormal conditions (vibration warning/critical or stopped status)
$abnormalMachines = $db->query("
    SELECT m.name, m.status,
           pl.name AS line_name,
           vr.rms_overall, vr.status AS vib_status,
           vr.sensor_1 AS vib_x, vr.sensor_2 AS vib_y, vr.sensor_3 AS vib_z,
           sr.v_r, sr.v_s, sr.v_t, sr.a_r, sr.a_s, sr.a_t,
           sr.temp_panel, sr.hum_panel, sr.f_r, sr.e_r, sr.e_s, sr.e_t,
           ROUND(od.availability*od.performance*od.quality/10000,1) AS oee_pct
    FROM machines m
    LEFT JOIN production_lines pl ON pl.id = m.line_id
    LEFT JOIN vibration_readings vr ON vr.machine_id = m.id
        AND vr.recorded_at = (SELECT MAX(x.recorded_at) FROM vibration_readings x WHERE x.machine_id = m.id)
    LEFT JOIN sensor_readings sr ON sr.machine_id = m.id
        AND sr.recorded_at = (SELECT MAX(x.recorded_at) FROM sensor_readings x WHERE x.machine_id = m.id)
    LEFT JOIN oee_daily od ON od.machine_id = m.id
        AND od.snap_date = (SELECT MAX(x.snap_date) FROM oee_daily x WHERE x.machine_id = m.id)
    WHERE m.status = 'stop' OR vr.status IN ('warning','critical')
    ORDER BY
        CASE vr.status WHEN 'critical' THEN 1 WHEN 'warning' THEN 2 ELSE 3 END,
        CASE m.status WHEN 'stop' THEN 1 ELSE 2 END,
        m.name
")->fetchAll();

// ── OEE Trend 14 days per machine ────────────────────────────
// Fetch all dates from the last 14 days
$dateRows = array_reverse($db->query("
    SELECT DISTINCT snap_date FROM oee_daily
    ORDER BY snap_date DESC LIMIT 14
")->fetchAll(PDO::FETCH_COLUMN));

// All data per machine
$trendRaw = $db->query("
    SELECT machine_id,
           snap_date,
           ROUND(availability*performance*quality/10000,1) AS oee
    FROM oee_daily
    WHERE snap_date >= (SELECT MIN(snap_date) FROM (
        SELECT snap_date FROM oee_daily ORDER BY snap_date DESC LIMIT 14
    ) sub)
    ORDER BY machine_id, snap_date
")->fetchAll();

// Build lookup [machine_id][snap_date] = oee
$trendMap = [];
foreach ($trendRaw as $r) {
  $trendMap[$r['machine_id']][$r['snap_date']] = (float) $r['oee'];
}

// Build chart dataset per machine — only machines with trend data
$chartMachines = [];
foreach ($machines as $m) {
  if (!isset($trendMap[$m['id']]))
    continue;
  $pts = [];
  foreach ($dateRows as $d)
    $pts[] = $trendMap[$m['id']][$d] ?? null;
  // Skip if all null
  if (count(array_filter($pts, fn($v) => $v !== null)) === 0)
    continue;
  $chartMachines[] = [
    'id' => $m['id'],
    'name' => $m['name'],
    'line' => $m['line_name'] ?? 'General',
    'data' => $pts,
    'oee' => (float) ($m['oee_pct'] ?? 0),
  ];
}

$chartLabels = json_encode(array_map(fn($d) => date('d/m', strtotime($d)), $dateRows));
$chartDatasets = json_encode($chartMachines);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <!-- AJAX refresh — no hard reload -->
  <title>OEE Live Monitor — PT. YADIN</title>
  <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
  <script src="vendor/chart.js/Chart.min.js"></script>
  <style>
    /* ════════════════════════════════════════════════════════════
   DESIGN SYSTEM  —  Elegant · Simple · Professional
   Palette: White / Light Gray / Slate / Maroon accent
   ════════════════════════════════════════════════════════════ */
    :root {
      /* Brand */
      --maroon: #8B1A1A;
      --maroon-dk: #6B1010;
      --maroon-lt: #f9eded;

      /* Semantic */
      --ok: #1a7f54;
      /* green  – running / normal */
      --ok-bg: #e6f7ef;
      --ok-border: #a8dfc3;
      --warn: #9a6400;
      /* amber  – warning */
      --warn-bg: #fef8e7;
      --warn-border: #f5d87a;
      --danger: #b91c1c;
      /* red    – critical / stop */
      --danger-bg: #fef2f2;
      --danger-border: #fca5a5;
      --info: #1d4ed8;
      --info-bg: #eff6ff;

      /* Neutrals */
      --bg: #f4f6f9;
      --surface: #ffffff;
      --surface-2: #f8f9fb;
      --border: #e2e6ea;
      --border-dk: #cdd2d8;
      --text-1: #111827;
      /* headings */
      --text-2: #374151;
      /* body */
      --text-3: #6b7280;
      /* muted */
      --text-4: #9ca3af;
      /* placeholder */

      --shadow-sm: 0 1px 3px rgba(0, 0, 0, .08);
      --shadow-md: 0 4px 12px rgba(0, 0, 0, .10);
      --radius: 10px;
      --font: 'Segoe UI', system-ui, Arial, sans-serif;
    }

    *,
    *::before,
    *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    html,
    body {
      width: 100%;
      height: 100%;
      overflow: hidden;
      background: var(--bg);
      color: var(--text-2);
      font-family: var(--font);
    }

    /* ── Master grid ─────────────────────────────────────────── */
    .tv {
      display: grid;
      grid-template-rows: 115px 1fr;
      height: 100vh;
      padding: 10px 12px 10px;
      gap: 8px;
    }

    /* ══════════════════════════════════════════════════════════
   HEADER
   ══════════════════════════════════════════════════════════ */
    .hdr {
      display: flex;
      flex-direction: column;
      background: linear-gradient(100deg, var(--maroon) 0%, var(--maroon-dk) 100%);
      border-radius: var(--radius);
      padding: 0;
      gap: 0;
      box-shadow: 0 4px 18px rgba(139, 26, 26, .30);
      overflow: hidden;
    }

    .hdr-top {
      display: flex;
      align-items: center;
      flex: 1;
      padding: 0 20px;
      gap: 0;
      min-height: 0;
    }

    /* Ticker row at the bottom of the header */
    .hdr-ticker {
      display: flex;
      align-items: center;
      gap: 10px;
      background: rgba(0, 0, 0, .18);
      border-top: 1px solid rgba(255, 255, 255, .10);
      padding: 0 14px;
      height: 26px;
      flex-shrink: 0;
      overflow: hidden;
    }

    .hdr-ticker .footer-lbl {
      font-size: .52rem;
      text-transform: uppercase;
      letter-spacing: 2px;
      color: rgba(255, 255, 255, .7);
      white-space: nowrap;
      font-weight: 800;
      flex-shrink: 0;
    }

    .hdr-ticker .ticker-wrap {
      flex: 1;
      overflow: hidden;
      white-space: nowrap;
    }

    .hdr-ticker .ticker-inner {
      display: inline-block;
      animation: ticker 180s linear infinite;
      font-size: .6rem;
      color: rgba(255, 255, 255, .6);
    }

    .hdr-ticker .ticker-inner strong {
      color: rgba(255, 255, 255, .9);
    }

    /* Brand */
    .hdr-brand {
      display: flex;
      align-items: center;
      gap: 12px;
      flex-shrink: 0;
    }

    .hdr-brand-icon {
      width: 56px;
      height: 56px;
      border-radius: 8px;
      background: rgba(255, 255, 255, .12);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 6px;
    }

    .hdr-brand-icon img {
      width: 100%;
      height: 100%;
      object-fit: contain;
    }

    .hdr-brand-text h1 {
      font-size: 1.15rem;
      font-weight: 800;
      letter-spacing: .8px;
      color: #fff;
    }

    .hdr-brand-text p {
      font-size: .65rem;
      color: rgba(255, 255, 255, .65);
      letter-spacing: 1.5px;
      text-transform: uppercase;
      margin-top: 2px;
    }

    /* Divider */
    .hdr-div {
      width: 1px;
      height: 36px;
      background: rgba(255, 255, 255, .2);
      margin: 0 18px;
      flex-shrink: 0;
    }

    /* Stats cluster */
    .hdr-stats {
      display: flex;
      align-items: center;
      gap: 0;
      flex: 1;
    }

    .hstat {
      text-align: center;
      padding: 0 14px;
    }

    .hstat .v {
      font-size: 1.85rem;
      font-weight: 900;
      line-height: 1;
      color: #fff;
    }

    .hstat .v.ok {
      color: #fff;
    }

    .hstat .v.danger {
      color: #fff;
    }

    .hstat .v.warn {
      color: #fff;
    }

    .hstat .v.gold {
      color: #fff;
    }

    .hstat .l {
      font-size: .62rem;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: rgba(255, 255, 255, .6);
      margin-top: 3px;
    }

    /* Clock */
    .hdr-clock {
      flex-shrink: 0;
      text-align: right;
    }

    #tvClock {
      font-size: 2.1rem;
      font-weight: 900;
      color: #fff;
      letter-spacing: 3px;
      font-variant-numeric: tabular-nums;
      line-height: 1;
    }

    #tvDate {
      font-size: .72rem;
      color: rgba(255, 255, 255, .75);
      margin-top: 3px;
    }

    #tvSync {
      font-size: .5rem;
      color: rgba(255, 255, 255, .4);
    }

    /* ══════════════════════════════════════════════════════════
   BODY  — left (cards) + right (panels)
   ══════════════════════════════════════════════════════════ */
    .body {
      display: grid;
      grid-template-columns: 1fr 520px;
      gap: 8px;
      min-height: 0;
    }

    /* ══════════════════════════════════════════════════════════
   LEFT — card scroller
   ══════════════════════════════════════════════════════════ */
    .card-panel {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      display: flex;
      flex-direction: column;
      min-height: 0;
      box-shadow: var(--shadow-sm);
    }

    .card-panel-hdr {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 8px 14px;
      background: var(--surface-2);
      border-bottom: 1px solid var(--border);
      flex-shrink: 0;
      border-radius: var(--radius) var(--radius) 0 0;
    }

    .card-panel-hdr .ttl {
      font-size: .7rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1.5px;
      color: var(--text-3);
    }

    .badge-live {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      background: var(--ok);
      color: #fff;
      font-size: .52rem;
      padding: 2px 8px;
      border-radius: 20px;
      font-weight: 700;
      letter-spacing: .5px;
    }

    .badge-live::before {
      content: '';
      width: 5px;
      height: 5px;
      border-radius: 50%;
      background: #fff;
      animation: blink 1.4s infinite;
    }

    @keyframes blink {

      0%,
      100% {
        opacity: 1
      }

      50% {
        opacity: .2
      }
    }

    /* Viewport + scroll */
    .scroll-viewport {
      flex: 1;
      overflow: hidden;
      position: relative;
      min-height: 0;
    }

    .scroll-viewport:hover .scroll-track {
      animation-play-state: paused !important;
    }

    .scroll-track {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 10px;
      padding: 10px;
      align-content: start;
    }

    /* ══════════════════════════════════════════════════════════
   MACHINE CARD  — 4 per row, elegant & clear
   ══════════════════════════════════════════════════════════ */
    .mc {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 14px 14px 12px;
      position: relative;
      overflow: hidden;
      box-shadow: var(--shadow-sm);
      transition: box-shadow .2s, transform .2s;
    }

    .mc:hover {
      box-shadow: var(--shadow-md);
      transform: translateY(-1px);
    }

    /* Top accent bar — single brand color */
    .mc::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 3px;
      border-radius: var(--radius) var(--radius) 0 0;
      background: var(--maroon);
      opacity: .35;
    }

    .mc.run::before {
      opacity: .5;
    }

    .mc.stop::before {
      opacity: .18;
    }

    /* Left accent — single neutral */
    .mc.oee-ex,
    .mc.oee-gd,
    .mc.oee-ok,
    .mc.oee-bd {
      border-left: 3px solid var(--border-dk);
    }

    /* Line + Name */
    .mc-line {
      font-size: .62rem;
      font-weight: 700;
      color: var(--text-1);
      text-transform: uppercase;
      letter-spacing: 1.2px;
      margin-bottom: 3px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .mc-name {
      font-size: 1rem;
      font-weight: 800;
      color: var(--text-1);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      margin-bottom: 12px;
      letter-spacing: -.2px;
    }

    /* ── OEE Big ── */
    .mc-oee-wrap {
      display: flex;
      align-items: flex-end;
      justify-content: center;
      gap: 6px;
      margin-bottom: 12px;
      padding: 10px 0;
      background: var(--surface-2);
      border-radius: 8px;
      border: 1px solid var(--border);
    }

    .mc-oee-val {
      font-size: 2.6rem;
      font-weight: 900;
      line-height: 1;
      letter-spacing: -2px;
    }

    .mc-oee-unit {
      font-size: 1rem;
      font-weight: 700;
      color: var(--text-3);
      margin-bottom: 4px;
    }

    .mc-oee-lbl {
      font-size: .58rem;
      text-transform: uppercase;
      letter-spacing: 1.5px;
      color: var(--text-1);
      font-weight: 700;
      margin-top: 3px;
      text-align: center;
    }

    /* ── A / P / Q bars ── */
    .mc-bars {
      margin-bottom: 10px;
      display: flex;
      flex-direction: column;
      gap: 5px;
    }

    .bar-row {
      display: flex;
      align-items: center;
      gap: 7px;
    }

    .bar-lbl {
      font-size: .65rem;
      font-weight: 700;
      color: var(--text-3);
      width: 16px;
      text-align: right;
      flex-shrink: 0;
    }

    .bar-track {
      flex: 1;
      height: 7px;
      background: #eef0f3;
      border-radius: 4px;
      overflow: hidden;
    }

    .bar-fill {
      height: 100%;
      border-radius: 4px;
      transition: width 1s ease;
    }

    .bar-val {
      font-size: .65rem;
      font-weight: 800;
      width: 34px;
      text-align: right;
      flex-shrink: 0;
    }

    /* ── Sensor 2x2 grid ── */
    .mc-sensors {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 5px;
      margin-bottom: 10px;
    }

    .snsr-box {
      background: var(--surface-2);
      border: 1px solid var(--border);
      border-radius: 6px;
      padding: 5px 7px;
    }

    .snsr-lbl {
      font-size: .58rem;
      color: var(--text-4);
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .5px;
    }

    .snsr-val {
      font-size: .85rem;
      font-weight: 800;
      color: var(--text-1);
      line-height: 1.2;
      margin-top: 1px;
    }

    /* ── Footer row ── */
    .mc-foot {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding-top: 9px;
      border-top: 1px solid var(--border);
      gap: 6px;
    }

    .status-pill {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      font-size: .68rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: .5px;
      padding: 3px 10px;
      border-radius: 20px;
    }

    .status-pill.run {
      background: #f0f2f5;
      color: #374151;
      border: 1px solid var(--border-dk);
    }

    .status-pill.stop {
      background: var(--maroon-lt);
      color: var(--maroon);
      border: 1px solid #ddbdbd;
    }

    .status-pill i {
      font-size: .55rem;
    }

    .vib-pill {
      font-size: .6rem;
      font-weight: 700;
      padding: 2px 8px;
      border-radius: 12px;
    }

    .vib-pill.normal {
      background: #f0f2f5;
      color: var(--text-3);
      border: 1px solid var(--border);
    }

    .vib-pill.warning {
      background: var(--maroon-lt);
      color: var(--maroon);
      border: 1px solid #ddbdbd;
    }

    .vib-pill.critical {
      background: var(--maroon);
      color: #fff;
      animation: blink .8s infinite;
    }

    .mc-age {
      font-size: .58rem;
      color: var(--text-4);
      white-space: nowrap;
    }

    /* ── Scroll control buttons ── */
    .scroll-ctrl {
      display: flex;
      align-items: center;
      gap: 4px;
    }

    .scroll-ctrl button {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 24px;
      height: 24px;
      border-radius: 6px;
      border: 1px solid var(--border-dk);
      background: var(--surface);
      color: var(--text-2);
      cursor: pointer;
      font-size: .7rem;
      line-height: 1;
      transition: background .15s, color .15s;
      padding: 0;
    }

    .scroll-ctrl button:hover {
      background: var(--maroon);
      color: #fff;
      border-color: var(--maroon);
    }

    .scroll-ctrl button.pause-active {
      background: var(--maroon-lt);
      color: var(--maroon);
      border-color: #ddbdbd;
    }

    /* ══════════════════════════════════════════════════════════
   RIGHT — side panels
   ══════════════════════════════════════════════════════════ */
    .right {
      display: flex;
      flex-direction: column;
      gap: 8px;
      min-height: 0;
    }

    .side-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      display: flex;
      flex-direction: column;
      box-shadow: var(--shadow-sm);
    }

    .side-hdr {
      padding: 8px 14px;
      background: var(--surface-2);
      border-bottom: 1px solid var(--border);
      flex-shrink: 0;
      border-radius: var(--radius) var(--radius) 0 0;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .side-hdr .ttl {
      font-size: .68rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1.5px;
      color: var(--text-3);
    }

    .side-body {
      flex: 1;
      min-height: 0;
      overflow: hidden;
    }

    /* ── Summary + Line Breakdown ── */
    .summary-top {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 6px;
      padding: 10px 12px 6px;
    }

    .sum-box {
      background: var(--surface-2);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 8px 10px;
      text-align: center;
    }

    .sum-box .sv {
      font-size: 1.4rem;
      font-weight: 900;
      line-height: 1;
    }

    .sum-box .sl {
      font-size: .55rem;
      color: var(--text-4);
      text-transform: uppercase;
      letter-spacing: .8px;
      margin-top: 2px;
    }

    /* Distribution mini bar */
    .dist-bar {
      display: flex;
      height: 8px;
      border-radius: 4px;
      overflow: hidden;
      margin: 6px 12px 0;
    }

    .dist-seg {
      height: 100%;
      transition: width .5s;
    }

    .dist-legend {
      display: flex;
      justify-content: center;
      gap: 12px;
      padding: 5px 12px 8px;
      font-size: .58rem;
      color: var(--text-3);
    }

    .dist-legend span {
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }

    .dot {
      width: 7px;
      height: 7px;
      border-radius: 50%;
      flex-shrink: 0;
    }

    /* Line breakdown table */
    .line-divider {
      height: 1px;
      background: var(--border);
      margin: 0 12px;
    }

    .line-title {
      font-size: .6rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1.5px;
      color: var(--text-4);
      padding: 8px 12px 5px;
    }

    .line-table {
      width: 100%;
      border-collapse: collapse;
    }

    .line-table td {
      padding: 5px 12px;
      font-size: .7rem;
      border-bottom: 1px solid var(--border);
    }

    .line-table tr:last-child td {
      border-bottom: none;
    }

    .line-name-cell {
      font-weight: 700;
      color: var(--text-2);
    }

    .line-run-cell {
      text-align: center;
    }

    .line-oee-cell {
      text-align: right;
      font-weight: 800;
    }

    .line-bar-cell {
      width: 70px;
    }

    .line-mini-bar {
      height: 5px;
      background: #eef0f3;
      border-radius: 3px;
      overflow: hidden;
    }

    .line-mini-fill {
      height: 100%;
      border-radius: 3px;
    }

    /* ── Chart ── */
    .chart-card {
      flex: 0 0 185px;
    }

    .chart-wrap {
      height: 100%;
      padding: 8px 10px 6px;
      position: relative;
    }

    /* ── Alert Panel ── */
    .alert-card {
      flex: 1;
      min-height: 0;
    }

    .alert-list {
      height: 100%;
      overflow: hidden;
      display: flex;
      flex-direction: column;
    }

    .alert-row {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 7px 12px;
      border-bottom: 1px solid var(--border);
      flex-shrink: 0;
    }

    .alert-row:last-child {
      border-bottom: none;
    }

    .alert-row:first-child {
      background: var(--surface-2);
    }

    .a-sev {
      flex-shrink: 0;
      width: 52px;
      padding: 2px 0;
      border-radius: 4px;
      text-align: center;
      font-size: .58rem;
      font-weight: 800;
      text-transform: uppercase;
    }

    .a-sev.critical {
      background: var(--danger);
      color: #fff;
    }

    .a-sev.warning {
      background: #f59e0b;
      color: #fff;
    }

    .a-sev.info {
      background: var(--info);
      color: #fff;
    }

    .a-mach {
      flex: 1;
    }

    .a-mach .name {
      font-size: .72rem;
      font-weight: 700;
      color: var(--text-1);
    }

    .a-mach .key {
      font-size: .6rem;
      color: var(--text-4);
    }

    .a-val {
      font-size: .75rem;
      font-weight: 800;
      color: var(--maroon);
      width: 52px;
      text-align: right;
      flex-shrink: 0;
    }

    .a-time {
      font-size: .58rem;
      color: var(--text-4);
      width: 36px;
      text-align: right;
      flex-shrink: 0;
    }

    .no-alert {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }

    .no-alert i {
      font-size: 1.8rem;
      color: var(--ok);
    }

    .no-alert .t1 {
      font-size: .8rem;
      font-weight: 700;
      color: var(--ok);
    }

    .no-alert .t2 {
      font-size: .62rem;
      color: var(--text-4);
    }

    @keyframes ticker {
      0% {
        transform: translateX(60vw)
      }

      100% {
        transform: translateX(-100%)
      }
    }

    /* ── AJAX smooth update transitions ── */
    .mc-oee-val,
    .bar-fill,
    .bar-val,
    .snsr-val,
    .status-pill,
    .vib-pill {
      transition: all .6s ease;
    }

    .mc {
      transition: box-shadow .2s, transform .2s, border-left-color .6s;
    }

    .val-flash {
      animation: valFlash .8s ease;
    }

    @keyframes valFlash {
      0% {
        opacity: 1;
      }

      30% {
        opacity: .25;
      }

      100% {
        opacity: 1;
      }
    }

    /* Sync indicator */
    #syncDot {
      display: inline-block;
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: #6ee7b7;
      margin-left: 6px;
      vertical-align: middle;
      transition: background .3s;
    }

    #syncDot.syncing {
      background: #fde68a;
      animation: blink .4s infinite;
    }

    #syncDot.error {
      background: #fca5a5;
    }
  </style>
</head>

<body>
  <div class="tv">

    <!-- ══ HEADER ════════════════════════════════════════════════ -->
    <div class="hdr">
      <div class="hdr-top">
        <div class="hdr-brand">
          <div class="hdr-brand-icon"><img src="img/yanmar.png" alt="Yanmar"></div>
          <div class="hdr-brand-text">
            <h1>OEE MONITORING SYSTEM</h1>
            <p>PT. YADIN &mdash; Live Dashboard</p>
          </div>
        </div>

        <div class="hdr-div"></div>

        <div class="hdr-stats">
          <div class="hstat">
            <div id="hStat-total" class="v gold"><?= $totalMachines ?></div>
            <div class="l">Total Machines</div>
          </div>
          <div class="hdr-div"></div>
          <div class="hstat">
            <div id="hStat-run" class="v ok"><?= $machineRun ?></div>
            <div class="l">Running</div>
          </div>
          <div class="hdr-div"></div>
          <div class="hstat">
            <div id="hStat-stop" class="v danger"><?= $machineStop ?></div>
            <div class="l">Stopped</div>
          </div>
          <div class="hdr-div"></div>
          <div class="hstat">
            <div id="hStat-avgOee" class="v gold"><?= number_format((float) $avgOEE, 1) ?>%</div>
            <div class="l">Avg OEE</div>
          </div>
          <div class="hdr-div"></div>
          <div class="hstat">
            <div id="hStat-ex" class="v ok"><?= $oeeExcellent ?></div>
            <div class="l">OEE ≥85%</div>
          </div>
          <div class="hdr-div"></div>
          <div class="hstat">
            <div id="hStat-good" class="v warn"><?= $oeeGood ?></div>
            <div class="l">OEE 65–85%</div>
          </div>
          <div class="hdr-div"></div>
          <div class="hstat">
            <div id="hStat-poor" class="v danger"><?= $oeePoor ?></div>
            <div class="l">OEE &lt;65%</div>
          </div>
          <div class="hdr-div"></div>
          <div class="hstat">
            <div id="hStat-alert" class="v <?= $alertCritical > 0 ? 'danger' : ($alertWarning > 0 ? 'warn' : 'ok') ?>">
              <?= $alertCount ?></div>
            <div class="l">Active Alerts</div>
          </div>
        </div>

        <div class="hdr-div"></div>
        <div class="hdr-clock">
          <div id="tvClock">--:--:--</div>
          <div id="tvDate"><span id="tvDateTxt">---</span><span id="syncDot" title="Live sync"></span></div>
        </div>
      </div><!-- /hdr-top -->

      <!-- Ticker running below header -->
      <div class="hdr-ticker">
        <span class="footer-lbl">● LIVE</span>
        <div class="ticker-wrap">
          <span class="ticker-inner">
            <?php foreach ($machines as $m): ?>
              &nbsp;&nbsp;<strong><?= htmlspecialchars($m['name']) ?></strong>
              OEE:<?= number_format((float) ($m['oee_pct'] ?? 0), 1) ?>%
              A:<?= number_format((float) ($m['availability'] ?? 0), 1) ?>%
              P:<?= number_format((float) ($m['performance'] ?? 0), 1) ?>%
              Q:<?= number_format((float) ($m['quality'] ?? 0), 1) ?>%
              [<?= strtoupper($m['status']) ?>]&nbsp;&#124;
            <?php endforeach; ?>
            &nbsp;&nbsp;&#9632;&nbsp;Auto-refresh 30s&nbsp;&#9632;&nbsp;Last update: <?= date('d M Y H:i:s') ?>
          </span>
        </div>
      </div>

    </div><!-- /hdr -->

    <!-- ══ BODY ══════════════════════════════════════════════════ -->
    <div class="body">

      <!-- ─── LEFT: 4-column card grid ──────────────────────────── -->
      <div class="card-panel">
        <div class="card-panel-hdr">
          <span class="ttl">
            <i class="fas fa-th-large" style="margin-right:6px;color:var(--maroon);"></i>
            Machine Status &mdash; <?= $totalMachines ?> Units
            <span style="color:var(--text-4);font-weight:400;margin-left:6px;">· hover to pause scroll</span>
          </span>
          <div style="display:flex;align-items:center;gap:8px;">
            <div class="scroll-ctrl">
              <button onclick="cardScrollUp()" title="Scroll up">&#8593;</button>
              <button id="cardPauseBtn" onclick="cardTogglePause()" title="Pause/Resume">&#9646;&#9646;</button>
              <button onclick="cardScrollDown()" title="Scroll down">&#8595;</button>
            </div>
            <span class="badge-live">LIVE</span>
          </div>
        </div>

        <div class="scroll-viewport" id="scrollVP">
          <div class="scroll-track" id="scrollTrack">

            <?php foreach ($machines as $m):
              $oee = (float) ($m['oee_pct'] ?? 0);
              $avl = (float) ($m['availability'] ?? 0);
              $prf = (float) ($m['performance'] ?? 0);
              $qlt = (float) ($m['quality'] ?? 0);

              // OEE color — black
              $oeeColor = $oee > 0 ? 'var(--text-1)' : 'var(--text-4)';
              $oeeClass = $oee >= 85 ? 'oee-ex' : ($oee >= 65 ? 'oee-gd' : ($oee >= 45 ? 'oee-ok' : ($oee > 0 ? 'oee-bd' : '')));

              // Bar colors — green/yellow/red based on value
              $bc = fn($v) => $v >= 85 ? '#16a34a' : ($v >= 65 ? '#d97706' : ($v > 0 ? '#dc2626' : '#eef0f3'));

              // Vib
              $vibSt = strtolower($m['vib_status'] ?? 'normal');

              // Sensor age
              $sAge = $m['sensor_at'] ? round((time() - strtotime($m['sensor_at'])) / 60) : null;
              $ageStr = $sAge === null ? '—' : ($sAge < 60 ? $sAge . 'm' : round($sAge / 60) . 'h');
              $ageC = $sAge === null ? 'var(--text-4)' : ($sAge <= 5 ? 'var(--ok)' : ($sAge <= 60 ? 'var(--warn)' : 'var(--danger)'));

              // Temp color — only red if > 50°C (real danger)
              $tmpC = ($m['temp_panel'] ?? 0) > 50 ? 'var(--maroon)' : 'var(--text-1)';
              ?>
              <div class="mc <?= $m['status'] ?> <?= $oeeClass ?>" data-machine-id="<?= $m['id'] ?>">

                <div class="mc-line"><?= htmlspecialchars($m['line_name'] ?? 'General') ?></div>
                <div class="mc-name"><?= htmlspecialchars($m['name']) ?></div>

                <!-- OEE Besar -->
                <div class="mc-oee-wrap">
                  <div>
                    <div class="mc-oee-val" style="color:<?= $oeeColor ?>;">
                      <?= $oee > 0 ? $oee : 'N/A' ?>
                    </div>
                    <div class="mc-oee-lbl">OEE Score</div>
                  </div>
                  <?php if ($oee > 0): ?>
                    <div class="mc-oee-unit">%</div>
                  <?php endif; ?>
                </div>

                <!-- Bars -->
                <div class="mc-bars">
                  <div class="bar-row">
                    <span class="bar-lbl">A</span>
                    <div class="bar-track">
                      <div class="bar-fill" style="width:<?= min($avl, 100) ?>%;background:<?= $bc($avl) ?>;"></div>
                    </div>
                    <span class="bar-val" style="color:<?= $bc($avl) ?>;"><?= $avl > 0 ? $avl . '%' : '—' ?></span>
                  </div>
                  <div class="bar-row">
                    <span class="bar-lbl">P</span>
                    <div class="bar-track">
                      <div class="bar-fill" style="width:<?= min($prf, 100) ?>%;background:<?= $bc($prf) ?>;"></div>
                    </div>
                    <span class="bar-val" style="color:<?= $bc($prf) ?>;"><?= $prf > 0 ? $prf . '%' : '—' ?></span>
                  </div>
                  <div class="bar-row">
                    <span class="bar-lbl">Q</span>
                    <div class="bar-track">
                      <div class="bar-fill" style="width:<?= min($qlt, 100) ?>%;background:<?= $bc($qlt) ?>;"></div>
                    </div>
                    <span class="bar-val" style="color:<?= $bc($qlt) ?>;"><?= $qlt > 0 ? $qlt . '%' : '—' ?></span>
                  </div>
                </div>

                <!-- Sensor boxes -->
                <div class="mc-sensors">
                  <div class="snsr-box">
                    <div class="snsr-lbl">Voltage</div>
                    <div class="snsr-val"><?= $m['v_r'] !== null ? number_format($m['v_r'], 1) . ' V' : '—' ?></div>
                  </div>
                  <div class="snsr-box">
                    <div class="snsr-lbl">Current</div>
                    <div class="snsr-val"><?= $m['a_r'] !== null ? number_format($m['a_r'], 2) . ' A' : '—' ?></div>
                  </div>
                  <div class="snsr-box">
                    <div class="snsr-lbl">Temp Panel</div>
                    <div class="snsr-val" style="color:<?= $tmpC ?>;">
                      <?= $m['temp_panel'] !== null ? number_format($m['temp_panel'], 1) . ' °C' : '—' ?></div>
                  </div>
                  <div class="snsr-box">
                    <div class="snsr-lbl">Humidity</div>
                    <div class="snsr-val"><?= $m['hum_panel'] !== null ? number_format($m['hum_panel'], 1) . ' %' : '—' ?>
                    </div>
                  </div>
                </div>

                <!-- Footer -->
                <div class="mc-foot">
                  <span class="status-pill <?= $m['status'] ?>">
                    <i class="fas <?= $m['status'] === 'run' ? 'fa-play' : 'fa-stop' ?>"></i>
                    <?= strtoupper($m['status']) ?>
                  </span>
                  <span class="vib-pill <?= $vibSt ?>"><?= strtoupper($vibSt) ?></span>
                  <span class="mc-age" style="color:<?= $ageC ?>;"><?= $ageStr ?> ago</span>
                </div>

              </div>
            <?php endforeach; ?>

          </div>
        </div>
      </div>

      <!-- ─── RIGHT panels ─────────────────────────────────────── -->
      <div class="right">

        <!-- Summary + Line Breakdown -->
        <div class="side-card" style="flex:0 0 auto;">
          <div class="side-hdr">
            <span class="ttl"><i class="fas fa-layer-group" style="margin-right:6px;color:var(--maroon);"></i>Summary
              &amp; Line Breakdown</span>
          </div>
          <!-- Top 3 stat boxes -->
          <div class="summary-top">
            <div class="sum-box">
              <div id="sum-avgOee" class="sv" style="color:var(--maroon);"><?= number_format((float) $avgOEE, 1) ?>%</div>
              <div class="sl">Avg OEE</div>
            </div>
            <div class="sum-box">
              <div id="sum-running" class="sv" style="color:var(--ok);"><?= $machineRun ?>/<?= $totalMachines ?></div>
              <div class="sl">Running</div>
            </div>
            <div class="sum-box">
              <div id="sum-alert" class="sv" style="color:<?= $alertCount > 0 ? 'var(--danger)' : 'var(--ok)' ?>;">
                <?= $alertCount ?></div>
              <div class="sl">Active Alerts</div>
            </div>
          </div>

          <!-- OEE Distribution bar -->
          <?php $tot = max($totalMachines, 1); ?>
          <div class="dist-bar" style="margin:4px 12px 0;">
            <div id="dist-ex" class="dist-seg"
              style="width:<?= round($oeeExcellent / $tot * 100) ?>%;background:var(--maroon);opacity:.9;"></div>
            <div id="dist-good" class="dist-seg"
              style="width:<?= round($oeeGood / $tot * 100) ?>%;background:var(--maroon);opacity:.5;"></div>
            <div id="dist-poor" class="dist-seg"
              style="width:<?= round($oeePoor / $tot * 100) ?>%;background:var(--maroon);opacity:.2;"></div>
            <div class="dist-seg" style="flex:1;background:#eef0f3;"></div>
          </div>
          <div class="dist-legend">
            <span><span class="dot" style="background:var(--maroon);opacity:.9;"></span><span id="dist-ex-lbl">≥85%
                (<?= $oeeExcellent ?>)</span></span>
            <span><span class="dot" style="background:var(--maroon);opacity:.5;"></span><span id="dist-good-lbl">65–85%
                (<?= $oeeGood ?>)</span></span>
            <span><span class="dot" style="background:var(--maroon);opacity:.2;border:1px solid #ccc;"></span><span
                id="dist-poor-lbl">&lt;65% (<?= $oeePoor ?>)</span></span>
          </div>

          <!-- Per-line breakdown -->
          <div class="line-divider"></div>
          <div style="display:flex;align-items:center;justify-content:space-between;padding:6px 12px 4px;">
            <span class="line-title" style="padding:0;">Breakdown by Production Line</span>
            <div class="scroll-ctrl">
              <button onclick="lineScrollUp()" title="Scroll up">&#8593;</button>
              <button id="linePauseBtn" onclick="lineTogglePause()" title="Pause/Resume">&#9646;&#9646;</button>
              <button onclick="lineScrollDown()" title="Scroll down">&#8595;</button>
            </div>
          </div>
          <div id="lineVP" style="height:180px;overflow:hidden;position:relative;flex-shrink:0;">
            <div id="lineTrack">
              <table class="line-table">
                <thead>
                  <tr style="background:var(--surface-2);">
                    <td
                      style="font-size:.6rem;font-weight:700;color:var(--text-4);text-transform:uppercase;letter-spacing:1px;">
                      Line</td>
                    <td
                      style="font-size:.6rem;font-weight:700;color:var(--text-4);text-transform:uppercase;letter-spacing:1px;text-align:center;">
                      Run/Total</td>
                    <td
                      style="font-size:.6rem;font-weight:700;color:var(--text-4);text-transform:uppercase;letter-spacing:1px;text-align:right;">
                      Avg OEE</td>
                    <td style="width:70px;"></td>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($lineStats as $lineName => $ls):
                    $oeeC2 = $ls['avg_oee'] > 0 ? 'var(--maroon)' : 'var(--text-4)';
                    $fillC = 'var(--maroon)';
                    $runRatio = $ls['total'] > 0 ? round($ls['run'] / $ls['total'] * 100) : 0;
                    ?>
                    <tr data-line="<?= rawurlencode($lineName) ?>">
                      <td class="line-name-cell"><?= htmlspecialchars($lineName) ?></td>
                      <td class="line-run-cell">
                        <span style="font-weight:800;color:var(--ok);"><?= $ls['run'] ?></span>
                        <span style="color:var(--text-4);">/<?= $ls['total'] ?></span>
                      </td>
                      <td class="line-oee-cell" style="color:<?= $oeeC2 ?>;">
                        <?= $ls['avg_oee'] > 0 ? $ls['avg_oee'] . '%' : '—' ?></td>
                      <td class="line-bar-cell">
                        <div class="line-mini-bar">
                          <div class="line-mini-fill"
                            style="width:<?= min($ls['avg_oee'], 100) ?>%;background:<?= $fillC ?>;"></div>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div><!-- /lineTrack -->
          </div><!-- /lineVP -->
        </div>

        <!-- OEE Trend Chart per machine -->
        <div class="side-card chart-card">
          <div class="side-hdr" style="justify-content:space-between;">
            <span class="ttl"><i class="fas fa-chart-line" style="margin-right:6px;color:var(--maroon);"></i>OEE Trend
              14 Days — per Machine</span>
            <div style="display:flex;align-items:center;gap:6px;">
              <button onclick="prevMachine()"
                style="background:var(--surface-2);border:1px solid var(--border);border-radius:5px;width:22px;height:22px;cursor:pointer;font-size:.65rem;line-height:1;color:var(--text-3);">&#8249;</button>
              <span id="chartMachIdx" style="font-size:.6rem;color:var(--text-4);min-width:48px;text-align:center;">1 /
                ?</span>
              <button onclick="nextMachine()"
                style="background:var(--surface-2);border:1px solid var(--border);border-radius:5px;width:22px;height:22px;cursor:pointer;font-size:.65rem;line-height:1;color:var(--text-3);">&#8250;</button>
              <span id="chartAutoIcon" style="font-size:.55rem;color:var(--text-4);margin-left:2px;"
                title="Auto-scroll active">&#9654; AUTO</span>
            </div>
          </div>
          <!-- machine info bar -->
          <div id="chartMachInfo"
            style="padding:5px 12px 4px;border-bottom:1px solid var(--border);background:var(--surface-2);display:flex;align-items:center;gap:8px;flex-shrink:0;">
            <span id="chartMachName" style="font-size:.78rem;font-weight:800;color:var(--text-1);">—</span>
            <span id="chartMachLine" style="font-size:.6rem;color:var(--text-4);"></span>
            <span style="flex:1;"></span>
            <span id="chartMachOee" style="font-size:.8rem;font-weight:900;"></span>
          </div>
          <!-- progress dots / slide indicator -->
          <div style="padding:3px 12px 0;background:var(--surface-2);flex-shrink:0;">
            <div style="height:3px;background:#eef0f3;border-radius:2px;overflow:hidden;">
              <div id="chartProgressFill"
                style="height:100%;width:0%;background:var(--maroon);border-radius:2px;transition:width .4s ease;">
              </div>
            </div>
          </div>
          <div class="side-body" style="padding:8px 10px 4px;min-height:0;">
            <div class="chart-wrap"><canvas id="oeeChart"></canvas></div>
          </div>
        </div>

        <!-- Alert Panel -->
        <div class="side-card alert-card">
          <div class="side-hdr">
            <span class="ttl"><i class="fas fa-exclamation-triangle"
                style="margin-right:6px;color:var(--maroon);"></i>Abnormal Machine Conditions</span>
            <div style="display:flex;align-items:center;gap:6px;">
              <span style="font-size:.6rem;color:var(--text-4);"><?= count($abnormalMachines) ?> machines</span>
              <div class="scroll-ctrl">
                <button onclick="alertScrollUp()" title="Scroll up">&#8593;</button>
                <button id="alertPauseBtn" onclick="alertTogglePause()" title="Pause/Resume">&#9646;&#9646;</button>
                <button onclick="alertScrollDown()" title="Scroll down">&#8595;</button>
              </div>
            </div>
          </div>
          <!-- Sub-header columns — fixed, does not scroll -->
          <div
            style="display:grid;grid-template-columns:1fr 80px 1fr;gap:0;padding:5px 14px 4px;background:var(--surface-2);border-bottom:2px solid var(--border-dk);flex-shrink:0;">
            <span
              style="font-size:.58rem;font-weight:700;color:var(--text-4);text-transform:uppercase;letter-spacing:.8px;">Machine</span>
            <span
              style="font-size:.58rem;font-weight:700;color:var(--text-4);text-transform:uppercase;letter-spacing:.8px;text-align:center;">Condition</span>
            <span
              style="font-size:.58rem;font-weight:700;color:var(--text-4);text-transform:uppercase;letter-spacing:.8px;text-align:left;padding-left:12px;">Details</span>
          </div>
          <div class="side-body" id="alertVP" style="overflow:hidden;position:relative;">
            <div id="alertTrack">
              <?php if (empty($abnormalMachines)): ?>
                <div class="no-alert">
                  <i class="fas fa-shield-alt"></i>
                  <div class="t1">All Machines Normal</div>
                  <div class="t2">No abnormal conditions detected</div>
                </div>
              <?php else: ?>
                <?php foreach ($abnormalMachines as $ab):
                  $vs = strtolower($ab['vib_status'] ?? 'normal');
                  $rms = (float) ($ab['rms_overall'] ?? 0);
                  $vx = $ab['vib_x'] !== null ? (float) $ab['vib_x'] : null;
                  $vy = $ab['vib_y'] !== null ? (float) $ab['vib_y'] : null;
                  $vz = $ab['vib_z'] !== null ? (float) $ab['vib_z'] : null;
                  $tmp = (float) ($ab['temp_panel'] ?? 0);
                  $hum = (float) ($ab['hum_panel'] ?? 0);
                  $vr = $ab['v_r'] !== null ? (float) $ab['v_r'] : null;
                  $vs_ = $ab['v_s'] !== null ? (float) $ab['v_s'] : null;
                  $vt = $ab['v_t'] !== null ? (float) $ab['v_t'] : null;
                  $ar = $ab['a_r'] !== null ? (float) $ab['a_r'] : null;
                  $as_ = $ab['a_s'] !== null ? (float) $ab['a_s'] : null;
                  $at = $ab['a_t'] !== null ? (float) $ab['a_t'] : null;

                  // ── Collect all issues ──────────────────────────────
                  $issues = [];

                  // Vibration per axis
                  if ($vs === 'critical' || $vs === 'warning') {
                    $axisThresh = $vs === 'critical' ? 7.1 : 2.8;
                    $axisLabel = $vs === 'critical' ? '⚠ CRITICAL' : '⚠ HIGH';
                    if ($vx !== null)
                      $issues[] = ['sev' => $vs, 'txt' => 'Vib X: ' . number_format($vx, 2) . ' mm/s' . ($vx > 7.1 ? ' (critical)' : ($vx > 2.8 ? ' (warning)' : ''))];
                    if ($vy !== null)
                      $issues[] = ['sev' => $vs, 'txt' => 'Vib Y: ' . number_format($vy, 2) . ' mm/s' . ($vy > 7.1 ? ' (critical)' : ($vy > 2.8 ? ' (warning)' : ''))];
                    if ($vz !== null)
                      $issues[] = ['sev' => $vs, 'txt' => 'Vib Z: ' . number_format($vz, 2) . ' mm/s' . ($vz > 7.1 ? ' (critical)' : ($vz > 2.8 ? ' (warning)' : ''))];
                    if ($rms > 0)
                      $issues[] = ['sev' => $vs, 'txt' => 'RMS Overall: ' . number_format($rms, 2) . ' mm/s'];
                  }

                  // Status stop
                  if ($ab['status'] === 'stop')
                    $issues[] = ['sev' => 'stop', 'txt' => 'Machine not operating'];

                  // Abnormal voltage (<190V or >240V per phase)
                  foreach (['R' => $vr, 'S' => $vs_, 'T' => $vt] as $ph => $val) {
                    if ($val !== null && ($val < 190 || $val > 240))
                      $issues[] = ['sev' => 'warn', 'txt' => 'Voltage Ph-' . $ph . ': ' . number_format($val, 1) . ' V'];
                  }

                  // High current (>30A)
                  foreach (['R' => $ar, 'S' => $as_, 'T' => $at] as $ph => $val) {
                    if ($val !== null && $val > 30)
                      $issues[] = ['sev' => 'warn', 'txt' => 'Current Ph-' . $ph . ': ' . number_format($val, 2) . ' A'];
                  }

                  // High panel temperature
                  if ($tmp > 50)
                    $issues[] = ['sev' => 'critical', 'txt' => 'Panel Temp: ' . number_format($tmp, 1) . ' °C'];
                  elseif ($tmp > 40)
                    $issues[] = ['sev' => 'warn', 'txt' => 'Panel Temp: ' . number_format($tmp, 1) . ' °C'];

                  // High humidity
                  if ($hum > 80)
                    $issues[] = ['sev' => 'warn', 'txt' => 'Humidity: ' . number_format($hum, 1) . ' %'];

                  // Determine level & badge color
                  $hasCrit = $vs === 'critical' || $tmp > 50;
                  $hasWarn = $vs === 'warning' || count($issues) > 0;
                  if ($hasCrit) {
                    $level = 'CRITICAL';
                    $lColor = '#dc2626';
                    $lBg = '#fef2f2';
                    $lBorder = '#fca5a5';
                    $blink = 'animation:blink .8s infinite;';
                  } elseif ($ab['status'] === 'stop') {
                    $level = 'STOP';
                    $lColor = 'var(--maroon)';
                    $lBg = 'var(--maroon-lt)';
                    $lBorder = '#ddbdbd';
                    $blink = '';
                  } elseif ($hasWarn) {
                    $level = 'WARNING';
                    $lColor = '#d97706';
                    $lBg = '#fffbeb';
                    $lBorder = '#fde68a';
                    $blink = '';
                  } else {
                    $level = 'INFO';
                    $lColor = '#374151';
                    $lBg = '#f0f2f5';
                    $lBorder = 'var(--border-dk)';
                    $blink = '';
                  }
                  ?>
                  <div
                    style="display:grid;grid-template-columns:140px 80px 1fr;align-items:start;gap:0;padding:8px 14px;border-bottom:1px solid var(--border);">
                    <!-- Name + Line -->
                    <div style="padding-top:2px;">
                      <div
                        style="font-size:.8rem;font-weight:800;color:var(--text-1);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        <?= htmlspecialchars($ab['name']) ?></div>
                      <div
                        style="font-size:.58rem;color:var(--text-3);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        <?= htmlspecialchars($ab['line_name'] ?? '—') ?></div>
                    </div>
                    <!-- Condition badge -->
                    <div style="text-align:center;padding-top:3px;">
                      <span
                        style="display:inline-block;padding:3px 10px;border-radius:12px;font-size:.63rem;font-weight:800;text-transform:uppercase;letter-spacing:.5px;background:<?= $lBg ?>;color:<?= $lColor ?>;border:1px solid <?= $lBorder ?>;<?= $blink ?>">
                        <?= $level ?>
                      </span>
                    </div>
                    <!-- Issue list -->
                    <div style="padding-left:12px;display:flex;flex-direction:column;gap:3px;">
                      <?php if (empty($issues)): ?>
                        <span style="font-size:.68rem;color:var(--text-4);">—</span>
                      <?php else:
                        foreach ($issues as $iss):
                          $ic = $iss['sev'] === 'critical' ? '#dc2626' : ($iss['sev'] === 'stop' ? 'var(--maroon)' : '#d97706');
                          $id = $iss['sev'] === 'critical' ? '●' : ($iss['sev'] === 'stop' ? '◼' : '◆');
                          ?>
                          <span
                            style="font-size:.68rem;font-weight:600;color:<?= $ic ?>;display:flex;align-items:center;gap:5px;">
                            <span style="font-size:.45rem;flex-shrink:0;"><?= $id ?></span>
                            <?= htmlspecialchars($iss['txt']) ?>
                          </span>
                        <?php endforeach; endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div><!-- /alertTrack -->
          </div>
        </div>

      </div>
    </div>


  </div>

  <script>
    /* ── Clock ─────────────────────────────────────────────────── */
    (function () {
      var D = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
      var M = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
      function p(n) { return String(n).padStart(2, '0'); }
      function tick() {
        var d = new Date();
        document.getElementById('tvClock').textContent = p(d.getHours()) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds());
        document.getElementById('tvDateTxt').textContent = D[d.getDay()] + ', ' + d.getDate() + ' ' + M[d.getMonth()] + ' ' + d.getFullYear() + ' ';
      }
      tick(); setInterval(tick, 1000);
    })();

    /* ── Auto-scroll: machine cards ────────────────────────────── */
    (function () {
      var vp = document.getElementById('scrollVP');
      var track = document.getElementById('scrollTrack');
      var btn = document.getElementById('cardPauseBtn');
      var pos = 0, dir = 1, hold = 0;
      var HOLD = 150, speed = 0.5;
      var paused = false, manualPause = false;

      function setBtn() {
        btn.innerHTML = manualPause ? '&#9654;' : '&#9646;&#9646;';
        btn.classList.toggle('pause-active', manualPause);
        btn.title = manualPause ? 'Resume' : 'Pause';
      }

      window.cardTogglePause = function () {
        manualPause = !manualPause;
        paused = manualPause;
        setBtn();
      };
      window.cardScrollUp = function () {
        pos = Math.max(0, pos - vp.offsetHeight * 0.6);
        vp.scrollTop = pos;
      };
      window.cardScrollDown = function () {
        var max = track.scrollHeight - vp.offsetHeight;
        pos = Math.min(max, pos + vp.offsetHeight * 0.6);
        vp.scrollTop = pos;
      };

      vp.addEventListener('mouseenter', function () { if (!manualPause) paused = true; });
      vp.addEventListener('mouseleave', function () { if (!manualPause) paused = false; });

      var tStart = 0;
      vp.addEventListener('touchstart', function (e) { tStart = e.touches[0].clientY; paused = true; });
      vp.addEventListener('touchend', function () { if (!manualPause) paused = false; });
      vp.addEventListener('touchmove', function (e) {
        pos += tStart - e.touches[0].clientY;
        tStart = e.touches[0].clientY;
        pos = Math.max(0, Math.min(pos, track.scrollHeight - vp.offsetHeight));
        vp.scrollTop = pos;
      });

      function frame() {
        if (!paused) {
          if (hold > 0) { hold--; }
          else {
            pos += speed * dir;
            var max = track.scrollHeight - vp.offsetHeight;
            if (max <= 0) { requestAnimationFrame(frame); return; }
            if (pos >= max) { pos = max; dir = -1; hold = HOLD; }
            else if (pos <= 0) { pos = 0; dir = 1; hold = HOLD; }
            vp.scrollTop = pos;
          }
        }
        requestAnimationFrame(frame);
      }
      requestAnimationFrame(frame);
    })();

    /* ── Auto-scroll line breakdown ────────────────────────────── */
    (function () {
      var vp = document.getElementById('lineVP');
      var track = document.getElementById('lineTrack');
      var btn = document.getElementById('linePauseBtn');
      if (!vp || !track) return;
      var pos = 0, dir = 1, hold = 0;
      var HOLD = 80, speed = 0.5;
      var paused = false, manualPause = false;

      function setBtn() {
        btn.innerHTML = manualPause ? '&#9654;' : '&#9646;&#9646;';
        btn.classList.toggle('pause-active', manualPause);
        btn.title = manualPause ? 'Resume' : 'Pause';
      }
      window.lineTogglePause = function () { manualPause = !manualPause; paused = manualPause; setBtn(); };
      window.lineScrollUp = function () { pos = Math.max(0, pos - vp.offsetHeight * .6); vp.scrollTop = pos; };
      window.lineScrollDown = function () { var max = track.scrollHeight - vp.offsetHeight; pos = Math.min(max, pos + vp.offsetHeight * .6); vp.scrollTop = pos; };

      vp.addEventListener('mouseenter', function () { if (!manualPause) paused = true; });
      vp.addEventListener('mouseleave', function () { if (!manualPause) paused = false; });

      function frame() {
        if (!paused) {
          if (hold > 0) { hold--; }
          else {
            pos += speed * dir;
            var max = track.scrollHeight - vp.offsetHeight;
            if (max <= 0) { requestAnimationFrame(frame); return; }
            if (pos >= max) { pos = max; dir = -1; hold = HOLD; }
            else if (pos <= 0) { pos = 0; dir = 1; hold = HOLD; }
            vp.scrollTop = pos;
          }
        }
        requestAnimationFrame(frame);
      }
      requestAnimationFrame(frame);
    })();

    /* ── Auto-scroll Alert panel ───────────────────────────────── */
    (function () {
      var vp = document.getElementById('alertVP');
      var track = document.getElementById('alertTrack');
      var btn = document.getElementById('alertPauseBtn');
      if (!vp || !track) return;
      var pos = 0, dir = 1, hold = 0;
      var HOLD = 100, speed = 0.4;
      var paused = false, manualPause = false;

      function setBtn() {
        btn.innerHTML = manualPause ? '&#9654;' : '&#9646;&#9646;';
        btn.classList.toggle('pause-active', manualPause);
        btn.title = manualPause ? 'Resume' : 'Pause';
      }

      window.alertTogglePause = function () {
        manualPause = !manualPause;
        paused = manualPause;
        setBtn();
      };
      window.alertScrollUp = function () {
        pos = Math.max(0, pos - vp.offsetHeight * 0.6);
        vp.scrollTop = pos;
      };
      window.alertScrollDown = function () {
        var max = track.scrollHeight - vp.offsetHeight;
        pos = Math.min(max, pos + vp.offsetHeight * 0.6);
        vp.scrollTop = pos;
      };

      vp.addEventListener('mouseenter', function () { if (!manualPause) paused = true; });
      vp.addEventListener('mouseleave', function () { if (!manualPause) paused = false; });

      function frame() {
        if (!paused) {
          if (hold > 0) { hold--; }
          else {
            pos += speed * dir;
            var max = track.scrollHeight - vp.offsetHeight;
            if (max <= 0) { requestAnimationFrame(frame); return; }
            if (pos >= max) { pos = max; dir = -1; hold = HOLD; }
            else if (pos <= 0) { pos = 0; dir = 1; hold = HOLD; }
            vp.scrollTop = pos;
          }
        }
        requestAnimationFrame(frame);
      }
      requestAnimationFrame(frame);
    })();

    /* ── OEE Chart per machine — auto-scroll left/right ─────────── */
    (function () {
      var LABELS = <?= $chartLabels ?>;
      var MACHINES = <?= $chartDatasets ?>;

      if (!MACHINES.length) return;

      var idx = 0;
      var autoPlay = true;
      var INTERVAL = 4500;   // ms per machine
      var timer = null;

      var ctx = document.getElementById('oeeChart').getContext('2d');

      function ptColor(v) { return v === null ? 'transparent' : '#8B1A1A'; }
      function oeeColor(v) { return v > 0 ? 'var(--maroon)' : 'var(--text-4)'; }

      var chart = new Chart(ctx, {
        type: 'line',
        data: {
          labels: LABELS,
          datasets: [{
            data: [],
            borderColor: '#8B1A1A',
            backgroundColor: 'rgba(139,26,26,.07)',
            borderWidth: 2.5,
            pointBackgroundColor: [],
            pointBorderColor: '#fff',
            pointBorderWidth: 1.5,
            pointRadius: 4,
            fill: true, spanGaps: true,
            lineTension: .4
          }]
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          animation: { duration: 500, easing: 'easeInOutQuart' },
          legend: { display: false },
          scales: {
            xAxes: [{ ticks: { fontColor: '#9ca3af', fontSize: 9, maxRotation: 45, minRotation: 45 }, gridLines: { color: 'rgba(0,0,0,.05)', drawBorder: false } }],
            yAxes: [{ ticks: { fontColor: '#9ca3af', fontSize: 9, min: 0, max: 100, callback: function (v) { return v + '%'; } }, gridLines: { color: 'rgba(0,0,0,.06)', drawBorder: false } }]
          },
          tooltips: {
            callbacks: {
              label: function (t) { return t.yLabel !== null ? t.yLabel + '%' : 'N/A'; }
            },
            backgroundColor: 'rgba(17,24,39,.85)',
            titleFontSize: 10, bodyFontSize: 11,
            displayColors: false, cornerRadius: 6
          },
          layout: { padding: { top: 8, bottom: 4 } }
        },
        plugins: [{
          afterDraw: function (c) {
            var ctx2 = c.ctx, ya = c.scales['y-axis-0'];
            [[85, 'rgba(26,127,84,.5)', '85%'], [65, 'rgba(245,158,11,.5)', '65%']].forEach(function (l) {
              var y = ya.getPixelForValue(l[0]);
              ctx2.save(); ctx2.setLineDash([4, 3]); ctx2.strokeStyle = l[1]; ctx2.lineWidth = 1;
              ctx2.beginPath(); ctx2.moveTo(c.chartArea.left, y); ctx2.lineTo(c.chartArea.right, y); ctx2.stroke();
              ctx2.fillStyle = l[1]; ctx2.font = 'bold 8px Segoe UI'; ctx2.fillText(l[2], c.chartArea.left + 3, y - 3);
              ctx2.restore();
            });
          }
        }]
      });

      function showMachine(i) {
        var m = MACHINES[i];
        var ds = chart.data.datasets[0];
        ds.data = m.data;
        ds.pointBackgroundColor = m.data.map(ptColor);
        chart.update();

        document.getElementById('chartMachName').textContent = m.name;
        document.getElementById('chartMachLine').textContent = m.line;
        var ov = m.oee > 0 ? m.oee + '%' : 'N/A';
        var el = document.getElementById('chartMachOee');
        el.textContent = 'OEE: ' + ov;
        el.style.color = oeeColor(m.oee);
        document.getElementById('chartMachIdx').textContent = (i + 1) + ' / ' + MACHINES.length;

        // progress bar: slide indicator
        var pct = MACHINES.length > 1 ? Math.round(i / (MACHINES.length - 1) * 100) : 100;
        document.getElementById('chartProgressFill').style.width = pct + '%';
      }

      /* Expose nav functions globally */
      window.nextMachine = function () {
        resetTimer();
        idx = (idx + 1) % MACHINES.length;
        showMachine(idx);
      };
      window.prevMachine = function () {
        resetTimer();
        idx = (idx - 1 + MACHINES.length) % MACHINES.length;
        showMachine(idx);
      };

      function resetTimer() {
        if (timer) clearInterval(timer);
        if (autoPlay) startTimer();
      }
      function startTimer() {
        timer = setInterval(function () {
          idx = (idx + 1) % MACHINES.length;
          showMachine(idx);
        }, INTERVAL);
      }

      /* Pause auto on hover */
      var wrap = document.getElementById('oeeChart').parentElement;
      wrap.addEventListener('mouseenter', function () {
        if (timer) { clearInterval(timer); timer = null; }
        document.getElementById('chartAutoIcon').textContent = '⏸ PAUSE';
      });
      wrap.addEventListener('mouseleave', function () {
        startTimer();
        document.getElementById('chartAutoIcon').textContent = '▶ AUTO';
      });

      showMachine(0);
      if (MACHINES.length > 1) startTimer();

      /* Expose chart update for AJAX */
      window.__chartUpdate = function (newMachines, newLabels) {
        MACHINES = newMachines;
        if (idx >= MACHINES.length) idx = 0;
        showMachine(idx);
      };
    })();

    /* ══════════════════════════════════════════════════════════
       AJAX LIVE REFRESH — smooth, no page reload
       ══════════════════════════════════════════════════════════ */
    (function () {
      var INTERVAL = 30000; // 30 seconds
      var syncDot = document.getElementById('syncDot');

      /* ── Helpers ── */
      function flash(el) { if (el) { el.classList.remove('val-flash'); void el.offsetWidth; el.classList.add('val-flash'); } }

      function setText(sel, val) { var el = document.querySelector(sel); if (el && el.textContent !== String(val)) { el.textContent = val; flash(el); } }
      function setHtml(sel, val) { var el = document.querySelector(sel); if (el) { el.innerHTML = val; } }

      function oeeColor(v) { return v >= 85 ? '#16a34a' : v >= 65 ? '#d97706' : v > 0 ? '#dc2626' : 'var(--text-4)'; }
      function barColor(v) { return v >= 85 ? '#16a34a' : v >= 65 ? '#d97706' : v > 0 ? '#dc2626' : '#eef0f3'; }

      /* ── Update header summary ── */
      function updateSummary(s) {
        setText('#hStat-total', s.total);
        setText('#hStat-run', s.run);
        setText('#hStat-stop', s.stop);
        setText('#hStat-avgOee', s.avg_oee + '%');
        setText('#hStat-ex', s.oee_excellent);
        setText('#hStat-good', s.oee_good);
        setText('#hStat-poor', s.oee_poor);
        setText('#hStat-alert', s.alert_count);
        // Summary panel
        setText('#sum-avgOee', s.avg_oee + '%');
        setText('#sum-running', s.run + '/' + s.total);
        setText('#sum-alert', s.alert_count);
        var el = document.getElementById('sum-alert');
        if (el) el.style.color = s.alert_count > 0 ? 'var(--danger)' : 'var(--ok)';
        // Distribution bar
        var tot = Math.max(s.total, 1);
        function setPct(id, pct) { var e = document.getElementById(id); if (e) e.style.width = pct + '%'; }
        setPct('dist-ex', Math.round(s.oee_excellent / tot * 100));
        setPct('dist-good', Math.round(s.oee_good / tot * 100));
        setPct('dist-poor', Math.round(s.oee_poor / tot * 100));
        setText('#dist-ex-lbl', '≥85% (' + s.oee_excellent + ')');
        setText('#dist-good-lbl', '65–85% (' + s.oee_good + ')');
        setText('#dist-poor-lbl', '<65% (' + s.oee_poor + ')');
      }

      /* ── Update machine cards ── */
      function updateCards(machines) {
        machines.forEach(function (m) {
          var card = document.querySelector('.mc[data-machine-id="' + m.id + '"]');
          if (!card) return;
          var oee = parseFloat(m.oee_pct) || 0;
          var avl = parseFloat(m.availability) || 0;
          var prf = parseFloat(m.performance) || 0;
          var qlt = parseFloat(m.quality) || 0;

          // OEE number
          var oeeEl = card.querySelector('.mc-oee-val');
          if (oeeEl) { var nv = oee > 0 ? oee : 'N/A'; if (oeeEl.textContent !== String(nv)) { oeeEl.textContent = nv; flash(oeeEl); } oeeEl.style.color = oee > 0 ? 'var(--text-1)' : 'var(--text-4)'; }

          // Bars
          var bars = card.querySelectorAll('.bar-fill');
          var vals = card.querySelectorAll('.bar-val');
          var apq = [avl, prf, qlt];
          apq.forEach(function (v, i) {
            if (bars[i]) { bars[i].style.width = Math.min(v, 100) + '%'; bars[i].style.background = barColor(v); }
            if (vals[i]) { var t = v > 0 ? v + '%' : '—'; if (vals[i].textContent !== t) { vals[i].textContent = t; flash(vals[i]); } vals[i].style.color = barColor(v); }
          });

          // Sensor boxes
          var sBoxes = card.querySelectorAll('.snsr-val');
          var sVals = [
            m.v_r !== null ? parseFloat(m.v_r).toFixed(1) + ' V' : '—',
            m.a_r !== null ? parseFloat(m.a_r).toFixed(2) + ' A' : '—',
            m.temp_panel !== null ? parseFloat(m.temp_panel).toFixed(1) + ' °C' : '—',
            m.hum_panel !== null ? parseFloat(m.hum_panel).toFixed(1) + ' %' : '—'
          ];
          sVals.forEach(function (v, i) { if (sBoxes[i] && sBoxes[i].textContent !== v) { sBoxes[i].textContent = v; flash(sBoxes[i]); } });

          // Status pill
          var pill = card.querySelector('.status-pill');
          if (pill) {
            var wasRun = pill.classList.contains('run');
            var isRun = m.status === 'run';
            if (wasRun !== isRun) {
              pill.className = 'status-pill ' + (isRun ? 'run' : 'stop');
              pill.innerHTML = '<i class="fas ' + (isRun ? 'fa-play' : 'fa-stop') + '"></i> ' + (isRun ? 'RUN' : 'STOP');
            }
          }

          // Vib pill
          var vs = (m.vib_status || 'normal').toLowerCase();
          var vPill = card.querySelector('.vib-pill');
          if (vPill) { vPill.className = 'vib-pill ' + vs; vPill.textContent = vs.toUpperCase(); }

          // Card status class
          card.className = 'mc ' + (m.status || 'stop') + ' ' + (oee >= 85 ? 'oee-ex' : oee >= 65 ? 'oee-gd' : oee >= 45 ? 'oee-ok' : oee > 0 ? 'oee-bd' : '');
          card.setAttribute('data-machine-id', m.id);

          // Sensor age
          var ageEl = card.querySelector('.mc-age');
          if (ageEl && m.sensor_at) {
            var sAge = Math.round((Date.now() / 1000 - new Date(m.sensor_at).getTime() / 1000) / 60);
            var ageStr = sAge < 60 ? sAge + 'm' : Math.round(sAge / 60) + 'h';
            ageEl.textContent = ageStr + ' ago';
            ageEl.style.color = sAge <= 5 ? 'var(--ok)' : sAge <= 60 ? 'var(--warn)' : 'var(--danger)';
          }
        });
      }

      /* ── Update line breakdown ── */
      function updateLines(lineStats) {
        lineStats.forEach(function (ls) {
          var row = document.querySelector('tr[data-line="' + encodeURIComponent(ls.name) + '"]');
          if (!row) return;
          var cells = row.querySelectorAll('td');
          if (!cells[1]) return;
          cells[1].innerHTML = '<span style="font-weight:800;color:var(--ok);">' + ls.run + '</span><span style="color:var(--text-4);">/' + ls.total + '</span>';
          if (cells[2]) { cells[2].textContent = ls.avg_oee > 0 ? ls.avg_oee + '%' : '—'; }
          var fill = row.querySelector('.line-mini-fill');
          if (fill) { fill.style.width = Math.min(ls.avg_oee, 100) + '%'; }
        });
      }

      /* ── Update abnormal panel ── */
      function updateAbnormal(abnormal) {
        var track = document.getElementById('alertTrack');
        if (!track) return;
        // Re-render entire track (small data, safe)
        if (!abnormal.length) {
          track.innerHTML = '<div class="no-alert"><i class="fas fa-shield-alt"></i><div class="t1">All Machines Normal</div><div class="t2">No abnormal conditions detected</div></div>';
          return;
        }
        var html = '';
        abnormal.forEach(function (ab) {
          var vs = (ab.vib_status || 'normal').toLowerCase();
          var rms = parseFloat(ab.rms_overall) || 0;
          var tmp = parseFloat(ab.temp_panel) || 0;
          var vx = ab.vib_x !== null ? parseFloat(ab.vib_x) : null;
          var vy = ab.vib_y !== null ? parseFloat(ab.vib_y) : null;
          var vz = ab.vib_z !== null ? parseFloat(ab.vib_z) : null;

          var issues = [];
          if (vs === 'critical' || vs === 'warning') {
            if (vx !== null) issues.push({ sev: vs, txt: 'Vib X: ' + vx.toFixed(2) + ' mm/s' + (vx > 7.1 ? ' (critical)' : vx > 2.8 ? ' (warning)' : '') });
            if (vy !== null) issues.push({ sev: vs, txt: 'Vib Y: ' + vy.toFixed(2) + ' mm/s' + (vy > 7.1 ? ' (critical)' : vy > 2.8 ? ' (warning)' : '') });
            if (vz !== null) issues.push({ sev: vs, txt: 'Vib Z: ' + vz.toFixed(2) + ' mm/s' + (vz > 7.1 ? ' (critical)' : vz > 2.8 ? ' (warning)' : '') });
            if (rms > 0) issues.push({ sev: vs, txt: 'RMS Overall: ' + rms.toFixed(2) + ' mm/s' });
          }
          if (ab.status === 'stop') issues.push({ sev: 'stop', txt: 'Machine not operating' });
          if (tmp > 50) issues.push({ sev: 'critical', txt: 'Panel Temp: ' + tmp.toFixed(1) + ' °C' });
          else if (tmp > 40) issues.push({ sev: 'warn', txt: 'Panel Temp: ' + tmp.toFixed(1) + ' °C' });

          var hasCrit = vs === 'critical' || tmp > 50;
          var level, lColor, lBg, lBorder, blink;
          if (hasCrit) { level = 'CRITICAL'; lColor = '#dc2626'; lBg = '#fef2f2'; lBorder = '#fca5a5'; blink = 'animation:blink .8s infinite;'; }
          else if (ab.status === 'stop') { level = 'STOP'; lColor = 'var(--maroon)'; lBg = 'var(--maroon-lt)'; lBorder = '#ddbdbd'; blink = ''; }
          else if (vs === 'warning') { level = 'WARNING'; lColor = '#d97706'; lBg = '#fffbeb'; lBorder = '#fde68a'; blink = ''; }
          else { level = 'INFO'; lColor = '#374151'; lBg = '#f0f2f5'; lBorder = 'var(--border-dk)'; blink = ''; }

          var issHtml = issues.map(function (iss) {
            var ic = iss.sev === 'critical' ? '#dc2626' : iss.sev === 'stop' ? 'var(--maroon)' : '#d97706';
            var id = iss.sev === 'critical' ? '●' : iss.sev === 'stop' ? '◼' : '◆';
            return '<span style="font-size:.68rem;font-weight:600;color:' + ic + ';display:flex;align-items:center;gap:5px;"><span style="font-size:.45rem;flex-shrink:0;">' + id + '</span>' + iss.txt + '</span>';
          }).join('');

          html += '<div style="display:grid;grid-template-columns:140px 80px 1fr;align-items:start;gap:0;padding:8px 14px;border-bottom:1px solid var(--border);">'
            + '<div style="padding-top:2px;"><div style="font-size:.8rem;font-weight:800;color:var(--text-1);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + ab.name + '</div>'
            + '<div style="font-size:.58rem;color:var(--text-3);margin-top:2px;">' + (ab.line_name || '—') + '</div></div>'
            + '<div style="text-align:center;padding-top:3px;"><span style="display:inline-block;padding:3px 10px;border-radius:12px;font-size:.63rem;font-weight:800;text-transform:uppercase;background:' + lBg + ';color:' + lColor + ';border:1px solid ' + lBorder + ';' + blink + '">' + level + '</span></div>'
            + '<div style="padding-left:12px;display:flex;flex-direction:column;gap:3px;">' + (issHtml || '<span style="font-size:.68rem;color:var(--text-4);">—</span>') + '</div>'
            + '</div>';
        });
        track.innerHTML = html;
      }

      /* ── Update ticker (only rebuild if data changed) ── */
      var _lastTickerHash = '';
      function updateTicker(machines) {
        var inner = document.querySelector('.hdr-ticker .ticker-inner');
        if (!inner) return;
        var txt = machines.map(function (m) {
          return '  <strong>' + m.name + '</strong> OEE:' + (parseFloat(m.oee_pct) || 0).toFixed(1) + '% A:' + (parseFloat(m.availability) || 0).toFixed(1) + '% P:' + (parseFloat(m.performance) || 0).toFixed(1) + '% Q:' + (parseFloat(m.quality) || 0).toFixed(1) + '% [' + m.status.toUpperCase() + '] |';
        }).join('');
        txt += '  ■ Live update ■ ';
        // Only update DOM if content changed (prevents animation restart)
        if (txt !== _lastTickerHash) {
          _lastTickerHash = txt;
          inner.innerHTML = txt;
        }
      }

      /* ── Main fetch with isFetching guard ── */
      var isFetching = false;
      function doRefresh() {
        if (isFetching) return;
        isFetching = true;
        syncDot.className = 'syncing';
        fetch('api/tv_data.php', { cache: 'no-store' })
          .then(function (r) { return r.json(); })
          .then(function (d) {
            isFetching = false;
            syncDot.className = '';
            updateSummary(d.summary);
            updateCards(d.machines);
            updateLines(d.line_stats);
            updateAbnormal(d.abnormal);
            updateTicker(d.machines);
            if (window.__chartUpdate) window.__chartUpdate(d.chart_machines, d.chart_labels);
            document.title = 'OEE Live Monitor — ' + d.ts;
          })
          .catch(function () {
            isFetching = false;
            syncDot.className = 'error';
          });
      }

      // Start interval
      setInterval(doRefresh, INTERVAL);

      // Warm-up: run after the first 2 seconds
      setTimeout(doRefresh, 2000);
    })();
  </script>
</body>

</html>