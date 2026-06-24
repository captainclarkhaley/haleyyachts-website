/* ============================================================
   Vendor Database - staff app front end.
   Talks to api.php (same realm). Lists are READ ONLY here.
   ============================================================ */

(function () {
    'use strict';

    var API = 'api/api.php';
    var NOTES_MAX = 150;
    var CONTACT_NOTES_MAX = 100;

    // App state.
    var lists = { vendor_types: [], coverage_areas: [] };
    var typeMode = 'all';
    var formContacts = []; // working copy of contacts inside the open form
    var contactSeq = 0;    // local id generator for unmounted contact rows

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
            return data;
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
                $('resultsBody').innerHTML = '<tr><td colspan="7" class="vdb-empty">' +
                    esc(data.error || 'Could not load vendors.') + '</td></tr>';
                $('resultCount').textContent = '';
                return;
            }
            renderResults(data.vendors);
            var n = data.count;
            $('resultCount').innerHTML = '<strong>' + n + '</strong> vendor' + (n === 1 ? '' : 's') +
                (filtersActive() ? ' matching filters' : ' total');
        }).catch(function () {
            $('resultsBody').innerHTML = '<tr><td colspan="7" class="vdb-empty">Network error loading vendors.</td></tr>';
        });
    }, 180);

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
                ? '<a href="tel:' + esc(v.primary_phone) + '">' + esc(v.primary_phone) + '</a>'
                : '<span style="color:#bbb">-</span>';
            var email = v.primary_email
                ? '<a href="mailto:' + esc(v.primary_email) + '">' + esc(v.primary_email) + '</a>'
                : '<span style="color:#bbb">-</span>';
            rows += '<tr>' +
                '<td class="vdb-vname">' + esc(v.name) + '</td>' +
                '<td>' + tagListHtml(v.types) + '</td>' +
                '<td>' + tagListHtml(v.areas, 'area') + '</td>' +
                '<td>' + phone + '</td>' +
                '<td>' + email + '</td>' +
                '<td>' + v.contact_count + '</td>' +
                '<td><div class="row-actions">' +
                    '<button class="btn btn-ghost btn-sm" data-edit="' + v.id + '">Edit</button>' +
                    '<button class="btn btn-danger btn-sm" data-del="' + v.id + '" data-name="' + esc(v.name) + '">Delete</button>' +
                '</div></td>' +
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
                    '<div><label>Contact Name</label><input type="text" data-f="name" value="' + esc(c.name) + '"></div>' +
                    '<div><label>Email</label><input type="email" data-f="email" value="' + esc(c.email) + '"></div>' +
                    '<div><label>Phone</label><input type="text" data-f="phone" value="' + esc(c.phone) + '"></div>' +
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

    function deleteVendor(id, name) {
        if (!confirm('Delete vendor "' + name + '"? This also removes its contacts. This cannot be undone.')) {
            return;
        }
        apiGet('r=vendors&action=delete&id=' + id).then(function (data) {
            if (!data.ok) { alert(data.error || 'Delete failed.'); return; }
            refresh();
        });
    }

    // ---- wiring ------------------------------------------------------------

    function wire() {
        $('fName').addEventListener('input', refresh);
        $('btnClear').addEventListener('click', function () {
            $('fName').value = '';
            setChecked('fTypes', []);
            setChecked('fAreas', []);
            setMode('all');
            refresh();
        });
        $('fTypes').addEventListener('change', refresh);
        $('fAreas').addEventListener('change', refresh);

        $('modeAll').addEventListener('click', function () { setMode('all'); refresh(); });
        $('modeAny').addEventListener('click', function () { setMode('any'); refresh(); });

        $('btnAdd').addEventListener('click', openAdd);
        $('modalClose').addEventListener('click', closeModal);
        $('btnCancel').addEventListener('click', closeModal);
        $('vendorOverlay').addEventListener('click', function (e) {
            if (e.target === this) { closeModal(); }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && $('vendorOverlay').classList.contains('open')) { closeModal(); }
        });

        $('vNotes').addEventListener('input', updateNotesCounter);
        $('btnSave').addEventListener('click', saveVendor);
        $('btnAddContact').addEventListener('click', addContact);

        // Delegated handlers on the results table.
        $('resultsBody').addEventListener('click', function (e) {
            var ed = e.target.getAttribute('data-edit');
            var dl = e.target.getAttribute('data-del');
            if (ed) { openEdit(parseInt(ed, 10)); }
            else if (dl) { deleteVendor(parseInt(dl, 10), e.target.getAttribute('data-name')); }
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
