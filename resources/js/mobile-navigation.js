const initMobileNavigation = () => {
    const layer = document.querySelector('[data-mobile-more]');
    const openButton = document.querySelector('[data-mobile-more-open]');
    const sheet = layer?.querySelector('.mobile-more-sheet');
    if (!layer || !openButton || !sheet || layer.dataset.bound === 'true') return;

    layer.dataset.bound = 'true';
    let lastFocus = null;
    let touchStart = 0;

    const focusables = () => [...sheet.querySelectorAll('a[href], button, input, select, textarea, [tabindex]:not([tabindex="-1"])')]
        .filter((element) => !element.disabled && element.offsetParent !== null);

    const close = () => {
        if (layer.hidden) return;
        layer.hidden = true;
        openButton.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('mobile-nav-open');
        lastFocus?.focus?.();
        lastFocus = null;
    };

    const open = () => {
        lastFocus = document.activeElement;
        layer.hidden = false;
        openButton.setAttribute('aria-expanded', 'true');
        document.body.classList.add('mobile-nav-open');
        requestAnimationFrame(() => sheet.focus());
    };

    openButton.addEventListener('click', () => (layer.hidden ? open() : close()));
    layer.querySelectorAll('[data-mobile-more-close]').forEach((element) => element.addEventListener('click', (event) => {
        if (event.currentTarget === event.target || event.currentTarget.matches('button')) close();
    }));
    layer.querySelectorAll('.mobile-more-row').forEach((link) => link.addEventListener('click', close));
    sheet.addEventListener('touchstart', (event) => { touchStart = event.changedTouches[0]?.clientY ?? 0; }, { passive: true });
    sheet.addEventListener('touchend', (event) => {
        if (event.changedTouches[0]?.clientY - touchStart > 60) close();
    }, { passive: true });
    document.addEventListener('keydown', (event) => {
        if (layer.hidden) return;
        if (event.key === 'Escape') { event.preventDefault(); close(); return; }
        if (event.key !== 'Tab') return;
        const items = focusables();
        if (items.length === 0) return;
        const first = items[0];
        const last = items[items.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
};

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initMobileNavigation, { once: true });
else initMobileNavigation();
