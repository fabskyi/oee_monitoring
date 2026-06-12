<?php
require_once '../includes/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) { http_response_code(401); exit; }

$myId      = (int)$_SESSION['user_id'];
$sincePub  = (int)($_GET['pub']  ?? 0);
$sincePriv = (int)($_GET['priv'] ?? 0);

// Release session lock so other page requests are never blocked by this SSE
session_write_close();

// Disable all output buffering — data must reach browser immediately
while (ob_get_level()) ob_end_clean();
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', false);

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-store');
header('X-Accel-Buffering: no');
header('X-Content-Type-Options: nosniff');

set_time_limit(30);
ignore_user_abort(false);

$pdo   = getDB();
$start = time();
$tick  = 0;

$MSGCOLS = "m.id,m.user_id,m.username,m.full_name,m.role,m.message,m.tag,m.to_user_id,m.reply_to_id,
            m.edited_at,m.deleted_for_all,m.is_forwarded,m.media_url,m.media_type,
            DATE_FORMAT(m.created_at,'%H:%i') AS time_str,
            r.message AS reply_msg,r.username AS reply_username,r.full_name AS reply_full_name,r.user_id AS reply_user_id";

// Pre-compile statements once (reuse across iterations)
$stmtPub  = $pdo->prepare("SELECT $MSGCOLS FROM chat_messages m LEFT JOIN chat_messages r ON r.id=m.reply_to_id WHERE m.id>? AND m.to_user_id IS NULL ORDER BY m.id ASC LIMIT 10");
$stmtPriv = $pdo->prepare("SELECT $MSGCOLS FROM chat_messages m LEFT JOIN chat_messages r ON r.id=m.reply_to_id WHERE m.id>? AND m.to_user_id=? ORDER BY m.id ASC LIMIT 10");
$stmtTyp  = $pdo->prepare("SELECT user_id,username,full_name,context FROM chat_typing WHERE user_id!=? AND updated_at>=DATE_SUB(NOW(3),INTERVAL 3 SECOND)");
$stmtUnrd = $pdo->prepare("SELECT m.user_id, COUNT(*) AS cnt FROM chat_messages m LEFT JOIN chat_read r ON r.user_id=? AND r.with_user_id=m.user_id WHERE m.to_user_id=? AND m.user_id!=? AND m.id>COALESCE(r.last_read_id,0) AND m.deleted_for_all=0 GROUP BY m.user_id");
// Messages edited or deleted-for-all in the last 4 seconds
$stmtMod  = $pdo->prepare("SELECT id,message,edited_at,deleted_for_all FROM chat_messages WHERE (edited_at>=DATE_SUB(NOW(3),INTERVAL 4 SECOND)) OR (deleted_for_all=1 AND deleted_at>=DATE_SUB(NOW(3),INTERVAL 4 SECOND)) LIMIT 30");
// Reactions changed in the last 4 seconds → resend full state per affected message
$stmtRxC  = $pdo->prepare("SELECT DISTINCT msg_id FROM chat_reactions WHERE updated_at>=DATE_SUB(NOW(3),INTERVAL 4 SECOND) LIMIT 30");
$stmtRxA  = $pdo->prepare("SELECT emoji,user_id FROM chat_reactions WHERE msg_id=?");
// Current public pin
$stmtPin  = $pdo->prepare("SELECT p.msg_id,m.message,m.username,m.full_name,m.user_id FROM chat_pins p JOIN chat_messages m ON m.id=p.msg_id WHERE p.channel='public' ORDER BY p.id DESC LIMIT 1");
// Read positions of others on conversations with me (for blue ticks)
$stmtRPos = $pdo->prepare("SELECT user_id, last_read_id FROM chat_read WHERE with_user_id=?");

function sse(string $event, $data): void {
    echo "event:{$event}\ndata:" . json_encode($data) . "\n\n";
    flush();
}

sse('ping', time());

$lastPinJson  = null;
$lastRPosJson = null;

while (true) {
    if (connection_aborted()) break;
    if (time() - $start >= 27) { sse('reconnect', ['pub' => $sincePub, 'priv' => $sincePriv]); break; }

    // ── New public messages ───────────────────────────────────
    $stmtPub->execute([$sincePub]);
    $rows = $stmtPub->fetchAll(PDO::FETCH_ASSOC);
    if ($rows) { $sincePub = (int)end($rows)['id']; sse('public', $rows); }

    // ── New private messages TO me ────────────────────────────
    $stmtPriv->execute([$sincePriv, $myId]);
    $priv = $stmtPriv->fetchAll(PDO::FETCH_ASSOC);
    if ($priv) { $sincePriv = (int)end($priv)['id']; sse('private', $priv); }

    // ── Typing (every 3rd tick) ───────────────────────────────
    if ($tick % 3 === 0) {
        $stmtTyp->execute([$myId]);
        sse('typing', $stmtTyp->fetchAll(PDO::FETCH_ASSOC));
    }

    // ── Edits / deletes / reactions (every 5th tick ≈ 1s) ────
    if ($tick % 5 === 0) {
        $stmtMod->execute();
        $mods = $stmtMod->fetchAll(PDO::FETCH_ASSOC);
        if ($mods) sse('modified', $mods);

        $stmtRxC->execute();
        $changed = $stmtRxC->fetchAll(PDO::FETCH_COLUMN);
        if ($changed) {
            $rx = [];
            foreach ($changed as $mid) {
                $stmtRxA->execute([$mid]);
                $map = [];
                foreach ($stmtRxA->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $em = $r['emoji'];
                    if (!isset($map[$em])) $map[$em] = ['emoji' => $em, 'count' => 0, 'mine' => false];
                    $map[$em]['count']++;
                    if ((int)$r['user_id'] === $myId) $map[$em]['mine'] = true;
                }
                $rx[(int)$mid] = array_values($map);
            }
            sse('reactions', $rx);
        }
    }

    // ── Pin + read positions (every 10th tick ≈ 2s, only on change) ──
    if ($tick % 10 === 0) {
        $stmtPin->execute();
        $pin = $stmtPin->fetch(PDO::FETCH_ASSOC) ?: null;
        $pj  = json_encode($pin);
        if ($pj !== $lastPinJson) { $lastPinJson = $pj; sse('pin', $pin); }

        $stmtRPos->execute([$myId]);
        $rposArr = $stmtRPos->fetchAll(PDO::FETCH_ASSOC);
        $rpos = new stdClass();
        foreach ($rposArr as $r) $rpos->{(int)$r['user_id']} = (int)$r['last_read_id'];
        $rj = json_encode($rpos);
        if ($rj !== $lastRPosJson) { $lastRPosJson = $rj; sse('readpos', $rpos); }

        $stmtUnrd->execute([$myId, $myId, $myId]);
        $map = new stdClass();
        foreach ($stmtUnrd->fetchAll(PDO::FETCH_ASSOC) as $r) $map->{(int)$r['user_id']} = (int)$r['cnt'];
        sse('unread', $map);
    }

    if ($tick % 25 === 0) sse('ping', time());

    $tick++;
    usleep(200000); // 200ms
}
