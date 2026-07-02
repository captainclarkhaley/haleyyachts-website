<?php
/**
 * pocket/cron.php - Pocket Listings expiration lifecycle (Broker Suite app #2),
 * Phase 3. Runs ONCE PER DAY as a scheduled shell job (cPanel cron), never from
 * the web.
 *
 * On each run it walks every ACTIVE listing that has an expires_at and:
 *   - ARCHIVES it (status -> 'archived', archived_at = now) once it has expired.
 *     The record and its images are KEPT - never deleted. No email on archive.
 *   - Sends a FINAL reminder when it has <= 1 day left (once, guarded by
 *     reminded_1d), or an EARLY reminder when it has <= 7 days but > 1 day left
 *     (once, guarded by reminded_7d). Only ONE reminder per listing per run; the
 *     1-day reminder takes priority. The reminded flag is set ONLY after the send
 *     actually succeeded, so a transient mail failure simply retries next run.
 *
 * ***** TEMPORARY - TESTING ONLY *****
 * Reminder emails go to the pocket_notify_to setting (the single test inbox by
 * default), exactly like the new-listing notification. This is NOT the real
 * audience. Later this goes to the LISTING BROKER's own email. Flagged loudly on
 * purpose. Going live is now a settings edit, not a code change.
 *
 * MAIL: this file NEVER edits the mail layer. It requires mailer.php (which pulls
 * in mail-smtp.php) purely to REUSE its formatters (p_format_price,
 * p_format_phone, p_mail_header_safe, p_h) and to CALL mail_smtp_send. The
 * environment values (site_base_url, pocket_notify_to, mail_from_address) now
 * come from the DB-backed suite_settings table via suite_setting() - read here
 * with the SAME hardcoded literals as fallbacks, so output is identical to
 * before. The reminder email is built by a small helper defined HERE, not in
 * mailer.php.
 *
 * TIME: everything is UTC. expires_at is stored UTC (datetime('now')); we compare
 * against gmdate('Y-m-d H:i:s').
 */

// ---------------------------------------------------------------------------
// CLI-ONLY GUARD (first thing that runs). A web request to this URL can never
// execute the lifecycle - it is a scheduled shell job only.
// ---------------------------------------------------------------------------
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

// The DB layer keys its paths off DOCUMENT_ROOT, which the CLI does not set.
// Point it at the suite root (one level up from pocket/) so vdb_connect
// opens the same vendors.sqlite the web app uses.
if (empty($_SERVER['DOCUMENT_ROOT'])) {
    $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 1);
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/api/db.php';
// Requiring the mailer also pulls in mail-smtp.php (the shared sender). We reuse
// its constants + formatters and call mail_smtp_send; we never redefine anything.
require_once __DIR__ . '/mailer.php';

/**
 * Build + send one expiry-reminder email for a listing row. Returns the boolean
 * mail_smtp_send result (never throws). To: the pocket_notify_to setting (test
 * inbox by default). From: the mail_from_address setting (the authenticated
 * mailbox). The absolute base for links/images is the site_base_url setting.
 * Each setting falls back to its ORIGINAL hardcoded literal, so output is
 * identical to before. Every listing value is HTML-escaped in the HTML body;
 * header values are CR/LF-stripped by the mailer's p_mail_header_safe before
 * they touch a header.
 *
 * @param PDO    $pdo         shared vendor DB (settings + broker-name lookup)
 * @param array  $row         the raw pocket_listings row
 * @param int    $daysLeft    whole days until expiry (>= 0)
 * @param string $expiresAt   the stored UTC expires_at string
 * @return bool
 */
function pocket_cron_send_reminder(PDO $pdo, array $row, $daysLeft, $expiresAt)
{
    // --- environment values from settings (Phase 1), each with the original
    // hardcoded literal as its fallback so a missing/blank setting sends exactly
    // as before. A $pdo is in scope, so read them here and thread through. ---
    $siteBase = suite_setting($pdo, 'site_base_url', 'https://haleyyachts.com');
    $notifyTo = suite_setting($pdo, 'pocket_notify_to', 'clark@mvroam.com');
    $mailFrom = suite_setting($pdo, 'mail_from_address', 'no-reply@haleyyachts.com');
    // Product-first branding for the footer (config-driven).
    $brandName  = suite_setting($pdo, 'brand_name', 'Yacht Broker Support');
    $tenantName = suite_setting($pdo, 'tenant_name', 'One Water Yacht Group');

    // --- title: "Year Make Model", omitting any missing part ---
    $year  = (isset($row['year'])  && $row['year']  !== null && $row['year']  !== '') ? (string) $row['year']  : '';
    $make  = isset($row['make'])  ? (string) $row['make']  : '';
    $model = isset($row['model']) ? (string) $row['model'] : '';
    $titleParts = array();
    if ($year  !== '') { $titleParts[] = $year; }
    if ($make  !== '') { $titleParts[] = $make; }
    if ($model !== '') { $titleParts[] = $model; }
    $title = trim(implode(' ', $titleParts));
    if ($title === '') { $title = 'Pocket Listing'; }

    // --- broker name for the "Listed by" line ---
    $brokerName = '';
    $brokerId = isset($row['broker_id']) ? (int) $row['broker_id'] : 0;
    if ($brokerId > 0) {
        $bStmt = $pdo->prepare('SELECT name, account_id FROM users WHERE id = ?');
        $bStmt->execute(array($brokerId));
        $b = $bStmt->fetch();
        if ($b) {
            $brokerName = (isset($b['name']) && trim((string) $b['name']) !== '')
                ? (string) $b['name']
                : (isset($b['account_id']) ? (string) $b['account_id'] : '');
        }
    }

    // --- price line (reuse the mailer's formatter) ---
    $priceType = (isset($row['price_type']) && $row['price_type'] === 'net') ? 'net' : 'list';
    $price = (isset($row['price']) && $row['price'] !== null && $row['price'] !== '')
        ? (int) $row['price'] : null;
    $priceDisplay = p_format_price($price, $priceType);

    // --- "N day(s)" wording + a readable expiry date ---
    $daysLeft = max(0, (int) $daysLeft);
    $dayWord  = ($daysLeft === 1) ? 'day' : 'days';
    $nWord    = $daysLeft . ' ' . $dayWord;
    $expiryDate = pocket_cron_format_date($expiresAt);

    $suiteUrl = $siteBase . '/pocket/';

    // --- subject (header-safe: strip any CR/LF a make/model could carry) ---
    $subject = p_mail_header_safe('Pocket Listing expiring in ' . $nWord . ': ' . $title);

    // --- plain-text body (literal text; nothing lands in a header) ---
    $text = array();
    $text[] = 'POCKET LISTING EXPIRING';
    $text[] = '';
    $text[] = $title;
    $text[] = 'Expires on ' . $expiryDate . ' (' . $nWord . ' left)';
    if ($priceDisplay !== '') { $text[] = 'Price: ' . $priceDisplay; }
    if ($brokerName !== '')   { $text[] = 'Listed by: ' . $brokerName; }
    $text[] = '';
    $text[] = 'Review or renew this listing in the Broker Suite:';
    $text[] = $suiteUrl;
    $text[] = '';
    $text[] = '(Reminder sent to the test inbox during rollout.)';
    $textBody = implode("\r\n", $text);

    // --- HTML body (co-branded, short; every value escaped with p_h) ---
    // $siteBase is threaded in for the masthead/footer banner image URLs.
    $htmlBody = pocket_cron_html_body(
        p_h($title), p_h($expiryDate), p_h($nWord),
        p_h($priceDisplay), p_h($brokerName), p_h($suiteUrl), p_h($siteBase),
        p_h($brandName), p_h($tenantName)
    );

    $listingId = isset($row['id']) ? (int) $row['id'] : 0;
    $to = p_mail_header_safe($notifyTo);

    // From defaults to the authenticated mailbox (mail_from_address). No Reply-To.
    return mail_smtp_send(
        $to,
        $subject,
        $textBody,
        $htmlBody,
        'pocket-expiry:listing-' . $listingId,
        '',                 // Reply-To: none
        $mailFrom           // From: no-reply@haleyyachts.com
    );
}

/** Render a stored UTC "YYYY-MM-DD HH:MM:SS" as "Mon D, YYYY" (mirrors the JS). */
function pocket_cron_format_date($raw)
{
    $s = trim((string) $raw);
    if ($s === '') { return ''; }
    $datePart = explode(' ', $s);
    $p = explode('-', $datePart[0]);
    if (count($p) !== 3) { return $datePart[0]; }
    $months = array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                    'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');
    $mi = (int) $p[1] - 1;
    if ($mi < 0 || $mi > 11) { return $datePart[0]; }
    return $months[$mi] . ' ' . (int) $p[2] . ', ' . $p[0];
}

/**
 * Short co-branded HTML body for the reminder. Values arrive ALREADY escaped
 * (p_h) from the caller. Kept in the same navy-masthead / cyan-keyline tone as
 * mailer.php's notification, but trimmed to a single "expiring" card.
 */
function pocket_cron_html_body($eTitle, $eExpiryDate, $eNWord, $ePrice, $eBrokerName, $eSuite, $eSiteBase,
    $eBrand = 'Yacht Broker Support', $eTenant = 'One Water Yacht Group')
{
    $priceRow = ($ePrice !== '')
        ? '<p style="font-family:\'Open Sans\', Arial, Helvetica, sans-serif; font-size:18px; line-height:24px; color:#0a1628; font-weight:700; margin:0 0 14px 0;">' . $ePrice . '</p>'
        : '';
    $brokerRow = ($eBrokerName !== '')
        ? '<p style="font-family:\'Open Sans\', Arial, Helvetica, sans-serif; font-size:13px; line-height:20px; color:#5b7a96; margin:0 0 4px 0;">Listed by ' . $eBrokerName . '</p>'
        : '';

    return '<!DOCTYPE html>' .
'<html lang="en"><head><meta charset="UTF-8" />' .
'<meta name="viewport" content="width=device-width, initial-scale=1" />' .
'<title>Pocket Listing Expiring</title></head>' .
'<body style="margin:0; padding:0; width:100%; background-color:#f4f6f8;" bgcolor="#f4f6f8">' .

'<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" bgcolor="#f4f6f8" style="background-color:#f4f6f8;">' .
'<tr><td align="center" style="padding:24px 12px;">' .

'<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="600" style="width:600px; max-width:600px; background-color:#ffffff;" bgcolor="#ffffff">' .

// Masthead
'<tr><td align="center" valign="middle" bgcolor="#0d2847" ' .
'style="background-color:#0d2847; background-image: linear-gradient(135deg, #0a1628 0%, #0d2847 50%, #134a6e 100%); padding:30px 32px;">' .
'<p style="font-family:\'Open Sans\', Arial, Helvetica, sans-serif; font-size:13px; line-height:18px; color:#e8eef5; font-weight:600; letter-spacing:2px; text-transform:uppercase; margin:0 0 16px 0; text-align:center;">' .
'Pocket Listing Expiring</p>' .
'<img src="' . $eSiteBase . '/images/email/owyg-banner-reverse.png" width="200" height="52" alt="One Water Yacht Group" ' .
'style="display:block; width:200px; max-width:200px; height:auto; border:0; outline:none; margin:0 auto;" />' .
'<p style="font-family:\'Open Sans\', Arial, Helvetica, sans-serif; font-size:11px; line-height:16px; color:#9fb8cf; font-weight:600; letter-spacing:2px; text-transform:uppercase; margin:14px 0 0 0; text-align:center;">Off-Market &middot; OWYG Broker Network</p>' .
'</td></tr>' .

// Cyan keyline
'<tr><td bgcolor="#21cbea" height="3" style="background-color:#21cbea; height:3px; line-height:3px; font-size:0; padding:0;">&nbsp;</td></tr>' .

// Body: title, expiry line, price, broker
'<tr><td bgcolor="#ffffff" style="background-color:#ffffff; padding:28px 40px 8px 40px;">' .
'<h1 style="font-family:\'Open Sans\', Arial, Helvetica, sans-serif; font-size:22px; line-height:28px; color:#0a1628; font-weight:700; margin:0 0 10px 0;">' . $eTitle . '</h1>' .
'<p style="font-family:\'Open Sans\', Arial, Helvetica, sans-serif; font-size:15px; line-height:22px; color:#c0392b; font-weight:600; margin:0 0 12px 0;">Expires on ' . $eExpiryDate . ' (' . $eNWord . ' left)</p>' .
$priceRow .
$brokerRow .
'</td></tr>' .

// CTA button
'<tr><td align="center" bgcolor="#ffffff" style="background-color:#ffffff; padding:18px 40px 32px 40px;">' .
'<p style="font-family:\'Open Sans\', Arial, Helvetica, sans-serif; font-size:14px; line-height:20px; color:#5b7a96; margin:0 0 14px 0; text-align:center;">Review or renew this listing before it archives</p>' .
'<a href="' . $eSuite . '" target="_blank" rel="noopener" ' .
'style="display:inline-block; background-color:#21cbea; color:#0a1628; font-family:\'Open Sans\', Arial, Helvetica, sans-serif; font-size:15px; font-weight:700; text-decoration:none; padding:13px 28px; border-radius:8px;">' .
'Open Pocket Listings</a></td></tr>' .

// Footer
'<tr><td bgcolor="#070e1a" style="background-color:#070e1a; padding:30px 32px 26px 32px;" align="center">' .
'<img src="' . $eSiteBase . '/images/email/owyg-banner-reverse.png" width="200" height="52" alt="One Water Yacht Group" ' .
'style="display:block; width:200px; max-width:200px; height:52px; border:0; outline:none; margin:0 auto 16px auto;" />' .
'<p style="font-family:\'Open Sans\', Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:rgba(255,255,255,0.55); margin:0 0 8px 0;">' .
'&copy; 2026 ' . $eBrand . ' &nbsp;&middot;&nbsp; ' . $eTenant . '</p>' .
'<p style="font-family:\'Open Sans\', Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:rgba(255,255,255,0.7); margin:0;">' .
'Internal broker-network reminder. Pocket listings are private and off-market - do not forward outside the network.</p>' .
'</td></tr>' .

'</table>' .
'</td></tr></table>' .
'</body></html>';
}

// ---------------------------------------------------------------------------
// MAIN
// ---------------------------------------------------------------------------

$sent     = 0;
$archived = 0;
$errors   = 0;

try {
    $pdo = vdb_connect();
} catch (Throwable $e) {
    error_log('pocket-cron: could not open the database: ' . $e->getMessage());
    fwrite(STDERR, 'pocket-cron: DB open failed: ' . $e->getMessage() . "\n");
    exit(1);
}

$now    = gmdate('Y-m-d H:i:s');
$nowTs  = strtotime($now . ' UTC');
if ($nowTs === false) { $nowTs = time(); }

try {
    $rows = $pdo
        ->query("SELECT * FROM pocket_listings WHERE status = 'active' AND expires_at IS NOT NULL")
        ->fetchAll();
} catch (Throwable $e) {
    error_log('pocket-cron: query failed: ' . $e->getMessage());
    fwrite(STDERR, 'pocket-cron: query failed: ' . $e->getMessage() . "\n");
    exit(1);
}

foreach ($rows as $row) {
    // Each listing is isolated: one bad row can never abort the whole run.
    try {
        $id        = (int) $row['id'];
        $expiresAt = (string) $row['expires_at'];
        $expTs     = strtotime($expiresAt . ' UTC');
        if ($expTs === false) {
            // Unparseable expires_at: skip it (don't archive on bad data).
            continue;
        }

        // --- expired? archive it (keep record + images, no email) ---
        if ($expTs <= $nowTs) {
            $upd = $pdo->prepare("
                UPDATE pocket_listings
                SET status = 'archived', archived_at = datetime('now')
                WHERE id = ? AND status = 'active'
            ");
            $upd->execute(array($id));
            if ($upd->rowCount() > 0) {
                $archived++;
                error_log('pocket-cron: archived listing ' . $id . ' (expired ' . $expiresAt . ')');
            }
            continue;
        }

        // --- not expired: how many whole days are left? ---
        $daysUntil = (int) ceil(($expTs - $nowTs) / 86400);

        // Only ONE reminder per listing per run; 1-day takes priority. The
        // reminded flag is set ONLY on a successful send, so a transient mail
        // failure just retries on the next daily run.
        if ($daysUntil <= 1 && (int) $row['reminded_1d'] === 0) {
            $ok = pocket_cron_send_reminder($pdo, $row, $daysUntil, $expiresAt);
            if ($ok) {
                $pdo->prepare('UPDATE pocket_listings SET reminded_1d = 1 WHERE id = ?')
                    ->execute(array($id));
                $sent++;
            } else {
                $errors++;
                error_log('pocket-cron: 1-day reminder NOT sent for listing ' . $id);
            }
        } elseif ($daysUntil <= 7 && $daysUntil > 1 && (int) $row['reminded_7d'] === 0) {
            $ok = pocket_cron_send_reminder($pdo, $row, $daysUntil, $expiresAt);
            if ($ok) {
                $pdo->prepare('UPDATE pocket_listings SET reminded_7d = 1 WHERE id = ?')
                    ->execute(array($id));
                $sent++;
            } else {
                $errors++;
                error_log('pocket-cron: 7-day reminder NOT sent for listing ' . $id);
            }
        }
    } catch (Throwable $e) {
        $errors++;
        error_log('pocket-cron: error on listing '
            . (isset($row['id']) ? (int) $row['id'] : '?') . ': ' . $e->getMessage());
    }
}

// One-line summary for the cron log.
echo 'pocket-cron: ' . $sent . ' reminder' . ($sent === 1 ? '' : 's') . ' sent, '
    . $archived . ' archived, ' . $errors . ' error' . ($errors === 1 ? '' : 's') . "\n";
exit(0);
