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
	 * The number of times woocommerce_subscriptions_calculated_total was triggered.
	 *
	 * @var int
	 */
	private $calculated_subscription_totals_count = 0;

	/**
	 * Set up the test class.
	 */
	public function set_up() {
		parent::set_up();

		$this->cart = WC()->cart;

		// Count the number of times woocommerce_subscriptions_calculated_total is triggered.
		add_action(
			'woocommerce_subscriptions_calculated_total',
			function( $subscription_total ) {
				$this->calculated_subscription_totals_count++;
				return $subscription_total;
			}
		);
	}

	/**
	 * Tear down the test class.
	 */
	public function tear_down() {
		parent::tear_down();

		$this->cart->empty_cart();
		$this->cart->recurring_carts = [];

		$this->calculated_subscription_totals_count = 0;
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

	/**
	 * Test that recurring carts are created when calculating totals with a mixed cart.
	 */
	public function test_calculate_subscription_totals_with_mixed_cart() {
		$subscription = WCS_Helper_Product::create_simple_subscription_product( [ 'price' => 10 ] );
		$simple       = WC_Helper_Product::create_simple_product();

		// First, check that there are no recurring carts.
		$this->assertEmpty( $this->cart->recurring_carts );

		$this->cart->add_to_cart( $subscription->get_id() );
		$this->cart->add_to_cart( $simple->get_id() );

		// Calculate the totals. This should create a recurring cart.
		$this->cart->calculate_totals();

		// Check that the recurring cart was created.
		$this->assertNotEmpty( $this->cart->recurring_carts );
		$this->assertCount( 1, $this->cart->recurring_carts );
		$this->assertCount( 1, reset( $this->cart->recurring_carts )->get_cart() );
	}

	/**
	 * Test that recurring carts are created only once when calculating totals with nested calls.
	 */
	public function test_calculate_subscription_totals_with_nested_calls() {
		$subscription = WCS_Helper_Product::create_simple_subscription_product( [ 'price' => 10 ] );
		$simple       = WC_Helper_Product::create_simple_product();

		// First, check that there are no recurring carts.
		$this->assertEmpty( $this->cart->recurring_carts );

		$this->cart->add_to_cart( $subscription->get_id() );
		$this->cart->add_to_cart( $simple->get_id() );

		add_action( 'woocommerce_calculate_totals', [ $this, 'mock_nested_callback' ] );

		$this->calculated_subscription_totals_count = 0;

		// Calculate the totals. This should create a recurring cart.
		$this->cart->calculate_totals();

		// Check that the recurring cart was created.
		$this->assertNotEmpty( $this->cart->recurring_carts );
		$this->assertCount( 1, $this->cart->recurring_carts );
		$this->assertCount( 1, reset( $this->cart->recurring_carts )->get_cart() );
		$this->assertEquals( 1, $this->calculated_subscription_totals_count );
	}

	/**
	 * A function to mock calling WC()->cart->calculate_totals() from within the calculate_totals action.
	 *
	 * @param WC_Cart $cart
	 */
	public function mock_nested_callback( $cart ) {
		if ( ! isset( $cart->recurring_cart_key ) ) {
			return;
		}

		// Nest the calculate_totals call by calling it again.
		WC()->cart->calculate_totals();
	}
}
