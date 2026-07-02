<?php
/**
 * db.php - PDO SQLite connection + schema auto-init for the Vendor Database.
 *
 * Shared by the staff API (vendors/api/api.php) and the admin lists endpoint
 * (admin/vendor-lists-api.php). On first run it creates the SQLite file, builds
 * the schema, and seeds the two predefined lists if they are empty.
 *
 * The DB file lives at:  {DOCUMENT_ROOT}/api/data/vendors.sqlite
 * It is kept OUT of git (.gitignore) and blocked from the web by
 * vendors/api/data/.htaccess. Back it up separately - it is the live data.
 *
 * Returns a configured PDO handle via vdb_connect().
 */

if (!function_exists('vdb_connect')) {

    /**
     * Open (and on first call build) the vendor database.
     * @return PDO
     */
    function vdb_connect()
    {
        static $pdo = null;
        if ($pdo instanceof PDO) {
            return $pdo;
        }

        $dataDir = $_SERVER['DOCUMENT_ROOT'] . '/api/data';
        $dbPath  = $dataDir . '/vendors.sqlite';

        if (!is_dir($dataDir)) {
            // Best effort. If this fails the PDO open below will surface a clear error.
            @mkdir($dataDir, 0755, true);
        }

        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
        // Concurrency hardening for a small multi-user team. WAL lets readers and a
        // writer run at the same time (searching while someone edits never blocks),
        // and busy_timeout makes a connection wait up to 5s for a lock instead of
        // immediately erroring if two writes ever collide. Both are idempotent per
        // connection; WAL mode persists on the file once set. Creates -wal/-shm
        // sidecar files (already gitignored).
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA busy_timeout = 5000');

        vdb_init_schema($pdo);
        vdb_migrate($pdo);
        vdb_seed_lists($pdo);
        // Build/repair the coverage-area hierarchy. Runs AFTER seed_lists so a
        // fresh install's flat legacy rows get promoted/parented; on the live DB
        // it parents the existing rows. Idempotent + additive (see the function).
        vdb_seed_hierarchy($pdo);

        // Pocket Listings (second Yacht Broker Support app). Its tables are created + seeded
        // here, sharing this same DB so it can read the users table (brokers). All
        // additive + idempotent - it never touches the vendor tables above.
        vdb_init_pocket_schema($pdo);
        vdb_seed_pocket_makes($pdo);

        // Vendor documents (per-vendor uploads + expiration reminders). Additive +
        // idempotent; never touches any existing vendor/contact/pocket table.
        vdb_init_documents_schema($pdo);
        vdb_seed_document_purposes($pdo);

        // Suite settings (Yacht Broker Support platform config layer, Phase 1). A single
        // key/value table of non-secret, environment/rollout knobs (base URL,
        // from address, notification recipients, admin email). Additive +
        // idempotent; seeded with today's hardcoded values via INSERT OR IGNORE
        // so a later admin edit is never overwritten. SMTP secrets stay OUT of
        // this table - they live in the untracked mail-secrets.php.
        vdb_init_settings_schema($pdo);
        vdb_seed_settings($pdo);

        return $pdo;
    }

    // =======================================================================
    // SUITE SETTINGS (Yacht Broker Support platform config layer, Phase 1)
    //
    // One flat key/value table of non-secret, environment/rollout knobs shared
    // across the suite. Code reads a setting with the current hardcoded literal
    // as the fallback default, so a missing OR blank row can never break a send.
    // SECRETS (SMTP host/user/pass, from_name default) are NOT stored here -
    // they stay in the untracked mail-secrets.php.
    // =======================================================================

    /**
     * Create the suite_settings table if it does not already exist. Additive +
     * idempotent. Double-quoted PHP string because the SQL carries a single-quote
     * literal (DEFAULT '') - keeps the quote layers from colliding.
     */
    function vdb_init_settings_schema(PDO $pdo)
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS suite_settings (
                key   TEXT PRIMARY KEY,
                value TEXT NOT NULL DEFAULT ''
            )
        ");
    }

    /**
     * Seed the suite settings with today's hardcoded values, using INSERT OR
     * IGNORE so an existing row (e.g. a later admin edit) is NEVER overwritten on
     * a subsequent load. On a fresh DB these rows are created; on an existing DB
     * any already-present key is left exactly as-is. The seeded values MUST equal
     * the current hardcoded literals so behavior is unchanged - the setting only
     * changes WHERE the value comes from, never WHAT it is.
     */
    function vdb_seed_settings(PDO $pdo)
    {
        $defaults = array(
            'site_base_url'     => 'https://owyg.yachtbrokersupport.com',
            'mail_from_address' => 'no-reply@owyg.yachtbrokersupport.com',
            'pocket_notify_to'  => 'clark@mvroam.com',
            'doc_admin_email'   => 'admin@OWYG.com',
            'brand_name'        => 'Yacht Broker Support',
            'tenant_name'       => 'One Water Yacht Group',
        );
        $stmt = $pdo->prepare('INSERT OR IGNORE INTO suite_settings (key, value) VALUES (?, ?)');
        foreach ($defaults as $key => $value) {
            $stmt->execute(array($key, $value));
        }
    }

    /**
     * Read a single suite setting. Returns the stored value, or $default when the
     * key is missing OR its stored value is '' (blank). All rows are loaded ONCE
     * into a per-request static cache on the first call so repeated reads never
     * re-query. Callers pass the current hardcoded literal as $default, so a
     * missing/blank setting behaves identically to the old hardcoded constant.
     *
     * @param PDO    $pdo
     * @param string $key
     * @param string $default value returned when the key is absent or stored blank
     * @return string
     */
    function suite_setting(PDO $pdo, $key, $default = '')
    {
        static $cache = null;
        if ($cache === null) {
            $cache = array();
            // First call fills the cache from a single query. If the table does
            // not exist yet for any reason, fall back to an empty cache so every
            // lookup returns its $default (identical to the old hardcoded value).
            try {
                $rows = $pdo->query('SELECT key, value FROM suite_settings')->fetchAll();
                foreach ($rows as $r) {
                    $cache[(string) $r['key']] = (string) $r['value'];
                }
            } catch (Throwable $e) {
                $cache = array();
            }
        }
        if (!array_key_exists($key, $cache)) {
            return $default;
        }
        $value = $cache[$key];
        return ($value === '') ? $default : $value;
    }

    /**
     * Create tables if they do not already exist.
     */
    function vdb_init_schema(PDO $pdo)
    {
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS vendor_types (
                id   INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                sort INTEGER NOT NULL DEFAULT 0
            )
        ');

        // coverage_areas is a SELF-REFERENTIAL hierarchy:
        //   kind = 'nationwide' | 'state' | 'region' | 'county'
        //   parent_id -> coverage_areas.id (NULL for top-level: nationwide + states)
        // Double-quoted PHP string because the SQL carries a single-quote literal
        // (DEFAULT 'county') - keeps the quote layers from colliding.
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS coverage_areas (
                id        INTEGER PRIMARY KEY AUTOINCREMENT,
                name      TEXT NOT NULL UNIQUE,
                sort      INTEGER NOT NULL DEFAULT 0,
                parent_id INTEGER NULL,
                kind      TEXT NOT NULL DEFAULT 'county',
                FOREIGN KEY (parent_id) REFERENCES coverage_areas(id) ON DELETE SET NULL
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS vendors (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                name       TEXT NOT NULL,
                address    TEXT NOT NULL DEFAULT '',
                phone      TEXT NOT NULL DEFAULT '',
                email      TEXT NOT NULL DEFAULT '',
                notes      TEXT NOT NULL DEFAULT '',
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                updated_at TEXT NOT NULL DEFAULT (datetime('now'))
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS contacts (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                vendor_id  INTEGER NOT NULL,
                name       TEXT NOT NULL DEFAULT '',
                email      TEXT NOT NULL DEFAULT '',
                phone      TEXT NOT NULL DEFAULT '',
                is_primary INTEGER NOT NULL DEFAULT 0,
                notes      TEXT NOT NULL DEFAULT '',
                FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
            )
        ");

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS vendor_type_map (
                vendor_id INTEGER NOT NULL,
                type_id   INTEGER NOT NULL,
                PRIMARY KEY (vendor_id, type_id),
                FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
                FOREIGN KEY (type_id)   REFERENCES vendor_types(id) ON DELETE CASCADE
            )
        ');

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS vendor_area_map (
                vendor_id INTEGER NOT NULL,
                area_id   INTEGER NOT NULL,
                PRIMARY KEY (vendor_id, area_id),
                FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
                FOREIGN KEY (area_id)   REFERENCES coverage_areas(id) ON DELETE CASCADE
            )
        ');

        // Vendor ratings. One dated row per rating; the average is the mean of all
        // rows. Ratings are now ATTRIBUTED to the staff member who left them via
        // rater_account / rater_name (captured server-side from the session). Old
        // rows predating attribution carry '' and display as "Anonymous"; the
        // anonymous capability is intentionally kept at the DB level (DEFAULT '').
        // Double-quoted PHP string because the SQL carries single-quote literals
        // (DEFAULT '' and datetime('now')) - keeps the quote layers from colliding.
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS vendor_ratings (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                vendor_id     INTEGER NOT NULL,
                stars         INTEGER NOT NULL,
                note          TEXT NOT NULL DEFAULT '',
                rater_account TEXT NOT NULL DEFAULT '',
                rater_name    TEXT NOT NULL DEFAULT '',
                created_at    TEXT NOT NULL DEFAULT (datetime('now')),
                FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
            )
        ");

        // Staff login accounts for the in-app auth that replaces cPanel Directory
        // Privacy on /vendors/. Passwords are stored ONLY as a bcrypt hash
        // (password_hash). Double-quoted PHP string because the SQL carries
        // single-quote literals (DEFAULT '' and datetime('now')).
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id                   INTEGER PRIMARY KEY AUTOINCREMENT,
                account_id           TEXT NOT NULL UNIQUE,
                name                 TEXT NOT NULL DEFAULT '',
                email                TEXT NOT NULL UNIQUE,
                cell                 TEXT NOT NULL DEFAULT '',
                home_office          TEXT NOT NULL DEFAULT '',
                password_hash        TEXT NOT NULL,
                active               INTEGER NOT NULL DEFAULT 1,
                is_admin             INTEGER NOT NULL DEFAULT 0,
                must_change_password INTEGER NOT NULL DEFAULT 0,
                created_at           TEXT NOT NULL DEFAULT (datetime('now')),
                updated_at           TEXT NOT NULL DEFAULT (datetime('now'))
            )
        ");

        // Self-service password reset tokens. We store ONLY a SHA-256 hash of the
        // raw token (the raw token lives only in the emailed link), with a 1-hour
        // expiry and a used flag. Double-quoted PHP string for the same reason.
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS password_resets (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id    INTEGER NOT NULL,
                token_hash TEXT NOT NULL,
                expires_at TEXT NOT NULL,
                used       INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");

        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_contacts_vendor ON contacts(vendor_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_typemap_vendor  ON vendor_type_map(vendor_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_areamap_vendor  ON vendor_area_map(vendor_id)');
        // NOTE: the index on coverage_areas(parent_id) is created in vdb_migrate,
        // AFTER the parent_id column is added. Creating it here would run before the
        // migration and fail on the live pre-hierarchy table ("no such column").
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_ratings_vendor  ON vendor_ratings(vendor_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_users_account   ON users(account_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_users_email     ON users(email)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_resets_token    ON password_resets(token_hash)');
    }

    /**
     * Idempotent column migrations for tables that already exist on the live
     * server. CREATE TABLE IF NOT EXISTS will NOT add a new column to an existing
     * table, so each added column is checked via PRAGMA table_info and only
     * ALTERed in when missing. This never drops or rewrites data: existing rows
     * keep their values and pick up the column's DEFAULT.
     */
    function vdb_migrate(PDO $pdo)
    {
        // users.must_change_password: forces a password change on next login for
        // new accounts and admin-reset accounts. Existing rows default to 0 (not
        // forced), so nobody already in the system is locked out by the upgrade.
        if (!vdb_column_exists($pdo, 'users', 'must_change_password')) {
            $pdo->exec('ALTER TABLE users ADD COLUMN must_change_password INTEGER NOT NULL DEFAULT 0');
        }

        // users.is_admin: gates the admin-only vendor delete. Existing rows
        // default to 0 (NOT admin), so nobody is silently granted delete rights by
        // the upgrade - Clark flags the right accounts as admin in admin/users.html
        // after deploy. Additive ALTER only; no data dropped or rewritten.
        if (!vdb_column_exists($pdo, 'users', 'is_admin')) {
            $pdo->exec('ALTER TABLE users ADD COLUMN is_admin INTEGER NOT NULL DEFAULT 0');
        }

        // vendor_ratings rater attribution: capture WHO left each rating going
        // forward. Both ADDITIVE - existing rating rows keep their values and
        // back-fill to '' (shown as "Anonymous" in the UI). The anonymous
        // capability is intentionally retained at the DB level. Double-quoted PHP
        // strings because the SQL carries the single-quote literal DEFAULT ''.
        if (!vdb_column_exists($pdo, 'vendor_ratings', 'rater_account')) {
            $pdo->exec("ALTER TABLE vendor_ratings ADD COLUMN rater_account TEXT NOT NULL DEFAULT ''");
        }
        if (!vdb_column_exists($pdo, 'vendor_ratings', 'rater_name')) {
            $pdo->exec("ALTER TABLE vendor_ratings ADD COLUMN rater_name TEXT NOT NULL DEFAULT ''");
        }

        // coverage_areas hierarchy columns. The live table predates the tiered
        // model, so CREATE TABLE IF NOT EXISTS will NOT add these - we ALTER them
        // in only when missing. Both are ADDITIVE: every existing area row keeps
        // its id, name, and sort untouched and simply picks up the column default
        // (parent_id NULL = top-level, kind 'county'). Nothing is dropped, renamed,
        // renumbered, or re-mapped. vendor_area_map is never touched.
        if (!vdb_column_exists($pdo, 'coverage_areas', 'parent_id')) {
            // No NOT NULL / no DEFAULT literal needed; SQLite back-fills NULL.
            $pdo->exec('ALTER TABLE coverage_areas ADD COLUMN parent_id INTEGER NULL');
        }
        if (!vdb_column_exists($pdo, 'coverage_areas', 'kind')) {
            // Double-quoted PHP string: the SQL carries a single-quote literal
            // (DEFAULT 'county'). Existing rows back-fill to 'county' - harmless,
            // and Clark re-tiers structural rows below + in the admin tool.
            $pdo->exec("ALTER TABLE coverage_areas ADD COLUMN kind TEXT NOT NULL DEFAULT 'county'");
        }

        // Index parent_id only now that the column is guaranteed to exist (added
        // by the ALTER above on the live DB, or present from CREATE TABLE on a
        // fresh install). Doing this in vdb_init_schema would run before this
        // migration and fail on the pre-hierarchy table.
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_areas_parent ON coverage_areas(parent_id)');
    }

    // -----------------------------------------------------------------------
    // Coverage-area hierarchy seeding (idempotent, additive, NON-destructive)
    // -----------------------------------------------------------------------

    /**
     * Find an existing coverage_areas row by EXACT name, case-insensitive.
     * Returns the row id (int) or 0 when none. Used so seeding REUSES rows that
     * already exist rather than ever creating a duplicate.
     */
    function vdb_area_id_by_name(PDO $pdo, $name)
    {
        $stmt = $pdo->prepare('SELECT id FROM coverage_areas WHERE name = ? COLLATE NOCASE');
        $stmt->execute(array($name));
        $id = $stmt->fetchColumn();
        return $id === false ? 0 : (int) $id;
    }

    /**
     * Ensure a structural node exists with the given name/kind/parent.
     *  - If a row with that exact name already exists (case-insensitive), REUSE
     *    it: set its kind, and set its parent ONLY when its parent is currently
     *    NULL (never overwrite a parent Clark may have set by hand).
     *  - Otherwise INSERT a new row at the end of the sort order.
     * Returns the node id. Never deletes or renumbers anything.
     */
    function vdb_ensure_area(PDO $pdo, $name, $kind, $parentId)
    {
        $id = vdb_area_id_by_name($pdo, $name);
        if ($id > 0) {
            // Reuse. Always normalize kind for a recognized structural row.
            $pdo->prepare('UPDATE coverage_areas SET kind = ? WHERE id = ?')
                ->execute(array($kind, $id));
            // Only set parent where it is currently unset, so we never clobber a
            // parent the admin tool already assigned.
            if ($parentId !== null) {
                $pdo->prepare('UPDATE coverage_areas SET parent_id = ? WHERE id = ? AND parent_id IS NULL')
                    ->execute(array($parentId, $id));
            }
            return $id;
        }
        $maxSort = (int) $pdo->query('SELECT COALESCE(MAX(sort), -1) FROM coverage_areas')->fetchColumn();
        $stmt = $pdo->prepare('INSERT INTO coverage_areas (name, sort, parent_id, kind) VALUES (?, ?, ?, ?)');
        $stmt->execute(array($name, $maxSort + 1, $parentId, $kind));
        return (int) $pdo->lastInsertId();
    }

    /**
     * Best-effort parenting of an EXISTING county row under a region/state.
     * Sets kind='county' and parent = $parentId, but ONLY when the row exists,
     * matches the name exactly (case-insensitive), AND its parent is currently
     * NULL. An unrecognized or already-parented row is left exactly as it is for
     * Clark to fix in the admin tool. Never inserts, deletes, or renumbers.
     */
    function vdb_parent_existing_county(PDO $pdo, $name, $parentId)
    {
        $id = vdb_area_id_by_name($pdo, $name);
        if ($id <= 0) {
            return; // unknown row -> leave top-level, do not guess
        }
        $pdo->prepare("
            UPDATE coverage_areas
            SET kind = 'county', parent_id = ?
            WHERE id = ? AND parent_id IS NULL
        ")->execute(array($parentId, $id));
    }

    /**
     * Build the tiered hierarchy on top of whatever coverage_areas already holds.
     * IDEMPOTENT + ADDITIVE: re-running it is a no-op once everything is in place.
     * It NEVER drops, deletes, or renumbers a row, NEVER touches vendor_area_map,
     * and only sets a parent where the parent is currently NULL on an exact-name
     * match. Wrapped in a transaction so a mid-way error rolls back cleanly and
     * the existing api error handling surfaces it.
     */
    function vdb_seed_hierarchy(PDO $pdo)
    {
        // Guard: the columns must exist (vdb_migrate runs before this). If for any
        // reason they do not, bail rather than error - the next load retries.
        if (!vdb_column_exists($pdo, 'coverage_areas', 'parent_id')
            || !vdb_column_exists($pdo, 'coverage_areas', 'kind')) {
            return;
        }

        $pdo->beginTransaction();
        try {
            // --- top-level: nationwide node (labeled "USA") ---
            // One-time rename of the original label to "USA". Idempotent: once
            // renamed no row matches, so it is a no-op. Preserves the row id, kind,
            // and all vendor_area_map links, and must run BEFORE the ensure below so
            // the ensure reuses this row instead of creating a duplicate "USA".
            $pdo->exec("UPDATE coverage_areas SET name = 'USA' WHERE name = 'Nationwide / Not location-dependent'");
            vdb_ensure_area($pdo, 'USA', 'nationwide', null);

            // --- top-level: Florida state node ---
            // Reuse a legacy "Statewide" or "Florida Statewide" row if present so
            // any vendor already tagged statewide keeps that exact tag (same id).
            // Otherwise reuse/create "Florida". We do NOT rename the legacy row;
            // we only promote it to kind='state', parent NULL.
            $flId = vdb_area_id_by_name($pdo, 'Florida');
            if ($flId <= 0) { $flId = vdb_area_id_by_name($pdo, 'Florida Statewide'); }
            if ($flId <= 0) { $flId = vdb_area_id_by_name($pdo, 'Statewide'); }
            if ($flId > 0) {
                // Promote the existing row in place (keep its name + id + tags).
                $pdo->prepare("UPDATE coverage_areas SET kind = 'state', parent_id = NULL WHERE id = ?")
                    ->execute(array($flId));
            } else {
                $flId = vdb_ensure_area($pdo, 'Florida', 'state', null);
            }

            // --- other top-level states (no children seeded yet) ---
            vdb_ensure_area($pdo, 'North Carolina', 'state', null);
            vdb_ensure_area($pdo, 'South Carolina', 'state', null);
            vdb_ensure_area($pdo, 'Maryland', 'state', null);
            vdb_ensure_area($pdo, 'New York', 'state', null);

            // --- Florida regions (reuse legacy region rows if present) ---
            $southFlId     = vdb_ensure_area($pdo, 'South Florida', 'region', $flId);
            $treasureId    = vdb_ensure_area($pdo, 'Treasure Coast', 'region', $flId);
            $gulfId        = vdb_ensure_area($pdo, 'Gulf Coast', 'region', $flId);

            // --- best-effort county parenting (exact name + parent currently NULL) ---
            $byRegion = array(
                $southFlId  => array('Miami-Dade', 'Broward', 'Palm Beach', 'Monroe (Keys)'),
                $treasureId => array('Martin', 'St. Lucie'),
                $gulfId     => array('Collier', 'Lee', 'Charlotte', 'Sarasota', 'Manatee', 'Pinellas', 'Hillsborough'),
            );
            foreach ($byRegion as $regionId => $counties) {
                foreach ($counties as $countyName) {
                    vdb_parent_existing_county($pdo, $countyName, (int) $regionId);
                }
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e; // let the api error handler surface it
        }
    }

    /**
     * The full coverage-area tree as a flat ordered list (id, name, kind,
     * parent_id, sort). Ordered for tree rendering: kind rank first
     * (nationwide, state, region, county) then sort then name. Callers that need
     * strict nesting build the parent->children map from parent_id. Reused by the
     * staff list endpoint (closure matching) and the admin tree UI.
     */
    function vdb_area_tree(PDO $pdo)
    {
        $rows = $pdo->query("
            SELECT id, name, kind, parent_id, sort
            FROM coverage_areas
            ORDER BY
                CASE kind
                    WHEN 'nationwide' THEN 0
                    WHEN 'state'      THEN 1
                    WHEN 'region'     THEN 2
                    WHEN 'county'     THEN 3
                    ELSE 4
                END,
                sort,
                name COLLATE NOCASE
        ")->fetchAll();
        foreach ($rows as &$r) {
            $r['id']        = (int) $r['id'];
            $r['parent_id'] = ($r['parent_id'] === null) ? null : (int) $r['parent_id'];
            $r['sort']      = (int) $r['sort'];
        }
        unset($r);
        return $rows;
    }

    /**
     * True if $column exists on $table, read from PRAGMA table_info. Used by
     * vdb_migrate to decide whether an ALTER TABLE is needed.
     */
    function vdb_column_exists(PDO $pdo, $table, $column)
    {
        // PRAGMA does not accept a bound parameter for the table name; the table
        // names here are hard-coded literals (never user input), so this is safe.
        $stmt = $pdo->query('PRAGMA table_info(' . $table . ')');
        if ($stmt === false) {
            return false;
        }
        foreach ($stmt->fetchAll() as $col) {
            if (isset($col['name']) && $col['name'] === $column) {
                return true;
            }
        }
        return false;
    }

    /**
     * Canonical HOME OFFICE list - the single source of truth used by BOTH the
     * admin account form (to render the dropdown) and the server-side validation
     * (to reject anything not in this set). Keep these two uses pointed here so
     * they never drift apart.
     */
    function vdb_home_offices()
    {
        return array(
            'Lindenhurst, NY',
            'Stevensville, MD',
            'Wilmington, NC',
            'Charleston, SC',
            'St. Augustine, FL',
            'Jupiter, FL',
            'Palm Beach Gardens, FL',
            'Dania Beach, FL',
            'Miami, FL',
            'Naples, FL',
            'Bradenton, FL',
            'Dunedin, FL',
            'Fort Lauderdale, FL',
        );
    }

    /**
     * Seed the predefined lists, but only if a list is empty. Clark edits these
     * afterward via the admin lists page, so we never overwrite his changes.
     */
    function vdb_seed_lists(PDO $pdo)
    {
        $types = array(
            'Surveyor',
            'Volvo / Engine Specialist',
            'Mechanic',
            'Insurance',
            'Boat Transport',
            'Detailing',
            'Diver',
            'Electronics',
            'Canvas / Upholstery',
            'Documentation',
            'Fiberglass / Hull',
            'Rigging',
        );

        // Fresh-install county seed only. The structural tiers (Nationwide,
        // states, regions) are created by vdb_seed_hierarchy(), which also parents
        // these counties under their region. On the LIVE DB this block is skipped
        // entirely (coverage_areas is non-empty), so the live rows are untouched.
        $areas = array(
            'Miami-Dade',
            'Broward',
            'Palm Beach',
            'Monroe (Keys)',
            'Martin',
            'St. Lucie',
            'Collier',
            'Lee',
            'Charlotte',
            'Sarasota',
            'Manatee',
            'Pinellas',
            'Hillsborough',
        );

        $count = (int) $pdo->query('SELECT COUNT(*) FROM vendor_types')->fetchColumn();
        if ($count === 0) {
            $stmt = $pdo->prepare('INSERT INTO vendor_types (name, sort) VALUES (?, ?)');
            foreach ($types as $i => $name) {
                $stmt->execute(array($name, $i));
            }
        }

        $count = (int) $pdo->query('SELECT COUNT(*) FROM coverage_areas')->fetchColumn();
        if ($count === 0) {
            $stmt = $pdo->prepare('INSERT INTO coverage_areas (name, sort) VALUES (?, ?)');
            foreach ($areas as $i => $name) {
                $stmt->execute(array($name, $i));
            }
        }
    }

    // =======================================================================
    // POCKET LISTINGS (Yacht Broker Support app #2) - schema + seed
    //
    // Shares this SQLite DB so it can read the users table (brokers). These
    // three tables are entirely NEW - CREATE TABLE IF NOT EXISTS never touches
    // or rewrites the vendor tables. Phase 1 scope: the network email,
    // print/share sheet, expiration cron, and comps are LATER phases, but the
    // schema already carries the room they need (status/archived_at for the
    // lifecycle, expires_at for expiration, price_type for net/list).
    // =======================================================================

    /**
     * Create the Pocket Listings tables if they do not already exist.
     * Additive + idempotent; never alters the vendor schema.
     */
    function vdb_init_pocket_schema(PDO $pdo)
    {
        // Controlled builder (make) list. Seeded once from vdb_seed_pocket_makes.
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS pocket_makes (
                id   INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                sort INTEGER NOT NULL DEFAULT 0
            )
        ');

        // A pocket (off-market) listing. broker_id references users.id - the
        // broker who entered it, captured server-side from the session, NEVER
        // from the request. price_type is the Net/List toggle. status +
        // archived_at exist for the later lifecycle phase but Phase 1 only ever
        // writes status='active'. expires_at = created_at + days_active, computed
        // on insert (for the later expiration phase; nothing enforces it yet).
        // Double-quoted PHP string: the SQL carries single-quote literals
        // (DEFAULT 'list', DEFAULT '', DEFAULT 'active', datetime('now')).
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS pocket_listings (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                broker_id   INTEGER NOT NULL,
                make        TEXT NOT NULL DEFAULT '',
                model       TEXT NOT NULL DEFAULT '',
                year        INTEGER,
                length      INTEGER,
                location    TEXT NOT NULL DEFAULT '',
                price       INTEGER,
                price_type  TEXT NOT NULL DEFAULT 'list',
                description TEXT NOT NULL DEFAULT '',
                days_active INTEGER,
                created_at  TEXT NOT NULL DEFAULT (datetime('now')),
                expires_at  TEXT,
                status      TEXT NOT NULL DEFAULT 'active',
                archived_at TEXT,
                FOREIGN KEY (broker_id) REFERENCES users(id)
            )
        ");

        // Up to 1 hero + 4 additional images per listing. Cascade-delete with the
        // listing so removing a listing cleans its image rows (the files on disk
        // are unlinked by the API delete handler). Double-quoted PHP string: the
        // SQL carries a single-quote literal (DEFAULT '').
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS pocket_listing_images (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                listing_id INTEGER NOT NULL,
                filename   TEXT NOT NULL DEFAULT '',
                is_hero    INTEGER NOT NULL DEFAULT 0,
                sort       INTEGER NOT NULL DEFAULT 0,
                FOREIGN KEY (listing_id) REFERENCES pocket_listings(id) ON DELETE CASCADE
            )
        ");

        // Index the FKs (search orders by created_at DESC; broker_id + listing_id
        // are the joined/filtered columns).
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_pocket_listings_broker ON pocket_listings(broker_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_pocket_listings_status ON pocket_listings(status)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_pocket_images_listing  ON pocket_listing_images(listing_id)');

        // ---- Phase 3 lifecycle columns (idempotent, additive) ----
        // pocket_listings predates the expiration reminders on the live server, so
        // CREATE TABLE IF NOT EXISTS above will NOT add these to the existing table.
        // Each is checked via PRAGMA table_info and ALTERed in only when missing.
        // reminded_7d / reminded_1d track which expiry reminder emails the daily
        // cron has already sent for a listing so it never double-sends; both are
        // reset to 0 when a listing is (re)activated. Existing rows back-fill to 0
        // (no reminder sent yet), which is correct - the cron will send on schedule.
        // Additive ALTER only; no data dropped or rewritten.
        if (!vdb_column_exists($pdo, 'pocket_listings', 'reminded_7d')) {
            $pdo->exec('ALTER TABLE pocket_listings ADD COLUMN reminded_7d INTEGER NOT NULL DEFAULT 0');
        }
        if (!vdb_column_exists($pdo, 'pocket_listings', 'reminded_1d')) {
            $pdo->exec('ALTER TABLE pocket_listings ADD COLUMN reminded_1d INTEGER NOT NULL DEFAULT 0');
        }
    }

    /**
     * Seed the controlled builder list ONLY when empty, so Clark can curate it
     * later without this overwriting his edits. Alphabetical; sort mirrors order.
     */
    function vdb_seed_pocket_makes(PDO $pdo)
    {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM pocket_makes')->fetchColumn();
        if ($count > 0) {
            return;
        }
        $makes = array(
            'Azimut', 'Bavaria', 'Beneteau', 'Bertram', 'Boston Whaler',
            'Cabo', 'Carver', 'Catalina', 'Chris-Craft', 'Cranchi',
            'Ferretti', 'Formula', 'Fountaine Pajot', 'Grady-White',
            'Grand Banks', 'Hatteras', 'Hinckley', 'Intrepid', 'Jeanneau',
            'Lagoon', 'Leopard', 'Marlow', 'MJM', 'Nordhavn',
            'Ocean Alexander', 'Pershing', 'Prestige', 'Princess', 'Pursuit',
            'Regal', 'Regulator', 'Riviera', 'Sabre', 'Sea Ray',
            'Sunseeker', 'Tiara', 'Viking', 'Wellcraft', 'World Cat', 'Yellowfin',
        );
        $stmt = $pdo->prepare('INSERT OR IGNORE INTO pocket_makes (name, sort) VALUES (?, ?)');
        foreach ($makes as $i => $name) {
            $stmt->execute(array($name, $i));
        }
    }

    // =======================================================================
    // VENDOR DOCUMENTS (per-vendor uploads + expiration reminders)
    //
    // Two NEW tables, entirely additive + idempotent (CREATE TABLE IF NOT
    // EXISTS), never touching the vendor/contact/pocket tables:
    //   document_purposes  - the admin-managed controlled list (mirrors
    //                         vendor_types: id / name UNIQUE / sort).
    //   vendor_documents   - one row per uploaded file. The BYTES live on disk
    //                        in vendors/api/docs/ under a random filename; this
    //                        row keeps the metadata + the two reminder flags.
    // =======================================================================

    /**
     * Create the vendor-documents tables if they do not already exist.
     * Additive + idempotent; never alters the vendor schema.
     */
    function vdb_init_documents_schema(PDO $pdo)
    {
        // Controlled Purpose list (Insurance, Certificate, admin can add more).
        // Same shape as vendor_types so the admin-managed-list pattern carries over.
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS document_purposes (
                id   INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                sort INTEGER NOT NULL DEFAULT 0
            )
        ');

        // One row per uploaded document. filename is the random on-disk name;
        // original_name is preserved for the download filename. purpose is the
        // Purpose label (denormalized text, like the pocket make field). expires_at
        // is NULL for no-expiry docs. reminded_10d / reminded_exp guard the cron so
        // each reminder is sent at most once. uploaded_by = users.id (session user).
        // Double-quoted PHP string: the SQL carries single-quote literals
        // (DEFAULT '' and datetime('now')).
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS vendor_documents (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                vendor_id     INTEGER NOT NULL,
                filename      TEXT NOT NULL,
                original_name TEXT NOT NULL DEFAULT '',
                purpose       TEXT NOT NULL DEFAULT '',
                description   TEXT NOT NULL DEFAULT '',
                provided_by   TEXT NOT NULL DEFAULT 'vendor',
                expires_at    TEXT NULL,
                reminded_10d  INTEGER NOT NULL DEFAULT 0,
                reminded_exp  INTEGER NOT NULL DEFAULT 0,
                uploaded_by   INTEGER NOT NULL DEFAULT 0,
                created_at    TEXT NOT NULL DEFAULT (datetime('now')),
                FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
            )
        ");

        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_docs_vendor ON vendor_documents(vendor_id)');

        // ---- Additive columns for an already-existing table (idempotent) ----
        // On a live server the vendor_documents table may predate these two
        // columns, and CREATE TABLE IF NOT EXISTS never adds a column to an
        // existing table. Each is guarded by vdb_column_exists and ALTERed in only
        // when missing. No data is dropped or rewritten:
        //   description  - optional short note (<= 50 chars), back-fills to ''.
        //   provided_by  - direction of the policy, 'vendor' (vendor provides it to
        //                  us) or 'us' (we provide it to the vendor). Existing rows
        //                  back-fill to 'vendor', preserving today's behavior.
        if (!vdb_column_exists($pdo, 'vendor_documents', 'description')) {
            $pdo->exec("ALTER TABLE vendor_documents ADD COLUMN description TEXT NOT NULL DEFAULT ''");
        }
        if (!vdb_column_exists($pdo, 'vendor_documents', 'provided_by')) {
            $pdo->exec("ALTER TABLE vendor_documents ADD COLUMN provided_by TEXT NOT NULL DEFAULT 'vendor'");
        }
    }

    /**
     * Seed the Purpose list ONLY when empty (guarded by a COUNT, exactly like
     * vdb_seed_lists does for vendor_types), so Clark can curate it afterward via
     * the add-purpose flow without this overwriting his edits.
     */
    function vdb_seed_document_purposes(PDO $pdo)
    {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM document_purposes')->fetchColumn();
        if ($count === 0) {
            $seed = array('Insurance', 'Certificate');
            $stmt = $pdo->prepare('INSERT INTO document_purposes (name, sort) VALUES (?, ?)');
            foreach ($seed as $i => $name) {
                $stmt->execute(array($name, $i));
            }
        }
    }
}
