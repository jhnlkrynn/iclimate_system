// Magic card (Magic UI), signed-in app: the border glow in resources/css/app-magic.css follows the pointer.
// One listener on the document, so cards a live refresh puts on the page (farmer and IT dashboards) glow too.
// Nested cards each get the position, so an outer card's glow follows the pointer as well.
const MAGIC_CARD = [
    '.card', '.glass-card', '.module-tile', '.kpi-tile',
    '.mfd-card', '.mfd-kpi', '.itd-card', '.fh-card', '.fh-kpi',
    '.mtd-card', '.mtd-tile', '.mtd-kpi', '.mhd-tile',
].join(',');

if (window.matchMedia('(hover: hover)').matches) {
    document.addEventListener('pointermove', (event) => {
        let card = event.target.closest?.(MAGIC_CARD);
        while (card) {
            const box = card.getBoundingClientRect();
            card.style.setProperty('--mx', `${event.clientX - box.left}px`);
            card.style.setProperty('--my', `${event.clientY - box.top}px`);
            card = card.parentElement?.closest(MAGIC_CARD);
        }
    }, { passive: true });
}
