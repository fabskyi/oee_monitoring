# OEE Monitoring System — Setup Guide
## Stack: XAMPP (MySQL + PHP) + React

---

## 📁 File Structure

```
oee_system/
├── sql/
│   └── oee_database.sql        ← Import ke phpMyAdmin
├── api/                        ← Salin ke C:\xampp\htdocs\oee_api\
│   ├── config.php
│   ├── lines.php
│   ├── machines.php
│   ├── sensors.php
│   ├── alerts.php
│   └── maintenance.php
└── OEE_Dashboard.jsx           ← React component (src/App.jsx)
```

---

## 🚀 LANGKAH SETUP

### STEP 1 — Import Database

1. Buka **XAMPP Control Panel** → Start **Apache** dan **MySQL**
2. Buka browser → `http://localhost/phpmyadmin`
3. Klik **Import** → pilih file `sql/oee_database.sql`
4. Klik **Go** → database `oee_monitoring` akan terbuat otomatis

---

### STEP 2 — Copy PHP API ke XAMPP

1. Buat folder baru: `C:\xampp\htdocs\oee_api\`
2. Salin semua file dari folder `api/` ke dalam folder tersebut:
   ```
   C:\xampp\htdocs\oee_api\
   ├── config.php
   ├── lines.php
   ├── machines.php
   ├── sensors.php
   ├── alerts.php
   └── maintenance.php
   ```
3. Test di browser: `http://localhost/oee_api/lines.php`
   → Harus tampil JSON dengan data lines

---

### STEP 3 — Setup React App

```bash
# Buat project React baru (jika belum ada)
npm create vite@latest oee-dashboard -- --template react
cd oee-dashboard

# Install dependencies
npm install

# Salin OEE_Dashboard.jsx ke src/App.jsx
# (ganti isi App.jsx dengan isi OEE_Dashboard.jsx)

# Jalankan
npm run dev
```

Buka browser: `http://localhost:5173`

---

### STEP 4 — Konfigurasi CORS (jika perlu)

Edit `C:\xampp\htdocs\oee_api\config.php`:
```php
$allowed_origins = [
    'http://localhost:5173',   // React Vite dev server
    'http://localhost:3000',   // Create React App
];
```

---

## 🔌 Integrasi ESP (Local Host)

ESP Anda tinggal POST ke endpoint:

```
POST http://192.168.x.x/oee_api/sensors.php
Content-Type: application/json

{
  "machine_id": 1,
  "v_r": 220.5, "v_s": 221.0, "v_t": 219.8,
  "a_r": 12.4,  "a_s": 12.1,  "a_t": 12.7,
  "f_r": 50.0,  "f_s": 50.1,  "f_t": 49.9,
  "e_r": 430,   "e_s": 428,   "e_t": 432,
  "temp_panel": 38.0,
  "hum_panel":  62.0,
  "source": "esp"
}
```

Ganti `192.168.x.x` dengan IP komputer XAMPP Anda di jaringan lokal.

Response sukses:
```json
{
  "success": true,
  "reading_id": 42,
  "alerts_created": 0
}
```

---

## 📊 Tabel Database

| Tabel | Fungsi |
|---|---|
| `production_lines` | Data line produksi |
| `machines` | Data mesin + status + gambar |
| `oee_settings` | Target OEE per mesin |
| `sensor_readings` | Data sensor real-time (time-series) |
| `sensor_thresholds` | Batas normal sensor per mesin |
| `alerts` | Log alert otomatis saat sensor abnormal |
| `maintenance_records` | Riwayat maintenance |
| `oee_daily` | Snapshot OEE harian |

---

## 🔗 API Endpoints

| Method | URL | Fungsi |
|---|---|---|
| GET | `/lines.php` | List semua line |
| POST | `/lines.php` | Tambah line |
| DELETE | `/lines.php?id=N` | Hapus line |
| GET | `/machines.php` | List semua mesin + sensor terakhir |
| POST | `/machines.php` | Tambah mesin |
| PUT | `/machines.php?id=N` | Update mesin/status |
| DELETE | `/machines.php?id=N` | Hapus mesin |
| POST | `/sensors.php` | Input data sensor (dari ESP) |
| GET | `/sensors.php?machine_id=N` | Data sensor terakhir |
| GET | `/alerts.php?line_id=N` | Alert aktif per line |
| DELETE | `/alerts.php` | Hapus semua alert |
| POST | `/maintenance.php` | Tambah record maintenance |
| DELETE | `/maintenance.php?id=N` | Hapus record maintenance |

---

## ⚙️ Troubleshooting

**"Cannot connect to API"**
→ Pastikan XAMPP Apache & MySQL sudah running
→ Cek folder ada di `C:\xampp\htdocs\oee_api\`

**CORS Error di browser**
→ Edit `config.php`, tambahkan origin React Anda ke `$allowed_origins`

**phpMyAdmin tidak bisa import**
→ Cek `php.ini` → naikkan `upload_max_filesize` dan `post_max_size`

**ESP tidak bisa POST ke XAMPP**
→ Pastikan ESP dan komputer di jaringan WiFi yang sama
→ Gunakan IP lokal komputer (cek dengan `ipconfig`)
→ Disable Windows Firewall untuk port 80

---

## 📡 Polling Interval

Dashboard React otomatis refresh data setiap **5 detik** dari database.
Untuk mengubah interval, edit `OEE_Dashboard.jsx`:
```javascript
const iv1 = setInterval(pollSensors, 5000); // ubah 5000 → ms yang diinginkan
```
