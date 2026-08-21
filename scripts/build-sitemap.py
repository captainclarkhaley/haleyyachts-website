#!/usr/bin/env python3
"""
build-sitemap.py - regenerate sitemap.xml from what is actually on the site.

WHY THIS EXISTS
---------------
sitemap.xml used to be maintained by hand, and it quietly fell two months
behind. The Article Manager publishes an article, writes the page, and registers
it in articles/articles-data.js - but it never touched the sitemap. So every
article published after mid-June 2026 was missing from it, along with two yacht
listing pages. Nothing was broken and nothing complained; the file just decayed.

A hand-maintained list of every page on a growing site will always end up like
that. This reads the site instead:

    Core pages     a fixed list below, with the priorities the old file used
    Yacht listings everything in yachts/*.html
    Articles       articles/articles-data.js, which the Article Manager already
                   maintains as the single source of truth for what is published

Anything on disk but not registered - a draft, a leftover test file - is left
out on purpose and reported, so an unregistered page is visible rather than
silently published to Google.

PUBLISHING NO LONGER NEEDS THIS
Since 2026-08-21 the Article Manager updates the Articles section of the sitemap
itself, as part of publishing, so nobody has to remember. Its builder is
`updateSitemapViaGitHub()` in admin/article-manager.html and it produces
byte-identical output to this script for that section - same entry shape, same
sort (date descending, then path). CHANGE ONE, CHANGE THE OTHER, and run
`--check` to prove they still agree.

This script is still what you run when something OTHER than an article changes:
a new yacht listing page, a new core page, or an article removed by hand. It is
also the safety net if a publish fails halfway.

USAGE
    python3 scripts/build-sitemap.py            rewrite sitemap.xml
    python3 scripts/build-sitemap.py --check    exit 1 if it is out of date

Idempotent, so it is always safe to just run it.
"""

import glob
import json
import os
import re
import subprocess
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SITE = 'https://haleyyachts.com'

# Core pages, in the order and with the weights the hand-written file used.
# (path, changefreq, priority) - '' is the home page.
CORE = [
    ('',               'weekly',  '1.0'),
    ('buy.html',       'weekly',  '0.9'),
    ('sell.html',      'monthly', '0.9'),
    ('services.html',  'monthly', '0.8'),
    ('valuation.html', 'monthly', '0.8'),
    ('about.html',     'monthly', '0.7'),
    ('contact.html',   'monthly', '0.7'),
    ('articles.html',  'weekly',  '0.7'),
    ('privacy.html',   'yearly',  '0.3'),
]

# Never listed: blocked in robots.txt, or not a page.
SKIP_DIRS = ('admin/', 'docs/', 'drafts/', 'documents/', 'email-templates/', 'social-media/')
# Not pages: an article skeleton, a shared footer fragment, and a video overlay
# template. A warning that always fires is a warning nobody reads.
SKIP_FILES = ('articles/_template.html', 'partials/footer.html',
              'images/video/cta-card/cta-card-template.html')


def existing_lastmods(path):
    """{url: lastmod} from the sitemap as it stands.

    Core pages and yacht listings keep whatever date is already recorded. That
    is not laziness - the Article Manager runs in a browser and cannot ask git
    anything, so if this script derived those dates from git history the two
    generators would produce different files and each would undo the other on
    every publish. Both preserve; only genuinely new URLs get a fresh date.
    """
    if not os.path.exists(path):
        return {}
    src = open(path, encoding='utf-8').read()
    return dict(re.findall(r'<loc>(.*?)</loc>\s*<lastmod>(.*?)</lastmod>', src, re.S))


def git_date(rel, fallback):
    try:
        d = subprocess.check_output(['git', '-C', ROOT, 'log', '-1', '--format=%cs', '--', rel],
                                    stderr=subprocess.DEVNULL).decode().strip()
        return d or fallback
    except Exception:
        return fallback


def published_articles():
    """Read articles-data.js - the Article Manager's own record of what is live.

    Deliberately NOT a glob of articles/**. A file sitting in that folder is not
    proof it was published; the manager registering it is. That distinction is
    exactly what kept a stray 'test Trading Up' article out of the old sitemap's
    successor and would keep the next one out too.
    """
    src = open(os.path.join(ROOT, 'articles', 'articles-data.js'), encoding='utf-8').read()
    m = re.search(r'const publishedArticles\s*=\s*(\[.*?\]);', src, re.S)
    if not m:
        raise SystemExit('build-sitemap: could not read publishedArticles from articles-data.js')
    items = json.loads(m.group(1))

    out = []
    for a in items:
        fn = a.get('fileName', '')
        if not fn:
            continue
        if not fn.endswith('.html'):
            fn += '.html'
        cat = a.get('category') or a.get('type') or ''
        rel = 'articles/%s/%s' % (cat, fn) if cat else 'articles/%s' % fn
        if not os.path.exists(os.path.join(ROOT, rel)):
            print('build-sitemap: WARNING registered but missing on disk: %s' % rel)
            continue
        out.append((rel, a.get('date', '')))
    return out


def entry(loc, lastmod, changefreq, priority):
    return ('  <url>\n    <loc>%s</loc>\n    <lastmod>%s</lastmod>\n'
            '    <changefreq>%s</changefreq>\n    <priority>%s</priority>\n  </url>'
            % (loc, lastmod, changefreq, priority))


def build():
    prior = existing_lastmods(os.path.join(ROOT, 'sitemap.xml'))
    listed = set()
    parts = ['<?xml version="1.0" encoding="UTF-8"?>',
             '<!-- GENERATED by scripts/build-sitemap.py. Do not hand-edit: run the script. -->',
             '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
             '',
             '  <!-- Core pages -->']
    for path, freq, pri in CORE:
        rel = path or 'index.html'
        loc = SITE + '/' + path
        parts.append(entry(loc, prior.get(loc) or git_date(rel, '2026-06-10'), freq, pri))
        listed.add(rel)

    parts += ['', '  <!-- Yacht listings -->']
    for p in sorted(glob.glob(os.path.join(ROOT, 'yachts', '*.html'))):
        rel = os.path.relpath(p, ROOT)
        loc = SITE + '/' + rel
        parts.append(entry(loc, prior.get(loc) or git_date(rel, '2026-06-10'), 'weekly', '0.9'))
        listed.add(rel)

    parts += ['', '  <!-- Articles -->']
    arts = sorted(published_articles(), key=lambda x: x[0])
    arts.sort(key=lambda x: x[1], reverse=True)      # stable: date desc, then path asc
    for rel, date in arts:
        parts.append(entry(SITE + '/' + rel, date or git_date(rel, '2026-06-10'), 'yearly', '0.6'))
        listed.add(rel)

    parts += ['', '</urlset>', '']
    return '\n'.join(parts), listed


def unlisted(listed):
    out = []
    for p in glob.glob(os.path.join(ROOT, '**', '*.html'), recursive=True):
        rel = os.path.relpath(p, ROOT)
        if rel.startswith(SKIP_DIRS) or rel in SKIP_FILES or rel in listed:
            continue
        out.append(rel)
    return sorted(out)


def main():
    check = '--check' in sys.argv
    xml, listed = build()
    path = os.path.join(ROOT, 'sitemap.xml')
    current = open(path, encoding='utf-8').read() if os.path.exists(path) else None

    left = unlisted(listed)
    if left:
        print('build-sitemap: on the site but NOT in the sitemap (unregistered - check these):')
        for rel in left:
            print('    ' + rel)

    n = xml.count('<loc>')
    if check:
        if current != xml:
            print('build-sitemap: sitemap.xml is out of date - run scripts/build-sitemap.py')
            return 1
        print('build-sitemap: sitemap.xml is current (%d urls)' % n)
        return 0

    if current == xml:
        print('build-sitemap: already current (%d urls)' % n)
        return 0
    open(path, 'w', encoding='utf-8').write(xml)
    print('build-sitemap: wrote sitemap.xml (%d urls)' % n)
    return 0


if __name__ == '__main__':
    sys.exit(main())
