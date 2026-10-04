// The header bell (layouts/navigation.blade.php) opens the signed-in user's notifications in the shared iClimate
// pop-up (info-modal.js) instead of leaving the page. Same data and unread count as the Notifications page
// (NotificationController@feed); opening an unread notification marks it read through the existing mark-read route,
// and every badge marked data-ic-notif-badge updates at once.

import { open, update, escapeHtml, icon } from './info-modal';

const TYPE_ICON = { Advisory: 'advisory', Warning: 'alert', Announcement: 'bell' };
const TYPE_TONE = { Advisory: 'is-info', Warning: 'is-watch', Announcement: 'is-muted' };

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

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

    let state = { filter: 'all', items: [], unread: 0, total: 0, feedUrl: '', indexUrl: '', markAllUrl: '', loading: false };

    const listMarkup = () => {
        const tabs = `<div class="ic-notif-tabs" role="tablist" aria-label="Show notifications">
            ${[['all', 'All', state.total], ['unread', 'Unread', state.unread]].map(([key, label, count]) => `<button type="button" role="tab" class="ic-notif-tab" data-ic-notif-filter="${key}" aria-selected="${state.filter === key}">${label}<span class="ic-notif-tab-count">${count}</span></button>`).join('')}
        </div>`;
        if (state.loading) return `${tabs}<div class="ic-m-loading" role="status" aria-busy="true"><span class="fad-skel is-wide"></span><span class="fad-skel"></span><span class="fad-skel is-short"></span><span class="ic-sr">Loading notifications…</span></div>`;
        if (state.error) return `${tabs}<div class="fad-m-note is-danger" role="alert"><strong>Notifications could not be loaded.</strong><span>Please check your connection and try again.</span><button type="button" class="fad-btn is-quiet is-compact" data-ic-notif-retry>Retry</button></div>`;
        if (!state.items.length) {
            return `${tabs}<div class="fad-m-note is-good" role="status"><strong>${state.filter === 'unread' ? 'You are all caught up.' : 'No notifications yet.'}</strong><span>${state.filter === 'unread' ? 'There are no unread notifications.' : 'Announcements, advisories and warnings sent to you will appear here.'}</span></div>`;
        }
        return `${tabs}<ul class="ic-notif-list">${state.items.map((note) => `
            <li>
                <button type="button" class="ic-notif-item ${note.is_read ? '' : 'is-unread'}" data-ic-notif-open="${note.id}" aria-label="${escapeHtml(`${note.is_read ? '' : 'Unread: '}${note.title}`)}">
                    <span class="ic-notif-icon" data-type="${escapeHtml(String(note.type || '').toLowerCase())}">${icon(TYPE_ICON[note.type] || 'bell')}</span>
                    <span class="ic-notif-main">
                        <span class="ic-notif-title">${escapeHtml(note.title)}</span>
                        ${note.message ? `<span class="ic-notif-text">${escapeHtml(note.message)}</span>` : ''}
                        <span class="ic-notif-meta">${note.type ? `<span class="fad-badge ${TYPE_TONE[note.type] || 'is-muted'}">${escapeHtml(note.type)}</span>` : ''}<time datetime="${escapeHtml(note.created_at || '')}">${escapeHtml(note.ago || '')}</time></span>
                    </span>
                    ${note.is_read ? '<span class="ic-notif-state">Read</span>' : '<span class="ic-notif-dot" aria-hidden="true"></span>'}
                </button>
            </li>`).join('')}</ul>`;
    };

    const footer = () => `${state.unread > 0 ? '<button type="button" class="fad-btn is-quiet" data-ic-notif-all>Mark all as read</button>' : ''}<a class="fad-btn" href="${escapeHtml(state.indexUrl)}">View all</a>`;

    const renderList = () => update({ body: listMarkup(), actions: footer() });

    async function load() {
        state.loading = true;
        state.error = false;
        renderList();
        try {
            const url = new URL(state.feedUrl, window.location.href);
            if (state.filter === 'unread') url.searchParams.set('filter', 'unread');
            const response = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', loader: false });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || !data.ok) throw new Error('feed');
            Object.assign(state, { items: data.items, unread: data.unread, total: data.total });
            setBadges(data.unread);
        } catch {
            state.error = true;
        } finally {
            state.loading = false;
            renderList();
        }
    }

    async function markRead(note) {
        if (note.is_read) return;
        note.is_read = true;              // shown as read at once; restored if the server says no
        try {
            const response = await fetch(note.mark_read_url, {
                method: 'PATCH',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                loader: false,
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || !data.ok) throw new Error('mark');
            state.unread = data.unread;
            setBadges(data.unread);
        } catch {
            note.is_read = false;
        }
    }

    function showOne(note) {
        update({
            body: `<button type="button" class="ic-notif-back" data-ic-notif-back>${icon('back')}Back to notifications</button>
                <article class="ic-notif-detail">
                    <div class="ic-notif-detail-tags">${note.type ? `<span class="fad-badge ${TYPE_TONE[note.type] || 'is-muted'}">${escapeHtml(note.type)}</span>` : ''}</div>
                    <h3>${escapeHtml(note.title)}</h3>
                    <p class="fad-m-meta"><time datetime="${escapeHtml(note.created_at || '')}">${escapeHtml(note.time || '')}</time> · ${escapeHtml(note.ago || '')}</p>
                    <div class="fad-m-body ic-notif-message">${escapeHtml(note.message || '').replace(/\n/g, '<br>')}</div>
                </article>`,
            actions: footer(),
        });
        document.querySelector('#icInfoModal [data-ic-notif-back]')?.focus();
    }

    document.addEventListener('click', async (event) => {
        const bell = event.target.closest('[data-ic-notifications]');
        if (bell) {
            if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey) return;
            event.preventDefault();
            Object.assign(state, { filter: 'all', feedUrl: bell.dataset.feedUrl, indexUrl: bell.dataset.indexUrl || bell.getAttribute('href'), markAllUrl: bell.dataset.markAllUrl });
            open({ title: 'Notifications', description: 'Announcements, advisories and warnings sent to you.', icon: 'bell', size: 'normal', trigger: bell, body: '' });
            load();
            return;
        }
        const root = event.target.closest('#icInfoModal');
        if (!root || !state.feedUrl) return;

        const filter = event.target.closest('[data-ic-notif-filter]');
        if (filter) {
            state.filter = filter.dataset.icNotifFilter;
            load();
            return;
        }
        if (event.target.closest('[data-ic-notif-retry]')) { load(); return; }
        if (event.target.closest('[data-ic-notif-back]')) {
            renderList();
            root.querySelector(`[data-ic-notif-filter="${state.filter}"]`)?.focus();
            return;
        }
        const item = event.target.closest('[data-ic-notif-open]');
        if (item) {
            const note = state.items.find((candidate) => String(candidate.id) === item.dataset.icNotifOpen);
            if (!note) return;
            showOne(note);
            await markRead(note);
            if (state.filter === 'unread') state.items = state.items.filter((candidate) => !candidate.is_read || candidate === note);
            return;
        }
        const all = event.target.closest('[data-ic-notif-all]');
        if (all && !all.disabled) {
            all.disabled = true;
            all.textContent = 'Marking…';
            try {
                const response = await fetch(state.markAllUrl, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    loader: false,
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok || !data.ok) throw new Error('all');
                state.items.forEach((note) => { note.is_read = true; });
                state.unread = data.unread;
                setBadges(data.unread);
                if (state.filter === 'unread') state.items = [];
                renderList();
            } catch {
                all.disabled = false;
                all.textContent = 'Could not update — try again';
            }
        }
    });
}
