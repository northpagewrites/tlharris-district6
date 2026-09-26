#!/usr/bin/env python3
"""Build the zero-install static preview pages from the real theme content.

The preview pages at the repository root (local-preview.html, about.html, ...)
let someone see the design in a browser without running WordPress or PHP.
They are generated, not hand-edited: page copy comes straight from the theme's
patterns in wp-content/themes/tlharris-public/patterns/, and anything WordPress
would pull from the database (priorities, meetings, schools) comes from the
content/*.json seed files. Edit those, then run:

    python3 tools/build-static-preview.py

The pages load static-preview.css (a stand-in for the styles WordPress builds
from theme.json) and then the theme's own style.css.
"""
from __future__ import annotations

import html
import json
import re
from datetime import date
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
THEME = ROOT / "wp-content/themes/tlharris-public"
PATTERNS = THEME / "patterns"

# Identity values WordPress reads from the tlharris-core settings.
IDENTITY = {
    "name": "T. L. Harris",
    "line": "Commissioner · District 6",
    "office_name": "Memphis-Shelby County Schools",
    "office_and_district": "Memphis-Shelby County Schools · District 6",
}

# WordPress routes and the static file that stands in for each.
ROUTES = {
    "/": "local-preview.html",
    "/about/": "about.html",
    "/priorities-progress/": "priorities.html",
    "/district-6/": "district-6.html",
    "/board-work/": "board-work.html",
    "/news/": "news.html",
    "/events/": "events.html",
    "/press/": "press.html",
    "/contact/": "contact.html",
    "/get-involved/": "get-involved.html",
}

NAV = [
    ("Home", "/"),
    ("About", "/about/"),
    ("Priorities", "/priorities-progress/"),
    ("District 6", "/district-6/"),
    ("The Board", "/board-work/"),
    ("News", "/news/"),
    ("Events", "/events/"),
]

MONTHS = ["January", "February", "March", "April", "May", "June", "July",
          "August", "September", "October", "November", "December"]


def readable_date(iso: str) -> str:
    d = date.fromisoformat(iso)
    return f"{MONTHS[d.month - 1]} {d.day}, {d.year}"


def pattern(name: str) -> str:
    """Pattern markup without its PHP header, exactly as WordPress seeds it."""
    raw = (PATTERNS / f"{name}.php").read_text()
    return re.sub(r"^<\?php[\s\S]*?\?>\s*", "", raw)


def to_static(markup: str) -> str:
    """Drop block comments and point site-root links at the static files."""
    out = re.sub(r"<!-- /?wp:[\s\S]*?-->\n?", "", markup)

    def link(m: re.Match) -> str:
        attr, url = m.group(1), m.group(2)
        path, _, frag = url.partition("#")
        if path in ROUTES:
            target = ROUTES[path] + (f"#{frag}" if frag else "")
            return f'{attr}="{target}"'
        if url.startswith("/wp-content/"):
            return f'{attr}="{url[1:]}"'
        return m.group(0)

    out = re.sub(r'(href|src|poster)="(/[^"]*)"', link, out)
    return out.strip() + "\n"


def replace_query(markup: str, rendered: str, index: int = 0) -> str:
    """Swap the index-th query loop in a pattern for a static rendering."""
    blocks = list(re.finditer(r"<!-- wp:query [\s\S]*?<!-- /wp:query -->", markup))
    m = blocks[index]
    return markup[: m.start()] + rendered + markup[m.end():]


def e(text: str) -> str:
    return html.escape(text, quote=True)


# ---------------------------------------------------------------------------
# Data from content/*.json
# ---------------------------------------------------------------------------
PRIORITIES = json.loads((ROOT / "content/priorities.json").read_text())["priorities"]
MEETINGS = json.loads((ROOT / "content/board-meetings.json").read_text())["meetings"]
SCHOOLS = json.loads((ROOT / "content/district-6-schools.json").read_text())["schools"]


def slug(text: str) -> str:
    return re.sub(r"[^a-z0-9]+", "-", text.lower()).strip("-")


def priority_card(p: dict, full: bool = False) -> str:
    link = f'priorities.html#{slug(p["title"])}'
    parts = [f'<div class="tlharris-card priority-card" id="{slug(p["title"])}">' if full else '<div class="tlharris-card priority-card">']
    if full:
        parts.append(f'<h3>{e(p["title"])}</h3>')
    else:
        parts.append(f'<h3><a href="{link}">{e(p["title"])}</a></h3>')
    parts.append(f'<p>{e(p.get("description", ""))}</p>')
    if full:
        if p.get("historical_target"):
            parts.append(
                '<blockquote class="tlharris-historical"><p>“' + e(p["historical_target"]) + '”</p>'
                '<cite>Campaign wording, kept as historical context. It is not a current commitment or a predicted outcome.</cite></blockquote>'
            )
        if p.get("why_it_matters"):
            parts.append(f'<p>{e(p["why_it_matters"])}</p>')
        if p.get("direct_control"):
            parts.append(f'<p><strong>What he controls</strong>{e(p["direct_control"])}</p>')
        if p.get("district_context"):
            parts.append(f'<p><strong>What depends on the district</strong>{e(p["district_context"])}</p>')
    parts.append("</div>")
    return "\n".join(parts)


def meeting_card(m: dict) -> str:
    comment = "Public comment taken" if m.get("public_comment") else "No public comment"
    loc = f'<p>{e(m["location"])}</p>' if m.get("location") else ""
    return (
        '<div class="tlharris-card">'
        f'<p class="tlharris-meta">{readable_date(m["date"])} · {e(m["time"])}</p>'
        f'<h3>{e(m["title"])}</h3>{loc}'
        f'<p class="tlharris-meta">{comment}</p>'
        '</div>'
    )


def meetings_grid() -> str:
    return '<div class="cards">\n' + "\n".join(meeting_card(m) for m in MEETINGS) + "\n</div>\n"


def school_lists() -> str:
    cols = []
    for level in ("Elementary", "Middle", "High"):
        rows = [s for s in SCHOOLS if level in s["levels"]]
        items = []
        for s in rows:
            tag = ' <span class="tag">K-8</span>' if len(s["levels"]) > 1 else ""
            items.append(f'<li><a href="{e(s["url"])}" rel="noopener">{e(s["name"])}</a>{tag}</li>')
        cols.append(f'<div><h3>{level} ({len(rows)})</h3><ul>' + "".join(items) + "</ul></div>")
    return '<div class="school-levels">' + "".join(cols) + "</div>\n"


# ---------------------------------------------------------------------------
# Shell
# ---------------------------------------------------------------------------
def header(active: str) -> str:
    items = []
    for label, route in NAV:
        cur = ' aria-current="page"' if route == active else ""
        items.append(f'<li><a href="{ROUTES[route]}"{cur}>{label}</a></li>')
    return f"""<a class="tlharris-skip" href="#main-content">Skip to content</a>
<header>
<div class="tlharris-utility has-ink-background-color has-white-color">
  <p>{e(IDENTITY["office_and_district"])}</p>
  <p><a href="https://www.scsk12.org/board/">MSCS Board of Education</a> · <a href="https://www.boarddocs.com/tn/scsk12/Board.nsf/Public">Board agendas</a></p>
</div>
<div class="tlharris-masthead">
  <div class="tlharris-brand">
    <figure class="tlharris-seal"><img src="wp-content/themes/tlharris-public/assets/images/tn-state-seal.svg" alt="Seal of the State of Tennessee"></figure>
    <div class="tlharris-wordmark-block">
      <p class="tlharris-wordmark"><a href="local-preview.html">{e(IDENTITY["name"])}</a></p>
      <p class="tlharris-wordmark-line">{e(IDENTITY["line"])}</p>
    </div>
  </div>
</div>
<nav class="tlharris-navbar has-ink-background-color" aria-label="Primary">
  <ul>{"".join(items)}</ul>
</nav>
</header>
"""


def footer() -> str:
    return f"""<footer class="tlharris-footer has-ink-background-color has-white-color">
<div class="tlharris-footer-cols">
  <div>
    <p class="tlharris-footer-name">{e(IDENTITY["name"])}</p>
    <p class="tlharris-meta">{e(IDENTITY["line"])}</p>
    <p class="tlharris-meta">{e(IDENTITY["office_and_district"])}</p>
  </div>
  <div>
    <p class="tlharris-eyebrow">Explore</p>
    <ul>
      <li><a href="about.html">About</a></li>
      <li><a href="priorities.html">Priorities</a></li>
      <li><a href="district-6.html">District 6</a></li>
      <li><a href="board-work.html">The Board</a></li>
      <li><a href="press.html">Press</a></li>
    </ul>
  </div>
  <div>
    <p class="tlharris-eyebrow">Constituents</p>
    <ul>
      <li><a href="events.html">Events</a></li>
      <li><a href="news.html">News</a></li>
      <li><a href="get-involved.html">Get Involved</a></li>
      <li><a href="contact.html">Contact</a></li>
    </ul>
  </div>
  <div>
    <p class="tlharris-eyebrow">Official resources</p>
    <ul>
      <li><a href="https://www.scsk12.org/board/">MSCS Board of Education</a></li>
      <li><a href="https://www.scsk12.org/board/?PN=45">MSCS Board Office</a></li>
      <li><a href="https://www.boarddocs.com/tn/scsk12/Board.nsf/Public">Board agendas (BoardDocs)</a></li>
    </ul>
  </div>
</div>
<div class="tlharris-footer-bottom"><p class="tlharris-meta">© T. L. Harris</p></div>
</footer>
"""


def document(title: str, description: str, active: str, main: str) -> str:
    full_title = "T. L. Harris" if title == "T. L. Harris" else f"{title} | T. L. Harris"
    return f"""<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{e(full_title)}</title>
<meta name="description" content="{e(description)}">
<!-- Generated by tools/build-static-preview.py. Edit the theme patterns or content/*.json, not this file. -->
<link rel="stylesheet" href="static-preview.css">
<link rel="stylesheet" href="wp-content/themes/tlharris-public/style.css">
</head>
<body>
{header(active)}<main id="main-content">
{main}</main>
{footer()}</body>
</html>
"""


def standard_page(title: str, body: str) -> str:
    return (
        f'<div class="wrap page-head"><h1>{e(title)}</h1></div>\n'
        f'<div class="wrap page-body">\n{body}</div>\n'
    )


# ---------------------------------------------------------------------------
# Pages
# ---------------------------------------------------------------------------
def home() -> str:
    slides = "\n".join(
        f'<figure class="tlharris-hero-slide tlharris-hero-slide-{i}"><img src="wp-content/themes/tlharris-public/assets/images/hero/hero-{i}.jpg" alt=""></figure>'
        for i in range(1, 6)
    )
    featured = [p for p in PRIORITIES if p["title"] in (
        "Early Literacy and Third-Grade Reading",
        "Safe and Supportive Learning Environments",
        "Public Performance Dashboard",
        "Quarterly Town Halls",
    )]
    priorities = replace_query(
        pattern("home-priorities"),
        '<div class="cards cards--4">\n' + "\n".join(priority_card(p) for p in featured) + "\n</div>\n",
    )
    meetings = replace_query(pattern("home-meetings"), meetings_grid())
    main = f"""<div class="tlharris-hero">
<div class="tlharris-hero-media">
{slides}
</div>
<h1 class="screen-reader-text">{e(IDENTITY["name"])}, {e(IDENTITY["line"])}</h1>
</div>
<div class="wrap" style="padding-top:clamp(1rem,2vw,1.5rem);padding-bottom:clamp(2rem,4vw,3rem)">
{to_static(pattern("home-facts"))}<div class="tlharris-intro">
<p class="tlharris-hero-tagline">The Standard Starts Now</p>
{to_static(pattern("page-home"))}</div></div>
<div class="tlharris-video-full">
<video controls playsinline preload="none" poster="wp-content/themes/tlharris-public/assets/video/wreg-informed-sources-extra-poster.jpg" src="wp-content/themes/tlharris-public/assets/video/wreg-informed-sources-extra.mp4"></video>
</div>
<p class="tlharris-video-caption"><strong>In the news</strong> T. L. Harris on WREG’s Informed Sources Extra</p>
<div class="wrap" style="padding-top:clamp(3rem,6vw,5rem);padding-bottom:clamp(3rem,6vw,5rem)">
{to_static(pattern("home-meet"))}</div>
{to_static(pattern("home-standard"))}<div class="wrap" style="padding-top:clamp(3rem,6vw,5rem);padding-bottom:clamp(3rem,6vw,5rem)">
{to_static(priorities)}</div>
<div class="wrap" style="padding-bottom:clamp(3rem,6vw,5rem)">
{to_static(meetings)}</div>
<div class="wrap" style="padding-bottom:clamp(3rem,6vw,5rem)">
{to_static(pattern("home-explore"))}</div>
"""
    return document(
        "T. L. Harris",
        "T. L. Harris, Commissioner for District 6 on the Memphis-Shelby County Schools Board of Education.",
        "/",
        main,
    )


def about() -> str:
    main = f"""<div class="wrap tlharris-profile" style="padding-top:clamp(2.5rem,6vw,5rem);padding-bottom:clamp(2rem,4vw,3rem)">
<div class="tlharris-profile-text">
<h1 class="tlharris-hero-name">About T. L. Harris</h1>
<p class="tlharris-hero-role">{e(IDENTITY["line"])}</p>
<p class="tlharris-meta">{e(IDENTITY["office_name"])}</p>
</div>
<figure class="tlharris-profile-photo"><img src="wp-content/themes/tlharris-public/assets/images/hero/hero-5.jpg" alt="Speaking at a podium, microphone in hand"></figure>
</div>
<div class="wrap page-body">
{to_static(pattern("page-about"))}</div>
"""
    return document("About T. L. Harris", "Biography of T. L. Harris, District 6 commissioner on the Memphis-Shelby County Schools Board of Education.", "/about/", main)


def board() -> str:
    body = to_static(pattern("page-board-work"))
    body += '<section class="page-section tlharris-keyline" id="meetings"><h2>Upcoming meetings</h2>'
    body += '<p>From the district\'s published calendar. Confirm details on the MSCS site before you go.</p>'
    body += meetings_grid() + "</section>\n"
    body += '<section class="page-section tlharris-keyline">' + to_static(pattern("board-work-meetings")) + "</section>\n"
    return document("The Board", "How the Memphis-Shelby County Schools Board of Education works, upcoming meetings and public comment.", "/board-work/", standard_page("The Board", body))


def priorities() -> str:
    rows = "\n".join(
        f'<li><div class="tlharris-plist__body"><h3>{e(p["title"])}</h3><p>{e(p.get("description", ""))}</p></div>'
        f'<div class="tlharris-plist__cat">{e(p["category"])}</div></li>'
        for p in PRIORITIES
    )
    main = (
        '<div class="wrap page-head"><h1>Priorities &amp; Progress</h1></div>\n'
        '<div class="wrap" style="padding-bottom:clamp(2rem,4vw,3rem)">\n' + to_static(pattern("page-priorities-progress")) + '</div>\n'
        + to_static(pattern("priorities-quarterly"))
        + '<div class="wrap" style="padding-top:clamp(3rem,6vw,5rem);padding-bottom:clamp(3rem,6vw,5rem)">\n'
        + '<h2 class="tlharris-keyline">The eight priorities</h2>\n'
        + f'<ol class="tlharris-plist">\n{rows}\n</ol>\n</div>\n'
        + '<div class="wrap page-body">\n' + to_static(pattern("priorities-how-measured"))
        + '<p class="tlharris-source">Sources: MSCS Board of Education; T. L. Harris campaign platform, voteharris901.com (campaign wording only).</p>\n</div>\n'
    )
    return document("Priorities & Progress", "The eight priorities T. L. Harris is working on for District 6, with progress reported every quarter.", "/priorities-progress/", main)


def district6() -> str:
    body = to_static(pattern("page-district-6"))
    body += '<section class="page-section tlharris-keyline">' + to_static(pattern("district-6-find-your-school")) + "</section>\n"
    body += '<section class="page-section tlharris-keyline"><h2>Schools in District 6</h2>'
    body += "<p>Three K-8 schools appear under both elementary and middle.</p>"
    body += school_lists() + "</section>\n"
    body += '<section class="page-section tlharris-keyline">' + to_static(pattern("district-6-resources")) + "</section>\n"
    return document("District 6", "Schools and resources in District 6 of Memphis-Shelby County Schools.", "/district-6/", standard_page("District 6", body))


def news() -> str:
    return document("News & Updates", "Updates from the office of T. L. Harris and news coverage.", "/news/", standard_page("News & Updates", to_static(pattern("page-news"))))


def events() -> str:
    body = to_static(pattern("page-events"))
    body += '<section class="page-section tlharris-keyline"><h2>Upcoming</h2>' + meetings_grid()
    body += '<p><a href="board-work.html#meetings">Full meeting schedule and agendas</a></p></section>\n'
    body += '<section class="page-section tlharris-keyline"><h2>Past events</h2><p>Past events will be archived here with summaries and follow-up actions.</p></section>\n'
    return document("Events", "Board meetings and community events in District 6.", "/events/", standard_page("Events", body))


def simple(name: str, title: str, route: str, description: str) -> str:
    return document(title, description, route, standard_page(title, to_static(pattern(name))))


PAGES = {
    "local-preview.html": home,
    "about.html": about,
    "board-work.html": board,
    "priorities.html": priorities,
    "district-6.html": district6,
    "news.html": news,
    "events.html": events,
    "press.html": lambda: simple("page-press", "Press & Media", "/press/", "Media resources for T. L. Harris."),
    "contact.html": lambda: simple("page-contact", "Contact", "/contact/", "How to reach the office of T. L. Harris."),
    "get-involved.html": lambda: simple("page-get-involved", "Get Involved", "/get-involved/", "Ways to take part in public education in District 6."),
}


def main() -> None:
    for filename, build in PAGES.items():
        out = build()
        leftover = re.findall(r'href="/(?!/)[^"]*"', out)
        if leftover:
            raise SystemExit(f"{filename}: unmapped site links {sorted(set(leftover))}")
        (ROOT / filename).write_text(out)
        print(f"wrote {filename}")


if __name__ == "__main__":
    main()
