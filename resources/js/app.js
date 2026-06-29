import './bootstrap';

import Alpine from 'alpinejs';

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
    bindLoadingForms();
    bindDeleteConfirmations();
});

function bindNavbarState() {
    const navbar = document.getElementById('navbar');
    if (!navbar) return;

    const update = () => navbar.classList.toggle('scrolled', window.scrollY > 10);
    update();
    window.addEventListener('scroll', update, { passive: true });
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

        form.addEventListener('submit', () => {
            overlay?.classList.add('show');
            form.querySelectorAll('button[type="submit"]').forEach((button) => {
                button.disabled = true;
                if (button.dataset.loadingText) button.textContent = button.dataset.loadingText;
            });
        });
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