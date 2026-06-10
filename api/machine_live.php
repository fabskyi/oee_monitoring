<?php
// ============================================================
//  api/machine_live.php — Live data untuk machine_detail AJAX
//  GET params:
//    machine_id  (required)
//    since       (datetime string, optional) — ambil data baru sejak waktu ini
// ============================================================
require_once '../includes/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store');

$db        = getDB();
$machineId = isset($_GET['machine_id']) ? (int)$_GET['machine_id'] : 0;
$since     = $_GET['since'] ?? null;

if (!$machineId) {
    echo json_encode(['success' => false, 'message' => 'machine_id required']);
    exit;
}

// ── 1. Latest sensor reading ──────────────────────────────────
$latest = $db->prepare("
    SELECT * FROM sensor_readings
    WHERE machine_id = ?
    ORDER BY recorded_at DESC
    LIMIT 1
");
$latest->execute([$machineId]);
$sensor = $latest->fetch() ?: null;

// ── 2. Latest vibration reading ───────────────────────────────
$latVib = $db->prepare("
    SELECT * FROM vibration_readings
    WHERE machine_id = ?
    ORDER BY recorded_at DESC
    LIMIT 1
");
$latVib->execute([$machineId]);
$vibration = $latVib->fetch() ?: null;

// ── 3. New sensor rows since ?since ───────────────────────────
//    Digunakan untuk append ke chart & tabel riwayat
$newRows = [];
if ($since) {
    $stmtNew = $db->prepare("
        SELECT recorded_at,
               v_r, v_s, v_t,
               a_r, a_s, a_t,
               f_r,
               e_r, e_s, e_t,
               temp_panel, hum_panel, source
        FROM sensor_readings
        WHERE machine_id = ?
          AND recorded_at > ?
        ORDER BY recorded_at ASC
        LIMIT 50
    ");
    $stmtNew->execute([$machineId, $since]);
    $newRows = $stmtNew->fetchAll();
}

// ── 4. New alerts since ?since ────────────────────────────────
$newAlerts = [];
if ($since) {
    $stmtAl = $db->prepare("
        SELECT created_at, sensor_key, sensor_value, severity, acknowledged
        FROM alerts
        WHERE machine_id = ?
          AND created_at > ?
        ORDER BY created_at ASC
        LIMIT 20
    ");
    $stmtAl->execute([$machineId, $since]);
    $newAlerts = $stmtAl->fetchAll();
}

// ── 5. Machine status (run/stop) ──────────────────────────────
$stmtM = $db->prepare("SELECT status FROM machines WHERE id = ? LIMIT 1");
$stmtM->execute([$machineId]);
$machStatus = $stmtM->fetchColumn() ?: 'unknown';

echo json_encode([
    'success'    => true,
    'ts'         => date('H:i:s'),
    'sensor'     => $sensor,
    'vibration'  => $vibration,
    'new_rows'   => $newRows,
    'new_alerts' => $newAlerts,
    'status'     => $machStatus,
], JSON_UNESCAPED_UNICODE);
