import { test, expect } from '@playwright/test';

async function logIn(page, email, dashboard) {
    await page.goto('/#login');
    await page.locator('#login-email').fill(email);
    await page.locator('#login-password').fill('password');
    await page.locator('[data-login-form] button[type="submit"]').click();
    await page.waitForURL(`**${dashboard}`, { waitUntil: 'commit' });
}

test('mao nav omits removed analysis pages and all 4 URLs redirect to mao dashboard', async ({ page }, testInfo) => {
    await logIn(page, 'mao@browser.test', '/mao/dashboard');

    if (testInfo.project.name === 'mobile-375') {
        const nav = page.locator('[data-mobile-nav]');
        await expect(nav).toBeVisible();
        await nav.locator('[data-mobile-more-open]').click();
        const sheet = page.locator('[data-mobile-more]');
        await expect(sheet).toBeVisible();
        for (const href of ['/analysis/impact', '/analysis/forecast', '/analysis/harvest-training', '/analysis/model-evaluation']) {
            await expect(sheet.locator(`a[href*="${href}"]`)).toHaveCount(0);
        }
    } else {
        await page.locator('.ic-topbar-menu-btn').click();
        const sidebar = page.locator('#mobileSidebar');
        await expect(sidebar).toBeVisible();
        for (const href of ['/analysis/impact', '/analysis/forecast', '/analysis/harvest-training', '/analysis/model-evaluation']) {
            await expect(sidebar.locator(`a[href*="${href}"]`)).toHaveCount(0);
        }
    }

    for (const url of ['/analysis/impact', '/analysis/forecast', '/analysis/harvest-training', '/analysis/model-evaluation']) {
        await page.goto(url);
        await page.waitForURL('**/mao/dashboard', { waitUntil: 'commit' });
    }
});
