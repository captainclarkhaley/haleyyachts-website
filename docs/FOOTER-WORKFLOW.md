# Footer: single source of truth

The site footer (brand logo, social icons, copyright line) lives in ONE file and
is injected into every public page by a sync script. Edit one file, run one
command, and the change lands on all 24 public pages. No more page-by-page edits.

## The one source

`partials/footer.html` is the single source of truth. It contains the exact
`<footer class="site-footer">...</footer>` block that every page renders.

Asset paths in the partial are **root-absolute** (e.g.
`/images/brand/haleyyachtslogo.png`). Because the site is served from the domain
root on cPanel/Apache, a root-absolute path resolves the same way on a root page,
an `articles/<category>/` page, and a `yachts/` page. There is no per-depth path
juggling.

## How the injection works

Each target page has two marker comments where the footer goes:

```
    <!-- FOOTER:START -->
    ...the footer block from partials/footer.html...
    <!-- FOOTER:END -->
```

`scripts/sync-footer.sh` reads the partial and rewrites everything between those
markers on every target page. The script is idempotent (safe to run repeatedly)
and the rendered footer stays in the static HTML, so it is fully crawlable for
SEO and works with no JavaScript and no server config.

## To change the footer (the 3-step workflow)

1. **Edit** `partials/footer.html` (change a link, swap a social icon, update the
   copyright year, etc.).
2. **Run** the sync from the repo root:
   ```
   sh scripts/sync-footer.sh
   ```
   It prints which pages changed and ends with `done: N of 24 page(s) updated`.
3. **Commit and push**, then run the cPanel Git pull to deploy:
   ```
   git add -A && git commit -m "Footer: <what changed>" && git push
   ```
   Then in cPanel -> Git Version Control -> Pull (or Deploy).

## Verifying

- `sh scripts/sync-footer.sh --check` exits non-zero and lists any page whose
  footer has drifted from the partial. Use it before committing or in CI.
- After a sync, every public page between its markers is byte-for-byte the
  partial, indented to match the page.

## Scope (what the script touches)

In scope (24 pages): the 8 root pages, all 13 `articles/` pages including
`articles/_template.html` (so future cloned articles inherit the shared footer),
and the 2 `yachts/` listing pages.

Out of scope (left alone on purpose): `admin/`, `email-templates/`, and
`images/video/cta-card/cta-card-template.html`. These are not public site pages
and keep their own footers (or none). The page list is hard-coded near the top of
`scripts/sync-footer.sh`; when you add a new public page, add it to that list (new
articles cloned from `_template.html` already carry the markers, but still add
them to the list so `--check` covers them).
