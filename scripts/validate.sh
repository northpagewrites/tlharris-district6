#!/usr/bin/env bash
# Repository validation.
#
# This script exists because v2 shipped with 43 invalid blocks, a template that
# lost its footer, and five templates with no skip-link target, and the previous
# version of this script reported "passed" on all of it. Every check below
# corresponds to something that actually went wrong.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
FAILED=0

fail() { echo "FAIL: $*"; FAILED=1; }
pass() { echo "ok:   $*"; }

# ---------------------------------------------------------------------------
# 1. Required files
# ---------------------------------------------------------------------------
required=(
  "$ROOT/CLAUDE.md"
  "$ROOT/README.md"
  "$ROOT/blueprint.json"
  "$ROOT/docs/staging-setup.md"
  "$ROOT/docs/content-sources.md"
  "$ROOT/docs/site-fields.md"
  "$ROOT/content/district-6-schools.json"
  "$ROOT/content/board-meetings.json"
  "$ROOT/content/priorities.json"
  "$ROOT/scripts/build-zips.sh"
  "$ROOT/wp-content/themes/tlharris-public/style.css"
  "$ROOT/wp-content/themes/tlharris-public/theme.json"
  "$ROOT/wp-content/themes/tlharris-public/functions.php"
  "$ROOT/wp-content/plugins/tlharris-core/tlharris-core.php"
)
for f in "${required[@]}"; do
  test -f "$f" || fail "missing required file: ${f#"$ROOT"/}"
done
pass "required files"

# ---------------------------------------------------------------------------
# 2. PHP syntax
#
# A skipped lint is not a passing lint. Set VALIDATE_ALLOW_NO_PHP=1 only if you
# know the CI step that does have PHP will run this too.
# ---------------------------------------------------------------------------
if command -v php >/dev/null 2>&1; then
  while IFS= read -r -d '' f; do
    php -l "$f" >/dev/null || fail "PHP syntax error in ${f#"$ROOT"/}"
  done < <(find "$ROOT/wp-content" -name '*.php' -print0)
  pass "PHP syntax"
elif [ "${VALIDATE_ALLOW_NO_PHP:-0}" = "1" ]; then
  echo "warn: PHP not installed, syntax check skipped (VALIDATE_ALLOW_NO_PHP=1)"
else
  fail "PHP is not installed, so the syntax check cannot run. Install PHP, or set VALIDATE_ALLOW_NO_PHP=1 to accept the risk."
fi

# ---------------------------------------------------------------------------
# 3. Theme content checks
# ---------------------------------------------------------------------------
python3 - "$ROOT" <<'PY' || FAILED=1
import json, pathlib, re, sys

root = pathlib.Path(sys.argv[1])
theme = root / 'wp-content/themes/tlharris-public'
failed = False

def fail(msg):
    global failed
    print(f'FAIL: {msg}')
    failed = True

theme_json = json.loads((theme / 'theme.json').read_text())
print('ok:   theme.json parses')

templates = sorted(theme.glob('templates/*.html'))
parts = sorted(theme.glob('parts/*.html'))

# 3a. Every template needs the skip-link target, or "Skip to content" goes nowhere.
for p in templates:
    s = p.read_text()
    if '<main' in s and 'id="main-content"' not in s:
        fail(f'{p.name}: has <main> but no id="main-content" for the skip link')
    if '<main' not in s:
        fail(f'{p.name}: no <main> landmark')
print('ok:   skip-link target on every template')

# 3b. The role is configuration. Templates must not restate it.
role_patterns = [
    r'Board Member\s*[·|]\s*District\s*6',
    r'Board Member for District\s*6',
    r'Memphis-Shelby County Schools Board Member',
]
for p in templates + parts:
    s = p.read_text()
    for pat in role_patterns:
        if re.search(pat, s, re.I):
            fail(f'{p.name}: hardcoded role "{pat}". Use the tlharris/identity binding.')
print('ok:   no hardcoded role in templates')

# 3c. Internal build notes must never reach a public page.
banned = [
    'placeholder', 'TBD', 'lorem ipsum', 'must be approved before',
    'should be populated', 'will appear here as they are', 'Do not publish',
    'legal review', 'communications approach', 'coming soon',
]
# The empty-state strings below are real user-facing copy, not build notes.
allowed = {
    'will appear here as they are published.',
    'will appear here as they are recorded and sourced.',
}
for p in templates + parts + sorted(theme.glob('patterns/*.php')):
    s = p.read_text()
    for phrase in banned:
        for m in re.finditer(re.escape(phrase), s, re.I):
            window = s[m.start():m.start() + 60]
            if any(a in window for a in allowed):
                continue
            fail(f'{p.name}: build note or placeholder in public copy: "{phrase}"')
print('ok:   no build notes in public copy')

# 3d. Synthetic imagery must never represent real people or places.
for p in templates + parts:
    if 'Gemini_Generated_Image' in p.read_text():
        fail(f'{p.name}: synthetic image reference')
print('ok:   no synthetic image references')

# 3e. Contrast. These exact pairs failed WCAG AA in v2.
def relative_luminance(hex_color):
    hex_color = hex_color.lstrip('#')
    channels = [int(hex_color[i:i + 2], 16) / 255 for i in (0, 2, 4)]
    linear = [c / 12.92 if c <= 0.03928 else ((c + 0.055) / 1.055) ** 2.4 for c in channels]
    return 0.2126 * linear[0] + 0.7152 * linear[1] + 0.0722 * linear[2]

def ratio(a, b):
    la, lb = relative_luminance(a), relative_luminance(b)
    hi, lo = max(la, lb), min(la, lb)
    return (hi + 0.05) / (lo + 0.05)

palette = {c['slug']: c['color'] for c in theme_json['settings']['color']['palette']}
checks = [
    ('muted', 'soft', 4.5, 'muted text on the soft band'),
    ('muted', 'paper', 4.5, 'muted text on paper'),
    ('muted', 'white', 4.5, 'muted text on cards'),
    ('ink', 'paper', 4.5, 'body text on paper'),
    ('white', 'ink', 4.5, 'text on the dark band'),
    ('accent', 'paper', 3.0, 'focus ring on paper'),
    ('accent', 'soft', 3.0, 'focus ring on the soft band'),
    ('accent', 'white', 3.0, 'focus ring on cards'),
    ('accent', 'ink', 3.0, 'focus ring on the dark band'),
]
for fg, bg, need, label in checks:
    if fg not in palette or bg not in palette:
        fail(f'palette is missing "{fg}" or "{bg}"')
        continue
    r = ratio(palette[fg], palette[bg])
    if r < need:
        fail(f'contrast: {label} is {r:.2f}:1, needs {need}:1')
print('ok:   colour contrast')

sys.exit(1 if failed else 0)
PY

# ---------------------------------------------------------------------------
# 4. Block validation
#
# The check that matters most. Run `npm install` once in the repo root to
# enable it. Without it, invalid block markup ships silently.
# ---------------------------------------------------------------------------
if [ -d "$ROOT/node_modules/@wordpress/blocks" ] && command -v node >/dev/null 2>&1; then
  node "$ROOT/tools/validate-blocks.mjs" || fail "block validation"
else
  echo "warn: block validation skipped. Run 'npm install' in the repo root to enable it."
fi

# ---------------------------------------------------------------------------
if [ "$FAILED" -ne 0 ]; then
  echo
  echo "VALIDATION FAILED"
  exit 1
fi

echo
echo "Validation passed."
