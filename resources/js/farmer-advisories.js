// The farmer pages' "View" pop-up (partials/fad-modal-shell.blade.php): Advisories, Climate Records and the dashboard's
// Latest Farming Advice. A link marked data-fad-view opens in the pop-up instead of leaving the page.
// The pop-up body is the link's own page rendered without the layout (?modal=1), fetched once per page load.
// The list, its filters and the scroll position are never touched, so closing returns the farmer to the same place.

const FOCUSABLE = 'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';

export function initAdvisoryModal() {
    const modal = document.querySelector('[data-fad-modal]');
    if (!modal || modal.dataset.bound === 'true') return;
    modal.dataset.bound = 'true';
    // The page wrapper (main.page-shell) is animated with a transform, which would make it the box a fixed popup
    // covers; as a direct child of <body> the overlay covers the whole window, sidebar and top bar included.
    document.body.appendChild(modal);

    const dialog = modal.querySelector('[data-fad-dialog]');
    const loadingTemplate = document.getElementById('fadModalLoading');
    const errorTemplate = document.getElementById('fadModalError');
    const cache = new Map();
    let opener = null;
    let currentUrl = null;
    let saving = false;

    const render = (content) => dialog.replaceChildren(content);
    const focusTitle = () => (dialog.querySelector('#fadModalTitle') || dialog).focus();
    const modalUrl = (href) => {
        const url = new URL(href, window.location.href);
        url.searchParams.set('modal', '1');
        return url.toString();
    };

    async function load(href) {
        render(loadingTemplate.content.cloneNode(true));
        focusTitle();
        try {
            let html = cache.get(href);
            if (!html) {
                const response = await fetch(modalUrl(href), {
                    headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                html = await response.text();
                cache.set(href, html);
            }
            if (currentUrl !== href) return; // closed or another advisory opened meanwhile
            const template = document.createElement('template');
            template.innerHTML = html;
            render(template.content);
            dialog.scrollTop = 0;
            focusTitle();
        } catch {
            if (currentUrl !== href) return;
            render(errorTemplate.content.cloneNode(true));
            (dialog.querySelector('[data-fad-retry]') || dialog).focus();
        }
    }

    function open(href, trigger) {
        opener = trigger;
        currentUrl = href;
        modal.hidden = false;
        document.documentElement.classList.add('fad-modal-open');
        load(href);
    }

    function close() {
        if (modal.hidden || saving) return; // never drop a feedback answer that is still being saved
        modal.hidden = true;
        currentUrl = null;
        dialog.replaceChildren();
        document.documentElement.classList.remove('fad-modal-open');
        opener?.focus();
        opener = null;
    }

    document.addEventListener('click', (event) => {
        const link = event.target.closest('[data-fad-view]');
        // Ctrl/Cmd/Shift/middle click keep their normal "open in a new tab/window" meaning.
        if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        open(link.href, link);
    });

    modal.addEventListener('click', (event) => {
        if (event.target.closest('[data-fad-close]') || event.target === modal) {
            close();
        } else if (event.target.closest('[data-fad-retry]') && currentUrl) {
            cache.delete(currentUrl);
            load(currentUrl);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (modal.hidden) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            close();
            return;
        }
        if (event.key !== 'Tab') return;
        // Keep keyboard focus inside the popup.
        const items = Array.from(dialog.querySelectorAll(FOCUSABLE)).filter((el) => el.getClientRects().length > 0);
        if (items.length === 0) {
            event.preventDefault();
            return;
        }
        const first = items[0];
        const last = items[items.length - 1];
        if (event.shiftKey && (document.activeElement === first || !dialog.contains(document.activeElement))) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && (document.activeElement === last || !dialog.contains(document.activeElement))) {
            event.preventDefault();
            first.focus();
        }
    });

    // Feedback: the same route and JSON answer the farmer dashboard uses; the popup stays open.
    modal.addEventListener('submit', async (event) => {
        const form = event.target.closest('[data-fad-feedback]');
        if (!form) return;
        event.preventDefault();
        if (saving) return;

        const button = event.submitter;
        const status = form.querySelector('[data-fad-feedback-status]');
        const votes = Array.from(form.querySelectorAll('button[name="is_helpful"]'));
        const body = new FormData(form);
        if (button?.name) body.set(button.name, button.value);

        saving = true;
        votes.forEach((vote) => { vote.disabled = true; });
        status.textContent = 'Saving your answer…';
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body,
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || !data.ok) throw new Error(data.message || 'Your answer was not saved. Please try again.');
            votes.forEach((vote) => vote.setAttribute('aria-pressed', String(vote === button)));
            status.textContent = 'Thanks — your feedback was saved.';
            if (currentUrl) cache.delete(currentUrl); // reopening shows the saved answer
        } catch (error) {
            status.textContent = error.message;
        } finally {
            saving = false;
            votes.forEach((vote) => { vote.disabled = false; });
        }
    });
}
