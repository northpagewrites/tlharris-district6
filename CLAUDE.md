# CLAUDE.md — T. L. Harris Public-Service Website

## Project goal
Build and maintain the T. L. Harris public-service website as a modern, accessible, fast, source-controlled WordPress site for the District 6 office.

## Source of truth
The repository is the source of truth for code, content architecture, design system, documentation, decisions, and agent handoffs. The live site is a separate production system and must not be treated as the place to invent or store requirements.

Git is now initialised. Work is exchanged as commits and diffs, never as replacement ZIP archives: a ZIP silently overwrites whatever the other collaborator changed.

## Working model
ChatGPT and Claude are both collaborators, with a division of labour recorded in `docs/decision-log.md`:

- **Claude** owns the codebase, implementation and testing. It can read the repository and run the validators.
- **ChatGPT** owns architecture, content, UX, requirements and strategy.
- **The office** owns every factual and legal decision. See `docs/open-decisions.md`.

Read this file plus `docs/` before changing architecture. Strategy, the full decision log and the open office decisions live in the private project repository. Keep changes small, reversible, and documented. Record resolved architecture choices in `docs/decision-log.md`.

## Current positioning
- Public-service/officeholder site, not a campaign site.
- Primary identity: name first. The current role is configuration, not brand.
- Primary experience: constituent service, Board work, priorities/progress, District 6 resources, events, news, and public record.
- Any future campaign/support layer must remain clearly separated from constituent-service content.
- Do not use district resources, staff, email, devices, logos, records, or public-service case data as campaign assets.

## Build order
1. Live-site week-one cleanup runs in parallel; see `docs/live-site-week-one.md`.
2. Get Help, then Board Work, then District 6 — grounded in official MSCS sources and office workflow.
3. Priorities & Progress, Events, News/Report to District 6, Press.
4. About as a verified shell until credentials, experience and portrait are approved.
5. `/my-story/` stays a reserved draft/noindex route. Do not publish it without written approval from the office.

## Content rules
- Do not invent current votes, meeting dates, school data, endorsements, credentials, accomplishments, or event details.
- Prefer official MSCS sources for Board schedules, agendas, school information, Board role, and district data.
- **Do not assert anything about the structure of the office that is not on a cited page.** A claim about Board members' offices and employment status was carried for a full version without being on any cited source, and it shaped public copy. If a source cannot be produced, the claim is removed, not softened.
- Re-check date-sensitive official sources immediately before publishing, and record the date in `tlharris_last_verified`.
- Treat "Priorities & Progress" as an accountability system: baseline, target, Harris's role/control, action taken, status, evidence, next update.
- Do not imply that one Board member controls district-wide outcomes that require collective Board or district action.
- Do not publish sensitive constituent case information or unnecessary student-identifying information.
- Collect only the minimum constituent data needed for the approved workflow.
- Never reuse constituent-service data for campaign communications.
- Never promise a response time the office has not approved and cannot consistently keep.
- **No internal build notes in public copy.** "Placeholder", "to be populated", "pending legal review" and similar belong in `docs/`, never on a page. `scripts/validate.sh` fails the build on them.

## Identity and future-proofing
- The public role is configuration. Read role title, office, district, tagline, official links and approved public contact fields from the `tlharris-core` identity settings.
- Templates read identity through the **`tlharris/identity` block binding**, not through hardcoded text and not through the shortcode. The shortcode is retained only so older content does not break.
- Never restate the role in template prose. The validator fails on `Commissioner · District 6`, `Commissioner for District 6` and `Memphis-Shelby County Schools Commissioner` (and the older `Board Member` forms) appearing in a template or part.
- The public title is "Commissioner" by office decision (2026-09-25, `docs/decision-log.md`). Official MSCS names (Board of Education, Board Office) stay as MSCS writes them.
- The name-first identity should survive a legitimate future public-role change without a theme rebuild.

## Newsletter and communications
- Public-service newsletter consent must be separate from campaign consent.
- Newsletter signup must use double opt-in once the provider is selected.
- Constituent-service messages and campaign text/email lists must remain separate systems with separate consent.

## Photography and evidence
- Use real photography only for factual/public-service representation.
- Do not use synthetic images to depict real schools, children, events, constituents, or public interactions.
- Obtain appropriate releases, especially for minors.
- Start the Proof Library immediately; see `proof-library/README.md`.
- **Evidence is never committed to Git.** The root `.gitignore` keeps the Proof Library contents out. Originals live in the office's private storage.

## Design rules
- Name-first identity; avoid campaign-first "Vote" treatment in public-service pages.
- Mobile-first, accessible, editorial civic design.
- Respect reduced-motion preferences.
- Colour contrast is enforced by the validator against the `theme.json` palette. Do not change a palette colour without re-running it.
- Prefer semantic HTML and WordPress core blocks.

## Technical architecture
- WordPress block theme: `wp-content/themes/tlharris-public/`
- Core content plugin: `wp-content/plugins/tlharris-core/`
- Custom post types, metadata and identity settings live in the plugin, not the theme, so content survives a theme change.
- **Templates are layout. Page copy lives in page content**, seeded from the patterns in `wp-content/themes/tlharris-public/patterns/`. A `page-*.html` template provides the shell, the H1 via `wp:post-title`, and `wp:post-content`. Hardcoding copy in a template makes the Pages screen a lie and forces every edit through the Site Editor, which saves outside Git.
- **All custom post types use `has_archive => false`.** A CPT archive registers its rewrite rule at `top` priority and shadows a Page with the same slug. Curated page templates own the listings.
- **Post meta keys are never underscore-prefixed.** Core's post-meta block binding calls `is_protected_meta()` and returns null for `_`-prefixed keys, so protected meta can never be displayed. Use `tlharris_*`.
- Every public-record entry carries `tlharris_source_url` and `tlharris_last_verified`.
- Query loops that need date or taxonomy logic opt in with a `className` handled by `query_loop_block_query_vars` in the plugin. Never filter by term ID: IDs are not portable between environments.
- The `className` goes on the **Query block**, and `tlharris_pass_query_class()` copies it down as block context, because core hands `query_loop_block_query_vars` the Post Template or pagination block, never the Query block. Without that, every filtered list silently shows the unfiltered query. Test a list by rendering the page, not by calling the filter with a fake block: the fake block is what hid this.
- Inside a query loop, per-item data comes from a **block binding** (`tlharris/date`), never a shortcode. Core expands shortcodes in the raw template before any block renders, so a shortcode in a card sees the page, not the card's record. A shortcode whose output spans several lines goes in a **Custom HTML** block (`wp:html`), not a Shortcode block: core runs `wpautop()` on Shortcode block content and leaves stray `</p>` tags. `scripts/validate.sh` enforces both.
- JavaScript must remain minimal. The site ships none; the Node packages are build-time validation only.
- `theme.json` is the design-system source of truth.
- Elementor is not a dependency. If the office chooses Elementor later for staff workflow reasons, record the decision before changing architecture.

## Static preview pages
- `local-preview.html`, `about.html` and the other root `.html` pages are generated by `python3 tools/build-static-preview.py` from the theme patterns and `content/*.json`. Edit the source, then rerun it. Never hand-edit the generated pages.

## Preview (WordPress Playground)
- `blueprint.json` builds the preview from **one** checkout of this repository, with WordPress and PHP pinned. Never `latest`, never a second fetch, never a release zip. There is no build step to go stale.
- Preview-only seeding lives in `preview/`, outside `wp-content/`. It runs only inside Playground and is the only place drafts are published. The plugin's importers create drafts and never publish; the production import workflow does not change for the preview's sake. `scripts/validate.sh` fails if either rule breaks.
- Before visual QA, prove the preview is the commit under test, and put the short SHA in the QA notes. The procedure is in `docs/preview-workflow.md`: `node scripts/preview.mjs url <sha>`, read the stamp, `node scripts/preview.mjs verify stamp.json <sha>`.
- A commit that is not pushed cannot be loaded by the hosted Playground. `docs/preview-workflow.md` ("Before a push") shows how to run the same runtime locally with the checkout mounted and verify the stamp the same way. It does not replace the pinned-link check after the push.

## Content types
`board_update`, `board_action`, `board_document`, `committee`, `priority`, `event`, `district_school`, `press_item`.

Taxonomies: `progress_status` (seeded: Not Started, Monitoring, In Progress, Completed, On Hold), `update_type` (seeded: Board Update, Report to District 6, Statement, Community, Education), `board_topic`.

## Site identity settings
See `docs/site-fields.md`.

## Important routes/pages
`/`, `/about/`, `/district-6/`, `/board-work/`, `/priorities-progress/`, `/news/`, `/events/`, `/get-help/`, `/press/`, `/contact/`, `/support/`, `/privacy/`, `/terms/`, `/accessibility/`

- `/get-involved/` — non-primary route for civic/community participation, not campaign canvassing.
- `/my-story/` — reserved draft/noindex route. The plugin stores its page ID and excludes it from robots and the sitemap, so renaming the slug cannot silently un-reserve it.

## Current official source starting points
See `docs/content-sources.md`. Every source is numbered (S1–S8) with an access date, and each page records which sources it draws on.

Two corrections are logged there, both from the same mistake: checking a page for one thing and concluding something about what else was on it. **Ask a source what it says. Do not infer what it does not say from a summary written for a different question.**

Verified facts are transcribed into `content/*.json` and imported into the CMS by the plugin's importer, never hardcoded into a template. **Everything imports as a draft**: a transcription is not a verification, and a human checks each entry against the live page before publishing.

## Before editing
1. Read this file.
2. Read `docs/content-sources.md` and `docs/staging-setup.md`. If the change is to be reviewed in the preview, read `docs/preview-workflow.md` too.
4. Inspect affected files.
5. Prefer small changes with clear diffs.

## Before deployment
```bash
npm install            # once; enables block validation
bash scripts/validate.sh
```

Then work `docs/deployment-checklist.md` by hand. The validator covers required files, PHP syntax, block validity, the preview blueprint (schema, pinned versions, one checkout, isolation of preview-only code, drafts-only importers, PHP and Node fingerprints agreeing), skip-link targets, hardcoded role text, build notes in public copy, synthetic image references, and colour contrast. It does not cover anything that needs a running site or a human.

A validator that passes is not a site that is ready. v2's validator passed while the theme carried 43 invalid blocks.

## Never do without explicit approval
- Push directly to production.
- Delete production content.
- Change DNS/domain records.
- Change campaign finance or legal language as a matter of legal interpretation.
- Publish biographical content that the office has not approved in writing.
- Activate constituent forms or newsletter services without an approved destination, consent workflow, and owner.
- Commit anything from the Proof Library.

## Preferred workflow
feature branch -> review -> staging -> QA -> production.

## Agent handoff format
At the end of a substantial change, summarise:
- changed files;
- tests run and their result;
- unresolved decisions;
- any production steps still required.
