<?php
// ============================================================
//  lines.php — Production Lines CRUD
//  URL: http://localhost/oee_api/lines.php
//  GET    /lines.php          → list all lines
//  POST   /lines.php          → create line   {name, description?}
//  PUT    /lines.php?id=N     → update line
//  DELETE /lines.php?id=N     → delete line
// ============================================================
require_once __DIR__ . '/config.php';

$db = getDB();
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

switch (method()) {

    // ── LIST ──────────────────────────────────────────────────────────────
    case 'GET':
        $rows = $db->query("
            SELECT l.*,
                   COUNT(m.id) AS machine_count
            FROM   production_lines l
            LEFT   JOIN machines m ON m.line_id = l.id
            GROUP  BY l.id
            ORDER  BY l.id
        ")->fetchAll();
        respond($rows);

    // ── CREATE ────────────────────────────────────────────────────────────
    case 'POST':
        $b = bodyJson();
        if (empty($b['name'])) respond(['error' => 'name is required'], 422);

        $st = $db->prepare("INSERT INTO production_lines (name, description) VALUES (?, ?)");
        $st->execute([$b['name'], $b['description'] ?? null]);
        $new = $db->query("SELECT * FROM production_lines WHERE id = " . $db->lastInsertId())->fetch();
        respond($new, 201);

    // ── UPDATE ────────────────────────────────────────────────────────────
    case 'PUT':
        if (!$id) respond(['error' => 'id required'], 422);
        $b = bodyJson();
        $db->prepare("UPDATE production_lines SET name=?, description=? WHERE id=?")
           ->execute([$b['name'], $b['description'] ?? null, $id]);
        respond(['success' => true]);

    // ── DELETE ────────────────────────────────────────────────────────────
    case 'DELETE':
        if (!$id) respond(['error' => 'id required'], 422);
        $db->prepare("DELETE FROM production_lines WHERE id=?")->execute([$id]);
        respond(['success' => true]);

    default:
        respond(['error' => 'Method not allowed'], 405);
}
