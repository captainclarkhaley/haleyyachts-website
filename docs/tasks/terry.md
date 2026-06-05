# Terry - Website + Engineering Task List

> **Owner / sole writer: Terry.** This is the only task file Terry edits.
> William rolls this up into `docs/TASK-LIST.md` (master). Do not edit the master directly.

*Last updated: June 4, 2026 (Instagram link activated; FB + IG both live on disk)*

## OPEN

### Pages / Content (technical)
- [x] Social icons (Facebook + Instagram): **BOTH LIVE on disk 2026-06-04.** Inline-SVG FB + IG icons in the footer site-wide (index, about, buy, sell, services, valuation, contact) and a "Follow Along" block in the contact-page contact-info column. All 8 FB anchors -> `https://facebook.com/clarkhaleyyachtbroker`, `data-social="facebook"`. All 8 IG anchors -> `https://instagram.com/capnclark`, `data-social="instagram"` (the `instagram-pending` state is cleared site-wide and the placeholder/TODO social comments are removed). aria-label, target="_blank", rel="noopener" in place. CSS: `.footer-social` + `.contact-social` in css/styles.css (brand-cyan #21cbea hover). NOTE: changes are on disk only -> needs commit + push + cPanel pull to go live.
- [ ] Social icons - X and LinkedIn: still deferred. Only FB + IG requested 2026-06-04. Add later if Clark wants them (same pattern).

### Polish
- [ ] Final logo pass if Clark still wants to revisit
- [ ] Review color scheme / typography on any pages that feel off
- [ ] Add `sitemap.xml` once a few articles are published

### Pre-Launch Hardening
- [ ] Confirm Formsubmit email verification is current for the contact form
- [ ] Confirm Formsubmit email verification is current for the valuation form
- [ ] Privacy policy PAGE: build + ship the page and cookie-consent banner. (handoff: Patrick drafts copy + Clark approves -> Terry ships the page/banner)
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

### June 4
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
