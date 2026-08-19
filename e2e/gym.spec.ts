import { test, expect, Page } from '@playwright/test';

/**
 * The blind-spot floor, end to end in a real browser.
 *
 * Both tests play the seeded Algorithm Pattern Gym to 18/20 = 90% at ~0.1s per
 * answer — accurate and fast, which the accuracy+latency bands alone read as
 * L7 Reflexive. The only difference is WHERE the two misses land: concentrated
 * on one pattern family (floored) vs. spread across two (promoted).
 */

// Serial: both tests drive the same dev server and sqlite file as the same user.
test.describe.configure({ mode: 'serial' });

const EMAIL = 'admin@academy.test';
const PASSWORD = 'password';

/** The seeded deck stores each item's correct answer FIRST in its choices. */
const CORRECT_CHOICE = 0;
const WRONG_CHOICE = 1;

/** The two Monotonic Stack prompts in the seeded deck — the family to zero. */
const ZERO_THIS_FAMILY = [
    /how many days until a WARMER day/i,
    /Largest rectangle area in a histogram/i,
];

/**
 * Families with ≥2 items in the seeded deck. A miss is only safe to spread onto
 * one of these — missing a single-item family would zero it, which is the very
 * thing the control test must avoid.
 */
const MULTI_ITEM_FAMILIES = ['Hashmap', 'Two Pointers', 'Binary Search', 'BFS', 'Heap', 'DP 1D', 'DFS'];

async function login(page: Page): Promise<void> {
    await page.goto('/login');
    await page.getByLabel(/email/i).fill(EMAIL);
    await page.getByLabel(/password/i).fill(PASSWORD);
    await page.getByRole('button', { name: /log in/i }).click();
    await expect(page).toHaveURL(/\/dashboard/);
}

async function startGym(page: Page): Promise<void> {
    await page.goto('/gyms/algorithm-pattern-gym');
    await page.getByRole('button', { name: /start session/i }).click();
}

test('the blind-spot floor withholds the top rung and names the family', async ({ page }) => {
    await login(page);
    await page.goto('/gyms/algorithm-pattern-gym');

    // The rule is stated before the run, not sprung after it.
    await expect(page.getByText('no zeroed category')).toBeVisible();

    await page.getByRole('button', { name: /start session/i }).click();

    for (let round = 1; round <= 20; round++) {
        await expect(page.getByText(`Round ${round} / 20`)).toBeVisible();

        const prompt = await page.locator('p.text-lg').first().innerText();
        const miss = ZERO_THIS_FAMILY.some((re) => re.test(prompt));

        const choices = page.locator('[data-choice]');
        await expect(choices.first()).toBeVisible();
        await choices.nth(miss ? WRONG_CHOICE : CORRECT_CHOICE).click();

        await page.getByRole('button', { name: /next round|see summary/i }).click();
    }

    await expect(page.getByText('90%')).toBeVisible();
    await expect(page.getByRole('heading', { name: /blind spot found/i })).toBeVisible();

    // The panel names the family, the evidence, and what the hole cost.
    // (String matchers, not regexes — only string matching normalizes the
    // newlines Blade leaves inside a wrapped sentence.)
    await expect(page.getByText('nothing correct in this category')).toBeVisible();
    await expect(page.getByText('Monotonic Stack').first()).toBeVisible();
    await expect(page.getByText('0 of 2 items in this run')).toBeVisible();
    await expect(page.getByText('L7 · Reflexive')).toBeVisible();
    await expect(page.getByText('withheld to')).toBeVisible();
    await expect(page.getByText('L4', { exact: true }).first()).toBeVisible();

    await page.screenshot({ path: 'test-results/blind-spot-light.png', fullPage: true });
    await page.evaluate(() => document.documentElement.classList.add('dark'));
    await page.screenshot({ path: 'test-results/blind-spot-dark.png', fullPage: true });
});

test('the same accuracy spread across families still reaches the top rung', async ({ page }) => {
    await login(page);
    await startGym(page);

    let missesLeft = 2;
    const missed = new Set<string>();

    for (let round = 1; round <= 20; round++) {
        await expect(page.getByText(`Round ${round} / 20`)).toBeVisible();

        const choices = page.locator('[data-choice]');
        await expect(choices.first()).toBeVisible();
        const family = (await choices.nth(CORRECT_CHOICE).innerText()).trim();

        // At most one miss per family, and only in families with a spare item.
        const miss = missesLeft > 0 && MULTI_ITEM_FAMILIES.includes(family) && !missed.has(family);
        if (miss) {
            missesLeft--;
            missed.add(family);
        }
        await choices.nth(miss ? WRONG_CHOICE : CORRECT_CHOICE).click();

        await page.getByRole('button', { name: /next round|see summary/i }).click();
    }

    expect(missesLeft, 'both misses should have landed').toBe(0);

    await expect(page.getByText('90%')).toBeVisible();
    await expect(page.getByRole('heading', { name: /reflex is stabilizing/i })).toBeVisible();
    await expect(page.getByText('L7', { exact: true }).first()).toBeVisible();
    await expect(page.getByText(/blind spot/i)).toHaveCount(0);
});
