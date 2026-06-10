<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// Session check — heartbeat and register are allowed without session (ESP32 device calls)
$method         = $_SERVER['REQUEST_METHOD'];
$publicActions  = ['register', 'heartbeat'];

$action = '';
$input  = [];
if ($method === 'GET') {
    $action = $_GET['action'] ?? '';
} elseif ($method === 'POST') {
    $raw   = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) $input = $_POST;
    $action = $_GET['action'] ?? ($input['action'] ?? '');
}

if (!in_array($action, $publicActions) && !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$role = $_SESSION['user_role'] ?? '';

function esp32Json($success, $data = null, $message = '', $code = 200) {
    http_response_code($code);
    $response = ['success' => $success, 'message' => $message];
    if ($data !== null) {
        $response = array_merge($response, is_array($data) ? $data : ['data' => $data]);
    }
    echo json_encode($response);
    exit;
}

function esp32RequireAdmin() {
    global $role;
    if ($role !== 'admin') {
        esp32Json(false, null, 'Admin access required', 403);
    }
}

function esp32MarkOffline(PDO $pdo) {
    $pdo->exec("UPDATE esp32_devices SET status = 'offline' WHERE last_seen < DATE_SUB(NOW(), INTERVAL 60 SECOND)");
}

try {
    $pdo = getDB();

    switch ($action) {

        case 'list':
            esp32MarkOffline($pdo);
            $stmt = $pdo->query(
                "SELECT e.id, e.device_id, e.machine_id, m.name AS machine_name,
                        e.ip_address, e.mac_address, e.firmware_version,
                        e.last_seen, e.status, e.created_at
                 FROM esp32_devices e
                 LEFT JOIN machines m ON e.machine_id = m.id
                 ORDER BY e.status ASC, e.device_id ASC"
            );
            $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            esp32Json(true, ['devices' => $devices, 'count' => count($devices)]);
            break;

        case 'get':
            $device_id = $_GET['device_id'] ?? '';
            if (empty($device_id)) esp32Json(false, null, 'device_id required', 400);

            $stmt = $pdo->prepare(
                "SELECT e.id, e.device_id, e.machine_id, m.name AS machine_name,
                        e.ip_address, e.mac_address, e.firmware_version,
                        e.last_seen, e.status, e.created_at
                 FROM esp32_devices e
                 LEFT JOIN machines m ON e.machine_id = m.id
                 WHERE e.device_id = ?"
            );
            $stmt->execute([$device_id]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$device) esp32Json(false, null, 'Device not found', 404);
            esp32Json(true, ['device' => $device]);
            break;

        case 'status':
            esp32MarkOffline($pdo);
            $stmt = $pdo->query(
                "SELECT
                     COUNT(*) AS total,
                     SUM(CASE WHEN status = 'online'  THEN 1 ELSE 0 END) AS online_count,
                     SUM(CASE WHEN status = 'offline' THEN 1 ELSE 0 END) AS offline_count,
                     MAX(last_seen) AS last_data
                 FROM esp32_devices"
            );
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            esp32Json(true, ['status' => $stats]);
            break;

        case 'register':
            $device_id        = $input['device_id']        ?? '';
            $machine_id       = $input['machine_id']        ?? null;
            $ip_address       = $input['ip_address']        ?? '';
            $mac_address      = $input['mac_address']       ?? '';
            $firmware_version = $input['firmware_version']  ?? '';

            if (empty($device_id)) esp32Json(false, null, 'device_id required', 400);

            $stmt = $pdo->prepare("SELECT id FROM esp32_devices WHERE device_id = ?");
            $stmt->execute([$device_id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $stmt = $pdo->prepare(
                    "UPDATE esp32_devices
                     SET machine_id = ?, ip_address = ?, mac_address = ?,
                         firmware_version = ?, last_seen = NOW(), status = 'online'
                     WHERE device_id = ?"
                );
                $stmt->execute([$machine_id ?: null, $ip_address, $mac_address, $firmware_version, $device_id]);
                esp32Json(true, null, 'Device updated');
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO esp32_devices (device_id, machine_id, ip_address, mac_address, firmware_version, last_seen, status, created_at)
                     VALUES (?, ?, ?, ?, ?, NOW(), 'online', NOW())"
                );
                $stmt->execute([$device_id, $machine_id ?: null, $ip_address, $mac_address, $firmware_version]);
                esp32Json(true, ['id' => (int)$pdo->lastInsertId()], 'Device registered');
            }
            break;

        case 'heartbeat':
            // Support both GET (?device_id=...) and POST (JSON body)
            $device_id = $_GET['device_id'] ?? ($input['device_id'] ?? '');
            if (empty($device_id)) esp32Json(false, null, 'device_id required', 400);

            $stmt = $pdo->prepare("SELECT id FROM esp32_devices WHERE device_id = ?");
            $stmt->execute([$device_id]);
            if (!$stmt->fetch()) {
                // Auto-register unknown device on heartbeat instead of 404
                $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                $stmt2 = $pdo->prepare(
                    "INSERT INTO esp32_devices (device_id, ip_address, last_seen, status, created_at)
                     VALUES (?, ?, NOW(), 'online', NOW())"
                );
                $stmt2->execute([$device_id, $ip]);
            } else {
                $stmt = $pdo->prepare("UPDATE esp32_devices SET last_seen = NOW(), status = 'online' WHERE device_id = ?");
                $stmt->execute([$device_id]);
            }
            esp32Json(true, ['last_seen' => date('Y-m-d H:i:s')], 'Heartbeat received');
            break;

        case 'readings':
            // Return latest sensor readings (from sensor_readings table)
            $limit = max(1, min(200, (int)($_GET['limit'] ?? 50)));
            $machine_id = isset($_GET['machine_id']) ? (int)$_GET['machine_id'] : null;

            if ($machine_id) {
                $stmt = $pdo->prepare(
                    "SELECT sr.*, m.name AS machine_name
                     FROM sensor_readings sr
                     LEFT JOIN machines m ON m.id = sr.machine_id
                     WHERE sr.machine_id = ?
                     ORDER BY sr.recorded_at DESC LIMIT ?"
                );
                $stmt->execute([$machine_id, $limit]);
            } else {
                $stmt = $pdo->prepare(
                    "SELECT sr.*, m.name AS machine_name
                     FROM sensor_readings sr
                     LEFT JOIN machines m ON m.id = sr.machine_id
                     ORDER BY sr.recorded_at DESC LIMIT ?"
                );
                $stmt->execute([$limit]);
            }
            $readings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            esp32Json(true, ['readings' => $readings, 'count' => count($readings)]);
            break;

        case 'update':
            esp32RequireAdmin();
            $device_id        = $input['device_id']        ?? '';
            $machine_id       = $input['machine_id']       ?? null;
            $ip_address       = $input['ip_address']       ?? null;
            $firmware_version = $input['firmware_version'] ?? null;

            if (empty($device_id)) esp32Json(false, null, 'device_id required', 400);

            $stmt = $pdo->prepare("SELECT id FROM esp32_devices WHERE device_id = ?");
            $stmt->execute([$device_id]);
            if (!$stmt->fetch()) esp32Json(false, null, 'Device not found', 404);

            $fields = [];
            $params = [];
            if ($machine_id !== null)       { $fields[] = 'machine_id = ?';       $params[] = $machine_id; }
            if ($ip_address !== null)       { $fields[] = 'ip_address = ?';       $params[] = $ip_address; }
            if ($firmware_version !== null) { $fields[] = 'firmware_version = ?'; $params[] = $firmware_version; }

            if (empty($fields)) esp32Json(false, null, 'No fields to update', 400);

            $params[] = $device_id;
            $stmt = $pdo->prepare("UPDATE esp32_devices SET " . implode(', ', $fields) . " WHERE device_id = ?");
            $stmt->execute($params);
            esp32Json(true, null, 'Device updated');
            break;

        case 'delete':
            esp32RequireAdmin();
            $device_id = $input['device_id'] ?? '';
            if (empty($device_id)) esp32Json(false, null, 'device_id required', 400);

            $stmt = $pdo->prepare("SELECT id FROM esp32_devices WHERE device_id = ?");
            $stmt->execute([$device_id]);
            if (!$stmt->fetch()) esp32Json(false, null, 'Device not found', 404);

            $stmt = $pdo->prepare("DELETE FROM esp32_devices WHERE device_id = ?");
            $stmt->execute([$device_id]);
            esp32Json(true, null, 'Device deleted');
            break;

        default:
            esp32Json(false, null, 'Invalid action', 400);
            break;
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
