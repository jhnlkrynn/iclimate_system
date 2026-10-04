/*
 * Light / Dark appearance, the same for Farmer, MAO and IT.
 *
 * The choice lives in localStorage ('iclimate-theme') on this device. An inline script at the top of
 * layouts/app.blade.php applies it to <html> (data-theme + Bootstrap's data-bs-theme) before any stylesheet loads, so
 * the page never flashes the other theme; this module wires the Light / Dark switch in the sidebar and keeps charts
 * readable on the dark background. Colours themselves live in resources/css/theme.css.
 */
const STORAGE_KEY = 'iclimate-theme';
const DARK_CHART = { text: '#c9d6ce', grid: 'rgba(255, 255, 255, .08)' };

export function currentTheme() {
    return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
}

export function applyTheme(theme, { save = true } = {}) {
    const value = theme === 'dark' ? 'dark' : 'light';
    const root = document.documentElement;
    root.setAttribute('data-theme', value);
    root.setAttribute('data-bs-theme', value);
    root.style.colorScheme = value;
    if (save) {
        try { localStorage.setItem(STORAGE_KEY, value); } catch (error) { /* private mode: the choice lasts for this page only */ }
    }
    syncSwitches(value);
    refreshCharts();
    document.dispatchEvent(new CustomEvent('ic:themechange', { detail: { theme: value } }));
}

function syncSwitches(theme) {
    document.querySelectorAll('[data-theme-set]').forEach((button) => {
        button.setAttribute('aria-pressed', button.dataset.themeSet === theme ? 'true' : 'false');
    });
}

/* ------------------------------------------------------------------ charts */
// Each page sets its own chart colours for a light background. In dark mode this plugin swaps axis, grid, title and
// legend colours just before a chart draws, gives tooltips a dark surface, and lightens dataset colours that are too dark
// to see on a dark card (a dark green line, for example). The page's own colours come back when Light is chosen again.
// Only colours change; the data and every calculation stay as they are.
const DARK_TOOLTIP = { bg: 'rgba(16, 28, 23, .96)', border: '#36493f', text: '#e4ece7' };

function parseColour(value) {
    if (typeof value !== 'string') return null;
    let m = value.trim().match(/^#([0-9a-f]{3}|[0-9a-f]{6})$/i);
    if (m) {
        const h = m[1].length === 3 ? m[1].split('').map((c) => c + c).join('') : m[1];
        return { r: parseInt(h.slice(0, 2), 16), g: parseInt(h.slice(2, 4), 16), b: parseInt(h.slice(4, 6), 16), a: 1 };
    }
    m = value.match(/^rgba?\(\s*(\d+)[\s,]+(\d+)[\s,]+(\d+)(?:[\s,/]+([\d.]+))?\s*\)$/i);
    return m ? { r: +m[1], g: +m[2], b: +m[3], a: m[4] === undefined ? 1 : +m[4] } : null;
}

function luminance({ r, g, b }) {
    const f = (c) => { c /= 255; return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4; };
    return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
}

// A colour too dark for a dark card becomes the same hue, lightened (mixed 55% towards white); others are kept.
function lighten(value) {
    const c = parseColour(value);
    if (!c || luminance(c) > 0.09) return value;
    const mix = (x) => Math.round(x + (255 - x) * 0.55);
    return `rgba(${mix(c.r)}, ${mix(c.g)}, ${mix(c.b)}, ${c.a})`;
}

const DATASET_COLOURS = ['borderColor', 'backgroundColor', 'pointBackgroundColor', 'pointBorderColor'];

const chartThemePlugin = {
    id: 'icTheme',
    beforeUpdate(chart) {
        const dark = currentTheme() === 'dark';
        const options = chart.options || {};
        const plugins = options.plugins || {};
        const saved = chart.$icThemeSaved || (chart.$icThemeSaved = {
            scales: {},
            legend: plugins.legend?.labels?.color,
            title: plugins.title?.color,
            tooltip: plugins.tooltip ? { bg: plugins.tooltip.backgroundColor, border: plugins.tooltip.borderColor, borderWidth: plugins.tooltip.borderWidth,
                title: plugins.tooltip.titleColor, body: plugins.tooltip.bodyColor, footer: plugins.tooltip.footerColor } : null,
        });

        Object.entries(options.scales || {}).forEach(([key, scale]) => {
            if (!scale) return;
            if (!(key in saved.scales)) {
                saved.scales[key] = { ticks: scale.ticks?.color, grid: scale.grid?.color, title: scale.title?.color, border: scale.border?.color };
            }
            const original = saved.scales[key];
            if (scale.ticks) scale.ticks.color = dark ? DARK_CHART.text : original.ticks;
            if (scale.grid) scale.grid.color = dark ? DARK_CHART.grid : original.grid;
            if (scale.title) scale.title.color = dark ? DARK_CHART.text : original.title;
            if (scale.border) scale.border.color = dark ? 'rgba(255, 255, 255, .16)' : original.border;
        });
        if (plugins.legend?.labels) plugins.legend.labels.color = dark ? DARK_CHART.text : saved.legend;
        if (plugins.title) plugins.title.color = dark ? DARK_CHART.text : saved.title;
        if (plugins.tooltip && saved.tooltip) {
            const t = plugins.tooltip, o = saved.tooltip;
            t.backgroundColor = dark ? DARK_TOOLTIP.bg : o.bg;
            t.borderColor = dark ? DARK_TOOLTIP.border : o.border;
            t.borderWidth = dark ? Math.max(1, o.borderWidth || 0) : o.borderWidth;
            t.titleColor = dark ? DARK_TOOLTIP.text : o.title;
            t.bodyColor = dark ? DARK_TOOLTIP.text : o.body;
            t.footerColor = dark ? DARK_TOOLTIP.text : o.footer;
        }

        (chart.data?.datasets || []).forEach((dataset) => {
            const original = dataset.$icThemeSaved || (dataset.$icThemeSaved = Object.fromEntries(DATASET_COLOURS.map((k) => [k, dataset[k]])));
            DATASET_COLOURS.forEach((key) => {
                const value = original[key];
                if (value === undefined) return;
                dataset[key] = dark ? (Array.isArray(value) ? value.map(lighten) : lighten(value)) : value;
            });
        });
    },
};

// Called by app.js right after Chart.js loads, before any page draws a chart.
export function registerChartTheme(Chart) {
    Chart.register(chartThemePlugin);
}

function refreshCharts() {
    const instances = window.Chart?.instances;
    if (!instances) return;
    Object.values(instances).forEach((chart) => {
        try { chart.update('none'); } catch (error) { /* a chart being torn down */ }
    });
}

/* ------------------------------------------------------------------ switch */
export function initTheme() {
    syncSwitches(currentTheme());

    if (document.documentElement.dataset.themeBound !== 'true') {
        document.documentElement.dataset.themeBound = 'true';
        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-theme-set]');
            if (!button) return;
            applyTheme(button.dataset.themeSet);
        });
        // Another tab changed the theme: follow it.
        window.addEventListener('storage', (event) => {
            if (event.key === STORAGE_KEY) applyTheme(event.newValue, { save: false });
        });
    }
}
