<?php
/**
 * auth.php - JSON authentication endpoint for the staff Vendor app login flow.
 *
 * Actions (?action=...):
 *   login            POST {account_id, password}        -> sets session, returns user
 *   logout           POST                               -> clears the session
 *   ping             GET/POST                           -> keep-alive; refreshes idle window
 *   me               GET                                -> current user + home offices
 *   forgot           POST {email}                       -> emails a reset link; generic OK
 *   reset            POST {token, password}             -> consumes a token, sets password
 *   update_profile   POST {name,email,cell,home_office} -> edits the SESSION user only
 *   change_password  POST {current_password,new_password} -> SESSION user; verifies current
 *   force_change_password POST {new_password}            -> SESSION user; first-login forced change
 *
 * Self-service note: update_profile and change_password operate STRICTLY on
 * $_SESSION['uid'] via current_user($pdo). They never accept a user id from the
 * request, so a logged-in user can only ever edit their own account. Account ID
 * is read-only (admin-assigned) and cannot be changed through this endpoint.
 *
 * Security model (see auth-lib.php for the session/cookie hardening):
 *   - Passwords stored ONLY as password_hash() bcrypt; verified with
 *     password_verify(). No plaintext is ever stored, logged, or echoed.
 *   - On successful login the session id is regenerated to prevent fixation.
 *   - Reset tokens: raw token in the email only; DB stores SHA-256(token) with a
 *     1-hour expiry and a used flag. Anti-enumeration: "forgot" always returns
 *     the same message whether or not the email matched.
 *   - Minimum password length enforced server-side on reset (admin create lives
 *     in /admin/users-api.php and enforces the same minimum there).
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/vendors/api/auth-lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendors/api/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendors/api/mail-lib.php';

// All Vendor app system mail (onboarding, admin reset, password-changed, reset
// link) is built and sent from vendors/api/mail-lib.php, which owns the
// from-address (VMAIL_FROM). Minimum password length is enforced here.
define('AUTH_MIN_PASSWORD', 8);

// Start the hardened session BEFORE any output (no whitespace precedes <?php).
start_secure_session();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function a_respond($payload, $status = 200)
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

function a_fail($message, $status = 400)
{
    a_respond(array('ok' => false, 'error' => $message), $status);
}

function a_body()
{
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) {
        return array();
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : array();
}

/**
 * Public-safe view of a user row (never the hash). Includes cell because this is
 * only ever returned to the logged-in user about THEIR OWN account (me /
 * update_profile / login), so it is the user's own data behind their session.
 */
function a_public_user(array $u)
{
    return array(
        'account_id'  => $u['account_id'],
        'name'        => $u['name'],
        'email'       => $u['email'],
        'cell'        => isset($u['cell']) ? $u['cell'] : '',
        'home_office' => $u['home_office'],
        // Surface the role so the front end can branch the delete behavior. The
        // delete is STILL enforced server-side from the session, never from this.
        'is_admin'    => isset($u['is_admin']) && (int) $u['is_admin'] === 1,
    );
}

try {
    $pdo    = vdb_connect();
    $action = isset($_GET['action']) ? $_GET['action'] : '';

    switch ($action) {

        // ---- login --------------------------------------------------------
        case 'login':
            $b         = a_body();
            $accountId = isset($b['account_id']) ? trim($b['account_id']) : '';
            $password  = isset($b['password']) ? (string) $b['password'] : '';
            if ($accountId === '' || $password === '') {
                // Generic on purpose - do not reveal which field is wrong.
                a_fail('Invalid credentials.', 401);
            }

            $stmt = $pdo->prepare(
                'SELECT id, account_id, name, email, cell, home_office, password_hash, active, must_change_password
                 FROM users WHERE account_id = ? COLLATE NOCASE'
            );
            $stmt->execute(array($accountId));
            $user = $stmt->fetch();

            // Same generic failure for unknown account, disabled account, or bad
            // password. password_verify runs only when we have a hash to compare.
            $ok = $user
                && (int) $user['active'] === 1
                && password_verify($password, $user['password_hash']);

            if (!$ok) {
                a_fail('Invalid credentials.', 401);
            }

            // Prevent session fixation: new id on privilege change.
            session_regenerate_id(true);
            $_SESSION['uid'] = (int) $user['id'];
            // Seed the idle window so the very first authenticated request after
            // login has a baseline to measure against (current_user refreshes it).
            $_SESSION['last_activity'] = time();

            // must_change_password tells the front end to route to the forced
            // change-password screen instead of the app. Returned as a bool.
            $out = a_public_user($user);
            $out['must_change_password'] = ((int) $user['must_change_password'] === 1);

            a_respond(array('ok' => true, 'user' => $out));
            break;

        // ---- logout -------------------------------------------------------
        case 'logout':
            // clear_session() (auth-lib) wipes session data and expires the
            // cookie - the same teardown the idle-expiry path uses.
            clear_session();
            a_respond(array('ok' => true));
            break;

        // ---- ping (keep-alive) --------------------------------------------
        // Lightweight endpoint the front end calls on real user activity to keep
        // an actively-working session from idling out server-side. current_user()
        // both enforces the idle window AND refreshes last_activity, so a valid
        // session is renewed and an already-expired one returns 401 (auth=false),
        // which the client treats as a signal to drop to login.
        case 'ping':
            $user = current_user($pdo);
            if ($user === null) {
                a_respond(array('ok' => false, 'auth' => false), 401);
            }
            a_respond(array('ok' => true));
            break;

        // ---- me -----------------------------------------------------------
        // Returns the current user's own public profile AND the canonical
        // home-office list, so the profile form prefills + populates its dropdown
        // from a single server source (vdb_home_offices()).
        case 'me':
            $user = current_user($pdo);
            if ($user === null) {
                a_respond(array('ok' => false, 'auth' => false), 200);
            }
            a_respond(array(
                'ok'           => true,
                'user'         => a_public_user($user),
                'home_offices' => vdb_home_offices(),
            ));
            break;

        // ---- update_profile (self-service; SESSION user ONLY) -------------
        // Edits ONLY the logged-in user's row. The target id comes from
        // current_user($pdo) / $_SESSION['uid'] - never from the request body -
        // so a user cannot edit another account by passing an id. Account ID is
        // intentionally NOT editable here (admin-assigned login handle).
        case 'update_profile':
            $user = current_user($pdo);
            if ($user === null) {
                a_fail('Not authenticated.', 401);
            }

            $b           = a_body();
            $name        = isset($b['name']) ? trim($b['name']) : '';
            $email       = isset($b['email']) ? trim($b['email']) : '';
            $cell        = isset($b['cell']) ? trim($b['cell']) : '';
            $homeOffice  = isset($b['home_office']) ? trim($b['home_office']) : '';

            if ($name === '') {
                a_fail('Name is required.');
            }
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                a_fail('A valid email address is required.');
            }
            // Home office must be empty or one of the canonical offices.
            $offices = vdb_home_offices();
            if ($homeOffice !== '' && !in_array($homeOffice, $offices, true)) {
                a_fail('Invalid home office.');
            }

            // Email uniqueness, case-insensitive, EXCLUDING this user's own row.
            $dup = $pdo->prepare(
                'SELECT id FROM users WHERE email = ? COLLATE NOCASE AND id <> ?'
            );
            $dup->execute(array($email, (int) $user['id']));
            if ($dup->fetch()) {
                a_fail('That email address is already in use.', 409);
            }

            // Update ONLY the session user's row. Double-quoted PHP string
            // because the SQL carries the single-quote literal datetime('now').
            $up = $pdo->prepare(
                "UPDATE users
                 SET name = ?, email = ?, cell = ?, home_office = ?, updated_at = datetime('now')
                 WHERE id = ?"
            );
            $up->execute(array($name, $email, $cell, $homeOffice, (int) $user['id']));

            $fresh = current_user($pdo);
            a_respond(array('ok' => true, 'user' => a_public_user($fresh)));
            break;

        // ---- change_password (self-service; SESSION user ONLY) -----------
        // Verifies the CURRENT password, then stores a new bcrypt hash on the
        // logged-in user's row only. The id comes from the session, never the
        // request. Neither password is ever logged or echoed.
        case 'change_password':
            $user = current_user($pdo);
            if ($user === null) {
                a_fail('Not authenticated.', 401);
            }

            $b       = a_body();
            $current = isset($b['current_password']) ? (string) $b['current_password'] : '';
            $new     = isset($b['new_password']) ? (string) $b['new_password'] : '';

            if ($current === '' || $new === '') {
                a_fail('Both the current and new password are required.');
            }
            if (strlen($new) < AUTH_MIN_PASSWORD) {
                a_fail('New password must be at least ' . AUTH_MIN_PASSWORD . ' characters.');
            }

            // Load this user's hash to verify the current password.
            $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
            $stmt->execute(array((int) $user['id']));
            $row = $stmt->fetch();
            if (!$row || !password_verify($current, $row['password_hash'])) {
                // Generic on purpose - do not reveal more than "it is wrong".
                a_fail('Current password is incorrect.', 403);
            }

            $hash = password_hash($new, PASSWORD_DEFAULT);

            $pdo->beginTransaction();
            try {
                // Update ONLY the session user's row. Double-quoted PHP string
                // for the datetime('now') single-quote literal.
                $upd = $pdo->prepare(
                    "UPDATE users SET password_hash = ?, updated_at = datetime('now') WHERE id = ?"
                );
                $upd->execute(array($hash, (int) $user['id']));

                // Invalidate any outstanding self-service reset tokens.
                $clear = $pdo->prepare(
                    'UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0'
                );
                $clear->execute(array((int) $user['id']));

                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }

            // Keep the user logged in; rotate the session id as a precaution.
            session_regenerate_id(true);

            // Best-effort security confirmation (no password in it). A mail
            // failure must not fail the change, so the bool is ignored.
            send_password_changed_email($user['email']);

            a_respond(array('ok' => true, 'message' => 'Your password has been changed.'));
            break;

        // ---- force_change_password (first-login forced change; SESSION user) --
        // For accounts flagged must_change_password = 1 (new accounts and
        // admin-reset accounts). The session was JUST established with the temp
        // password, so no current-password is required here. Operates STRICTLY on
        // the SESSION user via current_user($pdo) - never an id from the request.
        case 'force_change_password':
            $user = current_user($pdo);
            if ($user === null) {
                a_fail('Not authenticated.', 401);
            }

            // Nothing to do if they are not actually flagged - send them to the app
            // rather than silently changing a password no one asked to change.
            if ((int) $user['must_change_password'] !== 1) {
                a_fail('No password change is required.', 409);
            }

            $b   = a_body();
            $new = isset($b['new_password']) ? (string) $b['new_password'] : '';

            if ($new === '') {
                a_fail('A new password is required.');
            }
            if (strlen($new) < AUTH_MIN_PASSWORD) {
                a_fail('New password must be at least ' . AUTH_MIN_PASSWORD . ' characters.');
            }

            $hash = password_hash($new, PASSWORD_DEFAULT);

            $pdo->beginTransaction();
            try {
                // Update ONLY the session user's row, clearing the forced flag.
                // Double-quoted PHP string for the datetime('now') literal.
                $upd = $pdo->prepare(
                    "UPDATE users
                     SET password_hash = ?, must_change_password = 0, updated_at = datetime('now')
                     WHERE id = ?"
                );
                $upd->execute(array($hash, (int) $user['id']));

                // Invalidate any outstanding self-service reset tokens.
                $clear = $pdo->prepare(
                    'UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0'
                );
                $clear->execute(array((int) $user['id']));

                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }

            // Keep the user logged in; rotate the session id as a precaution.
            session_regenerate_id(true);

            // Best-effort security confirmation (no password in it).
            send_password_changed_email($user['email']);

            a_respond(array('ok' => true, 'message' => 'Your password has been set.'));
            break;

        // ---- forgot (anti-enumeration) ------------------------------------
        case 'forgot':
            $b     = a_body();
            $email = isset($b['email']) ? trim($b['email']) : '';

            // The generic message returned no matter what. Built once so every
            // exit path is identical and cannot leak whether the email exists.
            $generic = array(
                'ok'      => true,
                'message' => 'If that email is on file, a reset link has been sent.',
            );

            if ($email !== '') {
                $stmt = $pdo->prepare(
                    'SELECT id, email FROM users WHERE email = ? COLLATE NOCASE AND active = 1'
                );
                $stmt->execute(array($email));
                $u = $stmt->fetch();

                if ($u) {
                    $rawToken  = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $rawToken);
                    $expires   = gmdate('Y-m-d H:i:s', time() + 3600); // 1 hour, UTC

                    $ins = $pdo->prepare(
                        'INSERT INTO password_resets (user_id, token_hash, expires_at)
                         VALUES (?, ?, ?)'
                    );
                    $ins->execute(array((int) $u['id'], $tokenHash, $expires));

                    send_reset_link_email($u['email'], $rawToken);
                }
            }

            // Always the same response, same status, regardless of match.
            a_respond($generic);
            break;

        // ---- reset --------------------------------------------------------
        case 'reset':
            $b        = a_body();
            $rawToken = isset($b['token']) ? trim($b['token']) : '';
            $password = isset($b['password']) ? (string) $b['password'] : '';

            if ($rawToken === '') {
                a_fail('Missing or invalid reset token.');
            }
            if (strlen($password) < AUTH_MIN_PASSWORD) {
                a_fail('Password must be at least ' . AUTH_MIN_PASSWORD . ' characters.');
            }

            $tokenHash = hash('sha256', $rawToken);
            $stmt = $pdo->prepare(
                "SELECT id, user_id, expires_at, used
                 FROM password_resets WHERE token_hash = ?"
            );
            $stmt->execute(array($tokenHash));
            $row = $stmt->fetch();

            $valid = $row
                && (int) $row['used'] === 0
                && strcmp($row['expires_at'], gmdate('Y-m-d H:i:s')) > 0;

            if (!$valid) {
                a_fail('This reset link is invalid or has expired. Request a new one.', 400);
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);

            $pdo->beginTransaction();
            try {
                $up = $pdo->prepare(
                    "UPDATE users SET password_hash = ?, updated_at = datetime('now') WHERE id = ?"
                );
                $up->execute(array($hash, (int) $row['user_id']));

                $mark = $pdo->prepare('UPDATE password_resets SET used = 1 WHERE id = ?');
                $mark->execute(array((int) $row['id']));

                // Invalidate any other outstanding tokens for this user.
                $clear = $pdo->prepare(
                    'UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0'
                );
                $clear->execute(array((int) $row['user_id']));

                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }

            // Best-effort security confirmation to the account's email (no
            // password in it). A mail failure must not fail the reset.
            $em = $pdo->prepare('SELECT email FROM users WHERE id = ?');
            $em->execute(array((int) $row['user_id']));
            $emRow = $em->fetch();
            if ($emRow && !empty($emRow['email'])) {
                send_password_changed_email($emRow['email']);
            }

            a_respond(array('ok' => true, 'message' => 'Your password has been reset. You can now log in.'));
            break;

        default:
            a_fail('Unknown auth action.', 404);
    }
} catch (Throwable $e) {
    error_log('vendor-auth error: ' . $e->getMessage());
    a_fail('Server error.', 500);
}
