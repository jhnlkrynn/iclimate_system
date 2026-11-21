import { test, expect } from '@playwright/test';

// Alpine.js CSP build (2026-11-20): the profile page's components work under a policy without 'unsafe-eval',
// and nothing is blocked. Runs on the seeded throwaway server, never on real accounts.
async function login(page, email) {
    await page.goto('/');
    await page.locator('#login-email').fill(email);
    await page.locator('#login-password').fill('password');
    await page.locator('[data-login-form] button[type="submit"]').click();
    await page.waitForURL('**/dashboard');
}

test('profile modal and saved note work with the Alpine CSP build and no blocked scripts', async ({ page }) => {
    const problems = [];
    page.on('pageerror', error => problems.push(error.message));
    await page.addInitScript(() => document.addEventListener('securitypolicyviolation', e => console.error(`CSP ${e.effectiveDirective} ${e.blockedURI || 'inline'}`)));
    page.on('console', message => { if (message.type() === 'error' && /^CSP |Alpine/.test(message.text())) problems.push(message.text()); });

    await login(page, 'farmer@browser.test');
    const response = await page.goto('/profile');
    expect(response.headers()['content-security-policy']).not.toContain("'unsafe-eval'");

    // Delete-account confirmation: opens on the button, keeps focus inside, closes on Escape and on Cancel.
    const modal = page.locator('[x-data="modal"][data-modal-name="confirm-user-deletion"]');
    const trigger = page.locator('[x-data="modalTrigger"]:visible').first();
    await expect(modal).toBeHidden();
    await trigger.click();
    await expect(modal).toBeVisible();
    await expect.poll(() => page.evaluate(() => !!document.activeElement?.closest('[x-data="modal"]'))).toBe(true);
    await page.keyboard.press('Escape');
    await expect(modal).toBeHidden();
    await trigger.click();
    await expect(modal).toBeVisible();
    await modal.getByRole('button', { name: /cancel/i }).click();
    await expect(modal).toBeHidden();

    // Saving the profile shows "Saved." and hides it after two seconds.
    const form = page.locator('form:has(#name):visible').first();
    await Promise.all([page.waitForURL('**/profile*'), form.getByRole('button', { name: 'Save' }).click()]);
    const saved = page.locator('[x-data="flash"]:visible', { hasText: 'Saved.' }).first();
    await expect(saved).toBeVisible();
    await expect(saved).toBeHidden({ timeout: 5000 });

    expect(problems).toEqual([]);
});
