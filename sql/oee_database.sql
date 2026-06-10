-- ============================================================
--  OEE MONITORING SYSTEM — DATABASE SCHEMA
--  Compatible with: MySQL 5.7+ / MariaDB 10.3+ (XAMPP)
--  Created for: Hwacheon Real-time OEE Dashboard
-- ============================================================

CREATE DATABASE IF NOT EXISTS oee_monitoring
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE oee_monitoring;

-- ============================================================
-- 1. PRODUCTION LINES
-- ============================================================
CREATE TABLE IF NOT EXISTS production_lines (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100) NOT NULL,
  description TEXT,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- 2. MACHINES
-- ============================================================
CREATE TABLE IF NOT EXISTS machines (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  line_id     INT UNSIGNED NOT NULL,
  name        VARCHAR(100) NOT NULL,
  model       VARCHAR(100),
  status      ENUM('run','stop') DEFAULT 'stop',
  image_base64 LONGTEXT,          -- stores base64 image string
  sort_order  INT UNSIGNED DEFAULT 0,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (line_id) REFERENCES production_lines(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 3. OEE SETTINGS (per machine — editable targets)
-- ============================================================
CREATE TABLE IF NOT EXISTS oee_settings (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  machine_id   INT UNSIGNED NOT NULL UNIQUE,
  availability TINYINT UNSIGNED DEFAULT 85 COMMENT 'percent 0-100',
  performance  TINYINT UNSIGNED DEFAULT 85,
  quality      TINYINT UNSIGNED DEFAULT 95,
  updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 4. SENSOR READINGS (time-series — inserted by ESP / local API)
-- ============================================================
CREATE TABLE IF NOT EXISTS sensor_readings (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  machine_id   INT UNSIGNED NOT NULL,
  -- Voltage
  v_r          DECIMAL(7,2),
  v_s          DECIMAL(7,2),
  v_t          DECIMAL(7,2),
  -- Current (Ampere)
  a_r          DECIMAL(7,2),
  a_s          DECIMAL(7,2),
  a_t          DECIMAL(7,2),
  -- Frequency
  f_r          DECIMAL(7,3),
  f_s          DECIMAL(7,3),
  f_t          DECIMAL(7,3),
  -- Energy (kWh)
  e_r          DECIMAL(10,2),
  e_s          DECIMAL(10,2),
  e_t          DECIMAL(10,2),
  -- Environment
  temp_panel   DECIMAL(5,2),
  hum_panel    DECIMAL(5,2),
  -- Metadata
  source       VARCHAR(50) DEFAULT 'esp' COMMENT 'esp | manual | simulator',
  recorded_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_machine_time (machine_id, recorded_at),
  FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 5. ALERTS (auto-generated when sensor out of threshold)
-- ============================================================
CREATE TABLE IF NOT EXISTS alerts (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  machine_id   INT UNSIGNED NOT NULL,
  sensor_key   VARCHAR(30) NOT NULL  COMMENT 'v_r, a_s, temp_panel, etc.',
  sensor_value DECIMAL(10,3),
  threshold_lo DECIMAL(10,3),
  threshold_hi DECIMAL(10,3),
  severity     ENUM('warning','critical') DEFAULT 'warning',
  acknowledged TINYINT(1) DEFAULT 0,
  acknowledged_by VARCHAR(80),
  acknowledged_at TIMESTAMP NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_machine   (machine_id),
  INDEX idx_ack       (acknowledged),
  FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 6. SENSOR THRESHOLDS (per machine, configurable)
-- ============================================================
CREATE TABLE IF NOT EXISTS sensor_thresholds (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  machine_id  INT UNSIGNED NOT NULL,
  sensor_key  VARCHAR(30) NOT NULL,
  thresh_lo   DECIMAL(10,3) NOT NULL,
  thresh_hi   DECIMAL(10,3) NOT NULL,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_machine_sensor (machine_id, sensor_key),
  FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 7. MAINTENANCE RECORDS
-- ============================================================
CREATE TABLE IF NOT EXISTS maintenance_records (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  machine_id  INT UNSIGNED NOT NULL,
  type        ENUM('preventive','corrective','breakdown','inspection') DEFAULT 'preventive',
  description TEXT NOT NULL,
  technician  VARCHAR(100),
  maint_date  DATE NOT NULL,
  duration_min INT UNSIGNED COMMENT 'downtime in minutes',
  cost        DECIMAL(12,2),
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_machine (machine_id),
  INDEX idx_date    (maint_date),
  FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 8. OEE DAILY SNAPSHOT (for historical chart)
-- ============================================================
CREATE TABLE IF NOT EXISTS oee_daily (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  machine_id   INT UNSIGNED NOT NULL,
  snap_date    DATE NOT NULL,
  availability DECIMAL(5,2),
  performance  DECIMAL(5,2),
  quality      DECIMAL(5,2),
  oee_score    DECIMAL(5,2) AS (ROUND(availability * performance * quality / 10000, 2)) STORED,
  planned_time INT UNSIGNED COMMENT 'minutes',
  actual_run   INT UNSIGNED COMMENT 'minutes',
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_machine_date (machine_id, snap_date),
  FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- SEED DATA — Default Lines & Machines
-- ============================================================
INSERT INTO production_lines (name, description) VALUES
  ('Line A – Machining', 'CNC and turning machines'),
  ('Line B – Assembly',  'Assembly and quality check');

INSERT INTO machines (line_id, name, model, status, sort_order) VALUES
  (1, 'SIRIUS-UZ',  'CNC-5AX',  'run',  1),
  (1, 'VESTA-650T', 'TURN-650', 'run',  2),
  (2, 'HI-M G1',   'MILL-G1',  'stop', 1);

INSERT INTO oee_settings (machine_id, availability, performance, quality) VALUES
  (1, 92, 88, 95),
  (2, 80, 75, 90),
  (3, 70, 82, 97);

-- Default thresholds for each machine
INSERT INTO sensor_thresholds (machine_id, sensor_key, thresh_lo, thresh_hi)
SELECT m.id, t.sensor_key, t.lo, t.hi
FROM machines m
CROSS JOIN (
  SELECT 'v_r'        AS sensor_key, 195 AS lo, 240 AS hi UNION ALL
  SELECT 'v_s',  195, 240 UNION ALL SELECT 'v_t',  195, 240 UNION ALL
  SELECT 'a_r',    0,  20 UNION ALL SELECT 'a_s',    0,  20 UNION ALL SELECT 'a_t',    0,  20 UNION ALL
  SELECT 'f_r',   49,  51 UNION ALL SELECT 'f_s',   49,  51 UNION ALL SELECT 'f_t',   49,  51 UNION ALL
  SELECT 'e_r',    0, 600 UNION ALL SELECT 'e_s',    0, 600 UNION ALL SELECT 'e_t',    0, 600 UNION ALL
  SELECT 'temp_panel', 0, 50 UNION ALL
  SELECT 'hum_panel',  0, 75
) t;

-- Seed some sensor readings (last 5 minutes of fake data)
INSERT INTO sensor_readings (machine_id, v_r, v_s, v_t, a_r, a_s, a_t, f_r, f_s, f_t, e_r, e_s, e_t, temp_panel, hum_panel, source) VALUES
  (1, 220.1, 221.0, 219.5, 12.4, 12.1, 12.7, 50.0, 50.1, 49.9, 430, 428, 432, 38.0, 62.0, 'simulator'),
  (2, 215.0, 240.0, 218.0, 18.0, 17.5, 18.3, 50.2, 50.0, 49.8, 520, 518, 522, 55.0, 80.0, 'simulator'),
  (3, 221.0, 220.0, 222.0,  9.0,  9.1,  8.9, 50.0, 50.0, 50.1, 310, 312, 308, 35.0, 58.0, 'simulator');

INSERT INTO oee_daily (machine_id, snap_date, availability, performance, quality, planned_time, actual_run) VALUES
  (1, CURDATE(), 92, 88, 95, 480, 441),
  (2, CURDATE(), 80, 75, 90, 480, 384),
  (3, CURDATE(), 70, 82, 97, 480, 336);

SELECT 'Database oee_monitoring created and seeded successfully!' AS status;
