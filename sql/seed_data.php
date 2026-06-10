<?php
// SEED DATA GENERATOR - Jalankan sekali di browser: http://localhost/oee/sql/seed_data.php
$conn = new mysqli('localhost', 'root', '', 'oee_monitoring');
if ($conn->connect_error) die('DB Error: ' . $conn->connect_error);

$machines = [1 => 'SIRIUS-UZ', 2 => 'VESTA-650T', 3 => 'HI-M G1'];
$conn->query("DELETE FROM sensor_readings WHERE recorded_at < NOW()");
$conn->query("DELETE FROM oee_daily WHERE snap_date < CURDATE()");
$conn->query("DELETE FROM vibration_readings");
$conn->query("DELETE FROM alerts");
$conn->query("DELETE FROM maintenance_records");

$log = [];

// === 30 HARI OEE DAILY ===
foreach ($machines as $mid => $mname) {
    $base_avail = [1=>88, 2=>78, 3=>72][$mid];
    $base_perf  = [1=>85, 2=>74, 3=>80][$mid];
    $base_qual  = [1=>96, 2=>91, 3=>95][$mid];
    for ($d = 29; $d >= 0; $d--) {
        $date = date('Y-m-d', strtotime("-$d days"));
        $av = round($base_avail + mt_rand(-8, 8), 1);
        $pe = round($base_perf  + mt_rand(-8, 8), 1);
        $qu = round($base_qual  + mt_rand(-4, 4), 1);
        $av = max(50, min(99, $av));
        $pe = max(50, min(99, $pe));
        $qu = max(80, min(99, $qu));
        $oee = round($av * $pe * $qu / 10000, 2);
        $planned = 480;
        $actual  = round($planned * $av / 100);
        $conn->query("INSERT INTO oee_daily (machine_id,snap_date,availability,performance,quality,oee_score,planned_time,actual_run)
            VALUES ($mid,'$date',$av,$pe,$qu,$oee,$planned,$actual)
            ON DUPLICATE KEY UPDATE availability=$av,performance=$pe,quality=$qu,oee_score=$oee");
    }
}
$log[] = "✅ oee_daily: 30 hari × 3 mesin = 90 rows";

// === SENSOR READINGS - 24 jam per mesin (every 15 min) ===
$sensorCount = 0;
foreach ($machines as $mid => $mname) {
    $base_v = [1=>220, 2=>218, 3=>221][$mid];
    $base_a = [1=>12,  2=>18,  3=>9][$mid];
    $base_t = [1=>38,  2=>52,  3=>35][$mid];
    for ($h = 0; $h < 24; $h++) {
        for ($m = 0; $m < 60; $m += 15) {
            $ts = date('Y-m-d H:i:s', strtotime("today") + $h*3600 + $m*60 - 3600);
            $vr = round($base_v + mt_rand(-5,5) + mt_rand(-10,10)/10, 2);
            $vs = round($vr + mt_rand(-3,3), 2);
            $vt = round($vr + mt_rand(-3,3), 2);
            $ar = round($base_a + mt_rand(-20,20)/10, 2);
            $as_ = round($ar + mt_rand(-5,5)/10, 2);
            $at = round($ar + mt_rand(-5,5)/10, 2);
            $fr = round(50 + mt_rand(-5,5)/10, 2);
            $fs = round(50 + mt_rand(-5,5)/10, 2);
            $ft = round(50 + mt_rand(-5,5)/10, 2);
            $er = round(($ar * $vr * 0.001 * 0.25), 3);
            $es = round(($as_ * $vs * 0.001 * 0.25), 3);
            $et = round(($at * $vt * 0.001 * 0.25), 3);
            $tp = round($base_t + mt_rand(-30,30)/10, 1);
            $hum = round(55 + mt_rand(-100,100)/10, 1);
            $src = ($mid == 1) ? 'esp' : 'simulator';
            $conn->query("INSERT INTO sensor_readings (machine_id,v_r,v_s,v_t,a_r,a_s,a_t,f_r,f_s,f_t,e_r,e_s,e_t,temp_panel,hum_panel,source,recorded_at)
                VALUES ($mid,$vr,$vs,$vt,$ar,$as_,$at,$fr,$fs,$ft,$er,$es,$et,$tp,$hum,'$src','$ts')");
            $sensorCount++;
        }
    }
}
$log[] = "✅ sensor_readings: $sensorCount rows (24h × 4/h × 3 mesin)";

// === VIBRATION READINGS - 24 jam ===
$vibCount = 0;
foreach ($machines as $mid => $mname) {
    $base = [1=>1.2, 2=>3.1, 3=>5.8][$mid]; // 1=normal, 2=warning, 3=critical
    for ($h = 0; $h < 24; $h++) {
        for ($m = 0; $m < 60; $m += 10) {
            $ts = date('Y-m-d H:i:s', strtotime("today") + $h*3600 + $m*60 - 3600);
            $s1 = round(max(0.1, $base + mt_rand(-30,30)/100), 3);
            $s2 = round(max(0.1, $base + mt_rand(-40,40)/100), 3);
            $s3 = round(max(0.1, $base + mt_rand(-50,50)/100), 3);
            $rms = round(sqrt(($s1*$s1 + $s2*$s2 + $s3*$s3)/3), 3);
            $status = $rms < 2.8 ? 'normal' : ($rms < 7.1 ? 'warning' : 'critical');
            $conn->query("INSERT INTO vibration_readings (machine_id,sensor_1,sensor_2,sensor_3,rms_overall,status,source,recorded_at)
                VALUES ($mid,$s1,$s2,$s3,$rms,'$status','simulator','$ts')");
            $vibCount++;
        }
    }
}
$log[] = "✅ vibration_readings: $vibCount rows";

// === ALERTS ===
$alertData = [
    [1, 'temp_panel', 72.5, 0, 65, 'critical', date('Y-m-d H:i:s', strtotime('-2 hours'))],
    [2, 'v_r',        195.0, 200, 240, 'warning', date('Y-m-d H:i:s', strtotime('-5 hours'))],
    [2, 'a_r',        22.5, 0, 20, 'critical', date('Y-m-d H:i:s', strtotime('-3 hours'))],
    [3, 'temp_panel', 68.0, 0, 65, 'warning', date('Y-m-d H:i:s', strtotime('-1 hour'))],
    [1, 'hum_panel',  85.0, 0, 80, 'warning', date('Y-m-d H:i:s', strtotime('-30 minutes'))],
    [3, 'v_s',        245.0, 200, 240, 'critical', date('Y-m-d H:i:s', strtotime('-4 hours'))],
];
foreach ($alertData as $a) {
    [$mid, $skey, $sval, $lo, $hi, $sev, $ts] = $a;
    $conn->query("INSERT INTO alerts (machine_id,sensor_key,sensor_value,threshold_lo,threshold_hi,severity,acknowledged,created_at)
        VALUES ($mid,'$skey',$sval,$lo,$hi,'$sev',0,'$ts')");
}
$log[] = "✅ alerts: " . count($alertData) . " rows";

// === MAINTENANCE RECORDS ===
$techList = ['Budi Santoso', 'Ahmad Rizki', 'Dedi Kurniawan', 'Siti Rahayu'];
$mTypes = ['preventive', 'corrective', 'inspection', 'breakdown'];
$mDescs = [
    'preventive' => ['Penggantian oli mesin', 'Pembersihan filter udara', 'Cek dan kalibrasi sensor', 'Penggantian bearing'],
    'corrective' => ['Perbaikan motor penggerak', 'Penggantian belt conveyor', 'Perbaikan panel kontrol'],
    'inspection' => ['Inspeksi harian komponen', 'Pemeriksaan sistem pendingin', 'Cek keausan komponen'],
    'breakdown'  => ['Kerusakan mendadak motor', 'Overheating panel listrik'],
];
$machineIds = [1, 2, 3];
$mCount = 0;
for ($i = 0; $i < 15; $i++) {
    $mid = $machineIds[array_rand($machineIds)];
    $type = $mTypes[array_rand($mTypes)];
    $desc = $mDescs[$type][array_rand($mDescs[$type])];
    $tech = $techList[array_rand($techList)];
    $daysAgo = mt_rand(0, 25);
    $date = date('Y-m-d', strtotime("-$daysAgo days"));
    $dur  = mt_rand(30, 480);
    $cost = ($type === 'breakdown') ? mt_rand(500000, 5000000) : mt_rand(50000, 500000);
    $conn->query("INSERT INTO maintenance_records (machine_id,type,description,technician,maint_date,duration_min,cost,created_at)
        VALUES ($mid,'$type','$desc','$tech','$date',$dur,$cost,NOW())");
    $mCount++;
}
$log[] = "✅ maintenance_records: $mCount rows";

// === ESP32 DEVICES ===
$conn->query("INSERT INTO esp32_devices (device_id,machine_id,ip_address,mac_address,firmware_version,last_seen,status)
    VALUES ('ESP32-OEE-001',1,'192.168.183.167','AA:BB:CC:DD:EE:01','v2.0.1',NOW(),'online')
    ON DUPLICATE KEY UPDATE last_seen=NOW(), status='online'");
$conn->query("INSERT INTO esp32_devices (device_id,machine_id,ip_address,mac_address,firmware_version,last_seen,status)
    VALUES ('ESP32-OEE-002',2,'192.168.183.168','AA:BB:CC:DD:EE:02','v2.0.0',DATE_SUB(NOW(),INTERVAL 10 MINUTE),'offline')
    ON DUPLICATE KEY UPDATE status='offline'");
$log[] = "✅ esp32_devices: 2 rows";

$conn->close();
echo '<h2>✅ Seed Data Berhasil!</h2><ul>';
foreach ($log as $l) echo "<li>$l</li>";
echo '</ul><br><a href="../dashboard.php">→ Ke Dashboard</a>';
?>
