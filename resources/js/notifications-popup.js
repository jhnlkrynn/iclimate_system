// The header bell (layouts/navigation.blade.php) opens the signed-in user's notifications in the shared iClimate
// pop-up (info-modal.js) instead of leaving the page. Same data and unread count as the Notifications page
// (NotificationController@feed); opening an unread notification marks it read through the existing mark-read route,
// and every badge marked data-ic-notif-badge updates at once.
//
// "View all" stays in the same pop-up: it widens and becomes the full list the Notifications page offers (All / Unread /
// Read tabs, search, type filter, pages, open, mark as read, mark all as read) and, for accounts that may manage
// notifications (MAO and IT), send, edit and delete. The link still points at the Notifications page, so opening it in a
// new tab, or with scripts off, works as before.

import { open, update, escapeHtml, icon } from './info-modal';

const TYPE_ICON = { Advisory: 'advisory', Warning: 'alert', Announcement: 'bell' };
const TYPE_TONE = { Advisory: 'is-info', Warning: 'is-watch', Announcement: 'is-muted' };
const PAGE_SIZE = 10;                 // the Notifications page lists ten per page too
const TABS = { recent: [['all', 'All'], ['unread', 'Unread']], all: [['all', 'All'], ['unread', 'Unread'], ['read', 'Read']] };

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
const jsonHeaders = (extra = {}) => ({ Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest', ...extra });

function setBadges(count) {
    document.querySelectorAll('[data-ic-notif-badge]').forEach((badge) => {
        badge.textContent = count > 99 ? '99+' : String(count);
        badge.hidden = count <= 0;
    });
    document.querySelectorAll('[data-ic-notifications]').forEach((bell) => {
        bell.setAttribute('aria-label', count > 0 ? `Notifications, ${count} unread` : 'Notifications');
    });
}

// A notification opened from a list (Notifications page "Open", staff "View") is marked read by the server when its
// page is fetched; once the pop-up has it, the row loses its unread look and every badge takes the new count.
async function syncAfterListOpen(link) {
    const bell = document.querySelector('[data-ic-notifications]');
    if (!bell) return;
    const row = link.closest('[data-ic-notif-row], tr, .ic-table-card');
    for (let attempt = 0; attempt < 10; attempt += 1) {
        await new Promise((resolve) => { setTimeout(resolve, 500); });
        const loaded = document.querySelector('#icInfoModal:not([hidden]) [data-ic-body]:not(:has(.ic-m-loading)), #icInfoModal:not([hidden]) .fad-m-details, #icInfoModal:not([hidden]) .ic-m-page');
        if (loaded) break;
    }
    try {
        const response = await fetch(bell.dataset.feedUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin', loader: false });
        const data = await response.json();
        if (data.ok) setBadges(data.unread);
    } catch { /* the badge keeps its count until the next page */ }
    if (!row) return;
    row.classList.remove('is-unread');
    row.querySelectorAll('form[action*="mark-read"]').forEach((form) => form.remove());
    row.querySelectorAll('.fad-badge.is-good').forEach((badge) => { badge.className = 'fad-badge is-muted'; badge.textContent = 'Read'; });
    row.querySelectorAll('.badge.text-bg-warning').forEach((badge) => { badge.className = 'badge text-bg-success'; badge.textContent = 'Read'; });
}

export function initNotificationsPopup() {
    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[data-ic-view][data-ic-notif-item]');
        if (link && event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey) syncAfterListOpen(link);
    }, true); // capture: the pop-up's own link handler stops the event before it bubbles

    const bells = document.querySelectorAll('[data-ic-notifications]');
    if (!bells.length || document.documentElement.dataset.icNotifBound === 'true') return;
    document.documentElement.dataset.icNotifBound = 'true';

    const fresh = () => ({
        mode: 'recent', filter: 'all', search: '', type: '', page: 1, lastPage: 1, filtered: 0, from: 0, to: 0,
        items: [], types: [], unread: 0, total: 0, canManage: false, storeUrl: '', form: null,
        confirmId: null, flash: '', loading: false, error: false,
    });
    let state = { ...fresh(), feedUrl: '', indexUrl: '', markAllUrl: '' };

    const modalEl = () => document.getElementById('icInfoModal');
    const isAll = () => state.mode === 'all';
    const find = (id) => state.items.find((candidate) => String(candidate.id) === String(id));
    const typeBadge = (note) => (note.type ? `<span class="fad-badge ${TYPE_TONE[note.type] || 'is-muted'}">${escapeHtml(note.type)}</span>` : '');

    // ---------------------------------------------------------------- list
    const tabsMarkup = () => `<div class="ic-notif-tabs" role="tablist" aria-label="Show notifications">
        ${TABS[state.mode].map(([key, label]) => {
            const count = { all: state.total, unread: state.unread, read: Math.max(0, state.total - state.unread) }[key];
            return `<button type="button" role="tab" class="ic-notif-tab" data-ic-notif-filter="${key}" aria-selected="${state.filter === key}">${label}<span class="ic-notif-tab-count">${count}</span></button>`;
        }).join('')}
    </div>`;

    const toolsMarkup = () => `<form class="ic-notif-tools" data-ic-notif-search role="search">
        <div class="fad-field is-search">
            <label class="fad-label" for="icNotifSearch">Search</label>
            <input class="fad-input" id="icNotifSearch" type="search" name="search" value="${escapeHtml(state.search)}" placeholder="Search titles and messages">
        </div>
        <div class="fad-field">
            <label class="fad-label" for="icNotifType">Type</label>
            <select class="fad-input" id="icNotifType" name="type">
                <option value="">All</option>
                ${state.types.map((type) => `<option value="${escapeHtml(type)}" ${state.type === type ? 'selected' : ''}>${escapeHtml(type)}</option>`).join('')}
            </select>
        </div>
        <div class="ic-notif-tools-actions">
            <button type="submit" class="fad-btn is-compact">Apply</button>
            ${state.search || state.type ? '<button type="button" class="fad-btn is-quiet is-compact" data-ic-notif-reset>Reset</button>' : ''}
        </div>
    </form>`;

    const rowMarkup = (note) => {
        const main = `<button type="button" class="ic-notif-item ${note.is_read ? '' : 'is-unread'}" data-ic-notif-open="${note.id}" aria-label="${escapeHtml(`${note.is_read ? '' : 'Unread: '}${note.title}`)}">
                <span class="ic-notif-icon" data-type="${escapeHtml(String(note.type || '').toLowerCase())}">${icon(TYPE_ICON[note.type] || 'bell')}</span>
                <span class="ic-notif-main">
                    <span class="ic-notif-title">${escapeHtml(note.title)}</span>
                    ${note.message ? `<span class="ic-notif-text">${escapeHtml(note.message)}</span>` : ''}
                    <span class="ic-notif-meta">${typeBadge(note)}<time datetime="${escapeHtml(note.created_at || '')}">${escapeHtml(isAll() ? `${note.time || ''} · ${note.ago || ''}` : note.ago || '')}</time></span>
                </span>
                ${note.is_read ? '<span class="ic-notif-state">Read</span>' : '<span class="ic-notif-dot" aria-hidden="true"></span>'}
            </button>`;
        if (!isAll()) return `<li>${main}</li>`;
        if (String(state.confirmId) === String(note.id)) {
            return `<li class="ic-notif-row is-confirm" role="alertdialog" aria-label="Delete this notification?">
                <p><strong>Delete this notification?</strong> <span>${escapeHtml(note.title)}</span> This cannot be undone.</p>
                <div class="ic-notif-row-actions">
                    <button type="button" class="fad-btn is-destructive is-compact" data-ic-notif-delete-yes="${note.id}">Delete</button>
                    <button type="button" class="fad-btn is-quiet is-compact" data-ic-notif-delete-no>Cancel</button>
                </div>
            </li>`;
        }
        return `<li class="ic-notif-row">${main}
            <div class="ic-notif-row-actions">
                ${note.is_read ? '' : `<button type="button" class="fad-btn is-quiet is-compact" data-ic-notif-read="${note.id}">Mark as read</button>`}
                ${state.canManage ? `<button type="button" class="fad-btn is-quiet is-compact" data-ic-notif-edit="${note.id}">Edit</button>
                    <button type="button" class="fad-btn is-quiet is-compact is-danger-text" data-ic-notif-delete="${note.id}">Delete</button>` : ''}
            </div>
        </li>`;
    };

    const pagerMarkup = () => `<div class="ic-notif-pager">
        <p class="fad-support" role="status">Showing ${state.from}–${state.to} of ${state.filtered}</p>
        ${state.lastPage > 1 ? `<div class="ic-notif-pager-buttons">
            <button type="button" class="fad-btn is-quiet is-compact" data-ic-notif-page="${state.page - 1}" ${state.page <= 1 ? 'disabled' : ''}>Previous</button>
            <span class="fad-support">Page ${state.page} of ${state.lastPage}</span>
            <button type="button" class="fad-btn is-quiet is-compact" data-ic-notif-page="${state.page + 1}" ${state.page >= state.lastPage ? 'disabled' : ''}>Next</button>
        </div>` : ''}
    </div>`;

    const listMarkup = () => {
        const head = `${state.flash ? `<div class="fad-m-note is-good" role="status"><strong>${escapeHtml(state.flash)}</strong></div>` : ''}${tabsMarkup()}${isAll() ? toolsMarkup() : ''}`;
        if (state.loading) return `${head}<div class="ic-m-loading" role="status" aria-busy="true"><span class="fad-skel is-wide"></span><span class="fad-skel"></span><span class="fad-skel is-short"></span><span class="ic-sr">Loading notifications…</span></div>`;
        if (state.error) return `${head}<div class="fad-m-note is-danger" role="alert"><strong>Notifications could not be loaded.</strong><span>Please check your connection and try again.</span><button type="button" class="fad-btn is-quiet is-compact" data-ic-notif-retry>Retry</button></div>`;
        if (!state.items.length) {
            const filtering = isAll() && (state.search || state.type);
            const [lead, text] = filtering ? ['No notifications match.', 'Try another tab, type or search.']
                : state.filter === 'unread' ? ['You are all caught up.', 'There are no unread notifications.']
                    : state.filter === 'read' ? ['Nothing read yet.', 'Notifications you have opened will appear here.']
                        : ['No notifications yet.', 'Announcements, advisories and warnings sent to you will appear here.'];
            return `${head}<div class="fad-m-note is-good" role="status"><strong>${lead}</strong><span>${text}</span></div>`;
        }
        return `${head}<ul class="ic-notif-list ${isAll() ? 'is-full' : ''}">${state.items.map(rowMarkup).join('')}</ul>${isAll() ? pagerMarkup() : ''}`;
    };

    const footer = () => {
        const markAll = state.unread > 0 ? '<button type="button" class="fad-btn is-quiet" data-ic-notif-all>Mark all as read</button>' : '';
        if (!isAll()) return `${markAll}<a class="fad-btn" href="${escapeHtml(state.indexUrl)}" data-ic-notif-viewall>View all</a>`;
        return `${markAll}${state.canManage ? `<button type="button" class="fad-btn" data-ic-notif-new>Send notification</button>` : ''}`;
    };

    const renderList = () => {
        modalEl()?.classList.toggle('is-notif-all', isAll());
        update({ body: listMarkup(), actions: footer(), title: isAll() ? 'All Notifications' : 'Notifications' });
    };

    let ticket = 0;      // only the newest request may fill the list: a slow earlier answer is dropped

    async function load({ keepFlash = false } = {}) {
        const mine = ++ticket;
        if (!keepFlash) state.flash = '';
        state.loading = true;
        state.error = false;
        state.confirmId = null;
        renderList();
        try {
            const url = new URL(state.feedUrl, window.location.href);
            if (state.filter !== 'all') url.searchParams.set('filter', state.filter);
            if (isAll()) {
                url.searchParams.set('per_page', String(PAGE_SIZE));
                url.searchParams.set('page', String(state.page));
                if (state.search) url.searchParams.set('search', state.search);
                if (state.type) url.searchParams.set('type', state.type);
            }
            const response = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', loader: false });
            const data = await response.json().catch(() => ({}));
            if (mine !== ticket) return;
            if (!response.ok || !data.ok) throw new Error('feed');
            Object.assign(state, {
                items: data.items, unread: data.unread, total: data.total, filtered: data.filtered ?? data.items.length,
                page: data.page ?? 1, lastPage: data.last_page ?? 1, from: data.from ?? 0, to: data.to ?? 0,
                types: data.types || [], canManage: Boolean(data.can_manage), storeUrl: data.store_url || '',
            });
            setBadges(data.unread);
        } catch {
            if (mine !== ticket) return;
            state.error = true;
        }
        state.loading = false;
        renderList();
    }

    async function markRead(note) {
        if (note.is_read) return true;
        note.is_read = true;              // shown as read at once; restored if the server says no
        try {
            const response = await fetch(note.mark_read_url, { method: 'PATCH', headers: jsonHeaders(), credentials: 'same-origin', loader: false });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || !data.ok) throw new Error('mark');
            state.unread = data.unread;
            setBadges(data.unread);
            return true;
        } catch {
            note.is_read = false;
            return false;
        }
    }

    function showOne(note) {
        update({
            title: isAll() ? 'All Notifications' : 'Notifications',
            body: `<button type="button" class="ic-notif-back" data-ic-notif-back>${icon('back')}Back to notifications</button>
                <article class="ic-notif-detail">
                    <div class="ic-notif-detail-tags">${typeBadge(note)}</div>
                    <h3>${escapeHtml(note.title)}</h3>
                    <p class="fad-m-meta"><time datetime="${escapeHtml(note.created_at || '')}">${escapeHtml(note.time || '')}</time> · ${escapeHtml(note.ago || '')}</p>
                    <div class="fad-m-body ic-notif-message">${escapeHtml(note.message || '').replace(/\n/g, '<br>')}</div>
                </article>`,
            actions: isAll() && state.canManage ? `<button type="button" class="fad-btn is-quiet" data-ic-notif-edit="${note.id}">Edit</button>` : footer(),
        });
        document.querySelector('#icInfoModal [data-ic-notif-back]')?.focus();
    }

    // ---------------------------------------------------------------- send / edit (accounts that may manage notifications)
    async function formFields() {
        if (state.form) return state.form;
        const url = new URL(state.feedUrl, window.location.href);
        url.searchParams.set('form', '1');
        url.searchParams.set('per_page', '5');
        const response = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', loader: false });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.ok || !Array.isArray(data.form)) throw new Error('form');
        state.form = data.form;
        return state.form;
    }

    const fieldMarkup = (field, value) => {
        const id = `icNotifField_${field.name}`;
        const options = Object.entries(field.options || {});
        const control = field.type === 'select'
            ? `<select class="fad-input" id="${id}" name="${field.name}">${field.name === 'user_id' ? '<option value="">Select a user</option>' : ''}${options.map(([key, label]) => `<option value="${escapeHtml(key)}" ${String(value ?? '') === String(key) ? 'selected' : ''}>${escapeHtml(label)}</option>`).join('')}</select>`
            : field.type === 'textarea'
                ? `<textarea class="fad-input" id="${id}" name="${field.name}" rows="5">${escapeHtml(value ?? '')}</textarea>`
                : `<input class="fad-input" id="${id}" type="text" name="${field.name}" value="${escapeHtml(value ?? '')}">`;
        return `<div class="fad-field" data-ic-notif-field="${field.name}"><label class="fad-label" for="${id}">${escapeHtml(field.label)}</label>${control}<p class="ic-notif-field-error" data-ic-notif-error="${field.name}" hidden></p></div>`;
    };

    async function showForm(note = null) {
        update({ title: note ? 'Edit Notification' : 'Send Notification', body: '<div class="ic-m-loading" role="status" aria-busy="true"><span class="fad-skel is-wide"></span><span class="fad-skel"></span><span class="ic-sr">Loading the form…</span></div>', actions: '' });
        let fields;
        try { fields = await formFields(); } catch {
            update({ body: `<button type="button" class="ic-notif-back" data-ic-notif-back>${icon('back')}Back to notifications</button><div class="fad-m-note is-danger" role="alert"><strong>The form could not be loaded.</strong><span>Please check your connection and try again.</span></div>` });
            return;
        }
        // Sending picks who receives it; editing changes one notification that already has its recipient.
        const shown = fields.filter((field) => (note ? field.name !== 'recipient_scope' : true));
        const values = note ? { user_id: note.user_id, title: note.title, message: note.message, type: note.type, category: note.category || 'general' } : { recipient_scope: 'all', type: 'Announcement', category: 'general' };
        update({
            body: `<button type="button" class="ic-notif-back" data-ic-notif-back>${icon('back')}Back to notifications</button>
                <form class="ic-notif-form" data-ic-notif-form data-url="${escapeHtml(note ? note.update_url : state.storeUrl)}" data-method="${note ? 'PUT' : 'POST'}" novalidate>
                    <div class="fad-m-note is-danger" role="alert" data-ic-notif-form-error hidden></div>
                    ${shown.map((field) => fieldMarkup(field, values[field.name])).join('')}
                    ${note ? `<label class="ic-notif-check"><input type="checkbox" name="is_read" value="1" ${note.is_read ? 'checked' : ''}><span>Marked as read</span></label>` : ''}
                </form>`,
            actions: `<button type="button" class="fad-btn" data-ic-notif-save>${note ? 'Save changes' : 'Send notification'}</button>`,
        });
        syncRecipientField();
        document.querySelector('#icInfoModal .ic-notif-form .fad-input')?.focus();
    }

    // "Specific User" is only asked for when that is who the notification goes to.
    function syncRecipientField() {
        const form = document.querySelector('#icInfoModal [data-ic-notif-form]');
        const scope = form?.querySelector('[name="recipient_scope"]');
        const user = form?.querySelector('[data-ic-notif-field="user_id"]');
        if (scope && user) user.hidden = scope.value !== 'specific';
    }

    async function saveForm(button) {
        const form = document.querySelector('#icInfoModal [data-ic-notif-form]');
        if (!form || button.disabled) return;
        const label = button.textContent;
        const payload = Object.fromEntries(new FormData(form).entries());
        if (form.querySelector('[name="is_read"]')) payload.is_read = form.querySelector('[name="is_read"]').checked ? 1 : 0;
        if (payload.recipient_scope && payload.recipient_scope !== 'specific') delete payload.user_id;
        form.querySelectorAll('[data-ic-notif-error]').forEach((el) => { el.hidden = true; el.textContent = ''; });
        const banner = form.querySelector('[data-ic-notif-form-error]');
        banner.hidden = true;
        button.disabled = true;
        button.textContent = 'Saving…';
        try {
            const response = await fetch(form.dataset.url, { method: form.dataset.method, headers: jsonHeaders({ 'Content-Type': 'application/json' }), body: JSON.stringify(payload), credentials: 'same-origin', loader: false });
            const data = await response.json().catch(() => ({}));
            if (response.status === 422 && data.errors) {
                Object.entries(data.errors).forEach(([name, messages]) => {
                    const el = form.querySelector(`[data-ic-notif-error="${name}"]`);
                    if (el) { el.textContent = messages[0]; el.hidden = false; }
                });
                banner.innerHTML = '<strong>Please check the form.</strong><span>Some fields need your attention.</span>';
                banner.hidden = false;
                (form.querySelector('.ic-notif-field-error:not([hidden])')?.closest('.fad-field')?.querySelector('.fad-input'))?.focus();
                return;
            }
            if (!response.ok || !data.ok) throw new Error('save');
            Object.assign(state, { flash: data.message || 'Saved.', page: form.dataset.method === 'POST' ? 1 : state.page });
            await load({ keepFlash: true });
        } catch {
            banner.innerHTML = '<strong>This could not be saved.</strong><span>Please check your connection and try again.</span>';
            banner.hidden = false;
        } finally {
            if (document.contains(button)) { button.disabled = false; button.textContent = label; }
        }
    }

    async function remove(note, button) {
        button.disabled = true;
        button.textContent = 'Deleting…';
        try {
            const response = await fetch(note.destroy_url, { method: 'DELETE', headers: jsonHeaders(), credentials: 'same-origin', loader: false });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || !data.ok) throw new Error('delete');
            state.flash = data.message || 'Notification deleted.';
            if (state.items.length === 1 && state.page > 1) state.page -= 1;      // the last one on a page: step back
            await load({ keepFlash: true });
        } catch {
            state.confirmId = null;
            state.flash = '';
            state.error = true;
            renderList();
        }
    }

    // ---------------------------------------------------------------- events
    document.addEventListener('change', (event) => {
        if (event.target.matches('#icInfoModal [data-ic-notif-form] [name="recipient_scope"]')) syncRecipientField();
    });

    document.addEventListener('submit', (event) => {
        const search = event.target.closest('#icInfoModal [data-ic-notif-search]');
        if (search) {
            event.preventDefault();
            Object.assign(state, { search: search.elements.search.value.trim(), type: search.elements.type.value, page: 1 });
            load();
            return;
        }
        if (event.target.closest('#icInfoModal [data-ic-notif-form]')) {
            event.preventDefault();
            const save = document.querySelector('#icInfoModal [data-ic-notif-save]');
            if (save) saveForm(save);
        }
    });

    document.addEventListener('click', async (event) => {
        const bell = event.target.closest('[data-ic-notifications]');
        if (bell) {
            if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey) return;
            event.preventDefault();
            state = { ...fresh(), feedUrl: bell.dataset.feedUrl, indexUrl: bell.dataset.indexUrl || bell.getAttribute('href'), markAllUrl: bell.dataset.markAllUrl };
            open({ title: 'Notifications', description: 'Announcements, advisories and warnings sent to you.', icon: 'bell', size: 'normal', trigger: bell, body: '' });
            modalEl()?.addEventListener('ic-modal-closed', () => modalEl()?.classList.remove('is-notif-all'), { once: true });
            load();
            return;
        }
        const root = event.target.closest('#icInfoModal');
        if (!root || !state.feedUrl) return;
        const hit = (selector) => event.target.closest(selector);

        const viewAll = hit('[data-ic-notif-viewall]');
        if (viewAll) {
            if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey) return;   // new tab: the full page
            event.preventDefault();
            Object.assign(state, { mode: 'all', page: 1, search: '', type: '' });
            await load();
            root.querySelector('#icNotifSearch')?.focus();
            return;
        }
        const filter = hit('[data-ic-notif-filter]');
        if (filter) {
            Object.assign(state, { filter: filter.dataset.icNotifFilter, page: 1 });
            load();
            return;
        }
        if (hit('[data-ic-notif-reset]')) { Object.assign(state, { search: '', type: '', page: 1 }); load(); return; }
        const pageButton = hit('[data-ic-notif-page]');
        if (pageButton && !pageButton.disabled) {
            state.page = Math.min(state.lastPage, Math.max(1, Number(pageButton.dataset.icNotifPage)));
            await load();
            root.querySelector('.fad-m-scroll')?.scrollTo({ top: 0 });
            return;
        }
        if (hit('[data-ic-notif-retry]')) { load(); return; }
        if (hit('[data-ic-notif-back]')) {
            // The full list is re-read when a tab is filtering on read state, so a notification just opened moves tab.
            if (isAll() && state.filter !== 'all') await load(); else renderList();
            root.querySelector(`[data-ic-notif-filter="${state.filter}"]`)?.focus();
            return;
        }
        const read = hit('[data-ic-notif-read]');
        if (read) {
            const note = find(read.dataset.icNotifRead);
            if (!note) return;
            read.disabled = true;
            await markRead(note);
            if (state.filter === 'all') renderList(); else await load();
            return;
        }
        const edit = hit('[data-ic-notif-edit]');
        if (edit) { const note = find(edit.dataset.icNotifEdit); if (note) showForm(note); return; }
        if (hit('[data-ic-notif-new]')) { showForm(); return; }
        const save = hit('[data-ic-notif-save]');
        if (save) { saveForm(save); return; }
        const ask = hit('[data-ic-notif-delete]');
        if (ask) {
            state.confirmId = ask.dataset.icNotifDelete;
            state.flash = '';
            renderList();
            root.querySelector('[data-ic-notif-delete-no]')?.focus();
            return;
        }
        if (hit('[data-ic-notif-delete-no]')) { state.confirmId = null; renderList(); return; }
        const yes = hit('[data-ic-notif-delete-yes]');
        if (yes) { const note = find(yes.dataset.icNotifDeleteYes); if (note) remove(note, yes); return; }

        const item = hit('[data-ic-notif-open]');
        if (item) {
            const note = find(item.dataset.icNotifOpen);
            if (!note) return;
            showOne(note);
            await markRead(note);
            // The list it returns to: an opened notification leaves the Unread tab and joins the Read one.
            if (!isAll() && state.filter === 'unread') state.items = state.items.filter((candidate) => !candidate.is_read || candidate === note);
            return;
        }
        const all = hit('[data-ic-notif-all]');
        if (all && !all.disabled) {
            all.disabled = true;
            all.textContent = 'Marking…';
            try {
                const response = await fetch(state.markAllUrl, { method: 'POST', headers: jsonHeaders(), credentials: 'same-origin', loader: false });
                const data = await response.json().catch(() => ({}));
                if (!response.ok || !data.ok) throw new Error('all');
                state.unread = data.unread;
                setBadges(data.unread);
                if (isAll()) { state.page = 1; await load(); return; }
                state.items.forEach((note) => { note.is_read = true; });
                if (state.filter === 'unread') state.items = [];
                renderList();
            } catch {
                all.disabled = false;
                all.textContent = 'Could not update — try again';
            }
        }
    });
}
