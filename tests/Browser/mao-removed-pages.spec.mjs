import { test, expect } from '@playwright/test';

async function logIn(page, email, dashboard) {
    await page.goto('/#login');
    await page.locator('#login-email').fill(email);
    await page.locator('#login-password').fill('password');
    await page.locator('[data-login-form] button[type="submit"]').click();
    await page.waitForURL(`**${dashboard}`, { waitUntil: 'commit' });
    await page.waitForLoadState('domcontentloaded');
    await page.evaluate(() => {
        const m = document.getElementById('icWeatherAlertModal');
        if (m) {
            try { localStorage.setItem('iclimate.weather_alert_dismissed', m.getAttribute('data-alert-key') || ''); } catch (e) {}
            m.classList.remove('is-open');
            m.hidden = true;
        }
        document.documentElement.classList.remove('ic-alert-modal-lock');
    });
}

test('mao nav includes restored analysis pages while harvest-training still redirects to mao dashboard', async ({ page }, testInfo) => {
    await logIn(page, 'mao@browser.test', '/mao/dashboard');

    if (testInfo.project.name === 'mobile-375') {
        const nav = page.locator('[data-mobile-nav]');
        await expect(nav).toBeVisible();
        await nav.locator('[data-mobile-more-open]').click();
        const sheet = page.locator('[data-mobile-more]');
        await expect(sheet).toBeVisible();
        await expect(sheet.locator('a[href*="/analysis/harvest-training"]')).toHaveCount(0);
    } else {
        await page.locator('.ic-topbar-menu-btn').click();
        const sidebar = page.locator('#mobileSidebar');
        await expect(sidebar).toBeVisible();
        await expect(sidebar.locator('a[href*="/analysis/harvest-training"]')).toHaveCount(0);
    }

    for (const url of ['/analysis/impact', '/analysis/forecast', '/analysis/model-evaluation']) {
        const resp = await page.goto(url);
        expect(resp.status()).toBe(200);
    }

    await page.goto('/analysis/harvest-training');
    await page.waitForURL('**/mao/dashboard', { waitUntil: 'commit' });
});
