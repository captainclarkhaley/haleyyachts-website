<?php
/**
 * pocket/print.php - print-optimized ONE-PAGER for a single pocket listing
 * (Broker Suite app #2, Phase 2).
 *
 * Same server-side auth gate as index.php: an unauthenticated visitor is
 * redirected to the shared /vendors/ login BEFORE any markup is sent, and a user
 * who still owes a forced password change is bounced to change-password. The gate
 * cannot be bypassed by disabling JavaScript.
 *
 * The whole point of this page: the contact block shows the LOGGED-IN broker
 * (current_user), NOT the listing's owner - so any co-broker can print a listing
 * as their own to hand to a client. A caption states it is presented by that
 * broker.
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendors/api/auth-lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendors/api/db.php';

start_secure_session();
$pdo = vdb_connect();
$gateUser = current_user($pdo);
if ($gateUser === null) {
    // login.html lives one level up in /vendors/.
    header('Location: ../login.html');
    exit;
}
if ((int) $gateUser['must_change_password'] === 1) {
    header('Location: ../change-password.html');
    exit;
}

// NOTE: we do NOT include api.php - its top-level code runs require_auth + an
// action switch + p_respond()/exit on include, which would hijack this page.
// Instead this file loads + shapes the listing itself with the SAME query shape
// api.php's pocket_load/pocket_shape use (row + broker + images), so the data is
// identical to what the app shows.

/**
 * Load one listing by id, shaped like pocket_shape (types coerced, hero image
 * resolved). Returns null when the id is missing or no row matches. This mirrors
 * api.php's pocket_load/pocket_shape - kept self-contained so print.php never
 * includes api.php's executable body.
 */
function pr_load_listing(PDO $pdo, $id)
{
    $id = (int) $id;
    if ($id <= 0) { return null; }

    $stmt = $pdo->prepare('SELECT * FROM pocket_listings WHERE id = ?');
    $stmt->execute(array($id));
    $row = $stmt->fetch();
    if (!$row) { return null; }

    // Hero image (hero first, else first image). Same "uploads/<encoded>" shape
    // the API returns, which resolves relative to this same-origin page.
    $iStmt = $pdo->prepare('
        SELECT filename, is_hero
        FROM pocket_listing_images
        WHERE listing_id = ?
        ORDER BY is_hero DESC, sort, id
    ');
    $iStmt->execute(array($id));
    // Collect every image in display order, then split out the hero. The rest
    // (up to 4) print as a horizontal strip under the hero.
    $all = array();
    $heroIdx = -1;
    foreach ($iStmt->fetchAll() as $img) {
        $isHero = ((int) $img['is_hero'] === 1);
        $all[] = 'uploads/' . rawurlencode((string) $img['filename']);
        if ($isHero && $heroIdx === -1) { $heroIdx = count($all) - 1; }
    }
    if ($heroIdx === -1 && !empty($all)) { $heroIdx = 0; }
    $hero = ($heroIdx >= 0) ? $all[$heroIdx] : '';
    $more = array();
    foreach ($all as $i => $u) {
        if ($i === $heroIdx) { continue; }
        $more[] = $u;
        if (count($more) >= 4) { break; }
    }

    return array(
        'id'          => (int) $row['id'],
        'broker_id'   => (int) $row['broker_id'],
        'make'        => $row['make'],
        'model'       => $row['model'],
        'year'        => $row['year'] === null ? null : (int) $row['year'],
        'length'      => $row['length'] === null ? null : (int) $row['length'],
        'location'    => $row['location'],
        'price'       => $row['price'] === null ? null : (int) $row['price'],
        'price_type'  => $row['price_type'],
        'description' => $row['description'],
        'hero_url'    => $hero,
        'more_urls'   => $more,
    );
}

$h = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };

// --- format helpers (mirror the front end + mailer) ---
$fmtPhone = function ($raw) {
    $s = trim((string) $raw);
    if ($s === '') { return ''; }
    $d = preg_replace('/\D/', '', $s);
    if (strlen($d) === 10) {
        return '(' . substr($d, 0, 3) . ') ' . substr($d, 3, 3) . '-' . substr($d, 6);
    }
    if (strlen($d) === 11 && $d[0] === '1') {
        return '1 (' . substr($d, 1, 3) . ') ' . substr($d, 4, 3) . '-' . substr($d, 7);
    }
    return $s;
};
$fmtPrice = function ($price, $priceType) {
    // Client-facing sheet: a NET listing never prints a number - the net price
    // is a broker-to-broker figure, not for the buyer. List price prints as is.
    if ($priceType === 'net') { return 'Call for Pricing'; }
    if ($price === null || $price === '') { return 'Price on request'; }
    return '$' . number_format((int) $price) . ' (List)';
};

// --- load the listing ---
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$listing = ($id > 0) ? pr_load_listing($pdo, $id) : null;

// A clean tab/title (Year Make Model), not "Print Listing - Haley Yachts".
$pageTitle = 'Listing';
if ($listing) {
    $tp = array();
    foreach (array('year', 'make', 'model') as $k) {
        if (!empty($listing[$k])) { $tp[] = (string) $listing[$k]; }
    }
    if (!empty($tp)) { $pageTitle = implode(' ', $tp); }
}

// Logged-in broker (the presenter) - server-side, never the listing owner.
$presenterName = trim((string) $gateUser['name']);
if ($presenterName === '') { $presenterName = (string) $gateUser['account_id']; }
$presenterPhone = $fmtPhone(isset($gateUser['cell']) ? $gateUser['cell'] : '');
$presenterEmail = isset($gateUser['email']) ? (string) $gateUser['email'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $h($pageTitle); ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="../../favicon.ico" sizes="any">
    <style>
        :root {
            --navy:   #0a1628;
            --cyan:   #21cbea;
            --cyan-d: #1aa8c4;
            --ink:    #333;
            --muted:  #666;
            --line:   #e5e5e5;
            --canvas: #f4f6f8;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; }
        body {
            font-family: 'Open Sans', Arial, sans-serif;
            color: var(--ink);
            background: var(--canvas);
            line-height: 1.5;
        }

        /* Screen-only toolbar (hidden in print). */
        .pr-toolbar {
            background: var(--navy);
            color: #fff;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
        }
        .pr-toolbar a {
            color: #cfe9f1; text-decoration: none;
            border: 1px solid rgba(255,255,255,0.22);
            padding: 6px 12px; border-radius: 999px; font-size: .82rem;
        }
        .pr-toolbar a:hover { border-color: var(--cyan); color: #fff; }
        .pr-print-btn {
            font-family: inherit; font-size: .85rem; font-weight: 600;
            cursor: pointer; border: 1px solid var(--cyan);
            background: var(--cyan); color: var(--navy);
            border-radius: 8px; padding: 8px 18px;
        }
        .pr-print-btn:hover { background: var(--cyan-d); border-color: var(--cyan-d); }

        /* The printable sheet. A flex column so the footer caption is pushed to
           the bottom of the sheet regardless of description length. min-height
           fills the viewport on screen; in print the sheet fills the page. */
        .pr-sheet {
            max-width: 780px;
            margin: 22px auto;
            min-height: calc(100vh - 44px);
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .pr-head {
            background: var(--navy);
            background-image: linear-gradient(135deg, #0a1628 0%, #0d2847 50%, #134a6e 100%);
            color: #fff;
            padding: 14px 30px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            text-align: center;
        }
        .pr-head img.pr-owyg { height: 44px; width: auto; display: block; }
        .pr-head .pr-tag {
            font-size: .68rem; letter-spacing: 2px; text-transform: uppercase;
            color: #cfe9f1; font-weight: 600;
        }
        .pr-keyline { height: 3px; background: var(--cyan); }

        .pr-body { padding: 22px 30px 26px; flex: 1 0 auto; }

        /* Full-width title block above the two columns. */
        .pr-titleblock { margin: 0 0 18px 0; }
        .pr-title {
            font-size: 1.7rem; font-weight: 700; color: var(--navy);
            margin: 0 0 6px 0; line-height: 1.2;
        }
        .pr-specs-line { font-size: .95rem; color: var(--muted); margin: 0; }

        /* Two-column main body (table for print reliability). */
        .pr-cols { width: 100%; border-collapse: collapse; }
        .pr-cols td { vertical-align: top; padding: 0; }
        .pr-col-left  { width: 58%; padding-right: 18px !important; }
        .pr-col-right { width: 42%; }

        .pr-hero {
            width: 100%;
            max-height: 280px;
            object-fit: cover;
            border-radius: 10px;
            background: #dde5eb;
            display: block;
            margin-bottom: 10px;
        }

        /* Up to 3 additional images, horizontal strip under the hero. */
        .pr-gallery {
            display: flex;
            gap: 8px;
            margin: 0;
        }
        .pr-gallery img {
            flex: 1 1 0;
            min-width: 0;
            height: 78px;
            object-fit: cover;
            border-radius: 8px;
            background: #dde5eb;
            display: block;
        }

        /* Price is the loudest non-image element. */
        .pr-price {
            font-size: 1.6rem; font-weight: 700; color: var(--navy);
            margin: 0 0 18px 0; line-height: 1.2;
        }
        .pr-price .pr-pt {
            font-size: .72rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: .08em; color: var(--cyan-d); margin-left: 6px;
            white-space: nowrap;
        }

        /* Compact vertical spec list in the right column. */
        .pr-specs-list { margin: 0 0 20px 0; }
        .pr-specs-list .pr-spec {
            padding: 8px 0;
            border-bottom: 1px solid var(--line);
        }
        .pr-specs-list .pr-spec:first-child { border-top: 1px solid var(--line); }
        .pr-spec .lbl {
            font-size: .62rem; text-transform: uppercase; letter-spacing: 1px;
            color: var(--cyan-d); font-weight: 700; display: block; margin-bottom: 2px;
        }
        .pr-spec .val { font-size: .98rem; color: var(--navy); font-weight: 600; }

        .pr-desc-h {
            font-size: .68rem; text-transform: uppercase; letter-spacing: 2px;
            color: var(--muted); font-weight: 700; margin: 22px 0 8px 0;
        }
        .pr-desc {
            font-size: .82rem; color: var(--ink); white-space: pre-wrap;
            line-height: 1.5;
            margin: 0 0 20px 0;
            orphans: 2; widows: 2;
            border-left: 3px solid var(--cyan);
            padding-left: 14px;
        }

        /* Contact block: the LOGGED-IN broker (presenter). Sits at the bottom of
           the right column. */
        .pr-contact {
            border-top: 2px solid var(--navy);
            padding-top: 14px;
        }
        .pr-contact .pr-presented {
            font-size: .68rem; text-transform: uppercase; letter-spacing: 2px;
            color: var(--cyan-d); font-weight: 700; margin: 0 0 6px 0;
        }
        .pr-contact .pr-name {
            font-size: 1.15rem; font-weight: 700; color: var(--navy); margin: 0 0 4px 0;
        }
        .pr-contact .pr-line { font-size: .92rem; color: var(--ink); margin: 0 0 2px 0; word-break: break-word; }
        .pr-contact a { color: var(--cyan-d); text-decoration: none; }

        /* Footer caption pinned to the bottom of the sheet. flex:0 0 auto keeps
           it its natural height while .pr-body takes the slack above it. */
        .pr-footer {
            flex: 0 0 auto;
            border-top: 1px solid var(--line);
            padding: 12px 30px 16px;
            margin: 0;
        }
        .pr-caption {
            font-size: .74rem; color: var(--muted); margin: 0; text-align: center;
        }

        /* Narrow screen: stack the two columns (print stays two-column). */
        @media screen and (max-width: 560px) {
            .pr-cols, .pr-cols tbody, .pr-cols tr, .pr-cols td { display: block; width: 100%; }
            .pr-col-left { padding-right: 0 !important; margin-bottom: 18px; }
        }

        /* Screen-only bottom print button (hidden in print). */
        .pr-actions-bottom { text-align: center; margin: 4px auto 44px; }

        .pr-notfound {
            max-width: 560px; margin: 60px auto; background: #fff;
            border: 1px dashed var(--line); border-radius: 12px;
            padding: 48px 24px; text-align: center; color: var(--muted);
        }

        /* ---- PRINT ---- */
        @media print {
            /* margin:0 on @page drops the browser's own header (date, document
               title) and footer (page URL); body padding restores safe margins. */
            body { background: #fff; padding: 12mm; }
            .pr-toolbar { display: none !important; }
            .pr-actions-bottom { display: none !important; }
            .pr-sheet {
                max-width: 100%; margin: 0; border: none; border-radius: 0;
                /* Push the footer toward the page bottom via the flex layout.
                   Use a FIXED height, NOT vh: in print, vh is unreliable and
                   often maps to the screen viewport, which overshot the page and
                   bounced the footer onto page 2. 245mm sits inside a US Letter
                   printable area (11in minus the 24mm body padding is ~255mm),
                   leaving safe margin so the footer never spills. */
                min-height: 245mm;
            }
            .pr-head {
                background: var(--navy) !important;
                -webkit-print-color-adjust: exact; print-color-adjust: exact;
            }
            .pr-keyline {
                background: var(--cyan) !important;
                -webkit-print-color-adjust: exact; print-color-adjust: exact;
            }
            .pr-title, .pr-price, .pr-spec .val, .pr-contact .pr-name { color: #000; }
            .pr-desc, .pr-line { color: #000; }
            img { max-width: 100%; }
            a { color: #000; text-decoration: none; }

            /* Nothing here may split across pages except the description. */
            .pr-head,
            .pr-titleblock,
            .pr-col-left,
            .pr-col-right,
            .pr-footer {
                break-inside: avoid;
                page-break-inside: avoid;
                -webkit-column-break-inside: avoid;
            }
            .pr-caption { color: #000; }
            /* The description is the ONLY block allowed to flow to page 2. */
            .pr-desc {
                break-inside: auto;
                page-break-inside: auto;
                orphans: 2; widows: 2;
                /* Keep the cyan left rule in color when printed. */
                -webkit-print-color-adjust: exact; print-color-adjust: exact;
            }
            @page { margin: 0; }
        }
    </style>
</head>
<body>

<div class="pr-toolbar">
    <a href="index.php">&larr; Back to Pocket Listings</a>
    <button type="button" class="pr-print-btn js-print">Print</button>
</div>

<?php if (!$listing): ?>
    <div class="pr-notfound">
        <h2 style="margin:0 0 8px 0; color:#0a1628;">Listing not found</h2>
        <p style="margin:0;">That listing does not exist or has been removed.</p>
    </div>
<?php else:
    $titleParts = array();
    foreach (array('year', 'make', 'model') as $k) {
        if (isset($listing[$k]) && $listing[$k] !== null && trim((string) $listing[$k]) !== '') {
            $titleParts[] = (string) $listing[$k];
        }
    }
    $title = trim(implode(' ', $titleParts));
    if ($title === '') { $title = 'Pocket Listing'; }

    $priceType = (isset($listing['price_type']) && $listing['price_type'] === 'net') ? 'net' : 'list';
    $priceStr = $fmtPrice(
        (isset($listing['price']) && $listing['price'] !== null && $listing['price'] !== '') ? (int) $listing['price'] : null,
        $priceType
    );

    $specParts = array();
    if (!empty($listing['length']))   { $specParts[] = $h($listing['length']) . ' ft'; }
    if (!empty($listing['location'])) { $specParts[] = $h($listing['location']); }
    if (!empty($listing['year']))     { $specParts[] = $h($listing['year']); }
    $specsLine = implode(' &middot; ', $specParts);

    // Absolute-safe hero (the app serves it relative; on this same-origin page a
    // relative uploads/ path resolves fine).
    $heroUrl = (isset($listing['hero_url']) && $listing['hero_url'] !== '') ? (string) $listing['hero_url'] : '';
    $moreUrls = (isset($listing['more_urls']) && is_array($listing['more_urls'])) ? $listing['more_urls'] : array();
?>
    <div class="pr-sheet">
        <div class="pr-head">
            <img class="pr-owyg" src="../../images/email/owyg-banner-reverse.png" alt="One Water Yacht Group">
            <div class="pr-tag">Private Listing &middot; Off-Market</div>
        </div>
        <div class="pr-keyline"></div>

        <div class="pr-body">
            <div class="pr-titleblock">
                <h1 class="pr-title"><?php echo $h($title); ?></h1>
                <?php if ($specsLine !== ''): ?>
                    <p class="pr-specs-line"><?php echo $specsLine; ?></p>
                <?php endif; ?>
            </div>

            <?php
                // Cap the additional-images strip at 3 on the printout (the listing
                // still stores up to 4; we simply do not render the 4th here).
                $stripUrls = array_slice($moreUrls, 0, 3);
            ?>
            <table class="pr-cols"><tbody><tr>
                <td class="pr-col-left">
                    <?php if ($heroUrl !== ''): ?>
                        <img class="pr-hero" src="<?php echo $h($heroUrl); ?>" alt="<?php echo $h($title); ?>">
                    <?php endif; ?>
                    <?php if (!empty($stripUrls)): ?>
                        <div class="pr-gallery">
                            <?php foreach ($stripUrls as $mu): ?>
                                <img src="<?php echo $h($mu); ?>" alt="">
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </td>
                <td class="pr-col-right">
                    <p class="pr-price"><?php echo $h($priceStr); ?></p>

                    <div class="pr-specs-list">
                        <?php if (!empty($listing['length'])): ?>
                            <div class="pr-spec"><span class="lbl">Length</span><span class="val"><?php echo $h($listing['length']); ?> ft</span></div>
                        <?php endif; ?>
                        <?php if (!empty($listing['location'])): ?>
                            <div class="pr-spec"><span class="lbl">Location</span><span class="val"><?php echo $h($listing['location']); ?></span></div>
                        <?php endif; ?>
                        <?php if (!empty($listing['year'])): ?>
                            <div class="pr-spec"><span class="lbl">Year</span><span class="val"><?php echo $h($listing['year']); ?></span></div>
                        <?php endif; ?>
                        <?php if (!empty($listing['make'])): ?>
                            <div class="pr-spec"><span class="lbl">Make</span><span class="val"><?php echo $h($listing['make']); ?></span></div>
                        <?php endif; ?>
                        <?php if (!empty($listing['model'])): ?>
                            <div class="pr-spec"><span class="lbl">Model</span><span class="val"><?php echo $h($listing['model']); ?></span></div>
                        <?php endif; ?>
                    </div>

                    <div class="pr-contact">
                        <p class="pr-presented">Presented by</p>
                        <p class="pr-name"><?php echo $h($presenterName); ?></p>
                        <?php if ($presenterPhone !== ''): ?>
                            <p class="pr-line"><?php echo $h($presenterPhone); ?></p>
                        <?php endif; ?>
                        <?php if ($presenterEmail !== ''): ?>
                            <p class="pr-line"><a href="mailto:<?php echo $h(rawurlencode($presenterEmail)); ?>"><?php echo $h($presenterEmail); ?></a></p>
                        <?php endif; ?>
                    </div>
                </td>
            </tr></tbody></table>

            <?php if (!empty($listing['description'])): ?>
                <p class="pr-desc-h">Description</p>
                <div class="pr-desc"><?php echo $h($listing['description']); ?></div>
            <?php endif; ?>
        </div>

        <div class="pr-footer">
            <p class="pr-caption">This listing is presented by <?php echo $h($presenterName); ?> of Haley Yachts / One Water Yacht Group. Private, off-market - please do not distribute publicly.</p>
        </div>
    </div>

    <div class="pr-actions-bottom">
        <button type="button" class="pr-print-btn js-print">Print this listing</button>
    </div>
<?php endif; ?>

<script>
    (function () {
        // No auto-print: the broker sees the formatted sheet first, then prints
        // via a button when ready. Auto-firing window.print() on load caused a
        // second dialog to reopen after the first was dismissed.
        var btns = document.querySelectorAll('.js-print');
        for (var i = 0; i < btns.length; i++) {
            btns[i].addEventListener('click', function () { window.print(); });
        }
    })();
</script>

</body>
</html>
