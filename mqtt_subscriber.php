<?php
// ============================================================
//  MQTT Subscriber — Simpan data ESP32 ke MySQL
//  Jalankan di CMD: C:\xampp\php\php.exe mqtt_subscriber.php
//  Auto-reconnect kalau putus dari broker
// ============================================================
set_time_limit(0);
ini_set('default_socket_timeout', -1);

define('MQTT_HOST',       '192.168.183.143');
define('MQTT_PORT',       1883);
define('MQTT_TOPIC',      'yadin/sensor/+/data');
define('MQTT_KEEPALIVE',  60);    // detik keepalive MQTT
define('RECONNECT_DELAY', 5);     // detik tunggu sebelum reconnect
define('DB_HOST',         'localhost');
define('DB_USER',         'root');
define('DB_PASS',         '');
define('DB_NAME',         'oee_monitoring');

// ── Logging helper ────────────────────────────────────────────
function logMsg(string $msg): void {
    $ts = date('[Y-m-d H:i:s]');
    echo "$ts $msg\n";
    flush();
}

// ── Koneksi MySQL dengan auto-reconnect ───────────────────────
function getMySQL(): mysqli {
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($mysqli->connect_error) {
        logMsg("MySQL: " . $mysqli->connect_error);
        exit(1);
    }
    $mysqli->set_charset('utf8mb4');
    logMsg("MySQL connected");
    return $mysqli;
}

// ── MQTT Client ───────────────────────────────────────────────
class SimpleMQTT {
    private $socket  = null;
    private $msgid   = 1;
    private $lastPing = 0;
    public  $connected = false;

    public function connect(string $host, int $port, string $clientId, int $timeout = 10): bool {
        $this->socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if (!$this->socket) {
            logMsg("Socket: $errstr ($errno)");
            return false;
        }
        stream_set_timeout($this->socket, 0, 5000);    // 5ms non-blocking read
        stream_set_blocking($this->socket, false);

        // MQTT CONNECT packet
        $payload = $this->encStr('MQTT') . chr(4) . chr(2)
                 . chr(0) . chr(MQTT_KEEPALIVE)
                 . $this->encStr($clientId);
        fwrite($this->socket, chr(0x10) . $this->encLen(strlen($payload)) . $payload);

        // Tunggu CONNACK (max 5 detik)
        $deadline = time() + 5;
        $resp = '';
        while (strlen($resp) < 4 && time() < $deadline) {
            $chunk = fread($this->socket, 4);
            if ($chunk) $resp .= $chunk;
            usleep(5000);
        }
        if (strlen($resp) >= 4 && ord($resp[0]) === 0x20 && ord($resp[3]) === 0) {
            $this->connected = true;
            $this->lastPing  = time();
            logMsg("MQTT connected → $host:$port");
            return true;
        }
        logMsg("CONNACK gagal (hex: " . bin2hex(substr($resp,0,4)) . ")");
        fclose($this->socket);
        $this->socket = null;
        return false;
    }

    public function subscribe(string $topic): void {
        $mid     = $this->msgid++;
        $payload = chr($mid >> 8) . chr($mid & 0xFF) . $this->encStr($topic) . chr(0);
        fwrite($this->socket, chr(0x82) . $this->encLen(strlen($payload)) . $payload);
        usleep(20000);
        @fread($this->socket, 10); // SUBACK
        logMsg("Subscribed: $topic");
    }

    public function loop(callable $cb): void {
        logMsg("Menunggu pesan MQTT...\n");
        while ($this->connected) {
            // Kirim PINGREQ setiap KEEPALIVE/2 detik
            if (time() - $this->lastPing >= (MQTT_KEEPALIVE / 2)) {
                if (!$this->ping()) {
                    logMsg("PING gagal — putus");
                    $this->connected = false;
                    break;
                }
                $this->lastPing = time();
            }

            $byte = @fread($this->socket, 1);
            if ($byte === false || $byte === '') {
                usleep(2000);
                continue;
            }
            if (feof($this->socket)) {
                logMsg("Socket EOF — putus");
                $this->connected = false;
                break;
            }

            $cmd = ord($byte);

            // Decode remaining length
            $mult = 1; $remain = 0;
            do {
                $b = @fread($this->socket, 1);
                if ($b === false || $b === '') { usleep(1000); continue 2; }
                $b       = ord($b);
                $remain += ($b & 127) * $mult;
                $mult   *= 128;
            } while ($b & 128);

            if ($remain === 0) continue;
            $data = '';
            $left = $remain;
            while ($left > 0) {
                $chunk = @fread($this->socket, $left);
                if ($chunk === false || $chunk === '') { usleep(500); continue; }
                $data .= $chunk;
                $left -= strlen($chunk);
            }

            // PUBLISH
            if (($cmd & 0xF0) === 0x30) {
                $tLen    = (ord($data[0]) << 8) | ord($data[1]);
                $topic   = substr($data, 2, $tLen);
                $payload = substr($data, 2 + $tLen);
                $cb($topic, $payload);
            }
            // PINGRESP
            if ($cmd === 0xD0) { /* ok */ }
        }
        if ($this->socket) { fclose($this->socket); $this->socket = null; }
    }

    public function ping(): bool {
        $res = @fwrite($this->socket, chr(0xC0) . chr(0x00));
        return ($res !== false && $res > 0);
    }

    private function encStr(string $s): string {
        $l = strlen($s);
        return chr($l >> 8) . chr($l & 0xFF) . $s;
    }
    private function encLen(int $len): string {
        $out = '';
        do {
            $b = $len % 128; $len = intdiv($len, 128);
            if ($len > 0) $b |= 0x80;
            $out .= chr($b);
        } while ($len > 0);
        return $out;
    }
}

// ── Simpan ke MySQL ───────────────────────────────────────────
function saveToMySQL(mysqli $db, array $data): bool {
    // Auto-reconnect MySQL kalau koneksi putus
    if (!$db->ping()) {
        logMsg("MySQL ping gagal, reconnect...");
        $db->connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    }

    $mid = isset($data['machine_id']) ? (int)$data['machine_id'] : 1;

    // Pastikan machine_id ada di tabel machines (hindari FK error)
    $check = $db->query("SELECT id FROM machines WHERE id=$mid");
    if (!$check || $check->num_rows === 0) {
        logMsg("Machine $mid tidak ada di DB, data dilewati. Gunakan machine_id yang valid.");
        return false;
    }

    $stmt = $db->prepare("
        INSERT INTO sensor_readings
            (machine_id, v_r, v_s, v_t, a_r, a_s, a_t,
             f_r, f_s, f_t, e_r, e_s, e_t,
             temp_panel, hum_panel, source, recorded_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    if (!$stmt) { logMsg("Prepare: " . $db->error); return false; }

    $v_r  = (float)($data['v_r']        ?? 0);
    $v_s  = (float)($data['v_s']        ?? 0);
    $v_t  = (float)($data['v_t']        ?? 0);
    $a_r  = (float)($data['a_r']        ?? 0);
    $a_s  = (float)($data['a_s']        ?? 0);
    $a_t  = (float)($data['a_t']        ?? 0);
    $f_r  = (float)($data['f_r']        ?? 0);
    $f_s  = (float)($data['f_s']        ?? 0);
    $f_t  = (float)($data['f_t']        ?? 0);
    $e_r  = (float)($data['e_r']        ?? 0);
    $e_s  = (float)($data['e_s']        ?? 0);
    $e_t  = (float)($data['e_t']        ?? 0);
    $temp = (float)($data['temp_panel'] ?? 0);
    $hum  = (float)($data['hum_panel']  ?? 0);
    $src  = (string)($data['source']    ?? 'mqtt');

    $stmt->bind_param('idddddddddddddds',
        $mid, $v_r, $v_s, $v_t, $a_r, $a_s, $a_t,
        $f_r, $f_s, $f_t, $e_r, $e_s, $e_t, $temp, $hum, $src
    );

    if ($stmt->execute()) {
        logMsg(sprintf("machine=%d | V_R=%.1fV | A_R=%.2fA | Temp=%.1f°C | src=%s",
            $mid, $v_r, $a_r, $temp, $src));

        // Update machine status jadi 'run' saat ada data masuk
        $db->query("UPDATE machines SET status='run', updated_at=NOW() WHERE id=$mid");
        return true;
    }
    logMsg("Insert: " . $stmt->error);
    return false;
}

// ── Simpan vibration ke MySQL ─────────────────────────────────
function saveVibration(mysqli $db, int $mid, array $data): void {
    // vib_x/y/z/b → sensor_num 1/2/3/4, rms_overall = nilai sensor tsb
    $axes = [
        1 => (float)($data['vib_x'] ?? 0),
        2 => (float)($data['vib_y'] ?? 0),
        3 => (float)($data['vib_z'] ?? 0),
        4 => (float)($data['vib_b'] ?? 0),
    ];
    $vib_temp = (float)($data['vib_temp'] ?? 0);

    foreach ($axes as $snum => $rms) {
        $status = 'normal';
        if ($rms >= 7.1)      $status = 'critical';
        elseif ($rms >= 2.8)  $status = 'warning';

        $stmt = $db->prepare("
            INSERT INTO vibration_readings
                (machine_id, sensor_num, rms_overall, axis_b, temp_sensor, status, source, recorded_at)
            VALUES (?, ?, ?, ?, ?, ?, 'esp32_mqtt', NOW())
        ");
        if (!$stmt) { logMsg("Vib prepare: " . $db->error); continue; }

        $axis_b = ($snum === 4) ? $rms : null;
        $stmt->bind_param('iiddds', $mid, $snum, $rms, $axis_b, $vib_temp, $status);
        $stmt->execute();
    }
    logMsg(sprintf("vib machine=%d | X=%.2f Y=%.2f Z=%.2f B=%.2f",
        $mid,
        $axes[1], $axes[2], $axes[3], $axes[4]
    ));
}

// ── Main: loop auto-reconnect ─────────────────────────────────
logMsg("===========================================");
logMsg("  OEE MQTT Subscriber (auto-reconnect)");
logMsg("  Broker : " . MQTT_HOST . ":" . MQTT_PORT);
logMsg("  Topic  : " . MQTT_TOPIC);
logMsg("  DB     : " . DB_NAME . "@" . DB_HOST);
logMsg("===========================================");

$mysqli = getMySQL();

while (true) {
    $mqtt     = new SimpleMQTT();
    $clientId = 'php-oee-' . getmypid() . '-' . rand(100, 999);

    if ($mqtt->connect(MQTT_HOST, MQTT_PORT, $clientId)) {
        $mqtt->subscribe(MQTT_TOPIC);
        $mqtt->loop(function(string $topic, string $payload) use ($mysqli) {
            logMsg("[$topic]");
            $data = json_decode($payload, true);
            if (!is_array($data)) {
                logMsg("JSON invalid: " . substr($payload, 0, 100));
                return;
            }
            $mid = isset($data['machine_id']) ? (int)$data['machine_id'] : 0;
            if (saveToMySQL($mysqli, $data) && $mid > 0) {
                saveVibration($mysqli, $mid, $data);
            }
        });
    }

    logMsg("Reconnect dalam " . RECONNECT_DELAY . " detik...");
    sleep(RECONNECT_DELAY);
}
