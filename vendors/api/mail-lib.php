<?php
/**
 * mail-lib.php - shared, best-effort email builders for the Vendor app auth flow.
 *
 * Included by BOTH the staff auth endpoint (vendors/auth.php) and the admin
 * accounts endpoint (admin/users-api.php) so the onboarding, admin-reset, and
 * password-changed notices are built and sent from one place.
 *
 * Every sender here is BEST-EFFORT: a failed send is logged with a generic line
 * (NEVER a password) and the boolean is returned so the caller can ignore the
 * failure and still complete its operation (create / reset / change).
 *
 * TRANSPORT: mail is sent over authenticated SMTP through the real
 * no-reply@haleyyachts.com mailbox (see ../mail-smtp.php), NOT bare mail(). This
 * is what lets SPF/DKIM authenticate the message so it reaches the inbox.
 * Credentials live in the untracked, gitignored vendors/mail-secrets.php. If
 * that file is missing, the send degrades gracefully (logged, returns false) and
 * the app flow is never interrupted.
 *
 * SECURITY: a temporary password is included in the BODY of the onboarding and
 * admin-reset emails (that is the whole point of those messages). It is NEVER
 * written to error_log or any HTTP response. The password-changed confirmation
 * contains no password at all.
 */

// Shared authenticated-SMTP sender + config loader. Both Broker Suite mailers
// route through this so there is one transport and one secrets file.
require_once __DIR__ . '/../mail-smtp.php';

if (!defined('VMAIL_FROM')) {
    // From-address for all Vendor app system mail. Must be on the site domain so
    // SPF/DKIM can authenticate it once those records are configured.
    define('VMAIL_FROM', 'no-reply@haleyyachts.com');
}

if (!defined('ADMIN_NOTIFY_EMAIL')) {
    // ADMIN NOTIFICATION ADDRESS. When a NON-admin staff member requests a vendor
    // delete, the request is forwarded here instead of deleting. Change this one
    // line to redirect those notifications.
    define('ADMIN_NOTIFY_EMAIL', 'annika@owyg.com');
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

    /**
     * Low-level best-effort sender. Sends a plain-text message over authenticated
     * SMTP via the shared mail_smtp_send() (From: OneWater <no-reply@...>, with a
     * Reply-To of the same mailbox). Returns true on a successful hand-off, false
     * otherwise. On failure it logs a generic line tagged with $context and NEVER
     * the password or body. The caller treats a false return as non-fatal.
     *
     * Signature is unchanged from the previous mail()-based version, so all
     * callers keep working without edits.
     */
    function vmail_send($toEmail, $subject, $body, $context)
    {
        // header-safe the To/Subject: strip CR/LF/NUL so nothing can inject a
        // second header. The From is a fixed constant, not user input.
        $to      = str_replace(array("\r", "\n", "\0"), '', (string) $toEmail);
        $subject = str_replace(array("\r", "\n", "\0"), '', (string) $subject);

        // Plain-text only ($htmlBody = null). Reply-To mirrors the From mailbox.
        return mail_smtp_send(
            trim($to),
            trim($subject),
            $body,
            null,
            'vendor-mail:' . $context,
            VMAIL_FROM,   // Reply-To
            VMAIL_FROM    // From address (the authenticated mailbox)
        );
    }

    /**
     * New-account onboarding email. Carries the login URL, the user's Account ID,
     * and the admin-typed temporary password, plus a note that they will be asked
     * to set their own password on first sign-in.
     */
    function send_onboarding_email($toEmail, $accountId, $tempPassword)
    {
        $url     = vmail_login_url();
        $subject = 'Your OneWater Vendor App account';
        $body =
            "An account has been created for you in the OneWater Vendor App. This new " .
            "tool will help you and your fellow OWYG brokers share information regarding " .
            "the people and the companies that you use in providing quality services to " .
            "your clients.\r\n\r\n" .
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
        $subject = 'OneWater Vendor App - your password was reset';
        $body =
            "An administrator has reset the password on your OneWater Vendor App account.\r\n\r\n" .
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
        $subject = 'OneWater Vendor App - your password was changed';
        $body =
            "This is a confirmation that the password on your OneWater Vendor App " .
            "account was just changed.\r\n\r\n" .
            "If this was you, no action is needed.\r\n\r\n" .
            "If this was NOT you, contact your administrator right away.\r\n";

        return vmail_send($toEmail, $subject, $body, 'password-changed');
    }

    /**
     * Vendor delete-request notice. Sent to ADMIN_NOTIFY_EMAIL when a NON-admin
     * staff member asks to delete a vendor (the delete itself is blocked server
     * side; this email is the only side effect). Names WHO requested it (name +
     * account id) and WHICH vendor. Best-effort, same pattern as the other senders.
     * NOTE: actual delivery depends on the domain's SPF/DKIM being configured for
     * VMAIL_FROM - until then this may spam-folder or silently drop.
     */
    function send_delete_request_email($requesterName, $requesterAccount, $vendorName)
    {
        $subject = 'OneWater Vendor App - vendor delete request';
        $body =
            "A staff member has requested that a vendor be deleted from the Vendor App.\r\n\r\n" .
            "Requested by: " . $requesterName . " (account: " . $requesterAccount . ")\r\n" .
            "Vendor to delete: " . $vendorName . "\r\n\r\n" .
            "Non-admin staff cannot delete vendors themselves, so this request was " .
            "forwarded for an administrator to review and action in the Vendor App.\r\n";

        return vmail_send(ADMIN_NOTIFY_EMAIL, $subject, $body, 'delete-request');
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

        $subject = 'OneWater Vendor App - password reset';
        $body =
            "A password reset was requested for your OneWater Vendor App account.\r\n\r\n" .
            "Open this link to set a new password (it expires in 1 hour):\r\n\r\n" .
            $link . "\r\n\r\n" .
            "If you did not request this, you can ignore this email; your password will not change.\r\n";

        return vmail_send($toEmail, $subject, $body, 'reset-link');
    }
}
