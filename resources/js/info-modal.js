// The one iClimate information pop-up, shared by every role. It is built from the same parts as the Farmer
// Advisories "View" pop-up (.fad-modal / .fad-m-* in farmer-advisories.css): dimmed page, icon + title + short
// description, X, one scrolling body, a footer with Close. Nothing navigates away; closing returns focus to the
// button that opened it.
//
// Ways to open it:
//   <a href="/page" data-ic-view>                 the page's content (or its ?modal=1 version) inside the pop-up
//   <a href="/page" data-ic-view data-ic-select="#part">   only that part of the page
//   <button data-ic-template="tplId" data-ic-title="…">     the content of <template id="tplId"> (data-ic-close-label: the Close button's words)
//   <form data-ic-confirm="Delete this record?">            asks first (Delete / Archive / Publish …)
//   window.iClimateModal.open({ title, description, icon, kicker, body, actions, note, size, trigger })

const FOCUSABLE = 'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';

// Same strokes as resources/views/dashboards/mao/icon.blade.php, for content built in JavaScript.
export const ICONS = {
    info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
    check: '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
    alert: '<path d="M12 3 22 20H2L12 3Z"/><path d="M12 10v4M12 17h.01"/>',
    danger: '<circle cx="12" cy="12" r="9"/><path d="M12 7v6M12 16.5h.01"/>',
    map: '<path d="M12 21s6-5.2 6-10a6 6 0 1 0-12 0c0 4.8 6 10 6 10Z"/><circle cx="12" cy="11" r="2.2"/>',
    bell: '<path d="M6 9a6 6 0 1 1 12 0c0 6 2 7 2 7H4s2-1 2-7Z"/><path d="M10 20a2 2 0 0 0 4 0"/>',
    advisory: '<path d="M6 3h9l3 3v15H6z"/><path d="M9 11h6M9 15h6M9 7h3"/>',
    report: '<path d="M5 4h14v16H5z"/><path d="M9 9h6M9 13h6M9 17h3"/>',
    farmers: '<circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/><path d="M16 5.2a3 3 0 0 1 0 5.6M18.5 14.8c1.6.7 2.5 2.3 2.5 4.2"/>',
    weather: '<path d="M7 18a4 4 0 1 1 .9-7.9A5.5 5.5 0 0 1 18.5 11a3.5 3.5 0 0 1-.5 7Z"/>',
    yield: '<path d="M12 21V8M12 8c0-3 2-5 5-5 0 3-2 5-5 5ZM12 12c0-2.5-1.7-4.2-4.2-4.2 0 2.5 1.7 4.2 4.2 4.2Z"/>',
    clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    back: '<path d="M15 6l-6 6 6 6"/>',
    spark: '<path d="M11 3.5 12.9 9l5.6 1.9-5.6 1.9L11 18.4l-1.9-5.6-5.6-1.9L9.1 9z"/><path d="M18.5 15.5l.7 1.8 1.8.7-1.8.7-.7 1.8-.7-1.8-1.8-.7 1.8-.7z"/>',
};

export const icon = (name) => `<svg class="mtd-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">${ICONS[name] || ICONS.info}</svg>`;

export const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));

const CLOSE_ICON = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6 6 18"/></svg>';

let modal = null;
let dialog = null;
let opener = null;
let current = null;        // the request being shown; a late answer for an older one is dropped
let busy = false;          // a save in progress keeps the pop-up open
const cache = new Map();

function build() {
    if (modal) return;
    modal = document.createElement('div');
    modal.className = 'fad-modal ic-modal';
    modal.id = 'icInfoModal';
    modal.hidden = true;
    modal.innerHTML = '<div class="fad-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="icModalTitle" tabindex="-1" data-ic-dialog></div>';
    // A direct child of <body>: the page wrapper is transformed, which would otherwise trap a fixed overlay.
    document.body.appendChild(modal);
    dialog = modal.querySelector('[data-ic-dialog]');

    modal.addEventListener('click', (event) => {
        if (event.target.closest('[data-ic-close], [data-fad-close]') || (event.target === modal && modal.dataset.dismiss !== 'false')) close();
        else if (event.target.closest('[data-ic-retry]') && current?.retry) current.retry();
    });
}

function frame({ title, description = '', kicker = '', iconName = 'info', tone = '', closeLabel = 'Close' }) {
    return `<header class="fad-m-head ic-m-head">
            <span class="ic-m-icon ${tone ? `is-${tone}` : ''}" aria-hidden="true">${icon(iconName)}</span>
            <div class="fad-m-head-text">
                ${kicker ? `<p class="fad-label">${escapeHtml(kicker)}</p>` : ''}
                <h2 id="icModalTitle" tabindex="-1">${escapeHtml(title)}</h2>
                ${description ? `<p class="ic-m-desc" id="icModalDesc">${escapeHtml(description)}</p>` : ''}
            </div>
            <button type="button" class="fad-m-x" data-ic-close aria-label="Close ${escapeHtml(title)}">${CLOSE_ICON}</button>
        </header>
        <div class="fad-m-scroll" data-ic-body></div>
        <footer class="fad-m-foot ic-m-foot">
            <p class="ic-m-note" data-ic-note hidden></p>
            <div class="ic-m-actions" data-ic-actions></div>
            <button type="button" class="fad-btn is-quiet" data-ic-close>${escapeHtml(closeLabel)}</button>
        </footer>`;
}

function setContent(node, body) {
    if (body == null) return;
    if (typeof body === 'string') node.innerHTML = body;
    else node.replaceChildren(body);
}

function show(trigger, size, dismiss) {
    build();
    if (modal.hidden) opener = trigger || document.activeElement;
    modal.classList.toggle('is-wide', size === 'wide');
    modal.classList.toggle('is-compact', size === 'compact');
    modal.dataset.dismiss = dismiss === false ? 'false' : 'true';
    modal.hidden = false;
    document.documentElement.classList.add('fad-modal-open');
}

function focusTitle() {
    (dialog.querySelector('#icModalTitle, #fadModalTitle') || dialog).focus();
}

/**
 * Opens the pop-up with ready content.
 * body: HTML string or a Node. actions: HTML string of extra footer buttons (left of Close). note: small source/updated line.
 * Returns the dialog element so callers can bind their own controls.
 */
export function open({ title, description, kicker, icon: iconName, tone, body, actions = '', note = '', size = 'normal', trigger, dismiss, closeLabel } = {}) {
    show(trigger, size, dismiss);
    current = { id: Symbol('modal') };
    dialog.setAttribute('aria-describedby', description ? 'icModalDesc' : '');
    dialog.innerHTML = frame({ title, description, kicker, iconName, tone, closeLabel });
    setContent(dialog.querySelector('[data-ic-body]'), body);
    dialog.querySelector('[data-ic-actions]').innerHTML = actions;
    const noteEl = dialog.querySelector('[data-ic-note]');
    noteEl.hidden = !note;
    noteEl.innerHTML = note;
    dialog.scrollTop = 0;
    focusTitle();
    return dialog;
}

/** Replaces the body (and optionally the footer actions/note) of the pop-up that is open now. */
export function update({ body, actions, note, title } = {}) {
    if (!dialog || modal.hidden) return;
    if (title != null) dialog.querySelector('#icModalTitle').textContent = title;
    if (body != null) setContent(dialog.querySelector('[data-ic-body]'), body);
    if (actions != null) dialog.querySelector('[data-ic-actions]').innerHTML = actions;
    if (note != null) {
        const noteEl = dialog.querySelector('[data-ic-note]');
        noteEl.hidden = !note;
        noteEl.innerHTML = note;
    }
}

export const loadingBody = () => '<div class="ic-m-loading" aria-busy="true" role="status"><span class="fad-skel is-wide"></span><span class="fad-skel"></span><span class="fad-skel is-short"></span><span class="fad-skel is-block"></span><span class="ic-sr">Loading…</span></div>';

export const errorBody = (message = 'Please check your connection and try again.') => `<div class="fad-m-note is-danger" role="alert"><strong>Unable to load this information.</strong><span>${escapeHtml(message)}</span></div>`;

/** Shows the loading state, runs loader(); on failure shows the error with Retry. loader returns { body, actions?, note?, title? }. */
export async function openAsync(options, loader) {
    open({ ...options, body: loadingBody() });
    const request = current;
    const run = async () => {
        update({ body: loadingBody(), actions: '' });
        try {
            const result = await loader();
            if (current !== request || modal.hidden) return;
            update(result);
            focusTitle();
        } catch (error) {
            if (current !== request || modal.hidden) return;
            update({ body: errorBody(error?.userMessage), actions: '<button type="button" class="fad-btn" data-ic-retry>Retry</button>' });
            dialog.querySelector('[data-ic-retry]')?.focus();
        }
    };
    request.retry = run;
    await run();
}

const modalUrl = (href) => {
    const url = new URL(href, window.location.href);
    url.searchParams.set('modal', '1');
    return url.toString();
};

/**
 * Opens a page inside the pop-up. A page with its own pop-up version (?modal=1 answering with .fad-m-head) is shown
 * as is; any other page is reduced to its main content, its page header becoming the pop-up title.
 */
export function openUrl(href, { trigger, select, size = 'wide', title = 'Details', kicker = '' } = {}) {
    show(trigger, size);
    const request = { id: Symbol('url') };
    current = request;

    const fail = () => {
        if (current !== request) return;
        dialog.innerHTML = frame({ title, kicker });
        update({ body: errorBody(), actions: '<button type="button" class="fad-btn" data-ic-retry>Retry</button>' });
        dialog.querySelector('[data-ic-retry]')?.focus();
    };
    const run = async () => {
        dialog.innerHTML = frame({ title: `Loading ${title.toLowerCase()}…`, kicker });
        update({ body: loadingBody() });
        focusTitle();
        try {
            let html = cache.get(href);
            if (!html) {
                const response = await fetch(modalUrl(href), {
                    headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    loader: false,
                });
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                html = await response.text();
                cache.set(href, html);
            }
            if (current !== request || modal.hidden) return;
            const template = document.createElement('template');
            template.innerHTML = html;
            const doc = template.content;
            doc.querySelectorAll('script, style, link[rel="stylesheet"]').forEach((node) => node.remove());

            // A ready-made pop-up (advisories/_modal, farmer-records/_modal-*, crud/_modal): use it whole.
            if (!select && doc.querySelector('.fad-m-head')) {
                dialog.replaceChildren(doc);
            } else {
                const root = doc.querySelector('main.page-shell') || doc;
                const part = select ? root.querySelector(select) : root;
                if (!part) throw new Error('missing part');
                part.querySelectorAll('.alert-dismissible, [data-auto-dismiss], .ic-skip').forEach((node) => node.remove());
                const hero = part.querySelector('.page-hero, .fad-hero, .mfd-header');
                const heading = (hero || part).querySelector('h1, h2');
                const pageTitle = heading?.textContent.trim() || title;
                if (hero && !select) hero.remove();
                dialog.innerHTML = frame({ title: pageTitle, kicker, description: '' });
                const body = dialog.querySelector('[data-ic-body]');
                body.classList.add('ic-m-page');
                body.append(...(part === root && root.childNodes ? Array.from(root.childNodes) : [part]));
                dialog.querySelector('[data-ic-actions]').innerHTML = `<a class="fad-btn is-quiet" href="${escapeHtml(href)}" data-ic-full>Open full page</a>`;
            }
            dialog.scrollTop = 0;
            // Links inside the pop-up that open another record keep working, inside the pop-up.
            focusTitle();
        } catch {
            cache.delete(href);
            fail();
        }
    };
    request.retry = run;
    run();
}

export function close() {
    if (!modal || modal.hidden || busy) return;
    modal.hidden = true;
    current = null;
    dialog.replaceChildren();
    document.documentElement.classList.remove('fad-modal-open');
    modal.dispatchEvent(new CustomEvent('ic-modal-closed', { bubbles: true }));
    const target = opener;
    opener = null;
    if (target && document.contains(target)) target.focus();
}

export function setBusy(value) {
    busy = Boolean(value);
}

/** A confirmation in the same pop-up. Resolves true when the person confirms. */
export function confirmAction({ title = 'Please confirm', message, confirmLabel = 'Confirm', danger = false, trigger } = {}) {
    return new Promise((resolve) => {
        let answered = false;
        const finish = (value) => {
            if (answered) return;
            answered = true;
            modal.removeEventListener('ic-modal-closed', onClosed);
            resolve(value);
        };
        const onClosed = () => finish(false);
        open({
            title,
            icon: danger ? 'danger' : 'info',
            tone: danger ? 'danger' : '',
            size: 'compact',
            trigger,
            closeLabel: 'Cancel',
            body: `<p class="fad-m-body">${escapeHtml(message)}</p>`,
            actions: `<button type="button" class="fad-btn ${danger ? 'is-destructive' : ''}" data-ic-confirm-yes>${escapeHtml(confirmLabel)}</button>`,
        });
        modal.addEventListener('ic-modal-closed', onClosed);
        const yes = dialog.querySelector('[data-ic-confirm-yes]');
        yes.addEventListener('click', () => { finish(true); close(); });
        yes.focus();
    });
}

const confirmLabelFor = (message) => {
    const verb = String(message).match(/^(Delete|Remove|Archive|Publish|Make|Get|Retry|Restore|Approve)\b/i)?.[1];
    if (!verb) return 'Confirm';
    return verb.toLowerCase() === 'make' ? 'Yes, make active' : verb.charAt(0).toUpperCase() + verb.slice(1).toLowerCase();
};

export function initInfoModal() {
    if (window.iClimateModal) return;
    window.iClimateModal = { open, openAsync, openUrl, update, close, confirm: confirmAction, setBusy, icon, escapeHtml };

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[data-ic-view]');
        if (link && event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey) {
            event.preventDefault();
            event.stopPropagation();
            openUrl(link.href, { trigger: link, select: link.dataset.icSelect, title: link.dataset.icTitle || 'Details', kicker: link.dataset.icKicker || '', size: link.dataset.icSize || 'wide' });
            return;
        }
        // A small "i" with one explanation: the same pop-up, compact. Works by tap as well as by keyboard.
        const info = event.target.closest('[data-ic-info]');
        if (info) {
            event.preventDefault();
            event.stopPropagation();
            open({
                title: info.dataset.icTitle || 'About this',
                kicker: info.dataset.icKicker || '',
                icon: 'info',
                size: 'compact',
                trigger: info,
                body: `<p class="fad-m-body">${escapeHtml(info.dataset.icInfo)}</p>`,
            });
            return;
        }
        const button = event.target.closest('[data-ic-template]');
        if (button) {
            const template = document.getElementById(button.dataset.icTemplate);
            if (!template) return;
            event.preventDefault();
            open({
                title: button.dataset.icTitle || 'Details',
                description: button.dataset.icDescription || '',
                kicker: button.dataset.icKicker || '',
                icon: button.dataset.icIcon || 'info',
                size: button.dataset.icSize || 'normal',
                note: button.dataset.icNote ? escapeHtml(button.dataset.icNote) : '',
                actions: button.dataset.icHref ? `<a class="fad-btn" href="${escapeHtml(button.dataset.icHref)}">${escapeHtml(button.dataset.icHrefLabel || 'Open page')}</a>` : '',
                body: template.content.cloneNode(true),
                trigger: button,
                closeLabel: button.dataset.icCloseLabel || undefined,
            });
        }
    }, true);

    // Destructive and publishing actions ask in the same pop-up instead of the browser's own dialog.
    document.addEventListener('submit', async (event) => {
        const form = event.target.closest('form[data-ic-confirm]');
        if (!form || form.dataset.icConfirmed === 'true') return;
        event.preventDefault();
        event.stopImmediatePropagation();
        const message = form.dataset.icConfirm;
        const ok = await confirmAction({
            title: form.dataset.icConfirmTitle || 'Please confirm',
            message,
            confirmLabel: form.dataset.icConfirmLabel || confirmLabelFor(message),
            danger: /delete|remove|archive/i.test(message),
            trigger: event.submitter || form.querySelector('[type="submit"]'),
        });
        if (!ok) return;
        form.dataset.icConfirmed = 'true';
        if (form.requestSubmit) form.requestSubmit(event.submitter || undefined); else form.submit();
    }, true);

    document.addEventListener('keydown', (event) => {
        if (!modal || modal.hidden) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            close();
            return;
        }
        if (event.key !== 'Tab') return;
        const items = Array.from(dialog.querySelectorAll(FOCUSABLE)).filter((el) => el.getClientRects().length > 0);
        if (items.length === 0) {
            event.preventDefault();
            return;
        }
        const first = items[0];
        const last = items[items.length - 1];
        if (event.shiftKey && (document.activeElement === first || !dialog.contains(document.activeElement))) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && (document.activeElement === last || !dialog.contains(document.activeElement))) {
            event.preventDefault();
            first.focus();
        }
    });
}
