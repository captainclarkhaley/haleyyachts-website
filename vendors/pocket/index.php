<?php
/**
 * pocket/index.php - Pocket Listings app entry page WITH a server-side auth gate.
 *
 * Second app in the OWYG Broker Suite. An unauthenticated visitor is redirected
 * to the shared /vendors/ login BEFORE any markup is sent - the gate cannot be
 * bypassed by disabling JavaScript. The data API (pocket/api.php) enforces the
 * same checks independently (defense in depth).
 *
 * Lives under /vendors/pocket/ so it shares the path-scoped /vendors/ session
 * cookie and the same login. Reuses the vendor SQLite DB (vdb_connect) so it can
 * read the users table (brokers) and its own pocket_* tables.
 *
 * Phase 1 scope only. The broker-network email, print/share sheet, expiration
 * automation, and comps are LATER phases.
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendors/api/auth-lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendors/api/db.php';

start_secure_session();
$pdo = vdb_connect();
$gateUser = current_user($pdo);
if ($gateUser === null) {
    // login.html lives one level up in /vendors/.
    header('Location: ../login.html');
    exit;
}
if ((int) $gateUser['must_change_password'] === 1) {
    header('Location: ../change-password.html');
    exit;
}

// Server-side display values from the logged-in broker.
$fullName = trim((string) $gateUser['name']);
if ($fullName === '') { $fullName = (string) $gateUser['account_id']; }
$currentUserId = (int) $gateUser['id'];
$isAdmin = isset($gateUser['is_admin']) && (int) $gateUser['is_admin'] === 1;

// The controlled builder list for the Make dropdowns (filter + form).
$makes = $pdo->query('SELECT name FROM pocket_makes ORDER BY name COLLATE NOCASE')->fetchAll(PDO::FETCH_COLUMN);

$h = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pocket Listings - Haley Yachts</title>
    <meta name="robots" content="noindex, nofollow">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="../../favicon.ico" sizes="any">
    <link rel="stylesheet" href="pocket.css?v=<?php echo @filemtime(__DIR__ . '/pocket.css'); ?>">
</head>
<body
    data-user-id="<?php echo $h($currentUserId); ?>"
    data-user-name="<?php echo $h($fullName); ?>"
    data-is-admin="<?php echo $isAdmin ? '1' : '0'; ?>">

<header class="pl-header">
    <div class="pl-topright">
        <a class="pl-navlink" href="../suite.php" title="Back to the Broker Suite launcher">&larr; Broker Suite</a>
        <span class="pl-user-info"><?php echo $h($fullName); ?></span>
    </div>
    <img class="pl-brand-logo" src="../../images/email/owyg-banner-reverse.png" alt="One Water Yacht Group">
    <h1>Pocket Listings</h1>
    <div class="pl-accent-line"></div>
    <p>Private, off-market listings for the OWYG broker network</p>
</header>

<div class="pl-wrap">

    <!-- Search / filter bar -->
    <section class="pl-filters" aria-label="Search and filter">
        <div class="pl-filter-grid">
            <div class="pl-field pl-field-kw">
                <label for="fKeyword">Keyword</label>
                <input type="text" id="fKeyword" placeholder="Make, model, location, description...">
            </div>
            <div class="pl-field">
                <label for="fMake">Make</label>
                <select id="fMake">
                    <option value="">All makes</option>
                    <?php foreach ($makes as $m): ?>
                    <option value="<?php echo $h($m); ?>"><?php echo $h($m); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="pl-field pl-range">
                <label>Year</label>
                <div class="pl-range-inputs">
                    <input type="number" id="fYearMin" placeholder="Min" inputmode="numeric">
                    <span>-</span>
                    <input type="number" id="fYearMax" placeholder="Max" inputmode="numeric">
                </div>
            </div>
            <div class="pl-field pl-range">
                <label>Length (ft)</label>
                <div class="pl-range-inputs">
                    <input type="number" id="fLenMin" placeholder="Min" inputmode="numeric">
                    <span>-</span>
                    <input type="number" id="fLenMax" placeholder="Max" inputmode="numeric">
                </div>
            </div>
            <div class="pl-field pl-range">
                <label>Price ($)</label>
                <div class="pl-range-inputs">
                    <input type="number" id="fPriceMin" placeholder="Min" inputmode="numeric">
                    <span>-</span>
                    <input type="number" id="fPriceMax" placeholder="Max" inputmode="numeric">
                </div>
            </div>
        </div>
        <div class="pl-filter-actions">
            <div class="pl-result-count" id="resultCount">Loading...</div>
            <div class="pl-filter-buttons">
                <button type="button" class="btn btn-ghost" id="btnClear">Clear</button>
                <button type="button" class="btn btn-primary" id="btnNew">+ New Pocket Listing</button>
            </div>
        </div>
    </section>

    <!-- Cards (newest-entered first) -->
    <div class="pl-cards" id="cards">
        <div class="pl-empty" id="cardsEmpty">Loading listings...</div>
    </div>

    <div class="pl-foot-link">
        <a href="../suite.php">&larr; Return to the Broker Suite</a>
    </div>
</div>

<!-- ===== Detail overlay (full info + gallery) ===== -->
<div class="pl-overlay" id="detailOverlay" aria-hidden="true">
    <div class="pl-modal pl-modal-wide" role="dialog" aria-modal="true" aria-labelledby="detailTitle">
        <div class="pl-modal-head">
            <h2 id="detailTitle">Listing</h2>
            <button type="button" class="pl-modal-close" id="detailClose" aria-label="Close">&times;</button>
        </div>
        <div class="pl-modal-body" id="detailBody"></div>
        <div class="pl-modal-foot pl-modal-foot-split">
            <button type="button" class="btn btn-danger" id="btnDetailDelete" hidden>Delete</button>
            <div class="pl-foot-right">
                <button type="button" class="btn btn-ghost" id="btnDetailClose">Close</button>
                <button type="button" class="btn btn-primary" id="btnDetailEdit" hidden>Edit</button>
            </div>
        </div>
    </div>
</div>

<!-- ===== New / Edit form overlay ===== -->
<div class="pl-overlay" id="formOverlay" aria-hidden="true">
    <div class="pl-modal pl-modal-wide" role="dialog" aria-modal="true" aria-labelledby="formTitle">
        <div class="pl-modal-head">
            <h2 id="formTitle">New Pocket Listing</h2>
            <button type="button" class="pl-modal-close" id="formClose" aria-label="Close">&times;</button>
        </div>
        <div class="pl-modal-body">
            <div class="pl-notice error" id="formError"></div>

            <form class="pl-form" id="listingForm" autocomplete="off">
                <input type="hidden" id="fId">

                <div class="pl-form-grid">
                    <div class="pl-frow">
                        <label for="fFormMake">Make *</label>
                        <div class="pl-make-row">
                            <select id="fFormMake" required>
                                <option value="">Select a make...</option>
                                <?php foreach ($makes as $m): ?>
                                <option value="<?php echo $h($m); ?>"><?php echo $h($m); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="pl-make-add" id="btnAddMake"
                                title="Add a manufacturer not in the list">+ Add</button>
                        </div>
                    </div>
                    <div class="pl-frow">
                        <label for="fModel">Model</label>
                        <input type="text" id="fModel">
                    </div>
                    <div class="pl-frow">
                        <label for="fYear">Year</label>
                        <input type="number" id="fYear" inputmode="numeric" min="1900" max="2100">
                    </div>
                    <div class="pl-frow">
                        <label for="fLength">Length (ft)</label>
                        <input type="number" id="fLength" inputmode="numeric" min="0">
                    </div>
                    <div class="pl-frow">
                        <label for="fLocation">Location</label>
                        <input type="text" id="fLocation" placeholder="City, State">
                    </div>
                    <div class="pl-frow">
                        <label for="fDays">Days Active</label>
                        <input type="number" id="fDays" inputmode="numeric" min="0" placeholder="e.g. 30">
                    </div>
                    <div class="pl-frow">
                        <label for="fPrice">Price ($)</label>
                        <input type="text" id="fPrice" inputmode="numeric" autocomplete="off" placeholder="0">
                    </div>
                    <div class="pl-frow">
                        <label>Price Type</label>
                        <div class="pl-toggle" role="group" aria-label="Price type">
                            <button type="button" class="pl-toggle-btn active" id="ptList" data-val="list">List</button>
                            <button type="button" class="pl-toggle-btn" id="ptNet" data-val="net">Net</button>
                        </div>
                    </div>
                </div>

                <div class="pl-frow pl-frow-full">
                    <label for="fDesc">Description</label>
                    <textarea id="fDesc" maxlength="750" rows="4"
                        placeholder="Key details, condition, notes for other brokers..."></textarea>
                    <div class="pl-char-counter" id="descCount">0 / 750</div>
                </div>

                <div class="pl-frow pl-frow-full">
                    <label>Photos</label>
                    <div class="pl-image-inputs">
                        <div class="pl-image-slot">
                            <span class="pl-slot-label">Hero image (1)</span>
                            <input type="file" id="fHero" accept="image/jpeg,image/png,image/webp">
                        </div>
                        <div class="pl-image-slot">
                            <span class="pl-slot-label">Additional images (up to 3)</span>
                            <input type="file" id="fMore" accept="image/jpeg,image/png,image/webp" multiple>
                        </div>
                    </div>
                    <!-- Current images (edit only). Thumbnails with a remove (x)
                         control; removed ones are tracked client-side and sent as
                         remove_images[] on commit. -->
                    <div class="pl-existing" id="existingImgs" hidden>
                        <span class="pl-slot-label">Current images</span>
                        <div class="pl-existing-thumbs" id="existingThumbs"></div>
                    </div>
                    <div class="pl-optnote" id="optNote"></div>
                    <p class="pl-hint" id="imgCountNote" hidden></p>
                </div>
            </form>
        </div>
        <div class="pl-modal-foot">
            <button type="button" class="btn btn-ghost" id="btnFormCancel">Cancel</button>
            <button type="button" class="btn btn-primary" id="btnReview">Save &rarr; Review</button>
        </div>
    </div>
</div>

<!-- ===== Review overlay (client-side preview before commit) ===== -->
<div class="pl-overlay" id="reviewOverlay" aria-hidden="true">
    <div class="pl-modal pl-modal-wide" role="dialog" aria-modal="true" aria-labelledby="reviewTitle">
        <div class="pl-modal-head">
            <h2 id="reviewTitle">Review Listing</h2>
            <button type="button" class="pl-modal-close" id="reviewClose" aria-label="Close">&times;</button>
        </div>
        <div class="pl-modal-body">
            <div class="pl-notice error" id="reviewError"></div>
            <p class="pl-review-hint">Check everything below, then Commit to save. Nothing is stored until you commit.</p>
            <div id="reviewCard"></div>

            <!-- Commit progress (hidden until a commit starts) -->
            <div class="pl-progress" id="commitProgress" hidden aria-live="polite">
                <div class="pl-progress-label" id="commitProgressLabel">Uploading...</div>
                <div class="pl-progress-track">
                    <div class="pl-progress-bar" id="commitProgressBar"></div>
                </div>
            </div>
        </div>
        <div class="pl-modal-foot pl-modal-foot-split">
            <button type="button" class="btn btn-ghost" id="btnReviewEdit">&larr; Edit</button>
            <button type="button" class="btn btn-primary" id="btnCommit">Commit</button>
        </div>
    </div>
</div>

<script src="pocket.js?v=<?php echo @filemtime(__DIR__ . '/pocket.js'); ?>"></script>
</body>
</html>
