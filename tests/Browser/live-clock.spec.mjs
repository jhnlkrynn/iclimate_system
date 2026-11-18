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
    test(`${role} dashboard clock advances without a page reload`, async ({ page }) => {
        await signIn(page, role);
        await expect(page).toHaveURL(new RegExp(`${dashboard.replace('/', '\\/')}$`));
        const clock = page.locator('[data-live-clock]').first();
        await expect(clock).toHaveText(/^\d{1,2}:\d{2}:\d{2} [AP]M$/);

        const initialUrl = page.url();
        const initialPath = new URL(initialUrl).pathname;
        const first = await clock.textContent();
        await expect.poll(() => clock.textContent(), { timeout: 3500, intervals: [200] }).not.toBe(first);
        expect(new URL(page.url()).pathname).toBe(initialPath);
        await expect(page.locator('body')).toHaveAttribute('data-server-now', /T/);
    });
}
