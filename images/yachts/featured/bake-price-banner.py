#!/usr/bin/env python3
"""
Bake a matched "PRICE REDUCTION" banner onto featured-yacht photos.

Reference style is the Riviera 545 SUV banner: a thin dark semi-transparent
band across the very top (~11.5% of image height), with RED, all-caps,
centered text. Per Clark (2026-06-16) the lettering was dropped from heavy
Impact to a lighter, cleaner Open Sans SemiBold (the brand face) with only a
thin subtle stroke, so it reads clearly without being a heavy black slab.
Both the Riviera 545 and the Southern Wind 72 banners are produced from this
one script so they match as a set.

CLEAN MASTERS (recovered from git history, NOT painted over old banners):
  - Riviera 545 SUV: blob at c0c2ca2^ (parent of the commit that first baked
      the banner): git show c0c2ca2^:images/yachts/featured/riviera545suv.jpg
  - Southern Wind 72: the deleted pre-overlay master
      images/yachts/featured/southern-wind.original.jpg, recovered from
      commit ece0dbc (where it was added, before d8cf6af deleted it):
      git show ece0dbc:images/yachts/featured/southern-wind.original.jpg

Output is written to BOTH the featured path and the newsletter copy for each
boat, byte-identical, matching the existing convention.

Usage: run this from anywhere; pass the clean-master paths in, e.g. the
recovered blobs sit in /tmp/bake during the bake. See main() at the bottom.
"""

from PIL import Image, ImageDraw, ImageFont
import os

# --- Matched banner spec (Riviera 545 is the reference) ---
TEXT          = "PRICE REDUCTION"
BAND_FRAC     = 0.115          # band height as a fraction of image height (~11.5%)
BAND_RGBA     = (10, 14, 22, 158)  # dark navy-black, ~62% opacity (semi-transparent)
TEXT_FILL     = (214, 30, 30)  # brand-ish red, all-caps
STROKE_FILL   = (40, 0, 0)     # near-black, thin + subtle
STROKE_FRAC   = 0.018          # stroke width as fraction of font size (very thin)
TEXT_HEIGHT_FRAC = 0.52        # cap text to ~52% of band height, leaving margins
TEXT_WIDTH_FRAC  = 0.90        # never let text exceed 90% of image width

FONT_PATH = os.path.join(os.path.dirname(os.path.abspath(__file__)),
                         "fonts", "OpenSans-SemiBold.ttf")

OUTPUT_WIDTH = 1600  # deliver both at 1600px wide to match the live convention


def fit_font(draw, text, max_h, max_w):
    """Pick the largest Open Sans SemiBold size that fits height and width."""
    size = int(max_h)
    while size > 8:
        font = ImageFont.truetype(FONT_PATH, size)
        l, t, r, b = draw.textbbox((0, 0), text, font=font)
        if (b - t) <= max_h and (r - l) <= max_w:
            return font, (l, t, r, b)
        size -= 1
    font = ImageFont.truetype(FONT_PATH, 8)
    return font, draw.textbbox((0, 0), text, font=font)


def bake(clean_path, out_paths):
    img = Image.open(clean_path).convert("RGB")
    w, h = img.size

    # Normalize delivery width so both boats render at the same scale.
    if w != OUTPUT_WIDTH:
        new_h = round(h * OUTPUT_WIDTH / w)
        img = img.resize((OUTPUT_WIDTH, new_h), Image.LANCZOS)
        w, h = img.size

    band_h = round(h * BAND_FRAC)

    overlay = Image.new("RGBA", (w, band_h), BAND_RGBA)
    img.paste(Image.alpha_composite(
        img.crop((0, 0, w, band_h)).convert("RGBA"), overlay).convert("RGB"),
        (0, 0))

    draw = ImageDraw.Draw(img)
    max_text_h = band_h * TEXT_HEIGHT_FRAC
    max_text_w = w * TEXT_WIDTH_FRAC
    font, (l, t, r, b) = fit_font(draw, TEXT, max_text_h, max_text_w)

    tw, th = r - l, b - t
    x = (w - tw) / 2 - l
    y = (band_h - th) / 2 - t
    stroke = max(1, round(font.size * STROKE_FRAC))
    draw.text((x, y), TEXT, font=font, fill=TEXT_FILL,
              stroke_width=stroke, stroke_fill=STROKE_FILL)

    for p in out_paths:
        os.makedirs(os.path.dirname(p), exist_ok=True)
        img.save(p, "JPEG", quality=90)
        print("wrote", p, img.size)


def main():
    repo = os.path.normpath(os.path.join(os.path.dirname(os.path.abspath(__file__)),
                                         "..", "..", ".."))
    # Clean masters are staged here during a bake (recovered via git show).
    riv_clean = "/tmp/bake/riv-clean.jpg"
    sw_clean  = "/tmp/bake/sw-clean.jpg"

    bake(riv_clean, [
        os.path.join(repo, "images/yachts/featured/riviera545suv.jpg"),
        os.path.join(repo, "articles/newsletters/images/riviera545suv.jpg"),
    ])
    bake(sw_clean, [
        os.path.join(repo, "images/yachts/featured/southern-wind.jpg"),
        os.path.join(repo, "articles/newsletters/images/southern-wind.jpg"),
    ])


if __name__ == "__main__":
    main()
