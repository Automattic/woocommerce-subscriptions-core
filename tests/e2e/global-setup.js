const { chromium } = require( '@playwright/test' );

async function globalSetup( config ) {
	const baseUrl = config.projects[ 0 ].use.baseURL;
	const browser = await chromium.launch();
	const page = await browser.newPage();
	await page.goto( `${ baseUrl }/wp-login.php` );
	await page.fill( 'input#user_login', 'admin' );
	await page.fill( 'input#user_pass', 'password' );
	await page.click( 'text=Log In' );
	// Save signed-in state to 'storageState.json'.
	await page
		.context()
		.storageState( { path: './tests/e2e/storageState.json' } );
	await browser.close();
}

module.exports = globalSetup;
