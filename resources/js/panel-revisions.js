/*
 * Front-end behaviour for the thesis panel revisions (docs/panel-revisions.md).
 * Each part binds to markup by data attribute and re-binds when a pop-up adds new markup.
 *
 *   1.3  [data-harvest-timing]   reason for an early / late harvest, shown while the date is off
 *   1.2  [data-yield-converter]  enter a harvest in t/ha, total tons, kg or sacks; stored as t/ha
 *   1.4  [data-live-search]      debounced, highlighted, mobile-friendly live search
 */

const DAY_MS = 86400000;

/* ------------------------------------------------------------------ 1.3 harvest timing */
const parseDay = (v) => (/^\d{4}-\d{2}-\d{2}$/.test(v || '') ? Date.UTC(+v.slice(0, 4), +v.slice(5, 7) - 1, +v.slice(8, 10)) : null);
const monthEnd = (v) => (/^\d{4}-\d{2}$/.test(v || '') ? Date.UTC(+v.slice(0, 4), +v.slice(5, 7), 0) : null);
const formatDay = (ms) => new Date(ms).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric', timeZone: 'UTC' });

function updateHarvestTiming(box) {
    const form = box.closest('form');
    if (!form) return;
    const kind = form.querySelector('input[name="kind"]:checked');
    const harvestInput = form.querySelector('[name="harvested_on"]') || form.querySelector('[name="date"]');

    let expected = parseDay(box.dataset.expected);
    if (expected === null) {
        const scheduled = form.querySelector('[name="scheduled_harvest_date"]');
        const month = form.querySelector('[name="harvest_month"]');
        expected = parseDay(scheduled && scheduled.value) ?? monthEnd(month && month.value);
    }
    // Activity Calendar: each farm option carries the expected harvest of its open planting.
    const farm = form.querySelector('select[name="farm_id"]');
    const farmOption = farm && farm.selectedOptions ? farm.selectedOptions[0] : null;
    if (farmOption && 'expectedHarvest' in farmOption.dataset) expected = parseDay(farmOption.dataset.expectedHarvest);

    const actual = parseDay(harvestInput && harvestInput.value);
    const tolerance = Number(box.dataset.tolerance || 3);
    const days = expected !== null && actual !== null ? Math.round((actual - expected) / DAY_MS) : null;
    const isHarvest = !kind || kind.value === 'harvested';
    const off = isHarvest && days !== null && Math.abs(days) > tolerance;

    const reason = box.querySelector('[data-harvest-timing-reason]');
    const hasError = !!box.querySelector('[role="alert"]');
    box.hidden = !(off || hasError || (isHarvest && reason && reason.value));
    if (reason) reason.required = off;

    const hint = box.querySelector('[data-harvest-timing-hint]');
    if (hint) {
        hint.textContent = off
            ? (box.dataset.textHint || '')
                .replace(':days', String(Math.abs(days)))
                .replace(':timing', (days < 0 ? box.dataset.textEarly : box.dataset.textLate || '').toLowerCase())
                .replace(':expected', formatDay(expected))
            : '';
    }
    const other = box.querySelector('[data-harvest-timing-other]');
    const otherInput = other && other.querySelector('input');
    const isOther = !!reason && reason.value === 'other';
    if (other) other.hidden = !isOther;
    if (otherInput) otherInput.required = off && isOther;
}

function bindHarvestTiming(root = document) {
    root.querySelectorAll('[data-harvest-timing]').forEach((box) => {
        if (box.dataset.bound) return;
        box.dataset.bound = '1';
        const form = box.closest('form');
        if (!form) return;
        const run = () => updateHarvestTiming(box);
        form.addEventListener('input', run);
        form.addEventListener('change', run);
        run();
    });
}

/* ------------------------------------------------------------------ 1.2 yield unit converter */
// <div data-yield-converter data-sack-kg="50"> with [data-yield-amount], [data-yield-unit], [data-yield-area] and the
// real t/ha input [data-yield-target]. yield (t/ha) = total harvest in tons ÷ area harvested in hectares.
function bindYieldConverters(root = document) {
    root.querySelectorAll('[data-yield-converter]').forEach((box) => {
        if (box.dataset.bound) return;
        box.dataset.bound = '1';
        const scope = box.closest('form') || document;
        const find = (selector) => { try { return selector ? scope.querySelector(selector) : null; } catch (e) { return null; } };
        const amount = box.querySelector('[data-yield-amount]');
        const unit = box.querySelector('[data-yield-unit]');
        const area = box.querySelector('[data-yield-area]') || find(box.dataset.areaSelector || '[name="farm_area_hectares"]');
        const target = box.querySelector('[data-yield-target]') || find(box.dataset.targetSelector);
        const out = box.querySelector('[data-yield-result]');
        const sackKg = Number(box.dataset.sackKg || 50);
        if (!amount || !unit || !target) return;

        const recalc = () => {
            const value = parseFloat(amount.value);
            const hectares = parseFloat(area && area.value);
            const u = unit.value;
            let tons = null;
            if (Number.isFinite(value) && value > 0) {
                if (u === 't/ha') { target.value = value.toFixed(2); if (out) out.textContent = value.toFixed(2) + ' t/ha'; return; }
                tons = u === 'tons' ? value : u === 'kg' ? value / 1000 : u === 'sacks' ? (value * sackKg) / 1000 : null;
            }
            if (tons !== null && Number.isFinite(hectares) && hectares > 0) {
                const tPerHa = tons / hectares;
                target.value = tPerHa.toFixed(2);
                if (out) out.textContent = `${tons.toFixed(2)} t ÷ ${hectares} ha = ${tPerHa.toFixed(2)} t/ha`;
            } else if (out) {
                out.textContent = tons !== null ? (box.dataset.textNeedArea || 'Enter the area harvested to work out t/ha.') : '';
            }
        };
        [amount, unit, area].forEach((el) => el && el.addEventListener('input', recalc));
        [unit, area].forEach((el) => el && el.addEventListener('change', recalc));
    });
}

/* ------------------------------------------------------------------ 1.4 live search */
const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const escapeRegExp = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

/** "Juan <mark>Dela</mark> Cruz": every case-insensitive match of the typed words, HTML-escaped. */
export function highlight(text, query) {
    const safe = escapeHtml(text);
    const words = String(query || '').trim().split(/\s+/).filter((w) => w.length > 0).map(escapeRegExp);
    if (!words.length) return safe;
    return safe.replace(new RegExp(`(${words.map(escapeHtml).join('|')})`, 'gi'), '<mark>$1</mark>');
}

function bindLiveSearch(root = document) {
    root.querySelectorAll('[data-live-search]').forEach((box) => {
        if (box.dataset.bound) return;
        box.dataset.bound = '1';
        const input = box.querySelector('input[type="search"], input[data-live-search-input]');
        const list = box.querySelector('[data-live-search-results]');
        const status = box.querySelector('[data-live-search-status]');
        const url = box.dataset.liveSearch;
        const minChars = Number(box.dataset.minChars || 1);
        const delay = Number(box.dataset.debounce || 300);
        const texts = { none: box.dataset.textNone || 'No results found', searching: box.dataset.textSearching || 'Searching…' };
        if (!input || !list || !url) return;

        let timer = null;
        let controller = null;
        let active = -1;
        let items = [];

        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-expanded', 'false');
        input.setAttribute('aria-controls', list.id);
        input.setAttribute('autocomplete', 'off');

        const close = () => { list.hidden = true; input.setAttribute('aria-expanded', 'false'); active = -1; input.removeAttribute('aria-activedescendant'); };
        const open = () => { list.hidden = false; input.setAttribute('aria-expanded', 'true'); };
        const say = (text) => { if (status) status.textContent = text; };

        const render = (results, query) => {
            items = results;
            active = -1;
            if (!results.length) {
                list.innerHTML = `<li class="ls-empty" role="option" aria-disabled="true">${escapeHtml(texts.none)}</li>`;
                open();
                say(texts.none);
                return;
            }
            list.innerHTML = results.map((r, i) => `
                <li role="option" id="${list.id}-opt-${i}" class="ls-item" data-index="${i}">
                    <a href="${escapeHtml(r.url || '#')}" ${r.modal ? 'data-ic-view data-ic-size="normal" aria-haspopup="dialog"' : ''} tabindex="-1">
                        <span class="ls-title">${highlight(r.title, query)}</span>
                        ${r.meta ? `<span class="ls-meta">${highlight(r.meta, query)}</span>` : ''}
                        ${r.badge ? `<span class="ls-badge">${escapeHtml(r.badge)}</span>` : ''}
                    </a>
                </li>`).join('');
            open();
            say(`${results.length} result${results.length === 1 ? '' : 's'}`);
            // Keep the list in view above the phone keyboard.
            if (window.matchMedia('(max-width: 767.98px)').matches) {
                input.scrollIntoView({ block: 'start', behavior: 'smooth' });
            }
        };

        const run = async () => {
            const query = input.value.trim();
            if (query.length < minChars) { close(); list.innerHTML = ''; say(''); return; }
            if (controller) controller.abort();
            controller = new AbortController();
            say(texts.searching);
            box.classList.add('is-loading');
            try {
                const sep = url.includes('?') ? '&' : '?';
                const response = await fetch(`${url}${sep}q=${encodeURIComponent(query)}`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    signal: controller.signal,
                    credentials: 'same-origin',
                });
                if (!response.ok) throw new Error(String(response.status));
                const data = await response.json();
                if (input.value.trim() === query) render(Array.isArray(data.results) ? data.results : [], query);
            } catch (error) {
                if (error.name !== 'AbortError') { list.innerHTML = `<li class="ls-empty" role="option" aria-disabled="true">${escapeHtml(texts.none)}</li>`; open(); }
            } finally {
                box.classList.remove('is-loading');
            }
        };

        const move = (step) => {
            const options = list.querySelectorAll('.ls-item');
            if (!options.length) return;
            active = (active + step + options.length) % options.length;
            options.forEach((o, i) => o.classList.toggle('is-active', i === active));
            input.setAttribute('aria-activedescendant', options[active].id);
            options[active].scrollIntoView({ block: 'nearest' });
        };

        input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(run, delay); });
        input.addEventListener('focus', () => { if (list.innerHTML.trim() && input.value.trim().length >= minChars) open(); });
        input.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown') { event.preventDefault(); if (list.hidden) run(); else move(1); }
            else if (event.key === 'ArrowUp') { event.preventDefault(); move(-1); }
            else if (event.key === 'Escape') { close(); }
            else if (event.key === 'Enter' && active >= 0 && items[active]) {
                event.preventDefault();
                list.querySelectorAll('.ls-item a')[active]?.click();
            }
        });
        document.addEventListener('click', (event) => { if (!box.contains(event.target)) close(); });
    });
}

/* ------------------------------------------------------------------ 1.4 live list filter */
// <form data-live-filter> inside a page section: typing in its search box (300 ms after the last key) or changing a
// filter reloads the same page in the background and swaps every section next to the filter (the list, its counts and
// charts) without leaving the box, so the keyboard stays open on a phone. Matches are highlighted in the new results.
function highlightIn(node, query) {
    const words = String(query || '').trim().split(/\s+/).filter((w) => w.length > 1).map(escapeRegExp);
    if (!words.length || !node) return;
    const tester = new RegExp(`(${words.join('|')})`, 'i');   // no 'g' flag: .test() must not keep state
    const walker = document.createTreeWalker(node, NodeFilter.SHOW_TEXT, {
        acceptNode: (text) => {
            const parent = text.parentElement;
            if (!parent || parent.closest('script, style, mark, button, select, option, canvas, svg, [data-no-highlight]')) return NodeFilter.FILTER_REJECT;
            if (!parent.closest('td, th, li, dd, .frec-item, .ls-item, [data-highlight]')) return NodeFilter.FILTER_REJECT;
            return tester.test(text.nodeValue) ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
        },
    });
    const hits = [];
    while (walker.nextNode()) hits.push(walker.currentNode);
    hits.forEach((text) => {
        const span = document.createElement('span');
        span.innerHTML = escapeHtml(text.nodeValue).replace(new RegExp(`(${words.map(escapeHtml).join('|')})`, 'gi'), '<mark>$1</mark>');
        text.replaceWith(...span.childNodes);
    });
}

function bindLiveFilters(root = document) {
    root.querySelectorAll('form[data-live-filter]').forEach((form) => {
        if (form.dataset.bound) return;
        form.dataset.bound = '1';
        const search = form.querySelector('input[name="search"]');
        const anchor = form.closest('details') || form;
        const container = anchor.parentElement;
        if (!container) return;
        let timer = null;
        let controller = null;
        const status = document.createElement('p');
        status.className = 'visually-hidden';
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');
        form.appendChild(status);

        const run = async () => {
            const params = new URLSearchParams(new FormData(form));
            [...params.keys()].forEach((key) => { if (params.get(key) === '') params.delete(key); });
            const url = `${form.getAttribute('action') || window.location.pathname}?${params.toString()}`;
            if (controller) controller.abort();
            controller = new AbortController();
            container.classList.add('is-live-loading');
            try {
                const response = await fetch(url, { headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' }, signal: controller.signal, credentials: 'same-origin' });
                if (!response.ok) throw new Error(String(response.status));
                const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
                const freshForm = doc.querySelector('form[data-live-filter]');
                const freshAnchor = freshForm ? (freshForm.closest('details') || freshForm) : null;
                const freshContainer = freshAnchor?.parentElement;
                if (!freshContainer) throw new Error('No results on the page');
                // Swap the results after the filter (and anything before it marked data-live-swap, e.g. totals); the
                // filter itself and the focused box stay. Inline scripts in the new sections run again.
                const swappable = (list, mark) => {
                    const out = []; let after = false;
                    list.forEach((child) => { if (child === mark) { after = true; return; } if (after || child.hasAttribute('data-live-swap')) out.push(child); });
                    return out;
                };
                swappable([...container.children], anchor).forEach((child) => child.remove());
                swappable([...freshContainer.children], freshAnchor).forEach((child) => {
                    const node = document.importNode(child, true);
                    child.compareDocumentPosition(freshAnchor) & Node.DOCUMENT_POSITION_FOLLOWING ? container.insertBefore(node, anchor) : container.appendChild(node);
                    node.querySelectorAll('script').forEach((old) => {
                        const fresh = document.createElement('script');
                        [...old.attributes].forEach((a) => fresh.setAttribute(a.name, a.value));
                        fresh.textContent = old.textContent;
                        old.replaceWith(fresh);
                    });
                    highlightIn(node, search?.value);
                });
                window.history.replaceState(null, '', url);
                const count = container.querySelectorAll('tbody tr, .frec-item, [data-record-row]').length;
                status.textContent = count ? `${count} result${count === 1 ? '' : 's'}` : (form.dataset.textNone || 'No results found');
                document.dispatchEvent(new CustomEvent('ic:content-loaded', { detail: { root: container } }));
            } catch (error) {
                if (error.name !== 'AbortError') form.submit();   // fall back to a normal page load
            } finally {
                container.classList.remove('is-live-loading');
            }
        };

        if (search) search.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(run, 300); });
        form.querySelectorAll('select').forEach((select) => select.addEventListener('change', () => { clearTimeout(timer); run(); }));
        form.addEventListener('submit', (event) => { event.preventDefault(); clearTimeout(timer); run(); });
        if (search?.value) highlightIn(container, search.value);
    });
}

/* ------------------------------------------------------------------ boot */
export function initPanelRevisions(root = document) {
    bindHarvestTiming(root);
    bindYieldConverters(root);
    bindLiveSearch(root);
    bindLiveFilters(root);
}

let observerStarted = false;
export function watchPanelRevisions() {
    initPanelRevisions();
    if (observerStarted || typeof MutationObserver === 'undefined') return;
    observerStarted = true;
    let queued = false;
    new MutationObserver(() => {
        if (queued) return;
        queued = true;
        requestAnimationFrame(() => { queued = false; initPanelRevisions(); });
    }).observe(document.body, { childList: true, subtree: true });
}
