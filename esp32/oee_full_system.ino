/*
 * OEE Full System - ESP32 Arduino Code
 * Project: OEE Monitoring System for Hwacheon CNC Factory
 * Device: ESP32
 *
 * REQUIRED LIBRARIES (install via Arduino Library Manager):
 * - WiFi.h        : Built-in ESP32 library
 * - HTTPClient.h  : Built-in ESP32 library
 * - ArduinoJson.h : Install "ArduinoJson" by Benoit Blanchon (v6.x or v7.x)
 */

#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>

// =============================================================
//  CONFIGURATION SECTION - Edit these values as needed
// =============================================================

// WiFi credentials
const char* SSID     = "ONE-YADIN";
const char* PASSWORD = "Ramnay014#";

// Static IP configuration
IPAddress LOCAL_IP(192, 168, 183, 167);
// Gateway / Subnet / DNS will be obtained via DHCP first;
// if DHCP fails, fallback static values below are used.
IPAddress FALLBACK_GATEWAY(192, 168, 183, 1);
IPAddress FALLBACK_SUBNET(255, 255, 255, 0);
IPAddress FALLBACK_DNS(8, 8, 8, 8);

// Device identity
const char* DEVICE_ID       = "ESP32-OEE-001";
const char* FIRMWARE_VERSION = "1.0.0";

// Server base URL (no trailing slash)
const char* SERVER_BASE = "http://oee-monitoring.yadin.com";

// Timing intervals (milliseconds)
const unsigned long SEND_INTERVAL      = 3000;   // sensor data
const unsigned long VIBRATION_INTERVAL = 1000;   // vibration data
const unsigned long HEARTBEAT_INTERVAL = 30000;  // heartbeat
const unsigned long WIFI_TIMEOUT_MS    = 15000;  // WiFi connect timeout
const unsigned long WIFI_RETRY_BASE_MS = 2000;   // base backoff delay

// Vibration sensor analog pins
const int VIB_SENSOR_1_PIN = 34;
const int VIB_SENSOR_2_PIN = 35;
const int VIB_SENSOR_3_PIN = 32;

// Number of ADC samples per RMS calculation
const int VIB_SAMPLES = 100;

// ADC full scale -> mm/s mapping
const float ADC_MAX      = 4095.0f;
const float VIB_MAX_MMS  = 15.0f;

// machine_id this device monitors (must exist in DB)
const int MACHINE_ID = 1;

// =============================================================
//  GLOBAL STATE
// =============================================================

unsigned long lastSensorSend    = 0;
unsigned long lastVibrationSend = 0;
unsigned long lastHeartbeat     = 0;
unsigned long wifiRetryDelay    = WIFI_RETRY_BASE_MS;

String macAddress = "";

// =============================================================
//  FUNCTION DECLARATIONS
// =============================================================

bool  connectWiFi();
void  registerDevice();
bool  sendSensorData(float vr, float vs, float vt,
                     float ar, float as_, float at,
                     float fr, float fs, float ft,
                     float er, float es, float et,
                     float temp, float hum);
bool  sendVibrationData(float s1, float s2, float s3);
void  sendHeartbeat();
float readVibrationRMS(int pin, int samples);
float calcRmsOverall(float s1, float s2, float s3);
void  printStatus(float vr, float vs, float vt,
                  float ar, float as_, float at,
                  float fr, float fs, float ft,
                  float er, float es, float et,
                  float temp, float hum);

// =============================================================
//  SETUP
// =============================================================

void setup() {
  Serial.begin(115200);
  delay(500);

  Serial.println(F("=============================================="));
  Serial.println(F("  OEE Monitoring System - ESP32 Firmware"));
  Serial.print(F("  Device ID : ")); Serial.println(DEVICE_ID);
  Serial.print(F("  Firmware  : ")); Serial.println(FIRMWARE_VERSION);
  Serial.println(F("=============================================="));

  // Configure analog pins as input
  pinMode(VIB_SENSOR_1_PIN, INPUT);
  pinMode(VIB_SENSOR_2_PIN, INPUT);
  pinMode(VIB_SENSOR_3_PIN, INPUT);

  // Seed random with analog noise
  randomSeed(analogRead(33) + analogRead(36));

  if (connectWiFi()) {
    macAddress = WiFi.macAddress();
    Serial.print(F("[WiFi] MAC Address : ")); Serial.println(macAddress);
    Serial.print(F("[WiFi] IP Address  : ")); Serial.println(WiFi.localIP());
    registerDevice();
  } else {
    Serial.println(F("[WiFi] ERROR: Could not connect. Will retry in loop."));
  }
}

// =============================================================
//  MAIN LOOP
// =============================================================

void loop() {
  unsigned long now = millis();

  // --- WiFi watchdog ---
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println(F("[WiFi] Disconnected. Attempting reconnect..."));
    if (!connectWiFi()) {
      // Exponential backoff, cap at 60 s
      wifiRetryDelay = min(wifiRetryDelay * 2, (unsigned long)60000);
      Serial.print(F("[WiFi] Retry in "));
      Serial.print(wifiRetryDelay / 1000);
      Serial.println(F(" s"));
      delay(wifiRetryDelay);
      return;
    }
    wifiRetryDelay = WIFI_RETRY_BASE_MS; // reset backoff on success
    macAddress = WiFi.macAddress();
    registerDevice();
  }

  // --- Sensor data transmission ---
  if (now - lastSensorSend >= SEND_INTERVAL) {
    lastSensorSend = now;

    // Simulated realistic sensor values (replace with real sensor reads)
    float vr   = random(21000, 23000) / 100.0f;   // 210.00 – 230.00 V
    float vs   = random(21000, 23000) / 100.0f;
    float vt   = random(21000, 23000) / 100.0f;
    float ar   = random(800,  1500)   / 100.0f;   // 8.00 – 15.00 A
    float as_  = random(800,  1500)   / 100.0f;
    float at   = random(800,  1500)   / 100.0f;
    float fr   = random(4980, 5020)   / 100.0f;   // 49.80 – 50.20 Hz
    float fs   = random(4980, 5020)   / 100.0f;
    float ft   = random(4980, 5020)   / 100.0f;
    float er   = random(100,  500)    / 100.0f;   // 1.00 – 5.00 kWh
    float es   = random(100,  500)    / 100.0f;
    float et   = random(100,  500)    / 100.0f;
    float temp = random(2500, 4500)   / 100.0f;   // 25.00 – 45.00 °C
    float hum  = random(3000, 8000)   / 100.0f;   // 30.00 – 80.00 %

    printStatus(vr, vs, vt, ar, as_, at, fr, fs, ft, er, es, et, temp, hum);
    sendSensorData(vr, vs, vt, ar, as_, at, fr, fs, ft, er, es, et, temp, hum);
  }

  // --- Vibration data transmission ---
  if (now - lastVibrationSend >= VIBRATION_INTERVAL) {
    lastVibrationSend = now;

    float s1 = readVibrationRMS(VIB_SENSOR_1_PIN, VIB_SAMPLES);
    float s2 = readVibrationRMS(VIB_SENSOR_2_PIN, VIB_SAMPLES);
    float s3 = readVibrationRMS(VIB_SENSOR_3_PIN, VIB_SAMPLES);

    Serial.print(F("[VIB] S1="));  Serial.print(s1,  3);
    Serial.print(F(" S2="));       Serial.print(s2,  3);
    Serial.print(F(" S3="));       Serial.print(s3,  3);
    Serial.print(F(" RMS="));      Serial.println(calcRmsOverall(s1, s2, s3), 3);

    sendVibrationData(s1, s2, s3);
  }

  // --- Heartbeat ---
  if (now - lastHeartbeat >= HEARTBEAT_INTERVAL) {
    lastHeartbeat = now;
    sendHeartbeat();
  }
}

// =============================================================
//  connectWiFi
//  Returns true if connected successfully.
//  Tries DHCP first; if DHCP does not assign an address within
//  the timeout, forces the configured static IP.
// =============================================================

bool connectWiFi() {
  Serial.print(F("[WiFi] Connecting to "));
  Serial.println(SSID);

  WiFi.mode(WIFI_STA);
  WiFi.begin(SSID, PASSWORD);

  unsigned long start = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - start < WIFI_TIMEOUT_MS) {
    delay(500);
    Serial.print('.');
  }
  Serial.println();

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println(F("[WiFi] Connected via DHCP."));
    return true;
  }

  // DHCP failed - apply static IP and retry once
  Serial.println(F("[WiFi] DHCP failed. Applying static IP..."));
  WiFi.disconnect();
  delay(500);

  WiFi.config(LOCAL_IP, FALLBACK_GATEWAY, FALLBACK_SUBNET, FALLBACK_DNS);
  WiFi.begin(SSID, PASSWORD);

  start = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - start < WIFI_TIMEOUT_MS) {
    delay(500);
    Serial.print('.');
  }
  Serial.println();

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println(F("[WiFi] Connected with static IP."));
    return true;
  }

  Serial.println(F("[WiFi] Connection failed."));
  return false;
}

// =============================================================
//  registerDevice
//  POST device info to /api/esp32.php?action=register
// =============================================================

void registerDevice() {
  if (WiFi.status() != WL_CONNECTED) return;

  String url = String(SERVER_BASE) + "/api/esp32.php?action=register";

  StaticJsonDocument<256> doc;
  doc["device_id"]        = DEVICE_ID;
  doc["machine_id"]       = MACHINE_ID;
  doc["ip_address"]       = WiFi.localIP().toString();
  doc["mac_address"]      = macAddress;
  doc["firmware_version"] = FIRMWARE_VERSION;

  String payload;
  serializeJson(doc, payload);

  HTTPClient http;
  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(5000);

  int code = http.POST(payload);
  if (code > 0) {
    Serial.print(F("[REG] Device registered. HTTP "));
    Serial.println(code);
  } else {
    Serial.print(F("[REG] ERROR: "));
    Serial.println(http.errorToString(code));
  }
  http.end();
}

// =============================================================
//  sendSensorData
//  POST electrical sensor readings to /api/sensors.php?action=insert
// =============================================================

bool sendSensorData(float vr, float vs, float vt,
                    float ar, float as_, float at,
                    float fr, float fs, float ft,
                    float er, float es, float et,
                    float temp, float hum) {
  if (WiFi.status() != WL_CONNECTED) return false;

  String url = String(SERVER_BASE) + "/api/sensors.php?action=insert";

  StaticJsonDocument<512> doc;
  doc["device_id"]  = DEVICE_ID;
  doc["machine_id"] = MACHINE_ID;
  doc["source"]     = "esp";
  doc["v_r"]        = serialized(String(vr,  2));
  doc["v_s"]        = serialized(String(vs,  2));
  doc["v_t"]        = serialized(String(vt,  2));
  doc["a_r"]        = serialized(String(ar,  2));
  doc["a_s"]        = serialized(String(as_, 2));
  doc["a_t"]        = serialized(String(at,  2));
  doc["f_r"]        = serialized(String(fr,  2));
  doc["f_s"]        = serialized(String(fs,  2));
  doc["f_t"]        = serialized(String(ft,  2));
  doc["e_r"]        = serialized(String(er,  2));
  doc["e_s"]        = serialized(String(es,  2));
  doc["e_t"]        = serialized(String(et,  2));
  doc["temp_panel"] = serialized(String(temp, 2));
  doc["hum_panel"]  = serialized(String(hum,  2));

  String payload;
  serializeJson(doc, payload);

  HTTPClient http;
  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(5000);

  int code = http.POST(payload);
  bool ok  = (code == 200 || code == 201);

  if (!ok) {
    Serial.print(F("[SENSOR] HTTP error: "));
    Serial.println(code > 0 ? String(code) : http.errorToString(code));
  }
  http.end();
  return ok;
}

// =============================================================
//  sendVibrationData
//  POST vibration readings to /api/vibration.php?action=insert
// =============================================================

bool sendVibrationData(float s1, float s2, float s3) {
  if (WiFi.status() != WL_CONNECTED) return false;

  float rms = calcRmsOverall(s1, s2, s3);

  // Determine status string based on RMS overall
  String status;
  if      (rms < 2.8f)  status = "normal";
  else if (rms < 7.1f)  status = "warning";
  else                   status = "critical";

  String url = String(SERVER_BASE) + "/api/vibration.php?action=insert";

  StaticJsonDocument<256> doc;
  doc["device_id"]   = DEVICE_ID;
  doc["machine_id"]  = MACHINE_ID;
  doc["source"]      = "esp";
  doc["sensor_1"]    = serialized(String(s1,  3));
  doc["sensor_2"]    = serialized(String(s2,  3));
  doc["sensor_3"]    = serialized(String(s3,  3));
  doc["rms_overall"] = serialized(String(rms, 3));
  doc["status"]      = status;

  String payload;
  serializeJson(doc, payload);

  HTTPClient http;
  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(5000);

  int code = http.POST(payload);
  bool ok  = (code == 200 || code == 201);

  if (!ok) {
    Serial.print(F("[VIB] HTTP error: "));
    Serial.println(code > 0 ? String(code) : http.errorToString(code));
  }
  http.end();
  return ok;
}

// =============================================================
//  sendHeartbeat
//  POST keep-alive signal to /api/esp32.php?action=heartbeat
// =============================================================

void sendHeartbeat() {
  if (WiFi.status() != WL_CONNECTED) return;

  String url = String(SERVER_BASE) + "/api/esp32.php?action=heartbeat";

  StaticJsonDocument<128> doc;
  doc["device_id"]  = DEVICE_ID;
  doc["machine_id"] = MACHINE_ID;
  doc["ip_address"] = WiFi.localIP().toString();
  doc["rssi"]       = WiFi.RSSI();

  String payload;
  serializeJson(doc, payload);

  HTTPClient http;
  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  http.setTimeout(5000);

  int code = http.POST(payload);
  if (code > 0) {
    Serial.print(F("[HB] Heartbeat sent. HTTP "));
    Serial.print(code);
    Serial.print(F("  RSSI="));
    Serial.print(WiFi.RSSI());
    Serial.println(F(" dBm"));
  } else {
    Serial.print(F("[HB] ERROR: "));
    Serial.println(http.errorToString(code));
  }
  http.end();
}

// =============================================================
//  readVibrationRMS
//  Read <samples> ADC values from <pin>, compute RMS,
//  then scale from ADC range (0-4095) to mm/s (0-VIB_MAX_MMS).
// =============================================================

float readVibrationRMS(int pin, int samples) {
  double sumSq = 0.0;
  for (int i = 0; i < samples; i++) {
    int raw = analogRead(pin);           // 0 – 4095
    sumSq += (double)raw * (double)raw;
  }
  float rmsAdc = sqrt(sumSq / samples); // RMS in ADC counts
  // Scale to mm/s
  float mmPerS = (rmsAdc / ADC_MAX) * VIB_MAX_MMS;
  return mmPerS;
}

// =============================================================
//  calcRmsOverall
//  Combine three axis RMS values into one scalar.
// =============================================================

float calcRmsOverall(float s1, float s2, float s3) {
  return sqrt((s1 * s1 + s2 * s2 + s3 * s3) / 3.0f);
}

// =============================================================
//  printStatus
//  Pretty-print all sensor values to Serial.
// =============================================================

void printStatus(float vr, float vs, float vt,
                 float ar, float as_, float at,
                 float fr, float fs, float ft,
                 float er, float es, float et,
                 float temp, float hum) {
  Serial.println(F("--------------------------------------------------"));
  Serial.print(F("[SENSOR] Voltage  R="));  Serial.print(vr,  2);
  Serial.print(F(" V  S="));               Serial.print(vs,  2);
  Serial.print(F(" V  T="));               Serial.print(vt,  2);
  Serial.println(F(" V"));

  Serial.print(F("[SENSOR] Current  R="));  Serial.print(ar,  2);
  Serial.print(F(" A  S="));               Serial.print(as_, 2);
  Serial.print(F(" A  T="));               Serial.print(at,  2);
  Serial.println(F(" A"));

  Serial.print(F("[SENSOR] Freq     R="));  Serial.print(fr,  2);
  Serial.print(F(" Hz S="));              Serial.print(fs,  2);
  Serial.print(F(" Hz T="));              Serial.print(ft,  2);
  Serial.println(F(" Hz"));

  Serial.print(F("[SENSOR] Energy   R="));  Serial.print(er,  2);
  Serial.print(F(" kWh S="));             Serial.print(es,  2);
  Serial.print(F(" kWh T="));             Serial.print(et,  2);
  Serial.println(F(" kWh"));

  Serial.print(F("[SENSOR] Panel    Temp="));  Serial.print(temp, 2);
  Serial.print(F(" C  Hum="));                 Serial.print(hum,  2);
  Serial.println(F(" %"));
  Serial.println(F("--------------------------------------------------"));
}
