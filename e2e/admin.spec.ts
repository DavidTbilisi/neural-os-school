import { test, expect, BrowserContext, Page } from '@playwright/test';

/**
 * Serial, and the two Filament tests share ONE signed-in context.
 *
 * Filament's login counts successful sign-ins against a 5-per-minute limiter
 * and never clears them (unlike the learner door — see e2e/global-setup.ts).
 * Logging in per test burned two of those five on every suite run, so a second
 * or third run inside the same minute was refused and the browser sat on
 * /admin/login. One login per file, plus the limiter reset in global setup.
 */
test.describe.configure({ mode: 'serial' });

const EMAIL = 'admin@academy.test';
const PASSWORD = 'password';

let context: BrowserContext;
let admin: Page;

test.beforeAll(async ({ browser }) => {
    context = await browser.newContext();
    admin = await context.newPage();

    await admin.goto('/admin/login');
    await admin.getByLabel(/email/i).fill(EMAIL);
    await admin.getByLabel(/password/i).fill(PASSWORD);
    await admin.getByRole('button', { name: /sign in/i }).click();
    await expect(admin, 'admin sign-in should land on the panel').toHaveURL(/\/admin$/);
});

test.afterAll(async () => {
    await context?.close();
});

test('admin can log in and the analytics dashboard loads its widgets', async () => {
    await admin.goto('/admin');

    // Lazy-loaded Filament widgets actually resolve in a real browser.
    await expect(admin.getByText('Complexity').first()).toBeVisible();
    await expect(admin.getByText(/Coverage/i).first()).toBeVisible();
});

test('admin pages list loads with rows', async () => {
    await admin.goto('/admin/pages');
    await expect(admin.getByRole('heading', { name: 'Pages' })).toBeVisible();
    await expect(admin.locator('table tbody tr').first()).toBeVisible();
});

test('authenticated nav links to library and admin', async ({ page }) => {
    // The learner (Breeze) door, which lands on /dashboard. Its limiter clears
    // on success, so this login is safe to repeat per run.
    await page.goto('/login');
    await page.getByLabel(/email/i).fill(EMAIL);
    await page.getByLabel(/password/i).fill(PASSWORD);
    await page.getByRole('button', { name: /log in/i }).click();
    await expect(page).toHaveURL(/\/dashboard/);

    // The nav now exposes Library + Admin (staff), instead of a dead-end page.
    await expect(page.locator('a[href$="/library"]').first()).toBeVisible();
    await expect(page.locator('a[href$="/admin"]').first()).toBeVisible();

    await page.locator('a[href$="/library"]').first().click();
    await expect(page).toHaveURL(/\/library/);
});
