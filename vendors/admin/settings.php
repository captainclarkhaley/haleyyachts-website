<?php
/**
 * admin/settings.php - Broker Suite Settings editor (Phase 2a).
 *
 * View + edit the four non-secret suite_settings values from Phase 1
 * (site_base_url, mail_from_address, pocket_notify_to, doc_admin_email). These
 * are the environment / rollout knobs read at call time by the Pocket mailer,
 * the Pocket cron, and the vendor-document cron, each with the old hardcoded
 * literal as a fallback - so a blank value here can never break a send.
 *
 * SECURITY MODEL
 *   - Page + POST are gated by admin-guard.php: authenticated, password-current,
 *     is_admin === 1. A non-admin is redirected before any output and can never
 *     reach the save handler. The POST branch RE-CHECKS is_admin as defense in
 *     depth.
 *   - Only the four KNOWN keys are writable. The handler ignores any other
 *     field, so no arbitrary suite_settings key can be created from this form.
 *   - SMTP secrets are NOT in suite_settings and are never read, shown, or
 *     touched here - they live only in the untracked mail-secrets.php.
 *   - CSRF: a per-session token is issued and required on POST. Combined with the
 *     SameSite=Lax session cookie and the admin gate, this is a same-origin,
 *     admin-only write. (The rest of the suite relies on SameSite=Lax alone for
 *     its JSON POSTs; the token here is an added belt-and-suspenders for a
 *     browser form POST.)
 *   - Every value is escaped with htmlspecialchars on output.
 */

require_once __DIR__ . '/admin-guard.php';
// $pdo and $gateUser are now in scope, and $gateUser is guaranteed admin.

// ---------------------------------------------------------------------------
// The only keys this page is allowed to read or write. Anything not in this
// whitelist is ignored on save - no arbitrary suite_settings key can be added.
// The order here is the order the fields render.
// ---------------------------------------------------------------------------
$SETTING_KEYS = array('site_base_url', 'mail_from_address', 'pocket_notify_to', 'doc_admin_email');

$FIELD_META = array(
    'site_base_url' => array(
        'label' => 'Site base URL',
        'hint'  => 'Absolute base for links in emails and print sheets, e.g. https://haleyyachts.com. No trailing slash. This is the portability lever - it is what changes when the suite moves to its own domain.',
        'type'  => 'url',
    ),
    'mail_from_address' => array(
        'label' => 'Email From address',
        'hint'  => 'The From address suite emails are sent as, e.g. no-reply@haleyyachts.com.',
        'type'  => 'email',
    ),
    'pocket_notify_to' => array(
        'label' => 'Pocket Listings notification recipient',
        'hint'  => 'GO-LIVE SWITCH. New-listing and expiry-reminder emails from Pocket Listings go here. Right now it points to the single test inbox - changing this address is how you widen the audience or go live. One email address for now.',
        'type'  => 'email',
    ),
    'doc_admin_email' => array(
        'label' => 'Vendor document admin email',
        'hint'  => 'Recipient for vendor-document expiration reminders, e.g. admin@OWYG.com.',
        'type'  => 'email',
    ),
);

// ---- CSRF token (per session) ---------------------------------------------
if (empty($_SESSION['suite_admin_csrf'])) {
    $_SESSION['suite_admin_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['suite_admin_csrf'];

$errors  = array();   // key => message (field-level validation errors)
$notice  = '';        // top-level error banner text
$saved   = false;     // true after a successful save (drives the success banner)

/**
 * Read the four settings straight from the table (not suite_setting(), so blank
 * saved values show as blank in the form rather than resolving to a fallback).
 * Returns key => stored string, with '' for any missing key.
 */
$loadValues = function (PDO $pdo, array $keys) {
    $vals = array();
    foreach ($keys as $k) { $vals[$k] = ''; }
    $rows = $pdo->query('SELECT key, value FROM suite_settings')->fetchAll();
    foreach ($rows as $r) {
        $k = (string) $r['key'];
        if (array_key_exists($k, $vals)) {
            $vals[$k] = (string) $r['value'];
        }
    }
    return $vals;
};

// ===========================================================================
// SAVE HANDLER (POST)
// ===========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Defense in depth: re-verify admin inside the POST branch even though the
    // page prologue already gated. A non-admin session can never write here.
    $postIsAdmin = isset($gateUser['is_admin']) && (int) $gateUser['is_admin'] === 1;
    if (!$postIsAdmin) {
        header('Location: ../suite.php');
        exit;
    }

    // CSRF check: constant-time compare against the session token.
    $sent = isset($_POST['csrf']) ? (string) $_POST['csrf'] : '';
    if ($sent === '' || !hash_equals($csrf, $sent)) {
        $notice = 'Your session token did not match. Please reload the page and try again.';
    } else {

        // Collect + validate ONLY the whitelisted keys. Anything else in $_POST
        // is ignored - no arbitrary key can be introduced.
        $clean = array();
        foreach ($SETTING_KEYS as $key) {
            $raw  = isset($_POST[$key]) ? (string) $_POST[$key] : '';
            $val  = trim($raw);
            $type = $FIELD_META[$key]['type'];

            if ($type === 'email') {
                // Emails may be blank (a blank value falls back to the hardcoded
                // default at read time). If non-empty it must be a valid address.
                if ($val !== '' && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
                    $errors[$key] = 'Enter a valid email address, or leave blank to use the default.';
                    continue;
                }
                $clean[$key] = $val;
            } elseif ($type === 'url') {
                if ($val !== '') {
                    // Must be an absolute http(s) URL. Strip a single trailing slash.
                    if (stripos($val, 'http://') !== 0 && stripos($val, 'https://') !== 0) {
                        $errors[$key] = 'Must start with http:// or https://.';
                        continue;
                    }
                    $val = rtrim($val, '/');
                    // Belt-and-suspenders structural check on the trimmed URL.
                    if (!filter_var($val, FILTER_VALIDATE_URL)) {
                        $errors[$key] = 'That does not look like a valid URL.';
                        continue;
                    }
                }
                $clean[$key] = $val;
            }
        }

        if (empty($errors)) {
            // UPSERT each whitelisted key. INSERT ... ON CONFLICT(key) DO UPDATE
            // keeps this to one statement per key and can never touch a
            // non-whitelisted row. suite_settings.key is the PRIMARY KEY, so the
            // conflict target is valid.
            $stmt = $pdo->prepare(
                'INSERT INTO suite_settings (key, value) VALUES (:k, :v)
                 ON CONFLICT(key) DO UPDATE SET value = excluded.value'
            );
            $pdo->beginTransaction();
            try {
                foreach ($clean as $key => $value) {
                    $stmt->execute(array(':k' => $key, ':v' => $value));
                }
                $pdo->commit();
                $saved = true;
            } catch (Throwable $e) {
                $pdo->rollBack();
                $notice = 'Could not save the settings. Please try again.';
            }
        } else {
            $notice = 'Some values were not saved. Fix the highlighted fields and save again.';
        }
    }
}

// Values to render: the freshly-saved/attempted POST values on a POST (so the
// form keeps what the admin typed), otherwise the stored values.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values = array();
    foreach ($SETTING_KEYS as $key) {
        // On a successful save, prefer the cleaned (trailing-slash-stripped)
        // value so the form reflects exactly what was stored.
        if ($saved && isset($clean[$key])) {
            $values[$key] = $clean[$key];
        } else {
            $values[$key] = isset($_POST[$key]) ? trim((string) $_POST[$key]) : '';
        }
    }
} else {
    $values = $loadValues($pdo, $SETTING_KEYS);
}

$h = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Broker Suite Admin - Haley Yachts</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="../../favicon.ico" sizes="any">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Palette mirrors the suite launcher: navy #0a1628-ish + brand cyan. */
        :root {
            --navy: #0a1628;
            --navy-soft: #0c1f2e;
            --bg: #f5f7f9;
            --card: #ffffff;
            --ink: #1b2733;
            --muted: #64707c;
            --hair: rgba(20,40,60,0.10);
            --accent: #21cbea;
            --accent-text: #0e93b3;
            --accent-soft: rgba(33,203,234,0.12);
            --danger: #c0392b;
            --ok: #1b6e2e;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; }
        body {
            font-family: 'Open Sans', Arial, sans-serif;
            color: var(--ink);
            background: var(--bg);
            min-height: 100vh;
            line-height: 1.5;
        }
        .adm-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 18px 40px;
            background: var(--navy);
            color: #eef4f7;
        }
        .adm-topbar .brand {
            display: flex;
            align-items: center;
            gap: 18px;
        }
        .adm-topbar img { height: 34px; width: auto; display: block; }
        .adm-topbar .divider { width: 1px; height: 26px; background: rgba(238,244,247,0.2); }
        .adm-topbar .label {
            font-size: 11px;
            letter-spacing: .34em;
            color: var(--accent);
            font-weight: 600;
        }
        .adm-back {
            color: #cdd9e1;
            text-decoration: none;
            font-size: 13px;
            padding: 7px 14px;
            border: 1px solid rgba(238,244,247,0.18);
            border-radius: 999px;
        }
        .adm-back:hover { border-color: rgba(238,244,247,0.4); color: #fff; }

        .adm-wrap {
            max-width: 760px;
            margin: 0 auto;
            padding: 44px 24px 64px;
        }
        .adm-head h1 {
            margin: 0 0 6px;
            font-size: 30px;
            font-weight: 700;
            color: var(--navy);
        }
        .adm-head p { margin: 0 0 28px; color: var(--muted); font-size: 15px; max-width: 620px; }

        .adm-banner {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 22px;
        }
        .adm-banner.ok { background: #e8f7ea; border-left: 4px solid var(--ok); color: #14531f; }
        .adm-banner.err { background: #fdecea; border-left: 4px solid var(--danger); color: #7a241c; }

        .adm-card {
            background: var(--card);
            border: 1px solid var(--hair);
            border-radius: 14px;
            padding: 28px 28px 24px;
        }
        .adm-field { margin-bottom: 24px; }
        .adm-field:last-of-type { margin-bottom: 8px; }
        .adm-field label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--navy);
            margin-bottom: 7px;
        }
        .adm-field input[type="text"] {
            width: 100%;
            font-family: inherit;
            font-size: 15px;
            color: var(--ink);
            border: 1px solid var(--hair);
            border-radius: 8px;
            padding: 11px 13px;
            background: #fff;
        }
        .adm-field input[type="text"]:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-soft);
        }
        .adm-field input.has-error { border-color: var(--danger); }
        .adm-hint { margin: 7px 0 0; font-size: 12.5px; color: var(--muted); }
        .adm-hint.switch { color: var(--accent-text); font-weight: 600; }
        .adm-err { margin: 6px 0 0; font-size: 12.5px; color: var(--danger); font-weight: 600; }

        .adm-actions {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-top: 26px;
            padding-top: 22px;
            border-top: 1px solid var(--hair);
        }
        .adm-btn {
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid transparent;
            border-radius: 8px;
            padding: 11px 22px;
        }
        .adm-btn-primary { background: var(--accent); color: var(--navy); }
        .adm-btn-primary:hover { background: #17b6d4; }
        .adm-btn-ghost {
            background: transparent;
            color: var(--muted);
            border-color: var(--hair);
            text-decoration: none;
            display: inline-block;
        }
        .adm-btn-ghost:hover { border-color: var(--muted); color: var(--ink); }
        .adm-secret-note {
            margin-top: 22px;
            font-size: 12.5px;
            color: var(--muted);
            background: var(--bg);
            border: 1px solid var(--hair);
            border-radius: 8px;
            padding: 12px 14px;
        }
        @media (max-width: 640px) {
            .adm-topbar { padding: 16px 20px; }
            .adm-topbar .brand { gap: 12px; }
            .adm-wrap { padding: 32px 18px 52px; }
        }
    </style>
</head>
<body>

<header class="adm-topbar">
    <div class="brand">
        <img src="../../images/email/owyg-banner-reverse.png" alt="One Water Yacht Group">
        <span class="divider"></span>
        <span class="label">BROKER SUITE ADMIN</span>
    </div>
    <a class="adm-back" href="../suite.php">&larr; Back to Broker Suite</a>
</header>

<main class="adm-wrap">
    <div class="adm-head">
        <h1>Settings</h1>
        <p>Non-secret configuration for the suite. Blank a value to fall back to its built-in default. SMTP credentials are not stored here and cannot be edited from this screen.</p>
    </div>

    <?php if ($saved): ?>
        <div class="adm-banner ok">Settings saved.</div>
    <?php elseif ($notice !== ''): ?>
        <div class="adm-banner err"><?php echo $h($notice); ?></div>
    <?php endif; ?>

    <form method="post" action="settings.php" autocomplete="off" novalidate>
        <input type="hidden" name="csrf" value="<?php echo $h($csrf); ?>">

        <div class="adm-card">
            <?php foreach ($SETTING_KEYS as $key):
                $meta   = $FIELD_META[$key];
                $val    = isset($values[$key]) ? $values[$key] : '';
                $errMsg = isset($errors[$key]) ? $errors[$key] : '';
                $isSwitch = ($key === 'pocket_notify_to');
            ?>
            <div class="adm-field">
                <label for="f_<?php echo $h($key); ?>"><?php echo $h($meta['label']); ?></label>
                <input type="text"
                       id="f_<?php echo $h($key); ?>"
                       name="<?php echo $h($key); ?>"
                       value="<?php echo $h($val); ?>"
                       class="<?php echo $errMsg !== '' ? 'has-error' : ''; ?>"
                       spellcheck="false"
                       autocapitalize="off">
                <?php if ($errMsg !== ''): ?>
                    <p class="adm-err"><?php echo $h($errMsg); ?></p>
                <?php endif; ?>
                <p class="adm-hint<?php echo $isSwitch ? ' switch' : ''; ?>"><?php echo $h($meta['hint']); ?></p>
            </div>
            <?php endforeach; ?>

            <div class="adm-actions">
                <button type="submit" class="adm-btn adm-btn-primary">Save settings</button>
                <a class="adm-btn adm-btn-ghost" href="../suite.php">Cancel</a>
            </div>

            <p class="adm-secret-note">SMTP host, port, username, and password are secrets. They live in the untracked mail-secrets.php and are never shown or editable here.</p>
        </div>
    </form>
</main>

</body>
</html>
