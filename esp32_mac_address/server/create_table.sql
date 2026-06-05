-- Jalankan di phpMyAdmin → database inventory_db_clone → tab SQL

CREATE TABLE IF NOT EXISTS `sensor_data` (
  `id`          INT(11)       NOT NULL AUTO_INCREMENT,
  `device_id`   VARCHAR(50)   NOT NULL DEFAULT 'ESP32-001',
  `temperature` FLOAT         NOT NULL COMMENT 'Celcius',
  `humidity`    FLOAT         NOT NULL COMMENT 'Persen %',
  `voltage`     FLOAT         NOT NULL COMMENT 'Volt',
  `current`     FLOAT         NOT NULL COMMENT 'Ampere',
  `counter`     INT(11)       NOT NULL DEFAULT 0,
  `status`      VARCHAR(10)   NOT NULL DEFAULT 'OFF',
  `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
