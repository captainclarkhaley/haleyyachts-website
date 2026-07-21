#!/usr/bin/env python3
"""
Renders 1080x1920 vertical social cards (FB + LinkedIn) for the 360-walkthrough
campaign. CTA is the 360 tour link; visible "TAKE THE 360 TOUR" cyan chip sits
in the thumb zone. Same V3-email-template design language:
  - navy gradient base (#0a1628 -> #0d2847 -> #134a6e)
  - reverse-on-dark Haley Yachts swoosh lockup, bottom-left corner
  - Open Sans 300 uppercase headline + 60x3 cyan accent line
  - brand cyan (#21cbea) CTA chip with white text

Also renders the email hero cards Constant Contact pulls by URL, into
images/email/hero/. Optional full-width banner (banner_text) for a headline
claim like "Major Price Reduction" that sits under the header block.

Outputs:
  social-media/cta-cards/fringe-benefits-360-1080x1920.png
  social-media/cta-cards/fortunato-360-1080x1920.png
  social-media/cta-cards/*-1080x1350.png (4:5 in-feed variants)
  images/email/hero/fortunato-major-price-reduction-2026-07-21.png
"""

import os
from PIL import Image, ImageDraw, ImageFont, ImageFilter

# Paths are derived from this script's location so the render works regardless of
# which machine the repo is cloned to (repo root is two levels up from here).
_REPO_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
OUT_DIR = os.path.join(_REPO_ROOT, "social-media", "cta-cards")
# Email hero cards are the same design at 9:16, but they live with the other
# email assets because Constant Contact pulls them by absolute URL.
EMAIL_HERO_DIR = os.path.join(_REPO_ROOT, "images", "email", "hero")
LOGO_PATH = os.path.join(_REPO_ROOT, "images", "brand", "haleyyachtslogo-reverse.png")
# One Water reverse banner, for the stacked co-brand lockup.
OWYG_LOGO_PATH = os.path.join(_REPO_ROOT, "images", "email", "owyg-banner-reverse.png")
PHOTO_DIR = os.path.join(_REPO_ROOT, "images", "yachts", "featured")

W, H = 1080, 1920
NAVY_TOP    = (10, 22, 40)     # #0a1628
NAVY_MID    = (13, 40, 71)     # #0d2847
NAVY_BOTTOM = (19, 74, 110)    # #134a6e
ACCENT      = (33, 203, 234)   # #21cbea brand cyan
WHITE       = (255, 255, 255)
DIM         = (255, 255, 255, 180)
REDUCED     = (228, 64, 64)    # #e44040 warm red for the PRICE REDUCED badge

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


def composite_photo_top(canvas, photo_filename, photo_height=900, dim_alpha=120,
                        bottom_fade=220, top_scrim=0, top_scrim_alpha=255):
    """Paste a hero photo across the top of the canvas with a navy-tinted overlay
    so text stays readable on top of it. Photo bleeds to the edges; a soft
    gradient mask fades the bottom into the navy base.

    bottom_fade     - depth of that bottom fade. Keep it short when the subject
                      sits low in the frame, or the fade eats the hull.
    top_scrim       - depth of an optional fade at the TOP, mirrored: fully navy
                      at y=0 easing to clear by this depth. Gives headline text
                      at the top of the card something solid to sit on and pushes
                      whatever is behind it (a marina, another boat) back.
    top_scrim_alpha - how opaque that scrim is at y=0. 255 = fully navy."""
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

    # Soft fade-out mask along the bottom of the photo strip.
    mask = Image.new("L", photo.size, 255)
    mdraw = ImageDraw.Draw(mask)
    if bottom_fade > 0:
        fade_start = max(0, photo_height - bottom_fade)
        for y in range(fade_start, photo_height):
            t = (y - fade_start) / float(bottom_fade)
            mdraw.line([(0, y), (W, y)], fill=int(255 * (1 - t)))

    canvas.paste(photo, (0, 0), mask)

    # Top scrim: the same idea inverted. Solid navy at the very top easing to
    # clear, so a headline can sit at the top of the card and stay legible.
    if top_scrim > 0:
        scrim = Image.new("RGB", (W, top_scrim), NAVY_TOP)
        smask = Image.new("L", (W, top_scrim), 0)
        sdraw = ImageDraw.Draw(smask)
        for y in range(top_scrim):
            t = y / float(top_scrim)
            # ease-out so it holds near-solid at the top, then releases quickly
            sdraw.line([(0, y), (W, y)], fill=int(top_scrim_alpha * ((1 - t) ** 1.6)))
        canvas.paste(scrim, (0, 0), smask)

    return canvas


def draw_accent_line(draw, y, width=60, thickness=3, color=ACCENT):
    x = (W - width) // 2
    draw.rectangle([x, y, x + width, y + thickness], fill=color)


def draw_price_reduced_badge(draw, y_center, label="PRICE REDUCED", size=30,
                             letterspacing_px=3, pad_x=30, pad_y=14):
    """Centered red pill badge with white uppercase text, vertically centered on
    y_center. Returns (top, bottom) of the badge so callers can flow layout."""
    font = load_font("bold", size)
    advances = [int(font.getlength(ch)) for ch in label]
    text_w = sum(advances) + letterspacing_px * (len(label) - 1)
    badge_w = text_w + pad_x * 2
    badge_h = size + pad_y * 2
    x0 = (W - badge_w) // 2
    y0 = y_center - badge_h // 2
    radius = badge_h // 2
    draw.rounded_rectangle(
        [x0, y0, x0 + badge_w, y0 + badge_h], radius=radius, fill=REDUCED
    )
    x = x0 + pad_x
    ty = y0 + pad_y - 3
    for ch, adv in zip(label, advances):
        draw.text((x, ty), ch, font=font, fill=WHITE)
        x += adv + letterspacing_px
    return (y0, y0 + badge_h)


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


def draw_hot_cta_chip(canvas, label, url, y_center, label_font, url_font,
                       pad_x=70, pad_y=30, label_url_gap=14):
    """One unified 'hot' CTA: wider, brighter cyan pill with a glow shadow.
    Label sits on top, URL printed as smaller subtext on a second line INSIDE
    the same chip. Reads as a single clickable button."""
    label_w = int(label_font.getlength(label))
    url_w = int(url_font.getlength(url))
    text_w = max(label_w, url_w)
    chip_w = text_w + pad_x * 2
    chip_h = label_font.size + url_font.size + label_url_gap + pad_y * 2
    x0 = (W - chip_w) // 2
    y0 = y_center - chip_h // 2
    radius = chip_h // 2

    # Outer glow / shadow halo - draw on an RGBA layer and composite back.
    glow = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    gdraw = ImageDraw.Draw(glow)
    halo_pad = 22
    gdraw.rounded_rectangle(
        [x0 - halo_pad, y0 - halo_pad, x0 + chip_w + halo_pad, y0 + chip_h + halo_pad],
        radius=radius + halo_pad,
        fill=(33, 203, 234, 110),
    )
    glow = glow.filter(ImageFilter.GaussianBlur(radius=24))
    if canvas.mode != "RGBA":
        # Convert in place by re-pasting the merged composite onto canvas.
        merged = Image.alpha_composite(canvas.convert("RGBA"), glow).convert("RGB")
        canvas.paste(merged, (0, 0))
    else:
        canvas.alpha_composite(glow)

    # Re-bind draw to the (possibly mutated) canvas surface.
    draw = ImageDraw.Draw(canvas)
    draw.rounded_rectangle(
        [x0, y0, x0 + chip_w, y0 + chip_h], radius=radius, fill=ACCENT
    )

    # Label centered
    lx = x0 + (chip_w - label_w) // 2
    ly = y0 + pad_y - 4
    draw.text((lx, ly), label, font=label_font, fill=NAVY_TOP)

    # URL centered, slightly translucent navy so it reads as subtext but stays legible
    ux = x0 + (chip_w - url_w) // 2
    uy = ly + label_font.size + label_url_gap - 4
    draw.text((ux, uy), url, font=url_font, fill=(10, 22, 40))


def place_logo_bottom_corner(canvas, max_width=320, max_height=90, x_pad=60, y_pad=70, corner="left"):
    """Reverse-on-dark logo lockup in a bottom corner. Default left; pass
    corner='right' to anchor at the bottom-right. Returns the (x, y, w, h)
    rect actually drawn so callers can vertically align siblings to the logo."""
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
    return (x, y, new_w, new_h)


def place_cobrand_bottom_corner(canvas, hy_max_w=230, hy_max_h=62,
                                owyg_max_w=270, owyg_max_h=76,
                                gap=18, x_pad=60, y_pad=70, corner="right",
                                owyg_dy=0):
    """Stacked co-brand lockup: One Water ABOVE Haley Yachts, One Water slightly
    the larger of the two. Mirrors the website and email footers, where the OWYG
    reverse banner sits above the Haley mark on the dark band.

    Both marks are right-aligned to each other (or left, per `corner`) so their
    edges line up rather than floating. Returns the (x, y, w, h) rect of the
    WHOLE stack so the contact strip can centre against the pair, not one mark."""
    hy = Image.open(LOGO_PATH).convert("RGBA")
    owyg = Image.open(OWYG_LOGO_PATH).convert("RGBA")

    def fit(img, max_w, max_h):
        k = min(max_w / img.width, max_h / img.height)
        return img.resize((int(img.width * k), int(img.height * k)), Image.LANCZOS)

    hy = fit(hy, hy_max_w, hy_max_h)
    owyg = fit(owyg, owyg_max_w, owyg_max_h)

    stack_w = max(hy.width, owyg.width)
    stack_h = owyg.height + gap + hy.height
    x0 = (W - stack_w - x_pad) if corner == "right" else x_pad
    y0 = H - stack_h - y_pad

    # Align both marks to the same edge as the corner they sit in.
    ox = x0 + (stack_w - owyg.width) if corner == "right" else x0
    hx = x0 + (stack_w - hy.width) if corner == "right" else x0
    canvas.paste(owyg, (ox, y0 + owyg_dy), owyg)
    canvas.paste(hy, (hx, y0 + owyg.height + gap), hy)
    return (x0, y0, stack_w, stack_h)


def draw_contact_strip(draw, x_left, logo_rect, line1="DM CLARK HALEY",
                        line2="+1 561-817-1547"):
    """Two-line uppercase contact strip in brand cyan, vertically centered to
    the supplied logo rect, anchored on the left side of the canvas."""
    lx, ly, lw, lh = logo_rect
    font = load_font("bold", 30)
    ls = 2
    # Vertical layout: two lines + small gap, centered on the logo's mid-y.
    line_gap = 8
    block_h = font.size * 2 + line_gap
    logo_mid = ly + lh // 2
    y0 = logo_mid - block_h // 2 - 4  # tiny optical lift to align to caps

    for i, text in enumerate((line1, line2)):
        text = text.upper()
        # Manual letterspacing draw, left-anchored at x_left.
        x = x_left
        advances = [int(font.getlength(ch)) for ch in text]
        for ch, adv in zip(text, advances):
            draw.text((x, y0 + i * (font.size + line_gap)), ch, font=font, fill=ACCENT)
            x += adv + ls


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


def draw_full_width_banner(canvas, draw, y_top, text, height=None, fill=REDUCED,
                           text_fill=WHITE, size=40, letterspacing_px=5):
    """Full-bleed horizontal band with centered uppercase text. Unlike the small
    PRICE REDUCED pill, this spans edge to edge so it reads as a banner across
    the card rather than a badge attached to a line. Returns the band's bottom y
    so the caller can flow the rest of the layout beneath it."""
    label = text.upper()
    font = load_font("bold", size)
    # Shrink to fit if the headline text is long for the canvas.
    while size > 24 and text_width(font, label, letterspacing_px) > W - (MARGIN_X * 2):
        size -= 2
        font = load_font("bold", size)
    band_h = height if height is not None else font.size + 44
    draw.rectangle([0, y_top, W, y_top + band_h], fill=fill)
    y_text = y_top + (band_h - font.size) // 2 - 2   # optical centering for caps
    draw_text_centered(draw, label, font, y_text, text_fill,
                       letterspacing_px=letterspacing_px)
    return y_top + band_h


def render_card(out_filename, photo_filename, eyebrow, hull_name, sub_line,
                cta_label, cta_url, canvas_h=1920, price_reduced=False,
                banner_text=None, out_dir=None, title_at_top=False,
                photo_height=900, bottom_fade=220, top_scrim=0, cobrand=False):
    """Render a single 1080-wide CTA card. canvas_h controls the aspect:
       - 1920 -> 9:16 portrait (default; FB/IG Reels, Stories)
       - 1350 -> 4:5 portrait (FB/IG in-feed image posts, no scroll-crop)
       - 1080 -> 1:1 square (universal)
    All absolute Y positions and the photo strip scale proportionally to
    canvas_h / 1920. Headline + chip fonts stay at full size so messaging
    holds at thumb-stop distance; white space tightens instead."""
    global H
    saved_h = H
    H = canvas_h
    try:
        scale = H / 1920.0

        img = fast_gradient()
        photo_h = int(photo_height * scale)
        composite_photo_top(img, photo_filename,
                            photo_height=photo_h, dim_alpha=110,
                            # bottom_fade is deliberately NOT scaled: it was a
                            # fixed 220px on every aspect before this parameter
                            # existed, and scaling it changes the 4:5 renders.
                            bottom_fade=bottom_fade,
                            top_scrim=int(top_scrim * scale))
        draw = ImageDraw.Draw(img)

        # Hard horizontal budget for any text line.
        text_max_w = W - (MARGIN_X * 2)

        # ---- Eyebrow (above headline, on the photo, uppercase cyan)
        eyebrow_text = eyebrow.upper()
        ls = 4
        eyebrow_font = autosize_font("semibold", eyebrow_text, text_max_w, 40, 26, letterspacing_px=ls)
        y_eyebrow = int((150 if title_at_top else 740) * scale)
        draw_text_centered(
            draw, eyebrow_text, eyebrow_font, y_eyebrow, ACCENT, letterspacing_px=ls
        )

        # ---- Hull name (the big headline) - auto-size to fit width
        name_text = hull_name.upper()
        name_font = autosize_font("bold", name_text, text_max_w, 156, 70, letterspacing_px=4)
        y_name = int((222 if title_at_top else 820) * scale)
        draw_text_centered(draw, name_text, name_font, y_name, WHITE, letterspacing_px=4)

        # Position downstream elements relative to the actual rendered headline.
        # With the title pinned to the top, the photo runs on below it, so the
        # rest of the card has to flow from the bottom of the PHOTO instead -
        # otherwise the accent rule and banner would land on top of the boat.
        name_h = name_font.size
        if title_at_top:
            # Accent rule stays welded to the headline at the top of the card;
            # the rest of the content flows from the bottom of the photo so it
            # never lands on the boat.
            draw_accent_line(draw, y_name + name_h + int(30 * scale),
                             width=60, thickness=3)
            accent_y = photo_h + int(10 * scale)
        else:
            accent_y = y_name + name_h + int(38 * scale)
            draw_accent_line(draw, accent_y, width=60, thickness=3)

        # ---- Optional full-width banner, sitting directly under the header
        # block (photo + eyebrow + hull name + accent rule) and immediately
        # above the price it refers to, so the claim and the number read as one.
        y_after_header = accent_y + int(34 * scale)
        if banner_text:
            y_after_header = draw_full_width_banner(
                img, draw, y_after_header, banner_text,
                height=int(96 * scale), size=int(44 * scale),
            ) + int(30 * scale)

        # ---- Sub line (year/builder/price line, on navy base) - auto-size
        sub_font = autosize_font("semibold", sub_line, text_max_w, 46, 32)
        y_sub = y_after_header + int(30 * scale) if banner_text else accent_y + int(64 * scale)
        draw_text_centered(draw, sub_line, sub_font, y_sub, WHITE)

        # ---- PRICE REDUCED badge (under the price/sub line) when applicable
        badge_bottom = y_sub + sub_font.size
        if price_reduced:
            badge_center = y_sub + sub_font.size + int(48 * scale)
            _, badge_bottom = draw_price_reduced_badge(draw, badge_center)

        # ---- Teaser line (skip on tighter aspects where the chip would crowd it)
        teaser_font = load_font("regular", 38)
        y_teaser = badge_bottom + int(40 * scale)
        if canvas_h >= 1500:
            teaser_text = "Step aboard from anywhere."
            draw_text_centered(draw, teaser_text, teaser_font, y_teaser, DIM[:3])

        # ---- HOT CTA chip in the thumb zone
        chip_label_font = load_font("bold", 56)
        chip_url_font = load_font("semibold", 30)
        chip_y_center = H - int(SAFE_BOTTOM * scale) + int(20 * scale)
        while chip_label_font.size > 38:
            label_w = int(chip_label_font.getlength(cta_label.upper()))
            if label_w + 70 * 2 <= text_max_w:
                break
            chip_label_font = load_font("bold", chip_label_font.size - 4)
        while chip_url_font.size > 22:
            url_w = int(chip_url_font.getlength(cta_url))
            if url_w + 70 * 2 <= text_max_w:
                break
            chip_url_font = load_font("semibold", chip_url_font.size - 2)
        draw_hot_cta_chip(
            img, cta_label.upper(), cta_url, chip_y_center,
            chip_label_font, chip_url_font,
            pad_x=70, pad_y=30, label_url_gap=14,
        )
        # Re-bind draw because draw_hot_cta_chip may have swapped the surface.
        draw = ImageDraw.Draw(img)

        # ---- Brand mark, bottom-right; contact strip on the left in line.
        if cobrand:
            logo_rect = place_cobrand_bottom_corner(
                img, x_pad=60, y_pad=int(96 * scale), corner="right",
                owyg_dy=int(5 * scale),
            )
        else:
            logo_rect = place_logo_bottom_corner(
                img, max_width=240, max_height=64,
                x_pad=60, y_pad=int(80 * scale), corner="right",
            )
        draw_contact_strip(
            draw, x_left=MARGIN_X, logo_rect=logo_rect,
            line1="DM CLARK HALEY",
            line2="+1 561-817-1547",
        )

        out = os.path.join(out_dir or OUT_DIR, out_filename)
        img.save(out, "PNG", optimize=True)
        print("Wrote:", out)
    finally:
        H = saved_h


if __name__ == "__main__":
    os.makedirs(OUT_DIR, exist_ok=True)

    # Fringe Benefits - 2020 Riviera 545 SUV
    render_card(
        out_filename="fringe-benefits-360-1080x1920.png",
        photo_filename="riviera545suv.jpg",
        eyebrow="2020 Riviera 545 SUV",
        hull_name="Fringe Benefits",
        sub_line="West Palm Beach, FL  |  $1,295,000",
        cta_label="Take the 360 Tour",
        cta_url="360.haleyyachts.com/fringebenefit",
        price_reduced=True,
    )

    # Fortunato - 1991 Southern Wind 72
    render_card(
        out_filename="fortunato-360-1080x1920.png",
        photo_filename="southern-wind.jpg",
        eyebrow="1991 Southern Wind 72",
        hull_name="Fortunato",
        sub_line="Bruce Farr Fast 72  |  $329,000",
        cta_label="Take the 360 Tour",
        cta_url="360.haleyyachts.com/fortunato",
    )

    # ---- EMAIL HERO (Constant Contact) -----------------------------------
    # Same 9:16 design, written into images/email/hero/ because sent email pulls
    # its hero by absolute URL off the live site. NEVER overwrite a hero that has
    # already been sent - a new campaign gets a new filename, so the image in an
    # already-delivered email keeps showing what the recipient originally saw.
    # Layout notes (Clark, 2026-07-21): title pinned to the TOP over an inverted
    # scrim - solid navy at y=0 easing down - which both makes the headline
    # legible and pushes the superyacht behind Fortunato back. The photo runs
    # taller (1150) with a SHORT bottom fade (110) because the old 220px fade was
    # swallowing the bottom of her hull. Co-brand lockup stacks One Water above
    # Haley Yachts, One Water the larger of the two, matching the site + email
    # footers.
    render_card(
        out_filename="fortunato-major-price-reduction-2026-07-21.png",
        photo_filename="fortunato-alongside.jpg",
        eyebrow="1991 Southern Wind 72",
        hull_name="Fortunato",
        sub_line="Bruce Farr Fast 72  |  $329,000",
        cta_label="Take the 360 Tour",
        cta_url="360.haleyyachts.com/fortunato",
        banner_text="Major Price Reduction",
        title_at_top=True,
        photo_height=1150,
        bottom_fade=110,
        top_scrim=620,
        cobrand=True,
        out_dir=EMAIL_HERO_DIR,
    )

    # ---- 4:5 image-post variants (1080x1350) -----------------------------
    # FB/IG static image posts cap their feed preview around 4:5; portrait
    # 9:16 gets clipped or shrunken in-feed. The 4:5 sibling fills the feed
    # column edge-to-edge on mobile, no bars on desktop, and works on IG main
    # feed too. Reels keep using the 9:16 above; these are for static posts.

    # Fringe Benefits 4:5
    render_card(
        out_filename="fringe-benefits-360-1080x1350.png",
        photo_filename="riviera545suv.jpg",
        eyebrow="2020 Riviera 545 SUV",
        hull_name="Fringe Benefits",
        sub_line="West Palm Beach, FL  |  $1,295,000",
        cta_label="Take the 360 Tour",
        cta_url="360.haleyyachts.com/fringebenefit",
        canvas_h=1350,
        price_reduced=True,
    )

    # Fortunato 4:5
    render_card(
        out_filename="fortunato-360-1080x1350.png",
        photo_filename="southern-wind.jpg",
        eyebrow="1991 Southern Wind 72",
        hull_name="Fortunato",
        sub_line="Bruce Farr Fast 72  |  $329,000",
        cta_label="Take the 360 Tour",
        cta_url="360.haleyyachts.com/fortunato",
        canvas_h=1350,
    )
