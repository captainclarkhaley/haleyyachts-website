# Vendor Database - one-time setup (cPanel / GoDaddy)

The Vendor Database is a small server-side app. The website is static, but this
feature needs PHP and SQLite, both of which the GoDaddy cPanel server already
provides. Everything is file-based, there is no external database to provision.

## What it is

- Staff app: `https://haleyyachts.com/vendors/` (password realm: staff)
- Admin lists page: `https://haleyyachts.com/admin/vendor-lists.html` (password realm: admin, already set up)
- Data: a single SQLite file at `public_html/vendors/api/data/vendors.sqlite`

The staff app and the admin lists page write to the SAME SQLite file. The split
is by password realm: staff users (in `/vendors/`) can use the directory but can
never reach the admin lists endpoint, because that lives in `/admin/`, a
separate realm with its own users.

## One-time steps Clark must do on the server

### 1. Create a password realm on `/vendors/` and add staff users

This is the staff login, separate from the admin login.

1. In cPanel, open **Directory Privacy** (sometimes called "Password Protect
   Directories").
2. Browse to the `vendors` folder under `public_html`.
3. Check **Password protect this directory**, name it something like
   `Haley Yachts Vendors`, and Save.
4. Under **Create User**, add one login per staff member with a strong password.
   Repeat for each person who should have access.

cPanel writes the `.htpasswd` file and appends its managed `cp:ppd` block to
`vendors/.htaccess`. Do not hand-edit that block. Until this step is done, the
`/vendors/` area is unprotected if it is live on the web.

(The `/admin/` realm that protects `admin/vendor-lists.html` is already set up,
the same way the other admin tools are protected. Nothing new to do there.)

### 2. Confirm PHP has the SQLite driver (`pdo_sqlite`)

GoDaddy cPanel ships with this enabled by default, but to be sure:

- In cPanel, open **Select PHP Version** (or **MultiPHP INI Editor**), go to the
  **Extensions** tab, and confirm **pdo_sqlite** (and **sqlite3**) are checked.
- Or just load the staff app once (next step). If the driver is missing you will
  see a clear "Server error" and the cPanel error log will name `pdo_sqlite`.

### 3. The database auto-creates on first load

There is nothing to import. The first time any authenticated user opens the
staff app or the admin lists page, `vendors/api/db.php` creates
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

### 5. The admin realm already protects the lists page

`admin/vendor-lists.html` and `admin/vendor-lists-api.php` sit inside `/admin/`,
which already has its own Directory Privacy password from when the other admin
tools were set up. No extra step. Just confirm you can reach
`admin/vendor-lists.html` and that it prompts for the admin login.

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
3. Open `https://haleyyachts.com/admin/vendor-lists.html`, log in with the admin
   user, rename or add a list item, then reload the staff app and confirm the
   change shows up in the filters.
