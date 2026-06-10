<?php

// Application constants
define('APP_NAME', 'OEE Monitoring System');
define('VERSION', '2.0');
define('BASE_URL', 'http://localhost/oee');

// Database constants
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'oee_monitoring');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Returns a PDO database connection instance.
 */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            respond(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()], 500);
            exit;
        }
    }
    return $pdo;
}

/**
 * Sends a JSON API response and exits.
 *
 * @param mixed $data  The response payload.
 * @param int   $code  HTTP status code (default 200).
 */
function respond($data, int $code = 200): void {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($code);
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Parses and returns the JSON body of a POST request.
 *
 * @return array Decoded JSON body or empty array on failure.
 */
function bodyJson(): array {
    $raw = file_get_contents('php://input');
    if (empty($raw)) {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Returns the current authenticated user from session, verified against DB.
 *
 * @param PDO $pdo
 * @return array|null User row or null if not authenticated.
 */
function getCurrentUser(PDO $pdo): ?array {
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    return $user ?: null;
}

/**
 * Checks whether the currently logged-in user has at least the required role.
 * Role hierarchy: admin > operator > viewer
 *
 * @param string $requiredRole  'admin', 'operator', or 'viewer'.
 * @return bool
 */
function hasRole(string $requiredRole): bool {
    $hierarchy = [
        'viewer'   => 1,
        'operator' => 2,
        'admin'    => 3,
    ];

    $currentRole = $_SESSION['user_role'] ?? '';
    $currentLevel  = $hierarchy[$currentRole]  ?? 0;
    $requiredLevel = $hierarchy[$requiredRole] ?? 999;

    return $currentLevel >= $requiredLevel;
}
