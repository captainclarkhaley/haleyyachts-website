# Listing Copy: 1997 Offshore 48 "Island Girl"

> For Terry to drop into the listing-page template (same structure as `yachts/fortunato.html` / `yachts/fringe-benefits.html`). Copy only - no HTML build here.
> Brand rules applied: no em dashes anywhere; "One Water Yacht Group" / OWYG; warm-but-credible captain voice.
> Patrick, 2026-06-16.

---

## NEED FROM CLARK (fill the brackets before publish)

These are the hard specs we do not yet have. The narrative below is written to read well WITHOUT them, but the bracketed placeholders need real values before the page goes live:

- **[ASKING]** - asking price, or confirm we keep "Price on request"
- **[MODEL/VARIANT]** - exact Offshore 48 configuration: Pilothouse vs Sedan vs Sport (changes a couple of copy lines below)
- **[ENGINES]** - engine make/model + total hours
- **[STATEROOMS]** / **[HEADS]** - number of staterooms and heads
- **[LOCATION]** - current location of the vessel
- **[STANDOUT]** - any standout equipment, electronics, or refit/repower history worth naming

Where a hard number is required, the bracketed placeholder is left inline. If "Price on request" stays, use the "Price on request" variants flagged below and drop the price-amount meta tags.

---

## 1. Page `<title>`

**If a price is set:**
`1997 Offshore 48 "Island Girl" - [ASKING] | Haley Yachts`

**If Price on request:**
`1997 Offshore 48 "Island Girl" - Price on Request | Haley Yachts`

---

## 2. Meta description

`1997 Offshore 48 "Island Girl" - a semi-custom bluewater cruising motoryacht from Offshore Yachts of California, built to the pilothouse pedigree that owners cross oceans in. Offered by Clark Haley, Haley Yachts.`

---

## 3. Open Graph + Twitter

**OG/Twitter title (price set):** `1997 Offshore 48 "Island Girl" - [ASKING]`
**OG/Twitter title (Price on request):** `1997 Offshore 48 "Island Girl"`

**OG/Twitter description:**
`Offshore Yachts of California pedigree. A semi-custom bluewater motoryacht built to high standards and made for real distance. Step aboard.`

**og:image:alt / twitter image alt:**
`1997 Offshore 48 Island Girl cruising motoryacht`

---

## 4. JSON-LD Product description

`1997 Offshore 48 "Island Girl" - a semi-custom cruising motoryacht built by Offshore Yachts of California, the West Coast yard known for bluewater-capable, high-build-quality motoryachts made to go the distance in comfort.`

JSON-LD field guidance for Terry:
- `"brand"` -> `"Offshore Yachts"`
- `"model"` -> `"48"` (append variant once we have [MODEL/VARIANT])
- `"productionDate"` -> `"1997"`
- `"category"` -> `"Motor Yacht"`
- `additionalProperty` Type -> `"Cruising Motoryacht"`
- If Price on request, omit the `price` / `priceCurrency` offer fields and the `product:price:*` meta tags rather than inventing a number.

---

## 5. On-page positioning line (one punchy sentence)

`1997 Offshore 48, semi-custom motoryacht from Offshore Yachts of California. Built like a bluewater boat, finished like a home.`

---

## 6. H1 vessel name treatment

Follows the Fortunato pattern (name split, second half bold):

`Island <strong>Girl</strong>`

---

## 7. "About Island Girl" narrative (2 short paragraphs)

Island Girl is a 1997 Offshore 48, built by Offshore Yachts of California, a semi-custom yard whose reputation rests on one thing: motoryachts engineered to go offshore and stay comfortable doing it. Where most production boats this size are built for the marina and the occasional bay run, an Offshore is built to cross open water, ride a real sea with composure, and bring everyone aboard home easy. That pedigree does not fade with the model year. It is in the bones of the boat.

This is a yacht for the owner who wants distance without giving up the feeling of home. Inside, the joinery and finish are the kind that hold their value because they were done right the first time. Outside, she has the sea manners that let you pick a longer day, a farther anchorage, a real crossing, with the family aboard and confidence to spare. For a buyer who has been waiting on a well-built 48 with genuine bluewater pedigree, Island Girl is worth the trip to see in person.

---

## 8. Key Specs list

| Field | Value |
|-------|-------|
| Year | 1997 |
| Builder | Offshore Yachts (Offshore Yachts of California) |
| Model | 48 [MODEL/VARIANT] |
| Designer | Offshore Yachts |
| LOA | 48 ft |
| Type | Cruising Motoryacht |
| Asking | [ASKING] (or "Price on request") |

Optional add-on rows once Clark provides them (the Fortunato template only carries the seven above, but these slot cleanly into the same `<dl>` if Terry wants them on-page):
- Engines: [ENGINES]
- Accommodations: [STATEROOMS] staterooms / [HEADS] heads
- Location: [LOCATION]

---

## 9. Notes for Terry (mechanical, not copy)

- Inquiry form hidden fields, mailto subject, and the `_subject` line should read: `Island Girl - 1997 Offshore 48`.
- 360 walkthrough section: only include if a tour exists. If not, drop the section (Island Girl is not in the 360 redirect list yet).
- Back link target: `../buy.html#featured`.
- Hero/gallery image path TBD - confirm the filename with Clark.

---

## One-line rationale

Who/what/where: detail-page copy for the third June Logbook featured listing (1997 Offshore 48 "Island Girl"), written to sell the Offshore-of-California bluewater pedigree and convert a featured-listing click into a showing request, hosted at `yachts/island-girl.html`. Needs Clark's sign-off on the bracketed specs and final price before publish.
