<?php
/**
 * mail-secrets.sample.php - SINGLE shared SMTP credentials file for ALL Broker
 * Suite app mail (both the Vendor app auth mail AND the Pocket Listings
 * network notification).
 *
 * ============================================================================
 * ONE-TIME SETUP (in cPanel File Manager - no Terminal / SSH needed)
 * ============================================================================
 *   1. Copy this file to  mail-secrets.php  in this SAME folder:
 *          public_html/vendors/mail-secrets.php
 *   2. Put the REAL no-reply@haleyyachts.com mailbox password on the
 *      'password' line below.
 *   3. Save. mail-secrets.php is gitignored, so the password never enters git
 *      and a cPanel git pull will never overwrite or clobber it.
 *
 * Both mailers read THIS ONE file, so there is only ever one password to set.
 *
 * ============================================================================
 * cPanel / GoDaddy defaults
 * ============================================================================
 * Primary (recommended): port 465 with implicit TLS ('secure' => 'ssl').
 * Fallback if 465 is blocked or refuses: port 587 with STARTTLS
 *   ('port' => 587, 'secure' => 'tls').
 * You can switch 465 <-> 587 here WITHOUT any code changes - host, port, and
 * encryption are all driven from this file.
 *
 * If the TLS handshake aborts on a same-server relay (cert name mismatch),
 * leave 'allow_self_signed' => true. That relaxation only affects the internal
 * submission handshake, not authentication.
 */
return array(
    'host'              => 'mail.haleyyachts.com',   // SMTP server hostname
    'port'              => 465,                       // 465 = SSL (primary), 587 = STARTTLS (fallback)
    'secure'            => 'ssl',                     // 'ssl' for 465, 'tls' for 587
    'username'          => 'no-reply@haleyyachts.com',
    'password'          => 'PUT-THE-MAILBOX-PASSWORD-HERE',
    'from_name'         => 'OneWater',                // display name on the From line
    'allow_self_signed' => true,                      // relax cert check for same-server relay
);
