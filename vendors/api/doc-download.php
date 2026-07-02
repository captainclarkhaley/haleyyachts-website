<?php
/**
 * doc-download.php - the ONLY way to fetch a vendor document.
 *
 * The document bytes live in vendors/api/docs/, a directory that DENIES all
 * direct web access (its .htaccess is Require-all-denied). This endpoint is the
 * single authenticated gateway to that store:
 *
 *   1. It requires a valid in-app session (any authenticated, non-must-change
 *      user may view + download - same audience as the rest of the app). An
 *      unauthenticated request gets a 401 and no bytes.
 *   2. It looks up the vendor_documents row by ?id=(int), resolves the stored
 *      file with basename() (so a tampered filename cannot escape the store),
 *      confirms the file exists, then streams it with the right Content-Type and
 *      an attachment Content-Disposition using a SANITIZED original name.
 *
 * This file emits raw bytes, NOT JSON, so it does its own auth handling rather
 * than reusing require_auth (which returns a JSON 401).
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/vendors/api/auth-lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendors/api/db.php';

start_secure_session();

/** Send a bare-text status and stop (this endpoint never returns JSON). */
function dd_stop($status, $message)
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo $message;
    exit;
}

try {
    $pdo = vdb_connect();

    // Require a valid session. Any authenticated user may download; a user still
    // owing a forced password change is treated as not-yet-authenticated here.
    $user = current_user($pdo);
    if ($user === null) {
        dd_stop(401, 'Not authenticated.');
    }
    if ((int) $user['must_change_password'] === 1) {
        dd_stop(403, 'You must set a new password before using the app.');
    }

    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id <= 0) {
        dd_stop(400, 'Missing document id.');
    }

    $stmt = $pdo->prepare('SELECT filename, original_name FROM vendor_documents WHERE id = ?');
    $stmt->execute(array($id));
    $row = $stmt->fetch();
    if (!$row) {
        dd_stop(404, 'Document not found.');
    }

    // Resolve the stored file with basename() so nothing in the DB value can be
    // used to traverse out of the private docs directory.
    $safe = basename((string) $row['filename']);
    if ($safe === '' || $safe === '.' || $safe === '..') {
        dd_stop(404, 'Document not found.');
    }
    $path = $_SERVER['DOCUMENT_ROOT'] . '/vendors/api/docs/' . $safe;
    if (!is_file($path)) {
        dd_stop(404, 'Document file is missing.');
    }

    // Content-Type from the stored extension (we only ever store pdf/jpg/png/webp).
    $ext = strtolower(pathinfo($safe, PATHINFO_EXTENSION));
    switch ($ext) {
        case 'pdf':  $ctype = 'application/pdf'; break;
        case 'jpg':
        case 'jpeg': $ctype = 'image/jpeg'; break;
        case 'png':  $ctype = 'image/png'; break;
        case 'webp': $ctype = 'image/webp'; break;
        default:     $ctype = 'application/octet-stream'; break;
    }

    // Build a header-safe download filename from the stored original name. Strip
    // control chars, quotes, and anything that could break the header; keep an
    // extension that matches the real type so the OS opens it correctly.
    $download = basename((string) $row['original_name']);
    $download = preg_replace('/[\x00-\x1F\x7F"\\\\\/]+/', '', $download);
    $download = trim($download);
    if ($download === '') {
        $download = 'document.' . $ext;
    }

    // Fresh output buffer so nothing (a stray BOM, a notice) corrupts the bytes.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: ' . $ctype);
    header('Content-Disposition: attachment; filename="' . $download . '"');
    header('Content-Length: ' . (string) filesize($path));
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');

    readfile($path);
    exit;
} catch (Throwable $e) {
    error_log('doc-download error: ' . $e->getMessage());
    dd_stop(500, 'Server error.');
}
