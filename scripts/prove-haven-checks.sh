#!/bin/bash
# Prove the checks in scripts/build-haven-print.py by breaking them.
#
# A check that cannot be shown red is not a check. Every case below injects a
# real defect and states the exit code it must produce AND the message it must
# produce, so a build that fails for an unrelated reason does not count as
# proof. This script exits non-zero if any case comes back wrong, which is the
# point: a check that quietly stops working fails here instead of sitting in a
# hundred lines of output as one unread line.
#
# Everything happens in a throwaway mirror. The repo is never the subject.
#
# Usage: scripts/prove-haven-checks.sh          (about 45 seconds)

set -u
REPO="$(cd "$(dirname "$0")/.." && pwd)"
WORK="$(mktemp -d "${TMPDIR:-/tmp}/haven-prove.XXXXXX")"
trap 'rm -rf "$WORK"' EXIT

for d in docs/design images/brand scripts; do
  mkdir -p "$WORK/$d"
  cp -R "$REPO/$d/" "$WORK/$d/"
done
SRC="$WORK/docs/design/haven-spread-clean.html"
PY="$WORK/scripts/build-haven-print.py"
cp "$SRC" "$WORK/clean.bak"
cp "$PY" "$WORK/build.bak"
PASS=0
FAILED=0

# prove <expected exit> <label> <extended regex the output must contain>
prove () {
  local want="$1" label="$2" pattern="$3" out code ok=1
  rm -rf "$WORK/out"
  out="$(python3 "$PY" --out "$WORK/out/x.pdf" 2>&1)"
  code=$?
  [ "$code" = "$want" ] || ok=0
  printf '%s' "$out" | grep -qE "$pattern" || ok=0
  if [ "$ok" = 1 ]; then
    PASS=$((PASS + 1))
    printf '  ok    exit %s  %s\n' "$code" "$label"
  else
    FAILED=$((FAILED + 1))
    printf '  WRONG exit %s (wanted %s)  %s\n' "$code" "$want" "$label"
    printf '        expected to see: %s\n' "$pattern"
    printf '%s' "$out" | grep -E '^  FAIL |^FAIL: |all checks passed' | sed 's/^/        /'
  fi
  cp "$WORK/clean.bak" "$SRC"
  cp "$WORK/build.bak" "$PY"
  [ -f "$WORK/parked.png" ] && mv "$WORK/parked.png" "$WORK/images/brand/haleyyachtslogo.png"
  return 0
}

echo "=== proving the HAVEN print checks, in $WORK ==="
echo
echo "-- the artwork is all there ----------------------------------------"

sed -i '' 's|owyg/owyg-lockup.png|owyg/owyg-lockup-GONE.png|' "$SRC"
prove 1 "One Water lockup path broken" "image\(s\) inside the lockup box"

mv "$WORK/images/brand/haleyyachtslogo.png" "$WORK/parked.png"
prove 1 "Haley Yachts logo source deleted" "image\(s\) inside the lockup box"

sed -i '' '/open-sans-latin-400-italic.woff2/d' "$PY"
prove 1 "italic dropped from FONT_FACES" "face\(s\) missing from the output: OpenSans-Italic"

echo
echo "-- no placeholder reaches the page ---------------------------------"

sed -i '' 's|<dd>New build. Call for current delivery.</dd>|<dd>[Clark to confirm the current delivery window with Riviera before this goes to press]</dd>|' "$SRC"
prove 1 "bracket that WRAPS in the availability row" "placeholder still in the artwork: .Clark to confirm"

sed -i '' 's|<dd>New build. Call for current delivery.</dd>|<dd>New build. [TK]</dd>|' "$SRC"
prove 1 "two-character [TK]" "placeholder still in the artwork: .TK."

sed -i '' 's|<dd>New build. Call for current delivery.</dd>|<dd>New build. \&lt;TBC\&gt;</dd>|' "$SRC"
prove 1 "escaped angle bracket, which DOES reach the page" "placeholder still in the artwork: <TBC>"

# Deliberately green, and that is the assertion. A bare <TBC> in the HTML is
# eaten by the parser as an unknown tag and never reaches the page, so there is
# nothing in the PDF to catch. If this case ever goes red the check has started
# reading the source instead of the output.
sed -i '' 's|<dd>New build. Call for current delivery.</dd>|<dd>New build. <TBC></dd>|' "$SRC"
prove 0 "BARE <TBC>, which the parser eats: must stay green" "all checks passed"

echo
echo "-- the bleed covers the whole edge ---------------------------------"

sed -i '' 's|\.cyan-rule{left:-\.125in;top:10\.785in;width:17in|.cyan-rule{left:0in;top:10.785in;width:16.75in|' "$SRC"
prove 1 "cyan rule 0.125in short at both bottom corners" "bottom bleed is cyan"

sed -i '' 's|\.cyan-rule{left:-\.125in;top:10\.785in|.cyan-rule{left:-.125in;top:10.60in|' "$SRC"
prove 1 "white gap along the bottom bleed" "bottom bleed is cyan"

sed -i '' 's|  left:-\.125in;top:-\.125in;width:8\.5in;height:11\.125in;|  left:-.125in;top:0in;width:8.5in;height:11.125in;|' "$SRC"
prove 1 "white gap along the top bleed" "top bleed carries the hero"

sed -i '' 's|  left:-\.125in;top:-\.125in;width:8\.5in;height:11\.125in;|  left:0in;top:-.125in;width:8.5in;height:11.125in;|' "$SRC"
prove 1 "white gap along the left bleed" "left bleed carries the hero"

echo
echo "-- the marks are still on paper, which is what the flatten assumes --"

# into the 0.207in gap BETWEEN the two marks: the blind spot a ring of two
# bands above and below the lockup could not see
sed -i '' 's|\.accent-rule{left:9in;top:2\.7in;width:1\.2in|.accent-rule{left:15.15in;top:9.955in;width:1.2in|' "$SRC"
prove 1 "cyan rule laid between the two marks" "not sitting on paper"

echo
echo "-- the build directory is not somebody else's --------------------"
rm -rf "$WORK/out"
mkdir -p "$WORK/out/work"
echo "not ours" > "$WORK/out/work/keep-me.txt"
guard="$(python3 "$PY" --out "$WORK/out/x.pdf" 2>&1)"
gcode=$?
if [ "$gcode" = 1 ] && printf '%s' "$guard" | grep -q "did not create it" \
   && [ -f "$WORK/out/work/keep-me.txt" ]; then
  PASS=$((PASS + 1)); printf '  ok    exit 1  refuses to delete a work/ it did not make\n'
else
  FAILED=$((FAILED + 1)); printf '  WRONG exit %s  build directory guard\n' "$gcode"
fi

echo
echo "=== $PASS as expected, $FAILED wrong ==="
[ "$FAILED" = 0 ] || exit 1
