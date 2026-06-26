<?php
/**
 * db.php - PDO SQLite connection + schema auto-init for the Vendor Database.
 *
 * Shared by the staff API (vendors/api/api.php) and the admin lists endpoint
 * (admin/vendor-lists-api.php). On first run it creates the SQLite file, builds
 * the schema, and seeds the two predefined lists if they are empty.
 *
 * The DB file lives at:  {DOCUMENT_ROOT}/vendors/api/data/vendors.sqlite
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

        $dataDir = $_SERVER['DOCUMENT_ROOT'] . '/vendors/api/data';
        $dbPath  = $dataDir . '/vendors.sqlite';

        if (!is_dir($dataDir)) {
            // Best effort. If this fails the PDO open below will surface a clear error.
            @mkdir($dataDir, 0755, true);
        }

        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');

        vdb_init_schema($pdo);
        vdb_migrate($pdo);
        vdb_seed_lists($pdo);
        // Build/repair the coverage-area hierarchy. Runs AFTER seed_lists so a
        // fresh install's flat legacy rows get promoted/parented; on the live DB
        // it parents the existing rows. Idempotent + additive (see the function).
        vdb_seed_hierarchy($pdo);

        return $pdo;
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

        // Anonymous vendor ratings. One dated row per rating; the average is the
        // mean of all rows. No rater column by design (ratings are anonymous).
        // Double-quoted PHP string because the SQL carries single-quote literals
        // (DEFAULT '' and datetime('now')) - keeps the quote layers from colliding.
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS vendor_ratings (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                vendor_id  INTEGER NOT NULL,
                stars      INTEGER NOT NULL,
                note       TEXT NOT NULL DEFAULT '',
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
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
            // --- top-level: Nationwide node ---
            vdb_ensure_area($pdo, 'Nationwide / Not location-dependent', 'nationwide', null);

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
}
