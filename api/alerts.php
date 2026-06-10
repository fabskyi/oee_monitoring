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

$user_id  = (int)$_SESSION['user_id'];
$username = $_SESSION['username'] ?? '';
$role     = $_SESSION['user_role'] ?? '';
$method   = $_SERVER['REQUEST_METHOD'];
$action   = $_GET['action'] ?? '';

try {
    $pdo = getDB();

    if ($method === 'GET') {
        switch ($action) {

            case 'list':
                $where  = ['1=1'];
                $params = [];

                if (!empty($_GET['machine_id'])) {
                    $where[]  = 'a.machine_id = :machine_id';
                    $params[':machine_id'] = (int)$_GET['machine_id'];
                }
                if (!empty($_GET['severity']) && in_array($_GET['severity'], ['warning', 'critical'])) {
                    $where[]  = 'a.severity = :severity';
                    $params[':severity'] = $_GET['severity'];
                }
                if (isset($_GET['acknowledged']) && $_GET['acknowledged'] !== '') {
                    $where[]  = 'a.acknowledged = :acknowledged';
                    $params[':acknowledged'] = (int)$_GET['acknowledged'];
                }
                if (!empty($_GET['from'])) {
                    $where[]  = 'DATE(a.created_at) >= :from_date';
                    $params[':from_date'] = $_GET['from'];
                }
                if (!empty($_GET['to'])) {
                    $where[]  = 'DATE(a.created_at) <= :to_date';
                    $params[':to_date'] = $_GET['to'];
                }

                $whereStr = implode(' AND ', $where);
                $sql = "SELECT a.*, m.name AS machine_name,
                               u.full_name AS acknowledged_by_name
                        FROM alerts a
                        JOIN machines m ON a.machine_id = m.id
                        LEFT JOIN users u ON a.acknowledged_by = u.id
                        WHERE {$whereStr}
                        ORDER BY a.created_at DESC";

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $alerts]);
                break;

            case 'count_unread':
                $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM alerts WHERE acknowledged = 0");
                $row  = $stmt->fetch(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'count' => (int)$row['cnt']]);
                break;

            case 'get':
                if (empty($_GET['id'])) {
                    echo json_encode(['success' => false, 'message' => 'Missing id']);
                    exit;
                }
                $stmt = $pdo->prepare(
                    "SELECT a.*, m.name AS machine_name,
                            u.full_name AS acknowledged_by_name
                     FROM alerts a
                     JOIN machines m ON a.machine_id = m.id
                     LEFT JOIN users u ON a.acknowledged_by = u.id
                     WHERE a.id = ?"
                );
                $stmt->execute([(int)$_GET['id']]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    echo json_encode(['success' => false, 'message' => 'Alert not found']);
                    exit;
                }
                echo json_encode(['success' => true, 'data' => $row]);
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Unknown action']);
                break;
        }

    } elseif ($method === 'POST') {
        if ($role === 'viewer') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Forbidden']);
            exit;
        }

        switch ($action) {

            case 'acknowledge':
                if (empty($_GET['id'])) {
                    echo json_encode(['success' => false, 'message' => 'Missing id']);
                    exit;
                }
                $id   = (int)$_GET['id'];
                $stmt = $pdo->prepare(
                    "UPDATE alerts
                     SET acknowledged = 1,
                         acknowledged_by = :uid,
                         acknowledged_at = NOW()
                     WHERE id = :id AND acknowledged = 0"
                );
                $stmt->execute([':uid' => $user_id, ':id' => $id]);
                if ($stmt->rowCount() === 0) {
                    echo json_encode(['success' => false, 'message' => 'Alert not found or already acknowledged']);
                } else {
                    echo json_encode(['success' => true, 'message' => 'Alert acknowledged']);
                }
                break;

            case 'acknowledge_bulk':
                $input = json_decode(file_get_contents('php://input'), true);
                $ids   = $input['ids'] ?? [];

                if (empty($ids) || !is_array($ids)) {
                    echo json_encode(['success' => false, 'message' => 'No ids provided']);
                    exit;
                }

                $ids          = array_map('intval', $ids);
                $affected     = 0;
                $stmtBulk     = $pdo->prepare(
                    "UPDATE alerts
                     SET acknowledged = 1,
                         acknowledged_by = :uid,
                         acknowledged_at = NOW()
                     WHERE id = :id AND acknowledged = 0"
                );
                foreach ($ids as $alertId) {
                    $stmtBulk->execute([':uid' => $user_id, ':id' => $alertId]);
                    $affected += $stmtBulk->rowCount();
                }
                echo json_encode(['success' => true, 'message' => "{$affected} alert(s) acknowledged"]);
                break;

            case 'delete':
                if ($role !== 'admin') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Admin only']);
                    exit;
                }
                if (empty($_GET['id'])) {
                    echo json_encode(['success' => false, 'message' => 'Missing id']);
                    exit;
                }
                $stmt = $pdo->prepare("DELETE FROM alerts WHERE id = ?");
                $stmt->execute([(int)$_GET['id']]);
                if ($stmt->rowCount() === 0) {
                    echo json_encode(['success' => false, 'message' => 'Alert not found']);
                } else {
                    echo json_encode(['success' => true, 'message' => 'Alert deleted']);
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
