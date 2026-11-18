import { defineConfig } from '@playwright/test';
const port = Number(process.env.PLAYWRIGHT_PORT || 8123);
const baseURL = 'http://127.0.0.1:' + port;

// Browser tests for the public landing page. `npm run test:browser` starts the site on a throwaway SQLite
// database (tests/Browser/serve.sh); run `npm run build` first so the page has its assets.
// On macOS run it as `caffeinate -dimsu npm run test:browser` when the machine may idle: an idle Mac freezes the
// headless browser for minutes at a time, which shows up as random scroll and timeout failures.
export default defineConfig({
    testDir: 'tests/Browser',
    fullyParallel: false,
    workers: 1,
    timeout: 30_000,
    reporter: 'list',
    use: {
        baseURL,
        trace: 'retain-on-failure',
        // The scroll animations are switched off for the suite so timing never makes a test flaky; the reveal tests in
        // tests/Browser/landing.spec.mjs turn them back on where they check the motion itself.
        reducedMotion: 'reduce',
    },
    projects: [
        { name: 'desktop-1280', use: { browserName: 'chromium', viewport: { width: 1280, height: 800 } } },
        { name: 'mobile-375', use: { browserName: 'chromium', viewport: { width: 375, height: 740 }, isMobile: true, hasTouch: true } },
    ],
    webServer: {
        command: 'PLAYWRIGHT_PORT=' + port + ' sh tests/Browser/serve.sh',
        url: baseURL + '/login',
        timeout: 180_000,
        reuseExistingServer: false,
    },
});
