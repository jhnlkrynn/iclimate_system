// Farmer Home's two charts (dashboards/farmer/charts.blade.php), Farmer – Naicel: "My Harvest per Season" (bars, with
// the barangay average as a line) and "Next 14 Days" (highest temperature and chance of rain). The numbers come with
// the page (a JSON block per chart, from saved data); nothing is fetched. Chart.js is the one app.js already loads.

const FONT = 18;

function read(root, name) {
    const el = root.querySelector('[data-fh-chart-data="' + name + '"]');
    if (!el) return null;
    try { return JSON.parse(el.textContent); } catch (e) { return null; }
}

function css(root, name, fallback) {
    return getComputedStyle(root).getPropertyValue(name).trim() || fallback;
}

export async function initFarmerHomeCharts(root) {
    const harvest = read(root, 'harvest');
    const weather = read(root, 'weather');
    const compare = read(root, 'compare');
    if (!harvest && !weather && !compare) return;

    await window.iClimateVisualsReady;
    const Chart = window.Chart;
    if (!Chart) return;                                     // the page keeps its text; only the drawing is missing

    const brand = css(root, '--brand', '#485A42');                  // sage-700 if the page sets no --brand
    const warm = '#c2570c';
    const blue = '#1d6fb8';
    const narrow = window.matchMedia('(max-width: 575.98px)').matches;
    const common = {
        responsive: true,
        maintainAspectRatio: false,
        animation: false,                                   // drawn complete at once: quicker on slow phones
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { position: 'bottom', labels: { font: { size: FONT }, boxWidth: 18, padding: 14 } } },
    };

    const harvestCanvas = root.querySelector('#fhHarvestChart');
    if (harvest && harvestCanvas) {
        const datasets = [{ type: 'bar', label: harvest.text.mine, data: harvest.mine, backgroundColor: brand, borderRadius: 999, borderSkipped: false, maxBarThickness: 44, order: 2 }];
        if (harvest.has_average) {
            datasets.push({ type: 'line', label: harvest.text.average, data: harvest.average, borderColor: warm, backgroundColor: warm, borderWidth: 3, pointRadius: 5, spanGaps: true, order: 1 });
        }
        new Chart(harvestCanvas, {
            type: 'bar',
            data: { labels: harvest.labels, datasets },
            options: {
                ...common,
                scales: {
                    x: { ticks: { font: { size: FONT } }, grid: { display: false } },
                    y: { beginAtZero: true, title: { display: !narrow, text: harvest.text.axis, font: { size: FONT } }, ticks: { font: { size: FONT } } },
                },
                plugins: { ...common.plugins, tooltip: { callbacks: { label: (c) => c.dataset.label + ': ' + (c.parsed.y ?? '—') } } },
            },
        });
    }

    const weatherCanvas = root.querySelector('#fhWeatherChart');
    if (weather && weatherCanvas) {
        const datasets = [{ label: weather.text.temp, data: weather.temperature, yAxisID: 'y', borderColor: warm, backgroundColor: warm, borderWidth: 3, pointRadius: 4, tension: 0.3, spanGaps: true }];
        if (weather.has_rain) {
            datasets.push({ label: weather.text.rain, data: weather.rain, yAxisID: 'y1', borderColor: blue, backgroundColor: blue, borderWidth: 3, borderDash: [6, 4], pointRadius: 4, tension: 0.3, spanGaps: false });
        }
        new Chart(weatherCanvas, {
            type: 'line',
            data: { labels: weather.labels, datasets },
            options: {
                ...common,
                scales: {
                    x: { ticks: { font: { size: narrow ? 17 : FONT }, maxRotation: 0, autoSkip: true, maxTicksLimit: narrow ? 5 : 14 }, grid: { display: false } },
                    y: { position: 'left', title: { display: !narrow, text: weather.text.temp, font: { size: FONT } }, ticks: { font: { size: FONT }, callback: (v) => v + '°' } },
                    y1: { display: weather.has_rain, position: 'right', min: 0, max: 100, grid: { drawOnChartArea: false }, title: { display: !narrow, text: weather.text.rain, font: { size: FONT } }, ticks: { font: { size: FONT }, callback: (v) => v + '%' } },
                },
            },
        });
    }

    // Farmer – Naicel (2026-10-08): "Yield Comparison": this season's estimate, the usual Lian yield and the farmer's
    // last harvest as two or three bars in t/ha. One colour per figure, the same in both themes.
    const compareCanvas = root.querySelector('#fhCompareChart');
    if (compare && compareCanvas) {
        const colors = { estimate: brand, usual: '#6b7f8c', last: warm };
        new Chart(compareCanvas, {
            type: 'bar',
            data: { labels: compare.labels, datasets: [{ data: compare.values, backgroundColor: compare.keys.map((k) => colors[k] || brand), borderRadius: 999, borderSkipped: false, maxBarThickness: 30 }] },
            options: {
                ...common,
                indexAxis: 'y',
                layout: { padding: { left: 10 } },             // room for the longest bar name
                scales: {
                    x: { beginAtZero: true, title: { display: !narrow, text: compare.text.axis, font: { size: FONT } }, ticks: { font: { size: FONT } } },
                    y: { ticks: { font: { size: FONT } }, grid: { display: false } },
                },
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => c.parsed.x.toFixed(2) + ' t/ha' } } },
            },
        });
    }
}
