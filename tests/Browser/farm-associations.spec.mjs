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
        yes: box.locator('[data-association-member]'),
        answer: (value) => box.locator(`input[name="association_status"][value="${value}"]`),
        checked: box.locator('input[name="association_status"]:checked'),
        pick: page.locator('#farmer_organization_id'),
        listed: page.locator('#farmer_organization_id option:not([value=""])'),
        otherName: page.locator('#association_other_name'),
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

test('F1 Add Farm narrows the associations, asks again on a new barangay and sends "not listed" for review', async ({ page }, testInfo) => {
    await logIn(page);
    await page.goto('/farmer/farms/create');
    const f = form(page);
    const name = unique(testInfo, 'Pending');
    await page.locator('#farm_name').fill(name);
    await f.size.fill('1.5');

    // No barangay yet: a hint, and no "Yes".
    await expect(f.hint).toBeVisible();
    await expect(f.yes).toBeHidden();

    // The barangay narrows the list to its associations; the farm type narrows it further.
    await f.barangay.selectOption('Kapito');
    await expect(f.hint).toBeHidden();
    await expect(f.yes).toBeVisible();
    await expect(f.listed).toHaveText([KAPITO_RAINFED, KAPITO_IRRIGATED]);
    await f.type.selectOption('Irrigated');
    await expect(f.listed).toHaveText([KAPITO_IRRIGATED]);
    await f.type.selectOption('Rainfed');
    await expect(f.listed).toHaveText([KAPITO_RAINFED]);

    // No association of that type in the barangay: every association of the barangay.
    await f.barangay.selectOption('Prenza');
    await expect(f.listed).toHaveText([PRENZA]);

    // "Yes" shows the list; a new barangay clears the answer.
    await f.answer('member').check();
    await expect(f.pick).toBeVisible();
    await f.pick.selectOption({ label: PRENZA });
    await f.barangay.selectOption('Kapito');
    await expect(f.checked).toHaveCount(0);
    await expect(f.pick).toBeHidden();
    await expect(f.pick).toHaveValue('');

    // A barangay with no associations: only "isn't listed" and "No".
    await f.barangay.selectOption('Luyahan');
    await expect(f.yes).toBeHidden();
    await expect(f.answer('pending_other')).toBeVisible();
    await expect(f.answer('not_member')).toBeVisible();

    // The answer is required.
    await f.save.click();
    await expect(f.errors).toContainText('Answer if this farm is part of a farmers\' association.');
    await expect(f.barangay).toHaveValue('Luyahan');
    await expect(f.yes).toBeHidden();

    // "My association isn't listed" asks for the name and goes to the MAO.
    await expect(f.otherName).toBeHidden();
    await f.answer('pending_other').check();
    await expect(f.otherName).toBeVisible();
    await f.otherName.fill('Samahang Magsasaka ng Luyahan');
    await f.save.click();
    await page.waitForURL('**/farmer/my-farm', { waitUntil: 'commit' });
    await expect(card(page, name).locator('[data-farm-association]')).toHaveText('Waiting for MAO review');
});

test('F2 Edit Farm keeps the answer, narrows by farm type and asks again when the barangay changes', async ({ page }, testInfo) => {
    await logIn(page);
    await page.goto('/farmer/farms/create');
    let f = form(page);
    const name = unique(testInfo, 'Member');
    await page.locator('#farm_name').fill(name);
    await f.size.fill('1.5');
    await f.barangay.selectOption('Kapito');
    await f.type.selectOption('Irrigated');
    await f.answer('member').check();
    await f.pick.selectOption({ label: KAPITO_IRRIGATED });
    await f.save.click();
    await page.waitForURL('**/farmer/my-farm', { waitUntil: 'commit' });
    await expect(card(page, name).locator('[data-farm-association]')).toHaveText(`Samahan: ${KAPITO_IRRIGATED}`);

    // The edit page opens on the saved answer.
    await card(page, name).getByRole('link', { name: 'Edit' }).click();
    await page.waitForURL('**/farmer/farms/*/edit', { waitUntil: 'commit' });
    f = form(page);
    await expect(f.answer('member')).toBeChecked();
    await expect(f.pick).toBeVisible();
    await expect(f.pick).toHaveValue(/\d+/);
    await expect(f.listed).toHaveText([KAPITO_IRRIGATED]);
    await expect(f.again).toBeHidden();

    // Another farm type: that type's associations (the old choice is no longer one of them).
    await f.type.selectOption('Rainfed');
    await expect(f.listed).toHaveText([KAPITO_RAINFED]);
    await expect(f.pick).toHaveValue('');
    await f.type.selectOption('Irrigated');

    // Another barangay: the answer is cleared and asked again; saving without one is refused.
    await f.barangay.selectOption('Prenza');
    await expect(f.again).toBeVisible();
    await expect(f.checked).toHaveCount(0);
    await expect(f.listed).toHaveText([PRENZA]);
    await f.save.click();
    await expect(f.errors).toContainText('Answer if this farm is part of a farmers\' association.');
    await expect(f.barangay).toHaveValue('Prenza');

    // "Isn't listed" from the edit page goes to the MAO too.
    await f.answer('pending_other').check();
    await f.otherName.fill('Bagbag Irrigators');
    await f.save.click();
    await page.waitForURL('**/farmer/my-farm', { waitUntil: 'commit' });
    await expect(card(page, name).locator('[data-farm-association]')).toHaveText('Waiting for MAO review');
    await expect(card(page, name)).toContainText('Prenza');
});
