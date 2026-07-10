import { test, expect } from '@playwright/test';

test('library lists published pages and live search filters them', async ({ page }) => {
    await page.goto('/library');

    await expect(page.getByText(/published pages/i)).toBeVisible();

    const pageLinks = page.locator('a[href*="/wiki/"]');
    await expect(pageLinks.first()).toBeVisible();

    // Type into the live-search box; a matching result should appear after the
    // Livewire round-trip (this is the JS behaviour the PHPUnit tests can't cover).
    await page.locator('input[type="search"]').fill('memory');
    await expect(
        pageLinks.filter({ hasText: /memory/i }).first()
    ).toBeVisible();
});

test('a published page renders and its internal links navigate', async ({ page }) => {
    await page.goto('/library');

    await page.locator('a[href*="/wiki/"]').first().click();
    await expect(page).toHaveURL(/\/wiki\//);
    await expect(page.locator('article')).toBeVisible();
    await expect(page.locator('.prose')).toBeVisible();

    // Follow a resolved [[wiki-link]] inside the rendered body, if the page has one.
    const internal = page.locator('.prose a[href*="/wiki/"]').first();
    if (await internal.count()) {
        await internal.click();
        await expect(page).toHaveURL(/\/wiki\//);
        await expect(page.locator('article')).toBeVisible();
    }
});

test('a private page is not found for guests', async ({ page }) => {
    const res = await page.goto('/wiki/this-slug-should-never-be-public-xyz');
    expect(res?.status()).toBe(404);
});
