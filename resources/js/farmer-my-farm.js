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
    const varietySelect = dialog?.querySelector('[data-fmf-variety-select]');
    const previewBox = dialog?.querySelector('[data-fmf-harvest-preview]');
    const previewSummary = dialog?.querySelector('[data-fmf-preview-summary]');
    const previewToday = dialog?.querySelector('[data-fmf-preview-today]');
    const closingRef = dialog?.querySelector('[data-fmf-closing-ref]');
    let previewAbort = null;

    const currentKind = () => dialog?.querySelector('input[name="kind"]:checked')?.value || 'planted';

    const updatePreview = async () => {
        if (!previewBox || !varietySelect || !dateInput || currentKind() !== 'planted') {
            if (previewBox) previewBox.hidden = true;
            return;
        }
        const dateVal = dateInput.value;
        const varietyVal = varietySelect.value;
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
        if (varietySelect) {
            varietySelect.required = isPlanted;
            varietySelect.disabled = !isPlanted;
        }
        updatePreview();
        updateClosingRef();
    };

    if (varietySearch && varietySelect) {
        varietySearch.addEventListener('input', () => {
            const q = varietySearch.value.trim().toLowerCase();
            [...varietySelect.options].forEach((opt) => {
                if (!opt.value || opt.value === 'unknown') { opt.hidden = false; return; }
                const hay = (opt.dataset.search || opt.textContent).toLowerCase();
                opt.hidden = q !== '' && !hay.includes(q);
            });
        });
    }

    dialog?.addEventListener('change', (e) => {
        if (e.target.matches('input[name="kind"], select[name="farm_id"], input[name="date"], [data-fmf-variety-select]')) {
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
