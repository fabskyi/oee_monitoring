<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$action = $_REQUEST['action'] ?? '';

function updateOfflineDevices($pdo) {
    $sql = "UPDATE esp32_devices SET status = 'offline' WHERE last_seen < DATE_SUB(NOW(), INTERVAL 60 SECOND) AND status = 'online'";
    $pdo->exec($sql);
}

function checkThresholds($pdo, $machine_id, $data) {
    $sql = "SELECT * FROM sensor_thresholds WHERE machine_id = :machine_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':machine_id' => $machine_id]);
    $thresholds = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($thresholds as $threshold) {
        $key = $threshold['sensor_key'];
        if (!isset($data[$key])) continue;

        $value = floatval($data[$key]);
        $lo = $threshold['thresh_lo'] !== null ? floatval($threshold['thresh_lo']) : null;
        $hi = $threshold['thresh_hi'] !== null ? floatval($threshold['thresh_hi']) : null;

        $triggered = false;
        if ($lo !== null && $value < $lo) $triggered = true;
        if ($hi !== null && $value > $hi) $triggered = true;

        if (!$triggered) continue;

        $severity = 'warning';
        if ($lo !== null && $hi !== null) {
            $range = $hi - $lo;
            if ($range > 0) {
                $deviation = max(
                    ($lo !== null && $value < $lo) ? ($lo - $value) / $range : 0,
                    ($hi !== null && $value > $hi) ? ($value - $hi) / $range : 0
                );
                if ($deviation > 0.2) $severity = 'critical';
            }
        }

        $checkSql = "SELECT id FROM alerts
                     WHERE machine_id = :machine_id
                       AND sensor_key = :sensor_key
                       AND acknowledged = 0
                       AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([':machine_id' => $machine_id, ':sensor_key' => $key]);
        if ($checkStmt->fetch()) continue;

        $insertSql = "INSERT INTO alerts (machine_id, sensor_key, sensor_value, threshold_lo, threshold_hi, severity, acknowledged, created_at)
                      VALUES (:machine_id, :sensor_key, :sensor_value, :threshold_lo, :threshold_hi, :severity, 0, NOW())";
        $insertStmt = $pdo->prepare($insertSql);
        $insertStmt->execute([
            ':machine_id'   => $machine_id,
            ':sensor_key'   => $key,
            ':sensor_value' => $value,
            ':threshold_lo' => $lo,
            ':threshold_hi' => $hi,
            ':severity'     => $severity,
        ]);
    }
}

try {
    $pdo = getDB();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        updateOfflineDevices($pdo);

        if ($action === 'latest') {
            $machine_id = intval($_GET['machine_id'] ?? 0);
            if (!$machine_id) {
                echo json_encode(['success' => false, 'data' => null, 'message' => 'machine_id required']);
                exit;
            }

            $sql = "SELECT sr.*, m.name AS machine_name
                    FROM sensor_readings sr
                    JOIN machines m ON m.id = sr.machine_id
                    WHERE sr.machine_id = :machine_id
                    ORDER BY sr.recorded_at DESC
                    LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':machine_id' => $machine_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'data' => $row ?: null, 'message' => '']);

        } elseif ($action === 'history') {
            $machine_id = intval($_GET['machine_id'] ?? 0);
            $hours = intval($_GET['hours'] ?? 24);
            if (!$machine_id) {
                echo json_encode(['success' => false, 'data' => null, 'message' => 'machine_id required']);
                exit;
            }
            if ($hours < 1) $hours = 1;
            if ($hours > 720) $hours = 720;

            $sql = "SELECT * FROM sensor_readings
                    WHERE machine_id = :machine_id
                      AND recorded_at >= DATE_SUB(NOW(), INTERVAL :hours HOUR)
                    ORDER BY recorded_at ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':machine_id', $machine_id, PDO::PARAM_INT);
            $stmt->bindValue(':hours', $hours, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'data' => $rows, 'message' => '']);

        } elseif ($action === 'all_latest') {
            $sql = "SELECT sr.*, m.name AS machine_name, m.status AS machine_status
                    FROM sensor_readings sr
                    JOIN machines m ON m.id = sr.machine_id
                    JOIN (
                        SELECT machine_id, MAX(recorded_at) AS max_recorded_at
                        FROM sensor_readings
                        GROUP BY machine_id
                    ) latest ON sr.machine_id = latest.machine_id AND sr.recorded_at = latest.max_recorded_at
                    ORDER BY m.sort_order ASC, m.id ASC";
            $stmt = $pdo->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'data' => $rows, 'message' => '']);

        } else {
            echo json_encode(['success' => false, 'data' => null, 'message' => 'Unknown action']);
        }

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {

        if ($action !== 'insert') {
            echo json_encode(['success' => false, 'data' => null, 'message' => 'Unknown action']);
            exit;
        }

        $body = $_POST;
        if (empty($body)) {
            $raw = file_get_contents('php://input');
            if ($raw) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) $body = $decoded;
            }
        }

        $machine_id = null;

        if (!empty($body['device_id'])) {
            $device_id = trim($body['device_id']);
            $devStmt = $pdo->prepare("SELECT machine_id FROM esp32_devices WHERE device_id = :device_id LIMIT 1");
            $devStmt->execute([':device_id' => $device_id]);
            $devRow = $devStmt->fetch(PDO::FETCH_ASSOC);
            if ($devRow) {
                $machine_id = intval($devRow['machine_id']);
            }

            $updStmt = $pdo->prepare("UPDATE esp32_devices SET last_seen = NOW(), status = 'online' WHERE device_id = :device_id");
            $updStmt->execute([':device_id' => $device_id]);
        }

        if (!$machine_id && !empty($body['machine_id'])) {
            $machine_id = intval($body['machine_id']);
        }

        if (!$machine_id) {
            echo json_encode(['success' => false, 'data' => null, 'message' => 'machine_id or valid device_id required']);
            exit;
        }

        $fields = ['v_r','v_s','v_t','a_r','a_s','a_t','f_r','f_s','f_t','e_r','e_s','e_t','temp_panel','hum_panel'];
        $values = [':machine_id' => $machine_id];
        $setCols = [];

        foreach ($fields as $f) {
            $val = (isset($body[$f]) && $body[$f] !== '') ? floatval($body[$f]) : null;
            $values[":$f"] = $val;
            $setCols[] = $f;
        }

        $source = isset($body['source']) ? trim($body['source']) : 'manual';
        $allowed_sources = ['esp', 'manual', 'simulator'];
        if (!in_array($source, $allowed_sources)) $source = 'manual';
        $values[':source'] = $source;

        $colList   = implode(', ', $setCols);
        $paramList = implode(', ', array_map(function($f) { return ":$f"; }, $setCols));

        $sql = "INSERT INTO sensor_readings (machine_id, $colList, source, recorded_at)
                VALUES (:machine_id, $paramList, :source, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        $newId = $pdo->lastInsertId();

        checkThresholds($pdo, $machine_id, $body);

        echo json_encode(['success' => true, 'data' => ['id' => $newId], 'message' => 'Sensor reading inserted']);

    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'data' => null, 'message' => 'Method not allowed']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'data' => null, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'data' => null, 'message' => 'Error: ' . $e->getMessage()]);
}
