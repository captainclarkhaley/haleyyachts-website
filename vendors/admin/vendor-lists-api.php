<?php
/**
 * vendor-lists-api.php (Broker Suite admin copy) - ADMIN-ONLY endpoint to manage
 * the two predefined lists the Vendor Database draws from: Vendor Types and
 * Coverage Areas.
 *
 * RELOCATED from /admin/vendor-lists-api.php as part of Phase 2c. The original
 * still lives under /admin/ and keeps working until 2d retires it; this copy is
 * the in-app version, gated by the SAME session + is_admin flag the rest of the
 * Broker Suite uses instead of the old /admin/ Directory Privacy realm.
 *
 * AUTH MODEL (the only real change vs. the original): this is an API endpoint,
 * so it does NOT redirect. It requires a valid in-app session (require_auth ->
 * 401 JSON), blocks a user who still owes a forced password change (403
 * must_change), and then requires is_admin === 1 (403 'Administrators only.').
 * The admin check is enforced from the SESSION user ($authUser), never from the
 * request. Every action runs only for an authenticated admin. Unlike the Staff
 * Accounts API there are NO self-lockout guards here - this tool manages lists,
 * not accounts, so an admin cannot lock themselves out by editing it.
 *
 * It writes to the SAME SQLite database as the staff app, by requiring the
 * shared db.php from the vendors tree.
 *
 * Routed by:  ?list=vendor_type|coverage_area & action=add|rename|delete|reorder
 *
 * Coverage areas are a 3-level HIERARCHY (nationwide / state / region / county)
 * via a self-referential parent_id + a kind column on coverage_areas. The admin
 * add/edit actions accept and return parent_id + kind, and validate that the
 * parent/kind pairing is legal. Vendor Types stays a FLAT list - its endpoints
 * are unchanged.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/vendors/api/auth-lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendors/api/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond($payload, $status = 200)
{
    // JSON_INVALID_UTF8_SUBSTITUTE keeps a single bad byte from blanking the
    // whole response. If encoding still fails, say why instead of going silent.
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        http_response_code(500);
        echo json_encode(array('ok' => false, 'error' => 'JSON encode failed: ' . json_last_error_msg()));
        exit;
    }
    http_response_code($status);
    echo $json;
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
    // ---- in-app admin gate (replaces the old /admin/ Directory Privacy realm) --
    // Establish the session and the shared DB handle FIRST, then gate. $pdo is
    // reused by the action handlers below, so we do not double-connect.
    start_secure_session();
    $pdo = vdb_connect();

    // Hard gate: a valid in-app session is required. require_auth emits 401 JSON
    // and exits for an anonymous caller, so no handler below runs.
    $authUser = require_auth($pdo);

    // Forced-change gate: a user who still owes a password change is blocked with
    // the same 403 must_change contract the rest of the suite uses.
    if ((int) $authUser['must_change_password'] === 1) {
        respond(array(
            'ok'          => false,
            'must_change' => true,
            'error'       => 'You must set a new password before using this tool.',
        ), 403);
    }

    // Admin-only: decided from the SESSION user, never the request. A non-admin
    // gets 403 and NONE of the actions below run.
    $isAdmin = isset($authUser['is_admin']) && (int) $authUser['is_admin'] === 1;
    if (!$isAdmin) {
        fail('Administrators only.', 403);
    }

    $meta = list_meta(isset($_GET['list']) ? $_GET['list'] : '');
    if ($meta === null) {
        fail('Unknown list. Use list=vendor_type or list=coverage_area.', 404);
    }
    $table  = $meta['table'];
    $map    = $meta['map'];
    $col    = $meta['col'];
    $action = isset($_GET['action']) ? $_GET['action'] : 'get';

    $isAreas = ($table === 'coverage_areas');

    switch ($action) {
        case 'get':
            respond(array('ok' => true, 'items' => list_items($pdo, $table)));
            break;

        case 'audit':
            // Read-only: vendors with NO coverage area at all, so Clark can decide
            // which should be "Nationwide" vs a real area. Areas list only.
            if (!$isAreas) {
                fail('Audit is only available for coverage areas.', 404);
            }
            respond(array('ok' => true) + audit_uncovered_vendors($pdo));
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
            if ($isAreas) {
                // Coverage areas carry kind + parent_id; validate the pairing.
                $resolved = resolve_area_kind_parent($pdo, $b, 0);
                $stmt = $pdo->prepare('INSERT INTO coverage_areas (name, sort, kind, parent_id) VALUES (?, ?, ?, ?)');
                $stmt->execute(array($name, $maxSort + 1, $resolved['kind'], $resolved['parent_id']));
            } else {
                $stmt = $pdo->prepare('INSERT INTO ' . $table . ' (name, sort) VALUES (?, ?)');
                $stmt->execute(array($name, $maxSort + 1));
            }
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

        case 'edit':
            // Coverage areas only: change name and/or kind and/or parent in one go.
            // Vendor Types has no kind/parent, so use rename for it.
            if (!$isAreas) {
                fail('Use rename for vendor types.', 404);
            }
            $b    = body_json();
            $id   = isset($b['id']) ? (int) $b['id'] : 0;
            $name = isset($b['name']) ? trim($b['name']) : '';
            if ($id <= 0 || $name === '') {
                fail('id and name are required.');
            }
            if (!area_exists($pdo, $id)) {
                fail('Coverage area not found.', 404);
            }
            if (name_exists($pdo, 'coverage_areas', $name, $id)) {
                fail('Another item already uses that name.', 409);
            }
            $resolved = resolve_area_kind_parent($pdo, $b, $id);
            $stmt = $pdo->prepare('UPDATE coverage_areas SET name = ?, kind = ?, parent_id = ? WHERE id = ?');
            $stmt->execute(array($name, $resolved['kind'], $resolved['parent_id'], $id));
            respond(array('ok' => true, 'items' => list_items($pdo, $table)));
            break;

        case 'delete':
            $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
            if ($id <= 0) {
                fail('Missing id.');
            }
            // Block deleting a node that still has children, so we never orphan a
            // sub-tree silently (the FK on parent_id is ON DELETE SET NULL, which
            // would quietly promote the children to top-level). The admin must
            // re-parent or delete the children first.
            if ($isAreas) {
                $cStmt = $pdo->prepare('SELECT COUNT(*) FROM coverage_areas WHERE parent_id = ?');
                $cStmt->execute(array($id));
                $childCount = (int) $cStmt->fetchColumn();
                if ($childCount > 0) {
                    respond(array(
                        'ok'    => false,
                        'error' => 'This area has ' . $childCount . ' child area(s) under it. Re-parent or delete those first.',
                    ), 409);
                }
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
    // This endpoint is behind the in-app admin gate, so it is safe to return the
    // real message to help diagnose setup problems on the live server.
    respond(array('ok' => false, 'error' => 'Server error: ' . $e->getMessage()), 500);
}

/**
 * All items in a list, each with its current usage count.
 * Vendor Types: flat, ordered by name. Coverage Areas: the full tiered tree
 * (id, name, sort, kind, parent_id) in tree-render order so the admin can show
 * the indented hierarchy; the front end nests via parent_id.
 */
function list_items(PDO $pdo, $table)
{
    if ($table === 'coverage_areas') {
        // Reuse the canonical tree builder from db.php so the admin and the staff
        // app agree on shape + order.
        $rows = vdb_area_tree($pdo);
        foreach ($rows as &$row) {
            $row['usageCount'] = usage_count($pdo, 'vendor_area_map', 'area_id', (int) $row['id']);
        }
        unset($row);
        return $rows;
    }

    $rows = $pdo->query('SELECT id, name, sort FROM ' . $table . ' ORDER BY name COLLATE NOCASE')->fetchAll();
    foreach ($rows as &$row) {
        $row['usageCount'] = usage_count($pdo, 'vendor_type_map', 'type_id', (int) $row['id']);
    }
    unset($row);
    return $rows;
}

/** True if a coverage_areas row with this id exists. */
function area_exists(PDO $pdo, $id)
{
    $stmt = $pdo->prepare('SELECT 1 FROM coverage_areas WHERE id = ?');
    $stmt->execute(array((int) $id));
    return (bool) $stmt->fetchColumn();
}

/**
 * Validate + normalize the kind/parent pairing for a coverage-area add/edit.
 * Returns array('kind' => ..., 'parent_id' => int|null). Rules:
 *   - kind in {nationwide, state, region, county}; default 'county'.
 *   - nationwide + state are TOP-LEVEL: parent_id is forced NULL.
 *   - region: parent REQUIRED and must be a 'state'.
 *   - county: parent REQUIRED and must be a 'region' OR a 'state'.
 *   - a parent must exist and may not be the node itself (no self-parent); on
 *     edit we also forbid pointing at one of the node's own descendants (cycle).
 * fail()s with a clear message on any violation.
 */
function resolve_area_kind_parent(PDO $pdo, array $b, $selfId)
{
    $kind = isset($b['kind']) ? trim((string) $b['kind']) : 'county';
    $allowed = array('nationwide', 'state', 'region', 'county');
    if (!in_array($kind, $allowed, true)) {
        fail('Invalid kind. Use nationwide, state, region, or county.');
    }

    $rawParent = isset($b['parent_id']) && $b['parent_id'] !== '' && $b['parent_id'] !== null
        ? (int) $b['parent_id'] : 0;

    // Top-level kinds never have a parent.
    if ($kind === 'nationwide' || $kind === 'state') {
        return array('kind' => $kind, 'parent_id' => null);
    }

    if ($rawParent <= 0) {
        fail('A ' . $kind . ' needs a parent.');
    }
    if ($selfId > 0 && $rawParent === (int) $selfId) {
        fail('An area cannot be its own parent.');
    }
    // Load the parent row to check its kind.
    $pStmt = $pdo->prepare('SELECT kind FROM coverage_areas WHERE id = ?');
    $pStmt->execute(array($rawParent));
    $parentKind = $pStmt->fetchColumn();
    if ($parentKind === false) {
        fail('Chosen parent does not exist.');
    }
    if ($kind === 'region' && $parentKind !== 'state') {
        fail('A region\'s parent must be a state.');
    }
    if ($kind === 'county' && $parentKind !== 'region' && $parentKind !== 'state') {
        fail('A county\'s parent must be a region or a state.');
    }
    // Cycle guard on edit: the new parent must not be the node or one of its
    // descendants. Walk the parent chain upward from the chosen parent; if we
    // reach $selfId, it would form a loop.
    if ($selfId > 0) {
        $cur  = $rawParent;
        $hops = 0;
        while ($cur > 0 && $hops < 64) {
            if ($cur === (int) $selfId) {
                fail('That parent is inside this area\'s own branch (would create a loop).');
            }
            $aStmt = $pdo->prepare('SELECT parent_id FROM coverage_areas WHERE id = ?');
            $aStmt->execute(array($cur));
            $next = $aStmt->fetchColumn();
            $cur  = ($next === null || $next === false) ? 0 : (int) $next;
            $hops++;
        }
    }

    return array('kind' => $kind, 'parent_id' => $rawParent);
}

/**
 * Read-only audit: vendors that have NO coverage area mapped at all. Returns
 * { count, vendors:[{id,name}] } so Clark can decide which should be Nationwide
 * vs a real area. Ordered by name.
 */
function audit_uncovered_vendors(PDO $pdo)
{
    $rows = $pdo->query('
        SELECT v.id, v.name
        FROM vendors v
        WHERE NOT EXISTS (
            SELECT 1 FROM vendor_area_map m WHERE m.vendor_id = v.id
        )
        ORDER BY v.name COLLATE NOCASE
    ')->fetchAll();
    foreach ($rows as &$r) {
        $r['id'] = (int) $r['id'];
    }
    unset($r);
    return array('count' => count($rows), 'vendors' => $rows);
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
