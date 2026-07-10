import { test, expect, Page } from '@playwright/test';

const EMAIL = 'admin@academy.test';
const PASSWORD = 'password';

async function loginToAdmin(page: Page): Promise<void> {
    await page.goto('/admin/login');
    await page.getByLabel(/email/i).fill(EMAIL);
    await page.getByLabel(/password/i).fill(PASSWORD);
    await page.getByRole('button', { name: /sign in/i }).click();
    await expect(page).toHaveURL(/\/admin$/);
}

test('admin can log in and the analytics dashboard loads its widgets', async ({ page }) => {
    await loginToAdmin(page);

    // Lazy-loaded Filament widgets actually resolve in a real browser.
    await expect(page.getByText('Complexity').first()).toBeVisible();
    await expect(page.getByText(/Coverage/i).first()).toBeVisible();
});

test('admin pages list loads with rows', async ({ page }) => {
    await loginToAdmin(page);

    await page.goto('/admin/pages');
    await expect(page.getByRole('heading', { name: 'Pages' })).toBeVisible();
    await expect(page.locator('table tbody tr').first()).toBeVisible();
});

test('authenticated nav links to library and admin', async ({ page }) => {
    // Log in via the learner (Breeze) door, which lands on /dashboard.
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
