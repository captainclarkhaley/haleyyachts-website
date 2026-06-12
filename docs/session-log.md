# Haley Yachts - Session Log (durable record)

A dated, human-readable record of work sessions so Clark can refer back later. Newest entry on top.

This is the SESSION LOG (what we did, by date). The separate `SITE-UPDATES.md` is the how-to procedures reference (how to update common pieces of the site). Two different files.

---

## Session: SEO, GBP, image cleanup, broker email - 2026-06-11

Work spanned late 2026-06-10 into 2026-06-11. Everything below is on GitHub. Most on-site changes still need Clark's manual cPanel Git pull to go live; Article-Manager-published content and the GoDaddy / Google changes are noted where they are already live.

### Privacy / cookies
- `privacy.html` is built and live in the repo (footer link is single-sourced from the footer partial).
- Cookie-consent banner: CLOSED BY DECISION. We reworded the privacy policy to a browser / provider opt-out approach instead of an on-site accept / decline banner. No banner will be built. Revisit only if Haley Yachts targets EU / UK / EEA visitors (GDPR would then require an explicit consent gate).

### Admin: Image Library browser
- Built `admin/image-library.html` - a browse + copy-path tool plus a usage / orphan view (flags likely-unused images; best-effort, never an auto-delete list).
- Wired in from the Admin home (`admin/index.html`) and from the insert flows of both the Article Manager and the Featured Yacht editor.

### SEO pass
- Added `sitemap.xml` + `robots.txt` at the root.
- Sitewide LocalBusiness / YachtBroker (ProfessionalService + LocalBusiness) JSON-LD via the single-source footer partial.
- Product / Boat JSON-LD + canonicals on the 3 yacht listings.
- Canonical + OpenGraph + Twitter tags on the money pages: index, buy, sell, services, about, contact, valuation.
- Business hours in schema + on the contact page: Mon-Fri 9:00am-5:00pm, Sat / Sun by appointment (weekends deliberately left out of structured data, since schema.org has no clean by-appointment value).

### Canonical business address - corrected and reconciled sitewide
- Canonical NAP: 2401 PGA Blvd, Suite 164, Palm Beach Gardens, FL 33410. This matches OWYG corporate.
- An earlier pass had used the wrong street number (2601), and the privacy page had the wrong city (West Palm Beach). Both fixed.
- Reconciled across the footer JSON-LD (geo updated to the 2401 block, 26.8430 / -80.0738), the 6 displayed full-address strings (contact, valuation, privacy, and the 3 yacht pages), and the meta-description localities. Repo-wide grep for "2601" now returns zero.

### Image optimization + cleanup
- Full repo-wide raster pass: about 125 MB saved across 84 images (resized + recompressed in place, same filenames so no references broke).
- Removed orphan / duplicate images (~20 MB) and a redundant byte-identical brand-art directory (`logo-haley-yachts-logo/`, ~11 MB; kept `images/brand/favicon_logo/` as the canonical copy).
- Net result: roughly 145 MB+ lighter.
- Still open: the 14 MB hero MP4 needs a separate H.264 / WebM re-encode (out of scope for the image pass).

### floridayachts.ai redirect
- It was a broken masked GoDaddy forward (duplicate content, no link equity, HTTPS fully broken).
- Changed to a clean Permanent 301 to haleyyachts.com; verified live, with HTTPS fixed.
- Decision: haleyyachts.com stays primary; floridayachts.ai redirects in.
- floridayachts.com is taken (registered since 1998, not available).
- Shortlist of available defensive domains identified for Clark to optionally grab: haleyyacht.com, haleyyachts.net, haleyyachts.co, haleyyachts.us, haleyyachtsales.com, haleyyachtbrokers.com, palmbeachgardensyachts.com.

### Google Business Profile
- Full setup package authored at `docs/marketing/google-business-profile-setup.md`: categories, NAP, description (Clark chose the playful "Option B"), services, hours, photo checklist, 4 Google Posts, starter Q&A, and the claim / verify steps.
- Owner account: clark@haleyyachts.com.
- During the session Clark CLAIMED the profile and went LIVE with the description, hours, and photos.

### Content: 8 article drafts
Created as Article Manager JSON drafts in `drafts/`, awaiting Clark's review / publish:
- How to Buy a Yacht: The Step-by-Step Timeline
- Boat Loans and Marine Financing, Explained
- The True Cost of Yacht Ownership
- Diesel vs Outboard for the Florida Owner
- Trading Up: When to Move to a Bigger Yacht
- A First-Time Buyer's Guide to Riviera
- Insuring a Yacht in Florida: What to Know
- Provisioning and Prepping for a Crossing to the Bahamas

### Marketing email: broker co-broke
- Riviera 545 SUV "Fringe Benefits" broker co-broke email finalized at `email-templates/issues/riviera-545-suv-broker-cobroke.html`.
- Subject: "New Listing: 2020 Riviera 545 SUV". Listing links point to the OWYG details page. Ready to send.

### Infra
- GitHub push auth from the Mac was fixed during the session (credentials now stored). Pushes work normally.

### OPEN / NEXT for Clark
- Publish the 8 article drafts via the Article Manager.
- Listing syndication question: does One Water push to YachtWorld / boats.com / YATCO centrally, or is it per-broker? Answer this before duplicating effort.
- Season the GBP with the ready-made Google Posts + starter Q&A.
- Optionally register the defensive domains from the shortlist above.
- Optional heavier-media re-encode: the 14 MB hero MP4 is still pending.

---
