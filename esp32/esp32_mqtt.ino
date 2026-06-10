// ============================================================
//  ESP32 OEE Sensor — MQTT Publisher
//  Broker : broker.hivemq.com:1883
// ============================================================
#include <WiFi.h>
#include <PubSubClient.h>
#include <ArduinoJson.h>

// ── WiFi Config ──────────────────────────────────────────────
const char* WIFI_SSID     = "ONE-YADIN";
const char* WIFI_PASSWORD = "Ramnay014#";

// Static IP — DNS pakai Google agar bisa resolve broker.hivemq.com
IPAddress LOCAL_IP (192, 168, 183, 167);
IPAddress GATEWAY  (192, 168, 183, 1);
IPAddress SUBNET   (255, 255, 255, 0);
IPAddress DNS1     (192, 168, 183, 1); // ← Router lokal
IPAddress DNS2     (8, 8, 8, 8);      // ← Google DNS backup

// ── MQTT Config ───────────────────────────────────────────────
const char* MQTT_BROKER = "192.168.183.143";  // IP PC kamu (Mosquitto)
const int   MQTT_PORT   = 1883;

// ── Device Config ─────────────────────────────────────────────
const char* DEVICE_ID  = "ESP32-OEE-001";
const int   MACHINE_ID = 1;
const long  PUBLISH_MS = 5000;  // kirim setiap 5 detik

// ── Topics ────────────────────────────────────────────────────
char TOPIC_DATA[64];
char TOPIC_STATUS[64];
char TOPIC_HEARTBEAT[64];

// ── Objects ───────────────────────────────────────────────────
WiFiClient   wifiClient;
PubSubClient mqtt(wifiClient);

unsigned long lastPublish   = 0;
unsigned long lastHeartbeat = 0;

// ─────────────────────────────────────────────────────────────
void setupWiFi() {
    WiFi.disconnect(true);
    delay(500);
    WiFi.mode(WIFI_STA);

    // Set static IP dengan DNS Google
    if (!WiFi.config(LOCAL_IP, GATEWAY, SUBNET, DNS1, DNS2)) {
        Serial.println("⚠️  Static IP config gagal, pakai DHCP");
    }

    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
    Serial.print("Connecting WiFi");

    int retry = 0;
    while (WiFi.status() != WL_CONNECTED && retry < 40) {
        delay(500);
        Serial.print(".");
        retry++;
    }

    if (WiFi.status() == WL_CONNECTED) {
        Serial.println("\n✅ WiFi OK");
        Serial.println("   IP  : " + WiFi.localIP().toString());
        Serial.println("   DNS : " + WiFi.dnsIP().toString());
        Serial.println("   RSSI: " + String(WiFi.RSSI()) + " dBm");

        // Test DNS resolve
        IPAddress resolvedIP;
        if (WiFi.hostByName(MQTT_BROKER, resolvedIP)) {
            Serial.println("   DNS resolve OK: " + String(MQTT_BROKER) + " → " + resolvedIP.toString());
        } else {
            Serial.println("   ⚠️  DNS resolve GAGAL untuk: " + String(MQTT_BROKER));
            Serial.println("   Coba restart atau ganti DNS...");
        }
    } else {
        Serial.println("\n❌ WiFi gagal! Restart...");
        ESP.restart();
    }
}

// ─────────────────────────────────────────────────────────────
void mqttCallback(char* topic, byte* payload, unsigned int length) {
    String msg = "";
    for (unsigned int i = 0; i < length; i++) msg += (char)payload[i];
    Serial.println("📩 IN [" + String(topic) + "]: " + msg);
}

// ─────────────────────────────────────────────────────────────
bool mqttConnect() {
    // Client ID unik wajib untuk HiveMQ public
    uint32_t chipId = 0;
    for (int i = 0; i < 17; i += 8) {
        chipId |= ((ESP.getEfuseMac() >> (40 - i)) & 0xff) << i;
    }
    String clientId = "yadin-esp32-" + String(chipId, HEX);

    Serial.println("Connecting MQTT...");
    Serial.println("  Broker   : " + String(MQTT_BROKER) + ":" + String(MQTT_PORT));
    Serial.println("  ClientID : " + clientId);

    String willTopic = String(TOPIC_STATUS);
    String willMsg   = "{\"device_id\":\"" + String(DEVICE_ID) + "\",\"status\":\"offline\"}";

    mqtt.setSocketTimeout(15);  // timeout 15 detik

    bool ok = mqtt.connect(clientId.c_str(), nullptr, nullptr,
                           willTopic.c_str(), 1, true, willMsg.c_str());

    if (ok) {
        Serial.println("✅ MQTT Connected!");
        String onlineMsg = "{\"device_id\":\"" + String(DEVICE_ID) +
                           "\",\"status\":\"online\",\"ip\":\"" +
                           WiFi.localIP().toString() + "\"}";
        mqtt.publish(TOPIC_STATUS, onlineMsg.c_str(), true);
    } else {
        int rc = mqtt.state();
        Serial.println("❌ MQTT Gagal! rc=" + String(rc));
        // Kode error:
        // -4 = MQTT_CONNECTION_TIMEOUT
        // -3 = MQTT_CONNECTION_LOST (ECONRESET)
        // -2 = MQTT_CONNECT_FAILED
        // -1 = MQTT_DISCONNECTED
        //  1 = MQTT_CONNECT_BAD_PROTOCOL
        //  2 = MQTT_CONNECT_BAD_CLIENT_ID
        //  3 = MQTT_CONNECT_UNAVAILABLE
        //  5 = MQTT_CONNECT_UNAUTHORIZED
        if (rc == -3) {
            Serial.println("   → ECONRESET: Port 1883 mungkin diblokir router");
            Serial.println("   → Coba ganti ke port 8883 atau pakai broker lain");
        }
    }
    return ok;
}

// ─────────────────────────────────────────────────────────────
float readVoltage()   { return 215.0 + random(-50, 50) / 10.0; }
float readCurrent()   { return 8.5   + random(-20, 20) / 10.0; }
float readFrequency() { return 50.0  + random(-5,   5) / 10.0; }
float readEnergy()    { return 1.2   + random(-10, 10) / 100.0; }
float readTemp()      { return 35.0  + random(-20, 30) / 10.0; }
float readHum()       { return 60.0  + random(-50, 50) / 10.0; }

// ─────────────────────────────────────────────────────────────
void publishSensorData() {
    StaticJsonDocument<512> doc;
    doc["device_id"]  = DEVICE_ID;
    doc["machine_id"] = MACHINE_ID;
    doc["v_r"]        = readVoltage();
    doc["v_s"]        = readVoltage();
    doc["v_t"]        = readVoltage();
    doc["a_r"]        = readCurrent();
    doc["a_s"]        = readCurrent();
    doc["a_t"]        = readCurrent();
    doc["f_r"]        = readFrequency();
    doc["f_s"]        = readFrequency();
    doc["f_t"]        = readFrequency();
    doc["e_r"]        = readEnergy();
    doc["e_s"]        = readEnergy();
    doc["e_t"]        = readEnergy();
    doc["temp_panel"] = readTemp();
    doc["hum_panel"]  = readHum();
    doc["source"]     = "esp32_mqtt";

    char buf[512];
    serializeJson(doc, buf);

    if (mqtt.publish(TOPIC_DATA, buf)) {
        Serial.println("📤 OK → " + String(TOPIC_DATA));
        Serial.println("   " + String(buf));
    } else {
        Serial.println("❌ Publish gagal");
    }
}

// ─────────────────────────────────────────────────────────────
void publishHeartbeat() {
    StaticJsonDocument<128> doc;
    doc["device_id"] = DEVICE_ID;
    doc["uptime_ms"] = millis();
    doc["rssi"]      = WiFi.RSSI();
    doc["ip"]        = WiFi.localIP().toString();
    char buf[128];
    serializeJson(doc, buf);
    mqtt.publish(TOPIC_HEARTBEAT, buf);
    Serial.println("💓 Heartbeat sent");
}

// ─────────────────────────────────────────────────────────────
void setup() {
    Serial.begin(115200);
    delay(1000);
    Serial.println("\n\n=== ESP32 OEE MQTT ===");

    snprintf(TOPIC_DATA,      sizeof(TOPIC_DATA),      "yadin/sensor/%s/data",      DEVICE_ID);
    snprintf(TOPIC_STATUS,    sizeof(TOPIC_STATUS),    "yadin/sensor/%s/status",    DEVICE_ID);
    snprintf(TOPIC_HEARTBEAT, sizeof(TOPIC_HEARTBEAT), "yadin/device/%s/heartbeat", DEVICE_ID);

    setupWiFi();

    mqtt.setServer(MQTT_BROKER, MQTT_PORT);
    mqtt.setCallback(mqttCallback);
    mqtt.setBufferSize(512);
    mqtt.setKeepAlive(60);

    mqttConnect();
}

// ─────────────────────────────────────────────────────────────
void loop() {
    if (WiFi.status() != WL_CONNECTED) {
        Serial.println("WiFi lost...");
        setupWiFi();
        return;
    }

    if (!mqtt.connected()) {
        Serial.println("MQTT disconnected, retry 5s...");
        delay(5000);
        mqttConnect();
        return;
    }

    mqtt.loop();

    unsigned long now = millis();

    if (now - lastPublish >= PUBLISH_MS) {
        lastPublish = now;
        publishSensorData();
    }

    if (now - lastHeartbeat >= 30000) {
        lastHeartbeat = now;
        publishHeartbeat();
    }
}
