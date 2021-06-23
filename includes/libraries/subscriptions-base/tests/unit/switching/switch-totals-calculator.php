<?php

class WCS_Switch_Totals_Calculator_Test extends WCS_Unit_Test_Case {
	/**
	 * @var WC_Cart
	 */
	protected $cart;

	public function setUp() {
		parent::setUp();

		$this->cart = WC()->cart;
	}

	/**
	 * @covers   WCS_Switch_Totals_Calculator::__construct
	 * @expectedException InvalidArgumentException
	 * @requires PHP 7.0.0
	 */
	public function test__construct() {
		$this->get_instance();
	}

	/**
	 * @covers WCS_Switch_Totals_Calculator::calculate_prorated_totals
	 * @covers WCS_Switch_Totals_Calculator::get_switches_from_cart
	 * @covers WCS_Switch_Totals_Calculator::set_first_payment_timestamp
	 * @covers WCS_Switch_Totals_Calculator::set_end_timestamp
	 * @covers WCS_Switch_Totals_Calculator::set_switch_type_in_cart
	 */
	public function test_calculate_prorated_totals() {
		add_filter( 'wcs_switch_proration_switch_type', $this->return__( 'upgrade' ) );

		$switch_cart_item = $this->get_switch_cart_item_instance();
		$this->get_instance( $this->cart )->calculate_prorated_totals();

		$exiting_item = $this->cart->cart_contents[ $switch_cart_item->existing_item->get_id() ];

		// Set first payment timestamp.
		$this->assertEquals( $switch_cart_item->next_payment_timestamp, $exiting_item['subscription_switch']['first_payment_timestamp'], '', 10 );

		// Set end timestamp.
		$this->assertEquals( $switch_cart_item->end_timestamp, $exiting_item['subscription_switch']['end_timestamp'], '', 10 );


		// set switch type in cart.
		$this->assertEquals( 'upgraded', $exiting_item['subscription_switch']['upgraded_or_downgraded'] );
	}

	/**
	 * @covers WCS_Switch_Totals_Calculator::apportion_sign_up_fees
	 */
	public function test_calculate_prorated_totals_apportion_sign_up_fees() {
		update_option( WC_Subscriptions_Admin::$option_prefix . '_apportion_sign_up_fee', 'no' );
		update_option( 'woocommerce_prices_include_tax', 'no' );
		$switch_cart_item = $this->get_switch_cart_item_instance();
		$this->set_subscription_sign_up_fee( $switch_cart_item, 11.11 );

		$this->get_instance( $this->cart )->calculate_prorated_totals();

		// Should update to zero when _apportion_sign_up_fee is no.
		$this->assertEquals( 0, $switch_cart_item->product->get_meta( '_subscription_sign_up_fee' ) );

		update_option( WC_Subscriptions_Admin::$option_prefix . '_apportion_sign_up_fee', 'yes' );
		$this->get_instance( $this->cart )->calculate_prorated_totals();

		// if _apportion_sign_up_fee is yes.
		$this->assertEquals( 11.11, $switch_cart_item->product->get_meta( '_subscription_sign_up_fee' ) );
		$this->assertEquals( 11.11, $switch_cart_item->product->get_meta( '_subscription_sign_up_fee_prorated' ) );
	}

	/**
	 * @covers WCS_Switch_Totals_Calculator::calculate_prorated_totals
	 * @covers WCS_Switch_Totals_Calculator::should_prorate_recurring_price
	 * @covers WCS_Switch_Totals_Calculator::should_reduce_prepaid_term
	 * @covers WCS_Switch_Totals_Calculator::reduce_prepaid_term
	 * @covers WCS_Switch_Totals_Calculator::calculate_upgrade_cost
	 * @covers WCS_Switch_Totals_Calculator::set_upgrade_cost
	 * @covers WCS_Switch_Totals_Calculator::should_extend_prepaid_term
	 * @covers WCS_Switch_Totals_Calculator::extend_prepaid_term
	 * @covers WCS_Switch_Totals_Calculator::get_first_payment_timestamp
	 * @covers WCS_Switch_Totals_Calculator::calculate_pre_paid_days
	 */
	public function test_calculate_prorated_totals_should_prorate_recurring_price() {
		$product                                  = WCS_Helper_Product::create_simple_subscription_product();
		$switch_cart_item                         = $this->get_switch_cart_item_instance( null, $product );
		$switch_cart_item->next_payment_timestamp = 0;

		add_filter( 'wcs_switch_proration_reduce_pre_paid_term', $this->return__( false ) );

		add_filter( 'wcs_switch_proration_switch_type', $this->return__( 'downgrade' ) );
		update_option( WC_Subscriptions_Admin::$option_prefix . '_apportion_recurring_price', 'yes' );
		$this->get_instance( $this->cart )->calculate_prorated_totals();

		// Set a flag if the prepaid term has been adjusted.
		$this->get_instance( $this->cart )->calculate_prorated_totals();
		$this->assertTrue( $this->cart->cart_contents[ $switch_cart_item->existing_item->get_id() ]['subscription_switch']['recurring_payment_prorated'] );
	}

	/**
	 * @covers WCS_Switch_Totals_Calculator::should_apportion_length
	 * @covers WCS_Switch_Totals_Calculator::apportion_length
	 */
	public function test_calculate_prorated_totals_apportion_length() {
		$switch_cart_item = $this->get_switch_cart_item_instance();

		update_option( WC_Subscriptions_Admin::$option_prefix . '_apportion_length', 'yes' );
		add_filter( 'woocommerce_subscriptions_product_length', $this->return__( 5 ) );
		add_filter( 'woocommerce_subscription_renewal_payment_completed_count', $this->return__( 10 ) );
		$this->get_instance( $this->cart )->calculate_prorated_totals();

		// Should set to woocommerce_subscriptions_product_length when result is negative, (5-10)=-5.
		$this->assertEquals( WC_Subscriptions_Product::get_length( $switch_cart_item->canonical_product_id ), $switch_cart_item->product->get_meta( '_subscription_length' ) );

		// Clear the runtime-cached length before the new test.
		wcs_delete_objects_property( $switch_cart_item->product, 'subscription_base_length_prorated' );

		add_filter( 'woocommerce_subscriptions_product_length', $this->return__( 26 ) );
		add_filter( 'woocommerce_subscription_renewal_payment_completed_count', $this->return__( 19 ) );
		$this->get_instance( $this->cart )->calculate_prorated_totals();

		// When the result is positive (26-19)=7, should be set to the result.
		$this->assertEquals( 26 - 19, $switch_cart_item->product->get_meta( '_subscription_length' ) );

		// Clear the runtime-cached length before the new test.
		wcs_delete_objects_property( $switch_cart_item->product, 'subscription_base_length_prorated' );

		update_option( WC_Subscriptions_Admin::$option_prefix . '_apportion_length', 'no' );
		add_filter( 'woocommerce_subscriptions_product_length', $this->return__( 13 ) );
		add_filter( 'woocommerce_subscription_renewal_payment_completed_count', $this->return__( 10 ) );
		$this->get_instance( $this->cart )->calculate_prorated_totals();

		// Should update nothing when _apportion_length is no.
		$this->assertEquals( 26 - 19, $switch_cart_item->product->get_meta( '_subscription_length' ) );
	}

	/**
	 * @covers WCS_Switch_Totals_Calculator::set_first_payment_timestamp
	 */
	public function test_set_first_payment_timestamp() {
		$product_id          = $this->get_switch_cart_item_instance()->product->get_id();
		$instance            = $this->get_instance( $this->cart );
		$new_first_timestamp = strtotime( 'now' );

		$instance->set_first_payment_timestamp( $product_id, $new_first_timestamp );
		$this->assertEquals( $new_first_timestamp, $this->cart->cart_contents[ $product_id ]['subscription_switch']['first_payment_timestamp'], '', 10 );
	}

	/**
	 * @covers WCS_Switch_Totals_Calculator::set_end_timestamp
	 */
	public function test_set_end_timestamp() {
		$product_id        = $this->get_switch_cart_item_instance()->product->get_id();
		$instance          = $this->get_instance( $this->cart );
		$new_end_timestamp = strtotime( 'now' );

		$instance->set_end_timestamp( $product_id, $new_end_timestamp );
		$this->assertEquals( $new_end_timestamp, $this->cart->cart_contents[ $product_id ]['subscription_switch']['end_timestamp'], '', 10 );
	}

	/**
	 * @covers WCS_Switch_Totals_Calculator::set_switch_type_in_cart
	 */
	public function test_set_switch_type_in_cart() {
		$product_id = $this->get_switch_cart_item_instance()->product->get_id();
		$instance   = $this->get_instance( $this->cart );

		$instance->set_switch_type_in_cart( $product_id, 'SWITCH_TYPE' );
		$this->assertArrayHasKey( 'upgraded_or_downgraded', $this->cart->cart_contents[ $product_id ]['subscription_switch'] );
		$this->assertEquals( 'SWITCH_TYPEd', $this->cart->cart_contents[ $product_id ]['subscription_switch']['upgraded_or_downgraded'] );


		$instance->set_switch_type_in_cart( $product_id, '' );
		$this->assertEquals( 'd', $this->cart->cart_contents[ $product_id ]['subscription_switch']['upgraded_or_downgraded'] );
	}

	/**
	 * @covers WCS_Switch_Totals_Calculator::reset_prorated_price
	 */
	public function test_reset_prorated_price() {
		$switch_cart_item = $this->get_switch_cart_item_instance();
		$instance         = $this->get_instance( $this->cart );

		$this->set_subscription_sign_up_fee( $switch_cart_item, 18.18 );

		// Sign up fee should keep the same if `_subscription_price_prorated` doesn't exists.
		$instance->reset_prorated_price( $switch_cart_item );
		$this->assertFalse( $switch_cart_item->product->meta_exists( '_subscription_price_prorated' ) );
		$this->assertEquals( 18.18, $switch_cart_item->product->get_meta( '_subscription_sign_up_fee' ) );

		// Set new upgrade cost.
		$instance->set_upgrade_cost( $switch_cart_item, 19.19 );
		$this->assertTrue( $switch_cart_item->product->meta_exists( '_subscription_price_prorated' ) );
		$this->assertEquals( 18.18, $switch_cart_item->product->get_meta( '_subscription_sign_up_fee_prorated' ) );

		$instance->reset_prorated_price( $switch_cart_item );
		$this->assertEquals( 18.18, $switch_cart_item->product->get_meta( '_subscription_sign_up_fee' ) );
	}

	/**
	 * @covers WCS_Switch_Totals_Calculator::set_upgrade_cost
	 */
	public function test_set_upgrade_cost() {
		$switch_cart_item = $this->get_switch_cart_item_instance();
		$instance         = $this->get_instance( $this->cart );

		// Set a new sig up fee.
		$this->set_subscription_sign_up_fee( $switch_cart_item, 17.17 );

		$instance->set_upgrade_cost( $switch_cart_item, 17.17 );

		$this->assertEquals( 17.17, $switch_cart_item->product->get_meta( '_subscription_sign_up_fee_prorated', true ) );
		$this->assertEquals( 17.17, $switch_cart_item->product->get_meta( '_subscription_price_prorated', true ) );
		$this->assertEquals( 17.17 + 17.17, $switch_cart_item->product->get_meta( '_subscription_sign_up_fee', true ) );
	}

	/**
	 * @param WC_Cart $cart Default null
	 *
	 * @return WCS_Switch_Totals_Calculator
	 * @throws Exception
	 */
	private function get_instance( $cart = null ) {
		return new WCS_Switch_Totals_Calculator( $cart );
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

		try {
			$witching_item = new WCS_Switch_Cart_Item( $this->cart->cart_contents[ $item->get_id() ], $subscription, wcs_get_order_item( $item->get_id(), $subscription ) );

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

	/**
	 * @param WCS_Switch_Cart_Item $switch_cart_item
	 * @param float                $fee
	 */
	private function set_subscription_sign_up_fee( $switch_cart_item, $fee ) {
		$switch_cart_item->product->update_meta_data( '_subscription_sign_up_fee', $fee );
		$switch_cart_item->product->save();
	}

	public function tearDown() {
		parent::tearDown();

		$this->cart->empty_cart();
	}
}
