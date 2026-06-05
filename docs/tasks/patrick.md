# Patrick - Marketing / Social / Content Task List

> **Owner / sole writer: Patrick.** This is the only task file Patrick edits.
> William rolls this up into `docs/TASK-LIST.md` (master). Do not edit the master directly.

*Last updated: June 4, 2026*

## OPEN

### Content
- [ ] Begin publishing articles regularly (set + hold a cadence)
- [ ] Article CONTENT pipeline: keep feeding new pieces to the Article Manager (Terry owns the tool; Patrick owns the words)

### Contact Page Social
- [ ] Contact page social icons: decide the content / handles for Instagram, X, Facebook, LinkedIn. Deferred - Clark not ready (2026-05-06). (handoff: Patrick decides handles/content -> Terry builds the link markup)

### Privacy Policy
- [~] Privacy policy: DRAFT copy is WRITTEN and with Clark for approval (delivered via William, June 4). Awaiting Clark sign-off. Once approved, handoff to Terry to ship the page + cookie-consent banner. Draft discloses GA4 + Microsoft Clarity, Formsubmit-handled contact/valuation forms, OWYG affiliate sharing, retention, and user rights.

### Brand / Copy
- [ ] Brand-voice copy refinements on any pages that still feel off-voice (ongoing)
- [ ] Listing / positioning copy as new listings come in

### Newsletter
- [ ] "The Logbook" newsletter workflow: run the per-issue cadence (master template is built - see Done). Per-issue inputs: NOTE, 3 featured listings, 4 articles, 4 recent sales.

---

## RECENTLY COMPLETED / DONE

### June 4
- [x] **New article published**: "One Water Announces OWYG Bahamas Rendezvous (July 16-19, 2026)" at `articles/industry-news/2026-06-04-...html` (`adbc770`, `20ad079`).

### May 26
- [x] 360-walkthrough social campaign: two 1080x1920 vertical cards rendered at `social-media/cta-cards/fringe-benefits-360-1080x1920.png` and `fortunato-360-1080x1920.png` via new `render-360-cards.py` script. Navy gradient + photo hero + auto-sized headline + cyan "TAKE THE 360 TOUR" hot chip (URL folded inside as second line) + left-anchored "DM CLARK HALEY / +1 561-817-1547" contact strip + reverse logo in bottom-right. Same asset works for both FB and LinkedIn; only captions differ by platform. CTAs use the clean redirects `360.haleyyachts.com/fringebenefit` and `360.haleyyachts.com/fortunato`.

### May 4-7
- [x] Sell page copy: refine to match current branding - shipped 2026-05-06: IYBA member-broker credential + brokerage-tier MLS list (YachtWorld, Yatco, IYBA feed) replaces Boat Trader; intro paragraph rewritten in captain-grounded voice (Patrick draft 1A + 2A)
- [x] Services page copy: refine for Buying Assistance and Selling Assistance sections - shipped 2026-05-06: 8 conversion-focused tweaks (20-min/48-hr SLAs, captain credibility, channel list aligned to sell.html, free market analysis CTA) + new Buying-side "Trading Up?" valuation banner
- [x] Email newsletter workflow - "The Logbook" master template shipped 2026-05-07 at `email-templates/logbook.html` (+ plain-text companion + bake-masthead.py for monthly composite regen). Per-issue inputs: NOTE, 3 featured listings, 4 articles, 4 recent sales. UTM tracking wired through GA4. (Template build with Terry; content/cadence is Patrick's ongoing.)
- [x] Conversion-event scoping: defined the 4 live events + the remaining 3 for future build (listing_inquiry_submit, brochure_download, gallery_complete)

### Decisions
- ~~Contact page: map/location reference~~ - declined 2026-05-06, intentionally omitted

### Previously completed (content)
- [x] Articles: workflow documented; brand voice established
- [x] About page bio + blog card copy
- [x] Em dashes removed site-wide as a brand-voice rule (43 replacements; markup pass by Terry)

---

## NOTES / DECISIONS (marketing)
- No Denison branding on the site, even though Clark's bio mentions Denison history.
- Brand voice: captain-grounded, credible, no AI-filler. Multi-page site only.
- 360 tour redirects: `360.haleyyachts.com/fringebenefit`, `360.haleyyachts.com/fortunato`.
