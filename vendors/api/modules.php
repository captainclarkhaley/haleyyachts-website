<?php
/**
 * modules.php - per-tenant module enablement (white-label).
 *
 * Each app/module in the suite has ONE of four states, stored as a suite_setting
 * (seeded in db.php with TODAY's behavior so nothing changes for OWYG):
 *
 *   live    - visible + usable by everyone (any signed-in staff user)
 *   admin   - visible + usable only by in-app admins; a non-admin is bounced from
 *             the module page and sees a "coming soon" tile on the launcher
 *   soon    - shown on the launcher as a disabled "coming soon" tile; the module
 *             page (if any) bounces EVERYONE back to the launcher
 *   hidden  - no tile on the launcher at all; the module page bounces everyone
 *
 * The state is enforced in TWO places, not just visually:
 *   1. suite.php renders each tile per its resolved state (link vs coming-soon vs
 *      absent), from module_tiles($pdo, $isAdmin).
 *   2. Each module's OWN entry page calls module_guard($pdo, $key, $isAdmin,
 *      $redirect) up top, so a user who is not permitted is redirected BEFORE any
 *      markup - typing the URL directly does not get them in.
 *
 * Requires vendors/api/db.php (suite_setting()) already loaded with a live $pdo.
 */

if (!function_exists('module_registry')) {

    /**
     * The module registry: the single source of truth for which modules exist,
     * their launcher-tile presentation, the entry path a live tile links to, and
     * the settings key + default state that stores their enablement.
     *
     * default_state seeds TODAY's behavior exactly:
     *   vendor         -> live  (Vendor Management is open to all staff today)
     *   pocket         -> admin (Pocket's tile is admin-only today)
     *   broker_looking -> soon  (the "Broker Looking For..." placeholder tile)
     *
     * has_page = does this module have its own server-rendered entry page that
     * must self-enforce via module_guard()? (broker_looking has no page yet.)
     *
     * @return array key => meta
     */
    function module_registry()
    {
        return array(
            'vendor' => array(
                'setting_key'   => 'module_vendor_state',
                'default_state' => 'live',
                'name'          => 'Vendor Management',
                'monogram'      => 'VM',
                'desc'          => 'Access, manage, and search vendors by type, coverage area, and broker ratings.',
                'href'          => 'index.php',
                'has_page'      => true,
            ),
            'pocket' => array(
                'setting_key'   => 'module_pocket_state',
                'default_state' => 'admin',
                'name'          => 'Pocket Listings',
                'monogram'      => 'PL',
                'desc'          => 'Enter private off-market listings and let brokers search the network.',
                'href'          => 'pocket/',
                'has_page'      => true,
            ),
            'broker_looking' => array(
                'setting_key'   => 'module_broker_looking_state',
                'default_state' => 'soon',
                'name'          => 'Broker Looking For...',
                'monogram'      => 'BL',
                'desc'          => 'Post what your client is searching for and let brokers surface a match.',
                'href'          => '#',
                'has_page'      => false,
            ),
        );
    }

    /** The allowed states, in the order they appear in the admin dropdown. */
    function module_states()
    {
        return array(
            'live'   => 'Live (everyone)',
            'admin'  => 'Admin only',
            'soon'   => 'Coming soon (shown, disabled)',
            'hidden' => 'Hidden',
        );
    }

    /**
     * Resolve a module's current state from settings, falling back to its default.
     * An unknown/blank/invalid stored value falls back to the registry default, so
     * a bad row can never break the launcher or a guard.
     *
     * @return string one of live|admin|soon|hidden
     */
    function module_state(PDO $pdo, $key)
    {
        $reg = module_registry();
        if (!isset($reg[$key])) {
            return 'hidden';
        }
        $default = $reg[$key]['default_state'];
        $val = suite_setting($pdo, $reg[$key]['setting_key'], $default);
        $val = strtolower(trim((string) $val));
        return array_key_exists($val, module_states()) ? $val : $default;
    }

    /**
     * Can THIS user open the module (reach its usable page)?
     *   live  -> yes for anyone signed in
     *   admin -> yes only for an admin
     *   soon  -> no (nobody; it is a placeholder)
     *   hidden-> no
     *
     * @param bool $isAdmin whether the resolved session user is an in-app admin
     */
    function module_can_use(PDO $pdo, $key, $isAdmin)
    {
        $state = module_state($pdo, $key);
        if ($state === 'live')  { return true; }
        if ($state === 'admin') { return (bool) $isAdmin; }
        return false; // soon, hidden
    }

    /**
     * Server-side entry-page guard. Call at the top of a module's entry page(s)
     * AFTER the login/must-change gate but BEFORE any markup. If the current user
     * is not permitted to use the module, redirect to $redirect (the launcher) and
     * exit, so the page cannot be reached by typing the URL.
     *
     * @param string $redirect relative path to the launcher from the calling page
     *                         (e.g. 'suite.php' or '../suite.php')
     */
    function module_guard(PDO $pdo, $key, $isAdmin, $redirect)
    {
        if (!module_can_use($pdo, $key, $isAdmin)) {
            header('Location: ' . $redirect);
            exit;
        }
    }

    /**
     * Build the launcher tile list for suite.php, honoring each module's state and
     * the viewer's admin flag. Returns an ordered list of tiles to render:
     *   [ 'name','monogram','desc','href','status' ]  where status is 'live' (a
     *   navigable link) or 'soon' (an inert coming-soon tile). HIDDEN modules and
     *   admin-only modules for a non-admin are simply omitted / shown as soon per
     *   the rules below. This is the ONLY place tile presentation is decided.
     *
     * Presentation rules per state:
     *   live   -> live tile for everyone
     *   admin  -> live tile for an admin; coming-soon tile for a non-admin
     *             (preserves today's Pocket behavior exactly)
     *   soon   -> coming-soon tile for everyone
     *   hidden -> no tile for anyone
     */
    function module_tiles(PDO $pdo, $isAdmin)
    {
        $tiles = array();
        foreach (module_registry() as $key => $meta) {
            $state = module_state($pdo, $key);
            if ($state === 'hidden') {
                continue;
            }
            if ($state === 'live') {
                $status = 'live';
            } elseif ($state === 'admin') {
                $status = $isAdmin ? 'live' : 'soon';
            } else { // soon
                $status = 'soon';
            }
            $tiles[] = array(
                'name'     => $meta['name'],
                'monogram' => $meta['monogram'],
                'desc'     => $meta['desc'],
                'href'     => ($status === 'live') ? $meta['href'] : '#',
                'status'   => $status,
            );
        }
        return $tiles;
    }
}
