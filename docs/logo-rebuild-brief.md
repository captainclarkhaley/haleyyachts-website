# Haley Yachts - Primary Logo Rebuild Brief

Owner: Clark Haley. Drafted by Patrick (marketing). Last updated 2026-05-12.

Purpose: commission an HD, vector-master rebuild of the **Primary horizontal lockup** (standalone swoosh + "HALEY YACHTS" wordmark) plus a full variant set, so every surface (web, print, social, embroidery, signage) pulls from one source of truth.

---

## 1. What we have today

- Primary lockup exists ONLY as 970x174 PNG raster. No vector master.
  - `images/brand/haleyyachtslogo.png` - color on light bg
  - `images/brand/haleyyachtslogo-reverse.png` - white wordmark + blue swoosh on dark bg
  - `images/brand/haleyyachtslogo-footer.png` - all-white on dark bg
- Vector master exists for a DIFFERENT artwork (favicon rounded-square lockup) under `images/brand/favicon_logo/`. That artwork is reserved for favicon use only and is OUT OF SCOPE for this brief. Do not adapt it. The standalone-swoosh primary is the canonical mark and must be rebuilt as its own artwork family.

Geometry to preserve: the swoosh sits to the LEFT of a single-line wordmark. "HALEY" is the dark navy element, "YACHTS" and the swoosh share the medium brand-blue. Wordmark is a clean, slightly condensed geometric sans, all caps, even tracking. Swoosh reads as a stylized hull-wake mark with two strokes and a tapered tail.

---

## 2. Brand colors

Site reference, pulled from `css/styles.css`:

| Role | Hex | Notes |
|---|---|---|
| Brand navy (deep) | `#0a1628` | Used for "HALEY" portion of wordmark and dark hero backgrounds. |
| Brand navy (mid) | `#0d2847` | Secondary navy, headings on light bg. |
| Site cyan accent | `#21cbea` | Web UI accent. NOT the logo blue. |
| White | `#ffffff` | Reverse + all-white variants. |

The medium blue used in the swoosh and "YACHTS" on the existing PNG is NOT `#21cbea`. It reads as a deeper, more saturated royal blue. Designer must color-pick directly from `haleyyachtslogo.png` (sample multiple pixels, average) to lock the exact hex. Provide that hex back to Clark for canonical brand sheet.

Pantone equivalents (for print): designer to propose nearest PMS coated + uncoated, plus CMYK build, once the hex is locked.

---

## 3. Typography

Wordmark font is currently unknown. Visual cues: geometric sans, all caps, optical kerning, slightly condensed proportions, uniform stroke weight. Candidates to test for match: Gotham Bold, Brandon Grotesque Bold, Proxima Nova Bold, Montserrat Bold, Avenir Next Demi Bold.

Designer should NOT re-type the wordmark from a found font and call it done. If the font matches: confirm license. If not: trace the wordmark from the 970px PNG as outlined paths, then refine bezier curves for HD output. Final wordmark ships as outlined vectors (no live text) in all deliverables.

---

## 4. Variant matrix (deliverables)

All variants ship as: master SVG + AI + EPS + PDF, plus PNG exports at 1x / 2x / 3x at three widths (480px, 1200px, 2400px). Transparent backgrounds where applicable.

| # | Variant | Use case | Color spec |
|---|---|---|---|
| 1 | Primary color, horizontal | Site header, light bg | Navy "HALEY" + brand-blue swoosh + brand-blue "YACHTS" |
| 2 | Reverse, horizontal | Dark backgrounds, newsletter masthead | White "HALEY YACHTS" + brand-blue swoosh |
| 3 | All-white, horizontal | Photo backgrounds, dark hero overlays | 100% white everything |
| 4 | All-navy, horizontal | Single-color print on light, embossing, etched | 100% `#0a1628` everything |
| 5 | All-black, horizontal | Letterhead, fax, single-color stamps | 100% black everything |
| 6 | Primary color, stacked | Square social avatars, tight square frames | Swoosh on top, wordmark below, color spec same as #1 |
| 7 | Reverse, stacked | Dark social avatars | Same layout as #6, color spec same as #2 |
| 8 | Mark-only color | Watermarks, UI favicons (non-square), loose avatars | Brand-blue swoosh only, no wordmark |
| 9 | Mark-only white | Dark watermarks, video bug | White swoosh only |
| 10 | Mark-only navy | Single-color print | Navy swoosh only |

Plus brand sheet PDF: lockup, color values (HEX/RGB/CMYK/PMS), typography note, clear-space rule (minimum 1x cap-height padding on all sides), minimum size (web 120px wide for horizontal, 80px for stacked, 32px for mark-only), do/don't examples (no stretching, no recoloring, no drop shadows, no rotating the swoosh).

---

## 5. What NOT to change

- Overall geometry of the swoosh. Match the existing PNG.
- Relative size of swoosh to wordmark in the horizontal lockup.
- Color split: HALEY = navy, YACHTS + swoosh = brand-blue. Do not unify into one color in the color variant.
- Letterspacing and weight feel of the wordmark.
- Square-frame swoosh from the favicon artwork must NOT appear. The standalone swoosh is the primary mark.

---

## 6. Production path - RECOMMENDATION

**Recommended: hire a brand designer for a clean redraw + variant pack.** Budget ceiling **$600-$900**, 5-7 business day turnaround. Look for someone with luxury / marine / hospitality brand work in their portfolio, not a generic logo mill. Sources: Working Not Working, Dribbble pro, a referral from a Florida marine industry contact, or an established Fiverr Pro with vector-rebuild specialization.

Why not AI vector tools (recraft.ai, vectorizer.ai, Firefly):
- Auto-tracers will introduce subtle bezier drift on the swoosh tail and on the rounded shoulders of "C", "S", "Y". The current artwork is clean enough that those artifacts will be visible at large sizes.
- AI generative vector will hallucinate a new swoosh, not preserve the existing one. Unacceptable.
- Acceptable ONLY as a starting point that a human then refines in Illustrator. Not as a final deliverable.

Why not pure manual Illustrator vectorization in-house:
- Cheapest path (free if Clark or a contractor already has Illustrator), but Clark's time is the bottleneck and the variant matrix above is real production work, not a one-off trace. A designer will deliver the brand sheet and clear-space rules at the same time, which we need anyway.

Fallback if budget is tight: pay a vectorization specialist $150-$250 for just the master SVG of variant #1 (color horizontal), then derive the other 9 variants in-house in Figma / Illustrator. Riskier, but workable.

---

## 7. What to send the designer

Attach to the brief when commissioning:

- This document.
- `images/brand/haleyyachtslogo.png` (the reference artwork).
- `images/brand/haleyyachtslogo-reverse.png` (for the reverse color split).
- `images/brand/haleyyachtslogo-footer.png` (for the all-white reference).
- `images/brand/INDEX.md` (context on the asset family).
- A note: the favicon-square lockup in `images/brand/favicon_logo/` is a DIFFERENT artwork family. Do not use it as a reference. The standalone swoosh is the canonical mark.

---

## 8. Acceptance criteria

Before paying final invoice, verify:

- Master SVG opens cleanly in browser and in Illustrator, no rasterized embeds, all paths outlined.
- Side-by-side overlay of new SVG at 970x174 vs. existing PNG at 970x174 shows no visible geometry drift on the swoosh.
- Color hex values match the locked brand-blue and brand-navy (Clark signs off on the picked hex).
- All 10 variants delivered, each in SVG/AI/EPS/PDF + three PNG sizes.
- Brand sheet PDF included with color values, clear-space, minimum size, do/don't.
- Files named per the convention below.

File naming (so they slot into the repo without breaking existing references):

- Keep `haleyyachtslogo.png`, `haleyyachtslogo-reverse.png`, `haleyyachtslogo-footer.png` filenames for the new high-res raster exports of variants #1, #2, #3 - 50+ HTML files reference these.
- New variants: `haleyyachtslogo-allwhite.svg`, `haleyyachtslogo-allnavy.svg`, `haleyyachtslogo-allblack.svg`, `haleyyachtslogo-stacked.svg`, `haleyyachtslogo-stacked-reverse.svg`, `haleyyachtslogo-mark.svg`, `haleyyachtslogo-mark-white.svg`, `haleyyachtslogo-mark-navy.svg`.
- Master SVG of variant #1: `haleyyachtslogo.svg`.
- Print-ready vectors: `haleyyachtslogo.ai`, `haleyyachtslogo.pdf`, `haleyyachtslogo.eps`.

---

## 9. Open items needing Clark / James sign-off

- Final brand-blue hex (designer proposes, Clark approves).
- PMS coated + uncoated values (designer proposes once hex is locked).
- Budget approval at $600-$900 ceiling.
- Designer selection (Patrick to surface 2-3 candidates if Clark wants the search delegated).

Terry: once vector master lands, swap the site-header `<img>` to the new SVG and keep the PNG as a fallback. No code change needed in this brief - production swap is a separate Terry task.
