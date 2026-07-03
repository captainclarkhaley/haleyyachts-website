<?php
/**
 * branding.php - shared, config-driven branding + theming helpers.
 *
 * The suite is white-label: brand name, colors, logo, favicon, login copy, and
 * the company contact block are all suite_settings rows (seeded in db.php with
 * TODAY's OWYG values). This file is the ONE place that resolves those settings
 * into ready-to-use values and emits the theming markup, so no page hardcodes a
 * hex color or the OWYG logo path any more.
 *
 * Every getter passes the current OWYG literal as the suite_setting() fallback,
 * so a missing or blank row renders exactly like the pre-branding suite. Nothing
 * changes for OWYG; everything simply BECOMES configurable.
 *
 * Requires vendors/api/db.php (for suite_setting()) already loaded, and a live
 * $pdo. Include with:
 *     require_once $_SERVER['DOCUMENT_ROOT'] . '/api/branding.php';
 *
 * Web pages: call suite_theme_head($pdo) inside <head> AFTER the page's own
 * <style>/<link> so the :root override wins. Use suite_logo_url($pdo) /
 * suite_favicon_url($pdo) for image src. Emails: use suite_logo_abs($pdo) which
 * prefixes site_base_url (mail clients cannot resolve site-relative paths).
 */

if (!function_exists('suite_branding')) {

    /**
     * Resolve the full branding set once per request (statically cached).
     * Returns an associative array of already-resolved values, each falling back
     * to the current OWYG literal when the setting is blank/missing.
     *
     * @param PDO $pdo
     * @return array
     */
    function suite_branding(PDO $pdo)
    {
        static $b = null;
        if ($b !== null) {
            return $b;
        }
        $b = array(
            // Names.
            'brand_name'   => suite_setting($pdo, 'brand_name', 'Yacht Broker Support'),
            'tenant_name'  => suite_setting($pdo, 'tenant_name', 'One Water Yacht Group'),
            'company_name' => suite_setting($pdo, 'company_name', 'One Water Yacht Group'),

            // Images (site-relative).
            'logo_path'        => suite_setting($pdo, 'logo_path', '/images/email/owyg-banner-reverse.png'),
            'footer_logo_path' => suite_setting($pdo, 'footer_logo_path', '/images/email/owyg-banner-reverse.png'),
            'favicon_path'     => suite_setting($pdo, 'favicon_path', '/favicon.ico'),

            // Login copy.
            'login_title'   => suite_setting($pdo, 'login_title', 'Yacht Broker Support'),
            'login_tagline' => suite_setting($pdo, 'login_tagline', 'Yacht Broker Support - staff sign in'),

            // Colors.
            'header_color' => suite_setting($pdo, 'header_color', '#0a1628'),
            'brand_color'  => suite_setting($pdo, 'brand_color', '#21cbea'),
            'accent_color' => suite_setting($pdo, 'accent_color', '#1aa8c4'),

            // Company contact block.
            'company_address' => suite_setting($pdo, 'company_address', ''),
            'company_phone'   => suite_setting($pdo, 'company_phone', ''),
            'company_email'   => suite_setting($pdo, 'company_email', ''),
        );
        return $b;
    }

    /**
     * Sanitize a stored color to a safe CSS hex, or return the fallback. Accepts
     * #rgb / #rrggbb (case-insensitive). Anything else falls back so a bad stored
     * value can never inject into the emitted :root block.
     */
    function suite_color(PDO $pdo, $key, $fallback)
    {
        $val = suite_setting($pdo, $key, $fallback);
        $val = trim((string) $val);
        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $val)) {
            return $val;
        }
        return $fallback;
    }

    /** Site-relative header logo URL. */
    function suite_logo_url(PDO $pdo)
    {
        $b = suite_branding($pdo);
        return $b['logo_path'];
    }

    /** Site-relative footer logo URL. */
    function suite_footer_logo_url(PDO $pdo)
    {
        $b = suite_branding($pdo);
        return $b['footer_logo_path'];
    }

    /** Site-relative favicon URL. */
    function suite_favicon_url(PDO $pdo)
    {
        $b = suite_branding($pdo);
        return $b['favicon_path'];
    }

    /**
     * Absolute logo URL for emails (site_base_url + site-relative logo path).
     * Mail clients cannot resolve a site-relative path, so email builders must
     * use this. Mirrors what the crons/mailers did with the hardcoded path.
     */
    function suite_logo_abs(PDO $pdo)
    {
        $base = rtrim(suite_setting($pdo, 'site_base_url', 'https://owyg.yachtbrokersupport.com'), '/');
        return $base . suite_logo_url($pdo);
    }

    /**
     * Build a multi-line company contact block (name, address, phone, email).
     * $sep is the line separator (default a newline, e.g. for text emails). Blank
     * components are skipped. Returns '' if nothing is set beyond the name, so a
     * caller can decide whether to render it. Values are NOT html-escaped here -
     * the caller escapes per context (HTML vs plaintext).
     *
     * @return array ordered list of the non-empty lines
     */
    function suite_contact_lines(PDO $pdo, $includeName = true)
    {
        $b = suite_branding($pdo);
        $lines = array();
        if ($includeName && trim((string) $b['company_name']) !== '') {
            $lines[] = $b['company_name'];
        }
        foreach (array('company_address', 'company_phone', 'company_email') as $k) {
            $v = trim((string) $b[$k]);
            if ($v !== '') {
                $lines[] = $v;
            }
        }
        return $lines;
    }

    /**
     * Emit the theme <style> block for a web page <head>. Sets the color custom
     * properties from settings. Includes BOTH naming conventions the suite CSS
     * uses today (--navy / --cyan / --cyan-d in the external stylesheets AND
     * --accent / --accent-text used in the inline-styled pages) so a single call
     * re-themes every page regardless of which variable names it reads. Call this
     * AFTER the page's own <style>/<link> so it overrides the seeded defaults.
     *
     * Behavior-neutral for OWYG: with the seeded OWYG hex, this emits the exact
     * same values the CSS already hardcodes.
     */
    function suite_theme_head(PDO $pdo)
    {
        $header = suite_color($pdo, 'header_color', '#0a1628');
        $brand  = suite_color($pdo, 'brand_color', '#21cbea');
        $accent = suite_color($pdo, 'accent_color', '#1aa8c4');
        echo '<style id="suite-theme">:root{'
            . '--navy:' . $header . ';'
            . '--cyan:' . $brand . ';'
            . '--cyan-d:' . $accent . ';'
            . '--accent:' . $brand . ';'
            . '--accent-text:' . $accent . ';'
            . '--header-color:' . $header . ';'
            . '--brand-color:' . $brand . ';'
            . '--accent-color:' . $accent . ';'
            . '}</style>' . "\n";
    }
}
