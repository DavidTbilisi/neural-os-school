import { defineConfig } from '@playwright/test';

/**
 * Browser smoke tests. Runs on host Node against the app served by the podman
 * container on http://localhost:8000. If no server is running, Playwright starts
 * one via ./run (and tears it down); if one is already up, it reuses it.
 *
 *   npm run e2e
 *   E2E_PORT=8010 npm run e2e   # when something else already holds :8000
 *
 * Set E2E_PORT whenever another local service owns the default port —
 * reuseExistingServer would otherwise happily run the whole suite against it.
 */
const PORT = process.env.E2E_PORT ?? '8000';

export default defineConfig({
    testDir: './e2e',
    // Clears the login rate limiter; see e2e/global-setup.ts for why.
    globalSetup: './e2e/global-setup.ts',
    timeout: 30_000,
    expect: { timeout: 10_000 },
    fullyParallel: true,
    retries: 0,
    reporter: [['list']],
    use: {
        // 127.0.0.1 (not localhost) so the reuse-probe hits pasta's IPv4 listener
        // instead of IPv6 ::1, which would miss the running container.
        baseURL: `http://127.0.0.1:${PORT}`,
        headless: true,
        trace: 'on-first-retry',
    },
    projects: [
        { name: 'chromium', use: { browserName: 'chromium' } },
    ],
    webServer: {
        command: `./run php artisan serve --host=0.0.0.0 --port=${PORT}`,
        url: `http://127.0.0.1:${PORT}`,
        reuseExistingServer: true,
        timeout: 120_000,
    },
});
