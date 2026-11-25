// My Farm (resources/views/farmer-records/_activity.blade.php), Farmer – Naicel: the Activity Calendar pop-up (tap a
// date, choose Planted or Harvested and the farm) and the harvest question's "Not now". Saving is a normal form post,
// so the page also works when a script fails to load. Adding and editing farms is John Luke's farm pages.

export function initFarmerMyFarm() {
    const root = document.querySelector('[data-fmf-activity]');
    if (!root) return;

    const dialog = root.querySelector('[data-fmf-activity-dialog]');
    const open = () => { if (dialog && !dialog.open) { if (dialog.showModal) dialog.showModal(); else dialog.setAttribute('open', ''); syncDialogFields(); } };
    const close = () => { if (dialog) { if (dialog.close) dialog.close(); else dialog.removeAttribute('open'); } };

    const dateInput = dialog?.querySelector('input[name="date"]');
    const farmSelect = dialog?.querySelector('select[name="farm_id"]');
    const varietyWrap = dialog?.querySelector('[data-fmf-variety-wrap]');
    const varietySearch = dialog?.querySelector('[data-fmf-variety-search]');
    const varietyValue = dialog?.querySelector('[data-fmf-variety-value]');
    const varietyList = dialog?.querySelector('[data-fmf-variety-list]');
    const previewBox = dialog?.querySelector('[data-fmf-harvest-preview]');
    const previewSummary = dialog?.querySelector('[data-fmf-preview-summary]');
    const previewToday = dialog?.querySelector('[data-fmf-preview-today]');
    const closingRef = dialog?.querySelector('[data-fmf-closing-ref]');
    let previewAbort = null;
    let varietyOptions = [];
    try { varietyOptions = varietySearch?.dataset.options ? JSON.parse(varietySearch.dataset.options) : []; } catch (e) { varietyOptions = []; }

    const currentKind = () => dialog?.querySelector('input[name="kind"]:checked')?.value || 'planted';

    const updatePreview = async () => {
        if (!previewBox || !varietyValue || !dateInput || currentKind() !== 'planted') {
            if (previewBox) previewBox.hidden = true;
            return;
        }
        const dateVal = dateInput.value;
        const varietyVal = varietyValue.value;
        if (!dateVal || !varietyVal) {
            previewBox.hidden = true;
            return;
        }
        if (previewAbort) previewAbort.abort();
        previewAbort = new AbortController();
        try {
            const url = new URL(previewBox.dataset.estimateUrl, window.location.origin);
            url.searchParams.set('date', dateVal);
            if (varietyVal !== 'unknown') url.searchParams.set('variety', varietyVal);
            const res = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: previewAbort.signal });
            if (!res.ok) throw new Error('Estimate failed');
            const data = await res.json();
            previewSummary.textContent = data.preview_summary || data.label || '';
            previewToday.textContent = data.preview_from_today || '';
            previewToday.hidden = !data.preview_from_today;
            previewBox.hidden = false;
        } catch (err) {
            if (err.name !== 'AbortError') previewBox.hidden = true;
        }
    };

    const updateClosingRef = () => {
        if (!closingRef || !farmSelect) return;
        if (currentKind() !== 'harvested') {
            closingRef.hidden = true;
            return;
        }
        const opt = farmSelect.selectedOptions[0];
        let openInfo = null;
        try { openInfo = opt?.dataset.openPlanting ? JSON.parse(opt.dataset.openPlanting) : null; } catch (e) { openInfo = null; }
        if (!openInfo) {
            closingRef.hidden = true;
            return;
        }
        const farmName = opt.textContent.trim();
        const varietyName = openInfo.variety || closingRef.dataset.unknownVariety;
        const plantedPart = openInfo.planted_on ? `, ${closingRef.dataset.plantedWord} ${openInfo.planted_on}` : '';
        const expectedPart = openInfo.expected_harvest_label ? ` (${closingRef.dataset.expectedWord}: ${openInfo.expected_harvest_label})` : '';
        closingRef.textContent = `${closingRef.dataset.prefix}: ${farmName} · ${varietyName}${plantedPart}${expectedPart}`;
        closingRef.hidden = false;
    };

    const syncDialogFields = () => {
        const isPlanted = currentKind() === 'planted';
        if (varietyWrap) varietyWrap.hidden = !isPlanted;
        if (varietySearch) {
            varietySearch.required = isPlanted;
            varietySearch.disabled = !isPlanted;
        }
        if (varietyValue) varietyValue.disabled = !isPlanted;
        if (!isPlanted && varietyList) varietyList.hidden = true;
        updatePreview();
        updateClosingRef();
    };

    const renderVarietyMatches = (query) => {
        if (!varietyList || !varietySearch) return;
        const q = query.trim().toLowerCase();
        const matches = varietyOptions
            .filter((o) => q === '' || String(o.id) === 'unknown' || (o.search || o.label || '').toLowerCase().includes(q))
            .slice(0, 30);

        varietyList.innerHTML = '';
        if (matches.length === 0) {
            const li = document.createElement('li');
            li.className = 'fmf-combo-empty';
            li.textContent = varietySearch.dataset.emptyLabel || 'No matching variety found.';
            varietyList.appendChild(li);
        } else {
            matches.forEach((opt) => {
                const li = document.createElement('li');
                li.role = 'option';
                li.tabIndex = -1;
                li.textContent = opt.label;
                if (String(varietyValue?.value || '') === String(opt.id)) {
                    li.setAttribute('aria-selected', 'true');
                }
                li.addEventListener('mousedown', (ev) => {
                    ev.preventDefault();
                    varietySearch.value = opt.label;
                    if (varietyValue) varietyValue.value = String(opt.id);
                    varietySearch.setCustomValidity('');
                    varietyList.hidden = true;
                    varietySearch.setAttribute('aria-expanded', 'false');
                    updatePreview();
                });
                varietyList.appendChild(li);
            });
        }
        varietyList.hidden = false;
        varietySearch.setAttribute('aria-expanded', 'true');
    };

    if (varietySearch && varietyValue) {
        varietySearch.addEventListener('focus', () => renderVarietyMatches(varietySearch.value));
        varietySearch.addEventListener('input', () => {
            const typed = varietySearch.value.trim().toLowerCase();
            const exact = varietyOptions.find((o) => (o.label || '').toLowerCase() === typed);
            varietyValue.value = exact ? String(exact.id) : '';
            varietySearch.setCustomValidity('');
            renderVarietyMatches(varietySearch.value);
            updatePreview();
        });
        varietySearch.addEventListener('blur', () => {
            setTimeout(() => {
                if (varietyList) varietyList.hidden = true;
                varietySearch.setAttribute('aria-expanded', 'false');
            }, 120);
        });
    }

    dialog?.querySelector('form')?.addEventListener('submit', (e) => {
        if (currentKind() === 'planted' && varietySearch && varietyValue && !varietyValue.value) {
            e.preventDefault();
            varietySearch.setCustomValidity('Please pick a rice variety from the list.');
            varietySearch.reportValidity();
        }
    });

    dialog?.addEventListener('change', (e) => {
        if (e.target.matches('input[name="kind"], select[name="farm_id"], input[name="date"]')) {
            syncDialogFields();
        }
    });

    root.addEventListener('click', (e) => {
        const day = e.target.closest('[data-fmf-activity-open]');
        if (day && dialog) {
            const input = dialog.querySelector('input[name="date"]');
            if (input && day.dataset.fmfActivityOpen) input.value = day.dataset.fmfActivityOpen;
            open();
            return;
        }
        if (e.target.closest('[data-fmf-close]') || e.target === dialog) { close(); return; }

        // "Not now" on the harvest question: hidden for this visit; it is asked again the next time.
        const skip = e.target.closest('[data-fmf-survey-skip]');
        if (skip) {
            const card = skip.closest('[data-fmf-yield-survey]');
            try { sessionStorage.setItem('fmf-yield-survey-skip', card.dataset.fmfYieldSurvey); } catch (err) { /* private mode: only hidden now */ }
            card.hidden = true;
        }
    });

    const survey = root.querySelector('[data-fmf-yield-survey]');
    try {
        if (survey && !survey.querySelector('.fmf-error') && sessionStorage.getItem('fmf-yield-survey-skip') === survey.dataset.fmfYieldSurvey) survey.hidden = true;
    } catch (err) { /* keep it shown */ }

    // A save that failed comes back with the pop-up to open again (the form keeps what was typed).
    if (dialog && dialog.dataset.fmfOpen) open();
}
