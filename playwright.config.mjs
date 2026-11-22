import { defineConfig } from '@playwright/test';
const port = Number(process.env.PLAYWRIGHT_PORT || 8123);
const baseURL = 'http://127.0.0.1:' + port;

// Browser tests for the public landing page. `npm run test:browser` starts the site on a throwaway SQLite
// database (tests/Browser/serve.sh); run `npm run build` first so the page has its assets.
// On macOS run it as `caffeinate -dimsu npm run test:browser` when the machine may idle: an idle Mac freezes the
// headless browser for minutes at a time, which shows up as random scroll and timeout failures.
export default defineConfig({
    testDir: 'tests/Browser',
    outputDir: `test-results/port-${port}`,
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
        // The port goes in through `env`, not a `VAR=value command` prefix, so the command also starts from Windows
        // (Git Bash's bash and php on PATH) as well as macOS/Linux (audit 2026-11-23, L-3).
        command: 'bash tests/Browser/serve.sh',
        env: { PLAYWRIGHT_PORT: String(port) },
        url: baseURL + '/login',
        timeout: 180_000,
        reuseExistingServer: false,
    },
});
