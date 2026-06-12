<?php
// ============================================================
//  api/stream.php — Server-Sent Events untuk dashboard realtime
//  Browser buka sekali, server push tiap 1 detik
// ============================================================
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Lepas session lock SEGERA setelah auth — wajib sebelum infinite loop
session_write_close();

// SSE headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
set_time_limit(0);
ini_set('output_buffering', 'off');
ini_set('implicit_flush', 1);
while (ob_get_level()) ob_end_clean();

$db = getDB();

$tick     = 0;
$kpiEvery = 10;   // push KPI tiap 10 detik

function sseWrite(string $event, $data): void {
    echo "event: $event\n";
    echo 'data: ' . json_encode($data) . "\n\n";
    if (ob_get_level()) ob_flush();
    flush();
}

while (true) {
    if (connection_aborted()) break;

    $tick++;

    // ── Live: sensor terbaru + machine status (tiap 1 detik) ─────────────
    try {
        $rows = $db->query("
            SELECT m.id, m.name, m.status,
                   sr.v_r, sr.v_s, sr.v_t,
                   sr.a_r, sr.a_s, sr.a_t,
                   sr.temp_panel, sr.hum_panel,
                   sr.recorded_at AS last_reading,
                   TIMESTAMPDIFF(SECOND, sr.recorded_at, NOW()) AS secs_ago
            FROM machines m
            LEFT JOIN sensor_readings sr ON sr.id = (
                SELECT id FROM sensor_readings
                WHERE machine_id = m.id
                ORDER BY recorded_at DESC LIMIT 1
            )
            ORDER BY m.id ASC
        ")->fetchAll();

        // Cari data MQTT terbaru (semua mesin)
        $latestRow = $db->query("
            SELECT recorded_at, TIMESTAMPDIFF(SECOND, recorded_at, NOW()) AS secs_ago
            FROM sensor_readings ORDER BY recorded_at DESC LIMIT 1
        ")->fetch();

        $mqttSecs = $latestRow ? (int)$latestRow['secs_ago'] : 99999;
        $mqttLast = $latestRow ? substr($latestRow['recorded_at'], 11, 5) : null;

        $running = 0; $total = 0;
        foreach ($rows as $r) {
            $total++;
            if ($r['status'] === 'run') $running++;
        }

        sseWrite('live', [
            'running'   => $running,
            'stopped'   => $total - $running,
            'mqtt_live' => $mqttSecs <= 10,
            'mqtt_secs' => $mqttSecs,
            'mqtt_last' => $mqttLast,
            'machines'  => $rows,
        ]);
    } catch (Exception $e) {
        sseWrite('error', ['msg' => $e->getMessage()]);
    }

    // ── KPI berat: tiap 10 detik ──────────────────────────────────────────
    if ($tick % $kpiEvery === 0) {
        try {
            // OEE
            $oeeRow = $db->query("
                SELECT AVG(oee_score) AS avg_oee FROM oee_daily
                WHERE snap_date = (SELECT MAX(snap_date) FROM oee_daily)
            ")->fetch();
            $oee = round($oeeRow['avg_oee'] ?? 0, 1);

            // Alerts
            $alertRows = $db->query("
                SELECT severity, COUNT(*) AS cnt FROM alerts
                WHERE acknowledged=0 GROUP BY severity
            ")->fetchAll();
            $aTotal = $aHigh = $aMed = $aLow = 0;
            foreach ($alertRows as $a) {
                $aTotal += $a['cnt'];
                if ($a['severity']==='high')   $aHigh = $a['cnt'];
                if ($a['severity']==='medium') $aMed  = $a['cnt'];
                if ($a['severity']==='low')    $aLow  = $a['cnt'];
            }

            // Maintenance due
            $maintRow = $db->query("
                SELECT COUNT(*) AS cnt FROM maintenance_records
                WHERE maint_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            ")->fetch();

            // Donut
            $distLow = $distMid = $distHigh = 0;
            $distRows = $db->query("
                SELECT oee_score FROM oee_daily
                WHERE snap_date = (SELECT MAX(snap_date) FROM oee_daily)
            ")->fetchAll();
            foreach ($distRows as $dr) {
                $v = (float)$dr['oee_score'];
                if ($v < 60) $distLow++;
                elseif ($v <= 85) $distMid++;
                else $distHigh++;
            }

            // Recent alerts list
            $alertList = $db->query("
                SELECT a.*, m.name AS machine_name
                FROM alerts a LEFT JOIN machines m ON m.id=a.machine_id
                WHERE a.acknowledged=0
                ORDER BY a.created_at DESC LIMIT 5
            ")->fetchAll();
            foreach ($alertList as &$al) {
                $diff = time() - strtotime($al['created_at']);
                $al['ago'] = $diff < 60 ? $diff.'s ago'
                    : ($diff < 3600 ? round($diff/60).'m ago'
                    : ($diff < 86400 ? round($diff/3600).'h ago' : round($diff/86400).'d ago'));
            }

            // Maintenance list
            $maintList = $db->query("
                SELECT mr.*, m.name AS machine_name
                FROM maintenance_records mr LEFT JOIN machines m ON m.id=mr.machine_id
                ORDER BY mr.maint_date ASC LIMIT 5
            ")->fetchAll();

            sseWrite('kpi', [
                'oee'            => $oee,
                'alerts_total'   => $aTotal,
                'alerts_high'    => $aHigh,
                'alerts_medium'  => $aMed,
                'alerts_low'     => $aLow,
                'maintenance_due'=> (int)($maintRow['cnt'] ?? 0),
                'donut'          => [$distLow, $distMid, $distHigh],
                'alerts'         => $alertList,
                'maint'          => $maintList,
                'ts'             => date('H:i:s'),
            ]);
        } catch (Exception $e) {
            // skip kpi on error, continue stream
        }
    }

    sleep(1);
}
