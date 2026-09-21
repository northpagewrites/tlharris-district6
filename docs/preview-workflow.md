# Preview workflow

How a commit on `main` becomes something a person can look at, and how anyone
can prove that what they are looking at is the commit they meant.

```
Git commit  ->  blueprint.json  ->  WordPress Playground  ->  visual QA
(the source)    (one checkout,      (builds in the browser,    (against a commit
                 pinned versions)    writes a stamp)            that was verified)
```

There is **no build step**. No zip, no release asset, no CI artefact. Playground
fetches this repository from GitHub in the visitor's browser and assembles a
WordPress site from it. That is the whole pipeline, and it is why the preview
cannot drift away from Git: there is nothing in between to go stale.

## What is pinned, and what is not

| Thing | Pinned to | Notes |
|---|---|---|
| WordPress | `7.1.1` (exact) | `preferredVersions.wp`. Checked against a hosted Playground: it reported `7.1.1`. Never `latest` or `beta`. |
| PHP | `8.2` (minor) | `preferredVersions.php`. Playground cannot pin a PHP patch release, so this resolves to the newest 8.2.x (8.2.33 when tested). |
| Theme, plugin, content, bootstrap | One Git ref | A single checkout of the whole repository, so all four come from the same commit. |
| Playground itself | Nothing | `playground.wordpress.net` is a hosted app and can change under us. The stamp records what actually ran. |

`scripts/validate.sh` fails if either version stops being a plain version number,
or if the blueprint grows a second checkout (a second fetch is how theme and
plugin end up from different commits).

## Two links, for two jobs

**The floating link** is the one in `README.md`. It builds from the tip of
`main` each time it is opened. Use it to look at the site. It is not evidence of
anything, because "the tip of `main`" moves.

**The pinned link** is for QA and for bug reports. It names one commit, so it
builds the same tree today and next month.

```bash
git fetch origin
node scripts/preview.mjs url origin/main      # or any commit, tag or SHA
```

The script prints a Playground link. It reads `blueprint.json`, changes the
checkout's `ref` to the full commit SHA and `refType` to `commit` **in memory
only**, and never edits the file. If the commit is not on any remote branch it
warns you, because Playground can only load what has been pushed to GitHub.

## How the preview is produced

`blueprint.json`, in order:

| # | Step | What it does |
|---|---|---|
| 1 | `writeFiles` to `/wordpress` | Checks out the whole repository at one ref. The theme, plugin, `content/` and `preview/` all land in one place, in the layout WordPress expects (`/wordpress/wp-content/...`). |
| 2 | `activatePlugin` | Activates `tlharris-core`. |
| 3 | `activateTheme` | Activates `tlharris-public`. |
| 4 | `setSiteOptions` | Site title, tagline, pretty permalinks. |
| 5 | `runPHP` | Defines `TLHARRIS_PLAYGROUND_PREVIEW`, then requires `preview/bootstrap.php`. That is all the blueprint contains inline. |

Because the whole repository is written to `/wordpress`, the plugin's importer
finds `content/*.json` at the path it uses in any checkout
(`dirname( __DIR__, 3 ) . '/content'`), with no special-casing for Playground.

## The bootstrap, and why it is isolated

`preview/bootstrap.php` exists so that someone doing visual QA sees a populated
site instead of an empty one. In order, it:

1. fingerprints what was checked out and writes `tlharris-preview-stamp.json`
   with status `running`;
2. removes WordPress's own sample post and page;
3. creates the fifteen pages from the theme's page patterns and sets the front
   page;
4. runs the plugin's ordinary importers for schools, meetings and priorities,
   which create every record as a **draft**;
5. **preview only:** publishes the drafts those importers just created, so the
   pages have something to show;
6. writes the final stamp.

Step 5 is the one that must never leak, so it is fenced four ways:

- **It lives in `preview/`, outside `wp-content/`.** Only the theme and plugin
  folders are deployed. The bootstrap is never part of a real site.
- **It refuses to run elsewhere.** It dies unless `TLHARRIS_PLAYGROUND_PREVIEW`
  is defined *and* `$_SERVER['SERVER_SOFTWARE']` is `PHP.wasm`, which is what
  Playground's runtime reports.
- **It publishes only importer-created drafts**, found by the
  `tlharris_import_key` meta the importers set. A draft someone wrote by hand is
  never touched.
- **The importers never publish.** `tlharris_import_*` create drafts and stop.
  On a real site, imported records stay drafts until a person has checked each
  one against its source. The production import workflow is unchanged.

`scripts/validate.sh` enforces the last two and the isolation: it fails if
anything under `wp-content/` mentions Playground, if an importer can publish, or
if the blueprint does seeding inline instead of through the bootstrap.

## Verifying that a preview is the commit you meant

This is the procedure. Do it before visual QA, and put the short SHA in the QA
notes.

1. **Choose the commit.** `git fetch origin`, then `git rev-parse origin/main`
   (or whichever commit you are testing).
2. **Open the pinned link** from `node scripts/preview.mjs url <sha>`. Wait for
   the site to render. The bootstrap runs before Playground lands on the
   homepage, so a rendered homepage means the stamp is written.
3. **Read the stamp.** Playground nests the WordPress site two iframes deep. In
   the browser console of the Playground tab (or with a browser tool), run:

   ```js
   const outer = document.querySelector('iframe');           // Playground's own frame
   const site  = outer.contentDocument.querySelector('iframe'); // the WordPress site
   const scope = site.contentWindow.location.pathname.match(/^\/scope:[^/]+/)[0];
   await (await fetch(site.contentWindow.location.origin + scope + '/tlharris-preview-stamp.json')).text()
   ```

   The stamp exists only in that browser tab's virtual filesystem, so it cannot
   be fetched from a terminal.
4. **Compare it to Git.** Save the JSON to a file and run:

   ```bash
   node scripts/preview.mjs verify stamp.json <sha>
   ```

   It prints five `PASS` or `FAIL` lines and exits non-zero on any failure: the
   bootstrap finished without errors, the fingerprint matches the commit, the
   file count matches, and WordPress and PHP are the pinned versions.

If it prints `The preview represents <sha>`, start QA. If not, the failing line
says why:

| Failing check | Likely cause |
|---|---|
| fingerprint / file count | Wrong commit. `main` moved, a cache served an older copy, or the commit was never pushed. Use the pinned link. |
| bootstrap finished cleanly | The stamp's `errors` array says what failed. Do not QA a half-seeded site. |
| WordPress / PHP version | Playground no longer offers the pinned version. Update the pin deliberately, below. |

### What the fingerprint is

A hash of every file the site is built from, so two trees are the same exactly
when the hashes match. The rule is deliberately plain so the two
implementations, `preview/fingerprint.php` (inside the preview) and
`scripts/preview.mjs` (from Git, no checkout needed), cannot disagree:

1. Take every regular file under `wp-content/themes/tlharris-public`,
   `wp-content/plugins/tlharris-core`, `content` and `preview`, named relative
   to the repository root with forward slashes.
2. Sort the names byte-wise.
3. One line per file: the SHA-256 of its contents, two spaces, the name, a
   newline.
4. The fingerprint is the SHA-256 of all those lines.

`scripts/validate.sh` computes it both ways on the working tree and fails if they
differ. To compute one yourself: `node scripts/preview.mjs fingerprint <commit>`.

### What the stamp proves, and what it does not

It proves the theme, plugin, content and bootstrap in the running preview are
byte-identical to the named commit, on the stated WordPress and PHP versions.

It does not cover files that are not deployed (`docs/`, `scripts/`, `README.md`),
the Playground runtime, or anything changed in the preview afterwards. The stamp
is written once, when the preview is built. Edit a page in that session's
`wp-admin` and the stamp will not notice. Previews are disposable: nothing done
in one is saved to Git, and closing the tab discards it.

## Before a push: the same runtime, on local files

The hosted Playground can only load what is on GitHub. To check a commit that has
not been pushed, run the same PHP-in-WebAssembly runtime locally, with this
checkout mounted where the `git:directory` step would have written it. Use a
clean working tree: the stamp fingerprints the files that are actually mounted,
so uncommitted edits make `verify` fail, which is the point.

```bash
# 1. The blueprint, minus the git checkout (the files are mounted instead).
node -e '
const fs = require("fs");
const bp = JSON.parse(fs.readFileSync("blueprint.json", "utf8"));
bp.steps = bp.steps.filter((s) => s.step !== "writeFiles");
fs.writeFileSync("/tmp/blueprint-local.json", JSON.stringify(bp));
'

# 2. Serve it. Take --php and --wp from preferredVersions in blueprint.json.
npx @wp-playground/cli@3.1.54 server --php=8.2 --wp=7.1.1 --port=9410 \
  --blueprint=/tmp/blueprint-local.json \
  --mount="$PWD/wp-content/themes/tlharris-public:/wordpress/wp-content/themes/tlharris-public" \
  --mount="$PWD/wp-content/plugins/tlharris-core:/wordpress/wp-content/plugins/tlharris-core" \
  --mount="$PWD/content:/wordpress/content" \
  --mount="$PWD/preview:/wordpress/preview"

# 3. In a second terminal. "Ready" is printed before the bootstrap has finished,
#    so wait for the stamp rather than for that line.
until curl -sf http://127.0.0.1:9410/tlharris-preview-stamp.json -o stamp.json && [ -s stamp.json ]; do sleep 3; done
node scripts/preview.mjs verify stamp.json HEAD
```

The stamp is a static file, so it needs no login. To fetch a page as an
anonymous visitor, send `-H 'Cookie: playground_auto_login_already_happened=1'`;
without it the first request is a redirect that logs in as `admin`.

This proves the runtime, the bootstrap and the tree, and it is the only way to
check a commit before it is pushed. It does **not** prove that Playground can
fetch the repository from GitHub, or how the hosted app behaves. That remains the
job of a pinned link after the push, and it is still the check to run before
visual QA is signed off.

## Reference run

Recorded on 2026-09-20 with a pinned link to `c3a4d68`
(`c3a4d6831c8dbab5aaf2ce259c1a03fd63d20fbc`), built by the hosted Playground:

| | Reported by the preview |
|---|---|
| WordPress | `7.1.1` |
| PHP | `8.2.33` |
| `SERVER_SOFTWARE` | `PHP.wasm` |
| Fingerprint | `0cc782b23cba9ad4d7af356027c98304b435a67a70e13990015e47209dc9494d` over 49 files |

`node scripts/preview.mjs fingerprint c3a4d68` gives the same fingerprint from
Git alone. A browser running PHP compiled to WebAssembly, and Node reading Git
objects, arrived at the same hash: that agreement is the evidence the method
works. (`c3a4d68` predates `preview/`, so this run was made with an inline probe
in place of the bootstrap, computing the same fingerprint.)

Recorded the same day for `75d0664` (`75d066456241ea9fef80861437425400d3fdd788`)
with `@wp-playground/cli` 3.1.54 and the checkout mounted, as described above. This
was **not** the hosted app; `75d0664` had not been pushed.

| | Reported by the preview |
|---|---|
| WordPress | `7.1.1` |
| PHP | `8.2.33` |
| `SERVER_SOFTWARE` | `PHP.wasm` |
| Fingerprint | `269f958fb0b9eab41169521875508838b00a89dd977a2e5e1048148ddf38733a` over 51 files |

`node scripts/preview.mjs verify` printed five PASS lines for it, and
`node scripts/preview.mjs fingerprint 75d0664` gives the same fingerprint from Git.

## Changing a pin

1. Edit `preferredVersions` in `blueprint.json`. Use a real version number,
   and update the table at the top of this page to match. The validator checks
   that the two agree.
2. `npm run validate:blueprint` and `bash scripts/validate.sh`.
3. Open a pinned link, read the stamp, run `verify`. It checks the stamp's
   WordPress and PHP against the blueprint, so a version Playground quietly
   substituted is caught.
4. Commit the change on its own.

## Troubleshooting

- **"Blueprint validation error" on load.** `blueprint.json` does not match
  Playground's schema. `npm run validate:blueprint` reports the same error
  locally. (`meta.author` is required.)
- **The preview shows older code than `main`.** A cache, usually. Use the pinned
  link and run `verify`; the fingerprint says which commit you actually got.
- **`git:directory` fails to load.** Playground can only fetch public
  repositories. This one is public on purpose; the strategy and decisions live
  in a separate private repository that no preview needs.
- **The stamp returns 404.** The bootstrap did not run, or you fetched from the
  wrong frame. It must be the inner, WordPress frame, which sits under a
  `/scope:...` path.
