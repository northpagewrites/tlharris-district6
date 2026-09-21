# T. L. Harris — District 6

The WordPress theme and plugin behind the District 6 officeholder site for the
Memphis-Shelby County Schools Board of Education.

## See it running, with no install

The one-click preview runs a full WordPress — including `wp-admin` — in your
browser using WebAssembly. Nothing is installed and nothing is uploaded.

**[Open the preview](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/northpagewrites/tlharris-district6/main/blueprint.json)**

That link builds from the tip of `main` every time it is opened, on the WordPress
and PHP versions pinned in `blueprint.json`. There is no build step and nothing
to wait for.

To review one specific commit, and to prove the preview really is that commit,
use the pinned workflow in [`docs/preview-workflow.md`](docs/preview-workflow.md):

```bash
node scripts/preview.mjs url <commit>              # a link pinned to that commit
node scripts/preview.mjs verify stamp.json <commit>  # check a running preview against it
```

The preview seeds demo content and publishes it so the pages are not empty. That
seeding lives in `preview/` and cannot run anywhere but Playground; a real site's
imported records stay drafts until a person has checked them.

## What is in here

| Path | What it is |
|---|---|
| `wp-content/themes/tlharris-public/` | Block theme. Layout only, no page copy. |
| `wp-content/themes/tlharris-public/theme.json` | The design system: colour, type, spacing, buttons. |
| `wp-content/themes/tlharris-public/style.css` | Component styles. |
| `wp-content/themes/tlharris-public/patterns/` | Page copy. The words live here, not in templates. |
| `wp-content/plugins/tlharris-core/` | Content types, structured fields, identity settings. Portable across themes. |
| `content/*.json` | Verified facts, transcribed from official sources and imported into the CMS. |
| `blueprint.json` | The Playground preview: one checkout of this repository, pinned WordPress and PHP. |
| `preview/` | Preview-only seeding and the fingerprint. Never deployed; refuses to run outside Playground. |
| `scripts/validate.sh` | The validator. Run it before every push. |
| `scripts/preview.mjs` | Pinned preview links, commit fingerprints, and preview verification. |

## Working on the design

Three files cover almost all of it:

- **`theme.json`** — the palette, type scale, spacing scale and button styles.
  Change a colour here and it changes everywhere. The validator enforces WCAG
  contrast against this palette, so a change that fails accessibility fails the
  build.
- **`style.css`** — cards, the progress bar, field labels, focus rings.
- **`patterns/*.php`** — the copy on each page.

Templates in `templates/` are layout only. They contain block markup, which is
easy to break in ways that still look fine on the front end but show up as
broken blocks in the Site Editor — so run the validator after touching them.

## Before you push

```bash
npm install          # once
bash scripts/validate.sh
```

It checks required files, PHP syntax, block validity against the real Gutenberg
definitions, the preview blueprint against Playground's own schema, that the
preview is pinned and built from one checkout and that preview-only code has not
leaked into the theme or plugin, skip-link targets, hardcoded role text,
placeholder copy left in public pages, and colour contrast.

There is no CI in this repository yet, so the validator you run is the only gate.
`docs/ci/validate.yml` is a ready workflow; its header says how to enable it.

## The rules this project works to

`CLAUDE.md` holds them, and they apply to every contributor, human or
otherwise. The short version: nothing gets published that isn't traceable to an
official source, the current office title is configuration rather than brand,
and a page says plainly when it does not know something instead of estimating.

## Licence

GPL-2.0-or-later, matching WordPress.
