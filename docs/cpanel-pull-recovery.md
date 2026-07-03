# cPanel Git Pull Recovery - Broker Suite (brokersuite branch)

Author: Terry. Audience: Clark. No Terminal / SSH needed. cPanel Git Version
Control UI + File Manager only.

## What happened

"Update from Remote" aborted with:

```
error: Your local changes to the following files would be overwritten by merge:
  vendors/.htaccess
  vendors/change-password.html
  vendors/help.php
  vendors/index.php
  vendors/login.html
  vendors/reset.html
  vendors/suite.php
  vendors/pocket/index.php
  vendors/pocket/pocket.js
  vendors/vendors.js
Please commit your changes or stash them before you merge. Aborting
```

Plain-English translation: the copies of these files ON THE SERVER got edited
at some point (through File Manager or by the app), so they no longer match git.
Git will not overwrite server edits it did not make, so it stops. All of these
are APP CODE files. The repo is the source of truth for every one of them, so
the fix is to let git replace the server copies with the repo copies.

IMPORTANT, verified before writing this runbook:

- The repo `brokersuite` version of EVERY file in that list exists, so the pull
  will restore all of them. Nothing gets lost by clearing the server copies.
- Your live per-instance data is NOT in that conflict list and is NOT tracked by
  git, so the pull cannot touch it. That means the SQLite database, the Pocket
  uploads, the branding logo/favicon uploads, the vendor documents store, and
  `mail-secrets.php`. Confirmed safe. (Full list in "Data that must never be
  touched" below.)
- The repo `vendors/.htaccess` on brokersuite is the CLEAN version. It has NO
  password block in it (no `AuthType`, no `Require valid-user`, no cPanel
  Directory Privacy `cp:ppd` block). Pulling it will NOT put the directory
  password wall back. See section 3.

---

## 1. Confirm the deployed branch is `brokersuite` (do this FIRST)

If cPanel is deploying `main`, the new Broker Suite work is not on the server at
all, and no amount of pulling will bring it in. Check first.

1. cPanel home > **Git Version Control**.
2. Find the repository row for the OWYG site (the one whose "Deployment Path" /
   directory is the docroot, e.g. `public_html`). Click **Manage**.
3. Look at the **Checked-Out Branch** field near the top.
   - If it already says `brokersuite`: good, go to section 2.
   - If it says `main` (or anything else): switch it. In the **Pull or Deploy**
     area (or the branch dropdown at the top of Manage), open the branch
     selector, choose **brokersuite**, and confirm the checkout.

CAVEAT: cPanel will only let you switch branches if the working tree is clean.
If the branch dropdown refuses or errors because of "local changes," that is the
SAME conflict from section 2. Do section 2 first to clear the conflicting files,
then come back and switch the branch.

If `brokersuite` does not appear in the dropdown at all, click **Update** /
**Update from Remote** once (it will still abort on the conflict, that is fine)
so cPanel refreshes its list of remote branches, then reopen the dropdown.

---

## 2. Clear the blocking local changes (RECOMMENDED path)

Goal: remove the server's drifted copies of the conflicting APP files so git can
write the repo versions. We do this in File Manager by RENAMING each conflicting
file to a `.bak` name (safer than deleting: nothing is truly gone, and git will
restore the real file on the next pull).

Why rename works: git aborts because a TRACKED file on disk differs from the
repo. If that file is not on disk, git simply recreates it from the repo during
the pull. Renaming it to `something.bak` takes it out of the tracked path, so
the block clears and the pull restores the real file.

### 2a. The conflicting APP files (do these by rename)

In cPanel **File Manager**, navigate into the docroot, then into `vendors/`.
For EACH file below: right-click > **Rename**, and add `.bak` to the end.

Inside `vendors/`:
- `change-password.html`  ->  `change-password.html.bak`
- `help.php`              ->  `help.php.bak`
- `index.php`            ->  `index.php.bak`
- `login.html`           ->  `login.html.bak`
- `reset.html`           ->  `reset.html.bak`
- `suite.php`            ->  `suite.php.bak`
- `vendors.js`           ->  `vendors.js.bak`

Inside `vendors/pocket/`:
- `index.php`            ->  `index.php.bak`
- `pocket.js`            ->  `pocket.js.bak`

Do NOT rename anything else. Do NOT touch any folder. Leave `vendors/.htaccess`
for section 3 (handle it separately, next).

### 2b. Handle `vendors/.htaccess` (see section 3 for the why)

Also rename this one so the pull can proceed:
- `vendors/.htaccess`  ->  `vendors/.htaccess.bak`

Note: `.htaccess` starts with a dot, so it may be hidden. In File Manager,
click **Settings** (top right) and tick **Show Hidden Files (dotfiles)** so you
can see and rename it.

### 2c. Run the pull

1. Back in **Git Version Control** > your repo > **Manage**.
2. Make sure the checked-out branch is `brokersuite` (section 1).
3. Click **Update from Remote** (a.k.a. Pull). It should now succeed with no
   conflict, because none of the blocking files are on disk anymore.
4. cPanel reports success and shows the latest commit hash/message.

### 2d. Clean up the .bak files

After a SUCCESSFUL pull, verify the real files came back (you will see
`index.php`, `suite.php`, etc. present again in `vendors/`). Once confirmed, you
can delete the `.bak` copies in File Manager to keep things tidy. Keep them for a
day if you want a safety net; they are inert and not web-linked.

That is the whole recommended fix. Do NOT do section 5 (the fallback) unless the
pull in 2c still fails.

---

## 3. `vendors/.htaccess` - keep Directory Privacy OFF, keep login working

Background: earlier, the server copy of `vendors/.htaccess` was edited in File
Manager to REMOVE the cPanel Directory Privacy block (the `cp:ppd` /
`Require valid-user` password wall), because that wall was blocking pulls AND
double-gating the new in-app login. The Broker Suite now handles sign-in itself
(login.php / auth.php), so the directory-wide password must stay OFF.

Good news, already verified: the repo `brokersuite` version of
`vendors/.htaccess` is ALSO clean. It contains only:
- a `FilesMatch` rule denying direct access to `.htaccess` / `.htpasswd`,
- `Options -Indexes` (no directory listing),
- `DirectoryIndex suite.php index.php` (front door is the Suite launcher).

There is NO password block in it. So when the pull restores it in section 2c,
you get the correct clean file with the password wall still OFF. Nothing extra
to do.

CHECK AFTER THE PULL (30 seconds): open `vendors/.htaccess` in File Manager
(right-click > **Edit** or **View**). Confirm you do NOT see any of these lines:
- `AuthType Basic`
- `AuthUserFile`
- `Require valid-user`
- a block wrapped in `# cp:ppd` markers

If you DO see any of those (meaning cPanel's Directory Privacy tool got
re-enabled on the folder at some point), fix it the safe way, NOT by hand-editing
the file:
1. cPanel > **Directory Privacy** (a.k.a. Password Protect Directories).
2. Browse to the `vendors/` folder.
3. UNCHECK "Password protect this directory" and Save. cPanel removes its own
   block cleanly.

Do not delete the `AuthType` lines by hand in File Manager. Let the Directory
Privacy tool remove them so cPanel's own state stays consistent.

---

## 4. Post-pull smoke test (the whole point of the exercise)

Run these in a browser against the OWYG suite URL. All should pass.

1. **Login page loads:** go to `/vendors/login.php`. The sign-in page renders.
2. **Legacy shim redirects:** go to `/vendors/login.html`. It should bounce you
   straight to `/vendors/login.php` (same for `change-password.html` and
   `reset.html` if you test them).
3. **Forgot password:** click the forgot-password link, submit a staff email.
   The email that arrives links to `/vendors/reset.php` (not `.html`), and the
   reset link opens and lets you set a new password.
4. **Forced first-login change:** sign in as a user flagged for first login. It
   forces the change-password step before letting you into the app.
5. **Launcher tiles unchanged:** the Suite front door (`/vendors/` or
   `/vendors/suite.php`) shows the same tiles as before, links intact.
6. **New admin sections present:** in admin **Settings**, you now see the new
   **Branding** section and the **Modules** section.
7. **Module toggle works:** flip one module OFF, save, confirm that module's tile
   / access disappears; flip it back ON and confirm it returns.

If all seven pass, the white-label work is live on the server and the recovery
is done.

---

## 5. FALLBACK ONLY - if the pull in 2c still aborts

Only use this if renaming the files did NOT clear the conflict. Removing and
re-cloning the repo is heavier and must be done in the exact order below so live
data is never lost.

Key safety fact: the "Remove" action in cPanel Git Version Control only detaches
git tracking. It LEAVES the files on disk. So removing the repo does not delete
your data. Still, back up the data folders first, belt and suspenders.

### 5a. Back up the data that must never be touched (File Manager)

In `vendors/`, select each of these and use **Compress** to make a zip you can
download, OR **Copy** them to a folder OUTSIDE the docroot (e.g. a
`~/backups/` folder above `public_html`):
- `vendors/api/data/`  (contains the SQLite database, `*.sqlite`)
- `vendors/pocket/uploads/`  (Pocket file uploads)
- `vendors/uploads/branding/`  (admin-uploaded logo / favicon)
- `vendors/api/docs/`  (sensitive vendor documents)
- `vendors/mail-secrets.php`  (mail credentials)
- `vendors/pocket/mail-config.php`  (only if it exists on the server)

Download the zip to your computer before continuing.

### 5b. Remove the repo from Git Version Control

1. Git Version Control > the repo row > **Manage** (or the trash / Remove icon
   on the row).
2. Choose **Remove**. Confirm. This detaches git tracking. Files stay on disk.

### 5c. Re-clone fresh onto the docroot, on brokersuite

1. Git Version Control > **Create**.
2. Clone URL: `https://github.com/captainclarkhaley/haleyyachts-website.git`
3. Repository Path: the SAME docroot as before (e.g. `public_html`). cPanel will
   clone into the existing directory; because your files are already there it may
   ask to proceed - allow it.
4. After it clones, open **Manage** and switch the **Checked-Out Branch** to
   **brokersuite** (section 1).
5. Click **Update from Remote** to make sure it is at the latest commit.

### 5d. Restore / verify the data folders

The gitignored data folders are not in the repo, so the fresh clone will not
include real data - it only brings tracked placeholder files (`.htaccess`,
`.gitkeep`). Verify each live folder still has your real data:
- `vendors/api/data/` still has your `.sqlite` file.
- `vendors/pocket/uploads/` still has real uploads.
- `vendors/uploads/branding/` still has the uploaded logo / favicon.
- `vendors/api/docs/` still has the vendor documents.
- `vendors/mail-secrets.php` still present with the real credentials.

If any of those got emptied, restore them from the 5a backup zip via File
Manager (Upload + Extract into the right folder). Do not overwrite the tracked
`.htaccess` / `.gitkeep` in those folders with old copies; leave the fresh ones.

### 5e. Re-check `.htaccess` and run the smoke test

Do section 3 (confirm no password block) and section 4 (smoke test).

---

## 6. To avoid this next time

The recurring root cause is the server's working tree drifting away from git:
partly the `.htaccess` edits, partly tracked app files getting touched on the
server. Every touch of a tracked file creates a future pull conflict.

Habits that help right now:
- Do NOT edit tracked app files in File Manager (the `.php`, `.html`, `.js`
  files, and `.htaccess`). Change those in the repo and pull.
- The ONLY things you should edit on the server are the gitignored data/config:
  the SQLite data, uploads folders, and `mail-secrets.php`. Those are meant to
  live only on the server.
- Manage Directory Privacy through cPanel's Directory Privacy tool, never by
  hand-editing `.htaccess`.

Longer term: the roadmap's plan to give the Suite its OWN git repo
(`yacht-broker-support`, suite files at the repo root) with a clean deploy will
remove the shared Haley-site files from OWYG hosting and shrink the surface where
drift can happen. That is the real fix; these habits reduce the pain until then.
