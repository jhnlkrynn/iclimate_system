// My Farm (resources/views/farmer-records/_activity.blade.php), Farmer – Naicel: the Activity Calendar pop-up (tap a
// date, choose Planted or Harvested and the farm) and the harvest question's "Not now". Saving is a normal form post,
// so the page also works when a script fails to load. Adding and editing farms is John Luke's farm pages.

export function initFarmerMyFarm() {
    const root = document.querySelector('[data-fmf-activity]');
    if (!root) return;

    const dialog = root.querySelector('[data-fmf-activity-dialog]');
    const open = () => { if (dialog && !dialog.open) { if (dialog.showModal) dialog.showModal(); else dialog.setAttribute('open', ''); } };
    const close = () => { if (dialog) { if (dialog.close) dialog.close(); else dialog.removeAttribute('open'); } };

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
