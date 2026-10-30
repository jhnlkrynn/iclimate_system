// Public landing page (welcome.blade.php): live weather, nav scroll spy, FAQ and the contact form.
// The mobile menu lives with its markup in components/public-nav.blade.php.

// Scroll reveal: [data-reveal] content fades in and rises as it enters the screen, once. Items inside a
// [data-reveal-group] follow one another 70ms apart (the first six; any after that come with the sixth). The hero
// plays on load. welcome.blade.php's head only hides content when this can run (motion allowed, IntersectionObserver).
const root = document.documentElement;
if (root.classList.contains('js-reveal')) {
    const STAGGER_MS = 70;
    const MAX_STAGGERED = 6;
    const revealNow = (el) => el.classList.add('is-visible', 'reveal-now');

    const revealGroup = (group) => {
        [...group.querySelectorAll('[data-reveal]')].forEach((item, index) => {
            item.style.setProperty('--reveal-delay', `${Math.min(index, MAX_STAGGERED - 1) * STAGGER_MS}ms`);
            item.classList.add('is-visible');
        });
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (! entry.isIntersecting) return;
            observer.unobserve(entry.target);
            if (entry.target.hasAttribute('data-reveal-group')) revealGroup(entry.target);
            else entry.target.classList.add('is-visible');
        });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.12 });

    // The hero: on load, in the order its delays set.
    requestAnimationFrame(() => document.querySelectorAll('#hero [data-reveal]').forEach((el) => el.classList.add('is-visible')));

    document.querySelectorAll('[data-reveal-group]').forEach((group) => observer.observe(group));
    document.querySelectorAll('[data-reveal]').forEach((el) => {
        if (! el.closest('#hero') && ! el.closest('[data-reveal-group]')) observer.observe(el);
    });

    // A section reached through a link is shown straight away, not after the scroll.
    const showSection = (id) => {
        const section = id && document.getElementById(id);
        if (! section) return;
        section.querySelectorAll('[data-reveal]').forEach(revealNow);
        section.querySelectorAll('[data-reveal-group]').forEach((group) => observer.unobserve(group));
        section.querySelectorAll('[data-reveal]').forEach((el) => observer.unobserve(el));
    };
    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href*="#"]');
        if (! link) return;
        const url = new URL(link.href, location.href);
        if (url.pathname === location.pathname) showSection(url.hash.slice(1));
    });
    window.addEventListener('hashchange', () => showSection(location.hash.slice(1)));
    showSection(location.hash.slice(1));

    // Nothing important stays hidden: anything on or above the screen still hidden after 2s is shown, and everything
    // is shown before printing.
    const revealAll = (onlyReached) => document.querySelectorAll('[data-reveal]:not(.is-visible)').forEach((el) => {
        if (! onlyReached || el.getBoundingClientRect().top < window.innerHeight) revealNow(el);
    });
    setTimeout(() => revealAll(true), 2000);
    window.addEventListener('beforeprint', () => revealAll(false));

    root.dataset.reveal = 'ready';
}

// Office map: an iframe never matches :focus in Chromium, so when the keyboard moves into the Google map, the page
// loses focus to it and the map box gets its focus ring here instead.
const officeMapBox = document.querySelector('[data-office-map-box]');
if (officeMapBox) {
    const frame = officeMapBox.querySelector('iframe');
    window.addEventListener('blur', () => setTimeout(() => officeMapBox.classList.toggle('is-focused', document.activeElement === frame)));
    window.addEventListener('focus', () => officeMapBox.classList.remove('is-focused'));
}

// Hero card: the Sign in / Create account tabs switch the card in place (no page load). The address keeps the mode
// (#login / #register) for Back and refresh, and links to /#register or /#login elsewhere (footer, nav) open the card in
// that mode. Each form's captcha picture is (re)loaded only while it shows, because the server keeps one security code
// per session and loading the other form's picture would replace it.
const authCard = document.querySelector('[data-auth-card]');
if (authCard) {
    const panels = { login: authCard.querySelector('[data-auth-panel="login"]'), register: authCard.querySelector('[data-auth-panel="register"]') };
    const tabs = [...authCard.querySelectorAll('[data-auth-toggle] [role="tab"]')];
    const status = authCard.querySelector('[data-auth-status]');
    const firstField = { login: '#login-email', register: '#firstName' };
    const announce = { login: 'Log in form shown.', register: 'Create account form shown.' };
    let mode = authCard.dataset.authMode === 'register' ? 'register' : 'login';

    const loadCaptcha = (panel, refresh) => panel.querySelectorAll('img[data-captcha-src]').forEach((img) => {
        if (img.getAttribute('src') && ! refresh) return;
        img.src = `${img.dataset.captchaSrc}?v=${Date.now()}`;
        const answer = panel.querySelector('[name="captcha_answer"]');
        if (answer && refresh) answer.value = '';
    });

    const show = (next, { focus = null, animate = false, updateHash = true } = {}) => {
        if (next !== 'login' && next !== 'register') return;
        const changed = next !== mode;
        mode = next;
        authCard.dataset.authMode = next;
        Object.entries(panels).forEach(([key, panel]) => {
            panel.hidden = key !== next;
        });
        tabs.forEach((tab) => {
            const selected = tab.dataset.authSwitch === next;
            tab.setAttribute('aria-selected', String(selected));
            tab.tabIndex = selected ? 0 : -1;
        });
        loadCaptcha(panels[next], changed);
        if (animate) {
            panels[next].classList.add('is-entering');
            requestAnimationFrame(() => requestAnimationFrame(() => panels[next].classList.remove('is-entering')));
        }
        if (updateHash && location.hash !== `#${next}`) history.replaceState(null, '', `#${next}`);
        if (changed) status.textContent = announce[next];
        if (focus === 'tab') tabs.find((tab) => tab.dataset.authSwitch === next)?.focus();
        if (focus === 'field') authCard.querySelector(firstField[next])?.focus({ preventScroll: true });
    };

    // Click (or Enter/Space) on a tab: show that form and move to its first field.
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => show(tab.dataset.authSwitch, { focus: 'field', animate: true }));
        tab.addEventListener('keydown', (event) => {
            const target = {
                ArrowRight: tabs[(index + 1) % tabs.length],
                ArrowLeft: tabs[(index - 1 + tabs.length) % tabs.length],
                Home: tabs[0],
                End: tabs[tabs.length - 1],
            }[event.key];
            if (! target) return;
            event.preventDefault();
            show(target.dataset.authSwitch, { focus: 'tab', animate: true });
        });
    });

    // Links to #register / #login on this page (footer "Create account", nav): open the card in that mode and bring
    // the hero into view. Handled here because clicking a link to the hash already in the address fires no event.
    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');
        if (! link || authCard.contains(link)) return;
        const url = new URL(link.href, location.href);
        const target = url.hash.slice(1);
        if ((target !== 'login' && target !== 'register') || url.pathname !== location.pathname) return;
        event.preventDefault();
        show(target, { animate: true });
        document.getElementById('hero')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        authCard.querySelector(firstField[target])?.focus({ preventScroll: true });
    });

    // A sign-up that came back with errors opens on Create account; otherwise /#register and /#login choose the mode.
    const fromHash = location.hash.slice(1);
    if (authCard.dataset.authMode === 'register') show('register');
    else if (fromHash === 'register' || fromHash === 'login') show(fromHash);
    else show('login', { updateHash: false });

    // Back/forward or a typed address between #login and #register.
    window.addEventListener('hashchange', () => {
        const target = location.hash.slice(1);
        if (target === 'login' || target === 'register') show(target, { animate: true, updateHash: false });
    });
}

// Live Lian weather, fetched once from /api/weather/current (the nav chip's data-weather-url) and shared by the nav chip
// and the Weather analysis card. Gives up after 5s; resolves to null when there is no live reading.
const weatherUrl = document.querySelector('[data-weather-chip]')?.dataset.weatherUrl;
const num = (v, digits = 0) => (v === null || v === undefined || Number.isNaN(Number(v)) ? null : Number(v).toFixed(digits));
let weatherRequest = null;
const loadWeather = () => {
    if (! weatherRequest) {
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 5000);
        weatherRequest = (weatherUrl
            ? fetch(weatherUrl, { headers: { Accept: 'application/json' }, signal: controller.signal })
                .then((res) => (res.ok ? res.json() : null))
                .then((w) => (w && w.has_real_weather && num(w.temperature) !== null ? w : null))
                .catch(() => null)
            : Promise.resolve(null))
            .finally(() => clearTimeout(timer));
    }
    return weatherRequest;
};

// The weather icon for a condition label (WeatherAPI and Open-Meteo wording).
const weatherIcon = (label = '') => {
    const text = label.toLowerCase();
    if (text.includes('thunder')) return 'cloud-lightning';
    if (text.includes('drizzle') || text.includes('light rain') || text.includes('patchy rain')) return 'cloud-drizzle';
    if (text.includes('rain') || text.includes('shower')) return 'cloud-rain';
    if (text.includes('wind') || text.includes('gale')) return 'wind';
    if (text.includes('cloud') || text.includes('overcast') || text.includes('fog') || text.includes('mist')) return 'cloud';
    return 'sun';
};

// The Weather analysis card: the stored record rendered on the server stays when there is no live reading.
const weather = document.querySelector('[data-weather]');
if (weather) {
    const set = (selector, value) => {
        const el = weather.querySelector(selector);
        if (el && value !== null) el.textContent = value;
    };
    loadWeather().then((w) => {
        if (! w) return;
        set('[data-weather-temp]', `${num(w.temperature)}°C`);
        if (num(w.humidity) !== null) set('[data-weather-humidity]', `${num(w.humidity)}%`);
        if (num(w.wind_speed) !== null) set('[data-weather-wind]', `${num(w.wind_speed)} km/h`);
        if (num(w.rainfall, 1) !== null) set('[data-weather-rain]', `${num(w.rainfall, 1)} mm`);
        set('[data-weather-rain-label]', 'Rainfall today');
        set('[data-weather-status]', `Live now · ${w.condition_label || 'Current weather'}${w.updated_label ? ` · ${w.updated_label}` : ''}`);
    });
}

// Nav weather chip: [icon 27°C · Lian], and a panel with the details. Hidden when there is no live reading.
const chip = document.querySelector('[data-weather-chip]');
if (chip) {
    const wrap = chip.closest('[data-weather-chip-wrap]');
    const panel = document.getElementById(chip.getAttribute('aria-controls'));
    const setText = (selector, value) => { const el = panel.querySelector(selector); if (el) el.textContent = value; };

    loadWeather().then((w) => {
        if (! w) {
            wrap.hidden = true;
            return;
        }
        const condition = w.condition_label || 'Current weather';
        const temp = `${num(w.temperature)}°C`;
        const icon = weatherIcon(condition);
        chip.querySelector('[data-weather-skeleton]').remove();
        chip.querySelectorAll('[data-weather-icon]').forEach((svg) => svg.classList.toggle('hidden', svg.dataset.weatherIcon !== icon));
        chip.querySelector('[data-weather-chip-temp]').textContent = temp;
        chip.querySelector('[data-weather-chip-text]').classList.remove('hidden');
        chip.dataset.state = 'ready';
        chip.dataset.icon = icon;
        chip.setAttribute('aria-label', `Current weather in Lian: ${temp}, ${condition.toLowerCase()}`);

        setText('[data-weather-panel-condition]', condition);
        setText('[data-weather-panel-temp]', temp);
        setText('[data-weather-panel-humidity]', num(w.humidity) !== null ? `${num(w.humidity)}%` : '--');
        setText('[data-weather-panel-wind]', num(w.wind_speed) !== null ? `${num(w.wind_speed, 1)} km/h` : '--');
        setText('[data-weather-panel-rain]', num(w.rainfall, 1) !== null ? `${num(w.rainfall, 1)} mm` : '--');
        const updated = w.updated_at ? new Date(w.updated_at) : null;
        setText('[data-weather-panel-updated]', updated && ! Number.isNaN(updated.getTime())
            ? `Updated ${updated.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', timeZone: 'Asia/Manila' })}`
            : '');
    });

    const setOpen = (open, { restoreFocus = false } = {}) => {
        panel.hidden = ! open;
        chip.setAttribute('aria-expanded', String(open));
        if (open) panel.focus();
        else if (restoreFocus) chip.focus();
    };
    chip.addEventListener('click', () => {
        if (chip.dataset.state === 'ready') setOpen(panel.hidden);
    });
    document.addEventListener('click', (event) => {
        if (! panel.hidden && ! wrap.contains(event.target)) setOpen(false);
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && ! panel.hidden) setOpen(false, { restoreFocus: true });
    });
    // Leaving the panel with Tab closes it.
    panel.addEventListener('focusout', (event) => {
        if (! wrap.contains(event.relatedTarget)) setOpen(false);
    });
}

// Nav scroll spy: highlight the link of the section being read. While the page scrolls to a clicked
// link, the spy waits so the sections passed on the way do not flash one after another.
const navLinks = [...document.querySelectorAll('[data-nav-link]')];
const spySections = [...new Set(navLinks.map((link) => link.dataset.navLink))]
    .map((id) => document.getElementById(id))
    .filter(Boolean);
if (navLinks.length && spySections.length) {
    const highlight = (id) => navLinks.forEach((link) => {
        const active = link.dataset.navLink === id;
        link.classList.toggle('is-active', active);
        if (active) link.setAttribute('aria-current', 'true');
        else link.removeAttribute('aria-current');
    });
    let spyPaused = false;
    let resumeTimer;
    const resume = () => { spyPaused = false; clearTimeout(resumeTimer); };
    window.addEventListener('scrollend', resume);
    navLinks.forEach((link) => link.addEventListener('click', () => {
        highlight(link.dataset.navLink);
        spyPaused = true;
        clearTimeout(resumeTimer);
        resumeTimer = setTimeout(resume, 1500); // browsers without the scrollend event
    }));

    const observer = new IntersectionObserver((entries) => {
        if (spyPaused) return;
        entries.forEach((entry) => { if (entry.isIntersecting) highlight(entry.target.id); });
    }, { rootMargin: '-40% 0px -55% 0px', threshold: 0 });
    spySections.forEach((section) => observer.observe(section));
}

// FAQ accordion: opening one question closes the others. Buttons give Enter/Space for free.
document.querySelectorAll('[data-accordion]').forEach((accordion) => {
    const triggers = [...accordion.querySelectorAll('[data-accordion-trigger]')];
    triggers.forEach((trigger) => trigger.addEventListener('click', () => {
        const opening = trigger.getAttribute('aria-expanded') !== 'true';
        triggers.forEach((other) => {
            const open = other === trigger && opening;
            other.setAttribute('aria-expanded', String(open));
            document.getElementById(other.getAttribute('aria-controls')).hidden = !open;
        });
    }));
});

// "Send a message": delivered to the MAO staff as a notification, without leaving the page.
const contactForm = document.getElementById('contactForm');
if (contactForm) {
    const status = document.getElementById('contactStatus');
    const submit = document.getElementById('contactSubmit');
    const say = (text, ok) => {
        status.textContent = text;
        status.className = `rounded-lg px-3 py-2 text-sm ${ok ? 'bg-tint text-brand' : 'bg-red-50 text-red-700'}`;
        status.hidden = false;
    };
    const clearErrors = () => contactForm.querySelectorAll('[data-error-for]').forEach((el) => {
        el.hidden = true;
        el.textContent = '';
        contactForm.elements[el.dataset.errorFor]?.removeAttribute('aria-invalid');
    });
    const showErrors = (errors) => Object.entries(errors).forEach(([field, messages]) => {
        const el = contactForm.querySelector(`[data-error-for="${field}"]`);
        if (!el) return;
        el.textContent = messages[0];
        el.hidden = false;
        contactForm.elements[field]?.setAttribute('aria-invalid', 'true');
    });

    contactForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearErrors();
        submit.disabled = true;
        say('Sending your message...', true);
        try {
            const response = await fetch(contactForm.action, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(contactForm),
            });
            const data = await response.json().catch(() => ({}));
            if (response.ok && data.ok) {
                say(data.message, true);
                contactForm.reset();
            } else if (response.status === 422) {
                showErrors(data.errors || {});
                say('Please check the fields marked below.', false);
                contactForm.querySelector('[aria-invalid="true"]')?.focus();
            } else if (response.status === 419) {
                say('This page was open too long. Please reload it and send your message again.', false);
            } else if (response.status === 429) {
                say('Too many messages were sent from here. Please try again in a few minutes.', false);
            } else {
                say(data.message || 'Your message could not be sent. Please email the office instead.', false);
            }
        } catch (error) {
            say('Your message could not be sent. Please check your connection and try again.', false);
        } finally {
            submit.disabled = false;
        }
    });
}
