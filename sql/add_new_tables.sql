-- OEE System Expansion: New Tables
-- Generated: 2026-06-08

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','operator','viewer') DEFAULT 'operator',
  is_active TINYINT(1) DEFAULT 1,
  last_login TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS vibration_readings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  machine_id INT UNSIGNED NOT NULL,
  sensor_1 DECIMAL(8,3) COMMENT 'mm/s RMS Sensor 1',
  sensor_2 DECIMAL(8,3) COMMENT 'mm/s RMS Sensor 2',
  sensor_3 DECIMAL(8,3) COMMENT 'mm/s RMS Sensor 3',
  rms_overall DECIMAL(8,3),
  status ENUM('normal','warning','critical') DEFAULT 'normal',
  source VARCHAR(50) DEFAULT 'esp',
  recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_machine_time (machine_id, recorded_at),
  FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS system_settings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) NOT NULL UNIQUE,
  setting_value TEXT,
  description VARCHAR(255),
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS esp32_devices (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  device_id VARCHAR(50) NOT NULL UNIQUE,
  machine_id INT UNSIGNED NULL,
  ip_address VARCHAR(45),
  mac_address VARCHAR(17),
  firmware_version VARCHAR(20),
  last_seen TIMESTAMP NULL,
  status ENUM('online','offline') DEFAULT 'offline',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Default admin user (password: admin123)
INSERT INTO users (username, full_name, email, password_hash, role) VALUES
('admin', 'Administrator', 'admin@oee.local', '$2y$10$B6REHmdHp5oI4cGwqdLdu.dPaZukmKogmwbCijPBZjymKu6UGuypW', 'admin');

-- Default system settings
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('mqtt_broker',                 '',                     'MQTT broker hostname or IP address'),
('mqtt_port',                   '1883',                 'MQTT broker port'),
('mqtt_topic',                  'oee/vibration',        'MQTT topic for vibration data'),
('vibration_warning_threshold', '2.8',                  'Vibration warning threshold in mm/s RMS'),
('vibration_critical_threshold','7.1',                  'Vibration critical threshold in mm/s RMS'),
('oee_target',                  '85',                   'OEE target percentage'),
('data_retention_days',         '90',                   'Number of days to retain historical data'),
('site_name',                   'OEE Monitoring System','Site display name'),
('company_name',                'PT. YADIN',            'Company name');
