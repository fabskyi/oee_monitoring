<?php
require_once __DIR__ . '/../includes/config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    $export = $_GET['export'] ?? '';
    if ($export === 'print') {
        echo '<p style="color:red;">Unauthorized. Please login.</p>';
    } else {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    }
    exit;
}

$action = $_GET['action'] ?? '';
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
$machine_id = (isset($_GET['machine_id']) && $_GET['machine_id'] !== '') ? (int) $_GET['machine_id'] : null;
$export = $_GET['export'] ?? '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from))
    $from = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))
    $to = date('Y-m-d');

// ─────────────────────────────────────────────
// DATA FETCHERS
// ─────────────────────────────────────────────

function reportsFetchOeeSummary(PDO $pdo, $from, $to, $machine_id)
{
    $where = "WHERE od.snap_date BETWEEN :from AND :to";
    $params = [':from' => $from, ':to' => $to];
    if ($machine_id) {
        $where .= " AND od.machine_id = :mid";
        $params[':mid'] = $machine_id;
    }
    $sql = "SELECT od.machine_id, m.name AS machine_name,
                   COUNT(*) AS days,
                   ROUND(AVG(od.availability),2) AS avg_availability,
                   ROUND(AVG(od.performance),2)  AS avg_performance,
                   ROUND(AVG(od.quality),2)       AS avg_quality,
                   ROUND(AVG(od.oee_score),2)     AS avg_oee,
                   ROUND(MIN(od.oee_score),2)     AS min_oee,
                   ROUND(MAX(od.oee_score),2)     AS max_oee,
                   SUM(od.planned_time)            AS total_planned,
                   SUM(od.actual_run)              AS total_run
            FROM oee_daily od
            JOIN machines m ON od.machine_id = m.id
            {$where}
            GROUP BY od.machine_id, m.name
            ORDER BY m.name";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $summary = $st->fetchAll(PDO::FETCH_ASSOC);

    $sql2 = "SELECT od.snap_date,
                    ROUND(AVG(od.availability),2) AS availability,
                    ROUND(AVG(od.performance),2)  AS performance,
                    ROUND(AVG(od.quality),2)       AS quality,
                    ROUND(AVG(od.oee_score),2)     AS oee_score
             FROM oee_daily od
             JOIN machines m ON od.machine_id = m.id
             {$where}
             GROUP BY od.snap_date
             ORDER BY od.snap_date";
    $st2 = $pdo->prepare($sql2);
    $st2->execute($params);
    $trend = $st2->fetchAll(PDO::FETCH_ASSOC);

    return ['summary' => $summary, 'trend' => $trend];
}

function reportsFetchAlertReport(PDO $pdo, $from, $to)
{
    $params = [':from' => $from . ' 00:00:00', ':to' => $to . ' 23:59:59'];

    $st = $pdo->prepare(
        "SELECT severity, COUNT(*) AS total, SUM(acknowledged) AS ack_count
         FROM alerts WHERE created_at BETWEEN :from AND :to GROUP BY severity"
    );
    $st->execute($params);
    $stats = $st->fetchAll(PDO::FETCH_ASSOC);

    $st2 = $pdo->prepare(
        "SELECT a.id, m.name AS machine_name, a.sensor_key,
                a.sensor_value, a.threshold_lo, a.threshold_hi,
                a.severity, a.acknowledged, a.acknowledged_by,
                a.acknowledged_at, a.created_at
         FROM alerts a
         JOIN machines m ON a.machine_id = m.id
         WHERE a.created_at BETWEEN :from AND :to
         ORDER BY a.created_at DESC"
    );
    $st2->execute($params);
    $list = $st2->fetchAll(PDO::FETCH_ASSOC);

    $st3 = $pdo->prepare(
        "SELECT m.name AS machine_name, COUNT(*) AS alert_count,
                SUM(CASE WHEN a.severity='critical' THEN 1 ELSE 0 END) AS critical,
                SUM(CASE WHEN a.severity='warning'  THEN 1 ELSE 0 END) AS warning
         FROM alerts a
         JOIN machines m ON a.machine_id = m.id
         WHERE a.created_at BETWEEN :from AND :to
         GROUP BY a.machine_id, m.name
         ORDER BY alert_count DESC"
    );
    $st3->execute($params);
    $by_machine = $st3->fetchAll(PDO::FETCH_ASSOC);

    return ['stats' => $stats, 'list' => $list, 'by_machine' => $by_machine];
}

function reportsFetchEnergyReport(PDO $pdo, $from, $to, $machine_id)
{
    $where = "WHERE sr.recorded_at BETWEEN :from AND :to";
    $params = [':from' => $from . ' 00:00:00', ':to' => $to . ' 23:59:59'];
    if ($machine_id) {
        $where .= " AND sr.machine_id = :mid";
        $params[':mid'] = $machine_id;
    }

    $sql = "SELECT sr.machine_id, m.name AS machine_name,
                   COUNT(*) AS readings,
                   ROUND(AVG((sr.e_r + sr.e_s + sr.e_t)),4) AS avg_total_kwh,
                   ROUND(SUM((sr.e_r + sr.e_s + sr.e_t)),4) AS sum_total_kwh,
                   ROUND(AVG((sr.v_r + sr.v_s + sr.v_t)/3),2) AS avg_voltage,
                   ROUND(AVG((sr.a_r + sr.a_s + sr.a_t)/3),2) AS avg_current,
                   ROUND(AVG(sr.temp_panel),2) AS avg_temp,
                   ROUND(AVG(sr.hum_panel),2)  AS avg_hum
            FROM sensor_readings sr
            JOIN machines m ON sr.machine_id = m.id
            {$where}
            GROUP BY sr.machine_id, m.name ORDER BY m.name";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $summary = $st->fetchAll(PDO::FETCH_ASSOC);

    $sql2 = "SELECT DATE(sr.recorded_at) AS day,
                    ROUND(AVG((sr.e_r + sr.e_s + sr.e_t)),4) AS avg_kwh,
                    ROUND(SUM((sr.e_r + sr.e_s + sr.e_t)),4) AS sum_kwh
             FROM sensor_readings sr
             JOIN machines m ON sr.machine_id = m.id
             {$where}
             GROUP BY DATE(sr.recorded_at) ORDER BY day";
    $st2 = $pdo->prepare($sql2);
    $st2->execute($params);
    $trend = $st2->fetchAll(PDO::FETCH_ASSOC);

    return ['summary' => $summary, 'trend' => $trend];
}

// ─────────────────────────────────────────────
// CSV EXPORT
// ─────────────────────────────────────────────

function reportsOutputCsv($filename, $headers, $rows)
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

function reportsBuildOeeCsv($data, $from, $to)
{
    $headers = [
        'Machine',
        'Days',
        'Avg Availability (%)',
        'Avg Performance (%)',
        'Avg Quality (%)',
        'Avg OEE (%)',
        'Min OEE (%)',
        'Max OEE (%)',
        'Total Planned (min)',
        'Total Run (min)'
    ];
    $rows = [];
    foreach ($data['summary'] as $r) {
        $rows[] = [
            $r['machine_name'],
            $r['days'],
            $r['avg_availability'],
            $r['avg_performance'],
            $r['avg_quality'],
            $r['avg_oee'],
            $r['min_oee'],
            $r['max_oee'],
            $r['total_planned'],
            $r['total_run']
        ];
    }
    reportsOutputCsv("oee_summary_{$from}_{$to}.csv", $headers, $rows);
}

function reportsBuildAlertCsv($data, $from, $to)
{
    $headers = [
        'ID',
        'Machine',
        'Sensor Key',
        'Value',
        'Threshold Lo',
        'Threshold Hi',
        'Severity',
        'Acknowledged',
        'Acknowledged By',
        'Acknowledged At',
        'Created At'
    ];
    $rows = [];
    foreach ($data['list'] as $r) {
        $rows[] = [
            $r['id'],
            $r['machine_name'],
            $r['sensor_key'],
            $r['sensor_value'],
            $r['threshold_lo'],
            $r['threshold_hi'],
            $r['severity'],
            $r['acknowledged'] ? 'Yes' : 'No',
            $r['acknowledged_by'] ?? '',
            $r['acknowledged_at'] ?? '',
            $r['created_at']
        ];
    }
    reportsOutputCsv("alert_report_{$from}_{$to}.csv", $headers, $rows);
}

function reportsBuildEnergyCsv($data, $from, $to)
{
    $headers = [
        'Machine',
        'Readings',
        'Avg Total kWh',
        'Sum Total kWh',
        'Avg Voltage (V)',
        'Avg Current (A)',
        'Avg Temp (C)',
        'Avg Humidity (%)'
    ];
    $rows = [];
    foreach ($data['summary'] as $r) {
        $rows[] = [
            $r['machine_name'],
            $r['readings'],
            $r['avg_total_kwh'],
            $r['sum_total_kwh'],
            $r['avg_voltage'],
            $r['avg_current'],
            $r['avg_temp'],
            $r['avg_hum']
        ];
    }
    reportsOutputCsv("e_report_{$from}_{$to}.csv", $headers, $rows);
}

// ─────────────────────────────────────────────
// PRINT HTML
// ─────────────────────────────────────────────

function reportsPrintWrap($title, $from, $to, $bodyHtml)
{
    $generatedBy = htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Unknown');
    $generatedAt = date('d/m/Y H:i:s');
    echo '<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>' . htmlspecialchars($title) . '</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; background: #fff; color: #333; padding: 20px; }
  .print-header { display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #4e73df; padding-bottom: 12px; margin-bottom: 16px; }
  .print-header .brand { font-size: 18px; font-weight: bold; color: #4e73df; }
  .print-header .brand span { color: #858796; font-size: 12px; display: block; font-weight: normal; }
  .print-header .meta { text-align: right; font-size: 11px; color: #555; }
  h3 { font-size: 13px; margin: 14px 0 8px; color: #333; border-left: 3px solid #4e73df; padding-left: 6px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
  th { background: #4e73df; color: #fff; padding: 6px 8px; text-align: left; font-size: 11px; }
  td { padding: 5px 8px; border-bottom: 1px solid #e3e6f0; font-size: 11px; }
  tr:nth-child(even) td { background: #f8f9fc; }
  .badge { display:inline-block; padding:2px 7px; border-radius:10px; font-size:10px; font-weight:bold; }
  .badge-danger    { background:#e74a3b; color:#fff; }
  .badge-warning   { background:#f6c23e; color:#333; }
  .badge-success   { background:#1cc88a; color:#fff; }
  .badge-info      { background:#36b9cc; color:#fff; }
  .badge-secondary { background:#858796; color:#fff; }
  .stat-box { display:inline-block; border:1px solid #e3e6f0; border-radius:6px; padding:8px 16px; margin:0 8px 8px 0; min-width:120px; text-align:center; }
  .stat-box .val { font-size:20px; font-weight:bold; color:#4e73df; }
  .stat-box .lbl { font-size:10px; color:#858796; margin-top:2px; }
  .print-footer { margin-top: 20px; border-top: 1px solid #e3e6f0; padding-top: 8px; font-size: 10px; color: #858796; text-align: center; }
  @media print { body { padding: 10px; } button { display:none; } }
</style>
</head>
<body>
<div class="print-header">
  <div class="brand">OEE Monitoring System<span>YANMAR DIESEL INDONESIA</span></div>
  <div class="meta">
    <strong>' . htmlspecialchars($title) . '</strong><br>
    Periode: ' . htmlspecialchars($from) . ' s/d ' . htmlspecialchars($to) . '<br>
    Dicetak oleh: ' . $generatedBy . '<br>
    Tanggal cetak: ' . $generatedAt . '
  </div>
</div>
' . $bodyHtml . '
<div class="print-footer">OEE Monitoring System &mdash; Hwacheon CNC Factory &mdash; Dicetak: ' . $generatedAt . '</div>
<script>window.onload = function(){ window.print(); }</script>
</body>
</html>';
    exit;
}

function reportsRenderOeePrint($data, $from, $to)
{
    $html = '<h3>Ringkasan OEE per Mesin</h3>';
    $html .= '<table><thead><tr>
        <th>Mesin</th><th>Hari</th><th>Avg Availability</th><th>Avg Performance</th>
        <th>Avg Quality</th><th>Avg OEE</th><th>Min OEE</th><th>Max OEE</th>
        <th>Total Planned (min)</th><th>Total Run (min)</th>
    </tr></thead><tbody>';
    foreach ($data['summary'] as $r) {
        $oee = (float) $r['avg_oee'];
        $cls = $oee >= 85 ? 'success' : ($oee >= 60 ? 'warning' : 'danger');
        $html .= '<tr>
            <td>' . htmlspecialchars($r['machine_name']) . '</td>
            <td>' . $r['days'] . '</td>
            <td>' . $r['avg_availability'] . '%</td>
            <td>' . $r['avg_performance'] . '%</td>
            <td>' . $r['avg_quality'] . '%</td>
            <td><span class="badge badge-' . $cls . '">' . $r['avg_oee'] . '%</span></td>
            <td>' . $r['min_oee'] . '%</td>
            <td>' . $r['max_oee'] . '%</td>
            <td>' . $r['total_planned'] . '</td>
            <td>' . $r['total_run'] . '</td>
        </tr>';
    }
    $html .= '</tbody></table>';
    $html .= '<h3>Tren OEE Harian</h3>';
    $html .= '<table><thead><tr><th>Tanggal</th><th>Availability</th><th>Performance</th><th>Quality</th><th>OEE Score</th></tr></thead><tbody>';
    foreach ($data['trend'] as $r) {
        $html .= '<tr><td>' . $r['snap_date'] . '</td><td>' . $r['availability'] . '%</td>
                  <td>' . $r['performance'] . '%</td><td>' . $r['quality'] . '%</td>
                  <td>' . $r['oee_score'] . '%</td></tr>';
    }
    $html .= '</tbody></table>';
    reportsPrintWrap('Laporan OEE Summary', $from, $to, $html);
}

function reportsRenderAlertPrint($data, $from, $to)
{
    $total = array_sum(array_column($data['stats'], 'total'));
    $critical = 0;
    $warning = 0;
    foreach ($data['stats'] as $s) {
        if ($s['severity'] === 'critical')
            $critical = $s['total'];
        if ($s['severity'] === 'warning')
            $warning = $s['total'];
    }
    $html = '<div style="margin-bottom:12px;">
        <div class="stat-box"><div class="val">' . $total . '</div><div class="lbl">Total Alert</div></div>
        <div class="stat-box"><div class="val" style="color:#e74a3b">' . $critical . '</div><div class="lbl">Critical</div></div>
        <div class="stat-box"><div class="val" style="color:#f6c23e">' . $warning . '</div><div class="lbl">Warning</div></div>
    </div>';
    $html .= '<h3>Alert per Mesin</h3>';
    $html .= '<table><thead><tr><th>Mesin</th><th>Total Alert</th><th>Critical</th><th>Warning</th></tr></thead><tbody>';
    foreach ($data['by_machine'] as $r) {
        $html .= '<tr><td>' . htmlspecialchars($r['machine_name']) . '</td><td>' . $r['alert_count'] . '</td>
                  <td><span class="badge badge-danger">' . $r['critical'] . '</span></td>
                  <td><span class="badge badge-warning">' . $r['warning'] . '</span></td></tr>';
    }
    $html .= '</tbody></table>';
    $html .= '<h3>Daftar Alert</h3>';
    $html .= '<table><thead><tr><th>#</th><th>Mesin</th><th>Sensor</th><th>Nilai</th><th>Lo</th><th>Hi</th><th>Severity</th><th>Ack</th><th>Waktu</th></tr></thead><tbody>';
    foreach ($data['list'] as $r) {
        $sev = $r['severity'] === 'critical' ? 'danger' : 'warning';
        $ack = $r['acknowledged'] ? '<span class="badge badge-success">Ya</span>' : '<span class="badge badge-secondary">Belum</span>';
        $html .= '<tr>
            <td>' . $r['id'] . '</td>
            <td>' . htmlspecialchars($r['machine_name']) . '</td>
            <td>' . htmlspecialchars($r['sensor_key']) . '</td>
            <td>' . $r['sensor_value'] . '</td>
            <td>' . $r['threshold_lo'] . '</td>
            <td>' . $r['threshold_hi'] . '</td>
            <td><span class="badge badge-' . $sev . '">' . ucfirst($r['severity']) . '</span></td>
            <td>' . $ack . '</td>
            <td>' . $r['created_at'] . '</td>
        </tr>';
    }
    $html .= '</tbody></table>';
    reportsPrintWrap('Laporan Alert', $from, $to, $html);
}

function reportsRenderEnergyPrint($data, $from, $to)
{
    $html = '<h3>Ringkasan Energi per Mesin</h3>';
    $html .= '<table><thead><tr><th>Mesin</th><th>Readings</th><th>Avg kWh</th><th>Sum kWh</th>
              <th>Avg Voltage (V)</th><th>Avg Current (A)</th><th>Avg Temp (C)</th><th>Avg Humidity (%)</th></tr></thead><tbody>';
    foreach ($data['summary'] as $r) {
        $html .= '<tr>
            <td>' . htmlspecialchars($r['machine_name']) . '</td>
            <td>' . $r['readings'] . '</td>
            <td>' . $r['avg_total_kwh'] . '</td>
            <td>' . $r['sum_total_kwh'] . '</td>
            <td>' . $r['avg_voltage'] . '</td>
            <td>' . $r['avg_current'] . '</td>
            <td>' . $r['avg_temp'] . '</td>
            <td>' . $r['avg_hum'] . '</td>
        </tr>';
    }
    $html .= '</tbody></table>';
    $html .= '<h3>Tren Energi Harian</h3>';
    $html .= '<table><thead><tr><th>Tanggal</th><th>Avg kWh</th><th>Sum kWh</th></tr></thead><tbody>';
    foreach ($data['trend'] as $r) {
        $html .= '<tr><td>' . $r['day'] . '</td><td>' . $r['avg_kwh'] . '</td><td>' . $r['sum_kwh'] . '</td></tr>';
    }
    $html .= '</tbody></table>';
    reportsPrintWrap('Laporan Energi', $from, $to, $html);
}

// ─────────────────────────────────────────────
// ROUTE
// ─────────────────────────────────────────────

try {
    $pdo = getDB();
    $data = null;

    switch ($action) {

        case 'oee_summary':
            $data = reportsFetchOeeSummary($pdo, $from, $to, $machine_id);
            if ($export === 'csv')
                reportsBuildOeeCsv($data, $from, $to);
            if ($export === 'print')
                reportsRenderOeePrint($data, $from, $to);
            break;

        case 'alert_report':
            $data = reportsFetchAlertReport($pdo, $from, $to);
            if ($export === 'csv')
                reportsBuildAlertCsv($data, $from, $to);
            if ($export === 'print')
                reportsRenderAlertPrint($data, $from, $to);
            break;

        case 'e_report':
            $data = reportsFetchEnergyReport($pdo, $from, $to, $machine_id);
            if ($export === 'csv')
                reportsBuildEnergyCsv($data, $from, $to);
            if ($export === 'print')
                reportsRenderEnergyPrint($data, $from, $to);
            break;

        case 'maintenance_report':
            $where = "WHERE mr.maint_date BETWEEN :from AND :to";
            $params = [':from' => $from, ':to' => $to];
            if ($machine_id) {
                $where .= " AND mr.machine_id = :mid";
                $params[':mid'] = $machine_id;
            }
            $st = $pdo->prepare(
                "SELECT mr.maint_date, m.name AS machine_name,
                        mr.type, mr.technician, mr.duration_min, mr.description
                 FROM maintenance_records mr
                 LEFT JOIN machines m ON m.id = mr.machine_id
                 {$where}
                 ORDER BY mr.maint_date DESC"
            );
            $st->execute($params);
            $data = ['records' => $st->fetchAll(PDO::FETCH_ASSOC)];
            break;

        case 'vibration_report':
            $where = "WHERE vr.recorded_at BETWEEN :from AND :to";
            $params = [':from' => $from . ' 00:00:00', ':to' => $to . ' 23:59:59'];
            if ($machine_id) {
                $where .= " AND vr.machine_id = :mid";
                $params[':mid'] = $machine_id;
            }
            $st = $pdo->prepare(
                "SELECT vr.recorded_at, m.name AS machine_name,
                        vr.sensor_1, vr.sensor_2, vr.sensor_3,
                        vr.rms_overall, vr.status
                 FROM vibration_readings vr
                 LEFT JOIN machines m ON m.id = vr.machine_id
                 {$where}
                 ORDER BY vr.recorded_at DESC LIMIT 500"
            );
            $st->execute($params);
            $data = ['readings' => $st->fetchAll(PDO::FETCH_ASSOC)];
            break;

        default:
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit;
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'action' => $action,
        'from' => $from,
        'to' => $to,
        'data' => $data,
    ]);

} catch (PDOException $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
