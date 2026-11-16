// UI walkthrough used to validate the frontend and produce docs/screenshots + docs/demo.
// Needs the stack running and seeded. Setup: npm i playwright-core@1.49.1 (uses installed Microsoft Edge;
// for --video also run: npx playwright-core install ffmpeg).
// Usage: node tools/ui-walkthrough.mjs <outDir> [searchQuery] [--video]
import { chromium } from 'playwright-core';
import { mkdirSync } from 'node:fs';

const BASE = 'http://localhost:8080';
const out = process.argv[2] ?? './out';
const query = process.argv[3] ?? 'trip to goa';
const video = process.argv.includes('--video');
mkdirSync(out, { recursive: true });
// In video mode, slow down so a viewer can follow each step.
const pause = (page, ms = 900) => (video ? page.waitForTimeout(ms) : Promise.resolve());

const problems = [];
const browser = await chromium.launch({ channel: 'msedge' });

async function session({ name, viewport, colorScheme = 'light', recordVideo }) {
    const context = await browser.newContext({ viewport, colorScheme, deviceScaleFactor: 2, recordVideo });
    const page = await context.newPage();
    page.on('console', (m) => m.type() === 'error' && problems.push(`[${name}] console: ${m.text()}`));
    page.on('pageerror', (e) => problems.push(`[${name}] pageerror: ${e.message}`));
    return { context, page };
}

async function login(page) {
    await page.goto(BASE);
    await page.waitForSelector('#auth:not([hidden])');
    await pause(page);
    await page.type('#auth-email', 'demo@example.com', { delay: video ? 40 : 0 });
    await page.type('#auth-password', 'password123', { delay: video ? 40 : 0 });
    await pause(page, 500);
    await page.click('#auth-submit');
    await page.waitForSelector('.note-item');
    await pause(page, 1200);
}

async function openNoteTitled(page, title) {
    await page.click(`.note-item:has-text("${title}")`);
    await page.waitForSelector('#editor:not([hidden])');
}

// ---- Desktop light ----
{
    const { context, page } = await session({ name: 'desktop', viewport: { width: 1440, height: 900 }, recordVideo: video ? { dir: out, size: { width: 1440, height: 900 } } : undefined });
    await page.goto(BASE);
    await page.waitForSelector('#auth:not([hidden])');
    await page.screenshot({ path: `${out}/01-login.png` });

    await login(page);
    await page.mouse.move(0, 0);
    await page.waitForTimeout(400);
    await page.screenshot({ path: `${out}/02-notes-list.png` });

    await openNoteTitled(page, 'Production incident');
    await pause(page);
    await page.click('#summarize');
    await page.waitForSelector('#summary:not([hidden])', { timeout: 30000 });
    await page.waitForTimeout(400);
    await pause(page, 2500);
    await page.screenshot({ path: `${out}/03-ai-summary.png` });

    await page.click('#search-input');
    await page.type('#search-input', query, { delay: video ? 60 : 0 });
    await page.press('#search-input', 'Enter');
    await page.waitForSelector('.note-item .score, .list-state');
    await page.waitForTimeout(400);
    await pause(page, 2500);
    if (video) {
        // A second query in the video: a recipe question with none of the recipe words.
        await page.fill('#search-input', '');
        await page.type('#search-input', 'what should I cook tonight', { delay: 60 });
        await page.press('#search-input', 'Enter');
        await page.waitForSelector('.note-item .score');
        await pause(page, 2500);
    }
    await page.screenshot({ path: `${out}/04-semantic-search.png` });

    if (video) { await page.click('.list-meta .btn-link'); await page.waitForSelector('.note-item'); }
    await page.click('#new-note');
    await pause(page, 600);
    await page.click('#save');
    await page.waitForSelector('[data-error="title"]:not(:empty)');
    await pause(page, 1500);
    await page.screenshot({ path: `${out}/05-validation.png` });

    await page.type('#note-title', 'Electricity bill reminder', { delay: video ? 35 : 0 });
    await page.type('#note-content', 'Pay the BESCOM bill before the 15th to avoid the late fee. Autopay failed last month because the card expired.', { delay: video ? 18 : 0 });
    await page.click('#save');
    await page.waitForSelector('#index-state:has-text("Indexed")', { timeout: 20000 });
    await pause(page, 1500);
    await page.screenshot({ path: `${out}/06-created-and-indexed.png` });
    page.once('dialog', (d) => d.accept());
    await page.click('#delete');
    await page.waitForSelector('#editor-empty:not([hidden])');
    await pause(page, 1500);
    await context.close();
    if (video) { await page.video().saveAs(`${out}/demo.webm`); await page.video().delete(); await finish(); }
}

async function finish() {
    await browser.close();
    console.log(problems.length ? problems.join(' | ') : 'no console errors, no overflow');
    process.exit(0);
}

// ---- Desktop dark ----
{
    const { context, page } = await session({ name: 'dark', viewport: { width: 1440, height: 900 }, colorScheme: 'dark' });
    await login(page);
    await openNoteTitled(page, 'Production incident');
    await page.waitForTimeout(300);
    await page.screenshot({ path: `${out}/07-dark-mode.png` });
    await context.close();
}

// ---- Mobile ----
{
    const { context, page } = await session({ name: 'mobile', viewport: { width: 390, height: 844 } });
    await login(page);
    await page.screenshot({ path: `${out}/08-mobile-list.png` });
    await openNoteTitled(page, 'Production incident');
    await page.screenshot({ path: `${out}/09-mobile-editor.png` });
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    if (overflow) problems.push('[mobile] horizontal overflow');
    await context.close();
}

await browser.close();
console.log(problems.length ? problems.join('\n') : 'no console errors, no overflow');
