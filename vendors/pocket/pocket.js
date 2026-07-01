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
                    '<div class="pl-card-broker">Broker: ' + esc(l.broker_name || 'Unknown') + '</div>' +
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
        heroComp = null;
        moreComp = null;
        $('existingImgsNote').hidden = true;
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
        $('fPrice').value = (l.price == null) ? '' : l.price;
        $('fDesc').value = l.description || '';
        setPriceType(l.price_type);
        updateDescCount();
        var count = (l.images || []).length;
        if (count > 0) {
            var note = $('existingImgsNote');
            note.textContent = 'This listing already has ' + count + ' image' + (count === 1 ? '' : 's') +
                '. New uploads are added (up to 5 total).';
            note.hidden = false;
        }
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
            price: $('fPrice').value.trim(),
            price_type: priceType,
            description: $('fDesc').value
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
        if (d.moreFiles.length > 4) { return 'At most 4 additional images are allowed.'; }
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

    // "Commit": submit fields + files in ONE multipart request.
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

        $('btnCommit').disabled = true;
        apiPostForm('action=save', fd).then(function (data) {
            $('btnCommit').disabled = false;
            if (!data.ok) {
                showNotice('reviewError', data.error || 'Could not save the listing.');
                return;
            }
            draft = null;
            closeOverlay('reviewOverlay');
            loadListings();
        }).catch(function () {
            $('btnCommit').disabled = false;
            showNotice('reviewError', 'Network error saving the listing.');
        });
    }

    function wireForm() {
        $('btnNew').addEventListener('click', openNewForm);
        $('formClose').addEventListener('click', function () { closeOverlay('formOverlay'); });
        $('btnFormCancel').addEventListener('click', function () { closeOverlay('formOverlay'); });
        $('fDesc').addEventListener('input', updateDescCount);
        $('ptList').addEventListener('click', function () { setPriceType('list'); });
        $('ptNet').addEventListener('click', function () { setPriceType('net'); });

        // Compress images the moment they are attached (before commit). The
        // resulting small JPEGs are stored and awaited at Review/Commit time.
        $('fHero').addEventListener('change', function () {
            heroComp = startCompression(this.files, 'formError');
        });
        $('fMore').addEventListener('change', function () {
            moreComp = startCompression(this.files, 'formError');
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
            '<div class="pl-detail-broker">Listing broker: ' + esc(l.broker_name || 'Unknown') + '</div>';

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
        ['detailOverlay', 'formOverlay', 'reviewOverlay'].forEach(function (id) {
            $(id).addEventListener('click', function (e) {
                if (e.target === $(id)) { closeOverlay(id); }
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
