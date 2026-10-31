import { test, expect } from '@playwright/test';

async function logIn(page, email, dashboard) {
    await page.goto('/#login');
    await page.locator('#login-email').fill(email);
    await page.locator('#login-password').fill('password');
    await page.locator('[data-login-form] button[type="submit"]').click();
    await page.waitForURL(`**${dashboard}`, { waitUntil: 'commit' });
}

for (const [role, email, dashboard, routes] of [
    ['it', 'it@browser.test', '/admin/dashboard', ['/users', '/system-logs', '/reports']],
    ['mao', 'mao@browser.test', '/mao/dashboard', ['/climate-records', '/rice-productions', '/farmer-profiles']],
]) {
    test(`${role} CRUD lists render compact mobile cards`, async ({ page }, testInfo) => {
        test.skip(testInfo.project.name !== 'mobile-375', 'This is a mobile-only layout check.');
        await logIn(page, email, dashboard);

        for (const route of routes) {
            await page.goto(route);
            await expect(page.locator('main')).toBeVisible();
            if (await page.locator('.ic-table-cards').count()) {
                await expect(page.locator('.ic-table-cards')).toBeVisible();
            }
            if (route === '/users') {
                await expect(page.locator('[data-crud-row-card]').first()).toBeVisible();
            }
            const slug = route.slice(1).replaceAll('/', '-');
            await page.screenshot({ path: testInfo.outputPath(`${role}-${slug}-375.png`), fullPage: true });
            await page.setViewportSize({ width: 414, height: 740 });
            await page.screenshot({ path: testInfo.outputPath(`${role}-${slug}-414.png`), fullPage: true });
            await page.setViewportSize({ width: 375, height: 740 });
        }
    });
}
