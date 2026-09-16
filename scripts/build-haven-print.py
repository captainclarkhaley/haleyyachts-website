#!/usr/bin/env python3
"""Build print-ready artwork for the HAVEN | Palm Beach two-page spread.

The artwork itself lives in docs/design/haven-spread-clean.html and this
script NEVER modifies it. It derives a print-only copy into a build
directory and changes only things that cannot reach the printer as they
stand:

  - the three photographs are cropped from their originals and written
    into the build directory, so the output PDF carries real pixels
    instead of a link to one man's Dropbox
  - the hero scrim is baked into the hero image, because Chrome emits a
    CSS gradient as a 72 ppi raster tile and that is a banding risk on
    press; baking it also removes live transparency from the largest
    element on the spread
  - Open Sans is supplied from local static files, so a missing network
    cannot silently substitute Helvetica
  - the on-screen viewer script is removed, because the inline margins it
    writes on #stage leak into print and shift the artwork off the page

Geometry (crop windows, placed sizes) is READ OUT of the CSS, so the
artwork stays the single source of truth. Every derived number is printed
and the checks at the end fail loudly.

CMYK conversion is a separate, parameterised step. It does not run unless
--icc-profile is given, because the profile and the ink limit are the
printer's numbers and not ours to guess.

Usage:
    scripts/build-haven-print.py
    scripts/build-haven-print.py --marks
    scripts/build-haven-print.py --icc-profile /path/to.icc --ink-limit 300 --pdfx X-1a
"""

import argparse
import io
import os
import re
import shutil
import subprocess
import sys

import fitz  # PyMuPDF
import numpy as np
from PIL import Image, ImageCms

import cv2

Image.MAX_IMAGE_PIXELS = None

REPO = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC_HTML = os.path.join(REPO, "docs", "design", "haven-spread-clean.html")
FONT_DIR = os.path.join(REPO, "docs", "design", "fonts")
DEFAULT_OUT = os.path.join(REPO, "docs", "design", "build", "haven-spread-print.pdf")

CHROME = "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"

PT = 72.0                 # points per inch
BLEED_W, BLEED_H = 17.0, 11.125
TRIM_W, TRIM_H = 16.75, 10.875
BLEED = 0.125             # per outer edge
FOLD_FROM_TRIM_LEFT = 8.375
MARK_MARGIN = 0.25        # paper outside the bleed, used only for marks
MIN_PPI = 300.0

# Faces we supply locally. font-family / weight / style / file.
FONT_FACES = [
    ("Open Sans", 300, "normal", "open-sans-latin-300-normal.woff2"),
    ("Open Sans", 400, "normal", "open-sans-latin-400-normal.woff2"),
    ("Open Sans", 400, "italic", "open-sans-latin-400-italic.woff2"),
    ("Open Sans", 600, "normal", "open-sans-latin-600-normal.woff2"),
    ("Open Sans", 700, "normal", "open-sans-latin-700-normal.woff2"),
]

QR_URL = "https://haleyyachts.com/dock"


def fail(msg):
    print("FAIL: " + msg, file=sys.stderr)
    sys.exit(1)


# ---------------------------------------------------------------- CSS reading

def read_block(css, selector):
    m = re.search(re.escape(selector) + r"\s*\{([^}]*)\}", css)
    if not m:
        fail("could not find CSS block for %s in %s" % (selector, SRC_HTML))
    return m.group(1)


def inches(token):
    """'-3.926in' -> -3.926 ; '0' -> 0.0"""
    token = token.strip()
    if token in ("0", "0in"):
        return 0.0
    m = re.fullmatch(r"(-?[\d.]+)in", token)
    if not m:
        fail("expected an inch value, got %r" % token)
    return float(m.group(1))


def decl(block, prop):
    m = re.search(re.escape(prop) + r"\s*:\s*([^;]+);", block)
    if not m:
        fail("no %s declaration in block %r" % (prop, block[:60]))
    return m.group(1).strip()


def placement(css, selector, var_name):
    """Everything needed to crop one background image, read out of the CSS."""
    block = read_block(css, selector)
    box_w = inches(decl(block, "width"))
    box_h = inches(decl(block, "height"))
    size = decl(block, "background-size").split()
    pos = decl(block, "background-position").split()
    m = re.search(re.escape(var_name) + r'\s*:\s*url\("([^"]+)"\)', css)
    if not m:
        fail("no url for %s" % var_name)
    url = m.group(1)
    path = url[len("file://"):] if url.startswith("file://") else os.path.join(
        os.path.dirname(SRC_HTML), url)
    from urllib.parse import unquote
    return {
        "selector": selector,
        "box_w": box_w, "box_h": box_h,
        "img_w": inches(size[0]), "img_h": inches(size[1]),
        "pos_x": inches(pos[0]), "pos_y": inches(pos[1]),
        "path": unquote(path),
    }


def gradient_stops(css):
    """The hero scrim, read out of the CSS. Returns (height_in, [(pos, rgba)])."""
    block = read_block(css, ".hero-scrim")
    height = inches(decl(block, "height"))
    bg = decl(block, "background")
    if "to top" not in bg:
        fail("hero scrim is no longer a 'to top' gradient; the bake step "
             "assumes it is. Re-read the CSS before trusting this build.")
    stops = re.findall(
        r"rgba\((\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*([\d.]+)\)\s*(\d+)%", bg)
    if len(stops) < 2:
        fail("could not parse the hero scrim gradient stops")
    rgbs = {(int(r), int(g), int(b)) for r, g, b, _, _ in stops}
    if len(rgbs) != 1:
        fail("hero scrim uses more than one colour; the bake step assumes one")
    colour = rgbs.pop()
    ramp = [(int(p) / 100.0, float(a)) for _, _, _, a, p in stops]
    return height, colour, sorted(ramp)


# --------------------------------------------------------------- image assets

def srgb_to(profile_bytes, rgb):
    """Move an sRGB triple into the image's own working space."""
    if not profile_bytes:
        return rgb
    src = ImageCms.createProfile("sRGB")
    dst = ImageCms.ImageCmsProfile(io.BytesIO(profile_bytes))
    swatch = Image.new("RGB", (1, 1), rgb)
    out = ImageCms.profileToProfile(swatch, src, dst, outputMode="RGB")
    return out.getpixel((0, 0))


def prepare_image(p, out_path, max_ppi, scrim=None):
    """Crop to exactly what the layout shows, resample, keep the ICC profile."""
    im = Image.open(p["path"])
    if im.mode != "RGB":
        im = im.convert("RGB")
    icc = im.info.get("icc_profile")

    scale = im.width / p["img_w"]                  # source px per inch of layout
    x0 = int(round(-p["pos_x"] * scale))
    y0 = int(round(-p["pos_y"] * scale))
    cw = int(round(p["box_w"] * scale))
    ch = int(round(p["box_h"] * scale))

    if x0 < 0 or y0 < 0 or x0 + cw > im.width + 1 or y0 + ch > im.height + 1:
        fail("crop window %s falls outside %s (%dx%d)"
             % ((x0, y0, cw, ch), os.path.basename(p["path"]), im.width, im.height))
    cw = min(cw, im.width - x0)
    ch = min(ch, im.height - y0)

    want = p["box_w"] / p["box_h"]
    got = cw / ch
    if abs(want - got) / want > 0.005:
        fail("crop aspect %.4f does not match the printed box %.4f for %s"
             % (got, want, p["selector"]))

    crop = im.crop((x0, y0, x0 + cw, y0 + ch))
    native_ppi = cw / p["box_w"]

    if scrim is not None:
        crop = bake_scrim(crop, p, scrim, icc)

    out_ppi = native_ppi
    if native_ppi > max_ppi:
        out_ppi = max_ppi
        tw = int(round(p["box_w"] * out_ppi))
        th = int(round(p["box_h"] * out_ppi))
        crop = crop.resize((tw, th), Image.LANCZOS)

    crop.save(out_path, "JPEG", quality=95, subsampling=0, optimize=True,
              icc_profile=icc)

    return {
        "selector": p["selector"],
        "source": os.path.basename(p["path"]),
        "source_px": (im.width, im.height),
        "crop": (x0, y0, x0 + cw, y0 + ch),
        "crop_px": (cw, ch),
        "printed_in": (p["box_w"], p["box_h"]),
        "native_ppi": native_ppi,
        "written_px": crop.size,
        "written_ppi": out_ppi,
        "icc": "Adobe RGB (1998)" if icc else "untagged (sRGB assumed)",
        "out": out_path,
    }


def bake_scrim(crop, p, scrim, icc):
    """Composite the CSS scrim onto the hero at full resolution."""
    height_in, colour, ramp = scrim
    navy = srgb_to(icc, colour)

    px_per_in = crop.height / p["box_h"]
    band = int(round(height_in * px_per_in))
    band = min(band, crop.height)

    # y measured 0..1 from the BOTTOM of the band, matching 'to top'
    ys = (np.arange(band, dtype=np.float64)[::-1] + 0.5) / band
    pos = np.array([s[0] for s in ramp])
    alp = np.array([s[1] for s in ramp])
    alpha = np.interp(ys, pos, alp)[:, None, None]

    arr = np.asarray(crop, dtype=np.float64)
    tail = arr[crop.height - band:, :, :]
    navy_arr = np.array(navy, dtype=np.float64)[None, None, :]
    arr[crop.height - band:, :, :] = tail * (1.0 - alpha) + navy_arr * alpha
    return Image.fromarray(np.clip(arr + 0.5, 0, 255).astype(np.uint8), "RGB")


# ------------------------------------------------------------- derived markup

def derive_html(css_paths, build_dir, marks):
    with open(SRC_HTML, encoding="utf-8") as fh:
        html = fh.read()

    # the viewer script writes inline margins on #stage that survive into print
    html = re.sub(r"<script>.*?</script>", "", html, flags=re.S)
    # no network fonts: a missing CDN is a silent Helvetica on press
    html = re.sub(r'\s*<link rel="preconnect"[^>]*>', "", html)
    html = re.sub(r'\s*<link href="https://fonts\.googleapis\.com[^>]*>', "", html)

    # relative asset urls, resolved against the original file's directory
    html = html.replace('url("../../', 'url("file://' + REPO + "/")
    html = html.replace('url("haleyyachts-dock-qr.svg")',
                        'url("file://%s/docs/design/haleyyachts-dock-qr.svg")' % REPO)

    faces = "\n".join(
        "@font-face{font-family:'%s';font-style:%s;font-weight:%d;"
        'src:url("file://%s/%s") format("woff2");font-display:block}'
        % (fam, style, weight, FONT_DIR, fname)
        for fam, weight, style, fname in FONT_FACES)

    swaps = "\n".join(
        "%s{background-image:url(\"file://%s\") !important;"
        "background-size:100%% 100%% !important;"
        "background-position:0 0 !important}" % (sel, path)
        for sel, path in css_paths.items())

    pad = MARK_MARGIN if marks else 0.0
    overlay = """
<style>
/* ---- injected by scripts/build-haven-print.py. Build only. ---- */
%s
%s
.hero-scrim{display:none !important}   /* baked into the hero image */
@page{size:%.4fin %.4fin;margin:0}
@media print{
  body{padding:%.4fin !important;background:#fff}
  #stage{margin:0 !important;transform:none !important;width:auto !important;height:auto !important}
}
</style>
</head>""" % (faces, swaps,
              BLEED_W + 2 * pad, BLEED_H + 2 * pad, pad)

    html = html.replace("</head>", overlay, 1)
    out = os.path.join(build_dir, "haven-spread-print.html")
    with open(out, "w", encoding="utf-8") as fh:
        fh.write(html)
    return out


def render(html_path, pdf_path):
    cmd = [CHROME, "--headless=new", "--disable-gpu", "--no-sandbox",
           "--hide-scrollbars", "--allow-file-access-from-files",
           "--force-color-profile=srgb",
           "--virtual-time-budget=30000", "--no-pdf-header-footer",
           "--print-to-pdf=" + pdf_path, "file://" + html_path]
    r = subprocess.run(cmd, capture_output=True, text=True)
    if not os.path.exists(pdf_path):
        fail("Chrome produced no PDF\n" + r.stderr[-2000:])


# ------------------------------------------------------------------ pdf boxes

def box(page, key, rect):
    page.parent.xref_set_key(page.xref, key, "[ %g %g %g %g ]" % rect)


def finish(raw_pdf, out_pdf, marks):
    """Place the artwork on an exactly sized page and set the boxes."""
    src = fitz.open(raw_pdf)
    if len(src) != 1:
        fail("Chrome rendered %d pages, expected 1" % len(src))

    pad = MARK_MARGIN if marks else 0.0
    # Chrome lays out from the top-left of the sheet, so the artwork's bleed
    # rectangle starts at exactly (MARK_MARGIN, MARK_MARGIN) in fitz coords.
    clip = fitz.Rect(pad * PT, pad * PT,
                     (pad + BLEED_W) * PT, (pad + BLEED_H) * PT)

    page_w = (BLEED_W + 2 * pad) * PT
    page_h = (BLEED_H + 2 * pad) * PT
    doc = fitz.open()
    page = doc.new_page(width=page_w, height=page_h)
    page.show_pdf_page(fitz.Rect(pad * PT, pad * PT,
                                 (pad + BLEED_W) * PT, (pad + BLEED_H) * PT),
                       src, 0, clip=clip)

    bleed_rect = (pad * PT, pad * PT, (pad + BLEED_W) * PT, (pad + BLEED_H) * PT)
    trim_rect = (bleed_rect[0] + BLEED * PT, bleed_rect[1] + BLEED * PT,
                 bleed_rect[2] - BLEED * PT, bleed_rect[3] - BLEED * PT)

    if marks:
        draw_marks(page, trim_rect, bleed_rect)

    box(page, "BleedBox", bleed_rect)
    box(page, "TrimBox", trim_rect)
    box(page, "ArtBox", trim_rect)
    box(page, "CropBox", (0, 0, page_w, page_h))

    doc.set_metadata({
        "title": "Haley Yachts, HAVEN Palm Beach two-page spread",
        "author": "Haley Yachts",
        "creator": "scripts/build-haven-print.py",
        "producer": "PyMuPDF over headless Chrome",
        "subject": "17 x 11.125 in bleed, 16.75 x 10.875 in trim, fold at 8.375 in",
    })
    os.makedirs(os.path.dirname(out_pdf), exist_ok=True)
    doc.save(out_pdf, deflate=True, garbage=3)
    doc.close()
    src.close()


def draw_marks(page, trim, bleed):
    """Offset trim marks in the corners, plus a fold mark top and bottom."""
    off, ln = BLEED * PT, 0.1875 * PT
    black = (0, 0, 0)
    x0, y0, x1, y1 = trim
    for x in (x0, x1):
        for y in (y0, y1):
            dx = -1 if x == x0 else 1
            dy = -1 if y == y0 else 1
            page.draw_line((x + dx * off, y), (x + dx * (off + ln), y),
                           color=black, width=0.25)
            page.draw_line((x, y + dy * off), (x, y + dy * (off + ln)),
                           color=black, width=0.25)
    fold_x = x0 + FOLD_FROM_TRIM_LEFT * PT
    page.draw_line((fold_x, bleed[1]), (fold_x, bleed[1] - MARK_MARGIN * PT),
                   color=black, width=0.25, dashes="[2 2] 0")
    page.draw_line((fold_x, bleed[3]), (fold_x, bleed[3] + MARK_MARGIN * PT),
                   color=black, width=0.25, dashes="[2 2] 0")


# -------------------------------------------------------------------- checking

def check(pdf_path, marks, expect_cmyk=False):
    doc = fitz.open(pdf_path)
    page = doc[0]
    pad = MARK_MARGIN if marks else 0.0
    problems = []

    def near(a, b, tol=0.02):
        return abs(a - b) <= tol

    print("\n--- geometry -------------------------------------------------")
    boxes = {}
    for key in ("MediaBox", "CropBox", "BleedBox", "TrimBox"):
        v = doc.xref_get_key(page.xref, key)
        boxes[key] = v[1]
        print("  %-9s %s" % (key, v[1]))
    want_trim = ((pad + BLEED) * PT, (pad + BLEED) * PT,
                 (pad + BLEED_W - BLEED) * PT, (pad + BLEED_H - BLEED) * PT)
    for key, want in (("TrimBox", want_trim),
                      ("BleedBox", (pad * PT, pad * PT,
                                    (pad + BLEED_W) * PT, (pad + BLEED_H) * PT))):
        got = [float(v) for v in re.findall(r"-?[\d.]+", boxes[key])]
        if len(got) != 4 or any(abs(a - b) > 0.01 for a, b in zip(got, want)):
            problems.append("%s is %s, expected %s" % (key, boxes[key], list(want)))
    mb = page.mediabox
    if not (near(mb.width / PT, BLEED_W + 2 * pad) and
            near(mb.height / PT, BLEED_H + 2 * pad)):
        problems.append("MediaBox is %.4f x %.4f in" % (mb.width / PT, mb.height / PT))
    print("  page       %.4f x %.4f in   trim %.3f x %.3f in   fold at %.3f in"
          % (mb.width / PT, mb.height / PT, TRIM_W, TRIM_H, FOLD_FROM_TRIM_LEFT))

    print("\n--- fonts ----------------------------------------------------")
    fonts = page.get_fonts(full=True)
    if not fonts:
        problems.append("no fonts in the output at all")
    for xref, ext, ftype, basefont, name, enc in (f[:6] for f in fonts):
        embedded = ext not in ("n/a", "") or ftype == "Type3"
        how = ftype if ftype != "Type3" else "Type3 (vector outlines)"
        print("  %-28s %-24s %s" % (basefont or "(outlined)", how,
                                    "embedded" if embedded else "NOT EMBEDDED"))
        if not embedded:
            problems.append("font %s is not embedded" % basefont)
        if basefont and "OpenSans" not in basefont.replace(" ", ""):
            problems.append("unexpected font in the output: %s" % basefont)
        if ftype == "Type3":
            problems.append(
                "font %s came through as Type3 outlines, which means Chrome "
                "could not embed the source face" % (name or xref))

    print("\n--- images ---------------------------------------------------")
    infos = page.get_image_info(xrefs=True)
    seen = 0
    for i in infos:
        if i["width"] < 8 or i["height"] < 8:
            continue
        seen += 1
        w_in = abs(i["transform"][0]) / PT
        h_in = abs(i["transform"][3]) / PT
        ppi_x = i["width"] / w_in if w_in else 0
        ppi_y = i["height"] / h_in if h_in else 0
        print("  %5dx%-5d px  placed %6.3f x %6.3f in  ->  %6.1f x %6.1f ppi   %s"
              % (i["width"], i["height"], w_in, h_in, ppi_x, ppi_y, i["cs-name"]))
        if min(ppi_x, ppi_y) < MIN_PPI:
            problems.append("an image lands at %.0f ppi, under the %.0f ppi floor"
                            % (min(ppi_x, ppi_y), MIN_PPI))
        if expect_cmyk and "CMYK" not in i["cs-name"].upper():
            problems.append("image still in %s after conversion" % i["cs-name"])
    if seen == 0:
        problems.append("no images found in the output; the photography did "
                        "not make it into the file")

    print("\n--- copy -----------------------------------------------------")
    text = page.get_text("text")
    for line in text.splitlines():
        if "delivery" in line or "availability" in line.lower():
            print("  availability row: %r" % line.strip())
    stray = re.findall(r"\[[^\]\n]{3,}\]", text)
    print("  %d characters of live text, %d bracketed placeholders"
          % (len(text), len(stray)))
    for s in stray:
        problems.append("placeholder still in the artwork: %s" % s)
    if len(text) < 1500:
        problems.append("only %d characters of live text; the type has been "
                        "rasterised" % len(text))

    print("\n--- QR -------------------------------------------------------")
    qr = fitz.Rect((pad + BLEED + 14.25 - 0.06) * PT, (pad + BLEED + 9.8 - 0.06) * PT,
                   (pad + BLEED + 15.0 + 0.06) * PT, (pad + BLEED + 10.55 + 0.06) * PT)
    decoded = None
    for dpi in (600, 1200, 300):
        pix = page.get_pixmap(dpi=dpi, clip=qr, colorspace=fitz.csGRAY)
        img = np.frombuffer(pix.samples, dtype=np.uint8).reshape(pix.height, pix.width)
        val, _, _ = cv2.QRCodeDetector().detectAndDecode(img)
        print("  rasterised at %4d dpi (%dx%d px) -> %r" % (dpi, pix.width, pix.height, val))
        if val:
            decoded = val
            break
    if decoded != QR_URL:
        problems.append("QR decoded to %r, expected %r" % (decoded, QR_URL))

    print("\n--- registration and bleed -----------------------------------")
    dpi = 300
    pm = page.get_pixmap(dpi=dpi, colorspace=fitz.csRGB)
    arr = np.frombuffer(pm.samples, dtype=np.uint8).reshape(pm.height, pm.stride // 3, 3)
    arr = arr[:, :pm.width, :]

    def at(x_in, y_in):
        return tuple(int(v) for v in arr[int((pad + y_in) * dpi), int((pad + x_in) * dpi)])

    # after conversion the brand colours move on purpose, so the colour
    # identification loosens while the EDGE POSITIONS stay exact
    slack = 70 if expect_cmyk else 20

    def close(c, want, tol=14):
        return all(abs(a - b) <= tol for a, b in zip(c, want))

    paper = (253, 253, 251)
    cyan = (33, 203, 234)
    navy = (10, 22, 40)

    checks = [
        ("top-left bleed carries the hero", at(0.02, 0.02), lambda c: not close(c, paper)),
        ("right bleed carries the navy panel", at(16.98, 6.5), lambda c: close(c, navy, slack)),
        ("bottom bleed carries the cyan rule", at(8.5, 11.10), lambda c: close(c, cyan, slack)),
        ("left bleed carries the hero", at(0.02, 5.0), lambda c: not close(c, paper)),
    ]
    for label, colour, ok in checks:
        good = ok(colour)
        print("  %-38s %-16s %s" % (label, colour, "ok" if good else "WRONG"))
        if not good:
            problems.append(label + " -> got " + str(colour))

    row = arr[int((pad + 10.85) * dpi), :, :]
    edge = None
    for x in range(int((pad + 7.5) * dpi), int((pad + 9.5) * dpi)):
        if close(tuple(int(v) for v in row[x]), paper):
            edge = (x / dpi) - pad
            break
    print("  hero / recto boundary at %s in (want %.3f)"
          % ("%.3f" % edge if edge else "not found", BLEED + FOLD_FROM_TRIM_LEFT))
    if edge is None or not near(edge, BLEED + FOLD_FROM_TRIM_LEFT, 0.012):
        problems.append("hero/recto boundary is at %s, not %.3f in from the bleed edge"
                        % (edge, BLEED + FOLD_FROM_TRIM_LEFT))

    col = arr[:, int((pad + 16.9) * dpi), :]
    top = None
    for y in range(int((pad + 9.0) * dpi), int((pad + BLEED_H) * dpi)):
        if close(tuple(int(v) for v in col[y]), cyan, slack):
            top = (y / dpi) - pad
            break
    want_rule = 10.785 + BLEED
    print("  cyan rule top edge at %s in (want %.3f)"
          % ("%.3f" % top if top else "not found", want_rule))
    if top is None or not near(top, want_rule, 0.012):
        problems.append("cyan rule top edge is at %s, not %.3f in" % (top, want_rule))

    doc.close()
    return problems


# ------------------------------------------------------------------ CMYK step

def live_transparency(pdf_path):
    """Any ExtGState with constant alpha below 1. Ghostscript answers one of
    these by flattening the WHOLE page to a raster, which turns 60pt vector
    headline type into pixels. PDF/X-1a forbids it outright."""
    doc = fitz.open(pdf_path)
    found = []
    for x in range(1, doc.xref_length()):
        try:
            obj = doc.xref_object(x)
        except Exception:
            continue
        for key in ("ca", "CA"):
            m = re.search(r"/%s\s+([\d.]+)" % key, obj)
            if m and float(m.group(1)) < 1.0:
                found.append((x, key, float(m.group(1))))
    doc.close()
    return found


def convert_cmyk(src_pdf, out_pdf, profile):
    """Deferred step. Runs only when the printer's profile is supplied.

    Deliberately NOT -dPDFX=true: Ghostscript 10.08 answers that flag by
    rasterising the entire page. The PDF/X identification is stamped on
    afterwards instead, which leaves the type vector.
    """
    cmd = ["gs", "-dBATCH", "-dNOPAUSE", "-dSAFER", "-dNOOUTERSAVE",
           "--permit-file-read=" + src_pdf,
           "--permit-file-read=" + profile,
           "-sDEVICE=pdfwrite", "-dPDFSETTINGS=/prepress",
           "-dColorConversionStrategy=/CMYK",
           "-sColorConversionStrategyForImages=/CMYK",
           "-sOutputICCProfile=" + profile,
           "-dAutoFilterColorImages=false", "-sColorImageFilter=DCTEncode",
           "-dColorImageResolution=600", "-dDownsampleColorImages=false",
           "-dAutoFilterGrayImages=false", "-dDownsampleGrayImages=false",
           "-dSubsetFonts=true", "-dEmbedAllFonts=true",
           "-dPreserveTrMode=false",
           "-sOutputFile=" + out_pdf, src_pdf]
    r = subprocess.run(cmd, capture_output=True, text=True)
    if r.returncode != 0 or not os.path.exists(out_pdf):
        fail("Ghostscript conversion failed:\n" + (r.stderr or r.stdout)[-3000:])
    return r.stdout


def stamp_pdfx(pdf_path, profile, pdfx, condition):
    """OutputIntent plus the PDF/X version key. Boxes are set already."""
    doc = fitz.open(pdf_path)
    with open(profile, "rb") as fh:
        icc = fh.read()
    icc_xref = doc.get_new_xref()
    doc.update_object(icc_xref, "<< /N 4 >>")
    doc.update_stream(icc_xref, icc, compress=True)

    oi = doc.get_new_xref()
    doc.update_object(oi,
                      "<< /Type /OutputIntent /S /GTS_PDFX "
                      "/OutputConditionIdentifier (%s) /Info (%s) "
                      "/RegistryName (http://www.color.org) "
                      "/DestOutputProfile %d 0 R >>" % (condition, condition, icc_xref))
    root = doc.pdf_catalog()
    doc.xref_set_key(root, "OutputIntents", "[ %d 0 R ]" % oi)

    info = doc.xref_get_key(-1, "Info")
    if info[0] == "xref":
        doc.xref_set_key(int(info[1].split()[0]), "GTS_PDFXVersion",
                         "(PDF/%s)" % pdfx)
    tmp = pdf_path + ".stamped"
    doc.save(tmp, deflate=True)
    doc.close()
    os.replace(tmp, pdf_path)


def measure_tac(pdf_path, limit, dpi=100):
    """Total area coverage, measured on a CMYK raster of the converted file."""
    tif = pdf_path + ".tac.tif"
    cmd = ["gs", "-dBATCH", "-dNOPAUSE", "-dSAFER",
           "--permit-file-read=" + pdf_path, "-sDEVICE=tiff32nc",
           "-r%d" % dpi, "-sOutputFile=" + tif, pdf_path]
    r = subprocess.run(cmd, capture_output=True, text=True)
    if r.returncode != 0:
        fail("could not rasterise for ink coverage:\n" + r.stderr[-2000:])
    im = Image.open(tif)
    a = np.asarray(im, dtype=np.uint16)
    tac = a.sum(axis=2) / 255.0 * 100.0
    peak = float(tac.max())
    over = float((tac > limit).mean() * 100.0) if limit else 0.0
    os.unlink(tif)
    print("  peak total ink %.1f%%   area over %.0f%%: %.3f%% of the sheet"
          % (peak, limit or 0, over))
    return peak, over


# ------------------------------------------------------------------------ main

def main():
    ap = argparse.ArgumentParser(description=__doc__,
                                 formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--out", default=DEFAULT_OUT)
    ap.add_argument("--max-ppi", type=float, default=600.0,
                    help="cap on effective resolution; images are never upsampled")
    ap.add_argument("--marks", action="store_true",
                    help="add 0.25in of paper outside the bleed and draw offset "
                         "trim marks plus a fold mark")
    ap.add_argument("--icc-profile", help="destination CMYK profile. Without it "
                                          "the build stops at RGB, on purpose.")
    ap.add_argument("--ink-limit", type=float,
                    help="total area coverage the printer allows, checked after "
                         "conversion")
    ap.add_argument("--pdfx", default="X-1a:2001", help="PDF/X flavour string")
    ap.add_argument("--keep-build", action="store_true")
    args = ap.parse_args()

    if not os.path.exists(CHROME):
        fail("Google Chrome not found at " + CHROME)
    for _, _, _, fname in FONT_FACES:
        if not os.path.exists(os.path.join(FONT_DIR, fname)):
            fail("missing font %s in %s" % (fname, FONT_DIR))

    with open(SRC_HTML, encoding="utf-8") as fh:
        css = fh.read()

    build_dir = os.path.join(os.path.dirname(os.path.abspath(args.out)), "work")
    os.makedirs(build_dir, exist_ok=True)

    print("=== HAVEN print build ===")
    print("source     %s" % SRC_HTML)
    print("output     %s" % args.out)
    print("marks      %s" % ("yes" if args.marks else "no"))

    specs = [
        (".hero", "--img-hero", "hero.jpg", True),
        (".panel-photo", "--img-shot2", "panel.jpg", False),
        (".portrait", "--img-portrait", "portrait.jpg", False),
    ]
    scrim = gradient_stops(css)
    print("\n--- photography ----------------------------------------------")
    reports, swaps = [], {}
    for sel, var, name, is_hero in specs:
        p = placement(css, sel, var)
        if not os.path.exists(p["path"]):
            fail("source photograph missing: " + p["path"])
        r = prepare_image(p, os.path.join(build_dir, name), args.max_ppi,
                          scrim=scrim if is_hero else None)
        reports.append(r)
        swaps[sel] = r["out"]
        print("  %-13s %s" % (r["selector"], r["source"]))
        print("     source %dx%d px, %s" % (r["source_px"][0], r["source_px"][1], r["icc"]))
        print("     crop   X %d-%d, Y %d-%d  = %dx%d px"
              % (r["crop"][0], r["crop"][2], r["crop"][1], r["crop"][3],
                 r["crop_px"][0], r["crop_px"][1]))
        print("     placed %.3f x %.3f in  ->  %.1f ppi native, %.1f ppi written (%dx%d px)"
              % (r["printed_in"][0], r["printed_in"][1], r["native_ppi"],
                 r["written_ppi"], r["written_px"][0], r["written_px"][1]))
        if r["native_ppi"] < MIN_PPI:
            fail("%s is only %.0f ppi at printed size" % (sel, r["native_ppi"]))

    html = derive_html(swaps, build_dir, args.marks)
    raw = os.path.join(build_dir, "chrome.pdf")
    render(html, raw)
    finish(raw, args.out, args.marks)
    print("\nwrote %s (%.1f MB)" % (args.out, os.path.getsize(args.out) / 1e6))

    problems = check(args.out, args.marks)

    if args.icc_profile:
        if not os.path.exists(args.icc_profile):
            fail("no such ICC profile: " + args.icc_profile)
        cmyk_out = re.sub(r"\.pdf$", "-cmyk.pdf", args.out)
        print("\n--- CMYK conversion ------------------------------------------")
        print("  profile   %s" % args.icc_profile)
        print("  flavour   PDF/%s" % args.pdfx)
        alpha = live_transparency(args.out)
        if alpha:
            for xref, key, val in alpha:
                print("  live transparency: object %d has /%s %.3f" % (xref, key, val))
            fail("the artwork still carries live transparency, and Ghostscript "
                 "answers that by rasterising the whole spread. The 70%% white "
                 "captions are the source. Either the job is PDF/X-4, which "
                 "allows transparency, or those three text runs are set to "
                 "their opaque equivalents first. That is a colour edit and it "
                 "needs sign-off, so this script will not make it.")
        convert_cmyk(args.out, cmyk_out, args.icc_profile)
        stamp_pdfx(cmyk_out, args.icc_profile, args.pdfx,
                   os.path.splitext(os.path.basename(args.icc_profile))[0])
        print("  wrote %s (%.1f MB)" % (cmyk_out, os.path.getsize(cmyk_out) / 1e6))
        if args.ink_limit:
            peak, over = measure_tac(cmyk_out, args.ink_limit)
            if peak > args.ink_limit + 0.5:
                problems.append("peak total ink %.1f%% exceeds the %.0f%% limit"
                                % (peak, args.ink_limit))
        problems += check(cmyk_out, args.marks, expect_cmyk=True)
    else:
        print("\n--- CMYK conversion ------------------------------------------")
        print("  SKIPPED. No --icc-profile given, so the file stays RGB.")
        print("  This is deliberate: the destination profile and the ink limit")
        print("  are the printer's numbers. Supply them and re-run.")

    if not args.keep_build:
        shutil.rmtree(build_dir, ignore_errors=True)

    print("\n=== result ===")
    if problems:
        for p in problems:
            print("  FAIL  " + p)
        sys.exit(1)
    print("  all checks passed")


if __name__ == "__main__":
    main()
