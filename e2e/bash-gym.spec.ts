import { test, expect, Page } from '@playwright/test';

/**
 * The Bash Pattern Gym, played the way a learner plays it.
 *
 * Distinct from gym.spec.ts, which drives the Algorithm Pattern Gym and clicks
 * `[data-choice]` by INDEX (the seeded deck stores the correct answer first).
 * Index-clicking exercises the scoring machinery but proves nothing about the
 * deck: it would pass just as happily if every label were blank or if a prompt
 * and its answer had drifted apart.
 *
 * These tests answer from the prompt instead — read the shell line, decide the
 * defect family, click the choice with THAT LABEL. So they assert a content
 * contract as well as a mechanic:
 *
 *   - every prompt in the bank maps to exactly one family (no ambiguous items)
 *   - that family is actually among the four rendered choices
 *   - the family a competent reader picks is the one the seeder stored
 *
 * ANSWERS therefore duplicates BashPatternGymSeeder on purpose. When the seeder
 * changes, this fails with "unmapped prompt" or "answer not among choices" —
 * that is the test doing its job, not bit-rot. Update the map.
 */

// Serial: shares one dev server and sqlite file, as one user, with the other specs.
test.describe.configure({ mode: 'serial' });

const EMAIL = 'admin@academy.test';
const PASSWORD = 'password';

/** A distinctive fragment of each seeded prompt -> the family it belongs to. */
const ANSWERS: Array<[RegExp, string]> = [
    [/echo \{1\.\.\$n\}/,                        'Expansion order'],
    [/cmd='ls "my dir"'/,                        'Expansion order'],
    [/rm -rf \$tmpdir\/\*/,                      'Unquoted expansion'],
    [/cp \$src "\$dst"/,                         'Unquoted expansion'],
    [/if \[ -f \$file \]/,                       'Unquoted expansion'],
    [/trap "rm -rf \$tmpdir" EXIT/,              'Unquoted expansion'],
    [/while read line; do printf/,               'Word splitting'],
    [/for f in \$\(ls \*\.log\)/,                'Word splitting'],
    [/n=0; grep -c \. file \|/,                  'Subshell state loss'],
    [/cat urls\.txt \| while read/,              'Subshell state loss'],
    [/curl -s "\$url" \| tar xzf/,               'Exit status ignored'],
    [/local out=\$\(some_command\)/,             'Exit status ignored'],
    [/set -e; count=0; \(\( count\+\+ \)\)/,     'Exit status ignored'],
    [/make 2>&1 > build\.log/,                   'Redirection order'],
    [/sort -u data\.txt > data\.txt/,            'Redirection order'],
    [/grep "\*\.log" access\.log/,               'Glob vs regex'],
    [/case \$f in \+\(\[0-9\]\)\.txt/,           'Glob vs regex'],
    [/pat="\*\.txt"/,                            'Glob vs regex'],
    [/if \[\[ \$count > 9 \]\]/,                 'String vs numeric test'],
    [/month=\$\(date \+%m\)/,                    'String vs numeric test'],
    [/files=\(a\.txt "b c\.txt"\)/,              'Array vs string'],
    [/opts="--exclude/,                          'Array vs string'],
    [/find \. -name "\*\.tmp" \| xargs rm/,      'Filename safety'],
    [/find \/tmp -delete -name/,                 'Filename safety'],
    [/declare -A seen/,                          'Portability'],
    [/sed -i "s\/foo\/bar\/"/,                   'Portability'],
    [/out=\$\(cd "\$dir" && pwd\)/,              'Correct as written'],
    [/\(cd build && make\) && echo done/,        'Correct as written'],
    [/sudo tee -a \/etc\/hosts/,                 'Correct as written'],
    [/find \. -type f -print0 \| xargs -0 -r/,   'Correct as written'],
    [/printf .* "\$\{arr\[@\]\}"/,               'Correct as written'],
];

/**
 * Families with exactly two items in the seeded bank. The blind-spot test must
 * zero one of these: 20 of 31 items are drawn, so 11 at most are absent and at
 * least five of these sixteen always appear — the target is guaranteed. Zeroing
 * a bigger family (Unquoted expansion has four, Correct as written five) could
 * push accuracy under the 80% pass bar, which would lower the BAND and make the
 * test unable to tell a withheld rung from a simply-bad run.
 */
const TWO_ITEM_FAMILIES = [
    'Expansion order', 'Word splitting', 'Subshell state loss', 'Redirection order',
    'String vs numeric test', 'Array vs string', 'Filename safety', 'Portability',
];

/** The one family this prompt belongs to, or null if the map is ambiguous/missing. */
function decide(prompt: string): string | null {
    const hits = ANSWERS.filter(([re]) => re.test(prompt));
    return hits.length === 1 ? hits[0][1] : null;
}

async function login(page: Page): Promise<void> {
    await page.goto('/login');
    await page.getByLabel(/email/i).fill(EMAIL);
    await page.getByLabel(/password/i).fill(PASSWORD);
    await page.getByRole('button', { name: /log in/i }).click();
    await expect(page).toHaveURL(/\/dashboard/);
}

/** Read the round's prompt and its four choice labels. */
async function round(page: Page, n: number): Promise<{ prompt: string; labels: string[] }> {
    await expect(page.getByText(`Round ${n} / 20`)).toBeVisible();
    const prompt = (await page.locator('p.text-lg').first().innerText()).trim();
    const choices = page.locator('[data-choice]');
    await expect(choices.first()).toBeVisible();
    const labels = (await choices.allInnerTexts()).map((s) => s.trim());
    return { prompt, labels };
}

/** The last round's button reads "See summary"; wait for the summary to render. */
async function finish(page: Page): Promise<void> {
    await expect(page.getByRole('button', { name: /see summary/i })).toHaveCount(0);
    await expect(page.getByText(/\d+%/).first()).toBeVisible();
}

test('every drawn prompt is answerable from its own text, and a clean run reaches the top rung', async ({ page }) => {
    await login(page);
    await page.goto('/gyms/bash-pattern-gym');

    // The blind-spot rule is stated before the run, not sprung after it.
    await expect(page.getByText('no zeroed category')).toBeVisible();
    await page.getByRole('button', { name: /start session/i }).click();

    for (let n = 1; n <= 20; n++) {
        const { prompt, labels } = await round(page, n);

        const answer = decide(prompt);
        expect(answer, `no unique family mapped for prompt: ${prompt}`).not.toBeNull();
        expect(labels, `"${answer}" is not offered for: ${prompt}`).toContain(answer);

        await page.locator('[data-choice]').nth(labels.indexOf(answer!)).click();

        // The seeder's stored answer agrees with the one the prompt implies.
        await expect(page.getByText('✓ Correct')).toBeVisible();

        await page.getByRole('button', { name: /next round|see summary/i }).click();
    }

    await finish(page);
    await expect(page.getByText('100%')).toBeVisible();
    await expect(page.getByText('20/20 correct')).toBeVisible();
    await expect(page.getByText('L7')).toBeVisible();
    await expect(page.getByText(/blind spot/i)).toHaveCount(0);
});

test('zeroing one defect family withholds the rung and names the hole', async ({ page }) => {
    await login(page);
    await page.goto('/gyms/bash-pattern-gym');
    await page.getByRole('button', { name: /start session/i }).click();

    // Target the first two-item family drawn, then miss every item of it.
    let target: string | null = null;
    let missed = 0;

    for (let n = 1; n <= 20; n++) {
        const { prompt, labels } = await round(page, n);

        const answer = decide(prompt);
        expect(answer, `no unique family mapped for prompt: ${prompt}`).not.toBeNull();
        if (target === null && TWO_ITEM_FAMILIES.includes(answer!)) target = answer;

        const wrong = answer === target;
        if (wrong) missed++;
        const label = wrong ? labels.find((l) => l !== answer)! : answer!;

        await page.locator('[data-choice]').nth(labels.indexOf(label)).click();
        await page.getByRole('button', { name: /next round|see summary/i }).click();
    }

    expect(target, 'a two-item family should always be drawn').not.toBeNull();
    expect(missed, 'a two-item family can only be missed once or twice').toBeLessThanOrEqual(2);

    await finish(page);

    // Accuracy stays at or above the 80% pass bar, so the withheld rung is
    // demonstrably the floor's doing rather than a merely poor run.
    await expect(page.getByText(`${20 - missed}/20 correct`)).toBeVisible();

    await expect(page.getByRole('heading', { name: /blind spot found/i })).toBeVisible();
    await expect(page.getByText('nothing correct in this category')).toBeVisible();
    await expect(page.getByText(target!).first()).toBeVisible();
    // The panel pluralizes (Str::plural), and a two-item family may have had
    // only one of its items drawn — so the count is 1 or 2, and so is the noun.
    const noun = missed === 1 ? 'item' : 'items';
    await expect(page.getByText(`0 of ${missed} ${noun} in this run`)).toBeVisible();
    await expect(page.getByText('L7 · Reflexive')).toBeVisible();   // what speed+accuracy alone read
    await expect(page.getByText('withheld to')).toBeVisible();
    await expect(page.getByText('L4', { exact: true }).first()).toBeVisible();

    await page.screenshot({ path: 'test-results/bash-gym-blind-spot.png', fullPage: true });
});
