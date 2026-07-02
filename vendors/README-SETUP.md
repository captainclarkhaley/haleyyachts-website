# Vendor Database - one-time setup (cPanel / GoDaddy)

The Vendor Database is a small server-side app. The website is static, but this
feature needs PHP and SQLite, both of which the GoDaddy cPanel server already
provides. Everything is file-based, there is no external database to provision.

> **NEW: in-app login replaces the cPanel password on /vendors/.**
> The staff app now has its own database-backed login (account ID + password,
> with email and admin password reset). It is designed to REPLACE the cPanel
> Directory Privacy realm on `/vendors/`. See **"Staff login (in-app auth)"** and
> **"SAFE CUTOVER ORDER"** below. Do the cutover carefully and in order.

## What it is

- Staff app: `https://haleyyachts.com/vendors/` (Broker Suite login)
- Admin console (staff accounts + predefined lists): inside the Broker Suite,
  reached from the launcher's account menu > Admin (gated by the in-app
  `is_admin` login, NOT the `/admin/` folder password)
- Data: a single SQLite file at `public_html/vendors/api/data/vendors.sqlite`

The staff app and the admin console write to the SAME SQLite file. The split is
by role: every staff member signs in with their Broker Suite account, but only
those flagged `is_admin` see the Admin menu and can reach the account and list
management screens.

## Staff login (in-app auth)

The app authenticates staff itself, against the same SQLite database:

- **Accounts** are created by you (admin) in the Broker Suite admin console
  (launcher > account menu > Admin > Staff Accounts), gated by the in-app
  `is_admin` login. There is NO public self-signup.
- **Login page:** `vendors/login.html` (Account ID + password, plus "Forgot
  password?").
- **App page:** `vendors/index.php` (was `index.html`). It now checks for a valid
  session server-side and redirects logged-out visitors to `login.html` before
  any page content is sent.
- **Data API:** `vendors/api/api.php` requires a valid session and returns
  HTTP 401 to anyone without one. The data handlers do not run for an
  unauthenticated request. This is the real access control once the cPanel
  realm is removed.
- **Password reset:** two ways.
  1. Self-service: the staff member clicks "Forgot password?" on the login page,
     enters their email, and gets a one-time link (`reset.html?token=...`) that
     expires in 1 hour. For anti-enumeration the page always says "if that email
     is on file, a link has been sent," whether or not the email matched.
  2. Admin: in the Broker Suite admin console (Admin > Staff Accounts),
     "Reset PW" sets a new password directly. This is the fallback if email
     delivery is a problem.

How passwords are stored: only as a bcrypt hash (`password_hash`). Plain-text
passwords are never stored, logged, or shown. The admin page never displays a
hash. Sessions use HttpOnly + Secure + SameSite=Lax cookies and the session id
is regenerated on login.

**About the reset EMAILS:** the link is sent with PHP `mail()` from
`no-reply@haleyyachts.com` (configurable at the top of `vendors/auth.php`).
Whether it arrives depends on the server actually being able to send mail from
the domain. If resets land in spam or do not arrive, the domain may need SPF /
DKIM / a real mailbox configured. **The admin "Reset PW" button is the reliable
fallback in every case** - it does not depend on email at all. The DB file stays
protected by `vendors/api/data/.htaccess` regardless of any of this.

## SAFE CUTOVER ORDER (do this exactly, in order)

The in-app login is meant to REPLACE the cPanel Directory Privacy password on
`/vendors/`. Do not remove the cPanel password first - test the new login while
the cPanel popup is still active, then remove it. Order:

1. **Pull** the new files to the server (cPanel Git pull, as usual). This brings
   down `index.php`, `login.html`, `reset.html`, `auth.php`, `api/auth-lib.php`,
   and the Broker Suite admin console (`vendors/suite.php`,
   `vendors/admin/users.php`, `vendors/admin/users-api.php`), and removes the old
   `vendors/index.html`.
2. **Create your own account(s)** in the Broker Suite admin console (launcher >
   account menu > Admin > Staff Accounts), gated by the in-app `is_admin` login.
   Give yourself an Account ID, email, home office, and an initial password. Add
   the rest of the staff now or later.
3. **Test the new login** at `https://haleyyachts.com/vendors/`. The cPanel
   popup will still appear first (that is fine for now) - clear it with your
   existing cPanel staff credentials, then you should land on `login.html`. Sign
   in with the account you just made. Confirm the app loads, your name + home
   office show top-right, the vendor list works, and **Log out** returns you to
   the login page. Also test "Forgot password?" once (admin reset is the
   fallback if the email does not arrive).
4. **Only after that works**, remove the cPanel Directory Privacy realm from
   `/vendors/` in cPanel (Directory Privacy > `public_html/vendors` > uncheck
   "Password protect this directory"). This stops the double login: from then on
   the app's own login page is the single gate. `login.html`, `auth.php`, and
   `reset.html` become publicly reachable on purpose - logged-out staff need
   them to sign in. The app page and the data API stay protected by the session
   check. Do NOT put a directory-wide password back on top.

If something is wrong in step 3, do nothing in cPanel - the old password is
still protecting the area while you sort it out.

## One-time steps Clark must do on the server

> The cPanel realm step below is the OLD gate. Keep it during the cutover test
> (step 3 above), then remove it in step 4. It is documented here for reference
> and for the initial pre-cutover state.

### 1. Create a password realm on `/vendors/` and add staff users

This is the staff login, separate from the admin login. The thing that trips
people up: in cPanel the "turn protection on" control and the "create the
password" control are **two separate boxes on the same page**, and the password
box is lower down. You have to do both.

**Do this AFTER the cPanel Git pull** that brings the new files down. The
`vendors` folder has to exist on the server before it shows up in Directory
Privacy. If you do not see it in the folder browser, you have not pulled yet.

1. In cPanel, open **Directory Privacy** (sometimes called "Password Protect
   Directories"). You get a file browser.
2. Click into `public_html`, then click the **`vendors`** folder *name* (click
   the name to open the folder, not just the checkbox next to it).
3. **Turn protection on** (first box, near the top): check
   **Password protect this directory**, type a label in the
   "Name the protected directory" field (e.g. `Haley Yachts Vendors`), and click
   **Save**. This only switches protection ON. It does not create a login yet.
4. **Create the username + password** (second box - scroll DOWN on the same page
   to **Create User**, sometimes titled "Create a User who can access this
   directory"). Type a **Username**, type a **Password** twice (or use the
   generator), and click **Save** / **Add User**. That username + password is
   what staff type into the browser pop-up at `haleyyachts.com/vendors/`.
5. To add more staff, repeat step 4 with another username and password. Each
   person can have their own login, all pointing at the same `/vendors/` folder.

Use a **different** password from the `/admin/` login. The whole point of the
split is that staff get into `/vendors/` but not `/admin/`.

cPanel writes the `.htpasswd` file and appends its managed `cp:ppd` block to
`vendors/.htaccess`. Do not hand-edit that block. Until this step is done, the
`/vendors/` area is unprotected if it is live on the web.

(Staff accounts and the predefined lists are NOT protected by the `/admin/`
folder password anymore - they live in the Broker Suite admin console, gated by
the in-app `is_admin` login. The `/admin/` password realm still protects the
separate website admin tools, unchanged.)

### 2. Confirm PHP has the SQLite driver (`pdo_sqlite`)

GoDaddy cPanel ships with this enabled by default, but to be sure:

- In cPanel, open **Select PHP Version** (or **MultiPHP INI Editor**), go to the
  **Extensions** tab, and confirm **pdo_sqlite** (and **sqlite3**) are checked.
- Or just load the staff app once (next step). If the driver is missing you will
  see a clear "Server error" and the cPanel error log will name `pdo_sqlite`.

### 3. The database auto-creates on first load

There is nothing to import. The first time any authenticated user opens the
staff app or the Broker Suite admin console, `vendors/api/db.php` creates
`vendors/api/data/vendors.sqlite`, builds the tables, and seeds the starter
Vendor Types and Coverage Areas. From then on it just opens the existing file.

Make sure the `vendors/api/data/` directory is writable by the web server (cPanel
defaults to `0755`, which is fine since PHP runs as your user). If creation
fails you will see a server error and the log will mention a permission problem
on `data/`.

### 4. Back up the SQLite file - it is NOT in git or the deploy flow

This is important. `vendors/api/data/vendors.sqlite` is the live data and it is
deliberately **excluded from git** (see the repo `.gitignore`) so the deploy
pull never overwrites real vendor records with an empty file. That means it is
**not** covered by the GitHub history or the cPanel Git pull.

Back it up separately:

- The simplest route is cPanel **File Manager**: navigate to
  `public_html/vendors/api/data/`, select `vendors.sqlite`, and download it
  periodically. Or include `public_html/vendors/api/data/` in your normal cPanel
  backup.
- To restore, upload the `.sqlite` file back into that same folder.

Only `.htaccess` is tracked under `data/`. The `.sqlite` file (and any
`-journal`, `-wal`, `-shm` siblings) are ignored on purpose.

### 5. The Broker Suite admin console gates the lists page

Staff accounts and the predefined lists now live inside the Broker Suite
(`vendors/admin/users.php` and `vendors/admin/vendor-lists.php`, reached from the
launcher's account menu > Admin). They are gated by the in-app `is_admin` login,
NOT the `/admin/` folder password. No cPanel realm step for them. Just sign in to
the Broker Suite as an admin account and confirm the Admin menu shows
**Staff Accounts** and **Predefined Lists**. The `/admin/` password realm still
protects the separate website admin tools.

## Security notes (already handled in code, no action needed)

- `vendors/api/data/.htaccess` denies all web access to the SQLite file, so even
  if someone guessed the path they could not download the database. The realm
  password is the first line of defense; this is the second.
- `vendors/.htaccess` disables directory listing.
- All SQL uses prepared statements. Note character limits (150 for vendor notes,
  100 for contact notes) are enforced server-side as well as in the browser.
- Output is HTML-escaped in the front end.

## Quick smoke test after setup

1. Open `https://haleyyachts.com/vendors/`, log in with a staff user. You should
   see an empty results table and the filter lists already populated with the
   seeded Vendor Types and Coverage Areas.
2. Click **+ Add Vendor**, fill in a name, tick a type and an area, add a contact
   marked Primary, Save. It should appear in the table with the primary phone or
   email derived from that contact.
3. In the Broker Suite, open the account menu > Admin > Predefined Lists (as an
   admin account), rename or add a list item, then reload the staff app and
   confirm the change shows up in the filters.
