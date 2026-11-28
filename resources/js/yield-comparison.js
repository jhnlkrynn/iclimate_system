// /analysis/yield: refresh "Same season, past years" when the farm or season is changed (stored data only).
export function initYieldComparison() {
    const slot = document.querySelector('[data-yield-comparison-slot]');
    const form = document.querySelector('[data-yield-form]');
    if (!slot || !form || slot.dataset.bound === 'true') return;
    slot.dataset.bound = 'true';

    const farmInput = () => form.querySelector('[name="farm_id"]');
    const seasonInput = () => form.querySelector('[name="season"]');
    let version = 0;

    const refresh = async () => {
        const farmId = farmInput()?.value;
        const season = seasonInput()?.value;
        if (!farmId || !season) return;
        const current = slot.querySelector('[data-yield-comparison]');
        if (current?.dataset.farmId === String(farmId) && current?.dataset.season === season) return;
        const mine = ++version;
        slot.setAttribute('aria-busy', 'true');
        try {
            const url = new URL(slot.dataset.comparisonUrl, window.location.origin);
            url.searchParams.set('farm_id', farmId);
            url.searchParams.set('season', season);
            const response = await fetch(url, { headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', loader: false });
            if (!response.ok || mine !== version) return;
            slot.innerHTML = await response.text();
            const page = new URL(window.location.href);
            page.searchParams.set('farm_id', farmId);
            page.searchParams.set('season', season);
            window.history.replaceState(window.history.state, '', page);
        } catch (_) { /* the server-rendered comparison stays */ }
        finally { if (mine === version) slot.removeAttribute('aria-busy'); }
    };

    form.addEventListener('change', (event) => {
        if (event.target.matches('[name="farm_id"], [name="season"]')) void refresh();
    });
}
