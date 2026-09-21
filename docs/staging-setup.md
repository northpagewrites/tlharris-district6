# Staging Setup

Order matters. The plugin registers the content types and the reserved route, so
it is activated before the theme is configured.

## 1. Install

1. WordPress 6.7 or later on PHP 8.1 or later.
2. Copy `wp-content/plugins/tlharris-core` into the site's plugins folder and
   **activate it**. Activation seeds the status vocabularies, creates the
   reserved `/my-story/` page as a draft, and flushes rewrite rules.
3. Copy `wp-content/themes/tlharris-public` into the site's themes folder and
   activate it.

## 2. Configure identity

**Settings → T. L. Harris Identity.** Fill in at least the role, office,
district, `role_source_url` and `role_last_verified`. Templates read these
values; nothing about the current office is hardcoded in the theme.

Set the WordPress **Site Title** to the display name, because the header and
footer use the site title block.

## 3. Create the pages

Create a Page for each route below and insert the matching pattern from the
**T. L. Harris** pattern category, then edit the copy in place. The pattern is
the seed; the page is the source of truth afterwards.

| Page title | Slug | Pattern |
|---|---|---|
| Home | `home` | Homepage — hero content |
| About | `about` | About — page content |
| District 6 | `district-6` | District 6 — page content |
| Board Work | `board-work` | Board Work — page content |
| Priorities & Progress | `priorities-progress` | Priorities & Progress — page content |
| News & Updates | `news` | News & Updates — page content |
| Events | `events` | Events — page content |
| Get Help | `get-help` | Get Help — page content |
| Press | `press` | Press & Media — page content |
| Contact | `contact` | Contact — page content |
| Get Involved | `get-involved` | Get Involved — page content |
| Support | `support` | Support — page content |
| Privacy | `privacy` | — approved legal copy required |
| Terms | `terms` | — approved legal copy required |
| Accessibility | `accessibility` | — approved copy required |

`My Story` already exists as a draft. Leave it that way.

Templates attach by slug (`page-get-help.html` serves the page at `get-help`),
so the slugs above must match exactly.

### Page titles drive the H1

Each page template takes its H1 from the page title, so the title is what a
visitor reads. If the office wants the Get Help H1 to be a question, set that
page's title to "Need Help With a School or District Concern?" — the navigation
label is defined separately in `parts/header.html` and stays "Get Help".

## 3a. Import the verified content

**Public Record → Import Verified Content → Import now.** This loads the District
6 school list and the Board meeting list from `content/*.json` into the CMS as
`district_school` and `event` entries. Running it again updates rather than
duplicates. With WP-CLI: `wp tlharris import`.

**Everything imports as a draft, on purpose.** The school list was transcribed
from the district's District 6 profile page. Before publishing any of it, open
that page alongside the drafts and check each name and link. Publish them one by
one as you confirm them; the three school sections on the District 6 page fill in
as you go, and show an empty state until then.

The same applies to the three imported Board meetings. Meeting listings go stale,
so check them against the district's calendar before publishing and prefer adding
later meetings directly in the CMS rather than editing the JSON.

The eight priorities import the same way. Each one opens as **Not Started** with
no baseline, target, date, action or evidence, and the detail page says so in
plain words wherever a field is empty. **Not Started** here means that nothing
has been recorded yet; it does not say that no work has happened. Fill the
priorities in as real figures and real actions arrive, each with its own source
URL and verification date. A progress bar appears only once a numeric baseline,
current value and target all exist **and** an evidence link to their source is
recorded; with numbers but no link, the page says the source is missing.

Two lists on the Priorities & Progress page depend on dates you enter. A
priority appears under **Recently updated** (and in the homepage's priorities
section) only once it has a *Last verified* date, and under **Updates due** only
once it has a *Next update due* date. An update date that has passed stays in
the list, at the top. Until dates are entered, those lists correctly show that
nothing is recorded.

Four of the eight priorities have no "Why it matters" text and show "Not yet
documented." Each of the removed sentences asserted an education outcome without
a cited source. Write your own, and cite it. `content/priorities.json` explains
this in `_why_it_matters_note`. If you imported the priorities before this
change, those four sentences are still stored on the records: re-importing does
not clear a field the file no longer contains, so delete them by hand.

**Import the priorities once.** For priorities, running the import again
overwrites the title, excerpt and governance text with the file's version and
sets the status back to Not Started. That is right for a first import and wrong
once the office has edited or updated anything. After that, add or change
priorities in the CMS.

Every priority is flagged as an originally-2026-campaign subject, and that flag
is unverified: it was set from the campaign platform as described in
`docs/content-sources.md`, which is not in the repository. Check each flag
against the archived campaign site. Nothing from the campaign is displayed as
text yet, because the original wording (`historical_target`) is empty on all
eight.

If you are upgrading an install created before this version, the status
vocabulary changed: "Not started" and "In progress" were renamed to "Not Started"
and "In Progress", and "On Hold" was added. Rename the existing terms by hand
under Priorities & Progress → Progress Status; the seeder only creates terms that
are missing, so it will not rename them for you.

## 4. Set the front page

**Settings → Reading → A static page → Home.** The `front-page.html` template
takes over, and the page's own content supplies the hero paragraph and buttons.

## 5. Navigation

The header ships with the eight primary links defined in the template file, so
there is nothing to build in the admin. If you edit navigation in the Site
Editor, WordPress saves a database copy that overrides the file — export the
change back to `parts/header.html` or the repository stops being the source of
truth.

## 6. Before anything goes public

Run the validator and work the checklist:

```bash
npm install          # once, enables block validation
bash scripts/validate.sh
```

Then `docs/deployment-checklist.md`. Nothing is published until the office has
approved the content, and no page goes live carrying unverified claims.
