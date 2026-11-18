import { test, expect } from '@playwright/test';

// Regression (audit 2026-11-18): the Heat Map loads its layers from /api/heat-map with the browser's session cookie.
// Declared in routes/api.php no session was started, so every signed-in browser got 401 and "Map data could not be
// loaded". Feature tests did not notice because actingAs() bypasses the session; only a real browser does.
for (const [label, email, home] of [['MAO staff', 'reviewer@browser.test', '**/mao/dashboard'], ['Farmer', 'farmer@browser.test', '**/farmer/dashboard**']]) {
    test(`${label} sees Heat Map layers load with the session cookie`, async ({ page }) => {
        await page.goto('/');
        await page.locator('#login-email').fill(email);
        await page.locator('#login-password').fill('password');
        await page.locator('[data-login-form] button[type="submit"]').click();
        await page.waitForURL(home);

        const layer = page.waitForResponse((response) => response.url().includes('/api/heat-map?'));
        await page.goto('/heat-map');
        const response = await layer;
        expect(response.status()).toBe(200);
        expect(Array.isArray((await response.json()).barangays)).toBe(true);
        await expect(page.locator('[data-heat-map-api-status]')).not.toContainText('could not be loaded');
    });
}
