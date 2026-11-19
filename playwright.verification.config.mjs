import base from './playwright.config.mjs';
export default {
    ...base,
    testMatch: ['verification.spec.mjs', 'critical-workflows.spec.mjs'],
    projects: [
        ...base.projects,
        { name: 'tablet-768', use: { browserName: 'chromium', viewport: { width: 768, height: 1024 }, hasTouch: true } },
    ],
};
