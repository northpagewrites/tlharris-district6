#!/usr/bin/env bash
# Builds the static preview into ./site-out for Vercel (or any static host).
# Copies only what the pages use, so docs, PHP and tooling are never published.
# Set EXCLUDE_VIDEO=1 to leave the WREG clip out of the deployment.
set -euo pipefail
cd "$(dirname "$0")/.."

OUT=site-out
THEME=wp-content/themes/tlharris-public

rm -rf "$OUT"
mkdir -p "$OUT/$THEME/assets/video"

cp ./*.html static-preview.css "$OUT/"
cp "$THEME/style.css" "$OUT/$THEME/"
cp -R "$THEME/assets/images" "$OUT/$THEME/assets/"
cp "$THEME"/assets/video/*.jpg "$OUT/$THEME/assets/video/"
if [ "${EXCLUDE_VIDEO:-0}" != "1" ]; then
  cp "$THEME"/assets/video/*.mp4 "$OUT/$THEME/assets/video/"
fi

# The home page is local-preview.html; serve a copy of it at "/".
cp local-preview.html "$OUT/index.html"

# Keep the preview out of search engines until the office approves launch.
printf 'User-agent: *\nDisallow: /\n' > "$OUT/robots.txt"

echo "Built $OUT ($(find "$OUT" -type f | wc -l | tr -d ' ') files)"
