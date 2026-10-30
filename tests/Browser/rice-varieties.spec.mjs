// Rice Varieties smoke test: the catalog opens the Variety Details pop-up, and the DS / WS switch changes the figures.
// Seeded by tests/Browser/seed-landing.php (five rows of the NSIC list, including NSIC 2026 Rc 824H:
// transplanted maturity 109 DAS in the dry season, 108 in the wet season).
import { test, expect } from '@playwright/test';

async function logInAsFarmer(page) {
    await page.goto('/');
    await page.locator('#login-email').fill('farmer@browser.test');
    await page.locator('#login-password').fill('password');
    await page.locator('[data-login-form] button[type="submit"]').click();
    await page.waitForURL('**/farmer/dashboard', { waitUntil: 'commit' });
}

test('V1 the catalog opens the details pop-up and DS / WS changes the maturity', async ({ page }) => {
    await logInAsFarmer(page);
    await page.goto('/rice-varieties');
    await expect(page.locator('[data-rv-count]')).toContainText('5');

    await expect(page.locator('[data-rv-source]')).toContainText('Source: NSIC Registered Rice Varieties');

    const card = page.locator('.rv-card', { hasText: 'Mestiso 176' });
    await card.click();
    const dialog = page.locator('[data-rv-dialog]');
    await expect(dialog).toBeVisible();
    await expect(dialog.locator('#rvModalTitle')).toHaveText('Mestiso 176 (RTP750FP)');
    await expect(dialog.locator('[data-rv-f="nsic"]')).toHaveText('NSIC 2026 Rc 824H');

    const maturity = dialog.locator('[data-rv-stat="maturity"] [data-rv-stat-value]');
    await expect(dialog.locator('[data-rv-season="ws"]')).toHaveAttribute('aria-checked', 'true');
    await expect(maturity).toHaveText('108');
    await dialog.locator('[data-rv-season="ds"]').click();
    await expect(maturity).toHaveText('109');
    await expect(dialog.locator('[data-rv-season="ds"]')).toHaveAttribute('aria-checked', 'true');

    // No direct-seeded trials: a dash and "no trial data", never 0.
    await dialog.locator('[data-rv-method="dwsr"]').click();
    await expect(maturity).toHaveText('—');
    await expect(dialog.locator('[data-rv-stat="maturity"] [data-rv-stat-note]')).toBeVisible();

    // Only Agronomy and Grain Quality: no Pest & Disease tab.
    await expect(dialog.locator('[data-rv-tab]')).toHaveCount(2);
    await expect(dialog.locator('[data-rv-tab="pests"]')).toHaveCount(0);
    await expect(dialog.getByText('Pest & Disease')).toHaveCount(0);

    // Farm type as chips in the Recommendation Domain card.
    await expect(dialog.locator('[data-rv-farm-types] [data-rv-farm-type]')).toHaveText(['Irrigated']);

    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    await expect(card).toBeFocused();

    // The planting form lists the farm type's varieties first, then "Other varieties"; it follows "Type of farm".
    await page.goto('/farmer/planting-schedules/create');
    await page.locator('#farm_type').selectOption('Rainfed');
    await page.locator('#rice_variety_id-search').click();
    const list = page.locator('#rice_variety_id-list');
    await expect(list.locator('[data-rv-combo-head]')).toHaveText(['For rainfed farms', 'Other varieties']);
    await expect(list.locator('[role="option"]').nth(1)).toContainText('GSR 8');
    await page.locator('#farm_type').selectOption('Irrigated');
    await page.locator('#rice_variety_id-search').click();
    await expect(list.locator('[data-rv-combo-head]').first()).toHaveText('For irrigated farms');
    await expect(list.locator('[role="option"]').nth(1)).toContainText('Mestiso 176');

});

test('V2 a farmer can open Choose variety and see the variety picker on the planting form', async ({ page }) => {
    await logInAsFarmer(page);
    await page.goto('/rice-varieties');

    await page.locator('[data-rv-prompt] a').click();
    await expect(page).toHaveURL(/\/farmer\/planting-schedules\/\d+\/edit$/);
    await expect(page.locator('form.schedule-form')).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBeTruthy();

    await expect(page.locator('#rice_variety_id option', { hasText: 'GSR 8' })).toHaveCount(1);
});

async function logInAsIt(page) {
    await page.goto('/');
    await page.locator('#login-email').fill('it@browser.test');
    await page.locator('#login-password').fill('password');
    await page.locator('[data-login-form] button[type="submit"]').click();
    await page.waitForURL('**/admin/dashboard', { waitUntil: 'commit' });
}

test('V3 IT list: a row opens the pop-up, ⋯ shows Edit, and the Filters popover applies Irrigated', async ({ page }) => {
    await logInAsIt(page);
    await page.goto('/rice-varieties');
    const row = page.locator('tr.rv-row', { hasText: 'Mestiso 176' });

    // A click anywhere on the row opens the details pop-up.
    await row.locator('.rv-cell-type').click();
    const dialog = page.locator('[data-rv-dialog]');
    await expect(dialog.locator('#rvModalTitle')).toHaveText('Mestiso 176 (RTP750FP)');
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    await expect(row).toBeFocused();                                   // focus goes back to the row

    // "⋯": Edit and Deactivate; arrow keys move, Esc closes back on the button; it never opens the pop-up.
    const button = row.locator('[data-rv-menu-btn]');
    await button.focus();
    await page.keyboard.press('Enter');
    const menu = row.locator('[role="menu"]');
    await expect(menu).toBeVisible();
    await expect(menu.locator('[role="menuitem"]')).toHaveText(['Edit', 'Deactivate']);
    await expect(menu.locator('[role="menuitem"]').first()).toBeFocused();
    await page.keyboard.press('ArrowDown');
    await expect(menu.locator('[role="menuitem"]').nth(1)).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(menu).toBeHidden();
    await expect(button).toBeFocused();
    await expect(dialog).toBeHidden();
    await button.click();
    await expect(menu.locator('[data-rv-menu-edit]')).toHaveAttribute('href', /\/rice-varieties\/\d+\/edit$/);
    await expect(dialog).toBeHidden();
    await page.keyboard.press('Escape');

    // Filters: closed until opened; Irrigated applies and shows as a removable chip.
    const panel = page.locator('.rv-filters-panel');
    await expect(panel).toBeHidden();
    await page.locator('.rv-filters-btn').click();
    await expect(panel).toBeVisible();
    await panel.locator('[data-rv-filter="farm_type:Irrigated"]').click();
    await expect(page).toHaveURL(/farm_type=Irrigated/);
    await expect(page.locator('[data-rv-active-chip="farm_type"]')).toContainText('Irrigated');
    await expect(page.locator('[data-rv-filter-count]')).toHaveText('1');
    await expect(page.locator('tr.rv-row')).toHaveCount(2);           // the two irrigated varieties of the five
    if (process.env.RV_SHOT) await page.screenshot({ path: `${process.env.RV_SHOT}-${test.info().project.name}.png`, fullPage: true });
});
