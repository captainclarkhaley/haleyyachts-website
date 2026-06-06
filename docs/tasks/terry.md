# Terry - Website + Engineering Task List

> **Owner / sole writer: Terry.** This is the only task file Terry edits.
> William rolls this up into `docs/TASK-LIST.md` (master). Do not edit the master directly.

*Last updated: June 6, 2026 (Privacy Policy page built + shipped to repo: `privacy.html` at root, footer link wired single-source + synced across all 25 public pages; cookie-consent banner remains an open follow-up)*

## OPEN

### Pages / Content (technical)
- [ ] **Publish the Riviera 4300 Sports Express spotlight article.** Draft pushed to `drafts/2026-06-06-riviera-4300-sports-express-florida-day-boat-bahamas-weekender.html` (original US-framed boat review by Patrick, approved by Clark; promotes the new 4300 arriving July, with an urgency CTA). To publish: (1) move the file to `articles/boat-reviews/` (paths already set for that depth, canonical points there), (2) add a hero image at `articles/boat-reviews/images/riviera-4300-sports-express-hero.jpg` (Clark to provide a shot of the actual incoming boat, or stock 4300 imagery) and confirm the og/twitter/JSON-LD image + alt, (3) register in `articles/articles-data.js` as Boat Reviews dated 2026-06-06, (4) commit + push + cPanel pull. Draft is blocked from public via `drafts/.htaccess`. NOTE: this draft uses "One Water Yacht Group" in the author card + JSON-LD per Clark's OWYG rule; existing articles/template still say "One Water Yacht Sales" - see the OWYG cleanup item below.
- [ ] **OWYS -> OWYG cleanup (site-wide), pending Clark confirm.** `articles/_template.html` and existing published articles use "One Water Yacht Sales" in the author card and JSON-LD `worksFor`, the OWYS form Clark's rules forbid. On Clark's OK, replace with "One Water Yacht Group" across the template and all article pages (author-card text + JSON-LD).
- [x] **Bahamas articles - remove inline Denison byline + inline photo (Clark's call).** Follow-up to the About-Clark photo fix (882825c). Clark decided to drop the original inline author/byline entirely and keep only the standardized "About Clark" card at the bottom + on the template. Removed from `articles/travel/2026-05-05-bahamas-cruising-permit-changes-again.html`: the inline `<figure class="article-figure float-left">` headshot (the one I had repointed earlier - Clark wanted it gone, not fixed) AND the inline byline `<p>` containing "(Formerly Denison Yachts)". Removed from `articles/travel/2026-05-05-bahamas-cruising-permit-changes-april-2026.html`: the same inline byline `<p>` (that file had no inline photo). Body paragraph above ("If you're shopping for a yacht...") kept on both; About Clark `<aside class="article-author-card">` kept intact on both. Markup clean at the seam - no empty container, stray wrapper, or orphaned caption. Site-wide grep for "Denison" (case-insensitive, whole tree): NOW ABSENT from all published/site-facing files. Only 3 internal/non-public hits remain, all expected: `images/video/cta-card/README.md` (a dev note stating the no-Denison rule), `docs/tasks/terry.md` (this log), `docs/tasks/patrick.md` (the no-Denison rule restated). None is a live page; left untouched. On disk + pushed to GitHub main; needs cPanel pull to go live.
- [x] Social icons (Facebook + Instagram): **BOTH LIVE on disk 2026-06-04. 100% sitewide, audited.** Inline-SVG FB + IG icons in the footer on every public page. Originally on 9 footers (7 main pages + `articles.html` + `articles/_template.html`); a 2026-06-04 audit found 15 real public pages built before the template update still lacked the block, so they were back-filled with the identical block: 13 article pages (boat-reviews x1, how-to x2, industry-news x6, newsletters x1, travel x3) plus the 2 yacht listing pages (`yachts/fortunato.html`, `yachts/fringe-benefits.html`). Block inserted in the identical footer spot (between `.footer-brand` and the copyright `<p>`); absolute hrefs + inline SVGs so directory depth is irrelevant. Final full-site audit: **24/24 public pages with a `site-footer` carry both FB + IG, 0 missing.** All FB anchors -> `https://facebook.com/clarkhaleyyachtbroker`, `data-social="facebook"`; all IG anchors -> `https://instagram.com/capnclark`, `data-social="instagram"`; aria-label, target="_blank", rel="noopener" in place. Plus a "Follow Along" block in the contact-page contact-info column. CSS: `.footer-social` + `.contact-social` in css/styles.css (brand-cyan #21cbea hover). Scope-guarded (correctly excluded): `email-templates/*`, `images/video/cta-card/cta-card-template.html`, admin tools. NOTE: changes are on disk only -> needs commit + push + cPanel pull to go live.
- [ ] Social icons - X and LinkedIn: still deferred. Only FB + IG requested 2026-06-04. Add later if Clark wants them (same pattern).

### Polish
- [ ] Final logo pass if Clark still wants to revisit
- [ ] Review color scheme / typography on any pages that feel off
- [ ] Add `sitemap.xml` once a few articles are published

### Pre-Launch Hardening
- [ ] Confirm Formsubmit email verification is current for the contact form
- [ ] Confirm Formsubmit email verification is current for the valuation form
- [x] Privacy policy PAGE: **BUILT + SHIPPED to repo 2026-06-06.** `privacy.html` at root, matches site design system (header/nav, `.page-header` hero, `.section`/`.container`, `.legal-content` prose styles added to css/styles.css). Full approved copy from `docs/drafts/privacy-policy-draft.md`. Resolved placeholders: Effective date + Last updated = June 6, 2026; children's-privacy age threshold = **13** (US COPPA; Clark may switch to 16 for EU). All contact points clark@haleyyachts.com / +1 561-817-1547 / 2601 PGA Blvd., West Palm Beach, FL 33410. No em dashes. Footer link added single-source in `partials/footer.html` (`.footer-links` -> `/privacy.html`), `privacy.html` added to the `sync-footer.sh` PAGES list, `sh scripts/sync-footer.sh` run clean (25/25, --check OK). NOTE: on disk + pushed to GitHub main; **needs cPanel git pull to go public.**
- [ ] Cookie-consent banner: **does NOT exist on the site** (audited 2026-06-06: only "cookie-cutter" copy in services.html, no consent JS/HTML anywhere). The privacy page references a banner aspirationally. Build a real accept/decline non-essential-cookies banner (gate GA4 + Clarity on consent where required) as a follow-up. Not a blocker for the privacy page.
- [ ] Mark GA4 conversion events as "key events" in the Analytics admin so they appear in Conversions reports (GA4 `G-6CVE0DG8Z3`)
- [ ] Future: per-listing inquiry forms, brochure/spec PDF downloads, gallery component - then wire the remaining 3 events Patrick scoped (listing_inquiry_submit, brochure_download, gallery_complete)
- [ ] Cross-browser test on Chrome, Safari, Firefox, Edge
- [ ] Mobile device test on iPhone and Android
- [ ] Revisit hero `min-height: 600px` safety floor during device testing

### Admin Tool Backlog
- [ ] Image Library browser

### Post-Launch (technical)
- [ ] MLS service integration for Worldwide Listings (YachtSite embed retired 2026-05-06; replaced with coming-soon panel. Buy page default tab is now Featured Yachts until a real MLS feed is live - revert default to Worldwide once shipped.)

### Infra / Tooling
- [ ] Trial Leadfeeder (14-day free) for reverse-IP company identification on B2B/family-office traffic

---

## RECENTLY COMPLETED / DONE

### June 6
- [x] **"About Clark" author photo fix (all articles) - quality + centering.** Clark reported the circular author photo at the bottom of every article was low quality and off-center. DIAGNOSIS: the 13 published article author cards loaded `images/people/clark-haley-headshot.jpg` (the 5504x8256, ~10.7 MB full-length studio portrait) downscaled to a 120px circle -> soft from extreme downscale, and because the source is a tall 2:3 portrait with `object-fit: cover` and NO `object-position`, the circle cropped the vertical center (chest area), not the face = the "off-center" symptom. The `_template.html` separately pointed at a nonexistent file `images/people/clark-headshot.jpg`. FIX: swapped all author cards to `images/people/clark-haley-headshot-square.jpg` - the purpose-built 600x600 face-framed crop already used as the canonical headshot in every email template; at 120px (240px @2x retina) it is sharp and self-centers in the circle. Updated 13 published articles + `_template.html` (14 files). CSS `css/styles.css` `.article-author-card .author-photo` gained `object-position: center center` as a safety. Also fixed a separate broken inline body image in `articles/travel/2026-05-05-bahamas-cruising-permit-changes-again.html` (`<img src="images/clark-haley-headshot.jpg">` resolved to a nonexistent path = broken on that page) -> repointed to the square crop with a real alt. No em dashes. On disk + pushed to GitHub main; needs cPanel pull to go live. FLAGGED to William for Clark: that same travel article's byline still reads "(Formerly Denison Yachts)" - left untouched (content/prose, and it touches the no-Denison rule) pending Clark's call.

### June 5
- [x] **Fortunato price reduction: $435,000 -> $395,000** - changed the asking price on every live, forward-facing surface. `yachts/fortunato.html` (5 spots: `<title>`, meta description, `og:title`, `.listing-price`, and the Asking spec row). `js/featured-yachts.js` (the Fortunato card `name`, which also feeds the homepage + buy.html featured grid). `social-media/cta-cards/render-360-cards.py` (2 `sub_line` strings for the 360 social cards). DELIBERATELY LEFT UNCHANGED: the dated May 2026 Logbook archives (`email-templates/issues/logbook-2026-05.html` and `articles/newsletters/2026-05-09-the-logbook-may-2026.html`) - those are sent/published historical records and reflect the price at send time; rewriting them would falsify a dated issue. No other yacht's price touched. On disk + committed; needs cPanel pull.
- [x] **"PRICE REDUCTION" overlay on the featured Southern Wind image** - `images/yachts/featured/southern-wind.jpg` (the image `js/featured-yachts.js` and `yachts/fortunato.html` use) now carries a bold red "PRICE REDUCTION" banner across the upper area with a black text stroke + a subtle dark backing band for legibility against the sky. Original aspect ratio preserved (4800x3200). Original backed up to `images/yachts/featured/southern-wind.original.jpg` (and tracked in git history). TOOLING NOTE: Clark's directive specified ImageMagick, but `magick`/`convert` is not installed on this box and there is no Homebrew to add it; used Python Pillow 11.3.0 (Arial Bold) to produce the equivalent overlay. If ImageMagick is wanted as the standard going forward, it needs installing. On disk + committed; needs cPanel pull.

### June 4
- [x] **Single-source-of-truth footer shipped** - the footer (brand logo, FB + IG social icons, copyright) now lives in ONE file, `partials/footer.html`, and is injected into all 24 public pages by `scripts/sync-footer.sh` between `<!-- FOOTER:START -->` / `<!-- FOOTER:END -->` markers. Future footer changes = edit the partial, run the script, commit, pull. Build-time injection (not client-side JS), so the footer stays in the static HTML and is fully crawlable for SEO; works with no JS and no server config. **Asset paths normalized to root-absolute** (`/images/brand/haleyyachtslogo.png`) so the same partial renders correctly at every depth (root, `articles/<cat>/`, `yachts/`) with no per-depth `../` juggling - verified the logo + links resolve at all three depths. Script is idempotent and has a `--check` mode (non-zero exit on drift) for pre-commit/CI verification. Pure refactor: rendered footer is byte-equivalent to before (same icons, same live hrefs FB `https://facebook.com/clarkhaleyyachtbroker` + IG `https://instagram.com/capnclark`, same copyright) - no visible change. Scope: 8 root + 13 `articles/` (incl. `articles/_template.html` so new articles inherit it) + 2 `yachts/`. Correctly excluded: `admin/`, `email-templates/`, `images/video/cta-card/cta-card-template.html`. Workflow documented in `docs/FOOTER-WORKFLOW.md`. Committed + pushed; needs cPanel pull to deploy.
- [x] **Social icons back-filled to 100% sitewide (15 pre-template pages)** - a full-site audit found that 15 real public pages built before the template update still lacked the `.footer-social` block: 13 article pages (boat-reviews x1, how-to x2, industry-news x6, newsletters x1, travel x3) plus both yacht listing pages (`yachts/fortunato.html`, `yachts/fringe-benefits.html`). Inserted the byte-identical block from index.html into each, in the identical footer spot (between `.footer-brand` and the copyright `<p>`). Absolute external hrefs + inline SVGs, so no `../` adjustment at any depth. Re-audited every public `.html`: **24/24 pages with a `site-footer` now carry both FB (`https://facebook.com/clarkhaleyyachtbroker`) and IG (`https://instagram.com/capnclark`), 0 missing.** Scope-guarded files correctly left untouched: `email-templates/*`, `images/video/cta-card/cta-card-template.html`, admin tools. On disk only; needs commit + push + cPanel pull.
- [x] **Social icons made truly sitewide** - the original staging missed two footers: `articles.html` (the articles index) and `articles/_template.html` (the template every new article is built from). Copied the exact `.footer-social` block from index.html into both, with live hrefs (FB `https://facebook.com/clarkhaleyyachtbroker`, IG `https://instagram.com/capnclark`) and matching `data-social` attributes. Block uses inline SVGs + absolute external hrefs, so no `../` path adjustment was needed at any depth; template's `../../css/styles.css` reference confirmed intact. Footer-social block now present on all 9 footers and byte-identical to index.html. Future articles built from the template inherit the icons automatically. On disk only; needs commit + push + cPanel pull.
- [x] **Instagram link activated** - Clark sent the IG URL (`https://instagram.com/capnclark`). All 8 Instagram anchors (footer on all 7 pages + the "Follow Along" block on contact.html) now point to `https://instagram.com/capnclark`, `data-social="instagram"`. The `instagram-pending` state is cleared site-wide and the leftover placeholder/TODO social comments are removed. Social-icons task is now COMPLETE pending deploy (FB + IG both live on disk). On disk only; needs commit + push + cPanel pull.
- [x] **Facebook link activated** - Clark sent the FB URL. All 8 Facebook anchors (footer on all 7 pages + the "Follow Along" block on contact.html) now point to `https://facebook.com/clarkhaleyyachtbroker`, `data-social="facebook"`. On disk only; needs commit + push + cPanel pull.
- [x] **Social icons staged (Facebook + Instagram)** - inline-SVG FB + IG icons added to the footer on all 7 pages plus a "Follow Along" block on contact.html. All hrefs were placeholders (`href="#"`, `data-social="*-pending"`, TODO comments). New `.footer-social` / `.contact-social` styles in css/styles.css with #21cbea hover. No fabricated URLs. (FB since activated - see above.)
- [x] **Article Manager upgrade** - committed + pushed to origin/main (commits `ad2d865`, `4b60029`, `69e8fad`, `437723b`). GitHub-API publish round-trip verified live (test article published then removed, `66b35af` -> `1a36ce2`), confirming Clark's PAT is in place and working.
  - New **Write Article** mode: in-browser rich text editor (ported from `email-composer.html`) with **insert image at cursor** and **automatic in-browser resize** (1600px longest edge, JPEG ~0.85; PNG kept only when it has transparency). Removes Clark's manual image pre-sizing step.
  - Existing **Upload Word Doc** path kept intact behind a mode toggle.
  - Storage/publish moved to the **GitHub Contents API** using a personal access token stored in the browser (localStorage). Publishes article HTML + images + patches `articles/articles-data.js`, all over the internet, so it works from any computer's browser with no local repo. Old File System Access (folder-picker) flow retained as a **transitional fallback** when no token is set.
  - **Drafts**: save / recall / search / delete under `drafts/` via the API. New `drafts/.htaccess` (`Require all denied`) blocks drafts from the public site.
  - "Manage Published Articles" load + remove also works via the API when a token is set.
  - Required article fields marked with red asterisk + legend (`69e8fad`); friendly token message on GitHub 401 (`437723b`); token controls guarded (`4b60029`).
  - **Folded-in fix**: the numbered-section `<ol>` bug - on the Word path, single-item heading-like `<ol>` blocks now convert to `<h2>`.
- [x] **Shared GitHub API publishing layer** - `admin/featured-yachts.html` + `admin/email-composer.html` migrated onto the same `admin/js/github-api.js` layer as the article manager (`d4dc2a3`).
- [x] **Remote-access setup runbook** added at `docs/REMOTE-ACCESS-SETUP.md` (iMessage channel + Remote Control, scoped to Monterey 12.7.6 box) (`61a6cf0`).
- [x] Article Manager: numbered-section rendering bug fixed. Each section heading in a Word doc got wrapped in its own single-item `<ol>`, so all sections rendered as "1." Real fix shipped in the June 4 upgrade: `fixWordSectionLists()` converts single-item heading-like `<ol>` blocks to `<h2>` on the Word path (write mode emits clean markup and is unaffected). The old manual `start="N"` workaround on the Bahamas how-to article can be left as-is or cleaned up on next edit.

> Reminder: all of the above is committed + pushed to GitHub. To go live on haleyyachts.com / haleymarine.com it still needs a **cPanel Git Version Control Pull** (manual step). Article-manager-published content goes live immediately via the API and does not need the pull.
>
> Site is LIVE at haleyyachts.com (and haleymarine.com - both share the same /public_html via alias). Deploys via GitHub -> cPanel Git Version Control Pull.

### May 26
- [x] Fortunato dedicated listing page shipped at `yachts/fortunato.html`, modeled on `yachts/fringe-benefits.html` + adds an inline "360 Walkthrough" section (Kuula collection 7MFLp embedded) above the gallery so social-campaign traffic lands directly on the walkthrough. Featured Yachts data: `page` field updated to `yachts/fortunato.html` so the buy.html grid's image + title now deep-link to the dedicated page (previously only the Inquire/360 buttons worked).

### May 25
- [x] Featured Yachts: restored lightbox/modal 360 UX on /buy. The previous save from `admin/featured-yachts.html` (when Clark added Fortunato's Kuula tour) had regenerated `js/featured-yachts.js` from the admin tool's older embedded template, clobbering the newer lightbox + hasPage code with the old inline-panel renderer. Restored on disk: full-screen modal opened by the 360 badge AND a separate "View 360 Tour" text button in card actions, image + title clickable to the dedicated `yachts/*.html` listing page when `page` is set (Fringe Benefits has one; Fortunato does not). Fortunato's `kuula:` URL preserved exactly.
- [x] Featured Yachts admin tool: root-cause fix. Save flow no longer regenerates `js/featured-yachts.js` from an embedded template. Instead it reads the current file, parses the `featuredYachts` array region with a brace-aware walker, and applies targeted field replacements to the chosen entry. Move-to-position rewrites only the array region. The renderer code, CSS, modal scaffold, and helper functions in `js/featured-yachts.js` are never touched - the admin tool can only mutate data, not behavior. This prevents the lightbox UX from being clobbered again the next time anyone edits a listing.

### May 4-6
- [x] articles.html filter UX - shipped 2026-05-06: chips toggle exclusion, single combined grid sorted newest-first, search overlay (5 single-select tabs replaced with 5 chip toggles + one combined date-sorted grid)
- [x] Remove `noindex, nofollow` meta tags from any pages that still have them - audited 2026-05-06: only correctly present on admin pages; bonus added missing tag to `admin/featured-yachts.html`
- [x] Add Google Analytics + Microsoft Clarity tracking - shipped 2026-05-06 via `js/analytics.js` (GA4 `G-6CVE0DG8Z3`, Clarity `wn3878tuvv`); 4 conversion events live (contact_form_submit, valuation_request_submit, phone_click, email_click) plus buy_engaged_view proxy at 90s
- [x] Site Analytics dashboard - shipped 2026-05-06 at `/admin/site-analytics.html`: 9 pre-filled deep links into GA4 + Clarity, GA4 Property ID input upgrades links to direct deep links once set
- [x] Favicon swap: new Haley Yachts brand monogram (rounded blue square + wave mark) replaces old compass-rose; multi-res ICO + 6 PNG sizes + SVG wrapper
- [x] Buy page: World Wide Listings broken yachtsite.com embed replaced with styled "coming soon" panel
- [x] Article Manager: fixed `removeArticle` calling undefined `buildArticlesDataFile`; now mirrors the proven regex-replace pattern from `updateManifest`
- [x] Featured Yacht admin: rewrote save flow to use site-root directory handle + read-back verification (the old per-file-handle approach was silently writing to wrong locations)
- [x] Featured Yacht admin: new "Move to Position" feature (insert-shift reorder of slots)

### May 12
- [x] Buy page: default tab switched from World Wide Listings to Featured Yachts pending MLS API source. World Wide Listings remains accessible (now 2nd tab) and `/buy.html#worldwide` deep link still works. Tabs reordered visually so the active tab is leftmost: Featured | World Wide | New.

### April 19
- [x] Active development migrated from old Mac to new Claude Code workspace; GitHub push verified from new machine
- [x] Internal docs (TASK-LIST, SITE-UPDATES) moved to `/docs/` subfolder with Apache deny rule so they no longer render at public URLs
- [x] `admin/.htaccess` synced with live server state (cPanel Directory Privacy `cp:ppd` block now tracked in git)
- [x] Authored fresh root `.htaccess`: force HTTPS + HSTS (Strict-Transport-Security), security headers (X-Content-Type-Options, X-Frame-Options, Referrer-Policy, Permissions-Policy), extensionless URL routing, directory-listing + dotfile hardening

### April 17
- [x] GitHub repo published (captainclarkhaley/haleyyachts-website, public)
- [x] cPanel Git Version Control set up for one-step deploys from GitHub
- [x] Admin password protection activated via cPanel Directory Privacy
- [x] Riviera lineup on New Yachts tab: Sport Yachts, Sports Motor Yachts, SUVs, Sports Express - 18 models with images
- [x] 4300 Sports Express featured at top with 3-image panel and full description
- [x] Featured Yacht admin: renamed, expanded to 6 positions, empty-slot filtering, browse-and-copy image feature
- [x] Blog card images on About page (mvroam, double-wide)
- [x] Logo background made transparent
- [x] YachtSite embed auto-scroll issue fixed (space reservation + scroll guard)
- [x] Valuation banner added to bottom of Sell Your Yacht (sell.html) and Selling Assistance (services.html)
- [x] Hero video compressed to 13 MB (was 65 MB)

### Previously completed (technical)
- [x] Site architecture: multi-page HTML/CSS/JS, shared stylesheet, shared main.js
- [x] Hamburger drawer menu, header phone/email strip, enlarged logo, active nav highlighting
- [x] Sub-tab switching from URL hash
- [x] Logo, favicon, footer brand mark
- [x] About page: full bio, headshot, contact info, Get in Touch button, valuation banner
- [x] Contact page: headshot, Formsubmit form, phone validation, country selector
- [x] Valuation page auto-sends via Formsubmit
- [x] Mobile responsive layout
- [x] Home page hero video with poster fallback, refined headline/tagline
- [x] Buy page: Worldwide Listings (YachtSite embed), Featured Yachts, New Yachts tab
- [x] Sell page: Sell Your Yacht + Valuation tabs with imagery
- [x] Services page: Buying and Selling Assistance sections with images
- [x] Articles: template built, styles added, workflow documented
- [x] Em dashes removed site-wide (43 replacements) [build/markup pass]
- [x] Images folder reorganized by subject; filenames normalized
- [x] Featured Yachts section on Home page (shared data with Buy page)
- [x] Admin landing page + Article Manager + Content Manager + return nav
- [x] SITE-UPDATES.md reference doc

---

## NOTES / DECISIONS (technical)
- Hosting: GoDaddy Linux cPanel, primary domain is haleymarine.com with haleyyachts.com as alias (both share /public_html)
- Deploy workflow: edit -> GitHub Desktop commit + push -> cPanel Git Version Control Pull or Deploy tab -> Update from Remote
- GitHub repo is public; keep secrets out of the repo (use .gitignore for any API keys or credentials)
- DecapCMS available at /admin/content-manager.html but current workflow is Clark-sends-Claude-places
