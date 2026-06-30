<?php
/**
 * help.php - in-app Help / user manual for the staff Vendor app.
 *
 * Login-gated behind the SAME server-side auth gate as index.php: an
 * unauthenticated visitor is redirected to login.html BEFORE any markup, and a
 * user who still owes a forced password change is sent to change-password.html.
 *
 * This page is the LIVING user manual. The source draft lives at
 * docs/vendor-app-manual.md (blocked from the public site); future copy edits can
 * be made directly here.
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendors/api/auth-lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendors/api/db.php';

start_secure_session();
$gateUser = current_user(vdb_connect());
if ($gateUser === null) {
    header('Location: login.html');
    exit;
}
// Forced first-login password change: an account flagged must_change_password
// (new account or admin reset) cannot reach the app until it sets its own
// password. Redirect BEFORE any markup, exactly as index.php does.
if ((int) $gateUser['must_change_password'] === 1) {
    header('Location: change-password.html');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OneWater Vendor Database - Help</title>
    <meta name="robots" content="noindex, nofollow">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="../favicon.ico" sizes="any">
    <style>
        :root {
            --navy:#0a1628; --cyan:#21cbea; --cyan-d:#1aa8c4;
            --ink:#333; --muted:#666; --line:#e5e5e5; --canvas:#f4f6f8;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Open Sans', Arial, sans-serif;
            background: var(--canvas); margin: 0; color: var(--ink); line-height: 1.7;
            padding: 0 0 60px;
        }

        /* ----- Header ----- */
        .help-header {
            background: var(--navy); color: #fff;
            padding: 30px 24px 26px; text-align: center;
        }
        .help-logo {
            display: block; height: 44px; width: auto; max-width: 78%;
            margin: 0 auto 14px;
        }
        .help-header h1 {
            font-size: 1.5rem; font-weight: 300; text-transform: uppercase;
            letter-spacing: 2.5px; margin: 0;
        }
        .help-header h1 strong { font-weight: 700; color: var(--cyan); }
        .help-header .accent-line {
            width: 60px; height: 3px; background: var(--cyan); margin: 12px auto 12px;
        }
        .help-header p {
            margin: 0; font-size: 0.88rem; color: rgba(255,255,255,0.75); font-style: italic;
        }

        /* ----- Page shell ----- */
        .help-wrap {
            max-width: 820px; margin: 0 auto; padding: 0 22px;
        }
        .help-card {
            background: #fff; border: 1px solid var(--line); border-radius: 8px;
            box-shadow: 0 2px 14px rgba(0,0,0,0.05);
            padding: 36px 40px 44px; margin-top: 28px;
        }

        /* ----- Back link rows ----- */
        .help-backlink {
            text-align: center; margin: 22px 0 0; font-size: 0.9rem;
        }
        .help-backlink a {
            color: var(--cyan-d); text-decoration: none; font-weight: 600;
        }
        .help-backlink a:hover { text-decoration: underline; }
        .help-backlink.bottom { margin: 30px 0 0; }

        /* ----- Typography ----- */
        .help-card .lead {
            font-size: 1.02rem; color: var(--ink); margin: 0 0 8px;
        }
        .help-card h2 {
            font-size: 1.25rem; font-weight: 700; color: var(--navy);
            margin: 40px 0 10px; padding-top: 14px; border-top: 1px solid var(--line);
            scroll-margin-top: 16px;
        }
        .help-card h2:first-of-type { border-top: none; padding-top: 0; }
        .help-card h3 {
            font-size: 1.02rem; font-weight: 700; color: var(--navy);
            margin: 26px 0 8px; scroll-margin-top: 16px;
        }
        .help-card p { margin: 0 0 14px; }
        .help-card ul, .help-card ol { margin: 0 0 16px; padding-left: 24px; }
        .help-card li { margin-bottom: 7px; }
        .help-card li > ul, .help-card li > ol { margin: 7px 0 4px; }
        .help-card strong { color: var(--navy); font-weight: 600; }
        .help-card code {
            font-family: 'SFMono-Regular', Menlo, Consolas, monospace;
            font-size: 0.86em; background: #eef1f5; color: #33415c;
            border-radius: 3px; padding: 1px 5px;
        }
        .help-card a { color: var(--cyan-d); }

        /* ----- Table of contents ----- */
        .help-toc {
            background: var(--canvas); border: 1px solid var(--line);
            border-radius: 6px; padding: 18px 22px; margin: 0 0 30px;
        }
        .help-toc h2 {
            border: none; padding: 0; margin: 0 0 10px;
            font-size: 0.78rem; text-transform: uppercase; letter-spacing: 1.2px;
            color: var(--muted); font-weight: 700;
        }
        .help-toc ul { list-style: none; margin: 0; padding: 0; columns: 2; column-gap: 30px; }
        .help-toc li { margin-bottom: 6px; break-inside: avoid; }
        .help-toc a { color: var(--cyan-d); text-decoration: none; }
        .help-toc a:hover { text-decoration: underline; }

        /* ----- Screenshot placeholder boxes ----- */
        .help-shot {
            display: flex; align-items: center; justify-content: center;
            text-align: center; min-height: 120px;
            border: 2px dashed #b9c4d0; border-radius: 6px;
            background: #f0f3f7; color: var(--muted);
            padding: 22px 24px; margin: 16px 0 22px;
            font-size: 0.9rem; font-style: italic;
        }
        .help-shot::before {
            content: "Screenshot"; display: inline-block;
            font-size: 0.62rem; font-style: normal; font-weight: 700;
            text-transform: uppercase; letter-spacing: 1px;
            color: #8a98a8; margin-right: 10px;
            border: 1px solid #c4cedb; border-radius: 3px; padding: 2px 7px;
        }

        /* ----- Code block (Copy-for-Email example output) ----- */
        .help-codeblock {
            font-family: 'SFMono-Regular', Menlo, Consolas, monospace;
            font-size: 0.84rem; line-height: 1.55; color: #f4f6f8;
            background: var(--navy); border-radius: 6px;
            padding: 16px 18px; margin: 12px 0 22px;
            overflow-x: auto; white-space: pre;
        }

        /* ----- Divider used between major sections (mirrors the manual's ---) ----- */
        hr.help-rule {
            border: none; border-top: 1px solid var(--line); margin: 36px 0 0;
        }

        /* ----- Responsive ----- */
        @media (max-width: 640px) {
            .help-header { padding: 24px 18px 22px; }
            .help-header h1 { font-size: 1.2rem; letter-spacing: 1.6px; }
            .help-logo { height: 36px; }
            .help-wrap { padding: 0 14px; }
            .help-card { padding: 24px 20px 30px; }
            .help-toc ul { columns: 1; }
            .help-card h2 { font-size: 1.15rem; }
        }
    </style>
</head>
<body>

<header class="help-header">
    <img class="help-logo" src="../images/email/owyg-banner-reverse.png" alt="One Water Yacht Group">
    <h1>Vendor Database <strong>Help</strong></h1>
    <div class="accent-line"></div>
    <p>OneWater Vendor Database - staff user guide</p>
</header>

<div class="help-wrap">

    <div class="help-backlink top">
        <a href="index.php">&larr; Back to Vendor Database</a>
    </div>

    <div class="help-card">

        <p class="lead">The OneWater Vendor Database (the "Vendor App") is the OWYG staff directory of surveyors, mechanics, and trade vendors. Everyone on the team uses the same shared list, so when one person adds a good surveyor or rates a mechanic, the whole group sees it. This guide walks through signing in, finding a vendor, adding and editing records, rating vendors, and pulling vendor info into a client email. A shorter section at the end covers the admin tools for Clark and Annika.</p>

        <!-- IMG: main-screen -->
        <div class="help-shot">the Vendor Database main screen</div>

        <!-- ===== Table of contents ===== -->
        <nav class="help-toc" aria-label="Contents">
            <h2>Contents</h2>
            <ul>
                <li><a href="#getting-in">Getting in (signing in and out)</a></li>
                <li><a href="#forgot-your-password">Forgot your password</a></li>
                <li><a href="#finding-a-vendor">Finding a vendor</a></li>
                <li><a href="#on-a-phone">On a phone</a></li>
                <li><a href="#viewing-a-vendor">Viewing a vendor</a></li>
                <li><a href="#adding-a-vendor">Adding a vendor</a></li>
                <li><a href="#editing-a-vendor">Editing a vendor</a></li>
                <li><a href="#rating-a-vendor">Rating a vendor</a></li>
                <li><a href="#deleting-a-vendor">Deleting a vendor</a></li>
                <li><a href="#copying-vendors-into-an-email">Copying vendors into an email</a></li>
                <li><a href="#my-profile">My Profile</a></li>
                <li><a href="#for-administrators">For Administrators</a></li>
                <li><a href="#need-help">Need help?</a></li>
            </ul>
        </nav>

        <!-- ===== Getting in ===== -->
        <h2 id="getting-in">Getting in</h2>

        <h3>Signing in</h3>
        <ol>
            <li>Open the Vendor App. You will land on the sign-in screen.</li>
            <li>Enter your <strong>Account ID</strong>. This should match your OWYG email address.</li>
            <li>Enter your <strong>password</strong>.</li>
            <li>Click <strong>Sign In</strong>.</li>
        </ol>

        <!-- IMG: sign-in -->
        <div class="help-shot">the sign-in screen with Account ID and Password fields</div>

        <h3>First-time sign-in (set your own password)</h3>
        <p>The very first time you sign in, you use the temporary password an administrator emailed you. The app then requires you to set your own password before you can go any further.</p>
        <ol>
            <li>Sign in with your Account ID and the temporary password.</li>
            <li>On the "Set Your Password" screen, type a new password (at least 8 characters), then type it again to confirm.</li>
            <li>Click <strong>Set Password &amp; Continue</strong>. You go straight into the app.</li>
        </ol>
        <p>This same step happens again if an administrator ever resets your password for you. There is no way around it except to sign out, so plan to set a password you will remember.</p>

        <!-- IMG: set-password -->
        <div class="help-shot">the "Set Your Password" screen</div>

        <h3>Signing out</h3>
        <p>Click <strong>Log out</strong> in the top-right corner whenever you are done. This ends your session right away.</p>

        <h3>The 10-minute inactivity timeout</h3>
        <p>For security, the app signs you out after 10 minutes with no activity (no clicks, typing, or scrolling).</p>
        <ul>
            <li>At 9 minutes, a <strong>"Still there?"</strong> box appears with a 60-second countdown.</li>
            <li>Click <strong>Stay signed in</strong> to keep working. The timer resets.</li>
            <li>If you do nothing, the app signs you out and returns you to the sign-in screen at the end of the countdown.</li>
        </ul>
        <p>As long as you are actively clicking and typing, you stay signed in.</p>

        <!-- IMG: idle-warning -->
        <div class="help-shot">the "Still there?" inactivity warning box</div>

        <hr class="help-rule">

        <!-- ===== Forgot your password ===== -->
        <h2 id="forgot-your-password">Forgot your password</h2>
        <p>If you cannot remember your password:</p>
        <ol>
            <li>On the sign-in screen, click <strong>Forgot password?</strong></li>
            <li>Enter the email on your account.</li>
            <li>Click <strong>Send Reset Link</strong>.</li>
            <li>Check your email for a message from <strong>no-reply@haleyyachts.com</strong> with a reset link.</li>
            <li>Click the link, set a new password (at least 8 characters, entered twice), and you are sent back to sign in.</li>
        </ol>
        <p>The reset link <strong>expires in 1 hour</strong>. If it expires, just request a new one from the sign-in screen.</p>
        <p>For privacy, the app always shows the same confirmation message whether or not the email is on file, so the screen will not tell you if you typed the wrong address. If no email arrives, double-check the address with an administrator.</p>

        <hr class="help-rule">

        <!-- ===== Finding a vendor ===== -->
        <h2 id="finding-a-vendor">Finding a vendor</h2>
        <p>The top of the main screen is the search-and-filter bar. You can use one filter or stack several together. The result count under the bar updates as you go.</p>

        <!-- IMG: filter-bar -->
        <div class="help-shot">the search-and-filter bar with all four filters labeled</div>

        <h3>Search by name</h3>
        <p>The <strong>Vendor or Contact Name</strong> box matches either the vendor's name or the name of one of its contacts. Type any part of the name and the list narrows as you type.</p>

        <h3>Filter by Vendor Type</h3>
        <ol>
            <li>Use the <strong>Search types...</strong> box to narrow the long list of types.</li>
            <li>Check one or more types.</li>
            <li>The <strong>Type match</strong> toggle controls how multiple types combine:
                <ul>
                    <li><strong>Any</strong> (the default): show vendors that have at least one of the checked types.</li>
                    <li><strong>All</strong>: show only vendors that have every checked type.</li>
                </ul>
            </li>
        </ol>

        <h3>Filter by Coverage Area</h3>
        <p>Coverage areas are organized in tiers, from broad to specific:</p>
        <ul>
            <li><strong>USA</strong> (Nationwide) at the top</li>
            <li><strong>State</strong></li>
            <li><strong>Region</strong></li>
            <li><strong>County</strong></li>
        </ul>
        <p>The list is indented to show this structure. Checking an area is smart about the tiers in both directions:</p>
        <ul>
            <li>Pick a <strong>broad</strong> area (like a state) and you also get vendors tagged to areas <strong>inside</strong> it (its regions and counties).</li>
            <li>Pick a <strong>specific</strong> area (like a county) and you also get vendors tagged to the broader areas that <strong>contain</strong> it (its region, state).</li>
            <li>Any vendor tagged <strong>Nationwide</strong> shows up for any area you pick, since they cover everywhere.</li>
        </ul>
        <p>So you rarely have to guess the exact tier. Pick the area you care about and the app fills in the rest.</p>

        <!-- IMG: coverage-filter -->
        <div class="help-shot">the Coverage Area filter showing the indented USA / State / Region / County tiers</div>

        <h3>Filter by Rating</h3>
        <p>Check one or more rating buckets to narrow the list:</p>
        <ul>
            <li>5 stars, 4 stars, 3 stars, 2 stars, 1 star</li>
            <li><strong>Not rated</strong> for vendors that have no ratings yet</li>
        </ul>
        <p>A vendor lands in a bucket based on its average rounded to the nearest whole star.</p>

        <h3>Results, sorting, and clearing</h3>
        <ul>
            <li>The <strong>result count</strong> above the table tells you how many vendors match.</li>
            <li>Click any <strong>column header</strong> (Vendor Name, Type(s), Coverage Area(s), Primary Phone, Primary Email, Contacts, Avg Rating) to sort by it. Click the same header again to flip between ascending and descending. A small arrow shows the active sort.</li>
            <li>Click <strong>Clear</strong> to reset every filter, the search boxes, and the sort, and start fresh.</li>
        </ul>

        <hr class="help-rule">

        <!-- ===== On a phone ===== -->
        <h2 id="on-a-phone">On a phone</h2>
        <p>The app adjusts for small screens:</p>
        <ul>
            <li>Each result shows as a stacked <strong>card</strong> instead of a wide table row, with the vendor name as the card title.</li>
            <li>The filter bar stacks into a single column so everything is easy to tap.</li>
            <li>A <strong>Select all shown</strong> control appears above the results for the copy-for-email feature (see below).</li>
        </ul>
        <p>Everything else works the same as on a computer. Tap a vendor name to open it.</p>

        <!-- IMG: phone-cards -->
        <div class="help-shot">the phone view showing stacked vendor cards</div>

        <hr class="help-rule">

        <!-- ===== Viewing a vendor ===== -->
        <h2 id="viewing-a-vendor">Viewing a vendor</h2>
        <p>Click (or tap) a <strong>vendor name</strong> in the results to open its full detail view. You will see:</p>
        <ul>
            <li>The rating summary at the top (average and number of ratings)</li>
            <li>Name, address, primary phone, primary email</li>
            <li>Vendor types and coverage areas</li>
            <li>Vendor notes</li>
            <li>All <strong>contacts</strong> on file, with the primary contact marked</li>
            <li>The <strong>ratings</strong> section, where you can rate the vendor and see its rating history</li>
        </ul>
        <p>Phone numbers and email addresses are clickable, so on a phone you can tap to call or email.</p>
        <p>From here you can also <strong>Edit</strong> or <strong>Delete</strong> the vendor, or <strong>Close</strong> to go back to the list.</p>

        <!-- IMG: vendor-detail -->
        <div class="help-shot">the vendor detail view</div>

        <hr class="help-rule">

        <!-- ===== Adding a vendor ===== -->
        <h2 id="adding-a-vendor">Adding a vendor</h2>
        <ol>
            <li>Click <strong>+ Add Vendor</strong> at the top of the main screen.</li>
            <li>Fill in the fields:
                <ul>
                    <li><strong>Vendor Name</strong> (required)</li>
                    <li><strong>Address</strong></li>
                    <li><strong>Primary Phone</strong> and <strong>Primary Email</strong></li>
                    <li><strong>Vendor Notes</strong> (up to 150 characters)</li>
                    <li><strong>Vendor Types</strong> - check all that apply</li>
                    <li><strong>Coverage Areas</strong> - check all that apply</li>
                </ul>
            </li>
            <li>Add one or more <strong>contacts</strong> (optional):
                <ul>
                    <li>Click <strong>+ Add Contact</strong>.</li>
                    <li>Enter the contact's <strong>name, phone, email</strong>, and optional notes.</li>
                    <li>Mark one contact as the <strong>Primary contact</strong> if you like. The first contact you add is set as primary automatically; you can change it.</li>
                    <li>Use <strong>Remove</strong> to drop a contact row.</li>
                </ul>
            </li>
            <li>Click <strong>Save Vendor</strong>.</li>
        </ol>

        <!-- IMG: add-vendor -->
        <div class="help-shot">the Add Vendor form with the contacts section</div>

        <h3>The duplicate check</h3>
        <p>When you save a brand-new vendor, the app checks whether it might already exist. If the name matches an existing vendor, or a phone number matches, it pauses and shows a <strong>Possible duplicate</strong> panel listing the likely matches and why each one matched. You have three choices:</p>
        <ul>
            <li><strong>Open</strong> an existing match to go to that record instead. Note: this discards what you just typed.</li>
            <li><strong>Create anyway</strong> to save your new vendor despite the match.</li>
            <li><strong>Keep editing</strong> to go back to your form with everything you typed still there.</li>
        </ul>
        <p>This check only runs when you add a new vendor, not when you edit an existing one.</p>

        <!-- IMG: duplicate-panel -->
        <div class="help-shot">the "Possible duplicate" panel</div>

        <hr class="help-rule">

        <!-- ===== Editing a vendor ===== -->
        <h2 id="editing-a-vendor">Editing a vendor</h2>
        <ol>
            <li>Open the vendor (click its name).</li>
            <li>Click <strong>Edit</strong>.</li>
            <li>Change any fields, types, areas, or contacts.</li>
            <li>Click <strong>Save Vendor</strong>.</li>
        </ol>

        <hr class="help-rule">

        <!-- ===== Rating a vendor ===== -->
        <h2 id="rating-a-vendor">Rating a vendor</h2>
        <p>Ratings are shared with the whole team and now show <strong>who left them</strong>, so they build up a useful track record over time.</p>
        <ol>
            <li>Open the vendor and scroll to the <strong>Ratings</strong> section.</li>
            <li>Click the stars to choose a rating from <strong>1 to 5</strong>.</li>
            <li>Optionally type a short <strong>note</strong> (up to 150 characters).</li>
            <li>Click <strong>Submit rating</strong>.</li>
        </ol>
        <p>Your rating is added to the history with your name and the date, and the vendor's average updates right away. Each entry in the history has a <strong>Delete</strong> button if a rating needs to be removed.</p>

        <!-- IMG: ratings -->
        <div class="help-shot">the Ratings section with the star picker and history</div>

        <hr class="help-rule">

        <!-- ===== Deleting a vendor ===== -->
        <h2 id="deleting-a-vendor">Deleting a vendor</h2>
        <p>How delete works depends on whether you are an administrator:</p>
        <ul>
            <li><strong>Administrators</strong> delete the vendor directly. You get a confirmation prompt, and on OK the vendor and its contacts are removed. This cannot be undone.</li>
            <li><strong>Everyone else</strong> cannot delete a vendor. When you click Delete, the app tells you an administrator will be notified, and it emails your request to the admin. The vendor is <strong>not</strong> deleted; an admin decides.</li>
        </ul>
        <p>To start either way, open the vendor and click <strong>Delete</strong>.</p>

        <hr class="help-rule">

        <!-- ===== Copying vendors into an email ===== -->
        <h2 id="copying-vendors-into-an-email">Copying vendors into an email</h2>
        <p>This feature builds clean contact text for several vendors at once so you can paste it into a client email.</p>
        <ol>
            <li>In the results, <strong>check the box</strong> next to each vendor you want. To grab them all, use the <strong>select-all</strong> checkbox in the table header (or <strong>Select all shown</strong> on a phone).</li>
            <li>Click <strong>Copy ... for Email</strong> (the button shows the count, like "Copy 3 for Email").</li>
            <li>The text is copied to your clipboard, and a review box opens so you can see exactly what was copied.</li>
            <li>Click into your email and paste with <strong>Cmd+V</strong> (Mac) or <strong>Ctrl+V</strong> (Windows).</li>
        </ol>
        <p>Each vendor comes out as up to two lines:</p>
        <ul>
            <li><strong>Line 1:</strong> vendor name, primary phone, primary email</li>
            <li><strong>Line 2</strong> (only if the vendor has a primary contact): <code>Contact: name, phone, email</code></li>
        </ul>
        <p>So a vendor with a primary contact pastes in looking like this:</p>
        <div class="help-codeblock">Bayside Marine Survey, (954) 555-0182, info@baysidesurvey.com
Contact: Dana Reyes, (954) 555-0147, dana@baysidesurvey.com</div>
        <p>Vendors are separated by a blank line, and any missing piece (say, no email) is simply left out so you never get a stray comma.</p>
        <p>Closing the review box clears your selection, so start fresh next time.</p>

        <!-- IMG: copy-email -->
        <div class="help-shot">the Copy for Email review box with sample text</div>

        <hr class="help-rule">

        <!-- ===== My Profile ===== -->
        <h2 id="my-profile">My Profile</h2>
        <p>Click <strong>My Profile</strong> in the top-right corner to manage your own account.</p>
        <p>You can update your:</p>
        <ul>
            <li><strong>Name</strong></li>
            <li><strong>Email</strong></li>
            <li><strong>Cell</strong></li>
            <li><strong>Home Office</strong></li>
        </ul>
        <p>Your <strong>Account ID</strong> is shown but cannot be changed here; an administrator assigns it.</p>
        <p>The same screen has a <strong>Change Password</strong> section. Enter your current password, then your new password twice (at least 8 characters), and click <strong>Change Password</strong>.</p>

        <!-- IMG: my-profile -->
        <div class="help-shot">the My Profile screen with profile fields and the change-password section</div>

        <hr class="help-rule">

        <!-- ===== For Administrators ===== -->
        <h2 id="for-administrators">For Administrators</h2>
        <p>This section is for Clark and Annika. The admin tools live in the password-protected admin area, separate from the staff app.</p>

        <h3>Managing staff accounts</h3>
        <p>Open <strong>Staff Accounts</strong> (<code>admin/users.html</code>).</p>
        <p><strong>Create an account:</strong></p>
        <ol>
            <li>Click <strong>+ New Account</strong>.</li>
            <li>Fill in:
                <ul>
                    <li><strong>Account ID</strong> - use the person's OWYG email.</li>
                    <li><strong>Name</strong></li>
                    <li><strong>Email</strong> - used for self-service password resets.</li>
                    <li><strong>Cell</strong></li>
                    <li><strong>Home Office</strong></li>
                    <li><strong>Admin privileges</strong> - see below.</li>
                    <li><strong>Initial Password</strong> - at least 8 characters.</li>
                </ul>
            </li>
            <li>Click <strong>Save</strong>.</li>
        </ol>
        <p>The new user is emailed their temporary password and is required to change it the first time they sign in.</p>

        <!-- IMG: new-account -->
        <div class="help-shot">the New Account form</div>

        <p><strong>Admin privileges:</strong> the <strong>Administrator</strong> checkbox controls whether someone can delete vendors directly. Admins can delete; non-admins can only request a delete (which notifies an admin by email). Everyone defaults to non-admin, so be sure to flag at least one admin, or nobody can delete vendors.</p>

        <p><strong>Managing existing accounts:</strong> each row in the accounts table has buttons to:</p>
        <ul>
            <li><strong>Edit</strong> the account details (the Admin checkbox is here too). The password is not edited from Edit; use Reset PW.</li>
            <li><strong>Reset PW</strong> to set a new password for someone. They are emailed the new temporary password and must change it on their next sign-in. This also cancels any pending email-reset links.</li>
            <li><strong>Disable / Enable</strong> to block or restore login without deleting the account.</li>
            <li><strong>Delete</strong> to remove the account permanently.</li>
        </ul>

        <h3>Managing the predefined lists</h3>
        <p>Open <strong>Vendor Lists</strong> (<code>admin/vendor-lists.html</code>). These two lists are what staff choose from in the app; staff cannot edit them.</p>

        <!-- IMG: vendor-lists -->
        <div class="help-shot">the Vendor Lists admin page with Vendor Types and Coverage Areas</div>

        <p><strong>Vendor Types</strong> is a flat, alphabetical list. Use the box to add a type, <strong>Rename</strong> to change one, and <strong>Delete</strong> to remove one. If a type is in use, deleting it only unassigns it from those vendors; the vendors themselves stay.</p>

        <p><strong>Coverage Areas</strong> is the tiered tree: <strong>Nationwide (USA) / State / Region / County</strong>. To add an area:</p>
        <ol>
            <li>Type the <strong>area name</strong>.</li>
            <li>Pick the <strong>Tier</strong> (County, Region, State, or Nationwide).</li>
            <li>For a Region or County, choose its <strong>Parent</strong> (the broader area it sits under). State and Nationwide have no parent.</li>
            <li>Click <strong>Add area</strong>.</li>
        </ol>
        <p>To <strong>edit or re-parent</strong> an area, click <strong>Edit</strong> on its row and follow the prompts for name, tier, and parent. As with types, deleting an in-use area only unassigns it from vendors; it does not delete them.</p>
        <p>The page also has an audit panel listing any vendors that have <strong>no coverage area</strong>, so you can find and tag them.</p>

        <h3>Export CSV (admin only)</h3>
        <p>The <strong>Export CSV</strong> button (top-right of the staff app, visible only to admins) downloads the vendors that are <strong>currently shown</strong>. With no filters active, that is the whole database, so it doubles as a backup. With filters applied, you export just that filtered slice.</p>

        <h3>Delete-request notifications</h3>
        <p>When a non-admin requests a vendor delete, the system emails the request to the admin notification address so an admin can act on it.</p>

        <h3>A note on system emails</h3>
        <p>All system emails (onboarding/temporary passwords, password resets, and delete-request notifications) are sent from <strong>no-reply@haleyyachts.com</strong>. Let staff know to expect mail from that address and to check spam if it does not arrive.</p>

        <hr class="help-rule">

        <!-- ===== Need help? ===== -->
        <h2 id="need-help">Need help?</h2>
        <p>If something is not working or you are locked out, contact your administrator (Clark or Annika) and they can reset your access.</p>

    </div>

    <div class="help-backlink bottom">
        <a href="index.php">&larr; Back to Vendor Database</a>
    </div>

</div>

</body>
</html>
