# Format API — ESP32 ke Web Server

## Base URL
`http://<IP_SERVER>/oee/api/`

---

## 1. Tower Lamp State (KinCony B16M)
**Endpoint:** `POST api/machine_state.php`
**Kirim setiap:** 1 detik (hanya jika state berubah, atau heartbeat 30d)

```json
{
  "machine_id": 1,
  "lamp_green":  1,
  "lamp_yellow": 0,
  "lamp_red":    0,
  "rtc_time":   "2026-06-10 08:30:00"
}
```
- `lamp_green=1, yellow=0, red=0` → **RUN**
- `lamp_yellow=1, red=0`          → **STANDBY**
- `lamp_red=1`                    → **STOP / EMERGENCY**
- Semua 0                         → **STOP**

**Response:**
```json
{ "success": true, "state": "run", "changed": true,
  "oee_rt": { "availability": 85.2, "performance": 92.0 } }
```

---

## 2. Sensor Data SHT20 + PZEM 6L24 (via RS485)
**Endpoint:** `POST api/sensors.php`
**Kirim setiap:** 60 detik

```json
{
  "machine_id": 1,
  "source": "esp32_mqtt",
  "v_r": 220.5, "v_s": 219.8, "v_t": 221.0,
  "a_r": 12.3,  "a_s": 11.9,  "a_t": 12.1,
  "f_r": 49.98, "f_s": 49.98, "f_t": 49.98,
  "e_r": 1024.5,"e_s": 1010.2,"e_t": 1018.7,
  "temp_panel": 35.2,
  "hum_panel":  65.0,

  "sht_temp":    32.5,
  "sht_hum":     68.2,

  "pzem_volt":   220.3,
  "pzem_curr":   12.45,
  "pzem_pwr":    2734.0,
  "pzem_energy": 1045.23,
  "pzem_pf":     0.993,
  "pzem_freq":   49.98
}
```

---

## 3. Vibration Portable — WitMotion WTV B02-485
**Endpoint:** `POST api/vibration_portable.php`
**Kirim setiap:** 2-5 detik (saat sedang mengukur)

```json
{
  "machine_id":   1,
  "sensor_num":   1,
  "session_id":   12,
  "sensor_label": "DE",
  "x":    1.234,
  "y":    0.876,
  "z":    2.103,
  "b":    0.541,
  "rms":  2.456,
  "temp": 28.5
}
```
- `sensor_num`: 1, 2, 3, atau 4 (tergantung sensor mana yang aktif)
- `b`: axis keempat dari WTV B02
- `rms`: dihitung di ESP32 dari SQRT((x²+y²+z²+b²)/4), atau server yang hitung jika tidak dikirim

**Rotasi 4 sensor (contoh Arduino loop):**
```cpp
for (int s = 1; s <= 4; s++) {
  switchRS485ToSensor(s);         // switch relay ke sensor s
  delay(500);
  VibData d = readWTVB02();       // baca via RS485
  d.sensor_num = s;
  d.session_id = activeSessionId;
  postToServer(d);                // POST ke api/vibration_portable.php
  delay(2000);
}
```

---

## 4. Increment Produksi (opsional, dari ESP32 sensor produk)
**Endpoint:** `POST api/shift_production.php`

```json
{
  "action":     "increment",
  "machine_id": 1,
  "qty":        1
}
```
Bisa dipanggil dari sensor photoelectric counter di conveyor.

---

## 5. Pengecekan Status Server (heartbeat ESP32)
**Endpoint:** `GET api/mqtt_status.php`

Response digunakan ESP32 untuk tahu server masih aktif.

---

## Mapping GPIO KinCony B16M → Tower Lamp

| GPIO PCF8575 U58 | Input | Lampu         | Field JSON    |
|------------------|-------|---------------|---------------|
| P0               | 1     | Hijau (RUN)   | `lamp_green`  |
| P1               | 2     | Kuning (STBY) | `lamp_yellow` |
| P2               | 3     | Merah (STOP)  | `lamp_red`    |

RTC DS3231 via I2C (GPIO38 SDA, GPIO39 SCL) → kirim sebagai `rtc_time`.
