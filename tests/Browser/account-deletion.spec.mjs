// IT deletes a user account through the Delete account dialog (reason, preview, recipient line, typed name), then the
// deleted person signs in and sees why. Seeded by tests/Browser/seed-landing.php: one farmer per screen size.
import { test, expect } from '@playwright/test';

const PEOPLE = {
    'desktop-1280': { name: 'Delia Desktop', email: 'delete.desktop-1280@lian-farmers.ph' },
    'mobile-375': { name: 'Mona Mobile', email: 'delete.mobile-375@lian-farmers.ph' },
};

async function logIn(page, email, landing) {
    await page.goto('/');
    await page.locator('#login-email').fill(email);
    await page.locator('#login-password').fill('password');
    await page.locator('[data-login-form] button[type="submit"]').click();
    if (landing) await page.waitForURL(landing, { waitUntil: 'commit' });
}

test('D1 IT deletes an account with a reason, then the person sees why when signing in', async ({ page }, testInfo) => {
    const person = PEOPLE[testInfo.project.name];
    await logIn(page, 'it@browser.test', '**/admin/dashboard');
    await page.goto(`/users?search=${encodeURIComponent(person.name)}`);

    await page.locator('[data-user-delete]:visible').first().click();
    const dialog = page.locator('[data-user-delete-dialog]');
    await expect(dialog).toBeVisible();
    await expect(dialog.locator('[data-ud="name"]')).toHaveText(person.name);
    await expect(dialog.locator('[data-ud="email"]')).toHaveText(person.email);
    await expect(dialog.getByText('Only IT can see this')).toBeVisible();
    const submit = dialog.locator('[data-ud-submit]');
    await expect(submit).toBeDisabled();

    // "Other" needs at least 10 characters.
    await dialog.locator('#udReason').selectOption('other');
    await expect(dialog.locator('#udOther')).toBeVisible();
    await dialog.locator('#udConfirm').fill(person.name);
    await dialog.locator('#udOther').fill('too short');
    await expect(submit).toBeDisabled();

    // A listed reason: the preview and who gets the email.
    await dialog.locator('#udReason').selectOption('moved_out');
    await expect(dialog.locator('#udOther')).toBeHidden();
    await expect(dialog.locator('[data-ud-preview-title]')).toHaveText('Your iClimate account was deleted');
    await expect(dialog.locator('[data-ud-preview-text]')).toHaveText('Your account was closed because you have moved out of Lian.');
    await expect(dialog.locator('[data-ud-mail]')).toHaveText(`Email will be sent to ${person.email}`);
    await expect(submit).toBeEnabled();
    await dialog.locator('#udConfirm').fill(person.name.toLowerCase());
    await expect(submit).toBeDisabled();
    await dialog.locator('#udConfirm').fill(person.name);
    await dialog.locator('#udNote').fill('Confirmed at the barangay hall');

    // The dialog fits the screen.
    const box = await dialog.boundingBox();
    expect(box.x).toBeGreaterThanOrEqual(0);
    expect(box.x + box.width).toBeLessThanOrEqual(page.viewportSize().width);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1)).toBe(true);

    await submit.click();
    await expect(page.locator('.flash-toast-message', { hasText: `${person.name}'s account was deleted.` })).toBeVisible();

    // The person signs in with the right password: the panel, not the app.
    await page.context().clearCookies();
    await logIn(page, person.email);
    await page.waitForURL('**/account-deleted', { waitUntil: 'commit' });
    const panel = page.locator('[data-account-deleted]');
    await expect(panel.getByRole('heading', { name: 'Your iClimate account was deleted' })).toBeVisible();
    await expect(panel.locator('[data-account-deleted-reason]')).toHaveText('Your account was closed because you have moved out of Lian.');
    await expect(panel).toContainText('If you think this is a mistake, please visit or contact the Municipal Agriculture Office of Lian.');
    await expect(panel).not.toContainText('Confirmed at the barangay hall');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1)).toBe(true);
    await page.screenshot({ path: testInfo.outputPath(`panel-${testInfo.project.name}.png`), fullPage: true });
    await panel.locator('[data-account-deleted-home]').click();
    await page.waitForURL((url) => url.pathname === '/', { waitUntil: 'commit' });
});
