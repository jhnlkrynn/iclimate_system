// Add Farm / Edit Farm: the farmers' association question (resources/views/farms/form.blade.php). Seeded by
// tests/Browser/seed-landing.php: farmer farms@browser.test; Kapito has Saluysoy Farmers Association (Irrigated) and
// Kapito Rainfed Growers (Rainfed), Prenza only an Irrigated association, Luyahan none.
// Each run adds farms to the same account, so every farm gets a name of its own.
import { test, expect } from '@playwright/test';

const KAPITO_IRRIGATED = 'Saluysoy Farmers Association';
const KAPITO_RAINFED = 'Kapito Rainfed Growers';
const PRENZA = 'Prenza Bagbag Bungahan Farmers Association';

async function logIn(page) {
    await page.goto('/');
    await page.locator('#login-email').fill('farms@browser.test');
    await page.locator('#login-password').fill('password');
    await page.locator('[data-login-form] button[type="submit"]').click();
    await page.waitForURL('**/farmer/dashboard', { waitUntil: 'commit' });
}

function form(page) {
    const box = page.locator('[data-association]');
    return {
        box,
        barangay: page.locator('#barangay'),
        type: page.locator('#farm_type'),
        pick: page.locator('#farmer_organization_id'),
        listed: page.locator('#farmer_organization_id option:not([value=""])'),
        status: box.locator('input[name="association_status"]'),
        size: page.locator('#farm_size_value'),
        unit: page.locator('#farm_size_unit'),
        hint: box.locator('[data-association-hint]'),
        again: box.locator('[data-association-again]'),
        save: page.locator('form.ffm-form button[type="submit"]'),
        errors: page.locator('.frec-note.is-danger'),
    };
}

const unique = (testInfo, label) => `${label} ${testInfo.project.name} ${Date.now() % 1_000_000}`;

function card(page, name) {
    return page.locator('[data-farm-card]', { hasText: name });
}

test('F1 Add Farm narrows the associations, asks again on a new barangay and supports "not a member"', async ({ page }, testInfo) => {
    await logIn(page);
    await page.goto('/farmer/farms/create');
    const f = form(page);
    const name = unique(testInfo, 'Pending');
    await page.locator('#farm_name').fill(name);
    await f.size.fill('1.5');

    // No barangay yet: a hint, and the association dropdown is disabled.
    await expect(f.hint).toBeVisible();
    await expect(f.pick).toBeDisabled();

    // The barangay narrows the list to its registered associations; farm type does not hide a valid barangay association.
    await f.barangay.selectOption('Kapito');
    await expect(f.hint).toBeHidden();
    await expect(f.pick).toBeEnabled();
    await expect(f.listed).toHaveText([KAPITO_RAINFED, KAPITO_IRRIGATED]);
    await f.type.selectOption('Irrigated');
    await expect(f.listed).toHaveText([KAPITO_RAINFED, KAPITO_IRRIGATED]);
    await f.type.selectOption('Rainfed');
    await expect(f.listed).toHaveText([KAPITO_RAINFED, KAPITO_IRRIGATED]);

    // No association of that type in the barangay: every association of the barangay.
    await f.barangay.selectOption('Prenza');
    await expect(f.listed).toHaveText([PRENZA]);

    // A new barangay clears the selected association and membership status.
    await expect(f.pick).toBeEnabled();
    await f.pick.selectOption({ label: PRENZA });
    await f.barangay.selectOption('Kapito');
    await expect(f.status).toHaveValue('');
    await expect(f.pick).toHaveValue('');

    // A barangay with no associations: only "Not a member" remains.
    await f.barangay.selectOption('Luyahan');
    await expect(f.pick).toBeEnabled();
    await expect(f.pick.locator('option', { hasText: 'Not a member' })).toHaveCount(1);

    // The answer is required.
    await f.save.click();
    await expect(f.errors).toContainText('Answer if this farm is part of a farmers\' association.');
    await expect(f.barangay).toHaveValue('Luyahan');
    await expect(f.pick).toBeEnabled();

    // The only non-association choice is "Not a member".
    await f.pick.selectOption({ label: 'Not a member' });
    await expect(f.pick.locator('option', { hasText: 'My association isn\'t listed' })).toHaveCount(0);
    await f.save.click();
    await page.waitForURL('**/farmer/my-farm', { waitUntil: 'commit' });
    await expect(card(page, name).locator('[data-farm-association]')).toHaveText('Not a member');
});

test('F2 Edit Farm keeps the answer, filters by barangay and asks again when the barangay changes', async ({ page }, testInfo) => {
    await logIn(page);
    await page.goto('/farmer/farms/create');
    let f = form(page);
    const name = unique(testInfo, 'Member');
    await page.locator('#farm_name').fill(name);
    await f.size.fill('1.5');
    await f.barangay.selectOption('Kapito');
    await f.type.selectOption('Irrigated');
    await f.pick.selectOption({ label: KAPITO_IRRIGATED });
    await f.save.click();
    await page.waitForURL('**/farmer/my-farm', { waitUntil: 'commit' });
    await expect(card(page, name).locator('[data-farm-association]')).toHaveText(`Samahan: ${KAPITO_IRRIGATED}`);

    // The edit page opens on the saved answer.
    await card(page, name).getByRole('link', { name: 'Edit' }).click();
    await page.waitForURL('**/farmer/farms/*/edit', { waitUntil: 'commit' });
    f = form(page);
    await expect(f.status).toHaveValue('member');
    await expect(f.pick).toBeVisible();
    await expect(f.pick).toHaveValue(/\d+/);
    await expect(f.listed).toHaveText([KAPITO_RAINFED, KAPITO_IRRIGATED]);
    await expect(f.again).toBeHidden();

    // Farm type changes do not invalidate a registered association in the same barangay.
    await f.type.selectOption('Rainfed');
    await expect(f.listed).toHaveText([KAPITO_RAINFED, KAPITO_IRRIGATED]);
    await expect(f.pick).toHaveValue(/\d+/);
    await f.type.selectOption('Irrigated');

    // Another barangay: the answer is cleared and asked again; saving without one is refused.
    await f.barangay.selectOption('Prenza');
    await expect(f.again).toBeVisible();
    await expect(f.status).toHaveValue('');
    await expect(f.listed).toHaveText([PRENZA]);
    await f.save.click();
    await expect(f.errors).toContainText('Answer if this farm is part of a farmers\' association.');
    await expect(f.barangay).toHaveValue('Prenza');

    // Farmers can choose a listed association or "Not a member" only.
    await f.pick.selectOption({ label: 'Not a member' });
    await f.save.click();
    await page.waitForURL('**/farmer/my-farm', { waitUntil: 'commit' });
    await expect(card(page, name).locator('[data-farm-association]')).toHaveText('Not a member');
    await expect(card(page, name)).toContainText('Prenza');
});
