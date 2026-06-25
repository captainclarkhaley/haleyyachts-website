/* ============================================================
   Vendor Database - staff app front end.
   Talks to api.php (same realm). Lists are READ ONLY here.
   ============================================================ */

(function () {
    'use strict';

    var API = 'api/api.php';
    var NOTES_MAX = 150;
    var CONTACT_NOTES_MAX = 100;
    var RATING_NOTE_MAX = 150;

    // App state.
    var lists = { vendor_types: [], coverage_areas: [] };
    var typeMode = 'all';
    var formContacts = []; // working copy of contacts inside the open form
    var contactSeq = 0;    // local id generator for unmounted contact rows
    var currentVendors = []; // last result set rendered (before client filter/sort), for CSV export
    var ratingSort = '';   // '', 'desc', or 'asc' - click cycles the Avg Rating column
    var pendingStars = 0;  // star value picked in the detail rating control

    // ---- tiny helpers ------------------------------------------------------

    function $(id) { return document.getElementById(id); }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    // Format a US phone for DISPLAY only. 10 digits -> (305) 555-1212,
    // 11 digits starting with 1 -> 1 (305) 555-1212. Anything else (partial,
    // international, with extensions) is returned exactly as entered so we
    // never mangle what we cannot cleanly parse. Storage is untouched; this is
    // a presentation helper.
    function formatPhone(raw) {
        var s = String(raw == null ? '' : raw).trim();
        if (!s) { return s; }
        var digits = s.replace(/\D/g, '');
        if (digits.length === 10) {
            return '(' + digits.slice(0, 3) + ') ' + digits.slice(3, 6) + '-' + digits.slice(6);
        }
        if (digits.length === 11 && digits.charAt(0) === '1') {
            return '1 (' + digits.slice(1, 4) + ') ' + digits.slice(4, 7) + '-' + digits.slice(7);
        }
        return s;
    }

    // Star glyphs for an average. Rounds to the nearest whole star for the
    // visual; the numeric value carries the precise average alongside it.
    function starGlyphs(avg) {
        var filled = Math.round(Number(avg) || 0);
        if (filled < 0) { filled = 0; }
        if (filled > 5) { filled = 5; }
        var s = '';
        for (var i = 1; i <= 5; i++) {
            s += i <= filled ? '★' : '☆';
        }
        return '<span class="stars" aria-hidden="true">' + s + '</span>';
    }

    // Average + count cell/line for the list and detail header.
    function ratingDisplay(v) {
        if (!v.rating_count) {
            return '<span class="vdb-norating">Not rated</span>';
        }
        return starGlyphs(v.rating_avg) +
            ' <span class="rating-num">' + esc(String(v.rating_avg)) + '</span>' +
            ' <span class="rating-count">(' + v.rating_count + ')</span>';
    }

    // Render the stored UTC "YYYY-MM-DD HH:MM:SS" as a readable date.
    function formatDate(raw) {
        var s = String(raw == null ? '' : raw).trim();
        if (!s) { return ''; }
        var datePart = s.split(' ')[0];
        var p = datePart.split('-');
        if (p.length !== 3) { return esc(datePart); }
        var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                      'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        var mi = parseInt(p[1], 10) - 1;
        if (mi < 0 || mi > 11) { return esc(datePart); }
        return months[mi] + ' ' + parseInt(p[2], 10) + ', ' + p[0];
    }

    function debounce(fn, ms) {
        var t;
        return function () {
            var args = arguments, self = this;
            clearTimeout(t);
            t = setTimeout(function () { fn.apply(self, args); }, ms);
        };
    }

    function checkedIds(containerId) {
        var out = [];
        var boxes = $(containerId).querySelectorAll('input[type="checkbox"]:checked');
        for (var i = 0; i < boxes.length; i++) {
            out.push(parseInt(boxes[i].value, 10));
        }
        return out;
    }

    function apiGet(qs) {
        return fetch(API + '?' + qs, { headers: { 'Accept': 'application/json' } })
            .then(parseJson);
    }

    function apiPost(qs, body) {
        return fetch(API + '?' + qs, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(body || {})
        }).then(parseJson);
    }

    function parseJson(res) {
        return res.text().then(function (t) {
            var data;
            try { data = t ? JSON.parse(t) : {}; } catch (e) { data = { ok: false, error: 'Bad server response.' }; }
            if (!res.ok && data.ok !== false) { data.ok = false; }
            data._status = res.status;
            // Session expired or never authenticated: the API returns 401. Bounce
            // to the login page rather than showing a broken, empty app.
            if (res.status === 401) {
                window.location.href = 'login.html';
            }
            return data;
        });
    }

    // ---- session header (who is logged in + logout) -----------------------

    var currentUser = null;     // last-known public user from auth.php?action=me
    var homeOffices = [];       // canonical home-office list from the server

    function setUserBarLabel(u) {
        var label = u.name || u.account_id || '';
        if (u.home_office) { label += ' - ' + u.home_office; }
        $('userInfo').textContent = label;
    }

    function loadUserBar() {
        fetch('auth.php?action=me', { headers: { 'Accept': 'application/json' } })
            .then(function (res) {
                if (res.status === 401) { window.location.href = 'login.html'; return null; }
                return res.json();
            })
            .then(function (data) {
                if (!data || !data.ok || !data.user) { return; }
                currentUser = data.user;
                homeOffices = data.home_offices || [];
                setUserBarLabel(currentUser);
                $('userBar').hidden = false;
            })
            .catch(function () { /* leave the bar hidden on error */ });
    }

    function logout() {
        fetch('auth.php?action=logout', {
            method: 'POST',
            headers: { 'Accept': 'application/json' }
        }).then(function () {
            window.location.href = 'login.html';
        }).catch(function () {
            window.location.href = 'login.html';
        });
    }

    // ---- My Profile (self-service: own profile + own password) -------------

    // Post to the auth endpoint. Mirrors apiPost but targets auth.php and bounces
    // to the login page on a 401 (expired/absent session).
    function authPost(action, body) {
        return fetch('auth.php?action=' + action, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(body || {})
        }).then(function (res) {
            return res.text().then(function (t) {
                var data;
                try { data = t ? JSON.parse(t) : {}; } catch (e) { data = { ok: false, error: 'Bad server response.' }; }
                if (!res.ok && data.ok !== false) { data.ok = false; }
                data._status = res.status;
                if (res.status === 401) { window.location.href = 'login.html'; }
                return data;
            });
        });
    }

    function showNotice(id, msg) {
        var e = $(id);
        e.textContent = msg;
        e.classList.add('show');
    }
    function clearNotice(id) {
        var e = $(id);
        e.textContent = '';
        e.classList.remove('show');
    }

    function fillHomeOfficeOptions(selected) {
        var sel = $('pHomeOffice');
        var html = '<option value="">(none)</option>';
        for (var i = 0; i < homeOffices.length; i++) {
            var v = esc(homeOffices[i]);
            html += '<option value="' + v + '"' +
                (homeOffices[i] === selected ? ' selected' : '') + '>' + v + '</option>';
        }
        sel.innerHTML = html;
    }

    function openProfile() {
        clearNotice('profileError'); clearNotice('profileSuccess');
        clearNotice('pwError'); clearNotice('pwSuccess');
        $('pwCurrent').value = '';
        $('pwNew').value = '';
        $('pwConfirm').value = '';

        // Always pull fresh from the server so the form reflects the saved state.
        fetch('auth.php?action=me', { headers: { 'Accept': 'application/json' } })
            .then(function (res) {
                if (res.status === 401) { window.location.href = 'login.html'; return null; }
                return res.json();
            })
            .then(function (data) {
                if (!data || !data.ok || !data.user) {
                    showNotice('profileError', 'Could not load your profile.');
                    return;
                }
                currentUser = data.user;
                homeOffices = data.home_offices || homeOffices;
                $('pAccountId').value = currentUser.account_id || '';
                $('pName').value = currentUser.name || '';
                $('pEmail').value = currentUser.email || '';
                $('pCell').value = currentUser.cell || '';
                fillHomeOfficeOptions(currentUser.home_office || '');

                var ov = $('profileOverlay');
                ov.classList.add('open');
                ov.setAttribute('aria-hidden', 'false');
                $('pName').focus();
            })
            .catch(function () {
                showNotice('profileError', 'Network error loading your profile.');
            });
    }

    function closeProfile() {
        var ov = $('profileOverlay');
        ov.classList.remove('open');
        ov.setAttribute('aria-hidden', 'true');
    }

    function saveProfile() {
        clearNotice('profileError'); clearNotice('profileSuccess');

        var name = $('pName').value.trim();
        var email = $('pEmail').value.trim();
        if (!name) { showNotice('profileError', 'Name is required.'); $('pName').focus(); return; }
        if (!email) { showNotice('profileError', 'Email is required.'); $('pEmail').focus(); return; }

        var payload = {
            name: name,
            email: email,
            cell: $('pCell').value.trim(),
            home_office: $('pHomeOffice').value
        };

        $('btnSaveProfile').disabled = true;
        authPost('update_profile', payload).then(function (data) {
            $('btnSaveProfile').disabled = false;
            if (!data.ok) {
                showNotice('profileError', data.error || 'Could not save your profile.');
                return;
            }
            currentUser = data.user;
            setUserBarLabel(currentUser);
            showNotice('profileSuccess', 'Profile saved.');
        }).catch(function () {
            $('btnSaveProfile').disabled = false;
            showNotice('profileError', 'Network error saving your profile.');
        });
    }

    function savePassword() {
        clearNotice('pwError'); clearNotice('pwSuccess');

        var cur = $('pwCurrent').value;
        var nw = $('pwNew').value;
        var cf = $('pwConfirm').value;

        if (!cur) { showNotice('pwError', 'Enter your current password.'); $('pwCurrent').focus(); return; }
        if (nw.length < 8) { showNotice('pwError', 'New password must be at least 8 characters.'); $('pwNew').focus(); return; }
        if (nw !== cf) { showNotice('pwError', 'New passwords do not match.'); $('pwConfirm').focus(); return; }

        $('btnSavePassword').disabled = true;
        authPost('change_password', {
            current_password: cur,
            new_password: nw
        }).then(function (data) {
            $('btnSavePassword').disabled = false;
            if (!data.ok) {
                showNotice('pwError', data.error || 'Could not change your password.');
                return;
            }
            $('pwCurrent').value = '';
            $('pwNew').value = '';
            $('pwConfirm').value = '';
            showNotice('pwSuccess', data.message || 'Your password has been changed.');
        }).catch(function () {
            $('btnSavePassword').disabled = false;
            showNotice('pwError', 'Network error changing your password.');
        });
    }

    // ---- multi-select rendering -------------------------------------------

    function renderMultiSelect(containerId, items) {
        var html = '';
        if (!items.length) {
            html = '<div style="font-size:.8rem;color:#999;padding:6px">None defined.</div>';
        }
        for (var i = 0; i < items.length; i++) {
            var it = items[i];
            html += '<label><input type="checkbox" value="' + it.id + '"> ' + esc(it.name) + '</label>';
        }
        $(containerId).innerHTML = html;
    }

    // ---- listing -----------------------------------------------------------

    function buildListQuery() {
        var params = ['r=vendors', 'action=list'];
        var name = $('fName').value.trim();
        if (name) { params.push('name=' + encodeURIComponent(name)); }

        var types = checkedIds('fTypes');
        for (var i = 0; i < types.length; i++) { params.push('type_ids[]=' + types[i]); }
        params.push('type_mode=' + typeMode);

        var areas = checkedIds('fAreas');
        for (var j = 0; j < areas.length; j++) { params.push('area_ids[]=' + areas[j]); }

        return params.join('&');
    }

    var refresh = debounce(function () {
        apiGet(buildListQuery()).then(function (data) {
            if (!data.ok) {
                currentVendors = [];
                updateExportState();
                $('resultsBody').innerHTML = '<tr><td colspan="7" class="vdb-empty">' +
                    esc(data.error || 'Could not load vendors.') + '</td></tr>';
                $('resultCount').textContent = '';
                return;
            }
            currentVendors = data.vendors || [];
            updateExportState();
            renderFiltered();
        }).catch(function () {
            currentVendors = [];
            updateExportState();
            $('resultsBody').innerHTML = '<tr><td colspan="7" class="vdb-empty">Network error loading vendors.</td></tr>';
        });
    }, 180);

    // Apply the client-side minimum-rating filter and the rating sort to the
    // server result set, then render. The name/type/area filters are already
    // applied server-side; this only narrows and reorders what came back.
    function renderFiltered() {
        var min = parseInt($('fMinRating').value, 10) || 0;
        var rows = currentVendors.slice();

        if (min > 0) {
            rows = rows.filter(function (v) {
                // No ratings are excluded once a minimum above Any is set.
                return v.rating_count > 0 && Number(v.rating_avg) >= min;
            });
        }

        if (ratingSort) {
            rows.sort(function (a, b) {
                // Unrated vendors sort to the bottom regardless of direction.
                var av = a.rating_count > 0 ? Number(a.rating_avg) : -1;
                var bv = b.rating_count > 0 ? Number(b.rating_avg) : -1;
                if (av === bv) { return a.name.localeCompare(b.name); }
                return ratingSort === 'desc' ? bv - av : av - bv;
            });
        }

        renderResults(rows);
        updateSortIndicator();

        var n = rows.length;
        var ratingActive = min > 0;
        var label;
        if (filtersActive() || ratingActive) {
            label = '<strong>' + n + '</strong> vendor' + (n === 1 ? '' : 's') + ' matching filters';
        } else {
            label = '<strong>' + n + '</strong> vendor' + (n === 1 ? '' : 's') + ' total';
        }
        $('resultCount').innerHTML = label;
    }

    function updateSortIndicator() {
        var ind = $('ratingSortInd');
        if (!ind) { return; }
        ind.textContent = ratingSort === 'desc' ? '▼' : (ratingSort === 'asc' ? '▲' : '');
    }

    function filtersActive() {
        return $('fName').value.trim() !== '' ||
            checkedIds('fTypes').length > 0 ||
            checkedIds('fAreas').length > 0;
    }

    function tagListHtml(arr, cls) {
        if (!arr || !arr.length) { return '<span style="color:#bbb">-</span>'; }
        var h = '<div class="tag-list">';
        for (var i = 0; i < arr.length; i++) {
            h += '<span class="tag' + (cls ? ' ' + cls : '') + '">' + esc(arr[i].name) + '</span>';
        }
        return h + '</div>';
    }

    function renderResults(vendors) {
        if (!vendors.length) {
            $('resultsBody').innerHTML = '<tr><td colspan="7" class="vdb-empty">No vendors match. Adjust filters or add one.</td></tr>';
            return;
        }
        var rows = '';
        for (var i = 0; i < vendors.length; i++) {
            var v = vendors[i];
            var phone = v.primary_phone
                ? '<a href="tel:' + esc(v.primary_phone) + '">' + esc(formatPhone(v.primary_phone)) + '</a>'
                : '<span style="color:#bbb">-</span>';
            var email = v.primary_email
                ? '<a href="mailto:' + esc(v.primary_email) + '">' + esc(v.primary_email) + '</a>'
                : '<span style="color:#bbb">-</span>';
            rows += '<tr>' +
                '<td class="vdb-vname">' +
                    '<a href="#" class="vdb-vname-link" data-view="' + v.id + '">' + esc(v.name) + '</a>' +
                '</td>' +
                '<td>' + tagListHtml(v.types) + '</td>' +
                '<td>' + tagListHtml(v.areas, 'area') + '</td>' +
                '<td>' + phone + '</td>' +
                '<td>' + email + '</td>' +
                '<td>' + v.contact_count + '</td>' +
                '<td class="vdb-rating-cell">' + ratingDisplay(v) + '</td>' +
            '</tr>';
        }
        $('resultsBody').innerHTML = rows;
    }

    // ---- form (add / edit) -------------------------------------------------

    function openModal(title) {
        $('modalTitle').textContent = title;
        var ov = $('vendorOverlay');
        ov.classList.add('open');
        ov.setAttribute('aria-hidden', 'false');
        $('vName').focus();
    }

    function closeModal() {
        var ov = $('vendorOverlay');
        ov.classList.remove('open');
        ov.setAttribute('aria-hidden', 'true');
        clearFormError();
    }

    function clearFormError() {
        var e = $('formError');
        e.classList.remove('show');
        e.textContent = '';
    }

    function showFormError(msg) {
        var e = $('formError');
        e.textContent = msg;
        e.classList.add('show');
    }

    function resetForm() {
        $('vId').value = '';
        $('vName').value = '';
        $('vAddress').value = '';
        $('vPhone').value = '';
        $('vEmail').value = '';
        $('vNotes').value = '';
        updateNotesCounter();
        renderMultiSelect('formTypes', lists.vendor_types);
        renderMultiSelect('formAreas', lists.coverage_areas);
        formContacts = [];
        renderContacts();
        clearFormError();
    }

    function setChecked(containerId, ids) {
        var set = {};
        for (var i = 0; i < ids.length; i++) { set[ids[i]] = true; }
        var boxes = $(containerId).querySelectorAll('input[type="checkbox"]');
        for (var j = 0; j < boxes.length; j++) {
            boxes[j].checked = !!set[parseInt(boxes[j].value, 10)];
        }
    }

    function openAdd() {
        resetForm();
        openModal('Add Vendor');
    }

    function openEdit(id) {
        apiGet('r=vendors&action=get&id=' + id).then(function (data) {
            if (!data.ok) { alert(data.error || 'Could not load vendor.'); return; }
            var v = data.vendor;
            resetForm();
            $('vId').value = v.id;
            $('vName').value = v.name;
            $('vAddress').value = v.address;
            $('vPhone').value = v.phone;
            $('vEmail').value = v.email;
            $('vNotes').value = v.notes;
            updateNotesCounter();
            setChecked('formTypes', v.types.map(function (t) { return t.id; }));
            setChecked('formAreas', v.areas.map(function (a) { return a.id; }));
            formContacts = v.contacts.map(function (c) {
                return {
                    key: 'c' + (++contactSeq),
                    id: c.id,
                    name: c.name, email: c.email, phone: c.phone,
                    is_primary: !!c.is_primary, notes: c.notes
                };
            });
            renderContacts();
            openModal('Edit Vendor');
        });
    }

    // ---- detail view (read-only) ------------------------------------------

    var detailVendorId = 0; // vendor currently shown in the detail view

    function openDetail(id) {
        apiGet('r=vendors&action=get&id=' + id).then(function (data) {
            if (!data.ok) { alert(data.error || 'Could not load vendor.'); return; }
            detailVendorId = data.vendor.id;
            renderDetail(data.vendor);
            var ov = $('detailOverlay');
            ov.classList.add('open');
            ov.setAttribute('aria-hidden', 'false');
        });
    }

    function closeDetail() {
        var ov = $('detailOverlay');
        ov.classList.remove('open');
        ov.setAttribute('aria-hidden', 'true');
        detailVendorId = 0;
    }

    function detailValue(v) {
        return (v == null || v === '') ? '<span style="color:#bbb">-</span>' : esc(v);
    }

    function ratingHeaderLine(v) {
        if (!v.rating_count) {
            return '<div class="vdb-rating-summary">' +
                starGlyphs(0) + ' <span class="vdb-norating">No ratings yet</span></div>';
        }
        return '<div class="vdb-rating-summary">' +
            starGlyphs(v.rating_avg) +
            ' <span class="rating-num">' + esc(String(v.rating_avg)) + '</span> average from ' +
            v.rating_count + ' rating' + (v.rating_count === 1 ? '' : 's') +
        '</div>';
    }

    function renderDetail(v) {
        $('detailTitle').textContent = v.name;

        var phone = v.primary_phone
            ? '<a href="tel:' + esc(v.primary_phone) + '">' + esc(formatPhone(v.primary_phone)) + '</a>'
            : '<span style="color:#bbb">-</span>';
        var email = v.primary_email
            ? '<a href="mailto:' + esc(v.primary_email) + '">' + esc(v.primary_email) + '</a>'
            : '<span style="color:#bbb">-</span>';

        var h = ratingHeaderLine(v);

        h += '<dl class="vdb-detail">' +
            '<dt>Name</dt><dd>' + detailValue(v.name) + '</dd>' +
            '<dt>Address</dt><dd>' + detailValue(v.address) + '</dd>' +
            '<dt>Primary Phone</dt><dd>' + phone + '</dd>' +
            '<dt>Primary Email</dt><dd>' + email + '</dd>' +
            '<dt>Vendor Types</dt><dd>' + tagListHtml(v.types) + '</dd>' +
            '<dt>Coverage Areas</dt><dd>' + tagListHtml(v.areas, 'area') + '</dd>' +
            '<dt>Vendor Notes</dt><dd>' + detailValue(v.notes) + '</dd>' +
        '</dl>';

        h += '<div class="vdb-detail-contacts"><h3>Contacts</h3>';
        if (!v.contacts || !v.contacts.length) {
            h += '<div class="vdb-detail-empty">No contacts on file.</div>';
        } else {
            for (var i = 0; i < v.contacts.length; i++) {
                var c = v.contacts[i];
                var cPhone = c.phone
                    ? '<a href="tel:' + esc(c.phone) + '">' + esc(formatPhone(c.phone)) + '</a>'
                    : '<span style="color:#bbb">-</span>';
                var cEmail = c.email
                    ? '<a href="mailto:' + esc(c.email) + '">' + esc(c.email) + '</a>'
                    : '<span style="color:#bbb">-</span>';
                h += '<div class="vdb-detail-contact">' +
                    '<div class="dc-head">' +
                        '<span class="dc-name">' + detailValue(c.name) + '</span>' +
                        (c.is_primary ? '<span class="primary-badge">Primary</span>' : '') +
                    '</div>' +
                    '<dl class="vdb-detail dc-grid">' +
                        '<dt>Email</dt><dd>' + cEmail + '</dd>' +
                        '<dt>Phone</dt><dd>' + cPhone + '</dd>' +
                        '<dt>Notes</dt><dd>' + detailValue(c.notes) + '</dd>' +
                    '</dl>' +
                '</div>';
            }
        }
        h += '</div>';

        // Rate this vendor + ratings history. The history list is filled by
        // loadRatings() so add/delete can refresh just this section.
        h += '<div class="vdb-ratings">' +
            '<h3>Ratings</h3>' +
            '<div class="vdb-rate-control">' +
                '<div class="vdb-star-picker" id="starPicker" role="radiogroup" aria-label="Your rating">' +
                    '<button type="button" class="star-btn" data-star="1" aria-label="1 star">☆</button>' +
                    '<button type="button" class="star-btn" data-star="2" aria-label="2 stars">☆</button>' +
                    '<button type="button" class="star-btn" data-star="3" aria-label="3 stars">☆</button>' +
                    '<button type="button" class="star-btn" data-star="4" aria-label="4 stars">☆</button>' +
                    '<button type="button" class="star-btn" data-star="5" aria-label="5 stars">☆</button>' +
                '</div>' +
                '<input type="text" id="ratingNote" class="vdb-rating-note" maxlength="' + RATING_NOTE_MAX +
                    '" placeholder="Optional note (max ' + RATING_NOTE_MAX + ')">' +
                '<div class="char-counter" id="ratingNoteCount">0 / ' + RATING_NOTE_MAX + '</div>' +
                '<div class="vdb-notice error" id="ratingError"></div>' +
                '<button type="button" class="btn btn-primary btn-sm" id="btnSubmitRating">Submit rating</button>' +
            '</div>' +
            '<div class="vdb-rating-history" id="ratingHistory">' +
                '<div class="vdb-detail-empty">Loading ratings...</div>' +
            '</div>' +
        '</div>';

        $('detailBody').innerHTML = h;

        pendingStars = 0;
        loadRatings(v.id);
    }

    // ---- ratings (anonymous) ----------------------------------------------

    function setPendingStars(n) {
        pendingStars = n;
        var btns = document.querySelectorAll('#starPicker .star-btn');
        for (var i = 0; i < btns.length; i++) {
            var s = parseInt(btns[i].getAttribute('data-star'), 10);
            btns[i].textContent = s <= n ? '★' : '☆';
            btns[i].classList.toggle('on', s <= n);
        }
    }

    function loadRatings(vendorId) {
        apiGet('r=ratings&action=list&vendor_id=' + vendorId).then(function (data) {
            var host = $('ratingHistory');
            if (!host) { return; }
            if (!data.ok) {
                host.innerHTML = '<div class="vdb-detail-empty">Could not load ratings.</div>';
                return;
            }
            renderRatingHistory(data.ratings || []);
        });
    }

    function renderRatingHistory(ratings) {
        var host = $('ratingHistory');
        if (!host) { return; }
        if (!ratings.length) {
            host.innerHTML = '<div class="vdb-detail-empty">No ratings yet.</div>';
            return;
        }
        var html = '';
        for (var i = 0; i < ratings.length; i++) {
            var r = ratings[i];
            html += '<div class="vdb-rating-entry">' +
                '<div class="re-head">' +
                    starGlyphs(r.stars) +
                    '<span class="re-date">' + formatDate(r.created_at) + '</span>' +
                    '<button type="button" class="re-delete" data-rmrating="' + r.id +
                        '" aria-label="Delete rating">Delete</button>' +
                '</div>' +
                (r.note ? '<div class="re-note">' + esc(r.note) + '</div>' : '') +
            '</div>';
        }
        host.innerHTML = html;
    }

    function submitRating() {
        var err = $('ratingError');
        err.classList.remove('show');
        err.textContent = '';

        if (pendingStars < 1 || pendingStars > 5) {
            err.textContent = 'Pick a star rating from 1 to 5.';
            err.classList.add('show');
            return;
        }
        var note = $('ratingNote').value;
        if (note.length > RATING_NOTE_MAX) {
            err.textContent = 'Note exceeds ' + RATING_NOTE_MAX + ' characters.';
            err.classList.add('show');
            return;
        }

        $('btnSubmitRating').disabled = true;
        apiPost('r=ratings&action=add', {
            vendor_id: detailVendorId,
            stars: pendingStars,
            note: note.trim()
        }).then(function (data) {
            $('btnSubmitRating').disabled = false;
            if (!data.ok) {
                err.textContent = data.error || 'Could not save rating.';
                err.classList.add('show');
                return;
            }
            // Clear the input, refresh the detail header average + history + list.
            $('ratingNote').value = '';
            $('ratingNoteCount').textContent = '0 / ' + RATING_NOTE_MAX;
            $('ratingNoteCount').classList.remove('over');
            setPendingStars(0);
            applyRatingSummary(data);
            loadRatings(detailVendorId);
            refresh();
        }).catch(function () {
            $('btnSubmitRating').disabled = false;
            err.textContent = 'Network error saving rating.';
            err.classList.add('show');
        });
    }

    function deleteRating(id) {
        if (!confirm('Delete this rating? This cannot be undone.')) { return; }
        apiGet('r=ratings&action=delete&id=' + id).then(function (data) {
            if (!data.ok) { alert(data.error || 'Delete failed.'); return; }
            applyRatingSummary(data);
            loadRatings(detailVendorId);
            refresh();
        });
    }

    // Update the detail header summary line in place from an add/delete response.
    function applyRatingSummary(data) {
        var el = document.querySelector('#detailBody .vdb-rating-summary');
        if (!el) { return; }
        var v = { rating_avg: data.rating_avg, rating_count: data.rating_count };
        el.outerHTML = ratingHeaderLine(v);
    }

    function updateNotesCounter() {
        var n = $('vNotes').value.length;
        var el = $('vNotesCount');
        el.textContent = n + ' / ' + NOTES_MAX;
        el.classList.toggle('over', n > NOTES_MAX);
    }

    // ---- inline contacts ---------------------------------------------------

    function renderContacts() {
        var host = $('contactList');
        if (!formContacts.length) {
            host.innerHTML = '<div style="font-size:.82rem;color:#999;margin-bottom:8px">No contacts yet.</div>';
            return;
        }
        var html = '';
        for (var i = 0; i < formContacts.length; i++) {
            var c = formContacts[i];
            var notesLen = (c.notes || '').length;
            html += '<div class="contact-row" data-key="' + c.key + '">' +
                '<div class="cr-grid">' +
                    '<div><label>Contact Name</label><input type="text" data-f="name" autocomplete="off" value="' + esc(c.name) + '"></div>' +
                    '<div><label>Email</label><input type="email" data-f="email" autocomplete="off" value="' + esc(c.email) + '"></div>' +
                    '<div><label>Phone</label><input type="text" data-f="phone" autocomplete="off" value="' + esc(c.phone) + '"></div>' +
                '</div>' +
                '<div class="row" style="margin:10px 0 0">' +
                    '<label>Notes</label>' +
                    '<textarea data-f="notes" maxlength="' + CONTACT_NOTES_MAX + '">' + esc(c.notes) + '</textarea>' +
                    '<div class="char-counter' + (notesLen > CONTACT_NOTES_MAX ? ' over' : '') + '" data-counter>' +
                        notesLen + ' / ' + CONTACT_NOTES_MAX + '</div>' +
                '</div>' +
                '<div class="cr-foot">' +
                    '<label class="cr-primary"><input type="radio" name="primaryContact" data-f="primary"' +
                        (c.is_primary ? ' checked' : '') + '> Primary contact' +
                        (c.is_primary ? '<span class="primary-badge">Primary</span>' : '') + '</label>' +
                    '<button type="button" class="btn btn-danger btn-sm" data-rmcontact="' + c.key + '">Remove</button>' +
                '</div>' +
            '</div>';
        }
        host.innerHTML = html;
    }

    function syncContactsFromDom() {
        // Pull current field values back into formContacts before save/re-render.
        var rows = $('contactList').querySelectorAll('.contact-row');
        for (var i = 0; i < rows.length; i++) {
            var key = rows[i].getAttribute('data-key');
            var c = findContact(key);
            if (!c) { continue; }
            c.name = rows[i].querySelector('[data-f="name"]').value.trim();
            c.email = rows[i].querySelector('[data-f="email"]').value.trim();
            c.phone = rows[i].querySelector('[data-f="phone"]').value.trim();
            c.notes = rows[i].querySelector('[data-f="notes"]').value;
            c.is_primary = rows[i].querySelector('[data-f="primary"]').checked;
        }
    }

    function findContact(key) {
        for (var i = 0; i < formContacts.length; i++) {
            if (formContacts[i].key === key) { return formContacts[i]; }
        }
        return null;
    }

    function addContact() {
        syncContactsFromDom();
        formContacts.push({
            key: 'c' + (++contactSeq),
            name: '', email: '', phone: '',
            is_primary: formContacts.length === 0, // first contact defaults to primary
            notes: ''
        });
        renderContacts();
    }

    // ---- save / delete -----------------------------------------------------

    function saveVendor() {
        clearFormError();
        syncContactsFromDom();

        var name = $('vName').value.trim();
        if (!name) { showFormError('Vendor name is required.'); $('vName').focus(); return; }

        var notes = $('vNotes').value;
        if (notes.length > NOTES_MAX) { showFormError('Vendor notes exceed ' + NOTES_MAX + ' characters.'); return; }

        var primaries = 0;
        for (var i = 0; i < formContacts.length; i++) {
            if (formContacts[i].is_primary) { primaries++; }
            if ((formContacts[i].notes || '').length > CONTACT_NOTES_MAX) {
                showFormError('A contact note exceeds ' + CONTACT_NOTES_MAX + ' characters.');
                return;
            }
        }
        if (primaries > 1) { showFormError('Only one contact can be the primary.'); return; }

        var idVal = $('vId').value;
        var payload = {
            name: name,
            address: $('vAddress').value.trim(),
            phone: $('vPhone').value.trim(),
            email: $('vEmail').value.trim(),
            notes: notes.trim(),
            types: checkedIds('formTypes'),
            areas: checkedIds('formAreas'),
            contacts: formContacts.map(function (c) {
                return {
                    id: c.id || undefined,
                    name: c.name, email: c.email, phone: c.phone,
                    is_primary: c.is_primary, notes: c.notes
                };
            })
        };
        if (idVal) { payload.id = parseInt(idVal, 10); }

        $('btnSave').disabled = true;
        apiPost('r=vendors&action=save', payload).then(function (data) {
            $('btnSave').disabled = false;
            if (!data.ok) { showFormError(data.error || 'Save failed.'); return; }
            closeModal();
            refresh();
        }).catch(function () {
            $('btnSave').disabled = false;
            showFormError('Network error during save.');
        });
    }

    function deleteVendor(id, name, onDone) {
        if (!confirm('Delete vendor "' + name + '"? This also removes its contacts. This cannot be undone.')) {
            return;
        }
        apiGet('r=vendors&action=delete&id=' + id).then(function (data) {
            if (!data.ok) { alert(data.error || 'Delete failed.'); return; }
            if (typeof onDone === 'function') { onDone(); }
            refresh();
        });
    }

    // ---- CSV export --------------------------------------------------------

    // Quote a single CSV field per RFC 4180: wrap in quotes if it contains a
    // comma, quote, or newline, and double up any internal quotes.
    function csvCell(value) {
        var s = String(value == null ? '' : value);
        if (/[",\r\n]/.test(s)) {
            s = '"' + s.replace(/"/g, '""') + '"';
        }
        return s;
    }

    function namesJoined(arr) {
        if (!arr || !arr.length) { return ''; }
        var out = [];
        for (var i = 0; i < arr.length; i++) { out.push(arr[i].name); }
        return out.join('; ');
    }

    // Flatten contacts into one cell: each contact as
    // "Name | email | phone | Primary?(Y/N) | notes", separated by " || ".
    function contactsJoined(contacts) {
        if (!contacts || !contacts.length) { return ''; }
        var out = [];
        for (var i = 0; i < contacts.length; i++) {
            var c = contacts[i];
            out.push([
                c.name || '',
                c.email || '',
                c.phone || '',
                c.is_primary ? 'Y' : 'N',
                c.notes || ''
            ].join(' | '));
        }
        return out.join(' || ');
    }

    function buildCsv(vendors) {
        var headers = [
            'Vendor Name', 'Address', 'Primary Phone', 'Primary Email',
            'Vendor Types', 'Coverage Areas', 'Vendor Notes', 'Contacts',
            'Avg Rating', 'Rating Count'
        ];
        var lines = [headers.map(csvCell).join(',')];
        for (var i = 0; i < vendors.length; i++) {
            var v = vendors[i];
            lines.push([
                csvCell(v.name),
                csvCell(v.address),
                csvCell(v.primary_phone),
                csvCell(v.primary_email),
                csvCell(namesJoined(v.types)),
                csvCell(namesJoined(v.areas)),
                csvCell(v.notes),
                csvCell(contactsJoined(v.contacts)),
                csvCell(v.rating_count ? v.rating_avg : ''),
                csvCell(v.rating_count || 0)
            ].join(','));
        }
        return lines.join('\r\n');
    }

    function todayStamp() {
        var d = new Date();
        var mm = String(d.getMonth() + 1);
        var dd = String(d.getDate());
        if (mm.length < 2) { mm = '0' + mm; }
        if (dd.length < 2) { dd = '0' + dd; }
        return d.getFullYear() + '-' + mm + '-' + dd;
    }

    function exportCsv() {
        if (!currentVendors.length) { return; }
        // Prepend a UTF-8 BOM so Excel reads accented characters correctly.
        var csv = '﻿' + buildCsv(currentVendors);
        var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'haley-vendors-' + todayStamp() + '.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    function updateExportState() {
        var btn = $('btnExport');
        if (btn) { btn.disabled = currentVendors.length === 0; }
    }

    // ---- wiring ------------------------------------------------------------

    function wire() {
        var logoutBtn = $('btnLogout');
        if (logoutBtn) { logoutBtn.addEventListener('click', logout); }

        // My Profile modal.
        var profileBtn = $('btnProfile');
        if (profileBtn) { profileBtn.addEventListener('click', openProfile); }
        $('profileClose').addEventListener('click', closeProfile);
        $('btnProfileClose').addEventListener('click', closeProfile);
        $('profileOverlay').addEventListener('click', function (e) {
            if (e.target === this) { closeProfile(); }
        });
        $('btnSaveProfile').addEventListener('click', saveProfile);
        $('btnSavePassword').addEventListener('click', savePassword);

        $('fName').addEventListener('input', refresh);
        $('btnClear').addEventListener('click', function () {
            $('fName').value = '';
            setChecked('fTypes', []);
            setChecked('fAreas', []);
            setMode('all');
            $('fMinRating').value = '0';
            ratingSort = '';
            refresh();
        });
        $('fTypes').addEventListener('change', refresh);
        $('fAreas').addEventListener('change', refresh);

        // Minimum-rating filter is client-side: re-filter the loaded rows only.
        $('fMinRating').addEventListener('change', renderFiltered);

        // Avg Rating header cycles sort: none -> desc -> asc -> none.
        function cycleRatingSort() {
            ratingSort = ratingSort === '' ? 'desc' : (ratingSort === 'desc' ? 'asc' : '');
            renderFiltered();
        }
        $('thRating').addEventListener('click', cycleRatingSort);
        $('thRating').addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); cycleRatingSort(); }
        });

        $('modeAll').addEventListener('click', function () { setMode('all'); refresh(); });
        $('modeAny').addEventListener('click', function () { setMode('any'); refresh(); });

        $('btnExport').addEventListener('click', exportCsv);
        $('btnAdd').addEventListener('click', openAdd);
        $('modalClose').addEventListener('click', closeModal);
        $('btnCancel').addEventListener('click', closeModal);
        $('vendorOverlay').addEventListener('click', function (e) {
            if (e.target === this) { closeModal(); }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') { return; }
            if ($('profileOverlay').classList.contains('open')) { closeProfile(); }
            else if ($('vendorOverlay').classList.contains('open')) { closeModal(); }
            else if ($('detailOverlay').classList.contains('open')) { closeDetail(); }
        });

        $('vNotes').addEventListener('input', updateNotesCounter);
        $('btnSave').addEventListener('click', saveVendor);
        $('btnAddContact').addEventListener('click', addContact);

        // Results table is view-only: clicking a vendor name opens the detail view.
        $('resultsBody').addEventListener('click', function (e) {
            var vw = e.target.getAttribute('data-view');
            if (vw) { e.preventDefault(); openDetail(parseInt(vw, 10)); }
        });

        // Detail view: close, edit, and delete all act on the open vendor.
        $('detailClose').addEventListener('click', closeDetail);
        $('btnDetailClose').addEventListener('click', closeDetail);
        $('detailOverlay').addEventListener('click', function (e) {
            if (e.target === this) { closeDetail(); }
        });
        $('btnDetailEdit').addEventListener('click', function () {
            var id = detailVendorId;
            closeDetail();
            openEdit(id);
        });
        $('btnDetailDelete').addEventListener('click', function () {
            var id = detailVendorId;
            var name = $('detailTitle').textContent;
            deleteVendor(id, name, closeDetail);
        });

        // Detail body holds the rating control + history (rebuilt per open), so
        // its handlers are delegated off the stable #detailBody container.
        var detailBody = $('detailBody');
        detailBody.addEventListener('click', function (e) {
            var star = e.target.getAttribute('data-star');
            if (star) { setPendingStars(parseInt(star, 10)); return; }
            if (e.target.id === 'btnSubmitRating') { submitRating(); return; }
            var rm = e.target.getAttribute('data-rmrating');
            if (rm) { deleteRating(parseInt(rm, 10)); return; }
        });
        detailBody.addEventListener('input', function (e) {
            if (e.target.id !== 'ratingNote') { return; }
            var counter = $('ratingNoteCount');
            var n = e.target.value.length;
            counter.textContent = n + ' / ' + RATING_NOTE_MAX;
            counter.classList.toggle('over', n > RATING_NOTE_MAX);
        });

        // Format the primary phone field on blur, but only when it is a clean
        // 10/11-digit value. Partial or international input is left untouched.
        $('vPhone').addEventListener('blur', function () {
            this.value = formatPhone(this.value);
        });

        // Delegated handlers inside the contact list.
        var host = $('contactList');
        host.addEventListener('click', function (e) {
            var rm = e.target.getAttribute('data-rmcontact');
            if (rm) {
                syncContactsFromDom();
                formContacts = formContacts.filter(function (c) { return c.key !== rm; });
                renderContacts();
            }
        });
        host.addEventListener('input', function (e) {
            if (e.target.getAttribute('data-f') === 'notes') {
                var row = e.target.closest('.contact-row');
                var counter = row.querySelector('[data-counter]');
                var n = e.target.value.length;
                counter.textContent = n + ' / ' + CONTACT_NOTES_MAX;
                counter.classList.toggle('over', n > CONTACT_NOTES_MAX);
            }
        });
        // Format a contact phone field on blur when it is a clean 10/11-digit
        // value. Capture phase, because blur does not bubble.
        host.addEventListener('blur', function (e) {
            if (e.target.getAttribute('data-f') === 'phone') {
                e.target.value = formatPhone(e.target.value);
            }
        }, true);
        host.addEventListener('change', function (e) {
            if (e.target.getAttribute('data-f') === 'primary') {
                // Exclusive radio already enforces single selection; re-render for the badge.
                syncContactsFromDom();
                renderContacts();
            }
        });
    }

    function setMode(mode) {
        typeMode = mode;
        $('modeAll').classList.toggle('active', mode === 'all');
        $('modeAny').classList.toggle('active', mode === 'any');
    }

    // ---- boot --------------------------------------------------------------

    function boot() {
        wire();
        loadUserBar();
        apiGet('r=lists&action=get').then(function (data) {
            if (!data.ok) {
                $('resultsBody').innerHTML = '<tr><td colspan="7" class="vdb-empty">Could not load lists: ' +
                    esc(data.error || 'unknown error') + '</td></tr>';
                return;
            }
            lists.vendor_types = data.vendor_types || [];
            lists.coverage_areas = data.coverage_areas || [];
            renderMultiSelect('fTypes', lists.vendor_types);
            renderMultiSelect('fAreas', lists.coverage_areas);
            refresh();
        }).catch(function () {
            $('resultsBody').innerHTML = '<tr><td colspan="7" class="vdb-empty">Network error. Is the PHP API reachable?</td></tr>';
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
