#!/usr/bin/env bash
# Build installable theme and plugin zips, for a normal WordPress install or a
# staging host.
#
# The one-click Playground preview does not use these: blueprint.json loads the
# theme, plugin and content straight from the main branch.
#
# The zips must contain a single top-level folder named after the theme or
# plugin, which is what WordPress expects from an uploaded archive.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIST="$ROOT/dist"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

rm -rf "$DIST"
mkdir -p "$DIST"

cp -R "$ROOT/wp-content/themes/tlharris-public" "$STAGE/"
cp -R "$ROOT/wp-content/plugins/tlharris-core" "$STAGE/"

# The plugin reads content/*.json from three levels above itself, which is the
# repository root in a checkout. Inside a packaged install there is no such
# folder, so the data travels with the plugin and a filter points at it.
mkdir -p "$STAGE/tlharris-core/content"
cp "$ROOT"/content/*.json "$STAGE/tlharris-core/content/"

cat > "$STAGE/tlharris-core/content-path.php" <<'PHP'
<?php
/**
 * In a checkout the verified JSON lives at the repository root. In a packaged
 * install it ships inside the plugin. This points the importer at whichever
 * one is present.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'tlharris_content_dir',
	function ( $dir ) {
		return is_readable( $dir . '/priorities.json' ) ? $dir : __DIR__ . '/content';
	}
);
PHP

# Load that shim from the plugin bootstrap if it is not already wired in.
if ! grep -q "content-path.php" "$STAGE/tlharris-core/tlharris-core.php"; then
	printf '\n%s\n' "require_once __DIR__ . '/content-path.php';" >> "$STAGE/tlharris-core/tlharris-core.php"
fi

( cd "$STAGE" && zip -rq "$DIST/tlharris-public.zip" tlharris-public )
( cd "$STAGE" && zip -rq "$DIST/tlharris-core.zip" tlharris-core )

echo "built:"
ls -lh "$DIST" | awk 'NR>1 {printf "  %-28s %s\n", $9, $5}'
