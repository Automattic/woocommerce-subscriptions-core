<?php

class WCS_Switch_Cart_Item_Test extends WCS_Unit_Test_Case {
	/**
	 * @var WC_Cart
	 */
	protected $cart;

	/**
	 * @var  WCS_Switch_Cart_Item
	 */
	private $switch_cart_item;

	/**
	 * @var array
	 */
	private $cart_item;

	/**
	 * @var WC_Subscription
	 */
	private $subscription;

	/**
	 * @var WC_Order_Item
	 */
	private $existing_item;

	/**
	 * @var WC_Product
	 */
	private $product;

	public function setUp() {
		parent::setUp();

		$this->cart             = WC()->cart;
		$this->switch_cart_item = $this->get_switch_cart_item_instance();
	}

	/**
	 * @covers WCS_Switch_Cart_Item::__construct
	 */
	public function test___construct() {
		$this->assertEquals( $this->cart_item, $this->switch_cart_item->cart_item );
		$this->assertEquals( $this->subscription, $this->switch_cart_item->subscription );
		$this->assertEquals( $this->existing_item, $this->switch_cart_item->existing_item );
		$this->assertEquals( wcs_get_canonical_product_id( $this->cart_item ), $this->switch_cart_item->canonical_product_id );
		$this->assertEquals( $this->cart_item['data'], $this->switch_cart_item->product );
		$this->assertEquals( $this->cart_item['subscription_switch']['next_payment_timestamp'], $this->switch_cart_item->next_payment_timestamp );
		$this->assertEquals( wcs_date_to_time( WC_Subscriptions_Product::get_expiration_date( wcs_get_canonical_product_id( $this->cart_item ), $this->subscription->get_date( 'last_order_date_created' ) ) ), $this->switch_cart_item->end_timestamp, '', 10 );
	}

	/**
	 * @covers WCS_Switch_Cart_Item::get_days_until_next_payment
	 */
	public function test_get_days_until_next_payment() {
		$days_until_next_payment = ceil( ( $this->switch_cart_item->next_payment_timestamp - gmdate( 'U' ) ) / DAY_IN_SECONDS );

		$this->assertNull( $this->switch_cart_item->days_until_next_payment );
		$this->assertEquals( $days_until_next_payment, $this->switch_cart_item->get_days_until_next_payment() );
		$this->assertEquals( $days_until_next_payment, $this->switch_cart_item->days_until_next_payment );
	}

	/**
	 * @covers WCS_Switch_Cart_Item::get_days_in_old_cycle
	 */
	public function test_get_days_in_old_cycle() {
		$fake_days_in_old_cycle = 'get_FAKE_days_in_old_cycle';

		$this->assertNull( $this->switch_cart_item->days_in_old_cycle );

		$this->switch_cart_item->days_in_old_cycle = $fake_days_in_old_cycle;
		$this->assertEquals( $fake_days_in_old_cycle, $this->switch_cart_item->get_days_in_old_cycle() );
	}

	/**
	 * @covers WCS_Switch_Cart_Item::get_old_price_per_day
	 * @covers WCS_Switch_Cart_Item::get_days_in_old_cycle
	 * @covers WCS_Switch_Cart_Item::get_total_paid_for_current_period
	 */
	public function test_get_old_price_per_day() {
		$this->assertNull( $this->switch_cart_item->old_price_per_day );

		add_filter( 'wcs_switch_proration_days_in_old_cycle', $this->return__( 10 ) );

		$this->assertEquals( 0, $this->switch_cart_item->get_old_price_per_day() );
	}

	/**
	 * @covers WCS_Switch_Cart_Item::get_days_in_new_cycle
	 */
	public function test_get_days_in_new_cycle() {
		$fake_days_in_new_cycle = 'get_FAKE_days_in_new_cycle';

		$this->assertNull( $this->switch_cart_item->days_in_new_cycle );

		$this->switch_cart_item->days_in_new_cycle = $fake_days_in_new_cycle;
		$this->assertEquals( $fake_days_in_new_cycle, $this->switch_cart_item->get_days_in_new_cycle() );
	}

	/**
	 * @covers WCS_Switch_Cart_Item::get_new_price_per_day
	 * @covers WCS_Switch_Cart_Item::is_switch_during_trial
	 * @covers WCS_Switch_Cart_Item::trial_periods_match
	 */
	public function test_get_new_price_per_day() {
		$this->assertNull( $this->switch_cart_item->new_price_per_day );

		add_filter( 'wcs_switch_proration_days_in_new_cycle', $this->return__( 3 ) );
		add_filter( 'woocommerce_subscriptions_product_price', $this->return__( 111.23 ) );

		$this->assertEquals( ( 111.23 * $this->switch_cart_item->cart_item['quantity'] ) / 3, $this->switch_cart_item->get_new_price_per_day() );
	}

	/**
	 * @covers WCS_Switch_Cart_Item::get_last_order_paid_time
	 */
	public function test_get_last_order_paid_time() {
		$this->assertNull( $this->switch_cart_item->last_order_paid_time );
		$this->assertEquals( $this->subscription->get_time( 'start' ), $this->switch_cart_item->get_last_order_paid_time(), '', 10 );

		// Has parent order.
		$parent_order                                 = WCS_Helper_Subscription::create_parent_order( $this->subscription );
		$this->switch_cart_item->last_order_paid_time = null;
		$this->assertEquals( $parent_order->get_date_created()->getTimestamp(), $this->switch_cart_item->get_last_order_paid_time(), '', 10 );

		// parent has been paid.
		$this->switch_cart_item->last_order_paid_time = null;
		$parent_order->set_date_paid( $parent_order->get_date_created()->getTimestamp() + 5 ); // The order was paid 5 seconds after it was created.
		$parent_order->save();
		$this->assertEquals( $parent_order->get_date_paid()->getTimestamp(), $this->switch_cart_item->get_last_order_paid_time(), '', 10 );

		// has renewal orders.
		$this->switch_cart_item->last_order_paid_time = null;
		$renewal_order                                = WCS_Helper_Subscription::create_renewal_order( $this->subscription );
		$this->assertEquals( $renewal_order->get_date_created()->getTimestamp(), $this->switch_cart_item->get_last_order_paid_time(), '', 10 );

		// renewal has been paid.
		$this->switch_cart_item->last_order_paid_time = null;
		$renewal_order->set_date_paid( $renewal_order->get_date_created()->getTimestamp() + 5 ); // The order was paid 5 seconds after it was created.
		$renewal_order->save();
		$this->assertEquals( $renewal_order->get_date_paid()->getTimestamp(), $this->switch_cart_item->get_last_order_paid_time(), '', 10 );
	}

	/**
	 * @covers WCS_Switch_Cart_Item::get_total_paid_for_current_period
	 */
	public function test_get_total_paid_for_current_period() {
		$total_paid_for_current_period = WC_Subscriptions_Switcher::calculate_total_paid_since_last_order( $this->subscription, $this->existing_item, 'exclude_sign_up_fees' );

		$this->assertNull( $this->switch_cart_item->total_paid_for_current_period );
		$this->assertEquals( $total_paid_for_current_period, $this->switch_cart_item->get_total_paid_for_current_period() );
		$this->assertEquals( $total_paid_for_current_period, $this->switch_cart_item->total_paid_for_current_period );
	}

	/**
	 * @covers WCS_Switch_Cart_Item::get_days_since_last_payment
	 */
	public function test_get_days_since_last_payment() {
		$days_since_last_payment = floor( ( gmdate( 'U' ) - $this->switch_cart_item->get_last_order_paid_time() ) / DAY_IN_SECONDS );

		$this->assertNull( $this->switch_cart_item->days_since_last_payment );
		$this->assertEquals( $days_since_last_payment, $this->switch_cart_item->get_days_since_last_payment() );
		$this->assertEquals( $days_since_last_payment, $this->switch_cart_item->days_since_last_payment );
	}

	/**
	 * @covers WCS_Switch_Cart_Item::get_switch_type
	 * @expectedException     UnexpectedValueException
	 */
	public function test_get_switch_type() {
		$this->assertNull( $this->switch_cart_item->switch_type );

		add_filter( 'wcs_switch_proration_old_price_per_day', $this->return__( 25.24 ) );
		add_filter( 'wcs_switch_proration_new_price_per_day', $this->return__( 25.26 ) );

		// Should return upgrade when old_price is higher than new_price.
		$this->assertEquals( 'upgrade', $this->switch_cart_item->get_switch_type() );

		// Test exception.
		$this->switch_cart_item->switch_type = null;
		add_filter( 'wcs_switch_proration_switch_type', $this->return__( 'UNEXPECTED_SWITCH_TYPE' ) );
		$this->switch_cart_item->get_switch_type();
	}

	/**
	 * @covers WCS_Switch_Cart_Item::calculate_days_in_old_cycle
	 */
	public function test_calculate_days_in_old_cycle() {
		$return_no = function ( $value, $object_id, $key ) {
			if ( '_contains_synced_subscription' === $key ) {
				return 'no';
			}

			return $value;
		};
		add_filter( 'get_post_metadata', $return_no, 10, 3 );
		$this->switch_cart_item->total_paid_for_current_period = 10;

		// days_between_payments.
		$this->assertEquals( round( ( $this->switch_cart_item->next_payment_timestamp - $this->switch_cart_item->get_last_order_paid_time() ) / DAY_IN_SECONDS ), $this->switch_cart_item->calculate_days_in_old_cycle() );

		remove_filter( 'get_post_metadata', $return_no, 10 );
		$return_true = function ( $value, $object_id, $key ) {
			if ( '_contains_synced_subscription' === $key ) {
				return 'true';
			}

			return $value;
		};

		add_filter( 'get_post_metadata', $return_true, 10, 3 );
		$this->switch_cart_item->total_paid_for_current_period = 0;
		$this->switch_cart_item->next_payment_timestamp        = 0;

		// days_in_billing_cycle.
		$this->assertEquals( wcs_get_days_in_cycle( $this->subscription->get_billing_period(), $this->subscription->get_billing_interval() ), $this->switch_cart_item->calculate_days_in_old_cycle() );

		remove_filter( 'get_post_metadata', $return_true );
	}

	/**
	 * @covers WCS_Switch_Cart_Item::calculate_days_in_new_cycle
	 * @covers WCS_Switch_Cart_Item::get_last_order_paid_time
	 * @covers WCS_Switch_Cart_Item::get_days_in_old_cycle
	 * @covers WCS_Switch_Cart_Item::is_switch_during_trial
	 * @covers WCS_Switch_Cart_Item::trial_periods_match
	 */
	public function test_calculate_days_in_new_cycle() {
		$last_order_paid_time = $this->switch_cart_item->get_last_order_paid_time();
		add_filter( 'woocommerce_subscriptions_product_period_interval', $this->return__( 5 ) );
		add_filter( 'woocommerce_subscriptions_product_period', $this->return__( 'month' ) );

		$this->assertEquals( ( wcs_add_time( 5, 'month', $last_order_paid_time ) - $last_order_paid_time ) / DAY_IN_SECONDS, $this->switch_cart_item->calculate_days_in_new_cycle() );
	}

	/**
	 * @covers WCS_Switch_Cart_Item::is_virtual_product
	 */
	public function test_is_virtual_product() {
		$this->assertEquals( $this->switch_cart_item->product->is_virtual(), $this->switch_cart_item->is_virtual_product() );
	}

	/**
	 * @covers WCS_Switch_Cart_Item::trial_periods_match
	 */
	public function test_trial_periods_match() {
		$existing_product = $this->existing_item->get_product();

		$matching_length = (int) WC_Subscriptions_Product::get_trial_length( $this->product ) === (int) WC_Subscriptions_Product::get_trial_length( $existing_product );
		$matching_period = WC_Subscriptions_Product::get_trial_period( $this->product ) === WC_Subscriptions_Product::get_trial_period( $existing_product );

		$this->assertEquals( ( $matching_length && $matching_period ), $this->switch_cart_item->trial_periods_match() );
	}

	/**
	 * @covers WCS_Switch_Cart_Item::is_switch_during_trial
	 */
	public function test_is_switch_during_trial() {
		$this->assertEquals( ( $this->subscription->get_time( 'trial_end' ) > gmdate( 'U' ) ), $this->switch_cart_item->is_switch_during_trial() );
	}

	/**
	 * @param WC_Subscription $subscription
	 * @param WC_Product      $product
	 *
	 * @return false|WCS_Switch_Cart_Item
	 */
	private function get_switch_cart_item_instance( $subscription = null, $product = null ) {
		$witching_item = null;

		if ( is_null( $product ) ) {
			$product = WCS_Helper_Product::create_simple_subscription_product( array( 'subscription_length' => 1 ) );
		}
		if ( is_null( $subscription ) ) {
			$subscription = WCS_Helper_Subscription::create_subscription( array( 'status' => 'active' ) );
		}

		// Add order products.
		$item = new WC_Order_Item_Product();
		$item->set_props( array(
			'product'    => $product,
			'product_id' => $product->get_id(),
			'quantity'   => 4,
			'subtotal'   => wc_get_price_excluding_tax( $product, array( 'qty' => 4 ) ),
			'total'      => wc_get_price_excluding_tax( $product, array( 'qty' => 4 ) ),
		) );

		$item->save();
		$subscription->add_item( $item );
		$subscription->save();

		$this->add_content_to_cart( $product, $subscription, $item, array(
			'first_payment_timestamp' => strtotime( '2015-01-01 08:10:55' ),
			'next_payment_timestamp'  => strtotime( '2015-02-01 08:10:55' ),
			'end_timestamp'           => strtotime( '2016-01-01 08:10:55' ),
		) );

		$cart_item     = $this->cart->cart_contents[ $item->get_id() ];
		$existing_item = wcs_get_order_item( $item->get_id(), $subscription );

		try {
			$witching_item = new WCS_Switch_Cart_Item( $cart_item, $subscription, $existing_item );

			$this->cart_item     = $cart_item;
			$this->subscription  = $subscription;
			$this->existing_item = $existing_item;
			$this->product       = $product;

			return $witching_item;
		} catch ( Exception $e ) {
			return $witching_item;
		}
	}

	/**
	 * @param WC_Subscription       $subscription
	 * @param WC_Product            $product
	 * @param WC_Order_Item_Product $item
	 * @param array                 $subscription_switch_data
	 * @param mixed                 $cart_key
	 */
	private function add_content_to_cart( $product, $subscription, $item, $subscription_switch_data = array(), $cart_key = null ) {
		if ( is_null( $cart_key ) ) {
			$cart_key = $item->get_id();
		}

		$this->cart->cart_contents[ $cart_key ] = array(
			'product_id'          => $product->get_id(),
			'data'                => $product,
			'quantity'            => $item->get_quantity(),
			'subscription_switch' => wp_parse_args( $subscription_switch_data, array(
				'subscription_id' => $subscription->get_id(),
				'item_id'         => $item->get_id(),
			) ),
		);
	}
}
