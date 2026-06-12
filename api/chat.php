<?php
require_once '../includes/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store');

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }

$pdo    = getDB();
$myId   = (int)$_SESSION['user_id'];
$myName = $_SESSION['username']  ?? '';
$myFull = $_SESSION['full_name'] ?? '';
$myRole = $_SESSION['role']      ?? 'operator';
session_write_close();

$action = $_GET['action'] ?? '';

// ── Fetch messages with reply data + deleted-for-me filter ───────────
function fetchMsgs($pdo, int $myId, string $where, array $params): array {
    $sql = "SELECT m.id, m.user_id, m.username, m.full_name, m.role,
                   m.message, m.tag, m.to_user_id, m.reply_to_id,
                   m.edited_at, m.deleted_for_all, m.is_forwarded,
                   m.media_url, m.media_type,
                   DATE_FORMAT(m.created_at,'%H:%i') AS time_str,
                   r.message   AS reply_msg,
                   r.username  AS reply_username,
                   r.full_name AS reply_full_name,
                   r.user_id   AS reply_user_id
            FROM chat_messages m
            LEFT JOIN chat_messages r ON r.id = m.reply_to_id
            LEFT JOIN chat_deleted_for_me dfm ON dfm.user_id=? AND dfm.msg_id=m.id
            WHERE dfm.msg_id IS NULL AND $where";
    $s = $pdo->prepare($sql);
    $s->execute(array_merge([$myId], $params));
    return $s->fetchAll(PDO::FETCH_ASSOC);
}

switch ($action) {

    // ── Init ─────────────────────────────────────────────────────────
    case 'init':
        $pdo->exec("CREATE TABLE IF NOT EXISTS chat_messages (
            id               INT AUTO_INCREMENT PRIMARY KEY,
            user_id          INT NOT NULL,
            username         VARCHAR(100) NOT NULL,
            full_name        VARCHAR(150) DEFAULT '',
            role             VARCHAR(50)  DEFAULT 'operator',
            to_user_id       INT          DEFAULT NULL,
            reply_to_id      INT          DEFAULT NULL,
            message          TEXT         NOT NULL,
            tag              VARCHAR(20)  DEFAULT 'info',
            media_url        VARCHAR(500) DEFAULT NULL,
            media_type       VARCHAR(50)  DEFAULT NULL,
            is_forwarded     TINYINT(1)   DEFAULT 0,
            forwarded_from_id INT         DEFAULT NULL,
            edited_at        DATETIME(3)  DEFAULT NULL,
            deleted_at       DATETIME(3)  DEFAULT NULL,
            deleted_for_all  TINYINT(1)   DEFAULT 0,
            created_at       DATETIME(3)  DEFAULT CURRENT_TIMESTAMP(3),
            INDEX idx_pub(to_user_id,id), INDEX idx_dm(user_id,to_user_id,id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS chat_typing (
            user_id    INT PRIMARY KEY, username VARCHAR(100) NOT NULL,
            full_name  VARCHAR(150) DEFAULT '', context VARCHAR(30) DEFAULT 'public',
            updated_at DATETIME(3) DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS chat_read (
            user_id INT NOT NULL, with_user_id INT NOT NULL,
            last_read_id INT NOT NULL DEFAULT 0, PRIMARY KEY(user_id,with_user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS chat_reactions (
            msg_id     INT NOT NULL, user_id INT NOT NULL, emoji VARCHAR(20) NOT NULL,
            updated_at DATETIME(3) DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
            PRIMARY KEY(msg_id,user_id), INDEX idx_msg(msg_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS chat_pins (
            id        INT AUTO_INCREMENT PRIMARY KEY,
            msg_id    INT NOT NULL, channel VARCHAR(60) NOT NULL,
            pinned_by INT NOT NULL,
            pinned_at DATETIME(3) DEFAULT CURRENT_TIMESTAMP(3),
            INDEX idx_ch(channel)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS chat_deleted_for_me (
            user_id INT NOT NULL, msg_id INT NOT NULL,
            PRIMARY KEY(user_id,msg_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Migrations — add missing columns
        $cols = $pdo->query("SHOW COLUMNS FROM chat_messages")->fetchAll(PDO::FETCH_COLUMN);
        $need = [
            'reply_to_id'      => 'INT DEFAULT NULL',
            'media_url'        => 'VARCHAR(500) DEFAULT NULL',
            'media_type'       => 'VARCHAR(50) DEFAULT NULL',
            'is_forwarded'     => 'TINYINT(1) DEFAULT 0',
            'forwarded_from_id'=> 'INT DEFAULT NULL',
            'edited_at'        => 'DATETIME(3) DEFAULT NULL',
            'deleted_at'       => 'DATETIME(3) DEFAULT NULL',
            'deleted_for_all'  => 'TINYINT(1) DEFAULT 0',
        ];
        foreach ($need as $col => $def) {
            if (!in_array($col, $cols, true)) {
                try { $pdo->exec("ALTER TABLE chat_messages ADD COLUMN `$col` $def"); } catch (Exception $e) {}
            }
        }

        $pub  = $pdo->query("SELECT COALESCE(MAX(id),0) FROM chat_messages WHERE to_user_id IS NULL")->fetchColumn();
        $priv = $pdo->query("SELECT COALESCE(MAX(id),0) FROM chat_messages WHERE to_user_id IS NOT NULL")->fetchColumn();
        $now  = (int)(microtime(true) * 1000);
        echo json_encode(['lastPubId' => (int)$pub, 'lastPrivId' => (int)$priv, 'nowTs' => $now]);
        break;

    // ── Public history / polling ──────────────────────────────────────
    case 'history_public':
        $rows = fetchMsgs($pdo, $myId, "m.to_user_id IS NULL ORDER BY m.id DESC LIMIT 60", []);
        echo json_encode(['messages' => array_reverse($rows)]);
        break;

    case 'get_public':
        $since = (int)($_GET['since'] ?? 0);
        $rows  = fetchMsgs($pdo, $myId, "m.id>? AND m.to_user_id IS NULL ORDER BY m.id ASC LIMIT 20", [$since]);
        echo json_encode(['messages' => $rows]);
        break;

    case 'send_public':
        $input    = json_decode(file_get_contents('php://input'), true) ?: [];
        $msg      = trim($input['message'] ?? '');
        $tag      = in_array($input['tag'] ?? '', ['info','report','error','resolved']) ? $input['tag'] : 'info';
        $replyId  = (int)($input['reply_to_id'] ?? 0) ?: null;
        $mediaUrl = $input['media_url'] ?? null;
        $mediaTyp = $input['media_type'] ?? null;
        if (!$msg && !$mediaUrl) { echo json_encode(['error' => 'Empty']); break; }
        $stmt = $pdo->prepare("INSERT INTO chat_messages (user_id,username,full_name,role,to_user_id,reply_to_id,message,tag,media_url,media_type) VALUES (?,?,?,?,NULL,?,?,?,?,?)");
        $stmt->execute([$myId, $myName, $myFull, $myRole, $replyId, htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'), $tag, $mediaUrl, $mediaTyp]);
        $newId = (int)$pdo->lastInsertId();
        $pdo->prepare("DELETE FROM chat_typing WHERE user_id=?")->execute([$myId]);
        echo json_encode(['success' => true, 'id' => $newId]);
        break;

    // ── Private history / polling ─────────────────────────────────────
    case 'history_private':
        $with = (int)($_GET['with'] ?? 0);
        if (!$with) { echo json_encode(['messages' => []]); break; }
        $rows = fetchMsgs($pdo, $myId,
            "m.to_user_id IS NOT NULL AND ((m.user_id=? AND m.to_user_id=?) OR (m.user_id=? AND m.to_user_id=?)) ORDER BY m.id DESC LIMIT 60",
            [$myId, $with, $with, $myId]);
        echo json_encode(['messages' => array_reverse($rows)]);
        break;

    case 'get_private':
        $with  = (int)($_GET['with']  ?? 0);
        $since = (int)($_GET['since'] ?? 0);
        if (!$with) { echo json_encode(['messages' => []]); break; }
        $rows = fetchMsgs($pdo, $myId,
            "m.id>? AND m.to_user_id IS NOT NULL AND ((m.user_id=? AND m.to_user_id=?) OR (m.user_id=? AND m.to_user_id=?)) ORDER BY m.id ASC LIMIT 20",
            [$since, $myId, $with, $with, $myId]);
        echo json_encode(['messages' => $rows]);
        break;

    case 'send_private':
        $input    = json_decode(file_get_contents('php://input'), true) ?: [];
        $msg      = trim($input['message'] ?? '');
        $toId     = (int)($input['to_user_id'] ?? 0);
        $replyId  = (int)($input['reply_to_id'] ?? 0) ?: null;
        $mediaUrl = $input['media_url'] ?? null;
        $mediaTyp = $input['media_type'] ?? null;
        if ((!$msg && !$mediaUrl) || !$toId) { echo json_encode(['error' => 'Invalid']); break; }
        $stmt = $pdo->prepare("INSERT INTO chat_messages (user_id,username,full_name,role,to_user_id,reply_to_id,message,tag,media_url,media_type) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$myId, $myName, $myFull, $myRole, $toId, $replyId, htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'), 'info', $mediaUrl, $mediaTyp]);
        $newId = (int)$pdo->lastInsertId();
        $pdo->prepare("DELETE FROM chat_typing WHERE user_id=?")->execute([$myId]);
        echo json_encode(['success' => true, 'id' => $newId]);
        break;

    // ── Reactions ─────────────────────────────────────────────────────
    case 'react':
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $msgId = (int)($input['msg_id'] ?? 0);
        $emoji = mb_substr(trim($input['emoji'] ?? ''), 0, 10);
        if (!$msgId || !$emoji) { echo json_encode(['error' => 'Invalid']); break; }
        $chk = $pdo->prepare("SELECT emoji FROM chat_reactions WHERE msg_id=? AND user_id=?");
        $chk->execute([$msgId, $myId]);
        $cur = $chk->fetchColumn();
        if ($cur === $emoji) {
            $pdo->prepare("DELETE FROM chat_reactions WHERE msg_id=? AND user_id=?")->execute([$msgId, $myId]);
        } else {
            $pdo->prepare("INSERT INTO chat_reactions (msg_id,user_id,emoji) VALUES (?,?,?) ON DUPLICATE KEY UPDATE emoji=VALUES(emoji),updated_at=NOW(3)")->execute([$msgId, $myId, $emoji]);
        }
        // Return full reaction state for this message
        $all = $pdo->prepare("SELECT emoji, user_id FROM chat_reactions WHERE msg_id=?");
        $all->execute([$msgId]);
        $map = [];
        foreach ($all->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $em = $r['emoji'];
            if (!isset($map[$em])) $map[$em] = ['emoji' => $em, 'count' => 0, 'mine' => false];
            $map[$em]['count']++;
            if ((int)$r['user_id'] === $myId) $map[$em]['mine'] = true;
        }
        echo json_encode(['ok' => true, 'msg_id' => $msgId, 'reactions' => array_values($map)]);
        break;

    // ── Delete message ────────────────────────────────────────────────
    case 'delete_msg':
        $input  = json_decode(file_get_contents('php://input'), true) ?: [];
        $msgId  = (int)($input['msg_id'] ?? 0);
        $forAll = !empty($input['for_all']);
        if (!$msgId) { echo json_encode(['error' => 'Invalid']); break; }
        $owner = $pdo->prepare("SELECT user_id FROM chat_messages WHERE id=?");
        $owner->execute([$msgId]);
        $ownerId = (int)$owner->fetchColumn();
        if ($forAll) {
            if ($ownerId !== $myId) { echo json_encode(['error' => 'Not yours']); break; }
            $pdo->prepare("UPDATE chat_messages SET deleted_at=NOW(3),deleted_for_all=1 WHERE id=?")->execute([$msgId]);
        } else {
            $pdo->prepare("INSERT IGNORE INTO chat_deleted_for_me (user_id,msg_id) VALUES (?,?)")->execute([$myId, $msgId]);
        }
        echo json_encode(['ok' => true, 'msg_id' => $msgId, 'for_all' => $forAll]);
        break;

    // ── Edit message ──────────────────────────────────────────────────
    case 'edit_msg':
        $input  = json_decode(file_get_contents('php://input'), true) ?: [];
        $msgId  = (int)($input['msg_id'] ?? 0);
        $newMsg = trim($input['message'] ?? '');
        if (!$msgId || !$newMsg) { echo json_encode(['error' => 'Invalid']); break; }
        $chk = $pdo->prepare("SELECT user_id, created_at FROM chat_messages WHERE id=?");
        $chk->execute([$msgId]);
        $row = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$row || (int)$row['user_id'] !== $myId) { echo json_encode(['error' => 'Not yours']); break; }
        if ((time() - strtotime($row['created_at'])) > 900) { echo json_encode(['error' => 'Time limit exceeded (15 min)']); break; }
        $safe = htmlspecialchars($newMsg, ENT_QUOTES, 'UTF-8');
        $pdo->prepare("UPDATE chat_messages SET message=?, edited_at=NOW(3) WHERE id=?")->execute([$safe, $msgId]);
        echo json_encode(['ok' => true, 'msg_id' => $msgId, 'message' => $safe]);
        break;

    // ── Pin message ───────────────────────────────────────────────────
    case 'pin_msg':
        $input   = json_decode(file_get_contents('php://input'), true) ?: [];
        $msgId   = (int)($input['msg_id'] ?? 0);
        $channel = $input['channel'] ?? 'public';
        $unpin   = !empty($input['unpin']);
        $pdo->prepare("DELETE FROM chat_pins WHERE channel=?")->execute([$channel]);
        if (!$unpin && $msgId) {
            $pdo->prepare("INSERT INTO chat_pins (msg_id,channel,pinned_by) VALUES (?,?,?)")->execute([$msgId, $channel, $myId]);
            $p = $pdo->prepare("SELECT p.msg_id,m.message,m.username,m.full_name,m.user_id FROM chat_pins p JOIN chat_messages m ON m.id=p.msg_id WHERE p.channel=? LIMIT 1");
            $p->execute([$channel]);
            echo json_encode(['ok' => true, 'pin' => $p->fetch(PDO::FETCH_ASSOC)]);
        } else {
            echo json_encode(['ok' => true, 'pin' => null]);
        }
        break;

    case 'get_pins':
        $channel = $_GET['channel'] ?? 'public';
        $stmt = $pdo->prepare("SELECT p.msg_id,m.message,m.username,m.full_name,m.user_id FROM chat_pins p JOIN chat_messages m ON m.id=p.msg_id WHERE p.channel=? ORDER BY p.id DESC LIMIT 1");
        $stmt->execute([$channel]);
        echo json_encode(['pin' => $stmt->fetch(PDO::FETCH_ASSOC) ?: null]);
        break;

    // ── Forward message ───────────────────────────────────────────────
    case 'forward_msg':
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $msgId = (int)($input['msg_id'] ?? 0);
        $toRaw = $input['to'] ?? 'public';
        $toId  = $toRaw === 'public' ? null : (int)$toRaw;
        if (!$msgId) { echo json_encode(['error' => 'Invalid']); break; }
        $orig = $pdo->prepare("SELECT message,tag,media_url,media_type FROM chat_messages WHERE id=? AND deleted_for_all=0");
        $orig->execute([$msgId]);
        $row = $orig->fetch(PDO::FETCH_ASSOC);
        if (!$row) { echo json_encode(['error' => 'Not found']); break; }
        $stmt = $pdo->prepare("INSERT INTO chat_messages (user_id,username,full_name,role,to_user_id,message,tag,media_url,media_type,is_forwarded,forwarded_from_id) VALUES (?,?,?,?,?,?,?,?,?,1,?)");
        $stmt->execute([$myId, $myName, $myFull, $myRole, $toId, $row['message'], $row['tag'], $row['media_url'], $row['media_type'], $msgId]);
        echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
        break;

    // ── Search ────────────────────────────────────────────────────────
    case 'search':
        $q       = trim($_GET['q'] ?? '');
        $channel = $_GET['channel'] ?? 'public';
        if (strlen($q) < 2) { echo json_encode(['messages' => [], 'query' => $q]); break; }
        $like = '%' . addcslashes($q, '%_\\') . '%';
        if ($channel === 'public') {
            $rows = fetchMsgs($pdo, $myId, "m.to_user_id IS NULL AND m.deleted_for_all=0 AND m.message LIKE ? ORDER BY m.id DESC LIMIT 30", [$like]);
        } else {
            $withId = (int)$channel;
            $rows = fetchMsgs($pdo, $myId,
                "m.deleted_for_all=0 AND m.message LIKE ? AND m.to_user_id IS NOT NULL AND ((m.user_id=? AND m.to_user_id=?) OR (m.user_id=? AND m.to_user_id=?)) ORDER BY m.id DESC LIMIT 30",
                [$like, $myId, $withId, $withId, $myId]);
        }
        echo json_encode(['messages' => array_reverse($rows), 'query' => $q]);
        break;

    // ── Mark read ─────────────────────────────────────────────────────
    case 'mark_read':
        $input  = json_decode(file_get_contents('php://input'), true) ?: [];
        $withId = (int)($input['with_user_id'] ?? 0);
        $lastId = (int)($input['last_id'] ?? 0);
        if (!$withId || !$lastId) { echo json_encode(['ok' => false]); break; }
        $pdo->prepare("INSERT INTO chat_read (user_id,with_user_id,last_read_id) VALUES (?,?,?) ON DUPLICATE KEY UPDATE last_read_id=GREATEST(last_read_id,VALUES(last_read_id))")->execute([$myId, $withId, $lastId]);
        echo json_encode(['ok' => true]);
        break;

    // ── Users list ────────────────────────────────────────────────────
    case 'users':
        $stmt = $pdo->prepare(
            "SELECT u.id,u.username,u.full_name,u.role,
                CASE WHEN u.last_login>=DATE_SUB(NOW(),INTERVAL 30 MINUTE) THEN 1 ELSE 0 END AS online,
                COALESCE(uc.cnt,0) AS unread
             FROM users u
             LEFT JOIN (
                 SELECT m.user_id, COUNT(*) AS cnt FROM chat_messages m
                 LEFT JOIN chat_read r ON r.user_id=? AND r.with_user_id=m.user_id
                 WHERE m.to_user_id=? AND m.id>COALESCE(r.last_read_id,0) AND m.deleted_for_all=0
                 GROUP BY m.user_id
             ) uc ON uc.user_id=u.id
             WHERE u.id!=? AND u.is_active=1
             ORDER BY uc.cnt DESC, online DESC, u.username ASC"
        );
        $stmt->execute([$myId, $myId, $myId]);
        echo json_encode(['users' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    // ── User profile ──────────────────────────────────────────────────
    case 'user_profile':
        $uid = (int)($_GET['uid'] ?? 0);
        if (!$uid) { echo json_encode(['error' => 'No uid']); break; }
        $stmt = $pdo->prepare(
            "SELECT id,username,full_name,role,
                CASE WHEN last_login>=DATE_SUB(NOW(),INTERVAL 30 MINUTE) THEN 1 ELSE 0 END AS online,
                DATE_FORMAT(last_login,'%d %b %Y %H:%i') AS last_login_str,
                DATE_FORMAT(created_at,'%d %b %Y') AS joined_str
             FROM users WHERE id=? AND is_active=1 LIMIT 1"
        );
        $stmt->execute([$uid]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$u) { echo json_encode(['error' => 'Not found']); break; }
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM chat_messages WHERE user_id=? AND to_user_id IS NULL AND deleted_for_all=0");
        $cnt->execute([$uid]);
        $u['msg_count'] = (int)$cnt->fetchColumn();
        echo json_encode(['user' => $u]);
        break;

    // ── Upload media (image / file) ───────────────────────────────────
    case 'upload':
        if (empty($_FILES['file'])) { echo json_encode(['error' => 'No file']); break; }
        $f = $_FILES['file'];
        if ($f['error'] !== UPLOAD_ERR_OK) { echo json_encode(['error' => 'Upload error']); break; }
        if ($f['size'] > 10 * 1024 * 1024) { echo json_encode(['error' => 'Max 10MB']); break; }
        $allowed = [
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'text/plain' => 'txt', 'application/zip' => 'zip',
        ];
        $mime = mime_content_type($f['tmp_name']);
        if (!isset($allowed[$mime])) { echo json_encode(['error' => 'File type not allowed']); break; }
        $dir = __DIR__ . '/../uploads/chat';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $name = 'c' . $myId . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($f['tmp_name'], "$dir/$name")) { echo json_encode(['error' => 'Save failed']); break; }
        $isImg = strpos($mime, 'image/') === 0;
        echo json_encode([
            'ok' => true,
            'url' => 'uploads/chat/' . $name,
            'media_type' => $isImg ? 'image' : 'file',
            'orig_name' => htmlspecialchars($f['name'], ENT_QUOTES, 'UTF-8'),
        ]);
        break;

    // ── Bulk reactions for a list of message ids ──────────────────────
    case 'reactions_bulk':
        $ids = array_filter(array_map('intval', explode(',', $_GET['ids'] ?? '')));
        if (!$ids) { echo json_encode(['reactions' => new stdClass()]); break; }
        $ids = array_slice($ids, 0, 120);
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT msg_id, emoji, user_id FROM chat_reactions WHERE msg_id IN ($ph)");
        $stmt->execute($ids);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $mid = (int)$r['msg_id']; $em = $r['emoji'];
            if (!isset($out[$mid])) $out[$mid] = [];
            if (!isset($out[$mid][$em])) $out[$mid][$em] = ['emoji' => $em, 'count' => 0, 'mine' => false];
            $out[$mid][$em]['count']++;
            if ((int)$r['user_id'] === $myId) $out[$mid][$em]['mine'] = true;
        }
        $flat = [];
        foreach ($out as $mid => $ems) $flat[$mid] = array_values($ems);
        echo json_encode(['reactions' => $flat ?: new stdClass()]);
        break;

    // ── Read position of the other user in a DM (for blue ticks) ──────
    case 'read_pos':
        $with = (int)($_GET['with'] ?? 0);
        if (!$with) { echo json_encode(['last_read_id' => 0]); break; }
        $stmt = $pdo->prepare("SELECT last_read_id FROM chat_read WHERE user_id=? AND with_user_id=?");
        $stmt->execute([$with, $myId]);
        echo json_encode(['last_read_id' => (int)$stmt->fetchColumn()]);
        break;

    // ── Typing ────────────────────────────────────────────────────────
    case 'typing':
        $input   = json_decode(file_get_contents('php://input'), true) ?: [];
        $context = $input['context'] ?? 'public';
        $pdo->prepare("INSERT INTO chat_typing (user_id,username,full_name,context) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE context=VALUES(context),updated_at=NOW(3)")->execute([$myId, $myName, $myFull, $context]);
        echo json_encode(['ok' => true]);
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
}
