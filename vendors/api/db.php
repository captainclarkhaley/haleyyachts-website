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

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS vendors (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                name       TEXT NOT NULL,
                address    TEXT NOT NULL DEFAULT "",
                phone      TEXT NOT NULL DEFAULT "",
                email      TEXT NOT NULL DEFAULT "",
                notes      TEXT NOT NULL DEFAULT "",
                created_at TEXT NOT NULL DEFAULT (datetime("now")),
                updated_at TEXT NOT NULL DEFAULT (datetime("now"))
            )
        ');

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS contacts (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                vendor_id  INTEGER NOT NULL,
                name       TEXT NOT NULL DEFAULT "",
                email      TEXT NOT NULL DEFAULT "",
                phone      TEXT NOT NULL DEFAULT "",
                is_primary INTEGER NOT NULL DEFAULT 0,
                notes      TEXT NOT NULL DEFAULT "",
                FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
            )
        ');

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

        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_contacts_vendor ON contacts(vendor_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_typemap_vendor  ON vendor_type_map(vendor_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_areamap_vendor  ON vendor_area_map(vendor_id)');
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
