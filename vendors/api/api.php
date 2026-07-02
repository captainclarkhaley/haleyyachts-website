<?php
/**
 * api.php - JSON front controller for the staff Vendor Database.
 *
 * Routed by ?r=vendors|contacts|lists & action=...
 *
 * ACCESS CONTROL: this endpoint is the real security boundary now. It requires
 * an authenticated in-app session (require_auth below); an unauthenticated
 * request gets a 401 JSON response and NONE of the handlers run. This replaces
 * the old assumption that cPanel Directory Privacy on /vendors/ guarded it.
 *
 * The two predefined lists (vendor_types, coverage_areas) are READ ONLY from
 * here. They are managed by Clark only, via admin/vendor-lists-api.php, which
 * lives in the separate /admin/ realm.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/vendors/api/auth-lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendors/api/db.php';
// mail-lib provides send_delete_request_email() for the non-admin delete path.
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendors/api/mail-lib.php';

// Start the hardened session BEFORE any output so the auth cookie is honored.
start_secure_session();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Server-side limits (mirrored in the front end, enforced here as the source of truth).
define('VENDOR_NOTES_MAX', 150);
define('CONTACT_NOTES_MAX', 100);
define('RATING_NOTE_MAX', 150);
// Vendor documents: ~15MB cap, and the private on-disk store (never web-served;
// the deny-all .htaccess in that dir + the download endpoint are the boundary).
define('DOC_MAX_BYTES', 15 * 1024 * 1024);

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

/** Block a user who still owes a forced password change. Carries must_change so
 *  the front end can redirect to change-password.html. */
function fail_must_change()
{
    respond(array(
        'ok'          => false,
        'must_change' => true,
        'error'       => 'You must set a new password before using the app.',
    ), 403);
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
    $pdo = vdb_connect();

    // Hard gate: every data request must carry a valid session. On failure this
    // emits 401 JSON and exits, so no handler below runs for an anonymous caller.
    $authUser = require_auth($pdo);

    // Forced-change gate (defense in depth alongside the index.php redirect): a
    // user flagged must_change_password must not pull data through the API until
    // they set their own password. The 403 carries must_change so the front end
    // can route them to change-password.html.
    if ((int) $authUser['must_change_password'] === 1) {
        fail_must_change();
    }

    $r      = isset($_GET['r']) ? $_GET['r'] : '';
    $action = isset($_GET['action']) ? $_GET['action'] : '';

    switch ($r) {
        case 'lists':
            handle_lists($pdo, $action);
            break;
        case 'vendors':
            handle_vendors($pdo, $action, $authUser);
            break;
        case 'contacts':
            handle_contacts($pdo, $action);
            break;
        case 'ratings':
            handle_ratings($pdo, $action, $authUser);
            break;
        case 'documents':
            handle_documents($pdo, $action, $authUser);
            break;
        case 'purposes':
            handle_purposes($pdo, $action, $authUser);
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
    $types = $pdo->query('SELECT id, name FROM vendor_types ORDER BY name COLLATE NOCASE')->fetchAll();
    // Coverage areas now ride as the full tiered tree (id, name, kind, parent_id,
    // sort) in tree-render order so the front end can show an indented hierarchy.
    $areas = vdb_area_tree($pdo);
    respond(array(
        'ok'             => true,
        'vendor_types'   => $types,
        'coverage_areas' => $areas,
    ));
}

// ---------------------------------------------------------------------------
// vendors
// ---------------------------------------------------------------------------

function handle_vendors(PDO $pdo, $action, array $authUser)
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
            vendors_delete($pdo, $authUser);
            break;
        default:
            fail('Unknown vendors action.', 404);
    }
}

/**
 * Delete a vendor - ADMIN ONLY. This is the security boundary for delete.
 *
 * The decision is made from the SESSION user's is_admin (the $authUser that
 * require_auth already resolved from $_SESSION), NEVER from anything in the
 * request. A non-admin can therefore not delete even by calling this endpoint
 * directly with a crafted body or query string:
 *   - ADMIN: delete as before; contacts + maps cascade via FK.
 *   - NON-ADMIN: do NOT delete. Look up the vendor name, email the admin
 *     notification address with who asked + which vendor, and return
 *     {ok:true, forwarded:true} so the UI can confirm the request was sent.
 */
function vendors_delete(PDO $pdo, array $authUser)
{
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id <= 0) {
        fail('Missing vendor id.');
    }

    $isAdmin = isset($authUser['is_admin']) && (int) $authUser['is_admin'] === 1;

    if ($isAdmin) {
        $stmt = $pdo->prepare('DELETE FROM vendors WHERE id = ?');
        $stmt->execute(array($id));
        // Maps + contacts cascade via FK (foreign_keys is ON).
        respond(array('ok' => true, 'deleted' => $id));
        return;
    }

    // Non-admin: never delete. Forward a request email to the admin instead.
    $vStmt = $pdo->prepare('SELECT name FROM vendors WHERE id = ?');
    $vStmt->execute(array($id));
    $vendorName = $vStmt->fetchColumn();
    if ($vendorName === false) {
        fail('Vendor not found.', 404);
    }

    $requesterName = isset($authUser['name']) && $authUser['name'] !== ''
        ? $authUser['name']
        : (isset($authUser['account_id']) ? $authUser['account_id'] : '');
    $requesterAccount = isset($authUser['account_id']) ? $authUser['account_id'] : '';

    // Best-effort (mail-lib logs a generic line on failure); the request is
    // considered "forwarded" regardless so the UI gives consistent feedback.
    send_delete_request_email($requesterName, $requesterAccount, $vendorName);

    respond(array(
        'ok'        => true,
        'forwarded' => true,
        'message'   => 'Admin has been notified of your delete request.',
    ));
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
        // Match the vendor name OR any of its contacts' names, so staff can find
        // a vendor by the person they know as easily as by the company name.
        $where[]  = '(v.name LIKE ? OR v.id IN (SELECT vendor_id FROM contacts WHERE name LIKE ?))';
        $params[] = '%' . $name . '%';
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
        // Hierarchy-aware area matching. For the SELECTED area ids we expand to a
        // CLOSURE = each selected id + all its ANCESTORS + all its DESCENDANTS,
        // plus the Nationwide node (a Nationwide-tagged vendor matches any area
        // selection). A vendor matches the area facet if it carries ANY area in
        // that closure (OR within the facet, AND across facets - unchanged).
        // The area set is small, so the closure is computed in PHP from the tree.
        $closure = area_filter_closure($pdo, $areaIds);
        $place   = implode(',', array_fill(0, count($closure), '?'));
        $where[] = 'v.id IN (
            SELECT vendor_id FROM vendor_area_map
            WHERE area_id IN (' . $place . ')
        )';
        $params = array_merge($params, $closure);
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

/**
 * Expand a set of selected coverage-area ids into the closure of ids that should
 * match the area filter, using the tiered tree (parent_id hierarchy):
 *
 *   closure = for each selected X:  X  +  all ANCESTORS of X  +  all DESCENDANTS
 *             of X,  PLUS the Nationwide node (always).
 *
 * This gives containment in BOTH directions: selecting Florida pulls in its
 * regions/counties (descendants); selecting Palm Beach pulls in South Florida +
 * Florida (ancestors). The Nationwide node is added to every closure so a vendor
 * tagged Nationwide matches any area selection. Selecting Nationwide itself is
 * already covered (it is in its own closure). Returns a de-duplicated list of
 * positive ints; the caller uses it as the IN (...) set against vendor_area_map.
 */
function area_filter_closure(PDO $pdo, array $selectedIds)
{
    $tree = vdb_area_tree($pdo);

    // Index by id, and build a parent -> children adjacency from parent_id.
    $byId     = array();
    $children = array();
    $nationwideId = null;
    foreach ($tree as $row) {
        $id = (int) $row['id'];
        $byId[$id] = $row;
        $pid = ($row['parent_id'] === null) ? 0 : (int) $row['parent_id'];
        if (!isset($children[$pid])) {
            $children[$pid] = array();
        }
        $children[$pid][] = $id;
        if ($row['kind'] === 'nationwide') {
            $nationwideId = $id;
        }
    }

    $closure = array();

    foreach ($selectedIds as $sid) {
        $sid = (int) $sid;
        if (!isset($byId[$sid])) {
            continue; // ignore ids that are not real areas
        }
        $closure[$sid] = $sid;

        // Walk up to collect ancestors (guard against a bad self/loop ref).
        $cur  = $byId[$sid]['parent_id'];
        $hops = 0;
        while ($cur !== null && isset($byId[(int) $cur]) && $hops < 64) {
            $cur = (int) $cur;
            $closure[$cur] = $cur;
            $cur = $byId[$cur]['parent_id'];
            $hops++;
        }

        // Walk down to collect descendants (iterative DFS).
        $stack = array($sid);
        while (!empty($stack)) {
            $node = array_pop($stack);
            if (isset($children[$node])) {
                foreach ($children[$node] as $childId) {
                    if (!isset($closure[$childId])) {
                        $closure[$childId] = $childId;
                        $stack[] = $childId;
                    }
                }
            }
        }
    }

    // Nationwide always matches any area selection.
    if ($nationwideId !== null) {
        $closure[$nationwideId] = $nationwideId;
    }

    return array_values($closure);
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
        SELECT a.id, a.name, a.kind FROM vendor_area_map m
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

    $rating = rating_summary($pdo, $id);

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
        'rating_avg'    => $rating['rating_avg'],
        'rating_count'  => $rating['rating_count'],
    );
}

/**
 * Average (mean of all rows, rounded to 1 decimal, or null when none) and the
 * count of ratings for a vendor. Surfaced on both list and single get.
 */
function rating_summary(PDO $pdo, $vendorId)
{
    $stmt = $pdo->prepare('SELECT AVG(stars) AS avg, COUNT(*) AS cnt FROM vendor_ratings WHERE vendor_id = ?');
    $stmt->execute(array((int) $vendorId));
    $row   = $stmt->fetch();
    $count = (int) $row['cnt'];
    return array(
        'rating_avg'   => $count > 0 ? round((float) $row['avg'], 1) : null,
        'rating_count' => $count,
    );
}

/**
 * Reduce a phone to its last 10 digits, or '' if fewer than 10 digits remain
 * after stripping non-digits. Used to compare phones across vendors + contacts
 * regardless of formatting or a leading country code.
 */
function phone_key($raw)
{
    $digits = preg_replace('/\D/', '', (string) $raw);
    if (strlen($digits) < 10) {
        return '';
    }
    return substr($digits, -10);
}

/**
 * Find likely duplicates of a NEW vendor by name and by phone. Returns a list of
 * { id, name, phone, reason } summaries (reason = 'name' | 'phone' | 'name + phone'),
 * or an empty array when nothing matches. CREATE path only - the caller gates this.
 *
 * Name: case-insensitive exact match of the trimmed entered name.
 * Phone: collect every entered phone (the vendor phone + each contact phone),
 *   normalize each to its last 10 digits, then match against ALL existing phones
 *   on both sides - every vendor's phone AND every contact's phone.
 */
function find_duplicate_vendors(PDO $pdo, $name, $vendorPhone, array $contacts)
{
    // ---- name matches ----
    $nameIds = array();
    if ($name !== '') {
        $stmt = $pdo->prepare('SELECT id FROM vendors WHERE name = ? COLLATE NOCASE');
        $stmt->execute(array($name));
        foreach ($stmt->fetchAll() as $row) {
            $nameIds[(int) $row['id']] = true;
        }
    }

    // ---- entered phone keys (vendor phone + each contact phone) ----
    $enteredKeys = array();
    $vk = phone_key($vendorPhone);
    if ($vk !== '') {
        $enteredKeys[$vk] = true;
    }
    foreach ($contacts as $c) {
        $ck = phone_key(isset($c['phone']) ? $c['phone'] : '');
        if ($ck !== '') {
            $enteredKeys[$ck] = true;
        }
    }

    // ---- phone matches: normalize every existing phone on both sides ----
    $phoneIds = array();
    if (!empty($enteredKeys)) {
        $vStmt = $pdo->query('SELECT id, phone FROM vendors');
        foreach ($vStmt->fetchAll() as $row) {
            $k = phone_key($row['phone']);
            if ($k !== '' && isset($enteredKeys[$k])) {
                $phoneIds[(int) $row['id']] = true;
            }
        }
        $cStmt = $pdo->query('SELECT vendor_id, phone FROM contacts');
        foreach ($cStmt->fetchAll() as $row) {
            $k = phone_key($row['phone']);
            if ($k !== '' && isset($enteredKeys[$k])) {
                $phoneIds[(int) $row['vendor_id']] = true;
            }
        }
    }

    // ---- union + build summaries ----
    $allIds = array_keys($nameIds + $phoneIds);
    if (empty($allIds)) {
        return array();
    }

    $place = implode(',', array_fill(0, count($allIds), '?'));
    $stmt  = $pdo->prepare('SELECT id, name, phone FROM vendors WHERE id IN (' . $place . ')');
    $stmt->execute($allIds);

    $out = array();
    foreach ($stmt->fetchAll() as $row) {
        $vid     = (int) $row['id'];
        $byName  = isset($nameIds[$vid]);
        $byPhone = isset($phoneIds[$vid]);
        if ($byName && $byPhone) {
            $reason = 'name + phone';
        } elseif ($byName) {
            $reason = 'name';
        } else {
            $reason = 'phone';
        }
        $out[] = array(
            'id'     => $vid,
            'name'   => $row['name'],
            'phone'  => $row['phone'],
            'reason' => $reason,
        );
    }
    return $out;
}

/**
 * Create or update a vendor, with its type/area maps and inline contacts.
 * Body: { id?, name, address, phone, email, notes, types[], areas[], contacts[], allow_duplicate? }
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

    // Duplicate guard - CREATE ONLY. On a brand-new vendor (id <= 0) where the
    // caller has NOT opted out via allow_duplicate, look for likely duplicates by
    // name and by phone before inserting. If any match, do NOT insert: return 409
    // with the matches so the front end can offer Open / Create anyway. Edits
    // (id > 0) skip this entirely; an explicit allow_duplicate skips it too.
    $allowDuplicate = !empty($b['allow_duplicate']);
    if ($id <= 0 && !$allowDuplicate) {
        $dupes = find_duplicate_vendors($pdo, $name, $phone, $contacts);
        if (!empty($dupes)) {
            respond(array(
                'ok'         => false,
                'duplicate'  => true,
                'duplicates' => $dupes,
            ), 409);
        }
    }

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

// ---------------------------------------------------------------------------
// ratings (anonymous; one dated row per rating, average is the mean of rows)
// ---------------------------------------------------------------------------

function handle_ratings(PDO $pdo, $action, array $authUser)
{
    switch ($action) {
        case 'list':
            $vendorId = isset($_GET['vendor_id']) ? (int) $_GET['vendor_id'] : 0;
            if ($vendorId <= 0) {
                fail('Missing vendor_id.');
            }
            // rater_account / rater_name attribute each rating to its author. Old
            // rows predating attribution carry '' and the UI shows "Anonymous".
            $stmt = $pdo->prepare('
                SELECT id, stars, note, rater_account, rater_name, created_at
                FROM vendor_ratings WHERE vendor_id = ?
                ORDER BY created_at DESC, id DESC
            ');
            $stmt->execute(array($vendorId));
            $ratings = $stmt->fetchAll();
            foreach ($ratings as &$row) {
                $row['stars'] = (int) $row['stars'];
            }
            unset($row);
            $summary = rating_summary($pdo, $vendorId);
            respond(array(
                'ok'           => true,
                'ratings'      => $ratings,
                'rating_avg'   => $summary['rating_avg'],
                'rating_count' => $summary['rating_count'],
            ));
            break;

        case 'add':
            $b        = body_json();
            $vendorId = isset($b['vendor_id']) ? (int) $b['vendor_id'] : 0;
            if ($vendorId <= 0) {
                fail('Missing vendor_id.');
            }
            $check = $pdo->prepare('SELECT 1 FROM vendors WHERE id = ?');
            $check->execute(array($vendorId));
            if (!$check->fetchColumn()) {
                fail('Vendor not found.', 404);
            }

            // Stars must be an integer 1-5. Reject non-integers and out-of-range.
            $starsRaw = isset($b['stars']) ? $b['stars'] : null;
            if (!is_int($starsRaw) && !(is_string($starsRaw) && ctype_digit($starsRaw))) {
                fail('Stars must be a whole number from 1 to 5.');
            }
            $stars = (int) $starsRaw;
            if ($stars < 1 || $stars > 5) {
                fail('Stars must be from 1 to 5.');
            }

            $note = isset($b['note']) ? trim($b['note']) : '';
            if (mb_strlen($note) > RATING_NOTE_MAX) {
                fail('Rating note exceeds ' . RATING_NOTE_MAX . ' characters.');
            }

            // Attribute the rating to the SESSION user, never anything in the
            // request body. account_id is the stable identity; rater_name falls
            // back to account_id when the profile name is blank.
            $raterAccount = isset($authUser['account_id']) ? $authUser['account_id'] : '';
            $raterName    = (isset($authUser['name']) && trim($authUser['name']) !== '')
                ? $authUser['name']
                : $raterAccount;

            $ins = $pdo->prepare(
                'INSERT INTO vendor_ratings (vendor_id, stars, note, rater_account, rater_name)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $ins->execute(array($vendorId, $stars, $note, $raterAccount, $raterName));
            $newId = (int) $pdo->lastInsertId();

            $summary = rating_summary($pdo, $vendorId);
            respond(array(
                'ok'           => true,
                'id'           => $newId,
                'rating_avg'   => $summary['rating_avg'],
                'rating_count' => $summary['rating_count'],
            ));
            break;

        case 'delete':
            $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
            if ($id <= 0) {
                fail('Missing rating id.');
            }
            $row = $pdo->prepare('SELECT vendor_id FROM vendor_ratings WHERE id = ?');
            $row->execute(array($id));
            $vendorId = (int) $row->fetchColumn();
            $pdo->prepare('DELETE FROM vendor_ratings WHERE id = ?')->execute(array($id));
            $summary = $vendorId > 0
                ? rating_summary($pdo, $vendorId)
                : array('rating_avg' => null, 'rating_count' => 0);
            respond(array(
                'ok'           => true,
                'deleted'      => $id,
                'vendor_id'    => $vendorId,
                'rating_avg'   => $summary['rating_avg'],
                'rating_count' => $summary['rating_count'],
            ));
            break;

        default:
            fail('Unknown ratings action.', 404);
    }
}

// ---------------------------------------------------------------------------
// vendor documents (per-vendor uploads + expiration reminders)
//
// SENSITIVE files (insurance certificates, etc.). The bytes live in the private
// vendors/api/docs/ store, which is denied to the web by its .htaccess; the ONLY
// way to fetch one is the authenticated doc-download.php endpoint. Upload/delete
// are ADMIN-ONLY, enforced SERVER-SIDE from the SESSION user's is_admin (never
// the request). Any authenticated user may list + download.
// ---------------------------------------------------------------------------

/** Absolute path to the private document store (created on demand, 0755). */
function docs_dir()
{
    $dir = $_SERVER['DOCUMENT_ROOT'] . '/vendors/api/docs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

/** True when the session user is an admin (decided from $authUser, never input). */
function doc_is_admin(array $authUser)
{
    return isset($authUser['is_admin']) && (int) $authUser['is_admin'] === 1;
}

/**
 * Compute a document's status relative to now (UTC), from its expires_at:
 *   'expired'  - expires_at is in the past (date <= today)
 *   'expiring' - expires_at is today or within the next 10 days
 *   'ok'       - further out, or no expiry set
 * expires_at is stored as a plain YYYY-MM-DD date. We compare on whole days.
 */
function doc_status($expiresAt)
{
    $exp = trim((string) $expiresAt);
    if ($exp === '') {
        return 'ok';
    }
    $expTs = strtotime($exp . ' 00:00:00 UTC');
    if ($expTs === false) {
        return 'ok';
    }
    $todayTs = strtotime(gmdate('Y-m-d') . ' 00:00:00 UTC');
    if ($expTs < $todayTs) {
        return 'expired';
    }
    $daysUntil = (int) floor(($expTs - $todayTs) / 86400);
    if ($daysUntil <= 10) {
        return 'expiring';
    }
    return 'ok';
}

/** Validate a YYYY-MM-DD date string. Returns the normalized date or null. */
function doc_valid_date($raw)
{
    $s = trim((string) $raw);
    if ($s === '') {
        return null;
    }
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m)) {
        return null;
    }
    if (!checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
        return null;
    }
    return $s;
}

function handle_documents(PDO $pdo, $action, array $authUser)
{
    switch ($action) {
        case 'list':
            doc_list($pdo);
            break;
        case 'upload':
            doc_upload($pdo, $authUser);
            break;
        case 'delete':
            doc_delete($pdo, $authUser);
            break;
        default:
            fail('Unknown documents action.', 404);
    }
}

/**
 * List a vendor's documents (any authenticated user). Returns metadata only -
 * the file bytes are never exposed here; the client links to doc-download.php.
 * Each row carries a computed status ('expired' | 'expiring' | 'ok').
 */
function doc_list(PDO $pdo)
{
    $vendorId = isset($_GET['vendor_id']) ? (int) $_GET['vendor_id'] : 0;
    if ($vendorId <= 0) {
        fail('Missing vendor_id.');
    }
    $stmt = $pdo->prepare('
        SELECT id, purpose, description, provided_by, expires_at, original_name, created_at
        FROM vendor_documents
        WHERE vendor_id = ?
        ORDER BY created_at DESC, id DESC
    ');
    $stmt->execute(array($vendorId));
    $rows = $stmt->fetchAll();

    $out = array();
    foreach ($rows as $row) {
        $out[] = array(
            'id'            => (int) $row['id'],
            'purpose'       => $row['purpose'],
            'description'   => isset($row['description']) ? (string) $row['description'] : '',
            'provided_by'   => doc_normalize_provided_by(isset($row['provided_by']) ? $row['provided_by'] : 'vendor'),
            'expires_at'    => $row['expires_at'],
            'original_name' => $row['original_name'],
            'created_at'    => $row['created_at'],
            'status'        => doc_status($row['expires_at']),
        );
    }
    respond(array('ok' => true, 'documents' => $out));
}

/**
 * Upload a document to a vendor - ADMIN ONLY (403 for a non-admin, decided from
 * the SESSION user). Multipart POST fields: vendor_id, purpose (non-empty),
 * expires_at (optional YYYY-MM-DD), file.
 *
 * Files are validated by CONTENT, not extension: PDFs must start with "%PDF-";
 * images must pass getimagesize and be JPEG/PNG/WEBP. Anything else is rejected.
 * Stored under an unguessable random hex filename in the private docs store; the
 * original name is kept in the row for the download filename.
 */
function doc_upload(PDO $pdo, array $authUser)
{
    if (!doc_is_admin($authUser)) {
        fail('Only an administrator can upload documents.', 403);
    }

    // A POST that blew past post_max_size arrives with empty $_POST/$_FILES.
    if (empty($_POST) && empty($_FILES)
        && isset($_SERVER['CONTENT_LENGTH']) && (int) $_SERVER['CONTENT_LENGTH'] > 0) {
        fail('The upload is larger than the server allows. Use a smaller file, then try again.', 413);
    }

    $vendorId    = isset($_POST['vendor_id']) ? (int) $_POST['vendor_id'] : 0;
    $purpose     = isset($_POST['purpose']) ? trim((string) $_POST['purpose']) : '';
    $description = isset($_POST['description']) ? trim((string) $_POST['description']) : '';
    // Whitelist the direction to exactly 'vendor' or 'us'; anything else -> 'vendor'.
    $providedBy  = doc_normalize_provided_by(isset($_POST['provided_by']) ? $_POST['provided_by'] : 'vendor');
    $expires     = doc_valid_date(isset($_POST['expires_at']) ? $_POST['expires_at'] : '');

    if ($vendorId <= 0) {
        fail('Missing vendor_id.');
    }
    $check = $pdo->prepare('SELECT 1 FROM vendors WHERE id = ?');
    $check->execute(array($vendorId));
    if (!$check->fetchColumn()) {
        fail('Vendor not found.', 404);
    }
    if ($purpose === '') {
        fail('A document purpose is required.');
    }
    if (mb_strlen($purpose) > 60) {
        fail('Purpose is too long (60 characters max).');
    }
    // Description is optional but capped at 50 characters.
    if (mb_strlen($description) > 50) {
        fail('Description is too long (50 characters max).');
    }
    // An expires_at that was supplied but did not parse is a client error.
    if (isset($_POST['expires_at']) && trim((string) $_POST['expires_at']) !== '' && $expires === null) {
        fail('Expiration date must be a valid date (YYYY-MM-DD) or left blank.');
    }

    // ---- pull + validate the file BEFORE any DB write ----
    if (!isset($_FILES['file']) || !is_array($_FILES['file'])
        || !isset($_FILES['file']['error']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
        fail('Choose a file to upload.');
    }
    $f = $_FILES['file'];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        // Distinguish the size-limit errors from a generic failure.
        if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
            fail('The file is too large.', 413);
        }
        fail('The file failed to upload.');
    }

    // doc_store_upload validates by content, stores the file, and returns the
    // random on-disk name. On any invalid file it emits a JSON error via fail()
    // and exits, so control only continues here on a good, stored file.
    $storedName = doc_store_upload($f);

    $originalName = doc_clean_original_name((string) $f['name']);
    $uploadedBy   = isset($authUser['id']) ? (int) $authUser['id'] : 0;

    $ins = $pdo->prepare('
        INSERT INTO vendor_documents
            (vendor_id, filename, original_name, purpose, description, provided_by, expires_at, uploaded_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ');
    try {
        $ins->execute(array($vendorId, $storedName, $originalName, $purpose, $description, $providedBy, $expires, $uploadedBy));
    } catch (Throwable $e) {
        // Roll back the just-written file so we never orphan bytes on disk.
        $path = docs_dir() . '/' . $storedName;
        @unlink($path);
        throw $e;
    }
    $id = (int) $pdo->lastInsertId();

    respond(array(
        'ok'       => true,
        'document' => array(
            'id'            => $id,
            'purpose'       => $purpose,
            'description'   => $description,
            'provided_by'   => $providedBy,
            'expires_at'    => $expires,
            'original_name' => $originalName,
            'created_at'    => gmdate('Y-m-d H:i:s'),
            'status'        => doc_status($expires),
        ),
    ));
}

/**
 * Whitelist the document direction. Only 'vendor' (the vendor provides this policy
 * to us) or 'us' (we provide this policy to the vendor) are valid; anything else
 * falls back to 'vendor' so existing/unknown values stay vendor-provided.
 */
function doc_normalize_provided_by($raw)
{
    return ((string) $raw === 'us') ? 'us' : 'vendor';
}

/**
 * Validate + store one uploaded document. Returns the stored (random) filename.
 * On an invalid file it emits a clear JSON error via fail() and exits - it never
 * returns a bad name. Validation is by CONTENT, never the client name or MIME:
 *   - PDF: the first bytes must be "%PDF-".
 *   - Image: getimagesize must recognize it as JPEG, PNG, or WEBP.
 * Size is capped at DOC_MAX_BYTES. Files are chmod 0644.
 */
function doc_store_upload(array $f)
{
    $size = isset($f['size']) ? (int) $f['size'] : 0;
    if ($size <= 0) {
        fail('The file was empty.');
    }
    if ($size > DOC_MAX_BYTES) {
        fail('The file is too large (max 15 MB).', 413);
    }
    $tmp = isset($f['tmp_name']) ? $f['tmp_name'] : '';
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        fail('Invalid upload.');
    }

    // Sniff the real content. Read the first 5 bytes for the PDF magic; otherwise
    // hand the whole file to getimagesize which validates the image structure.
    $ext = null;
    $fh = @fopen($tmp, 'rb');
    $head = ($fh !== false) ? fread($fh, 5) : '';
    if ($fh !== false) {
        fclose($fh);
    }
    if ($head === '%PDF-') {
        $ext = 'pdf';
    } else {
        $info = @getimagesize($tmp);
        if ($info !== false) {
            switch ($info[2]) {
                case IMAGETYPE_JPEG: $ext = 'jpg';  break;
                case IMAGETYPE_PNG:  $ext = 'png';  break;
                case IMAGETYPE_WEBP: $ext = 'webp'; break;
            }
        }
    }
    if ($ext === null) {
        fail('Unsupported file type. Upload a PDF, JPG, PNG, or WEBP.');
    }

    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest = docs_dir() . '/' . $name;
    if (!move_uploaded_file($tmp, $dest)) {
        fail('Could not store the uploaded file.');
    }
    @chmod($dest, 0644);
    return $name;
}

/**
 * Normalize the client-supplied original name for storage + later download. Keep
 * just the basename, strip control chars, and cap the length. Never used as a
 * path - only echoed back in a sanitized Content-Disposition header downstream.
 */
function doc_clean_original_name($raw)
{
    $name = basename((string) $raw);
    // Drop anything non-printable / control.
    $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name);
    $name = trim($name);
    if ($name === '' || $name === '.' || $name === '..') {
        $name = 'document';
    }
    if (mb_strlen($name) > 180) {
        $name = mb_substr($name, 0, 180);
    }
    return $name;
}

/**
 * Delete a document - ADMIN ONLY (403 for a non-admin, decided from the SESSION
 * user). Removes the row and unlinks the file. The stored filename is run through
 * basename() before it touches the filesystem so a tampered value cannot escape
 * the docs store.
 */
function doc_delete(PDO $pdo, array $authUser)
{
    if (!doc_is_admin($authUser)) {
        fail('Only an administrator can delete documents.', 403);
    }

    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id <= 0) {
        $b  = body_json();
        $id = isset($b['id']) ? (int) $b['id'] : 0;
    }
    if ($id <= 0) {
        fail('Missing document id.');
    }

    $stmt = $pdo->prepare('SELECT filename FROM vendor_documents WHERE id = ?');
    $stmt->execute(array($id));
    $filename = $stmt->fetchColumn();
    if ($filename === false) {
        fail('Document not found.', 404);
    }

    $pdo->prepare('DELETE FROM vendor_documents WHERE id = ?')->execute(array($id));

    // Best-effort unlink; basename() guards against any stored path trick.
    $safe = basename((string) $filename);
    if ($safe !== '' && $safe !== '.' && $safe !== '..') {
        @unlink(docs_dir() . '/' . $safe);
    }

    respond(array('ok' => true, 'deleted' => $id));
}

// ---------------------------------------------------------------------------
// document purposes (controlled list; list = any user, add = ADMIN ONLY)
// ---------------------------------------------------------------------------

function handle_purposes(PDO $pdo, $action, array $authUser)
{
    switch ($action) {
        case 'list':
            $rows = $pdo->query('SELECT id, name FROM document_purposes ORDER BY sort, name COLLATE NOCASE')->fetchAll();
            $out = array();
            foreach ($rows as $r) {
                $out[] = array('id' => (int) $r['id'], 'name' => $r['name']);
            }
            respond(array('ok' => true, 'purposes' => $out));
            break;

        case 'add':
            purpose_add($pdo, $authUser);
            break;

        default:
            fail('Unknown purposes action.', 404);
    }
}

/**
 * Add a Purpose to the controlled list - ADMIN ONLY (decided from the SESSION
 * user). Dedupes case-insensitively (COLLATE NOCASE): an existing match returns
 * its canonical name and does NOT insert a duplicate. Otherwise inserts with
 * sort = max(sort)+1. Mirrors the pocket add_make flow.
 */
function purpose_add(PDO $pdo, array $authUser)
{
    if (!doc_is_admin($authUser)) {
        fail('Only an administrator can add a purpose.', 403);
    }

    $name = '';
    if (isset($_POST['name'])) {
        $name = trim((string) $_POST['name']);
    }
    if ($name === '') {
        $b = body_json();
        if (isset($b['name'])) {
            $name = trim((string) $b['name']);
        }
    }
    if ($name === '') {
        fail('A purpose name is required.');
    }
    if (mb_strlen($name) > 60) {
        fail('Purpose name is too long (60 characters max).');
    }

    $sel = $pdo->prepare('SELECT id, name FROM document_purposes WHERE name = ? COLLATE NOCASE');
    $sel->execute(array($name));
    $existing = $sel->fetch();
    if ($existing) {
        respond(array('ok' => true, 'name' => (string) $existing['name']));
    }

    $maxSort = (int) $pdo->query('SELECT COALESCE(MAX(sort), 0) FROM document_purposes')->fetchColumn();
    $ins = $pdo->prepare('INSERT INTO document_purposes (name, sort) VALUES (?, ?)');
    $ins->execute(array($name, $maxSort + 1));

    respond(array('ok' => true, 'name' => $name));
}
