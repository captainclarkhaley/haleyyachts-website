<?php
/**
 * vendor-diag.php - TEMPORARY diagnostic for the Vendor Database.
 *
 * Lives in /admin/ so it is behind the admin password. Loading it prints a
 * plain-text health report: PHP version, whether the SQLite driver is present,
 * the resolved paths, folder writability, and the result of an actual DB
 * connect attempt (with the real exception message if it fails).
 *
 * Once the database is working, this file can be deleted.
 */

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

echo "Vendor Database diagnostics\n";
echo "===========================\n\n";

echo "PHP version:        " . PHP_VERSION . "\n";
echo "pdo_sqlite loaded:  " . (extension_loaded('pdo_sqlite') ? 'YES' : 'NO  <-- problem if NO') . "\n";
echo "PDO drivers:        " . (class_exists('PDO') ? implode(', ', PDO::getAvailableDrivers()) : 'PDO class missing') . "\n\n";

$docroot = isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '(none)';
$dbphp   = $docroot . '/vendors/api/db.php';
$dataDir = $docroot . '/vendors/api/data';
$dbPath  = $dataDir . '/vendors.sqlite';

echo "DOCUMENT_ROOT:      " . $docroot . "\n";
echo "db.php path:        " . $dbphp . "\n";
echo "db.php exists:      " . (file_exists($dbphp) ? 'YES' : 'NO  <-- vendors/ not pulled?') . "\n";
echo "data dir:           " . $dataDir . "\n";
echo "data dir exists:    " . (is_dir($dataDir) ? 'YES' : 'NO') . "\n";
echo "data dir writable:  " . (is_dir($dataDir) && is_writable($dataDir) ? 'YES' : 'NO  <-- problem if NO') . "\n";
echo "db file exists:     " . (file_exists($dbPath) ? 'YES' : 'no (created on first connect)') . "\n";
if (file_exists($dbPath)) {
    echo "db file writable:   " . (is_writable($dbPath) ? 'YES' : 'NO  <-- problem if NO') . "\n";
}

echo "\nAttempting database connect...\n";
echo "------------------------------\n";
try {
    require_once $dbphp;
    if (!function_exists('vdb_connect')) {
        throw new RuntimeException('db.php loaded but vdb_connect() is not defined.');
    }
    $pdo = vdb_connect();
    $types  = (int) $pdo->query('SELECT COUNT(*) FROM vendor_types')->fetchColumn();
    $areas  = (int) $pdo->query('SELECT COUNT(*) FROM coverage_areas')->fetchColumn();
    $vendrs = (int) $pdo->query('SELECT COUNT(*) FROM vendors')->fetchColumn();
    echo "CONNECT OK\n";
    echo "vendor_types rows:   " . $types . "\n";
    echo "coverage_areas rows: " . $areas . "\n";
    echo "vendors rows:        " . $vendrs . "\n";
    echo "\nThe database is healthy. You can delete admin/vendor-diag.php now.\n";
} catch (Throwable $e) {
    echo "CONNECT FAILED\n";
    echo get_class($e) . ": " . $e->getMessage() . "\n\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
