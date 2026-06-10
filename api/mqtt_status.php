<?php
// ============================================================
//  api/mqtt_status.php — Cek apakah data MQTT masih masuk
//  Digunakan oleh TV Dashboard dan Machine Detail untuk
//  menampilkan indikator "Live" / "No Signal"
// ============================================================
require_once '../includes/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store');

$db = getDB();

// Data dianggap "live" kalau ada sensor reading dalam 2 menit terakhir
$row = $db->query("
    SELECT
        MAX(recorded_at)                           AS last_data,
        TIMESTAMPDIFF(SECOND, MAX(recorded_at), NOW()) AS seconds_ago,
        COUNT(*)                                   AS total_today,
        source
    FROM sensor_readings
    WHERE recorded_at >= NOW() - INTERVAL 10 MINUTE
    GROUP BY source
    ORDER BY last_data DESC
    LIMIT 1
")->fetch();

$lastData   = $row['last_data']    ?? null;
$secsAgo    = $row['seconds_ago']  ?? 9999;
$source     = $row['source']       ?? '-';
$isLive     = $lastData && $secsAgo <= 120;   // live = data dalam 2 menit

// Juga cek broker reachable via TCP (cepat, 1 detik timeout)
$brokerOk = false;
$sock = @fsockopen('192.168.183.143', 1883, $errno, $errstr, 1);
if ($sock) { fclose($sock); $brokerOk = true; }

echo json_encode([
    'live'       => $isLive,
    'last_data'  => $lastData,
    'seconds_ago'=> (int)$secsAgo,
    'source'     => $source,
    'broker_ok'  => $brokerOk,
    'ts'         => date('H:i:s'),
], JSON_UNESCAPED_UNICODE);
