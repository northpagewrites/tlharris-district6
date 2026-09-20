# T. L. Harris — District 6

The WordPress theme and plugin behind the District 6 officeholder site for the
Memphis-Shelby County Schools Board of Education.

## See it running, with no install

The one-click preview runs a full WordPress — including `wp-admin` — in your
browser using WebAssembly. Nothing is installed and nothing is uploaded.

**[Open the preview](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/northpagewrites/tlharris-district6/main/blueprint.json)**

It rebuilds from `main` automatically, so the link always shows the current
state of this repository.

## What is in here

| Path | What it is |
|---|---|
| `wp-content/themes/tlharris-public/` | Block theme. Layout only, no page copy. |
| `wp-content/themes/tlharris-public/theme.json` | The design system: colour, type, spacing, buttons. |
| `wp-content/themes/tlharris-public/style.css` | Component styles. |
| `wp-content/themes/tlharris-public/patterns/` | Page copy. The words live here, not in templates. |
| `wp-content/plugins/tlharris-core/` | Content types, structured fields, identity settings. Portable across themes. |
| `content/*.json` | Verified facts, transcribed from official sources and imported into the CMS. |
| `scripts/validate.sh` | The validator. Run it before every push. |

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
definitions, skip-link targets, hardcoded role text, placeholder copy left in
public pages, and colour contrast. The same checks run in CI on every push and
pull request.

## The rules this project works to

`CLAUDE.md` holds them, and they apply to every contributor, human or
otherwise. The short version: nothing gets published that isn't traceable to an
official source, the current office title is configuration rather than brand,
and a page says plainly when it does not know something instead of estimating.

## Licence

GPL-2.0-or-later, matching WordPress.
