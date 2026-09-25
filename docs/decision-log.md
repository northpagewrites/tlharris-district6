# Decision log

Resolved choices that change how the site is built or what it says. Newest first.

## 2026-09-25 — Title, colours and design system

**Public title is "Commissioner".** Directed by the office (Chris Dorsey, web lead).
The MSCS District 6 profile (S9) titles the seat "Board Member" and does not use
"Commissioner"; this is a presentation choice by the office, not a sourced fact.
Implemented as configuration: the `role_title` default in `tlharris-core` is now
`Commissioner`, so templates keep reading it through the `tlharris/identity`
binding. Page copy says "commissioner" where it used to say "Board member".
Left verbatim: the names of MSCS bodies and pages (Board of Education, Board
Office, Addressing the Board) and the headline of a news article. The validator
now rejects a hardcoded "Commissioner · District 6" in templates the same way it
rejects "Board Member · District 6". An existing install keeps whatever title is
saved on the T. L. Harris Identity screen; change it there.

**Palette uses the MSCS brand colours** (S14): red #C41230 as the accent, blue
#005DAB for the navigation bar and links, a deep navy #041132 for text and dark
bands, white paper. The MSCS logo is not used. The validator's contrast checks
were extended to cover white on blue, white on red, and blue links.

**Design system.** One sans family (Helvetica Neue stack, no web fonts to load),
left-aligned 12-column layout, square buttons, hairline rules with a short red
bar opening each section. Removed: the gold gradient rule, the glow gradients on
dark bands, scroll-in animations, card and button lift effects, the icon tile
grid, and tracked-capital labels above every heading. `theme.json` now sets
`defaultFontSizes: false`; before this, WordPress core's presets silently
replaced the theme's font sizes.

**Tennessee state seal removed from the header.** The seal belongs to the State of
Tennessee and this is not a state office. The header now uses the name as the
wordmark. The SVG file is still in `assets/images/` and is not referenced.

**Tennessee state seal put back, top-left of the header (Chris's call, Sept. 25).**
Chris asked for it after the note above. It sits left of the name in the masthead.
The concern above still stands, so the office must approve it in writing before
launch. To take it out: delete the `tlharris-brand` seal image in
`parts/header.html` and the `<figure class="tlharris-seal">` line in
`tools/build-static-preview.py`, then rebuild.

**Homepage is about the person first.** Order: hero, four sourced facts, the
Report to District 6 video, "Meet T. L. Harris", priorities, get help, upcoming
meetings, site index. Section copy lives in `patterns/home-*.php`, not in the
template.

**Static preview pages are generated.** `python3 tools/build-static-preview.py`
builds `local-preview.html`, `about.html` and the other root pages from the
theme patterns and `content/*.json`, with `static-preview.css` standing in for
the styles WordPress builds from `theme.json`. Do not hand-edit those pages.
