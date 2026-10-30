// Clicks through every interactive element of the public landing page (IDs match the landing page brief:
// N = nav, H = hero, A = advisory, B = about, R = roles, Q = FAQ, C = contact, T = footer, G = general).
// Run with `npm run test:browser` (starts the site on a throwaway database, see tests/Browser/serve.sh).
import { test, expect } from '@playwright/test';

const NAV_HEIGHT = 73;
const PNG_1PX = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=', 'base64');
const ACCOUNTS = {
    farmer: { email: 'farmer@browser.test', dashboard: '/farmer/dashboard' },
    mao: { email: 'mao@browser.test', dashboard: '/mao/dashboard' },
    it: { email: 'it@browser.test', dashboard: '/admin/dashboard' },
};

const isMobile = (testInfo) => testInfo.project.name.startsWith('mobile');

let consoleErrors;
// A test that makes a request fail on purpose lists the console messages it expects here.
let expectedConsoleErrors;

test.beforeEach(async ({ page, context, baseURL }) => {
    // Outside requests (map tiles, Google Fonts, Facebook, Google Maps) get an empty answer, so the tests do not
    // depend on the network and a slow third party cannot fail them.
    await context.route((url) => !url.href.startsWith(baseURL), (route) => {
        const url = route.request().url();
        if (url.includes('arcgisonline.com')) return route.fulfill({ status: 200, contentType: 'image/png', body: PNG_1PX });
        if (url.includes('fonts.googleapis.com')) return route.fulfill({ status: 200, contentType: 'text/css', body: '' });
        return route.fulfill({ status: 200, contentType: 'text/html', body: '<!doctype html><title>stub</title>' });
    });

    // G3: any script error or console error while on the landing page fails the test.
    consoleErrors = [];
    expectedConsoleErrors = [];
    const onLanding = () => new URL(page.url()).pathname === '/';
    page.on('pageerror', (error) => { if (onLanding()) consoleErrors.push(`pageerror: ${error.message}`); });
    page.on('console', (message) => {
        if (message.type() !== 'error' || !onLanding()) return;
        // The contact form's validation answer (422) is expected in C1; Chrome logs it like a failed download.
        if (message.text().includes('status of 422') && message.location().url.endsWith('/contact')) return;
        // Messages from inside the embedded Google map belong to Google's page, not ours.
        if (/^https:\/\/(maps|www)\.google\.com\//.test(message.location().url)) return;
        if (expectedConsoleErrors.some((pattern) => pattern.test(message.text()))) return;
        consoleErrors.push(`console: ${message.text()}`);
    });
});

test.afterEach(async () => {
    expect(consoleErrors, 'console errors on the landing page').toEqual([]);
});

async function openLanding(page) {
    await page.goto('/');
    await expect(page.locator('[data-public-nav]')).toBeVisible();
}

async function openMobileMenuIfNeeded(page, testInfo) {
    if (!isMobile(testInfo)) return;
    await page.locator('[data-nav-toggle]').click();
    await expect(page.locator('[data-nav-menu]')).toBeVisible();
}

function navLink(page, id, testInfo) {
    return isMobile(testInfo)
        ? page.locator(`[data-nav-menu] [data-nav-link="${id}"]`)
        : page.locator(`header nav[aria-label="Main"] [data-nav-link="${id}"]`);
}

/** The section starts right under the sticky nav, and its heading is on screen and not hidden by the nav. */
async function expectScrolledTo(page, id) {
    await expect.poll(async () => page.evaluate(([sectionId, navHeight]) => {
        const top = Math.round(document.getElementById(sectionId).getBoundingClientRect().top);
        return Math.abs(top - navHeight) <= 2;
    }, [id, NAV_HEIGHT]), { timeout: 10_000 }).toBe(true); // a long smooth scroll on the tall mobile page

    const heading = await page.evaluate((sectionId) => {
        const box = document.getElementById(sectionId).querySelector('h1, h2').getBoundingClientRect();
        return { top: box.top, bottom: box.bottom };
    }, id);
    const navBottom = await page.locator('[data-public-nav]').evaluate((nav) => nav.getBoundingClientRect().bottom);
    expect(heading.top).toBeGreaterThanOrEqual(navBottom);
    expect(heading.bottom).toBeLessThanOrEqual(page.viewportSize().height);
}

// Login tests check where the sign-in sends each role; they wait for that address, not for the dashboard to finish
// loading (dashboards make slow outside weather and ML calls on the test server).
async function logIn(page, email) {
    await page.locator('#login-email').fill(email);
    await page.locator('#login-password').fill('password');
    await page.locator('[data-login-form] button[type="submit"]').click();
}

// ─── TOP NAV ─────────────────────────────────────────────────────────────

test('N1 logo and office name go to the home page', async ({ page }) => {
    await page.goto('/#contact');
    await page.locator('[data-nav-home]').click();
    await expect(page).toHaveURL(/127\.0\.0\.1:8123\/$/);
    await expect(page.locator('#hero')).toBeVisible();
});

for (const [id, label] of [['hero', 'N2 Home'], ['about', 'N3 About'], ['features', 'N4 Features'], ['roles', 'N5 Who it’s for'], ['contact', 'N6 Contact']]) {
    test(`${label} scrolls smoothly to #${id} below the sticky nav`, async ({ page }, testInfo) => {
        await openLanding(page);
        if (id === 'hero') await page.evaluate(() => window.scrollTo(0, 2500));
        // Smooth unless the visitor asked for reduced motion (this suite runs with reduced motion; M1 checks smooth).
        expect(await page.evaluate(() => getComputedStyle(document.documentElement).scrollBehavior)).toBe('auto');
        await openMobileMenuIfNeeded(page, testInfo);
        await navLink(page, id, testInfo).click();
        await expect(page).toHaveURL(new RegExp(`#${id}$`));
        await expectScrolledTo(page, id);
    });
}

test('N7 the active nav link follows the section being read', async ({ page }, testInfo) => {
    test.skip(isMobile(testInfo), 'the desktop link row is where the highlight shows');
    await openLanding(page);
    await expect(navLink(page, 'hero', testInfo)).toHaveClass(/is-active/);
    for (const id of ['about', 'features', 'roles', 'contact']) {
        await page.evaluate((sectionId) => document.getElementById(sectionId).scrollIntoView({ behavior: 'instant' }), id);
        await expect(navLink(page, id, testInfo)).toHaveClass(/is-active/);
        await expect(navLink(page, id, testInfo)).toHaveAttribute('aria-current', 'true');
        await expect(page.locator('header nav[aria-label="Main"] .is-active')).toHaveCount(1);
    }
});

test('N12 mobile menu opens, closes on link click and on Esc', async ({ page }, testInfo) => {
    test.skip(!isMobile(testInfo), 'the menu button only shows on small screens');
    await openLanding(page);
    const toggle = page.locator('[data-nav-toggle]');
    const menu = page.locator('[data-nav-menu]');
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(menu).toBeHidden();

    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await expect(toggle).toHaveAttribute('aria-label', 'Close menu');
    await expect(menu).toBeVisible();

    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(menu).toBeHidden();

    await toggle.click();
    await page.keyboard.press('Escape');
    await expect(menu).toBeHidden();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(toggle).toBeFocused();

    await toggle.click();
    await menu.locator('[data-nav-link="features"]').click();
    await expect(menu).toBeHidden();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expectScrolledTo(page, 'features');
});

// ─── HERO ────────────────────────────────────────────────────────────────

test('H1 Explore features scrolls to #features', async ({ page }) => {
    await openLanding(page);
    await page.locator('[data-hero-features]').click();
    await expect(page).toHaveURL(/#features$/);
    await expectScrolledTo(page, 'features');
});

test('H2 How it works scrolls to #how', async ({ page }) => {
    await openLanding(page);
    await page.locator('[data-hero-how]').click();
    await expect(page).toHaveURL(/#how$/);
    await expectScrolledTo(page, 'how');
});

test('H3 login form posts to the login route with CSRF and the controller field names', async ({ page }) => {
    await openLanding(page);
    const form = page.locator('[data-login-form]');
    await expect(form).toHaveAttribute('method', 'POST');
    await expect(form).toHaveAttribute('action', 'http://127.0.0.1:8123/login');
    await expect(form.locator('input[name="_token"]')).toHaveCount(1);
    await expect(form.locator('input[name="email"]')).toBeVisible();
    await expect(form.locator('input[name="password"]')).toBeVisible();
    await expect(form.locator('input[name="remember"]')).toHaveCount(0); // no "Remember me": the sign-in ends with the browser
});

for (const [role, account] of Object.entries(ACCOUNTS)) {
    test(`H4 valid ${role} login from the landing card opens the ${role} dashboard`, async ({ page }) => {
        await openLanding(page);
        await logIn(page, account.email);
        await page.waitForURL(`**${account.dashboard}`, { waitUntil: 'commit' });
        expect(new URL(page.url()).pathname).toBe(account.dashboard);
    });
}

for (const [role, account] of Object.entries(ACCOUNTS)) {
    test(`H9 a page opened before logging in (the heat map) is ignored: the ${role} lands on the ${role} dashboard`, async ({ page }) => {
        await page.goto('/heat-map');
        await expect(page).toHaveURL(/\/#login$/); // a guest: Laravel remembers the heat map, /login sends on to the card
        await openLanding(page);
        await logIn(page, account.email);
        await page.waitForURL(`**${account.dashboard}`, { waitUntil: 'commit' });
        expect(new URL(page.url()).pathname).toBe(account.dashboard);
    });
}

test('H5 invalid login returns to the landing page with the error in the card', async ({ page }) => {
    await openLanding(page);
    await page.locator('#login-email').fill(ACCOUNTS.farmer.email);
    await page.locator('#login-password').fill('wrong-password');
    await page.locator('[data-login-form] button[type="submit"]').click();
    await page.waitForLoadState('domcontentloaded');
    expect(new URL(page.url()).pathname).toBe('/');
    await expect(page.locator('#login-email-error')).toBeVisible();
    await expect(page.locator('#login-email-error')).toContainText('do not match');
    await expect(page.locator('#login-email')).toHaveValue(ACCOUNTS.farmer.email);
    await expect(page.locator('#login-email')).toHaveAttribute('aria-invalid', 'true');
});

test('H6 Forgot password? opens the password reset request page', async ({ page }) => {
    await openLanding(page);
    await page.locator('[data-forgot]').click();
    await expect(page).toHaveURL(/\/forgot-password$/);
});

test('H6b the sign-in password eye shows and hides the password without submitting or losing the caret', async ({ page }) => {
    await openLanding(page);
    const password = page.locator('#login-password');
    const eye = page.locator('[data-login-form] [data-password-toggle]');
    await expect(password).toHaveAttribute('type', 'password');
    await expect(password).toHaveAttribute('autocomplete', 'current-password');
    await expect(eye).toHaveAttribute('aria-controls', 'login-password');
    await expect(eye).toHaveAttribute('aria-label', 'Show password');

    await password.fill('password');
    await password.evaluate((input) => input.setSelectionRange(3, 3));
    await eye.click();
    await expect(password).toHaveAttribute('type', 'text');
    await expect(eye).toHaveAttribute('aria-pressed', 'true');
    await expect(eye).toHaveAttribute('aria-label', 'Hide password');
    await expect(password).toBeFocused();
    expect(await password.evaluate((input) => [input.selectionStart, input.selectionEnd])).toEqual([3, 3]);
    expect(new URL(page.url()).pathname).toBe('/');

    await eye.focus();
    await page.keyboard.press('Enter');
    await expect(password).toHaveAttribute('type', 'password');
    await page.keyboard.press('Space');
    await expect(password).toHaveAttribute('type', 'text');
    expect(new URL(page.url()).pathname).toBe('/');

    // Typed text never runs under the eye.
    const [field, icon] = await Promise.all([password.boundingBox(), eye.boundingBox()]);
    const paddingRight = await password.evaluate((input) => parseFloat(getComputedStyle(input).paddingRight));
    expect(field.x + field.width - paddingRight).toBeLessThanOrEqual(icon.x + 1);

    // The form still signs in with the password showing.
    await page.locator('#login-email').fill(ACCOUNTS.farmer.email);
    await page.locator('[data-login-form] button[type="submit"]').click();
    await page.waitForURL(`**${ACCOUNTS.farmer.dashboard}`, { waitUntil: 'commit' });
});

test('H7 the Create account tab switches the card in place, without leaving the page', async ({ page }) => {
    await openLanding(page);
    const card = page.locator('[data-auth-card]');
    await expect(page.getByText('New to iClimate?')).toHaveCount(0);
    await page.getByRole('tab', { name: 'Create account' }).click();
    await expect(card).toHaveAttribute('data-auth-mode', 'register');
    expect(new URL(page.url()).pathname).toBe('/');
    await expect(page).toHaveURL(/#register$/);
    await expect(page.locator('#auth-panel-register')).toBeVisible();
    await expect(page.locator('#auth-panel-login')).toBeHidden();
    await expect(page.locator('#firstName')).toBeFocused();
    await expect(page.locator('[data-auth-status]')).toHaveText('Create account form shown.');
    await expect(page.getByRole('tab', { name: 'Create account' })).toHaveAttribute('aria-selected', 'true');
    await expect(page.getByRole('tab', { name: 'Sign in' })).toHaveAttribute('aria-selected', 'false');
    await expect(page.getByText('Already have an account?')).toHaveCount(0);
});

test('K1 the Sign in / Create account tabs switch both ways, by mouse and by keyboard', async ({ page }) => {
    await openLanding(page);
    const card = page.locator('[data-auth-card]');
    const signIn = page.getByRole('tab', { name: 'Sign in' });
    const create = page.getByRole('tab', { name: 'Create account' });
    await expect(page.getByRole('tablist', { name: 'Log in or create an account' })).toBeVisible();

    await create.click();
    await expect(card).toHaveAttribute('data-auth-mode', 'register');
    await signIn.click();
    await expect(card).toHaveAttribute('data-auth-mode', 'login');
    await expect(page).toHaveURL(/#login$/);
    await expect(page.locator('#login-email')).toBeFocused();

    // Keyboard: the selected tab is the one Tab stop; arrows move between the tabs and switch the card.
    await signIn.focus();
    await page.keyboard.press('ArrowRight');
    await expect(create).toBeFocused();
    await expect(create).toHaveAttribute('aria-selected', 'true');
    await expect(create).toHaveAttribute('tabindex', '0');
    await expect(signIn).toHaveAttribute('tabindex', '-1');
    await expect(card).toHaveAttribute('data-auth-mode', 'register');
    await expect(page).toHaveURL(/#register$/);
    await page.keyboard.press('ArrowLeft');
    await expect(signIn).toBeFocused();
    await expect(card).toHaveAttribute('data-auth-mode', 'login');
    await page.keyboard.press('End');
    await expect(card).toHaveAttribute('data-auth-mode', 'register');
    await page.keyboard.press('Home');
    await expect(card).toHaveAttribute('data-auth-mode', 'login');
    // Enter on a tab switches too and moves into the form.
    await create.focus();
    await page.keyboard.press('Enter');
    await expect(card).toHaveAttribute('data-auth-mode', 'register');
    await expect(page.locator('#firstName')).toBeFocused();
    expect(new URL(page.url()).pathname).toBe('/');
});

test('K2 /#register opens the card in Create account mode, and a refresh keeps it', async ({ page }) => {
    await page.goto('/#register');
    await expect(page.locator('[data-auth-card]')).toHaveAttribute('data-auth-mode', 'register');
    await expect(page.locator('[data-auth-panel="register"]')).toBeVisible();
    await page.reload();
    await expect(page.locator('[data-auth-panel="register"]')).toBeVisible();
    await page.goto('/#login');
    await expect(page.locator('[data-auth-panel="login"]')).toBeVisible();
});

test('K3 an empty sign-up shows its errors on the landing page in Create account mode', async ({ page }) => {
    await page.goto('/#register');
    await page.locator('#createAccountBtn').click();
    expect(new URL(page.url()).pathname).toBe('/');
    await expect(page.locator('[data-auth-card]')).toHaveAttribute('data-auth-mode', 'register');
    await expect(page.locator('#formMessage')).toBeVisible();
    for (const id of ['firstName', 'lastName', 'email', 'barangay', 'password', 'confirmPassword', 'captcha_answer', 'agreeTerms']) {
        await expect(page.locator(`#${id}-error`)).toBeVisible();
    }
    await expect(page.locator('#firstName')).toBeFocused();
});

test('K4 a sign-up the server refuses comes back to the landing page in Create account mode with its error', async ({ page }) => {
    await page.goto('/#register');
    await page.locator('#firstName').fill('Juan');
    await page.locator('#lastName').fill('Dela Cruz');
    await page.locator('#email').fill(`juan.${Date.now()}@example.com`);
    await page.locator('#barangay').selectOption({ index: 1 });
    await page.locator('#password').fill('Password123!');
    await page.locator('#confirmPassword').fill('Password123!');
    await page.locator('#captcha_answer').fill('WRONG');
    await page.locator('#agreeTerms').check();
    await page.locator('#createAccountBtn').click();
    await page.waitForLoadState('domcontentloaded');
    expect(new URL(page.url()).pathname).toBe('/');
    await expect(page).toHaveURL(/#register$/);
    await expect(page.locator('[data-auth-panel="register"]')).toBeVisible();
    await expect(page.locator('#captcha_answer-error')).toContainText('security check answer is incorrect');
    await expect(page.locator('#firstName')).toHaveValue('Juan');
    await expect(page.locator('#password')).toHaveValue('');
    await expect(page.locator('#login-email')).toHaveValue('');
});

test('K5 password eye, strength meter, rule chips and captcha work in the card', async ({ page }) => {
    await page.goto('/#register');
    const password = page.locator('#password');
    await password.fill('abc');
    await expect(page.locator('#passwordStrengthLabel')).toContainText('Weak');
    await password.fill('Abcdef1!');
    await expect(page.locator('#passwordStrengthLabel')).toContainText('Strong');
    await expect(page.locator('[data-password-rule="symbol"]')).toHaveClass(/is-met/);
    await page.locator('#pwdToggle').click();
    await expect(password).toHaveAttribute('type', 'text');
    await expect(page.locator('#pwdToggle')).toHaveAttribute('aria-pressed', 'true');
    await page.locator('#pwdToggle').click();
    await expect(password).toHaveAttribute('type', 'password');
    // The confirm eye works on its own.
    await page.locator('#confirmPwdToggle').click();
    await expect(page.locator('#confirmPassword')).toHaveAttribute('type', 'text');
    await expect(password).toHaveAttribute('type', 'password');
    await expect(page.locator('#confirmPwdToggle')).toHaveAttribute('aria-controls', 'confirmPassword');
    const captcha = page.locator('[data-auth-panel="register"] .captcha-image');
    await expect(captcha).toHaveAttribute('src', /\/captcha\/register\?v=\d+/);
    await expect.poll(() => captcha.evaluate((img) => img.complete && img.naturalWidth > 0)).toBe(true);
});

test('K7 Create account mode: every placeholder, value and label shows in full, clear of the eye and chevron', async ({ page }, testInfo) => {
    test.skip(isMobile(testInfo), 'sets its own sizes');
    for (const [width, height] of [[375, 667], [768, 1024], [1280, 800], [1440, 900]]) {
        await page.setViewportSize({ width, height });
        await page.goto('/#register');
        await expect(page.locator('[data-auth-panel="register"]')).toBeVisible();
        await page.waitForTimeout(250); // the card's width transition
        const problems = await page.evaluate(() => {
            const out = [];
            const ctx = document.createElement('canvas').getContext('2d');
            const panel = document.querySelector('[data-auth-panel="register"]');
            const fields = [...panel.querySelectorAll('input.form-input, select.form-input, input.org-ms-search')]
                .filter((el) => el.offsetParent !== null && el.type !== 'hidden');
            for (const el of fields) {
                const cs = getComputedStyle(el);
                const name = el.id || el.name;
                const text = el.tagName === 'SELECT' ? (el.selectedOptions[0]?.text ?? '').trim() : (el.value || el.placeholder);
                ctx.font = `${cs.fontStyle} ${cs.fontWeight} ${cs.fontSize} ${cs.fontFamily}`;
                const textWidth = ctx.measureText(text).width;
                const room = el.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight);
                if (el.scrollWidth > el.clientWidth) out.push(`${name}: scrollWidth ${el.scrollWidth} > clientWidth ${el.clientWidth}`);
                if (textWidth > room + 0.5) out.push(`${name}: "${text}" needs ${Math.ceil(textWidth)}px, has ${Math.floor(room)}px`);
                if (cs.textOverflow === 'ellipsis') out.push(`${name}: uses an ellipsis`);
                const box = el.getBoundingClientRect();
                const textRight = box.right - parseFloat(cs.paddingRight);
                if (el.tagName === 'SELECT') {
                    // The chevron is 16px, 10px from the right edge.
                    if (textRight > box.right - 26) out.push(`${name}: text box runs under the chevron`);
                }
                const eye = el.parentElement.querySelector('.pwd-toggle');
                if (eye && eye.getBoundingClientRect().left < textRight - 0.5) out.push(`${name}: eye button overlaps the text box`);
            }
            for (const label of panel.querySelectorAll('label')) {
                if (label.offsetParent !== null && label.scrollWidth > label.clientWidth + 1) out.push(`label "${label.textContent.trim()}" is cut off`);
            }
            return out;
        });
        expect(problems, `${width}px`).toEqual([]);

        const cardWidth = () => page.locator('[data-auth-card]').evaluate((card) => Math.round(card.getBoundingClientRect().width));
        if (width >= 1280) {
            expect(await cardWidth(), `${width}px Create account card`).toBe(640);
            await page.locator('[data-auth-toggle] [data-auth-switch="login"]').click();
            await expect.poll(cardWidth).toBe(480); // Log in mode keeps 30rem
        }
        if (width < 640) {
            const columns = await page.evaluate(() => [...document.querySelectorAll('[data-auth-panel="register"] .form-row-2col')]
                .map((row) => getComputedStyle(row).gridTemplateColumns.split(' ').length));
            expect(columns, `${width}px rows`).toEqual([1, 1, 1]);
        }
    }
});

test('K6 Create account mode: no sideways scroll at 375 and 1280px, nothing cut off, two-column rows on desktop', async ({ page }, testInfo) => {
    test.skip(isMobile(testInfo), 'sets its own sizes');
    for (const [width, height] of [[375, 667], [1280, 800]]) {
        await page.setViewportSize({ width, height });
        await page.goto('/#register');
        await expect(page.locator('[data-auth-panel="register"]')).toBeVisible();
        const report = await page.evaluate(() => {
            const card = document.querySelector('[data-auth-card]');
            const hero = document.getElementById('hero');
            const row = document.querySelector('[data-auth-panel="register"] .form-row-2col');
            return {
                overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
                cardClips: card.scrollHeight > card.clientHeight + 1 || getComputedStyle(card).overflowY !== 'visible',
                cardInsideHero: card.getBoundingClientRect().bottom <= hero.getBoundingClientRect().bottom + 1,
                columns: getComputedStyle(row).gridTemplateColumns.split(' ').length,
            };
        });
        expect(report.overflow, `${width}px`).toBeLessThanOrEqual(0);
        expect(report.cardClips, `${width}px card has an inner scroll or clips`).toBe(false);
        expect(report.cardInsideHero, `${width}px hero grows with the card`).toBe(true);
        expect(report.columns, `${width}px field columns`).toBe(width >= 1024 ? 2 : 1);
    }
});

for (const [role, account] of Object.entries(ACCOUNTS)) {
    test(`H8 logged-in ${role} sees Go to dashboard, which opens the ${role} dashboard`, async ({ page }) => {
        await openLanding(page);
        await logIn(page, account.email);
        await page.waitForURL(`**${account.dashboard}`, { waitUntil: 'commit' });
        await openLanding(page);
        await expect(page.locator('[data-login-form]')).toHaveCount(0);
        await page.locator('[data-go-dashboard]').click();
        await page.waitForURL(`**${account.dashboard}`, { waitUntil: 'commit' });
        expect(new URL(page.url()).pathname).toBe(account.dashboard);
    });
}

test('H10 Sign out under Go to dashboard comes back to the landing sign-in card, with no remember cookie', async ({ page, context }) => {
    const account = Object.values(ACCOUNTS)[0];
    await openLanding(page);
    await logIn(page, account.email);
    await page.waitForURL(`**${account.dashboard}`, { waitUntil: 'commit' });
    expect((await context.cookies()).some((c) => c.name.startsWith('remember_web_'))).toBe(false);
    await openLanding(page);
    await page.locator('[data-landing-logout] button').click();
    await page.waitForURL(/\/#login$/);
    await expect(page.locator('[data-login-form]')).toBeVisible();
    await expect(page.locator('[data-go-dashboard]')).toHaveCount(0);
});

test('H11 every Show password eye is a 44px tap target with its icon where it was', async ({ page }) => {
    const measure = (selector) => page.locator(selector).evaluate((button) => {
        const box = button.getBoundingClientRect();
        const icon = button.querySelector('svg').getBoundingClientRect();
        const field = document.getElementById(button.getAttribute('aria-controls')).getBoundingClientRect();
        return { width: box.width, height: box.height, iconFromRight: Math.round(field.right - (icon.left + icon.width / 2)) };
    });
    await openLanding(page);
    // Sign in: icon centre 25px in from the field's right edge; Create account: 20px (as before the bigger target).
    expect(await measure('[data-auth-panel="login"] [data-password-toggle]')).toEqual({ width: 44, height: 44, iconFromRight: 25 });
    await page.goto('/#register');
    await expect(page.locator('[data-auth-card]')).toHaveAttribute('data-auth-mode', 'register');
    for (const id of ['#pwdToggle', '#confirmPwdToggle']) {
        expect(await measure(id), id).toEqual({ width: 44, height: 44, iconFromRight: 20 });
    }
});

// ─── ADVISORY (removed) / ABOUT ──────────────────────────────────────────

test('A1 the MAO advisory block is gone, even with a published advisory', async ({ page }) => {
    await openLanding(page);
    await expect(page.locator('#advisory')).toHaveCount(0);
    await expect(page.getByText('All advisories')).toHaveCount(0);
    await expect(page.getByText('Typhoon watch for Lian')).toHaveCount(0);
});

test('B1 About the MAO scrolls to #contact', async ({ page }) => {
    await openLanding(page);
    await page.locator('[data-about-mao]').click();
    await expect(page).toHaveURL(/#contact$/);
    await expectScrolledTo(page, 'contact');
});

test('F1 feature, fact and step cards do not look clickable', async ({ page }) => {
    await openLanding(page);
    const cards = page.locator('#about li, #features article, #features li, #how li');
    expect(await cards.count()).toBeGreaterThan(10);
    for (const card of await cards.all()) {
        expect(await card.evaluate((el) => getComputedStyle(el).cursor)).toBe('auto');
        expect(await card.locator('a, button').count()).toBe(0);
    }
});

// ─── WHO USES ICLIMATE ───────────────────────────────────────────────────

test('R1 clicking a tab shows its panel', async ({ page }) => {
    await openLanding(page);
    for (const key of ['mao', 'it', 'farmers']) {
        await page.locator(`#role-tab-${key}`).click();
        await expect(page.locator(`#role-tab-${key}`)).toHaveAttribute('aria-selected', 'true');
        await expect(page.locator(`#role-panel-${key}`)).toBeVisible();
        await expect(page.locator('#roles [role="tabpanel"]:visible')).toHaveCount(1);
    }
});

test('R2 arrow keys move between tabs with the right ARIA', async ({ page }) => {
    await openLanding(page);
    await expect(page.locator('#roles [role="tablist"]')).toHaveCount(1);
    const farmers = page.locator('#role-tab-farmers');
    await expect(farmers).toHaveAttribute('aria-controls', 'role-panel-farmers');
    await expect(page.locator('#role-panel-farmers')).toHaveAttribute('role', 'tabpanel');

    await farmers.focus();
    await page.keyboard.press('ArrowRight');
    await expect(page.locator('#role-tab-mao')).toBeFocused();
    await expect(page.locator('#role-tab-mao')).toHaveAttribute('aria-selected', 'true');
    await expect(farmers).toHaveAttribute('aria-selected', 'false');
    await expect(page.locator('#role-panel-mao')).toBeVisible();

    await page.keyboard.press('ArrowRight');
    await page.keyboard.press('ArrowRight');
    await expect(farmers).toBeFocused(); // wraps around

    await page.keyboard.press('ArrowLeft');
    await expect(page.locator('#role-tab-it')).toBeFocused();
    await expect(page.locator('#role-panel-it')).toBeVisible();
    await expect(page.locator('#role-tab-it')).toHaveAttribute('tabindex', '0');
    await expect(farmers).toHaveAttribute('tabindex', '-1');
});

for (const key of ['farmers', 'mao', 'it']) {
    test(`R3 the ${key} panel's log in button opens the sign-in card`, async ({ page }) => {
        await openLanding(page);
        await page.locator(`#role-tab-${key}`).click();
        await page.locator(`#role-panel-${key} [data-role-login]`).click();
        await expect(page).toHaveURL(/\/#login$/);
        await expect(page.locator('[data-auth-card]')).toHaveAttribute('data-auth-mode', 'login');
    });
}

// ─── FAQ ─────────────────────────────────────────────────────────────────

test('Q1 opening a question closes the others', async ({ page }) => {
    await openLanding(page);
    await expect(page.locator('#faq-a-0')).toBeVisible();
    await page.locator('#faq-q-1').click();
    await expect(page.locator('#faq-a-1')).toBeVisible();
    await expect(page.locator('#faq-a-0')).toBeHidden();
    await expect(page.locator('#faq-q-0')).toHaveAttribute('aria-expanded', 'false');
    await page.locator('#faq-q-2').click();
    await expect(page.locator('#faq-a-2')).toBeVisible();
    await expect(page.locator('#faq-a-1')).toBeHidden();
    await page.locator('#faq-q-2').click();
    await expect(page.locator('#faq-a-2')).toBeHidden();
});

test('Q2 questions open and close with Enter and Space', async ({ page }) => {
    await openLanding(page);
    const question = page.locator('#faq-q-2');
    await question.focus();
    await page.keyboard.press('Enter');
    await expect(question).toHaveAttribute('aria-expanded', 'true');
    await expect(page.locator('#faq-a-2')).toBeVisible();
    await page.keyboard.press('Space');
    await expect(question).toHaveAttribute('aria-expanded', 'false');
    await expect(page.locator('#faq-a-2')).toBeHidden();
});

// ─── CONTACT ─────────────────────────────────────────────────────────────

test('C1 contact form shows validation errors, then the success message', async ({ page }) => {
    await openLanding(page);
    const form = page.locator('#contactForm');
    await expect(form.locator('input[name="_token"]')).toHaveCount(1);
    await expect(form).toHaveAttribute('action', 'http://127.0.0.1:8123/contact');

    await form.locator('#contact-email').fill('not-an-email');
    await form.locator('#contactSubmit').click();
    await expect(form.locator('#contact-name-error')).toBeVisible();
    await expect(form.locator('#contact-email-error')).toContainText('valid email');
    await expect(form.locator('#contact-message-error')).toBeVisible();
    await expect(form.locator('#contactStatus')).toContainText('Please check');

    await form.locator('#contact-name').fill('Juan Dela Cruz');
    await form.locator('#contact-email').fill('juan@example.com');
    await form.locator('#contact-role').selectOption('Rice Farmer');
    await form.locator('#contact-message').fill('When is the next seed distribution?');
    await form.locator('#contactSubmit').click();
    await expect(form.locator('#contactStatus')).toContainText('Thank you. Your message was sent');
    await expect(form.locator('#contact-name-error')).toBeHidden();
    await expect(form.locator('#contact-name')).toHaveValue('');
});

test('C2 email links use mailto: (no phone number is published yet)', async ({ page }) => {
    await openLanding(page);
    await expect(page.locator('[data-contact-email]')).toHaveAttribute('href', 'mailto:mao_lian@yahoo.com');
    await expect(page.locator('[data-footer-email]')).toHaveAttribute('href', 'mailto:mao_lian@yahoo.com');
    await expect(page.locator('#faq a[href^="mailto:"]')).toHaveAttribute('href', 'mailto:mao_lian@yahoo.com');
    await expect(page.locator('a[href^="tel:"]')).toHaveCount(0);
});

test('C3 Get directions opens Google Maps directions to Lian Municipal Hall in a new tab', async ({ page }) => {
    await openLanding(page);
    const link = page.locator('[data-directions]');
    await expect(link).toHaveAttribute('target', '_blank');
    await expect(link).toHaveAttribute('rel', 'noopener noreferrer');
    const [popup] = await Promise.all([page.waitForEvent('popup'), link.click()]);
    expect(popup.url()).toBe('https://www.google.com/maps/dir/?api=1&destination=Lian%20Municipal%20Hall%2C%20Lian%2C%20Batangas');
});

test('C4 the Google map of Lian Municipal Hall is embedded in #contact', async ({ page }) => {
    await openLanding(page);
    const map = page.locator('#contact iframe[data-office-map]');
    await expect(map).toHaveCount(1);
    await expect(map).toHaveAttribute('title', 'Map of the Municipal Agriculture Office, Lian Municipal Hall, Lian, Batangas');
    const src = await map.getAttribute('src');
    expect(src).toContain('Lian%20Municipal%20Hall');
    expect(src).toContain('output=embed');
    await expect(map).toHaveAttribute('loading', 'lazy');
    await map.scrollIntoViewIfNeeded();
    await expect(map).toBeVisible();
    const box = await map.boundingBox();
    expect(Math.round(box.height)).toBe(260); // fixed height: no layout shift
    // The old Leaflet map and its floating label are gone.
    await expect(page.locator('#officeMap, #officeMapCard, .leaflet-container')).toHaveCount(0);
});

// ─── FOOTER ──────────────────────────────────────────────────────────────

for (const id of ['about', 'features', 'roles', 'contact']) {
    test(`T1 footer Explore link to #${id}`, async ({ page }) => {
        await openLanding(page);
        await page.locator(`[data-footer-link="${id}"]`).click();
        await expect(page).toHaveURL(new RegExp(`#${id}$`));
        await expectScrolledTo(page, id);
    });
}

test('T1 footer Create account opens the hero card in Create account mode', async ({ page }) => {
    await openLanding(page);
    await page.locator('[data-footer-account="register"]').click();
    expect(new URL(page.url()).pathname).toBe('/');
    await expect(page).toHaveURL(/#register$/);
    await expect(page.locator('[data-auth-card]')).toHaveAttribute('data-auth-mode', 'register');
    await expectScrolledTo(page, 'hero');
    await expect(page.locator('#firstName')).toBeFocused();
    // Clicking it again when the address already ends in #register still opens the card.
    await page.getByRole('tab', { name: 'Sign in' }).click();
    await page.evaluate(() => history.replaceState(null, '', '#register'));
    await page.locator('[data-footer-account="register"]').click();
    await expect(page.locator('[data-auth-card]')).toHaveAttribute('data-auth-mode', 'register');
});

for (const [key, path] of [['login', '/#login'], ['password', '/forgot-password']]) {
    test(`T1 footer Account link opens ${path}`, async ({ page }) => {
        await openLanding(page);
        await page.locator(`[data-footer-account="${key}"]`).click();
        await expect(page).toHaveURL(new RegExp(`${path}$`));
    });
}

test('T2 Facebook opens our page in a new tab; email icon uses mailto:', async ({ page }) => {
    await openLanding(page);
    const facebook = page.locator('[data-footer-facebook]');
    await expect(facebook).toHaveAttribute('rel', 'noopener noreferrer');
    const [popup] = await Promise.all([page.waitForEvent('popup'), facebook.click()]);
    expect(popup.url()).toBe('https://www.facebook.com/profile.php?id=61551743430540');
    await expect(page.locator('[data-footer-email]')).toHaveAttribute('href', 'mailto:mao_lian@yahoo.com');
});

test('T3 Back to top scrolls smoothly to the top', async ({ page }) => {
    await openLanding(page);
    await page.locator('[data-back-to-top]').click();
    await expect.poll(() => page.evaluate(() => window.scrollY), { timeout: 5000 }).toBe(0);
});

// ─── GENERAL ─────────────────────────────────────────────────────────────

test('G1 live weather endpoint answers and the weather card is filled', async ({ page, request }) => {
    const response = await request.get('/login/weather');
    expect(response.ok()).toBe(true);
    expect(await response.json()).toHaveProperty('has_real_weather');
    await openLanding(page);
    await expect(page.locator('[data-weather-status]')).not.toBeEmpty();
});

const VIEWPORTS = [[375, 667], [390, 844], [768, 1024], [1280, 800], [1440, 900], [1920, 1080]];
const SECTIONS = ['hero', 'about', 'features', 'how', 'roles', 'faq', 'contact'];

for (const [width, height] of [[1280, 800], [1440, 900]]) {
    test(`G2 at ${width}x${height} the whole hero, login card included, is visible without scrolling`, async ({ page }, testInfo) => {
        test.skip(isMobile(testInfo), 'desktop sizes');
        await page.setViewportSize({ width, height });
        await openLanding(page);
        const boxes = await page.evaluate(() => {
            const rect = (el) => { const r = el.getBoundingClientRect(); return { top: r.top, bottom: r.bottom, left: r.left, right: r.right }; };
            return {
                nav: rect(document.querySelector('[data-public-nav]')),
                hero: rect(document.getElementById('hero')),
                card: rect(document.querySelector('[data-login-card]')),
                headline: rect(document.querySelector('#hero h1')),
                buttons: rect(document.querySelector('[data-hero-how]')),
            };
        });
        expect(boxes.hero.top).toBe(boxes.nav.bottom);
        expect(Math.round(boxes.hero.bottom)).toBe(height); // exactly one screen
        expect(boxes.card.top).toBeGreaterThanOrEqual(boxes.nav.bottom);
        expect(boxes.card.bottom).toBeLessThanOrEqual(height);
        expect(boxes.headline.top).toBeGreaterThanOrEqual(boxes.nav.bottom);
        expect(boxes.buttons.bottom).toBeLessThanOrEqual(height);
        expect(boxes.card.left).toBeGreaterThan(boxes.headline.right - 1); // two columns: text left, card right
    });
}

test('G2 at every size: one screen per section, nothing cut off or under the nav, no sideways scroll, 44px buttons', async ({ page }, testInfo) => {
    test.skip(isMobile(testInfo), 'sets its own sizes');
    for (const [width, height] of VIEWPORTS) {
        await page.setViewportSize({ width, height });
        await openLanding(page);
        const report = await page.evaluate(([ids, navHeight]) => {
            const problems = [];
            const vh = window.innerHeight;
            if (document.documentElement.scrollWidth > document.documentElement.clientWidth) problems.push('horizontal scroll');
            for (const id of ids) {
                const section = document.getElementById(id);
                const style = getComputedStyle(section);
                if (section.offsetHeight < vh - navHeight - 1) problems.push(`#${id} shorter than one screen (${section.offsetHeight}px)`);
                if (style.overflowY !== 'visible' || style.overflowX !== 'visible') problems.push(`#${id} clips its content`);
                if (section.scrollHeight > section.clientHeight + 1) problems.push(`#${id} content overflows`);
                // Jump there and check nothing sits under the nav.
                section.scrollIntoView({ behavior: 'instant' });
                const navBottom = document.querySelector('[data-public-nav]').getBoundingClientRect().bottom;
                const top = section.getBoundingClientRect().top;
                if (Math.abs(top - navBottom) > 2 && Math.ceil(window.scrollY + vh) < document.documentElement.scrollHeight) problems.push(`#${id} starts at ${top}, nav ends at ${navBottom}`);
                const heading = section.querySelector('h1, h2').getBoundingClientRect();
                if (heading.top < navBottom) problems.push(`#${id} heading under the nav`);
            }
            window.scrollTo({ top: 0, behavior: 'instant' });
            const small = [...document.querySelectorAll('button, [role="tab"], .lp-btn-primary, .lp-btn-neutral, .lp-icon-btn')]
                .filter((el) => el.offsetParent !== null && !el.closest('.leaflet-container'))
                .filter((el) => el.getBoundingClientRect().height < 44)
                .map((el) => `${el.textContent.trim().slice(0, 25) || el.getAttribute('aria-label')} (${el.getBoundingClientRect().height}px)`);
            if (small.length) problems.push(`buttons under 44px: ${small.join(', ')}`);
            const offscreen = [...document.querySelectorAll('a, button, input, select, textarea')]
                .filter((el) => el.offsetParent !== null && el.getAttribute('aria-hidden') !== 'true' && !el.closest('.leaflet-container'))
                .filter((el) => { const r = el.getBoundingClientRect(); return r.right > window.innerWidth + 1 || r.left < -1; })
                .map((el) => el.outerHTML.slice(0, 60));
            if (offscreen.length) problems.push(`off screen: ${offscreen.join(', ')}`);
            return problems;
        }, [SECTIONS, NAV_HEIGHT]);
        expect(report, `${width}x${height}`).toEqual([]);
    }
});

test('G4 keyboard focus reaches the controls and is always visible', async ({ page }, testInfo) => {
    await openLanding(page);
    const seen = new Set();
    for (let i = 0; i < 70; i += 1) {
        await page.keyboard.press('Tab');
        const focus = await page.evaluate(async () => {
            const el = document.activeElement;
            if (!el || el === document.body) return null;
            if (el.tagName === 'IFRAME') await new Promise((resolve) => setTimeout(resolve, 50)); // the ring is added a tick after the page loses focus
            // An iframe cannot show its own focus style; the map box around it shows the ring instead.
            const style = getComputedStyle(el.tagName === 'IFRAME' ? el.closest('[data-office-map-box]') ?? el : el);
            const visible = (style.outlineStyle !== 'none' && parseFloat(style.outlineWidth) > 0) || style.boxShadow !== 'none';
            return { key: el.id || el.getAttribute('href') || el.getAttribute('name') || el.textContent.trim().slice(0, 30), visible, tag: el.tagName };
        });
        if (!focus) continue;
        expect(focus.visible, `no visible focus style on ${focus.tag} ${focus.key}`).toBe(true);
        seen.add(focus.key);
    }
    for (const expected of ['/login', 'login-email', 'login-password', 'role-tab-farmers', 'faq-q-0']) {
        if (isMobile(testInfo) && expected === '/login') continue;
        expect([...seen].some((key) => key === expected || key.endsWith(expected)), `${expected} is keyboard reachable`).toBe(true);
    }
});

// ─── SCROLL ANIMATIONS ───────────────────────────────────────────────────

test.describe('scroll reveal with motion allowed', () => {
    test.use({ reducedMotion: 'no-preference' });

    test('M1 the Features cards fade in when scrolled to, once, and the page never scrolls sideways', async ({ page }, testInfo) => {
        test.skip(isMobile(testInfo), 'one size is enough for the motion');
        await page.goto('/');
        await expect(page.locator('html')).toHaveClass(/js-reveal/);
        await expect(page.locator('html')).toHaveAttribute('data-reveal', 'ready');
        expect(await page.evaluate(() => getComputedStyle(document.documentElement).scrollBehavior)).toBe('smooth');
        // The hero is not animated: its heading is there on load, without any scrolling.
        await expect(page.locator('#hero h1')).toBeVisible();
        expect(await page.locator('#hero h1').getAttribute('data-reveal')).toBeNull();
        expect(await page.locator('#hero h1').evaluate((el) => getComputedStyle(el).opacity)).toBe('1');
        await expect.poll(() => page.locator('[data-auth-card]').evaluate((el) => getComputedStyle(el).opacity)).toBe('1');

        const cards = page.locator('#features [data-reveal-group] [data-reveal]');
        expect(await cards.count()).toBe(6);
        // Below the fold the cards wait, hidden, even after the 2s safety net.
        await page.waitForTimeout(2200);
        await expect(cards.first()).not.toHaveClass(/is-visible/);
        expect(await cards.first().evaluate((el) => getComputedStyle(el).opacity)).toBe('0');

        // Scrolling (not a link) brings them in, one after another.
        await page.evaluate(() => window.scrollTo({ top: document.getElementById('features').offsetTop - 73, behavior: 'instant' }));
        for (let i = 0; i < 6; i += 1) await expect(cards.nth(i)).toHaveClass(/is-visible/);
        const delays = await cards.evaluateAll((els) => els.map((el) => el.style.getPropertyValue('--reveal-delay')));
        expect(delays).toEqual(['0ms', '70ms', '140ms', '210ms', '280ms', '350ms']);
        await expect.poll(() => cards.evaluateAll((els) => els.map((el) => getComputedStyle(el).opacity)), { timeout: 3000 })
            .toEqual(['1', '1', '1', '1', '1', '1']);
        await expect.poll(() => cards.last().evaluate((el) => getComputedStyle(el).transform)).toBe('none');

        // The nav gained its hairline shadow once scrolled.
        await expect(page.locator('[data-public-nav]')).toHaveClass(/is-scrolled/);
        expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(0);
    });

    test('M2 a nav link shows its section straight away; How it works draws its lines', async ({ page }, testInfo) => {
        test.skip(isMobile(testInfo), 'desktop nav');
        await page.goto('/');
        await navLink(page, 'roles', testInfo).click();
        // Shown at once (no transition), not after the scroll.
        await expect(page.locator('#roles h2')).toHaveClass(/reveal-now/);
        expect(await page.locator('#roles h2').evaluate((el) => getComputedStyle(el).opacity)).toBe('1');

        await page.evaluate(() => window.scrollTo({ top: document.getElementById('how').offsetTop - 73, behavior: 'instant' }));
        const line = page.locator('#how [data-reveal-line]').first();
        await expect.poll(() => line.evaluate((el) => getComputedStyle(el).transform), { timeout: 3000 }).toBe('matrix(1, 0, 0, 1, 0, 0)');
        // Nothing inside the hero card or the map is ever marked for animation.
        expect(await page.locator('[data-auth-card] [data-reveal], [data-office-map-box][data-reveal], [data-office-map-box] [data-reveal], #contactForm [data-reveal]').count()).toBe(0);
    });
});

test('M3 with reduced motion every [data-reveal] item is visible on load', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('html')).not.toHaveClass(/js-reveal/);
    const hidden = await page.locator('[data-reveal], [data-reveal-line]').evaluateAll((els) => els
        .filter((el) => el.offsetParent !== null || el.closest('[hidden]') === null)
        .filter((el) => { const cs = getComputedStyle(el); return cs.opacity !== '1' || cs.transform !== 'none'; })
        .map((el) => el.outerHTML.slice(0, 80)));
    expect(hidden).toEqual([]);
    expect(await page.locator('[data-reveal]').count()).toBeGreaterThan(20);
});

test('L1 the MAO logo (the Lian seal) in the nav and footer loads, square and unstretched, beside the iClimate logo', async ({ page }) => {
    await openLanding(page);
    const navLogo = page.locator('[data-nav-home] img[src$="images/mao-lian-seal.png"]');
    await expect(navLogo).toHaveAttribute('alt', 'Municipal Agriculture Office logo');
    await expect.poll(() => navLogo.evaluate((img) => img.complete && img.naturalWidth)).toBe(512);
    const sizes = await page.locator('[data-nav-home] img').evaluateAll((imgs) => imgs.map((img) => {
        const box = img.getBoundingClientRect();
        return { src: img.getAttribute('src').split('/').pop(), width: Math.round(box.width), height: Math.round(box.height), fit: getComputedStyle(img).objectFit, radius: getComputedStyle(img).borderRadius };
    }));
    const mao = sizes.find((img) => img.src === 'mao-lian-seal.png');
    const iclimate = sizes.find((img) => img.src === 'iclimate-logo.png');
    expect(mao.width).toBe(mao.height);
    expect(mao.height).toBe(iclimate.height);
    expect(mao.fit).toBe('contain');
    expect(mao.radius).toBe('0px');
    const footerLogo = page.locator('[data-footer-home] img[src$="images/mao-lian-seal.png"]');
    await footerLogo.scrollIntoViewIfNeeded();
    await expect.poll(() => footerLogo.evaluate((img) => img.complete && img.naturalWidth)).toBeGreaterThan(0);
    // The seal replaced the old MAO logo everywhere on the page.
    expect(await page.locator('img[src*="mao-logo.png"]').count()).toBe(0);
});

// ─── NAV: WEATHER CHIP AND TEXT SIZE ─────────────────────────────────────

const WEATHER = {
    has_real_weather: true, temperature: 27.4, humidity: 81, wind_speed: 12.3, rainfall: 3.24,
    condition_label: 'Thunderstorm', condition_emoji: '⛈️', updated_at: '2026-10-18T09:05:00+08:00', updated_label: 'Updated 9:05 AM',
};
const mockWeather = (page, body = WEATHER, status = 200) => page.route('**/api/weather/current', (route) => route.fulfill({ status, contentType: 'application/json', body: JSON.stringify(body) }));

test('W1 the weather chip shows the live temperature and condition icon', async ({ page }, testInfo) => {
    let requests = 0;
    page.on('request', (request) => { if (request.url().includes('/api/weather/current')) requests += 1; });
    await mockWeather(page);
    await openLanding(page);
    const chip = page.locator('[data-weather-chip]');
    await expect(chip).toHaveAttribute('data-state', 'ready');
    await expect(chip).toHaveAttribute('data-icon', 'cloud-lightning');
    await expect(chip.locator('[data-weather-icon="cloud-lightning"]')).toBeVisible();
    await expect(chip.locator('[data-weather-icon="sun"]')).toBeHidden();
    await expect(chip).toHaveAttribute('aria-label', 'Current weather in Lian: 27°C, thunderstorm');
    expect((await chip.innerText()).trim()).toBe(isMobile(testInfo) ? '27°C' : '27°C · Lian'); // what is shown
    const box = await chip.boundingBox();
    expect(box.height).toBeGreaterThanOrEqual(44);
    // The Weather analysis card used the same answer: one request for both.
    await expect(page.locator('[data-weather-temp]')).toHaveText('27°C');
    expect(requests).toBe(1);
});

test('W2 the weather panel opens and closes with click, outside click and Esc', async ({ page }) => {
    await mockWeather(page);
    await openLanding(page);
    const chip = page.locator('[data-weather-chip]');
    const panel = page.locator('#weatherPanel');
    await expect(chip).toHaveAttribute('data-state', 'ready');

    await chip.click();
    await expect(panel).toBeVisible();
    await expect(chip).toHaveAttribute('aria-expanded', 'true');
    await expect(panel).toBeFocused();
    await expect(panel.locator('[data-weather-panel-condition]')).toHaveText('Thunderstorm');
    await expect(panel.locator('[data-weather-panel-temp]')).toHaveText('27°C');
    await expect(panel.locator('[data-weather-panel-humidity]')).toHaveText('81%');
    await expect(panel.locator('[data-weather-panel-wind]')).toHaveText('12.3 km/h');
    await expect(panel.locator('[data-weather-panel-rain]')).toHaveText('3.2 mm');
    await expect(panel.locator('[data-weather-panel-updated]')).toHaveText('Updated 9:05 AM');
    // Guests are sent to the login card, so no page is remembered for after the login.
    await expect(panel.locator('[data-weather-heat-map]')).toHaveAttribute('href', '#login');
    await expect(panel.locator('[data-weather-heat-map]')).toHaveText('Log in to view the heat map');

    await page.mouse.click(5, 400); // outside
    await expect(panel).toBeHidden();
    await expect(chip).toHaveAttribute('aria-expanded', 'false');

    await chip.click();
    await expect(panel).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(panel).toBeHidden();
    await expect(chip).toBeFocused();

    await chip.click();
    await chip.click(); // the chip toggles it closed too
    await expect(panel).toBeHidden();

    // "Log in to view the heat map" opens the login card on this page.
    await page.getByRole('tab', { name: 'Create account' }).click();
    await chip.click();
    await panel.locator('[data-weather-heat-map]').click();
    expect(new URL(page.url()).pathname).toBe('/');
    await expect(page.locator('[data-auth-card]')).toHaveAttribute('data-auth-mode', 'login');
    await expect(page.locator('#login-email')).toBeFocused();
});

test('W3 the chip hides when the weather endpoint fails, and when it times out', async ({ page }) => {
    expectedConsoleErrors.push(/status of 500/); // the failure this test makes on purpose
    await mockWeather(page, { message: 'Server Error' }, 500);
    await openLanding(page);
    await expect(page.locator('[data-weather-chip-wrap]')).toBeHidden();
    await expect(page.locator('header')).not.toContainText('Error');

    await page.unroute('**/api/weather/current');
    await page.route('**/api/weather/current', () => { /* never answers */ });
    await openLanding(page);
    const wrap = page.locator('[data-weather-chip-wrap]');
    await expect(page.locator('[data-weather-chip]')).toHaveAttribute('data-state', 'loading');
    await expect(wrap).toBeHidden({ timeout: 8000 });
});

test('W4 a reading with no live weather hides the chip; the skeleton keeps its size while loading', async ({ page }) => {
    let release;
    await page.route('**/api/weather/current', (route) => new Promise((resolve) => { release = () => resolve(route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ has_real_weather: false }) })); }));
    await openLanding(page);
    const chip = page.locator('[data-weather-chip]');
    const loadingBox = await chip.boundingBox();
    expect(loadingBox.width).toBeGreaterThanOrEqual(88);
    expect(loadingBox.height).toBeGreaterThanOrEqual(44);
    release();
    await expect(page.locator('[data-weather-chip-wrap]')).toBeHidden();
});

test('X2 the nav at 375 and 1280px: no sideways scroll, nothing cut off or under the nav, controls at least 44px, no text size button', async ({ page }, testInfo) => {
    test.skip(isMobile(testInfo), 'sets its own sizes');
    await mockWeather(page);
    {
        for (const [width, height] of [[375, 667], [1280, 800]]) {
            await page.setViewportSize({ width, height });
            await page.goto('/');
            await expect(page.locator('[data-text-size-toggle]')).toHaveCount(0);
            await expect(page.locator('[data-weather-chip]')).toHaveAttribute('data-state', 'ready');
            const problems = await page.evaluate(() => {
                const out = [];
                if (document.documentElement.scrollWidth > document.documentElement.clientWidth) out.push('horizontal scroll');
                const nav = document.querySelector('[data-public-nav]');
                const navRow = nav.firstElementChild.getBoundingClientRect();
                if (navRow.height > parseFloat(getComputedStyle(nav).height) + 1) out.push('nav row wraps');
                // Nothing in the nav row overlaps the next item.
                const rowItems = [...nav.querySelectorAll('[data-nav-home] img, [data-weather-chip], [data-nav-toggle]')].filter((el) => el.offsetParent !== null);
                for (let i = 1; i < rowItems.length; i += 1) {
                    if (rowItems[i].getBoundingClientRect().left < rowItems[i - 1].getBoundingClientRect().right - 0.5) out.push(`nav items overlap at ${i}`);
                }
                for (const el of nav.querySelectorAll('[data-weather-chip], [data-nav-toggle], [data-nav-home]')) {
                    const r = el.getBoundingClientRect();
                    if (el.offsetParent !== null && (r.right > window.innerWidth + 1 || r.left < -1)) out.push(`${el.dataset ? Object.keys(el.dataset)[0] : el.tagName} off screen`);
                }
                for (const id of ['hero', 'about', 'features', 'how', 'roles', 'faq', 'contact']) {
                    const section = document.getElementById(id);
                    if (section.scrollHeight > section.clientHeight + 1) out.push(`#${id} content overflows`);
                    section.scrollIntoView({ behavior: 'instant' });
                    const navBottom = nav.getBoundingClientRect().bottom;
                    const top = section.getBoundingClientRect().top;
                    if (Math.abs(top - navBottom) > 2 && Math.ceil(window.scrollY + window.innerHeight) < document.documentElement.scrollHeight) out.push(`#${id} starts at ${top}, nav ends at ${navBottom}`);
                }
                window.scrollTo({ top: 0, behavior: 'instant' });
                const small = [...document.querySelectorAll('button, [role="tab"], .lp-btn-primary, .lp-btn-neutral, .lp-icon-btn')]
                    .filter((el) => el.offsetParent !== null && el.getBoundingClientRect().height < 44)
                    .map((el) => el.textContent.trim().slice(0, 20) || el.getAttribute('aria-label'));
                if (small.length) out.push(`under 44px: ${small.join(', ')}`);
                // The office name is never cut off.
                for (const span of nav.querySelectorAll('[data-nav-home] span span')) {
                    if (span.offsetParent !== null && span.scrollWidth > span.clientWidth + 1) out.push(`"${span.textContent.trim()}" is cut off`);
                }
                return out;
            });
            // The weather panel opens fully on screen.
            await page.locator('[data-weather-chip]').click();
            const panel = await page.locator('#weatherPanel').boundingBox();
            expect(panel.x, `${width}px panel left`).toBeGreaterThanOrEqual(0);
            expect(panel.x + panel.width, `${width}px panel right`).toBeLessThanOrEqual(width);
            await page.keyboard.press('Escape');
            expect(problems, `${width}px`).toEqual([]);
        }
    }
});
