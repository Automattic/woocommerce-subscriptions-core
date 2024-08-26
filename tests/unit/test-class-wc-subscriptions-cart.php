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
	 * Tear down the test class.
	 */
	public function tear_down() {
		parent::tear_down();

		$this->cart->empty_cart();
		$this->cart->recurring_carts = [];
	}

	/**
	 * Test that recurring carts are created when calculating totals.
	 */
	public function test_calculate_subscription_totals() {
		$product = WCS_Helper_Product::create_simple_subscription_product( [ 'price' => 10 ] );

		// First, check that there are no recurring carts.
		$this->assertEmpty( $this->cart->recurring_carts );

		$this->cart->add_to_cart( $product->get_id() );

		// Calculate the totals. This should create a recurring cart.
		$this->cart->calculate_totals();

		// Check that the recurring cart was created.
		$this->assertNotEmpty( $this->cart->recurring_carts );
		$this->assertCount( 1, $this->cart->recurring_carts );
	}

	/**
	 * Test that recurring carts are created when calculating totals.
	 */
	public function test_calculate_subscription_totals_multiple_recurring_carts() {
		$product_1 = WCS_Helper_Product::create_simple_subscription_product( [ 'price' => 10 ] );
		$product_2 = WCS_Helper_Product::create_simple_subscription_product(
			[
				'price'               => 20,
				'subscription_period' => 'year',
			]
		);

		// First, check that there are no recurring carts.
		$this->assertEmpty( $this->cart->recurring_carts );

		$this->cart->add_to_cart( $product_1->get_id() );
		$this->cart->add_to_cart( $product_2->get_id() );

		// Calculate the totals. This should create a recurring cart.
		$this->cart->calculate_totals();

		// Check that the recurring cart was created.
		$this->assertNotEmpty( $this->cart->recurring_carts );
		$this->assertCount( 2, $this->cart->recurring_carts );
	}

	/**
	 * Test that recurring carts are created when calculating totals.
	 */
	public function test_calculate_subscription_totals_multiple_items_one_cart() {
		$product_1 = WCS_Helper_Product::create_simple_subscription_product(
			[
				'price'               => 10,
				'subscription_period' => 'year',
			]
		);
		$product_2 = WCS_Helper_Product::create_simple_subscription_product(
			[
				'price'               => 20,
				'subscription_period' => 'year',
			]
		);

		// First, check that there are no recurring carts.
		$this->assertEmpty( $this->cart->recurring_carts );

		$this->cart->add_to_cart( $product_1->get_id() );
		$this->cart->add_to_cart( $product_2->get_id() );

		// Calculate the totals. This should create a recurring cart.
		$this->cart->calculate_totals();

		// Check that the recurring cart was created.
		$this->assertNotEmpty( $this->cart->recurring_carts );
		$this->assertCount( 1, $this->cart->recurring_carts ); // Only one recurring cart should be created for both items.
		$this->assertCount( 2, reset( $this->cart->recurring_carts )->get_cart() );
	}
}
