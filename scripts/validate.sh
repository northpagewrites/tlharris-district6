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
  "$ROOT/docs/preview-workflow.md"
  "$ROOT/content/district-6-schools.json"
  "$ROOT/content/board-meetings.json"
  "$ROOT/content/priorities.json"
  "$ROOT/preview/bootstrap.php"
  "$ROOT/preview/fingerprint.php"
  "$ROOT/scripts/build-zips.sh"
  "$ROOT/scripts/preview.mjs"
  "$ROOT/tools/validate-blueprint.mjs"
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
  done < <(find "$ROOT/wp-content" "$ROOT/preview" -name '*.php' -print0)
  pass "PHP syntax (wp-content and preview)"
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
# 5. Playground blueprint
#
# The one-click preview fails outright if blueprint.json does not match the
# Playground schema. Needs network to fetch the schema and skips with a warning
# if it cannot.
# ---------------------------------------------------------------------------
if [ -d "$ROOT/node_modules/ajv" ] && command -v node >/dev/null 2>&1; then
  node "$ROOT/tools/validate-blueprint.mjs" || fail "blueprint validation"
else
  echo "warn: blueprint validation skipped. Run 'npm install' in the repo root to enable it."
fi

# ---------------------------------------------------------------------------
# 6. Preview build: pinned, from one commit, and isolated
#
# docs/preview-workflow.md explains each rule. In short: a preview that floats
# on "latest", or stitches theme, plugin and content together from separate
# fetches, cannot be tied to a Git commit, and preview-only seeding must never
# leak into the theme, the plugin or the real import workflow.
# ---------------------------------------------------------------------------
python3 - "$ROOT" <<'PY' || FAILED=1
import json, pathlib, re, sys

root = pathlib.Path(sys.argv[1])
failures = 0

def fail(msg):
    global failures
    print(f'FAIL: {msg}')
    failures += 1

bp = json.loads((root / 'blueprint.json').read_text())
steps = bp.get('steps', [])

# 6a. Versions are pinned. "latest" and "beta" move under you.
mark = failures
pins = bp.get('preferredVersions', {})
for key in ('wp', 'php'):
    value = pins.get(key)
    if not isinstance(value, str) or not re.fullmatch(r'\d+\.\d+(\.\d+)?', value):
        fail(f'blueprint.json: preferredVersions.{key} must be a pinned version such as "8.2", got {value!r}')
# The pins are quoted in the workflow doc. A stale doc is worse than none.
doc = (root / 'docs/preview-workflow.md').read_text()
for key in ('wp', 'php'):
    value = pins.get(key)
    if isinstance(value, str) and f'`{value}`' not in doc:
        fail(f'docs/preview-workflow.md does not mention the pinned {key} version `{value}`. Update its table')
if failures == mark:
    print('ok:   WordPress and PHP versions are pinned and documented')

# 6b. One checkout of the whole repository, so theme, plugin, content and the
# bootstrap all come from a single ref.
mark = failures
checkouts = [s for s in steps if isinstance(s.get('filesTree'), dict) and s['filesTree'].get('resource') == 'git:directory']
if len(checkouts) != 1:
    fail(f'blueprint.json: expected exactly one git:directory checkout, found {len(checkouts)}')
else:
    tree = checkouts[0]['filesTree']
    if checkouts[0].get('step') != 'writeFiles' or checkouts[0].get('writeToPath') != '/wordpress':
        fail('blueprint.json: the checkout must be a writeFiles step to /wordpress')
    if 'path' in tree:
        fail('blueprint.json: the checkout must take the whole repository (no "path"), or parts could come from different refs')
    if tree.get('refType') not in ('branch', 'commit'):
        fail('blueprint.json: the checkout needs an explicit refType of "branch" or "commit"')
    for step in steps:
        if step is not checkouts[0] and '"resource"' in json.dumps(step):
            fail(f'blueprint.json: step "{step.get("step")}" fetches a second resource; everything must come from the one checkout')
    if failures == mark:
        print('ok:   blueprint has a single checkout of the repository')

# 6c. The only inline PHP is the switch and the require. Seeding lives in preview/.
mark = failures
runs = [s for s in steps if s.get('step') == 'runPHP']
if len(runs) != 1:
    fail(f'blueprint.json: expected exactly one runPHP step, found {len(runs)}')
else:
    code = runs[0].get('code', '')
    if 'TLHARRIS_PLAYGROUND_PREVIEW' not in code or '/wordpress/preview/bootstrap.php' not in code:
        fail('blueprint.json: the runPHP step must define TLHARRIS_PLAYGROUND_PREVIEW and require preview/bootstrap.php')
    if re.search(r'wp_insert_post|wp_update_post|post_status|update_option|wp_delete', code):
        fail('blueprint.json: seeding belongs in preview/bootstrap.php, not inline in the blueprint')
if failures == mark:
    print('ok:   blueprint runs only the preview bootstrap')

# 6d. The bootstrap refuses to run anywhere but Playground, and publishes only
# records the importers created.
mark = failures
bootstrap = (root / 'preview/bootstrap.php').read_text()
for needle, why in (
    ('TLHARRIS_PLAYGROUND_PREVIEW', 'the opt-in constant'),
    ("'PHP.wasm'", 'the Playground runtime check'),
    ('tlharris_import_key', 'the restriction to importer-created records'),
):
    if needle not in bootstrap:
        fail(f'preview/bootstrap.php: missing {why} ({needle})')
if failures == mark:
    print('ok:   bootstrap guard and publish restriction present')

# 6e. Nothing that ships knows Playground exists.
mark = failures
for p in sorted((root / 'wp-content').rglob('*')):
    if p.is_file() and re.search(r'playground|TLHARRIS_PLAYGROUND_PREVIEW|PHP\.wasm', p.read_text(errors='ignore'), re.I):
        fail(f'{p.relative_to(root)}: mentions Playground. Preview-only code belongs in preview/, outside wp-content')
if failures == mark:
    print('ok:   theme and plugin are free of preview-only code')

# 6f. The real importers only ever create drafts. Publishing is a human step.
mark = failures
plugin = (root / 'wp-content/plugins/tlharris-core/tlharris-core.php').read_text()
for fn in ('tlharris_import_schools', 'tlharris_import_meetings', 'tlharris_import_priorities'):
    m = re.search(r'\nfunction\s+' + fn + r'\s*\(.*?(?=\nfunction\s|\Z)', plugin, re.S)
    if not m:
        fail(f'plugin: {fn}() not found')
        continue
    body = m.group(0)
    if re.search(r"""['"]publish['"]|wp_publish_post""", body):
        fail(f'plugin: {fn}() must never publish. Imported records stay drafts until a person reviews them')
    if "'draft'" not in body:
        fail(f'plugin: {fn}() does not set post_status to draft')
if failures == mark:
    print('ok:   importers create drafts only')

sys.exit(1 if failures else 0)
PY

# ---------------------------------------------------------------------------
# 7. The two fingerprint implementations agree
#
# preview/fingerprint.php runs inside the preview; scripts/preview.mjs runs on
# a developer's machine or straight against Git. If they ever disagree, a
# correct preview would fail verification, or worse, a wrong one would pass.
# ---------------------------------------------------------------------------
if command -v php >/dev/null 2>&1 && command -v node >/dev/null 2>&1; then
  php_fp="$(php -r 'require $argv[1] . "/preview/fingerprint.php"; echo json_encode( tlharris_preview_fingerprint( $argv[1] ) );' "$ROOT")"
  node_fp="$(node "$ROOT/scripts/preview.mjs" fingerprint --worktree)"
  if python3 - "$php_fp" "$node_fp" <<'PY'
import json, sys
php, node = json.loads(sys.argv[1]), json.loads(sys.argv[2])
sys.exit(0 if (php['fingerprint'], php['files']) == (node['fingerprint'], node['files']) else 1)
PY
  then
    pass "PHP and Node fingerprints agree ($(echo "$node_fp" | python3 -c 'import json,sys; d=json.load(sys.stdin); print(d["files"], "files")'))"
  else
    fail "preview/fingerprint.php and scripts/preview.mjs disagree: php=$php_fp node=$node_fp"
  fi
else
  echo "warn: fingerprint parity check skipped (needs both php and node)."
fi

# ---------------------------------------------------------------------------
if [ "$FAILED" -ne 0 ]; then
  echo
  echo "VALIDATION FAILED"
  exit 1
fi

echo
echo "Validation passed."
