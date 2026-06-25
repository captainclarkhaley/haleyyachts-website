<?php
/**
 * users-api.php - ADMIN-ONLY endpoint to manage staff login accounts for the
 * Vendor app (the users table).
 *
 * This file lives in /admin/, protected by its own cPanel Directory Privacy
 * realm. It does NOT require the app session - the admin realm is its gate. It
 * is deliberately admin-scoped: it must only ever live under /admin/.
 *
 * Writes to the SAME SQLite database as the staff app, via the shared db.php.
 *
 * Routed by ?action=list|create|update|toggle|delete|reset-password
 *
 * Security: passwords are stored ONLY as password_hash() bcrypt. Hashes are
 * never returned to the browser. Minimum password length enforced here too.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/vendors/api/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

define('USERS_MIN_PASSWORD', 8);

function u_respond($payload, $status = 200)
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

function u_fail($message, $status = 400)
{
    u_respond(array('ok' => false, 'error' => $message), $status);
}

function u_body()
{
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) {
        return array();
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : array();
}

/** Public account row - NEVER includes password_hash. */
function u_list(PDO $pdo)
{
    $rows = $pdo->query(
        'SELECT id, account_id, name, email, cell, home_office, active, created_at, updated_at
         FROM users ORDER BY name COLLATE NOCASE, account_id COLLATE NOCASE'
    )->fetchAll();
    foreach ($rows as &$r) {
        $r['active'] = (int) $r['active'] === 1;
    }
    unset($r);
    return $rows;
}

/** True if account_id is already taken (case-insensitive), optionally excluding an id. */
function u_account_taken(PDO $pdo, $accountId, $excludeId)
{
    $sql  = 'SELECT 1 FROM users WHERE account_id = ? COLLATE NOCASE';
    $args = array($accountId);
    if ($excludeId > 0) { $sql .= ' AND id != ?'; $args[] = $excludeId; }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);
    return (bool) $stmt->fetchColumn();
}

/** True if email is already taken (case-insensitive), optionally excluding an id. */
function u_email_taken(PDO $pdo, $email, $excludeId)
{
    $sql  = 'SELECT 1 FROM users WHERE email = ? COLLATE NOCASE';
    $args = array($email);
    if ($excludeId > 0) { $sql .= ' AND id != ?'; $args[] = $excludeId; }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);
    return (bool) $stmt->fetchColumn();
}

/** Validate a home office against the single canonical list in db.php. */
function u_valid_office($office)
{
    return $office === '' || in_array($office, vdb_home_offices(), true);
}

try {
    $pdo    = vdb_connect();
    $action = isset($_GET['action']) ? $_GET['action'] : 'list';

    switch ($action) {

        case 'list':
            u_respond(array(
                'ok'           => true,
                'users'        => u_list($pdo),
                'home_offices' => vdb_home_offices(),
            ));
            break;

        case 'create':
            $b          = u_body();
            $accountId  = isset($b['account_id']) ? trim($b['account_id']) : '';
            $name       = isset($b['name']) ? trim($b['name']) : '';
            $email      = isset($b['email']) ? trim($b['email']) : '';
            $cell       = isset($b['cell']) ? trim($b['cell']) : '';
            $office     = isset($b['home_office']) ? trim($b['home_office']) : '';
            $password   = isset($b['password']) ? (string) $b['password'] : '';

            if ($accountId === '') { u_fail('Account ID is required.'); }
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { u_fail('A valid email is required.'); }
            if (!u_valid_office($office)) { u_fail('Choose a home office from the list.'); }
            if (strlen($password) < USERS_MIN_PASSWORD) {
                u_fail('Password must be at least ' . USERS_MIN_PASSWORD . ' characters.');
            }
            if (u_account_taken($pdo, $accountId, 0)) { u_fail('That Account ID is already taken.', 409); }
            if (u_email_taken($pdo, $email, 0))       { u_fail('That email is already in use.', 409); }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                'INSERT INTO users (account_id, name, email, cell, home_office, password_hash, active)
                 VALUES (?, ?, ?, ?, ?, ?, 1)'
            );
            $stmt->execute(array($accountId, $name, $email, $cell, $office, $hash));
            u_respond(array('ok' => true, 'users' => u_list($pdo)));
            break;

        case 'update':
            // Profile fields only. Password changes go through reset-password.
            $b         = u_body();
            $id        = isset($b['id']) ? (int) $b['id'] : 0;
            $accountId = isset($b['account_id']) ? trim($b['account_id']) : '';
            $name      = isset($b['name']) ? trim($b['name']) : '';
            $email     = isset($b['email']) ? trim($b['email']) : '';
            $cell      = isset($b['cell']) ? trim($b['cell']) : '';
            $office    = isset($b['home_office']) ? trim($b['home_office']) : '';

            if ($id <= 0) { u_fail('Missing user id.'); }
            if ($accountId === '') { u_fail('Account ID is required.'); }
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { u_fail('A valid email is required.'); }
            if (!u_valid_office($office)) { u_fail('Choose a home office from the list.'); }
            if (u_account_taken($pdo, $accountId, $id)) { u_fail('That Account ID is already taken.', 409); }
            if (u_email_taken($pdo, $email, $id))       { u_fail('That email is already in use.', 409); }

            $stmt = $pdo->prepare(
                "UPDATE users
                 SET account_id = ?, name = ?, email = ?, cell = ?, home_office = ?, updated_at = datetime('now')
                 WHERE id = ?"
            );
            $stmt->execute(array($accountId, $name, $email, $cell, $office, $id));
            u_respond(array('ok' => true, 'users' => u_list($pdo)));
            break;

        case 'toggle':
            // Enable/disable an account. A disabled account cannot log in and
            // loses access immediately (current_user re-checks active each call).
            $b      = u_body();
            $id     = isset($b['id']) ? (int) $b['id'] : 0;
            $active = !empty($b['active']) ? 1 : 0;
            if ($id <= 0) { u_fail('Missing user id.'); }
            $stmt = $pdo->prepare("UPDATE users SET active = ?, updated_at = datetime('now') WHERE id = ?");
            $stmt->execute(array($active, $id));
            u_respond(array('ok' => true, 'users' => u_list($pdo)));
            break;

        case 'delete':
            $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
            if ($id <= 0) { u_fail('Missing user id.'); }
            // password_resets cascade via FK (foreign_keys is ON).
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute(array($id));
            u_respond(array('ok' => true, 'users' => u_list($pdo)));
            break;

        case 'reset-password':
            // Admin sets a new password directly (the fallback to email reset).
            $b        = u_body();
            $id       = isset($b['id']) ? (int) $b['id'] : 0;
            $password = isset($b['password']) ? (string) $b['password'] : '';
            if ($id <= 0) { u_fail('Missing user id.'); }
            if (strlen($password) < USERS_MIN_PASSWORD) {
                u_fail('Password must be at least ' . USERS_MIN_PASSWORD . ' characters.');
            }
            $check = $pdo->prepare('SELECT 1 FROM users WHERE id = ?');
            $check->execute(array($id));
            if (!$check->fetchColumn()) { u_fail('User not found.', 404); }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = datetime('now') WHERE id = ?");
            $stmt->execute(array($hash, $id));
            // Invalidate any outstanding self-service reset tokens for this user.
            $pdo->prepare('UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0')->execute(array($id));
            u_respond(array('ok' => true, 'users' => u_list($pdo)));
            break;

        default:
            u_fail('Unknown action.', 404);
    }
} catch (Throwable $e) {
    error_log('users-api error: ' . $e->getMessage());
    // Behind the admin realm: safe to surface the real message for setup debugging.
    u_respond(array('ok' => false, 'error' => 'Server error: ' . $e->getMessage()), 500);
}
