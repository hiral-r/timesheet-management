// Fills and submits the Google Form once per entry.
// Usage: node fill-form.mjs <job.json>
// job.json: { formUrl, headless, dryRun, email, entries: [{ id, answers: { "<Question title>": "<value>", ... } }] }
// Prints one line per entry:  RESULT {"id":1,"ok":true}
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { openBrowser, isLoginPage } from './browser.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
const job = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));

const norm = (s) => String(s || '').replace(/\*/g, '').replace(/\s+/g, ' ').trim().toLowerCase();
const cssValue = (s) => String(s).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
const report = (r) => console.log('RESULT ' + JSON.stringify(r));

async function questionItems(page) {
    const items = page.locator('div[role="listitem"]');
    const out = [];
    const count = await items.count();
    for (let i = 0; i < count; i++) {
        const item = items.nth(i);
        const heading = item.locator('[role="heading"]').first();
        if (!(await heading.count())) continue;
        out.push({ item, title: norm(await heading.innerText()) });
    }
    return out;
}

function findItem(items, title) {
    const t = norm(title);
    return items.find((q) => q.title === t) || items.find((q) => q.title.startsWith(t));
}

async function answer(page, item, title, value) {
    // Dropdown
    const listbox = item.locator('[role="listbox"]');
    if (await listbox.count()) {
        await listbox.first().click();
        const option = page.locator(`[role="option"][data-value="${cssValue(value)}"]:visible`).first();
        try {
            await option.waitFor({ state: 'visible', timeout: 5000 });
        } catch {
            await page.keyboard.press('Escape');
            throw new Error(`Option "${value}" not found in "${title}"`);
        }
        await option.click();
        await page.waitForTimeout(500);
        return;
    }

    // Multiple choice
    if (await item.locator('[role="radio"]').count()) {
        const radio = item.locator(`[role="radio"][data-value="${cssValue(value)}"]`);
        if (!(await radio.count())) throw new Error(`Choice "${value}" not found in "${title}"`);
        await radio.first().click();
        return;
    }

    // Short answer / paragraph / email
    const field = item.locator('textarea, input[type="text"], input[type="email"], input[type="url"], input:not([type])').first();
    if (!(await field.count())) throw new Error(`No input box found for "${title}"`);
    await field.fill(String(value ?? ''));
}

async function handleEmail(page, items) {
    // "Record my email address with my response" checkbox (signed-in forms)
    const boxes = page.locator('[role="checkbox"]');
    for (let i = 0; i < (await boxes.count()); i++) {
        const box = boxes.nth(i);
        const label = norm((await box.getAttribute('aria-label')) || '');
        const near = norm(await box.locator('xpath=ancestor::label[1]').innerText().catch(() => ''));
        if ((label + ' ' + near).includes('email') && (await box.getAttribute('aria-checked')) === 'false') {
            await box.click();
        }
    }
    // Typed email question
    if (job.email) {
        const q = findItem(items, 'email');
        const input = q ? q.item.locator('input') : page.locator('input[type="email"]');
        if ((await input.count()) && (await input.first().isEditable())) await input.first().fill(job.email);
    }
}

async function fillOne(page, entry) {
    await page.goto(job.formUrl, { waitUntil: 'domcontentloaded' });

    if (isLoginPage(page.url())) {
        throw new Error('Not signed in to Google. Run:  cd form-filler && npm run login -- "<form url>"');
    }

    await page.locator('div[role="listitem"]').first().waitFor({ timeout: 20000 });
    const items = await questionItems(page);

    await handleEmail(page, items);

    for (const [title, value] of Object.entries(entry.answers)) {
        if (value === null || value === undefined || value === '') continue;
        const q = findItem(items, title);
        if (!q) throw new Error(`Question "${title}" not found on the form`);
        await answer(page, q.item, title, value);
    }

    if (job.dryRun) {
        await page.waitForTimeout(6000);
        throw new Error('Dry run: form filled but NOT submitted');
    }

    const submit = page.locator('div[role="button"], button').filter({ hasText: /^\s*Submit\s*$/i }).first();
    await submit.click();

    try {
        await Promise.race([
            page.waitForURL(/formResponse/, { timeout: 20000 }),
            page.getByText(/response has been recorded/i).first().waitFor({ timeout: 20000 }),
        ]);
    } catch {
        const required = await page.getByText(/required question/i).count();
        throw new Error(required ? 'Form says a required question is empty or invalid' : 'No confirmation after Submit');
    }
}

const context = await openBrowser({ headless: !!job.headless, channel: job.browser });
const page = context.pages()[0] || (await context.newPage());

for (const entry of job.entries) {
    try {
        await fillOne(page, entry);
        report({ id: entry.id, ok: true });
    } catch (err) {
        await page.screenshot({ path: path.join(here, 'last-error.png'), fullPage: true }).catch(() => {});
        report({ id: entry.id, ok: false, error: String(err.message || err).split('\n')[0] });
        if (/Not signed in/.test(err.message)) break;
    }
}

await context.close();
