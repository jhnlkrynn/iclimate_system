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
        // The map itself stays still: clicking anywhere on it opens Google Maps (the .lp-map-link over it).
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
            // Whole municipality in view, clear of the office card above the pin.
            map.fitBounds(outline.getBounds(), { paddingTopLeft: [24, 90], paddingBottomRight: [24, 24], animate: false });
        }

        const pin = L.marker(office, {
            icon: L.divIcon({
                className: 'lp-pin',
                html: '<svg width="28" height="40" viewBox="0 0 28 40" aria-hidden="true"><path d="M14 0C6.3 0 0 6.2 0 13.9 0 24.3 14 40 14 40s14-15.7 14-26.1C28 6.2 21.7 0 14 0Z" fill="#EA4335"/><path d="M14 1C6.8 1 1 6.8 1 13.9c0 9.6 12.1 23.7 13 24.6.9-.9 13-15 13-24.6C27 6.8 21.2 1 14 1Z" fill="none" stroke="#B31412" stroke-width="1"/><circle cx="14" cy="14" r="5" fill="#B31412"/></svg>',
                iconSize: [28, 40], iconAnchor: [14, 40], tooltipAnchor: [0, -40],
            }),
            keyboard: false, interactive: false,
        }).addTo(map);

        const card = document.getElementById('heroMapCard');
        if (card) {
            pin.bindTooltip(card, { permanent: true, direction: 'top', offset: [0, -6], className: 'lp-map-tip', opacity: 1 }).openTooltip();
        }
        new ResizeObserver(() => map.invalidateSize()).observe(heroMap);
    }).catch(() => { /* the panel still shows the office card without the map */ });
}
