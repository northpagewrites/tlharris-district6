# Site-Level Identity Fields

The current role is configuration, not brand. These fields live in the
`tlharris-core` plugin, under **Settings → T. L. Harris Identity**.

## Fields

| Field | Type | Purpose |
|---|---|---|
| `display_name` | text | Name-first identity |
| `role_title` | text | Current public role |
| `office_name` | text | Office or institution |
| `district` | text | District |
| `tagline` | text | Tagline |
| `role_source_url` | url | Official source confirming the role |
| `role_last_verified` | date | When that source was last checked |
| `public_email` | email | Approved public contact address |
| `mailing_address` | textarea | Public mailing address |
| `board_home_url` | url | MSCS Board home |
| `board_office_url` | url | MSCS Board Office |
| `board_docs_url` | url | BoardDocs |
| `board_office_phone` | text | MSCS Board Office phone |
| `board_office_email` | email | MSCS Board Office email |
| `site_operator` | text | Who publishes this site |
| `official_site_notice` | textarea | Statement that this is not an official MSCS site |
| `primary_domain` | url | Canonical domain |

## How templates read them

Through the `tlharris/identity` block binding, never by hardcoding:

```html
<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"tlharris/identity","args":{"key":"line"}}}}} -->
<p></p>
<!-- /wp:paragraph -->
```

Keys are the field names above, plus three derived values:

- `line` — role and district joined, e.g. "Board Member · District 6"
- `name_and_line` — display name with the line appended
- `office_and_district` — office and district joined

The `[tlharris_identity part="…"]` shortcode still works so existing content does
not break, but new templates should use the binding: a shortcode shows as raw
text in the editor, carries no styling hook, and cannot supply a URL.

## Why it matters

`scripts/validate.sh` fails the build if a template restates the role in prose.
The site should survive a legitimate future change of public role by editing
these fields, not by rebuilding the theme.

## Two sources of truth to resolve

The header and footer use `wp:site-title`, which reads **Settings → General**,
while the plugin also stores `display_name` and `tagline`. Pick one owner:
core for name and tagline, the plugin for role, office, district and links.
Until that is decided, set the WordPress Site Title to the display name so the
two agree. Tracked in `docs/open-decisions.md`.
