-- ============================================================
--  Migration V2 — OEE System Upgrade
--  Tower Lamp State | Shift Production | Extended Sensors
--  Portable Vibration (4x WTV B02-485)
-- ============================================================

-- ── 1. Extend sensor_readings: SHT20 + PZEM 6L24 ────────────
ALTER TABLE sensor_readings
    ADD COLUMN IF NOT EXISTS sht_temp    DECIMAL(6,2)  DEFAULT NULL COMMENT 'SHT20 Suhu °C',
    ADD COLUMN IF NOT EXISTS sht_hum     DECIMAL(6,2)  DEFAULT NULL COMMENT 'SHT20 Kelembaban %',
    ADD COLUMN IF NOT EXISTS pzem_volt   DECIMAL(7,2)  DEFAULT NULL COMMENT 'PZEM Tegangan V',
    ADD COLUMN IF NOT EXISTS pzem_curr   DECIMAL(8,3)  DEFAULT NULL COMMENT 'PZEM Arus A',
    ADD COLUMN IF NOT EXISTS pzem_pwr    DECIMAL(10,2) DEFAULT NULL COMMENT 'PZEM Daya W',
    ADD COLUMN IF NOT EXISTS pzem_energy DECIMAL(12,3) DEFAULT NULL COMMENT 'PZEM Energi kWh',
    ADD COLUMN IF NOT EXISTS pzem_pf     DECIMAL(5,3)  DEFAULT NULL COMMENT 'PZEM Power Factor',
    ADD COLUMN IF NOT EXISTS pzem_freq   DECIMAL(5,2)  DEFAULT NULL COMMENT 'PZEM Frekuensi Hz';

-- ── 2. Extend vibration_readings: 4 sensor portable ──────────
ALTER TABLE vibration_readings
    ADD COLUMN IF NOT EXISTS sensor_num  TINYINT        DEFAULT 1    COMMENT 'Nomor sensor portable (1-4)',
    ADD COLUMN IF NOT EXISTS axis_b      DECIMAL(8,4)   DEFAULT NULL COMMENT 'Axis B mm/s (WTV B02)',
    ADD COLUMN IF NOT EXISTS temp_sensor DECIMAL(6,2)   DEFAULT NULL COMMENT 'Suhu internal sensor °C',
    ADD COLUMN IF NOT EXISTS session_id  INT            DEFAULT NULL COMMENT 'FK vibration_sessions';

-- ── 3. State mesin dari Tower Lamp ───────────────────────────
CREATE TABLE IF NOT EXISTS machine_states (
    id          BIGINT AUTO_INCREMENT PRIMARY KEY,
    machine_id  INT NOT NULL,
    state       ENUM('run','standby','stop','emergency') NOT NULL DEFAULT 'stop',
    lamp_green  TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Lampu hijau (RUN)',
    lamp_yellow TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Lampu kuning (STANDBY)',
    lamp_red    TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Lampu merah (STOP/EMERGENCY)',
    recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    source      VARCHAR(50) DEFAULT 'tower_lamp',
    INDEX idx_ms_machine_time (machine_id, recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 4. Produksi per shift (Quality OEE) ──────────────────────
CREATE TABLE IF NOT EXISTS shift_production (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    machine_id    INT NOT NULL,
    shift_date    DATE NOT NULL,
    shift_no      TINYINT NOT NULL DEFAULT 1 COMMENT '1=Pagi 2=Siang 3=Malam',
    shift_start   TIME NOT NULL DEFAULT '07:00:00',
    shift_end     TIME NOT NULL DEFAULT '15:00:00',
    plan_qty      INT NOT NULL DEFAULT 0 COMMENT 'Target produksi shift',
    total_out     INT NOT NULL DEFAULT 0 COMMENT 'Total produk keluar (akumulasi)',
    total_reject  INT NOT NULL DEFAULT 0 COMMENT 'Reject diinput akhir shift',
    good_count    INT NOT NULL DEFAULT 0 COMMENT 'total_out - total_reject',
    operator_name VARCHAR(100) DEFAULT NULL,
    notes         TEXT DEFAULT NULL,
    is_closed     TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=shift sudah ditutup',
    input_by      INT DEFAULT NULL,
    input_at      DATETIME DEFAULT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_machine_shift (machine_id, shift_date, shift_no),
    INDEX idx_sp_date (shift_date, machine_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 5. Session vibration portable ────────────────────────────
CREATE TABLE IF NOT EXISTS vibration_sessions (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    machine_id  INT NOT NULL,
    sensor_num  TINYINT NOT NULL DEFAULT 1 COMMENT 'Sensor ke-1 s.d. 4',
    sensor_label VARCHAR(50) DEFAULT NULL COMMENT 'Label titik ukur (mis: DE, NDE, Fan)',
    started_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at    DATETIME DEFAULT NULL,
    reading_count INT NOT NULL DEFAULT 0,
    notes       VARCHAR(255) DEFAULT NULL,
    created_by  INT DEFAULT NULL,
    INDEX idx_vs_machine (machine_id, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 6. Konfigurasi shift per mesin ───────────────────────────
CREATE TABLE IF NOT EXISTS shift_config (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    machine_id  INT NOT NULL,
    shift_no    TINYINT NOT NULL DEFAULT 1,
    shift_name  VARCHAR(50) DEFAULT 'Shift 1',
    start_time  TIME NOT NULL DEFAULT '07:00:00',
    end_time    TIME NOT NULL DEFAULT '15:00:00',
    plan_qty    INT NOT NULL DEFAULT 0 COMMENT 'Target default per shift',
    is_active   TINYINT(1) DEFAULT 1,
    UNIQUE KEY uq_sc (machine_id, shift_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 7. Insert config shift default untuk semua mesin ─────────
INSERT IGNORE INTO shift_config (machine_id, shift_no, shift_name, start_time, end_time, plan_qty)
SELECT id, 1, 'Shift Pagi',  '07:00:00', '15:00:00', 0 FROM machines
UNION ALL
SELECT id, 2, 'Shift Siang', '15:00:00', '23:00:00', 0 FROM machines
UNION ALL
SELECT id, 3, 'Shift Malam', '23:00:00', '07:00:00', 0 FROM machines;
