// "5 seconds ago" text for stored times. Same rules as App\Support\RelativeTime (the server's first render);
// live-clock.js re-applies it to every [data-relative-time] element once per second.
const WORDS = {
    en: { now: 'just now', second: ['second', 'seconds'], minute: ['minute', 'minutes'], hour: ['hour', 'hours'], day: ['day', 'days'], past: ':n :unit ago', future: 'in :n :unit' },
    fil: { now: 'ngayon lang', second: ['segundo', 'segundo'], minute: ['minuto', 'minuto'], hour: ['oras', 'oras'], day: ['araw', 'araw'], past: ':n :unit na ang nakalipas', future: 'sa loob ng :n :unit' },
};

export function wordsFor(locale) {
    const lang = String(locale || 'en').toLowerCase();
    return lang.startsWith('fil') || lang.startsWith('tl') ? WORDS.fil : WORDS.en;
}

export function formatRelativeSeconds(seconds, locale = 'en') {
    const words = wordsFor(locale);
    const s = Math.floor(seconds);
    // Small clock differences between the server and the database must not read as "in 2 seconds".
    if (s > -60 && s < 5) return words.now;

    const future = s < 0;
    const abs = Math.abs(s);
    let n;
    let unit;
    if (abs < 60) [n, unit] = [abs, 'second'];
    else if (abs < 3600) [n, unit] = [Math.floor(abs / 60), 'minute'];
    else if (abs < 86400) [n, unit] = [Math.floor(abs / 3600), 'hour'];
    else [n, unit] = [Math.floor(abs / 86400), 'day'];

    return (future ? words.future : words.past).replace(':n', String(n)).replace(':unit', words[unit][n === 1 ? 0 : 1]);
}

/** Text for an ISO time at `nowMs`, or null when the attribute is missing or unreadable. */
export function formatRelative(iso, nowMs, locale = 'en') {
    const at = Date.parse(iso || '');
    if (Number.isNaN(at)) return null;
    return formatRelativeSeconds((nowMs - at) / 1000, locale);
}

/** Update every [data-relative-time] element below `root` (only when the text changes). */
export function applyRelativeTimes(root, nowMs, fallbackLocale = 'en') {
    root.querySelectorAll('[data-relative-time]').forEach((element) => {
        const locale = element.closest('[lang]')?.getAttribute('lang') || fallbackLocale;
        const text = formatRelative(element.getAttribute('datetime') || element.dataset.relativeTime, nowMs, locale);
        if (text !== null && element.textContent !== text) element.textContent = text;
    });
}
