import { test, expect } from '@playwright/test';

async function signInFarmer(page) {
    await page.goto('/login');
    await page.locator('#login-email').fill('farmer@browser.test');
    await page.locator('#login-password').fill('password');
    await page.locator('[data-login-form] button[type="submit"]').click();
    await expect(page).toHaveURL(/\/farmer\/dashboard$/);
    await page.waitForLoadState('domcontentloaded');
    await page.evaluate(() => {
        const m = document.getElementById('icWeatherAlertModal');
        if (m) {
            try { localStorage.setItem('iclimate.weather_alert_dismissed', m.getAttribute('data-alert-key') || ''); } catch (e) {}
            m.classList.remove('is-open');
            m.hidden = true;
        }
        document.documentElement.classList.remove('ic-alert-modal-lock');
    });
}

test('farmer KPI detail opens as centered modal on desktop and bottom sheet on mobile, closes on Esc, and returns focus', async ({ page }) => {
    await signInFarmer(page);

    const card = page.locator('[data-panel]').first();
    await expect(card).toBeVisible();
    await card.focus();
    await card.click();

    const drawer = page.locator('#fhDrawer');
    const panel = page.locator('[data-fh-drawer-panel]');
    await expect(drawer).toBeVisible();
    await expect(panel).toBeVisible();

    const box = await panel.boundingBox();
    const vp = page.viewportSize();
    expect(box).not.toBeNull();

    if (vp.width >= 576) {
        expect(box.width).toBeLessThanOrEqual(560);
        // Centered horizontally within viewport
        const leftGap = box.x;
        const rightGap = vp.width - (box.x + box.width);
        expect(Math.abs(leftGap - rightGap)).toBeLessThanOrEqual(4);
    } else {
        // Full-width bottom sheet with drag handle on phones
        expect(Math.round(box.width)).toBe(vp.width);
        await expect(panel.locator('.fh-drawer-handle')).toBeVisible();
    }

    await page.keyboard.press('Escape');
    await expect(drawer).toBeHidden();
    await expect(card).toBeFocused();
});
