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

$role   = $_SESSION['user_role'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

function maintenanceJson($data) {
    echo json_encode($data);
    exit;
}

function maintenanceInput() {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) return $decoded;
    return $_POST;
}

try {
    $pdo = getDB();

    if ($method === 'GET') {
        switch ($action) {

            case 'list':
                $where  = ['1=1'];
                $params = [];

                if (!empty($_GET['machine_id'])) {
                    $where[]  = 'mr.machine_id = :machine_id';
                    $params[':machine_id'] = (int)$_GET['machine_id'];
                }
                if (!empty($_GET['type'])) {
                    $where[]  = 'mr.type = :type';
                    $params[':type'] = $_GET['type'];
                }
                if (!empty($_GET['from'])) {
                    $where[]  = 'mr.maint_date >= :from_date';
                    $params[':from_date'] = $_GET['from'];
                }
                if (!empty($_GET['to'])) {
                    $where[]  = 'mr.maint_date <= :to_date';
                    $params[':to_date'] = $_GET['to'];
                }

                $sql = "SELECT mr.*, m.name AS machine_name
                        FROM maintenance_records mr
                        JOIN machines m ON mr.machine_id = m.id
                        WHERE " . implode(' AND ', $where) . "
                        ORDER BY mr.maint_date DESC, mr.created_at DESC";

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
                maintenanceJson(['success' => true, 'data' => $records]);
                break;

            case 'get':
                $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
                if (!$id) maintenanceJson(['success' => false, 'message' => 'Invalid ID']);

                $stmt = $pdo->prepare(
                    "SELECT mr.*, m.name AS machine_name
                     FROM maintenance_records mr
                     JOIN machines m ON mr.machine_id = m.id
                     WHERE mr.id = ?"
                );
                $stmt->execute([$id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) maintenanceJson(['success' => false, 'message' => 'Record not found']);
                maintenanceJson(['success' => true, 'data' => $row]);
                break;

            case 'upcoming':
                $days = isset($_GET['days']) ? max(1, (int)$_GET['days']) : 30;
                $stmt = $pdo->prepare(
                    "SELECT mr.*, m.name AS machine_name
                     FROM maintenance_records mr
                     JOIN machines m ON mr.machine_id = m.id
                     WHERE mr.maint_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days DAY)
                     ORDER BY mr.maint_date ASC"
                );
                $stmt->execute([':days' => $days]);
                $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
                maintenanceJson(['success' => true, 'data' => $records]);
                break;

            case 'stats':
                $typeStats = $pdo->query(
                    "SELECT type, COUNT(*) AS cnt, SUM(duration_min) AS total_duration,
                            AVG(duration_min) AS avg_duration, SUM(cost) AS total_cost
                     FROM maintenance_records
                     WHERE maint_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                       AND maint_date < DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01')
                     GROUP BY type"
                )->fetchAll(PDO::FETCH_ASSOC);

                $trend = $pdo->query(
                    "SELECT DATE_FORMAT(maint_date, '%Y-%m') AS month,
                            COUNT(*) AS total_count,
                            SUM(duration_min) AS total_duration,
                            SUM(cost) AS total_cost
                     FROM maintenance_records
                     WHERE maint_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                     GROUP BY DATE_FORMAT(maint_date, '%Y-%m')
                     ORDER BY month ASC"
                )->fetchAll(PDO::FETCH_ASSOC);

                $totals = $pdo->query(
                    "SELECT COUNT(*) AS total_records,
                            SUM(duration_min) AS total_downtime_min,
                            AVG(duration_min) AS avg_duration_min,
                            SUM(cost) AS total_cost
                     FROM maintenance_records"
                )->fetch(PDO::FETCH_ASSOC);

                maintenanceJson([
                    'success' => true,
                    'data' => ['by_type' => $typeStats, 'trend' => $trend, 'totals' => $totals]
                ]);
                break;

            case 'calendar':
                $month = $_GET['month'] ?? date('Y-m');
                if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
                    maintenanceJson(['success' => false, 'message' => 'Invalid month format. Use YYYY-MM']);
                }
                $from = $month . '-01';
                $to   = date('Y-m-t', strtotime($from));

                $stmt = $pdo->prepare(
                    "SELECT mr.id, mr.machine_id, mr.type, mr.description, mr.technician,
                            mr.maint_date, mr.duration_min, mr.cost,
                            m.name AS machine_name
                     FROM maintenance_records mr
                     JOIN machines m ON mr.machine_id = m.id
                     WHERE mr.maint_date BETWEEN :from_date AND :to_date
                     ORDER BY mr.maint_date ASC"
                );
                $stmt->execute([':from_date' => $from, ':to_date' => $to]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $typeColors = [
                    'preventive'  => '#1cc88a',
                    'corrective'  => '#f6c23e',
                    'breakdown'   => '#e74a3b',
                    'inspection'  => '#36b9cc',
                ];
                $events = [];
                foreach ($rows as $row) {
                    $events[] = [
                        'id'            => $row['id'],
                        'date'          => $row['maint_date'],
                        'machine_name'  => $row['machine_name'],
                        'type'          => $row['type'],
                        'description'   => $row['description'],
                        'title'         => '[' . ucfirst($row['type']) . '] ' . $row['machine_name'],
                        'color'         => $typeColors[$row['type']] ?? '#4e73df',
                        'extendedProps' => $row,
                    ];
                }
                maintenanceJson(['success' => true, 'data' => $events]);
                break;

            default:
                maintenanceJson(['success' => false, 'message' => 'Unknown action']);
        }

    } elseif ($method === 'POST') {
        if ($role === 'viewer') {
            http_response_code(403);
            maintenanceJson(['success' => false, 'message' => 'Forbidden: viewers cannot modify data']);
        }

        $input  = maintenanceInput();
        $action = $input['action'] ?? $_GET['action'] ?? '';

        switch ($action) {

            case 'create':
                $required = ['machine_id', 'type', 'description', 'technician', 'maint_date'];
                foreach ($required as $field) {
                    if (empty($input[$field])) {
                        maintenanceJson(['success' => false, 'message' => "Field '$field' is required"]);
                    }
                }

                $machine_id   = (int)$input['machine_id'];
                $type         = $input['type'];
                $description  = trim($input['description']);
                $technician   = trim($input['technician']);
                $maint_date   = $input['maint_date'];
                $duration_min = (isset($input['duration_min']) && $input['duration_min'] !== '') ? (int)$input['duration_min'] : null;
                $cost         = (isset($input['cost']) && $input['cost'] !== '') ? (float)$input['cost'] : null;

                $valid_types = ['preventive', 'corrective', 'breakdown', 'inspection'];
                if (!in_array($type, $valid_types)) {
                    maintenanceJson(['success' => false, 'message' => 'Invalid type']);
                }

                $chk = $pdo->prepare("SELECT id FROM machines WHERE id = ?");
                $chk->execute([$machine_id]);
                if (!$chk->fetch()) {
                    maintenanceJson(['success' => false, 'message' => 'Machine not found']);
                }

                $stmt = $pdo->prepare(
                    "INSERT INTO maintenance_records (machine_id, type, description, technician, maint_date, duration_min, cost, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
                );
                $stmt->execute([$machine_id, $type, $description, $technician, $maint_date, $duration_min, $cost]);
                maintenanceJson(['success' => true, 'message' => 'Maintenance record created', 'id' => (int)$pdo->lastInsertId()]);
                break;

            case 'update':
                $id = isset($input['id']) ? (int)$input['id'] : 0;
                if (!$id) maintenanceJson(['success' => false, 'message' => 'Invalid ID']);

                $chk = $pdo->prepare("SELECT id FROM maintenance_records WHERE id = ?");
                $chk->execute([$id]);
                if (!$chk->fetch()) maintenanceJson(['success' => false, 'message' => 'Record not found']);

                $fields = [];
                $params = [];
                $allowed = ['machine_id', 'type', 'description', 'technician', 'maint_date', 'duration_min', 'cost'];
                foreach ($allowed as $field) {
                    if (array_key_exists($field, $input)) {
                        if ($field === 'type') {
                            $valid_types = ['preventive', 'corrective', 'breakdown', 'inspection'];
                            if (!in_array($input[$field], $valid_types)) {
                                maintenanceJson(['success' => false, 'message' => 'Invalid type']);
                            }
                        }
                        $fields[] = "$field = ?";
                        if ($field === 'machine_id' || $field === 'duration_min') {
                            $params[] = (int)$input[$field];
                        } elseif ($field === 'cost') {
                            $params[] = (float)$input[$field];
                        } else {
                            $params[] = $input[$field];
                        }
                    }
                }

                if (empty($fields)) maintenanceJson(['success' => false, 'message' => 'No fields to update']);

                $params[] = $id;
                $stmt = $pdo->prepare("UPDATE maintenance_records SET " . implode(', ', $fields) . " WHERE id = ?");
                $stmt->execute($params);
                maintenanceJson(['success' => true, 'message' => 'Maintenance record updated']);
                break;

            case 'delete':
                if ($role !== 'admin') {
                    http_response_code(403);
                    maintenanceJson(['success' => false, 'message' => 'Forbidden: only admins can delete records']);
                }

                $id = isset($input['id']) ? (int)$input['id'] : 0;
                if (!$id) maintenanceJson(['success' => false, 'message' => 'Invalid ID']);

                $stmt = $pdo->prepare("DELETE FROM maintenance_records WHERE id = ?");
                $stmt->execute([$id]);
                if ($stmt->rowCount() > 0) {
                    maintenanceJson(['success' => true, 'message' => 'Maintenance record deleted']);
                } else {
                    maintenanceJson(['success' => false, 'message' => 'Record not found']);
                }
                break;

            default:
                maintenanceJson(['success' => false, 'message' => 'Unknown action']);
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
