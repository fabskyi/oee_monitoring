<?php
/**
 * Serves user avatar as an actual image (cached).
 * Usage: <img src="api/avatar.php?id=5">
 */
require_once '../includes/config.php';

$id = (int)($_GET['id'] ?? 0);

// Default SVG fallback
function defaultAvatar(): void {
    header('Content-Type: image/svg+xml');
    header('Cache-Control: public, max-age=300');
    readfile(__DIR__ . '/../img/undraw_profile.svg');
    exit;
}

if (!$id) defaultAvatar();

$pdo  = getDB();
$stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$row = $stmt->fetch();

if (!$row || empty($row['avatar'])) defaultAvatar();

$avatar = $row['avatar'];

// Parse data URI: data:<mime>;base64,<data>
if (preg_match('/^data:([a-zA-Z0-9\-\/+]+);base64,(.+)$/', $avatar, $m)) {
    $mime = $m[1];
    $data = base64_decode($m[2]);
    if ($data === false) defaultAvatar();
    header('Content-Type: ' . $mime);
    header('Cache-Control: public, max-age=3600');
    header('Content-Length: ' . strlen($data));
    header('ETag: "av-' . $id . '-' . crc32($data) . '"');
    echo $data;
} else {
    defaultAvatar();
}
