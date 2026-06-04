#!/bin/bash
#
# Renders the Haley Yachts CTA end card template to two PNGs at exact size.
# No dependencies beyond the Google Chrome already installed on this Mac.
#
# Usage:
#   bash /Users/jameschaley/Desktop/Claude/haleyyachts-website/images/video/cta-card/render-cards.sh
#
# Output (written next to this script):
#   cta-card-1080x1080.png
#   cta-card-1080x1350.png
#
# Re-run any time after editing cta-card-template.html.

set -e

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CHROME="/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"
TPL="file://$DIR/cta-card-template.html"

echo "Rendering 1080x1080 ..."
"$CHROME" --headless=new --disable-gpu --hide-scrollbars \
  --force-device-scale-factor=1 --allow-file-access-from-files \
  --window-size=1080,1080 --virtual-time-budget=4000 \
  --screenshot="$DIR/cta-card-1080x1080.png" "$TPL#only-1x1"

echo "Rendering 1080x1350 ..."
"$CHROME" --headless=new --disable-gpu --hide-scrollbars \
  --force-device-scale-factor=1 --allow-file-access-from-files \
  --window-size=1080,1350 --virtual-time-budget=4000 \
  --screenshot="$DIR/cta-card-1080x1350.png" "$TPL#only-4x5"

echo "Done. PNGs are in: $DIR"
