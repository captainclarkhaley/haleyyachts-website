<?php
/**
 * admin-guard.php - shared auth prologue for every Broker Suite admin page.
 *
 * Broker Suite admin pages live under /vendors/admin/ and are gated by the SAME
 * in-app session + is_admin flag the rest of the suite uses - NOT the old
 * password-protected /admin/ folder (that separate area is untouched by this
 * work and stays as-is). Include this file at the very top of an admin page,
 * BEFORE any output:
 *
 *     require_once __DIR__ . '/admin-guard.php';
 *     // $pdo and $gateUser are now available; a non-admin never gets here.
 *
 * The gate, in order:
 *   1. Not logged in                  -> ../login.html
 *   2. Owes a forced password change  -> ../change-password.html
 *   3. Logged in but NOT an admin     -> ../suite.php
 * Only an authenticated admin who has completed any required password change
 * falls through to the page body. All redirects happen before markup, so the
 * gate cannot be bypassed by disabling JavaScript.
 *
 * This include is reused by 2b (Staff Accounts) and 2c (Predefined Lists) so
 * the gate lives in exactly one place.
 *
 * Exposes to the including page:
 *   PDO   $pdo       the shared vendor/suite database handle (vdb_connect)
 *   array $gateUser  the resolved, active, admin user row from current_user()
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/vendors/api/auth-lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendors/api/db.php';

start_secure_session();
$pdo      = vdb_connect();
$gateUser = current_user($pdo);

// 1. Not authenticated -> shared /vendors/ login (one level up from /admin/).
if ($gateUser === null) {
    header('Location: ../login.html');
    exit;
}

// 2. Still owes a forced password change -> finish that first.
if ((int) $gateUser['must_change_password'] === 1) {
    header('Location: ../change-password.html');
    exit;
}

// 3. Authenticated but NOT an admin -> bounce back to the suite launcher.
//    A non-admin can never load ANY admin page, even by typing the URL.
$gateIsAdmin = isset($gateUser['is_admin']) && (int) $gateUser['is_admin'] === 1;
if (!$gateIsAdmin) {
    header('Location: ../suite.php');
    exit;
}

// From here down the including page runs with $pdo + $gateUser, guaranteed admin.
