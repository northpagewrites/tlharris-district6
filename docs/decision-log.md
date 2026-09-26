# Decision log

Resolved choices that change how the site is built or what it says. Newest first.

## 2026-09-25 (newest) — Site copy is in T. L. Harris's own voice

Chris's call: the site should read as his page, not as a page written about him. Copy across
the theme is now first person ("I", "my") instead of "he", "his" and "T. L. Harris": Home
(meet section, "My goals for District 6", explore cards, video caption "My interview on WREG's
Informed Sources Extra"), About (retitled "About Me"), Priorities intro and "How each report
works", The Board, Press, Events, News, District 6, and the Privacy, Terms and Accessibility
pages. The priority records (`content/priorities.json`, `direct_control`, `role`,
`district_context`) and the editor field labels in `tlharris-core` ("What I directly
control", "What I can influence") follow the same voice. His name still appears where a name
belongs: header, footer, page titles, photo captions and the About heading. Wording stays
limited to facts already sourced; nothing new was claimed. First person puts words in his
mouth, so he or the office must read and approve the copy before launch (the quote and the
"I have said" line on About are his own words from the sources). Existing WordPress pages do
not update on their own, so the office must re-seed or edit page content in WP admin. Recorded
in `CLAUDE.md` Content rules so later edits keep the voice.

## 2026-09-25 — Quarter months, legal pages, Facebook link, share preview

Chris's calls, four changes. (1) Progress reports now name their months: the start of each
quarter, January, April, July and October (Q1 to Q4). The Priorities page band, the "How each
report works" Timing row and the homepage line say so. The office should confirm it will
publish on that schedule before launch, since the first quarter start after taking office is
October 2026. (2) Privacy, Terms and Accessibility pages now have standard text
(`patterns/page-privacy.php`, `page-terms.php`, `page-accessibility.php`), built into the
static preview and the preview bootstrap. They are standard wording, not lawyer-written:
the office or counsel should read them. They say the site has no accounts, forms or
advertising trackers, so update the Privacy page the day a contact form, sign-up form or
analytics is added. They point to the MSCS Board Office for questions until the office has
its own contact. The Terms page says who publishes the site and that official MSCS sources
control; that is the only place that idea now appears. (3) The footer links to the Facebook
page "Vote Harris 901 | Memphis TN" (https://www.facebook.com/p/Vote-Harris-901-61588380617604/),
labeled "Follow on Facebook". That is a campaign-named page, and this project keeps
campaign and constituent work separate (see the Support page), so the office or campaign
counsel should confirm the link is fine on this site. (4) Link previews: `functions.php`
now adds Open Graph and Twitter tags, using `assets/images/share.jpg` (1200 by 630, cropped
from the swearing-in photo), so shared links show a picture, title and description. The
static preview pages do not carry these tags because the image link must be a full web
address.

## 2026-09-25 — Hero is photos only; site notice removed

Chris's call, two changes. (1) The homepage hero is now the photographs only: no text on
them and no dark band under them (a first try with a dark band below was taken out the same
day, and a second try with the text above the facts was moved too). Just a thin gold line
under the photos. "The Standard Starts Now" and "Working for the students, families and
schools of District 6, from Whitehaven across Southwest Memphis." sit directly under the
Sources line below the four facts, in the space that was already there. The "Meet T. L.
Harris" button was dropped (About is in the menu). The name and title are already in the
header; the page keeps a hidden page title for screen readers. The photos are back at their
original full size (86% of the window height, up to 800px, edge to edge); a shrunk, uncropped
version was tried and dropped because Chris wanted them big. The white area starts right
under the photos. (2) Removed
the footer line "This
website is published by the office of T. L. Harris and is not an official Memphis-Shelby
County Schools website." It was on every page. The `official_site_notice` field is still in
the plugin, unused, so it can be put back by adding the identity paragraph back to
`parts/footer.html`. Open point for the office: with the Tennessee seal in the header and no
notice, a visitor could take this for an official MSCS site. The office should confirm it is
comfortable without the line before launch.

## 2026-09-25 — Homepage video is the WREG interview

Chris's call. The homepage video band now plays T. L. Harris's interview on WREG's
"Informed Sources Extra" (file: `assets/video/wreg-informed-sources-extra.mp4`, with a
poster frame beside it). It came from a phone screen recording, so it was re-encoded
to a 14 MB web MP4 (720p, H.264). The caption reads "In the news", not "Report to
District 6", because it is a TV station's broadcast, not his own update. The band now
shows the full 16:9 picture instead of a wide crop, so the station's name bar and the
top of his head are not cut off. On-screen title in the clip is "MSCS School Board
Member, Dist. 6" (WREG's wording). Still needed before launch: WREG's or the
office's OK to host the clip (or swap in a link to WREG's own page), and captions for
accessibility. The "Report to District 6" video slot is free again for his own update.

## 2026-09-25 — Priorities page redesigned; status labels no longer shown

Chris's call. The Priorities & Progress page now lists all eight priorities as a numbered
list and states that progress reports are published four times a year, once every
quarter. Removed from every public page: the status labels (including "Not Started"),
the "Accountability at a glance" counts, the "Recently updated" and "Updates due"
sections, and the "not yet documented" style fallbacks on each priority's own page,
which now say the item is reported each quarter. This replaces the 2026-09-21 office
decision to show the seeded status neutrally. The status field stays in the data.
No priority is labelled "In Progress" or "Completed" until a recorded action or
document supports it. Campaign targets and the "what a commissioner controls" detail
stay on each priority's own page and are not on the list page. The office should
confirm the quarterly statement and the new wording before launch.

## 2026-09-25 (latest) — Get Help removed from the site

Chris's call. Removed: the menu link, the footer link, the homepage "Need help with a
school issue?" band, the "Find your way around" entry, the Get Help page itself
(template, page and resources patterns, static preview page, and its entry in
`preview/bootstrap.php`), and every button or link that pointed to it (Contact,
Support, The Board, District 6 resources). The Get Involved button "How to speak at a
meeting" now goes to the MSCS "Addressing the Board" page. The Contact page still lists
the Board Office phone and email. The open "Get Help contact form" decision is moot.
Do not re-add a `/get-help/` route without asking Chris.

## 2026-09-25 (later) — Original colours back; slogan and four phrases added

**Colours are back to the original palette.** Chris's call. Ink #16222C, warm paper
#F7F5F1 and gold #946E2F as the accent, with muted #5B6873, line #DCE1E4 and soft
#EEF1F2. This replaces the MSCS red and blue palette in the entry below. The `blue`
palette slug is gone: the navigation bar and links use ink, buttons and the short
section bars use gold. The layout and design system in the entry below are unchanged.

**Slogan and phrases from the campaign site (S11), wording only.** Hero tagline "The
Standard Starts Now"; a dark band on the homepage headed "Raising the Standard for
District 6" with "Safe schools. Strong academics. Fiscal discipline. Community
partnership."; one sentence on the Priorities page that names "The Third Grade
Standard" as campaign wording kept as history. Not carried over: Donate, Volunteer,
Join Team Harris, the endorsement quote, and the third-grade dropout statistic (no
cited source; the same reason the `why_it_matters` lines were removed). The office
should approve the slogan wording before launch.

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
