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

test('farmer has no Forecast nav link and /weather-predictions redirects to dashboard', async ({ page }, testInfo) => {
    await logIn(page, 'farmer@browser.test', '/farmer/dashboard');

    await expect(page.locator('a[href*="/weather-predictions"]')).toHaveCount(0);

    if (testInfo.project.name === 'mobile-375') {
        await expect(page.locator('[data-mobile-nav]')).toBeVisible();
    }

    await page.goto('/weather-predictions');
    await page.waitForURL('**/farmer/dashboard', { waitUntil: 'commit' });
    await expect(page.locator('[data-farmer-dashboard], .fh-shell, main').first()).toBeVisible();
});

test('mao sees Forecast in nav and can open /weather-predictions', async ({ page }, testInfo) => {
    await logIn(page, 'mao@browser.test', '/mao/dashboard');

    if (testInfo.project.name === 'mobile-375') {
        const nav = page.locator('[data-mobile-nav]');
        await expect(nav).toBeVisible();
        await expect(nav).toContainText(/Forecast|Panahon/);
    } else {
        await page.locator('.ic-topbar-menu-btn').click();
        await expect(page.locator('#mobileSidebar a[href*="/weather-predictions"]')).toBeVisible();
    }

    await page.goto('/weather-predictions');
    await expect(page.locator('h1')).toContainText(/Forecast|Taya ng Panahon/);
    await expect(page.locator('[data-wpx-weather]')).toBeVisible();
});
