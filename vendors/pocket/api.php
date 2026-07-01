<?php
/**
 * pocket/api.php - JSON API for the Pocket Listings app (Broker Suite app #2).
 *
 * ACCESS CONTROL: this endpoint is a real security boundary. Every request must
 * carry a valid in-app session (require_auth below); an unauthenticated request
 * gets 401 JSON and NONE of the handlers run. A user who still owes a forced
 * password change is blocked with the same 403 must_change gate the vendor API
 * uses. Owner-or-admin permission on save/delete is enforced SERVER-SIDE from
 * the session user - never trusted from the request.
 *
 * Shares the vendor SQLite DB (vdb_connect) so it can read the users table
 * (brokers). Phase 1 scope only: NO network email, print/share, expiration
 * cron, or comps. The schema leaves room for those later phases.
 *
 * Routed by ?action=list|get|save|delete.
 *   - list/get/delete: JSON or query params.
 *   - save: multipart/form-data (fields + up to 1 hero + 3 additional images).
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/vendors/api/auth-lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendors/api/db.php';

start_secure_session();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Server-side limits (mirrored in the front end; enforced here as the source of truth).
define('POCKET_DESC_MAX', 750);          // comfortably one page on the print sheet
define('POCKET_MAX_IMAGES', 4);          // 1 hero + 3 additional
define('POCKET_MAX_ADDITIONAL', 3);
define('POCKET_IMG_MAX_BYTES', 12 * 1024 * 1024); // 12 MB per upload (pre-resize)
define('POCKET_IMG_MAX_DIM', 1600);      // longest side after resize, px

/** Emit a JSON response with an HTTP status and stop. */
function p_respond($payload, $status = 200)
{
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

function p_fail($message, $status = 400)
{
    p_respond(array('ok' => false, 'error' => $message), $status);
}

/** Block a user who still owes a forced password change. */
function p_fail_must_change()
{
    p_respond(array(
        'ok'          => false,
        'must_change' => true,
        'error'       => 'You must set a new password before using the app.',
    ), 403);
}

/** Read the JSON request body into an associative array (empty array if none). */
function p_body_json()
{
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) {
        return array();
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : array();
}

/** Absolute path to the uploads directory (created on demand). */
function p_uploads_dir()
{
    $dir = $_SERVER['DOCUMENT_ROOT'] . '/vendors/pocket/uploads';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

try {
    $pdo = vdb_connect();

    // Hard gate: every request must carry a valid session. Emits 401 + exits on
    // failure, so no handler below runs for an anonymous caller.
    $authUser = require_auth($pdo);

    // Forced-change gate (defense in depth alongside the index.php redirect).
    if ((int) $authUser['must_change_password'] === 1) {
        p_fail_must_change();
    }

    $action = isset($_GET['action']) ? $_GET['action'] : '';

    switch ($action) {
        case 'list':
            pocket_list($pdo);
            break;
        case 'get':
            pocket_get($pdo);
            break;
        case 'save':
            pocket_save($pdo, $authUser);
            break;
        case 'delete':
            pocket_delete($pdo, $authUser);
            break;
        case 'add_make':
            pocket_add_make($pdo);
            break;
        default:
            p_fail('Unknown action.', 404);
    }
} catch (Throwable $e) {
    error_log('pocket-api error: ' . $e->getMessage());
    // Behind the app login, so it is safe to surface the real message to help
    // diagnose issues on the live server.
    p_fail('Server error: ' . $e->getMessage(), 500);
}

// ---------------------------------------------------------------------------
// list (search / filter; newest-entered first; active only by default)
// ---------------------------------------------------------------------------

/**
 * List pocket listings with optional filters.
 * Filters (all optional, GET):
 *   q            keyword - matches make / model / location / description
 *   make         exact make name (from pocket_makes)
 *   year_min, year_max
 *   length_min, length_max
 *   price_min, price_max
 * Default order: created_at DESC, id DESC (newest entered first). Active only.
 */
function pocket_list(PDO $pdo)
{
    $q     = isset($_GET['q']) ? trim($_GET['q']) : '';
    $make  = isset($_GET['make']) ? trim($_GET['make']) : '';

    $where  = array("l.status = 'active'");
    $params = array();

    if ($q !== '') {
        $where[]  = '(l.make LIKE ? OR l.model LIKE ? OR l.location LIKE ? OR l.description LIKE ?)';
        $like = '%' . $q . '%';
        $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    }
    if ($make !== '') {
        $where[]  = 'l.make = ?';
        $params[] = $make;
    }

    // Numeric min/max ranges. Each guarded so a blank/non-numeric value is ignored.
    $ranges = array(
        array('year',   'year_min',   'year_max'),
        array('length', 'length_min', 'length_max'),
        array('price',  'price_min',  'price_max'),
    );
    foreach ($ranges as $r) {
        list($col, $minKey, $maxKey) = $r;
        if (isset($_GET[$minKey]) && $_GET[$minKey] !== '' && is_numeric($_GET[$minKey])) {
            $where[]  = 'l.' . $col . ' >= ?';
            $params[] = (int) $_GET[$minKey];
        }
        if (isset($_GET[$maxKey]) && $_GET[$maxKey] !== '' && is_numeric($_GET[$maxKey])) {
            $where[]  = 'l.' . $col . ' <= ?';
            $params[] = (int) $_GET[$maxKey];
        }
    }

    $sql = 'SELECT l.* FROM pocket_listings l';
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    // Newest ENTERED first. id DESC breaks ties deterministically.
    $sql .= ' ORDER BY l.created_at DESC, l.id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $out = array();
    foreach ($rows as $row) {
        $out[] = pocket_shape($pdo, $row);
    }

    p_respond(array('ok' => true, 'count' => count($out), 'listings' => $out));
}

// ---------------------------------------------------------------------------
// get (one listing, full detail + all images)
// ---------------------------------------------------------------------------

function pocket_get(PDO $pdo)
{
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $listing = pocket_load($pdo, $id);
    if (!$listing) {
        p_fail('Listing not found.', 404);
    }
    p_respond(array('ok' => true, 'listing' => $listing));
}

/** Load one listing row + broker name + images, or null. */
function pocket_load(PDO $pdo, $id)
{
    if ($id <= 0) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM pocket_listings WHERE id = ?');
    $stmt->execute(array($id));
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    return pocket_shape($pdo, $row);
}

/** Shape a listing row for the client: types coerced, broker name + images attached. */
function pocket_shape(PDO $pdo, array $row)
{
    $id       = (int) $row['id'];
    $brokerId = (int) $row['broker_id'];

    // Broker display name + phone from the users table.
    $bStmt = $pdo->prepare('SELECT name, account_id, cell FROM users WHERE id = ?');
    $bStmt->execute(array($brokerId));
    $b = $bStmt->fetch();
    $brokerName  = '';
    $brokerPhone = '';
    if ($b) {
        $brokerName = (isset($b['name']) && trim($b['name']) !== '')
            ? $b['name']
            : (isset($b['account_id']) ? $b['account_id'] : '');
        $brokerPhone = isset($b['cell']) ? (string) $b['cell'] : '';
    }

    // Images: hero first, then additional by sort.
    $iStmt = $pdo->prepare('
        SELECT id, filename, is_hero, sort
        FROM pocket_listing_images
        WHERE listing_id = ?
        ORDER BY is_hero DESC, sort, id
    ');
    $iStmt->execute(array($id));
    $images = array();
    $hero = '';
    foreach ($iStmt->fetchAll() as $img) {
        $file = (string) $img['filename'];
        $isHero = (int) $img['is_hero'] === 1;
        $images[] = array(
            'id'       => (int) $img['id'],
            'filename' => $file,
            'url'      => 'uploads/' . rawurlencode($file),
            'is_hero'  => $isHero,
        );
        if ($isHero && $hero === '') {
            $hero = 'uploads/' . rawurlencode($file);
        }
    }
    // If no explicit hero flag, fall back to the first image as the thumbnail.
    if ($hero === '' && !empty($images)) {
        $hero = $images[0]['url'];
    }

    return array(
        'id'          => $id,
        'broker_id'   => $brokerId,
        'broker_name' => $brokerName,
        'broker_phone' => $brokerPhone,
        'make'        => $row['make'],
        'model'       => $row['model'],
        'year'        => $row['year'] === null ? null : (int) $row['year'],
        'length'      => $row['length'] === null ? null : (int) $row['length'],
        'location'    => $row['location'],
        'price'       => $row['price'] === null ? null : (int) $row['price'],
        'price_type'  => $row['price_type'],
        'description' => $row['description'],
        'days_active' => $row['days_active'] === null ? null : (int) $row['days_active'],
        'created_at'  => $row['created_at'],
        'expires_at'  => $row['expires_at'],
        'status'      => $row['status'],
        'hero_url'    => $hero,
        'images'      => $images,
    );
}

// ---------------------------------------------------------------------------
// save (create OR update) - multipart/form-data
// ---------------------------------------------------------------------------

/**
 * Create or update a listing.
 *
 * Permission (server-enforced): a NEW listing is always owned by the session
 * user (broker_id = current user). An UPDATE (id > 0) is allowed ONLY if the
 * listing's broker_id === current user id OR the current user is_admin. This is
 * decided from the SESSION user, never from the request body.
 *
 * Fields arrive as multipart form fields; images arrive as file inputs:
 *   hero        - single file (optional)
 *   images[]    - up to 3 additional files
 * Validation: make must exist in pocket_makes; price_type in {net,list};
 * description <= 750 chars; year/length/price numeric; at most 4 images total.
 * On create, expires_at = created_at + days_active.
 */
function pocket_save(PDO $pdo, array $authUser)
{
    $userId  = (int) $authUser['id'];
    $isAdmin = isset($authUser['is_admin']) && (int) $authUser['is_admin'] === 1;

    // If the POST exceeded the server's post_max_size, PHP silently discards ALL
    // of $_POST and $_FILES. Detect that (a body arrived but nothing parsed) and
    // give a clear message instead of a misleading "Make is required".
    if (empty($_POST) && empty($_FILES)
        && isset($_SERVER['CONTENT_LENGTH']) && (int) $_SERVER['CONTENT_LENGTH'] > 0) {
        p_fail('The upload is larger than the server allows. Use smaller images (or fewer of them), then try again.', 413);
    }

    $id          = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $make        = isset($_POST['make']) ? trim($_POST['make']) : '';
    $model       = isset($_POST['model']) ? trim($_POST['model']) : '';
    $location    = isset($_POST['location']) ? trim($_POST['location']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $priceType   = isset($_POST['price_type']) ? trim($_POST['price_type']) : 'list';

    // Numeric fields: blank -> null, otherwise int. Reject non-numeric that is
    // non-blank so bad input is caught rather than silently zeroed.
    $year        = p_num_field('year');
    $length      = p_num_field('length');
    $price       = p_num_field('price');
    $daysActive  = p_num_field('days_active');

    // ---- validation ----
    if ($make === '') {
        p_fail('Make is required.');
    }
    // Make is a combobox: brokers pick from pocket_makes OR type a make not yet in
    // the list (a fuller manufacturer list is coming). So we accept any non-empty
    // make; just cap the length so a stray paste cannot bloat the field.
    if (mb_strlen($make) > 60) {
        p_fail('Make is too long (60 characters max).');
    }
    if ($priceType !== 'net' && $priceType !== 'list') {
        p_fail('Price type must be Net or List.');
    }
    if (mb_strlen($description) > POCKET_DESC_MAX) {
        p_fail('Description exceeds ' . POCKET_DESC_MAX . ' characters.');
    }
    if ($year !== null && ($year < 1900 || $year > 2100)) {
        p_fail('Year is out of range.');
    }
    if ($length !== null && $length < 0) {
        p_fail('Length must be positive.');
    }
    if ($price !== null && $price < 0) {
        p_fail('Price must be positive.');
    }
    if ($daysActive !== null && $daysActive < 0) {
        p_fail('Days active must be positive.');
    }

    // ---- ownership check for updates (SERVER-SIDE) ----
    $existing = null;
    if ($id > 0) {
        $eStmt = $pdo->prepare('SELECT * FROM pocket_listings WHERE id = ?');
        $eStmt->execute(array($id));
        $existing = $eStmt->fetch();
        if (!$existing) {
            p_fail('Listing not found.', 404);
        }
        if (!$isAdmin && (int) $existing['broker_id'] !== $userId) {
            p_fail('You can only edit your own listings.', 403);
        }
    }

    // ---- count existing images (for the update path 4-image cap) ----
    $existingImageCount = 0;
    if ($id > 0) {
        $cStmt = $pdo->prepare('SELECT COUNT(*) FROM pocket_listing_images WHERE listing_id = ?');
        $cStmt->execute(array($id));
        $existingImageCount = (int) $cStmt->fetchColumn();
    }

    // ---- images marked for removal on edit (remove_images[]) ----
    // Parse to a set of positive ints, then keep ONLY the ids that actually
    // belong to THIS listing. Constraining every query by listing_id is what
    // stops one broker from deleting another listing's images by guessing ids;
    // the listing itself was ownership-checked above. We capture the filenames
    // now so the files can be unlinked AFTER the transaction commits.
    $removeIds   = array();  // validated, listing-owned ids to delete
    $removeFiles = array();  // filenames for those ids, for post-commit unlink
    $removedHero = false;    // did the removal set include the current hero?
    if ($id > 0) {
        $requested = array();
        if (isset($_POST['remove_images']) && is_array($_POST['remove_images'])) {
            foreach ($_POST['remove_images'] as $rid) {
                $n = (int) $rid;
                if ($n > 0) { $requested[$n] = true; }  // dedupe via keys
            }
        }
        if (!empty($requested)) {
            $ids = array_keys($requested);
            $place = implode(',', array_fill(0, count($ids), '?'));
            $rStmt = $pdo->prepare(
                'SELECT id, filename, is_hero FROM pocket_listing_images
                 WHERE listing_id = ? AND id IN (' . $place . ')'
            );
            $rStmt->execute(array_merge(array($id), $ids));
            foreach ($rStmt->fetchAll() as $r) {
                $removeIds[]   = (int) $r['id'];
                $removeFiles[] = (string) $r['filename'];
                if ((int) $r['is_hero'] === 1) { $removedHero = true; }
            }
        }
    }
    $removeCount = count($removeIds);

    // ---- gather + validate uploaded files BEFORE any DB write ----
    // hero: single. images[]: up to 3 additional.
    $heroFile  = p_pick_single_upload('hero');
    $moreFiles = p_pick_multi_upload('images');
    if (count($moreFiles) > POCKET_MAX_ADDITIONAL) {
        p_fail('At most ' . POCKET_MAX_ADDITIONAL . ' additional images are allowed.');
    }
    $newImageCount = ($heroFile ? 1 : 0) + count($moreFiles);
    // Cap check against what will actually remain: existing minus the valid
    // removals, plus the new uploads.
    $effectiveExisting = $existingImageCount - $removeCount;
    if ($effectiveExisting + $newImageCount > POCKET_MAX_IMAGES) {
        p_fail('A listing may have at most ' . POCKET_MAX_IMAGES . ' images (1 hero + 3 additional).');
    }

    // Process (validate type + resize + store) each upload to disk first. If any
    // fails we bail before touching the DB, and clean up any files already saved.
    $savedFiles = array(); // absolute paths written, for rollback cleanup
    $heroName   = '';
    $moreNames  = array();
    try {
        if ($heroFile) {
            $heroName = p_store_image($heroFile);
            $savedFiles[] = p_uploads_dir() . '/' . $heroName;
        }
        foreach ($moreFiles as $mf) {
            $nm = p_store_image($mf);
            $moreNames[] = $nm;
            $savedFiles[] = p_uploads_dir() . '/' . $nm;
        }
    } catch (Throwable $e) {
        foreach ($savedFiles as $f) { @unlink($f); }
        p_fail($e->getMessage());
    }

    // Capture create-vs-edit BEFORE $id gets reassigned to lastInsertId below.
    // A NEW listing has $id === 0 here; the notification email fires on create
    // ONLY, never on an edit.
    $isNew = ($id <= 0);

    // ---- DB write ----
    $pdo->beginTransaction();
    try {
        if ($id > 0) {
            // Update: keep created_at + broker_id; recompute expires_at from the
            // ORIGINAL created_at when days_active is provided so it stays coherent.
            $expiresAt = $existing['expires_at'];
            if ($daysActive !== null) {
                $expiresAt = p_compute_expiry($existing['created_at'], $daysActive);
            }
            $stmt = $pdo->prepare("
                UPDATE pocket_listings
                SET make = ?, model = ?, year = ?, length = ?, location = ?,
                    price = ?, price_type = ?, description = ?, days_active = ?,
                    expires_at = ?
                WHERE id = ?
            ");
            $stmt->execute(array(
                $make, $model, $year, $length, $location,
                $price, $priceType, $description, $daysActive,
                $expiresAt, $id,
            ));
        } else {
            // Create: broker_id = SESSION user. created_at defaults to now via the
            // column DEFAULT; read it back to compute a coherent expires_at.
            $ins = $pdo->prepare("
                INSERT INTO pocket_listings
                    (broker_id, make, model, year, length, location, price,
                     price_type, description, days_active, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
            ");
            $ins->execute(array(
                $userId, $make, $model, $year, $length, $location, $price,
                $priceType, $description, $daysActive,
            ));
            $id = (int) $pdo->lastInsertId();

            // Compute expires_at = created_at + days_active, using the row's stored
            // created_at (single source of truth).
            if ($daysActive !== null) {
                $cStmt = $pdo->prepare('SELECT created_at FROM pocket_listings WHERE id = ?');
                $cStmt->execute(array($id));
                $createdAt = (string) $cStmt->fetchColumn();
                $expiresAt = p_compute_expiry($createdAt, $daysActive);
                $pdo->prepare('UPDATE pocket_listings SET expires_at = ? WHERE id = ?')
                    ->execute(array($expiresAt, $id));
            }
        }

        // Remove the images the broker marked for deletion. Both the id list and
        // the listing_id constraint were validated above, but we re-scope by
        // listing_id here too so the DELETE can never touch another listing.
        if (!empty($removeIds)) {
            $place = implode(',', array_fill(0, count($removeIds), '?'));
            $del = $pdo->prepare(
                'DELETE FROM pocket_listing_images
                 WHERE listing_id = ? AND id IN (' . $place . ')'
            );
            $del->execute(array_merge(array($id), $removeIds));
        }

        // Insert image rows for the newly stored files. Hero flagged is_hero=1.
        // On update we APPEND to whatever survived the removal above.
        if ($heroName !== '') {
            // A new hero is supplied: demote any prior hero so there is at most
            // one hero flagged, then insert the new one as the hero.
            if ($id > 0) {
                $pdo->prepare('UPDATE pocket_listing_images SET is_hero = 0 WHERE listing_id = ?')
                    ->execute(array($id));
            }
            $pdo->prepare('
                INSERT INTO pocket_listing_images (listing_id, filename, is_hero, sort)
                VALUES (?, ?, 1, 0)
            ')->execute(array($id, $heroName));
        }
        if (!empty($moreNames)) {
            // Continue the sort order after any existing additional images.
            $sStmt = $pdo->prepare('SELECT COALESCE(MAX(sort), 0) FROM pocket_listing_images WHERE listing_id = ? AND is_hero = 0');
            $sStmt->execute(array($id));
            $sort = (int) $sStmt->fetchColumn();
            $imgIns = $pdo->prepare('
                INSERT INTO pocket_listing_images (listing_id, filename, is_hero, sort)
                VALUES (?, ?, 0, ?)
            ');
            foreach ($moreNames as $nm) {
                $sort++;
                $imgIns->execute(array($id, $nm, $sort));
            }
        }

        // Hero integrity: guarantee at most one hero, and a hero whenever images
        // remain. If the old hero was removed and NO new hero replaced it, the
        // listing is left with zero heroes; promote the earliest surviving image
        // (hero flag first, then sort, then id) so the thumbnail still resolves.
        // Zero remaining images is allowed and leaves nothing to promote.
        if ($id > 0 && $heroName === '' && $removedHero) {
            $pStmt = $pdo->prepare('
                SELECT id FROM pocket_listing_images
                WHERE listing_id = ?
                ORDER BY is_hero DESC, sort, id
                LIMIT 1
            ');
            $pStmt->execute(array($id));
            $promoteId = $pStmt->fetchColumn();
            if ($promoteId !== false) {
                $pdo->prepare('UPDATE pocket_listing_images SET is_hero = 1 WHERE id = ? AND listing_id = ?')
                    ->execute(array((int) $promoteId, $id));
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        foreach ($savedFiles as $f) { @unlink($f); }
        throw $e;
    }

    // The removed image ROWS are gone (committed). Now unlink their files from
    // disk, best-effort, using the same basename() guard as pocket_delete so a
    // stored path trick cannot reach outside the uploads dir.
    if (!empty($removeFiles)) {
        $dir = p_uploads_dir();
        foreach ($removeFiles as $f) {
            $safe = basename($f);
            if ($safe !== '' && $safe !== '.' && $safe !== '..') {
                @unlink($dir . '/' . $safe);
            }
        }
    }

    $listing = pocket_load($pdo, $id);

    // ---- network notification email (create only, OUTSIDE the transaction) ----
    // Fired only for a brand-new listing, and only after the commit succeeded so
    // the email never announces a listing that failed to save. The mailer never
    // throws: it returns a boolean and logs its own failures, so a mail problem
    // cannot break this API response. 'notify_sent' is informational only.
    $notifySent = false;
    if ($isNew && $listing) {
        require_once __DIR__ . '/mailer.php';
        $notifySent = pocket_notify_new_listing($pdo, $listing, $authUser);
    }

    p_respond(array('ok' => true, 'listing' => $listing, 'notify_sent' => $notifySent));
}

/** Read a numeric POST field: '' -> null, numeric -> int, non-numeric -> fail. */
function p_num_field($key)
{
    if (!isset($_POST[$key]) || trim((string) $_POST[$key]) === '') {
        return null;
    }
    $v = trim((string) $_POST[$key]);
    if (!is_numeric($v)) {
        p_fail(ucfirst(str_replace('_', ' ', $key)) . ' must be a number.');
    }
    return (int) $v;
}

/** created_at ("YYYY-MM-DD HH:MM:SS" UTC) + N days, as the same string format. */
function p_compute_expiry($createdAt, $days)
{
    $createdAt = (string) $createdAt;
    $ts = strtotime($createdAt . ' UTC');
    if ($ts === false) {
        $ts = time();
    }
    $ts += ((int) $days) * 86400;
    return gmdate('Y-m-d H:i:s', $ts);
}

// ---------------------------------------------------------------------------
// image upload helpers
// ---------------------------------------------------------------------------

/**
 * Normalize $_FILES['key'] into a single upload array or null. Ignores empty
 * (no-file) inputs. Throws on a real upload error.
 */
function p_pick_single_upload($key)
{
    if (!isset($_FILES[$key]) || !is_array($_FILES[$key])) {
        return null;
    }
    $f = $_FILES[$key];
    if (!isset($f['error']) || $f['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('An image failed to upload.');
    }
    return array(
        'tmp_name' => $f['tmp_name'],
        'size'     => (int) $f['size'],
        'name'     => (string) $f['name'],
    );
}

/**
 * Normalize $_FILES['key'][] (multi-file input) into an array of upload arrays,
 * skipping empty slots. Throws on a real upload error.
 */
function p_pick_multi_upload($key)
{
    $out = array();
    if (!isset($_FILES[$key]) || !is_array($_FILES[$key]) || !isset($_FILES[$key]['error'])) {
        return $out;
    }
    $errors = $_FILES[$key]['error'];
    if (!is_array($errors)) {
        // Single-file shaped input under an array key: normalize to one.
        $single = p_pick_single_upload($key);
        return $single ? array($single) : array();
    }
    foreach ($errors as $i => $err) {
        if ($err === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($err !== UPLOAD_ERR_OK) {
            throw new RuntimeException('An image failed to upload.');
        }
        $out[] = array(
            'tmp_name' => $_FILES[$key]['tmp_name'][$i],
            'size'     => (int) $_FILES[$key]['size'][$i],
            'name'     => (string) $_FILES[$key]['name'][$i],
        );
    }
    return $out;
}

/**
 * Validate + resize + store one uploaded image. Returns the stored filename
 * (unguessable random hex + extension). Throws (message shown to the user) on
 * anything invalid. Resizes down so the longest side is <= POCKET_IMG_MAX_DIM.
 *
 * Type is decided by the ACTUAL image content (getimagesize / finfo), not the
 * client-supplied name or MIME, so a mislabeled file cannot slip through.
 */
function p_store_image(array $file)
{
    if ($file['size'] <= 0) {
        throw new RuntimeException('An image was empty.');
    }
    if ($file['size'] > POCKET_IMG_MAX_BYTES) {
        throw new RuntimeException('An image is too large (max 12 MB).');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Invalid upload.');
    }

    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        throw new RuntimeException('An uploaded file is not a valid image.');
    }
    $type = $info[2]; // IMAGETYPE_*
    $allowed = array(IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP);
    if (!in_array($type, $allowed, true)) {
        throw new RuntimeException('Images must be JPG, PNG, or WEBP.');
    }

    $ext = ($type === IMAGETYPE_PNG) ? 'png' : (($type === IMAGETYPE_WEBP) ? 'webp' : 'jpg');
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest = p_uploads_dir() . '/' . $name;

    // Resize with GD when available; otherwise store as-is (still validated).
    $stored = false;
    if (function_exists('imagecreatetruecolor')) {
        $stored = p_resize_and_save($file['tmp_name'], $dest, $type);
    }
    if (!$stored) {
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new RuntimeException('Could not store an uploaded image.');
        }
    }
    @chmod($dest, 0644);
    return $name;
}

/**
 * Load $src (GD), scale so the longest side <= POCKET_IMG_MAX_DIM (never upscale),
 * and write it to $dest in the same format. Returns true on success, false to let
 * the caller fall back to a plain move. Preserves PNG/WEBP alpha.
 */
function p_resize_and_save($src, $dest, $type)
{
    switch ($type) {
        case IMAGETYPE_JPEG:
            if (!function_exists('imagecreatefromjpeg')) { return false; }
            $img = @imagecreatefromjpeg($src);
            break;
        case IMAGETYPE_PNG:
            if (!function_exists('imagecreatefrompng')) { return false; }
            $img = @imagecreatefrompng($src);
            break;
        case IMAGETYPE_WEBP:
            if (!function_exists('imagecreatefromwebp')) { return false; }
            $img = @imagecreatefromwebp($src);
            break;
        default:
            return false;
    }
    if (!$img) { return false; }

    $w = imagesx($img);
    $h = imagesy($img);
    $max = POCKET_IMG_MAX_DIM;
    $scale = 1.0;
    if ($w > $max || $h > $max) {
        $scale = ($w >= $h) ? ($max / $w) : ($max / $h);
    }

    if ($scale < 1.0) {
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        // Preserve transparency for PNG/WEBP.
        if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_WEBP) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefilledrectangle($dst, 0, 0, $nw, $nh, $transparent);
        }
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);
        $img = $dst;
    }

    $ok = false;
    switch ($type) {
        case IMAGETYPE_JPEG:
            $ok = imagejpeg($img, $dest, 85);
            break;
        case IMAGETYPE_PNG:
            $ok = imagepng($img, $dest, 6);
            break;
        case IMAGETYPE_WEBP:
            $ok = function_exists('imagewebp') ? imagewebp($img, $dest, 85) : false;
            break;
    }
    imagedestroy($img);
    return $ok;
}

// ---------------------------------------------------------------------------
// delete (owner-or-admin, SERVER-SIDE)
// ---------------------------------------------------------------------------

/**
 * Delete a listing. Allowed ONLY when the session user owns it (broker_id ===
 * current user id) OR is an admin. The decision uses the SESSION user, never the
 * request. Image rows cascade via FK; the files on disk are unlinked here.
 */
function pocket_delete(PDO $pdo, array $authUser)
{
    $userId  = (int) $authUser['id'];
    $isAdmin = isset($authUser['is_admin']) && (int) $authUser['is_admin'] === 1;

    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id <= 0) {
        // Also accept it in a JSON body for symmetry with the front end.
        $b = p_body_json();
        $id = isset($b['id']) ? (int) $b['id'] : 0;
    }
    if ($id <= 0) {
        p_fail('Missing listing id.');
    }

    $stmt = $pdo->prepare('SELECT broker_id FROM pocket_listings WHERE id = ?');
    $stmt->execute(array($id));
    $brokerId = $stmt->fetchColumn();
    if ($brokerId === false) {
        p_fail('Listing not found.', 404);
    }
    if (!$isAdmin && (int) $brokerId !== $userId) {
        p_fail('You can only delete your own listings.', 403);
    }

    // Collect the image filenames first so we can unlink the files after the row
    // (and its cascaded image rows) are gone.
    $fStmt = $pdo->prepare('SELECT filename FROM pocket_listing_images WHERE listing_id = ?');
    $fStmt->execute(array($id));
    $files = array();
    foreach ($fStmt->fetchAll() as $r) {
        $files[] = (string) $r['filename'];
    }

    $pdo->prepare('DELETE FROM pocket_listings WHERE id = ?')->execute(array($id));

    // Best-effort file cleanup (basename guards against any stored path tricks).
    $dir = p_uploads_dir();
    foreach ($files as $f) {
        $safe = basename($f);
        if ($safe !== '' && $safe !== '.' && $safe !== '..') {
            @unlink($dir . '/' . $safe);
        }
    }

    p_respond(array('ok' => true, 'deleted' => $id));
}

// ---------------------------------------------------------------------------
// add_make (add a manufacturer to pocket_makes; any authenticated broker)
// ---------------------------------------------------------------------------

/**
 * Add a manufacturer to the curated pocket_makes list. Gated by the top-of-file
 * require_auth (any authenticated broker may add one - the same shared list feeds
 * both the form and the filter selects).
 *
 * Reads `name` (query param, form field, or JSON body), trims it, validates it is
 * non-empty and <= 60 chars, then dedupes case-insensitively: if a row already
 * matches (COLLATE NOCASE) we return that row's canonical name and DO NOT insert
 * a duplicate. Otherwise INSERT it with sort = max(sort)+1 and return the name.
 * Responds { ok:true, name: <canonical name> }.
 */
function pocket_add_make(PDO $pdo)
{
    $name = '';
    if (isset($_GET['name'])) {
        $name = trim((string) $_GET['name']);
    }
    if ($name === '' && isset($_POST['name'])) {
        $name = trim((string) $_POST['name']);
    }
    if ($name === '') {
        $b = p_body_json();
        if (isset($b['name'])) { $name = trim((string) $b['name']); }
    }

    if ($name === '') {
        p_fail('Manufacturer name is required.');
    }
    if (mb_strlen($name) > 60) {
        p_fail('Manufacturer name is too long (60 characters max).');
    }

    // Dedupe case-insensitively. If it already exists, return the canonical name.
    $sel = $pdo->prepare('SELECT id, name FROM pocket_makes WHERE name = ? COLLATE NOCASE');
    $sel->execute(array($name));
    $existing = $sel->fetch();
    if ($existing) {
        p_respond(array('ok' => true, 'name' => (string) $existing['name']));
    }

    // Give it a sort value after the current max so it lands sensibly if the app
    // ever orders by sort (the selects order by name, so this is future-proofing).
    $maxSort = (int) $pdo->query('SELECT COALESCE(MAX(sort), 0) FROM pocket_makes')->fetchColumn();
    $ins = $pdo->prepare('INSERT INTO pocket_makes (name, sort) VALUES (?, ?)');
    $ins->execute(array($name, $maxSort + 1));

    p_respond(array('ok' => true, 'name' => $name));
}
