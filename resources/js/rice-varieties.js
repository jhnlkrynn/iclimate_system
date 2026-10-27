// Rice Varieties (resources/views/rice-varieties/*): the Variety Details pop-up of the catalog pages and the planting
// form's variety picker with its expected-harvest preview. Both work on top of plain HTML: without this script the
// catalog cards are links to nothing harmful and the picker is an ordinary <select>.

const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const num = (value, digits = 0) => (value === null || value === undefined ? null : Number(value).toFixed(digits).replace(/\.0+$/, ''));

// ─── Variety Details pop-up ─────────────────────────────────────────────────

export function initVarietyDetails() {
    const dialog = document.querySelector('[data-rv-dialog]');
    if (!dialog || dialog.dataset.ready) return;
    dialog.dataset.ready = 'true';

    const t = JSON.parse(dialog.querySelector('[data-rv-i18n]')?.textContent || '{}');
    const urlFor = (id) => dialog.dataset.rvShowUrl.replace('__ID__', encodeURIComponent(id));
    const q = (sel) => dialog.querySelector(sel);
    const state = { data: null, season: 'ws', method: 'tpr', opener: null, controller: null };
    const locale = document.documentElement.lang || 'en';
    const dateLabel = (date) => date.toLocaleDateString(locale === 'fil' ? 'fil-PH' : 'en-US', { month: 'short', day: 'numeric', year: 'numeric', timeZone: 'UTC' });
    const addDays = (iso, days) => { const d = new Date(`${iso}T00:00:00Z`); d.setUTCDate(d.getUTCDate() + days); return d; };

    const show = (part) => {
        q('[data-rv-loading]').hidden = part !== 'loading';
        q('[data-rv-error]').hidden = part !== 'error';
        q('[data-rv-content]').hidden = part !== 'content';
    };

    const open = async (id, opener) => {
        state.opener = opener || document.activeElement;
        state.controller?.abort();
        state.controller = new AbortController();
        show('loading');
        q('[data-rv-f="name"]').textContent = opener?.dataset.rvName || t.loading;
        ['nsic', 'line_name', 'breeder', 'released', 'type'].forEach((f) => { const el = q(`[data-rv-f="${f}"]`); el.textContent = ''; el.hidden = true; });
        if (!dialog.open) { if (dialog.showModal) dialog.showModal(); else dialog.setAttribute('open', ''); }
        q('[data-rv-close]').focus();
        try {
            const response = await fetch(urlFor(id), { headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: state.controller.signal });
            if (!response.ok) throw new Error(`Variety request failed (${response.status})`);
            render(await response.json());
        } catch (error) {
            if (error.name === 'AbortError') return;
            console.error('iClimate could not load the rice variety.', error);
            show('error');
        }
    };

    const close = () => {
        state.controller?.abort();
        if (dialog.open) { if (dialog.close) dialog.close(); else dialog.removeAttribute('open'); }
    };
    dialog.addEventListener('close', () => { state.opener?.focus?.(); });

    const setField = (name, text) => { const el = q(`[data-rv-f="${name}"]`); el.textContent = text || ''; el.hidden = !text; };

    const render = (data) => {
        state.data = data;
        [state.method, state.season] = (data.default_combo || 'tpr_ws').split('_');
        setField('name', data.name);
        setField('nsic', data.nsic);
        setField('line_name', data.line_name);
        setField('breeder', data.breeder);
        setField('released', data.year ? t.released.replace(':year', data.year) : '');
        setField('type', data.type_label);
        q('[data-rv-f="name"]').hidden = false;

        renderGrain(data);
        renderRecommendation(data.recommendation);
        q('[data-rv-farm-types]').innerHTML = data.farm_types.map((f) => `<span class="rv-farm-chip is-${esc(f.key.toLowerCase())}" data-rv-farm-type="${esc(f.key)}">${esc(f.label)}</span>`).join('');
        const plant = q('[data-rv-plant]');
        plant.hidden = !data.plant_url;
        if (data.plant_url) plant.href = data.plant_url;
        selectTab('agronomy', false);
        update();
        show('content');
    };

    // Everything that follows the DS / WS and Transplanted / Direct-seeded switches.
    const update = () => {
        const data = state.data;
        if (!data) return;
        const combo = data.combos[`${state.method}_${state.season}`];
        dialog.querySelectorAll('[data-rv-season]').forEach((b) => b.setAttribute('aria-checked', String(b.dataset.rvSeason === state.season)));
        dialog.querySelectorAll('[data-rv-method]').forEach((b) => b.setAttribute('aria-checked', String(b.dataset.rvMethod === state.method)));

        const stat = (key, value, digits) => {
            const tile = q(`[data-rv-stat="${key}"]`);
            const empty = value === null || value === undefined;
            tile.querySelector('[data-rv-stat-value]').textContent = empty ? '—' : num(value, digits);
            tile.querySelector('[data-rv-stat-unit]').hidden = empty;
            tile.querySelector('[data-rv-stat-note]').hidden = !empty;
            tile.classList.toggle('is-empty', empty);
        };
        stat('yield', combo.yield, 1);
        stat('max', data.max_yield, 1);
        stat('maturity', combo.maturity.avg, 0);
        stat('height', combo.height.avg, 1);

        renderRange(combo.maturity);
        renderAgronomy(data, combo);
    };

    const SCALE = [80, 150];   // days after sowing: the same scale for every variety, so bars can be compared
    const at = (days) => Math.max(0, Math.min(100, ((days - SCALE[0]) / (SCALE[1] - SCALE[0])) * 100));
    const renderRange = (maturity) => {
        const bar = q('[data-rv-range]');
        const note = q('[data-rv-range-note]');
        const { avg, min, max } = maturity;
        bar.hidden = avg === null;
        q('[data-rv-range-empty]').hidden = avg !== null;
        if (avg === null) { q('[data-rv-calendar]').hidden = true; return; }
        const low = min ?? avg;
        const high = max ?? avg;
        const fill = q('[data-rv-range-fill]');
        fill.style.left = `${at(low)}%`;
        fill.style.width = `${Math.max(1.5, at(high) - at(low))}%`;
        q('[data-rv-range-avg]').style.left = `${at(avg)}%`;
        q('[data-rv-range-min]').textContent = min !== null ? `${num(min)}` : '';
        q('[data-rv-range-max]').textContent = max !== null ? `${num(max)}` : '';
        q('[data-rv-range-avgtext]').textContent = t.range_avg.replace(':days', num(avg));
        note.hidden = min !== null && max !== null;

        // A farmer's own variety: the same days on their calendar (planting date + days − seedling days).
        const planted = state.data.planted;
        const calendar = q('[data-rv-calendar]');
        calendar.hidden = !planted;
        if (planted) {
            const date = (days) => dateLabel(addDays(planted.date, days - planted.offset));
            const cFill = q('[data-rv-cal-fill]');
            cFill.style.left = `${at(low)}%`;
            cFill.style.width = `${Math.max(1.5, at(high) - at(low))}%`;
            q('[data-rv-cal-avg]').style.left = `${at(avg)}%`;
            q('[data-rv-cal-start]').textContent = min !== null ? date(min) : '';
            q('[data-rv-cal-end]').textContent = max !== null ? date(max) : '';
            q('[data-rv-cal-avgtext]').textContent = date(avg);
            q('[data-rv-cal-note]').textContent = t.calendar_note.replace(':date', dateLabel(addDays(planted.date, 0)));
        }
    };

    const rangeText = (r, unit = '') => {
        if (r.avg === null) return '—';
        const span = r.min !== null && r.max !== null ? ` (${num(r.min, 1)}–${num(r.max, 1)})` : '';
        return `${num(r.avg, 1)}${unit}${span}`;
    };
    const renderAgronomy = (data, combo) => {
        const unit = state.method === 'tpr' ? t.tillers_unit_tpr : t.tillers_unit_dwsr;
        q('[data-rv-a="tillers"]').textContent = combo.tillers.avg === null ? `— ${t.no_trial}` : `${rangeText(combo.tillers)} ${unit}`;
        q('[data-rv-a="culm"]').textContent = data.culm_strength || '—';
        q('[data-rv-a="height"]').textContent = combo.height.min !== null && combo.height.max !== null
            ? `${num(combo.height.min, 1)}–${num(combo.height.max, 1)} cm`
            : (combo.height.avg !== null ? `${num(combo.height.avg, 1)} cm` : `— ${t.no_trial}`);

        const cells = dialog.querySelectorAll('[data-rv-yield-cell]');
        cells.forEach((cell) => {
            const value = data.combos[cell.dataset.rvYieldCell].yield;
            cell.textContent = value === null ? '—' : num(value, 1);
            cell.classList.toggle('is-current', cell.dataset.rvYieldCell === `${state.method}_${state.season}`);
        });
        const regions = q('[data-rv-regions]');
        regions.hidden = combo.yield_regions.length === 0;
        regions.querySelector('ul').innerHTML = combo.yield_regions.map((r) => `<li><span>${esc(r.label)}</span><strong>${esc(num(r.t, 1))} t/ha${r.min !== null && r.max !== null ? ` <small>(${esc(num(r.min, 1))}–${esc(num(r.max, 1))})</small>` : ''}</strong></li>`).join('');
    };

    const renderGrain = (data) => {
        const amylose = q('[data-rv-amylose]');
        amylose.hidden = !data.amylose;
        q('[data-rv-amylose-empty]').hidden = Boolean(data.amylose);
        if (data.amylose) {
            q('[data-rv-amylose-value]').textContent = `${num(data.amylose.value, 1)}%`;
            const label = q('[data-rv-amylose-label]');
            label.textContent = data.amylose.label;
            label.className = `rv-amylose-label is-${data.amylose.level}`;
        }
        const facts = q('[data-rv-facts]');
        facts.innerHTML = data.grain_facts.length ? data.grain_facts.map((fact) => `<li>${esc(fact)}</li>`).join('') : `<li class="is-muted">${esc(t.none)}</li>`;
    };

    const renderRecommendation = (reco) => {
        ['location', 'season', 'establishment', 'adaptation'].forEach((key) => {
            q(`[data-rv-reco="${key}"]`).textContent = reco[key] || '—';
        });
    };

    // Tabs (Agronomy / Pest & Disease / Grain Quality): click, or Left / Right / Home / End.
    const tabs = [...dialog.querySelectorAll('[data-rv-tab]')];
    const selectTab = (name, focus = true) => {
        tabs.forEach((tab) => {
            const on = tab.dataset.rvTab === name;
            tab.setAttribute('aria-selected', String(on));
            tab.tabIndex = on ? 0 : -1;
            q(`#${tab.getAttribute('aria-controls')}`).hidden = !on;
            if (on && focus) tab.focus();
        });
    };
    dialog.addEventListener('keydown', (event) => {
        const tab = event.target.closest('[data-rv-tab]');
        if (!tab) return;
        const i = tabs.indexOf(tab);
        const next = { ArrowRight: i + 1, ArrowLeft: i - 1, Home: 0, End: tabs.length - 1 }[event.key];
        if (next === undefined) return;
        event.preventDefault();
        selectTab(tabs[(next + tabs.length) % tabs.length].dataset.rvTab);
    });

    dialog.addEventListener('click', (event) => {
        if (event.target === dialog || event.target.closest('[data-rv-close]')) { close(); return; }
        const tab = event.target.closest('[data-rv-tab]');
        if (tab) { selectTab(tab.dataset.rvTab); return; }
        const season = event.target.closest('[data-rv-season]');
        if (season) { state.season = season.dataset.rvSeason; update(); return; }
        const method = event.target.closest('[data-rv-method]');
        if (method) { state.method = method.dataset.rvMethod; update(); return; }
        if (event.target.closest('[data-rv-retry]') && state.data === null && state.lastId) open(state.lastId, state.opener);
    });

    // Any [data-rv-open="ID"] on the page (catalog cards, table rows, MAO's variety names, the farmer's card).
    document.addEventListener('click', (event) => {
        const opener = event.target.closest('[data-rv-open]');
        // The IT table's Active switch and Edit link sit inside a row that opens the pop-up.
        if (!opener || event.metaKey || event.ctrlKey || event.target.closest('[data-rv-stop]')) return;
        event.preventDefault();
        state.data = null;
        state.lastId = opener.dataset.rvOpen;
        open(opener.dataset.rvOpen, opener);
    });
    document.addEventListener('keydown', (event) => {
        const row = event.target.closest('tr[data-rv-open]');
        if (row && event.target === row && (event.key === 'Enter' || event.key === ' ')) { event.preventDefault(); state.lastId = row.dataset.rvOpen; open(row.dataset.rvOpen, row); }
    });
}

// ─── Planting form: variety picker and expected-harvest preview ─────────────

export function initVarietyPicker() {
    document.querySelectorAll('[data-rv-planting]').forEach((root) => {
        if (root.dataset.ready) return;
        root.dataset.ready = 'true';
        const form = root.closest('form');
        const select = root.querySelector('[data-rv-select]');
        const planting = form.querySelector('#planting_date');
        const harvest = form.querySelector('#scheduled_harvest_date');
        const season = form.querySelector('#season');
        const mode = root.querySelector('[data-rv-mode]');
        const seedlingWrap = root.querySelector('[data-rv-seedling]');
        const seedling = root.querySelector('[data-rv-seedling-age]');
        const preview = form.querySelector('[data-rv-preview]');
        const useButton = form.querySelector('[data-rv-use]');
        let latest = null;
        let controller = null;

        // Varieties for the farm's type first: "Type of farm" on the same form.
        buildCombobox(select, () => form.querySelector('#farm_type')?.value || '');

        const checked = (name) => form.querySelector(`input[name="${name}"]:checked`)?.value || '';
        const syncBasis = () => {
            const direct = checked('crop_establishment').startsWith('Direct');
            // A direct-seeded crop has no transplanting date: the date is the sowing date.
            root.querySelectorAll('[data-rv-basis]').forEach((radio) => { radio.disabled = direct && radio.value === 'transplanting'; });
            if (direct && checked('date_basis') === 'transplanting') root.querySelector('[data-rv-basis][value="sowing"]').checked = true;
            seedlingWrap.hidden = checked('date_basis') !== 'transplanting';
        };

        const refresh = async () => {
            syncBasis();
            if (!select.value || !planting.value) {
                preview.hidden = true;
                useButton.hidden = true;
                return;
            }
            controller?.abort();
            controller = new AbortController();
            const url = new URL(root.dataset.rvEstimateUrl, window.location.origin);
            url.searchParams.set('variety', select.value);
            url.searchParams.set('date', planting.value);
            if (season?.value) url.searchParams.set('season', season.value);
            if (checked('crop_establishment')) url.searchParams.set('establishment', checked('crop_establishment'));
            url.searchParams.set('basis', checked('date_basis') || 'sowing');
            if (checked('date_basis') === 'transplanting' && seedling.value) url.searchParams.set('seedling_age', seedling.value);
            try {
                const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: controller.signal });
                if (!response.ok) throw new Error(`Estimate failed (${response.status})`);
                latest = await response.json();
                preview.hidden = false;
                preview.querySelector('[data-rv-preview-label]').textContent = latest.label;
                preview.querySelector('[data-rv-preview-basis]').textContent = latest.basis;
                if (mode.value !== 'manual' && harvest) {
                    harvest.value = latest.expected_harvest_date;
                    harvest.dispatchEvent(new Event('change', { bubbles: true }));
                }
                useButton.hidden = mode.value !== 'manual' || harvest?.value === latest.expected_harvest_date;
            } catch (error) {
                if (error.name !== 'AbortError') { preview.hidden = true; console.error('iClimate could not estimate the harvest date.', error); }
            }
        };

        // Typing a harvest date by hand keeps it; "Use this date" goes back to the variety's date.
        harvest?.addEventListener('input', () => { mode.value = 'manual'; if (latest) useButton.hidden = harvest.value === latest.expected_harvest_date; });
        useButton.addEventListener('click', () => {
            mode.value = 'auto';
            root.querySelector('[data-rv-manual-note]')?.remove();
            refresh();
        });

        [select, planting, season, seedling].forEach((el) => el?.addEventListener('change', refresh));
        root.addEventListener('change', (event) => { if (event.target.matches('[data-rv-establishment], [data-rv-basis]')) refresh(); });
        refresh();
    });
}

// The <select> stays the form field; a text box with a list of matches is put in front of it. Matches every word
// typed against the variety's name, NSIC number, line and breeder; newest varieties come first (the select's order).
function buildCombobox(select, farmType = () => '') {
    const id = `${select.id}-search`;
    const listId = `${select.id}-list`;
    const options = [...select.options].map((option) => ({
        value: option.value, label: option.textContent.trim(), search: `${option.dataset.search || ''} ${option.textContent}`.toLowerCase(),
        farm: (option.dataset.farmTypes || '').split(' ').filter(Boolean), order: Number(option.dataset.order ?? -1),
    }));
    // Newest first, as the server sent them before grouping them by farm type.
    const varieties = options.slice(1).sort((a, b) => a.order - b.order);
    const farmLabels = JSON.parse(select.dataset.rvFarmLabels || '{}');
    const none = options[0];

    const wrap = document.createElement('div');
    wrap.className = 'rv-combo';
    wrap.innerHTML = `<input type="text" id="${id}" class="${esc(select.className)} rv-combo-input" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="${listId}" autocomplete="off" placeholder="${esc(select.dataset.rvPlaceholder || '')}">`
        + `<ul id="${listId}" class="rv-combo-list" role="listbox" hidden></ul>`;
    select.after(wrap);
    select.hidden = true;
    select.tabIndex = -1;
    const label = document.querySelector(`label[for="${select.id}"]`);
    if (label) label.htmlFor = id;
    const input = wrap.querySelector('input');
    const list = wrap.querySelector('ul');
    if (select.getAttribute('aria-describedby')) input.setAttribute('aria-describedby', select.getAttribute('aria-describedby'));
    if (select.classList.contains('is-invalid')) input.setAttribute('aria-invalid', 'true');

    let matches = [];
    let active = -1;
    const current = () => options.find((o) => o.value === select.value);
    input.value = select.value ? current()?.label || '' : '';

    const render = () => {
        const words = input.value.toLowerCase().split(/\s+/).filter(Boolean);
        const showing = current() && input.value === current().label;
        const found = varieties.filter((o) => showing || words.every((w) => o.search.includes(w)));
        // The farm's type first, then "Other varieties" (varieties with no farm type go there too).
        const type = farmType();
        const suits = type ? found.filter((o) => o.farm.includes(type)) : found;
        const others = type ? found.filter((o) => !o.farm.includes(type)) : [];
        const shownSuits = suits.slice(0, 40);
        const shownOthers = others.slice(0, Math.max(0, 40 - shownSuits.length));
        matches = [none, ...shownSuits, ...shownOthers];
        const item = (o, i) => `<li id="${listId}-${i}" role="option" aria-selected="${o.value === select.value}" data-value="${esc(o.value)}" class="${i === active ? 'is-active' : ''}${o === none ? ' is-none' : ''}">${esc(o.label)}</li>`;
        const head = (text) => `<li class="rv-combo-head" role="presentation" data-rv-combo-head>${esc(text)}</li>`;
        let html = item(none, 0);
        if (type && shownSuits.length) html += head(select.dataset.rvSuits.replace(':type', farmLabels[type] || type));
        html += shownSuits.map((o, i) => item(o, i + 1)).join('');
        if (type && shownOthers.length) html += head(select.dataset.rvOther);
        html += shownOthers.map((o, i) => item(o, i + 1 + shownSuits.length)).join('');
        list.innerHTML = html;
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        input.setAttribute('aria-activedescendant', active >= 0 ? `${listId}-${active}` : '');
    };
    const closeList = () => { list.hidden = true; input.setAttribute('aria-expanded', 'false'); active = -1; };
    const choose = (option) => {
        select.value = option.value;
        input.value = option.value ? option.label : '';
        closeList();
        select.dispatchEvent(new Event('change', { bubbles: true }));
    };

    input.addEventListener('focus', () => { active = -1; render(); });
    input.addEventListener('click', () => { if (list.hidden) { active = -1; render(); } });
    // A different "Type of farm" regroups the list at once.
    select.form?.querySelector('#farm_type')?.addEventListener('change', () => { if (!list.hidden) render(); });
    input.addEventListener('input', () => { active = 0; render(); });
    input.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (list.hidden) render();
            active = Math.max(0, Math.min(matches.length - 1, active + (event.key === 'ArrowDown' ? 1 : -1)));
            render();
            list.querySelector('.is-active')?.scrollIntoView({ block: 'nearest' });
        } else if (event.key === 'Enter' && !list.hidden && active >= 0) {
            event.preventDefault();
            choose(matches[active]);
        } else if (event.key === 'Escape') {
            closeList();
        }
    });
    list.addEventListener('mousedown', (event) => event.preventDefault());
    list.addEventListener('click', (event) => {
        const item = event.target.closest('[data-value]');
        if (item) choose(options.find((o) => o.value === item.dataset.value));
    });
    input.addEventListener('blur', () => {
        closeList();
        // Text that matches no choice goes back to the chosen variety (or "I don't know").
        input.value = select.value ? current()?.label || '' : '';
    });
}
