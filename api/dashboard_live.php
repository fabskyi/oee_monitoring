<?php
// ============================================================
//  api/dashboard_live.php — Fast poll untuk realtime MQTT
//  Hanya return: status mesin + last_reading + mqtt indicator
//  Query ringan, dipanggil setiap 5 detik dari dashboard.php
// ============================================================
require_once '../includes/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store');

$db = getDB();

// ── Status + last sensor reading per mesin (satu query GROUP BY) ──
$rows = $db->query("
    SELECT m.id, m.name, m.status,
           sr.last_reading,
           TIMESTAMPDIFF(SECOND, sr.last_reading, NOW()) AS secs_ago
    FROM machines m
    LEFT JOIN (
        SELECT machine_id, MAX(recorded_at) AS last_reading
        FROM sensor_readings
        WHERE recorded_at >= NOW() - INTERVAL 24 HOUR
        GROUP BY machine_id
    ) sr ON sr.machine_id = m.id
    ORDER BY m.id ASC
")->fetchAll();

// ── Hitung running / stopped ──────────────────────────────────
$running = 0; $stopped = 0;
foreach ($rows as $r) {
    if ($r['status'] === 'run') $running++; else $stopped++;
}

// ── MQTT live check: ada data dalam 2 menit? ─────────────────
$mqttRow = $db->query("
    SELECT MAX(recorded_at) AS last_ts,
           TIMESTAMPDIFF(SECOND, MAX(recorded_at), NOW()) AS secs_ago
    FROM sensor_readings
    WHERE recorded_at >= NOW() - INTERVAL 10 MINUTE
      AND source IN ('mqtt','esp32_mqtt')
")->fetch();

$mqttLive    = $mqttRow && $mqttRow['last_ts'] && (int)$mqttRow['secs_ago'] <= 120;
$mqttLastTs  = $mqttRow['last_ts'] ?? null;
$mqttSecsAgo = $mqttRow ? (int)$mqttRow['secs_ago'] : 9999;

echo json_encode([
    'ts'          => date('H:i:s'),
    'running'     => $running,
    'stopped'     => $stopped,
    'mqtt_live'   => $mqttLive,
    'mqtt_last'   => $mqttLastTs ? date('H:i:s', strtotime($mqttLastTs)) : null,
    'mqtt_secs'   => $mqttSecsAgo,
    'machines'    => array_map(function($r) {
        return [
            'id'          => (int)$r['id'],
            'status'      => $r['status'],
            'last_reading'=> $r['last_reading'],
            'secs_ago'    => $r['secs_ago'] !== null ? (int)$r['secs_ago'] : null,
        ];
    }, $rows),
], JSON_UNESCAPED_UNICODE);
