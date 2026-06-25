<?php
/**
 * auth-lib.php - shared authentication helpers for the staff Vendor app.
 *
 * This is the security boundary once cPanel Directory Privacy is removed from
 * /vendors/. It provides:
 *   - start_secure_session(): session_start() with hardened cookie flags.
 *   - current_user($pdo):     the logged-in user row (or null) from $_SESSION['uid'].
 *   - require_auth($pdo):      sends 401 JSON and exits when not logged in (API use).
 *
 * Used by the JSON data API (vendors/api/api.php), the auth endpoint
 * (vendors/auth.php), and the server-side page gate (vendors/index.php).
 *
 * Passwords are NEVER stored or compared in plaintext anywhere - hashing lives
 * in password_hash()/password_verify() at the call sites (auth.php, users-api).
 */

if (!function_exists('start_secure_session')) {

    /**
     * Start the PHP session with hardened cookie parameters, before any output.
     * HttpOnly (no JS access), Secure (HTTPS only), SameSite=Lax (CSRF mitigation
     * for cross-site navigations). Idempotent: safe to call more than once.
     */
    function start_secure_session()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Secure flag: the live site is HTTPS. Detect it so a stray HTTP request
        // does not silently drop the flag; default to true if we cannot tell.
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        $params = array(
            'lifetime' => 0,        // session cookie; clears on browser close
            'path'     => '/vendors/',
            'httponly' => true,
            'secure'   => $https,
            'samesite' => 'Lax',
        );

        // PHP 7.3+ accepts the options array (incl. samesite). The site runs
        // PHP 7.4+/8.x so this path is the one taken.
        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params($params);
        } else {
            session_set_cookie_params(
                $params['lifetime'], $params['path'] . '; samesite=Lax',
                '', $params['secure'], $params['httponly']
            );
        }

        session_name('HYVENDORSID');
        session_start();
    }

    /**
     * Return the current logged-in, still-active user row, or null.
     * Re-reads from the DB each call so a disabled/deleted account loses access
     * immediately rather than riding an old session.
     *
     * @return array|null
     */
    function current_user(PDO $pdo)
    {
        if (empty($_SESSION['uid'])) {
            return null;
        }
        $stmt = $pdo->prepare(
            'SELECT id, account_id, name, email, cell, home_office, active, must_change_password
             FROM users WHERE id = ? AND active = 1'
        );
        $stmt->execute(array((int) $_SESSION['uid']));
        $row = $stmt->fetch();
        return $row ? $row : null;
    }

    /**
     * API guard: ensure an authenticated session, or emit 401 JSON and exit.
     * This is the real access control for the data API after cPanel auth is gone.
     * Returns the user row on success.
     *
     * @return array
     */
    function require_auth(PDO $pdo)
    {
        $user = current_user($pdo);
        if ($user === null) {
            // Match the api.php JSON contract so the front end can detect it.
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                header('Cache-Control: no-store');
                http_response_code(401);
            }
            echo json_encode(array('ok' => false, 'error' => 'Not authenticated.', 'auth' => false));
            exit;
        }
        return $user;
    }
}
