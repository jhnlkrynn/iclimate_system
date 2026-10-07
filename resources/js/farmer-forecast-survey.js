// The Forecast page's small survey (weather-predictions/partials/forecast-survey.blade.php), Farmer – Naicel:
// "Tama ba ang taya ng panahon kahapon?". "Skip" hides it for that day on this device; an answer is sent without
// leaving the page and the card becomes a short thank-you. Without scripts it is a normal form post.

const SKIP_KEY = 'iclimate-forecast-survey-skip';

export function initForecastSurvey() {
    const form = document.querySelector('[data-forecast-survey]');
    if (!form) return;

    const day = form.dataset.forecastSurvey;
    try { if (localStorage.getItem(SKIP_KEY) === day) { form.hidden = true; return; } } catch (e) { /* storage blocked: keep it shown */ }

    const observed = form.querySelector('[data-forecast-survey-observed]');
    const status = form.querySelector('[data-forecast-survey-status]');
    const showObserved = () => {
        const answer = form.querySelector('input[name="accuracy"]:checked');
        const needed = !!answer && answer.value !== 'accurate';
        observed.hidden = !needed;
        if (!needed) observed.querySelectorAll('input').forEach((input) => { input.checked = false; });
    };
    showObserved();
    form.addEventListener('change', (e) => { if (e.target.name === 'accuracy') showObserved(); });

    form.addEventListener('click', (e) => {
        if (!e.target.closest('[data-forecast-survey-skip]')) return;
        try { localStorage.setItem(SKIP_KEY, day); } catch (err) { /* only hidden now */ }
        form.hidden = true;
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;
        try {
            const res = await fetch(form.action, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form),
                loader: false,
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.ok) throw new Error(data.message || 'bad status ' + res.status);
            const thanks = document.createElement('p');
            thanks.className = 'fad-card wpx-survey wpx-survey-thanks';
            thanks.setAttribute('role', 'status');
            thanks.textContent = data.message;
            form.replaceWith(thanks);
        } catch (err) {
            console.error('Forecast survey failed:', err);
            status.textContent = form.dataset.textFailed;
            button.disabled = false;
        }
    });
}
