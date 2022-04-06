/* eslint-disable jest/no-test-callback */
// @ts-check
const { test, expect } = require( '@playwright/test' );

const testProduct = {
	title: 'Test Simple Subscription',
	price: '15.5',
};

test( 'Create a subscription product', async ( { page } ) => {
	await page.goto( '/wp-admin/post-new.php?post_type=product' );
	const productTypeSelect = page.locator( 'select[name="product-type"]' );

	// Select subscription product type
	await expect( productTypeSelect ).toBeVisible();
	await productTypeSelect.selectOption( 'subscription' );

	// Enter product title
	await page.locator( 'input[name="post_title"]' ).type( testProduct.title );
	await page
		.locator( 'input[name="_subscription_price"]' )
		.type( testProduct.price );

	// Publish
	await page.click( 'input[name="publish"]' );

	// Navigate to product frontend
	await page.click( '#sample-permalink a' );

	// Check frontend product
	const productTitle = await page.locator( '.summary .product_title' );
	await expect( productTitle ).toContainText( testProduct.title );

	const productPrice = await page.locator( '.summary .price' );
	await expect( productPrice ).toContainText( testProduct.price );
} );
