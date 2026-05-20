#!/usr/bin/env python3
"""
Renders the Fringe Benefits reel cards (CTA + opening title), 1080x1920, brand-navy
background, white text, canonical reverse-on-dark Haley Yachts swoosh.

Outputs:
  /Users/jameschaley/Desktop/Claude/haleyyachts-website/social-media/cta-cards/fringe-benefits-cta-1080x1920.png
  /Users/jameschaley/Desktop/Claude/haleyyachts-website/social-media/cta-cards/fringe-benefits-title-1080x1920.png
"""

import os
from PIL import Image, ImageDraw, ImageFont

OUT_DIR = "/Users/jameschaley/Desktop/Claude/haleyyachts-website/social-media/cta-cards"
LOGO_PATH = "/Users/jameschaley/Desktop/Claude/haleyyachts-website/images/brand/haleyyachtslogo-reverse.png"

W, H = 1080, 1920
NAVY = (10, 22, 40)         # #0a1628 - matches site --section-navy
ACCENT = (33, 203, 234)     # #21cbea - brand cyan accent
WHITE = (255, 255, 255)
DIM = (255, 255, 255, 180)

# Generous safe-area inset so Instagram chrome doesn't clip critical lines.
# IG typically reserves the bottom ~220px (action bar) and top ~250px (header).
SAFE_TOP = 280
SAFE_BOTTOM = 320
MARGIN_X = 90


def load_font(weight_hint, size):
    """Load a known-good explicit TTF for the requested weight. Site brand voice is
    Open Sans; for static cards Arial / Helvetica is a clean fallback that PIL
    renders consistently without ligature surprises."""
    candidates = {
        "bold":     ["/System/Library/Fonts/Supplemental/Arial Bold.ttf"],
        "semibold": ["/System/Library/Fonts/Supplemental/Arial Bold.ttf"],
        "regular":  ["/System/Library/Fonts/Supplemental/Arial.ttf"],
        "light":    ["/System/Library/Fonts/Supplemental/Arial.ttf"],
    }
    for path in candidates[weight_hint]:
        if os.path.exists(path):
            try:
                return ImageFont.truetype(path, size)
            except Exception:
                continue
    return ImageFont.load_default()


def draw_text_centered(draw, text, font, y, fill, letterspacing_px=0):
    """Center-align text at vertical position y (where y is the top of the cap-line).
    Uses font.getlength for stable per-char advances and a single shared baseline
    so letter-spacing does not bounce individual glyphs vertically.
    """
    if letterspacing_px == 0:
        w = int(font.getlength(text))
        x = (W - w) // 2
        draw.text((x, y), text, font=font, fill=fill)
        return font.size

    # Letter-spaced path: keep all chars on the same baseline.
    advances = [int(font.getlength(ch)) for ch in text]
    total = sum(advances) + letterspacing_px * (len(text) - 1)
    x = (W - total) // 2
    for ch, adv in zip(text, advances):
        draw.text((x, y), ch, font=font, fill=fill)
        x += adv + letterspacing_px
    return font.size


def place_logo_top(canvas, max_width=620, max_height=180):
    """Reverse-on-dark logo, centered horizontally, at the top safe area."""
    logo = Image.open(LOGO_PATH).convert("RGBA")
    # Scale to fit both bounds
    scale = min(max_width / logo.width, max_height / logo.height)
    new_w = int(logo.width * scale)
    new_h = int(logo.height * scale)
    logo = logo.resize((new_w, new_h), Image.LANCZOS)
    x = (W - new_w) // 2
    y = 160  # sits just above SAFE_TOP, still within IG-safe area
    canvas.paste(logo, (x, y), logo)
    return y + new_h


def draw_accent_line(draw, y, width=90, thickness=4):
    x = (W - width) // 2
    draw.rectangle([x, y, x + width, y + thickness], fill=ACCENT)


def render_cta_card():
    img = Image.new("RGB", (W, H), NAVY)
    draw = ImageDraw.Draw(img)

    logo_bottom = place_logo_top(img)

    # ---- Headline: FRINGE BENEFITS
    headline_font = load_font("bold", 92)
    y_headline = logo_bottom + 180
    draw_text_centered(
        draw, "FRINGE BENEFITS", headline_font, y_headline, WHITE, letterspacing_px=6
    )

    # Accent line under headline
    draw_accent_line(draw, y_headline + 130, width=110, thickness=4)

    # ---- Subtitle: 2020 Riviera 545 SUV  |  $1,495,000
    sub_font = load_font("semibold", 52)
    y_sub = y_headline + 200
    draw_text_centered(
        draw,
        "2020 Riviera 545 SUV  |  $1,495,000",
        sub_font,
        y_sub,
        WHITE,
    )

    # ---- Location line
    loc_font = load_font("regular", 44)
    y_loc = y_sub + 110
    draw_text_centered(draw, "Lying West Palm Beach, FL", loc_font, y_loc, DIM[:3])

    # ---- CTA URL + DM line (anchored to bottom safe area)
    url_font = load_font("semibold", 50)
    dm_font = load_font("regular", 42)

    # Pin two lines just above the IG bottom action bar
    y_dm = H - SAFE_BOTTOM - 50  # bottom line
    y_url = y_dm - 80            # url line above it

    draw_text_centered(
        draw, "haleyyachts.com/yachts/fringe-benefits", url_font, y_url, ACCENT
    )
    draw_text_centered(draw, "DM Clark to schedule a showing", dm_font, y_dm, WHITE)

    out = os.path.join(OUT_DIR, "fringe-benefits-cta-1080x1920.png")
    img.save(out, "PNG", optimize=True)
    print("Wrote:", out)


def render_title_card():
    img = Image.new("RGB", (W, H), NAVY)
    draw = ImageDraw.Draw(img)

    logo_bottom = place_logo_top(img)

    # Vertically center the two-line title block in the safe area
    label_font = load_font("semibold", 56)   # "2020 RIVIERA 545 SUV"
    name_font = load_font("bold", 130)       # "Fringe Benefits"

    # Compute total block height for vertical centering
    label_h = 56
    spacer = 50
    name_h = 130

    block_h = label_h + spacer + name_h + 40  # +40 for accent line space
    safe_h = (H - SAFE_BOTTOM) - SAFE_TOP - (logo_bottom - 160)
    # Anchor the block visually around the upper-middle of the safe area
    y_top = SAFE_TOP + 200

    draw_text_centered(
        draw,
        "2020 RIVIERA 545 SUV",
        label_font,
        y_top,
        ACCENT,
        letterspacing_px=8,
    )

    # accent line
    draw_accent_line(draw, y_top + 100, width=110, thickness=4)

    # Hull name - hero treatment
    draw_text_centered(draw, "Fringe Benefits", name_font, y_top + 200, WHITE)

    # Subtle tagline at the bottom safe area
    tag_font = load_font("regular", 42)
    y_tag = H - SAFE_BOTTOM - 50
    draw_text_centered(
        draw,
        "Luxury cruising with adventure-ready capability",
        tag_font,
        y_tag,
        DIM[:3],
    )

    out = os.path.join(OUT_DIR, "fringe-benefits-title-1080x1920.png")
    img.save(out, "PNG", optimize=True)
    print("Wrote:", out)


if __name__ == "__main__":
    os.makedirs(OUT_DIR, exist_ok=True)
    render_cta_card()
    render_title_card()
