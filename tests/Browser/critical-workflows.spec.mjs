import { test, expect } from '@playwright/test';

async function login(page, email, password='password', destination='mao') {
    await page.goto('/');
    await page.locator('#login-email').fill(email);
    await page.locator('#login-password').fill(password);
    await page.locator('[data-login-form] button[type="submit"]').click();
    if (destination) await page.waitForURL(`**/${destination}/dashboard`);
}

test('ordinary staff assigns an approved chairman, requests a revision and accepts the corrected report', async ({ page, browser }, info) => {
    const project=info.project.name;
    const context=await browser.newContext(info.project.use);
    const chairman=await context.newPage();
    try {
        await login(chairman,`chairman-${project}@browser.test`,`Chairman-${project}!23`,'farmer');
        expect((await chairman.request.get('/association-chairman')).status()).toBe(403);
        await login(page,'reviewer@browser.test');
        expect((await page.request.get('/users')).status()).toBe(403);
        await page.goto(`/mao/farmer-approvals?show=approved&search=chairman-${project}%40browser.test`);
        const account=page.locator('tbody tr',{hasText:`chairman-${project}@browser.test`});
        const details=await account.getByRole('link',{name:'View Details'}).getAttribute('href');
        const id=new URL(details).pathname.split('/').pop();
        await page.goto('/mao/association-reports');
        const row=page.locator('tbody tr',{hasText:`Workflow ${project}`});
        await row.locator('input[name="user_id"]').fill(id);
        await row.getByRole('button',{name:'Assign chairman'}).click();
        await expect(row).toContainText(`Chairman ${project}`);
        await page.locator('#periodKey').fill(`browser-${project}`);
        await page.locator('#periodLabel').fill(`Browser period ${project}`);
        await page.locator('#reportDue').fill('2027-01-31T17:00');
        await page.getByRole('button',{name:'Create reporting period'}).click();
        await chairman.goto('/association-chairman');
        await chairman.locator('tbody tr',{hasText:`Browser period ${project}`}).getByRole('link',{name:'Open'}).click();
        await chairman.getByLabel('Planting date',{exact:true}).fill('2026-05-01');
        await chairman.getByLabel('Harvest date',{exact:true}).fill('2026-09-01');
        await chairman.getByLabel('Production (metric tons)').fill('8');
        await chairman.getByLabel('Area harvested (hectares)').fill('2');
        await chairman.getByLabel('Irrigation',{exact:true}).selectOption('Rainfed');
        await chairman.getByLabel('Remarks',{exact:true}).fill('First observed totals');
        await chairman.getByLabel('Supporting document').setInputFiles({name:'evidence.png',mimeType:'image/png',buffer:Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a3V0AAAAASUVORK5CYII=','base64')});
        await chairman.getByRole('button',{name:'Submit formally'}).click();
        await page.reload();
        await row.getByRole('link',{name:'View',exact:true}).click();
        await page.locator('textarea[name="notes"]').fill('Please correct harvested area.');
        await page.getByRole('button',{name:'Request revision'}).click();
        await chairman.goto('/association-chairman');
        await chairman.locator('tbody tr',{hasText:`Browser period ${project}`}).getByRole('link',{name:'Revise'}).click();
        await expect(chairman.getByLabel('Planting date',{exact:true})).toHaveValue('2026-05-01');
        await expect(chairman.getByLabel('Harvest date',{exact:true})).toHaveValue('2026-09-01');
        await expect(chairman.getByLabel('Irrigation',{exact:true})).toHaveValue('Rainfed');
        await chairman.getByLabel('Area harvested (hectares)').fill('4');
        await chairman.getByLabel('Remarks',{exact:true}).fill('Corrected observed totals');
        await chairman.getByRole('button',{name:'Submit formally'}).click();
        await page.reload();
        await expect(page.locator('[data-submission-version="1"]')).toContainText('First observed totals');
        await expect(page.locator('[data-submission-version="2"]')).toContainText('Corrected observed totals');
        const downloaded=page.waitForEvent('download');
        await page.getByRole('link',{name:'Download supporting document',exact:true}).click();
        expect((await downloaded).suggestedFilename()).toMatch(/\.png$/);
        await page.locator('textarea[name="notes"]').fill('Verified observed totals.');
        await page.getByRole('button',{name:'Accept report'}).click();
        await expect(page.locator('main')).toContainText('accepted');
        await expect(page.locator('main')).toContainText('2.0000 tons/hectare');
        await chairman.goto('/association-chairman');
        await chairman.locator('tbody tr',{hasText:`Browser period ${project}`}).getByRole('link',{name:'Open'}).click();
        await expect(chairman.getByRole('link',{name:'Revise report'})).toHaveCount(0);
        await expect(chairman.locator('main')).toContainText('Verified observed totals.');
        expect(await chairman.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1)).toBe(true);
    } finally { await context.close(); }
});

test('pending and rejected accounts cannot sign in or open protected routes', async ({ page,request },info) => {
    for(const kind of ['pending','rejected']) {
        const email=`${kind}-${info.project.name}@browser.test`;
        const password=`${kind[0].toUpperCase()+kind.slice(1)}-${info.project.name}!23`;
        await login(page,email,password,null);
        await expect(page.locator('#login-email')).toBeVisible();
        expect(new URL(page.url()).pathname).not.toBe('/farmer/dashboard');
        expect((await page.request.get('/farmer/dashboard',{maxRedirects:0})).status()).toBe(302);
        const response=await request.post('/api/mobile/login',{data:{email,password}});
        expect([403,422]).toContain(response.status());
        expect((await response.json()).user).toBeUndefined();
    }
});

test('ordinary staff issues a one-time recovery code and the farmer chooses a new password', async ({page,browser},info) => {
    const email=`recovery-${info.project.name}@browser.test`;
    await login(page,'reviewer@browser.test');
    await page.goto(`/mao/farmer-approvals?show=approved&search=${encodeURIComponent(email)}`);
    await page.locator('tbody tr',{hasText:email}).getByRole('link',{name:'View Details'}).click();
    await page.locator('#verificationMethod').selectOption('in_person');
    await page.locator('#identityConfirmed').check();
    await page.getByRole('button',{name:'Give a one-time recovery code'}).click();
    const code=(await page.locator('[data-recovery-code]').textContent()).trim();
    await page.reload();
    await expect(page.locator('[data-recovery-code]')).toHaveCount(0);
    const context=await browser.newContext(info.project.use);const farmer=await context.newPage();
    try {
        await farmer.goto('/account/recover');
        await farmer.locator('input[name="email"]').fill(email);
        await farmer.locator('input[name="token"]').fill(code);
        await farmer.locator('input[name="password"]').fill(`Recovered-${info.project.name}!2026`);
        await farmer.locator('input[name="password_confirmation"]').fill(`Recovered-${info.project.name}!2026`);
        await farmer.locator('[data-recovery-form] button[type="submit"]').click();
        await login(farmer,email,`Recovered-${info.project.name}!2026`,'farmer');
    } finally {await context.close();}
});
