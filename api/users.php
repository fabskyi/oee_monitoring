<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// Admin-only endpoint
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Admin access required.', 'data' => null]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// Merge JSON body into accessible input
$jsonInput = [];
if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) $jsonInput = $decoded;
}

function usersPost($key, $default = '') {
    global $jsonInput;
    return $jsonInput[$key] ?? $_POST[$key] ?? $default;
}

$action = $_GET['action'] ?? usersPost('action', '');

function usersJson($success, $message = '', $data = null) {
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

try {
    $pdo = getDB();

    switch ($action) {

        // GET ?action=list
        case 'list':
            $stmt  = $pdo->query(
                "SELECT id, username, full_name, email, role, is_active, last_login, created_at
                 FROM users ORDER BY created_at DESC"
            );
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            usersJson(true, 'Success', $users);
            break;

        // GET ?action=get&id=X
        case 'get':
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) usersJson(false, 'Invalid ID');
            $stmt = $pdo->prepare(
                "SELECT id, username, full_name, email, role, is_active, last_login, created_at
                 FROM users WHERE id = ?"
            );
            $stmt->execute([$id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) usersJson(false, 'User not found');
            usersJson(true, 'Success', $user);
            break;

        // POST ?action=create
        case 'create':
            $username  = trim(usersPost('username'));
            $full_name = trim(usersPost('full_name'));
            $email     = trim(usersPost('email'));
            $password  = usersPost('password');
            $role      = usersPost('role', 'viewer');
            $is_active = (int)usersPost('is_active', 1);

            if (!$username || !$full_name || !$email || !$password) {
                usersJson(false, 'username, full_name, email, and password are required');
            }
            if (!in_array($role, ['admin', 'operator', 'viewer'])) {
                usersJson(false, 'Invalid role. Must be admin, operator, or viewer');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                usersJson(false, 'Invalid email format');
            }
            if (strlen($password) < 6) {
                usersJson(false, 'Password must be at least 6 characters');
            }

            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) usersJson(false, 'Username already exists');

            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) usersJson(false, 'Email already exists');

            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare(
                "INSERT INTO users (username, full_name, email, password_hash, role, is_active, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([$username, $full_name, $email, $hash, $role, $is_active]);
            usersJson(true, 'User created successfully', ['id' => (int)$pdo->lastInsertId()]);
            break;

        // POST ?action=update
        case 'update':
            $id        = (int)usersPost('id', 0);
            $username  = trim(usersPost('username'));
            $full_name = trim(usersPost('full_name'));
            $email     = trim(usersPost('email'));
            $role      = usersPost('role');
            $password  = usersPost('password');
            $is_active_raw = usersPost('is_active', null);
            $is_active = ($is_active_raw !== null && $is_active_raw !== '') ? (int)$is_active_raw : null;

            if (!$id) usersJson(false, 'Invalid ID');

            $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) usersJson(false, 'User not found');

            if ($username) {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                $stmt->execute([$username, $id]);
                if ($stmt->fetch()) usersJson(false, 'Username already exists');
            }
            if ($email) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) usersJson(false, 'Invalid email format');
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $id]);
                if ($stmt->fetch()) usersJson(false, 'Email already exists');
            }
            if ($role && !in_array($role, ['admin', 'operator', 'viewer'])) {
                usersJson(false, 'Invalid role. Must be admin, operator, or viewer');
            }
            if ($password && strlen($password) < 6) {
                usersJson(false, 'Password must be at least 6 characters');
            }

            $fields = [];
            $params = [];
            if ($username)           { $fields[] = 'username = ?';      $params[] = $username; }
            if ($full_name)          { $fields[] = 'full_name = ?';     $params[] = $full_name; }
            if ($email)              { $fields[] = 'email = ?';         $params[] = $email; }
            if ($role)               { $fields[] = 'role = ?';          $params[] = $role; }
            if ($is_active !== null) { $fields[] = 'is_active = ?';     $params[] = $is_active; }
            if ($password)           { $fields[] = 'password_hash = ?'; $params[] = password_hash($password, PASSWORD_BCRYPT); }

            if (empty($fields)) usersJson(false, 'No fields to update');

            $params[] = $id;
            $stmt = $pdo->prepare("UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?");
            $stmt->execute($params);
            usersJson(true, 'User updated successfully');
            break;

        // POST ?action=reset_password
        case 'reset_password':
            $id           = (int)usersPost('id', 0);
            $new_password = usersPost('new_password');

            if (!$id) usersJson(false, 'Invalid ID');
            if (!$new_password) usersJson(false, 'new_password is required');
            if (strlen($new_password) < 6) usersJson(false, 'Password must be at least 6 characters');

            $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) usersJson(false, 'User not found');

            $hash = password_hash($new_password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$hash, $id]);
            usersJson(true, 'Password reset successfully');
            break;

        // POST ?action=toggle_active
        case 'toggle_active':
            $id = (int)usersPost('id', 0);
            if (!$id) usersJson(false, 'Invalid ID');

            if ($id === (int)$_SESSION['user_id']) {
                usersJson(false, 'Cannot deactivate your own account');
            }

            $stmt = $pdo->prepare("SELECT id, is_active FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) usersJson(false, 'User not found');

            $newStatus = $user['is_active'] ? 0 : 1;
            $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
            $stmt->execute([$newStatus, $id]);

            $msg = $newStatus ? 'User activated' : 'User deactivated';
            usersJson(true, $msg, ['is_active' => $newStatus]);
            break;

        // POST ?action=delete
        case 'delete':
            $id = (int)usersPost('id', 0);
            if (!$id) usersJson(false, 'Invalid ID');

            if ($id === (int)$_SESSION['user_id']) {
                usersJson(false, 'Cannot delete your own account');
            }

            $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) usersJson(false, 'User not found');

            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            usersJson(true, 'User deleted successfully');
            break;

        default:
            usersJson(false, 'Invalid or missing action');
            break;
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage(), 'data' => null]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage(), 'data' => null]);
}
