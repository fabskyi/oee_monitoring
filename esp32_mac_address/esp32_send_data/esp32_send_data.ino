#include <WiFi.h>
#include <HTTPClient.h>

// ========== KONFIGURASI WiFi ==========
const char* SSID     = "ONE-YADIN";
const char* PASSWORD = "Ramnay014#";

// Static IP
IPAddress LOCAL_IP  (192, 168, 183, 167);
IPAddress GATEWAY   (192, 168, 183,   1);
IPAddress SUBNET    (255, 255, 255,   0);
IPAddress DNS1      (  8,   8,   8,   8);

// ========== KONFIGURASI SERVER ==========
const char* SERVER_URL = "http://DOMAIN_ANDA/api_sensor.php"; // ganti DOMAIN_ANDA
const char* DEVICE_ID  = "ESP32-001";
// =========================================

int counter = 0;

void setup() {
  Serial.begin(115200);
  delay(1000);

  WiFi.config(LOCAL_IP, GATEWAY, SUBNET, DNS1);
  WiFi.begin(SSID, PASSWORD);

  Serial.print("Connecting WiFi");
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println("\n[OK] WiFi terhubung: " + WiFi.localIP().toString());
}

void loop() {
  if (WiFi.status() == WL_CONNECTED) {

    // Generate data random
    float temperature = random(2000, 4000) / 100.0;  // 20.00 - 40.00 °C
    float humidity    = random(3000, 9000) / 100.0;  // 30.00 - 90.00 %
    float voltage     = random(2200, 2400) / 100.0;  // 22.00 - 24.00 V
    float current     = random(100,  500)  / 100.0;  //  1.00 -  5.00 A
    String status     = (random(0, 2) == 0) ? "ON" : "OFF";
    counter++;

    Serial.println("\n--- Kirim Data ---");
    Serial.printf("Temp : %.2f °C\n", temperature);
    Serial.printf("Humi : %.2f %%\n", humidity);
    Serial.printf("Volt : %.2f V\n",  voltage);
    Serial.printf("Curr : %.2f A\n",  current);
    Serial.printf("Count: %d\n",      counter);
    Serial.printf("Stat : %s\n",      status.c_str());

    HTTPClient http;
    http.begin(SERVER_URL);
    http.addHeader("Content-Type", "application/x-www-form-urlencoded");

    String postData = "device_id="   + String(DEVICE_ID)
                    + "&temperature=" + String(temperature, 2)
                    + "&humidity="    + String(humidity, 2)
                    + "&voltage="     + String(voltage, 2)
                    + "&current="     + String(current, 2)
                    + "&counter="     + String(counter)
                    + "&status="      + status;

    int httpCode = http.POST(postData);

    if (httpCode > 0) {
      Serial.printf("[HTTP] Response: %d\n", httpCode);
      Serial.println(http.getString());
    } else {
      Serial.printf("[ERROR] HTTP: %s\n", http.errorToString(httpCode).c_str());
    }

    http.end();
  } else {
    Serial.println("[WARN] WiFi putus, reconnecting...");
    WiFi.reconnect();
  }

  delay(5000); // kirim setiap 5 detik
}
