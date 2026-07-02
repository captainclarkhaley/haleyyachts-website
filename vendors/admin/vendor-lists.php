<?php
/**
 * vendor-lists.php (Broker Suite admin copy) - Predefined Lists management page
 * (Vendor Types + Coverage Areas).
 *
 * RELOCATED from /admin/vendor-lists.html as part of Phase 2c. The gate below
 * replaces the old /admin/ Directory Privacy realm with the shared in-app admin
 * guard: a non-admin (or an unauthenticated visitor) is redirected BEFORE any
 * markup, so the page cannot be reached by disabling JavaScript. admin-guard.php
 * exposes $pdo and $gateUser once we fall through. The page's data calls hit
 * vendor-lists-api.php in this same folder (the relocated, admin-gated API).
 *
 * The original /admin/vendor-lists.html is untouched and keeps working until 2d.
 */
require_once __DIR__ . '/admin-guard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Lists - Broker Suite Admin - <?php echo htmlspecialchars((string) $brandName, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Open Sans', Arial, sans-serif;
            background: #f4f6f8;
            margin: 0;
            padding: 40px 20px 60px;
            color: #333;
            line-height: 1.6;
        }
        .admin-container { max-width: 900px; margin: 0 auto; }
        .admin-header {
            background: #0a1628; color: #fff;
            padding: 36px 48px; border-radius: 6px 6px 0 0; text-align: center;
            position: relative;
        }
        .admin-back {
            position: absolute; top: 16px; left: 20px;
            color: #cfe9f1; text-decoration: none; font-size: 0.82rem;
            border: 1px solid rgba(255,255,255,0.22); padding: 6px 12px; border-radius: 999px;
        }
        .admin-back:hover { border-color: #21cbea; color: #fff; }
        .admin-header h1 {
            font-size: 1.7rem; font-weight: 300; text-transform: uppercase;
            letter-spacing: 3px; margin: 0;
        }
        .admin-header h1 strong { font-weight: 700; color: #21cbea; }
        .admin-header .accent-line { width: 60px; height: 3px; background: #21cbea; margin: 12px auto 14px; }
        .admin-header p { margin: 0; font-size: 0.92rem; color: rgba(255,255,255,0.75); font-style: italic; }
        .admin-body {
            background: #fff; padding: 36px 48px; border-radius: 0 0 6px 6px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }
        .security-notice {
            background: #fff7e6; border-left: 4px solid #e89c20; padding: 14px 18px;
            border-radius: 3px; margin-bottom: 28px; font-size: 0.85rem; color: #5a4a20;
        }
        .security-notice strong {
            display: block; text-transform: uppercase; letter-spacing: 1px;
            font-size: 0.74rem; color: #8a6820; margin-bottom: 5px;
        }
        .lists-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 28px; align-items: start; }
        @media (max-width: 720px) { .lists-grid { grid-template-columns: 1fr; } }
        .list-panel {
            border: 1px solid #e5e5e5; border-radius: 6px; overflow: hidden;
        }
        .list-panel h2 {
            margin: 0; background: #0a1628; color: #fff; padding: 14px 18px;
            font-size: 0.82rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1.5px;
        }
        .list-body { padding: 14px 18px 18px; }
        ul.items { list-style: none; margin: 0 0 14px; padding: 0; }
        ul.items li {
            display: flex; align-items: center; gap: 8px;
            padding: 8px 10px; border: 1px solid #e5e5e5; border-radius: 4px;
            margin-bottom: 7px; background: #fff;
        }
        ul.items li .name { flex: 1; font-size: 0.9rem; color: #0a1628; }
        ul.items li .usage {
            font-size: 0.68rem; color: #888; background: #f0f2f5;
            border-radius: 3px; padding: 1px 7px; white-space: nowrap;
        }
        .icon-btn {
            border: 1px solid #e5e5e5; background: #fff; color: #666;
            border-radius: 3px; cursor: pointer; font-size: 0.72rem;
            padding: 2px 7px; line-height: 1.2;
        }
        .icon-btn:hover:not(:disabled) { border-color: #21cbea; color: #21cbea; }
        .icon-btn:disabled { opacity: 0.35; cursor: default; }
        .add-row { display: flex; gap: 8px; margin: 0 0 14px; }
        .add-row input {
            flex: 1; font-family: inherit; font-size: 0.88rem; padding: 8px 10px;
            border: 1px solid #e5e5e5; border-radius: 4px;
        }
        .add-row input:focus { outline: none; border-color: #21cbea; box-shadow: 0 0 0 2px rgba(33,203,234,0.18); }
        .btn {
            font-family: inherit; font-size: 0.78rem; font-weight: 600; cursor: pointer;
            border: 1px solid transparent; border-radius: 4px; padding: 8px 14px;
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        .btn-primary { background: #21cbea; color: #fff; }
        .btn-primary:hover { background: #1aa8c4; }
        .btn-ghost { background: #fff; color: #666; border-color: #e5e5e5; }
        .btn-ghost:hover { border-color: #21cbea; color: #21cbea; }
        .btn-danger-text {
            background: none; border: none; color: #c0392b; cursor: pointer;
            font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .btn-danger-text:hover { text-decoration: underline; }
        .notice {
            padding: 10px 14px; border-radius: 4px; font-size: 0.82rem; margin-bottom: 14px; display: none;
        }
        .notice.show { display: block; }
        .notice.error { background: #fdecea; border-left: 4px solid #c0392b; color: #7a241c; }
        .notice.ok { background: #e8f7ea; border-left: 4px solid #1b6e2e; color: #1b5e2a; }
        .footer-link { text-align: center; margin-top: 26px; font-size: 0.85rem; }
        .footer-link a { color: #21cbea; text-decoration: none; }
        .footer-link a:hover { text-decoration: underline; }
        .empty { font-size: 0.85rem; color: #999; font-style: italic; padding: 6px 2px; }

        /* ----- Coverage Areas hierarchy tree ----- */
        ul.tree { list-style: none; margin: 0 0 14px; padding: 0; }
        ul.tree li {
            display: flex; align-items: center; gap: 8px;
            padding: 7px 10px; border: 1px solid #e5e5e5; border-radius: 4px;
            margin-bottom: 6px; background: #fff;
        }
        ul.tree li .name { flex: 1; font-size: 0.88rem; color: #0a1628; }
        ul.tree li.depth-1 { margin-left: 18px; }
        ul.tree li.depth-2 { margin-left: 36px; }
        ul.tree li.depth-3 { margin-left: 54px; }
        ul.tree li.kind-nationwide { border-left: 3px solid #6b46c1; }
        ul.tree li.kind-state { border-left: 3px solid #0a1628; }
        ul.tree li.kind-region { border-left: 3px solid #21cbea; }
        ul.tree li.kind-county { border-left: 3px solid #c4ccd6; }
        .kind-badge {
            font-size: 0.6rem; text-transform: uppercase; letter-spacing: 0.7px;
            font-weight: 700; border-radius: 3px; padding: 1px 6px; white-space: nowrap;
        }
        .kind-badge.nationwide { background: #efe7fb; color: #6b46c1; }
        .kind-badge.state { background: #e6eaf0; color: #0a1628; }
        .kind-badge.region { background: #def6fb; color: #1aa8c4; }
        .kind-badge.county { background: #f0f2f5; color: #5a6472; }

        .area-add {
            border: 1px solid #e5e5e5; border-radius: 5px; background: #fafbfc;
            padding: 12px 14px; margin-bottom: 16px;
        }
        .area-add .field { margin-bottom: 9px; }
        .area-add label {
            display: block; font-size: 0.68rem; font-weight: 600; text-transform: uppercase;
            letter-spacing: 0.8px; color: #888; margin-bottom: 3px;
        }
        .area-add input, .area-add select {
            width: 100%; font-family: inherit; font-size: 0.86rem; padding: 7px 9px;
            border: 1px solid #e5e5e5; border-radius: 4px; background: #fff;
        }
        .area-add input:focus, .area-add select:focus {
            outline: none; border-color: #21cbea; box-shadow: 0 0 0 2px rgba(33,203,234,0.18);
        }
        .area-add .area-add-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 4px; }

        .audit-panel {
            margin-top: 24px; border: 1px solid #e5e5e5; border-radius: 6px; overflow: hidden;
        }
        .audit-panel h2 {
            margin: 0; background: #0a1628; color: #fff; padding: 14px 18px;
            font-size: 0.82rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1.5px;
        }
        .audit-body { padding: 14px 18px 18px; }
        .audit-summary { font-size: 0.9rem; color: #0a1628; margin-bottom: 10px; }
        .audit-summary strong { color: #c0392b; }
        ul.audit-list { list-style: none; margin: 0; padding: 0; column-gap: 24px; }
        ul.audit-list li { font-size: 0.86rem; color: #333; padding: 3px 0; }
    </style>
</head>
<body>

<div class="admin-container">
    <div class="admin-header">
        <a class="admin-back" href="../suite.php" title="Back to the Broker Suite menu">&larr; Broker Suite</a>
        <h1>VENDOR <strong>LISTS</strong></h1>
        <div class="accent-line"></div>
        <p>Manage the Vendor Types and Coverage Areas used by the staff Vendor Database</p>
    </div>

    <div class="admin-body">
        <div class="security-notice">
            <strong>Admin only</strong>
            These two lists feed the staff Vendor Database at <code>/vendors/</code>. Staff can choose from them but cannot edit them. Changes here are saved straight to the shared vendor database. Deleting an in-use item only unassigns it from vendors; it does not delete the vendors.
        </div>

        <div class="notice error" id="notice"></div>

        <div class="lists-grid">
            <!-- Vendor Types -->
            <div class="list-panel">
                <h2>Vendor Types</h2>
                <div class="list-body">
                    <div class="add-row">
                        <input type="text" id="typeAdd" placeholder="New vendor type...">
                        <button class="btn btn-primary" data-add="vendor_type">Add</button>
                    </div>
                    <ul class="items" id="typeItems"></ul>
                </div>
            </div>

            <!-- Coverage Areas (hierarchy) -->
            <div class="list-panel">
                <h2>Coverage Areas</h2>
                <div class="list-body">
                    <div class="area-add">
                        <div class="field">
                            <label for="areaAddName">Area name</label>
                            <input type="text" id="areaAddName" placeholder="e.g. Duval">
                        </div>
                        <div class="field">
                            <label for="areaAddKind">Tier</label>
                            <select id="areaAddKind">
                                <option value="county">County</option>
                                <option value="region">Region</option>
                                <option value="state">State</option>
                                <option value="nationwide">Nationwide</option>
                            </select>
                        </div>
                        <div class="field" id="areaAddParentWrap">
                            <label for="areaAddParent">Parent</label>
                            <select id="areaAddParent"></select>
                        </div>
                        <div class="area-add-actions">
                            <button class="btn btn-primary" id="areaAddBtn">Add area</button>
                        </div>
                    </div>
                    <ul class="tree" id="areaItems"></ul>
                </div>
            </div>
        </div>

        <!-- Audit: vendors with no coverage area -->
        <div class="audit-panel">
            <h2>Vendors with no coverage area</h2>
            <div class="audit-body">
                <div class="audit-summary" id="auditSummary">Loading...</div>
                <ul class="audit-list" id="auditList"></ul>
            </div>
        </div>

        <div class="footer-link">
            <a href="../suite.php">&larr; Back to Broker Suite</a> &nbsp;|&nbsp;
            <a href="../index.php" target="_blank">Open staff Vendor Database &rarr;</a>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';
    var API = 'vendor-lists-api.php';

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function notice(msg, kind) {
        var n = document.getElementById('notice');
        n.textContent = msg;
        n.className = 'notice show ' + (kind || 'error');
        if (kind === 'ok') { setTimeout(function () { n.className = 'notice'; }, 2500); }
    }
    function clearNotice() { document.getElementById('notice').className = 'notice'; }

    function call(list, action, opts) {
        opts = opts || {};
        var url = API + '?list=' + encodeURIComponent(list) + '&action=' + action;
        if (opts.query) { url += '&' + opts.query; }
        var init = { headers: { 'Accept': 'application/json' } };
        if (opts.body) {
            init.method = 'POST';
            init.headers['Content-Type'] = 'application/json';
            init.body = JSON.stringify(opts.body);
        }
        return fetch(url, init).then(function (res) {
            return res.text().then(function (t) {
                var d;
                try { d = t ? JSON.parse(t) : {}; } catch (e) { d = { ok: false, error: 'Bad server response.' }; }
                d._status = res.status;
                // Session expired or lost: bounce to the shared /vendors/ login,
                // mirroring the Staff Accounts page. One level up from /admin/.
                if (res.status === 401) { window.location.href = '../login.html'; }
                return d;
            });
        });
    }

    // ================= Vendor Types (FLAT - unchanged behavior) =============

    var typeItems = [];

    function renderTypes() {
        var ul = document.getElementById('typeItems');
        if (!typeItems.length) {
            ul.innerHTML = '<li class="empty">None yet. Add one below.</li>';
            return;
        }
        var html = '';
        for (var i = 0; i < typeItems.length; i++) {
            var it = typeItems[i];
            var usage = it.usageCount > 0
                ? '<span class="usage">' + it.usageCount + ' in use</span>' : '';
            html += '<li data-id="' + it.id + '">' +
                '<span class="name">' + esc(it.name) + '</span>' +
                usage +
                '<button class="icon-btn" data-rename="' + it.id + '">Rename</button>' +
                '<button class="btn-danger-text" data-del="' + it.id + '" data-usage="' + it.usageCount + '">Delete</button>' +
            '</li>';
        }
        ul.innerHTML = html;
    }

    function loadTypes() {
        return call('vendor_type', 'get').then(function (d) {
            if (!d.ok) { notice(d.error || 'Could not load vendor types.'); return; }
            typeItems = d.items || [];
            renderTypes();
        });
    }

    function addType() {
        var input = document.getElementById('typeAdd');
        var name = input.value.trim();
        if (!name) { input.focus(); return; }
        call('vendor_type', 'add', { body: { name: name } }).then(function (d) {
            if (!d.ok) { notice(d.error || 'Add failed.'); return; }
            typeItems = d.items; input.value = ''; renderTypes();
            notice('Added "' + name + '".', 'ok');
        });
    }

    function renameType(id) {
        var cur = '';
        for (var i = 0; i < typeItems.length; i++) { if (typeItems[i].id == id) { cur = typeItems[i].name; } }
        var name = prompt('Rename vendor type:', cur);
        if (name === null) { return; }
        name = name.trim();
        if (!name || name === cur) { return; }
        call('vendor_type', 'rename', { body: { id: parseInt(id, 10), name: name } }).then(function (d) {
            if (!d.ok) { notice(d.error || 'Rename failed.'); return; }
            typeItems = d.items; renderTypes(); notice('Renamed.', 'ok');
        });
    }

    function deleteType(id, usage) {
        usage = parseInt(usage, 10) || 0;
        var msg = usage > 0
            ? 'This type is assigned to ' + usage + ' vendor(s). Deleting it will remove it from them (the vendors stay). Continue?'
            : 'Delete this type?';
        if (!confirm(msg)) { return; }
        var q = 'id=' + id + (usage > 0 ? '&confirm=1' : '');
        call('vendor_type', 'delete', { query: q }).then(function (d) {
            if (!d.ok && d.needsConfirm) {
                call('vendor_type', 'delete', { query: 'id=' + id + '&confirm=1' }).then(function (d2) {
                    if (!d2.ok) { notice(d2.error || 'Delete failed.'); return; }
                    typeItems = d2.items; renderTypes(); notice('Deleted.', 'ok');
                });
                return;
            }
            if (!d.ok) { notice(d.error || 'Delete failed.'); return; }
            typeItems = d.items; renderTypes(); notice('Deleted.', 'ok');
        });
    }

    // ================= Coverage Areas (HIERARCHY) ===========================

    var areaItems = [];          // flat tree rows: {id,name,kind,parent_id,sort,usageCount}

    function areaById(id) {
        id = parseInt(id, 10);
        for (var i = 0; i < areaItems.length; i++) { if (areaItems[i].id === id) { return areaItems[i]; } }
        return null;
    }

    // Depth of a node by walking parent_id (0 = top-level). Cycle-guarded.
    function areaDepth(it) {
        var depth = 0, cur = it, hops = 0;
        while (cur && cur.parent_id != null && hops < 64) {
            cur = areaById(cur.parent_id); depth++; hops++;
        }
        return depth;
    }

    // Order the flat rows for a nested tree render: each parent immediately
    // followed by its children, recursively, top-level grouped by kind rank.
    function orderedTree() {
        var byParent = {};
        for (var i = 0; i < areaItems.length; i++) {
            var p = areaItems[i].parent_id == null ? 0 : areaItems[i].parent_id;
            (byParent[p] = byParent[p] || []).push(areaItems[i]);
        }
        var kindRank = { nationwide: 0, state: 1, region: 2, county: 3 };
        function sortGroup(arr) {
            arr.sort(function (a, b) {
                var ka = kindRank[a.kind] == null ? 4 : kindRank[a.kind];
                var kb = kindRank[b.kind] == null ? 4 : kindRank[b.kind];
                if (ka !== kb) { return ka - kb; }
                return String(a.name).toLowerCase().localeCompare(String(b.name).toLowerCase());
            });
            return arr;
        }
        var out = [];
        (function walk(parentId) {
            var kids = sortGroup(byParent[parentId] || []);
            for (var j = 0; j < kids.length; j++) {
                out.push(kids[j]);
                walk(kids[j].id);
            }
        })(0);
        return out;
    }

    function renderAreas() {
        var ul = document.getElementById('areaItems');
        if (!areaItems.length) {
            ul.innerHTML = '<li class="empty">None yet. Add one above.</li>';
            return;
        }
        var ordered = orderedTree();
        var html = '';
        for (var i = 0; i < ordered.length; i++) {
            var it = ordered[i];
            var depth = areaDepth(it);
            if (depth > 3) { depth = 3; }
            var usage = it.usageCount > 0
                ? '<span class="usage">' + it.usageCount + ' in use</span>' : '';
            html += '<li class="depth-' + depth + ' kind-' + esc(it.kind) + '" data-id="' + it.id + '">' +
                '<span class="kind-badge ' + esc(it.kind) + '">' + esc(it.kind) + '</span>' +
                '<span class="name">' + esc(it.name) + '</span>' +
                usage +
                '<button class="icon-btn" data-edit="' + it.id + '">Edit</button>' +
                '<button class="btn-danger-text" data-del="' + it.id + '" data-usage="' + it.usageCount + '">Delete</button>' +
            '</li>';
        }
        ul.innerHTML = html;
    }

    function loadAreas() {
        return call('coverage_area', 'get').then(function (d) {
            if (!d.ok) { notice(d.error || 'Could not load coverage areas.'); return; }
            areaItems = (d.items || []).map(function (r) {
                r.id = parseInt(r.id, 10);
                r.parent_id = (r.parent_id == null) ? null : parseInt(r.parent_id, 10);
                return r;
            });
            renderAreas();
            populateParentSelect('areaAddParent', document.getElementById('areaAddKind').value, null);
        });
    }

    // Parent <select> options constrained by the chosen kind:
    //   region -> states only;  county -> regions + states;
    //   state/nationwide -> top-level (no parent; control hidden).
    // excludeId keeps a node out of its own parent list (and we drop its subtree
    // too, since you cannot parent a node under its own descendant).
    function populateParentSelect(selectId, kind, excludeId) {
        var wrap = document.getElementById('areaAddParentWrap');
        var sel = document.getElementById(selectId);
        if (kind === 'state' || kind === 'nationwide') {
            if (wrap) { wrap.style.display = 'none'; }
            sel.innerHTML = '';
            return;
        }
        if (wrap) { wrap.style.display = ''; }

        var allowedKinds = (kind === 'region') ? { state: true } : { state: true, region: true };

        // Build the set of ids to exclude: the node itself + its descendants.
        var exclude = {};
        if (excludeId != null) {
            exclude[excludeId] = true;
            var changed = true;
            while (changed) {
                changed = false;
                for (var i = 0; i < areaItems.length; i++) {
                    var it = areaItems[i];
                    if (it.parent_id != null && exclude[it.parent_id] && !exclude[it.id]) {
                        exclude[it.id] = true; changed = true;
                    }
                }
            }
        }

        var opts = '<option value="">(choose a parent)</option>';
        var ordered = orderedTree();
        for (var j = 0; j < ordered.length; j++) {
            var node = ordered[j];
            if (!allowedKinds[node.kind]) { continue; }
            if (exclude[node.id]) { continue; }
            var indent = node.kind === 'region' ? '— ' : '';
            opts += '<option value="' + node.id + '">' + esc(indent + node.name + ' (' + node.kind + ')') + '</option>';
        }
        sel.innerHTML = opts;
    }

    function addArea() {
        var name = document.getElementById('areaAddName').value.trim();
        var kind = document.getElementById('areaAddKind').value;
        var parentSel = document.getElementById('areaAddParent');
        var parentId = (kind === 'state' || kind === 'nationwide') ? '' : parentSel.value;
        if (!name) { document.getElementById('areaAddName').focus(); return; }
        call('coverage_area', 'add', { body: { name: name, kind: kind, parent_id: parentId } }).then(function (d) {
            if (!d.ok) { notice(d.error || 'Add failed.'); return; }
            areaItems = normalizeAreas(d.items);
            document.getElementById('areaAddName').value = '';
            renderAreas();
            populateParentSelect('areaAddParent', document.getElementById('areaAddKind').value, null);
            notice('Added "' + name + '".', 'ok');
        });
    }

    function normalizeAreas(items) {
        return (items || []).map(function (r) {
            r.id = parseInt(r.id, 10);
            r.parent_id = (r.parent_id == null) ? null : parseInt(r.parent_id, 10);
            return r;
        });
    }

    // Edit an area: name, kind, parent. Uses a small prompt-driven flow to stay
    // consistent with the existing prompt-based rename, but constrains parent
    // choices by kind via the server validation (which rejects illegal pairings).
    function editArea(id) {
        var it = areaById(id);
        if (!it) { return; }
        var name = prompt('Area name:', it.name);
        if (name === null) { return; }
        name = name.trim();
        if (!name) { notice('Name is required.'); return; }

        var kind = prompt('Tier - type one of: nationwide, state, region, county', it.kind);
        if (kind === null) { return; }
        kind = kind.trim().toLowerCase();
        if (['nationwide', 'state', 'region', 'county'].indexOf(kind) === -1) {
            notice('Invalid tier. Use nationwide, state, region, or county.'); return;
        }

        var parentId = '';
        if (kind === 'region' || kind === 'county') {
            var choices = parentChoiceList(kind, id);
            if (!choices.length) {
                notice('No valid parent exists for a ' + kind + ' yet. Add a state/region first.'); return;
            }
            var menu = 'Choose a parent by number:\n';
            for (var i = 0; i < choices.length; i++) {
                menu += (i + 1) + ') ' + choices[i].name + ' (' + choices[i].kind + ')\n';
            }
            var pick = prompt(menu, it.parent_id ? String(parentIndex(choices, it.parent_id) + 1) : '1');
            if (pick === null) { return; }
            var n = parseInt(pick, 10);
            if (!n || n < 1 || n > choices.length) { notice('Invalid parent choice.'); return; }
            parentId = choices[n - 1].id;
        }

        call('coverage_area', 'edit', { body: { id: parseInt(id, 10), name: name, kind: kind, parent_id: parentId } }).then(function (d) {
            if (!d.ok) { notice(d.error || 'Edit failed.'); return; }
            areaItems = normalizeAreas(d.items);
            renderAreas();
            populateParentSelect('areaAddParent', document.getElementById('areaAddKind').value, null);
            notice('Saved.', 'ok');
        });
    }

    // Valid parent rows for a kind, excluding the node + its descendants.
    function parentChoiceList(kind, excludeId) {
        var allowedKinds = (kind === 'region') ? { state: true } : { state: true, region: true };
        var exclude = {};
        if (excludeId != null) {
            exclude[excludeId] = true;
            var changed = true;
            while (changed) {
                changed = false;
                for (var i = 0; i < areaItems.length; i++) {
                    var it = areaItems[i];
                    if (it.parent_id != null && exclude[it.parent_id] && !exclude[it.id]) {
                        exclude[it.id] = true; changed = true;
                    }
                }
            }
        }
        var out = [];
        var ordered = orderedTree();
        for (var j = 0; j < ordered.length; j++) {
            if (!allowedKinds[ordered[j].kind]) { continue; }
            if (exclude[ordered[j].id]) { continue; }
            out.push(ordered[j]);
        }
        return out;
    }

    function parentIndex(choices, parentId) {
        for (var i = 0; i < choices.length; i++) { if (choices[i].id === parentId) { return i; } }
        return 0;
    }

    function deleteArea(id, usage) {
        usage = parseInt(usage, 10) || 0;
        var msg = usage > 0
            ? 'This area is assigned to ' + usage + ' vendor(s). Deleting it will remove it from them (the vendors stay). Continue?'
            : 'Delete this area?';
        if (!confirm(msg)) { return; }
        var q = 'id=' + id + (usage > 0 ? '&confirm=1' : '');
        call('coverage_area', 'delete', { query: q }).then(function (d) {
            if (!d.ok && d.needsConfirm) {
                call('coverage_area', 'delete', { query: 'id=' + id + '&confirm=1' }).then(function (d2) {
                    if (!d2.ok) { notice(d2.error || 'Delete failed.'); return; }
                    areaItems = normalizeAreas(d2.items); renderAreas();
                    populateParentSelect('areaAddParent', document.getElementById('areaAddKind').value, null);
                    notice('Deleted.', 'ok');
                });
                return;
            }
            if (!d.ok) { notice(d.error || 'Delete failed.'); return; }
            areaItems = normalizeAreas(d.items); renderAreas();
            populateParentSelect('areaAddParent', document.getElementById('areaAddKind').value, null);
            notice('Deleted.', 'ok');
        });
    }

    // ================= Audit: vendors with no coverage area ================

    function loadAudit() {
        call('coverage_area', 'audit').then(function (d) {
            var sum = document.getElementById('auditSummary');
            var list = document.getElementById('auditList');
            if (!d.ok) { sum.textContent = d.error || 'Could not load audit.'; list.innerHTML = ''; return; }
            if (!d.count) {
                sum.innerHTML = 'Every vendor has at least one coverage area.';
                list.innerHTML = '';
                return;
            }
            sum.innerHTML = '<strong>' + d.count + '</strong> vendor' + (d.count === 1 ? '' : 's') +
                ' have no coverage area. Tag them (e.g. Nationwide) in the staff app.';
            var html = '';
            for (var i = 0; i < d.vendors.length; i++) {
                html += '<li>' + esc(d.vendors[i].name) + '</li>';
            }
            list.innerHTML = html;
        });
    }

    // ================= wiring ===============================================

    document.getElementById('typeItems').addEventListener('click', function (e) {
        var t = e.target;
        if (t.dataset.rename) { renameType(t.dataset.rename); }
        else if (t.dataset.del) { deleteType(t.dataset.del, t.dataset.usage); }
    });
    document.querySelector('[data-add="vendor_type"]').addEventListener('click', addType);
    document.getElementById('typeAdd').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { addType(); }
    });

    document.getElementById('areaItems').addEventListener('click', function (e) {
        var t = e.target;
        if (t.dataset.edit) { editArea(t.dataset.edit); }
        else if (t.dataset.del) { deleteArea(t.dataset.del, t.dataset.usage); }
    });
    document.getElementById('areaAddBtn').addEventListener('click', addArea);
    document.getElementById('areaAddName').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { addArea(); }
    });
    document.getElementById('areaAddKind').addEventListener('change', function () {
        populateParentSelect('areaAddParent', this.value, null);
    });

    clearNotice();
    loadTypes();
    loadAreas();
    loadAudit();
})();
</script>
</body>
</html>
