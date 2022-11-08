<?php
/**
 * Class: WC_Subscription_Payment_Count_Test
 */
class WC_Subscriptions_Payment_Count_Test extends WP_UnitTestCase {

	/** A basic subscription used to test against */
	public $subscription;

	/**
	 * An internal cache of subscription-related orders.
	 *
	 * @var WC_Order[][]|WC_Order[]
	 */
	public $orders = array(
		'parent'  => null,
		'renewal' => array(),
		'switch'  => array(),
	);

	/**
	 * Setup the suite for testing the WC_Subscription::get_payment_count() function.
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.6.0
	 */
	public function set_up() {
		parent::set_up();

		$subscription = WCS_Helper_Subscription::create_subscription();

		// Create a parent order
		$this->orders['parent'] = WCS_Helper_Subscription::create_parent_order( $subscription );

		// Create a few renewal orders.
		for ( $i = 1; $i <= 4; $i ++ ) {
			$this->orders['renewal'][] = WCS_Helper_Subscription::create_renewal_order( $subscription );
		}

		// Create a few switch orders.
		for ( $i = 1; $i <= 2; $i ++ ) {
			$this->orders['switch'][] = WCS_Helper_Subscription::create_switch_order( $subscription );
		}

		$this->subscription = $subscription;
	}

	/**
	 * Test that hooking onto woocommerce_subscription_payment_completed_count will result in deprecated notice.
	 *
	 * When a third party hooks onto the filter, the result from get_payment_count() should be the callback filtered result
	 * irrespective of the real payment count.
	 *
	 * @expectedDeprecated woocommerce_subscription_payment_completed_count
	 * @group subscriptions-payment-count
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v2.0
	 */
	public function test_get_payment_count_deprecated() {
		$deprecated_hook = 'woocommerce_subscription_payment_completed_count';
		$callback        = function() {
			return 100;
		};

		$this->assertEquals( 0, $this->subscription->get_payment_count() );

		add_filter( $deprecated_hook, $callback );

		$this->assertEquals( 100, $this->subscription->get_payment_count() );

		// Mark all renewal orders as paid
		array_walk( $this->orders['renewal'], array( $this, 'mark_related_order_as_complete' ) );
		PHPUnit_Utils::clear_singleton_property( $this->subscription, 'cached_payment_count' );
		$this->assertEquals( 100, $this->subscription->get_payment_count() );

		// Mark the parent order as paid.
		$this->mark_related_order_as_complete( $this->orders['parent'] );
		PHPUnit_Utils::clear_singleton_property( $this->subscription, 'cached_payment_count' );
		$this->assertEquals( 100, $this->subscription->get_payment_count() );

		remove_filter( $deprecated_hook, $callback );

		// Now that the callback has been detached, check the real value (5 -> 4 renewals and 1 parent) is returned.
		$this->assertEquals( 5, $this->subscription->get_payment_count() );
	}

	/**
	 * Tests various order type combinations of payment counts.
	 *
	 * @group subscriptions-payment-count
	 */
	public function test_get_payment_count() {

		// Check that all order types are 0 to start.
		foreach ( array( 'parent', 'renewal', 'switch', 'resubscribe' ) as $order_type ) {
			$this->assertEquals( 0, $this->subscription->get_payment_count( 'completed', $order_type ) );
			$this->assertEquals( 0, $this->subscription->get_payment_count( 'refunded', $order_type ) );
			$this->assertEquals( 0, $this->subscription->get_payment_count( 'net', $order_type ) );
		}

		// Mark the parent as paid
		$this->mark_related_order_as_complete( $this->orders['parent'] );
		PHPUnit_Utils::clear_singleton_property( $this->subscription, 'cached_payment_count' );

		$this->assertEquals( 1, $this->subscription->get_payment_count( 'completed', 'parent' ) );

		foreach ( array( 'renewal', 'switch', 'resubscribe' ) as $order_type ) {
			$this->assertEquals( 1, $this->subscription->get_payment_count( 'completed', array( $order_type, 'parent' ) ) );
			$this->assertEquals( 0, $this->subscription->get_payment_count( 'refunded', array( $order_type, 'parent' ) ) );
			$this->assertEquals( 1, $this->subscription->get_payment_count( 'net', array( $order_type, 'parent' ) ) );
		}

		// Mark 3 renewals as paid and one as refunded.
		$first_three_renewals = array_slice( $this->orders['renewal'], 0, 3 );
		array_walk( $first_three_renewals, array( $this, 'mark_related_order_as_complete' ) );
		$this->mark_related_order_as_refunded( $this->orders['renewal'][0] );
		PHPUnit_Utils::clear_singleton_property( $this->subscription, 'cached_payment_count' );

		// Check the parent and renewal combined totals are correct (3 paid renewals, 1 refunded renewal and 1 paid parent).
		$this->assertEquals( 4, $this->subscription->get_payment_count( 'completed' ) );
		$this->assertEquals( 1, $this->subscription->get_payment_count( 'refunded' ) );
		$this->assertEquals( 3, $this->subscription->get_payment_count( 'net' ) );

		// Check the renewal relation with other types - the other order types counts should be 0 so the expected renewal counts should be returned
		foreach ( array( 'switch', 'resubscribe' ) as $order_type ) {
			$this->assertEquals( 3, $this->subscription->get_payment_count( 'completed', array( $order_type, 'renewal' ) ) );
			$this->assertEquals( 1, $this->subscription->get_payment_count( 'refunded', array( $order_type, 'renewal' ) ) );
			$this->assertEquals( 2, $this->subscription->get_payment_count( 'net', array( $order_type, 'renewal' ) ) );
		}

		// Mark 1 switch as paid and refunded.
		$this->mark_related_order_as_complete( $this->orders['switch'][0] );
		$this->mark_related_order_as_refunded( $this->orders['switch'][0] );
		PHPUnit_Utils::clear_singleton_property( $this->subscription, 'cached_payment_count' );

		$this->assertEquals( 1, $this->subscription->get_payment_count( 'completed', 'switch' ) );
		$this->assertEquals( 1, $this->subscription->get_payment_count( 'refunded', 'switch' ) );
		$this->assertEquals( 0, $this->subscription->get_payment_count( 'net', 'switch' ) );

		$this->assertEquals( 5, $this->subscription->get_payment_count( 'completed', array( 'parent', 'renewal', 'switch' ) ) );
		$this->assertEquals( 2, $this->subscription->get_payment_count( 'refunded', array( 'parent', 'renewal', 'switch' ) ) );
		$this->assertEquals( 3, $this->subscription->get_payment_count( 'net', array( 'parent', 'renewal', 'switch' ) ) );
	}

	/**
	 * Sets the order as payment completed.
	 *
	 * @param WC_Order $order
	 */
	protected function mark_related_order_as_complete( $order ) {
		$order->payment_complete();
	}

	/**
	 * Sets the order to a refunded status and saves the order.
	 *
	 * @param WC_Order $order
	 */
	protected function mark_related_order_as_refunded( $order ) {
		$order->update_status( 'refunded' );
		$order->save();
	}
}
