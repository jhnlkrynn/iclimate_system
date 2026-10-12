// Magic card (Magic UI): each bordered card tracks the pointer so its border glow follows it.
document.querySelectorAll([
    '.pillar-card', '.feature-card', '.role-card', '.contact-method',
    '.mission-card', '.contact-form-card', '.contact-details',
].join(',')).forEach((card) => {
    card.classList.add('magic-card');
    // The glow is positioned inside the card; leave cards that are already positioned alone.
    if (getComputedStyle(card).position === 'static') card.style.position = 'relative';
    card.addEventListener('pointermove', (event) => {
        const box = card.getBoundingClientRect();
        card.style.setProperty('--mx', `${event.clientX - box.left}px`);
        card.style.setProperty('--my', `${event.clientY - box.top}px`);
    });
});

// Hero map: a Google Maps-style street map of Lian (Esri World Street Map tiles),
// the municipal border as a red dashed line, a red pin on the Municipal
// Agriculture Office and the office card above it like an info window.
// Leaflet loads only when the map is on the page; without it the card stays
// where the markup put it.
const heroMap = document.getElementById('heroMap');
if (heroMap) {
    const boundaryShape = fetch(heroMap.dataset.boundary).then((res) => (res.ok ? res.json() : null)).catch(() => null);
    Promise.all([import('leaflet'), import('leaflet/dist/leaflet.css'), boundaryShape]).then(([{ default: L }, , boundary]) => {
        const office = [Number(heroMap.dataset.lat), Number(heroMap.dataset.lng)];
        // The map itself stays still: clicking anywhere on it opens Google Maps (the .hero-map-link over it).
        const map = L.map(heroMap, {
            zoomControl: false, dragging: false, scrollWheelZoom: false, doubleClickZoom: false,
            boxZoom: false, keyboard: false, touchZoom: false, attributionControl: false,
        }).setView(office, 12);
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}', {
            maxZoom: 18,
        }).addTo(map);

        if (boundary) {
            const outline = L.geoJSON(boundary, {
                interactive: false,
                style: { color: '#EA4335', weight: 2.5, opacity: 0.9, dashArray: '6 6', fillColor: '#EA4335', fillOpacity: 0.04 },
            }).addTo(map);
            // Whole municipality in view, clear of the office card above and the weather card below.
            map.fitBounds(outline.getBounds(), { paddingTopLeft: [32, 150], paddingBottomRight: [32, 140], animate: false });
        }

        const pin = L.marker(office, {
            icon: L.divIcon({
                className: 'hero-pin',
                html: '<svg width="28" height="40" viewBox="0 0 28 40" aria-hidden="true"><path d="M14 0C6.3 0 0 6.2 0 13.9 0 24.3 14 40 14 40s14-15.7 14-26.1C28 6.2 21.7 0 14 0Z" fill="#EA4335"/><path d="M14 1C6.8 1 1 6.8 1 13.9c0 9.6 12.1 23.7 13 24.6.9-.9 13-15 13-24.6C27 6.8 21.2 1 14 1Z" fill="none" stroke="#B31412" stroke-width="1"/><circle cx="14" cy="14" r="5" fill="#B31412"/></svg>',
                iconSize: [28, 40], iconAnchor: [14, 40], tooltipAnchor: [0, -40],
            }),
            keyboard: false, interactive: false,
        }).addTo(map);

        const card = document.getElementById('heroMapCard');
        if (card) {
            pin.bindTooltip(card, { permanent: true, direction: 'top', offset: [0, -8], className: 'hero-map-tip', opacity: 1 }).openTooltip();
        }
        new ResizeObserver(() => map.invalidateSize()).observe(heroMap);
    }).catch(() => { /* the panel still shows the office card without the map */ });
}

// Nav: one pill slides to whichever link is active (set on click and by the scroll spy).
const navLinks = document.getElementById('navLinks');
if (navLinks) {
    const indicator = document.createElement('span');
    indicator.className = 'nav-indicator';
    navLinks.prepend(indicator);
    navLinks.classList.add('has-indicator');
    const place = () => {
        const active = navLinks.querySelector('.nav-link.active');
        indicator.style.opacity = active ? '1' : '0';
        if (!active) return;
        indicator.style.width = `${active.offsetWidth}px`;
        indicator.style.height = `${active.offsetHeight}px`;
        indicator.style.transform = `translate(${active.offsetLeft}px, ${active.offsetTop}px)`;
    };
    new MutationObserver(place).observe(navLinks, { subtree: true, attributeFilter: ['class'] });
    window.addEventListener('resize', place);
    document.fonts?.ready.then(place);
    place();
}

// Number ticker (Magic UI): a figure counts up to its value, keeping its unit.
const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
window.icTick = (el) => {
    const text = el.textContent.trim();
    const match = text.match(/^(-?\d+(?:\.(\d+))?)(.*)$/);
    if (!match || reduceMotion) return;
    const target = Number(match[1]);
    const decimals = match[2]?.length ?? 0;
    const start = performance.now();
    const frame = (now) => {
        const t = Math.min((now - start) / 1200, 1);
        const eased = 1 - Math.pow(2, -10 * t);
        el.textContent = (target * (t === 1 ? 1 : eased)).toFixed(decimals) + match[3];
        if (t < 1) requestAnimationFrame(frame);
    };
    requestAnimationFrame(frame);
};
document.querySelectorAll('[data-tick]').forEach(window.icTick);

// Dia text reveal (Magic UI): a band of colour sweeps across the place line and
// leaves the text in its own colour behind it. Starts with the page, alongside the blur fade.
const dia = document.querySelector('[data-dia]');
if (dia && !reduceMotion) {
    const colors = ['#7fd6b5', '#c9f2e1', '#ffb005', '#fa7a3d', '#e1e1fe'];
    const band = 17;
    const textColor = getComputedStyle(dia).color;
    const gradient = (pos) => {
        const start = pos - band;
        const end = pos + band;
        if (start >= 100) return 'none';
        const parts = [];
        if (start > 0) parts.push(`${textColor} 0%`, `${textColor} ${start.toFixed(2)}%`);
        colors.forEach((c, i) => parts.push(`${c} ${(start + (i / (colors.length - 1)) * band * 2).toFixed(2)}%`));
        if (end < 100) parts.push(`transparent ${end.toFixed(2)}%`, 'transparent 100%');
        return `linear-gradient(90deg, ${parts.join(', ')})`;
    };
    const ease = (t) => (t < 0.5 ? 4 * t ** 3 : 1 - (-2 * t + 2) ** 3 / 2);
    Object.assign(dia.style, { color: 'transparent', webkitBackgroundClip: 'text', backgroundClip: 'text', backgroundImage: gradient(-band) });
    const startAt = performance.now();
    const frame = (now) => {
        const t = Math.min(Math.max((now - startAt) / 1200, 0), 1);
        const image = gradient(-band + ease(t) * (100 + band * 2));
        if (image === 'none' || t === 1) {
            Object.assign(dia.style, { color: '', backgroundImage: '', webkitBackgroundClip: '', backgroundClip: '' });
            return;
        }
        dia.style.backgroundImage = image;
        requestAnimationFrame(frame);
    };
    requestAnimationFrame(frame);
}
