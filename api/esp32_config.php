<?php
require_once '../includes/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) { http_response_code(401); respond(['success'=>false,'message'=>'Unauthorized']); }

$pdo    = getDB();
$action = $_REQUEST['action'] ?? '';

// ── Auto-migrate tables ───────────────────────────────────────────────────────
function initTables(PDO $pdo): void {
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS esp32_config (
        id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        device_id    VARCHAR(50)  NOT NULL,
        param_key    VARCHAR(100) NOT NULL,
        param_value  TEXT,
        param_type   ENUM('string','int','float','bool','password') DEFAULT 'string',
        param_group  VARCHAR(50)  DEFAULT 'general',
        label        VARCHAR(100) NOT NULL DEFAULT '',
        updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_dev_key (device_id, param_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("
    CREATE TABLE IF NOT EXISTS esp32_firmware (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        device_id   VARCHAR(50)  NOT NULL,
        version     VARCHAR(30)  NOT NULL,
        filename    VARCHAR(255) NOT NULL,
        file_size   INT UNSIGNED DEFAULT 0,
        notes       TEXT,
        uploaded_by INT UNSIGNED,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_active   TINYINT(1) DEFAULT 0,
        INDEX idx_dev (device_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
try {
    initTables($pdo);
    // Ensure correct collation (fixes tables created with wrong collation in previous versions)
    $pdo->exec("ALTER TABLE esp32_config   CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("ALTER TABLE esp32_firmware CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
} catch (PDOException $e) { /* non-fatal */ }

// ── Default parameter template per group ─────────────────────────────────────
function defaultParams(): array {
    return [
        ['group'=>'basic',   'key'=>'device_name',          'label'=>'Nama Device',             'type'=>'string',   'value'=>'ESP32-001'],
        ['group'=>'basic',   'key'=>'location',              'label'=>'Lokasi / Panel',           'type'=>'string',   'value'=>''],
        ['group'=>'basic',   'key'=>'description',           'label'=>'Deskripsi',               'type'=>'string',   'value'=>''],
        ['group'=>'network', 'key'=>'wifi_ssid',             'label'=>'WiFi SSID',               'type'=>'string',   'value'=>'ONE-YADIN'],
        ['group'=>'network', 'key'=>'wifi_password',         'label'=>'WiFi Password',           'type'=>'password', 'value'=>'Ramnay014#'],
        ['group'=>'network', 'key'=>'static_ip_enabled',     'label'=>'Gunakan Static IP',       'type'=>'bool',     'value'=>'0'],
        ['group'=>'network', 'key'=>'static_ip',             'label'=>'Static IP',               'type'=>'string',   'value'=>''],
        ['group'=>'network', 'key'=>'subnet_mask',           'label'=>'Subnet Mask',             'type'=>'string',   'value'=>'255.255.255.0'],
        ['group'=>'network', 'key'=>'gateway',               'label'=>'Gateway',                 'type'=>'string',   'value'=>''],
        ['group'=>'network', 'key'=>'dns',                   'label'=>'DNS Server',              'type'=>'string',   'value'=>'8.8.8.8'],
        ['group'=>'mqtt',    'key'=>'mqtt_broker',           'label'=>'MQTT Broker (IP/Host)',   'type'=>'string',   'value'=>'192.168.183.143'],
        ['group'=>'mqtt',    'key'=>'mqtt_port',             'label'=>'MQTT Port',               'type'=>'int',      'value'=>'1883'],
        ['group'=>'mqtt',    'key'=>'mqtt_user',             'label'=>'MQTT Username',           'type'=>'string',   'value'=>''],
        ['group'=>'mqtt',    'key'=>'mqtt_password',         'label'=>'MQTT Password',           'type'=>'password', 'value'=>''],
        ['group'=>'mqtt',    'key'=>'mqtt_topic_prefix',     'label'=>'Topic Prefix',            'type'=>'string',   'value'=>'yadin/sensor'],
        ['group'=>'mqtt',    'key'=>'mqtt_qos',              'label'=>'QoS Level (0/1/2)',       'type'=>'int',      'value'=>'0'],
        ['group'=>'mqtt',    'key'=>'mqtt_keepalive_s',      'label'=>'Keep-alive (detik)',      'type'=>'int',      'value'=>'60'],
        ['group'=>'sensor',  'key'=>'sampling_interval_ms',  'label'=>'Interval Sampling (ms)',  'type'=>'int',      'value'=>'500'],
        ['group'=>'sensor',  'key'=>'send_interval_s',       'label'=>'Interval Kirim (detik)',  'type'=>'int',      'value'=>'5'],
        ['group'=>'sensor',  'key'=>'vibration_warning',     'label'=>'Vibration Warning (mm/s)','type'=>'float',    'value'=>'2.8'],
        ['group'=>'sensor',  'key'=>'vibration_critical',    'label'=>'Vibration Critical (mm/s)','type'=>'float',   'value'=>'7.1'],
        ['group'=>'sensor',  'key'=>'temp_warning_c',        'label'=>'Suhu Warning (°C)',       'type'=>'float',    'value'=>'70'],
        ['group'=>'sensor',  'key'=>'temp_critical_c',       'label'=>'Suhu Critical (°C)',      'type'=>'float',    'value'=>'85'],
        ['group'=>'sensor',  'key'=>'humidity_warning_pct',  'label'=>'Humidity Warning (%)',    'type'=>'float',    'value'=>'85'],
        ['group'=>'ota',     'key'=>'ota_enabled',           'label'=>'OTA Aktif',               'type'=>'bool',     'value'=>'1'],
        ['group'=>'ota',     'key'=>'ota_check_interval_min','label'=>'Cek OTA tiap (menit)',    'type'=>'int',      'value'=>'60'],
        ['group'=>'ota',     'key'=>'ota_url',               'label'=>'OTA URL (opsional)',      'type'=>'string',   'value'=>''],
    ];
}

// ── Global error handler — ensure JSON is always returned ────────────────────
set_exception_handler(function(Throwable $e) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
    exit;
});

// ── Route actions ─────────────────────────────────────────────────────────────
switch ($action) {

    // ── List devices ─────────────────────────────────────────────────────────
    case 'list_devices':
        $rows = $pdo->query("
            SELECT e.id, e.device_id, e.ip_address, e.mac_address,
                   e.firmware_version, e.last_seen, e.status,
                   m.name AS machine_name,
                   m.id   AS machine_id,
                   (SELECT COUNT(*) FROM esp32_config c WHERE c.device_id=e.device_id) AS cfg_count
            FROM esp32_devices e
            LEFT JOIN machines m ON m.id=e.machine_id
            ORDER BY e.device_id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
        respond(['success'=>true,'devices'=>$rows]);

    // ── Get config for one device ─────────────────────────────────────────────
    case 'get_config':
        $devId = $_GET['device_id'] ?? '';
        if (!$devId) respond(['success'=>false,'message'=>'device_id required'],400);

        // Fetch stored values
        $s = $pdo->prepare("SELECT param_key,param_value,param_type,param_group,label FROM esp32_config WHERE device_id=? ORDER BY id ASC");
        $s->execute([$devId]);
        $stored = [];
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) $stored[$r['param_key']] = $r;

        // Merge with defaults (fill missing params)
        $params = [];
        foreach (defaultParams() as $d) {
            $key  = $d['key'];
            $row  = $stored[$key] ?? null;
            $params[] = [
                'param_group' => $row['param_group'] ?? $d['group'],
                'param_key'   => $key,
                'label'       => $row['label']        ?? $d['label'],
                'param_type'  => $row['param_type']   ?? $d['type'],
                'param_value' => $row !== null         ? $row['param_value'] : $d['value'],
            ];
        }

        // Custom params (not in default list)
        $defaults = array_column(defaultParams(), null, 'key');
        foreach ($stored as $key => $r) {
            if (!isset($defaults[$key])) {
                $params[] = [
                    'param_group' => $r['param_group'],
                    'param_key'   => $key,
                    'label'       => $r['label'],
                    'param_type'  => $r['param_type'],
                    'param_value' => $r['param_value'],
                ];
            }
        }
        respond(['success'=>true,'params'=>$params]);

    // ── Save config (batch upsert) ────────────────────────────────────────────
    case 'save_config':
        $body  = bodyJson();
        $devId = $body['device_id'] ?? '';
        $items = $body['params']    ?? [];
        if (!$devId || !is_array($items)) respond(['success'=>false,'message'=>'Invalid payload'],400);

        $stmt = $pdo->prepare("
            INSERT INTO esp32_config (device_id,param_key,param_value,param_type,param_group,label)
            VALUES (?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
                param_value=VALUES(param_value),
                param_type=VALUES(param_type),
                param_group=VALUES(param_group),
                label=VALUES(label),
                updated_at=NOW()
        ");
        $pdo->beginTransaction();
        foreach ($items as $item) {
            $stmt->execute([
                $devId,
                $item['param_key']   ?? '',
                $item['param_value'] ?? '',
                $item['param_type']  ?? 'string',
                $item['param_group'] ?? 'general',
                $item['label']       ?? '',
            ]);
        }
        $pdo->commit();
        respond(['success'=>true,'message'=>'Konfigurasi tersimpan']);

    // ── Add new device ────────────────────────────────────────────────────────
    case 'add_device':
        $body   = bodyJson();
        $devId  = trim($body['device_id']  ?? '');
        $machId = $body['machine_id'] !== '' ? (int)($body['machine_id'] ?? 0) : null;
        if (!$devId) respond(['success'=>false,'message'=>'device_id wajib diisi'],400);

        // Check duplicate
        $chk = $pdo->prepare("SELECT id FROM esp32_devices WHERE device_id=?");
        $chk->execute([$devId]);
        if ($chk->fetch()) respond(['success'=>false,'message'=>'Device ID sudah ada'],409);

        $ins = $pdo->prepare("INSERT INTO esp32_devices (device_id,machine_id,status) VALUES (?,?,'offline')");
        $ins->execute([$devId, $machId ?: null]);

        // Seed default config
        $seed = $pdo->prepare("
            INSERT IGNORE INTO esp32_config (device_id,param_key,param_value,param_type,param_group,label)
            VALUES (?,?,?,?,?,?)
        ");
        foreach (defaultParams() as $d) {
            $val = ($d['key']==='device_name') ? $devId : $d['value'];
            $seed->execute([$devId,$d['key'],$val,$d['type'],$d['group'],$d['label']]);
        }
        respond(['success'=>true,'message'=>'Device ditambahkan','device_id'=>$devId]);

    // ── Delete device ─────────────────────────────────────────────────────────
    case 'delete_device':
        $body  = bodyJson();
        $devId = $body['device_id'] ?? '';
        if (!$devId) respond(['success'=>false,'message'=>'device_id required'],400);
        $pdo->prepare("DELETE FROM esp32_config   WHERE device_id=?")->execute([$devId]);
        $pdo->prepare("DELETE FROM esp32_firmware WHERE device_id=?")->execute([$devId]);
        $pdo->prepare("DELETE FROM esp32_devices  WHERE device_id=?")->execute([$devId]);
        respond(['success'=>true,'message'=>'Device dihapus']);

    // ── List machines (for dropdown) ──────────────────────────────────────────
    case 'list_machines':
        $rows = $pdo->query("SELECT id, name FROM machines ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
        respond(['success'=>true,'machines'=>$rows]);

    // ── Generate config (JSON or C header) ───────────────────────────────────
    case 'generate_config':
        $devId  = $_GET['device_id'] ?? '';
        $format = $_GET['format']    ?? 'json';
        if (!$devId) respond(['success'=>false,'message'=>'device_id required'],400);

        $s = $pdo->prepare("SELECT param_key,param_value,param_type,param_group FROM esp32_config WHERE device_id=?");
        $s->execute([$devId]);
        $rows = $s->fetchAll(PDO::FETCH_ASSOC);

        // Build key→value map for topic auto-generation
        $cfgMap = [];
        foreach ($rows as $r) $cfgMap[$r['param_key']] = $r['param_value'];
        $topicPrefix = rtrim($cfgMap['mqtt_topic_prefix'] ?? 'yadin/sensor', '/');
        $mqttTopic   = $topicPrefix . '/' . $devId . '/data';

        if ($format === 'header') {
            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Disposition: attachment; filename="config_'.preg_replace('/\W/','_',$devId).'.h"');
            echo "// ============================================================\n";
            echo "//  Auto-generated by OEE Monitoring System\n";
            echo "//  Device  : $devId\n";
            echo "//  Generated: " . date('Y-m-d H:i:s') . "\n";
            echo "//  DO NOT EDIT MANUALLY — regenerate from web panel\n";
            echo "// ============================================================\n";
            echo "#pragma once\n";

            $grp = '';
            foreach ($rows as $r) {
                if ($r['param_group'] !== $grp) {
                    $grp = $r['param_group'];
                    echo "\n// ── " . strtoupper($grp) . " " . str_repeat('─', max(0, 48 - strlen($grp))) . "\n";
                }
                // Skip — replaced by computed values below
                if ($r['param_key'] === 'mqtt_topic_prefix') continue;
                // ota_url auto-filled below if empty
                if ($r['param_key'] === 'ota_url') continue;

                $key = 'CFG_' . strtoupper($r['param_key']);
                $val = $r['param_value'];
                if ($r['param_type'] === 'bool') {
                    echo "#define {$key} " . ($val ? 'true' : 'false') . "\n";
                } elseif (in_array($r['param_type'], ['int', 'float'])) {
                    echo "#define {$key} " . $val . "\n";
                } else {
                    echo "#define {$key} \"" . addslashes($val) . "\"\n";
                }
            }
            // Auto-computed topic
            echo "\n// ── MQTT TOPIC (auto: prefix/device_id/data) " . str_repeat('─', 4) . "\n";
            echo "#define CFG_MQTT_TOPIC \"" . addslashes($mqttTopic) . "\"\n";

            // OTA URL — auto-fill dengan endpoint server jika kosong
            $storedOtaUrl = $cfgMap['ota_url'] ?? '';
            $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ? 'https' : 'http')
                     . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
                     . '/oee/api/esp32_ota.php?device=' . urlencode($devId);
            $otaUrl = $storedOtaUrl ?: $baseUrl;
            echo "\n// ── OTA URL (auto: server OEE → firmware aktif device ini) ─\n";
            echo "// Format: http://<server>/oee/api/esp32_ota.php?device=<ID>\n";
            echo "#define CFG_OTA_URL \"" . addslashes($otaUrl) . "\"\n";
            exit;
        }

        // JSON format
        $out = ['device_id' => $devId, 'generated_at' => date('c'), 'config' => []];
        foreach ($rows as $r) {
            if ($r['param_key'] === 'mqtt_topic_prefix') continue; // replaced by full topic below
            $v = $r['param_value'];
            if ($r['param_type'] === 'bool')        $v = (bool)(int)$v;
            elseif ($r['param_type'] === 'int')     $v = (int)$v;
            elseif ($r['param_type'] === 'float')   $v = (float)$v;
            $out['config'][$r['param_group']][$r['param_key']] = $v;
        }
        // Auto-inject full topic
        $out['config']['mqtt']['mqtt_topic'] = $mqttTopic;
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="config_'.preg_replace('/\W/','_',$devId).'.json"');
        echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;

    // ── Upload firmware ───────────────────────────────────────────────────────
    case 'upload_firmware':
        if (!isset($_FILES['firmware'])) respond(['success'=>false,'message'=>'No file'],400);
        $devId   = $_POST['device_id'] ?? '';
        $version = trim($_POST['version'] ?? '');
        $notes   = trim($_POST['notes']   ?? '');
        if (!$devId || !$version) respond(['success'=>false,'message'=>'device_id & version wajib'],400);

        $file = $_FILES['firmware'];
        if ($file['error'] !== UPLOAD_ERR_OK) respond(['success'=>false,'message'=>'Upload error: '.$file['error']],400);

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'bin') respond(['success'=>false,'message'=>'Hanya file .bin yang diizinkan'],400);
        if ($file['size'] > 4 * 1024 * 1024) respond(['success'=>false,'message'=>'Maks 4 MB'],400);

        $dir  = __DIR__ . '/../uploads/firmware/';
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $safe = preg_replace('/[^a-z0-9_\-\.]/i','_',$devId).'_'.preg_replace('/[^a-z0-9_\-\.]/i','_',$version).'.bin';
        move_uploaded_file($file['tmp_name'], $dir.$safe);

        $ins = $pdo->prepare("INSERT INTO esp32_firmware (device_id,version,filename,file_size,notes,uploaded_by) VALUES (?,?,?,?,?,?)");
        $ins->execute([$devId,$version,$safe,$file['size'],$notes,(int)$_SESSION['user_id']]);
        respond(['success'=>true,'message'=>'Firmware ter-upload','filename'=>$safe]);

    // ── List firmware ─────────────────────────────────────────────────────────
    case 'list_firmware':
        $devId = $_GET['device_id'] ?? '';
        $s = $pdo->prepare("SELECT f.*,u.full_name AS uploader FROM esp32_firmware f LEFT JOIN users u ON u.id=f.uploaded_by WHERE f.device_id=? ORDER BY f.id DESC");
        $s->execute([$devId]);
        respond(['success'=>true,'firmware'=>$s->fetchAll(PDO::FETCH_ASSOC)]);

    // ── Activate firmware (sets is_active=1, rest=0) ──────────────────────────
    case 'activate_firmware':
        $body = bodyJson();
        $devId = $body['device_id'] ?? '';
        $fwId  = (int)($body['firmware_id'] ?? 0);
        if (!$devId || !$fwId) respond(['success'=>false,'message'=>'Invalid'],400);
        $pdo->prepare("UPDATE esp32_firmware SET is_active=0 WHERE device_id=?")->execute([$devId]);
        $pdo->prepare("UPDATE esp32_firmware SET is_active=1 WHERE id=? AND device_id=?")->execute([$fwId,$devId]);
        respond(['success'=>true,'message'=>'Firmware diaktifkan — device akan update saat cek OTA berikutnya']);

    // ── Delete firmware ───────────────────────────────────────────────────────
    case 'delete_firmware':
        $body = bodyJson();
        $fwId = (int)($body['firmware_id'] ?? 0);
        $s = $pdo->prepare("SELECT filename FROM esp32_firmware WHERE id=?");
        $s->execute([$fwId]);
        $fw = $s->fetch();
        if ($fw) {
            $path = __DIR__.'/../uploads/firmware/'.$fw['filename'];
            if (file_exists($path)) unlink($path);
            $pdo->prepare("DELETE FROM esp32_firmware WHERE id=?")->execute([$fwId]);
        }
        respond(['success'=>true,'message'=>'Firmware dihapus']);

    // ── Add custom param ──────────────────────────────────────────────────────
    case 'add_param':
        $body = bodyJson();
        $devId = $body['device_id'] ?? '';
        $key   = trim($body['param_key'] ?? '');
        if (!$devId || !$key) respond(['success'=>false,'message'=>'device_id & param_key wajib'],400);
        $key = preg_replace('/[^a-z0-9_]/i','_',$key);
        $stmt = $pdo->prepare("INSERT IGNORE INTO esp32_config (device_id,param_key,param_value,param_type,param_group,label) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$devId,$key,$body['param_value']??'',$body['param_type']??'string',$body['param_group']??'custom',$body['label']??$key]);
        respond(['success'=>true,'param_key'=>$key]);

    // ── Apply shared defaults to all/specific devices ────────────────────────
    // Pushes wifi_ssid, wifi_password, mqtt_broker, mqtt_port, mqtt_topic_prefix
    // to every device — useful after changing the network/broker globally.
    case 'apply_defaults':
        $body   = bodyJson();
        $target = $body['device_id'] ?? ''; // empty = all devices
        $sharedKeys = ['wifi_ssid','wifi_password','mqtt_broker','mqtt_port',
                       'mqtt_topic_prefix','mqtt_qos','mqtt_keepalive_s',
                       'sampling_interval_ms'];
        $defMap = array_column(defaultParams(), null, 'key');

        // Overrides from request body (optional — caller can pass custom values)
        $overrides = $body['params'] ?? [];
        foreach ($overrides as $k => $v) {
            if (isset($defMap[$k])) $defMap[$k]['value'] = $v;
        }

        // Get target device list
        if ($target) {
            $devIds = [$target];
        } else {
            $devIds = $pdo->query("SELECT device_id FROM esp32_devices")->fetchAll(PDO::FETCH_COLUMN);
        }

        $stmt = $pdo->prepare("
            INSERT INTO esp32_config (device_id,param_key,param_value,param_type,param_group,label)
            VALUES (?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE param_value=VALUES(param_value), updated_at=NOW()
        ");
        $pdo->beginTransaction();
        $count = 0;
        foreach ($devIds as $did) {
            foreach ($sharedKeys as $k) {
                if (!isset($defMap[$k])) continue;
                $d = $defMap[$k];
                $stmt->execute([$did, $k, $d['value'], $d['type'], $d['group'], $d['label']]);
                $count++;
            }
        }
        $pdo->commit();
        respond(['success'=>true,'message'=>"$count parameter diperbarui ke " . count($devIds) . " device"]);

    // ── Delete custom param ───────────────────────────────────────────────────
    case 'delete_param':
        $body  = bodyJson();
        $devId = $body['device_id'] ?? '';
        $key   = $body['param_key'] ?? '';
        $pdo->prepare("DELETE FROM esp32_config WHERE device_id=? AND param_key=?")->execute([$devId,$key]);
        respond(['success'=>true]);

    default:
        respond(['success'=>false,'message'=>'Unknown action'],400);
}
