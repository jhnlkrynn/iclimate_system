// Audit 2026-11-22: farmer flows end to end, on the desktop and the phone project (playwright.config.mjs).
//   A1 add a farm, draw it on the map, see the measured size, then remove the farm;
//   A2 every dashboard card opens its own summary panel; X, the overlay and Esc close it; nothing leaves the page;
//   A3 the activity calendar, the weather & predictions page and the advisories open without page errors;
//   A4 a farmer is turned away from MAO pages.
// Seeded by tests/Browser/seed-landing.php (farmer@browser.test, farms@browser.test; password "password").
import { test, expect } from '@playwright/test';

async function logIn(page, email) {
    await page.goto('/');
    await page.locator('#login-email').fill(email);
    await page.locator('#login-password').fill('password');
    await page.locator('[data-login-form] button[type="submit"]').click();
    await page.waitForURL('**/farmer/dashboard', { waitUntil: 'commit' });
}

function watchErrors(page) {
    const errors = [];
    page.on('pageerror', (e) => errors.push(e.message));
    page.on('console', (m) => {
        // The live-update socket (Reverb) is not started for browser tests.
        if (m.type() === 'error' && !/WebSocket|ERR_CONNECTION_REFUSED|tile|arcgisonline|openstreetmap/i.test(m.text())) errors.push(m.text());
    });
    return errors;
}

test('A1 add a farm, draw it on the map, see the measured size, then remove it', async ({ page }, testInfo) => {
    const errors = watchErrors(page);
    await logIn(page, 'farms@browser.test');
    const name = `Audit Field ${testInfo.project.name} ${Date.now() % 1_000_000}`;

    await page.goto('/farmer/farms/create');
    await page.locator('#farm_name').fill(name);
    await page.locator('#farm_size_value').fill('1.5');
    await page.locator('#barangay').selectOption('Luyahan');
    await page.locator('#farm_type').selectOption('Rainfed');
    await page.locator('#farmer_organization_id').selectOption({ label: 'Not a member' });
    await page.locator('form.ffm-form button[type="submit"]').click();
    await page.waitForURL('**/farmer/my-farm', { waitUntil: 'commit' });
    const card = page.locator('[data-farm-card]', { hasText: name });
    await expect(card).toBeVisible();
    const id = await card.getAttribute('data-farm-card');

    // Draw four corners around the middle of the map (the map opens on the farm's barangay).
    await page.goto(`/farmer/farms/${id}/map`);
    const map = page.locator('#farmMap');
    // Leaflet is loaded lazily (window.iClimateVisualsReady): wait until the map is really drawn, then bring it on screen.
    await expect(map).toHaveClass(/leaflet-container/, { timeout: 15_000 });
    await map.scrollIntoViewIfNeeded();
    const box = await map.boundingBox();
    const cx = box.x + box.width / 2;
    const cy = box.y + box.height / 2;
    for (const [dx, dy] of [[-40, -30], [40, -30], [40, 30], [-40, 30]]) {
        await page.mouse.click(cx + dx, cy + dy);
    }
    await expect(page.locator('#farmMapPoints')).toHaveText('4');
    const shown = await page.locator('#farmMapArea').textContent();
    expect(shown).toMatch(/\d+\.\d\d ha/);
    await page.locator('#farmMapSave').click();
    await page.waitForLoadState('networkidle');

    // Saved: the map page shows the measured area the server calculated, and no error.
    await page.goto(`/farmer/farms/${id}/map`);
    await expect(page.locator('#farmMapArea')).toHaveText(/\d+(\.\d+)? ha/);
    await expect(page.locator('.frec-note.is-danger')).toHaveCount(0);

    // Remove the farm (the site's own confirm dialog, not a browser one).
    await page.goto('/farmer/my-farm');
    await card.locator('form[data-ic-confirm] button[type="submit"]').click();
    await page.locator('[data-ic-confirm-yes]').click();
    await page.waitForURL('**/farmer/my-farm', { waitUntil: 'commit' });
    await expect(page.locator('[data-farm-card]', { hasText: name })).toHaveCount(0);
    expect(errors).toEqual([]);
});

test('A2 every dashboard card opens only its own summary panel and nothing leaves the page', async ({ page }) => {
    const errors = watchErrors(page);
    await logIn(page, 'farmer@browser.test');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('a.fh-kpi')).toHaveCount(0);
    const cards = await page.locator('[data-panel]').evaluateAll((els) => els.map((e) => e.dataset.panel));
    expect(cards.length).toBeGreaterThan(0);

    const closeWith = [
        async () => page.locator('.fh-drawer-x').click(),
        async () => page.mouse.click(5, 5),
        async () => page.keyboard.press('Escape'),
    ];
    for (const [i, key] of cards.entries()) {
        await page.locator(`[data-panel="${key}"]`).first().click();
        const drawer = page.locator('#fhDrawer');
        await expect(drawer).toHaveClass(/is-open/);
        await expect(page).toHaveURL(/\/farmer\/dashboard$/);
        // The drawer shows exactly this card's panel (copied from its template in the "panels" slot).
        const body = drawer.locator('[data-fh-drawer-body]');
        await expect(body).toHaveAttribute('data-panel', key);
        await expect(body.locator('[data-panel-title]')).toHaveCount(1);
        await expect(body.locator('.fh-drawer-more')).toHaveAttribute('href', /\/(farmer|planting|weather|heat-map)/);
        await closeWith[i % 3]();
        await expect(drawer).toBeHidden();
    }
    expect(errors).toEqual([]);
});

test('A3 the calendar, weather & predictions and advisories pages open without errors or sideways scrolling', async ({ page }) => {
    const errors = watchErrors(page);
    await logIn(page, 'farmer@browser.test');
    for (const path of ['/farmer/my-farm', '/calendar', '/weather-predictions', '/planting-advisories', '/heat-map']) {
        const res = await page.goto(path);
        expect(res.status(), path).toBe(200);
        await page.waitForLoadState('domcontentloaded');
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow, `${path} scrolls sideways`).toBeLessThanOrEqual(1);
    }
    expect(errors).toEqual([]);
});

test('A4 a farmer is turned away from MAO pages', async ({ page }) => {
    await logIn(page, 'farmer@browser.test');
    for (const path of ['/mao/dashboard', '/mao/farmer-approvals', '/mao/personnel', '/mao/planner']) {
        const res = await page.goto(path);
        const refused = res.status() === 403 || !new URL(page.url()).pathname.startsWith(path);
        expect(refused, `${path}: HTTP ${res.status()} at ${page.url()}`).toBe(true);
    }
});
