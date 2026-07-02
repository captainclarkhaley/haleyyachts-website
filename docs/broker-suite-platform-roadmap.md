# Broker Suite - Platform Roadmap

Evolving the OWYG Broker Suite from "a couple of apps living under `/vendors/`"
into a portable, multi-module platform with a consistent structure, central
configuration, a unified admin console, and eventually its own URL/domain.

Blocked from the public site (lives under `docs/`). This is the living plan;
update it as phases land.

## Target structure (end state)

Each module is a self-contained peer folder with the same skeleton; shared
services live in one place; environment-specific values live in config, not code.

```
brokersuite/                 (suite root, formerly vendors/)
├── suite.php                launcher + admin dropdown
├── login.html, change-password.html
├── config.php / settings    base URL, from-name, recipients, admin email (DB-backed)
├── core/                    shared: auth-lib, db, mail-smtp, mail-secrets, lib/phpmailer
├── vendordb/                module: page, js, css, api, help, help-img, docs store
├── pocket/                  module: page, js, css, api, cron, print, help, help-img, uploads
├── admin/                   admin console pages (users, lists, settings)
└── data/                    vendors.sqlite (shared) + backups
```

Today Pocket already follows the module pattern; the Vendor DB is the root and
needs lifting into `vendordb/`, with shared code pulled into `core/`.

## Configuration model

- **Secrets** (untracked file, NEVER in the DB or an admin screen): SMTP
  host/port/secure/username/password. Stays in `mail-secrets.php`.
- **Settings** (DB table, admin-editable): non-secret, environment/rollout knobs:
  - `site_base_url` - absolute base for email + print URLs (the portability lever)
  - `mail_from_name`, `mail_from_address`
  - `pocket_notify_to` - the new-listing + expiry reminder recipient (the
    test-inbox -> go-live switch)
  - `doc_admin_email` - admin@OWYG.com for vendor-document reminders
  Code reads settings with the current hardcoded value as the fallback default,
  so a missing/blank setting can never break a send.

## Phases

**Phase 1 - Config/settings layer (DONE, 2026-07-02, commit 383f96b).** DB
`suite_settings` table + a cached `suite_setting()` accessor, seeded via INSERT
OR IGNORE with site_base_url / mail_from_address / pocket_notify_to /
doc_admin_email. The Pocket mailer + cron and the vendor doc-cron now read those
at call time with the old literal as the fallback. Behavior-neutral (values
unchanged; only the source moved). No UI yet - editing a value still means a
direct DB change until the Phase 2 editor lands.

**Phase 2 - Admin console.** An admin-only dropdown on `suite.php` (gated by the
in-app `is_admin`) that consolidates the existing admin tools (staff accounts,
predefined lists) plus a Settings editor for the Phase 1 values. Retire the
separate password-protected admin area - one login, one place to manage.

**Phase 3 - Restructure + rename `/vendors` -> `/brokersuite`.** Lift the Vendor
DB into `vendordb/`, shared code into `core/`, and rename the suite root. Done as
ONE migration since both churn every path. Migration steps (NOT just a rename):
- Session cookie is path-scoped to `/vendors/` -> change to `/brokersuite/`
  (everyone re-logs-in once).
- Server-side data is not in git (`vendors.sqlite`, Pocket `uploads/`, vendor
  `docs/`, `mail-secrets.php`) - relocate those folders in cPanel File Manager.
- The two cPanel cron commands (`pocket/cron.php`, `api/doc-cron.php`) - update
  their paths.
- Every internal path reference + any staff link from the public site.

**Future - own URL/domain.** Once the above lands, this is mostly a
`site_base_url` change + DNS + moving the suite to the new docroot.

## Pending go-live changes

Config edits to make (via the admin Settings editor) when each feature goes live:
- **Pocket Listings notification (`pocket_notify_to`)** - when ready to go live,
  change it from the test inbox to **`owygsalesall@owyg.com`** (Clark, 2026-07-02).
  Sends to one address; no per-broker fan-out is built.

## Adding a module (e.g. "Broker Looking For...")

Copy the module skeleton (page, js, css, api, help, help-img, own uploads if
needed), register it in the suite app registry, reuse `core/` services and the
settings layer. "Broker Looking For..." is the current placeholder tile (Coming
Soon) and will be the first module built on the finished skeleton.
