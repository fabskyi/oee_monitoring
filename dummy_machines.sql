-- ============================================================
--  Dummy Machine Data — PT. YADIN OEE Monitoring
--  Tambahkan production lines + mesin + data historis
-- ============================================================

SET NAMES utf8mb4;
START TRANSACTION;

-- ── 1. Production Lines tambahan ─────────────────────────────
INSERT INTO production_lines (name, description) VALUES
('Line C – Welding',    'Lini pengelasan komponen'),
('Line D – Finishing',  'Lini finishing & QC'),
('Line E – Assembly 2', 'Lini assembly lanjutan');

-- ── 2. Mesin baru ─────────────────────────────────────────────
-- Ambil ID line yang baru dibuat
SET @lcW = (SELECT id FROM production_lines WHERE name LIKE 'Line C%' LIMIT 1);
SET @lcF = (SELECT id FROM production_lines WHERE name LIKE 'Line D%' LIMIT 1);
SET @lcA = (SELECT id FROM production_lines WHERE name LIKE 'Line E%' LIMIT 1);
SET @lA  = 1;  -- Line A – Machining
SET @lB  = 2;  -- Line B – Assembly

INSERT INTO machines (line_id, name, model, status, sort_order) VALUES
-- Line A – Machining (tambahan)
(@lA, 'VESTA-800T',    'Hwacheon VESTA 800',   'run',  2),
(@lA, 'CUTEX-310',     'Hwacheon CUTEX 310',   'run',  3),
(@lA, 'CUTEX-410',     'Hwacheon CUTEX 410',   'stop', 4),
(@lA, 'SIRIUS-UZ 2',   'Hwacheon SIRIUS 600',  'run',  5),
-- Line B – Assembly (tambahan)
(@lB, 'HI-M G1 #2',    'Hwacheon HI-M 400',    'run',  2),
(@lB, 'HI-M SP',       'Hwacheon HI-M SP',     'run',  3),
(@lB, 'SPACE-5',       'Hwacheon SPACE-5',     'stop', 4),
-- Line C – Welding
(@lcW, 'WELD-01',      'Lincoln Electric 350', 'run',  1),
(@lcW, 'WELD-02',      'Lincoln Electric 350', 'run',  2),
(@lcW, 'WELD-03',      'Miller Dynasty 280',   'stop', 3),
(@lcW, 'WELD-04',      'Miller Dynasty 280',   'run',  4),
(@lcW, 'WELD-05',      'ESAB Rebel 235',       'run',  5),
-- Line D – Finishing
(@lcF, 'GRIND-01',     'STUDER S31',           'run',  1),
(@lcF, 'GRIND-02',     'STUDER S31',           'run',  2),
(@lcF, 'POLISH-01',    'Timesavers 42 Series', 'stop', 3),
(@lcF, 'PAINT-01',     'Nordson Sure Coat',    'run',  4),
(@lcF, 'PAINT-02',     'Nordson Sure Coat',    'run',  5),
(@lcF, 'INSPECT-01',   'Zeiss Contura',        'run',  6),
-- Line E – Assembly 2
(@lcA, 'ASSM-01',      'Custom Jig A',         'run',  1),
(@lcA, 'ASSM-02',      'Custom Jig A',         'run',  2),
(@lcA, 'ASSM-03',      'Custom Jig B',         'stop', 3),
(@lcA, 'ASSM-04',      'Custom Jig B',         'run',  4),
(@lcA, 'PRESS-01',     'Amada HFE 100',        'run',  5),
(@lcA, 'PRESS-02',     'Amada HFE 80',         'run',  6);

-- ── 3. OEE harian 14 hari (semua mesin baru) ─────────────────
-- Buat prosedur sementara lewat multi-insert untuk tiap tanggal
-- Mesin baru mulai dari ID 5 ke atas (4 mesin lama sudah ada)

INSERT INTO oee_daily (machine_id, snap_date, availability, performance, quality, planned_time, actual_run)
SELECT
    m.id,
    DATE_SUB(CURDATE(), INTERVAL d.n DAY),
    -- availability 70–98%
    ROUND(70 + (RAND() * 28), 2),
    -- performance  65–99%
    ROUND(65 + (RAND() * 34), 2),
    -- quality      88–99.9%
    ROUND(88 + (RAND() * 11.9), 2),
    480,
    ROUND(480 * (0.70 + RAND()*0.28))
FROM machines m
CROSS JOIN (
    SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
    UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9
    UNION SELECT 10 UNION SELECT 11 UNION SELECT 12 UNION SELECT 13
) d
WHERE m.id NOT IN (1,2,3,4)   -- skip mesin lama
ON DUPLICATE KEY UPDATE
    availability = VALUES(availability),
    performance  = VALUES(performance),
    quality      = VALUES(quality);

-- ── 4. Sensor readings terbaru (satu record per mesin baru) ──
INSERT INTO sensor_readings
    (machine_id, v_r, v_s, v_t, a_r, a_s, a_t, f_r, f_s, f_t,
     e_r, e_s, e_t, temp_panel, hum_panel, source, recorded_at)
SELECT
    m.id,
    ROUND(208 + RAND()*20, 1),   -- v_r
    ROUND(208 + RAND()*20, 1),   -- v_s
    ROUND(208 + RAND()*20, 1),   -- v_t
    ROUND(4   + RAND()*18, 2),   -- a_r
    ROUND(4   + RAND()*18, 2),   -- a_s
    ROUND(4   + RAND()*18, 2),   -- a_t
    ROUND(49.8 + RAND()*0.4, 3), -- f_r
    ROUND(49.8 + RAND()*0.4, 3), -- f_s
    ROUND(49.8 + RAND()*0.4, 3), -- f_t
    ROUND(0.5  + RAND()*4, 2),   -- e_r
    ROUND(0.5  + RAND()*4, 2),   -- e_s
    ROUND(0.5  + RAND()*4, 2),   -- e_t
    ROUND(28   + RAND()*22, 1),  -- temp_panel
    ROUND(40   + RAND()*30, 1),  -- hum_panel
    'manual',
    NOW() - INTERVAL FLOOR(RAND()*30) MINUTE
FROM machines m
WHERE m.id NOT IN (1,2,3,4);

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
WHERE m.id NOT IN (1,2,3,4);

COMMIT;
