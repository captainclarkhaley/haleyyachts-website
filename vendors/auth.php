<?php
/**
 * auth.php - JSON authentication endpoint for the staff Vendor app login flow.
 *
 * Actions (?action=...):
 *   login   POST {account_id, password}  -> sets session, returns the user
 *   logout  POST                         -> clears the session
 *   me      GET                          -> the current user, or {ok:false}
 *   forgot  POST {email}                 -> emails a reset link; ALWAYS generic OK
 *   reset   POST {token, password}       -> consumes a token, sets a new password
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

// From-address for password-reset emails. Override here if the domain mailbox
// changes. Must be on the site domain for SPF/DKIM to pass.
define('AUTH_MAIL_FROM', 'no-reply@haleyyachts.com');
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

/** Public-safe view of a user row (never the hash). */
function a_public_user(array $u)
{
    return array(
        'account_id'  => $u['account_id'],
        'name'        => $u['name'],
        'email'       => $u['email'],
        'home_office' => $u['home_office'],
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
                'SELECT id, account_id, name, email, cell, home_office, password_hash, active
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

            a_respond(array('ok' => true, 'user' => a_public_user($user)));
            break;

        // ---- logout -------------------------------------------------------
        case 'logout':
            $_SESSION = array();
            if (ini_get('session.use_cookies')) {
                $p = session_get_cookie_params();
                setcookie(
                    session_name(), '', time() - 42000,
                    $p['path'], $p['domain'], $p['secure'], $p['httponly']
                );
            }
            session_destroy();
            a_respond(array('ok' => true));
            break;

        // ---- me -----------------------------------------------------------
        case 'me':
            $user = current_user($pdo);
            if ($user === null) {
                a_respond(array('ok' => false, 'auth' => false), 200);
            }
            a_respond(array('ok' => true, 'user' => a_public_user($user)));
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

                    a_send_reset_email($u['email'], $rawToken);
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

            a_respond(array('ok' => true, 'message' => 'Your password has been reset. You can now log in.'));
            break;

        default:
            a_fail('Unknown auth action.', 404);
    }
} catch (Throwable $e) {
    error_log('vendor-auth error: ' . $e->getMessage());
    a_fail('Server error.', 500);
}

/**
 * Send the password-reset email with the raw token link. Best-effort: mail()
 * returning false is logged but never changes the generic "forgot" response.
 */
function a_send_reset_email($toEmail, $rawToken)
{
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'haleyyachts.com';
    // Strip anything but a sane host to keep it out of the link/headers.
    $host = preg_replace('/[^A-Za-z0-9.\-:]/', '', $host);

    $link = 'https://' . $host . '/vendors/reset.html?token=' . $rawToken;

    $subject = 'Haley Yachts Vendor App - password reset';
    $body =
        "A password reset was requested for your Haley Yachts Vendor App account.\r\n\r\n" .
        "Open this link to set a new password (it expires in 1 hour):\r\n\r\n" .
        $link . "\r\n\r\n" .
        "If you did not request this, you can ignore this email; your password will not change.\r\n";

    $headers = 'From: Haley Yachts <' . AUTH_MAIL_FROM . ">\r\n" .
               'Reply-To: ' . AUTH_MAIL_FROM . "\r\n" .
               "Content-Type: text/plain; charset=UTF-8\r\n";

    $sent = @mail($toEmail, $subject, $body, $headers);
    if (!$sent) {
        error_log('vendor-auth: reset email send failed for a user (mail() returned false).');
    }
}
