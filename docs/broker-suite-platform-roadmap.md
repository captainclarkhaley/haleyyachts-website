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

**Phase 2 - Admin console (DONE, 2026-07-02).** An admin-only dropdown on
`suite.php` (gated by the in-app `is_admin`) with Staff Accounts, Predefined
Lists, and a Settings editor - all under `vendors/admin/` behind the shared
`admin-guard.php` (in-app login, no separate password). Shipped in sub-phases:
2a settings editor + console shell (`3e0f406`); 2b staff accounts relocated with
self-lockout / last-admin guards (`6f4b575`); 2c predefined lists relocated
(`264bd33`); 2d retired the old `/admin/` copies + fixed all references
(`4eb122d`). The `/admin/` folder (website tools + its password realm) is
untouched.

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

## Next phase - post-migration priorities (Clark, 2026-07-02)

**Sequencing:** get everything TESTED and working on the OWYG subdomain first,
THEN clean up structure BEFORE OWYG goes live. Go-live is ~2 weeks out, so there
is time to work these items. On Clark's return we review these and build a
next-phase plan together.

Priority order Clark set the night of the migration:
1. **Printing** - get the Pocket customer print sheet fully sorted and working
   on owyg.
2. **Source / template system** - a master instance (possibly at the
   `yachtbrokersupport.com` root) used to SPAWN new groups/companies if Clark
   decides to sell the product. Envisioned flow: provision a new subdomain, copy
   the source files down, configure for the new company. This is the multi-tenant
   / white-label direction - bring architecture options to the discussion.
3. **Per-tenant config layer** - config files that change look & behavior per
   subdomain: color schemes, logos, email formats, etc. This is what makes #2
   work (each spawned subdomain is a config, not a code fork). Builds on the
   existing `suite_settings` layer. Keep in mind during all development; not a
   high priority today.
4. **Stack standardization review** - Clark wants to review the languages/styles
   used and standardize. NOTE for the discussion: he assumed everything beyond
   HTML was React; the actual stack is plain HTML/CSS + vanilla JS on the front
   end and PHP + SQLite on the back end, no framework and no build step
   (deliberate, so it runs on basic cPanel). Explain the current stack, then
   discuss whether/how to standardize.

**Structure cleanup (before go-live):**
- **Eliminate the shared repo.** Extract the suite into its OWN git repo (e.g.
  `yacht-broker-support`), suite files at the repo root, and point the OWYG
  server's Git deployment at that repo. Drops the Haley public-site files and the
  `haleyyachts-website` folder name from the OWYG hosting - makes the hosting as
  cleanly separated as the branding now is. Not a tomorrow project, but do it
  before OWYG goes live.
