import { test, expect } from '@playwright/test';
test('prediction request persists and renders a safe withheld or missing-data result', async({page})=>{
    test.skip(process.env.API_ML_ACCEPTANCE!=='1','Requires the explicitly configured isolated ML acceptance service and diagnostic fixtures.');
    await page.goto('/');await page.locator('#login-email').fill('farmer@browser.test');await page.locator('#login-password').fill('password');
    await page.locator('[data-login-form] button[type="submit"]').click();await page.waitForURL('**/farmer/dashboard');
    await page.goto('/analysis/yield');
    await page.locator('select[name="season"]').selectOption('Wet');
    await page.locator('form[action$="/analysis/yield"] button[type="submit"]').click();
    const statusUrl=await page.locator('[data-prediction-status-url]').first().getAttribute('data-prediction-status-url');
    expect(statusUrl).toBeTruthy();
    await expect.poll(async()=>await (await page.request.get(statusUrl)).json(),{timeout:20000}).toMatchObject({status:'failed'});
    const status=await (await page.request.get(statusUrl)).json();
    expect(status).not.toHaveProperty('predicted_yield');
    const safeReason=String(status.error||'').toLowerCase();
    expect(safeReason.includes('accuracy not verified')||safeReason.includes('not enough data')).toBeTruthy();
    await expect(page.locator('main')).toContainText(safeReason.includes('accuracy not verified')?'accuracy not verified':'Not enough data',{timeout:20000});
    await expect(page.locator('main')).not.toContainText(/\b\d+(?:\.\d+)?\s*t\/ha\b/);
    await page.goto('/farmer/dashboard');await expect(page.locator('main')).toBeVisible();
});
