import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/E2E/browser',
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    workers: 1,
    reporter: [['list']],
    use: {
        baseURL: process.env.PLAYWRIGHT_BASE_URL ?? process.env.APP_URL ?? 'http://127.0.0.1:8080',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
        launchOptions: {
            args: ['--no-sandbox', '--disable-dev-shm-usage'],
        },
    },
});
