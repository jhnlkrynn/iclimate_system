import './bootstrap';
import 'bootstrap/dist/js/bootstrap.bundle.min.js';
import { initScrollAnimations } from './scroll-animations';
import Alpine from 'alpinejs';
// Shared by the Farmer, MAO, and IT Expert/Admin dashboards so risk-map
// rendering logic exists in exactly one place. initBarangayRiskMap() itself
// no-ops on any page without a #barangayRiskMap element.
import { initBarangayRiskMap } from './barangay-risk-map';
import { initUiEnhancements } from './ui-enhancements';
import { registerChartExplainer } from './chart-explainer';
import '../css/chart-explainer.css';
import { initFarmerDashboard } from './farmer-dashboard';

const wrapChartLabel = (label, maxLineLength = 14, maxLines = 3) => {
    const normalized = String(label ?? '').trim();
    if (!normalized) return [''];

    const words = normalized.split(/\s+/);
    const lines = [];
    let current = '';

    words.forEach((word) => {
        const pieces = word.length > maxLineLength
            ? word.match(new RegExp(`.{1,${maxLineLength}}`, 'g')) ?? [word]
            : [word];

        pieces.forEach((piece) => {
            const next = current ? `${current} ${piece}` : piece;
            if (next.length > maxLineLength && current) {
                lines.push(current);
                current = piece;
            } else {
                current = next;
            }
        });
    });

    if (current) lines.push(current);
    if (lines.length <= maxLines) return lines;

    const visible = lines.slice(0, maxLines);
    visible[maxLines - 1] = `${visible[maxLines - 1].replace(/\.+$/, '')}...`;

    return visible;
};

const horizontalCategoryTicks = (overrides = {}) => ({
    color: '#1F2937',
    maxRotation: 0,
    minRotation: 0,
    autoSkip: false,
    padding: 10,
    font: {
        size: 14,
        weight: 600,
        lineHeight: 1.25,
    },
    ...overrides,
    font: {
        size: 14,
        weight: 600,
        lineHeight: 1.25,
        ...(overrides.font ?? {}),
    },
});

window.iClimateChartLabels = {
    wrap: wrapChartLabel,
    horizontalCategoryTicks,
};

const chartSelector = [
    'canvas[id*="Chart"]',
    'canvas[data-dash-chart]',        // charts drawn by the shared dashboard-overview component (no id)
    '#farmersByOrganizationChart',
    '#farmersByOrganizationChartCompact',
    '#adminFarmersByOrganizationChart',
    '#rainfallChart',
    '#temperatureChart',
    '#humidityChart',
    '#windSpeedChart',
    '#riceYieldTrendChart',
    '#itUsersByRoleChart',
    '#itRecordsByTypeChart',
    '#itSystemActivityTrendChart',
    '#farmerWeatherTrendChart',
].join(',');

const leafletSelector = '#boundaryMap, #staffBoundaryMap, #barangayRiskMap, #farmerRiskMap';

// Inline dashboard scripts are parsed before this deferred module executes. Give
// every consumer one stable promise instead of making it race dynamic imports.
window.iClimateVisualsReady = (async () => {
    if (document.querySelector(chartSelector)) {
        try {
            const {
                Chart,
                ArcElement,
                BarController,
                BarElement,
                CategoryScale,
                DoughnutController,
                Filler,
                Legend,
                LinearScale,
                LineController,
                LineElement,
                PointElement,
                Tooltip,
            } = await import('chart.js');

            Chart.register(
                ArcElement,
                BarController,
                BarElement,
                CategoryScale,
                DoughnutController,
                Filler,
                Legend,
                LinearScale,
                LineController,
                LineElement,
                PointElement,
                Tooltip,
            );
            Chart.defaults.color = '#1F2937';
            Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
            Chart.defaults.font.size = 14;
            Chart.defaults.plugins.tooltip.titleFont = { size: 15, weight: '700' };
            Chart.defaults.plugins.tooltip.bodyFont = { size: 14 };
            // "ⓘ Learn More" + click-for-explanation on every chart, present and future.
            registerChartExplainer(Chart);
            window.Chart = Chart;
        } catch (error) {
            console.error('iClimate could not load Chart.js.', error);
        }
    }

    if (document.querySelector(leafletSelector)) {
        try {
            const { default: Leaflet } = await import('leaflet');
            window.L = Leaflet;
        } catch (error) {
            console.error('iClimate could not load Leaflet.', error);
        }
    }

    if (document.getElementById('barangayRiskMap') && window.L) {
        initBarangayRiskMap();
    }
})();

window.Alpine = Alpine;

Alpine.start();

/*
 * iClimate shared front-end helpers.
 * These are safe for Laravel forms: no fake auth redirects, no static .html navigation,
 * and no prevention of server-side validation/submission.
 */
function initApp() {
    bindNavbarState();
    bindMobileNavigation();
    bindScrollSpy();
    bindPasswordToggles();
    bindRegisterNameSync();
    bindGestureFeedback();
    bindRevealMotion();
    bindLogoutConfirmations();
    bindFastNavigation();
    bindLoadingForms();
    bindDeleteConfirmations();
    bindResponsiveCollapse();
    bindChartTabs();
    bindStatDrawers();
    initUiEnhancements(document);
    initFarmerDashboard();
}

// Support cached/back-forward pages where DOMContentLoaded has already fired.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initApp);
} else {
    initApp();
}

function bindNavbarState() {
    const navbar = document.getElementById('navbar');
    if (!navbar) return;

    const update = () => navbar.classList.toggle('scrolled', window.scrollY > 10);
    update();
    window.addEventListener('scroll', update, { passive: true });
}

function bindRevealMotion() {
    // Major content blocks that fade/rise into view once. Rows are never
    // listed: tables animate as one container (.table-responsive).
    const selectors = [
        '.page-hero',
        '.alert',
        '.card:not(.no-reveal)',
        '.glass-card:not(.no-reveal)',
        '.module-tile:not(.no-reveal)',
        '.climate-chip:not(.no-reveal)',
        '.risk-card:not(.no-reveal)',
        '.stat-card:not(.no-reveal)',
        '.soft-section:not(.no-reveal)',
        '.filter-panel:not(.no-reveal)',
        '.table-responsive:not(.no-reveal)',
        // Dashboards
        '.mao-hero', '.mao-panel', '.mao-card', '.live-weather-card', '.schedule-monitor-card', '.chart-box',
        '.it-hero', '.it-panel', '.it-card',
        '.farmer-org-card', '.farmer-org-chart-shell', '.dashboard-group',
        // Predictions, forms, tables, calendar, feeds, messages
        '.wp-panel', '.wp-metric', '.wp-result-card', '.wp-input-card',
        '.ic-table-card', '.form-card', '.calendar-card', '.upcoming-card', '.manage-panel',
        '.me-panel', '.ai-result-card', '.feed-card', '.feed-post-card', '.msg-panel', '.side-panel',
        '.advisory-card', '.advisory-safety-card', '.notice-card', '.perk-card',
    ].join(',');

    const skip = '.modal, .offcanvas, .ic-ai-panel, .ic-drawer, .ic-drawer-panel, .farmer-console, .leaflet-container, .table-responsive .card';
    const mapSelector = '.leaflet-container, #barangayRiskMap, #boundaryMap, #staffBoundaryMap';

    const elements = [...document.querySelectorAll(selectors)]
        .filter((element) => !element.closest(skip));

    // Stagger only siblings that appear together: delay follows the position
    // among matched siblings of the same parent, capped so long lists never wait.
    const perParent = new Map();
    elements.forEach((element) => {
        const index = perParent.get(element.parentElement) ?? 0;
        perParent.set(element.parentElement, index + 1);

        if (!element.dataset.reveal) {
            element.dataset.reveal = element.querySelector(mapSelector) ? 'fade' : 'fade-up';
        }
        if (!element.dataset.revealDelay) element.dataset.revealDelay = String(Math.min(index * 70, 350));
    });

    initScrollAnimations(document);
}

function bindGestureFeedback() {
    const selectors = [
        '.btn',
        '.sidebar-link',
        '.sidebar-ai-card',
        '.ai-chip',
        '.ic-ai-chip',
        '.quick-action',
        '.priority-card',
    ].join(',');

    document.querySelectorAll(selectors).forEach((element) => {
        if (element.dataset.gestureBound === 'true') return;
        element.dataset.gestureBound = 'true';

        element.addEventListener('pointerdown', (event) => {
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            if (element.classList.contains('disabled') || element.hasAttribute('disabled')) return;

            const rect = element.getBoundingClientRect();
            const ripple = document.createElement('span');
            ripple.className = 'ic-ripple';
            ripple.style.left = `${event.clientX - rect.left}px`;
            ripple.style.top = `${event.clientY - rect.top}px`;
            element.appendChild(ripple);
            ripple.addEventListener('animationend', () => ripple.remove(), { once: true });
        }, { passive: true });
    });
}

function bindMobileNavigation() {
    const hamburger = document.getElementById('hamburger');
    const navLinks = document.getElementById('navLinks');
    if (!hamburger || !navLinks) return;

    hamburger.addEventListener('click', () => {
        navLinks.classList.toggle('open');
        hamburger.classList.toggle('active');
    });

    navLinks.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            navLinks.classList.remove('open');
            hamburger.classList.remove('active');
        });
    });
}

function bindScrollSpy() {
    const sections = [...document.querySelectorAll('section[id]')];
    const navAnchors = [...document.querySelectorAll('.nav-link')];
    if (!sections.length || !navAnchors.length) return;

    const update = () => {
        let current = sections[0].id;
        sections.forEach((section) => {
            if (window.scrollY >= section.offsetTop - 120) current = section.id;
        });

        navAnchors.forEach((link) => {
            link.classList.toggle('active', link.getAttribute('href') === `#${current}`);
        });
    };

    update();
    window.addEventListener('scroll', update, { passive: true });
}

function bindPasswordToggles() {
    const pairs = [
        ['pwdToggle', 'password'],
        ['confirmPwdToggle', 'confirmPassword'],
        ['confirmPasswordToggle', 'password_confirmation'],
    ];

    pairs.forEach(([toggleId, inputId]) => {
        const toggle = document.getElementById(toggleId);
        const input = document.getElementById(inputId);
        if (!toggle || !input || toggle.dataset.bound === 'true') return;

        toggle.dataset.bound = 'true';
        toggle.addEventListener('click', () => {
            const hidden = input.type === 'password';
            input.type = hidden ? 'text' : 'password';
            toggle.setAttribute('aria-label', hidden ? 'Hide password' : 'Show password');
        });
    });
}

function bindRegisterNameSync() {
    const form = document.querySelector('.register-form');
    const firstName = document.getElementById('firstName');
    const lastName = document.getElementById('lastName');
    const name = document.getElementById('name');
    if (!form || !firstName || !lastName || !name || form.dataset.nameSyncBound === 'true') return;

    form.dataset.nameSyncBound = 'true';
    const sync = () => {
        name.value = `${firstName.value.trim()} ${lastName.value.trim()}`.trim();
    };

    firstName.addEventListener('input', sync);
    lastName.addEventListener('input', sync);
    form.addEventListener('submit', sync);
    sync();
}

function bindLoadingForms() {
    const overlay = document.getElementById('loadingOverlay');
    document.querySelectorAll('form[data-loading="true"]').forEach((form) => {
        if (form.dataset.loadingBound === 'true') return;
        form.dataset.loadingBound = 'true';

        form.addEventListener('submit', (event) => {
            if (event.defaultPrevented) return;

            if (form.matches('[data-logout-confirm]') && form.dataset.logoutConfirmed !== 'true') {
                return;
            }

            form.dataset.submitting = 'true';
            overlay?.classList.add('show');
            lockSubmitButtons(form);
        });
    });
}

function bindLogoutConfirmations() {
    const modal = document.getElementById('icLogoutConfirm');
    const cancelButton = modal?.querySelector('[data-logout-cancel]');
    const confirmButton = modal?.querySelector('[data-logout-confirm-submit]');
    const overlay = document.getElementById('loadingOverlay');
    const progress = document.getElementById('pageProgress');
    let pendingForm = null;

    const close = () => {
        modal?.classList.remove('show');
        document.body.classList.remove('ic-modal-open');
        pendingForm = null;
    };

    const open = (form) => {
        pendingForm = form;
        form.classList.remove('is-loading-action');
        delete form.dataset.submitting;
        overlay?.classList.remove('show');
        progress?.classList.remove('show');
        modal?.classList.add('show');
        document.body.classList.add('ic-modal-open');
        cancelButton?.focus();
    };

    cancelButton?.addEventListener('click', close);
    modal?.addEventListener('click', (event) => {
        if (event.target === modal) close();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal?.classList.contains('show')) close();
    });
    confirmButton?.addEventListener('click', () => {
        if (!pendingForm) return;

        const form = pendingForm;
        pendingForm = null;
        form.dataset.logoutConfirmed = 'true';
        modal?.classList.remove('show');
        confirmButton.disabled = true;
        confirmButton.textContent = 'Logging out...';
        HTMLFormElement.prototype.submit.call(form);
    });

    document.querySelectorAll('form[data-logout-confirm]').forEach((form) => {
        if (form.dataset.logoutConfirmBound === 'true') return;
        form.dataset.logoutConfirmBound = 'true';

        form.addEventListener('submit', (event) => {
            if (form.dataset.logoutConfirmed === 'true') {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();

            if (modal) {
                open(form);
            } else if (window.confirm(form.dataset.logoutConfirm || 'Are you sure you want to log out?')) {
                form.dataset.logoutConfirmed = 'true';
                HTMLFormElement.prototype.submit.call(form);
            }
        });
    });
}

function openDrawer(drawer) {
    if (!drawer) return;
    drawer.classList.add('show');
    drawer.setAttribute('aria-hidden', 'false');
    document.body.classList.add('ic-modal-open');
}

function closeDrawer(drawer) {
    if (!drawer) return;
    drawer.classList.remove('show');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('ic-modal-open');
}

function bindStatDrawers() {
    document.querySelectorAll('[data-drawer-open]').forEach((trigger) => {
        if (trigger.dataset.drawerTriggerBound === 'true') return;
        trigger.dataset.drawerTriggerBound = 'true';

        trigger.addEventListener('click', () => {
            openDrawer(document.querySelector(trigger.dataset.drawerOpen));
        });
    });

    document.querySelectorAll('.ic-drawer').forEach((drawer) => {
        if (drawer.dataset.drawerBound === 'true') return;
        drawer.dataset.drawerBound = 'true';

        drawer.querySelectorAll('[data-drawer-close]').forEach((button) => {
            button.addEventListener('click', () => closeDrawer(drawer));
        });

        drawer.addEventListener('click', (event) => {
            if (event.target === drawer) closeDrawer(drawer);
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        document.querySelectorAll('.ic-drawer.show').forEach((drawer) => closeDrawer(drawer));
    });
}

function bindFastNavigation() {
    const progress = document.getElementById('pageProgress');
    const prefetched = new Set();
    const maxPrefetches = 10;
    const samePage = (link) => {
        const url = new URL(link.href, window.location.href);

        return url.origin === window.location.origin
            && url.pathname === window.location.pathname
            && url.search === window.location.search
            && url.hash !== '';
    };
    const canWarm = (link) => {
        if (link.target && link.target !== '_self') return false;
        if (link.hasAttribute('download') || link.href.startsWith('mailto:') || link.href.startsWith('tel:')) return false;
        if (samePage(link)) return false;

        const url = new URL(link.href, window.location.href);

        return url.origin === window.location.origin;
    };
    const warmLink = (link) => {
        if (!canWarm(link) || prefetched.size >= maxPrefetches || prefetched.has(link.href)) return;

        prefetched.add(link.href);
        const prefetch = document.createElement('link');
        prefetch.rel = 'prefetch';
        prefetch.href = link.href;
        prefetch.as = 'document';
        document.head.appendChild(prefetch);
    };

    document.querySelectorAll('a[href]').forEach((link) => {
        if (link.dataset.fastNavBound === 'true') return;
        link.dataset.fastNavBound = 'true';

        link.addEventListener('mouseenter', () => warmLink(link), { passive: true });
        link.addEventListener('focus', () => warmLink(link), { passive: true });
        link.addEventListener('touchstart', () => warmLink(link), { passive: true });
        link.addEventListener('click', () => {
            if (!canWarm(link)) return;

            progress?.classList.add('show');
            document.body.classList.add('ic-page-leaving');
            link.classList.add('is-loading-action');
        });
    });

    document.querySelectorAll('form').forEach((form) => {
        if (form.dataset.fastSubmitBound === 'true') return;
        form.dataset.fastSubmitBound = 'true';

        form.addEventListener('submit', (event) => {
            if (event.defaultPrevented) return;

            if (form.matches('[data-logout-confirm]') && form.dataset.logoutConfirmed !== 'true') {
                return;
            }

            if (form.dataset.submitting === 'true') return;
            form.dataset.submitting = 'true';

            const method = (form.getAttribute('method') || 'GET').toUpperCase();
            progress?.classList.add('show');
            lockSubmitButtons(form);

            if (method !== 'GET') {
                document.body.classList.add('ic-page-leaving');
                form.classList.add('is-loading-action');
            }
        });
    });

    window.addEventListener('pageshow', () => {
        progress?.classList.remove('show');
        document.body.classList.remove('ic-page-leaving');
        document.getElementById('loadingOverlay')?.classList.remove('show');
        document.querySelectorAll('.is-loading-action').forEach((element) => element.classList.remove('is-loading-action'));
        document.querySelectorAll('form[data-submitting="true"]').forEach((form) => delete form.dataset.submitting);
        document.querySelectorAll('button[disabled]').forEach((button) => {
            if (button.dataset.loadingOriginalText) {
                button.textContent = button.dataset.loadingOriginalText;
                delete button.dataset.loadingOriginalText;
            }
            button.disabled = false;
        });
    });
}

function lockSubmitButtons(form) {
    form.querySelectorAll('button[type="submit"]').forEach((button) => {
        button.disabled = true;
        if (button.dataset.loadingText) {
            if (!button.dataset.loadingOriginalText) button.dataset.loadingOriginalText = button.textContent;
            button.textContent = button.dataset.loadingText;
        }
    });
}

function bindDeleteConfirmations() {
    document.querySelectorAll('[data-confirm]').forEach((element) => {
        if (element.dataset.confirmBound === 'true') return;
        element.dataset.confirmBound = 'true';

        element.addEventListener('click', (event) => {
            const message = element.dataset.confirm || 'Are you sure you want to continue?';
            if (!window.confirm(message)) event.preventDefault();
        });
    });
}

/*
 * Shared "expand for details" primitive: a native <details class="ic-collapse">
 * opens by default on desktop widths (today's behavior for any content moved
 * behind it) and starts collapsed on mobile so secondary content doesn't force
 * long scrolling. `data-force-collapsed`/`data-force-open` override the default
 * in both directions. Once a person manually toggles one, we stop overriding it.
 */
function bindResponsiveCollapse() {
    const desktopQuery = window.matchMedia('(min-width: 768px)');

    const defaultOpenState = (details) => {
        if (details.dataset.forceOpen === 'true') return true;
        if (details.dataset.forceCollapsed === 'true') return false;
        return desktopQuery.matches;
    };

    const applyDefaults = () => {
        document.querySelectorAll('.ic-collapse').forEach((details) => {
            if (details.dataset.userToggled === 'true') return;
            details.open = defaultOpenState(details);
        });
    };

    applyDefaults();

    if (bindResponsiveCollapse.bound) return;
    bindResponsiveCollapse.bound = true;

    desktopQuery.addEventListener('change', applyDefaults);

    document.addEventListener('toggle', (event) => {
        const target = event.target;
        if (target instanceof HTMLDetailsElement && target.classList.contains('ic-collapse')) {
            target.dataset.userToggled = 'true';
        }
    }, true);
}

/*
 * Shows one chart at a time on narrow screens for any container marked
 * [data-chart-tabs] with an `.ic-chart-tabs` button row and matching
 * [data-chart-pane] elements. Desktop keeps every chart visible (today's grid).
 */
function bindChartTabs() {
    const mobileQuery = window.matchMedia('(max-width: 767.98px)');

    document.querySelectorAll('[data-chart-tabs]').forEach((container) => {
        const tabsRow = container.querySelector('.ic-chart-tabs');
        const panes = [...container.querySelectorAll('[data-chart-pane]')];
        if (!tabsRow || !panes.length) return;

        const activate = (key) => {
            panes.forEach((pane) => {
                pane.hidden = mobileQuery.matches && pane.dataset.chartPane !== key;
            });
            tabsRow.querySelectorAll('.ic-chart-tab').forEach((tab) => {
                tab.classList.toggle('active', tab.dataset.chartTab === key);
            });
            // Chart.js canvases created while hidden (or made visible again after
            // being hidden) need a nudge to pick up their real size.
            window.dispatchEvent(new Event('resize'));
        };

        const applyMode = () => {
            if (mobileQuery.matches) {
                const activeTab = tabsRow.querySelector('.ic-chart-tab.active') || tabsRow.querySelector('.ic-chart-tab');
                activate(activeTab?.dataset.chartTab);
            } else {
                panes.forEach((pane) => { pane.hidden = false; });
            }
        };

        if (container.dataset.chartTabsBound !== 'true') {
            container.dataset.chartTabsBound = 'true';

            tabsRow.querySelectorAll('.ic-chart-tab').forEach((tab) => {
                tab.addEventListener('click', () => activate(tab.dataset.chartTab));
            });

            mobileQuery.addEventListener('change', applyMode);
        }

        applyMode();
    });
}
