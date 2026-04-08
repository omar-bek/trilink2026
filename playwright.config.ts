import { defineConfig, devices } from '@playwright/test';

/**
 * TriLink end-to-end smoke tests.
 *
 * Runs against the local `php artisan serve` instance on :8000. Login is
 * driven through the seeded users from ComprehensiveSeeder (all share the
 * password "password").
 */
export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: 0,
    workers: 1,
    reporter: [['list'], ['html', { open: 'never', outputFolder: 'tests/e2e/playwright-report' }]],
    use: {
        baseURL: process.env.E2E_BASE_URL || 'http://127.0.0.1:8000',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'off',
        ignoreHTTPSErrors: true,
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});
