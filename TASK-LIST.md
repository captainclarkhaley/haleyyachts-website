# Haley Yachts Website - Launch Task List
*Last updated: April 13, 2026*

---

## COMPLETED
- [x] Site architecture - multi-page HTML/CSS/JS structure
- [x] Shared stylesheet (css/styles.css) and script (js/main.js)
- [x] Hamburger drawer menu (right-side slide-in, Open Sans, dimmed backdrop)
- [x] Header click-to-call phone and email strip (centered between logo and menu)
- [x] Enlarged header logo (+20%)
- [x] Active nav highlighting per page
- [x] Sub-tab switching from URL hash (e.g., sell.html#selling-assist)
- [x] Site logo designed - compass rose monogram with HY serif letters
- [x] Favicon generated (.ico + .svg, darker variant for small sizes)
- [x] Footer brand mark (light variant for dark background)
- [x] About page - full bio, headshot, contact info below photo
- [x] About page "Get in Touch" button below bio
- [x] Removed Denison references site-wide
- [x] "What Is Your Boat Worth?" banner on About page
- [x] Valuation form page - auto-sends via Formsubmit
- [x] Contact page - headshot, Formsubmit form, phone validation, country selector
- [x] Mobile responsive layout
- [x] Buy page - background image added to Worldwide Listings box (yacht-on-plane)
- [x] Sell page - 6000SY background image + "Learn More" button linking to services
- [x] Services page - images for Buying Assistance (GB-aft-view) and Selling Assistance (thanks-bud-stern)
- [x] Home page hero tagline refined and moved below buttons
- [x] Em dashes removed site-wide (43 replacements across 10 files)
- [x] Images folder reorganized into subject-based structure (brand/yachts/lifestyle/people/graphics/video)
- [x] All image filenames normalized (lowercase, hyphens)
- [x] Reduced hero video height to 85vh so footer is visible on load
- [x] Featured Yachts section added to Home page (shared data source with Buy page)
- [x] Admin landing page created at `/admin/` with security notice and tool links
- [x] Featured Image Maintenance admin tool (direct SAVE to featured-yachts.js via File System Access API)
- [x] Content Manager (DecapCMS) moved to `/admin/content-manager.html`
- [x] Return navigation (Back to Admin / Live Site) added to all admin tool pages
- [x] Started SITE-UPDATES.md - running reference for how to maintain site sections

---

## SECTION CONTENT (refine each page)

### Home Page
- [x] Add hero video background
- [x] Create `hero-poster.jpg` still frame for video fallback
- [x] Refine headline and tagline copy
- [x] Add Featured Yachts section below hero (shares data with Buy page via js/featured-yachts.js)

### Buy Page
- [x] Add background image to Worldwide Listings center box
- [x] Add real featured yacht listings with photos and details (Sangaris, Riviera 465 SUV, Southern Wind 72)
- [ ] Add Riviera Yachts content and images to "New Yachts" tab
- [x] Worldwide Listings: YachtSite embed wired into Buy > World Wide Listings tab (power_sail/min/max blank, per_page=100)
- [ ] **TEST: Verify YachtSite embed renders correctly once site is live on a real host** *(third-party scripts can behave differently on localhost vs. production)*

### Sell Page
- [x] Add image to "Sell Your Yacht" subsection
- [x] Wire "Learn More" button to services page
- [ ] Refine copy to match current branding
- [ ] **TEST: Verify valuation form Formsubmit send button works once site is live** *(requires Formsubmit email confirmation)*

### Services Page
- [x] Add images for Buying Assistance and Selling Assistance
- [ ] Refine copy for both subsections

### Articles Page
- [x] Article detail template built (`articles/_template.html`) with full SEO (OG, Twitter, JSON-LD schema, breadcrumbs)
- [x] Article detail page styles added to css/styles.css
- [x] Article workflow documented in SITE-UPDATES.md
- [ ] Clark sends first article (Word doc + hero image + category + edit level)
- [ ] Replace placeholder cards on articles.html with real articles as they publish
- [ ] Add sitemap.xml once a few articles are published (for SEO submission)
- [ ] DecapCMS: optional future path - leave as-is for now since Clark-sends-Claude-uploads workflow is in place

### Contact Page
- [x] Update contact form to use Formsubmit (auto-send)
- [x] Add headshot photo to left of form
- [ ] **TEST: Verify Formsubmit send button works once site is live** *(requires Formsubmit email confirmation)*
- [ ] Add map or location reference if desired
- [ ] Consider adding social media links

---

## IMAGES & MEDIA
- [x] Hero video (Home page background)
- [x] Site logo and favicon
- [x] Buying/Selling assistance images (Services page)
- [x] Valuation boat banner (Riviera-4300-SE)
- [x] Images folder organization and naming convention
- [x] Real yacht photos for Buy page featured listings (3 featured yachts populated)
- [x] Hero video poster still frame (`hero-poster.jpg`)
- [ ] Riviera Yachts model photos for New Yachts tab
- [ ] Optimize/compress large images (headshot is 10MB, home video is 65MB)

---

## BRANDING & DESIGN
- [x] Design Haley Yachts logo (compass rose + HY monogram)
- [x] Create favicon
- [x] Review color scheme and typography across pages
- [ ] Add social media icons/links (Instagram, X, Facebook, LinkedIn)
- [ ] Final logo polish pass (Clark may want to revisit)

---

## STAGING DEPLOYMENT (HaleyMarine.com)

**Plan:** Use HaleyMarine.com as the test/staging URL. Upload site, verify everything works end-to-end, then add haleyyachts.com as an Add-on Domain pointing to the same folder when ready to go live.

**Prep items (Claude to do before upload):**
- [ ] Add `<meta name="robots" content="noindex, nofollow">` to every page so Google doesn't index the staging site
- [ ] Update DecapCMS `admin/config.yml` `site_url` to match staging URL (`https://haleymarine.com`)

**GoDaddy setup steps (Clark to do):**
- [ ] Confirm GoDaddy plan allows another Add-on Domain
- [ ] In cPanel, add HaleyMarine.com as an Add-on Domain pointing to `/public_html/haleymarine/` (or similar folder)
- [ ] Upload site files via cPanel File Manager or FTP
- [ ] Enable free SSL certificate for haleymarine.com
- [ ] Activate admin password protection: cPanel > Directory Privacy > `/public_html/haleymarine/admin` > add user
- [ ] Confirm HTTPS loads correctly, then uncomment HTTPS redirect block in root `.htaccess`
- [ ] Uncomment Strict-Transport-Security header after HTTPS is verified working

**Test pass once live:**
- [ ] All pages load via HTTPS
- [ ] Admin area prompts for password
- [ ] Featured Image Maintenance SAVE tool works (first-time file picker + subsequent saves)
- [ ] Contact form submits via Formsubmit (confirm email received)
- [ ] Valuation form submits via Formsubmit (confirm email received)
- [ ] Mobile and cross-browser test on the live URL

**When ready to flip to production:**
- [ ] Add haleyyachts.com as an Add-on Domain pointing to the SAME folder
- [ ] Repeat for haleyyachting.com and any other purchased domains (or configure as domain forwards)
- [ ] Remove `noindex, nofollow` meta tags from all pages
- [ ] Update DecapCMS `site_url` to `https://haleyyachts.com`
- [ ] Submit XML sitemap to Google Search Console

---

## TECHNICAL / PRE-LAUNCH
- [x] `.htaccess` files created for admin protection and site-wide security headers
- [ ] **Activate admin password protection on GoDaddy** via cPanel > Directory Privacy (see SITE-UPDATES.md for steps)
- [ ] Uncomment HTTPS redirect in root `.htaccess` once SSL is installed
- [ ] Uncomment Strict-Transport-Security header after HTTPS is confirmed working
- [ ] Confirm Formsubmit email verification (clark@haleyyachts.com)
- [ ] Set up hosting (GoDaddy or similar)
- [ ] Configure domain (haleyyachts.com) DNS to new host
- [ ] Install SSL certificate (HTTPS)
- [ ] Add Google Analytics or similar tracking
- [ ] Create XML sitemap for SEO
- [ ] Add meta tags / Open Graph tags for social sharing
- [ ] Test all forms end-to-end on live server
- [ ] Cross-browser testing (Chrome, Safari, Firefox, Edge)
- [ ] Mobile device testing (iPhone, Android)
- [ ] Revisit hero `min-height: 600px` safety floor during device testing - may push footer off-screen on short viewports
- [ ] Page speed optimization (compress images, minify CSS/JS)

---

## ADMIN TOOLS
- [x] Admin landing page at `/admin/index.html` with links to all tools
- [x] Featured Image Maintenance tool
- [x] Article Manager tool (`admin/article-manager.html`) - upload Word docs + images, auto-publish to site
- [x] Articles page (`articles.html`) dynamically loads from `articles/articles-data.js`
- [ ] Add authentication/login to admin area (see Technical/Pre-Launch section)
- [ ] Future tool: Contact Form Submissions viewer (requires backend or HubSpot)
- [ ] Future tool: Site Analytics dashboard (requires Google Analytics integration)
- [ ] Future tool: Image Library browser (`admin/image-library.html`)

---

## FUTURE / POST-LAUNCH
- [ ] HubSpot CRM integration (replace Formsubmit with HubSpot forms)
- [ ] MLS service integration for World Wide Listings
- [ ] Begin publishing articles via Article Manager tool
- [ ] SEO optimization and Google Search Console setup
- [ ] Set up email newsletter workflow
