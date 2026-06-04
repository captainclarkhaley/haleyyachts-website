# Haley Yachts - Video CTA End Card

A reusable end card to drop at the end of Clark's videos. Top zone is a
per-video placeholder (product/yacht photo + 1-2 caption lines); the rest is
fixed branding: headshot, name, title, contact, Haley Yachts logo, OWYG logo.

## Files

- `cta-card-template.html` - the editable, self-contained template (both sizes).
- `render-cards.sh` - renders both PNGs with the installed Chrome. No installs.
- `render-cards.js` - same thing via Node, if you prefer (`node render-cards.js`).
- `cta-card-1080x1080.png` - square / IG feed (after you render).
- `cta-card-1080x1350.png` - 4:5 portrait / IG tall (after you render).

## Render (one step)

```bash
bash "/Users/jameschaley/Desktop/Claude/haleyyachts-website/images/video/cta-card/render-cards.sh"
```

Outputs both PNGs into this folder. Re-run any time after editing the template.

How it works: the template holds both cards on one page. Loading it with
`#only-1x1` or `#only-4x5` isolates one card at exact canvas size, and headless
Chrome screenshots it. Opening the file normally in a browser shows both cards
stacked for preview.

## Swap in a per-video product photo + caption

Open `cta-card-template.html` in any editor. Do this in BOTH cards (square and
portrait) so both sizes match, or just the one size you need.

1. Find the block marked `<!-- PRODUCT ZONE -->`.
2. Turn off the placeholder hint: add `has-photo` to the product-zone div:
   `<div class="product-zone has-photo">`
3. Point at your photo on the `.product-photo` div:
   `<div class="product-photo" style="background-image:url('my-yacht.jpg');">`
   Easiest path: drop the photo file into this same folder and use just its
   filename. A web URL also works.
4. Edit the caption text in `.product-caption`. Keep it to one or two short
   lines. The second line uses the `caption-sub` span for the smaller detail.
   The placeholder text names its own font so you can match it for consistency.
5. Re-render with the command above.

## Fonts (match these for consistency across every video)

All text is **Open Sans** (free on Google Fonts). If you retype or replace a
line in Premiere, use the same family, weight, size, and color:

| Text | Weight | Size (1x1 / 4x5) | Color |
|------|--------|------------------|-------|
| Caption title (your listing) | SemiBold (600) | 30px | `#0d2847` |
| Caption detail line | Regular (400) | 22px | `#5d6b78` |
| Broker name | ExtraBold (800) | 54px / 60px | `#ffffff` |
| Broker title | Regular (400) | 23px / 25px | `#bcd3e2` |
| Contact lines | Medium (500) | 25px / 27px | `#eaf3f8` |

The caption (top zone) is the only text you overwrite per video; everything in
the broker block is fixed.

When `has-photo` is set, the dashed box and "Drop product photo here" hint
disappear and the cyan caption bar becomes a plain caption. With it off, the
card renders in placeholder/template mode so you can see where things land.

## Change sizes

The two canvases are defined by `.card-1x1` (1080x1080) and `.card-4x5`
(1080x1350) in the template CSS, plus the matching `--window-size` values in
`render-cards.sh`. To add another size, copy a card block, give it a new class
with the new height, add a `body.only-NAME` rule and a hash branch in the
inline script, then add a render line to the script with the new
`--window-size`. Ask William/Terry if you want another size wired up.

## Brand notes

- Palette and Open Sans pulled from the live site CSS. No new colors invented.
- Reverse/white logos used on the dark navy background.
- No em dashes anywhere. No Denison branding.
