// ============================================================
// Shared GitHub Contents API layer for Haley Yachts admin tools
// ------------------------------------------------------------
// Plain top-level globals (no module system on this site). Include via
//   <script src="js/github-api.js"></script>
// before a tool's own inline script. Provides:
//   - token storage on a SINGLE shared localStorage key (one token across tools)
//   - gh* fetch helpers (get / put / delete / list)
//   - friendly 401 handling via a pluggable status reporter
//   - base64 helpers for text and binary (image/PDF) uploads
//
// This is an EXTERNAL JS file, so it is exempt from the GoDaddy
// </html> / </body> / <script> injection hazard that affects inline
// scripts building HTML via template literals. Do not add string-concat
// masking here.
//
// Page-specific UI (token-box buttons, confirmed/entry toggle, status bar)
// stays in each tool. This file exposes only generic primitives:
//   getGhToken, hasGhToken, setGhToken, removeGhToken
// plus the fetch helpers and setGhStatusReporter for 401 messaging.
// ============================================================

var GH_OWNER  = 'captainclarkhaley';
var GH_REPO   = 'haleyyachts-website';
var GH_BRANCH = 'main';
var GH_API    = 'https://api.github.com';
var GH_TOKEN_KEY = 'haley-gh-token';

// ===== Token storage (shared key, browser localStorage only) =====
function getGhToken() { return localStorage.getItem(GH_TOKEN_KEY) || ''; }
function hasGhToken() { return !!getGhToken(); }
function setGhToken(value) { localStorage.setItem(GH_TOKEN_KEY, value); }
function removeGhToken() { localStorage.removeItem(GH_TOKEN_KEY); }

function ghHeaders() {
    return {
        'Authorization': 'Bearer ' + getGhToken(),
        'Accept': 'application/vnd.github+json',
        'X-GitHub-Api-Version': '2022-11-28'
    };
}

// ===== 401 handling with a pluggable reporter =====
// Each page calls setGhStatusReporter(itsOwnShowStatus) once at init so a
// rejected token surfaces in that page's own status bar. Default is a no-op
// so the shared file never assumes a global showStatus exists.
var ghStatusReporter = function (msg, type) {};
function setGhStatusReporter(fn) { if (typeof fn === 'function') ghStatusReporter = fn; }

// Plain-English message for a rejected/expired GitHub token.
var GH_AUTH_MESSAGE = 'GitHub rejected the token (it may be expired, revoked, or invalid). Open the GitHub Publishing section, replace the token with a fresh one, and try again.';

// Build a tagged auth error so every call site can recognize a 401 and show
// the friendly token message instead of a raw status dump.
function ghAuthError() {
    var e = new Error(GH_AUTH_MESSAGE);
    e.isGhAuthError = true;
    return e;
}

// In a catch block, show the friendly token message on a 401, otherwise the
// supplied generic fallback (already including the error detail).
function showGhError(err, fallbackMessage) {
    if (err && err.isGhAuthError) {
        ghStatusReporter(GH_AUTH_MESSAGE, 'error');
    } else {
        ghStatusReporter(fallbackMessage, 'error');
    }
}

// ===== Contents API helpers =====

// GET a file's sha (and decoded text if asked). Returns null if 404.
async function ghGetFile(path, wantText) {
    const url = GH_API + '/repos/' + GH_OWNER + '/' + GH_REPO + '/contents/' + encodeApiPath(path) + '?ref=' + GH_BRANCH;
    const res = await fetch(url, { headers: ghHeaders(), cache: 'no-store' });
    if (res.status === 401) throw ghAuthError();
    if (res.status === 404) return null;
    if (!res.ok) throw new Error('GitHub GET ' + path + ' failed: ' + res.status + ' ' + (await res.text()).slice(0, 200));
    const json = await res.json();
    let text = null;
    if (wantText && json.content) {
        text = decodeURIComponent(escape(atob(json.content.replace(/\n/g, ''))));
    }
    return { sha: json.sha, text };
}

// PUT (create/update) a file. content is a base64 string. Retries once on sha conflict.
async function ghPutFile(path, base64Content, message, sha) {
    const url = GH_API + '/repos/' + GH_OWNER + '/' + GH_REPO + '/contents/' + encodeApiPath(path);
    const body = { message: message, content: base64Content, branch: GH_BRANCH };
    if (sha) body.sha = sha;
    let res = await fetch(url, { method: 'PUT', headers: ghHeaders(), body: JSON.stringify(body) });
    if (res.status === 409) {
        const fresh = await ghGetFile(path, false);
        body.sha = fresh ? fresh.sha : undefined;
        res = await fetch(url, { method: 'PUT', headers: ghHeaders(), body: JSON.stringify(body) });
    }
    if (res.status === 401) throw ghAuthError();
    if (!res.ok) throw new Error('GitHub PUT ' + path + ' failed: ' + res.status + ' ' + (await res.text()).slice(0, 200));
    return res.json();
}

// Deletes a file via the Contents API. A file that is already absent is treated
// as already-deleted and never throws: 404 on the sha lookup, or 404 on the
// DELETE itself (the file vanished between lookup and delete). Returns true if
// this call removed the file, false if it was already gone. Callers that don't
// care about the distinction can ignore the return value.
async function ghDeleteFile(path, message) {
    const existing = await ghGetFile(path, false);
    if (!existing) return false;
    const url = GH_API + '/repos/' + GH_OWNER + '/' + GH_REPO + '/contents/' + encodeApiPath(path);
    const res = await fetch(url, {
        method: 'DELETE', headers: ghHeaders(),
        body: JSON.stringify({ message: message, sha: existing.sha, branch: GH_BRANCH })
    });
    if (res.status === 401) throw ghAuthError();
    if (res.status === 404) return false;
    if (!res.ok) throw new Error('GitHub DELETE ' + path + ' failed: ' + res.status);
    return true;
}

async function ghListDir(path) {
    const url = GH_API + '/repos/' + GH_OWNER + '/' + GH_REPO + '/contents/' + encodeApiPath(path) + '?ref=' + GH_BRANCH;
    const res = await fetch(url, { headers: ghHeaders(), cache: 'no-store' });
    if (res.status === 401) throw ghAuthError();
    if (res.status === 404) return [];
    if (!res.ok) throw new Error('GitHub list ' + path + ' failed: ' + res.status);
    return res.json();
}

function encodeApiPath(path) {
    return path.split('/').map(encodeURIComponent).join('/');
}

// ===== base64 helpers =====
function strToBase64(str) {
    return btoa(unescape(encodeURIComponent(str)));
}
async function blobToBase64(blob) {
    const buf = await blob.arrayBuffer();
    let binary = '';
    const bytes = new Uint8Array(buf);
    const chunk = 0x8000;
    for (let i = 0; i < bytes.length; i += chunk) {
        binary += String.fromCharCode.apply(null, bytes.subarray(i, i + chunk));
    }
    return btoa(binary);
}
