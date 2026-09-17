import { defineConfig } from '@playwright/test';
export default defineConfig({
    testDir: '.',
    testMatch: '*.spec.ts',
    use: { baseURL: 'http://127.0.0.1:8766', viewport: { width: 1440, height: 1000 } },
    webServer: {
        command: 'python3 -m http.server 8766 --bind 127.0.0.1 --directory extensions/obsidian',
        cwd: '../../..',
        url: 'http://127.0.0.1:8766/preview/',
        reuseExistingServer: false,
    },
});
