import { defineConfig } from '@playwright/test';

/**
 * Browser smoke tests. Runs on host Node against the app served by the podman
 * container on http://localhost:8000. If no server is running, Playwright starts
 * one via ./run (and tears it down); if one is already up, it reuses it.
 *
 *   npm run e2e
 */
export default defineConfig({
    testDir: './e2e',
    timeout: 30_000,
    expect: { timeout: 10_000 },
    fullyParallel: true,
    retries: 0,
    reporter: [['list']],
    use: {
        // 127.0.0.1 (not localhost) so the reuse-probe hits pasta's IPv4 listener
        // instead of IPv6 ::1, which would miss the running container.
        baseURL: 'http://127.0.0.1:8000',
        headless: true,
        trace: 'on-first-retry',
    },
    projects: [
        { name: 'chromium', use: { browserName: 'chromium' } },
    ],
    webServer: {
        command: './run php artisan serve --host=0.0.0.0 --port=8000',
        url: 'http://127.0.0.1:8000',
        reuseExistingServer: true,
        timeout: 120_000,
    },
});
