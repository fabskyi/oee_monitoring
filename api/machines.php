<?php
require_once __DIR__ . '/../includes/config.php';

// CORS headers for ESP32 and other clients
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Session check — return 401 JSON (do NOT redirect; this is an API)
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$role    = $_SESSION['user_role'] ?? '';
$user_id = $_SESSION['user_id'];

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Support JSON body for POST requests
$body = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST)) {
    $raw = file_get_contents('php://input');
    if (!empty($raw)) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $body = $decoded;
        }
    }
}

// Helper: get POST or JSON body value
function postVal(string $key, $default = null) {
    global $body;
    if (isset($_POST[$key])) {
        return $_POST[$key];
    }
    if (isset($body[$key])) {
        return $body[$key];
    }
    return $default;
}

try {
    $pdo = getDB();

    switch ($action) {

        // ─── GET: list all machines ───────────────────────────────────────────
        case 'list':
            $sql = "
                SELECT
                    m.id,
                    m.line_id,
                    pl.name        AS line_name,
                    m.name,
                    m.model,
                    m.status,
                    m.image_base64,
                    m.sort_order,
                    m.created_at,
                    sr.v_r, sr.v_s, sr.v_t,
                    sr.a_r, sr.a_s, sr.a_t,
                    sr.f_r, sr.f_s, sr.f_t,
                    sr.e_r, sr.e_s, sr.e_t,
                    sr.temp_panel, sr.hum_panel,
                    sr.source      AS sensor_source,
                    sr.recorded_at AS sensor_recorded_at,
                    os.availability AS target_availability,
                    os.performance  AS target_performance,
                    os.quality      AS target_quality
                FROM machines m
                LEFT JOIN production_lines pl ON pl.id = m.line_id
                LEFT JOIN sensor_readings sr ON sr.id = (
                    SELECT id FROM sensor_readings
                    WHERE machine_id = m.id
                    ORDER BY recorded_at DESC
                    LIMIT 1
                )
                LEFT JOIN oee_settings os ON os.machine_id = m.id
                ORDER BY m.sort_order ASC, m.id ASC
            ";
            $stmt     = $pdo->query($sql);
            $machines = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $machines]);
            break;

        // ─── GET: single machine full detail ─────────────────────────────────
        case 'get':
            $id = intval($_GET['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid machine ID']);
                exit;
            }

            $stmt = $pdo->prepare("
                SELECT
                    m.*,
                    pl.name AS line_name,
                    os.availability AS target_availability,
                    os.performance  AS target_performance,
                    os.quality      AS target_quality
                FROM machines m
                LEFT JOIN production_lines pl ON pl.id = m.line_id
                LEFT JOIN oee_settings os ON os.machine_id = m.id
                WHERE m.id = ?
            ");
            $stmt->execute([$id]);
            $machine = $stmt->fetch();

            if (!$machine) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Machine not found']);
                exit;
            }

            // Latest sensor reading
            $stmt2 = $pdo->prepare("
                SELECT * FROM sensor_readings
                WHERE machine_id = ?
                ORDER BY recorded_at DESC
                LIMIT 1
            ");
            $stmt2->execute([$id]);
            $machine['latest_sensor'] = $stmt2->fetch() ?: null;

            // Latest OEE daily
            $stmt3 = $pdo->prepare("
                SELECT * FROM oee_daily
                WHERE machine_id = ?
                ORDER BY snap_date DESC
                LIMIT 1
            ");
            $stmt3->execute([$id]);
            $machine['latest_oee'] = $stmt3->fetch() ?: null;

            echo json_encode(['success' => true, 'data' => $machine]);
            break;

        // ─── GET: all production lines ────────────────────────────────────────
        case 'lines':
            $stmt  = $pdo->query("SELECT id, name, description FROM production_lines ORDER BY name ASC");
            $lines = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $lines]);
            break;

        // ─── POST: create machine ─────────────────────────────────────────────
        case 'create':
            if ($role === 'viewer') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
                exit;
            }

            $name       = trim(postVal('name', ''));
            $line_id    = intval(postVal('line_id', 0));
            $model      = trim(postVal('model', ''));
            $status     = postVal('status', 'stop');
            $sort_order = intval(postVal('sort_order', 0));
            $image_b64  = postVal('image_base64', null);

            if ($name === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Machine name is required']);
                exit;
            }
            if ($line_id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Production line is required']);
                exit;
            }
            if (!in_array($status, ['run', 'stop'], true)) {
                $status = 'stop';
            }

            $stmt = $pdo->prepare("
                INSERT INTO machines (line_id, name, model, status, image_base64, sort_order, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$line_id, $name, $model, $status, $image_b64, $sort_order]);
            $new_id = (int) $pdo->lastInsertId();

            echo json_encode([
                'success' => true,
                'message' => 'Machine created successfully',
                'data'    => ['id' => $new_id]
            ]);
            break;

        // ─── POST: update machine ─────────────────────────────────────────────
        case 'update':
            if ($role === 'viewer') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
                exit;
            }

            $id         = intval(postVal('id', 0));
            $name       = trim(postVal('name', ''));
            $line_id    = intval(postVal('line_id', 0));
            $model      = trim(postVal('model', ''));
            $status     = postVal('status', 'stop');
            $sort_order = intval(postVal('sort_order', 0));
            $image_b64  = postVal('image_base64', null);

            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid machine ID']);
                exit;
            }
            if ($name === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Machine name is required']);
                exit;
            }
            if ($line_id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Production line is required']);
                exit;
            }
            if (!in_array($status, ['run', 'stop'], true)) {
                $status = 'stop';
            }

            // Only update image_base64 when a new value is supplied
            if ($image_b64 !== null && $image_b64 !== '') {
                $stmt = $pdo->prepare("
                    UPDATE machines
                    SET line_id=?, name=?, model=?, status=?, image_base64=?, sort_order=?
                    WHERE id=?
                ");
                $stmt->execute([$line_id, $name, $model, $status, $image_b64, $sort_order, $id]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE machines
                    SET line_id=?, name=?, model=?, status=?, sort_order=?
                    WHERE id=?
                ");
                $stmt->execute([$line_id, $name, $model, $status, $sort_order, $id]);
            }

            if ($stmt->rowCount() === 0) {
                $chk = $pdo->prepare("SELECT id FROM machines WHERE id=?");
                $chk->execute([$id]);
                if (!$chk->fetch()) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Machine not found']);
                    exit;
                }
            }

            echo json_encode(['success' => true, 'message' => 'Machine updated successfully']);
            break;

        // ─── POST: delete machine (admin only) ────────────────────────────────
        case 'delete':
            if ($role !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                exit;
            }

            $id = intval(postVal('id', 0));
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid machine ID']);
                exit;
            }

            $chk = $pdo->prepare("SELECT id FROM machines WHERE id=?");
            $chk->execute([$id]);
            if (!$chk->fetch()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Machine not found']);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM machines WHERE id=?");
            $stmt->execute([$id]);

            echo json_encode(['success' => true, 'message' => 'Machine deleted successfully']);
            break;

        // ─── POST: toggle run/stop status ─────────────────────────────────────
        case 'update_status':
            if ($role === 'viewer') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden']);
                exit;
            }

            $id     = intval(postVal('id', 0));
            $status = postVal('status', '');

            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid machine ID']);
                exit;
            }

            // If status not supplied or invalid, toggle current value
            if (!in_array($status, ['run', 'stop'], true)) {
                $chk = $pdo->prepare("SELECT status FROM machines WHERE id=?");
                $chk->execute([$id]);
                $row = $chk->fetch();
                if (!$row) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Machine not found']);
                    exit;
                }
                $status = ($row['status'] === 'run') ? 'stop' : 'run';
            }

            $stmt = $pdo->prepare("UPDATE machines SET status=? WHERE id=?");
            $stmt->execute([$status, $id]);

            echo json_encode([
                'success' => true,
                'message' => 'Status updated',
                'data'    => ['status' => $status]
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid or missing action']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
