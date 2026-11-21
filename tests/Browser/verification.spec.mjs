import { test, expect } from '@playwright/test';

// Critical authenticated page walk complements form/CRUD tests and covers tablet layouts.
const cases = [
    ['farmer', 'farmer@browser.test', ['/farmer/dashboard', '/weather-predictions', '/heat-map', '/analysis/yield', '/calendar', '/farmer/my-farm', '/farmer/farms/create', '/farmer/planting-schedules/create', '/farmer/my-reports', '/planting-advisories', '/profile', '/notifications']],
    ['mao', 'reviewer@browser.test', ['/mao/dashboard', '/mao/farmer-approvals', '/mao/farm-associations', '/mao/association-reports', '/mao/planner', '/mao/alerts', '/heat-map', '/weather-predictions', '/reports', '/profile', '/notifications']],
];
for (const [role, email, routes] of cases) {
    test(`${role} protected pages render without runtime errors or horizontal overflow`, async ({ page }) => {
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        // Strict CSP (2026-11-20): any blocked script, handler or resource is a failure.
        await page.addInitScript(() => document.addEventListener('securitypolicyviolation', e => console.error(`CSP ${e.effectiveDirective} ${e.blockedURI || 'inline'} ${e.sourceFile}:${e.lineNumber}`)));
        page.on('console', message => { if (message.type() === 'error' && message.text().startsWith('CSP ')) errors.push(message.text()); });
        page.on('response', response => { if (response.url().startsWith(test.info().project.use.baseURL || '') && response.status() >= 500) errors.push(`${response.status()} ${response.url()}`); });
        await page.goto('/');
        await page.locator('#login-email').fill(email);
        await page.locator('#login-password').fill('password');
        await page.locator('[data-login-form] button[type="submit"]').click();
        await page.waitForURL(`**/${role}/dashboard`);
        for (const route of routes) {
            const response = await page.goto(route);
            expect(response.status(), route).toBe(200);
            await expect(page.locator('main')).toBeVisible();
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), route).toBe(true);
        }
        expect(errors).toEqual([]);
    });
}

test('public HTTP boundary enforces CSRF and hides sensitive files', async ({ request }) => {
    const response = await request.post('/login', { data: { email: 'nobody@browser.test', password: 'invalid' } });
    expect(response.status()).toBe(419);
    for (const path of ['/.env', '/.git/config', '/storage/logs/laravel.log']) {
        const result = await request.get(path);
        expect([403, 404], path).toContain(result.status());
    }
    const csp = (await request.get('/login')).headers()['content-security-policy'] || '';
    expect(csp).toMatch(/script-src 'nonce-[A-Za-z0-9+\/=_-]+' 'strict-dynamic'/);
    expect(csp).toContain("script-src-attr 'none'");
    expect(csp).toContain("object-src 'none'");
    const dashboard = await request.get('/mao/dashboard', { maxRedirects: 0 });
    expect(dashboard.status()).toBe(302);
});
