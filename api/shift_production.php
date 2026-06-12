<?php
// ============================================================
//  api/shift_production.php — Manajemen produksi per shift
//
//  GET  ?action=current&machine_id=1       → shift aktif sekarang
//  GET  ?action=list&machine_id=1&date=... → list shift suatu hari
//  POST action=increment   → +1 total_out (dipanggil ESP32 atau manual)
//  POST action=close       → tutup shift, input reject, hitung OEE Quality
//  POST action=set_plan    → set target qty
//  POST action=open        → buka/buat shift baru
// ============================================================
require_once '../includes/config.php';
header('Content-Type: application/json; charset=utf-8');

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$action = ($method === 'GET') ? ($_GET['action'] ?? 'current') : (json_decode(file_get_contents('php://input'),true)['action'] ?? $_POST['action'] ?? '');
$input  = $method === 'POST' ? (json_decode(file_get_contents('php://input'),true) ?: $_POST) : [];

// ── Helper: shift aktif dari waktu sekarang ───────────────────
function getCurrentShift(PDO $db, int $mid): ?array
{
    $now  = date('H:i:s');
    $date = date('Y-m-d');

    // Cari di shift_config yang aktif
    $cfg = $db->prepare("
        SELECT sc.*, sp.id AS sp_id, sp.total_out, sp.total_reject, sp.good_count,
               sp.plan_qty AS sp_plan, sp.is_closed, sp.operator_name
        FROM shift_config sc
        LEFT JOIN shift_production sp
            ON sp.machine_id = sc.machine_id
           AND sp.shift_date  = ?
           AND sp.shift_no    = sc.shift_no
        WHERE sc.machine_id = ? AND sc.is_active = 1
          AND (
              (sc.start_time < sc.end_time AND ? BETWEEN sc.start_time AND sc.end_time)
           OR (sc.start_time > sc.end_time AND (? >= sc.start_time OR ? < sc.end_time))
          )
        LIMIT 1
    ");
    $cfg->execute([$date, $mid, $now, $now, $now]);
    $row = $cfg->fetch();
    if (!$row) return null;

    // Auto-buat record shift_production jika belum ada
    if (!$row['sp_id']) {
        $db->prepare("
            INSERT IGNORE INTO shift_production
                (machine_id, shift_date, shift_no, shift_start, shift_end, plan_qty)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([$mid, $date, $row['shift_no'], $row['start_time'], $row['end_time'], $row['plan_qty']]);

        $row['sp_id']        = $db->lastInsertId();
        $row['total_out']    = 0;
        $row['total_reject'] = 0;
        $row['good_count']   = 0;
        $row['is_closed']    = 0;
    }

    $row['sp_plan'] = $row['sp_plan'] ?: $row['plan_qty'];
    return $row;
}

// ─────────────────────────────────────────────────────────────
switch ($action) {

    // ── GET: shift aktif sekarang ─────────────────────────────
    case 'current':
        $mid   = (int)($_GET['machine_id'] ?? 0);
        $shift = getCurrentShift($db, $mid);
        if (!$shift) { echo json_encode(['success'=>false,'error'=>'Tidak ada shift aktif']); break; }

        // Hitung OEE availability + performance dari machine_states
        $oeeRt = getRealtimeAPQ($db, $mid, $shift);
        echo json_encode(['success'=>true, 'shift'=>$shift, 'oee_rt'=>$oeeRt], JSON_UNESCAPED_UNICODE);
        break;

    // ── GET: list shift suatu tanggal ─────────────────────────
    case 'list':
        $mid  = (int)($_GET['machine_id'] ?? 0);
        $date = $_GET['date'] ?? date('Y-m-d');
        $rows = $db->prepare("
            SELECT sp.*, m.name AS machine_name
            FROM shift_production sp
            JOIN machines m ON m.id = sp.machine_id
            WHERE sp.machine_id = ? AND sp.shift_date = ?
            ORDER BY sp.shift_no
        ");
        $rows->execute([$mid, $date]);
        echo json_encode(['success'=>true,'data'=>$rows->fetchAll()], JSON_UNESCAPED_UNICODE);
        break;

    // ── POST: +1 produk keluar (ESP32 atau tombol manual) ─────
    case 'increment':
        $mid   = (int)($input['machine_id'] ?? 0);
        $qty   = (int)($input['qty'] ?? 1);
        $shift = getCurrentShift($db, $mid);
        if (!$shift || $shift['is_closed']) {
            echo json_encode(['success'=>false,'error'=>'Shift tidak aktif atau sudah ditutup']); break;
        }
        $db->prepare("
            UPDATE shift_production SET total_out = total_out + ?, good_count = good_count + ?
            WHERE id = ?
        ")->execute([$qty, $qty, $shift['sp_id']]);
        $newOut = $shift['total_out'] + $qty;
        echo json_encode(['success'=>true,'total_out'=>$newOut], JSON_UNESCAPED_UNICODE);
        break;

    // ── POST: tutup shift + input reject ─────────────────────
    case 'close':
        $mid          = (int)($input['machine_id'] ?? 0);
        $totalReject  = (int)($input['total_reject'] ?? 0);
        $operatorName = trim($input['operator_name'] ?? '');
        $notes        = trim($input['notes'] ?? '');

        $shift = getCurrentShift($db, $mid);
        if (!$shift) { echo json_encode(['success'=>false,'error'=>'Shift tidak ditemukan']); break; }
        if ($shift['is_closed']) { echo json_encode(['success'=>false,'error'=>'Shift sudah ditutup']); break; }

        $totalOut  = (int)$shift['total_out'];
        $goodCount = max(0, $totalOut - $totalReject);
        $quality   = $totalOut > 0 ? round($goodCount / $totalOut * 100, 1) : 0;

        // Simpan ke shift_production
        $db->prepare("
            UPDATE shift_production
            SET total_reject  = ?,
                good_count    = ?,
                operator_name = ?,
                notes         = ?,
                is_closed     = 1,
                input_at      = NOW()
            WHERE id = ?
        ")->execute([$totalReject, $goodCount, $operatorName, $notes, $shift['sp_id']]);

        // Hitung A & P dari machine_states lalu simpan ke oee_daily
        $oeeRt = getRealtimeAPQ($db, $mid, $shift);
        $avail  = $oeeRt['availability'];
        $perf   = $oeeRt['performance'];
        $oeeScore = round($avail * $perf * $quality / 10000, 1);

        $db->prepare("
            INSERT INTO oee_daily
                (machine_id, snap_date, availability, performance, quality, oee_score)
            VALUES (?, CURDATE(), ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                availability = VALUES(availability),
                performance  = VALUES(performance),
                quality      = VALUES(quality),
                oee_score    = VALUES(oee_score)
        ")->execute([$mid, $avail, $perf, $quality, $oeeScore]);

        echo json_encode([
            'success'    => true,
            'total_out'  => $totalOut,
            'reject'     => $totalReject,
            'good'       => $goodCount,
            'quality'    => $quality,
            'availability'=> $avail,
            'performance' => $perf,
            'oee'        => $oeeScore,
        ], JSON_UNESCAPED_UNICODE);
        break;

    // ── POST: set target plan_qty ─────────────────────────────
    case 'set_plan':
        $mid     = (int)($input['machine_id'] ?? 0);
        $shiftNo = (int)($input['shift_no']   ?? 1);
        $planQty = (int)($input['plan_qty']   ?? 0);
        $date    = $input['date'] ?? date('Y-m-d');

        $db->prepare("
            UPDATE shift_production SET plan_qty=? WHERE machine_id=? AND shift_date=? AND shift_no=?
        ")->execute([$planQty, $mid, $date, $shiftNo]);
        $db->prepare("
            UPDATE shift_config SET plan_qty=? WHERE machine_id=? AND shift_no=?
        ")->execute([$planQty, $mid, $shiftNo]);

        echo json_encode(['success'=>true,'plan_qty'=>$planQty], JSON_UNESCAPED_UNICODE);
        break;

    // ── POST: update total_out tanpa close shift ──────────────
    case 'set_total_out':
        $mid      = (int)($input['machine_id'] ?? 0);
        $totalOut = (int)($input['total_out']  ?? 0);
        $shift    = getCurrentShift($db, $mid);
        if (!$shift) { echo json_encode(['success'=>false,'error'=>'Tidak ada shift aktif']); break; }
        if ($shift['is_closed']) { echo json_encode(['success'=>false,'error'=>'Shift sudah ditutup']); break; }
        $good = max(0, $totalOut - (int)$shift['total_reject']);
        $db->prepare("UPDATE shift_production SET total_out=?, good_count=?, input_at=NOW() WHERE id=?")
           ->execute([$totalOut, $good, $shift['sp_id']]);
        echo json_encode(['success'=>true,'total_out'=>$totalOut,'good_count'=>$good], JSON_UNESCAPED_UNICODE);
        break;

    // ── POST: buka/buat shift baru secara manual ─────────────
    case 'open':
        $mid     = (int)($input['machine_id'] ?? 0);
        $shiftNo = (int)($input['shift_no']   ?? 1);
        $date    = $input['date'] ?? date('Y-m-d');
        $cfgStmt = $db->prepare("SELECT * FROM shift_config WHERE machine_id=? AND shift_no=? AND is_active=1 LIMIT 1");
        $cfgStmt->execute([$mid, $shiftNo]);
        $cfg = $cfgStmt->fetch();
        if (!$cfg) { echo json_encode(['success'=>false,'error'=>'Shift config tidak ditemukan']); break; }
        $db->prepare("INSERT IGNORE INTO shift_production (machine_id, shift_date, shift_no, shift_start, shift_end, plan_qty) VALUES (?,?,?,?,?,?)")
           ->execute([$mid, $date, $shiftNo, $cfg['start_time'], $cfg['end_time'], $cfg['plan_qty']]);
        $newId = $db->lastInsertId();
        echo json_encode(['success'=>true,'sp_id'=>$newId], JSON_UNESCAPED_UNICODE);
        break;

    // ── GET: latest production totals for a specific shift (ESP32 source) ──
    // NOTE: This is a fallback mock/passthrough. If you have a real ESP32
    // sensor table, replace the query below with your actual sensor read.
    // Currently it reads from shift_production (same as manual save), which
    // means it returns the last saved values — useful as a sync check.
    case 'esp32_latest':
        $mid     = (int)($_GET['machine_id'] ?? $input['machine_id'] ?? 0);
        $shiftNo = (int)($_GET['shift_no']   ?? $input['shift_no']   ?? 0);
        $date    = $_GET['date'] ?? $input['date'] ?? date('Y-m-d');

        if (!$mid || !$shiftNo) {
            echo json_encode(['success'=>false,'error'=>'machine_id and shift_no required']);
            break;
        }

        // --- Attempt to read from a real sensor table (esp32_counters) if it exists ---
        $hasSensorTable = false;
        try {
            $chk = $db->query("SELECT 1 FROM esp32_counters LIMIT 1");
            $hasSensorTable = ($chk !== false);
        } catch (Exception $e) { $hasSensorTable = false; }

        if ($hasSensorTable) {
            // Real sensor path: read latest row from esp32_counters
            $stmt = $db->prepare("
                SELECT total_out, total_reject, recorded_at
                FROM esp32_counters
                WHERE machine_id = ? AND shift_no = ? AND shift_date = ?
                ORDER BY recorded_at DESC LIMIT 1
            ");
            $stmt->execute([$mid, $shiftNo, $date]);
            $row = $stmt->fetch();
            if ($row) {
                echo json_encode(['success'=>true,'source'=>'esp32','data'=>[
                    'total_out'    => (int)$row['total_out'],
                    'total_reject' => (int)$row['total_reject'],
                    'recorded_at'  => $row['recorded_at'],
                ]], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['success'=>false,'error'=>'No sensor data available']);
            }
        } else {
            // Fallback mock: return last saved production values from shift_production
            $stmt = $db->prepare("
                SELECT total_out, total_reject, input_at AS recorded_at
                FROM shift_production
                WHERE machine_id = ? AND shift_no = ? AND shift_date = ?
                LIMIT 1
            ");
            $stmt->execute([$mid, $shiftNo, $date]);
            $row = $stmt->fetch();
            if ($row) {
                echo json_encode(['success'=>true,'source'=>'fallback_db','data'=>[
                    'total_out'    => (int)$row['total_out'],
                    'total_reject' => (int)$row['total_reject'],
                    'recorded_at'  => $row['recorded_at'],
                ]], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['success'=>false,'error'=>'No sensor data available']);
            }
        }
        break;

    default:
        echo json_encode(['success'=>false,'error'=>'Action tidak dikenal']);
}

// ─────────────────────────────────────────────────────────────
function getRealtimeAPQ(PDO $db, int $mid, array $shift): array
{
    // Ambil machine_states dalam rentang waktu shift hari ini
    $shiftDate  = $shift['shift_date'] ?? date('Y-m-d');
    $shiftStart = $shiftDate . ' ' . ($shift['shift_start'] ?? '07:00:00');
    $shiftEnd   = date('Y-m-d H:i:s'); // sampai sekarang

    $rows = $db->prepare("
        SELECT state, recorded_at FROM machine_states
        WHERE machine_id = ? AND recorded_at >= ? AND recorded_at <= ?
        ORDER BY recorded_at ASC
    ");
    $rows->execute([$mid, $shiftStart, $shiftEnd]);
    $states = $rows->fetchAll();

    $dur = ['run'=>0,'standby'=>0,'stop'=>0,'emergency'=>0];
    $now = time();

    for ($i = 0; $i < count($states); $i++) {
        $tS = strtotime($states[$i]['recorded_at']);
        $tE = isset($states[$i+1]) ? strtotime($states[$i+1]['recorded_at']) : $now;
        $d  = max(0, $tE - $tS);
        $dur[$states[$i]['state']] += $d;
    }

    $runSec     = $dur['run'];
    $standbySec = $dur['standby'];
    $stopSec    = $dur['stop'] + $dur['emergency'];
    $totalSec   = $runSec + $standbySec + $stopSec;

    // Planned time = shift duration
    $sStart = strtotime($shiftStart);
    $sEnd   = strtotime($shift['shift_date'].' '.$shift['shift_end']);
    if ($sEnd <= $sStart) $sEnd += 86400; // shift malam melewati tengah malam
    $plannedSec = max($sEnd - $sStart, 1);

    $availability = round(($runSec + $standbySec) / $plannedSec * 100, 1);
    $performance  = ($runSec + $standbySec) > 0
                    ? round($runSec / ($runSec + $standbySec) * 100, 1)
                    : 0;

    // Quality (dari shift_production jika sudah ada data)
    $totalOut  = (int)($shift['total_out']   ?? 0);
    $reject    = (int)($shift['total_reject'] ?? 0);
    $good      = max(0, $totalOut - $reject);
    $quality   = $totalOut > 0 ? round($good / $totalOut * 100, 1) : 100;

    $oee = round($availability * $performance * $quality / 10000, 1);

    return [
        'run_min'       => round($runSec/60),
        'standby_min'   => round($standbySec/60),
        'stop_min'      => round($stopSec/60),
        'planned_min'   => round($plannedSec/60),
        'availability'  => $availability,
        'performance'   => $performance,
        'quality'       => $quality,
        'oee'           => $oee,
        'total_out'     => $totalOut,
        'total_reject'  => $reject,
    ];
}
