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
}

for (const [role, dashboard] of accounts) {
    test(`${role} top bar shows greeting, muted date, no live clock, and responsive controls`, async ({ page }) => {
        await signIn(page, role);
        await expect(page).toHaveURL(new RegExp(`${dashboard.replace('/', '\\/')}$`));

        const topbar = page.locator('nav.ic-topbar');
        await expect(topbar).toBeVisible();
        await expect(page.locator('[data-live-clock]')).toHaveCount(0);

        const box = await topbar.boundingBox();
        expect(Math.round(box.height)).toBe(64);

        const width = page.viewportSize().width;
        if (width >= 1024) {
            await expect(topbar.locator('.ic-topbar-greeting')).toBeVisible();
            await expect(topbar.locator('.ic-topbar-date')).toHaveText(/^[A-Z][a-z]{2}, [A-Z][a-z]{2} \d{1,2}$/);
        } else {
            await expect(topbar.locator('.ic-topbar-greeting')).toBeHidden();
        }

        if (role !== 'it') {
            if (width >= 768) {
                await expect(topbar.locator('.ic-lang')).toBeVisible();
                await expect(topbar.locator('.ic-lang button')).toHaveText(['EN', 'FIL']);
            } else {
                await expect(topbar.locator('.ic-lang')).toBeHidden();
                await expect(topbar.locator('.ic-topbar-farm-wrap')).toBeHidden();
            }
        }
    });
}
