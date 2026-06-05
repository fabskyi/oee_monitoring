#include <WiFi.h>

// ========== KONFIGURASI ==========
const char* SSID     = "ONE-YADIN";       // ganti dengan nama WiFi Anda
const char* PASSWORD = "Ramnay014#";   // ganti dengan password WiFi Anda

// Static IP
IPAddress LOCAL_IP  (192, 168, 183, 167);
IPAddress GATEWAY   (192, 168, 183,   1);
IPAddress SUBNET    (255, 255, 255,   0);
IPAddress DNS1      (  8,   8,   8,   8);
// =================================

void setup() {
  Serial.begin(115200);
  delay(1000);
  Serial.println("\n============================");
  Serial.println("   ESP32 Static IP + WiFi");
  Serial.println("============================");

  // Set Static IP sebelum connect
  if (!WiFi.config(LOCAL_IP, GATEWAY, SUBNET, DNS1)) {
    Serial.println("[ERROR] Gagal set Static IP!");
  }

  Serial.printf("Connecting ke WiFi: %s", SSID);
  WiFi.begin(SSID, PASSWORD);

  int timeout = 0;
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
    timeout++;
    if (timeout > 30) {
      Serial.println("\n[ERROR] Gagal konek! Cek SSID/Password.");
      return;
    }
  }

  Serial.println("\n[OK] Terhubung!");
  Serial.println("----------------------------");
  Serial.print  ("SSID     : "); Serial.println(WiFi.SSID());
  Serial.print  ("IP       : "); Serial.println(WiFi.localIP());
  Serial.print  ("Gateway  : "); Serial.println(WiFi.gatewayIP());
  Serial.print  ("Subnet   : "); Serial.println(WiFi.subnetMask());
  Serial.print  ("MAC      : "); Serial.println(WiFi.macAddress());
  Serial.print  ("RSSI     : "); Serial.print(WiFi.RSSI()); Serial.println(" dBm");
  Serial.println("============================");
}

void loop() {
  // Reconnect otomatis jika putus
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("[WARN] WiFi putus, mencoba reconnect...");
    WiFi.reconnect();
    delay(5000);
  }
}
