// Regenerate the README screenshots from the local dev stack.
// Usage: node dev/screenshots.mjs   (needs dev/up.sh running and Chrome/Chromium; set CHROME=/path if not found)
import puppeteer from 'puppeteer-core';
import { execSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';

const BASE = process.env.BASE || 'http://localhost:8888';
const OUT = new URL('../docs/screenshots/', import.meta.url).pathname;
const CHROME = process.env.CHROME || ['google-chrome', 'chromium', 'chromium-browser']
	.map((b) => { try { return execSync(`command -v ${b}`).toString().trim(); } catch { return ''; } })
	.find(Boolean);

mkdirSync(OUT, { recursive: true });
const browser = await puppeteer.launch({ executablePath: CHROME, headless: true, args: ['--no-sandbox'] });
const page = await browser.newPage();
await page.setViewport({ width: 1280, height: 860, deviceScaleFactor: 1 });

const shot = async (url, file, prepare) => {
	await page.goto(BASE + url, { waitUntil: 'networkidle0' });
	if (prepare) await prepare();
	await page.screenshot({ path: OUT + file });
	console.log('wrote docs/screenshots/' + file);
};

await shot('/task-table/', 'frontend-table.png');
await shot('/task-table/', 'frontend-signup-modal.png', async () => {
	await page.click('.event-signup');
	await new Promise((r) => setTimeout(r, 400));
});

await page.goto(BASE + '/wp-login.php', { waitUntil: 'networkidle0' });
await page.type('#user_login', 'admin');
await page.type('#user_pass', 'admin');
await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('#wp-submit')]);

await shot('/wp-admin/admin.php?page=task-table', 'admin-events.png');
await shot('/wp-admin/admin.php?page=task-table-signups', 'admin-signups.png');
await shot('/wp-admin/admin.php?page=task-table-email-settings', 'admin-email-settings.png');

await browser.close();
