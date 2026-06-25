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

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS coverage_areas (
                id   INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                sort INTEGER NOT NULL DEFAULT 0
            )
        ');

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
            'South Florida',
            'Treasure Coast',
            'Gulf Coast',
            'Statewide',
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
