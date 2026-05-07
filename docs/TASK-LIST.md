# Haley Yachts Website - Task List
*Last updated: May 6, 2026*

Site is LIVE at haleyyachts.com (and haleymarine.com - both share the same /public_html via alias). Deploys via GitHub -> cPanel Git Version Control Pull.

---

## STILL TO DO

### Content Refinement
- [x] articles.html filter UX - shipped 2026-05-06: chips toggle exclusion, single combined grid sorted newest-first, search overlay
- [x] Sell page: refine copy to match current branding - shipped 2026-05-06: IYBA member-broker credential + brokerage-tier MLS list (YachtWorld, Yatco, IYBA feed) replaces Boat Trader; intro paragraph rewritten in captain-grounded voice (Patrick draft 1A + 2A)
- [x] Services page: refine copy for Buying Assistance and Selling Assistance sections - shipped 2026-05-06: 8 conversion-focused tweaks (20-min/48-hr SLAs, captain credibility, channel list aligned to sell.html, free market analysis CTA) + new Buying-side "Trading Up?" valuation banner
- [ ] Contact page: optional map/location reference
- [ ] Contact page: social media icons/links (Instagram, X, Facebook, LinkedIn)

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

### Post-Launch
- [ ] HubSpot CRM integration (replace Formsubmit with HubSpot forms)
- [ ] MLS service integration for Worldwide Listings (currently using YachtSite embed)
- [ ] Begin publishing articles regularly
- [ ] Email newsletter workflow

---

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
