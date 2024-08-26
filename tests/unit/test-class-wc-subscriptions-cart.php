<?php
/**
 * Tests for the WC_Subscriptions_Cart class.
 */
class WC_Subscriptions_Cart_Test extends WP_UnitTestCase {

	/**
	 * @var WC_Cart
	 */
	private $cart;

	/**
	 * Set up the test class.
	 */
	public function set_up() {
		parent::set_up();

		$this->cart = WC()->cart;
	}

	/**
	 * Test that recurring carts are created when calculating totals.
	 */
	public function test_calculate_subscription_totals() {
		$product = WCS_Helper_Product::create_simple_subscription_product( array( 'price' => 10 ) );

		$this->cart->add_to_cart( $product->get_id() );

		// First, check that there are no recurring carts.
		$this->assertEmpty( $this->cart->recurring_carts );

		// Calculate the totals. This should create a recurring cart.
		$this->cart->calculate_totals();

		// Check that the recurring cart was created.
		$this->assertNotEmpty( $this->cart->recurring_carts );
	}
}
