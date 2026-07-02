<?php
/**
 * mail-smtp.php - shared authenticated-SMTP sender for ALL Broker Suite app
 * mail (the Vendor app auth mail in api/mail-lib.php AND the Pocket Listings
 * network notification in pocket/mailer.php).
 *
 * WHY THIS EXISTS
 *   Bare PHP mail() does not deliver reliably on this cPanel/GoDaddy host, and
 *   even when it does the message is unauthenticated (SPF/DKIM fail -> spam).
 *   Everything now goes out through the real no-reply@haleyyachts.com mailbox
 *   over authenticated SMTP (PHPMailer), so the message aligns with the domain's
 *   SPF/DKIM and lands in the inbox.
 *
 * ONE SECRETS FILE
 *   Credentials live in an UNTRACKED, gitignored, server-side file:
 *       vendors/mail-secrets.php   (copied from vendors/mail-secrets.sample.php)
 *   Both mailers read that ONE file, so Clark only ever maintains one password.
 *   For backward compatibility this also falls back to the legacy Pocket file
 *   (vendors/pocket/mail-config.php) if the consolidated file is not present.
 *
 * BEST EFFORT
 *   mail_smtp_send() NEVER throws to the caller. On any failure (config missing,
 *   auth/connect/TLS error) it error_log()s a generic line - never the password
 *   or the message body - and returns false, exactly like the old mail() path.
 *   A missing config degrades gracefully (log + return false); it never
 *   white-screens the app.
 */

if (!function_exists('mail_smtp_config')) {

    /**
     * Load and normalize the shared SMTP credentials.
     *
     * Looks first for the consolidated vendors/mail-secrets.php, then falls back
     * to the legacy vendors/pocket/mail-config.php so an existing deployment that
     * only has the old file keeps working. Returns a normalized config array, or
     * null when no usable config file is found (missing file, not an array, or
     * missing a required key). Callers treat null as "cannot send".
     *
     * @return array|null
     */
    function mail_smtp_config()
    {
        // Search order: consolidated first, legacy pocket file second.
        $candidates = array(
            __DIR__ . '/mail-secrets.php',
            __DIR__ . '/pocket/mail-config.php',
        );

        $cfg = null;
        foreach ($candidates as $path) {
            if (is_file($path)) {
                $loaded = include $path;
                if (is_array($loaded)) {
                    $cfg = $loaded;
                    break;
                }
            }
        }
        if ($cfg === null) {
            return null;
        }

        // Required keys must be present and non-empty, or we cannot authenticate.
        foreach (array('host', 'username', 'password') as $k) {
            if (!isset($cfg[$k]) || (string) $cfg[$k] === '') {
                return null;
            }
        }

        return array(
            'host'              => (string) $cfg['host'],
            'port'              => isset($cfg['port']) ? (int) $cfg['port'] : 465,
            'secure'            => isset($cfg['secure']) ? (string) $cfg['secure'] : 'ssl',
            'username'          => (string) $cfg['username'],
            'password'          => (string) $cfg['password'],
            'from_name'         => (isset($cfg['from_name']) && (string) $cfg['from_name'] !== '')
                                        ? (string) $cfg['from_name'] : 'OWYG Brokers',
            'allow_self_signed' => !empty($cfg['allow_self_signed']),
        );
    }

    /**
     * Send one message over authenticated SMTP. Best-effort: returns true on a
     * successful hand-off to the SMTP server, false on any failure, and never
     * throws to the caller.
     *
     * @param string      $toEmail   recipient address (already header-safe)
     * @param string      $subject   subject line (already header-safe)
     * @param string      $textBody  plain-text body (always required)
     * @param string|null $htmlBody  optional HTML body; when non-empty the message
     *                               is sent multipart (HTML + the text AltBody)
     * @param string      $context   short tag for the error log (e.g. 'onboarding')
     * @param string      $replyTo   optional Reply-To address ('' = none)
     * @param string      $fromEmail From address; defaults to the config username
     *                               (the authenticated mailbox) when left ''
     * @param string      $cc        optional CC address ('' = none)
     * @param string      $fromName  optional From display name; defaults to the
     *                               config from_name when left ''
     * @return bool
     */
    function mail_smtp_send($toEmail, $subject, $textBody, $htmlBody, $context,
        $replyTo = '', $fromEmail = '', $cc = '', $fromName = '')
    {
        $mail = null;
        try {
            $cfg = mail_smtp_config();
            if ($cfg === null) {
                error_log('mail-smtp: ' . $context . ' not sent - mail-secrets.php '
                    . 'missing or incomplete.');
                return false;
            }

            require_once __DIR__ . '/lib/phpmailer/Exception.php';
            require_once __DIR__ . '/lib/phpmailer/PHPMailer.php';
            require_once __DIR__ . '/lib/phpmailer/SMTP.php';

            $mail = new PHPMailer\PHPMailer\PHPMailer(true); // true => throw on error
            $mail->isSMTP();
            $mail->Host     = $cfg['host'];
            $mail->Port     = $cfg['port'];
            $mail->SMTPAuth = true;
            $mail->Username = $cfg['username'];
            $mail->Password = $cfg['password'];
            if ($cfg['secure'] !== '') {
                // 'ssl' => implicit TLS (465), 'tls' => STARTTLS (587).
                $mail->SMTPSecure = $cfg['secure'];
            }
            if ($cfg['allow_self_signed']) {
                // Relax the cert check for a same-server relay whose cert name may
                // not match the configured host alias. Affects only the handshake.
                $mail->SMTPOptions = array('ssl' => array(
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ));
            }

            $mail->CharSet = 'UTF-8';

            // From is the authenticated mailbox by default so SPF/DKIM align. The
            // From DISPLAY NAME may be overridden per-send via $fromName; when left
            // '' it keeps the config default exactly as before.
            $from     = ($fromEmail !== '') ? $fromEmail : $cfg['username'];
            $fromDisp = ($fromName !== '') ? $fromName : $cfg['from_name'];
            $mail->setFrom($from, $fromDisp);

            $mail->addAddress($toEmail);
            if ($replyTo !== '') {
                $mail->addReplyTo($replyTo);
            }
            if ($cc !== '') {
                $mail->addCC($cc);
            }

            $mail->Subject = $subject;

            if ($htmlBody !== null && $htmlBody !== '') {
                $mail->isHTML(true);
                $mail->Body    = $htmlBody;
                $mail->AltBody = $textBody;
            } else {
                $mail->isHTML(false);
                $mail->Body = $textBody;
            }

            $mail->send();
            return true;
        } catch (Throwable $e) {
            // PHPMailer stashes the SMTP-level detail in ErrorInfo; include it so
            // the log shows WHY the send failed (auth, connect, TLS). No body or
            // password is ever logged.
            $detail = ($mail !== null && $mail->ErrorInfo !== '') ? ' | ' . $mail->ErrorInfo : '';
            error_log('mail-smtp: ' . $context . ' send failed' . $detail);
            return false;
        }
    }
}
