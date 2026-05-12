# Haley Yachts brand assets

Authoritative map of every file in this folder. Filenames are kept as-is
because they are referenced by 50+ HTML files, the admin tools, and the
newsletter bake script - renaming would mean a multi-file rewrite for a
non-public reorg. Use this file to figure out which asset to grab.

## Artwork families

- **Primary horizontal lockup** - the standalone swoosh (no square frame)
  plus a "HALEY YACHTS" wordmark. This is the canonical site-header and
  footer mark. Exists only as 970x174 raster - **no vector source in the
  repo**. If we need a larger or vector version, that's a Patrick / brand
  task, not something Terry can synthesize from what we have.

- **Favicon-square horizontal lockup** - rounded blue square containing
  an inset swoosh, paired with a "HALEY YACHTS" wordmark. This is the
  version with a vector master under `favicon_logo/`. Used in the
  newsletter masthead.

- **Favicon-only square mark** - just the rounded-square monogram (no
  wordmark) at various sizes plus an SVG. Used for browser favicons and
  app icons.

- **Archive** - deprecated logo / monogram concepts kept for history.
  Do not use on the live site.

## Files

| Filename | Dimensions | Family | Variant | Intended use |
|---|---|---|---|---|
| `haleyyachtslogo.png` | 970x174 | Primary | Color (navy + brand blue) | Site header, footer on light, article schema logo |
| `haleyyachtslogo-reverse.png` | 970x174 | Primary | "Reverse" - white outline wordmark + color swoosh | Currently unused on the live site; kept as the outline-style variant |
| `haleyyachtslogo-footer.png` | 970x174 | Primary | All-white | Drop on dark backgrounds where pure white is required (newsletter footer) |
| `haleyyachtslogo-reverse@2x.png` | 1940x348 | Favicon-square | White HALEY + brand-blue YACHTS, rounded-square swoosh | Source for the newsletter masthead (`email-templates/bake-masthead.py`). Misnamed - it is NOT a 2x of `haleyyachtslogo-reverse.png`, it is a different artwork family. Do not swap them. |
| `favicon.svg` | 512x512 viewBox | Favicon-monogram | Color | Modern browsers (`<link rel="icon" type="image/svg+xml">`) |
| `favicon-16.png` | 16x16 | Favicon-monogram | Color | Legacy browser tab |
| `favicon-32.png` | 32x32 | Favicon-monogram | Color | Browser tab |
| `favicon-48.png` | 48x48 | Favicon-monogram | Color | Windows site tile |
| `favicon-180.png` | 180x180 | Favicon-monogram | Color | iOS home-screen `apple-touch-icon` |
| `favicon-192.png` | 192x192 | Favicon-monogram | Color | Android home-screen / PWA manifest |
| `favicon-512.png` | 512x512 | Favicon-monogram | Color | PWA manifest, high-res share previews |
| `favicon_logo/HALEY YACHTS.ai` | vector | Favicon-square | Color | Adobe Illustrator master for the square-lockup family |
| `favicon_logo/HALEY YACHTS.pdf` | vector | Favicon-square | Color | Print-ready vector |
| `favicon_logo/HALEY YACHTS.eps` (CMYK) | vector | Favicon-square | Color (CMYK) | Print vendors that want CMYK EPS |
| `favicon_logo/HALEY YACHTS RGB.eps` | vector | Favicon-square | Color (RGB) | Screen-targeted EPS |
| `favicon_logo/HALEY YACHTS.png` | 4165x4165 | Favicon-square | Color | High-res raster export of the square lockup |
| `favicon_logo/HALEY YACHTS.jpg` | 4165x4165 | Favicon-square | Color (no transparency) | JPG fallback for vendors that reject PNG |
| `monogram-f.svg` | 260x260 | Archive (deprecated) | Color | Old "HY in compass rose" concept - do not use |
| `monogram-f-light.svg` | 260x260 | Archive (deprecated) | Light-on-dark | Old "HY in compass rose" concept - do not use |
| `archive/logo-concept-1.svg` | 400x80 | Archive | Color | Early hull-silhouette wordmark concept |
| `archive/logo-concept-2.svg` | 400x80 | Archive | Color | Early wave-mark wordmark concept |
| `archive/logo-concept-3.svg` | 420x80 | Archive | Color | Early geometric-bow wordmark concept |
| `archive/monogram-a.svg` | 200x200 | Archive | Color | HY-interlocking concept |
| `archive/monogram-b.svg` | 200x200 | Archive | Color | HY-in-circle concept |
| `archive/monogram-c.svg` | 200x200 | Archive | Color | HY split-color concept |
| `archive/monogram-d.svg` | 240x200 | Archive | Color | Serif HY overlap concept |
| `archive/monogram-e.svg` | 220x220 | Archive | Color | Serif HY in square frame concept |

## Pickers (TL;DR)

- Need the site-header logo? `haleyyachtslogo.png`
- Need a white logo for dark backgrounds (email footer, dark hero)? `haleyyachtslogo-footer.png`
- Need a print/vector logo for a vendor? `favicon_logo/HALEY YACHTS.pdf` (or `.ai` / `.eps`)
- Need a favicon at a specific size? `favicon-16/32/48/180/192/512.png` or the SVG
- Need the newsletter masthead source? Already wired into `email-templates/bake-masthead.py`; do not change it without re-running the bake.

## Known gap

There is no vector master for the **Primary horizontal lockup** (the
standalone-swoosh wordmark used everywhere on the site). All three
primary files are 970x174 raster. If we ever need to enlarge it past
its current footprint or re-color cleanly, we will need Patrick to get
a vector from the designer.
