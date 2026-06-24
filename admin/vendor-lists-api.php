<?php
/**
 * vendor-lists-api.php - ADMIN-ONLY endpoint to manage the two predefined lists
 * the Vendor Database draws from: Vendor Types and Coverage Areas.
 *
 * This file lives in /admin/, which is protected by its own cPanel Directory
 * Privacy realm. Staff using the /vendors/ app are in a DIFFERENT realm and can
 * never reach this endpoint - that separation is the whole role model. The
 * staff API (vendors/api/api.php) treats both lists as read-only.
 *
 * It writes to the SAME SQLite database as the staff app, by requiring the
 * shared db.php from the vendors tree.
 *
 * Routed by:  ?list=vendor_type|coverage_area & action=add|rename|delete|reorder
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/vendors/api/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond($payload, $status = 200)
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function fail($message, $status = 400)
{
    respond(array('ok' => false, 'error' => $message), $status);
}

function body_json()
{
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) {
        return array();
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : array();
}

/** Map the list key to its table + map table + map column. */
function list_meta($listKey)
{
    switch ($listKey) {
        case 'vendor_type':
            return array('table' => 'vendor_types',   'map' => 'vendor_type_map', 'col' => 'type_id');
        case 'coverage_area':
            return array('table' => 'coverage_areas', 'map' => 'vendor_area_map', 'col' => 'area_id');
        default:
            return null;
    }
}

try {
    $pdo  = vdb_connect();
    $meta = list_meta(isset($_GET['list']) ? $_GET['list'] : '');
    if ($meta === null) {
        fail('Unknown list. Use list=vendor_type or list=coverage_area.', 404);
    }
    $table  = $meta['table'];
    $map    = $meta['map'];
    $col    = $meta['col'];
    $action = isset($_GET['action']) ? $_GET['action'] : 'get';

    switch ($action) {
        case 'get':
            respond(array('ok' => true, 'items' => list_items($pdo, $table)));
            break;

        case 'add':
            $b    = body_json();
            $name = isset($b['name']) ? trim($b['name']) : '';
            if ($name === '') {
                fail('Name is required.');
            }
            if (name_exists($pdo, $table, $name, 0)) {
                fail('An item with that name already exists.', 409);
            }
            $maxSort = (int) $pdo->query('SELECT COALESCE(MAX(sort), -1) FROM ' . $table)->fetchColumn();
            $stmt = $pdo->prepare('INSERT INTO ' . $table . ' (name, sort) VALUES (?, ?)');
            $stmt->execute(array($name, $maxSort + 1));
            respond(array('ok' => true, 'items' => list_items($pdo, $table)));
            break;

        case 'rename':
            $b    = body_json();
            $id   = isset($b['id']) ? (int) $b['id'] : 0;
            $name = isset($b['name']) ? trim($b['name']) : '';
            if ($id <= 0 || $name === '') {
                fail('id and name are required.');
            }
            if (name_exists($pdo, $table, $name, $id)) {
                fail('Another item already uses that name.', 409);
            }
            $stmt = $pdo->prepare('UPDATE ' . $table . ' SET name = ? WHERE id = ?');
            $stmt->execute(array($name, $id));
            respond(array('ok' => true, 'items' => list_items($pdo, $table)));
            break;

        case 'delete':
            $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
            if ($id <= 0) {
                fail('Missing id.');
            }
            // Cascade clears the map rows via FK, so deleting an in-use item just
            // unassigns it from vendors. We require an explicit confirm flag so the
            // UI can warn first with the usage count.
            $confirm = isset($_GET['confirm']) && $_GET['confirm'] === '1';
            $count   = usage_count($pdo, $map, $col, $id);
            if ($count > 0 && !$confirm) {
                respond(array(
                    'ok'           => false,
                    'needsConfirm' => true,
                    'usageCount'   => $count,
                    'error'        => 'In use by ' . $count . ' vendor(s). Re-send with confirm=1 to remove it from them.',
                ), 409);
            }
            $pdo->prepare('DELETE FROM ' . $table . ' WHERE id = ?')->execute(array($id));
            respond(array('ok' => true, 'items' => list_items($pdo, $table)));
            break;

        case 'reorder':
            // Body: { order: [id, id, id, ...] } in the desired display order.
            $b     = body_json();
            $order = isset($b['order']) && is_array($b['order']) ? $b['order'] : array();
            if (empty($order)) {
                fail('order array is required.');
            }
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare('UPDATE ' . $table . ' SET sort = ? WHERE id = ?');
                $i = 0;
                foreach ($order as $rawId) {
                    $stmt->execute(array($i, (int) $rawId));
                    $i++;
                }
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
            respond(array('ok' => true, 'items' => list_items($pdo, $table)));
            break;

        default:
            fail('Unknown action.', 404);
    }
} catch (Throwable $e) {
    error_log('vendor-lists-api error: ' . $e->getMessage());
    // This endpoint is behind the admin password, so it is safe to return the
    // real message to help diagnose setup problems on the live server.
    respond(array('ok' => false, 'error' => 'Server error: ' . $e->getMessage()), 500);
}

/** All items in a list, in display order, each with its current usage count. */
function list_items(PDO $pdo, $table)
{
    $meta  = ($table === 'vendor_types')
        ? array('map' => 'vendor_type_map', 'col' => 'type_id')
        : array('map' => 'vendor_area_map', 'col' => 'area_id');
    $rows = $pdo->query('SELECT id, name, sort FROM ' . $table . ' ORDER BY sort, name')->fetchAll();
    foreach ($rows as &$row) {
        $row['usageCount'] = usage_count($pdo, $meta['map'], $meta['col'], (int) $row['id']);
    }
    unset($row);
    return $rows;
}

function usage_count(PDO $pdo, $map, $col, $id)
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM ' . $map . ' WHERE ' . $col . ' = ?');
    $stmt->execute(array($id));
    return (int) $stmt->fetchColumn();
}

/** Case-insensitive name existence check, optionally excluding one id. */
function name_exists(PDO $pdo, $table, $name, $excludeId)
{
    $sql  = 'SELECT 1 FROM ' . $table . ' WHERE name = ? COLLATE NOCASE';
    $args = array($name);
    if ($excludeId > 0) {
        $sql   .= ' AND id != ?';
        $args[] = $excludeId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);
    return (bool) $stmt->fetchColumn();
}
