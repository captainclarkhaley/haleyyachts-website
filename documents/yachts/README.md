# Yacht brochures - one canonical document per boat

Established 2026-07-21 (Clark). This folder is the single home for a boat's
spec sheet / brochure PDF.

## The rule

**One boat, one document, one stable path.**

    documents/yachts/<year>-<builder>-<model>-<boat-name>.pdf

All lowercase, hyphens only, no spaces, no parentheses, no underscores. The boat
name goes last when she has one:

    1991-southern-wind-72-fortunato.pdf
    2020-riviera-545-suv-fringe-benefits.pdf
    2024-sunseeker-manhattan-68.pdf          <- no boat name, that is fine

Everything that needs the brochure points at that one path: the featured card in
`js/featured-yachts.js`, the detail page under `yachts/`, and the Logbook.

## Why it is not under `featured/`

These used to live in `documents/yachts/featured/`, which quietly implied the
Featured Yacht editor owned their lifecycle. It did: dropping a boat from the
featured list deleted her brochure. But a brochure is linked from the Logbook,
and the Logbook issues are already in people's inboxes and cannot be revised.

That is not hypothetical. Sangaris sold, left the featured list, and her spec
sheet was deleted in commit `3e968ce` (2026-06-08), which broke the link in the
already-sent May 2026 Logbook. Nobody noticed for six weeks.

So: **a document outlives the featured slot.** The Featured Yacht editor no
longer auto-deletes PDFs at all (see `admin/featured-yachts.html`), and the path
no longer says "featured".

## Adding a brochure

Upload through the Featured Yacht editor and it lands here automatically. If you
add one by hand, name it per the rule above and point the card at it.

Rename an existing brochure only if you also add a `RedirectPermanent` for the
old path in the site's root `.htaccess`. Past newsletters cannot be edited, so a
brochure's published URL has to keep resolving forever.

## `archive/`

Superseded revisions and secondary documents. Nothing links here, nothing is
served from here on purpose - it exists so an older revision is recoverable
without digging through git history. Current contents:

| File | What it is |
|---|---|
| `1991-southern-wind-72-fortunato--rev-2026-05.pdf` | The revision the May 2026 Logbook linked. Superseded; that URL now redirects to the current brochure. |
| `1991-southern-wind-72-fortunato--rev-earlier-30pp.pdf` | An earlier 30-page revision. Never linked. |
| `1997-offshore-48-island-girl--1p-spec-sheet.pdf` | A one-page Haley-branded summary, distinct from the full 10-page builder brochure. Never linked. |

## Related

- Price changes inside a brochure: `scripts/patch-pdf-price.py`
- Legacy path redirects: section 3b of the root `.htaccess`
