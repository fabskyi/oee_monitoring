<?php
// ============================================================
//  api/machine_state.php — Terima state Tower Lamp dari ESP32
//
//  POST JSON: {
//    "machine_id": 1,
//    "lamp_green":  1,   ← Input PCF8575 U58
//    "lamp_yellow": 0,
//    "lamp_red":    0,
//    "rtc_time":  "2026-06-10 08:30:00"  ← dari DS3231 (opsional)
//  }
//
//  State logic:
//    green=1, yellow=0, red=0 → run
//    yellow=1, red=0          → standby
//    red=1                    → emergency
//    semua 0                  → stop
// ============================================================
require_once '../includes/config.php';
header('Content-Type: application/json; charset=utf-8');

// ── GET: kembalikan state terbaru (dipanggil dari shift_input.php) ──
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $mid    = (int)($_GET['machine_id'] ?? 0);
    if ($action === 'latest') {
        if (!$mid) { echo json_encode(['success'=>false,'error'=>'machine_id required']); exit; }
        $db = getDB();
        $st = $db->prepare("
            SELECT state, lamp_green, lamp_yellow, lamp_red, recorded_at
            FROM machine_states WHERE machine_id=? ORDER BY recorded_at DESC LIMIT 1
        ");
        $st->execute([$mid]);
        $r = $st->fetch();
        echo json_encode([
            'success'     => true,
            'state'       => $r['state']       ?? null,
            'lamp_green'  => (int)($r['lamp_green']  ?? 0),
            'lamp_yellow' => (int)($r['lamp_yellow'] ?? 0),
            'lamp_red'    => (int)($r['lamp_red']    ?? 0),
            'ts'          => $r['recorded_at']  ?? null,
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['success'=>false,'error'=>'Action tidak dikenal']);
    }
    exit;
}

// Terima POST body JSON atau form
$raw   = file_get_contents('php://input');
$input = $raw ? json_decode($raw, true) : $_POST;
if (!$input) { echo json_encode(['success'=>false,'error'=>'No input']); exit; }

$mid    = (int)($input['machine_id']  ?? 0);
$green  = (int)($input['lamp_green']  ?? 0);
$yellow = (int)($input['lamp_yellow'] ?? 0);
$red    = (int)($input['lamp_red']    ?? 0);
$rtcTs  = $input['rtc_time'] ?? null; // dari DS3231

if (!$mid) { echo json_encode(['success'=>false,'error'=>'machine_id required']); exit; }

// ── Tentukan state dari kombinasi lampu ───────────────────────
if ($green && !$red)        $state = 'run';
elseif ($yellow && !$red)   $state = 'standby';
elseif ($red && !$green)    $state = 'emergency';
else                        $state = 'stop';

$db = getDB();

// ── Cek state terakhir — simpan hanya jika berubah ───────────
$lastStmt = $db->prepare(
    "SELECT state FROM machine_states WHERE machine_id=? ORDER BY recorded_at DESC LIMIT 1"
);
$lastStmt->execute([$mid]);
$lastState = $lastStmt->fetchColumn();

$changed = ($lastState !== $state);
if ($changed) {
    $ins = $db->prepare("
        INSERT INTO machine_states (machine_id, state, lamp_green, lamp_yellow, lamp_red, recorded_at)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $ts = $rtcTs ?: date('Y-m-d H:i:s');
    $ins->execute([$mid, $state, $green, $yellow, $red, $ts]);

    // Update machines.status
    $ms = $state === 'run' ? 'run' : 'stop';
    $db->prepare("UPDATE machines SET status=?, updated_at=NOW() WHERE id=?")->execute([$ms, $mid]);
}

// ── Hitung OEE availability realtime hari ini ─────────────────
// (durasi setiap state, dari midnight atau awal shift)
$oeeRow = calcRealtimeOEE($db, $mid);

echo json_encode([
    'success'  => true,
    'state'    => $state,
    'changed'  => $changed,
    'oee_rt'   => $oeeRow,
    'ts'       => date('H:i:s'),
], JSON_UNESCAPED_UNICODE);

// ─────────────────────────────────────────────────────────────
function calcRealtimeOEE(PDO $db, int $mid): array
{
    // Ambil state hari ini dari midnight
    $rows = $db->prepare("
        SELECT state, recorded_at
        FROM machine_states
        WHERE machine_id = ? AND recorded_at >= CURDATE()
        ORDER BY recorded_at ASC
    ");
    $rows->execute([$mid]);
    $states = $rows->fetchAll();

    $durations = ['run'=>0,'standby'=>0,'stop'=>0,'emergency'=>0];
    $now = time();

    for ($i = 0; $i < count($states); $i++) {
        $tStart = strtotime($states[$i]['recorded_at']);
        $tEnd   = isset($states[$i+1]) ? strtotime($states[$i+1]['recorded_at']) : $now;
        $dur    = max(0, $tEnd - $tStart);
        $durations[$states[$i]['state']] += $dur;
    }

    $runSec      = $durations['run'];
    $standbySec  = $durations['standby'];
    $stopSec     = $durations['stop'] + $durations['emergency'];
    $totalSec    = $runSec + $standbySec + $stopSec;

    // Planned time: ambil dari shift config atau pakai waktu berlalu hari ini
    $plannedSec = max($totalSec, 1);

    $availability = round(($runSec + $standbySec) / $plannedSec * 100, 1);
    $performance  = ($runSec + $standbySec) > 0
                    ? round($runSec / ($runSec + $standbySec) * 100, 1)
                    : 0;

    return [
        'run_min'      => round($runSec/60),
        'standby_min'  => round($standbySec/60),
        'stop_min'     => round($stopSec/60),
        'availability' => $availability,
        'performance'  => $performance,
    ];
}
