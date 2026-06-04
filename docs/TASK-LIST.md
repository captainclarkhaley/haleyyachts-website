# Haley Yachts Website - Task List
*Last updated: June 4, 2026*

## BUILT, AWAITING COMMIT + UPLOAD (June 4)

- [ ] Article Manager upgrade (built on disk in `admin/article-manager.html`, NOT yet committed - git was unavailable in the build environment, so Clark or William must commit + push, then deploy via cPanel Git pull):
  - New **Write Article** mode: in-browser rich text editor (ported from `email-composer.html`) with **insert image at cursor** and **automatic in-browser resize** (1600px longest edge, JPEG ~0.85; PNG kept only when it has transparency). Removes Clark's manual image pre-sizing step.
  - Existing **Upload Word Doc** path kept intact behind a mode toggle.
  - Storage/publish moved to the **GitHub Contents API** using a personal access token stored in the browser (localStorage). Publishes article HTML + images + patches `articles/articles-data.js`, all over the internet, so it works from any computer's browser with no local repo. Old File System Access (folder-picker) flow retained as a **transitional fallback** when no token is set.
  - **Drafts**: save / recall / search / delete under `drafts/` via the API. New `drafts/.htaccess` (`Require all denied`) blocks drafts from the public site.
  - "Manage Published Articles" load + remove also works via the API when a token is set.
  - **Folded-in fix**: the numbered-section `<ol>` bug (see Admin Tool Backlog below) - on the Word path, single-item heading-like `<ol>` blocks now convert to `<h2>`.
  - Prereq for Clark: create a fine-grained PAT scoped to the `haleyyachts-website` repo with Contents: Read and write, at https://github.com/settings/personal-access-tokens , then paste it into the GitHub Publishing box once.

Site is LIVE at haleyyachts.com (and haleymarine.com - both share the same /public_html via alias). Deploys via GitHub -> cPanel Git Version Control Pull.

---

## RECENTLY COMPLETED (May 26)

- [x] Fortunato dedicated listing page shipped at `yachts/fortunato.html`, modeled on `yachts/fringe-benefits.html` + adds an inline "360 Walkthrough" section (Kuula collection 7MFLp embedded) above the gallery so social-campaign traffic lands directly on the walkthrough. Featured Yachts data: `page` field updated to `yachts/fortunato.html` so the buy.html grid's image + title now deep-link to the dedicated page (previously only the Inquire/360 buttons worked).
- [x] 360-walkthrough social campaign: two 1080x1920 vertical cards rendered at `social-media/cta-cards/fringe-benefits-360-1080x1920.png` and `fortunato-360-1080x1920.png` via new `render-360-cards.py` script. Navy gradient + photo hero + auto-sized headline + cyan "TAKE THE 360 TOUR" hot chip (URL folded inside as second line) + left-anchored "DM CLARK HALEY / +1 561-817-1547" contact strip + reverse logo in bottom-right. Same asset works for both FB and LinkedIn; only captions differ by platform. CTAs use the clean redirects `360.haleyyachts.com/fringebenefit` and `360.haleyyachts.com/fortunato`.

## RECENTLY COMPLETED (May 25)

- [x] Featured Yachts: restored lightbox/modal 360 UX on /buy. The previous save from `admin/featured-yachts.html` (when Clark added Fortunato's Kuula tour) had regenerated `js/featured-yachts.js` from the admin tool's older embedded template, clobbering the newer lightbox + hasPage code with the old inline-panel renderer. Restored on disk: full-screen modal opened by the 360 badge AND a separate "View 360 Tour" text button in card actions, image + title clickable to the dedicated `yachts/*.html` listing page when `page` is set (Fringe Benefits has one; Fortunato does not). Fortunato's `kuula:` URL preserved exactly.
- [x] Featured Yachts admin tool: root-cause fix. Save flow no longer regenerates `js/featured-yachts.js` from an embedded template. Instead it reads the current file, parses the `featuredYachts` array region with a brace-aware walker, and applies targeted field replacements to the chosen entry. Move-to-position rewrites only the array region. The renderer code, CSS, modal scaffold, and helper functions in `js/featured-yachts.js` are never touched - the admin tool can only mutate data, not behavior. This prevents the lightbox UX from being clobbered again the next time anyone edits a listing.

---

## STILL TO DO

### Content Refinement
- [x] articles.html filter UX - shipped 2026-05-06: chips toggle exclusion, single combined grid sorted newest-first, search overlay
- [x] Sell page: refine copy to match current branding - shipped 2026-05-06: IYBA member-broker credential + brokerage-tier MLS list (YachtWorld, Yatco, IYBA feed) replaces Boat Trader; intro paragraph rewritten in captain-grounded voice (Patrick draft 1A + 2A)
- [x] Services page: refine copy for Buying Assistance and Selling Assistance sections - shipped 2026-05-06: 8 conversion-focused tweaks (20-min/48-hr SLAs, captain credibility, channel list aligned to sell.html, free market analysis CTA) + new Buying-side "Trading Up?" valuation banner
- [ ] Contact page: social media icons/links (Instagram, X, Facebook, LinkedIn) - deferred, Clark not ready (2026-05-06)
- ~~Contact page: map/location reference~~ - declined 2026-05-06, intentionally omitted

### Polish
- [ ] Final logo pass if you still want to revisit
- [ ] Review color scheme/typography on any pages that feel off
- [ ] Add sitemap.xml once a few articles are published

### Pre-Launch Hardening
- [x] Remove `noindex, nofollow` meta tags from any pages that still have them - audited 2026-05-06: only correctly present on admin pages; bonus added missing tag to `admin/featured-yachts.html`
- [ ] Confirm Formsubmit email verification is current for contact form
- [ ] Confirm Formsubmit email verification is current for valuation form
- [x] HSTS (Strict-Transport-Security) - shipped with fresh root `.htaccess` 2026-04-19
- [x] Add Google Analytics + Microsoft Clarity tracking - shipped 2026-05-06 via `js/analytics.js` (GA4 `G-6CVE0DG8Z3`, Clarity `wn3878tuvv`); 4 conversion events live (contact_form_submit, valuation_request_submit, phone_click, email_click) plus buy_engaged_view proxy at 90s
- [ ] Privacy policy page + cookie consent banner (needed for EU/UK visitors; Patrick to draft, Clark to approve, Terry to ship)
- [ ] Mark GA4 conversion events as "key events" in the Analytics admin so they show up in Conversions reports
- [ ] Trial Leadfeeder (14-day free) for reverse-IP company identification on B2B/family-office traffic
- [ ] Future: per-listing inquiry forms, brochure/spec PDF downloads, gallery component - then wire the remaining 3 events Patrick scoped (listing_inquiry_submit, brochure_download, gallery_complete)
- [ ] Cross-browser test on Chrome, Safari, Firefox, Edge
- [ ] Mobile device test on iPhone and Android
- [ ] Revisit hero `min-height: 600px` safety floor during device testing

### Admin Tool Backlog
- [ ] Contact Form Submissions viewer (waiting on HubSpot - Clark provisioning ~2026-05-08)
- [x] Site Analytics dashboard - shipped 2026-05-06 at `/admin/site-analytics.html`: 9 pre-filled deep links into GA4 + Clarity, GA4 Property ID input upgrades links to direct deep links once set
- [ ] Image Library browser
- [x] Article Manager: numbered-section rendering bug. Each section heading in a Word doc got wrapped in its own single-item `<ol>`, so all sections rendered as "1." Real fix shipped in the June 4 upgrade: `fixWordSectionLists()` converts single-item heading-like `<ol>` blocks to `<h2>` on the Word path (write mode emits clean markup and is unaffected). The old manual `start="N"` workaround on the Bahamas how-to article can be left as-is or cleaned up on next edit.

### Post-Launch
- [ ] HubSpot CRM integration (replace Formsubmit with HubSpot forms)
- [ ] MLS service integration for Worldwide Listings (YachtSite embed retired 2026-05-06; replaced with coming-soon panel. Buy page default tab is now Featured Yachts until a real MLS feed is live - revert default to Worldwide once shipped.)
- [ ] Begin publishing articles regularly
- [x] Email newsletter workflow - "The Logbook" master template shipped 2026-05-07 at `email-templates/logbook.html` (+ plain-text companion + bake-masthead.py for monthly composite regen). Per-issue inputs: NOTE, 3 featured listings, 4 articles, 4 recent sales. UTM tracking wired through GA4.

---

## RECENTLY COMPLETED (May 12)

- [x] Buy page: default tab switched from World Wide Listings to Featured Yachts pending MLS API source. World Wide Listings remains accessible (now 2nd tab) and `/buy.html#worldwide` deep link still works. Rationale: the YachtSite embed was replaced with a "coming soon" panel on 2026-05-06, so leading with Featured Yachts is the better visitor experience until a real MLS feed is wired in. Tabs reordered visually so the active tab is leftmost: Featured | World Wide | New.

## RECENTLY COMPLETED (May 4-6)

- [x] Favicon swap: new Haley Yachts brand monogram (rounded blue square + wave mark) replaces old compass-rose; multi-res ICO + 6 PNG sizes + SVG wrapper
- [x] Buy page: World Wide Listings broken yachtsite.com embed replaced with styled "coming soon" panel
- [x] Article Manager: fixed `removeArticle` calling undefined `buildArticlesDataFile`; now mirrors the proven regex-replace pattern from `updateManifest`
- [x] Featured Yacht admin: rewrote save flow to use site-root directory handle + read-back verification (the old per-file-handle approach was silently writing to wrong locations)
- [x] Featured Yacht admin: new "Move to Position" feature (insert-shift reorder of slots)
- [x] articles.html filter UX: 5 single-select tabs replaced with 5 chip toggles + one combined date-sorted grid
- [x] Site Analytics: GA4 (`G-6CVE0DG8Z3`) + Microsoft Clarity (`wn3878tuvv`) installed via `js/analytics.js`; 4 conversion events live + buy_engaged_view proxy
- [x] Site Analytics admin tool: `/admin/site-analytics.html` with pre-filled deep links into GA4 + Clarity dashboards, recordings, heatmaps, events
- [x] noindex audit across all public pages (clean) + added missing tag to `admin/featured-yachts.html`

## RECENTLY COMPLETED (April 19)

- [x] Active development migrated from old Mac to new Claude Code workspace; GitHub push verified from new machine
- [x] Internal docs (TASK-LIST, SITE-UPDATES) moved to `/docs/` subfolder with Apache deny rule so they no longer render at public URLs
- [x] `admin/.htaccess` synced with live server state (cPanel Directory Privacy `cp:ppd` block now tracked in git)
- [x] Authored fresh root `.htaccess`: force HTTPS + HSTS, security headers (X-Content-Type-Options, X-Frame-Options, Referrer-Policy, Permissions-Policy), extensionless URL routing, directory-listing + dotfile hardening

## RECENTLY COMPLETED (April 17)

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

---

## PREVIOUSLY COMPLETED

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
- [x] Em dashes removed site-wide (43 replacements)
- [x] Images folder reorganized by subject; filenames normalized
- [x] Featured Yachts section on Home page (shared data with Buy page)
- [x] Admin landing page + Article Manager + Content Manager + return nav
- [x] SITE-UPDATES.md reference doc

---

## NOTES / DECISIONS

- Hosting: GoDaddy Linux cPanel, primary domain is haleymarine.com with haleyyachts.com as alias (both share /public_html)
- Deploy workflow: edit -> GitHub Desktop commit + push -> cPanel Git Version Control Pull or Deploy tab -> Update from Remote
- GitHub repo is public; keep secrets out of the repo (use .gitignore for any API keys or credentials)
- DecapCMS available at /admin/content-manager.html but current workflow is Clark-sends-Claude-places
