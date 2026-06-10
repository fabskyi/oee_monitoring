<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

function settingsRequireAdmin() {
    if (($_SESSION['user_role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        exit;
    }
}

function settingsInput() {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

try {
    $pdo = getDB();

    if ($method === 'GET') {
        switch ($action) {

            case 'all':
                $stmt = $pdo->query(
                    "SELECT setting_key, setting_value FROM system_settings ORDER BY setting_key"
                );
                $rows   = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result = [];
                foreach ($rows as $row) {
                    $result[$row['setting_key']] = $row['setting_value'];
                }
                echo json_encode(['success' => true, 'data' => $result]);
                break;

            case 'get':
                $key = $_GET['key'] ?? '';
                if ($key === '') {
                    echo json_encode(['success' => false, 'message' => 'key is required']);
                    break;
                }
                $stmt = $pdo->prepare(
                    "SELECT setting_value, updated_at FROM system_settings WHERE setting_key = ?"
                );
                $stmt->execute([$key]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    echo json_encode(['success' => true, 'data' => $row]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Setting not found']);
                }
                break;

            case 'thresholds':
                $machine_id = isset($_GET['machine_id']) ? (int)$_GET['machine_id'] : 0;
                if ($machine_id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'machine_id is required']);
                    break;
                }
                $stmt = $pdo->prepare(
                    "SELECT id, sensor_key, thresh_lo, thresh_hi
                     FROM sensor_thresholds WHERE machine_id = ? ORDER BY sensor_key"
                );
                $stmt->execute([$machine_id]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $rows]);
                break;

            case 'oee_settings':
                $stmt = $pdo->query(
                    "SELECT m.id AS machine_id, m.name AS machine_name,
                            COALESCE(o.availability, 90) AS availability,
                            COALESCE(o.performance, 95)  AS performance,
                            COALESCE(o.quality, 99)       AS quality
                     FROM machines m
                     LEFT JOIN oee_settings o ON o.machine_id = m.id
                     ORDER BY m.name"
                );
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $rows]);
                break;

            case 'db_stats':
                $tables = [
                    'users', 'production_lines', 'machines', 'oee_settings',
                    'sensor_readings', 'alerts', 'sensor_thresholds',
                    'maintenance_records', 'oee_daily', 'vibration_readings',
                    'system_settings', 'esp32_devices',
                ];
                $stats = [];
                foreach ($tables as $tbl) {
                    $s = $pdo->query("SELECT COUNT(*) AS cnt FROM `{$tbl}`");
                    $stats[$tbl] = (int)$s->fetchColumn();
                }
                $sizeStmt = $pdo->query(
                    "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                     FROM information_schema.tables WHERE table_schema = DATABASE()"
                );
                $size_mb = (float)$sizeStmt->fetchColumn();
                echo json_encode(['success' => true, 'data' => ['tables' => $stats, 'db_size_mb' => $size_mb]]);
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Unknown action']);
                break;
        }

    } elseif ($method === 'POST') {
        settingsRequireAdmin();
        $input = settingsInput();

        switch ($action) {

            case 'save':
                $settings = $input['settings'] ?? [];
                if (empty($settings) || !is_array($settings)) {
                    echo json_encode(['success' => false, 'message' => 'settings object is required']);
                    break;
                }
                $stmt = $pdo->prepare(
                    "INSERT INTO system_settings (setting_key, setting_value, updated_at)
                     VALUES (?, ?, NOW())
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()"
                );
                $pdo->beginTransaction();
                try {
                    foreach ($settings as $key => $value) {
                        $stmt->execute([$key, $value]);
                    }
                    $pdo->commit();
                    echo json_encode(['success' => true, 'message' => count($settings) . ' setting(s) saved']);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
                break;

            case 'update_threshold':
                $machine_id = isset($input['machine_id']) ? (int)$input['machine_id'] : 0;
                $sensor_key = trim($input['sensor_key'] ?? '');
                $thresh_lo  = isset($input['thresh_lo'])  ? (float)$input['thresh_lo']  : null;
                $thresh_hi  = isset($input['thresh_hi'])  ? (float)$input['thresh_hi']  : null;

                if ($machine_id <= 0 || $sensor_key === '') {
                    echo json_encode(['success' => false, 'message' => 'machine_id and sensor_key are required']);
                    break;
                }
                $stmt = $pdo->prepare(
                    "INSERT INTO sensor_thresholds (machine_id, sensor_key, thresh_lo, thresh_hi)
                     VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE thresh_lo = VALUES(thresh_lo), thresh_hi = VALUES(thresh_hi)"
                );
                $stmt->execute([$machine_id, $sensor_key, $thresh_lo, $thresh_hi]);
                echo json_encode(['success' => true, 'message' => 'Threshold updated']);
                break;

            case 'update_oee_setting':
                $machine_id   = isset($input['machine_id'])   ? (int)$input['machine_id']   : 0;
                $availability = isset($input['availability']) ? (float)$input['availability'] : null;
                $performance  = isset($input['performance'])  ? (float)$input['performance']  : null;
                $quality      = isset($input['quality'])      ? (float)$input['quality']      : null;

                if ($machine_id <= 0 || $availability === null || $performance === null || $quality === null) {
                    echo json_encode(['success' => false, 'message' => 'machine_id, availability, performance, quality are required']);
                    break;
                }
                $stmt = $pdo->prepare(
                    "INSERT INTO oee_settings (machine_id, availability, performance, quality)
                     VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE availability = VALUES(availability),
                                             performance  = VALUES(performance),
                                             quality      = VALUES(quality)"
                );
                $stmt->execute([$machine_id, $availability, $performance, $quality]);
                echo json_encode(['success' => true, 'message' => 'OEE setting updated']);
                break;

            case 'purge_data':
                $stmt      = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'retention_days'");
                $retention = (int)($stmt->fetchColumn() ?: 90);
                if ($retention < 1) {
                    echo json_encode(['success' => false, 'message' => 'Invalid retention_days value']);
                    break;
                }
                $cutoff = date('Y-m-d H:i:s', strtotime("-{$retention} days"));
                $pdo->beginTransaction();
                try {
                    $s1 = $pdo->prepare("DELETE FROM sensor_readings WHERE recorded_at < ?");
                    $s1->execute([$cutoff]);
                    $sensor_deleted = $s1->rowCount();

                    $s2 = $pdo->prepare("DELETE FROM vibration_readings WHERE recorded_at < ?");
                    $s2->execute([$cutoff]);
                    $vib_deleted = $s2->rowCount();

                    $pdo->commit();
                    echo json_encode([
                        'success' => true,
                        'message' => "Purged data older than {$retention} days",
                        'data'    => [
                            'sensor_readings_deleted'    => $sensor_deleted,
                            'vibration_readings_deleted' => $vib_deleted,
                            'cutoff_date'                => $cutoff,
                        ],
                    ]);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
                break;

            case 'reset_thresholds':
                $machine_id = isset($input['machine_id']) ? (int)$input['machine_id'] : 0;
                if ($machine_id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'machine_id is required']);
                    break;
                }
                $defaults = [
                    'v_r'        => [200, 240],
                    'v_s'        => [200, 240],
                    'v_t'        => [200, 240],
                    'a_r'        => [0,   20],
                    'a_s'        => [0,   20],
                    'a_t'        => [0,   20],
                    'f_r'        => [49,  51],
                    'f_s'        => [49,  51],
                    'f_t'        => [49,  51],
                    'e_r'        => [0,   10000],
                    'e_s'        => [0,   10000],
                    'e_t'        => [0,   10000],
                    'temp_panel' => [0,   60],
                    'hum_panel'  => [0,   80],
                ];
                $pdo->beginTransaction();
                try {
                    $del = $pdo->prepare("DELETE FROM sensor_thresholds WHERE machine_id = ?");
                    $del->execute([$machine_id]);
                    $ins = $pdo->prepare(
                        "INSERT INTO sensor_thresholds (machine_id, sensor_key, thresh_lo, thresh_hi) VALUES (?, ?, ?, ?)"
                    );
                    foreach ($defaults as $key => [$lo, $hi]) {
                        $ins->execute([$machine_id, $key, $lo, $hi]);
                    }
                    $pdo->commit();
                    echo json_encode(['success' => true, 'message' => 'Thresholds reset to defaults', 'data' => $defaults]);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Unknown action']);
                break;
        }

    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
