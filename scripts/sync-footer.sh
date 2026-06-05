#!/bin/sh
# sync-footer.sh - Single-source-of-truth footer injector for Haley Yachts.
#
# WHAT IT DOES
#   Reads partials/footer.html (the ONE source of truth for the site footer)
#   and writes its contents into every public page between the marker lines:
#       <!-- FOOTER:START -->
#       ...partial contents...
#       <!-- FOOTER:END -->
#
#   First run: any page that still has a raw <footer class="site-footer">...</footer>
#   block (no markers yet) is upgraded in place - the raw footer is replaced by
#   the marker-wrapped partial. Subsequent runs: only the text between the
#   markers is refreshed, so re-running is idempotent.
#
# USAGE
#   sh scripts/sync-footer.sh           # inject partial into all target pages
#   sh scripts/sync-footer.sh --check   # verify only; non-zero exit if drift
#
# PATHS
#   The partial uses ROOT-ABSOLUTE asset paths (/images/brand/...), which resolve
#   correctly at every directory depth because the site is served from docroot.
#   No per-depth path rewriting is needed.
#
# SCOPE
#   Operates on the public page list below. Deliberately excludes admin/,
#   email-templates/, and the cta-card template (non-public, keep own footers).

set -eu

# Resolve repo root from this script's location (scripts/..).
SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
ROOT=$(cd "$SCRIPT_DIR/.." && pwd)
PARTIAL="$ROOT/partials/footer.html"

START_MARK='<!-- FOOTER:START -->'
END_MARK='<!-- FOOTER:END -->'

MODE="write"
if [ "${1:-}" = "--check" ]; then
  MODE="check"
fi

if [ ! -f "$PARTIAL" ]; then
  echo "ERROR: partial not found: $PARTIAL" >&2
  exit 2
fi

# Build the canonical block (markers + partial), each line indented 4 spaces
# to match the site's existing footer indentation under <body>/<main>.
TMP_BLOCK=$(mktemp)
{
  printf '    %s\n' "$START_MARK"
  while IFS= read -r line || [ -n "$line" ]; do
    if [ -n "$line" ]; then
      printf '    %s\n' "$line"
    else
      printf '\n'
    fi
  done < "$PARTIAL"
  printf '    %s\n' "$END_MARK"
} > "$TMP_BLOCK"

# Target public pages (relative to repo root). Excludes admin/, email-templates/,
# images/video/cta-card/cta-card-template.html.
PAGES="
about.html
articles.html
buy.html
contact.html
index.html
sell.html
services.html
valuation.html
articles/_template.html
articles/boat-reviews/2026-05-04-world-premiere-riviera-6200-sport-yacht.html
articles/how-to/2026-05-05-how-to-choose-a-yacht-broker.html
articles/how-to/2026-05-09-how-to-plan-your-first-bahamas-cruise.html
articles/industry-news/2026-04-03-sunseeker-gets-a-new-permanent-ceo.html
articles/industry-news/2026-05-05-eight-sunseeker-yachts-find-new-owners-at-fort-lauderdale-in.html
articles/industry-news/2026-05-05-west-marine-readies-possible-bankruptcy.html
articles/industry-news/2026-05-08-riviera-unveils-integrated-solar-breakthrough.html
articles/industry-news/2026-05-09-we-lost-a-yachting-giant.html
articles/industry-news/2026-06-04-one-water-announces-owyg-bahamas-rendezvous-july-16-19-2026.html
articles/newsletters/2026-05-09-the-logbook-may-2026.html
articles/travel/2026-05-02-a-simple-trip-to-the-abacos.html
articles/travel/2026-05-05-bahamas-cruising-permit-changes-again.html
articles/travel/2026-05-05-bahamas-cruising-permit-changes-april-2026.html
yachts/fringe-benefits.html
yachts/fortunato.html
"

CHANGED=0
DRIFT=0
COUNT=0

for rel in $PAGES; do
  [ -n "$rel" ] || continue
  f="$ROOT/$rel"
  COUNT=$((COUNT + 1))
  if [ ! -f "$f" ]; then
    echo "ERROR: target page missing: $rel" >&2
    exit 2
  fi

  OUT=$(mktemp)

  # awk: replace either an existing marker block OR a raw <footer class="site-footer">
  # block with the canonical block. Preserves all other content verbatim.
  awk -v blockfile="$TMP_BLOCK" '
    BEGIN {
      # Load the canonical block into an array.
      n = 0
      while ((getline l < blockfile) > 0) { block[n++] = l }
      close(blockfile)
      inblock = 0
      done_for_file = 0
    }
    {
      line = $0
    }
    # Existing marker block: swallow from START to END, emit canonical once.
    inblock == 0 && index(line, "<!-- FOOTER:START -->") {
      inblock = 1
      for (i = 0; i < n; i++) print block[i]
      next
    }
    inblock == 1 {
      if (index(line, "<!-- FOOTER:END -->")) { inblock = 0 }
      next
    }
    # Raw legacy footer (no markers): swallow <footer ...site-footer...> .. </footer>.
    inraw == 0 && index(line, "<footer class=\"site-footer\"") {
      inraw = 1
      for (i = 0; i < n; i++) print block[i]
      if (index(line, "</footer>")) { inraw = 0 }
      next
    }
    inraw == 1 {
      if (index(line, "</footer>")) { inraw = 0 }
      next
    }
    { print line }
  ' "$f" > "$OUT"

  if cmp -s "$f" "$OUT"; then
    rm -f "$OUT"
  else
    if [ "$MODE" = "check" ]; then
      echo "DRIFT: $rel" >&2
      DRIFT=$((DRIFT + 1))
      rm -f "$OUT"
    else
      mv "$OUT" "$f"
      CHANGED=$((CHANGED + 1))
      echo "updated: $rel"
    fi
  fi
done

rm -f "$TMP_BLOCK"

if [ "$MODE" = "check" ]; then
  if [ "$DRIFT" -gt 0 ]; then
    echo "CHECK FAILED: $DRIFT of $COUNT page(s) out of sync with partial." >&2
    exit 1
  fi
  echo "CHECK OK: all $COUNT page(s) match partials/footer.html."
  exit 0
fi

echo "done: $CHANGED of $COUNT page(s) updated from partials/footer.html."
