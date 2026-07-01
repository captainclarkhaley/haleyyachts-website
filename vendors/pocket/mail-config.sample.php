<?php
/**
 * SMTP credentials for the Pocket Listings network-notification email.
 *
 * SETUP (one time, in cPanel File Manager - no Terminal needed):
 *   1. Copy this file to  mail-config.php  in this SAME folder
 *      (public_html/vendors/pocket/mail-config.php).
 *   2. Put the real no-reply@haleyyachts.com mailbox password on the
 *      'password' line below.
 *   3. Save. mail-config.php is gitignored, so the password never enters
 *      git and a git pull will never overwrite it.
 *
 * Defaults match a GoDaddy cPanel mailbox. If a send fails, the usual
 * alternates are: host 'localhost', or port 587 with 'secure' => 'tls'.
 */
return array(
    'host'              => 'mail.haleyyachts.com',   // SMTP server
    'port'              => 465,                       // 465 = SSL, 587 = TLS
    'secure'            => 'ssl',                     // 'ssl' or 'tls'
    'username'          => 'no-reply@haleyyachts.com',
    'password'          => 'PUT-THE-MAILBOX-PASSWORD-HERE',
    'allow_self_signed' => true,                      // relax cert check for same-server relay
);
