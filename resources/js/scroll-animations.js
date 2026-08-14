const motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
let revealObserver = null;
let parallaxFrame = null;
const parallaxElements = new Set();

function applyRevealOptions(element) {
    if (element.dataset.revealDelay) {
        element.style.setProperty('--ic-reveal-delay', `${Number(element.dataset.revealDelay) || 0}ms`);
    }

    if (element.dataset.revealDuration) {
        element.style.setProperty('--ic-reveal-duration', `${Number(element.dataset.revealDuration) || 650}ms`);
    }

    if (element.dataset.revealDistance) {
        element.style.setProperty('--ic-reveal-distance', `${Number(element.dataset.revealDistance) || 30}px`);
    }
}

export function initRevealAnimations(container = document) {
    const elements = [...container.querySelectorAll('[data-reveal]')]
        .filter((element) => element.dataset.revealBound !== 'true');

    if (!elements.length) return;

    document.body.classList.add('js-enabled');

    elements.forEach((element) => {
        element.dataset.revealBound = 'true';
        applyRevealOptions(element);
    });

    if (motionQuery.matches || !('IntersectionObserver' in window)) {
        elements.forEach((element) => element.classList.add('is-visible'));
        return;
    }

    revealObserver ??= new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;

            entry.target.classList.add('is-visible');
            revealObserver.unobserve(entry.target);

            if (window.Chart) {
                window.dispatchEvent(new Event('resize'));
            }
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -36px 0px' });

    elements.forEach((element) => revealObserver.observe(element));
}

export function applyRevealStagger(container = document, selector = '[data-reveal-stagger] > *', step = 70) {
    container.querySelectorAll(selector).forEach((element, index) => {
        if (!element.dataset.reveal) element.dataset.reveal = 'fade-up';
        if (!element.dataset.revealDelay) element.dataset.revealDelay = String(index * step);
    });
}

function updateParallax() {
    if (motionQuery.matches || window.innerWidth < 768) {
        parallaxElements.forEach((element) => element.style.setProperty('--ic-parallax-y', '0px'));
        parallaxFrame = null;
        return;
    }

    parallaxElements.forEach((element) => {
        const speed = Number(element.dataset.parallaxSpeed || 0.12);
        const limit = Number(element.dataset.parallaxLimit || 36);
        const rect = element.getBoundingClientRect();
        if (rect.bottom < -120 || rect.top > window.innerHeight + 120) return;

        const centerOffset = (rect.top + rect.height / 2) - window.innerHeight / 2;
        const shift = Math.max(-limit, Math.min(limit, centerOffset * speed * -1));
        element.style.setProperty('--ic-parallax-y', `${shift.toFixed(1)}px`);
    });

    parallaxFrame = null;
}

function requestParallax() {
    if (parallaxFrame) return;
    parallaxFrame = requestAnimationFrame(updateParallax);
}

export function initParallax(container = document) {
    container.querySelectorAll('[data-parallax]').forEach((element) => parallaxElements.add(element));
    if (!parallaxElements.size) return;

    requestParallax();
    window.addEventListener('scroll', requestParallax, { passive: true });
    window.addEventListener('resize', requestParallax);
}

export function animateValueChange(target, value) {
    if (!target || target.textContent === String(value)) return;

    if (motionQuery.matches) {
        target.textContent = value;
        return;
    }

    target.classList.remove('ic-value-changing');
    void target.offsetWidth;
    target.textContent = value;
    target.classList.add('ic-value-changing');
    target.addEventListener('animationend', () => target.classList.remove('ic-value-changing'), { once: true });
}

export function initScrollAnimations(container = document) {
    applyRevealStagger(container);
    initRevealAnimations(container);
    initParallax(container);
}

window.iClimateMotion = {
    initRevealAnimations,
    initScrollAnimations,
    animateValueChange,
};
