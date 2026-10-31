import { test, expect } from '@playwright/test';

const accounts = [
    ['farmer', 'farmer@browser.test', '/farmer/dashboard', [/Today|Ngayon/, 'Forecast', /Advisories|Advice/, /My Farm|Aking Farm/, 'More']],
    ['mao', 'mao@browser.test', '/mao/dashboard', ['Home', 'Heat Map', 'Advisories', 'Records', 'More']],
    ['it', 'it@browser.test', '/admin/dashboard', ['Home', 'Users', 'Reports', 'Messages', 'More']],
];

async function logIn(page, email, dashboard) {
    await page.goto('/#login');
    await page.locator('#login-email').fill(email);
    await page.locator('#login-password').fill('password');
    await page.locator('[data-login-form] button[type="submit"]').click();
    await page.waitForURL(`**${dashboard}`, { waitUntil: 'commit' });
}

for (const [role, email, dashboard, labels] of accounts) {
    test(`${role} mobile navigation mirrors the role drawer`, async ({ page }, testInfo) => {
        test.skip(testInfo.project.name !== 'mobile-375', 'Mobile navigation is intentionally hidden on desktop.');
        await logIn(page, email, dashboard);

        const nav = page.locator('[data-mobile-nav]');
        await expect(nav).toBeVisible();
        await expect(nav.locator('.mobile-tab')).toHaveCount(5);
        for (const label of labels) await expect(nav).toContainText(label);
        await expect(page.locator('#mobileSidebar')).toBeAttached();
        await expect(page.locator('body')).not.toHaveCSS('overflow', 'hidden');

        await nav.locator('[data-mobile-more-open]').click();
        const sheet = page.locator('[data-mobile-more]');
        await expect(sheet).toBeVisible();
        await expect(page.locator('body')).toHaveClass(/mobile-nav-open/);
        await page.keyboard.press('Escape');
        await expect(sheet).toBeHidden();
        await expect(page.locator('body')).not.toHaveClass(/mobile-nav-open/);

        if (role === 'farmer') {
            await expect(sheet).not.toContainText('MAO Staff');
            await expect(sheet).toContainText('Weather & risk');
        }
        await expect(nav.locator('[data-mobile-season-line]')).toBeVisible();

        await page.screenshot({ path: testInfo.outputPath(`${role}-375.png`), fullPage: false });
        await page.setViewportSize({ width: 414, height: 740 });
        await page.screenshot({ path: testInfo.outputPath(`${role}-414.png`), fullPage: false });
    });
}
