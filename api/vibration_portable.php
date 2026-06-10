<?php
// ============================================================
//  api/vibration_portable.php — Endpoint WitMotion WTV B02-485
//
//  Menerima data 4 sensor vibration portable (bergantian)
//  ESP32 POST:
//  {
//    "machine_id": 1,
//    "sensor_num": 1,          ← sensor ke-1 s.d. 4
//    "session_id": 12,         ← dari vibration_sessions (opsional)
//    "sensor_label": "DE",     ← Drive End, NDE, Fan, dll
//    "x": 1.23, "y": 0.87, "z": 2.10, "b": 0.54,
//    "rms": 2.45,
//    "temp": 28.5              ← suhu internal sensor
//  }
//
//  GET ?action=session_start&machine_id=1&sensor_num=1&label=DE
//  GET ?action=session_end&session_id=12
//  GET ?action=latest&machine_id=1&sensor_num=0  (0=semua)
//  GET ?action=history&machine_id=1&sensor_num=1&limit=50
// ============================================================
require_once '../includes/config.php';
header('Content-Type: application/json; charset=utf-8');

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// ISO 10816 thresholds (mm/s RMS)
define('VIB_WARN',     2.8);
define('VIB_CRITICAL', 7.1);

// ─────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $raw   = file_get_contents('php://input');
    $input = $raw ? json_decode($raw, true) : $_POST;

    $mid       = (int)($input['machine_id']  ?? 0);
    $sensorNum = (int)($input['sensor_num']  ?? 1);
    $sessionId = isset($input['session_id']) ? (int)$input['session_id'] : null;
    $label     = trim($input['sensor_label'] ?? '');
    $x         = isset($input['x'])   ? (float)$input['x']   : null;
    $y         = isset($input['y'])   ? (float)$input['y']   : null;
    $z         = isset($input['z'])   ? (float)$input['z']   : null;
    $b         = isset($input['b'])   ? (float)$input['b']   : null;
    $temp      = isset($input['temp'])? (float)$input['temp']: null;

    // Hitung RMS jika tidak dikirim
    $rms = isset($input['rms']) ? (float)$input['rms'] : null;
    if ($rms === null) {
        $vals = array_filter([$x, $y, $z, $b], fn($v) => $v !== null);
        $rms  = $vals ? round(sqrt(array_sum(array_map(fn($v) => $v*$v, $vals)) / count($vals)), 4) : null;
    }

    // Status ISO 10816
    $status = 'normal';
    if ($rms !== null) {
        if ($rms >= VIB_CRITICAL)     $status = 'critical';
        elseif ($rms >= VIB_WARN)     $status = 'warning';
    }

    // Insert ke vibration_readings
    $ins = $db->prepare("
        INSERT INTO vibration_readings
            (machine_id, sensor_num, sensor_1, sensor_2, sensor_3, axis_b,
             rms_overall, temp_sensor, status, session_id, recorded_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $ins->execute([$mid, $sensorNum, $x, $y, $z, $b, $rms, $temp, $status, $sessionId]);
    $newId = $db->lastInsertId();

    // Update session reading count
    if ($sessionId) {
        $db->prepare("UPDATE vibration_sessions SET reading_count = reading_count + 1 WHERE id=?")
           ->execute([$sessionId]);
    }

    // Trigger alert jika critical/warning
    if ($status !== 'normal') {
        $key = 'vib_rms_sensor'.$sensorNum;
        $db->prepare("
            INSERT INTO alerts (machine_id, sensor_key, sensor_value, threshold_hi, severity, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ")->execute([
            $mid, $key, $rms, VIB_CRITICAL,
            $status === 'critical' ? 'critical' : 'warning'
        ]);
    }

    echo json_encode([
        'success'    => true,
        'id'         => $newId,
        'rms'        => $rms,
        'status'     => $status,
        'sensor_num' => $sensorNum,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── GET ───────────────────────────────────────────────────────
$action = $_GET['action'] ?? 'latest';
$mid    = (int)($_GET['machine_id'] ?? 0);

switch ($action) {

    // Mulai session pengukuran portable
    case 'session_start':
        $sensorNum = (int)($_GET['sensor_num'] ?? 1);
        $label     = trim($_GET['label'] ?? '');
        $db->prepare("
            INSERT INTO vibration_sessions (machine_id, sensor_num, sensor_label)
            VALUES (?, ?, ?)
        ")->execute([$mid, $sensorNum, $label]);
        echo json_encode(['success'=>true,'session_id'=>$db->lastInsertId()]);
        break;

    // Akhiri session
    case 'session_end':
        $sid = (int)($_GET['session_id'] ?? 0);
        $db->prepare("UPDATE vibration_sessions SET ended_at=NOW() WHERE id=?")
           ->execute([$sid]);
        // Ambil summary session
        $sum = $db->prepare("
            SELECT COUNT(*) AS cnt, AVG(rms_overall) AS avg_rms, MAX(rms_overall) AS max_rms,
                   MAX(status) AS worst_status
            FROM vibration_readings WHERE session_id=?
        ");
        $sum->execute([$sid]);
        echo json_encode(['success'=>true,'summary'=>$sum->fetch()]);
        break;

    // Data terbaru semua sensor (untuk live monitor di web)
    case 'latest':
        $sensorNum = (int)($_GET['sensor_num'] ?? 0); // 0 = semua
        // Gunakan JOIN ke derived table MAX per sensor_num (lebih cepat dari correlated subquery)
        $sql = "
            SELECT vr.id, vr.machine_id, vr.sensor_num, vr.status,
                   vr.sensor_1, vr.sensor_2, vr.sensor_3,
                   vr.axis_b, vr.rms_overall, vr.temp_sensor,
                   vr.session_id, vr.recorded_at,
                   COALESCE(vs.sensor_label, '') AS sensor_label
            FROM vibration_readings vr
            INNER JOIN (
                SELECT machine_id, sensor_num, MAX(recorded_at) AS mx
                FROM vibration_readings
                WHERE machine_id = " . (int)$mid . "
                " . ($sensorNum ? "AND sensor_num = " . (int)$sensorNum : "") . "
                GROUP BY machine_id, sensor_num
            ) lx ON lx.machine_id = vr.machine_id
                 AND lx.sensor_num = vr.sensor_num
                 AND lx.mx         = vr.recorded_at
            LEFT JOIN vibration_sessions vs ON vs.id = vr.session_id
            ORDER BY vr.sensor_num
        ";
        $rows = $db->query($sql)->fetchAll();
        echo json_encode(['success'=>true,'data'=>$rows, 'thresholds'=>['warn'=>VIB_WARN,'critical'=>VIB_CRITICAL]], JSON_UNESCAPED_UNICODE);
        break;

    // History sensor tertentu
    case 'history':
        $sensorNum = (int)($_GET['sensor_num'] ?? 1);
        $limit     = min((int)($_GET['limit'] ?? 50), 200);
        $since     = $_GET['since'] ?? null;
        $stmt = $db->prepare("
            SELECT * FROM vibration_readings
            WHERE machine_id=? AND sensor_num=?
            " . ($since ? "AND recorded_at > ?" : "") . "
            ORDER BY recorded_at DESC LIMIT ?
        ");
        $params = $since ? [$mid, $sensorNum, $since, $limit] : [$mid, $sensorNum, $limit];
        $stmt->execute($params);
        $rows = array_reverse($stmt->fetchAll());
        echo json_encode(['success'=>true,'data'=>$rows], JSON_UNESCAPED_UNICODE);
        break;

    // List sessions portable
    case 'sessions':
        $date = $_GET['date'] ?? date('Y-m-d');
        $stmt = $db->prepare("
            SELECT vs.*, m.name AS machine_name
            FROM vibration_sessions vs
            JOIN machines m ON m.id = vs.machine_id
            WHERE vs.machine_id=? AND DATE(vs.started_at)=?
            ORDER BY vs.started_at DESC
        ");
        $stmt->execute([$mid, $date]);
        echo json_encode(['success'=>true,'data'=>$stmt->fetchAll()], JSON_UNESCAPED_UNICODE);
        break;

    default:
        echo json_encode(['success'=>false,'error'=>'Action tidak dikenal']);
}
