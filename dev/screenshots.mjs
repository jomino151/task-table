// Regenerate the README screenshots from the local dev stack.
// Usage: node dev/screenshots.mjs   (needs dev/up.sh running and Chrome/Chromium; set CHROME=/path if not found)
import puppeteer from 'puppeteer-core';
import { execSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';

const BASE = process.env.BASE || 'http://localhost:8888';
const OUT = new URL( '../docs/screenshots/', import.meta.url ).pathname;
const CHROME =
	process.env.CHROME ||
	[ 'google-chrome', 'chromium', 'chromium-browser' ]
		.map( ( b ) => {
			try {
				return execSync( `command -v ${ b }` ).toString().trim();
			} catch {
				return '';
			}
		} )
		.find( Boolean );

mkdirSync( OUT, { recursive: true } );
const browser = await puppeteer.launch( {
	executablePath: CHROME,
	headless: true,
	args: [ '--no-sandbox' ],
} );
const page = await browser.newPage();
await page.setViewport( { width: 1280, height: 860, deviceScaleFactor: 1 } );

const shot = async ( url, file, prepare, clip ) => {
	await page.goto( BASE + url, { waitUntil: 'networkidle0' } );
	if ( prepare ) {
		await prepare();
	}
	await page.screenshot( {
		path: OUT + file,
		clip: clip ? await clip() : undefined,
	} );
	console.log( 'wrote docs/screenshots/' + file );
};

// Crop to the blocks themselves: from the task table down to the end of the subscribe form.
const PAD = 24;
const blocksClip = () =>
	page.evaluate( ( pad ) => {
		const table = document
			.querySelector( '.wp-block-task-table-task-table' )
			.getBoundingClientRect();
		const form = document
			.querySelector( '.wp-block-task-table-new-event-signup' )
			.getBoundingClientRect();
		const top = table.top + window.scrollY - pad;
		return {
			x: table.left - pad,
			y: top,
			width: table.width + 2 * pad,
			height: form.bottom + window.scrollY + pad - top,
		};
	}, PAD );

await shot( '/task-table/', 'frontend-table.png', null, blocksClip );
await shot( '/task-table/', 'frontend-signup-modal.png', async () => {
	await page.click( '.event-signup' );
	await new Promise( ( r ) => setTimeout( r, 400 ) );
} );

// Phone width: rows render as stacked cards.
await page.setViewport( {
	width: 375,
	height: 812,
	deviceScaleFactor: 2,
	isMobile: true,
	hasTouch: true,
} );
// Only the heading and the first card, so the image sits next to the desktop one in the README.
await shot( '/task-table/', 'frontend-mobile.png', null, async () => {
	const clip = await blocksClip();
	return { ...clip, height: Math.min( clip.height, 560 ) };
} );
await page.setViewport( { width: 1280, height: 860, deviceScaleFactor: 1 } );

await page.goto( BASE + '/wp-login.php', { waitUntil: 'networkidle0' } );
await page.type( '#user_login', 'admin' );
await page.type( '#user_pass', 'admin' );
await Promise.all( [
	page.waitForNavigation( { waitUntil: 'networkidle0' } ),
	page.click( '#wp-submit' ),
] );

await shot( '/wp-admin/admin.php?page=task-table', 'admin-events.png' );
await shot(
	'/wp-admin/admin.php?page=task-table-signups',
	'admin-signups.png'
);
await shot(
	'/wp-admin/admin.php?page=task-table-email-settings',
	'admin-email-settings.png'
);

await browser.close();
