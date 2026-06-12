<?php
require_once '../includes/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }
session_write_close();

header('Content-Type: application/json; charset=utf-8');

$uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'chat' . DIRECTORY_SEPARATOR;
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
    echo json_encode(['error' => 'Cannot create upload directory']); exit;
}

if (empty($_FILES['file'])) { echo json_encode(['error' => 'No file']); exit; }
$f = $_FILES['file'];
if ($f['error'] !== UPLOAD_ERR_OK) { echo json_encode(['error' => 'Upload error ' . $f['error']]); exit; }
if ($f['size'] > 20 * 1024 * 1024) { echo json_encode(['error' => 'Max 20MB']); exit; }

$ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
$allowedImg   = ['jpg','jpeg','png','gif','webp'];
$allowedAudio = ['mp3','ogg','webm','wav','m4a','opus'];
$allowedFile  = ['pdf','doc','docx','xls','xlsx','txt','zip','csv','ppt','pptx'];
$allowed = array_merge($allowedImg, $allowedAudio, $allowedFile);
if (!in_array($ext, $allowed, true)) { echo json_encode(['error' => 'File type not allowed']); exit; }

$name = bin2hex(random_bytes(12)) . '.' . $ext;
if (!move_uploaded_file($f['tmp_name'], $uploadDir . $name)) { echo json_encode(['error' => 'Move failed']); exit; }

$type = in_array($ext, $allowedImg, true) ? 'image'
      : (in_array($ext, $allowedAudio, true) ? 'audio' : 'file');

echo json_encode([
    'ok'   => true,
    'url'  => 'uploads/chat/' . $name,
    'type' => $type,
    'name' => htmlspecialchars($f['name'], ENT_QUOTES, 'UTF-8'),
    'size' => $f['size'],
]);
