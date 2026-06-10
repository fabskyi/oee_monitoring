<?php
// Load config (functions like hasRole, getDB)
if (!defined('DB_NAME')) {
    require_once __DIR__ . '/config.php';
}

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// optional role check
if (isset($requiredRole) && !hasRole($requiredRole)) {
    header('Location: dashboard.php?error=unauthorized');
    exit;
}
