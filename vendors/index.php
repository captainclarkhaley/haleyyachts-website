<?php
/**
 * index.php - staff Vendor app entry page WITH a server-side auth gate.
 *
 * This file was index.html. It is now PHP so an unauthenticated visitor is
 * redirected to the login page BEFORE any markup is sent - the gate cannot be
 * bypassed by disabling JavaScript. The data API (api/api.php) enforces the same
 * check independently, so this redirect is convenience + defense in depth.
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendors/api/auth-lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendors/api/db.php';

start_secure_session();
if (current_user(vdb_connect()) === null) {
    header('Location: login.html');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Database - Haley Yachts</title>
    <meta name="robots" content="noindex, nofollow">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="../favicon.ico" sizes="any">
    <link rel="stylesheet" href="vendors.css">
</head>
<body>

<header class="vdb-header">
    <div class="vdb-userbar" id="userBar" hidden>
        <span class="vdb-user-info" id="userInfo"></span>
        <button type="button" class="vdb-logout" id="btnProfile">My Profile</button>
        <button type="button" class="vdb-logout" id="btnLogout">Log out</button>
    </div>
    <h1>HALEY <strong>YACHTS</strong> VENDOR DATABASE</h1>
    <div class="accent-line"></div>
    <p>Staff directory of surveyors, mechanics, and trade vendors</p>
</header>

<div class="vdb-wrap">

    <!-- Filter bar -->
    <section class="vdb-filters" aria-label="Filters">
        <div class="vdb-filter-grid">
            <div class="vdb-field">
                <label for="fName">Vendor Name</label>
                <input type="text" id="fName" placeholder="Search by name...">
            </div>

            <div class="vdb-field">
                <label for="fTypeSearch">Vendor Type</label>
                <input type="text" id="fTypeSearch" class="vdb-multi-search" placeholder="Search types..."
                    aria-label="Filter the vendor type options">
                <div class="vdb-multi" id="fTypes" role="group" aria-label="Vendor type filter"></div>
                <div class="vdb-modetoggle">
                    <span>Type match:</span>
                    <span class="seg" role="group" aria-label="Type match mode">
                        <button type="button" id="modeAll" class="active" data-mode="all">All</button>
                        <button type="button" id="modeAny" data-mode="any">Any</button>
                    </span>
                </div>
            </div>

            <div class="vdb-field">
                <label for="fAreaSearch">Coverage Area</label>
                <input type="text" id="fAreaSearch" class="vdb-multi-search" placeholder="Search areas..."
                    aria-label="Filter the coverage area options">
                <div class="vdb-multi" id="fAreas" role="group" aria-label="Coverage area filter"></div>
            </div>

            <div class="vdb-field vdb-field-rating">
                <label>Rating</label>
                <div class="vdb-multi" id="fRating" role="group" aria-label="Rating filter">
                    <label><input type="checkbox" value="5"> 5 stars</label>
                    <label><input type="checkbox" value="4"> 4 stars</label>
                    <label><input type="checkbox" value="3"> 3 stars</label>
                    <label><input type="checkbox" value="2"> 2 stars</label>
                    <label><input type="checkbox" value="1"> 1 star</label>
                    <label><input type="checkbox" value="0"> Not rated</label>
                </div>
            </div>
        </div>

        <div class="vdb-filter-actions">
            <div class="vdb-result-count" id="resultCount">Loading...</div>
            <div>
                <button type="button" class="btn btn-export" id="btnExport"
                    title="Exports the vendors currently shown. With no filters active that is the full database, so it doubles as a backup.">Export CSV</button>
                <button type="button" class="btn btn-ghost" id="btnClear">Clear</button>
                <button type="button" class="btn btn-primary" id="btnAdd">+ Add Vendor</button>
            </div>
        </div>
    </section>

    <!-- Results -->
    <div class="vdb-table-wrap">
        <table class="vdb-table">
            <thead>
                <tr>
                    <th class="vdb-th-sort" data-sort="name" role="button" tabindex="0"
                        aria-label="Vendor Name, click to sort">Vendor Name <span class="sort-ind"></span></th>
                    <th class="vdb-th-sort" data-sort="types" role="button" tabindex="0"
                        aria-label="Types, click to sort">Type(s) <span class="sort-ind"></span></th>
                    <th class="vdb-th-sort" data-sort="areas" role="button" tabindex="0"
                        aria-label="Coverage Areas, click to sort">Coverage Area(s) <span class="sort-ind"></span></th>
                    <th class="vdb-th-sort" data-sort="phone" role="button" tabindex="0"
                        aria-label="Primary Phone, click to sort">Primary Phone <span class="sort-ind"></span></th>
                    <th class="vdb-th-sort" data-sort="email" role="button" tabindex="0"
                        aria-label="Primary Email, click to sort">Primary Email <span class="sort-ind"></span></th>
                    <th class="vdb-th-sort" data-sort="contacts" role="button" tabindex="0"
                        aria-label="Contacts, click to sort">Contacts <span class="sort-ind"></span></th>
                    <th class="vdb-th-sort" data-sort="rating" role="button" tabindex="0"
                        aria-label="Average rating, click to sort">Avg Rating <span class="sort-ind"></span></th>
                </tr>
            </thead>
            <tbody id="resultsBody">
                <tr><td colspan="7" class="vdb-empty">Loading vendors...</td></tr>
            </tbody>
        </table>
    </div>

    <div class="vdb-foot-link">
        <a href="../index.html">&larr; Return to public site</a>
    </div>
</div>

<!-- Detail view (read-only) -->
<div class="vdb-overlay" id="detailOverlay" aria-hidden="true">
    <div class="vdb-modal" role="dialog" aria-modal="true" aria-labelledby="detailTitle">
        <div class="vdb-modal-head">
            <h2 id="detailTitle">Vendor</h2>
            <button type="button" class="vdb-modal-close" id="detailClose" aria-label="Close">&times;</button>
        </div>
        <div class="vdb-modal-body" id="detailBody"></div>
        <div class="vdb-modal-foot vdb-modal-foot-split">
            <button type="button" class="btn btn-danger" id="btnDetailDelete">Delete</button>
            <div class="vdb-foot-right">
                <button type="button" class="btn btn-ghost" id="btnDetailClose">Close</button>
                <button type="button" class="btn btn-primary" id="btnDetailEdit">Edit</button>
            </div>
        </div>
    </div>
</div>

<!-- Add / Edit modal -->
<div class="vdb-overlay" id="vendorOverlay" aria-hidden="true">
    <div class="vdb-modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="vdb-modal-head">
            <h2 id="modalTitle">Add Vendor</h2>
            <button type="button" class="vdb-modal-close" id="modalClose" aria-label="Close">&times;</button>
        </div>
        <div class="vdb-modal-body">
            <div class="vdb-notice error" id="formError"></div>

            <form class="vdb-form" id="vendorForm" autocomplete="off">
                <input type="hidden" id="vId">

                <div class="row">
                    <label for="vName">Vendor Name *</label>
                    <input type="text" id="vName" required>
                </div>

                <div class="row">
                    <label for="vAddress">Address</label>
                    <input type="text" id="vAddress">
                </div>

                <div class="row row-2">
                    <div>
                        <label for="vPhone">Primary Phone</label>
                        <input type="text" id="vPhone" autocomplete="off">
                    </div>
                    <div>
                        <label for="vEmail">Primary Email</label>
                        <input type="email" id="vEmail" autocomplete="off">
                    </div>
                </div>

                <div class="row">
                    <label for="vNotes">Vendor Notes</label>
                    <textarea id="vNotes" maxlength="150"></textarea>
                    <div class="char-counter" id="vNotesCount">0 / 150</div>
                </div>

                <div class="row multi-pair">
                    <div>
                        <label>Vendor Types</label>
                        <div class="vdb-multi" id="formTypes"></div>
                    </div>
                    <div>
                        <label>Coverage Areas</label>
                        <div class="vdb-multi" id="formAreas"></div>
                    </div>
                </div>

                <div class="vdb-contacts">
                    <h3>Contacts</h3>
                    <div id="contactList"></div>
                    <button type="button" class="btn btn-ghost btn-sm" id="btnAddContact">+ Add Contact</button>
                </div>
            </form>
        </div>
        <div class="vdb-modal-foot">
            <button type="button" class="btn btn-ghost" id="btnCancel">Cancel</button>
            <button type="button" class="btn btn-primary" id="btnSave">Save Vendor</button>
        </div>
    </div>
</div>

<!-- My Profile modal (self-service: edit own profile + change own password) -->
<div class="vdb-overlay" id="profileOverlay" aria-hidden="true">
    <div class="vdb-modal" role="dialog" aria-modal="true" aria-labelledby="profileTitle">
        <div class="vdb-modal-head">
            <h2 id="profileTitle">My Profile</h2>
            <button type="button" class="vdb-modal-close" id="profileClose" aria-label="Close">&times;</button>
        </div>
        <div class="vdb-modal-body">

            <!-- Profile details -->
            <form class="vdb-form" id="profileForm" autocomplete="off">
                <div class="vdb-notice error" id="profileError"></div>
                <div class="vdb-notice info" id="profileSuccess"></div>

                <div class="row">
                    <label for="pAccountId">Account ID</label>
                    <input type="text" id="pAccountId" readonly disabled
                        title="Your login handle is assigned by an administrator and cannot be changed here.">
                </div>

                <div class="row">
                    <label for="pName">Name *</label>
                    <input type="text" id="pName" autocomplete="off" required>
                </div>

                <div class="row row-2">
                    <div>
                        <label for="pEmail">Email *</label>
                        <input type="email" id="pEmail" autocomplete="off" required>
                    </div>
                    <div>
                        <label for="pCell">Cell</label>
                        <input type="text" id="pCell" autocomplete="off">
                    </div>
                </div>

                <div class="row">
                    <label for="pHomeOffice">Home Office</label>
                    <select id="pHomeOffice"></select>
                </div>

                <div class="vdb-profile-actions">
                    <button type="button" class="btn btn-primary" id="btnSaveProfile">Save Profile</button>
                </div>
            </form>

            <!-- Change password -->
            <div class="vdb-pw-section">
                <h3>Change Password</h3>
                <form class="vdb-form" id="passwordForm" autocomplete="off">
                    <div class="vdb-notice error" id="pwError"></div>
                    <div class="vdb-notice info" id="pwSuccess"></div>

                    <div class="row">
                        <label for="pwCurrent">Current Password</label>
                        <input type="password" id="pwCurrent" autocomplete="current-password">
                    </div>
                    <div class="row row-2">
                        <div>
                            <label for="pwNew">New Password</label>
                            <input type="password" id="pwNew" autocomplete="new-password">
                        </div>
                        <div>
                            <label for="pwConfirm">Confirm New Password</label>
                            <input type="password" id="pwConfirm" autocomplete="new-password">
                        </div>
                    </div>
                    <div class="vdb-profile-actions">
                        <button type="button" class="btn btn-primary" id="btnSavePassword">Change Password</button>
                    </div>
                </form>
            </div>

        </div>
        <div class="vdb-modal-foot">
            <button type="button" class="btn btn-ghost" id="btnProfileClose">Close</button>
        </div>
    </div>
</div>

<script src="vendors.js"></script>
</body>
</html>
