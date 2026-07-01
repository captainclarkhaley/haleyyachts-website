<?php
/**
 * DEPRECATED - use the consolidated secrets file instead.
 *
 * SMTP credentials are now shared by BOTH Broker Suite mailers (the Vendor app
 * auth mail AND this Pocket notification) in ONE file:
 *
 *     vendors/mail-secrets.php   (copied from vendors/mail-secrets.sample.php)
 *
 * Set the password there once and both mailers use it. This per-app file is
 * kept only as a backward-compatibility fallback: if vendors/mail-secrets.php
 * is absent, the mailer falls back to vendors/pocket/mail-config.php (this
 * file's non-sample copy). Prefer the consolidated file so there is only one
 * password to maintain.
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
