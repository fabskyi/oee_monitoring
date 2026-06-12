<?php
// ── ESP32 OTA Firmware Server ─────────────────────────────────────────────────
// URL yang dipasang di CFG_OTA_URL:
//   http://192.168.183.143/oee/api/esp32_ota.php?device=ESP32-OEE-001
//
// ESP32 memanggil URL ini saat cek OTA. Server akan:
//   • Cari firmware aktif (is_active=1) untuk device tersebut
//   • Kirim file .bin jika ada, header 304 jika tidak ada update baru
// ─────────────────────────────────────────────────────────────────────────────
require_once '../includes/config.php';

$devId = trim($_GET['device'] ?? '');
if (!$devId) { http_response_code(400); exit('device param required'); }

$pdo = getDB();
try {
    $s = $pdo->prepare("SELECT * FROM esp32_firmware WHERE device_id=? AND is_active=1 ORDER BY id DESC LIMIT 1");
    $s->execute([$devId]);
    $fw = $s->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    exit('DB error');
}

if (!$fw) {
    // Tidak ada firmware aktif
    http_response_code(304);
    exit;
}

$path = __DIR__ . '/../uploads/firmware/' . $fw['filename'];
if (!file_exists($path)) {
    http_response_code(404);
    exit('Firmware file not found');
}

// Cek versi dari header X-ESP32-STA-MAC atau custom header
// ESP32 HTTPUpdate mengirim header "x-ESP32-version"
$clientVer = $_SERVER['HTTP_X_ESP32_VERSION'] ?? '';
if ($clientVer && $clientVer === $fw['version']) {
    // Versi sama — tidak perlu update
    http_response_code(304);
    exit;
}

// Kirim binary
$size = filesize($path);
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($path) . '"');
header('Content-Length: ' . $size);
header('x-MD5: ' . md5_file($path));
http_response_code(200);
readfile($path);
exit;
