import { test, expect } from '@playwright/test';

const accounts = [
    ['farmer', '/farmer/dashboard'],
    ['mao', '/mao/dashboard'],
];

async function signIn(page, email) {
    await page.goto('/login');
    await page.locator('#login-email').fill(`${email}@browser.test`);
    await page.locator('#login-password').fill('password');
    await page.locator('[data-login-form] button[type="submit"]').click();
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

for (const [role, dashboard] of accounts) {
    test(`${role} top bar shows 72px row, greeting, flag buttons, no live clock, and responsive controls`, async ({ page }) => {
        await signIn(page, role);
        await expect(page).toHaveURL(new RegExp(dashboard.replace('/', '\\/')));

        const topbar = page.locator('nav.ic-topbar');
        await expect(topbar).toBeVisible();
        await expect(page.locator('[data-live-clock]')).toHaveCount(0);

        const box = await topbar.boundingBox();
        expect(Math.round(box.height)).toBe(72);

        const width = page.viewportSize().width;
        if (width >= 1024) {
            await expect(topbar.locator('.ic-topbar-greeting')).toBeVisible();
        } else {
            await expect(topbar.locator('.ic-topbar-greeting')).toBeHidden();
        }

        await expect(topbar.locator('.ic-lang')).toBeVisible();
        await expect(topbar.locator('.ic-lang button[title="Filipino"][aria-label="Filipino"]')).toBeVisible();
        if (width >= 768) {
            await expect(topbar.locator('.ic-lang button[title="English"][aria-label="English"]')).toBeVisible();
        } else {
            await expect(topbar.locator('.ic-topbar-farm-wrap')).toBeHidden();
        }

        const avatarBtn = topbar.locator('.ic-avatar-btn');
        await expect(avatarBtn).toBeVisible();
        await expect(avatarBtn).toHaveAttribute('aria-label', /^Account menu for /);
        await expect(avatarBtn.locator('.ic-user-avatar-icon')).toBeVisible();

        await avatarBtn.click();
        const menu = topbar.locator('.ic-account-menu');
        await expect(menu).toBeVisible();
        await expect(menu.locator('.ic-account-logout')).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(menu).toBeHidden();
    });
}
