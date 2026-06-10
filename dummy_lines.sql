-- ============================================================
--  Tambah Line F–T (15 line baru) + 4 mesin per line
-- ============================================================
SET NAMES utf8mb4;
START TRANSACTION;

-- ── 1. Tambah Production Lines ────────────────────────────────
INSERT INTO production_lines (name, description) VALUES
('Line F – Turning',      'Lini turning CNC'),
('Line G – Milling',      'Lini milling presisi'),
('Line H – Drilling',     'Lini pengeboran komponen'),
('Line I – Grinding',     'Lini grinding halus'),
('Line J – Boring',       'Lini boring silinder'),
('Line K – Broaching',    'Lini broaching profil'),
('Line L – Pressing',     'Lini press hidrolik'),
('Line M – Casting',      'Lini pengecoran aluminium'),
('Line N – Forging',      'Lini tempa komponen'),
('Line O – Heat Treat',   'Lini perlakuan panas'),
('Line P – Coating',      'Lini coating & plating'),
('Line Q – Sub-Assembly', 'Lini sub-assembly unit'),
('Line R – Testing',      'Lini pengujian fungsi'),
('Line S – Packaging',    'Lini pengemasan produk'),
('Line T – Maintenance',  'Lini MRO & overhaul');

-- ── 2. Tambah 4 mesin per line baru ──────────────────────────
INSERT INTO machines (line_id, name, model, status, sort_order)
SELECT
    pl.id,
    CONCAT(SUBSTRING(pl.name, 6, 1), '-CNC-0', n.num),
    CASE n.num
        WHEN 1 THEN 'Hwacheon VESTA 500'
        WHEN 2 THEN 'Hwacheon CUTEX 250'
        WHEN 3 THEN 'Hwacheon HI-M 300'
        WHEN 4 THEN 'Hwacheon SIRIUS 400'
    END,
    CASE WHEN RAND() > 0.25 THEN 'run' ELSE 'stop' END,
    n.num
FROM production_lines pl
CROSS JOIN (SELECT 1 num UNION SELECT 2 UNION SELECT 3 UNION SELECT 4) n
WHERE pl.id > 5;

-- ── 3. OEE harian 14 hari untuk semua mesin baru ─────────────
INSERT INTO oee_daily (machine_id, snap_date, availability, performance, quality, planned_time, actual_run)
SELECT
    m.id,
    DATE_SUB(CURDATE(), INTERVAL d.n DAY),
    ROUND(68 + (RAND() * 30), 2),
    ROUND(65 + (RAND() * 34), 2),
    ROUND(88 + (RAND() * 11.9), 2),
    480,
    ROUND(480 * (0.68 + RAND()*0.30))
FROM machines m
CROSS JOIN (
    SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
    UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9
    UNION SELECT 10 UNION SELECT 11 UNION SELECT 12 UNION SELECT 13
) d
WHERE m.id NOT IN (SELECT id FROM machines WHERE id <= (SELECT MAX(id) FROM machines WHERE line_id <= 5))
ON DUPLICATE KEY UPDATE
    availability = VALUES(availability),
    performance  = VALUES(performance),
    quality      = VALUES(quality);

-- ── 4. Sensor readings terbaru ───────────────────────────────
INSERT INTO sensor_readings
    (machine_id, v_r, v_s, v_t, a_r, a_s, a_t, f_r, f_s, f_t,
     e_r, e_s, e_t, temp_panel, hum_panel, source, recorded_at)
SELECT
    m.id,
    ROUND(208 + RAND()*20, 1),
    ROUND(208 + RAND()*20, 1),
    ROUND(208 + RAND()*20, 1),
    ROUND(4   + RAND()*18, 2),
    ROUND(4   + RAND()*18, 2),
    ROUND(4   + RAND()*18, 2),
    ROUND(49.8 + RAND()*0.4, 3),
    ROUND(49.8 + RAND()*0.4, 3),
    ROUND(49.8 + RAND()*0.4, 3),
    ROUND(0.5  + RAND()*4, 2),
    ROUND(0.5  + RAND()*4, 2),
    ROUND(0.5  + RAND()*4, 2),
    ROUND(28   + RAND()*22, 1),
    ROUND(40   + RAND()*30, 1),
    'manual',
    NOW() - INTERVAL FLOOR(RAND()*30) MINUTE
FROM machines m
WHERE m.line_id > 5;

-- ── 5. Vibration readings terbaru ────────────────────────────
INSERT INTO vibration_readings
    (machine_id, sensor_1, sensor_2, sensor_3, rms_overall, status, source, recorded_at)
SELECT
    m.id,
    ROUND(0.3 + RAND()*5.5, 3),
    ROUND(0.3 + RAND()*5.5, 3),
    ROUND(0.3 + RAND()*5.5, 3),
    ROUND(0.5 + RAND()*6.5, 3),
    CASE
        WHEN RAND() < 0.60 THEN 'normal'
        WHEN RAND() < 0.80 THEN 'warning'
        ELSE 'critical'
    END,
    'manual',
    NOW() - INTERVAL FLOOR(RAND()*45) MINUTE
FROM machines m
WHERE m.line_id > 5;

COMMIT;
