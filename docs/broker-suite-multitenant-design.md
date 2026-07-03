# Broker Suite - Multi-Tenant / White-Label Design Proposal

Status: DISCUSSION DRAFT (Terry, 2026-07-03). Nothing here is built. This is
options + a recommendation per area for Clark's return, tied to roadmap
priorities 2 (source/template master) and 3 (per-tenant config).

Blocked from the public site (lives under `docs/`).

## The goal in one sentence

Make a new customer subdomain "a config, not a code fork": provision a
subdomain, drop in one identical copy of the suite, then set colors, logo,
company identity, printing, and cron from the admin menus, with no per-tenant
code edits.

## Where we are today (grounded in the code)

Good news first: the foundation for this already exists.

- **Settings layer is real and working.** `suite_settings` (key/value table in
  `vendors/api/db.php`) plus the cached `suite_setting($pdo, $key, $default)`
  accessor. Every read passes the old hardcoded value as a fallback, so a
  missing or blank row can never break anything. Seeded via `INSERT OR IGNORE`,
  so an admin edit is never clobbered on the next load.
- **Six settings today:** `site_base_url`, `mail_from_address`,
  `pocket_notify_to`, `doc_admin_email`, `brand_name`, `tenant_name`. The last
  two already prove the pattern for per-tenant identity: they are read across
  the print sheet, both crons, the admin console, and the auth pages.
- **Admin Settings editor exists** (`vendors/admin/settings.php`), admin-gated,
  CSRF-protected, with a strict key whitelist. It currently edits only the first
  four keys. Adding branding is "extend this screen," not "build a screen."
- **Secrets are already separated from settings.** SMTP host/user/pass live in
  the untracked `vendors/mail-secrets.php` (copied from the `.sample`), read by
  `mail-smtp.php`. This is exactly the per-instance secret model we want.
- **Server data already lives outside git per instance:** `vendors.sqlite`,
  Pocket `uploads/`, vendor `docs/`, and `mail-secrets.php` are all gitignored.
  A git pull never touches them. Perfect for multi-tenant.

The gaps for multi-tenant:

1. **Colors are hardcoded hex, re-declared per file.** `print.php` and
   `settings.php` each carry their own `:root { --navy:#0a1628; --cyan:#21cbea; }`
   block; `vendors.css` has another. There is no single place a tenant's palette
   comes from.
2. **The logo is a hardcoded root-absolute path** repeated across ~13 files:
   `/images/email/owyg-banner-reverse.png`. Every tenant would show the OWYG
   banner until each file is hand-edited. This is a code fork waiting to happen.
3. **No spawn process is written down.** The `README-SETUP.md` is a good
   one-tenant setup guide but it is manual, OWYG-specific, and does not cover
   "stamp out tenant N."

So the work is: finish the config-driven branding (logo + colors), harden or
replace the print sheet, make cron easy to wire and easy to verify, and write a
repeatable spawn checklist (scripted where cPanel allows).

---

## Area 1 - Per-tenant config model (colors, logos, identity)

### What belongs in per-tenant settings

Extend `suite_settings` with a small, additive branding group. All follow the
existing value-with-fallback rule, so an un-configured tenant renders the
current OWYG look until it is branded.

Already present: `site_base_url`, `mail_from_address`, `pocket_notify_to`,
`doc_admin_email`, `brand_name`, `tenant_name`.

Add:

- `logo_path` - uploaded file path for the tenant's primary logo (the reverse/
  on-navy version used on mastheads and the print sheet).
- `brand_color` - primary accent (today's `--cyan` / `#21cbea`).
- `brand_color_dark` - accent hover/darker (today's `#1aa8c4` / `#0e93b3`).
- `header_color` - masthead/navy base (today's `#0a1628`).
- `contact_block` - free-text company contact line shown on the print sheet
  footer and email footers (address / phone / license line).
- `timezone` - for display of dates if we ever localize the crons (they run in
  UTC today; see Area 3 caveat).

Deliberately NOT in settings: SMTP secrets (stay in `mail-secrets.php`). That
line does not move.

### How to apply branding with NO build step

This is the crux. Two mechanisms, both zero-build, both already half-present:

**Colors: emit CSS custom properties from settings.**

The suite already uses CSS variables everywhere (`var(--navy)`, `var(--cyan)`,
`var(--accent)`). Today those `:root` values are literal hex. We make `:root`
come from settings instead. Two implementation options:

- Option A - a tiny PHP-generated `theme.css`. A `theme.css.php` reads the four
  color settings and emits one `:root { ... }` block with the right
  `Content-Type: text/css`. Each page links it once. One file, cached per
  request. Downside: it is an extra HTTP request and a `.php` served as CSS,
  which some caching setups fret over.
- Option B - inline the `:root` block in each page `<head>` from settings
  (a `suite_theme_vars($pdo)` helper that echoes `<style>:root{...}</style>`).
  No extra request, no caching questions. Downside: the block repeats in each
  page's head (small, and it is generated, so not a maintenance cost).

**Recommendation: Option B (inline `:root` from settings).** It is the least
moving parts, no new served-as-CSS file, and it fits the existing pattern where
each page already prints a `:root` in its own `<style>`. We replace the literal
hex in those `:root` blocks with values from `suite_setting()`. One shared
helper, called in each `<head>`. The rest of the CSS already references the
variables, so nothing else changes.

**Logo: an uploaded file path in settings.**

Replace the ~13 hardcoded `/images/email/owyg-banner-reverse.png` references
with `suite_setting($pdo, 'logo_path', '/images/email/owyg-banner-reverse.png')`.
The fallback is the current OWYG banner, so nothing breaks before a tenant
uploads their own. The Branding screen has a file upload (same mechanics Pocket
already uses for listing images) that writes into the per-instance `uploads/`
folder and stores the resulting path in `logo_path`.

One caveat worth naming: emails need an ABSOLUTE logo URL (mail clients cannot
resolve a site-relative path). The email builders already prepend `site_base_url`
to the banner path, so the mechanism is: store `logo_path` site-relative, and in
emails emit `site_base_url + logo_path`. That is exactly what the crons do today
with the hardcoded path.

### First-run / admin "Branding" screen

Extend `vendors/admin/settings.php` (or add a sibling `admin/branding.php` using
the same `admin-guard.php`) with a Branding section:

- Text fields: brand name, tenant name, contact block.
- Color pickers (native `<input type="color">`, zero JS libs) for the three/four
  colors, each showing the current value and a "reset to default" that blanks
  the row so the fallback applies.
- Logo upload with a live preview against the header color.
- The existing key-whitelist + CSRF + admin-gate model extends cleanly; just add
  the new keys to `$SETTING_KEYS` and `$FIELD_META`.

A non-technical person brands a new tenant entirely from this screen. That is
the whole point of priority 3.

---

## Area 2 - Printing

Two separate problems. Reliability first, because it is what bit them last
night; branding second.

### 2a - Reliability

**Why the browser print sheet is fragile (the honest version).**

`vendors/pocket/print.php` is a single HTML page the browser prints via
`window.print()`. The git history is a paper trail of the fight: roughly
fourteen commits tuning page breaks and the footer, including "footer bouncing
to page 2," "stop forcing sheet height," "reserve fixed description height,"
"drop the description reserve," "fix extra/blank page from footer-pin
min-height," and "hero max-height 280 -> 210." The current file even carries
long comments explaining WHY forcing a page height pushes the footer to page 2.

The root cause is structural, not a bug we can finally squash: **browser print
is not deterministic across browsers, print drivers, paper sizes, and the user's
"fit to page" / margin / headers-and-footers settings.** Chrome, Safari, Firefox,
and the printer's own scaling each lay the page out slightly differently. We can
tune it to look right on ONE machine and it drifts on the next. For a
single-tenant internal tool that is annoying. For a white-label product handed to
customers we do not control, it is a support burden that scales with every tenant.

Options:

- **Option A - keep hardening the browser-print CSS.** Zero new dependencies,
  no weight added. But we have already spent ~14 commits here and it is still
  environment-dependent. Every new tenant is a new set of browsers/printers to
  drift on. We would be shipping the known-fragile path to customers.
- **Option B - generate a real PDF server-side.** The sheet is rendered once, on
  our server, to a fixed-size PDF that looks identical no matter who opens or
  prints it. The broker gets a "Download PDF" (and later "Email to client")
  instead of a browser print dialog. On cPanel with no build step this means
  vendoring a pure-PHP PDF library: **Dompdf** or **mPDF**. Both are pure PHP,
  install by dropping the folder in (like the vendored PHPMailer already in
  `vendors/lib/phpmailer/`), and need no Composer, no build, no system binary.

  - Dompdf: lighter, renders HTML+CSS. Good enough for a one-page sheet; weaker
    on advanced CSS. Our sheet is a simple two-column table layout, well within
    its range.
  - mPDF: heavier but stronger on precise page control, headers/footers, and
    fonts. More reliable for "footer always at the bottom of page 1," which is
    the exact thing we have been fighting.

  Tradeoff: a real dependency (a few MB vendored into the repo), and we render
  server-side (a little CPU per sheet, negligible at this volume). In return we
  get bulletproof, identical output on every tenant and every device, plus the
  file is trivially emailable/attachable, which is a feature the browser-print
  path can never offer.

**Recommendation: Option B, PDF via mPDF, once printing is otherwise stable on
OWYG.** The consistency alone justifies it, and the moment this is a product for
customers we do not control, "it prints the same everywhere and you can email it"
is worth far more than saving a few MB. mPDF over Dompdf specifically because our
one recurring pain (footer placement / page breaks) is exactly mPDF's strength.
We keep the current `print.php` HTML as the template that feeds the PDF, so the
layout work already done is not thrown away; we change how it is rendered, not
what it looks like.

Sequencing note: roadmap priority 1 is "get printing working on OWYG." A
pragmatic path is to ship the current hardened HTML sheet for OWYG go-live
(it is close), then move to mPDF as the multi-tenant hardening step, so we are
not blocking go-live on a new dependency.

### 2b - Tenant branding of the sheet

Whichever render path we pick, the sheet pulls all identity from settings so
each tenant's sheet is automatically theirs:

- Logo: `logo_path` (fallback = current OWYG banner).
- Company name / tenant name: `brand_name` / `tenant_name` (already wired in
  `print.php`).
- Colors: the same `:root`-from-settings mechanism from Area 1 (the sheet's
  navy header and cyan keyline become `header_color` and `brand_color`).
- Contact/footer line: `contact_block`.

`print.php` already reads `brand_name` and `tenant_name`, so this is finishing a
pattern that is 40% done, not starting one. Going to PDF does not change this;
the PDF is rendered from the same settings-driven template.

---

## Area 3 - Cron configuration per subdomain

Each instance needs two daily jobs:
`vendors/pocket/cron.php` (expiry reminders + archive) and
`vendors/api/doc-cron.php` (vendor-document reminders). Both are hard CLI-only
(they `exit` on a web request), both key off `DOCUMENT_ROOT` set to the suite
root, both read their environment from `suite_settings`. That design is already
tenant-friendly: point them at a tenant's docroot and they use that tenant's DB
and settings automatically.

The question is purely operational: how do we wire two cron jobs per subdomain
without it being a fiddly manual chore each time.

Options:

- **Option A - per-subdomain cPanel cron entries (one line each).** At spawn,
  add two cron jobs in that cPanel account pointed at the new docroot. Exact
  commands (daily, staggered so they do not overlap):

  ```
  # Pocket expiry reminders + archive - 3:10am
  10 3 * * * /usr/local/bin/php /home/USER/SUBDOMAIN_DOCROOT/vendors/pocket/cron.php >/dev/null 2>&1

  # Vendor-document expiry reminders - 3:20am
  20 3 * * * /usr/local/bin/php /home/USER/SUBDOMAIN_DOCROOT/vendors/api/doc-cron.php >/dev/null 2>&1
  ```

  (The exact PHP binary path can vary on GoDaddy; `which php` or the cPanel cron
  UI's own example line gives the right one.) Simple, isolated (one tenant's cron
  failure never touches another), and it is how OWYG runs today. Downside: it is
  a manual step per spawn, and if you forget it, reminders silently never fire.

- **Option B - one central scheduler that hits every tenant.** A single cron on
  the master calls a small runner that loops over a tenant registry and triggers
  each tenant's jobs (over HTTPS to a token-protected endpoint, or by shelling
  into each docroot). One cron to maintain; new tenants join a list instead of
  getting their own cron. Downsides: it needs an auth token per tenant (the jobs
  are deliberately CLI-only today, so an HTTP trigger means adding a guarded web
  entry point, which widens the attack surface), one tenant's slow/broken run can
  delay others, and it couples all tenants to one scheduler. More moving parts,
  more to secure.

- **Can cPanel/GoDaddy script cron creation at provision time?** cPanel exposes
  UAPI `Cron::add_line`, callable from the account shell or the API. On GoDaddy
  shared/cPanel hosting, whether UAPI is reachable from your context varies by
  plan, and each tenant living in its OWN cPanel account (the likely model for a
  sold product) means credentials/API access per account. Realistically: if all
  tenants sit under one cPanel account as subdomains, a provisioning script CAN
  add both cron lines via UAPI. If each tenant is its own cPanel account, cron
  creation stays a manual checklist step per account. Treat scripted cron as a
  "nice if the hosting model allows," not a guarantee.

- **Cron health indicator (do this regardless).** Add a `cron_last_run` style
  timestamp: each cron writes `pocket_cron_last_run` / `doc_cron_last_run` (and
  a short status) into `suite_settings` at the end of a successful run. The admin
  console shows "Pocket cron last ran: 3:10am today (green) / never (red)."
  Now you can SEE at a glance whether a freshly spawned instance's cron is wired,
  instead of discovering it weeks later when a reminder was never sent. This is a
  tiny change (two writes + one read on an admin page) and it is the single
  highest-value cron item for easy spawning.

**Recommendation:** keep **per-subdomain cPanel cron entries (Option A)** as the
model - it matches the isolated, CLI-only design and avoids a new authed web
endpoint - and pair it with the **cron-health indicator**, which turns "did I
remember to set up cron on tenant N" from an invisible risk into a red dot on the
admin console. Document the two exact cron lines in the spawn checklist. Only
consider the central scheduler (Option B) if we end up with many tenants under
one account AND the per-account cron step becomes the bottleneck.

---

## Area 4 - The spawn process (stand up a new customer subdomain)

Target end-to-end flow. The aim is that most of this is a script or a wizard, and
the human-in-cPanel steps are a short, unambiguous checklist.

1. **Provision subdomain + docroot** (cPanel: Subdomains -> create
   `tenant.yachtbrokersupport.com` with its own docroot). Manual in cPanel.
   Ties to roadmap priority 2: the master/source lives at
   `yachtbrokersupport.com` (or a `source/` repo) and every tenant is a
   subdomain off it.
2. **Deploy the suite** (cPanel Git pull of the suite's OWN repo into the new
   docroot). Ties directly to the roadmap's "eliminate the shared repo / extract
   the suite into its own repo" cleanup item. One repo, pulled identically into
   every tenant docroot. No per-tenant branch, no fork.
3. **Create per-instance data folders** (`vendors/api/data/`, Pocket `uploads/`,
   vendor `docs/`) - these are gitignored, so they do not come down with the
   pull. The DB auto-creates on first load (existing behavior in `db.php`), so
   really this is "make sure the folders exist and are writable." Scriptable.
4. **Drop in `mail-secrets.php`** (copy the `.sample`, set the tenant's mailbox
   password). Manual, by design - it is a secret. One file.
5. **Seed settings** - on first load the settings table auto-seeds with the
   OWYG-flavored defaults. For a new tenant we want tenant-flavored blanks or a
   short seeding step so it does not briefly show OWYG identity. Simplest:
   leave the fallbacks as generic product defaults and let the Branding screen
   fill identity; or a one-shot "new tenant" seed that blanks tenant-specific
   rows.
6. **Set `site_base_url`** to the new subdomain (Settings screen). This is the
   single most important value - it drives every email/print absolute URL. The
   existing Settings editor already handles it.
7. **Register the two cron jobs** (Area 3, Option A) - paste the two documented
   lines into the tenant's cPanel cron. Manual unless UAPI scripting is available
   on the hosting model.
8. **Brand it** from the admin Branding screen (Area 1): logo upload, colors,
   contact block, brand/tenant names. Fully menu-driven, non-technical.
9. **Smoke test**: log in, add a listing, print/PDF it, confirm the branding is
   the tenant's, and check the cron-health indicator is not red.

### Scripted vs manual

- **Scriptable** (a `setup.php` first-run wizard or a provisioning script):
  data-folder creation + permissions (3), settings seeding (5), setting
  `site_base_url` (6), a guided branding hand-off (8), and the smoke-test
  checklist. A first-run **setup wizard page** (guarded so it only runs on a
  fresh, unconfigured instance, then locks itself) is the most non-technical-
  friendly form of this: it walks the operator through base URL, identity,
  colors, logo, and shows the exact cron lines to paste.
- **Stays manual in cPanel:** subdomain + docroot (1), the Git pull (2, though
  it is one click), `mail-secrets.php` (4, a secret on purpose), and cron
  registration (7, unless UAPI is usable).

### Rough time per spawn once streamlined

- Today, cold, no tooling: an hour-plus of careful cPanel clicking and
  file-editing, with real risk of missing a step (like last night's cron/printer
  pain).
- With the own-repo pull, a first-run setup wizard, config-driven branding, and
  the documented cron lines: realistically **15 to 20 minutes** per tenant, most
  of it waiting on cPanel (subdomain create, Git pull) and the manual secret +
  cron paste. The branding itself is a few minutes in the wizard.

### Prerequisites this depends on (roadmap order)

- **Priority 2, own repo / source master.** Step 2 assumes the suite is its own
  repo pulled into each docroot. Until the "eliminate the shared repo" cleanup
  lands, every spawn drags the Haley public site along. Do that first.
- **Priority 3, config-driven branding.** Steps 5, 6, 8 assume logo + colors are
  settings, not hardcoded. That is Area 1. It is the enabling work for spawn to
  be "a config, not a fork."

---

## The single biggest decision

**Printing: keep fighting browser print, or commit to server-side PDF (mPDF).**
Everything else here is additive and low-risk (more settings, a branding screen,
a cron timestamp, a checklist). Printing is the one place with a real
architectural fork, it is the thing that hurt last night, and it is the thing
that gets worse with every tenant because we do not control their browsers and
printers. My recommendation is server-side PDF, but it is a genuine tradeoff
(a vendored dependency and server-side rendering vs bulletproof identical output
plus emailable sheets), so it is the call worth making deliberately with Clark.

## Area 5 - Operator console (provider control plane)

New requirement from Clark: he needs a view that is OWYG's, not the customer's.
"Per subdomain I'm going to need something like an admin for OUR OWN purposes,
not the customer admin, but for us." The concrete asks are megabytes of data per
subdomain and things like renewal dates, the kind of information he needs to roll
this out to other customers.

That is a different animal from everything above. Areas 1 to 4 describe each
tenant's own customer-facing admin (branding, settings, cron for that one
instance). This is the LANDLORD view: one dashboard, OWYG-only, sitting ABOVE all
the tenants and looking down across every subdomain at once. Higher-privileged,
separate auth, spans customers' data.

### The concept

A central provider dashboard that lists every tenant and shows OWYG-only
operational data per tenant. It is deliberately NOT reachable by any tenant login
and never mixes into the per-tenant customer admin.

This fits the roadmap's source/master instance idea cleanly. Priority 2 already
calls for a master/source instance (possibly at `yachtbrokersupport.com`) that is
the template every tenant is stamped from. Let that same master double as the
operator console: it is the natural "one place above all tenants," it already
knows the canonical layout, and it is the box OWYG controls. One master instance,
two hats: template source AND fleet dashboard.

### The tenant registry (central, provider-owned)

The backbone is a registry that OWYG owns, living on the master, NOT inside any
tenant instance. One record per tenant. Rough fields:

- `subdomain` / `base_url` - the tenant's address (matches its `site_base_url`).
- `company_name` - the customer's business name.
- `plan` / `tier` - what they bought.
- `renewal_date` / `billing_date` - when they are up for renewal or next billed.
- `primary_contact` - name + email/phone for the account.
- `status` - active / suspended / trial.
- `enabled_modules` - which parts of the suite they have (Pocket, vendor docs,
  etc.), for when the product is sold in pieces.
- `provisioned_at` - when the tenant was stood up.

The key design point: this provider metadata, especially renewal and billing,
lives CENTRALLY in the registry, not in the tenant instance. A customer must not
see or edit their own renewal date, and OWYG must not have to log into fifteen
separate admin consoles to answer "who renews this month." It is business data
about the tenant, so it belongs to the provider, next to the fleet view.

Storage can be as light as a small SQLite table (same engine the suite already
uses) or even a flat JSON file on the master to start. No new stack.

### Per-tenant metrics and how to collect them (no framework)

The headline metric Clark named is data usage in megabytes per subdomain. That is
a small, pure-PHP routine: recursively walk the tenant's data footprint and sum
file sizes. The footprint is exactly the per-instance, gitignored data we already
isolate in Area 0/1: the `vendors.sqlite` DB, Pocket `uploads/`, and vendor
`docs/`. A recursive directory sizer plus the DB file size gives bytes, divide for
MB. No dependency, a few dozen lines.

The real question is how the operator console GETS that number from each tenant.
Two models:

- **Option A - PULL.** Each tenant exposes a tiny authenticated stats endpoint,
  e.g. `/brokersuite/core/stats.php`, that returns JSON: usage bytes, record
  counts, cron last-run timestamps, app/schema version. The operator console
  loops the registry and calls each tenant's endpoint on demand (or on a
  dashboard refresh) with a shared operator token in the request. Pros: always
  live, the console is the only thing that has to be smart, a tenant that is down
  simply shows as unreachable. Cons: it adds one new web-reachable endpoint per
  tenant that must be locked hard (see Security), and the console does N HTTP
  calls per refresh.
- **Option B - PUSH.** Each tenant's existing daily cron reports its own stats UP
  to a registry endpoint on the master. Pros: no new inbound endpoint on the
  tenants (reuses the cron that already runs), the master just receives and
  stores, cheap dashboard reads (data is already local). Cons: numbers are as
  fresh as the last cron run (up to a day stale), and a tenant whose cron is
  broken silently stops reporting, though that doubles as a health signal.

**Recommendation: PULL (Option A) as the primary, because the operator wants
on-demand truth ("how big is tenant N right now"), and a down/unreachable tenant
is itself useful signal.** It also keeps all the provider logic in one place, the
master, instead of spread across every tenant's cron. If per-refresh HTTP fan-out
ever gets heavy, layer PUSH on top as a cache: cron pushes a daily snapshot the
console shows instantly, and PULL is the "refresh now" button. Start with one,
PULL, and only add the second if volume warrants.

Beyond MB, cheap and useful metrics to surface per tenant:

- `last_active` - most recent login or write, so dormant tenants are visible.
- Cron health - reuse the `pocket_cron_last_run` / `doc_cron_last_run` timestamps
  from Area 3. The operator console is the natural fleet-wide place to see a red
  dot when a tenant's cron has gone dark.
- Record counts - vendors on file, active Pocket listings, etc.
- Storage vs quota - the MB number against a per-plan cap, so you can see who is
  near a limit before it is a problem.
- App / schema version - which tenants are behind after a suite update, so an
  upgrade rollout is visible instead of guessed.

### How the account model changes this

This leans directly on open question 2 (hosting model), and the answer swings the
whole approach:

- **All subdomains under ONE cPanel account** makes fleet measurement and
  management much simpler. The data footprints all sit under one home directory,
  so the operator console (running on that same account) can size tenant folders
  directly on disk, no per-tenant HTTP call needed for the MB number. Cron,
  registry, and disk sizing all live in one place.
- **A reseller / WHM setup** is better still for the storage question: disk usage
  is available at the ACCOUNT level from WHM, so per-tenant MB can come straight
  from the hosting layer rather than being computed by app code at all.
- **Separate cPanel accounts per tenant** pushes you firmly toward the app-level
  PULL/PUSH reporting above, because the master cannot see another account's disk
  directly. The stats endpoint (or cron push) becomes the only way to get the
  number.

So the collection design is downstream of the account decision. Recommendation:
make the account-model call (open question 2) first, since one account or WHM
gives you the MB number nearly for free, while separate accounts commit you to the
stats-endpoint work. Either way the registry and the dashboard are the same; only
the metric-collection plumbing changes.

### Security (non-negotiable, this spans customers' data)

The operator console and any stats endpoints must be OWYG-only, on auth that is
completely separate from tenant logins:

- The console lives behind its own login on the master, not a tenant admin
  password. A tenant admin must never be able to reach the fleet view.
- Any PULL stats endpoint on a tenant is protected by a shared operator token
  (a secret the tenant does not display and cannot regenerate), checked before it
  returns a single byte. Same idea for a PUSH endpoint on the master.
- Ideally IP-limit the console and the endpoints to OWYG (allowlist), so even a
  leaked token is not enough on its own.
- Treat the operator token like `mail-secrets.php`: untracked, per-deployment, not
  in git.

This is stricter than the tenant admin on purpose. The tenant admin sees one
company's data; the operator console sees every customer's data footprint at once,
so a compromise here is a cross-customer incident, not a single-tenant one.

### Start simple

Do not build the whole fleet-ops suite up front. Begin with exactly what Clark
named plus the cheap wins that ride along:

1. The registry (subdomain, company, renewal date, contact, status) as a small
   table or JSON on the master.
2. Data usage in MB per tenant (the recursive sizer, collection method per the
   account decision).
3. Renewal date, surfaced on the dashboard with a "renews this month" flag.
4. Cron health, reused from Area 3 at zero extra cost.

That is the two things he asked for plus the two nearly-free ones. Grow the field
set (plans, quotas, module toggles, version tracking, per-tenant billing history)
as the customer count and the business actually need them, not before.

---

## Open questions for Clark

1. **Printing:** are we comfortable vendoring a pure-PHP PDF library (mPDF) for
   consistent output and emailable sheets, or do we stay on hardened browser
   print for now and only revisit if it keeps breaking? (My rec: go PDF, but
   after OWYG go-live so we do not block priority 1.)
2. **Hosting model for tenants:** all tenants as subdomains under ONE cPanel
   account, or each tenant its OWN cPanel account? This decides whether cron and
   provisioning can be scripted (one account) or stay a manual checklist per
   account.
3. **Spawn ergonomics:** do you want a first-run **setup wizard** page baked into
   the suite (walks the operator through base URL, identity, colors, logo, and
   prints the cron lines to paste), or is a written checklist + the existing
   Settings/Branding screens enough?
4. **Seeding identity:** for a brand-new tenant, should the defaults be generic
   product defaults (blank until branded) rather than OWYG's, so a fresh instance
   never briefly shows OWYG's name/logo/colors before branding?
5. **Reminder recipients per tenant:** today Pocket reminders go to one
   `pocket_notify_to` address. Same single-address model per tenant, or does a
   sold product eventually need per-broker fan-out (a bigger build)?
6. **Operator console home:** put the OWYG-only fleet dashboard on the master/
   source instance at `yachtbrokersupport.com` (so master = template source AND
   control plane), or somewhere else entirely? (My rec: the master.)
7. **Metric collection:** PULL (console calls each tenant's stats endpoint) or
   PUSH (each tenant's cron reports up)? This is partly decided by open question 2
   above. (My rec: PULL as primary, and if one account/WHM, size disk directly.)
</content>
</invoke>
