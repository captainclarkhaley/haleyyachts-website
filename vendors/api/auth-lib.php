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

    // Server-enforced idle window, in seconds. A session with no authenticated
    // request inside this window is treated as expired by current_user() and the
    // caller gets a 401. Set LONGER than the client's 10-minute idle timeout so
    // the browser always logs out first and the server is only a backstop - this
    // avoids a race where the client believes it is alive but the server already
    // killed the session.
    if (!defined('SERVER_IDLE')) {
        define('SERVER_IDLE', 900); // 15 minutes
    }

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

        // Keep PHP's garbage collector from reaping a session before our own
        // idle window does. A short host default for session.gc_maxlifetime can
        // GC an otherwise-valid session out from under an active user; raise it
        // (comfortably above SERVER_IDLE) BEFORE session_start() so OUR idle
        // logic in current_user() is the single source of truth for expiry.
        @ini_set('session.gc_maxlifetime', '1800'); // 30 minutes

        // Secure flag: the live site is HTTPS. Detect it so a stray HTTP request
        // does not silently drop the flag; default to true if we cannot tell.
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        $params = array(
            'lifetime' => 0,        // session cookie; clears on browser close
            'path'     => '/',
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
     * Clear and destroy the current session in full (data + cookie). Used both by
     * the idle-expiry path here and reusable by the logout action. Mirrors the
     * cookie-clearing the logout endpoint does so an expired session leaves no
     * stale cookie behind.
     */
    function clear_session()
    {
        $_SESSION = array();
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(
                session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']
            );
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /**
     * Return the current logged-in, still-active user row, or null.
     * Re-reads from the DB each call so a disabled/deleted account loses access
     * immediately rather than riding an old session.
     *
     * Server-enforced idle window: if the session has gone untouched for longer
     * than SERVER_IDLE seconds, it is treated as expired - the session is cleared
     * and null is returned, so the caller answers 401 and the front end bounces to
     * login. Every successful authenticated resolution refreshes last_activity, so
     * any real request (page gate, data API, auth me/profile/password, ping) keeps
     * the window alive. This is the keep-alive backstop for an actively-working
     * user and the timeout for a walked-away one.
     *
     * @return array|null
     */
    function current_user(PDO $pdo)
    {
        if (empty($_SESSION['uid'])) {
            return null;
        }

        // Idle expiry: enforced before the DB read so an expired session costs
        // nothing extra. last_activity is unix time; absent on a freshly migrated
        // session, in which case we set it below rather than expire immediately.
        $now = time();
        if (isset($_SESSION['last_activity'])
            && ($now - (int) $_SESSION['last_activity']) > SERVER_IDLE) {
            clear_session();
            return null;
        }

        $stmt = $pdo->prepare(
            'SELECT id, account_id, name, email, cell, home_office, active, is_admin, must_change_password
             FROM users WHERE id = ? AND active = 1'
        );
        $stmt->execute(array((int) $_SESSION['uid']));
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        // Refresh the idle window on every authenticated request.
        $_SESSION['last_activity'] = $now;
        return $row;
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
