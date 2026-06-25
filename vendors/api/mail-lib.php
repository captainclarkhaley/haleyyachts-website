<?php
/**
 * mail-lib.php - shared, best-effort email builders for the Vendor app auth flow.
 *
 * Included by BOTH the staff auth endpoint (vendors/auth.php) and the admin
 * accounts endpoint (admin/users-api.php) so the onboarding, admin-reset, and
 * password-changed notices are built and sent from one place.
 *
 * Every sender here is BEST-EFFORT: mail() returning false is logged with a
 * generic line (NEVER a password) and the boolean is returned so the caller can
 * ignore the failure and still complete its operation (create / reset / change).
 *
 * SECURITY: a temporary password is included in the BODY of the onboarding and
 * admin-reset emails (that is the whole point of those messages). It is NEVER
 * written to error_log or any HTTP response. The password-changed confirmation
 * contains no password at all.
 *
 * Actual deliverability still depends on the domain's SPF/DKIM being in place
 * for no-reply@haleyyachts.com - that is pending and out of scope for this file.
 */

if (!defined('VMAIL_FROM')) {
    // From-address for all Vendor app system mail. Must be on the site domain so
    // SPF/DKIM can authenticate it once those records are configured.
    define('VMAIL_FROM', 'no-reply@haleyyachts.com');
}

if (!function_exists('vmail_login_url')) {

    /**
     * The staff sign-in URL, built from a sanitized HTTP_HOST. Falls back to the
     * production host when the header is absent (e.g. CLI). The sanitizer strips
     * anything but a sane host so nothing hostile leaks into the link or headers.
     */
    function vmail_login_url()
    {
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'haleyyachts.com';
        $host = preg_replace('/[^A-Za-z0-9.\-:]/', '', $host);
        return 'https://' . $host . '/vendors/login.html';
    }

    /** Standard plain-text headers for all Vendor app system mail. */
    function vmail_headers()
    {
        return 'From: Haley Yachts <' . VMAIL_FROM . ">\r\n" .
               'Reply-To: ' . VMAIL_FROM . "\r\n" .
               "Content-Type: text/plain; charset=UTF-8\r\n";
    }

    /**
     * Low-level best-effort sender. Returns mail()'s bool. On failure it logs a
     * generic line tagged with $context and NEVER the password or body. The
     * caller treats a false return as non-fatal.
     */
    function vmail_send($toEmail, $subject, $body, $context)
    {
        $sent = @mail($toEmail, $subject, $body, vmail_headers());
        if (!$sent) {
            error_log('vendor-mail: ' . $context . ' send failed (mail() returned false).');
        }
        return $sent;
    }

    /**
     * New-account onboarding email. Carries the login URL, the user's Account ID,
     * and the admin-typed temporary password, plus a note that they will be asked
     * to set their own password on first sign-in.
     */
    function send_onboarding_email($toEmail, $accountId, $tempPassword)
    {
        $url     = vmail_login_url();
        $subject = 'Your Haley Yachts Vendor App account';
        $body =
            "An account has been created for you in the Haley Yachts Vendor App.\r\n\r\n" .
            "Sign in here:\r\n" . $url . "\r\n\r\n" .
            "Account ID: " . $accountId . "\r\n" .
            "Temporary password: " . $tempPassword . "\r\n\r\n" .
            "For your security, you will be asked to set your own password the first " .
            "time you sign in.\r\n\r\n" .
            "If you were not expecting this, contact your administrator.\r\n";

        return vmail_send($toEmail, $subject, $body, 'onboarding');
    }

    /**
     * Admin-reset notice. Tells the user an administrator reset their password,
     * gives the new temporary password, and that they will set a new one on the
     * next sign-in.
     */
    function send_admin_reset_email($toEmail, $accountId, $tempPassword)
    {
        $url     = vmail_login_url();
        $subject = 'Haley Yachts Vendor App - your password was reset';
        $body =
            "An administrator has reset the password on your Haley Yachts Vendor App account.\r\n\r\n" .
            "Sign in here:\r\n" . $url . "\r\n\r\n" .
            "Account ID: " . $accountId . "\r\n" .
            "Temporary password: " . $tempPassword . "\r\n\r\n" .
            "For your security, you will be asked to set a new password the next time " .
            "you sign in.\r\n\r\n" .
            "If you did not expect this, contact your administrator.\r\n";

        return vmail_send($toEmail, $subject, $body, 'admin-reset');
    }

    /**
     * Security confirmation sent after a password is changed (self-service, forced
     * first-login change, or a completed forgot-password reset). Contains NO
     * password - it is only an after-the-fact heads-up.
     */
    function send_password_changed_email($toEmail)
    {
        $subject = 'Haley Yachts Vendor App - your password was changed';
        $body =
            "This is a confirmation that the password on your Haley Yachts Vendor App " .
            "account was just changed.\r\n\r\n" .
            "If this was you, no action is needed.\r\n\r\n" .
            "If this was NOT you, contact your administrator right away.\r\n";

        return vmail_send($toEmail, $subject, $body, 'password-changed');
    }

    /**
     * Forgot-password reset-link email. Moved here from auth.php so all Vendor app
     * system mail lives in one place. Best-effort, same as the rest. The raw token
     * appears only in the link (never logged); the DB stores only its SHA-256 hash.
     */
    function send_reset_link_email($toEmail, $rawToken)
    {
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'haleyyachts.com';
        $host = preg_replace('/[^A-Za-z0-9.\-:]/', '', $host);
        $link = 'https://' . $host . '/vendors/reset.html?token=' . $rawToken;

        $subject = 'Haley Yachts Vendor App - password reset';
        $body =
            "A password reset was requested for your Haley Yachts Vendor App account.\r\n\r\n" .
            "Open this link to set a new password (it expires in 1 hour):\r\n\r\n" .
            $link . "\r\n\r\n" .
            "If you did not request this, you can ignore this email; your password will not change.\r\n";

        return vmail_send($toEmail, $subject, $body, 'reset-link');
    }
}
