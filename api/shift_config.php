<?php
// ============================================================
//  api/shift_config.php — CRUD Konfigurasi Shift, Target & Istirahat
//
//  GET  ?action=list&machine_id=1
//  GET  ?action=breaks&machine_id=1&shift_no=1
//  GET  ?action=day_summary&machine_id=1&date=2026-06-10
//  POST action=save           → buat/update shift config
//  POST action=delete         → hapus shift config
//  POST action=toggle         → aktif/nonaktif
//  POST action=save_break     → tambah/update jam istirahat
//  POST action=delete_break   → hapus jam istirahat
//  POST action=toggle_break   → aktif/nonaktif istirahat
//  POST action=save_day_plan  → override target hari ini
//  POST action=save_production→ simpan output + reject
// ============================================================
require_once '../includes/config.php';
header('Content-Type: application/json; charset=utf-8');

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$action = $method === 'GET'
    ? ($_GET['action'] ?? 'list')
    : (json_decode(file_get_contents('php://input'), true)['action'] ?? '');
$input = $method === 'POST'
    ? (json_decode(file_get_contents('php://input'), true) ?: $_POST)
    : [];

// ─────────────────────────────────────────────────────────────
switch ($action) {

    // ── GET: list shift config ────────────────────────────────
    case 'list':
        $mid = (int)($_GET['machine_id'] ?? 0);
        if (!$mid) { echo json_encode(['success'=>false,'error'=>'machine_id required']); exit; }
        $rows = $db->prepare("
            SELECT id, shift_no, shift_name, start_time, end_time, plan_qty, is_active,
                   TIME_TO_SEC(TIMEDIFF(
                       IF(end_time > start_time, end_time, ADDTIME(end_time,'24:00:00')),
                       start_time)) / 60 AS duration_min
            FROM shift_config
            WHERE machine_id = ?
            ORDER BY shift_no
        ");
        $rows->execute([$mid]);
        $shifts = $rows->fetchAll();

        // Sertakan total menit istirahat per shift
        foreach ($shifts as &$sh) {
            $sh['break_minutes'] = getTotalBreakMin($db, $mid, (int)$sh['shift_no']);
            $sh['net_minutes']   = max(0, (float)$sh['duration_min'] - $sh['break_minutes']);
        }
        unset($sh);
        echo json_encode(['success'=>true,'data'=>$shifts], JSON_UNESCAPED_UNICODE);
        break;

    // ── GET: list istirahat untuk satu shift ──────────────────
    case 'breaks':
        $mid = (int)($_GET['machine_id'] ?? 0);
        $sno = (int)($_GET['shift_no']   ?? 0);
        $rows = $db->prepare("
            SELECT id, break_name, start_time, end_time, is_active,
                   TIME_TO_SEC(TIMEDIFF(end_time, start_time))/60 AS duration_min
            FROM shift_breaks
            WHERE machine_id=? AND shift_no=?
            ORDER BY start_time
        ");
        $rows->execute([$mid, $sno]);
        echo json_encode(['success'=>true,'data'=>$rows->fetchAll()], JSON_UNESCAPED_UNICODE);
        break;

    // ── GET: ringkasan harian (shift + produksi + availability) ─
    case 'day_summary':
        $mid  = (int)($_GET['machine_id'] ?? 0);
        $date = $_GET['date'] ?? date('Y-m-d');
        if (!$mid) { echo json_encode(['success'=>false,'error'=>'machine_id required']); exit; }

        $rows = $db->prepare("
            SELECT sc.id AS config_id, sc.shift_no, sc.shift_name,
                   sc.start_time, sc.end_time, sc.plan_qty AS default_plan, sc.is_active,
                   TIME_TO_SEC(TIMEDIFF(
                       IF(sc.end_time > sc.start_time, sc.end_time, ADDTIME(sc.end_time,'24:00:00')),
                       sc.start_time)) / 60 AS duration_min,
                   sp.id AS sp_id, sp.plan_qty AS day_plan,
                   sp.total_out, sp.total_reject, sp.good_count,
                   sp.operator_name, sp.notes, sp.is_closed, sp.input_at
            FROM shift_config sc
            LEFT JOIN shift_production sp
                   ON sp.machine_id = sc.machine_id
                  AND sp.shift_date  = ?
                  AND sp.shift_no    = sc.shift_no
            WHERE sc.machine_id = ? AND sc.is_active = 1
            ORDER BY sc.shift_no
        ");
        $rows->execute([$date, $mid]);
        $shifts = $rows->fetchAll();

        foreach ($shifts as &$sh) {
            $breakMin = getTotalBreakMin($db, $mid, (int)$sh['shift_no']);
            $sh['break_minutes'] = $breakMin;
            $sh['net_minutes']   = max(0, (float)$sh['duration_min'] - $breakMin);
            $sh['availability']  = calcAvailability($db, $mid, $date,
                                       $sh['start_time'], $sh['end_time'], $breakMin);
            $sh['plan_qty']      = $sh['day_plan'] ?? $sh['default_plan'];
        }
        unset($sh);

        echo json_encode(['success'=>true,'data'=>$shifts,'date'=>$date], JSON_UNESCAPED_UNICODE);
        break;

    // ── POST: simpan shift config ─────────────────────────────
    case 'save':
        $id        = (int)($input['id']         ?? 0);
        $mid       = (int)($input['machine_id'] ?? 0);
        $shiftNo   = (int)($input['shift_no']   ?? 0);
        $shiftName = trim($input['shift_name']  ?? '');
        $startTime = $input['start_time'] ?? '';
        $endTime   = $input['end_time']   ?? '';
        $planQty   = (int)($input['plan_qty']   ?? 0);

        if (!$mid || !$shiftNo || !$startTime || !$endTime) {
            echo json_encode(['success'=>false,'error'=>'Data tidak lengkap']); exit;
        }
        if (!$shiftName) $shiftName = 'Shift ' . $shiftNo;

        $re = '/^\d{1,2}:\d{2}(:\d{2})?$/';
        if (!preg_match($re, $startTime) || !preg_match($re, $endTime)) {
            echo json_encode(['success'=>false,'error'=>'Format waktu harus HH:MM']); exit;
        }

        if ($id) {
            $db->prepare("UPDATE shift_config SET shift_no=?,shift_name=?,start_time=?,end_time=?,plan_qty=?
                WHERE id=? AND machine_id=?")
               ->execute([$shiftNo,$shiftName,$startTime,$endTime,$planQty,$id,$mid]);
            $newId = $id;
        } else {
            $exists = $db->prepare("SELECT id FROM shift_config WHERE machine_id=? AND shift_no=?");
            $exists->execute([$mid, $shiftNo]);
            if ($exists->fetch()) {
                echo json_encode(['success'=>false,'error'=>"Shift $shiftNo sudah ada"]); exit;
            }
            $db->prepare("INSERT INTO shift_config (machine_id,shift_no,shift_name,start_time,end_time,plan_qty,is_active) VALUES (?,?,?,?,?,?,1)")
               ->execute([$mid,$shiftNo,$shiftName,$startTime,$endTime,$planQty]);
            $newId = $db->lastInsertId();
        }
        echo json_encode(['success'=>true,'id'=>$newId], JSON_UNESCAPED_UNICODE);
        break;

    // ── POST: hapus shift config ──────────────────────────────
    case 'delete':
        $id  = (int)($input['id']         ?? 0);
        $mid = (int)($input['machine_id'] ?? 0);
        if (!$id||!$mid){ echo json_encode(['success'=>false,'error'=>'ID required']); exit; }
        $db->prepare("DELETE FROM shift_config WHERE id=? AND machine_id=?")->execute([$id,$mid]);
        echo json_encode(['success'=>true], JSON_UNESCAPED_UNICODE);
        break;

    // ── POST: toggle aktif shift ──────────────────────────────
    case 'toggle':
        $id  = (int)($input['id']         ?? 0);
        $mid = (int)($input['machine_id'] ?? 0);
        $db->prepare("UPDATE shift_config SET is_active=1-is_active WHERE id=? AND machine_id=?")
           ->execute([$id,$mid]);
        $val = $db->prepare("SELECT is_active FROM shift_config WHERE id=?");
        $val->execute([$id]);
        echo json_encode(['success'=>true,'is_active'=>(int)$val->fetchColumn()], JSON_UNESCAPED_UNICODE);
        break;

    // ── POST: simpan jam istirahat ────────────────────────────
    case 'save_break':
        $id        = (int)($input['id']         ?? 0);
        $mid       = (int)($input['machine_id'] ?? 0);
        $sno       = (int)($input['shift_no']   ?? 0);
        $name      = trim($input['break_name']  ?? 'Istirahat');
        $startTime = $input['start_time'] ?? '';
        $endTime   = $input['end_time']   ?? '';

        if (!$mid||!$sno||!$startTime||!$endTime) {
            echo json_encode(['success'=>false,'error'=>'Data tidak lengkap']); exit;
        }
        // Validasi end > start
        if ($endTime <= $startTime) {
            echo json_encode(['success'=>false,'error'=>'Jam selesai harus lebih dari jam mulai']); exit;
        }

        if ($id) {
            $db->prepare("UPDATE shift_breaks SET break_name=?,start_time=?,end_time=? WHERE id=? AND machine_id=?")
               ->execute([$name,$startTime,$endTime,$id,$mid]);
            $newId = $id;
        } else {
            $db->prepare("INSERT INTO shift_breaks (machine_id,shift_no,break_name,start_time,end_time,is_active) VALUES (?,?,?,?,?,1)")
               ->execute([$mid,$sno,$name,$startTime,$endTime]);
            $newId = $db->lastInsertId();
        }

        // Hitung ulang durasi
        $dur = (strtotime('1970-01-01 '.$endTime) - strtotime('1970-01-01 '.$startTime)) / 60;
        echo json_encode(['success'=>true,'id'=>$newId,'duration_min'=>$dur], JSON_UNESCAPED_UNICODE);
        break;

    // ── POST: hapus jam istirahat ─────────────────────────────
    case 'delete_break':
        $id  = (int)($input['id']         ?? 0);
        $mid = (int)($input['machine_id'] ?? 0);
        if (!$id||!$mid){ echo json_encode(['success'=>false,'error'=>'ID required']); exit; }
        $db->prepare("DELETE FROM shift_breaks WHERE id=? AND machine_id=?")->execute([$id,$mid]);
        echo json_encode(['success'=>true], JSON_UNESCAPED_UNICODE);
        break;

    // ── POST: toggle aktif istirahat ──────────────────────────
    case 'toggle_break':
        $id  = (int)($input['id']         ?? 0);
        $mid = (int)($input['machine_id'] ?? 0);
        $db->prepare("UPDATE shift_breaks SET is_active=1-is_active WHERE id=? AND machine_id=?")
           ->execute([$id,$mid]);
        $val = $db->prepare("SELECT is_active FROM shift_breaks WHERE id=?");
        $val->execute([$id]);
        echo json_encode(['success'=>true,'is_active'=>(int)$val->fetchColumn()], JSON_UNESCAPED_UNICODE);
        break;

    // ── POST: override target hari ini ────────────────────────
    case 'save_day_plan':
        $mid     = (int)($input['machine_id'] ?? 0);
        $date    = $input['date']    ?? date('Y-m-d');
        $shiftNo = (int)($input['shift_no'] ?? 0);
        $plan    = (int)($input['plan_qty'] ?? 0);
        $cfg     = $db->prepare("SELECT * FROM shift_config WHERE machine_id=? AND shift_no=? LIMIT 1");
        $cfg->execute([$mid, $shiftNo]);
        $cfgRow  = $cfg->fetch();
        if (!$cfgRow){ echo json_encode(['success'=>false,'error'=>'Shift config tidak ada']); exit; }
        $db->prepare("INSERT INTO shift_production (machine_id,shift_date,shift_no,shift_start,shift_end,plan_qty)
            VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE plan_qty=?")
           ->execute([$mid,$date,$shiftNo,$cfgRow['start_time'],$cfgRow['end_time'],$plan,$plan]);
        echo json_encode(['success'=>true,'plan_qty'=>$plan], JSON_UNESCAPED_UNICODE);
        break;

    // ── POST: simpan produksi (output + reject) ───────────────
    case 'save_production':
        $mid          = (int)($input['machine_id']   ?? 0);
        $date         = $input['date']               ?? date('Y-m-d');
        $shiftNo      = (int)($input['shift_no']     ?? 0);
        $totalOut     = (int)($input['total_out']    ?? 0);
        $totalReject  = (int)($input['total_reject'] ?? 0);
        $operatorName = trim($input['operator_name'] ?? '');
        $notes        = trim($input['notes']         ?? '');
        $close        = (bool)($input['close']       ?? false);

        $cfg = $db->prepare("SELECT * FROM shift_config WHERE machine_id=? AND shift_no=? LIMIT 1");
        $cfg->execute([$mid, $shiftNo]);
        $cfgRow = $cfg->fetch();
        if (!$cfgRow){ echo json_encode(['success'=>false,'error'=>'Shift config tidak ada']); exit; }

        $goodCount = max(0, $totalOut - $totalReject);
        $quality   = $totalOut > 0 ? round($goodCount / $totalOut * 100, 1) : 0;

        $db->prepare("INSERT INTO shift_production
                (machine_id,shift_date,shift_no,shift_start,shift_end,plan_qty,
                 total_out,total_reject,good_count,operator_name,notes,is_closed,input_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())
            ON DUPLICATE KEY UPDATE
                total_out=VALUES(total_out),total_reject=VALUES(total_reject),
                good_count=VALUES(good_count),operator_name=VALUES(operator_name),
                notes=VALUES(notes),is_closed=VALUES(is_closed),input_at=NOW()")
           ->execute([$mid,$date,$shiftNo,$cfgRow['start_time'],$cfgRow['end_time'],
                      $cfgRow['plan_qty'],$totalOut,$totalReject,$goodCount,
                      $operatorName,$notes,$close?1:0]);

        echo json_encode(['success'=>true,'good_count'=>$goodCount,'quality'=>$quality], JSON_UNESCAPED_UNICODE);
        break;

    default:
        echo json_encode(['success'=>false,'error'=>'Action tidak dikenal']);
}

// ═════════════════════════════════════════════════════════════
// Helper: total menit istirahat aktif untuk satu shift
// ═════════════════════════════════════════════════════════════
function getTotalBreakMin(PDO $db, int $mid, int $sno): int
{
    $q = $db->prepare("
        SELECT SUM(TIME_TO_SEC(TIMEDIFF(end_time, start_time))/60) AS total
        FROM shift_breaks
        WHERE machine_id=? AND shift_no=? AND is_active=1
    ");
    $q->execute([$mid, $sno]);
    return (int)($q->fetchColumn() ?? 0);
}

// ═════════════════════════════════════════════════════════════
// Helper: hitung Availability (%) dengan pengurangan istirahat
//
//   Planned Production Time = durasi shift - total istirahat aktif
//   Availability = (run + standby) / Planned Production Time × 100
// ═════════════════════════════════════════════════════════════
function calcAvailability(PDO $db, int $mid, string $date,
                           string $start, string $end, int $breakMin = -1): float
{
    $dtStart = $date . ' ' . $start;
    $cross   = (strncmp($end, $start, 5) < 0) ? 86400 : 0;
    $dtEnd   = date('Y-m-d H:i:s', strtotime($date . ' ' . $end) + $cross);

    // Batasi ke sekarang agar tidak over-count shift yang belum selesai
    $now = date('Y-m-d H:i:s');
    if ($dtEnd > $now) $dtEnd = $now;
    if ($dtEnd <= $dtStart) return 0;

    // Ambil machine_states dalam rentang shift
    $rows = $db->prepare("
        SELECT state, recorded_at FROM machine_states
        WHERE machine_id=? AND recorded_at >= ? AND recorded_at <= ?
        ORDER BY recorded_at ASC
    ");
    $rows->execute([$mid, $dtStart, $dtEnd]);
    $states = $rows->fetchAll();
    if (empty($states)) return 0;

    // Hitung total menit istirahat jika belum di-pass
    if ($breakMin < 0) {
        $breakMin = getTotalBreakMin($db, $mid, 0); // fallback
    }

    // Durasi shift dikurangi istirahat = Planned Production Time
    $shiftSec   = strtotime($dtEnd) - strtotime($dtStart);
    $plannedSec = max(1, $shiftSec - ($breakMin * 60));

    // Hitung run + standby seconds
    $activeSec = 0;
    for ($i = 0; $i < count($states); $i++) {
        $tS = strtotime($states[$i]['recorded_at']);
        $tE = isset($states[$i+1])
            ? strtotime($states[$i+1]['recorded_at'])
            : strtotime($dtEnd);
        if ($states[$i]['state'] === 'run' || $states[$i]['state'] === 'standby')
            $activeSec += max(0, $tE - $tS);
    }

    // Jangan melebihi 100%
    return min(100, round($activeSec / $plannedSec * 100, 1));
}
