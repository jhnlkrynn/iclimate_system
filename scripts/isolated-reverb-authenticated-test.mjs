import { chromium } from 'playwright';

const base = process.env.PROBE_BASE_URL;
const wsUrl = process.env.PROBE_WS_URL;

if (!base || !wsUrl) throw new Error('PROBE_BASE_URL and PROBE_WS_URL are required');

async function login(browser, email, dashboardPath) {
    const context = await browser.newContext();
    const page = await context.newPage();
    const response = await page.goto(`${base}/#login`, { waitUntil: 'domcontentloaded' });
    if (!response || response.status() !== 200) throw new Error(`landing_status:${response?.status()}`);
    if (await page.locator('#login-email').count() !== 1) throw new Error(`login_form_missing:${page.url()}`);
    await page.locator('#login-email').fill(email);
    await page.locator('#login-password').fill('password');
    await Promise.all([
        page.waitForURL(`**${dashboardPath}`, { waitUntil: 'domcontentloaded' }),
        page.locator('[data-login-form] button[type="submit"]').click(),
    ]);
    if (new URL(page.url()).pathname !== dashboardPath) throw new Error(`dashboard_mismatch:${page.url()}`);
    const csrf = await page.locator('meta[name="csrf-token"]').getAttribute('content');
    const userId = await page.locator('body').getAttribute('data-user-id');
    if (!csrf || !userId) throw new Error('authenticated_markers_missing');
    return { context, page, csrf, userId: Number(userId) };
}

async function authorizeAndSubscribe(page, csrf, channel) {
    return page.evaluate(async ({ wsUrl, csrf, channel }) => {
        const socket = new WebSocket(wsUrl);
        const established = await new Promise((resolve, reject) => {
            const timer = setTimeout(() => reject(new Error('handshake_timeout')), 8000);
            socket.onmessage = (message) => {
                const data = JSON.parse(message.data);
                if (data.event === 'pusher:connection_established') {
                    clearTimeout(timer);
                    resolve(JSON.parse(data.data).socket_id);
                }
            };
        });
        const response = await fetch('/broadcasting/auth', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            body: new URLSearchParams({ socket_id: established, channel_name: channel }),
        });
        const body = await response.json();
        if (response.status !== 200 || !body.auth) return { status: response.status, subscribed: false };
        socket.send(JSON.stringify({ event: 'pusher:subscribe', data: { auth: body.auth, channel } }));
        const ack = await new Promise((resolve, reject) => {
            const timer = setTimeout(() => reject(new Error('subscription_ack_timeout')), 8000);
            socket.onmessage = (message) => {
                const data = JSON.parse(message.data);
                if (data.event === 'pusher_internal:subscription_succeeded' && data.channel === channel) {
                    clearTimeout(timer);
                    resolve(true);
                }
            };
        });
        return { status: response.status, subscribed: ack === true };
    }, { wsUrl, csrf, channel });
}

async function unauthorizedStatus(page, csrf, targetUserId) {
    return page.evaluate(async ({ wsUrl, csrf, targetUserId }) => {
        const socket = new WebSocket(wsUrl);
        const socketId = await new Promise((resolve, reject) => {
            const timer = setTimeout(() => reject(new Error('unauthorized_handshake_timeout')), 8000);
            socket.onmessage = (message) => {
                const data = JSON.parse(message.data);
                if (data.event === 'pusher:connection_established') {
                    clearTimeout(timer);
                    resolve(JSON.parse(data.data).socket_id);
                }
            };
        });
        const response = await fetch('/broadcasting/auth', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            body: new URLSearchParams({ socket_id: socketId, channel_name: `private-user.${targetUserId}` }),
        });
        socket.close();
        return response.status;
    }, { wsUrl, csrf, targetUserId });
}

async function openNotificationListener(page, csrf, channel) {
    return page.evaluate(async ({ wsUrl, csrf, channel }) => {
        const socket = new WebSocket(wsUrl);
        window.__probeEvents = [];
        return new Promise((resolve, reject) => {
            const timer = setTimeout(() => reject(new Error('notification_subscription_timeout')), 10000);
            socket.onerror = () => reject(new Error('notification_socket_error'));
            socket.onmessage = async (message) => {
                const data = JSON.parse(message.data);
                if (data.event === 'pusher:connection_established') {
                    const socketId = JSON.parse(data.data).socket_id;
                    const response = await fetch('/broadcasting/auth', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                        body: new URLSearchParams({ socket_id: socketId, channel_name: channel }),
                    });
                    const auth = await response.json();
                    if (response.status !== 200) return reject(new Error(`event_auth_status:${response.status}`));
                    socket.send(JSON.stringify({ event: 'pusher:subscribe', data: { auth: auth.auth, channel } }));
                } else if (data.event === 'pusher_internal:subscription_succeeded' && data.channel === channel) {
                    clearTimeout(timer);
                    resolve(true);
                } else if (data.event === 'notification.changed' && data.channel === channel) {
                    const payload = typeof data.data === 'string' ? JSON.parse(data.data) : data.data;
                    window.__probeEvents.push({ channel: data.channel, action: payload?.action, notificationId: payload?.notification_id });
                }
            };
        });
    }, { wsUrl, csrf, channel });
}

async function waitForNotification(page) {
    await page.waitForFunction(() => window.__probeEvents?.length > 0, null, { timeout: 10000 });
    return page.evaluate(() => window.__probeEvents.shift());
}

async function main() {
    const browser = await chromium.launch({ headless: true });
    const farmer = await login(browser, 'farmer@browser.test', '/farmer/dashboard');
    const mao = await login(browser, 'mao@browser.test', '/mao/dashboard');
    const channel = `private-user.${farmer.userId}`;
    const requireUI = process.env.PROBE_REQUIRE_UI === '1';
    if (requireUI) {
        await farmer.page.waitForFunction(() => window.Echo?.connector?.pusher?.connection?.state === 'connected'
            && Object.values(window.Echo.connector.pusher.channels.channels).some(channel => channel.name.startsWith('private-user.') && channel.subscribed), null, { timeout: 10000 });
    }
    const beforeBadge = Number(await farmer.page.locator('[data-ic-notif-badge]').first().textContent());
    const subscription = await authorizeAndSubscribe(farmer.page, farmer.csrf, channel);
    if (!subscription.subscribed) throw new Error(`subscription_failed:${subscription.status}`);
    const unauthorized = await unauthorizedStatus(farmer.page, farmer.csrf, mao.userId);
    await openNotificationListener(farmer.page, farmer.csrf, channel);
    const response = await mao.page.request.post(`${base}/notifications`, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': mao.csrf },
        form: { recipient_scope: 'specific', user_id: String(farmer.userId), title: 'Isolated WebSocket probe', message: 'Isolated', type: 'Announcement', category: 'general' },
    });
    const event = await waitForNotification(farmer.page);
    if (requireUI) {
        await farmer.page.waitForFunction(before => [...document.querySelectorAll('[data-ic-notif-badge]')].some(badge => Number(badge.textContent) > before), beforeBadge, { timeout: 10000 });
        await farmer.page.evaluate(() => { window.Echo.disconnect(); window.Echo.connect(); });
        await farmer.page.waitForFunction(() => window.Echo?.connector?.pusher?.connection?.state === 'connected', null, { timeout: 10000 });
    }
    if (response.status() !== 200) throw new Error(`notification_store_status:${response.status()}`);
    if (event.action !== 'created') throw new Error(`event_action:${event.action}`);
    if (unauthorized !== 403) throw new Error(`unauthorized_status:${unauthorized}`);
    await openNotificationListener(farmer.page, farmer.csrf, channel);
    const reconnectResponse = await mao.page.request.post(`${base}/notifications`, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': mao.csrf },
        form: { recipient_scope: 'specific', user_id: String(farmer.userId), title: 'Isolated WebSocket reconnect probe', message: 'Isolated reconnect', type: 'Announcement', category: 'general' },
    });
    const reconnectEvent = await waitForNotification(farmer.page);
    if (reconnectResponse.status() !== 200 || reconnectEvent.action !== 'created') throw new Error(`reconnect_failed:${reconnectResponse.status()}:${reconnectEvent.action}`);
    await farmer.context.close();
    await mao.context.close();
    await browser.close();
    console.log(JSON.stringify({ login: 'PASS', handshake: 'PASS', private_auth: 'PASS', subscription: 'PASS', unauthorized_status: unauthorized, event: 'PASS', reconnect: 'PASS', compiled_ui: requireUI ? 'PASS' : 'NOT_TESTED', channel, notification_id_present: Number.isInteger(event.notificationId) && Number.isInteger(reconnectEvent.notificationId) }));
}

main().catch((error) => {
    console.error(error.message);
    process.exitCode = 1;
});
