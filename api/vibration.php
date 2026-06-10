<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// Session check — only for browser-side GET actions; ESP32 POST insert is unauthenticated
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'GET' && !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

function vibCalcRms($s1, $s2, $s3) {
    return sqrt(($s1 * $s1 + $s2 * $s2 + $s3 * $s3) / 3);
}

function vibGetStatus($rms, PDO $pdo) {
    $warn = 2.8;
    $crit = 7.1;
    try {
        $stmt = $pdo->query(
            "SELECT setting_key, setting_value FROM system_settings
             WHERE setting_key IN ('vibration_warn_threshold','vibration_crit_threshold')"
        );
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['setting_key'] === 'vibration_warn_threshold') $warn = (float)$row['setting_value'];
            if ($row['setting_key'] === 'vibration_crit_threshold') $crit = (float)$row['setting_value'];
        }
    } catch (Exception $e) { /* use defaults */ }

    if ($rms >= $crit) return ['status' => 'critical', 'warn' => $warn, 'crit' => $crit];
    if ($rms >= $warn) return ['status' => 'warning',  'warn' => $warn, 'crit' => $crit];
    return ['status' => 'normal', 'warn' => $warn, 'crit' => $crit];
}

function vibCreateAlert(PDO $pdo, $machine_id, $sensor_key, $sensor_value, $thresh_lo, $thresh_hi, $severity) {
    // Avoid duplicate unacknowledged alerts
    $stmt = $pdo->prepare(
        "SELECT id FROM alerts WHERE machine_id = ? AND sensor_key = ? AND acknowledged = 0
         ORDER BY created_at DESC LIMIT 1"
    );
    $stmt->execute([$machine_id, $sensor_key]);
    if ($stmt->fetch()) return;

    $stmt = $pdo->prepare(
        "INSERT INTO alerts (machine_id, sensor_key, sensor_value, threshold_lo, threshold_hi, severity, acknowledged, created_at)
         VALUES (?, ?, ?, ?, ?, ?, 0, NOW())"
    );
    $stmt->execute([$machine_id, $sensor_key, $sensor_value, $thresh_lo, $thresh_hi, $severity]);
}

try {
    $pdo = getDB();

    // ── GET ───────────────────────────────────────────────────────────────────

    if ($method === 'GET') {

        if ($action === 'all_latest') {
            $sql = "SELECT vr.*, m.name AS machine_name
                    FROM vibration_readings vr
                    INNER JOIN (
                        SELECT machine_id, MAX(recorded_at) AS max_at
                        FROM vibration_readings
                        GROUP BY machine_id
                    ) latest ON vr.machine_id = latest.machine_id AND vr.recorded_at = latest.max_at
                    INNER JOIN machines m ON m.id = vr.machine_id
                    ORDER BY m.name";
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $rows]);
            exit;
        }

        $machine_id = isset($_GET['machine_id']) ? (int)$_GET['machine_id'] : 0;
        if (!$machine_id) {
            echo json_encode(['success' => false, 'message' => 'machine_id required']);
            exit;
        }

        switch ($action) {

            case 'latest':
                $stmt = $pdo->prepare(
                    "SELECT * FROM vibration_readings WHERE machine_id = ? ORDER BY recorded_at DESC LIMIT 1"
                );
                $stmt->execute([$machine_id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $row]);
                break;

            case 'history':
                $hours = isset($_GET['hours']) ? max(1, (int)$_GET['hours']) : 24;
                $stmt  = $pdo->prepare(
                    "SELECT id, machine_id, sensor_1, sensor_2, sensor_3, rms_overall, status, source, recorded_at
                     FROM vibration_readings
                     WHERE machine_id = ? AND recorded_at >= NOW() - INTERVAL ? HOUR
                     ORDER BY recorded_at ASC"
                );
                $stmt->execute([$machine_id, $hours]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $rows, 'hours' => $hours]);
                break;

            case 'stats':
                $stmt = $pdo->prepare(
                    "SELECT
                         AVG(sensor_1) AS avg_s1, MAX(sensor_1) AS max_s1,
                         AVG(sensor_2) AS avg_s2, MAX(sensor_2) AS max_s2,
                         AVG(sensor_3) AS avg_s3, MAX(sensor_3) AS max_s3,
                         AVG(rms_overall) AS avg_rms, MAX(rms_overall) AS max_rms,
                         COUNT(*) AS total_readings,
                         SUM(status='normal')   AS count_normal,
                         SUM(status='warning')  AS count_warning,
                         SUM(status='critical') AS count_critical
                     FROM vibration_readings
                     WHERE machine_id = ? AND recorded_at >= NOW() - INTERVAL 24 HOUR"
                );
                $stmt->execute([$machine_id]);
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                foreach ($stats as $k => $v) {
                    if (strpos($k, 'count') !== false || $k === 'total_readings') {
                        $stats[$k] = $v !== null ? (int)$v : 0;
                    } else {
                        $stats[$k] = $v !== null ? round((float)$v, 4) : null;
                    }
                }
                echo json_encode(['success' => true, 'data' => $stats]);
                break;

            case 'since':
                // Ambil vibration rows baru sejak timestamp tertentu
                $since = $_GET['since'] ?? null;
                if (!$since) {
                    echo json_encode(['success' => false, 'message' => 'since param required']);
                    break;
                }
                $stmt = $pdo->prepare(
                    "SELECT * FROM vibration_readings
                     WHERE machine_id = ? AND recorded_at > ?
                     ORDER BY recorded_at ASC
                     LIMIT 50"
                );
                $stmt->execute([$machine_id, $since]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $rows]);
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Unknown action']);
                break;
        }
        exit;
    }

    // ── POST ──────────────────────────────────────────────────────────────────

    if ($method === 'POST') {
        if ($action !== 'insert') {
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) $input = $_POST;

        $device_id  = $input['device_id']  ?? null;
        $machine_id = isset($input['machine_id']) ? (int)$input['machine_id'] : 0;
        $s1         = isset($input['sensor_1']) ? (float)$input['sensor_1'] : null;
        $s2         = isset($input['sensor_2']) ? (float)$input['sensor_2'] : null;
        $s3         = isset($input['sensor_3']) ? (float)$input['sensor_3'] : null;
        $source     = $input['source'] ?? 'esp';

        if ($s1 === null || $s2 === null || $s3 === null) {
            echo json_encode(['success' => false, 'message' => 'sensor_1, sensor_2, sensor_3 required']);
            exit;
        }

        // Resolve machine_id from device_id if needed
        if (!$machine_id && $device_id) {
            $stmt = $pdo->prepare("SELECT machine_id FROM esp32_devices WHERE device_id = ? LIMIT 1");
            $stmt->execute([$device_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) $machine_id = (int)$row['machine_id'];
        }

        if (!$machine_id) {
            echo json_encode(['success' => false, 'message' => 'Could not resolve machine_id']);
            exit;
        }

        $rms        = round(vibCalcRms($s1, $s2, $s3), 4);
        $statusInfo = vibGetStatus($rms, $pdo);
        $status     = $statusInfo['status'];

        $stmt = $pdo->prepare(
            "INSERT INTO vibration_readings (machine_id, sensor_1, sensor_2, sensor_3, rms_overall, status, source, recorded_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$machine_id, $s1, $s2, $s3, $rms, $status, $source]);
        $insert_id = (int)$pdo->lastInsertId();

        // Update device last_seen
        if ($device_id) {
            $stmt = $pdo->prepare("UPDATE esp32_devices SET last_seen = NOW(), status = 'online' WHERE device_id = ?");
            $stmt->execute([$device_id]);
        }

        // Create alert if warning or critical
        if ($status === 'warning' || $status === 'critical') {
            vibCreateAlert($pdo, $machine_id, 'rms_overall', $rms, $statusInfo['warn'], $statusInfo['crit'], $status);
        }

        echo json_encode([
            'success'     => true,
            'id'          => $insert_id,
            'machine_id'  => $machine_id,
            'rms_overall' => $rms,
            'status'      => $status,
        ]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
