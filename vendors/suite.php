<?php
/**
 * suite.php - Broker Suite launcher (master app menu).
 *
 * This is the screen a broker lands on after signing in: an umbrella home that
 * lists every app in the suite (Vendor Management, Pocket Listings, ...) and lets
 * the broker open one, edit their profile, or log out.
 *
 * Login-gated behind the SAME server-side auth gate as index.php / help.php: an
 * unauthenticated visitor is redirected to login.html BEFORE any markup, and a
 * user who still owes a forced password change is sent to change-password.html.
 * The gate cannot be bypassed by disabling JavaScript.
 *
 * The session cookie is path-scoped to /vendors/, which is exactly why this file
 * lives under /vendors/ - do not move it out of that path.
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth-lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/db.php';

start_secure_session();
$pdo = vdb_connect();
$gateUser = current_user($pdo);
if ($gateUser === null) {
    header('Location: login.html');
    exit;
}
if ((int) $gateUser['must_change_password'] === 1) {
    header('Location: change-password.html');
    exit;
}

// Config-driven branding (product-first): the wordmark shown as the primary
// identity, with the tenant/org as the secondary mark.
$brandName  = suite_setting($pdo, 'brand_name', 'Yacht Broker Support');
$tenantName = suite_setting($pdo, 'tenant_name', 'One Water Yacht Group');

// --- derive display values from the logged-in user (server-side) ------------
$fullName  = trim((string) $gateUser['name']);
if ($fullName === '') { $fullName = (string) $gateUser['account_id']; }

// First name for the hero greeting.
$firstName = $fullName;
$sp = strpos($fullName, ' ');
if ($sp !== false) { $firstName = substr($fullName, 0, $sp); }

// Avatar initials: first letter of the first two words, uppercased. Falls back
// to the first two characters of a single-word name.
$initials = '';
$parts = preg_split('/\s+/', $fullName);
foreach ($parts as $p) {
    if ($p !== '') { $initials .= strtoupper(substr($p, 0, 1)); }
    if (strlen($initials) >= 2) { break; }
}
if ($initials === '') { $initials = 'B'; }
if (strlen($initials) === 1 && strlen($fullName) >= 2) {
    $initials = strtoupper(substr($fullName, 0, 2));
}

$isAdmin = isset($gateUser['is_admin']) && (int) $gateUser['is_admin'] === 1;

// Time-of-day overline, computed server-side from the host clock.
$hour = (int) date('G');
if ($hour < 12)      { $greeting = 'GOOD MORNING'; }
elseif ($hour < 18)  { $greeting = 'GOOD AFTERNOON'; }
else                 { $greeting = 'GOOD EVENING'; }

$h = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Broker Suite - <?php echo $h($brandName); ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ============================================================
           YACHT BROKER SUPPORT - BROKER SUITE - palette tokens
           --accent is the brand cyan (#23cbea). (Palette originated
           with the Haley Yachts site; kept as-is for the product.)
           ============================================================ */
        :root {
            --navy: #0c1f2e;
            --bg: #f5f7f9;
            --card: #ffffff;
            --footer: #eef1f4;
            --ink: #1b2733;
            --muted: #64707c;
            --hair: rgba(20,40,60,0.10);
            --accent: #23cbea;
            --accent-text: #0e93b3;
            --accent-soft: rgba(35,203,234,0.12);
            --accent-line: rgba(35,203,234,0.55);
            --on-navy: #eef4f7;
            --on-navy-dim: #8fa6b4;
            --logout-red: #c0503a;
            --danger: #c0392b;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; }
        body {
            font-family: 'Manrope', Arial, sans-serif;
            color: var(--ink);
            background: var(--bg);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            line-height: 1.5;
        }

        /* ----- Top bar ----- */
        .bs-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 20px 44px;
            background: var(--navy);
            color: var(--on-navy);
        }
        .bs-brand {
            display: flex;
            align-items: center;
            gap: 22px;
            min-width: 0;
        }
        .bs-brand img { height: 28px; width: auto; display: block; object-fit: contain; opacity: .92; }
        .bs-brand-divider { width: 1px; height: 32px; background: rgba(238,244,247,0.2); flex: none; }
        .bs-brand-label {
            font-size: 11px;
            letter-spacing: .42em;
            color: var(--accent);
            font-weight: 600;
            white-space: nowrap;
        }
        /* Primary product wordmark (typographic - no logo image yet). */
        .bs-wordmark {
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-width: 0;
        }
        .bs-wordmark .bs-wm-name {
            font-family: 'Cormorant Garamond', Georgia, serif;
            font-size: 26px;
            line-height: 1;
            font-weight: 600;
            letter-spacing: .01em;
            color: var(--on-navy);
            white-space: nowrap;
        }
        .bs-wordmark .bs-wm-name .bs-wm-accent { color: var(--accent); }
        .bs-wordmark .bs-wm-tenant {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .bs-wordmark .bs-wm-tenant img { height: 18px; width: auto; display: block; object-fit: contain; opacity: .85; }
        .bs-wordmark .bs-wm-tenant .bs-wm-tenant-label {
            font-size: 10px;
            letter-spacing: .28em;
            text-transform: uppercase;
            color: rgba(238,244,247,0.6);
            white-space: nowrap;
        }

        /* ----- Account menu ----- */
        .bs-account { position: relative; flex: none; }
        .bs-account-trigger {
            display: flex;
            align-items: center;
            gap: 13px;
            cursor: pointer;
            padding: 7px 10px 7px 7px;
            border-radius: 999px;
            border: 1px solid rgba(238,244,247,0.16);
            background: transparent;
            font-family: inherit;
            color: inherit;
        }
        .bs-account-trigger:hover { border-color: rgba(238,244,247,0.34); }
        .bs-avatar {
            width: 40px; height: 40px;
            border-radius: 50%;
            background: var(--accent);
            color: var(--navy);
            display: grid;
            place-items: center;
            font-family: 'Cormorant Garamond', serif;
            font-weight: 700;
            font-size: 17px;
            letter-spacing: .04em;
            flex: none;
        }
        .bs-account-name {
            display: flex;
            flex-direction: column;
            line-height: 1.25;
            text-align: left;
        }
        .bs-account-name .nm { font-size: 14px; font-weight: 600; color: var(--on-navy); }
        .bs-account-name .rl { font-size: 11.5px; color: var(--on-navy-dim); letter-spacing: .04em; }
        .bs-chevron { color: var(--on-navy-dim); font-size: 11px; margin-left: 2px; }

        .bs-dropdown {
            position: absolute;
            top: 58px;
            right: 0;
            width: 210px;
            background: var(--card);
            border: 1px solid var(--hair);
            border-radius: 12px;
            box-shadow: 0 24px 50px -20px rgba(12,31,51,0.4);
            overflow: hidden;
            z-index: 5;
            display: none;
        }
        .bs-dropdown.open { display: block; }
        .bs-dropdown button {
            display: block;
            width: 100%;
            text-align: left;
            padding: 12px 16px;
            font-family: inherit;
            font-size: 13.5px;
            background: transparent;
            border: none;
            cursor: pointer;
            color: var(--ink);
        }
        .bs-dropdown button:hover { background: var(--bg); }
        .bs-dropdown .bs-menu-profile { border-bottom: 1px solid var(--hair); }
        .bs-dropdown .bs-menu-logout { color: var(--logout-red); }
        /* Admin section inside the account dropdown (admin only). A labeled
           group of links to the Broker Suite admin pages, set off from the
           personal-account items above it by a top border + a small caption. */
        .bs-dropdown .bs-menu-admin-head {
            padding: 10px 16px 4px;
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: var(--muted);
            border-top: 1px solid var(--hair);
            background: var(--card);
        }
        .bs-dropdown a.bs-menu-link {
            display: block;
            width: 100%;
            text-align: left;
            padding: 12px 16px;
            font-family: inherit;
            font-size: 13.5px;
            text-decoration: none;
            color: var(--ink);
        }
        .bs-dropdown a.bs-menu-link:hover { background: var(--bg); }

        /* ----- Main ----- */
        .bs-main {
            flex: 1;
            width: 100%;
            max-width: 1180px;
            margin: 0 auto;
            padding: 52px 44px 64px;
        }

        /* ----- Hero ----- */
        .bs-hero { margin-bottom: 40px; }
        .bs-overline {
            font-size: 12px;
            letter-spacing: .34em;
            color: var(--accent-text);
            font-weight: 700;
        }
        .bs-hero h1 {
            margin: 14px 0 8px;
            font-family: 'Cormorant Garamond', serif;
            font-weight: 600;
            font-size: 46px;
            line-height: 1.05;
            color: var(--navy);
        }
        .bs-hero p { margin: 0; font-size: 16px; color: var(--muted); max-width: 560px; }

        /* ----- Section label ----- */
        .bs-section-label {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 22px;
        }
        .bs-section-label .lbl { font-size: 12px; letter-spacing: .28em; color: var(--muted); font-weight: 700; }
        .bs-section-label .rule { flex: 1; height: 1px; background: var(--hair); }
        .bs-section-label .count { font-size: 12.5px; color: var(--muted); }

        /* ----- App grid ----- */
        .bs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        .bs-tile {
            display: flex;
            flex-direction: column;
            min-height: 214px;
            padding: 26px;
            background: var(--card);
            border: 1px solid var(--hair);
            border-radius: 15px;
            text-decoration: none;
            color: inherit;
            transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease;
        }
        /* Only navigable (live) tiles lift on hover / show a pointer. */
        a.bs-tile { cursor: pointer; }
        a.bs-tile:hover {
            transform: translateY(-5px);
            box-shadow: 0 24px 44px -26px rgba(12,31,51,0.28);
            border-color: var(--accent-line);
        }
        /* Coming-soon tile is a non-navigable div: no lift, default cursor. */
        .bs-tile.bs-tile-soon { cursor: default; }
        .bs-monogram {
            width: 56px; height: 56px;
            border-radius: 12px;
            background: var(--navy);
            color: var(--accent);
            display: grid;
            place-items: center;
            font-family: 'Cormorant Garamond', serif;
            font-weight: 700;
            font-size: 22px;
            letter-spacing: .04em;
        }
        .bs-tile h3 {
            margin: 20px 0 0;
            font-family: 'Cormorant Garamond', serif;
            font-weight: 600;
            font-size: 25px;
            color: var(--navy);
            line-height: 1.1;
        }
        .bs-tile-desc { margin: 9px 0 0; font-size: 13.5px; line-height: 1.5; color: var(--muted); }
        .bs-badge-row { margin-top: auto; padding-top: 20px; }
        .bs-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 13px;
            border-radius: 999px;
            font-size: 11.5px;
            font-weight: 700;
            letter-spacing: .12em;
        }
        .bs-badge-live { background: var(--accent-soft); color: var(--accent-text); }
        .bs-badge-live .dot { width: 7px; height: 7px; border-radius: 50%; background: var(--accent); }
        .bs-badge-soon { border: 1px solid rgba(100,112,124,0.4); color: var(--muted); }
        .bs-badge-soon .dot { width: 7px; height: 7px; border-radius: 50%; border: 1.5px solid var(--muted); }

        /* ----- Add-application tile (admin only) ----- */
        .bs-tile-add {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 12px;
            min-height: 214px;
            padding: 26px;
            background: transparent;
            border: 1.5px dashed rgba(20,40,60,0.2);
            border-radius: 15px;
            color: var(--muted);
            cursor: pointer;
            font-family: inherit;
            transition: border-color .25s ease, color .25s ease;
        }
        .bs-tile-add:hover { border-color: var(--accent-line); color: var(--accent-text); }
        .bs-tile-add .plus {
            width: 46px; height: 46px;
            border-radius: 50%;
            border: 1.5px solid currentColor;
            display: grid;
            place-items: center;
            font-size: 24px;
            font-weight: 300;
            line-height: 1;
        }
        .bs-tile-add .lbl { font-size: 13.5px; font-weight: 600; letter-spacing: .02em; }
        .bs-add-note {
            margin-top: 10px;
            font-size: 11.5px;
            color: var(--muted);
            text-align: center;
            display: none;
        }
        .bs-add-note.show { display: block; }

        /* ----- Footer ----- */
        .bs-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 22px 44px;
            border-top: 1px solid var(--hair);
            background: var(--footer);
        }
        .bs-footer .aff { font-size: 12px; color: var(--muted); letter-spacing: .02em; }
        .bs-footer .aff strong { color: var(--ink); font-weight: 600; }
        .bs-footer .copy { font-size: 12px; color: var(--muted); }

        /* ----- My Profile modal (mirrors the vendor app's modal) ----- */
        .bs-overlay {
            position: fixed;
            inset: 0;
            background: rgba(12,31,51,0.5);
            display: none;
            align-items: flex-start;
            justify-content: center;
            padding: 40px 16px;
            overflow-y: auto;
            z-index: 50;
        }
        .bs-overlay.open { display: flex; }
        .bs-modal {
            width: 100%;
            max-width: 520px;
            background: var(--card);
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 30px 60px -20px rgba(12,31,51,0.5);
        }
        .bs-modal-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 22px;
            background: var(--navy);
            color: var(--on-navy);
        }
        .bs-modal-head h2 {
            margin: 0;
            font-family: 'Cormorant Garamond', serif;
            font-weight: 600;
            font-size: 24px;
        }
        .bs-modal-close {
            background: transparent;
            border: none;
            color: var(--on-navy);
            font-size: 26px;
            line-height: 1;
            cursor: pointer;
        }
        .bs-modal-body { padding: 22px; }
        .bs-modal-foot {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 16px 22px;
            border-top: 1px solid var(--hair);
        }
        .bs-form .row { margin-bottom: 16px; }
        .bs-form .row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .bs-form label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--muted);
            margin-bottom: 6px;
        }
        .bs-form input, .bs-form select {
            width: 100%;
            font-family: inherit;
            font-size: 14px;
            color: var(--ink);
            border: 1px solid var(--hair);
            border-radius: 8px;
            padding: 10px 12px;
            background: #fff;
        }
        .bs-form input:focus, .bs-form select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(35,203,234,0.15);
        }
        .bs-form input[readonly] { background: var(--bg); color: var(--muted); cursor: not-allowed; }
        .bs-pw-section { margin-top: 24px; border-top: 1px solid var(--hair); padding-top: 20px; }
        .bs-pw-section h3 {
            margin: 0 0 14px;
            font-family: 'Cormorant Garamond', serif;
            font-weight: 600;
            font-size: 20px;
            color: var(--navy);
        }
        .bs-actions { text-align: right; }
        .bs-btn {
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid transparent;
            border-radius: 8px;
            padding: 10px 18px;
        }
        .bs-btn-primary { background: var(--accent); color: var(--navy); }
        .bs-btn-primary:hover { background: #1bb6d3; }
        .bs-btn-primary:disabled { opacity: .6; cursor: default; }
        .bs-btn-ghost { background: transparent; color: var(--muted); border-color: var(--hair); }
        .bs-btn-ghost:hover { border-color: var(--muted); color: var(--ink); }
        .bs-notice {
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 16px;
            display: none;
        }
        .bs-notice.show { display: block; }
        .bs-notice.error { background: #fdecea; border-left: 4px solid var(--danger); color: #7a241c; }
        .bs-notice.info { background: #e8f7ea; border-left: 4px solid #1b6e2e; color: #1b5e2a; }

        /* ----- Responsive ----- */
        @media (max-width: 700px) {
            .bs-topbar { flex-direction: column; align-items: stretch; gap: 16px; padding: 18px 22px; }
            .bs-brand { justify-content: center; }
            .bs-account { align-self: center; }
            .bs-dropdown { right: 50%; transform: translateX(50%); }
            .bs-main { padding: 36px 22px 48px; }
            .bs-hero h1 { font-size: 38px; }
            .bs-footer { flex-direction: column; align-items: flex-start; gap: 8px; padding: 20px 22px; }
        }
        @media (max-width: 420px) {
            .bs-form .row-2 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- ===== Top bar ===== -->
<header class="bs-topbar">
    <div class="bs-brand">
        <div class="bs-wordmark">
            <span class="bs-wm-name"><?php echo $h($brandName); ?></span>
            <span class="bs-wm-tenant">
                <img src="/images/email/owyg-banner-reverse.png" alt="<?php echo $h($tenantName); ?>">
                <span class="bs-wm-tenant-label"><?php echo $h($tenantName); ?></span>
            </span>
        </div>
        <span class="bs-brand-divider"></span>
        <span class="bs-brand-label">BROKER SUITE</span>
    </div>

    <div class="bs-account">
        <button type="button" class="bs-account-trigger" id="accountTrigger"
            aria-haspopup="true" aria-expanded="false">
            <span class="bs-avatar"><?php echo $h($initials); ?></span>
            <span class="bs-account-name">
                <span class="nm"><?php echo $h($fullName); ?></span>
                <span class="rl">Licensed Broker</span>
            </span>
            <span class="bs-chevron" aria-hidden="true">&#9662;</span>
        </button>
        <div class="bs-dropdown" id="accountDropdown" role="menu">
            <button type="button" class="bs-menu-profile" id="menuProfile" role="menuitem">Profile</button>
            <button type="button" class="bs-menu-logout" id="menuLogout" role="menuitem">Logout</button>
            <?php if ($isAdmin): ?>
            <!--
                ADMIN section. Server-side gated on $isAdmin: a non-admin never
                receives this markup at all (not merely hidden with CSS), and the
                admin pages themselves re-gate via admin/admin-guard.php.
                Phase 2a shipped Settings; 2b added Staff Accounts; 2c adds
                Predefined Lists.
            -->
            <div class="bs-menu-admin-head">Admin</div>
            <a class="bs-menu-link" href="admin/users.php" role="menuitem">Staff Accounts</a>
            <a class="bs-menu-link" href="admin/vendor-lists.php" role="menuitem">Predefined Lists</a>
            <a class="bs-menu-link" href="admin/settings.php" role="menuitem">Settings</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- ===== Main ===== -->
<main class="bs-main">

    <section class="bs-hero">
        <div class="bs-overline" id="bsGreeting"><?php echo $h($greeting); ?></div>
        <h1>Welcome back, <?php echo $h($firstName); ?>.</h1>
        <p>Choose an application to open. Your suite grows as new tools come aboard.</p>
    </section>

    <div class="bs-section-label">
        <span class="lbl">APPLICATIONS</span>
        <span class="rule"></span>
        <span class="count" id="appCount"></span>
    </div>

    <div class="bs-grid" id="appGrid">
        <?php
        // App registry: a simple hardcoded list for now (name, monogram,
        // description, href, status). When a real app-registry backend exists,
        // this array is what it feeds. The "{N} available" count = number of
        // visible app tiles (excludes the add-application tile).
        $apps = array(
            array(
                'name'     => 'Vendor Management',
                'monogram' => 'VM',
                'desc'     => 'Access, manage, and search vendors by type, coverage area, and broker ratings.',
                'href'     => 'index.php',
                'status'   => 'live',
            ),
            array(
                'name'     => 'Pocket Listings',
                'monogram' => 'PL',
                'desc'     => 'Enter private off-market listings and let OWYG brokers search the network.',
                // LIVE for admins (navigable link to the app); coming-soon for
                // everyone else. Gated server-side on the resolved session user so
                // a non-admin cannot reach it by tampering with the client.
                'href'     => $isAdmin ? 'pocket/' : '#',
                'status'   => $isAdmin ? 'live' : 'soon',
            ),
            array(
                'name'     => 'Broker Looking For...',
                'monogram' => 'BL',
                'desc'     => 'Post what your client is searching for and let OWYG brokers surface a match.',
                'href'     => '#',
                'status'   => 'soon',
            ),
        );

        foreach ($apps as $app) {
            $isLive = ($app['status'] === 'live');
            // Live tiles are real links; coming-soon tiles are inert (a div), so
            // they are not navigable and do not lift on hover.
            if ($isLive) {
                echo '<a class="bs-tile" href="' . $h($app['href']) . '">';
            } else {
                echo '<div class="bs-tile bs-tile-soon">';
            }
            echo '<div class="bs-monogram">' . $h($app['monogram']) . '</div>';
            echo '<h3>' . $h($app['name']) . '</h3>';
            echo '<p class="bs-tile-desc">' . $h($app['desc']) . '</p>';
            echo '<div class="bs-badge-row">';
            if ($isLive) {
                echo '<span class="bs-badge bs-badge-live"><span class="dot"></span>LIVE</span>';
            } else {
                echo '<span class="bs-badge bs-badge-soon"><span class="dot"></span>COMING SOON</span>';
            }
            echo '</div>';
            echo $isLive ? '</a>' : '</div>';
        }
        ?>

        <?php if ($isAdmin): ?>
        <!--
            Admin-only "Add application" affordance. Inert for now: there is no
            app-registry backend yet, so clicking it just shows a friendly note.
            This is where a future "register a new app" flow wires in (open a
            form/modal that appends to the app registry above).
        -->
        <button type="button" class="bs-tile-add" id="addAppTile">
            <span class="plus" aria-hidden="true">+</span>
            <span class="lbl">Add application</span>
            <span class="bs-add-note" id="addAppNote">App registration is coming soon.</span>
        </button>
        <?php endif; ?>
    </div>
</main>

<!-- ===== Footer ===== -->
<footer class="bs-footer">
    <span class="aff">Yacht brokerage with <strong>One Water Yacht Group</strong></span>
    <span class="copy">&copy; 2026 <?php echo $h($brandName); ?> &middot; <?php echo $h($tenantName); ?></span>
</footer>

<!-- ===== My Profile modal (wired to the same auth.php endpoints as the vendor app) ===== -->
<div class="bs-overlay" id="profileOverlay" aria-hidden="true">
    <div class="bs-modal" role="dialog" aria-modal="true" aria-labelledby="profileTitle">
        <div class="bs-modal-head">
            <h2 id="profileTitle">My Profile</h2>
            <button type="button" class="bs-modal-close" id="profileClose" aria-label="Close">&times;</button>
        </div>
        <div class="bs-modal-body">

            <form class="bs-form" id="profileForm" autocomplete="off">
                <div class="bs-notice error" id="profileError"></div>
                <div class="bs-notice info" id="profileSuccess"></div>

                <div class="row">
                    <label for="pAccountId">Account ID</label>
                    <input type="text" id="pAccountId" readonly disabled
                        title="Your login handle is assigned by an administrator and cannot be changed here.">
                </div>

                <div class="row">
                    <label for="pName">Name *</label>
                    <input type="text" id="pName" autocomplete="off" required>
                </div>

                <div class="row row-2">
                    <div>
                        <label for="pEmail">Email *</label>
                        <input type="email" id="pEmail" autocomplete="off" required>
                    </div>
                    <div>
                        <label for="pCell">Cell</label>
                        <input type="text" id="pCell" autocomplete="off">
                    </div>
                </div>

                <div class="row">
                    <label for="pHomeOffice">Home Office</label>
                    <select id="pHomeOffice"></select>
                </div>

                <div class="bs-actions">
                    <button type="button" class="bs-btn bs-btn-primary" id="btnSaveProfile">Save Profile</button>
                </div>
            </form>

            <div class="bs-pw-section">
                <h3>Change Password</h3>
                <form class="bs-form" id="passwordForm" autocomplete="off">
                    <div class="bs-notice error" id="pwError"></div>
                    <div class="bs-notice info" id="pwSuccess"></div>

                    <div class="row">
                        <label for="pwCurrent">Current Password</label>
                        <input type="password" id="pwCurrent" autocomplete="current-password">
                    </div>
                    <div class="row row-2">
                        <div>
                            <label for="pwNew">New Password</label>
                            <input type="password" id="pwNew" autocomplete="new-password">
                        </div>
                        <div>
                            <label for="pwConfirm">Confirm New Password</label>
                            <input type="password" id="pwConfirm" autocomplete="new-password">
                        </div>
                    </div>
                    <div class="bs-actions">
                        <button type="button" class="bs-btn bs-btn-primary" id="btnSavePassword">Change Password</button>
                    </div>
                </form>
            </div>

        </div>
        <div class="bs-modal-foot">
            <button type="button" class="bs-btn bs-btn-ghost" id="btnProfileClose">Close</button>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';
    function $(id) { return document.getElementById(id); }

    // Greeting from the broker's LOCAL computer time (not the server clock), so
    // it reflects the time zone the broker is actually in. Overrides the
    // server-rendered fallback in #bsGreeting.
    (function setLocalGreeting() {
        var el = $('bsGreeting');
        if (!el) { return; }
        var h = new Date().getHours();
        el.textContent = h < 12 ? 'GOOD MORNING' : (h < 18 ? 'GOOD AFTERNOON' : 'GOOD EVENING');
    })();

    var homeOffices = [];   // canonical home-office list from auth.php?action=me
    var currentUser = null; // last-known public user from auth.php?action=me

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    // ---- app count (visible app tiles, excludes the add-application tile) ----
    (function setAppCount() {
        var grid = $('appGrid');
        var n = grid ? grid.querySelectorAll('.bs-tile').length : 0;
        var el = $('appCount');
        if (el) { el.textContent = n + ' available'; }
    })();

    // ---- account menu (toggle, outside-click, Escape) ------------------------
    var trigger = $('accountTrigger');
    var dropdown = $('accountDropdown');

    function menuOpen() { return dropdown.classList.contains('open'); }
    function openMenu() {
        dropdown.classList.add('open');
        trigger.setAttribute('aria-expanded', 'true');
    }
    function closeMenu() {
        dropdown.classList.remove('open');
        trigger.setAttribute('aria-expanded', 'false');
    }
    trigger.addEventListener('click', function (e) {
        e.stopPropagation();
        if (menuOpen()) { closeMenu(); } else { openMenu(); }
    });
    document.addEventListener('click', function (e) {
        if (menuOpen() && !dropdown.contains(e.target) && e.target !== trigger) {
            closeMenu();
        }
    });

    // ---- auth POST helper (mirrors the vendor app's authPost) ----------------
    function authPost(action, body) {
        return fetch('auth.php?action=' + action, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(body || {})
        }).then(function (res) {
            return res.text().then(function (t) {
                var data;
                try { data = t ? JSON.parse(t) : {}; } catch (e) { data = { ok: false, error: 'Bad server response.' }; }
                if (!res.ok && data.ok !== false) { data.ok = false; }
                data._status = res.status;
                if (res.status === 401) { window.location.href = 'login.html'; }
                return data;
            });
        });
    }

    // ---- logout (same sign-out flow as the vendor app) -----------------------
    function logout() {
        fetch('auth.php?action=logout', {
            method: 'POST',
            headers: { 'Accept': 'application/json' }
        }).then(function () {
            window.location.href = 'login.html';
        }).catch(function () {
            window.location.href = 'login.html';
        });
    }
    $('menuLogout').addEventListener('click', logout);

    // ---- My Profile modal (same fields + endpoints as the vendor app) --------
    function showNotice(id, msg) { var e = $(id); e.textContent = msg; e.classList.add('show'); }
    function clearNotice(id) { var e = $(id); e.textContent = ''; e.classList.remove('show'); }

    function fillHomeOfficeOptions(selected) {
        var sel = $('pHomeOffice');
        var html = '<option value="">(none)</option>';
        for (var i = 0; i < homeOffices.length; i++) {
            var v = esc(homeOffices[i]);
            html += '<option value="' + v + '"' +
                (homeOffices[i] === selected ? ' selected' : '') + '>' + v + '</option>';
        }
        sel.innerHTML = html;
    }

    function openProfile() {
        closeMenu();
        clearNotice('profileError'); clearNotice('profileSuccess');
        clearNotice('pwError'); clearNotice('pwSuccess');
        $('pwCurrent').value = ''; $('pwNew').value = ''; $('pwConfirm').value = '';

        // Always pull fresh from the server so the form reflects saved state.
        fetch('auth.php?action=me', { headers: { 'Accept': 'application/json' } })
            .then(function (res) {
                if (res.status === 401) { window.location.href = 'login.html'; return null; }
                return res.json();
            })
            .then(function (data) {
                if (!data || !data.ok || !data.user) {
                    showNotice('profileError', 'Could not load your profile.');
                    openOverlay();
                    return;
                }
                currentUser = data.user;
                homeOffices = data.home_offices || homeOffices;
                $('pAccountId').value = currentUser.account_id || '';
                $('pName').value = currentUser.name || '';
                $('pEmail').value = currentUser.email || '';
                $('pCell').value = currentUser.cell || '';
                fillHomeOfficeOptions(currentUser.home_office || '');
                openOverlay();
                $('pName').focus();
            })
            .catch(function () {
                showNotice('profileError', 'Network error loading your profile.');
                openOverlay();
            });
    }

    function openOverlay() {
        var ov = $('profileOverlay');
        ov.classList.add('open');
        ov.setAttribute('aria-hidden', 'false');
    }
    function closeProfile() {
        var ov = $('profileOverlay');
        ov.classList.remove('open');
        ov.setAttribute('aria-hidden', 'true');
    }

    function saveProfile() {
        clearNotice('profileError'); clearNotice('profileSuccess');
        var name = $('pName').value.trim();
        var email = $('pEmail').value.trim();
        if (!name) { showNotice('profileError', 'Name is required.'); $('pName').focus(); return; }
        if (!email) { showNotice('profileError', 'Email is required.'); $('pEmail').focus(); return; }

        $('btnSaveProfile').disabled = true;
        authPost('update_profile', {
            name: name,
            email: email,
            cell: $('pCell').value.trim(),
            home_office: $('pHomeOffice').value
        }).then(function (data) {
            $('btnSaveProfile').disabled = false;
            if (!data.ok) {
                showNotice('profileError', data.error || 'Could not save your profile.');
                return;
            }
            currentUser = data.user;
            // Reflect a name change in the header (name + avatar initials).
            updateAccountHeader(currentUser.name);
            showNotice('profileSuccess', 'Profile saved.');
        }).catch(function () {
            $('btnSaveProfile').disabled = false;
            showNotice('profileError', 'Network error saving your profile.');
        });
    }

    function savePassword() {
        clearNotice('pwError'); clearNotice('pwSuccess');
        var cur = $('pwCurrent').value;
        var nw = $('pwNew').value;
        var cf = $('pwConfirm').value;
        if (!cur) { showNotice('pwError', 'Enter your current password.'); $('pwCurrent').focus(); return; }
        if (nw.length < 8) { showNotice('pwError', 'New password must be at least 8 characters.'); $('pwNew').focus(); return; }
        if (nw !== cf) { showNotice('pwError', 'New passwords do not match.'); $('pwConfirm').focus(); return; }

        $('btnSavePassword').disabled = true;
        authPost('change_password', {
            current_password: cur,
            new_password: nw
        }).then(function (data) {
            $('btnSavePassword').disabled = false;
            if (!data.ok) {
                showNotice('pwError', data.error || 'Could not change your password.');
                return;
            }
            $('pwCurrent').value = ''; $('pwNew').value = ''; $('pwConfirm').value = '';
            showNotice('pwSuccess', data.message || 'Your password has been changed.');
        }).catch(function () {
            $('btnSavePassword').disabled = false;
            showNotice('pwError', 'Network error changing your password.');
        });
    }

    // Keep the header name + avatar initials in sync after a profile save.
    function updateAccountHeader(name) {
        name = (name || '').trim();
        if (!name) { return; }
        var nm = trigger.querySelector('.bs-account-name .nm');
        if (nm) { nm.textContent = name; }
        var parts = name.split(/\s+/);
        var ini = '';
        for (var i = 0; i < parts.length && ini.length < 2; i++) {
            if (parts[i]) { ini += parts[i].charAt(0).toUpperCase(); }
        }
        if (ini.length === 1 && name.length >= 2) { ini = name.substring(0, 2).toUpperCase(); }
        var av = trigger.querySelector('.bs-avatar');
        if (av && ini) { av.textContent = ini; }
    }

    $('menuProfile').addEventListener('click', openProfile);
    $('profileClose').addEventListener('click', closeProfile);
    $('btnProfileClose').addEventListener('click', closeProfile);
    $('btnSaveProfile').addEventListener('click', saveProfile);
    $('btnSavePassword').addEventListener('click', savePassword);
    // Dismiss the profile modal on backdrop click.
    $('profileOverlay').addEventListener('click', function (e) {
        if (e.target === $('profileOverlay')) { closeProfile(); }
    });

    // ---- Escape: close whatever is open (modal first, then menu) --------------
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape' && e.key !== 'Esc') { return; }
        if ($('profileOverlay').classList.contains('open')) { closeProfile(); }
        else if (menuOpen()) { closeMenu(); }
    });

    // ---- Add-application (admin only, inert for now) -------------------------
    // Future: replace this note with a "register a new app" flow that appends to
    // the app registry rendered above.
    var addTile = $('addAppTile');
    if (addTile) {
        addTile.addEventListener('click', function () {
            var note = $('addAppNote');
            if (note) { note.classList.add('show'); }
        });
    }
})();
</script>
</body>
</html>
