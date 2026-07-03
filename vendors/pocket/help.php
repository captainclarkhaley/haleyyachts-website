<?php
/**
 * pocket/help.php - in-app Help / user manual for the Pocket Listings app.
 *
 * Login-gated behind the SAME server-side auth gate as pocket/index.php: an
 * unauthenticated visitor is redirected to the shared /vendors/ login BEFORE any
 * markup, and a user who still owes a forced password change is sent to
 * change-password.html. Both live one level up in /vendors/.
 *
 * Same look, CSS, and structure as /vendors/help.php. Figures load from
 * pocket/help-img/. Every figure is guarded with file_exists so the page renders
 * clean before the screenshots are dropped in.
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth-lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/branding.php';

start_secure_session();
$pdo = vdb_connect();
$gateUser = current_user($pdo);
if ($gateUser === null) {
    header('Location: ../login.html');
    exit;
}
if ((int) $gateUser['must_change_password'] === 1) {
    header('Location: ../change-password.html');
    exit;
}

// Config-driven branding (product-first).
$brandName  = suite_setting($pdo, 'brand_name', 'Yacht Broker Support');
$tenantName = suite_setting($pdo, 'tenant_name', 'One Water Yacht Group');
$logoUrl    = suite_logo_url($pdo);
$faviconUrl = suite_favicon_url($pdo);
$h = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pocket Listings Help - <?php echo $h($brandName); ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Open+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="<?php echo $h($faviconUrl); ?>" sizes="any">
    <style>
        :root {
            --navy:#0a1628; --cyan:#21cbea; --cyan-d:#1aa8c4;
            --ink:#333; --muted:#666; --line:#e5e5e5; --canvas:#f4f6f8;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Open Sans', Arial, sans-serif;
            background: var(--canvas); margin: 0; color: var(--ink); line-height: 1.7;
            padding: 0 0 60px;
        }

        /* ----- Header ----- */
        .help-header {
            background: var(--navy); color: #fff;
            padding: 30px 24px 26px; text-align: center;
        }
        .help-logo {
            display: block; height: 44px; width: auto; max-width: 78%;
            margin: 0 auto 14px;
        }
        /* Primary product wordmark (typographic), OWYG banner demoted to a small
           secondary tenant mark beneath. */
        .help-wordmark { display: flex; flex-direction: column; align-items: center; gap: 8px; margin: 0 auto 14px; }
        .help-wordmark .help-wm-name {
            font-family: 'Cormorant Garamond', Georgia, serif;
            font-size: 1.9rem; line-height: 1; font-weight: 600; letter-spacing: 0.5px; color: #fff;
        }
        .help-wordmark img { display: block; height: 24px; width: auto; opacity: 0.82; }
        .help-header h1 {
            font-size: 1.5rem; font-weight: 300; text-transform: uppercase;
            letter-spacing: 2.5px; margin: 0;
        }
        .help-header h1 strong { font-weight: 700; color: var(--cyan); }
        .help-header .accent-line {
            width: 60px; height: 3px; background: var(--cyan); margin: 12px auto 12px;
        }
        .help-header p {
            margin: 0; font-size: 0.88rem; color: rgba(255,255,255,0.75); font-style: italic;
        }

        /* ----- Page shell ----- */
        .help-wrap {
            max-width: 820px; margin: 0 auto; padding: 0 22px;
        }
        .help-card {
            background: #fff; border: 1px solid var(--line); border-radius: 8px;
            box-shadow: 0 2px 14px rgba(0,0,0,0.05);
            padding: 36px 40px 44px; margin-top: 28px;
        }

        /* ----- Back link rows ----- */
        .help-backlink {
            text-align: center; margin: 22px 0 0; font-size: 0.9rem;
        }
        .help-backlink a {
            color: var(--cyan-d); text-decoration: none; font-weight: 600;
        }
        .help-backlink a:hover { text-decoration: underline; }
        .help-backlink.bottom { margin: 30px 0 0; }

        /* ----- Typography ----- */
        .help-card .lead {
            font-size: 1.02rem; color: var(--ink); margin: 0 0 8px;
        }
        .help-card h2 {
            font-size: 1.25rem; font-weight: 700; color: var(--navy);
            margin: 40px 0 10px; padding-top: 14px; border-top: 1px solid var(--line);
            scroll-margin-top: 16px;
        }
        .help-card h2:first-of-type { border-top: none; padding-top: 0; }
        .help-card h3 {
            font-size: 1.02rem; font-weight: 700; color: var(--navy);
            margin: 26px 0 8px; scroll-margin-top: 16px;
        }
        .help-card p { margin: 0 0 14px; }
        .help-card ul, .help-card ol { margin: 0 0 16px; padding-left: 24px; }
        .help-card li { margin-bottom: 7px; }
        .help-card li > ul, .help-card li > ol { margin: 7px 0 4px; }
        .help-card strong { color: var(--navy); font-weight: 600; }
        .help-card code {
            font-family: 'SFMono-Regular', Menlo, Consolas, monospace;
            font-size: 0.86em; background: #eef1f5; color: #33415c;
            border-radius: 3px; padding: 1px 5px;
        }
        .help-card a { color: var(--cyan-d); }

        /* ----- Table of contents ----- */
        .help-toc {
            background: var(--canvas); border: 1px solid var(--line);
            border-radius: 6px; padding: 18px 22px; margin: 0 0 30px;
        }
        .help-toc h2 {
            border: none; padding: 0; margin: 0 0 10px;
            font-size: 0.78rem; text-transform: uppercase; letter-spacing: 1.2px;
            color: var(--muted); font-weight: 700;
        }
        .help-toc ul { list-style: none; margin: 0; padding: 0; columns: 2; column-gap: 30px; }
        .help-toc li { margin-bottom: 6px; break-inside: avoid; }
        .help-toc a { color: var(--cyan-d); text-decoration: none; }
        .help-toc a:hover { text-decoration: underline; }

        /* ----- Screenshot placeholder boxes ----- */
        .help-shot {
            display: flex; align-items: center; justify-content: center;
            text-align: center; min-height: 120px;
            border: 2px dashed #b9c4d0; border-radius: 6px;
            background: #f0f3f7; color: var(--muted);
            padding: 22px 24px; margin: 16px 0 22px;
            font-size: 0.9rem; font-style: italic;
        }
        .help-shot::before {
            content: "Screenshot"; display: inline-block;
            font-size: 0.62rem; font-style: normal; font-weight: 700;
            text-transform: uppercase; letter-spacing: 1px;
            color: #8a98a8; margin-right: 10px;
            border: 1px solid #c4cedb; border-radius: 3px; padding: 2px 7px;
        }
        /* A placed screenshot (replaces a .help-shot placeholder once the image
           exists). Responsive, bordered, with an italic caption. */
        .help-fig { margin: 16px 0 22px; }
        .help-fig img {
            display: block; max-width: 100%; height: auto;
            border: 1px solid #e1e6ec; border-radius: 6px;
            box-shadow: 0 2px 14px rgba(0,0,0,0.08);
        }
        .help-fig figcaption {
            margin-top: 8px; font-size: 0.8rem; color: var(--muted);
            font-style: italic; text-align: center;
        }
        /* Portrait phone screenshot: cap the width so it does not dominate. */
        .help-fig-phone img { max-width: 300px; margin-left: auto; margin-right: auto; }

        /* ----- Code block ----- */
        .help-codeblock {
            font-family: 'SFMono-Regular', Menlo, Consolas, monospace;
            font-size: 0.84rem; line-height: 1.55; color: #f4f6f8;
            background: var(--navy); border-radius: 6px;
            padding: 16px 18px; margin: 12px 0 22px;
            overflow-x: auto; white-space: pre;
        }

        /* ----- Divider used between major sections ----- */
        hr.help-rule {
            border: none; border-top: 1px solid var(--line); margin: 36px 0 0;
        }

        /* ----- Responsive ----- */
        @media (max-width: 640px) {
            .help-header { padding: 24px 18px 22px; }
            .help-header h1 { font-size: 1.2rem; letter-spacing: 1.6px; }
            .help-logo { height: 36px; }
            .help-wrap { padding: 0 14px; }
            .help-card { padding: 24px 20px 30px; }
            .help-toc ul { columns: 1; }
            .help-card h2 { font-size: 1.15rem; }
        }
    </style>
    <?php suite_theme_head($pdo); // config-driven :root color override, must follow the page style block ?>
</head>
<body>

<header class="help-header">
    <div class="help-wordmark">
        <span class="help-wm-name"><?php echo $h($brandName); ?></span>
        <img src="<?php echo $h($logoUrl); ?>" alt="<?php echo $h($tenantName); ?>">
    </div>
    <h1>Pocket Listings <strong>Help</strong></h1>
    <div class="accent-line"></div>
    <p>Pocket Listings - broker user guide</p>
</header>

<div class="help-wrap">

    <div class="help-backlink top">
        <a href="index.php">&larr; Back to Pocket Listings</a>
    </div>

    <div class="help-card">

        <p class="lead">Pocket Listings is a private, off-market listings board for the OWYG broker network. A broker posts a boat, and other OWYG brokers can search it and contact the listing broker directly. It uses the same sign-in as the rest of Yacht Broker Support, so if you are already signed in to the Vendor Database you are signed in here too. This guide covers finding a listing, creating one, editing it (including photos), printing a customer sheet, how a listing expires, and the admin tools.</p>

        <?php if (file_exists(__DIR__ . '/help-img/main-screen.png')): ?>
        <!-- IMG: main-screen -->
        <figure class="help-fig">
            <img src="help-img/main-screen.png?v=<?php echo @filemtime(__DIR__ . '/help-img/main-screen.png'); ?>" alt="Pocket Listings main screen" loading="lazy">
            <figcaption>the Pocket Listings main screen</figcaption>
        </figure>
        <?php endif; ?>

        <!-- ===== Table of contents ===== -->
        <nav class="help-toc" aria-label="Contents">
            <h2>Contents</h2>
            <ul>
                <li><a href="#what-it-is">What Pocket Listings is</a></li>
                <li><a href="#finding-a-listing">Finding a listing</a></li>
                <li><a href="#creating-a-listing">Creating a new listing</a></li>
                <li><a href="#what-commit-does">What Commit does</a></li>
                <li><a href="#editing-a-listing">Editing a listing</a></li>
                <li><a href="#printing-a-sheet">Printing a customer sheet</a></li>
                <li><a href="#lifecycle">Lifecycle and expiration</a></li>
                <li><a href="#for-administrators">For Administrators</a></li>
                <li><a href="#deleting-a-listing">Deleting a listing</a></li>
                <li><a href="#need-help">Need help?</a></li>
            </ul>
        </nav>

        <!-- ===== What it is ===== -->
        <h2 id="what-it-is">What Pocket Listings is</h2>
        <p>Pocket Listings is a shared, private board of off-market boats for the OWYG broker network. It is not public and it is not on the open web. Only signed-in OWYG brokers can see it.</p>
        <p>The idea is simple: when you have a boat that is not listed publicly, you post it here so the rest of the network knows it exists. Another broker who has a buyer can search the board, find your listing, and contact you directly. Sign-in is the same account you use for the Vendor Database and the rest of Yacht Broker Support.</p>

        <hr class="help-rule">

        <!-- ===== Finding a listing ===== -->
        <h2 id="finding-a-listing">Finding a listing</h2>
        <p>The top of the main screen is the filter bar. You can use one filter or stack several together. Listings show <strong>newest-entered first</strong>.</p>
        <p>The filters are:</p>
        <ul>
            <li><strong>Keyword</strong> - matches make, model, location, and description.</li>
            <li><strong>Make</strong> - narrow to a single builder.</li>
            <li><strong>Year</strong> - a Min and Max range.</li>
            <li><strong>Length (ft)</strong> - a Min and Max range.</li>
            <li><strong>Price ($)</strong> - a Min and Max range.</li>
        </ul>
        <p>Click <strong>Clear</strong> to reset every filter and start fresh.</p>

        <hr class="help-rule">

        <!-- ===== Creating a listing ===== -->
        <h2 id="creating-a-listing">Creating a new listing</h2>
        <p>Click <strong>+ New Pocket Listing</strong> at the top of the main screen and fill in the fields:</p>
        <ul>
            <li><strong>Make</strong> - a dropdown of builders. If the builder you need is not there, click <strong>+ Add</strong> to add a new make (you confirm before it is added).</li>
            <li><strong>Model</strong></li>
            <li><strong>Year</strong></li>
            <li><strong>Length</strong> (in feet)</li>
            <li><strong>Location</strong> (city, state)</li>
            <li><strong>Days Active</strong> - how many days the listing stays live. This sets the expiration.</li>
            <li><strong>Price</strong>, with a <strong>Net / List</strong> toggle to say which kind of price it is.</li>
            <li><strong>Description</strong> - up to 750 characters of key details, condition, and notes for other brokers.</li>
            <li><strong>Photos</strong> - a <strong>Hero image</strong> plus up to <strong>3 additional images</strong>. Photos are optimized automatically when you attach them.</li>
        </ul>

        <?php if (file_exists(__DIR__ . '/help-img/new-listing.png')): ?>
        <!-- IMG: new-listing -->
        <figure class="help-fig">
            <img src="help-img/new-listing.png?v=<?php echo @filemtime(__DIR__ . '/help-img/new-listing.png'); ?>" alt="New Pocket Listing form" loading="lazy">
            <figcaption>the New Pocket Listing form</figcaption>
        </figure>
        <?php endif; ?>

        <p>When the form is filled in, click <strong>Save &rarr; Review</strong>. You get a <strong>preview card</strong> showing exactly how the listing will look. From there you can:</p>
        <ul>
            <li><strong>Edit</strong> to go back and change anything.</li>
            <li><strong>Commit</strong> to save.</li>
        </ul>
        <p><strong>Nothing is saved until you Commit.</strong> If you close the review without committing, the listing is not created.</p>

        <?php if (file_exists(__DIR__ . '/help-img/review-commit.png')): ?>
        <!-- IMG: review-commit -->
        <figure class="help-fig">
            <img src="help-img/review-commit.png?v=<?php echo @filemtime(__DIR__ . '/help-img/review-commit.png'); ?>" alt="Review and Commit screen" loading="lazy">
            <figcaption>the Review screen with the Edit and Commit buttons</figcaption>
        </figure>
        <?php endif; ?>

        <hr class="help-rule">

        <!-- ===== What Commit does ===== -->
        <h2 id="what-commit-does">What Commit does</h2>
        <p>Committing does two things: it <strong>saves the listing</strong> to the board, and it <strong>notifies the broker network by email</strong> so the rest of the OWYG brokers know a new boat is available.</p>

        <hr class="help-rule">

        <!-- ===== Editing a listing ===== -->
        <h2 id="editing-a-listing">Editing a listing</h2>
        <p>Open a listing and click <strong>Edit</strong>. You can change any field the same way you filled them in when creating it.</p>
        <h3>Changing the photos</h3>
        <p>In edit mode you see the current images as <strong>thumbnails</strong>, each with an <strong>x</strong> to remove it. As you remove images, a live counter tells you <strong>"Keeping X of N, you can add Y more"</strong> so you always know how many slots are free. Add new photos to fill the freed slots.</p>
        <p>A listing holds at most <strong>4 images</strong>: 1 hero plus 3 additional.</p>

        <?php if (file_exists(__DIR__ . '/help-img/edit-images.png')): ?>
        <!-- IMG: edit-images -->
        <figure class="help-fig">
            <img src="help-img/edit-images.png?v=<?php echo @filemtime(__DIR__ . '/help-img/edit-images.png'); ?>" alt="Editing a listing's images" loading="lazy">
            <figcaption>editing images: current thumbnails with an x to remove, plus the keep/add counter</figcaption>
        </figure>
        <?php endif; ?>

        <hr class="help-rule">

        <!-- ===== Printing a customer sheet ===== -->
        <h2 id="printing-a-sheet">Printing a customer sheet</h2>
        <p>Open a listing and click <strong>Print</strong>. This opens a clean, one-page sheet with <strong>your</strong> contact info (whoever is signed in), ready to hand or email to a customer.</p>
        <p>Listings priced <strong>Net</strong> print as <strong>"Call for Pricing"</strong> on the sheet, so a net price is never shown to a customer.</p>

        <?php if (file_exists(__DIR__ . '/help-img/print-sheet.png')): ?>
        <!-- IMG: print-sheet -->
        <figure class="help-fig">
            <img src="help-img/print-sheet.png?v=<?php echo @filemtime(__DIR__ . '/help-img/print-sheet.png'); ?>" alt="Printable customer sheet" loading="lazy">
            <figcaption>the printable one-page customer sheet</figcaption>
        </figure>
        <?php endif; ?>

        <hr class="help-rule">

        <!-- ===== Lifecycle and expiration ===== -->
        <h2 id="lifecycle">Lifecycle and expiration</h2>
        <p>The <strong>Days Active</strong> value you set on a listing controls when it expires.</p>
        <ul>
            <li>The listing broker gets a reminder <strong>7 days</strong> before the listing expires, and again <strong>1 day</strong> before.</li>
            <li>When a listing expires it is <strong>automatically archived</strong>. It is hidden from the board but <strong>not deleted</strong>.</li>
        </ul>

        <hr class="help-rule">

        <!-- ===== For Administrators ===== -->
        <h2 id="for-administrators">For Administrators</h2>
        <p>Administrators have a few extra tools:</p>
        <ul>
            <li><strong>Show archived</strong> - a toggle that reveals archived (expired) listings alongside the active ones.</li>
            <li><strong>Reactivate</strong> - on an archived listing, an admin can reactivate it, which gives it a fresh active window.</li>
            <li><strong>Add a new Make</strong> - admins can add a builder to the Make list.</li>
        </ul>

        <?php if (file_exists(__DIR__ . '/help-img/archived.png')): ?>
        <!-- IMG: archived -->
        <figure class="help-fig">
            <img src="help-img/archived.png?v=<?php echo @filemtime(__DIR__ . '/help-img/archived.png'); ?>" alt="Show archived and Reactivate" loading="lazy">
            <figcaption>the admin "Show archived" view with the Reactivate control</figcaption>
        </figure>
        <?php endif; ?>

        <hr class="help-rule">

        <!-- ===== Deleting a listing ===== -->
        <h2 id="deleting-a-listing">Deleting a listing</h2>
        <p>A listing can be deleted by its <strong>owner</strong> (the broker who created it) or by an <strong>administrator</strong>. Open the listing and use the <strong>Delete</strong> button.</p>

        <hr class="help-rule">

        <!-- ===== Need help? ===== -->
        <h2 id="need-help">Need help?</h2>
        <p>If something is not working or you are locked out, contact your administrator (Clark or your Administrative Assistant) and they can help.</p>

    </div>

    <div class="help-backlink bottom">
        <a href="index.php">&larr; Back to Pocket Listings</a>
    </div>

</div>

</body>
</html>
