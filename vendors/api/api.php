<?php
/**
 * api.php - JSON front controller for the staff Vendor Database.
 *
 * Routed by ?r=vendors|contacts|lists & action=...
 * Sits inside the /vendors/ password realm (cPanel Directory Privacy), so every
 * request here is already authenticated as a staff user.
 *
 * The two predefined lists (vendor_types, coverage_areas) are READ ONLY from
 * here. They are managed by Clark only, via admin/vendor-lists-api.php, which
 * lives in the separate /admin/ realm.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/vendors/api/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Server-side limits (mirrored in the front end, enforced here as the source of truth).
define('VENDOR_NOTES_MAX', 150);
define('CONTACT_NOTES_MAX', 100);

/** Emit a JSON response with an HTTP status and stop. */
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

/** Read the JSON request body into an associative array (empty array if none). */
function body_json()
{
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) {
        return array();
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : array();
}

/** Coerce a value to an array of positive ints (used for type_ids[], area_ids[], etc.). */
function int_id_list($value)
{
    if (!is_array($value)) {
        return array();
    }
    $out = array();
    foreach ($value as $v) {
        $n = (int) $v;
        if ($n > 0) {
            $out[$n] = $n; // dedupe via key
        }
    }
    return array_values($out);
}

try {
    $pdo    = vdb_connect();
    $r      = isset($_GET['r']) ? $_GET['r'] : '';
    $action = isset($_GET['action']) ? $_GET['action'] : '';

    switch ($r) {
        case 'lists':
            handle_lists($pdo, $action);
            break;
        case 'vendors':
            handle_vendors($pdo, $action);
            break;
        case 'contacts':
            handle_contacts($pdo, $action);
            break;
        default:
            fail('Unknown resource.', 404);
    }
} catch (Throwable $e) {
    // Do not leak internals to the client; log server-side.
    error_log('vendor-api error: ' . $e->getMessage());
    fail('Server error.', 500);
}

// ---------------------------------------------------------------------------
// lists (READ ONLY here)
// ---------------------------------------------------------------------------

function handle_lists(PDO $pdo, $action)
{
    if ($action !== 'get') {
        fail('Lists are read-only here. Use the admin tool to edit them.', 403);
    }
    $types = $pdo->query('SELECT id, name FROM vendor_types ORDER BY sort, name')->fetchAll();
    $areas = $pdo->query('SELECT id, name FROM coverage_areas ORDER BY sort, name')->fetchAll();
    respond(array(
        'ok'             => true,
        'vendor_types'   => $types,
        'coverage_areas' => $areas,
    ));
}

// ---------------------------------------------------------------------------
// vendors
// ---------------------------------------------------------------------------

function handle_vendors(PDO $pdo, $action)
{
    switch ($action) {
        case 'list':
            vendors_list($pdo);
            break;
        case 'get':
            $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
            $vendor = load_vendor($pdo, $id);
            if (!$vendor) {
                fail('Vendor not found.', 404);
            }
            respond(array('ok' => true, 'vendor' => $vendor));
            break;
        case 'save':
            vendors_save($pdo);
            break;
        case 'delete':
            $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
            if ($id <= 0) {
                fail('Missing vendor id.');
            }
            $stmt = $pdo->prepare('DELETE FROM vendors WHERE id = ?');
            $stmt->execute(array($id));
            // Maps + contacts cascade via FK (foreign_keys is ON).
            respond(array('ok' => true, 'deleted' => $id));
            break;
        default:
            fail('Unknown vendors action.', 404);
    }
}

/**
 * List vendors with full detail, applying optional filters.
 * Filters: name (substring), type_ids[], area_ids[], type_mode=all|any.
 * Across facets the match is AND. Areas are OR. Types are All or Any per mode.
 */
function vendors_list(PDO $pdo)
{
    $name     = isset($_GET['name']) ? trim($_GET['name']) : '';
    $typeIds  = int_id_list(isset($_GET['type_ids']) ? $_GET['type_ids'] : array());
    $areaIds  = int_id_list(isset($_GET['area_ids']) ? $_GET['area_ids'] : array());
    $typeMode = (isset($_GET['type_mode']) && $_GET['type_mode'] === 'any') ? 'any' : 'all';

    $where  = array();
    $params = array();

    if ($name !== '') {
        $where[]  = 'v.name LIKE ?';
        $params[] = '%' . $name . '%';
    }

    if (!empty($typeIds)) {
        $place = implode(',', array_fill(0, count($typeIds), '?'));
        if ($typeMode === 'all') {
            // Vendor must carry EVERY selected type.
            $where[] = 'v.id IN (
                SELECT vendor_id FROM vendor_type_map
                WHERE type_id IN (' . $place . ')
                GROUP BY vendor_id
                HAVING COUNT(DISTINCT type_id) = ?
            )';
            $params = array_merge($params, $typeIds, array(count($typeIds)));
        } else {
            // Vendor carries ANY selected type.
            $where[] = 'v.id IN (
                SELECT vendor_id FROM vendor_type_map
                WHERE type_id IN (' . $place . ')
            )';
            $params = array_merge($params, $typeIds);
        }
    }

    if (!empty($areaIds)) {
        // Areas are OR: vendor covers any selected area.
        $place   = implode(',', array_fill(0, count($areaIds), '?'));
        $where[] = 'v.id IN (
            SELECT vendor_id FROM vendor_area_map
            WHERE area_id IN (' . $place . ')
        )';
        $params = array_merge($params, $areaIds);
    }

    $sql = 'SELECT v.id, v.name, v.address, v.phone, v.email, v.notes FROM vendors v';
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY v.name COLLATE NOCASE';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $vendors = array();
    foreach ($rows as $row) {
        $vendors[] = enrich_vendor($pdo, $row);
    }

    respond(array('ok' => true, 'count' => count($vendors), 'vendors' => $vendors));
}

/** Load one vendor row + its types/areas/contacts, or null. */
function load_vendor(PDO $pdo, $id)
{
    if ($id <= 0) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT id, name, address, phone, email, notes FROM vendors WHERE id = ?');
    $stmt->execute(array($id));
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    return enrich_vendor($pdo, $row);
}

/** Attach types, areas, contacts, and derived primary phone/email to a vendor row. */
function enrich_vendor(PDO $pdo, array $row)
{
    $id = (int) $row['id'];

    $tStmt = $pdo->prepare('
        SELECT t.id, t.name FROM vendor_type_map m
        JOIN vendor_types t ON t.id = m.type_id
        WHERE m.vendor_id = ? ORDER BY t.sort, t.name
    ');
    $tStmt->execute(array($id));
    $types = $tStmt->fetchAll();

    $aStmt = $pdo->prepare('
        SELECT a.id, a.name FROM vendor_area_map m
        JOIN coverage_areas a ON a.id = m.area_id
        WHERE m.vendor_id = ? ORDER BY a.sort, a.name
    ');
    $aStmt->execute(array($id));
    $areas = $aStmt->fetchAll();

    $cStmt = $pdo->prepare('
        SELECT id, name, email, phone, is_primary, notes
        FROM contacts WHERE vendor_id = ?
        ORDER BY is_primary DESC, name COLLATE NOCASE
    ');
    $cStmt->execute(array($id));
    $contacts = $cStmt->fetchAll();
    foreach ($contacts as &$c) {
        $c['is_primary'] = (int) $c['is_primary'] === 1;
    }
    unset($c);

    // Derived primary phone/email for the list and detail "Primary Phone/Email".
    // The vendor's OWN field wins when set. Only when it is blank do we fall back
    // to a contact: the primary contact first, then the first contact that has a
    // value. This keeps what the broker typed in the vendor record authoritative.
    $primaryPhone = $row['phone'];
    $primaryEmail = $row['email'];
    if ($primaryPhone === '' || $primaryEmail === '') {
        foreach ($contacts as $c) {
            if ($c['is_primary']) {
                if ($primaryPhone === '' && $c['phone'] !== '') { $primaryPhone = $c['phone']; }
                if ($primaryEmail === '' && $c['email'] !== '') { $primaryEmail = $c['email']; }
            }
        }
        foreach ($contacts as $c) {
            if ($primaryPhone === '' && $c['phone'] !== '') { $primaryPhone = $c['phone']; }
            if ($primaryEmail === '' && $c['email'] !== '') { $primaryEmail = $c['email']; }
        }
    }

    return array(
        'id'            => $id,
        'name'          => $row['name'],
        'address'       => $row['address'],
        'phone'         => $row['phone'],
        'email'         => $row['email'],
        'notes'         => $row['notes'],
        'types'         => $types,
        'areas'         => $areas,
        'contacts'      => $contacts,
        'contact_count' => count($contacts),
        'primary_phone' => $primaryPhone,
        'primary_email' => $primaryEmail,
    );
}

/**
 * Create or update a vendor, with its type/area maps and inline contacts.
 * Body: { id?, name, address, phone, email, notes, types[], areas[], contacts[] }
 * contacts[] = { id?, name, email, phone, is_primary, notes }
 */
function vendors_save(PDO $pdo)
{
    $b = body_json();

    $name = isset($b['name']) ? trim($b['name']) : '';
    if ($name === '') {
        fail('Vendor name is required.');
    }

    $id      = isset($b['id']) ? (int) $b['id'] : 0;
    $address = isset($b['address']) ? trim($b['address']) : '';
    $phone   = isset($b['phone']) ? trim($b['phone']) : '';
    $email   = isset($b['email']) ? trim($b['email']) : '';
    $notes   = isset($b['notes']) ? trim($b['notes']) : '';

    if (mb_strlen($notes) > VENDOR_NOTES_MAX) {
        fail('Vendor notes exceed ' . VENDOR_NOTES_MAX . ' characters.');
    }

    $typeIds  = int_id_list(isset($b['types']) ? $b['types'] : array());
    $areaIds  = int_id_list(isset($b['areas']) ? $b['areas'] : array());
    $contacts = isset($b['contacts']) && is_array($b['contacts']) ? $b['contacts'] : array();

    // Validate contacts up front (enforce limits + single primary).
    $primarySeen = false;
    foreach ($contacts as $c) {
        $cnotes = isset($c['notes']) ? trim($c['notes']) : '';
        if (mb_strlen($cnotes) > CONTACT_NOTES_MAX) {
            fail('A contact note exceeds ' . CONTACT_NOTES_MAX . ' characters.');
        }
        if (!empty($c['is_primary'])) {
            if ($primarySeen) {
                fail('Only one contact may be the primary.');
            }
            $primarySeen = true;
        }
    }

    $pdo->beginTransaction();
    try {
        if ($id > 0) {
            $stmt = $pdo->prepare("
                UPDATE vendors
                SET name = ?, address = ?, phone = ?, email = ?, notes = ?, updated_at = datetime('now')
                WHERE id = ?
            ");
            $stmt->execute(array($name, $address, $phone, $email, $notes, $id));
            if ($stmt->rowCount() === 0) {
                // Confirm it actually exists (rowCount can be 0 on a no-op too).
                $check = $pdo->prepare('SELECT 1 FROM vendors WHERE id = ?');
                $check->execute(array($id));
                if (!$check->fetchColumn()) {
                    $pdo->rollBack();
                    fail('Vendor not found.', 404);
                }
            }
        } else {
            $stmt = $pdo->prepare('
                INSERT INTO vendors (name, address, phone, email, notes)
                VALUES (?, ?, ?, ?, ?)
            ');
            $stmt->execute(array($name, $address, $phone, $email, $notes));
            $id = (int) $pdo->lastInsertId();
        }

        // Rebuild type/area maps (validate ids against the real lists).
        $pdo->prepare('DELETE FROM vendor_type_map WHERE vendor_id = ?')->execute(array($id));
        if (!empty($typeIds)) {
            $valid = valid_ids($pdo, 'vendor_types', $typeIds);
            $ins   = $pdo->prepare('INSERT OR IGNORE INTO vendor_type_map (vendor_id, type_id) VALUES (?, ?)');
            foreach ($valid as $tid) {
                $ins->execute(array($id, $tid));
            }
        }

        $pdo->prepare('DELETE FROM vendor_area_map WHERE vendor_id = ?')->execute(array($id));
        if (!empty($areaIds)) {
            $valid = valid_ids($pdo, 'coverage_areas', $areaIds);
            $ins   = $pdo->prepare('INSERT OR IGNORE INTO vendor_area_map (vendor_id, area_id) VALUES (?, ?)');
            foreach ($valid as $aid) {
                $ins->execute(array($id, $aid));
            }
        }

        // Rebuild contacts wholesale: simplest correct behavior for an inline editor.
        $pdo->prepare('DELETE FROM contacts WHERE vendor_id = ?')->execute(array($id));
        if (!empty($contacts)) {
            $ins = $pdo->prepare('
                INSERT INTO contacts (vendor_id, name, email, phone, is_primary, notes)
                VALUES (?, ?, ?, ?, ?, ?)
            ');
            foreach ($contacts as $c) {
                $cname  = isset($c['name']) ? trim($c['name']) : '';
                $cemail = isset($c['email']) ? trim($c['email']) : '';
                $cphone = isset($c['phone']) ? trim($c['phone']) : '';
                $cprim  = !empty($c['is_primary']) ? 1 : 0;
                $cnotes = isset($c['notes']) ? trim($c['notes']) : '';
                // Skip fully blank contact rows.
                if ($cname === '' && $cemail === '' && $cphone === '' && $cnotes === '') {
                    continue;
                }
                $ins->execute(array($id, $cname, $cemail, $cphone, $cprim, $cnotes));
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    $vendor = load_vendor($pdo, $id);
    respond(array('ok' => true, 'vendor' => $vendor));
}

/** Return the subset of $ids that actually exist in $table. */
function valid_ids(PDO $pdo, $table, array $ids)
{
    if (empty($ids)) {
        return array();
    }
    $place = implode(',', array_fill(0, count($ids), '?'));
    $stmt  = $pdo->prepare('SELECT id FROM ' . $table . ' WHERE id IN (' . $place . ')');
    $stmt->execute(array_values($ids));
    return array_map('intval', array_column($stmt->fetchAll(), 'id'));
}

// ---------------------------------------------------------------------------
// contacts (standalone save/delete; the inline editor uses vendors save)
// ---------------------------------------------------------------------------

function handle_contacts(PDO $pdo, $action)
{
    switch ($action) {
        case 'save':
            $b = body_json();
            $vendorId = isset($b['vendor_id']) ? (int) $b['vendor_id'] : 0;
            if ($vendorId <= 0) {
                fail('Missing vendor_id.');
            }
            $check = $pdo->prepare('SELECT 1 FROM vendors WHERE id = ?');
            $check->execute(array($vendorId));
            if (!$check->fetchColumn()) {
                fail('Vendor not found.', 404);
            }

            $name  = isset($b['name']) ? trim($b['name']) : '';
            $email = isset($b['email']) ? trim($b['email']) : '';
            $phone = isset($b['phone']) ? trim($b['phone']) : '';
            $prim  = !empty($b['is_primary']) ? 1 : 0;
            $notes = isset($b['notes']) ? trim($b['notes']) : '';
            $id    = isset($b['id']) ? (int) $b['id'] : 0;

            if (mb_strlen($notes) > CONTACT_NOTES_MAX) {
                fail('Contact notes exceed ' . CONTACT_NOTES_MAX . ' characters.');
            }

            $pdo->beginTransaction();
            try {
                // Exclusive primary: if this one is primary, demote the rest.
                if ($prim === 1) {
                    $pdo->prepare('UPDATE contacts SET is_primary = 0 WHERE vendor_id = ?')
                        ->execute(array($vendorId));
                }
                if ($id > 0) {
                    $stmt = $pdo->prepare('
                        UPDATE contacts SET name = ?, email = ?, phone = ?, is_primary = ?, notes = ?
                        WHERE id = ? AND vendor_id = ?
                    ');
                    $stmt->execute(array($name, $email, $phone, $prim, $notes, $id, $vendorId));
                } else {
                    $stmt = $pdo->prepare('
                        INSERT INTO contacts (vendor_id, name, email, phone, is_primary, notes)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ');
                    $stmt->execute(array($vendorId, $name, $email, $phone, $prim, $notes));
                    $id = (int) $pdo->lastInsertId();
                }
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
            respond(array('ok' => true, 'vendor' => load_vendor($pdo, $vendorId)));
            break;

        case 'delete':
            $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
            if ($id <= 0) {
                fail('Missing contact id.');
            }
            $row = $pdo->prepare('SELECT vendor_id FROM contacts WHERE id = ?');
            $row->execute(array($id));
            $vendorId = (int) $row->fetchColumn();
            $pdo->prepare('DELETE FROM contacts WHERE id = ?')->execute(array($id));
            respond(array(
                'ok'      => true,
                'deleted' => $id,
                'vendor'  => $vendorId > 0 ? load_vendor($pdo, $vendorId) : null,
            ));
            break;

        default:
            fail('Unknown contacts action.', 404);
    }
}
