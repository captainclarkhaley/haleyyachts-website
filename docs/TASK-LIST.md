# Haley Yachts Website - Task List
*Last updated: April 19, 2026*

Site is LIVE at haleyyachts.com (and haleymarine.com - both share the same /public_html via alias). Deploys via GitHub -> cPanel Git Version Control Pull.

---

## STILL TO DO

### Content Refinement
- [ ] Articles: send me your first article (Word doc + hero image + category + edit level) so we can replace the placeholder cards on articles.html
- [ ] articles.html filter UX (Terry, scheduled): default view shows articles from ALL categories, sorted by date published (newest first). Category chips act as exclusion filters - clicking a category hides it. All active by default; user clicks to narrow. Clark will review + test locally before publishing.
- [ ] Sell page: refine copy to match current branding
- [ ] Services page: refine copy for Buying Assistance and Selling Assistance sections
- [ ] Contact page: optional map/location reference
- [ ] Contact page: social media icons/links (Instagram, X, Facebook, LinkedIn)

### Polish
- [ ] Final logo pass if you still want to revisit
- [ ] Review color scheme/typography on any pages that feel off
- [ ] Add sitemap.xml once a few articles are published

### Pre-Launch Hardening
- [ ] Remove `noindex, nofollow` meta tags from any pages that still have them (site is live, should be indexable)
- [ ] Confirm Formsubmit email verification is current for contact form
- [ ] Confirm Formsubmit email verification is current for valuation form
- [x] HSTS (Strict-Transport-Security) - shipped with fresh root `.htaccess` 2026-04-19
- [ ] Add Google Analytics (or similar) tracking
- [ ] Cross-browser test on Chrome, Safari, Firefox, Edge
- [ ] Mobile device test on iPhone and Android
- [ ] Revisit hero `min-height: 600px` safety floor during device testing

### Admin Tool Backlog
- [ ] Future tool: Contact Form Submissions viewer (requires HubSpot or backend)
- [ ] Future tool: Site Analytics dashboard (Google Analytics integration)
- [ ] Future tool: Image Library browser

### Post-Launch
- [ ] HubSpot CRM integration (replace Formsubmit with HubSpot forms)
- [ ] MLS service integration for Worldwide Listings (currently using YachtSite embed)
- [ ] Begin publishing articles regularly
- [ ] Email newsletter workflow

---

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
