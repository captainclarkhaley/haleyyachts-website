<?php
/**
 * doc-cron.php - vendor document expiration reminders. Runs ONCE PER DAY as a
 * scheduled shell job (cPanel cron), never from the web.
 *
 * On each run it walks every vendor_documents row that has an expires_at and, for
 * each one, sends AT MOST ONE reminder email:
 *   - EXPIRED:  once the expiration date has passed and reminded_exp = 0, send the
 *               "expired" reminder; on a successful send set reminded_exp = 1.
 *   - 10-DAY:   otherwise, when the document is within 10 days of expiry (1..10
 *               days out) and reminded_10d = 0, send the "expiring" reminder; on a
 *               successful send set reminded_10d = 1.
 *   Expiry takes priority. A flag is set ONLY after the send actually succeeded,
 *   so a transient mail failure simply retries on the next daily run.
 *
 * DIRECTION (provided_by): each document is either provided BY THE VENDOR to us
 * ('vendor', the default and original behavior) or provided BY US to the vendor
 * ('us'). The cadence + flags (reminded_10d / reminded_exp) are identical for both;
 * only the recipient and message differ.
 *
 * RECIPIENT + MAIL by direction:
 *   - 'vendor': To = the vendor's PRIMARY email (vendors.email if set, else the
 *     primary contact's email, else the first contact's email). CC = admin@OWYG.com.
 *     Reply-To = admin@OWYG.com. From NAME = "Admin at One Water Yacht Group". The
 *     body asks the vendor to email an updated document to admin@OWYG.com. If the
 *     vendor has NO resolvable email we cannot remind, so the row is skipped and
 *     logged (flags stay unset so a later address addition still reminds).
 *   - 'us': the audience is INTERNAL. To = admin@OWYG.com (the vendor is NOT
 *     emailed). No CC. Reply-To = admin@OWYG.com. From NAME = "Admin at One Water
 *     Yacht Group". The body tells us OUR policy for that vendor is expiring/expired
 *     and to upload the renewed policy under that vendor's documents. There is always
 *     a recipient, so this case never skips for a missing vendor email.
 *
 * From address = default (no-reply@haleyyachts.com). Every value is HTML-escaped in
 * the HTML body; header values are CR/LF-stripped before they touch a header.
 *
 * TIME: everything is UTC. expires_at is a plain YYYY-MM-DD date; we compare it
 * against gmdate('Y-m-d H:i:s').
 */

// ---------------------------------------------------------------------------
// CLI-ONLY GUARD (first thing that runs). A web request can never run this.
// ---------------------------------------------------------------------------
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

// The DB layer keys its paths off DOCUMENT_ROOT, which the CLI does not set.
// Point it at the site root (two levels up from /vendors/api/) so vdb_connect
// opens the same vendors.sqlite the web app uses.
if (empty($_SERVER['DOCUMENT_ROOT'])) {
    $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/vendors/api/db.php';
require_once __DIR__ . '/../mail-smtp.php';

// Reminder branding. DOC_CRON_FROM_NAME is a FIXED contextual label (not an
// environment value), so it stays a constant. The admin email + the absolute
// site base are now DB-backed suite_settings (Phase 1): they are read ONCE in
// MAIN after vdb_connect (each with its original hardcoded literal as fallback)
// and threaded through, so a bad row can never change routing mid-run and a
// missing/blank setting behaves identically to today.
define('DOC_CRON_FROM_NAME',   'Admin at One Water Yacht Group');

/** HTML-escape a value for safe inclusion in the HTML body. */
function doc_cron_h($v)
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

/** Strip CR/LF so a value can never inject an extra email header. */
function doc_cron_header_safe($v)
{
    return trim(str_replace(array("\r", "\n"), ' ', (string) $v));
}

/** Render a stored YYYY-MM-DD date as "Mon D, YYYY" (empty on bad input). */
function doc_cron_format_date($raw)
{
    $s = trim((string) $raw);
    if ($s === '') { return ''; }
    $p = explode('-', explode(' ', $s)[0]);
    if (count($p) !== 3) { return $s; }
    $months = array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                    'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');
    $mi = (int) $p[1] - 1;
    if ($mi < 0 || $mi > 11) { return $s; }
    return $months[$mi] . ' ' . (int) $p[2] . ', ' . $p[0];
}

/**
 * Resolve the vendor's PRIMARY email:
 *   1. vendors.email if non-empty
 *   2. else the primary contact (is_primary = 1) email
 *   3. else the first contact's email
 * Returns the address, or '' when the vendor has no usable address anywhere.
 */
function doc_cron_vendor_email(PDO $pdo, $vendorId)
{
    $vStmt = $pdo->prepare('SELECT email FROM vendors WHERE id = ?');
    $vStmt->execute(array((int) $vendorId));
    $vEmail = trim((string) $vStmt->fetchColumn());
    if ($vEmail !== '') {
        return $vEmail;
    }

    $pStmt = $pdo->prepare("
        SELECT email FROM contacts
        WHERE vendor_id = ? AND is_primary = 1 AND email <> ''
        LIMIT 1
    ");
    $pStmt->execute(array((int) $vendorId));
    $pEmail = trim((string) $pStmt->fetchColumn());
    if ($pEmail !== '') {
        return $pEmail;
    }

    $fStmt = $pdo->prepare("
        SELECT email FROM contacts
        WHERE vendor_id = ? AND email <> ''
        ORDER BY is_primary DESC, id
        LIMIT 1
    ");
    $fStmt->execute(array((int) $vendorId));
    return trim((string) $fStmt->fetchColumn());
}

/** Vendor display name, or 'this vendor' when the row somehow has no name. */
function doc_cron_vendor_name(PDO $pdo, $vendorId)
{
    $stmt = $pdo->prepare('SELECT name FROM vendors WHERE id = ?');
    $stmt->execute(array((int) $vendorId));
    $name = trim((string) $stmt->fetchColumn());
    return $name !== '' ? $name : 'this vendor';
}

/**
 * Whitelist the stored direction to 'vendor' or 'us' (anything else -> 'vendor'),
 * so a bad/legacy value can never route an email the wrong way.
 */
function doc_cron_provided_by(array $row)
{
    $v = isset($row['provided_by']) ? (string) $row['provided_by'] : 'vendor';
    return ($v === 'us') ? 'us' : 'vendor';
}

/**
 * Build + send one reminder for a document row. $mode is 'expired' or 'expiring'.
 * Branches on the document direction:
 *   - 'vendor' (default): To = vendor primary email ($toEmail); CC + Reply-To =
 *     admin; asks the VENDOR to send us an updated document.
 *   - 'us': To = admin@OWYG.com (internal); no CC; Reply-To = admin; tells US that
 *     OUR policy for this vendor is expiring/expired and to upload the renewal.
 * Both use From name "Admin at One Water Yacht Group" and the default (no-reply)
 * From address. The document description is included in both variants when present.
 * Every value is HTML-escaped in the HTML body and CR/LF-stripped in headers.
 * Returns the boolean mail_smtp_send result (never throws).
 *
 * $adminEmail (the doc_admin_email setting) and $siteBase (the site_base_url
 * setting) are read ONCE in MAIN and passed in, so routing cannot change
 * mid-run. Both carry their original hardcoded literal as the fallback default.
 */
function doc_cron_send(PDO $pdo, array $row, $toEmail, $vendorName, $mode, $adminEmail, $siteBase)
{
    $purpose     = (string) $row['purpose'];
    $description = isset($row['description']) ? trim((string) $row['description']) : '';
    $expiryDate  = doc_cron_format_date($row['expires_at']);
    $expired     = ($mode === 'expired');
    $direction   = doc_cron_provided_by($row);
    $docId       = isset($row['id']) ? (int) $row['id'] : 0;

    if ($direction === 'us') {
        // ---- INTERNAL variant: WE provide this policy to the vendor. ----
        $subject = doc_cron_header_safe(
            ($expired ? 'Our policy expired: ' : 'Our policy expiring: ')
            . $vendorName . ' - ' . $purpose
        );

        $lead = $expired
            ? ('The policy we provide to ' . $vendorName . ' for ' . $purpose . ' has expired'
                . ($expiryDate !== '' ? ' (expired ' . $expiryDate . ')' : '') . '.')
            : ('The policy we provide to ' . $vendorName . ' for ' . $purpose . ' is expiring soon'
                . ($expiryDate !== '' ? ' (expires ' . $expiryDate . ')' : '') . '.');

        $text = array();
        $text[] = $expired ? 'OUR POLICY EXPIRED' : 'OUR POLICY EXPIRING';
        $text[] = '';
        $text[] = $lead;
        $text[] = 'Vendor: ' . $vendorName;
        $text[] = 'Document: ' . $purpose;
        if ($description !== '') {
            $text[] = 'Note: ' . $description;
        }
        if ($expiryDate !== '') {
            $text[] = ($expired ? 'Expired on: ' : 'Expires on: ') . $expiryDate;
        }
        $text[] = '';
        $text[] = 'Please upload the renewed policy under this vendor\'s documents so our records stay current.';
        $text[] = '';
        $text[] = 'Admin at One Water Yacht Group';
        $textBody = implode("\r\n", $text);

        $htmlBody = doc_cron_html_body_us(
            $expired,
            doc_cron_h($vendorName),
            doc_cron_h($purpose),
            doc_cron_h($description),
            doc_cron_h($expiryDate),
            $siteBase
        );

        $to = doc_cron_header_safe($toEmail); // = the admin email from the caller
        return mail_smtp_send(
            $to,
            $subject,
            $textBody,
            $htmlBody,
            'vendor-doc-expiry:doc-' . $docId,
            $adminEmail,            // Reply-To: admin email
            '',                     // From address: default (no-reply@haleyyachts.com)
            '',                     // CC: none (internal)
            DOC_CRON_FROM_NAME      // From NAME: Admin at One Water Yacht Group
        );
    }

    // ---- VENDOR variant (unchanged behavior): the vendor provides this to us. ----
    $subject = doc_cron_header_safe(
        ($expired ? 'Document expired: ' : 'Document expiring: ')
        . $vendorName . ' - ' . $purpose
    );

    // --- plain-text body ---
    $lead = $expired
        ? ('A document we have on file for ' . $vendorName . ' has expired'
            . ($expiryDate !== '' ? ' (expired ' . $expiryDate . ')' : '') . '.')
        : ('A document we have on file for ' . $vendorName . ' is expiring soon'
            . ($expiryDate !== '' ? ' (expires ' . $expiryDate . ')' : '') . '.');

    $text = array();
    $text[] = $expired ? 'DOCUMENT EXPIRED' : 'DOCUMENT EXPIRING';
    $text[] = '';
    $text[] = $lead;
    $text[] = 'Document: ' . $purpose;
    if ($description !== '') {
        $text[] = 'Note: ' . $description;
    }
    if ($expiryDate !== '') {
        $text[] = ($expired ? 'Expired on: ' : 'Expires on: ') . $expiryDate;
    }
    $text[] = '';
    $text[] = 'Please send an updated document to ' . $adminEmail . ' so we can keep our records current.';
    $text[] = '';
    $text[] = 'Thank you,';
    $text[] = 'Admin at One Water Yacht Group';
    $textBody = implode("\r\n", $text);

    // --- HTML body (every value escaped) ---
    $htmlBody = doc_cron_html_body(
        $expired,
        doc_cron_h($vendorName),
        doc_cron_h($purpose),
        doc_cron_h($description),
        doc_cron_h($expiryDate),
        doc_cron_h($adminEmail),
        $siteBase
    );

    $to = doc_cron_header_safe($toEmail);

    return mail_smtp_send(
        $to,
        $subject,
        $textBody,
        $htmlBody,
        'vendor-doc-expiry:doc-' . $docId,
        $adminEmail,            // Reply-To: admin email
        '',                     // From address: default (no-reply@haleyyachts.com)
        $adminEmail,            // CC: admin email
        DOC_CRON_FROM_NAME      // From NAME: Admin at One Water Yacht Group
    );
}

/**
 * Short co-branded HTML body. All values arrive ALREADY escaped from the caller.
 * Same navy-masthead / cyan-keyline tone as the pocket reminders.
 */
function doc_cron_html_body($expired, $eVendor, $ePurpose, $eDescription, $eExpiryDate, $eAdminEmail, $siteBase)
{
    $eSiteBase = doc_cron_h($siteBase);
    $heading = $expired ? 'Document Expired' : 'Document Expiring';
    $lead = $expired
        ? ('A document we have on file for <strong>' . $eVendor . '</strong> has expired.')
        : ('A document we have on file for <strong>' . $eVendor . '</strong> is expiring soon.');
    $descRow = ($eDescription !== '')
        ? '<p style="font-family:\'Open Sans\', Arial, Helvetica, sans-serif; font-size:14px; line-height:20px; color:#5b7a96; margin:0 0 4px 0;">' . $eDescription . '</p>'
        : '';
    $dateLabel = $expired ? 'Expired on' : 'Expires on';
    $dateRow = ($eExpiryDate !== '')
        ? '<p style="font-family:\'Open Sans\', Arial, Helvetica, sans-serif; font-size:14px; line-height:20px; color:#5b7a96; margin:0 0 4px 0;">' . $dateLabel . ' ' . $eExpiryDate . '</p>'
        : '';

    return '<!DOCTYPE html>' .
'<html lang="en"><head><meta charset="UTF-8" />' .
'<meta name="viewport" content="width=device-width, initial-scale=1" />' .
'<title>' . $heading . '</title></head>' .
'<body style="margin:0; padding:0; width:100%; background-color:#f4f6f8;" bgcolor="#f4f6f8">' .

'<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" bgcolor="#f4f6f8" style="background-color:#f4f6f8;">' .
'<tr><td align="center" style="padding:24px 12px;">' .

'<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="600" style="width:600px; max-width:600px; background-color:#ffffff;" bgcolor="#ffffff">' .

// Masthead
'<tr><td align="center" valign="middle" bgcolor="#0d2847" ' .
'style="background-color:#0d2847; background-image: linear-gradient(135deg, #0a1628 0%, #0d2847 50%, #134a6e 100%); padding:30px 32px;">' .
'<p style="font-family:\'Open Sans\', Arial, Helvetica, sans-serif; font-size:13px; line-height:18px; color:#e8eef5; font-weight:600; letter-spacing:2px; text-transform:uppercase; margin:0 0 16px 0; text-align:center;">' .
$heading . '</p>' .
'<img src="' . $eSiteBase . '/images/email/owyg-banner-reverse.png" width="200" height="52" alt="One Water Yacht Group" ' .
'style="display:block; width:200px; max-width:200px; height:auto; border:0; outline:none; margin:0 auto;" />' .
'</td></tr>' .

// Cyan keyline
'<tr><td bgcolor="#21cbea" height="3" style="background-color:#21cbea; height:3px; line-height:3px; font-size:0; padding:0;">&nbsp;</td></tr>' .

// Body
'<tr><td bgcolor="#ffffff" style="background-color:#ffffff; padding:28px 40px 8px 40px;">' .
'<p style="font-family:\'Open Sans\', Arial, Helvetica, sans-serif; font-size:16px; line-height:24px; color:#0a1628; margin:0 0 14px 0;">' . $lead . '</p>' .
'<p style="font-family:\'Open Sans\', Arial, Helvetica, sans-serif; font-size:15px; line-height:22px; color:#0a1628; font-weight:700; margin:0 0 4px 0;">Document: ' . $ePurpose . '</p>' .
$descRow .
$dateRow .
'<p style="font-family:\'Open Sans\', Arial, Helvetica, sans-serif; font-size:15px; line-height:22px; color:#0a1628; margin:18px 0 0 0;">Please send an updated document to ' .
'<a href="mailto:' . $eAdminEmail . '" style="color:#134a6e; font-weight:700; text-decoration:none;">' . $eAdminEmail . '</a> so we can keep our records current.</p>' .
'</td></tr>' .

// Footer
'<tr><td bgcolor="#070e1a" style="background-color:#070e1a; padding:30px 32px 26px 32px;" align="center">' .
'<img src="' . $eSiteBase . '/images/email/owyg-banner-reverse.png" width="200" height="52" alt="One Water Yacht Group" ' .
'style="display:block; width:200px; max-width:200px; height:52px; border:0; outline:none; margin:0 auto 16px auto;" />' .
'<p style="font-family:\'Open Sans\', Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:rgba(255,255,255,0.55); margin:0;">' .
'&copy; 2026 Haley Yachts &nbsp;|&nbsp; One Water Yacht Group &nbsp;|&nbsp; Palm Beach Gardens, Florida</p>' .
'</td></tr>' .

'</table>' .
'</td></tr></table>' .
'</body></html>';
}

/**
 * INTERNAL variant of the HTML body, for a policy WE provide to the vendor
 * (provided_by = 'us'). Same co-branded shell; the copy is aimed at us, not the
 * vendor, and it asks us to upload the renewal under the vendor's documents. All
 * values arrive ALREADY escaped from the caller.
 */
function doc_cron_html_body_us($expired, $eVendor, $ePurpose, $eDescription, $eExpiryDate, $siteBase)
{
    $eSiteBase = doc_cron_h($siteBase);
    $heading = $expired ? 'Our Policy Expired' : 'Our Policy Expiring';
    $lead = $expired
        ? ('The policy we provide to <strong>' . $eVendor . '</strong> for ' . $ePurpose . ' has expired.')
        : ('The policy we provide to <strong>' . $eVendor . '</strong> for ' . $ePurpose . ' is expiring soon.');
    $descRow = ($eDescription !== '')
        ? '<p style="font-family:\'Open Sans\', Arial, Helvetica, sans-serif; font-size:14px; line-height:20px; color:#5b7a96; margin:0 0 4px 0;">' . $eDescription . '</p>'
        : '';
    $dateLabel = $expired ? 'Expired on' : 'Expires on';
    $dateRow = ($eExpiryDate !== '')
        ? '<p style="font-family:\'Open Sans\', Arial, Helvetica, sans-serif; font-size:14px; line-height:20px; color:#5b7a96; margin:0 0 4px 0;">' . $dateLabel . ' ' . $eExpiryDate . '</p>'
        : '';

    return '<!DOCTYPE html>' .
'<html lang="en"><head><meta charset="UTF-8" />' .
'<meta name="viewport" content="width=device-width, initial-scale=1" />' .
'<title>' . $heading . '</title></head>' .
'<body style="margin:0; padding:0; width:100%; background-color:#f4f6f8;" bgcolor="#f4f6f8">' .

'<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" bgcolor="#f4f6f8" style="background-color:#f4f6f8;">' .
'<tr><td align="center" style="padding:24px 12px;">' .

'<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="600" style="width:600px; max-width:600px; background-color:#ffffff;" bgcolor="#ffffff">' .

// Masthead
'<tr><td align="center" valign="middle" bgcolor="#0d2847" ' .
'style="background-color:#0d2847; background-image: linear-gradient(135deg, #0a1628 0%, #0d2847 50%, #134a6e 100%); padding:30px 32px;">' .
'<p style="font-family:\'Open Sans\', Arial, Helvetica, sans-serif; font-size:13px; line-height:18px; color:#e8eef5; font-weight:600; letter-spacing:2px; text-transform:uppercase; margin:0 0 16px 0; text-align:center;">' .
$heading . '</p>' .
'<img src="' . $eSiteBase . '/images/email/owyg-banner-reverse.png" width="200" height="52" alt="One Water Yacht Group" ' .
'style="display:block; width:200px; max-width:200px; height:auto; border:0; outline:none; margin:0 auto;" />' .
'</td></tr>' .

// Cyan keyline
'<tr><td bgcolor="#21cbea" height="3" style="background-color:#21cbea; height:3px; line-height:3px; font-size:0; padding:0;">&nbsp;</td></tr>' .

// Body
'<tr><td bgcolor="#ffffff" style="background-color:#ffffff; padding:28px 40px 8px 40px;">' .
'<p style="font-family:\'Open Sans\', Arial, Helvetica, sans-serif; font-size:16px; line-height:24px; color:#0a1628; margin:0 0 14px 0;">' . $lead . '</p>' .
'<p style="font-family:\'Open Sans\', Arial, Helvetica, sans-serif; font-size:15px; line-height:22px; color:#0a1628; font-weight:700; margin:0 0 4px 0;">Vendor: ' . $eVendor . '</p>' .
'<p style="font-family:\'Open Sans\', Arial, Helvetica, sans-serif; font-size:15px; line-height:22px; color:#0a1628; font-weight:700; margin:0 0 4px 0;">Document: ' . $ePurpose . '</p>' .
$descRow .
$dateRow .
'<p style="font-family:\'Open Sans\', Arial, Helvetica, sans-serif; font-size:15px; line-height:22px; color:#0a1628; margin:18px 0 0 0;">Please upload the renewed policy under this vendor\'s documents so our records stay current.</p>' .
'</td></tr>' .

// Footer
'<tr><td bgcolor="#070e1a" style="background-color:#070e1a; padding:30px 32px 26px 32px;" align="center">' .
'<img src="' . $eSiteBase . '/images/email/owyg-banner-reverse.png" width="200" height="52" alt="One Water Yacht Group" ' .
'style="display:block; width:200px; max-width:200px; height:52px; border:0; outline:none; margin:0 auto 16px auto;" />' .
'<p style="font-family:\'Open Sans\', Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:rgba(255,255,255,0.55); margin:0;">' .
'&copy; 2026 Haley Yachts &nbsp;|&nbsp; One Water Yacht Group &nbsp;|&nbsp; Palm Beach Gardens, Florida</p>' .
'</td></tr>' .

'</table>' .
'</td></tr></table>' .
'</body></html>';
}

// ---------------------------------------------------------------------------
// MAIN
// ---------------------------------------------------------------------------

$sent10   = 0;
$sentExp  = 0;
$errors   = 0;

try {
    $pdo = vdb_connect();
} catch (Throwable $e) {
    error_log('doc-cron: could not open the database: ' . $e->getMessage());
    fwrite(STDERR, 'doc-cron: DB open failed: ' . $e->getMessage() . "\n");
    exit(1);
}

// Read the environment values ONCE for the whole run (each with its original
// hardcoded literal as the fallback). Reading them here, before the loop, means
// a later bad/blank row can never change routing mid-run, and a missing setting
// behaves exactly like the old constant.
$adminEmail = suite_setting($pdo, 'doc_admin_email', 'admin@OWYG.com');
$siteBase   = suite_setting($pdo, 'site_base_url', 'https://haleyyachts.com');

$now   = gmdate('Y-m-d H:i:s');
$nowTs = strtotime($now . ' UTC');
if ($nowTs === false) { $nowTs = time(); }

try {
    $rows = $pdo
        ->query('SELECT * FROM vendor_documents WHERE expires_at IS NOT NULL')
        ->fetchAll();
} catch (Throwable $e) {
    error_log('doc-cron: query failed: ' . $e->getMessage());
    fwrite(STDERR, 'doc-cron: query failed: ' . $e->getMessage() . "\n");
    exit(1);
}

foreach ($rows as $row) {
    // Each document is isolated: one bad row can never abort the whole run.
    try {
        $id  = (int) $row['id'];
        $exp = trim((string) $row['expires_at']);
        if ($exp === '') {
            continue; // no expiry -> nothing to remind about
        }
        // Compare against END of the expiry day so a doc expiring "today" is not
        // treated as already expired until the day is over.
        $expTs = strtotime($exp . ' 23:59:59 UTC');
        if ($expTs === false) {
            continue; // unparseable date: skip rather than guess
        }

        $isExpired = ($expTs <= $nowTs);
        // Whole days until expiry, rounded up (only meaningful when not expired).
        $daysUntil = (int) ceil(($expTs - $nowTs) / 86400);

        // Decide which (if any) reminder is due. Expiry takes priority. One per run.
        $mode = null;
        if ($isExpired && (int) $row['reminded_exp'] === 0) {
            $mode = 'expired';
        } elseif (!$isExpired && $daysUntil > 0 && $daysUntil <= 10 && (int) $row['reminded_10d'] === 0) {
            $mode = 'expiring';
        }
        if ($mode === null) {
            continue;
        }

        $vendorId  = (int) $row['vendor_id'];
        $direction = doc_cron_provided_by($row);

        if ($direction === 'us') {
            // WE provide this policy: the audience is internal, so the admin email
            // is always the recipient. This case never skips for a missing vendor
            // email.
            $toEmail = $adminEmail;
        } else {
            // The vendor provides this to us: email the vendor's primary address.
            $toEmail = doc_cron_vendor_email($pdo, $vendorId);
            if ($toEmail === '') {
                // Cannot remind without an address. Log + skip; flags stay unset so a
                // later address addition still gets a reminder.
                error_log('doc-cron: no email for vendor ' . $vendorId . ' (document ' . $id . '); skipped.');
                continue;
            }
        }
        $vendorName = doc_cron_vendor_name($pdo, $vendorId);

        $ok = doc_cron_send($pdo, $row, $toEmail, $vendorName, $mode, $adminEmail, $siteBase);
        if ($ok) {
            if ($mode === 'expired') {
                $pdo->prepare('UPDATE vendor_documents SET reminded_exp = 1 WHERE id = ?')->execute(array($id));
                $sentExp++;
            } else {
                $pdo->prepare('UPDATE vendor_documents SET reminded_10d = 1 WHERE id = ?')->execute(array($id));
                $sent10++;
            }
        } else {
            $errors++;
            error_log('doc-cron: ' . $mode . ' reminder NOT sent for document ' . $id . ' (vendor ' . $vendorId . ')');
        }
    } catch (Throwable $e) {
        $errors++;
        error_log('doc-cron: error on document '
            . (isset($row['id']) ? (int) $row['id'] : '?') . ': ' . $e->getMessage());
    }
}

// One-line summary for the cron log.
echo 'doc-cron: ' . $sent10 . ' 10-day, ' . $sentExp . ' expired, ' . $errors . ' error'
    . ($errors === 1 ? '' : 's') . "\n";
exit(0);
