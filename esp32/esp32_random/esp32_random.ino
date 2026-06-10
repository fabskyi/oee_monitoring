// ============================================================
//  ESP32 — Kirim Data Random via MQTT
//  Broker  : 192.168.183.143:1883 (Mosquitto lokal)
//  Topic   : yadin/sensor/ESP32-OEE-001/data
// ============================================================
#include <WiFi.h>
#include <PubSubClient.h>
#include <ArduinoJson.h>

// ── WiFi (DHCP) ───────────────────────────────────────────────
const char* SSID     = "ONE-YADIN";
const char* PASSWORD = "Ramnay014#";

// ── MQTT ─────────────────────────────────────────────────────
const char* BROKER    = "192.168.183.143";
const int   PORT      = 1883;
const char* DEVICE_ID = "ESP32-OEE-001";
const char* TOPIC     = "yadin/sensor/ESP32-OEE-001/data";
const int   INTERVAL  = 500;

// ─────────────────────────────────────────────────────────────
WiFiClient   wifiClient;
PubSubClient mqtt(wifiClient);
unsigned long lastSend = 0;

float randFloat(float mn, float mx) {
    return mn + (float)random(0, 1000) / 1000.0 * (mx - mn);
}

void setupWiFi() {
    WiFi.mode(WIFI_STA);
    WiFi.begin(SSID, PASSWORD);
    Serial.print("Connecting WiFi");
    while (WiFi.status() != WL_CONNECTED) {
        delay(500); Serial.print(".");
    }
    Serial.println("\n WiFi: " + WiFi.localIP().toString());
}

void mqttConnect() {
    while (!mqtt.connected()) {
        Serial.print("Connecting MQTT... ");
        String id = "esp32-" + String((uint32_t)ESP.getEfuseMac(), HEX);
        if (mqtt.connect(id.c_str())) {
            Serial.println("Connected!");
        } else {
            Serial.println("Gagal rc=" + String(mqtt.state()) + " retry 3s...");
            delay(3000);
        }
    }
}

void sendData() {
    StaticJsonDocument<512> doc;
    doc["device_id"]  = DEVICE_ID;
    doc["machine_id"] = 1;
    doc["v_r"] = randFloat(210.0, 225.0);
    doc["v_s"] = randFloat(210.0, 225.0);
    doc["v_t"] = randFloat(210.0, 225.0);
    doc["a_r"] = randFloat(5.0, 12.0);
    doc["a_s"] = randFloat(5.0, 12.0);
    doc["a_t"] = randFloat(5.0, 12.0);
    doc["f_r"] = randFloat(49.5, 50.5);
    doc["f_s"] = randFloat(49.5, 50.5);
    doc["f_t"] = randFloat(49.5, 50.5);
    doc["e_r"] = randFloat(0.8, 2.0);
    doc["e_s"] = randFloat(0.8, 2.0);
    doc["e_t"] = randFloat(0.8, 2.0);
    doc["temp_panel"] = randFloat(28.0, 45.0);
    doc["hum_panel"]  = randFloat(40.0, 80.0);
    doc["source"]     = "esp32_mqtt";

    char buf[512];
    serializeJson(doc, buf);

    if (mqtt.publish(TOPIC, buf)) {
        Serial.println("Sent: " + String(buf));
    } else {
        Serial.println("Gagal kirim!");
    }
}

void setup() {
    Serial.begin(115200);
    delay(500);
    Serial.println("\n=== ESP32 MQTT Random Data ===");
    setupWiFi();
    mqtt.setServer(BROKER, PORT);
    mqtt.setBufferSize(512);
    mqttConnect();
}

void loop() {
    if (WiFi.status() != WL_CONNECTED) setupWiFi();
    if (!mqtt.connected()) mqttConnect();
    mqtt.loop();
    if (millis() - lastSend >= INTERVAL) {
        lastSend = millis();
        sendData(); 
    }
}