<?php
// ========== KONFIGURASI DATABASE ==========
$host     = "localhost";
$user     = "root";
$password = "";
$database = "oee_monitoring";
// ==========================================

header("Content-Type: application/json");

// Koneksi
$conn = mysqli_connect($host, $user, $password, $database);

// Cek koneksi — kirim JSON jika gagal
if (!$conn) {
    http_response_code(500);
    echo json_encode([
        "status"   => "error",
        "message"  => "Koneksi DB gagal: " . mysqli_connect_error(),
        "db"       => $database
    ]);
    exit;
}

// Kalau GET → hanya cek koneksi
if ($_SERVER["REQUEST_METHOD"] === "GET") {
    echo json_encode([
        "status"   => "ok",
        "message"  => "Koneksi DB berhasil",
        "db"       => $database,
        "server"   => $host
    ]);
    exit;
}

// ===== POST → simpan data dari ESP32 =====
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $device_id   = mysqli_real_escape_string($conn, $_POST["device_id"]   ?? "ESP32-001");
    $temperature = floatval($_POST["temperature"] ?? 0);
    $humidity    = floatval($_POST["humidity"]    ?? 0);
    $voltage     = floatval($_POST["voltage"]     ?? 0);
    $current     = floatval($_POST["current"]     ?? 0);
    $counter     = intval($_POST["counter"]       ?? 0);
    $status      = mysqli_real_escape_string($conn, $_POST["status"] ?? "OFF");

    $sql = "INSERT INTO sensor_data
            (device_id, temperature, humidity, voltage, current, counter, status)
            VALUES
            ('$device_id', $temperature, $humidity, $voltage, $current, $counter, '$status')";

    if (mysqli_query($conn, $sql)) {
        echo json_encode([
            "status"  => "ok",
            "message" => "Data tersimpan",
            "id"      => mysqli_insert_id($conn),
            "db"      => $database
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "status"  => "error",
            "message" => mysqli_error($conn),
            "db"      => $database
        ]);
    }
}

mysqli_close($conn);
?>
