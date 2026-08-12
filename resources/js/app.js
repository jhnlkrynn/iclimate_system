import './bootstrap';
import 'bootstrap/dist/js/bootstrap.bundle.min.js';
import {
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
} from 'chart.js';
import L from 'leaflet';

import Alpine from 'alpinejs';

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

window.Chart = Chart;
window.L = L;
await import('leaflet.heat');

window.Alpine = Alpine;

Alpine.start();

/*
 * iClimate shared front-end helpers.
 * These are safe for Laravel forms: no fake auth redirects, no static .html navigation,
 * and no prevention of server-side validation/submission.
 */
document.addEventListener('DOMContentLoaded', () => {
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
    bindStatDrawers();
});

function bindNavbarState() {
    const navbar = document.getElementById('navbar');
    if (!navbar) return;

    const update = () => navbar.classList.toggle('scrolled', window.scrollY > 10);
    update();
    window.addEventListener('scroll', update, { passive: true });
}

function bindRevealMotion() {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const selectors = [
        '.page-hero',
        '.alert',
        '.card',
        '.glass-card',
        '.module-tile',
        '.climate-chip',
        '.risk-card',
        '.stat-card',
        '.soft-section',
        '.filter-panel',
        '.table-responsive',
    ].join(',');

    const elements = [...document.querySelectorAll(selectors)]
        .filter((element) => !element.closest('.modal, .offcanvas, .ic-ai-panel'));

    elements.slice(0, 36).forEach((element, index) => {
        if (element.dataset.revealBound === 'true') return;
        element.dataset.revealBound = 'true';
        element.classList.add('ic-reveal');
        element.style.setProperty('--ic-reveal-delay', `${Math.min(index * 38, 260)}ms`);
    });

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;

            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -32px 0px' });

    elements.forEach((element) => observer.observe(element));
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
