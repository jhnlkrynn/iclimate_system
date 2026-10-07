// My Farm (resources/views/farmer-records/my-farm.blade.php), Farmer – Naicel: the "Add / Edit Farm" pop-up, the
// Activity Calendar pop-up (tap a date, choose Planted or Harvested and the farm) and the harvest question's "Not now".
// Everything is saved by normal form posts, so the page also works when a script fails to load.

export function initFarmerMyFarm() {
    const root = document.querySelector('[data-my-farm]');
    if (!root) return;

    const open = (dialog) => { if (dialog && !dialog.open) { if (dialog.showModal) dialog.showModal(); else dialog.setAttribute('open', ''); } };
    const close = (dialog) => { if (dialog) { if (dialog.close) dialog.close(); else dialog.removeAttribute('open'); } };

    const farmDialog = root.querySelector('[data-fmf-farm-dialog]');
    const activityDialog = root.querySelector('[data-fmf-activity-dialog]');

    /** Points the farm form at "add" or at one farm; `fill` is false when the form already holds what the farmer typed. */
    function showFarm(target, button, fill) {
        if (!farmDialog) return;
        const form = farmDialog.querySelector('form');
        const editing = target !== 'new' && button;
        form.action = editing ? button.dataset.action : farmDialog.dataset.actionNew;
        form.querySelector('[data-fmf-method]').value = editing ? 'PUT' : 'POST';
        farmDialog.querySelector('[data-fmf-farm-title]').textContent = editing ? farmDialog.dataset.titleEdit : farmDialog.dataset.titleNew;
        if (fill) {
            form.elements.name.value = editing ? button.dataset.name : '';
            form.elements.barangay.value = editing ? button.dataset.barangay : '';
            form.elements.area_hectares.value = editing ? button.dataset.area : '';
            const type = editing ? button.dataset.type : 'Irrigated';
            form.querySelectorAll('input[name="farm_type"]').forEach((radio) => { radio.checked = radio.value === type; });
            farmDialog.querySelectorAll('.fmf-error').forEach((el) => el.remove());
        }
        open(farmDialog);
    }

    root.addEventListener('click', (e) => {
        const farmButton = e.target.closest('[data-fmf-farm-open]');
        if (farmButton) { showFarm(farmButton.dataset.fmfFarmOpen, farmButton, true); return; }

        const dayButton = e.target.closest('[data-fmf-activity-open]');
        if (dayButton && activityDialog) {
            const input = activityDialog.querySelector('input[name="date"]');
            if (input && dayButton.dataset.fmfActivityOpen) input.value = dayButton.dataset.fmfActivityOpen;
            open(activityDialog);
            return;
        }

        const closer = e.target.closest('[data-fmf-close]');
        if (closer) { close(closer.closest('dialog')); return; }

        // A click on the dimmed area around a pop-up closes it.
        if (e.target instanceof HTMLDialogElement) { close(e.target); return; }

        // "Not now" on the harvest question: hidden for this visit; it is asked again the next time.
        const skip = e.target.closest('[data-fmf-survey-skip]');
        if (skip) {
            const card = skip.closest('[data-fmf-yield-survey]');
            try { sessionStorage.setItem('fmf-yield-survey-skip', card.dataset.fmfYieldSurvey); } catch (err) { /* private mode: only hidden now */ }
            card.hidden = true;
        }
    });

    root.addEventListener('submit', (e) => {
        const form = e.target.closest('[data-fmf-confirm]');
        if (form && !window.confirm(form.dataset.fmfConfirm)) e.preventDefault();
    });

    const survey = root.querySelector('[data-fmf-yield-survey]');
    try {
        if (survey && !survey.querySelector('.fmf-error') && sessionStorage.getItem('fmf-yield-survey-skip') === survey.dataset.fmfYieldSurvey) survey.hidden = true;
    } catch (err) { /* keep it shown */ }

    // A save that failed comes back with the pop-up to open again (the form keeps what was typed).
    if (farmDialog && farmDialog.dataset.fmfOpen) {
        const target = farmDialog.dataset.fmfOpen;
        showFarm(target, target === 'new' ? null : root.querySelector('[data-fmf-farm-open="' + target + '"]'), false);
    }
    if (activityDialog && activityDialog.dataset.fmfOpen) open(activityDialog);
}
