<?php
/**
 * users-api.php (Broker Suite admin copy) - ADMIN-ONLY endpoint to manage staff
 * login accounts for the suite (the users table).
 *
 * RELOCATED from /admin/users-api.php as part of Phase 2b. The original still
 * lives under /admin/ and keeps working until 2d retires it; this copy is the
 * in-app version, gated by the SAME session + is_admin flag the rest of the
 * Broker Suite uses instead of the old /admin/ Directory Privacy realm.
 *
 * AUTH MODEL (the only real change vs. the original, plus the self-lockout
 * guards below): this is an API endpoint, so it does NOT redirect. It requires a
 * valid in-app session (require_auth -> 401 JSON), blocks a user who still owes a
 * forced password change (403 must_change), and then requires is_admin === 1
 * (403 'Administrators only.'). The admin check is enforced from the SESSION user
 * ($authUser), never from the request. Every action runs only for an
 * authenticated admin.
 *
 * Writes to the SAME SQLite database as the staff app, via the shared db.php.
 *
 * Routed by ?action=list|create|update|toggle|delete|reset-password
 *
 * Security: passwords are stored ONLY as password_hash() bcrypt. Hashes are
 * never returned to the browser. Minimum password length enforced here too.
 *
 * SELF-LOCKOUT GUARDS (new in 2b): because the admin is now a user row in the
 * same table, an admin must not be able to delete, deactivate, or demote their
 * own account, and the last active administrator must not be removed. These
 * checks run BEFORE the corresponding write.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth-lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/mail-lib.php';

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
        'SELECT id, account_id, name, email, cell, home_office, active, is_admin, created_at, updated_at
         FROM users ORDER BY name COLLATE NOCASE, account_id COLLATE NOCASE'
    )->fetchAll();
    foreach ($rows as &$r) {
        $r['active']   = (int) $r['active'] === 1;
        $r['is_admin'] = (int) $r['is_admin'] === 1;
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

/** Count of currently active administrators (active = 1 AND is_admin = 1). */
function u_active_admin_count(PDO $pdo)
{
    return (int) $pdo->query('SELECT COUNT(*) FROM users WHERE active = 1 AND is_admin = 1')->fetchColumn();
}

/** True if the user row (by id) is currently an active administrator. */
function u_is_active_admin(PDO $pdo, $id)
{
    $stmt = $pdo->prepare('SELECT 1 FROM users WHERE id = ? AND active = 1 AND is_admin = 1');
    $stmt->execute(array((int) $id));
    return (bool) $stmt->fetchColumn();
}

try {
    start_secure_session();
    $pdo = vdb_connect();

    // ---- in-app admin gate (replaces the old /admin/ Directory Privacy realm) --
    // Hard gate: a valid in-app session is required. require_auth emits 401 JSON
    // and exits for an anonymous caller, so no handler below runs.
    $authUser = require_auth($pdo);

    // Forced-change gate: a user who still owes a password change is blocked with
    // the same 403 must_change contract the pocket API uses.
    if ((int) $authUser['must_change_password'] === 1) {
        u_respond(array(
            'ok'          => false,
            'must_change' => true,
            'error'       => 'You must set a new password before using this tool.',
        ), 403);
    }

    // Admin-only: decided from the SESSION user, never the request. A non-admin
    // gets 403 and NONE of the actions below run.
    $isAdmin = isset($authUser['is_admin']) && (int) $authUser['is_admin'] === 1;
    if (!$isAdmin) {
        u_fail('Administrators only.', 403);
    }

    // The acting admin's own id, used by the self-lockout guards below.
    $meId = (int) $authUser['id'];

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
            $isAdminNew = !empty($b['is_admin']) ? 1 : 0;

            if ($accountId === '') { u_fail('Account ID is required.'); }
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { u_fail('A valid email is required.'); }
            if (!u_valid_office($office)) { u_fail('Choose a home office from the list.'); }
            if (strlen($password) < USERS_MIN_PASSWORD) {
                u_fail('Password must be at least ' . USERS_MIN_PASSWORD . ' characters.');
            }
            if (u_account_taken($pdo, $accountId, 0)) { u_fail('That Account ID is already taken.', 409); }
            if (u_email_taken($pdo, $email, 0))       { u_fail('That email is already in use.', 409); }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            // must_change_password = 1: the staff member must replace this
            // admin-typed temp password the first time they sign in.
            $stmt = $pdo->prepare(
                'INSERT INTO users (account_id, name, email, cell, home_office, password_hash, active, is_admin, must_change_password)
                 VALUES (?, ?, ?, ?, ?, ?, 1, ?, 1)'
            );
            $stmt->execute(array($accountId, $name, $email, $cell, $office, $hash, $isAdminNew));

            // Best-effort onboarding email with the temp password in the body.
            // A mail failure must NOT fail account creation, so the bool is
            // ignored beyond mail-lib's own generic (passwordless) log line.
            send_onboarding_email($email, $accountId, $password);

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
            $isAdminNew = !empty($b['is_admin']) ? 1 : 0;

            if ($id <= 0) { u_fail('Missing user id.'); }
            if ($accountId === '') { u_fail('Account ID is required.'); }
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { u_fail('A valid email is required.'); }
            if (!u_valid_office($office)) { u_fail('Choose a home office from the list.'); }
            if (u_account_taken($pdo, $accountId, $id)) { u_fail('That Account ID is already taken.', 409); }
            if (u_email_taken($pdo, $email, $id))       { u_fail('That email is already in use.', 409); }

            // ---- self-lockout + last-admin guards (BEFORE the write) ----
            // Only relevant when this update is REMOVING admin (is_admin -> 0)
            // from someone who is currently an active admin.
            if ($isAdminNew === 0 && u_is_active_admin($pdo, $id)) {
                if ($id === $meId) {
                    u_fail('You cannot remove your own admin access.');
                }
                if (u_active_admin_count($pdo) <= 1) {
                    u_fail('At least one active administrator is required.');
                }
            }

            $stmt = $pdo->prepare(
                "UPDATE users
                 SET account_id = ?, name = ?, email = ?, cell = ?, home_office = ?, is_admin = ?, updated_at = datetime('now')
                 WHERE id = ?"
            );
            $stmt->execute(array($accountId, $name, $email, $cell, $office, $isAdminNew, $id));
            u_respond(array('ok' => true, 'users' => u_list($pdo)));
            break;

        case 'toggle':
            // Enable/disable an account. A disabled account cannot log in and
            // loses access immediately (current_user re-checks active each call).
            $b      = u_body();
            $id     = isset($b['id']) ? (int) $b['id'] : 0;
            $active = !empty($b['active']) ? 1 : 0;
            if ($id <= 0) { u_fail('Missing user id.'); }

            // ---- self-lockout + last-admin guards (BEFORE the write) ----
            // Only relevant when this toggle DEACTIVATES the account (active -> 0).
            if ($active === 0) {
                if ($id === $meId) {
                    u_fail('You cannot deactivate your own account.');
                }
                // Deactivating an active admin must not drop the active-admin
                // count to zero.
                if (u_is_active_admin($pdo, $id) && u_active_admin_count($pdo) <= 1) {
                    u_fail('At least one active administrator is required.');
                }
            }

            $stmt = $pdo->prepare("UPDATE users SET active = ?, updated_at = datetime('now') WHERE id = ?");
            $stmt->execute(array($active, $id));
            u_respond(array('ok' => true, 'users' => u_list($pdo)));
            break;

        case 'delete':
            $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
            if ($id <= 0) { u_fail('Missing user id.'); }

            // ---- self-lockout + last-admin guards (BEFORE the write) ----
            if ($id === $meId) {
                u_fail('You cannot delete your own account.');
            }
            // Deleting an active admin must not drop the active-admin count to zero.
            if (u_is_active_admin($pdo, $id) && u_active_admin_count($pdo) <= 1) {
                u_fail('At least one active administrator is required.');
            }

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
            $check = $pdo->prepare('SELECT account_id, email FROM users WHERE id = ?');
            $check->execute(array($id));
            $target = $check->fetch();
            if (!$target) { u_fail('User not found.', 404); }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            // must_change_password = 1: the user is forced to replace this
            // admin-set temp password on their next sign-in.
            $stmt = $pdo->prepare(
                "UPDATE users SET password_hash = ?, must_change_password = 1, updated_at = datetime('now') WHERE id = ?"
            );
            $stmt->execute(array($hash, $id));
            // Invalidate any outstanding self-service reset tokens for this user.
            $pdo->prepare('UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0')->execute(array($id));

            // Best-effort notice with the new temp password in the body. A mail
            // failure must NOT fail the reset, so the bool is ignored.
            send_admin_reset_email($target['email'], $target['account_id'], $password);

            u_respond(array('ok' => true, 'users' => u_list($pdo)));
            break;

        default:
            u_fail('Unknown action.', 404);
    }
} catch (Throwable $e) {
    error_log('users-api error: ' . $e->getMessage());
    // Behind the in-app admin gate: safe to surface the real message for setup debugging.
    u_respond(array('ok' => false, 'error' => 'Server error: ' . $e->getMessage()), 500);
}
