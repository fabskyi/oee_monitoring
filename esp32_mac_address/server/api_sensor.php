<?php
// ========== KONFIGURASI DATABASE ==========
$host     = "localhost";          // biasanya localhost di shared hosting
$user     = "root";               // ganti dengan user MySQL Anda
$password = "";                   // ganti dengan password MySQL Anda
$database = "inventory_db_clone"; // sesuai database Anda
// ==========================================

header("Content-Type: application/json");

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $conn->connect_error]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $device_id   = $conn->real_escape_string($_POST["device_id"]   ?? "ESP32-001");
    $temperature = floatval($_POST["temperature"] ?? 0);
    $humidity    = floatval($_POST["humidity"]    ?? 0);
    $voltage     = floatval($_POST["voltage"]     ?? 0);
    $current     = floatval($_POST["current"]     ?? 0);
    $counter     = intval($_POST["counter"]       ?? 0);
    $status      = $conn->real_escape_string($_POST["status"] ?? "OFF");

    $sql = "INSERT INTO sensor_data
            (device_id, temperature, humidity, voltage, current, counter, status)
            VALUES
            ('$device_id', $temperature, $humidity, $voltage, $current, $counter, '$status')";

    if ($conn->query($sql)) {
        echo json_encode([
            "status"  => "success",
            "message" => "Data tersimpan",
            "id"      => $conn->insert_id
        ]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => $conn->error]);
    }
} else {
    // GET → tampilkan 10 data terakhir
    $result = $conn->query("SELECT * FROM sensor_data ORDER BY created_at DESC LIMIT 10");
    $rows = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    echo json_encode(["status" => "success", "data" => $rows]);
}

$conn->close();
?>
