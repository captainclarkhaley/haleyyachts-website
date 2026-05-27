#!/usr/bin/env python3
"""
Renders 1080x1920 vertical social cards (FB + LinkedIn) for the 360-walkthrough
campaign. CTA is the 360 tour link; visible "TAKE THE 360 TOUR" cyan chip sits
in the thumb zone. Same V3-email-template design language:
  - navy gradient base (#0a1628 -> #0d2847 -> #134a6e)
  - reverse-on-dark Haley Yachts swoosh lockup, bottom-left corner
  - Open Sans 300 uppercase headline + 60x3 cyan accent line
  - brand cyan (#21cbea) CTA chip with white text

Outputs:
  social-media/cta-cards/fringe-benefits-360-1080x1920.png
  social-media/cta-cards/fortunato-360-1080x1920.png
"""

import os
from PIL import Image, ImageDraw, ImageFont, ImageFilter

OUT_DIR = "/Users/jameschaley/Desktop/Claude/haleyyachts-website/social-media/cta-cards"
LOGO_PATH = "/Users/jameschaley/Desktop/Claude/haleyyachts-website/images/brand/haleyyachtslogo-reverse.png"
PHOTO_DIR = "/Users/jameschaley/Desktop/Claude/haleyyachts-website/images/yachts/featured"

W, H = 1080, 1920
NAVY_TOP    = (10, 22, 40)     # #0a1628
NAVY_MID    = (13, 40, 71)     # #0d2847
NAVY_BOTTOM = (19, 74, 110)    # #134a6e
ACCENT      = (33, 203, 234)   # #21cbea brand cyan
WHITE       = (255, 255, 255)
DIM         = (255, 255, 255, 180)

# IG/FB story safe areas; LinkedIn vertical posts have lighter chrome but we
# design to the tighter constraint so the same asset works everywhere.
SAFE_TOP    = 260
SAFE_BOTTOM = 320
MARGIN_X    = 80


def load_font(weight_hint, size):
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


def text_width(font, text, letterspacing_px=0):
    if not text:
        return 0
    if letterspacing_px == 0:
        return int(font.getlength(text))
    advances = [int(font.getlength(ch)) for ch in text]
    return sum(advances) + letterspacing_px * (len(text) - 1)


def draw_text_centered(draw, text, font, y, fill, letterspacing_px=0):
    if letterspacing_px == 0:
        w = int(font.getlength(text))
        x = (W - w) // 2
        draw.text((x, y), text, font=font, fill=fill)
        return font.size
    advances = [int(font.getlength(ch)) for ch in text]
    total = sum(advances) + letterspacing_px * (len(text) - 1)
    x = (W - total) // 2
    for ch, adv in zip(text, advances):
        draw.text((x, y), ch, font=font, fill=fill)
        x += adv + letterspacing_px
    return font.size


def build_background(photo_filename):
    """Photo hero on top half, navy gradient base behind, with a heavy navy overlay
    on the photo so text overlays remain readable. If the photo is missing we
    fall back to a pure 3-stop navy gradient."""
    base = Image.new("RGB", (W, H), NAVY_TOP)

    # 3-stop vertical gradient base layer
    for y in range(H):
        if y < H * 0.4:
            t = y / (H * 0.4)
            r = int(NAVY_TOP[0] + (NAVY_MID[0] - NAVY_TOP[0]) * t)
            g = int(NAVY_TOP[1] + (NAVY_MID[1] - NAVY_TOP[1]) * t)
            b = int(NAVY_TOP[2] + (NAVY_MID[2] - NAVY_TOP[2]) * t)
        else:
            t = (y - H * 0.4) / (H * 0.6)
            r = int(NAVY_MID[0] + (NAVY_BOTTOM[0] - NAVY_MID[0]) * t)
            g = int(NAVY_MID[1] + (NAVY_BOTTOM[1] - NAVY_MID[1]) * t)
            b = int(NAVY_MID[2] + (NAVY_BOTTOM[2] - NAVY_MID[2]) * t)
        for x in range(W):
            base.putpixel((x, y), (r, g, b))

    # Optimization: the per-pixel loop above is correct but slow at 1080x1920.
    # Replace with vectorized gradient via PIL's ImageDraw lines.
    return base


def fast_gradient():
    base = Image.new("RGB", (W, H), NAVY_TOP)
    draw = ImageDraw.Draw(base)
    mid_y = int(H * 0.4)
    for y in range(0, mid_y):
        t = y / mid_y
        r = int(NAVY_TOP[0] + (NAVY_MID[0] - NAVY_TOP[0]) * t)
        g = int(NAVY_TOP[1] + (NAVY_MID[1] - NAVY_TOP[1]) * t)
        b = int(NAVY_TOP[2] + (NAVY_MID[2] - NAVY_TOP[2]) * t)
        draw.line([(0, y), (W, y)], fill=(r, g, b))
    span = H - mid_y
    for y in range(mid_y, H):
        t = (y - mid_y) / span
        r = int(NAVY_MID[0] + (NAVY_BOTTOM[0] - NAVY_MID[0]) * t)
        g = int(NAVY_MID[1] + (NAVY_BOTTOM[1] - NAVY_MID[1]) * t)
        b = int(NAVY_MID[2] + (NAVY_BOTTOM[2] - NAVY_MID[2]) * t)
        draw.line([(0, y), (W, y)], fill=(r, g, b))
    return base


def composite_photo_top(canvas, photo_filename, photo_height=900, dim_alpha=120):
    """Paste a hero photo across the top of the canvas with a navy-tinted overlay
    so text stays readable on top of it. Photo bleeds to the edges; a soft
    gradient mask fades the bottom into the navy base."""
    path = os.path.join(PHOTO_DIR, photo_filename)
    if not os.path.exists(path):
        return canvas

    photo = Image.open(path).convert("RGB")
    # Cover-fit to (W, photo_height)
    scale = max(W / photo.width, photo_height / photo.height)
    nw = int(photo.width * scale)
    nh = int(photo.height * scale)
    photo = photo.resize((nw, nh), Image.LANCZOS)
    # Center-crop
    left = (nw - W) // 2
    top = (nh - photo_height) // 2
    photo = photo.crop((left, top, left + W, top + photo_height))

    # Apply navy tint overlay
    overlay = Image.new("RGB", photo.size, NAVY_TOP)
    photo = Image.blend(photo, overlay, dim_alpha / 255)

    # Soft fade-out mask along the bottom 200px
    mask = Image.new("L", photo.size, 255)
    mdraw = ImageDraw.Draw(mask)
    fade_start = photo_height - 220
    for y in range(fade_start, photo_height):
        t = (y - fade_start) / 220
        alpha = int(255 * (1 - t))
        mdraw.line([(0, y), (W, y)], fill=alpha)

    canvas.paste(photo, (0, 0), mask)
    return canvas


def draw_accent_line(draw, y, width=60, thickness=3, color=ACCENT):
    x = (W - width) // 2
    draw.rectangle([x, y, x + width, y + thickness], fill=color)


def draw_cta_chip(draw, label, y_center, font, pad_x=46, pad_y=22):
    """Cyan pill chip with white uppercase text, centered horizontally."""
    text_w = int(font.getlength(label))
    chip_w = text_w + pad_x * 2
    chip_h = font.size + pad_y * 2
    x0 = (W - chip_w) // 2
    y0 = y_center - chip_h // 2
    radius = chip_h // 2
    draw.rounded_rectangle(
        [x0, y0, x0 + chip_w, y0 + chip_h], radius=radius, fill=ACCENT
    )
    tx = x0 + pad_x
    ty = y0 + pad_y - 4
    draw.text((tx, ty), label, font=font, fill=NAVY_TOP)


def place_logo_bottom_corner(canvas, max_width=320, max_height=90, x_pad=60, y_pad=70, corner="left"):
    """Reverse-on-dark logo lockup in a bottom corner. Default left; pass
    corner='right' to anchor at the bottom-right."""
    logo = Image.open(LOGO_PATH).convert("RGBA")
    scale = min(max_width / logo.width, max_height / logo.height)
    new_w = int(logo.width * scale)
    new_h = int(logo.height * scale)
    logo = logo.resize((new_w, new_h), Image.LANCZOS)
    if corner == "right":
        x = W - new_w - x_pad
    else:
        x = x_pad
    y = H - new_h - y_pad
    canvas.paste(logo, (x, y), logo)


def place_logo_bottom_center(canvas, max_width=300, max_height=80, y_pad=80):
    logo = Image.open(LOGO_PATH).convert("RGBA")
    scale = min(max_width / logo.width, max_height / logo.height)
    new_w = int(logo.width * scale)
    new_h = int(logo.height * scale)
    logo = logo.resize((new_w, new_h), Image.LANCZOS)
    x = (W - new_w) // 2
    y = H - new_h - y_pad
    canvas.paste(logo, (x, y), logo)


def autosize_font(weight, text, max_width, start_size, min_size=40, letterspacing_px=0):
    """Pick the largest font size from start_size down to min_size that fits
    `text` within `max_width` (accounting for optional letter-spacing)."""
    size = start_size
    while size > min_size:
        font = load_font(weight, size)
        if text_width(font, text, letterspacing_px) <= max_width:
            return font
        size -= 4
    return load_font(weight, min_size)


def render_card(out_filename, photo_filename, eyebrow, hull_name, sub_line,
                cta_label, cta_url):
    img = fast_gradient()
    composite_photo_top(img, photo_filename, photo_height=900, dim_alpha=110)
    draw = ImageDraw.Draw(img)

    # Hard horizontal budget for any text line.
    text_max_w = W - (MARGIN_X * 2)

    # ---- Eyebrow (above headline, on the photo, uppercase cyan)
    # Auto-size and choose letter-spacing that still fits in the safe width.
    eyebrow_text = eyebrow.upper()
    ls = 4
    eyebrow_font = autosize_font("semibold", eyebrow_text, text_max_w, 40, 26, letterspacing_px=ls)
    y_eyebrow = 740
    draw_text_centered(
        draw, eyebrow_text, eyebrow_font, y_eyebrow, ACCENT, letterspacing_px=ls
    )

    # ---- Hull name (the big headline, bottom of photo zone) - auto-size to fit
    name_text = hull_name.upper()
    name_font = autosize_font("bold", name_text, text_max_w, 156, 70, letterspacing_px=4)
    y_name = 820
    draw_text_centered(draw, name_text, name_font, y_name, WHITE, letterspacing_px=4)

    # Position downstream elements relative to the actual rendered headline size.
    name_h = name_font.size
    accent_y = y_name + name_h + 38
    draw_accent_line(draw, accent_y, width=60, thickness=3)

    # ---- Sub line (year/builder/price line, on navy base) - auto-size
    sub_font = autosize_font("semibold", sub_line, text_max_w, 46, 32)
    y_sub = accent_y + 64
    draw_text_centered(draw, sub_line, sub_font, y_sub, WHITE)

    # ---- Teaser line
    teaser_font = load_font("regular", 38)
    y_teaser = y_sub + 110
    teaser_text = "Step aboard from anywhere."
    draw_text_centered(draw, teaser_text, teaser_font, y_teaser, DIM[:3])

    # ---- CTA chip in the thumb zone
    chip_font = load_font("bold", 44)
    chip_y_center = H - SAFE_BOTTOM + 30
    draw_cta_chip(draw, cta_label.upper(), chip_y_center, chip_font,
                  pad_x=50, pad_y=24)

    # ---- URL centered under the chip
    url_font = load_font("regular", 32)
    y_url = chip_y_center + 80
    draw_text_centered(draw, cta_url, url_font, y_url, DIM[:3])

    # ---- Brand mark, bottom-right CORNER (Clark spec: "top or bottom corner,
    # not centered like a poster"). Tucked below the URL line so the chip + URL
    # remain the clear focal point.
    place_logo_bottom_corner(img, max_width=240, max_height=64,
                             x_pad=60, y_pad=80, corner="right")

    out = os.path.join(OUT_DIR, out_filename)
    img.save(out, "PNG", optimize=True)
    print("Wrote:", out)


if __name__ == "__main__":
    os.makedirs(OUT_DIR, exist_ok=True)

    # Fringe Benefits - 2020 Riviera 545 SUV
    render_card(
        out_filename="fringe-benefits-360-1080x1920.png",
        photo_filename="riviera545suv.jpg",
        eyebrow="2020 Riviera 545 SUV",
        hull_name="Fringe Benefits",
        sub_line="West Palm Beach, FL  |  $1,495,000",
        cta_label="Take the 360 Tour",
        cta_url="360.haleyyachts.com/fringebenefit",
    )

    # Fortunato - 1991 Southern Wind 72
    render_card(
        out_filename="fortunato-360-1080x1920.png",
        photo_filename="southern-wind.jpg",
        eyebrow="1991 Southern Wind 72",
        hull_name="Fortunato",
        sub_line="Bruce Farr Fast 72  |  $435,000",
        cta_label="Take the 360 Tour",
        cta_url="360.haleyyachts.com/fortunato",
    )
