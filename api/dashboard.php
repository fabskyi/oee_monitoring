<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'kpi':
        getKpi($pdo);
        break;
    case 'oee_trend':
        getOeeTrend($pdo);
        break;
    case 'machines_table':
        getMachinesTable($pdo);
        break;
    case 'recent_alerts':
        getRecentAlerts($pdo);
        break;
    case 'upcoming_maintenance':
        getUpcomingMaintenance($pdo);
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        break;
}

function getKpi($pdo) {
    $today = date('Y-m-d');

    // OEE today: average oee_score from oee_daily for today
    $stmt = $pdo->prepare("SELECT AVG(oee_score) as oee_today FROM oee_daily WHERE snap_date = ?");
    $stmt->execute([$today]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $oee_today = $row['oee_today'] !== null ? round((float)$row['oee_today'], 1) : null;

    // Machines running
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM machines WHERE status = 'run'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $machines_running = (int)$row['cnt'];

    // Active alerts (unacknowledged)
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM alerts WHERE acknowledged = 0");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $active_alerts = (int)$row['cnt'];

    // Maintenance due: upcoming maintenance within next 7 days
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM maintenance_records WHERE maint_date BETWEEN ? AND DATE_ADD(?, INTERVAL 7 DAY)");
    $stmt->execute([$today, $today]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $maintenance_due = (int)$row['cnt'];

    echo json_encode([
        'oee_today'       => $oee_today,
        'machines_running'=> $machines_running,
        'active_alerts'   => $active_alerts,
        'maintenance_due' => $maintenance_due,
    ]);
}

function getOeeTrend($pdo) {
    $days = isset($_GET['days']) ? max(1, (int)$_GET['days']) : 7;
    $start = date('Y-m-d', strtotime("-{$days} days"));

    $stmt = $pdo->prepare("
        SELECT od.snap_date as date, m.name as machine_name, od.oee_score
        FROM oee_daily od
        JOIN machines m ON m.id = od.machine_id
        WHERE od.snap_date >= ?
        ORDER BY od.snap_date ASC, m.name ASC
    ");
    $stmt->execute([$start]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($rows as $row) {
        $result[] = [
            'date'         => $row['snap_date'],
            'machine_name' => $row['machine_name'],
            'oee_score'    => round((float)$row['oee_score'], 1),
        ];
    }

    echo json_encode($result);
}

function getMachinesTable($pdo) {
    // Get all machines with latest OEE and latest sensor reading
    $stmt = $pdo->query("
        SELECT
            m.id,
            m.name,
            m.model,
            m.status,
            pl.name as line_name,
            od.oee_score,
            od.availability,
            od.performance,
            od.quality,
            od.snap_date,
            sr.v_r, sr.v_s, sr.v_t,
            sr.a_r, sr.a_s, sr.a_t,
            sr.temp_panel,
            sr.hum_panel,
            sr.recorded_at as sensor_time
        FROM machines m
        LEFT JOIN production_lines pl ON pl.id = m.line_id
        LEFT JOIN (
            SELECT od1.*
            FROM oee_daily od1
            INNER JOIN (
                SELECT machine_id, MAX(snap_date) as max_date
                FROM oee_daily
                GROUP BY machine_id
            ) od2 ON od1.machine_id = od2.machine_id AND od1.snap_date = od2.max_date
        ) od ON od.machine_id = m.id
        LEFT JOIN (
            SELECT sr1.*
            FROM sensor_readings sr1
            INNER JOIN (
                SELECT machine_id, MAX(recorded_at) as max_time
                FROM sensor_readings
                GROUP BY machine_id
            ) sr2 ON sr1.machine_id = sr2.machine_id AND sr1.recorded_at = sr2.max_time
        ) sr ON sr.machine_id = m.id
        ORDER BY pl.name ASC, m.sort_order ASC, m.name ASC
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($rows as $row) {
        $result[] = [
            'id'           => (int)$row['id'],
            'name'         => $row['name'],
            'model'        => $row['model'],
            'status'       => $row['status'],
            'line_name'    => $row['line_name'],
            'oee_score'    => $row['oee_score'] !== null ? round((float)$row['oee_score'], 1) : null,
            'availability' => $row['availability'] !== null ? round((float)$row['availability'], 1) : null,
            'performance'  => $row['performance'] !== null ? round((float)$row['performance'], 1) : null,
            'quality'      => $row['quality'] !== null ? round((float)$row['quality'], 1) : null,
            'snap_date'    => $row['snap_date'],
            'v_r'          => $row['v_r'] !== null ? (float)$row['v_r'] : null,
            'v_s'          => $row['v_s'] !== null ? (float)$row['v_s'] : null,
            'v_t'          => $row['v_t'] !== null ? (float)$row['v_t'] : null,
            'a_r'          => $row['a_r'] !== null ? (float)$row['a_r'] : null,
            'a_s'          => $row['a_s'] !== null ? (float)$row['a_s'] : null,
            'a_t'          => $row['a_t'] !== null ? (float)$row['a_t'] : null,
            'temp_panel'   => $row['temp_panel'] !== null ? (float)$row['temp_panel'] : null,
            'hum_panel'    => $row['hum_panel'] !== null ? (float)$row['hum_panel'] : null,
            'sensor_time'  => $row['sensor_time'],
        ];
    }

    echo json_encode($result);
}

function getRecentAlerts($pdo) {
    $stmt = $pdo->query("
        SELECT
            a.id,
            m.name as machine_name,
            a.sensor_key,
            a.sensor_value,
            a.threshold_lo,
            a.threshold_hi,
            a.severity,
            a.created_at
        FROM alerts a
        JOIN machines m ON m.id = a.machine_id
        WHERE a.acknowledged = 0
        ORDER BY a.created_at DESC
        LIMIT 5
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($rows as $row) {
        $result[] = [
            'id'           => (int)$row['id'],
            'machine_name' => $row['machine_name'],
            'sensor_key'   => $row['sensor_key'],
            'sensor_value' => (float)$row['sensor_value'],
            'threshold_lo' => $row['threshold_lo'] !== null ? (float)$row['threshold_lo'] : null,
            'threshold_hi' => $row['threshold_hi'] !== null ? (float)$row['threshold_hi'] : null,
            'severity'     => $row['severity'],
            'created_at'   => $row['created_at'],
        ];
    }

    echo json_encode($result);
}

function getUpcomingMaintenance($pdo) {
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT
            mr.id,
            m.name as machine_name,
            mr.type,
            mr.description,
            mr.technician,
            mr.maint_date,
            mr.duration_min,
            mr.cost
        FROM maintenance_records mr
        JOIN machines m ON m.id = mr.machine_id
        WHERE mr.maint_date >= ?
        ORDER BY mr.maint_date ASC
        LIMIT 5
    ");
    $stmt->execute([$today]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($rows as $row) {
        $result[] = [
            'id'           => (int)$row['id'],
            'machine_name' => $row['machine_name'],
            'type'         => $row['type'],
            'description'  => $row['description'],
            'technician'   => $row['technician'],
            'maint_date'   => $row['maint_date'],
            'duration_min' => $row['duration_min'] !== null ? (int)$row['duration_min'] : null,
            'cost'         => $row['cost'] !== null ? (float)$row['cost'] : null,
        ];
    }

    echo json_encode($result);
}
