import { test, expect, Page } from '@playwright/test';

/**
 * Per-learner navigation: the bar the profile form saves is the bar every
 * shell renders. Runs serially against the shared dev user, and puts the
 * arrangement back with Reset so the other specs see the default bar.
 */
const EMAIL = 'admin@academy.test';
const PASSWORD = 'password';

test.describe.configure({ mode: 'serial' });

async function login(page: Page): Promise<void> {
    await page.goto('/login');
    await page.getByLabel(/email/i).fill(EMAIL);
    await page.getByLabel(/password/i).fill(PASSWORD);
    await page.getByRole('button', { name: /log in/i }).click();
    await expect(page).toHaveURL(/\/dashboard/);
}

/** The link labels in the top bar, left to right. */
async function barLabels(page: Page): Promise<string[]> {
    return page.locator('header nav').first().locator('a').allTextContents();
}

test('a learner reorders and hides their own navigation', async ({ page }) => {
    await login(page);
    await page.goto('/profile');

    const navSection = page.locator('section', { has: page.getByRole('heading', { name: 'Navigation' }) });
    await expect(navSection).toBeVisible();

    // Start from the defaults — the shared dev user may carry an arrangement
    // from an earlier run, and the moves below assume a known starting order.
    await navSection.getByRole('button', { name: 'Reset' }).click();
    await expect(navSection.getByText('Saved.')).toBeVisible();

    // Dashboard is locked — it may move, but it has no Show switch.
    const dashboardRow = navSection.locator('li', { hasText: 'Dashboard' });
    await expect(dashboardRow.getByText('always shown')).toBeVisible();
    await expect(dashboardRow.locator('input[type=checkbox]')).toHaveCount(0);

    // Push Gyms above Library, and switch Courses off.
    const gymsRow = navSection.locator('li', { hasText: 'Gyms' });
    await gymsRow.getByRole('button', { name: /move gyms earlier/i }).click();
    await gymsRow.getByRole('button', { name: /move gyms earlier/i }).click();
    await navSection.locator('li', { hasText: 'Courses' }).first()
        .locator('input[type=checkbox]').uncheck();

    await navSection.getByRole('button', { name: 'Save' }).click();
    await expect(navSection.getByText('Saved.')).toBeVisible();

    // The dashboard bar repaints without a reload — a second Livewire round
    // trip after the save, so this has to be an auto-retrying assertion.
    const dashboardBar = page.locator('nav').first();
    await expect(dashboardBar.getByRole('link', { name: 'Courses', exact: true })).toHaveCount(0);
    await expect(dashboardBar.getByRole('link', { name: 'Gyms', exact: true })).toBeVisible();

    // ...and the public bar agrees on the next page load.
    await page.goto('/library');
    const labels = (await barLabels(page)).map((l) => l.trim());
    expect(labels).not.toContain('Courses');
    expect(labels.indexOf('Gyms')).toBeLessThan(labels.indexOf('Library'));
    await page.screenshot({ path: 'storage/app/e2e-nav-custom.png', clip: { x: 0, y: 0, width: 1280, height: 120 } });
});

test('reset puts the default bar back', async ({ page }) => {
    await login(page);
    await page.goto('/profile');

    const navSection = page.locator('section', { has: page.getByRole('heading', { name: 'Navigation' }) });
    await navSection.getByRole('button', { name: 'Reset' }).click();
    await expect(navSection.getByText('Saved.')).toBeVisible();

    await page.goto('/library');
    const labels = (await barLabels(page)).map((l) => l.trim());
    expect(labels.slice(0, 3)).toEqual(['Library', 'Courses', 'Gyms']);
    await page.screenshot({ path: 'storage/app/e2e-nav-default.png', clip: { x: 0, y: 0, width: 1280, height: 120 } });
});
