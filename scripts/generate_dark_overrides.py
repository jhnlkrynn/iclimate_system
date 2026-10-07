"""Generate dark-mode counterparts for hard-coded light surfaces / dark text in CSS.

usage: python scripts/generate_dark_overrides.py   (writes resources/css/theme-dark-pages.css)
Each colour is mapped onto the dark theme tokens defined in resources/css/theme.css:
  neutral light background  -> var(--dk-surface-2) (or --dk-surface-3 for hover/active/selected states)
  tinted light background   -> color-mix(same hue, dark surface)
  light border              -> var(--dk-border) / tinted border
  dark neutral text         -> var(--dk-ink) / var(--dk-ink-mid) / var(--dk-muted)
  dark coloured text        -> a light tint of the same hue
@media wrappers and !important are preserved.
"""
import re, sys, colorsys

def parse_colour(v):
    m = re.search(r'#([0-9a-fA-F]{8}|[0-9a-fA-F]{6}|[0-9a-fA-F]{3,4})\b', v)
    if m:
        h = m.group(1)
        if len(h) in (3, 4): h = ''.join(c * 2 for c in h)
        r, g, b = (int(h[i:i + 2], 16) for i in (0, 2, 4))
        a = int(h[6:8], 16) / 255 if len(h) == 8 else 1
        return (r, g, b, a), m.group(0)
    m = re.search(r'rgba?\(\s*(\d+)[\s,]+(\d+)[\s,]+(\d+)(?:[\s,/]+([\d.]+%?))?\s*\)', v)
    if m:
        a = m.group(4)
        a = (float(a[:-1]) / 100 if a and a.endswith('%') else float(a)) if a else 1
        return (int(m.group(1)), int(m.group(2)), int(m.group(3)), a), m.group(0)
    if re.search(r'(?<![-\w])white(?![-\w])', v): return (255, 255, 255, 1), 'white'
    return None, None

def lum(r, g, b):
    f = lambda c: (c / 255) / 12.92 if c / 255 <= .03928 else ((c / 255 + .055) / 1.055) ** 2.4
    return .2126 * f(r) + .7152 * f(g) + .0722 * f(b)

def hls(r, g, b):
    return colorsys.rgb_to_hls(r / 255, g / 255, b / 255)

def hexof(h, l, s):
    r, g, b = colorsys.hls_to_rgb(h, l, s)
    return '#%02x%02x%02x' % (round(r * 255), round(g * 255), round(b * 255))

STATUS = re.compile(r'watch|warn|danger|error|crit|fail|success|good|info|unread|is-today|is-mine|flag|privileged|is-high|is-sev|severe|moderate')
STATE = re.compile(r':hover|:focus|\.active|\.is-active|\.is-selected|\[aria-(pressed|selected|current)|:checked|\[open\]|\.is-today')

def map_bg(rgba, sel):
    r, g, b, a = rgba
    if a < .5 or lum(r, g, b) < .55: return None
    h, l, s = hls(r, g, b)
    chroma = (max(r, g, b) - min(r, g, b)) / 255
    if chroma >= .02 and STATUS.search(sel):   # a pale status tint (warning, danger, unread, today...) keeps its hue
        return f'color-mix(in srgb, {hexof(h, .45, max(.45, min(.7, s)))} 16%, var(--dk-surface))'
    if chroma < .08:   # neutral, or so pale it reads as white
        return 'var(--dk-surface-3)' if STATE.search(sel) else 'var(--dk-surface-2)'
    if s < .2:
        return 'var(--dk-surface-3)' if STATE.search(sel) else 'var(--dk-surface-2)'
    hue = hexof(h, .45, min(.65, max(.4, s)))
    return f'color-mix(in srgb, {hue} {"26" if STATE.search(sel) else "18"}%, var(--dk-surface))'

def map_border(rgba):
    r, g, b, a = rgba
    if lum(r, g, b) < .45: return None
    h, l, s = hls(r, g, b)
    if s < .2 or (max(r, g, b) - min(r, g, b)) / 255 < .1: return 'var(--dk-border-strong)'
    return f'color-mix(in srgb, {hexof(h, .5, min(.6, s))} 30%, var(--dk-border))'

def map_fg(rgba):
    r, g, b, a = rgba
    L = lum(r, g, b)
    if L > .2: return None
    h, l, s = hls(r, g, b)
    if s < .18 or L < .03 or (max(r, g, b) - min(r, g, b)) / 255 < .16:   # greys (incl. slate), near-black greens used as heading ink
        return 'var(--dk-ink)' if L < .035 else ('var(--dk-ink-mid)' if L < .1 else 'var(--dk-muted)')
    return hexof(h, .76, min(.6, max(.35, s)))

def blocks(css):
    """Yield (media_prefix, selector, declarations) for top-level and one-level @media rules."""
    css = re.sub(r'/\*.*?\*/', '', css, flags=re.S)
    css = re.sub(r'@(keyframes|-webkit-keyframes)[^{]*\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}', '', css)
    i, n = 0, len(css)
    def rules(text, media):
        for m in re.finditer(r'([^{}]+)\{([^{}]*)\}', text):
            yield media, ' '.join(m.group(1).split()), m.group(2)
    while i < n:
        m = re.compile(r'@(media|supports)([^{]*)\{').search(css, i)
        if not m:
            yield from rules(css[i:], None); break
        yield from rules(css[i:m.start()], None)
        depth, j = 1, m.end()
        while j < n and depth:
            depth += {'{': 1, '}': -1}.get(css[j], 0); j += 1
        yield from rules(css[m.end():j - 1], f'@{m.group(1)}{m.group(2)}'.strip())
        i = j

# White on purpose: buttons and text on the green page headers, the top bar and the sidebar, the Climora logo button.
KEEP = re.compile(r'\.(mfd-header|mtd-header|fad-hero|page-hero|wp-hero|feed-hero|msg-hero|topbar|sidebar|ic-sidebar|offcanvas|ic-ai-toggle|itd-header|mao-hero|it-hero|hero)\b')

def split_top(sel):
    """Split a selector list on commas that are not inside :is() / :not() / attribute brackets."""
    parts, depth, cur = [], 0, ''
    for ch in sel:
        depth += {'(': 1, '[': 1, ')': -1, ']': -1}.get(ch, 0)
        if ch == ',' and depth == 0:
            parts.append(cur); cur = ''
        else:
            cur += ch
    return parts + [cur]

def scope(sel):
    out = []
    for part in [p.strip() for p in split_top(sel) if p.strip()]:
        if KEEP.search(part): continue
        if part.startswith(('html', ':root')) or '::-webkit' in part or part.startswith(('from', 'to')) or re.match(r'^\d', part):
            continue
        if part.startswith('body'):
            out.append('html[data-theme="dark"] ' + part)
        else:
            out.append('html[data-theme="dark"] body ' + part)
    return ',\n'.join(out)

# Custom properties left alone: Bootstrap's, the app-wide tokens (themed in theme.css), and status / risk / accent colours.
TOKEN_SKIP = ('--bs-', '--iclimate-', '--risk-', '--accent', '--white', '--sidebar-', '--status-', '--reg-', '--k-', '--icl-',
              '--mao-green')   # fills buttons as well as colouring text: its text uses are lightened in theme.css instead

def generate(label, css):
    out, count = [], 0
    for media, sel, decls in blocks(css):
        new = []
        for d in decls.split(';'):
            if ':' not in d: continue
            prop, val = (x.strip() for x in d.split(':', 1))
            imp = ' !important' if '!important' in val else ''
            if prop in ('background', 'background-color') and 'url(' not in val:
                if 'gradient' in val:
                    stops = [parse_colour(x)[0] for x in re.findall(r'(#[0-9a-fA-F]{3,8}\b|rgba?\([^)]*\)|\bwhite\b)', val)]
                    stops = [s for s in stops if s]
                    if stops and all(s[3] >= .5 and lum(*s[:3]) > .55 for s in stops):
                        mapped = map_bg(stops[0], sel)
                        if mapped: new.append(f'background: {mapped}{imp}')
                    continue
                rgba, _ = parse_colour(val)
                if rgba:
                    mapped = map_bg(rgba, sel)
                    if mapped: new.append(f'{prop}: {mapped}{imp}')
            elif prop in ('border', 'border-color', 'border-top', 'border-bottom', 'border-left', 'border-right', 'border-top-color', 'border-bottom-color'):
                rgba, raw = parse_colour(val)
                if rgba:
                    mapped = map_border(rgba)
                    if mapped:
                        target = prop if prop.endswith('color') else prop + '-color'
                        new.append(f'{target}: {mapped}{imp}')
            elif prop.startswith('--') and not prop.startswith(TOKEN_SKIP):
                rgba, _ = parse_colour(val)
                if rgba and val.strip().startswith(('#', 'rgb', 'white')):
                    name = prop.lower()
                    if re.search(r'(bg|surface|paper|panel|card|field|soft|tint)', name): mapped = map_bg(rgba, sel)
                    elif re.search(r'(line|border|rule|edge)', name): mapped = map_border(rgba)
                    elif re.search(r'(ink|text|muted|fg|title|label|heading|green|color)', name): mapped = map_fg(rgba)
                    else: mapped = None
                    if mapped: new.append(f'{prop}: {mapped}{imp}')
            elif prop == 'color':
                rgba, _ = parse_colour(val)
                if rgba:
                    mapped = map_fg(rgba)
                    if mapped: new.append(f'color: {mapped}{imp}')
        if new:
            s = scope(sel)
            if not s: continue
            count += 1
            rule = f'{s} {{ {"; ".join(dict.fromkeys(new))}; }}'
            out.append(f'{media} {{\n{rule}\n}}' if media else rule)
    return count, out

ROOT = __import__('pathlib').Path(__file__).resolve().parent.parent
HEADER = """/*
 * iClimate dark theme: generated counterparts for colours that pages hard-code for the light theme.
 *
 * GENERATED by scripts/generate_dark_overrides.py: do not edit by hand, re-run it after changing a page's colours:
 *     python scripts/generate_dark_overrides.py
 * Sources: the <style> blocks of the Blade views and the page stylesheets listed in that script.
 * Every colour maps onto the dark tokens in theme.css (--dk-surface*, --dk-border*, --dk-ink*, --dk-muted): neutral light
 * backgrounds become dark surfaces, tinted ones keep their hue on a dark surface, dark text becomes light text (coloured
 * text keeps its hue). theme.css loads after this file, so its hand-written rules win where both apply.
 */
"""
CSS_FILES = ['app.css', 'ui-refresh.css', 'glass-system.css', 'farmer-dashboard.css', 'farmer-advisories.css', 'farmer-calendar.css',
             'farmer-records.css', 'farmer-messages.css', 'farmer-predictions.css', 'it-dashboard.css', 'it-pages.css', 'mao-dashboard.css',
             'info-modal.css', 'risk-overview.css', 'chart-explainer.css', 'animations.css',
             # page styles that used to be <style> blocks inside the Blade views
             'pages/weather-predictions.css', 'pages/community-feed.css']
SKIP_VIEWS = ('welcome', 'auth/', 'layouts/guest', 'errors/', 'reports/print', 'emails/')

if __name__ == '__main__':
    parts, total = [HEADER], 0
    views = ROOT / 'resources' / 'views'
    for f in sorted(views.rglob('*.blade.php')):
        rel = f.relative_to(views).as_posix()
        if rel.startswith(SKIP_VIEWS): continue
        css = '\n'.join(re.findall(r'<style[^>]*>(.*?)</style>', f.read_text(encoding='utf-8'), re.S))
        if not css.strip(): continue
        count, rules = generate(rel, css)
        if rules: parts.append(f'\n/* ---- views/{rel} ({count}) */\n' + '\n'.join(rules)); total += count
    for name in CSS_FILES:
        count, rules = generate(name, (ROOT / 'resources' / 'css' / name).read_text(encoding='utf-8'))
        if rules: parts.append(f'\n/* ---- css/{name} ({count}) */\n' + '\n'.join(rules)); total += count
    out = ROOT / 'resources' / 'css' / 'theme-dark-pages.css'
    out.write_text('\n'.join(parts) + '\n', encoding='utf-8')
    print(f'{total} rules -> {out}')
