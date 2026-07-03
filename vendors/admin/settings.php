<?php
/**
 * admin/settings.php - Yacht Broker Support Settings editor.
 *
 * Two concerns on one admin-gated screen:
 *   1. ENVIRONMENT (Phase 1/2a): the non-secret rollout knobs (site_base_url,
 *      mail_from_address, pocket_notify_to, doc_admin_email) read at call time by
 *      the mailers/crons, each with the old hardcoded literal as a fallback.
 *   2. BRANDING (white-label phase): identity (brand/tenant/login copy), colors
 *      (emitted as CSS custom properties by branding.php), the company contact
 *      block (used on print sheets, email footers, and page footers), and the
 *      uploaded logo / footer logo / favicon.
 *
 * Every branding value is seeded in db.php with today's OWYG value, so a blank
 * row falls back to the current look and nothing changes for OWYG.
 *
 * SECURITY MODEL
 *   - Page + POST are gated by admin-guard.php: authenticated, password-current,
 *     is_admin === 1. The POST branch RE-CHECKS is_admin as defense in depth.
 *   - Only the KNOWN keys are writable (a strict whitelist). Any other field is
 *     ignored, so no arbitrary suite_settings key can be created from this form.
 *   - Uploads are validated by ACTUAL image content (getimagesize), stored under
 *     a random name in the gitignored uploads/branding dir, and only the
 *     resulting site-relative path is written to settings.
 *   - SMTP secrets are NOT in suite_settings and are never shown or touched here.
 *   - CSRF: a per-session token is required on POST.
 *   - Every value is escaped with htmlspecialchars on output.
 */

require_once __DIR__ . '/admin-guard.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/modules.php';
// $pdo, $gateUser (admin), $brandName, $tenantName, $logoUrl, $faviconUrl in scope.

// ---------------------------------------------------------------------------
// Text/URL/email/color settings this page may read or write. The order here is
// the render order within each section. Uploads (logo/favicon) are handled
// separately, below, because they are files, not text fields.
// ---------------------------------------------------------------------------
$FIELD_META = array(
    // --- Environment ---
    'site_base_url' => array(
        'section' => 'env',
        'label'   => 'Site base URL',
        'hint'    => 'Absolute base for links in emails and print sheets, e.g. https://owyg.yachtbrokersupport.com. No trailing slash. This is the portability lever, it is what changes when the suite moves to its own domain.',
        'type'    => 'url',
    ),
    'mail_from_address' => array(
        'section' => 'env',
        'label'   => 'Email From address',
        'hint'    => 'The From address suite emails are sent as, e.g. no-reply@owyg.yachtbrokersupport.com.',
        'type'    => 'email',
    ),
    'pocket_notify_to' => array(
        'section' => 'env',
        'label'   => 'Pocket Listings notification recipient (single address)',
        'hint'    => 'Every Pocket Listings new-listing and expiry-reminder email is sent to this ONE address. To reach all brokers, set this to a distribution-list address that forwards to the group. Left blank, it falls back to the built-in test inbox, not the network.',
        'type'    => 'email',
    ),
    'doc_admin_email' => array(
        'section' => 'env',
        'label'   => 'Vendor document admin email',
        'hint'    => 'Recipient for vendor-document expiration reminders, e.g. admin@OWYG.com.',
        'type'    => 'email',
    ),

    // --- Identity ---
    'brand_name' => array(
        'section' => 'identity',
        'label'   => 'Product / suite display name',
        'hint'    => 'The product-first wordmark shown across the suite, e.g. Yacht Broker Support.',
        'type'    => 'text',
    ),
    'tenant_name' => array(
        'section' => 'identity',
        'label'   => 'Tenant / organization name',
        'hint'    => 'The organization shown as the secondary mark and in footers, e.g. One Water Yacht Group.',
        'type'    => 'text',
    ),
    'login_title' => array(
        'section' => 'identity',
        'label'   => 'Login screen title',
        'hint'    => 'Heading shown on the sign-in screen. Falls back to the product name when blank.',
        'type'    => 'text',
    ),
    'login_tagline' => array(
        'section' => 'identity',
        'label'   => 'Login screen tagline',
        'hint'    => 'A short line under the login title, e.g. "Yacht Broker Support - staff sign in".',
        'type'    => 'text',
    ),

    // --- Colors ---
    'header_color' => array(
        'section' => 'colors',
        'label'   => 'Header color',
        'hint'    => 'The masthead / navy base color.',
        'type'    => 'color',
        'default' => '#0a1628',
    ),
    'brand_color' => array(
        'section' => 'colors',
        'label'   => 'Brand / accent color',
        'hint'    => 'The primary accent (buttons, keylines, highlights).',
        'type'    => 'color',
        'default' => '#21cbea',
    ),
    'accent_color' => array(
        'section' => 'colors',
        'label'   => 'Accent (dark) color',
        'hint'    => 'The darker accent used for hovers and links.',
        'type'    => 'color',
        'default' => '#1aa8c4',
    ),

    // --- Company contact block ---
    'company_name' => array(
        'section' => 'contact',
        'label'   => 'Company name',
        'hint'    => 'Shown on print sheets, email footers, and page footers.',
        'type'    => 'text',
    ),
    'company_address' => array(
        'section' => 'contact',
        'label'   => 'Company address',
        'hint'    => 'Optional. One line, e.g. 123 Marina Way, Fort Lauderdale, FL 33316.',
        'type'    => 'text',
    ),
    'company_phone' => array(
        'section' => 'contact',
        'label'   => 'Company phone',
        'hint'    => 'Optional. Shown in the contact block.',
        'type'    => 'text',
    ),
    'company_email' => array(
        'section' => 'contact',
        'label'   => 'Company email',
        'hint'    => 'Optional. Shown in the contact block.',
        'type'    => 'email',
    ),
);
$SETTING_KEYS = array_keys($FIELD_META);

// Upload fields: key => [label, hint, default path]. Each stores a site-relative
// path in the named setting; blank falls back to the committed OWYG banner.
$UPLOAD_META = array(
    'logo_path' => array(
        'label'   => 'Header logo',
        'hint'    => 'PNG, JPG, or WEBP. Shown on the masthead of every page and at the top of emails. Ideally a reverse (light-on-dark) logo, since it sits on the navy header.',
        'default' => '/images/email/owyg-banner-reverse.png',
    ),
    'footer_logo_path' => array(
        'label'   => 'Footer logo',
        'hint'    => 'PNG, JPG, or WEBP. Shown in email footers. Leave unset to reuse the header logo look.',
        'default' => '/images/email/owyg-banner-reverse.png',
    ),
    'favicon_path' => array(
        'label'   => 'Favicon',
        'hint'    => 'ICO, PNG, or SVG. The little icon in the browser tab.',
        'default' => '/favicon.ico',
    ),
);

// ---- CSRF token (per session) ---------------------------------------------
if (empty($_SESSION['suite_admin_csrf'])) {
    $_SESSION['suite_admin_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['suite_admin_csrf'];

$errors = array();   // key => message (field-level validation errors)
$notice = '';        // top-level error banner text
$saved  = false;     // true after a successful save

/**
 * Read the settings straight from the table (not suite_setting(), so a blank
 * saved value shows as blank in the form rather than resolving to a fallback).
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

/**
 * Validate + store one uploaded brand image. Returns the site-relative path on
 * success, throws RuntimeException (message shown to the admin) on any problem.
 * Type is decided by actual content, not the client name/MIME. ICO is accepted
 * for the favicon (validated by extension + a light signature check, since
 * getimagesize does not read .ico on all builds); images go through getimagesize.
 */
$storeBrandImage = function (array $file, $allowIco) {
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // no file chosen for this field
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed. Please try a smaller file.');
    }
    if ($file['size'] <= 0) {
        throw new RuntimeException('The file was empty.');
    }
    if ($file['size'] > 4 * 1024 * 1024) {
        throw new RuntimeException('The file is too large (max 4 MB).');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Invalid upload.');
    }

    $ext  = strtolower((string) pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    $info = @getimagesize($file['tmp_name']);

    if ($info !== false) {
        $type = $info[2];
        switch ($type) {
            case IMAGETYPE_JPEG: $ext = 'jpg';  break;
            case IMAGETYPE_PNG:  $ext = 'png';  break;
            case IMAGETYPE_WEBP: $ext = 'webp'; break;
            case IMAGETYPE_GIF:  $ext = 'gif';  break;
            default:
                // getimagesize recognized something we do not allow.
                throw new RuntimeException('Use a PNG, JPG, WEBP, SVG, or ICO file.');
        }
    } else {
        // getimagesize could not read it: allow SVG (text) and, for the favicon,
        // ICO, validated by extension. Everything else is rejected.
        if ($ext === 'svg') {
            $head = (string) @file_get_contents($file['tmp_name'], false, null, 0, 512);
            if (stripos($head, '<svg') === false) {
                throw new RuntimeException('That does not look like a valid SVG.');
            }
        } elseif ($allowIco && ($ext === 'ico')) {
            // .ico header: 00 00 01 00 (reserved + type=icon).
            $sig = (string) @file_get_contents($file['tmp_name'], false, null, 0, 4);
            if (strlen($sig) < 4 || $sig[0] !== "\x00" || $sig[1] !== "\x00" || $sig[2] !== "\x01") {
                throw new RuntimeException('That does not look like a valid .ico file.');
            }
            $ext = 'ico';
        } else {
            throw new RuntimeException('Use a PNG, JPG, WEBP, SVG, or ICO file.');
        }
    }

    $dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/branding';
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }

    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Could not store the uploaded file.');
    }
    @chmod($dest, 0644);
    return '/uploads/branding/' . $name;
};

// ===========================================================================
// SAVE HANDLER (POST)
// ===========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Defense in depth: re-verify admin inside the POST branch.
    $postIsAdmin = isset($gateUser['is_admin']) && (int) $gateUser['is_admin'] === 1;
    if (!$postIsAdmin) {
        header('Location: ../suite.php');
        exit;
    }

    $sent = isset($_POST['csrf']) ? (string) $_POST['csrf'] : '';
    if ($sent === '' || !hash_equals($csrf, $sent)) {
        $notice = 'Your session token did not match. Please reload the page and try again.';
    } else {

        // --- text/url/email/color fields (whitelist only) ---
        $clean = array();
        foreach ($SETTING_KEYS as $key) {
            $raw  = isset($_POST[$key]) ? (string) $_POST[$key] : '';
            $val  = trim($raw);
            $type = $FIELD_META[$key]['type'];

            if ($type === 'email') {
                if ($val !== '' && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
                    $errors[$key] = 'Enter a valid email address, or leave blank to use the default.';
                    continue;
                }
                $clean[$key] = $val;
            } elseif ($type === 'url') {
                if ($val !== '') {
                    if (stripos($val, 'http://') !== 0 && stripos($val, 'https://') !== 0) {
                        $errors[$key] = 'Must start with http:// or https://.';
                        continue;
                    }
                    $val = rtrim($val, '/');
                    if (!filter_var($val, FILTER_VALIDATE_URL)) {
                        $errors[$key] = 'That does not look like a valid URL.';
                        continue;
                    }
                }
                $clean[$key] = $val;
            } elseif ($type === 'color') {
                // A native color input always sends #rrggbb, but validate anyway so
                // a crafted POST cannot store junk that lands in the :root block.
                if ($val !== '' && !preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $val)) {
                    $errors[$key] = 'Enter a hex color like #21cbea, or leave blank for the default.';
                    continue;
                }
                $clean[$key] = ($val === '') ? '' : strtolower($val);
            } else { // text
                $clean[$key] = $val;
            }
        }

        // --- uploads (logo, footer logo, favicon) ---
        // A "reset to default" checkbox per upload blanks the stored path so the
        // fallback applies. Otherwise a newly uploaded file replaces the path; if
        // neither is set, the existing stored value is kept untouched.
        $uploadClean = array();   // key => new path OR '' (reset), missing = keep
        foreach ($UPLOAD_META as $key => $meta) {
            $resetFlag = isset($_POST['reset_' . $key]);
            $file = isset($_FILES[$key]) ? $_FILES[$key] : null;
            $hasFile = $file && isset($file['error']) && $file['error'] !== UPLOAD_ERR_NO_FILE;

            if ($resetFlag && !$hasFile) {
                $uploadClean[$key] = ''; // blank -> fall back to default
                continue;
            }
            if ($hasFile) {
                try {
                    $path = $storeBrandImage($file, $key === 'favicon_path');
                    if ($path !== null) { $uploadClean[$key] = $path; }
                } catch (RuntimeException $e) {
                    $errors[$key] = $e->getMessage();
                }
            }
            // else: no file + no reset -> leave the stored value as-is.
        }

        // --- module enablement (whitelist from the registry) ---
        // Each module posts <setting_key> = one of live|admin|soon|hidden. An
        // unknown key or an invalid value is rejected (defense against a crafted
        // POST); a valid value is stored verbatim.
        $moduleClean = array();   // setting_key => state
        $validStates = array_keys(module_states());
        foreach (module_registry() as $mKey => $mMeta) {
            $sk = $mMeta['setting_key'];
            if (!isset($_POST[$sk])) { continue; }
            $mv = strtolower(trim((string) $_POST[$sk]));
            if (!in_array($mv, $validStates, true)) {
                $errors[$sk] = 'Choose a valid module state.';
                continue;
            }
            $moduleClean[$sk] = $mv;
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare(
                'INSERT INTO suite_settings (key, value) VALUES (:k, :v)
                 ON CONFLICT(key) DO UPDATE SET value = excluded.value'
            );
            $pdo->beginTransaction();
            try {
                foreach ($clean as $key => $value) {
                    $stmt->execute(array(':k' => $key, ':v' => $value));
                }
                foreach ($uploadClean as $key => $value) {
                    $stmt->execute(array(':k' => $key, ':v' => $value));
                }
                foreach ($moduleClean as $key => $value) {
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

// Values to render.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values = array();
    foreach ($SETTING_KEYS as $key) {
        if ($saved && isset($clean[$key])) {
            $values[$key] = $clean[$key];
        } else {
            $values[$key] = isset($_POST[$key]) ? trim((string) $_POST[$key]) : '';
        }
    }
    $uploadValues = $loadValues($pdo, array_keys($UPLOAD_META));
    if ($saved) {
        foreach ($uploadClean as $k => $v) { $uploadValues[$k] = $v; }
    }
} else {
    $values       = $loadValues($pdo, $SETTING_KEYS);
    $uploadValues = $loadValues($pdo, array_keys($UPLOAD_META));
}

$h = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };

// Resolve each upload's effective preview src (stored value or its default).
$uploadPreview = function ($key) use ($uploadValues, $UPLOAD_META) {
    $v = isset($uploadValues[$key]) ? trim((string) $uploadValues[$key]) : '';
    return ($v !== '') ? $v : $UPLOAD_META[$key]['default'];
};

// Effective selected state per module for rendering the dropdowns. On a failed
// POST we echo back what the admin picked (from $_POST) so their selection is
// preserved; otherwise we show the currently resolved state (module_state()
// already falls back to the registry default for a blank/invalid row).
$moduleRegistry = module_registry();
$moduleStateOptions = module_states();
$moduleSelected = array();
foreach ($moduleRegistry as $mKey => $mMeta) {
    $sk = $mMeta['setting_key'];
    if ($saved && isset($moduleClean[$sk])) {
        // Just-saved value (the per-request suite_setting cache is stale here).
        $moduleSelected[$mKey] = $moduleClean[$sk];
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !$saved && isset($_POST[$sk])) {
        // Failed POST: echo back the admin's pick so it is not lost.
        $sel = strtolower(trim((string) $_POST[$sk]));
        $moduleSelected[$mKey] = array_key_exists($sel, $moduleStateOptions)
            ? $sel : module_state($pdo, $mKey);
    } else {
        $moduleSelected[$mKey] = module_state($pdo, $mKey);
    }
}

$sectionOrder = array(
    'identity' => 'Identity',
    'colors'   => 'Colors',
    'contact'  => 'Company contact',
    'env'      => 'Environment',
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Admin - <?php echo $h($brandName); ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="<?php echo $h($faviconUrl); ?>" sizes="any">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Open+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
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
        .adm-topbar .brand { display: flex; align-items: center; gap: 18px; }
        .adm-topbar img { height: 34px; width: auto; display: block; }
        .adm-topbar .wordmark { display: flex; flex-direction: column; gap: 4px; }
        .adm-topbar .wordmark .wm-name {
            font-family: 'Cormorant Garamond', Georgia, serif;
            font-size: 22px; line-height: 1; font-weight: 600; letter-spacing: .01em; color: #fff;
        }
        .adm-topbar .wordmark img { height: 16px; opacity: .82; }
        .adm-topbar .divider { width: 1px; height: 26px; background: rgba(238,244,247,0.2); }
        .adm-topbar .label {
            font-size: 11px; letter-spacing: .34em; color: var(--accent); font-weight: 600;
        }
        .adm-back {
            color: #cdd9e1; text-decoration: none; font-size: 13px;
            padding: 7px 14px; border: 1px solid rgba(238,244,247,0.18); border-radius: 999px;
        }
        .adm-back:hover { border-color: rgba(238,244,247,0.4); color: #fff; }

        .adm-wrap { max-width: 760px; margin: 0 auto; padding: 44px 24px 64px; }
        .adm-head h1 { margin: 0 0 6px; font-size: 30px; font-weight: 700; color: var(--navy); }
        .adm-head p { margin: 0 0 28px; color: var(--muted); font-size: 15px; max-width: 620px; }

        .adm-banner { padding: 12px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 22px; }
        .adm-banner.ok { background: #e8f7ea; border-left: 4px solid var(--ok); color: #14531f; }
        .adm-banner.err { background: #fdecea; border-left: 4px solid var(--danger); color: #7a241c; }

        .adm-card {
            background: var(--card); border: 1px solid var(--hair);
            border-radius: 14px; padding: 24px 28px 20px; margin-bottom: 20px;
        }
        .adm-card > h2 {
            margin: 0 0 4px; font-size: 13px; font-weight: 700; letter-spacing: .12em;
            text-transform: uppercase; color: var(--accent-text);
        }
        .adm-card > .adm-card-sub { margin: 0 0 20px; font-size: 13px; color: var(--muted); }

        .adm-field { margin-bottom: 22px; }
        .adm-field:last-child { margin-bottom: 4px; }
        .adm-field label {
            display: block; font-size: 12px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .06em; color: var(--navy); margin-bottom: 7px;
        }
        .adm-field input[type="text"],
        .adm-field input[type="url"],
        .adm-field input[type="email"] {
            width: 100%; font-family: inherit; font-size: 15px; color: var(--ink);
            border: 1px solid var(--hair); border-radius: 8px; padding: 11px 13px; background: #fff;
        }
        .adm-field select.adm-select {
            width: 100%; font-family: inherit; font-size: 15px; color: var(--ink);
            border: 1px solid var(--hair); border-radius: 8px; padding: 11px 13px; background: #fff;
        }
        .adm-field input:focus, .adm-field select.adm-select:focus {
            outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft);
        }
        .adm-field input.has-error, .adm-field select.has-error { border-color: var(--danger); }
        .adm-hint { margin: 7px 0 0; font-size: 12.5px; color: var(--muted); }
        .adm-err { margin: 6px 0 0; font-size: 12.5px; color: var(--danger); font-weight: 600; }

        /* Color rows */
        .adm-color-row { display: flex; align-items: center; gap: 12px; }
        .adm-color-row input[type="color"] {
            width: 48px; height: 40px; padding: 0; border: 1px solid var(--hair);
            border-radius: 8px; background: #fff; cursor: pointer;
        }
        .adm-color-row .adm-color-hex {
            font-family: 'Open Sans', monospace; font-size: 14px; color: var(--muted);
            border: 1px solid var(--hair); border-radius: 8px; padding: 9px 12px; width: 120px;
        }
        .adm-color-row .adm-reset {
            font-size: 12px; color: var(--accent-text); background: none; border: none;
            cursor: pointer; text-decoration: underline; padding: 0;
        }

        /* Upload rows */
        .adm-upload { display: flex; gap: 18px; align-items: flex-start; }
        .adm-upload .adm-preview {
            flex: none; width: 200px; min-height: 60px; border: 1px solid var(--hair);
            border-radius: 8px; background: var(--navy); display: grid; place-items: center;
            padding: 12px; overflow: hidden;
        }
        .adm-upload .adm-preview.favicon { background: var(--bg); width: 92px; }
        .adm-upload .adm-preview img { max-width: 100%; max-height: 52px; display: block; }
        .adm-upload .adm-preview.favicon img { max-height: 40px; }
        .adm-upload .adm-upload-controls { flex: 1; min-width: 0; }
        .adm-upload input[type="file"] { font-size: 13px; }
        .adm-upload .adm-reset-line { margin-top: 10px; font-size: 12.5px; color: var(--muted); }
        .adm-upload .adm-reset-line input { margin-right: 6px; }

        .adm-actions {
            display: flex; align-items: center; gap: 14px; margin-top: 6px;
            padding: 20px 0 4px;
        }
        .adm-btn {
            font-family: inherit; font-size: 14px; font-weight: 600; cursor: pointer;
            border: 1px solid transparent; border-radius: 8px; padding: 11px 22px;
        }
        .adm-btn-primary { background: var(--accent); color: var(--navy); }
        .adm-btn-primary:hover { background: #17b6d4; }
        .adm-btn-ghost {
            background: transparent; color: var(--muted); border-color: var(--hair);
            text-decoration: none; display: inline-block;
        }
        .adm-btn-ghost:hover { border-color: var(--muted); color: var(--ink); }
        .adm-secret-note {
            margin-top: 8px; font-size: 12.5px; color: var(--muted); background: var(--bg);
            border: 1px solid var(--hair); border-radius: 8px; padding: 12px 14px;
        }
        @media (max-width: 640px) {
            .adm-topbar { padding: 16px 20px; }
            .adm-wrap { padding: 32px 18px 52px; }
            .adm-upload { flex-direction: column; }
        }
    </style>
    <?php suite_theme_head($pdo); // config-driven :root color override, must follow the page style block ?>
</head>
<body>

<header class="adm-topbar">
    <div class="brand">
        <div class="wordmark">
            <span class="wm-name"><?php echo $h($brandName); ?></span>
            <img src="<?php echo $h($logoUrl); ?>" alt="<?php echo $h($tenantName); ?>">
        </div>
        <span class="divider"></span>
        <span class="label">ADMIN</span>
    </div>
    <a class="adm-back" href="../suite.php">&larr; Back to Menu</a>
</header>

<main class="adm-wrap">
    <div class="adm-head">
        <h1>Settings</h1>
        <p>Branding and non-secret configuration for the suite. Blank a value to fall back to its built-in default. SMTP credentials are not stored here and cannot be edited from this screen.</p>
    </div>

    <?php if ($saved): ?>
        <div class="adm-banner ok">Settings saved.</div>
    <?php elseif ($notice !== ''): ?>
        <div class="adm-banner err"><?php echo $h($notice); ?></div>
    <?php endif; ?>

    <form method="post" action="settings.php" autocomplete="off" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf" value="<?php echo $h($csrf); ?>">

        <?php foreach ($sectionOrder as $sectionKey => $sectionLabel):
            $keysInSection = array();
            foreach ($SETTING_KEYS as $k) {
                if ($FIELD_META[$k]['section'] === $sectionKey) { $keysInSection[] = $k; }
            }
        ?>
        <div class="adm-card">
            <h2><?php echo $h($sectionLabel); ?></h2>
            <?php foreach ($keysInSection as $key):
                $meta   = $FIELD_META[$key];
                $val    = isset($values[$key]) ? $values[$key] : '';
                $errMsg = isset($errors[$key]) ? $errors[$key] : '';
                $type   = $meta['type'];
            ?>
            <div class="adm-field">
                <label for="f_<?php echo $h($key); ?>"><?php echo $h($meta['label']); ?></label>
                <?php if ($type === 'color'):
                    $def = isset($meta['default']) ? $meta['default'] : '#000000';
                    $current = ($val !== '') ? $val : $def;
                ?>
                    <div class="adm-color-row">
                        <input type="color"
                               id="f_<?php echo $h($key); ?>"
                               value="<?php echo $h($current); ?>"
                               data-target="hex_<?php echo $h($key); ?>"
                               data-default="<?php echo $h($def); ?>">
                        <input type="text"
                               id="hex_<?php echo $h($key); ?>"
                               name="<?php echo $h($key); ?>"
                               class="adm-color-hex<?php echo $errMsg !== '' ? ' has-error' : ''; ?>"
                               value="<?php echo $h($val); ?>"
                               placeholder="<?php echo $h($def); ?>"
                               spellcheck="false" autocapitalize="off">
                        <button type="button" class="adm-reset" data-reset-color="<?php echo $h($key); ?>" data-default="<?php echo $h($def); ?>">Reset to default</button>
                    </div>
                <?php else: ?>
                    <input type="<?php echo $type === 'url' ? 'url' : ($type === 'email' ? 'email' : 'text'); ?>"
                           id="f_<?php echo $h($key); ?>"
                           name="<?php echo $h($key); ?>"
                           value="<?php echo $h($val); ?>"
                           class="<?php echo $errMsg !== '' ? 'has-error' : ''; ?>"
                           spellcheck="false" autocapitalize="off">
                <?php endif; ?>
                <?php if ($errMsg !== ''): ?>
                    <p class="adm-err"><?php echo $h($errMsg); ?></p>
                <?php endif; ?>
                <p class="adm-hint"><?php echo $h($meta['hint']); ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>

        <!-- ===== Modules ===== -->
        <div class="adm-card">
            <h2>Modules</h2>
            <p class="adm-card-sub">Control which apps appear on the launcher and who can open them. Each state is enforced on the launcher tile AND on the module's own page, so a user who is not permitted cannot reach it by typing the URL. Live: everyone. Admin only: only in-app admins (others see it as coming soon). Coming soon: shown but disabled for everyone. Hidden: no tile at all.</p>
            <?php foreach ($moduleRegistry as $mKey => $mMeta):
                $sk     = $mMeta['setting_key'];
                $selVal = isset($moduleSelected[$mKey]) ? $moduleSelected[$mKey] : $mMeta['default_state'];
                $errMsg = isset($errors[$sk]) ? $errors[$sk] : '';
            ?>
            <div class="adm-field">
                <label for="f_<?php echo $h($sk); ?>"><?php echo $h($mMeta['name']); ?></label>
                <select id="f_<?php echo $h($sk); ?>" name="<?php echo $h($sk); ?>"
                        class="adm-select<?php echo $errMsg !== '' ? ' has-error' : ''; ?>">
                    <?php foreach ($moduleStateOptions as $stateKey => $stateLabel): ?>
                    <option value="<?php echo $h($stateKey); ?>"<?php echo $selVal === $stateKey ? ' selected' : ''; ?>>
                        <?php echo $h($stateLabel); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($errMsg !== ''): ?>
                    <p class="adm-err"><?php echo $h($errMsg); ?></p>
                <?php endif; ?>
                <p class="adm-hint"><?php echo $h($mMeta['desc']); ?></p>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ===== Logo & favicon uploads ===== -->
        <div class="adm-card">
            <h2>Logo &amp; favicon</h2>
            <p class="adm-card-sub">Upload the tenant's brand images. Leave a field empty to keep the current image; tick "reset to default" to fall back to the built-in mark.</p>
            <?php foreach ($UPLOAD_META as $key => $meta):
                $errMsg = isset($errors[$key]) ? $errors[$key] : '';
                $isFav  = ($key === 'favicon_path');
                $accept = $isFav ? 'image/png,image/x-icon,image/vnd.microsoft.icon,.ico,image/svg+xml'
                                 : 'image/png,image/jpeg,image/webp,image/svg+xml';
            ?>
            <div class="adm-field">
                <label><?php echo $h($meta['label']); ?></label>
                <div class="adm-upload">
                    <div class="adm-preview<?php echo $isFav ? ' favicon' : ''; ?>">
                        <img src="<?php echo $h($uploadPreview($key)); ?>" alt="Current <?php echo $h($meta['label']); ?>">
                    </div>
                    <div class="adm-upload-controls">
                        <input type="file" name="<?php echo $h($key); ?>" accept="<?php echo $h($accept); ?>">
                        <?php if ($errMsg !== ''): ?>
                            <p class="adm-err"><?php echo $h($errMsg); ?></p>
                        <?php endif; ?>
                        <p class="adm-hint"><?php echo $h($meta['hint']); ?></p>
                        <label class="adm-reset-line">
                            <input type="checkbox" name="reset_<?php echo $h($key); ?>" value="1">
                            Reset to the built-in default
                        </label>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="adm-actions">
            <button type="submit" class="adm-btn adm-btn-primary">Save settings</button>
            <a class="adm-btn adm-btn-ghost" href="../suite.php">Cancel</a>
        </div>

        <p class="adm-secret-note">SMTP host, port, username, and password are secrets. They live in the untracked mail-secrets.php and are never shown or editable here.</p>
    </form>
</main>

<script>
(function () {
    'use strict';
    // Keep the color picker and its hex text field in sync, both directions.
    var pickers = document.querySelectorAll('input[type="color"][data-target]');
    for (var i = 0; i < pickers.length; i++) {
        (function (picker) {
            var hex = document.getElementById(picker.getAttribute('data-target'));
            picker.addEventListener('input', function () {
                if (hex) { hex.value = picker.value; }
            });
            if (hex) {
                hex.addEventListener('input', function () {
                    var v = hex.value.trim();
                    if (/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(v)) { picker.value = v; }
                });
            }
        })(pickers[i]);
    }
    // "Reset to default" for a color blanks the hex field (so the fallback
    // applies on save) and shows the default in the swatch.
    var resets = document.querySelectorAll('[data-reset-color]');
    for (var j = 0; j < resets.length; j++) {
        (function (btn) {
            btn.addEventListener('click', function () {
                var key = btn.getAttribute('data-reset-color');
                var def = btn.getAttribute('data-default') || '#000000';
                var hex = document.getElementById('hex_' + key);
                var picker = document.getElementById('f_' + key);
                if (hex) { hex.value = ''; }
                if (picker) { picker.value = def; }
            });
        })(resets[j]);
    }
})();
</script>

</body>
</html>
