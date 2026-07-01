<?php
/**
 * pocket/mailer.php - network-notification email for Pocket Listings (Broker
 * Suite app #2), Phase 2.
 *
 * Exposes one function: pocket_notify_new_listing(PDO, array $listing, array
 * $authUser). It is called by api.php's save handler AFTER the transaction
 * commits, and ONLY when a listing was just CREATED (never on edit). It sends a
 * co-branded HTML + plain-text email announcing the new listing to the network.
 *
 * The email is sent over authenticated SMTP through the shared sender in
 * ../mail-smtp.php (the real no-reply@haleyyachts.com mailbox), NOT bare mail().
 * A failure here NEVER breaks the API response: the send is best-effort,
 * error_log()s on failure, and returns a boolean the caller can surface as an
 * informational field. A missing secrets file degrades gracefully (logged,
 * returns false).
 *
 * SECURITY: every value that lands in a mail HEADER (To, From, Reply-To,
 * Subject) is stripped of CR/LF first (p_mail_header_safe) so a make, model, or
 * broker email cannot inject extra headers. Every user-supplied value in the
 * HTML body is escaped with htmlspecialchars.
 */

// Shared authenticated-SMTP sender + config loader. Both Broker Suite mailers
// route through this so there is one transport and one secrets file.
require_once __DIR__ . '/../mail-smtp.php';

if (!function_exists('pocket_notify_new_listing')) {

    // =======================================================================
    // CONFIG
    // =======================================================================

    // ***** TEMPORARY - TESTING ONLY *****
    // While the network email is under test, ALL notifications go to this ONE
    // inbox. This is NOT the real audience. When the real recipient list is
    // decided, replace this single address with a list (or a users-table query,
    // e.g. every active broker's email) and loop the send. Flagged loudly on
    // purpose - do not ship a real rollout with this hard-coded single To.
    define('POCKET_NOTIFY_TO', 'clark@mvroam.com');

    // The From address, sent over authenticated SMTP so SPF/DKIM authenticate
    // it. The From DISPLAY NAME ("OneWater") comes from the shared secrets file
    // (from_name), not from here, so both Broker Suite mailers stay consistent.
    define('POCKET_MAIL_FROM', 'no-reply@haleyyachts.com');

    // Absolute base for image URLs and the "view in Broker Suite" link. Email
    // clients cannot resolve relative paths, so everything the email references
    // must be absolute off this.
    define('POCKET_SITE_BASE', 'https://haleyyachts.com');

    // =======================================================================
    // PUBLIC ENTRY
    // =======================================================================

    /**
     * Send the new-listing notification email. Returns true if mail() accepted
     * the message for delivery, false otherwise. Never throws to the caller.
     *
     * @param PDO   $pdo      shared vendor DB (to look up the listing broker's contact)
     * @param array $listing  the shaped listing (pocket_shape output)
     * @param array $authUser the session user who created it (unused for content
     *                        here - the LISTING broker is the contact - but kept
     *                        in the signature for symmetry and future use)
     * @return bool
     */
    function pocket_notify_new_listing(PDO $pdo, array $listing, array $authUser)
    {
        try {
            // --- resolve the LISTING broker's contact (name, email, cell) ---
            // pocket_shape does NOT carry the broker email, so query users
            // directly by the listing's broker_id.
            $brokerId = isset($listing['broker_id']) ? (int) $listing['broker_id'] : 0;
            $brokerName  = '';
            $brokerEmail = '';
            $brokerCell  = '';
            if ($brokerId > 0) {
                $bStmt = $pdo->prepare('SELECT name, account_id, email, cell FROM users WHERE id = ?');
                $bStmt->execute(array($brokerId));
                $b = $bStmt->fetch();
                if ($b) {
                    $brokerName = (isset($b['name']) && trim((string) $b['name']) !== '')
                        ? (string) $b['name']
                        : (isset($b['account_id']) ? (string) $b['account_id'] : '');
                    $brokerEmail = isset($b['email']) ? (string) $b['email'] : '';
                    $brokerCell  = isset($b['cell']) ? (string) $b['cell'] : '';
                }
            }
            // Fall back to the shaped values if the direct lookup came up short.
            if ($brokerName === '' && isset($listing['broker_name'])) {
                $brokerName = (string) $listing['broker_name'];
            }
            if ($brokerCell === '' && isset($listing['broker_phone'])) {
                $brokerCell = (string) $listing['broker_phone'];
            }

            // --- build the display pieces ---
            $make  = isset($listing['make'])  ? (string) $listing['make']  : '';
            $model = isset($listing['model']) ? (string) $listing['model'] : '';
            $year  = (isset($listing['year']) && $listing['year'] !== null && $listing['year'] !== '')
                ? (string) $listing['year'] : '';
            $length   = (isset($listing['length']) && $listing['length'] !== null && $listing['length'] !== '')
                ? (string) $listing['length'] : '';
            $location = isset($listing['location']) ? (string) $listing['location'] : '';
            $description = isset($listing['description']) ? (string) $listing['description'] : '';

            $priceType = (isset($listing['price_type']) && $listing['price_type'] === 'net') ? 'net' : 'list';
            $price = (isset($listing['price']) && $listing['price'] !== null && $listing['price'] !== '')
                ? (int) $listing['price'] : null;

            // Title: Year Make Model, omitting any missing part gracefully.
            $titleParts = array();
            if ($year  !== '') { $titleParts[] = $year; }
            if ($make  !== '') { $titleParts[] = $make; }
            if ($model !== '') { $titleParts[] = $model; }
            $title = trim(implode(' ', $titleParts));
            if ($title === '') { $title = 'New Pocket Listing'; }

            $priceDisplay = p_format_price($price, $priceType); // e.g. "$450,000 (Net)"

            // Hero image absolute URL, or '' if there is none. hero_url from the
            // shape is a relative "uploads/<encoded>" path.
            $heroAbs = '';
            if (isset($listing['hero_url']) && (string) $listing['hero_url'] !== '') {
                $heroAbs = POCKET_SITE_BASE . '/vendors/pocket/' . ltrim((string) $listing['hero_url'], '/');
            }

            // Additional images (up to 4), absolute URLs, for the strip under the
            // hero. Skip the hero itself (the shape lists it first / flags it).
            $heroRel = isset($listing['hero_url']) ? (string) $listing['hero_url'] : '';
            $moreAbs = array();
            if (!empty($listing['images']) && is_array($listing['images'])) {
                foreach ($listing['images'] as $im) {
                    $u = isset($im['url']) ? (string) $im['url'] : '';
                    if ($u === '') { continue; }
                    if (!empty($im['is_hero'])) { continue; }
                    if ($heroRel !== '' && $u === $heroRel) { continue; }
                    $moreAbs[] = POCKET_SITE_BASE . '/vendors/pocket/' . ltrim($u, '/');
                    if (count($moreAbs) >= 4) { break; }
                }
            }

            $suiteUrl = POCKET_SITE_BASE . '/vendors/pocket/';
            $brokerPhoneFmt = p_format_phone($brokerCell);

            // --- subject ---
            // "New Pocket Listing: {Year Make Model} - {price}"; when there is no
            // title info, omit the dash+title gracefully.
            $subjectCore = trim(implode(' ', $titleParts));
            $subject = 'New Pocket Listing';
            if ($subjectCore !== '') {
                $subject .= ': ' . $subjectCore;
                if ($price !== null) {
                    $subject .= ' - ' . $priceDisplay;
                }
            } elseif ($price !== null) {
                $subject .= ': ' . $priceDisplay;
            }
            // Header-safe: strip any CR/LF a make/model could carry.
            $subject = p_mail_header_safe($subject);

            // --- recipients / header addresses (all header-sanitized) ---
            // From address + display name are handled by the shared sender
            // (From: no-reply@haleyyachts.com, display "OneWater" from the
            // secrets file). Here we only sanitize the To and the Reply-To.
            $to      = p_mail_header_safe(POCKET_NOTIFY_TO);
            $replyTo = p_mail_header_safe($brokerEmail); // may be '' -> omitted below

            // --- build both MIME parts ---
            $textBody = p_build_text_body(
                $title, $length, $location, $year, $priceDisplay,
                $description, $brokerName, $brokerPhoneFmt, $brokerEmail, $suiteUrl
            );
            $htmlBody = p_build_html_body(
                $title, $length, $location, $year, $priceDisplay,
                $description, $brokerName, $brokerPhoneFmt, $brokerEmail,
                $heroAbs, $moreAbs, $suiteUrl
            );

            // --- send via the shared authenticated-SMTP sender ---
            // Bare mail() does not deliver on this GoDaddy host, so the
            // notification goes out through the real no-reply@haleyyachts.com
            // mailbox over authenticated SMTP. Credentials live in the UNTRACKED,
            // gitignored vendors/mail-secrets.php (shared with the Vendor app
            // mailer), never in the repo. A missing secrets file degrades
            // gracefully (logged, returns false) and never breaks the API flow.
            $listingId = isset($listing['id']) ? (int) $listing['id'] : 0;

            $ok = mail_smtp_send(
                $to,
                $subject,
                $textBody,
                $htmlBody,
                'pocket-mailer:listing-' . $listingId,
                $replyTo,            // Reply-To: the listing broker (may be '')
                POCKET_MAIL_FROM     // From: no-reply@haleyyachts.com
            );
            if (!$ok) {
                error_log('pocket-mailer: notification not sent for listing ' . $listingId);
            }
            return $ok;
        } catch (Throwable $e) {
            error_log('pocket-mailer error: ' . $e->getMessage());
            return false;
        }
    }

    // =======================================================================
    // HELPERS
    // =======================================================================

    /**
     * Strip CR and LF from a value bound for a mail header, defeating header
     * injection (a make/model/email cannot smuggle a second header). Also trims
     * surrounding whitespace. Returns a single clean line.
     */
    function p_mail_header_safe($value)
    {
        $value = (string) $value;
        // Remove CR, LF, and NUL outright rather than just trimming the ends.
        $value = str_replace(array("\r", "\n", "\0"), '', $value);
        return trim($value);
    }

    /**
     * Format a whole-dollar price as "$450,000", appending " (Net)" or " (List)"
     * per the price type. Null price -> "Price on request" (no type suffix).
     */
    function p_format_price($price, $priceType)
    {
        if ($price === null) {
            return 'Price on request';
        }
        $out = '$' . number_format((int) $price);
        $out .= ($priceType === 'net') ? ' (Net)' : ' (List)';
        return $out;
    }

    /**
     * Format a US phone: 10 digits -> (305) 555-1212, 11 with a leading 1 ->
     * 1 (305) 555-1212, anything else returned trimmed as entered. Mirrors the
     * front-end formatPhone().
     */
    function p_format_phone($raw)
    {
        $s = trim((string) $raw);
        if ($s === '') {
            return '';
        }
        $d = preg_replace('/\D/', '', $s);
        if (strlen($d) === 10) {
            return '(' . substr($d, 0, 3) . ') ' . substr($d, 3, 3) . '-' . substr($d, 6);
        }
        if (strlen($d) === 11 && $d[0] === '1') {
            return '1 (' . substr($d, 1, 3) . ') ' . substr($d, 4, 3) . '-' . substr($d, 7);
        }
        return $s;
    }

    /** Shorthand htmlspecialchars for the HTML body (quotes escaped, UTF-8). */
    function p_h($s)
    {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Build the plain-text alternative. Same information as the HTML part.
     * Nothing here lands in a header, so no HTML escaping is applied - it is
     * literal text.
     */
    function p_build_text_body($title, $length, $location, $year, $priceDisplay,
        $description, $brokerName, $brokerPhoneFmt, $brokerEmail, $suiteUrl)
    {
        $lines = array();
        $lines[] = 'NEW POCKET LISTING';
        $lines[] = 'Private, off-market listing for the OWYG broker network';
        $lines[] = '';
        $lines[] = $title;

        $specs = array();
        if ($length !== '')   { $specs[] = 'Length: ' . $length . ' ft'; }
        if ($location !== '') { $specs[] = 'Location: ' . $location; }
        if ($year !== '')     { $specs[] = 'Year: ' . $year; }
        if (!empty($specs)) {
            $lines[] = implode('  |  ', $specs);
        }
        $lines[] = 'Price: ' . $priceDisplay;

        if (trim($description) !== '') {
            $lines[] = '';
            $lines[] = $description;
        }

        $lines[] = '';
        $lines[] = '-- Listed by --';
        if ($brokerName !== '')     { $lines[] = $brokerName; }
        if ($brokerPhoneFmt !== '') { $lines[] = $brokerPhoneFmt; }
        if ($brokerEmail !== '')    { $lines[] = $brokerEmail; }

        $lines[] = '';
        $lines[] = 'View in the Broker Suite: ' . $suiteUrl;

        return implode("\r\n", $lines);
    }

    /**
     * Build the HTML alternative: table-based, inline CSS, co-branded Haley
     * Yachts + One Water, matching the tone of email-templates/general.html
     * (navy masthead, cyan keyline, dark footer with both marks). Every
     * user-supplied value is escaped with p_h().
     */
    function p_build_html_body($title, $length, $location, $year, $priceDisplay,
        $description, $brokerName, $brokerPhoneFmt, $brokerEmail, $heroAbs, $moreAbs, $suiteUrl)
    {
        $eTitle = p_h($title);
        $ePrice = p_h($priceDisplay);
        $eBrokerName = p_h($brokerName);

        // Specs line (escaped, joined with a subtle separator).
        $specParts = array();
        if ($length !== '')   { $specParts[] = p_h($length) . ' ft'; }
        if ($location !== '') { $specParts[] = p_h($location); }
        if ($year !== '')     { $specParts[] = p_h($year); }
        $specsHtml = implode(' &middot; ', $specParts);

        // Description: escape, then turn newlines into <br> for readability.
        $descHtml = '';
        if (trim($description) !== '') {
            $descHtml = nl2br(p_h($description));
        }

        // Hero image block (skipped cleanly when there is none).
        $heroBlock = '';
        if ($heroAbs !== '') {
            $heroBlock =
                '<tr>' .
                  '<td align="center" style="padding:0; font-size:0; line-height:0; background-color:#ffffff;">' .
                    '<img src="' . p_h($heroAbs) . '" width="600" alt="' . $eTitle . '" ' .
                    'style="display:block; width:100%; max-width:600px; height:auto; border:0; outline:none;" />' .
                  '</td>' .
                '</tr>';
        }

        // Additional images (up to 4) as an even row under the hero. Table-based
        // for email-client width control; equal-width cells.
        $galleryBlock = '';
        if (!empty($moreAbs) && is_array($moreAbs)) {
            $n = count($moreAbs);
            $pct = (int) floor(100 / $n);
            $cells = '';
            foreach ($moreAbs as $mu) {
                $cells .=
                    '<td width="' . $pct . '%" valign="top" style="padding:0 3px; font-size:0; line-height:0;">' .
                    '<img src="' . p_h($mu) . '" alt="" ' .
                    'style="display:block; width:100%; height:auto; border:0; outline:none; border-radius:4px;" />' .
                    '</td>';
            }
            $galleryBlock =
                '<tr><td style="padding:6px 6px 0 6px; background-color:#ffffff;" bgcolor="#ffffff">' .
                '<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="width:100%;"><tr>' .
                $cells .
                '</tr></table></td></tr>';
        }

        // Contact rows (each rendered only when present).
        $contactRows = '';
        if ($brokerPhoneFmt !== '') {
            $contactRows .=
                '<p style="font-family:\'Open Sans\', Arial, Helvetica, sans-serif; font-size:14px; line-height:22px; color:#333333; margin:0 0 2px 0;">' .
                p_h($brokerPhoneFmt) .
                '</p>';
        }
        if ($brokerEmail !== '') {
            // Escape for BOTH the visible text and the mailto href. The scheme is
            // a fixed literal; only the address (escaped) is interpolated.
            $eEmail = p_h($brokerEmail);
            $contactRows .=
                '<p style="font-family:\'Open Sans\', Arial, Helvetica, sans-serif; font-size:14px; line-height:22px; margin:0;">' .
                '<a href="mailto:' . rawurlencode($brokerEmail) . '" style="color:#21cbea; text-decoration:none; font-weight:600;">' .
                $eEmail . '</a>' .
                '</p>';
        }

        $specsRow = ($specsHtml !== '')
            ? '<p style="font-family:\'Open Sans\', Arial, Helvetica, sans-serif; font-size:14px; line-height:22px; color:#5b7a96; margin:0 0 10px 0;">' . $specsHtml . '</p>'
            : '';

        $descRow = ($descHtml !== '')
            ? '<p style="font-family:\'Open Sans\', Arial, Helvetica, sans-serif; font-size:16px; line-height:26px; color:#333333; margin:0 0 8px 0;">' . $descHtml . '</p>'
            : '';

        $eSuite = p_h($suiteUrl);

        // Assemble. Kept close to general.html's masthead/keyline/footer tone.
        $html = '<!DOCTYPE html>' .
'<html lang="en"><head><meta charset="UTF-8" />' .
'<meta name="viewport" content="width=device-width, initial-scale=1" />' .
'<title>New Pocket Listing</title></head>' .
'<body style="margin:0; padding:0; width:100%; background-color:#f4f6f8;" bgcolor="#f4f6f8">' .

'<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" bgcolor="#f4f6f8" style="background-color:#f4f6f8;">' .
'<tr><td align="center" style="padding:24px 12px;">' .

'<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="600" style="width:600px; max-width:600px; background-color:#ffffff;" bgcolor="#ffffff">' .

// Masthead
'<tr><td align="center" valign="middle" bgcolor="#0d2847" ' .
'style="background-color:#0d2847; background-image: linear-gradient(135deg, #0a1628 0%, #0d2847 50%, #134a6e 100%); padding:30px 32px;">' .
'<p style="font-family:\'Open Sans\', Arial, Helvetica, sans-serif; font-size:13px; line-height:18px; color:#e8eef5; font-weight:600; letter-spacing:2px; text-transform:uppercase; margin:0 0 16px 0; text-align:center;">' .
'New Pocket Listing</p>' .
'<img src="' . POCKET_SITE_BASE . '/images/email/owyg-banner-reverse.png" width="200" height="52" alt="One Water Yacht Group" ' .
'style="display:block; width:200px; max-width:200px; height:auto; border:0; outline:none; margin:0 auto;" />' .
'</td></tr>' .

// Cyan keyline
'<tr><td bgcolor="#21cbea" height="3" style="background-color:#21cbea; height:3px; line-height:3px; font-size:0; padding:0;">&nbsp;</td></tr>' .

// Hero (optional)
$heroBlock .

// Additional images strip (optional)
$galleryBlock .

// Body: title, specs, price, description
'<tr><td bgcolor="#ffffff" style="background-color:#ffffff; padding:28px 40px 8px 40px;">' .
'<p style="font-family:\'Open Sans\', Arial, Helvetica, sans-serif; font-size:11px; line-height:16px; color:#5b7a96; font-weight:600; letter-spacing:2px; text-transform:uppercase; margin:0 0 8px 0;">Off-Market &middot; OWYG Broker Network</p>' .
'<h1 style="font-family:\'Open Sans\', Arial, Helvetica, sans-serif; font-size:24px; line-height:30px; color:#0a1628; font-weight:700; margin:0 0 8px 0;">' . $eTitle . '</h1>' .
$specsRow .
'<p style="font-family:\'Open Sans\', Arial, Helvetica, sans-serif; font-size:20px; line-height:26px; color:#0a1628; font-weight:700; margin:0 0 16px 0;">' . $ePrice . '</p>' .
$descRow .
'</td></tr>' .

// Divider
'<tr><td style="padding:8px 40px 0 40px;"><table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">' .
'<tr><td style="border-top:1px solid #e2e2e2; font-size:0; line-height:0;">&nbsp;</td></tr></table></td></tr>' .

// Listed by (the LISTING broker)
'<tr><td bgcolor="#ffffff" style="background-color:#ffffff; padding:20px 40px 8px 40px;">' .
'<p style="font-family:\'Open Sans\', Arial, Helvetica, sans-serif; font-size:11px; line-height:16px; color:#5b7a96; font-weight:600; letter-spacing:2px; text-transform:uppercase; margin:0 0 6px 0;">Listed by</p>' .
'<p style="font-family:\'Open Sans\', Arial, Helvetica, sans-serif; font-size:18px; line-height:24px; color:#0a1628; font-weight:600; margin:0 0 8px 0;">' . ($eBrokerName !== '' ? $eBrokerName : 'A Haley Yachts broker') . '</p>' .
$contactRows .
'</td></tr>' .

// CTA button
'<tr><td align="center" bgcolor="#ffffff" style="background-color:#ffffff; padding:24px 40px 32px 40px;">' .
'<a href="' . $eSuite . '" target="_blank" rel="noopener" ' .
'style="display:inline-block; background-color:#21cbea; color:#0a1628; font-family:\'Open Sans\', Arial, Helvetica, sans-serif; font-size:15px; font-weight:700; text-decoration:none; padding:13px 28px; border-radius:8px;">' .
'View in the Broker Suite</a></td></tr>' .

// Footer
'<tr><td bgcolor="#070e1a" style="background-color:#070e1a; padding:30px 32px 26px 32px;" align="center">' .
'<img src="' . POCKET_SITE_BASE . '/images/email/owyg-banner-reverse.png" width="200" height="52" alt="One Water Yacht Group" ' .
'style="display:block; width:200px; max-width:200px; height:52px; border:0; outline:none; margin:0 auto 16px auto;" />' .
'<img src="' . POCKET_SITE_BASE . '/images/brand/haleyyachtslogo-footer.png" width="160" height="28" alt="Haley Yachts" ' .
'style="display:block; width:160px; max-width:160px; height:28px; border:0; outline:none; opacity:0.85; margin:0 auto 14px auto;" />' .
'<p style="font-family:\'Open Sans\', Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:rgba(255,255,255,0.55); margin:0 0 8px 0;">' .
'&copy; 2026 Haley Yachts &nbsp;|&nbsp; One Water Yacht Group &nbsp;|&nbsp; Palm Beach Gardens, Florida</p>' .
'<p style="font-family:\'Open Sans\', Arial, Helvetica, sans-serif; font-size:12px; line-height:18px; color:rgba(255,255,255,0.7); margin:0;">' .
'Internal broker-network notification. Pocket listings are private and off-market - do not forward outside the network.</p>' .
'</td></tr>' .

'</table>' .
'</td></tr></table>' .
'</body></html>';

        return $html;
    }
}
