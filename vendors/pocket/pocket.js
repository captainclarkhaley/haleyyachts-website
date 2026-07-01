/* ============================================================
   Pocket Listings - staff app front end (Broker Suite app #2).
   Talks to api.php (same /vendors/ realm + session).
   Phase 1: list/search, new+edit form, review-then-commit,
   image upload, detail view. NO network email / print-share /
   expiration / comps - those are later phases.
   ============================================================ */

(function () {
    'use strict';

    var API = 'api.php';
    var DESC_MAX = 750;

    // Client-side image compression settings. Mirrors the server backstop
    // (api.php resizes to 1600px too) but doing it here keeps the UPLOAD small
    // so a big phone photo never trips the server's post_max_size limit.
    var IMG_MAX_DIM = 1600;      // longest side, px (never upscale a smaller image)
    var IMG_JPEG_QUALITY = 0.82; // canvas.toBlob JPEG quality

    // Identity from the server-rendered <body> data attributes. The SERVER still
    // enforces every permission; these only decide which buttons to show.
    var CURRENT_USER_ID = parseInt(document.body.getAttribute('data-user-id'), 10) || 0;
    var IS_ADMIN = document.body.getAttribute('data-is-admin') === '1';

    // Working state.
    var listings = [];        // last result set rendered
    var draft = null;         // the pending form draft awaiting commit (review stage)
    var priceType = 'list';   // Net/List toggle in the form

    // Edit-only image state. existingImgs is the listing's current images (each
    // {id, url, is_hero}); removedIds holds the integer ids the broker marked for
    // removal. Both reset in resetForm(). New-listing flow leaves these empty.
    var existingImgs = [];
    var removedIds = {};      // set keyed by int id -> true

    function $(id) { return document.getElementById(id); }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function debounce(fn, ms) {
        var t;
        return function () {
            var a = arguments, self = this;
            clearTimeout(t);
            t = setTimeout(function () { fn.apply(self, a); }, ms);
        };
    }

    // Format a whole-dollar price as $1,234,567.
    function fmtPrice(n) {
        if (n == null || n === '') { return 'Price on request'; }
        var num = Number(n);
        if (!isFinite(num)) { return 'Price on request'; }
        return '$' + num.toLocaleString('en-US');
    }

    // Format a US phone for display: 10 digits -> (305) 555-1212, 11 with a
    // leading 1 -> 1 (305) 555-1212, anything else returned as entered.
    function formatPhone(raw) {
        var s = String(raw == null ? '' : raw).trim();
        if (!s) { return s; }
        var d = s.replace(/\D/g, '');
        if (d.length === 10) { return '(' + d.slice(0, 3) + ') ' + d.slice(3, 6) + '-' + d.slice(6); }
        if (d.length === 11 && d.charAt(0) === '1') { return '1 (' + d.slice(1, 4) + ') ' + d.slice(4, 7) + '-' + d.slice(7); }
        return s;
    }

    // Render a stored UTC "YYYY-MM-DD HH:MM:SS" as a readable "Mon D, YYYY".
    function formatDate(raw) {
        var s = String(raw == null ? '' : raw).trim();
        if (!s) { return ''; }
        var p = s.split(' ')[0].split('-');
        if (p.length !== 3) { return s.split(' ')[0]; }
        var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                      'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        var mi = parseInt(p[1], 10) - 1;
        if (mi < 0 || mi > 11) { return s.split(' ')[0]; }
        return months[mi] + ' ' + parseInt(p[2], 10) + ', ' + p[0];
    }

    // Digits only, and the same digits grouped with thousands commas.
    function digitsOnly(s) { return String(s == null ? '' : s).replace(/\D/g, ''); }
    function withCommas(s) {
        var d = digitsOnly(s);
        return d === '' ? '' : d.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    // Live thousands-separator formatting on a text input, caret-preserving.
    function attachPriceFormat(el) {
        if (!el) { return; }
        el.addEventListener('input', function () {
            var caret = el.selectionStart;
            var digitsLeft = digitsOnly(el.value.slice(0, caret)).length;
            var formatted = withCommas(el.value);
            el.value = formatted;
            var pos = 0, seen = 0;
            while (pos < formatted.length && seen < digitsLeft) {
                if (/\d/.test(formatted.charAt(pos))) { seen++; }
                pos++;
            }
            if (el.setSelectionRange) { el.setSelectionRange(pos, pos); }
        });
    }

    // ---- response parsing (mirrors the vendor app: 401 -> login, 403 must_change) ----
    function parseJson(res) {
        return res.text().then(function (t) {
            var data;
            try { data = t ? JSON.parse(t) : {}; } catch (e) { data = { ok: false, error: 'Bad server response.' }; }
            if (!res.ok && data.ok !== false) { data.ok = false; }
            data._status = res.status;
            if (res.status === 401) {
                window.location.href = '../login.html';
                return new Promise(function () {});
            }
            if (res.status === 403 && data && data.must_change) {
                window.location.href = '../change-password.html';
                return new Promise(function () {});
            }
            return data;
        });
    }

    function apiGet(qs) {
        return fetch(API + '?' + qs, { headers: { 'Accept': 'application/json' } }).then(parseJson);
    }
    // Multipart POST (for save, which carries files). Body is a FormData.
    function apiPostForm(qs, formData) {
        return fetch(API + '?' + qs, {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            body: formData
        }).then(parseJson);
    }
    // Simple POST (delete) - id in the query string.
    function apiPost(qs) {
        return fetch(API + '?' + qs, {
            method: 'POST',
            headers: { 'Accept': 'application/json' }
        }).then(parseJson);
    }

    // Multipart POST via XMLHttpRequest so we can report UPLOAD progress (fetch
    // cannot). Posts the same FormData to api.php?<qs>. onProgress(pct) is called
    // with 0-100 while bytes upload (or with null when the total is not known),
    // then again with 100 once the upload completes. Re-implements the auth
    // handling that parseJson does (XHR bypasses it): 401 -> login, 403+
    // must_change -> change-password. Resolves with the parsed { ok, error,
    // listing } body; rejects with an Error carrying a clear message on network
    // failure or a non-JSON body.
    function apiPostFormXhr(qs, formData, onProgress) {
        return new Promise(function (resolve, reject) {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', API + '?' + qs);
            xhr.setRequestHeader('Accept', 'application/json');

            if (xhr.upload && typeof onProgress === 'function') {
                xhr.upload.onprogress = function (e) {
                    if (e.lengthComputable && e.total > 0) {
                        onProgress(Math.round((e.loaded / e.total) * 100));
                    } else {
                        onProgress(null);
                    }
                };
                // Upload finished; server is now processing (resize + DB write).
                xhr.upload.onload = function () { onProgress(100); };
            }

            xhr.onload = function () {
                var status = xhr.status;
                if (status === 401) {
                    window.location.href = '../login.html';
                    return; // leave the promise pending; navigation takes over
                }
                var data;
                try { data = xhr.responseText ? JSON.parse(xhr.responseText) : {}; }
                catch (e) { data = null; }

                if (status === 403 && data && data.must_change) {
                    window.location.href = '../change-password.html';
                    return;
                }
                if (data === null) {
                    reject(new Error('Bad server response.'));
                    return;
                }
                if (status < 200 || status >= 300) {
                    if (data.ok !== false) { data.ok = false; }
                }
                data._status = status;
                resolve(data);
            };
            xhr.onerror = function () { reject(new Error('Network error saving the listing.')); };
            xhr.ontimeout = function () { reject(new Error('The upload timed out. Please try again.')); };

            xhr.send(formData);
        });
    }

    // ======================================================================
    // LIST + SEARCH/FILTER
    // ======================================================================

    function currentFilterQS() {
        var params = [];
        function add(key, val) {
            val = (val == null) ? '' : String(val).trim();
            if (val !== '') { params.push(key + '=' + encodeURIComponent(val)); }
        }
        add('q', $('fKeyword').value);
        add('make', $('fMake').value);
        add('year_min', $('fYearMin').value);
        add('year_max', $('fYearMax').value);
        add('length_min', $('fLenMin').value);
        add('length_max', $('fLenMax').value);
        add('price_min', $('fPriceMin').value);
        add('price_max', $('fPriceMax').value);
        return params.join('&');
    }

    function loadListings() {
        var qs = 'action=list';
        var f = currentFilterQS();
        if (f) { qs += '&' + f; }
        apiGet(qs).then(function (data) {
            if (!data.ok) {
                $('cards').innerHTML = '<div class="pl-empty">' + esc(data.error || 'Could not load listings.') + '</div>';
                $('resultCount').textContent = '';
                return;
            }
            listings = data.listings || [];
            renderCards();
        }).catch(function () {
            $('cards').innerHTML = '<div class="pl-empty">Network error loading listings.</div>';
        });
    }

    function renderCards() {
        var wrap = $('cards');
        var n = listings.length;
        $('resultCount').innerHTML = '<strong>' + n + '</strong> active listing' + (n === 1 ? '' : 's');

        if (n === 0) {
            wrap.innerHTML = '<div class="pl-empty">No listings match. Add one with "+ New Pocket Listing".</div>';
            return;
        }

        var html = '';
        for (var i = 0; i < n; i++) {
            var l = listings[i];
            var titleBits = [l.make, l.model, l.year].filter(function (x) { return x != null && x !== ''; });
            var title = esc(titleBits.join(' ')) || 'Listing';
            var metaBits = [];
            if (l.length) { metaBits.push(esc(l.length) + ' ft'); }
            if (l.location) { metaBits.push(esc(l.location)); }
            var meta = metaBits.join(' &middot; ');
            var ptLabel = (l.price_type === 'net') ? 'NET' : 'LIST';

            var thumb;
            if (l.hero_url) {
                thumb = '<div class="pl-card-thumb" style="background-image:url(\'' + esc(l.hero_url) + '\')">' +
                    '<span class="pl-card-status">' + esc(l.status || 'active') + '</span></div>';
            } else {
                thumb = '<div class="pl-card-thumb noimg">No photo' +
                    '<span class="pl-card-status">' + esc(l.status || 'active') + '</span></div>';
            }

            html += '<div class="pl-card" data-id="' + l.id + '">' +
                thumb +
                '<div class="pl-card-body">' +
                    '<div class="pl-card-title">' + title + '</div>' +
                    (meta ? '<div class="pl-card-meta">' + meta + '</div>' : '') +
                    '<div class="pl-card-price">' + esc(fmtPrice(l.price)) +
                        '<span class="pl-price-type">' + ptLabel + '</span></div>' +
                    '<div class="pl-card-broker">Broker: ' + esc(l.broker_name || 'Unknown') +
                        (l.broker_phone ? ' &middot; ' + esc(formatPhone(l.broker_phone)) : '') + '</div>' +
                    (l.created_at ? '<div class="pl-card-date">Listed ' + esc(formatDate(l.created_at)) + '</div>' : '') +
                    '<div class="pl-card-actions">' +
                        '<a class="pl-card-print" href="print.php?id=' + l.id + '" ' +
                            'target="_blank" rel="noopener" data-print="' + l.id + '">Print</a>' +
                    '</div>' +
                '</div>' +
            '</div>';
        }
        wrap.innerHTML = html;

        var cards = wrap.querySelectorAll('.pl-card');
        for (var j = 0; j < cards.length; j++) {
            cards[j].addEventListener('click', function () {
                openDetail(parseInt(this.getAttribute('data-id'), 10));
            });
        }
        // Print links open the one-pager in a new tab; stop the click from also
        // opening the card detail overlay behind it.
        var printLinks = wrap.querySelectorAll('.pl-card-print');
        for (var k = 0; k < printLinks.length; k++) {
            printLinks[k].addEventListener('click', function (e) {
                e.stopPropagation();
            });
        }
    }

    var debouncedLoad = debounce(loadListings, 280);

    function wireFilters() {
        ['fKeyword', 'fYearMin', 'fYearMax', 'fLenMin', 'fLenMax', 'fPriceMin', 'fPriceMax']
            .forEach(function (id) { $(id).addEventListener('input', debouncedLoad); });
        $('fMake').addEventListener('change', loadListings);
        $('btnClear').addEventListener('click', function () {
            ['fKeyword', 'fYearMin', 'fYearMax', 'fLenMin', 'fLenMax', 'fPriceMin', 'fPriceMax']
                .forEach(function (id) { $(id).value = ''; });
            $('fMake').value = '';
            loadListings();
        });
    }

    // Transient top-center toast (self-styled, auto-dismiss). Used as a light
    // testing aid to surface the network-email result to admins after a commit.
    function plToast(msg, ok) {
        var t = document.createElement('div');
        t.textContent = msg;
        t.style.cssText = 'position:fixed;top:16px;left:50%;transform:translateX(-50%);z-index:9999;'
            + 'background:' + (ok ? '#0a1628' : '#c0392b') + ';color:#fff;padding:10px 18px;'
            + 'border-radius:8px;font-size:.85rem;font-weight:600;'
            + 'box-shadow:0 8px 24px -8px rgba(10,22,40,0.5);opacity:0;transition:opacity .2s;';
        document.body.appendChild(t);
        requestAnimationFrame(function () { t.style.opacity = '1'; });
        setTimeout(function () {
            t.style.opacity = '0';
            setTimeout(function () { if (t.parentNode) { t.parentNode.removeChild(t); } }, 300);
        }, 5000);
    }

    // ======================================================================
    // OVERLAY helpers
    // ======================================================================

    function openOverlay(id) {
        var ov = $(id);
        ov.classList.add('open');
        ov.setAttribute('aria-hidden', 'false');
    }
    function closeOverlay(id) {
        var ov = $(id);
        ov.classList.remove('open');
        ov.setAttribute('aria-hidden', 'true');
    }
    function showNotice(id, msg) { var e = $(id); e.textContent = msg; e.classList.add('show'); }
    function clearNotice(id) { var e = $(id); e.textContent = ''; e.classList.remove('show'); }

    // ======================================================================
    // CLIENT-SIDE IMAGE COMPRESSION
    // ----------------------------------------------------------------------
    // When a broker attaches a photo we resize it (longest side <= 1600px) and
    // re-encode to JPEG in the browser BEFORE it goes into the draft/upload. The
    // server still validates + resizes as the backstop; this just keeps the
    // multipart body small so a 10 MB phone photo does not trip post_max_size.
    // ======================================================================

    // Decode a File into something drawable, honouring EXIF orientation. Prefer
    // createImageBitmap with imageOrientation:'from-image' (auto-rotates); fall
    // back to a plain <img> + object URL (modern browsers auto-orient a drawn
    // <img> anyway). Resolves with { source, width, height, cleanup }.
    function decodeImage(file) {
        var canBitmap = (typeof createImageBitmap === 'function');
        if (canBitmap) {
            try {
                return createImageBitmap(file, { imageOrientation: 'from-image' }).then(function (bmp) {
                    return { source: bmp, width: bmp.width, height: bmp.height, cleanup: function () {
                        if (bmp.close) { bmp.close(); }
                    } };
                }).catch(function () {
                    return decodeImageViaTag(file);
                });
            } catch (e) {
                // Older engine that lacks the imageOrientation option throws.
                return decodeImageViaTag(file);
            }
        }
        return decodeImageViaTag(file);
    }

    function decodeImageViaTag(file) {
        return new Promise(function (resolve, reject) {
            var url = URL.createObjectURL(file);
            var img = new Image();
            img.onload = function () {
                resolve({ source: img, width: img.naturalWidth, height: img.naturalHeight,
                    cleanup: function () { URL.revokeObjectURL(url); } });
            };
            img.onerror = function () {
                URL.revokeObjectURL(url);
                reject(new Error('decode failed'));
            };
            img.src = url;
        });
    }

    // Resize + JPEG re-encode a File. Resolves with a compressed File (.jpg), or
    // the ORIGINAL file if it cannot be decoded/encoded (graceful fallback: the
    // server still validates + resizes). Never rejects.
    function compressImage(file) {
        if (!file || !/^image\//i.test(file.type || '')) {
            return Promise.resolve(file);
        }
        return decodeImage(file).then(function (dec) {
            var w = dec.width, h = dec.height;
            if (!w || !h) { dec.cleanup(); return file; }

            var scale = 1;
            if (w > IMG_MAX_DIM || h > IMG_MAX_DIM) {
                scale = (w >= h) ? (IMG_MAX_DIM / w) : (IMG_MAX_DIM / h);
            }
            var nw = Math.max(1, Math.round(w * scale));
            var nh = Math.max(1, Math.round(h * scale));

            var canvas = document.createElement('canvas');
            canvas.width = nw;
            canvas.height = nh;
            var ctx = canvas.getContext('2d');
            if (!ctx) { dec.cleanup(); return file; }
            // White backfill so PNG transparency flattens to white (not black).
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, nw, nh);
            ctx.drawImage(dec.source, 0, 0, nw, nh);
            dec.cleanup();

            return new Promise(function (resolve) {
                if (!canvas.toBlob) { resolve(file); return; }
                canvas.toBlob(function (blob) {
                    if (!blob) { resolve(file); return; }
                    var base = (file.name || 'image').replace(/\.[^.]+$/, '');
                    var name = (base || 'image') + '.jpg';
                    var out;
                    try {
                        out = new File([blob], name, { type: 'image/jpeg' });
                    } catch (e) {
                        // Some engines lack the File constructor; a named Blob works
                        // for FormData too, but tag it so the multipart part is sane.
                        blob.name = name;
                        out = blob;
                    }
                    resolve(out);
                }, 'image/jpeg', IMG_JPEG_QUALITY);
            });
        }).catch(function () {
            // Undecodable / unsupported: fall back to the original file.
            return file;
        });
    }

    // Per-input compression state. Each holds { promise, files } where promise
    // resolves to an array of compressed Files. readFormDraft() awaits these so a
    // commit never races ahead of an in-flight resize.
    var heroComp = null;   // single-file: files is [File] or []
    var moreComp = null;   // multi-file: files is [File, ...]

    function fmtBytes(b) {
        b = b || 0;
        if (b < 1024 * 1024) { return Math.max(1, Math.round(b / 1024)) + ' KB'; }
        return (b / 1048576).toFixed(1) + ' MB';
    }

    // Sum the compressed sizes of everything currently attached (hero + more)
    // and show it under the image inputs, so the broker sees the real upload
    // payload. Awaits both compression promises so the number is post-shrink.
    function updateOptTotal() {
        var proms = [];
        if (heroComp) { proms.push(heroComp.promise); }
        if (moreComp) { proms.push(moreComp.promise); }
        if (!proms.length) { clearNotice('optNote'); return; }
        Promise.all(proms).then(function (results) {
            var files = [];
            results.forEach(function (r) { files = files.concat(r || []); });
            if (!files.length) { clearNotice('optNote'); return; }
            var bytes = files.reduce(function (a, f) { return a + ((f && f.size) || 0); }, 0);
            showNotice('optNote', files.length + ' image' + (files.length === 1 ? '' : 's') +
                ' optimized · ' + fmtBytes(bytes) + ' to upload');
        });
    }

    // Kick off compression for a freshly-selected FileList. Returns a state
    // object; also flips a lightweight "optimizing" note while it runs.
    function startCompression(fileList, noticeId) {
        var files = Array.prototype.slice.call(fileList);
        if (!files.length) { return { promise: Promise.resolve([]), files: [] }; }
        showNotice(noticeId, 'Optimizing image' + (files.length === 1 ? '' : 's') + '...');
        var promise = Promise.all(files.map(compressImage)).then(function (out) {
            clearNotice(noticeId);
            return out;
        });
        return { promise: promise, files: files };
    }

    // ======================================================================
    // NEW / EDIT FORM
    // ======================================================================

    function setPriceType(v) {
        priceType = (v === 'net') ? 'net' : 'list';
        $('ptList').classList.toggle('active', priceType === 'list');
        $('ptNet').classList.toggle('active', priceType === 'net');
    }

    function updateDescCount() {
        var len = $('fDesc').value.length;
        var el = $('descCount');
        el.textContent = len + ' / ' + DESC_MAX;
        el.classList.toggle('over', len > DESC_MAX);
    }

    function resetForm() {
        $('fId').value = '';
        $('fFormMake').value = '';
        $('fModel').value = '';
        $('fYear').value = '';
        $('fLength').value = '';
        $('fLocation').value = '';
        $('fDays').value = '';
        $('fPrice').value = '';
        $('fDesc').value = '';
        $('fHero').value = '';
        $('fMore').value = '';
        setPriceType('list');
        updateDescCount();
        clearNotice('formError');
        clearNotice('optNote');
        heroComp = null;
        moreComp = null;
        existingImgs = [];
        removedIds = {};
        $('existingThumbs').innerHTML = '';
        $('existingImgs').hidden = true;
        $('imgCountNote').hidden = true;
    }

    // Count existing images NOT marked for removal.
    function keptExistingCount() {
        var kept = 0;
        for (var i = 0; i < existingImgs.length; i++) {
            if (!removedIds[existingImgs[i].id]) { kept++; }
        }
        return kept;
    }

    // Count new files currently attached (compressed state if present, else the
    // raw inputs). Hero is 0/1; more is the additional count.
    function newFileCounts() {
        var hero = heroComp
            ? (heroComp.files.length ? 1 : 0)
            : ($('fHero').files.length ? 1 : 0);
        var more = moreComp
            ? moreComp.files.length
            : $('fMore').files.length;
        return { hero: hero, more: more };
    }

    // Live "Keeping N of M. You can add K more." line under the image inputs.
    // Only shown on edit (when the listing had images). Recomputed as thumbnails
    // are removed and as new files are attached.
    function updateImgCount() {
        var note = $('imgCountNote');
        if (!existingImgs.length) { note.hidden = true; return; }
        var total = existingImgs.length;
        var kept = keptExistingCount();
        var nf = newFileCounts();
        var finalCount = kept + nf.hero + nf.more;
        var room = 4 - finalCount;
        var msg = 'Keeping ' + kept + ' of ' + total + '. ';
        if (room > 0) {
            msg += 'You can add ' + room + ' more.';
        } else if (room === 0) {
            msg += 'At the 4-image limit.';
        } else {
            msg += 'Over the 4-image limit by ' + (-room) + '. Remove some.';
        }
        note.textContent = msg;
        note.hidden = false;
    }

    // Render the current-image thumbnails for the edit form. Each has a remove
    // (x) control; the hero gets a small badge. Removing a thumbnail marks its id
    // in removedIds, fades the node, and recomputes the live count.
    function renderExistingThumbs() {
        var wrap = $('existingThumbs');
        wrap.innerHTML = '';
        if (!existingImgs.length) {
            $('existingImgs').hidden = true;
            return;
        }
        $('existingImgs').hidden = false;
        for (var i = 0; i < existingImgs.length; i++) {
            var img = existingImgs[i];
            var cell = document.createElement('div');
            cell.className = 'pl-ex-thumb';
            cell.setAttribute('data-img-id', String(img.id));

            var pic = document.createElement('img');
            pic.src = img.url;
            pic.alt = 'Listing image';
            cell.appendChild(pic);

            if (img.is_hero) {
                var badge = document.createElement('span');
                badge.className = 'pl-ex-badge';
                badge.textContent = 'Hero';
                cell.appendChild(badge);
            }

            var x = document.createElement('button');
            x.type = 'button';
            x.className = 'pl-ex-remove';
            x.setAttribute('aria-label', 'Remove this image');
            x.textContent = '×';
            (function (id, node) {
                x.addEventListener('click', function () {
                    removedIds[id] = true;
                    node.classList.add('removed');
                    updateImgCount();
                });
            })(img.id, cell);
            cell.appendChild(x);

            wrap.appendChild(cell);
        }
    }

    function openNewForm() {
        resetForm();
        $('formTitle').textContent = 'New Pocket Listing';
        openOverlay('formOverlay');
        $('fFormMake').focus();
    }

    // Open the form pre-filled from an existing listing (edit). Images are not
    // re-uploaded on edit unless the broker adds new ones; we note the count.
    function openEditForm(l) {
        resetForm();
        $('formTitle').textContent = 'Edit Pocket Listing';
        $('fId').value = l.id;
        $('fFormMake').value = l.make || '';
        $('fModel').value = l.model || '';
        $('fYear').value = (l.year == null) ? '' : l.year;
        $('fLength').value = (l.length == null) ? '' : l.length;
        $('fLocation').value = l.location || '';
        $('fDays').value = (l.days_active == null) ? '' : l.days_active;
        $('fPrice').value = (l.price == null) ? '' : withCommas(String(l.price));
        $('fDesc').value = l.description || '';
        setPriceType(l.price_type);
        updateDescCount();
        // Populate the current-image thumbnails so the broker can remove/replace
        // them. The live count line reflects removals + new attachments.
        existingImgs = (l.images || []).map(function (im) {
            return { id: im.id, url: im.url, is_hero: !!im.is_hero };
        });
        removedIds = {};
        renderExistingThumbs();
        updateImgCount();
        openOverlay('formOverlay');
        $('fFormMake').focus();
    }

    // Read the form into a draft object. Resolves with the draft once any
    // in-flight image compression has finished, so heroFile / moreFiles are the
    // SMALL (resized + JPEG) versions that get uploaded. If nothing was selected
    // (or the input changed without a stored compression state) it falls back to
    // reading the raw file inputs directly. Returns a Promise.
    function readFormDraft() {
        var base = {
            id: $('fId').value ? parseInt($('fId').value, 10) : 0,
            make: $('fFormMake').value.trim(),
            model: $('fModel').value.trim(),
            year: $('fYear').value.trim(),
            length: $('fLength').value.trim(),
            location: $('fLocation').value.trim(),
            days_active: $('fDays').value.trim(),
            price: digitsOnly($('fPrice').value),
            price_type: priceType,
            description: $('fDesc').value,
            // Existing images marked for removal (ints). Empty on new listings.
            removeIds: Object.keys(removedIds).map(function (k) { return parseInt(k, 10); })
                .filter(function (n) { return n > 0; }),
            // Snapshot of how many existing images survive, for the review guard.
            keptExisting: keptExistingCount()
        };

        // Await the compressed results. If no compression state exists for an
        // input (e.g. it was never touched) but the raw input holds files, fall
        // back to those originals so nothing is silently dropped.
        var heroPromise = heroComp
            ? heroComp.promise.then(function (arr) { return arr.length ? arr[0] : null; })
            : Promise.resolve($('fHero').files.length ? $('fHero').files[0] : null);
        var morePromise = moreComp
            ? moreComp.promise
            : Promise.resolve(Array.prototype.slice.call($('fMore').files));

        return Promise.all([heroPromise, morePromise]).then(function (r) {
            base.heroFile = r[0];
            base.moreFiles = r[1];
            return base;
        });
    }

    // Client-side validation before the review stage (server re-validates).
    function validateDraft(d) {
        if (!d.make) { return 'Make is required.'; }
        if (d.description.length > DESC_MAX) { return 'Description exceeds ' + DESC_MAX + ' characters.'; }
        if (d.moreFiles.length > 3) { return 'At most 3 additional images are allowed.'; }
        // Final image count after removals + new uploads must fit the 4-image cap.
        // Hero is not forced client-side (the server guarantees exactly one hero);
        // this only blocks going OVER the limit so the broker gets feedback before
        // the commit round-trip.
        var newHero = d.heroFile ? 1 : 0;
        var finalCount = d.keptExisting + newHero + d.moreFiles.length;
        if (finalCount > 4) {
            return 'A listing may have at most 4 images. Remove some before saving.';
        }
        var numeric = [['year', d.year], ['length', d.length], ['price', d.price], ['days_active', d.days_active]];
        for (var i = 0; i < numeric.length; i++) {
            var v = numeric[i][1];
            if (v !== '' && isNaN(Number(v))) {
                return numeric[i][0].replace('_', ' ') + ' must be a number.';
            }
        }
        return null;
    }

    // "Save -> Review": validate, build the draft, render the preview card.
    // Image compression is async, so this awaits readFormDraft(). The Review
    // button is disabled while a big photo is still optimizing so the broker
    // cannot race ahead of the resize.
    function goToReview() {
        clearNotice('formError');
        var btn = $('btnReview');
        btn.disabled = true;
        readFormDraft().then(function (d) {
            btn.disabled = false;
            var err = validateDraft(d);
            if (err) { showNotice('formError', err); return; }
            draft = d;
            renderReview(d);
            closeOverlay('formOverlay');
            clearNotice('reviewError');
            openOverlay('reviewOverlay');
        }).catch(function () {
            btn.disabled = false;
            showNotice('formError', 'Could not prepare the images. Please try again.');
        });
    }

    // ======================================================================
    // REVIEW CARD (client-side preview; hero to the RIGHT of the info)
    // ======================================================================

    function renderReview(d) {
        var titleBits = [d.make, d.model, d.year].filter(function (x) { return x !== '' && x != null; });
        var title = esc(titleBits.join(' ')) || 'Listing';
        var ptLabel = (d.price_type === 'net') ? 'NET' : 'LIST';

        // Build the hero preview from the chosen File via an object URL.
        var heroHtml;
        if (d.heroFile) {
            var url = URL.createObjectURL(d.heroFile);
            heroHtml = '<img class="pl-rc-hero" src="' + url + '" alt="Hero preview">';
        } else {
            heroHtml = '<div class="pl-rc-hero-empty">No hero image</div>';
        }
        var thumbsHtml = '';
        if (d.moreFiles.length) {
            thumbsHtml = '<div class="pl-rc-thumbs">';
            for (var i = 0; i < d.moreFiles.length; i++) {
                thumbsHtml += '<img src="' + URL.createObjectURL(d.moreFiles[i]) + '" alt="Additional preview">';
            }
            thumbsHtml += '</div>';
        }

        function line(lbl, val) {
            if (val === '' || val == null) { return ''; }
            return '<div class="pl-rc-line"><span class="lbl">' + esc(lbl) + '</span>' + esc(val) + '</div>';
        }

        var html = '<div class="pl-rc">' +
            '<div class="pl-rc-info">' +
                '<div class="pl-rc-title">' + title + '</div>' +
                line('Length', d.length ? d.length + ' ft' : '') +
                line('Location', d.location) +
                line('Days active', d.days_active) +
                '<div class="pl-rc-price">' + esc(fmtPrice(d.price)) +
                    '<span class="pl-price-type">' + ptLabel + '</span></div>' +
                (d.description ? '<div class="pl-rc-desc">' + esc(d.description) + '</div>' : '') +
            '</div>' +
            '<div class="pl-rc-media">' + heroHtml + thumbsHtml + '</div>' +
        '</div>';

        $('reviewCard').innerHTML = html;
    }

    // ---- commit progress UI ------------------------------------------------
    // The bar has two phases: a DETERMINATE fill (0-100%) while the image bytes
    // upload, then an INDETERMINATE "Saving listing..." sweep once the upload
    // hits 100% (the server is resizing + writing to the DB, which is not
    // trackable). setCommitProgress drives the determinate fill; goSavingPhase
    // flips it to indeterminate; hideCommitProgress resets it on finish/error.

    function showCommitProgress() {
        var wrap = $('commitProgress');
        wrap.classList.remove('indeterminate');
        $('commitProgressBar').style.width = '0%';
        $('commitProgressLabel').textContent = 'Uploading... 0%';
        wrap.hidden = false;
    }
    function setCommitProgress(pct) {
        // Guard: once we have flipped to the Saving phase, ignore late ticks.
        if ($('commitProgress').classList.contains('indeterminate')) { return; }
        if (pct == null) {
            // Total unknown - show an indeterminate-ish label but keep the label.
            $('commitProgressLabel').textContent = 'Uploading...';
            return;
        }
        var p = Math.max(0, Math.min(100, pct));
        $('commitProgressBar').style.width = p + '%';
        if (p >= 100) {
            goSavingPhase();
        } else {
            $('commitProgressLabel').textContent = 'Uploading... ' + p + '%';
        }
    }
    function goSavingPhase() {
        var wrap = $('commitProgress');
        if (wrap.classList.contains('indeterminate')) { return; }
        wrap.classList.add('indeterminate');
        $('commitProgressBar').style.width = '100%';
        $('commitProgressLabel').textContent = 'Saving listing...';
    }
    function hideCommitProgress() {
        var wrap = $('commitProgress');
        wrap.hidden = true;
        wrap.classList.remove('indeterminate');
        $('commitProgressBar').style.width = '0%';
    }

    // Enable/disable the review overlay's action buttons as a group.
    function setCommitButtonsDisabled(disabled) {
        $('btnCommit').disabled = disabled;
        $('btnReviewEdit').disabled = disabled;
        $('reviewClose').disabled = disabled;
    }

    // "Commit": submit fields + files in ONE multipart request, over XHR so the
    // upload progress can drive the bar.
    function commitDraft() {
        if (!draft) { return; }
        clearNotice('reviewError');
        var fd = new FormData();
        if (draft.id) { fd.append('id', String(draft.id)); }
        fd.append('make', draft.make);
        fd.append('model', draft.model);
        fd.append('year', draft.year);
        fd.append('length', draft.length);
        fd.append('location', draft.location);
        fd.append('days_active', draft.days_active);
        fd.append('price', draft.price);
        fd.append('price_type', draft.price_type);
        fd.append('description', draft.description);
        if (draft.heroFile) { fd.append('hero', draft.heroFile); }
        for (var i = 0; i < draft.moreFiles.length; i++) {
            fd.append('images[]', draft.moreFiles[i]);
        }
        // Existing images the broker removed (edit only). One entry per id.
        if (draft.removeIds && draft.removeIds.length) {
            for (var r = 0; r < draft.removeIds.length; r++) {
                fd.append('remove_images[]', String(draft.removeIds[r]));
            }
        }

        setCommitButtonsDisabled(true);
        showCommitProgress();

        apiPostFormXhr('action=save', fd, setCommitProgress).then(function (data) {
            if (!data.ok) {
                hideCommitProgress();
                setCommitButtonsDisabled(false);
                showNotice('reviewError', data.error || 'Could not save the listing.');
                return;
            }
            // Success: the overlay closes and the list reloads. Reset the UI so a
            // later commit starts clean, and re-enable the buttons behind it.
            var wasNew = !draft.id;
            hideCommitProgress();
            setCommitButtonsDisabled(false);
            draft = null;
            closeOverlay('reviewOverlay');
            loadListings();
            // Testing aid: on a NEW listing, tell admins whether the server's
            // mail() accepted the network notification. This is about acceptance,
            // not delivery - a "sent" that never arrives is a whitelist/SPF issue.
            if (IS_ADMIN && wasNew && typeof data.notify_sent !== 'undefined') {
                plToast(data.notify_sent
                    ? 'Network email: sent ✓'
                    : 'Network email: NOT sent ✗', !!data.notify_sent);
            }
        }).catch(function (err) {
            hideCommitProgress();
            setCommitButtonsDisabled(false);
            showNotice('reviewError', (err && err.message) || 'Network error saving the listing.');
        });
    }

    // ---- "+ Add" manufacturer flow ----------------------------------------
    // Prompt for a name, require an explicit confirm ("are you sure" gate), then
    // POST to add_make. On success add the canonical name as an <option> to BOTH
    // the form select and the filter select (kept in sync) and select it in the
    // form. If the make already exists (case-insensitive) we never create a
    // duplicate - just select the existing option.

    // Find an existing <option> in a select by case-insensitive value match.
    function findOption(sel, name) {
        var target = String(name).toLowerCase();
        for (var i = 0; i < sel.options.length; i++) {
            if (sel.options[i].value.toLowerCase() === target) { return sel.options[i]; }
        }
        return null;
    }

    // Insert a make option alphabetically (case-insensitive) into a select,
    // skipping the leading placeholder/"All makes" option. Returns the option
    // (existing one if already present).
    function insertMakeOption(sel, name) {
        var existing = findOption(sel, name);
        if (existing) { return existing; }
        var opt = document.createElement('option');
        opt.value = name;
        opt.textContent = name;
        var lower = name.toLowerCase();
        var placed = false;
        // Start at index 1 to keep the placeholder first option in place.
        for (var i = 1; i < sel.options.length; i++) {
            if (sel.options[i].value.toLowerCase() > lower) {
                sel.add(opt, sel.options[i]);
                placed = true;
                break;
            }
        }
        if (!placed) { sel.add(opt); }
        return opt;
    }

    function addMake() {
        var raw = window.prompt('New manufacturer name:');
        if (raw == null) { return; }            // cancelled
        var name = raw.trim();
        if (name === '') { return; }            // empty -> do nothing

        var formSel = $('fFormMake');
        // Already in the list? Just select it, no confirm, no POST, no duplicate.
        var have = findOption(formSel, name);
        if (have) { formSel.value = have.value; return; }

        if (!window.confirm('Add "' + name + '" as a new manufacturer?')) { return; }

        var btn = $('btnAddMake');
        btn.disabled = true;
        apiPost('action=add_make&name=' + encodeURIComponent(name)).then(function (data) {
            btn.disabled = false;
            if (!data.ok || !data.name) {
                showNotice('formError', (data && data.error) || 'Could not add the manufacturer.');
                return;
            }
            // Canonical name from the server (may be an existing row's casing).
            var canonical = data.name;
            insertMakeOption(formSel, canonical);
            insertMakeOption($('fMake'), canonical);   // keep the filter select in sync
            // Select the (possibly existing) canonical option in the form.
            var picked = findOption(formSel, canonical);
            if (picked) { formSel.value = picked.value; }
            clearNotice('formError');
        }).catch(function () {
            btn.disabled = false;
            showNotice('formError', 'Network error adding the manufacturer.');
        });
    }

    function wireForm() {
        $('btnNew').addEventListener('click', openNewForm);
        $('btnAddMake').addEventListener('click', addMake);
        $('formClose').addEventListener('click', function () { closeOverlay('formOverlay'); });
        $('btnFormCancel').addEventListener('click', function () { closeOverlay('formOverlay'); });
        $('fDesc').addEventListener('input', updateDescCount);
        attachPriceFormat($('fPrice'));
        $('ptList').addEventListener('click', function () { setPriceType('list'); });
        $('ptNet').addEventListener('click', function () { setPriceType('net'); });

        // Compress images the moment they are attached (before commit). The
        // resulting small JPEGs are stored and awaited at Review/Commit time.
        $('fHero').addEventListener('change', function () {
            heroComp = startCompression(this.files, 'optNote');
            updateOptTotal();
            updateImgCount();
        });
        $('fMore').addEventListener('change', function () {
            moreComp = startCompression(this.files, 'optNote');
            updateOptTotal();
            updateImgCount();
        });

        $('btnReview').addEventListener('click', goToReview);

        // Review overlay
        $('reviewClose').addEventListener('click', function () { closeOverlay('reviewOverlay'); });
        $('btnReviewEdit').addEventListener('click', function () {
            // Back to the form with data intact (the inputs still hold the values;
            // file inputs cannot be programmatically re-set, but the File handles
            // are preserved in `draft` so a commit still carries them).
            closeOverlay('reviewOverlay');
            openOverlay('formOverlay');
        });
        $('btnCommit').addEventListener('click', commitDraft);
    }

    // ======================================================================
    // DETAIL VIEW
    // ======================================================================

    function openDetail(id) {
        apiGet('action=get&id=' + id).then(function (data) {
            if (!data.ok || !data.listing) {
                alert(data.error || 'Could not load the listing.');
                return;
            }
            renderDetail(data.listing);
            openOverlay('detailOverlay');
        });
    }

    function renderDetail(l) {
        var titleBits = [l.make, l.model, l.year].filter(function (x) { return x != null && x !== ''; });
        $('detailTitle').textContent = titleBits.join(' ') || 'Listing';

        var ptLabel = (l.price_type === 'net') ? 'Net' : 'List';
        var heroHtml = l.hero_url
            ? '<img class="pl-detail-hero" src="' + esc(l.hero_url) + '" alt="Listing photo">'
            : '';

        function item(lbl, val) {
            if (val === '' || val == null) { return ''; }
            return '<div class="pl-detail-item"><span class="lbl">' + esc(lbl) + '</span>' +
                '<span class="val">' + esc(val) + '</span></div>';
        }

        var grid = '<div class="pl-detail-grid">' +
            item('Make', l.make) +
            item('Model', l.model) +
            item('Year', l.year) +
            item('Length', l.length ? l.length + ' ft' : '') +
            item('Location', l.location) +
            item('Price', fmtPrice(l.price) + ' (' + ptLabel + ')') +
            item('Days active', l.days_active) +
        '</div>';

        var desc = l.description
            ? '<div class="pl-detail-desc">' + esc(l.description) + '</div>'
            : '';

        // Gallery: all images beyond the hero (hero already shown large above).
        var galleryHtml = '';
        if (l.images && l.images.length) {
            var g = '';
            for (var i = 0; i < l.images.length; i++) {
                g += '<img src="' + esc(l.images[i].url) + '" alt="Listing photo" ' +
                    'onclick="window.open(this.src, \'_blank\')">';
            }
            galleryHtml = '<div class="pl-gallery">' + g + '</div>';
        }

        $('detailBody').innerHTML = heroHtml + grid + desc + galleryHtml +
            '<div class="pl-detail-broker">Listing broker: ' + esc(l.broker_name || 'Unknown') +
                (l.broker_phone ? ' &middot; ' + esc(formatPhone(l.broker_phone)) : '') + '</div>' +
            '<div class="pl-detail-actions">' +
                '<a class="pl-card-print" href="print.php?id=' + l.id + '" target="_blank" rel="noopener">Print one-pager</a>' +
            '</div>';

        // Owner-or-admin controls. The SERVER still enforces this on save/delete;
        // hiding the buttons is only a UI courtesy.
        var canEdit = IS_ADMIN || (l.broker_id === CURRENT_USER_ID);
        $('btnDetailEdit').hidden = !canEdit;
        $('btnDetailDelete').hidden = !canEdit;

        $('btnDetailEdit').onclick = function () {
            closeOverlay('detailOverlay');
            openEditForm(l);
        };
        $('btnDetailDelete').onclick = function () {
            if (!confirm('Delete this listing? This cannot be undone.')) { return; }
            apiPost('action=delete&id=' + l.id).then(function (data) {
                if (!data.ok) { alert(data.error || 'Could not delete.'); return; }
                closeOverlay('detailOverlay');
                loadListings();
            });
        };
    }

    function wireDetail() {
        $('detailClose').addEventListener('click', function () { closeOverlay('detailOverlay'); });
        $('btnDetailClose').addEventListener('click', function () { closeOverlay('detailOverlay'); });
    }

    // ======================================================================
    // Global: backdrop click + Escape close
    // ======================================================================

    function wireGlobal() {
        // Close on a backdrop click ONLY when the mouse press also STARTED on the
        // backdrop. Without this, a text drag that begins inside a field and is
        // released over the backdrop fires a click on the overlay and closes it,
        // losing in-progress entry (matches the vendor app's bindBackdropDismiss).
        ['detailOverlay', 'formOverlay', 'reviewOverlay'].forEach(function (id) {
            var ov = $(id);
            var downOnSelf = false;
            ov.addEventListener('mousedown', function (e) {
                downOnSelf = (e.target === ov);
            });
            ov.addEventListener('click', function (e) {
                if (downOnSelf && e.target === ov) { closeOverlay(id); }
                downOnSelf = false;
            });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape' && e.key !== 'Esc') { return; }
            ['reviewOverlay', 'formOverlay', 'detailOverlay'].forEach(function (id) {
                if ($(id).classList.contains('open')) { closeOverlay(id); }
            });
        });
    }

    // ---- init ----
    wireFilters();
    wireForm();
    wireDetail();
    wireGlobal();
    loadListings();
})();
