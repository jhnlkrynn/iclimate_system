import { test, expect } from '@playwright/test';

async function register(request, project, action) {
    const name = `Account ${action} ${project}`;
    const email = `account-${action}-${project}@browser.test`;
    const response = await request.post('/api/mobile/register', {
        data: {
            name, email, password: 'FarmerPassword!23', password_confirmation: 'FarmerPassword!23',
            contact_number: '09170000001', barangay: 'Prenza', farm_type: 'Irrigated',
        },
    });
    expect(response.status()).toBe(201);
    expect((await response.json()).user.status).toBe('Pending');
    return { name, email };
}

async function signInAsStaff(page) {
    await page.goto('/');
    await page.locator('#login-email').fill('reviewer@browser.test');
    await page.locator('#login-password').fill('password');
    await page.locator('[data-login-form] button[type="submit"]').click();
    await page.waitForURL('**/mao/dashboard');
}

test('ordinary MAO staff approves a pending registration through Account Requests', async ({ page, request }, info) => {
    const farmer = await register(request, info.project.name, 'approve');
    await signInAsStaff(page);
    await page.goto(`/mao/farmer-approvals?search=${encodeURIComponent(farmer.email)}`);
    const row = page.locator('[data-farmer-approvals] tbody tr', { hasText: farmer.email });
    await expect(row).toContainText('Pending');
    await expect(row).toContainText('09170000001');
    await row.getByRole('button', { name: 'Approve', exact: true }).click();
    await expect(row).toHaveCount(0);
    await page.goto(`/mao/farmer-approvals?show=approved&search=${encodeURIComponent(farmer.email)}`);
    await expect(row).toContainText('Approved');
    await expect(row).toContainText('mao Tester');
    await row.getByRole('link', { name: 'View Details' }).click();
    await expect(page.getByRole('heading', { name: 'Review history' })).toBeVisible();
    await expect(page.locator('article')).toContainText('Approved');
    expect((await page.request.get('/users')).status()).toBe(403);
});

test('staff rejection and Farmer corrections preserve the existing application', async ({ page, request }, info) => {
    const farmer = await register(request, info.project.name, 'reject');
    await signInAsStaff(page);
    await page.goto(`/mao/farmer-approvals?search=${encodeURIComponent(farmer.email)}`);
    const row = page.locator('[data-farmer-approvals] tbody tr', { hasText: farmer.email });
    await row.locator('summary', { hasText: 'Reject' }).click();
    await row.locator('select[name="reason"]').selectOption('incomplete');
    await row.locator('textarea[name="note"]').fill('Please provide corrected contact details.');
    await row.getByRole('button', { name: 'Reject sign-up' }).click();
    await expect(row).toHaveCount(0);
    await page.goto(`/mao/farmer-approvals?show=rejected&search=${encodeURIComponent(farmer.email)}`);
    await expect(row).toContainText('Rejected');
    await expect(row).toContainText('Please provide corrected contact details.');
    await page.goto('/');
    await page.getByRole('button', { name: 'Sign out', exact: true }).click();
    await expect(page.locator('#login-email')).toBeVisible();
    await page.goto('/registration/resubmit');
    await page.locator('#resubmitEmail').fill(farmer.email);
    await page.locator('#resubmitPassword').fill('FarmerPassword!23');
    await page.locator('#resubmitNote').fill('My corrected contact details are ready for verification.');
    await page.getByRole('button', { name: 'Submit for review' }).click();
    await expect(page.locator('#login-email')).toBeVisible();
    await signInAsStaff(page);
    await page.goto(`/mao/farmer-approvals?search=${encodeURIComponent(farmer.email)}`);
    await expect(row).toHaveCount(1);
    await expect(row).toContainText('Pending');
    await row.getByRole('link', { name: 'View Details' }).click();
    await expect(page.locator('main')).toContainText('My corrected contact details are ready for verification.');
    await expect(page.locator('article')).toContainText('Rejected');
});
