#!/usr/bin/env python3
"""
Re-bake the Logbook newsletter masthead composite.

Combines a hero photo with the Haley Yachts reverse logo, "The Logbook" title,
tagline, and a cyan accent line into a single 1200x720 JPG (displays at 600x360
in the email at 2x DPI).

Usage:
    python3 bake-masthead.py [--photo PATH]

Defaults assume the script is run from the repo root. The output overwrites
images/email/logbook-masthead.jpg in place, so just upload the file to the
live server after running.

Requires: Pillow (pip3 install Pillow).
"""
import argparse
import os
import sys

from PIL import Image, ImageDraw, ImageFont

DEFAULTS = {
    "photo":  "haleyyachts-website/images/email/nordhavn80.jpeg",
    "logo":   "haleyyachts-website/images/brand/haleyyachtslogo-reverse@2x.png",
    "out":    "haleyyachts-website/images/email/logbook-masthead.jpg",
    "title":  "The Logbook",
    "tagline":"MONTHLY BRIEFING FROM CLARK HALEY, LICENSED FLORIDA YACHT BROKER",
}

W, H = 1200, 720
LOGO_W = 315           # +12.5% over original 280px size, per Clark
TITLE_PT = 110
TAGLINE_PT = 22
ACCENT_PT_W = 80
ACCENT_RGB = (33, 203, 234)  # brand cyan


def main() -> int:
    p = argparse.ArgumentParser()
    p.add_argument("--photo", default=DEFAULTS["photo"])
    p.add_argument("--logo",  default=DEFAULTS["logo"])
    p.add_argument("--out",   default=DEFAULTS["out"])
    p.add_argument("--title", default=DEFAULTS["title"])
    p.add_argument("--tagline", default=DEFAULTS["tagline"])
    args = p.parse_args()

    photo = Image.open(args.photo).convert("RGB")
    pw, ph = photo.size
    scale = max(W / pw, H / ph)
    photo = photo.resize((int(pw * scale), int(ph * scale)), Image.LANCZOS)
    nw, nh = photo.size
    photo = photo.crop(((nw - W) // 2, (nh - H) // 2, (nw + W) // 2, (nh + H) // 2))

    overlay = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    od = ImageDraw.Draw(overlay)
    for y in range(H):
        a = int(40 + (140 - 40) * ((y / H) ** 1.4))
        od.line([(0, y), (W, y)], fill=(8, 22, 40, a))
    canvas = Image.alpha_composite(photo.convert("RGBA"), overlay)

    logo = Image.open(args.logo).convert("RGBA")
    lw, lh = logo.size
    logo = logo.resize((LOGO_W, int(lh * LOGO_W / lw)), Image.LANCZOS)
    canvas.paste(logo, (40, 40), logo)

    draw = ImageDraw.Draw(canvas)
    title_font = ImageFont.truetype(
        "/System/Library/Fonts/Supplemental/Georgia Bold Italic.ttf", TITLE_PT
    )
    tag_font = ImageFont.truetype(
        "/System/Library/Fonts/Supplemental/Georgia.ttf", TAGLINE_PT
    )

    tbox = draw.textbbox((0, 0), args.title, font=title_font)
    tw, th = tbox[2] - tbox[0], tbox[3] - tbox[1]
    tx, ty = (W - tw) // 2, int(H * 0.58)
    draw.text((tx + 3, ty + 3), args.title, font=title_font, fill=(0, 0, 0, 120))
    draw.text((tx, ty), args.title, font=title_font, fill=(255, 255, 255, 255))

    gbox = draw.textbbox((0, 0), args.tagline, font=tag_font)
    gw = gbox[2] - gbox[0]
    gx, gy = (W - gw) // 2, ty + th + 50
    draw.text((gx, gy), args.tagline, font=tag_font, fill=(220, 230, 240, 255))

    line_y = gy + 40
    draw.line(
        [((W - ACCENT_PT_W) // 2, line_y),
         ((W + ACCENT_PT_W) // 2, line_y)],
        fill=ACCENT_RGB, width=3,
    )

    canvas.convert("RGB").save(args.out, "JPEG", quality=85, optimize=True)
    print(f"Wrote {args.out}  ({os.path.getsize(args.out):,} bytes)")
    return 0


if __name__ == "__main__":
    sys.exit(main())
