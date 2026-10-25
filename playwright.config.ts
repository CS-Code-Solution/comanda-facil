import { defineConfig } from '@playwright/test';

// Requires a dedicated, migrated pilot fixture. Never starts or resets a database.
export default defineConfig({
    testDir: './tests/Browser',
    workers: 1,
    retries: 0,
    timeout: 120_000,
    expect: { timeout: 10_000 },
    reporter: 'list',
    use: {
        baseURL: process.env.PILOT_URL ?? 'http://127.0.0.1:18096',
        headless: true,
        actionTimeout: 15_000,
        viewport: { width: 390, height: 844 },
        trace: 'off',
        screenshot: 'off',
        video: 'off',
        launchOptions: process.env.PILOT_BROWSER
            ? { executablePath: process.env.PILOT_BROWSER }
            : {},
    },
});
