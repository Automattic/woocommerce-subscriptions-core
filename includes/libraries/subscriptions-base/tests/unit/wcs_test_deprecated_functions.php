<?php
/**
 *
 */
class WCS_Deprecated_Functions_Unit_Tests extends WCS_Unit_Test_Case {

	public function setUp() {
		parent::setUp();

		add_filter( 'woocommerce_order_item_get_subtotal', array( $this, 'return_0_if_empty' ) );
	}

	public function tearDown() {
		global $wpdb;

		remove_action( 'before_delete_post', 'WC_Subscriptions_Manager::maybe_cancel_subscription', 10 );
		_delete_all_posts();

		// Delete line items
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}woocommerce_order_items" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}woocommerce_order_itemmeta" );

		$this->commit_transaction();
		parent::tearDown();
		add_action( 'before_delete_post', 'WC_Subscriptions_Manager::maybe_cancel_subscription', 10, 1 );
	}

	/**
	 * includes/wcs-deprecated-functions.php
	 */
	public function test_wcs_get_old_subscription_key() {
		$subscription = WCS_Helper_Subscription::create_subscription( array( 'status' => 'active' ) );

		$product = WCS_Helper_Product::create_simple_subscription_product();

		WCS_Helper_Subscription::add_product( $subscription, $product );
		$subscription->save();

		$key_should_be = $subscription->get_id() . '_' . $product->get_id();

		$key = wcs_get_old_subscription_key( $subscription );

		$this->assertEquals( $key_should_be, wcs_get_old_subscription_key( $subscription ) );
	}

	public function wcs_get_singular_garbage_datas() {
		return array(
			array( false ),
			array( true ),
			array( null ),
			array( -1 ),
			array( new WP_Error( 'foo' ) ),
			array( 'foo' ),
			array( '' ),
			array( array( 4 ) ),
			array( new stdClass() ),
		);
	}

	/**
	 * includes/wcs_deprecated-functions.php
	 */
	public function test_wcs_get_subscription_id_from_key() {
		$product = WCS_Helper_Product::create_simple_subscription_product();

		$order = WCS_Helper_Subscription::create_order();
		WCS_Helper_Subscription::add_product( $order, $product );

		$subscription = WCS_Helper_Subscription::create_subscription( array(
			'status' => 'active',
			'order_id' => wcs_get_objects_property( $order, 'id' ),
		) );
		WCS_Helper_Subscription::add_product( $subscription, $product );

		$subscription_key = wcs_get_objects_property( $order, 'id' ) . '_' . $product->get_id();

		$broken_subscription_key = wcs_get_objects_property( $order, 'id' );

		$this->assertEquals( $subscription->get_id(), wcs_get_subscription_id_from_key( $subscription_key ) );
		$this->assertEquals( $subscription->get_id(), wcs_get_subscription_id_from_key( $broken_subscription_key ) );
	}

	/**
	 * @dataProvider wcs_get_singular_garbage_datas
	 */
	public function test_wcs_get_subscription_id_from_key_fail( $input ) {
		$this->assertNull( wcs_get_subscription_id_from_key( $input ) );
	}


	/**
	 * Pretty much the same setup as the id from key
	 *
	 * includes/wcs_deprecated-functions.php
	 */
	public function test_wcs_get_subscription_from_key() {
		$product = WCS_Helper_Product::create_simple_subscription_product();

		$order = WCS_Helper_Subscription::create_order();
		WCS_Helper_Subscription::add_product( $order, $product );

		if ( is_callable( array( $order, 'save' ) ) ) {
			$order->save();
		}

		$subscription = WCS_Helper_Subscription::create_subscription( array(
			'status'   => 'active',
			'order_id' => wcs_get_objects_property( $order, 'id' ),
		) );
		WCS_Helper_Subscription::add_product( $subscription, $product );
		$subscription->save();
		$subscription = wcs_get_subscription( $subscription->get_id() );

		$subscription_key = wcs_get_objects_property( $order, 'id' ) . '_' . $product->get_id();

		$broken_subscription_key = wcs_get_objects_property( $order, 'id' );

		$this->assertEquals( $subscription, wcs_get_subscription_from_key( $subscription_key ) );
		$this->assertEquals( $subscription, wcs_get_subscription_from_key( $broken_subscription_key ) );
	}

	/**
	 * @dataProvider wcs_get_singular_garbage_datas
	 * @expectedException PHPUnit_Framework_Error
	 */
	public function test_wcs_get_subscription_from_key_fail( $input ) {
		if ( ! method_exists( 'PHPUnit_Runner_Version', 'id' ) || version_compare( PHPUnit_Runner_Version::id(), '6.0', '>=' ) ) {
			$this->setExpectedException( '\PHPUnit\Framework\Error\Notice' );
		}
		$this->assertNull( wcs_get_subscription_from_key( $input ) );
	}

	/**
	 * includes/wcs_deprecated-functions.php
	 */
	public function test_wcs_get_subscription_in_deprecated_structure() {
		$product = WCS_Helper_Product::create_simple_subscription_product( array( 'price' => 10 ) );

		$order = WCS_Helper_Subscription::create_order();
		WCS_Helper_Subscription::add_product( $order, $product );

		$subscription = WCS_Helper_Subscription::create_subscription( array(
			'status' => 'active',
			'order_id' => wcs_get_objects_property( $order, 'id' ),
		) );
		WCS_Helper_Subscription::add_product( $subscription, $product );

		$subscription->payment_complete();

		$deprecated_subscription = wcs_get_subscription_in_deprecated_structure( $subscription );

		$this->assertArrayHasKey( 'order_id', $deprecated_subscription );
		$this->assertEquals( wcs_get_objects_property( $order, 'id' ), $deprecated_subscription['order_id'] );

		$this->assertArrayHasKey( 'product_id', $deprecated_subscription );
		$this->assertEquals( $product->get_id(), $deprecated_subscription['product_id'] );

		$this->assertArrayHasKey( 'variation_id', $deprecated_subscription );
		$this->assertEquals( 0, $deprecated_subscription['variation_id'] );

		$this->assertArrayHasKey( 'status', $deprecated_subscription );
		$this->assertEquals( 'active', $deprecated_subscription['status'] );

		$this->assertArrayHasKey( 'period', $deprecated_subscription );
		$this->assertEquals( 'month', $deprecated_subscription['period'] );

		$this->assertArrayHasKey( 'interval', $deprecated_subscription );
		$this->assertEquals( '1', $deprecated_subscription['interval'] );

		$this->assertArrayHasKey( 'length', $deprecated_subscription );
		$this->assertEquals( 0, $deprecated_subscription['length'] );

		$this->assertArrayHasKey( 'expiry_date', $deprecated_subscription );
		$this->assertEquals( '0', $deprecated_subscription['expiry_date'] );

		$this->assertArrayHasKey( 'end_date', $deprecated_subscription );
		$this->assertEquals( '0', $deprecated_subscription['end_date'] );

		$this->assertArrayHasKey( 'failed_payments', $deprecated_subscription );
		$this->assertEquals( 0, $deprecated_subscription['failed_payments'] );

		$this->assertArrayHasKey( 'completed_payments', $deprecated_subscription );
		$this->assertCount( 1, $deprecated_subscription['completed_payments'] );

		$this->assertArrayHasKey( 'suspension_count', $deprecated_subscription );
		$this->assertEquals( '0', $deprecated_subscription['suspension_count'] );

		$this->assertArrayHasKey( 'last_payment_date', $deprecated_subscription );
		$this->assertEquals( $deprecated_subscription['completed_payments'][0], $deprecated_subscription['last_payment_date'] );
	}

	// public function test_wc_subscriptions_get_subscriptions() {

	// }

	// public function test_wc_subscriptions_add_months() {

	// }

	// public function test_wc_subscriptions_get_total_subscription_count() {

	// }

	// public function test_wc_subscriptions_get_subscription_status_counts() {

	// }

	// public function test_wc_subscriptions_get_subscription_count() {

	// }

	// public function test_wc_subscription_cart_cart_contains_subscription_renewal() {

	// }

	// public function test_wc_subscription_cart_cart_contains_failed_renewal_order_payment() {

	// }

	// public function test_wc_subscription_cart_get_formatted_discounts_before_tax() {

	// }

	// public function test_wc_subscription_cart_get_formatted_discounts_after_tax() {

	// }

	// public function test_wc_subscription_cart_cart_totals_fee_html() {

	// }

	// public function test_wc_subscription_cart_get_formatted_cart_total() {

	// }

	// public function test_wc_subscription_cart_get_recurring_tax_totals() {

	// }

	// public function test_wc_subscription_cart_get_taxes_total_html() {

	// }

	// public function test_wc_subscription_cart_get_formatted_total() {

	// }

	// public function test_wc_subscription_cart_get_formatted_total_ex_tax() {

	// }

	// public function test_wc_subscription_cart_get_recurring_totals_fields() {

	// }

	// public function test_wc_subscription_cart_get_cart_subscription_period() {

	// }

	// public function test_wc_subscription_cart_get_cart_subscription_interval() {

	// }

	// public function test_wc_subscription_cart_get_cart_subscription_length() {

	// }

	// public function test_wc_subscription_cart_get_cart_subscription_trial_length() {

	// }

	// public function test_wc_subscription_cart_get_cart_subscription_trial_period() {

	// }

	// public function test_wc_subscription_cart_get_recurring_cart_contents_total() {

	// }

	// public function test_wc_subscription_cart_get_recurring_subtotal_ex_tax() {

	// }

	// public function test_wc_subscription_cart_get_recurring_subtotal() {

	// }

	// public function test_wc_subscription_cart_get_recurring_discount_cart() {

	// }

	// public function test_wc_subscription_cart_cart_totals_fee_html() {

	// }

	// public function test_wc_subscription_cart_get_recurring_discount_cart_tax() {

	// }

	// public function test_wc_subscription_cart_get_recurring_discount_total() {

	// }

	// public function test_wc_subscription_cart_get_recurring_shipping_tax_total() {

	// }

	// public function test_wc_subscription_cart_get_recurring_shipping_total() {

	// }

	// public function test_wc_subscription_cart_get_recurring_taxes() {

	// }

	// public function test_wc_subscription_cart_get_recurring_fees() {

	// }

	// public function test_wc_subscription_cart_get_recurring_taxes_total() {

	// }

	// public function test_wc_subscription_cart_get_recurring_total_tax() {

	// }

	// public function test_wc_subscription_cart_get_recurring_total_ex_tax() {

	// }

	// public function test_wc_subscription_cart_get_recurring_total() {

	// }

	// public function test_wc_subscription_cart_calculate_recurring_shipping() {

	// }

	// public function test_wc_subscription_cart_get_cart_subscription_string() {

	// }

	// public function test_wc_subscription_cart_set_calculated_total() {

	// }

	// public function test_wc_subscription_cart_get_items_product_id() {

	// }

	// public function test_wc_subscription_cart_increase_coupon_discount_amount() {

	// }

	// public function test_wcs_change_gateway_update_recurring_payment_method() {

	// }

	// public function test_wcs_change_gateway_can_subscription_be_changed_to() {

	// }

	// public function test_wcs_checkout_filter_woocommerce_create_order() {

	// }

	// public function test_wcs_checkout_filter_woocommerce_my_account_my_orders_actions() {

	// }

	// public function test_wcs_coupon_cart_contains_recurring_discount() {

	// }

	// public function test_wcs_coupon_cart_contains_sign_up_discount() {

	// }

	// public function test_wcs_coupon_restore_coupons() {

	// }

	// public function test_wcs_coupon_apply_subscription_discount_before_tax() {

	// }

	// public function test_wcs_coupon_apply_subscription_discount_after_tax() {

	// }

	// public function test_wcs_email_send_subscription_email() {

	// }

	// public function test_wcs_manager_process_subscription_payment() {

	// }

	// public function test_wcs_manager_process_subscription_payment_failure() {

	// }

	// public function test_wcs_manager_create_pending_subscription_for_order() {

	// }

	// public function test_wcs_manager_process_subscriptions_on_checkout() {

	// }

	// public function test_wcs_manager_update_users_subscriptions_for_order() {

	// }

	// public function test_wcs_manager_update_users_subscriptions() {

	// }

	// public function test_wcs_manager_update_subscription() {

	// }

	// public function test_wcs_manager_maybe_change_users_subscription() {

	// }

	// public function test_wcs_manager_can_subscription_be_changed_to() {

	// }

	// public function test_wcs_manager_get_subscription() {

	// }

	// public function test_wcs_manager_get_status_to_display() {

	// }

	// public function test_wcs_manager_get_subscription_period_strings() {

	// }

	// public function test_wcs_manager_get_subscription_period_interval_strings() {

	// }

	// public function test_wcs_manager_get_subscription_ranges() {

	// }

	// public function test_wcs_manager_get_subscription_trial_lengths() {

	// }

	// public function test_wcs_manager_get_subscription_trial_period_strings() {

	// }

	// public function test_wcs_manager_get_available_time_periods() {

	// }

	// public function test_wcs_manager_get_subscription_key() {

	// }

	// public function test_wcs_manager_get_subscriptions_failed_payment_count() {

	// }

	// public function test_wcs_manager_get_subscriptions_completed_payment_count() {

	// }

	// public function test_wcs_manager_get_subscription_expiration_date() {

	// }

	// public function test_wcs_manager_set_expiration_date() {

	// }

	// public function test_wcs_manager_calculate_subscription_expiration_date() {

	// }

	// public function test_wcs_manager_get_next_payment_date() {

	// }

	// public function test_wcs_manager_set_next_payment_date() {

	// }

	// public function test_wcs_manager_get_last_payment_date() {

	// }

	// public function test_wcs_manager_update_wp_cron_lock() {

	// }

	// public function test_wcs_manager_calculate_next_payment_date() {

	// }

	// public function test_wcs_manager_get_trial_expiration_date() {

	// }

	// public function test_wcs_manager_set_trial_expiration_date() {

	// }

	// public function test_wcs_manager_calculate_trial_expiration_date() {

	// }

	// public function test_wcs_manager_get_user_id_from_subscription_key() {

	// }

	// public function test_wcs_manager_requires_manual_renewal() {

	// }

	// public function test_wcs_manager_subscription_requires_payment() {

	// }

	// public function test_wcs_manager_user_owns_subscription() {

	// }

	// public function test_wcs_manager_user_has_subscription() {

	// }

	// public function test_wcs_manager_get_all_users_subscriptions() {

	// }

	// public function test_wcs_manager_get_users_subscriptions() {

	// }

	// public function test_wcs_manager_make_user_inactive() {

	// }

	// public function test_wcs_manager_maybe_assign_user_cancelled_role() {

	// }

	// public function test_wcs_manager_update_users_role() {

	// }

	// public function test_wcs_manager_mark_paying_customer() {

	// }

	// public function test_wcs_manager_mark_not_paying_customer() {

	// }

	// public function test_wcs_manager_get_users_change_status_link() {

	// }

	// public function test_wcs_manager_update_next_payment_date() {

	// }

	// public function test_wcs_manager_get_subscription_price_string() {

	// }

	// public function test_wcs_manager_maybe_put_subscription_on_hold() {

	// }

	// public function test_wcs_manager_maybe_process_subscription_payment() {

	// }

	// public function test_wcs_manager_current_user_can_suspend_subscription() {

	// }

	// public function test_wcs_manager_search_subscriptions() {

	// }

	// public function test_wcs_manager_activate_subscription() {

	// }

	// public function test_wcs_manager_reactivate_subscription() {

	// }

	// public function test_wcs_manager_put_subscription_on_hold() {

	// }

	// public function test_wcs_manager_cancel_subscription() {

	// }

	// public function test_wcs_manager_failed_subscription_signup() {

	// }

	// public function test_wcs_manager_trash_subscription() {

	// }

	// public function test_wcs_manager_delete_subscription() {

	// }

	// public function test_wcs_manager_ajax_update_next_payment_date() {

	// }

	// public function test_wcs_manager_safeguard_scheduled_payments() {

	// }

	// public function test_wcs_manager_maybe_reschedule_subscription_payment() {

	// }

	// public function test_wcs_order_is_order_editable() {

	// }

	// public function test_wcs_order_get_price_per_period() {

	// }

	// public function test_wcs_order_generate_renewal_order() {

	// }

	// public function test_wcs_order_maybe_send_customer_renewal_order_email() {

	// }

	// public function test_wcs_order_send_customer_renewal_order_email() {

	// }

	// public function test_wcs_order_is_renewal() {

	// }

	// public function test_wcs_order_record_order_payment() {

	// }

	// public function test_wcs_order_is_item_a_subscription() {

	// }

	// public function test_wcs_order_get_item() {

	// }

	// public function test_wcs_order_get_recurring_total_proportion() {

	// }

	// public function test_wcs_order_order_contains_subscription() {

	// }

	// public function test_wcs_order_set_recurring_payment_method() {

	// }

	// public function test_wcs_order_is_download_permitted() {

	// }

	// public function test_wcs_order_prefill_order_item_meta() {

	// }

	// public function test_wcs_order_calculate_recurring_line_taxes() {

	// }

	// public function test_wcs_order_remove_line_tax() {

	// }

	// public function test_wcs_order_add_line_tax() {

	// }

	// public function test_wcs_order_get_total_initial_payment() {

	// }

	// public function test_wcs_order_get_item_recurring_amount() {

	// }

	// public function test_wcs_order_get_recurring_discount_cart() {

	// }

	// public function test_wcs_order_get_recurring_discount_cart_tax() {

	// }

	// public function test_wcs_order_get_recurring_discount_total() {

	// }

	// public function test_wcs_order_get_recurring_shipping_tax_total() {

	// }

	// public function test_wcs_order_get_recurring_shipping_total() {

	// }

	// public function test_wcs_order_get_recurring_shipping_methods() {

	// }

	// public function test_wcs_order_get_recurring_taxes() {

	// }

	// public function test_wcs_order_get_recurring_total_tax() {

	// }

	// public function test_wcs_order_get_recurring_total_ex_tax() {

	// }

	// public function test_wcs_order_get_recurring_total() {

	// }

	// public function test_wcs_order_get_order_subscription_string() {

	// }

	// public function test_wcs_order_get_recurring_items() {

	// }

	// public function test_wcs_order_get_subscription_period() {

	// }

	// public function test_wcs_order_() {

	// }

	// public function test_wcs_order_get_subscription_interval() {

	// }

	// public function test_wcs_order_get_subscription_length() {

	// }

	// public function test_wcs_order_get_subscription_trial_length() {

	// }

	// public function test_wcs_order_get_subscription_trial_period() {

	// }

	// public function test_wcs_order_get_next_payment_timestamp() {

	// }

	// public function test_wcs_order_get_next_payment_date() {

	// }

	// public function test_wcs_order_get_last_payment_date() {

	// }

	// public function test_wcs_order_calculate_next_payment_date() {

	// }

	// public function test_wcs_order_get_failed_payment_count() {

	// }

	// public function test_wcs_order_get_outstanding_balance() {

	// }

	// public function test_wcs_order_safeguard_scheduled_payments() {

	// }

	// public function test_wcs_order_get_formatted_line_total() {

	// }

	// public function test_wcs_order_get_subtotal_to_display() {

	// }

	// public function test_wcs_order_get_cart_discount_to_display() {

	// }

	// public function test_wcs_order_get_order_discount_to_display() {

	// }

	// public function test_wcs_order_get_formatted_order_total() {

	// }

	// public function test_wcs_order_get_shipping_to_display() {

	// }

	// public function test_wcs_order_get_order_item_totals() {

	// }

	// public function test_wcs_order_load_order_data() {

	// }

	// public function test_wcs_order_order_shipping_method() {

	// }

	// public function test_wcs_order_get_item_sign_up_fee() {

	// }

	// public function test_wcs_order_maybe_record_order_payment() {

	// }

	// public function test_wcs_renewal_order_generate_paid_renewal_order() {

	// }

	// public function test_wcs_renewal_order_generate_failed_payment_renewal_order() {

	// }

	// public function test_wcs_renewal_order_maybe_generate_manual_renewal_order() {

	// }

	// public function test_wcs_renewal_order_get_parent_order_id() {

	// }

	// public function test_wcs_renewal_order_get_parent_order() {

	// }

	// public function test_wcs_renewal_order_get_renewal_order_count() {

	// }

	// public function test_wcs_renewal_order_get_users_renewal_link() {

	// }

	// public function test_wcs_renewal_order_get_users_renewal_link_for_product() {

	// }

	// public function test_wcs_renewal_order_can_subscription_be_renewed() {

	// }

	// public function test_wcs_renewal_order_generate_renewal_order() {

	// }

	// public function test_wcs_renewal_order_is_purchasable() {

	// }

	// public function test_wcs_renewal_order_get_renewal_orders() {

	// }

	// public function test_wcs_renewal_order_get_checkout_payment_url() {

	// }

	// public function test_wcs_renewal_order_maybe_process_failed_renewal_order_payment() {

	// }

	// public function test_wcs_renewal_order_process_failed_renewal_order_payment() {

	// }

	// public function test_wcs_renewal_order_maybe_record_renewal_order_payment() {

	// }

	// public function test_wcs_renewal_order_maybe_record_renewal_order_payment_failure() {

	// }

	// public function test_wcs_renewal_order_process_subscription_payment_on_child_order() {

	// }

	// public function test_wcs_renewal_order_trigger_processed_failed_renewal_order_payment_hook() {

	// }

	// public function test_wcs_switcher_can_subscription_be_cancelled() {

	// }

	// public function test_wcs_switcher_add_switch_button() {

	// }

	// public function test_wcs_switcher_get_switch_link() {

	// }

	// public function test_wcs_switcher_can_subscription_be_changed_to() {

	// }

	// public function test_wcs_switcher_cart_contains_subscription_switch() {

	// }

	// public function test_wcs_switcher_customise_cart_subscription_string_details() {

	// }

	// public function test_wcs_switcher_calculate_first_payment_date() {

	// }

	// public function test_wcs_switcher_get_first_payment_date() {

	// }

	// public function test_wcs_switcher_add_switched_status_string() {

	// }

	// public function test_wcs_switcher_maybe_set_apporitioned_totals() {

	// }

	// public function test_wcs_switcher_order_contains_subscription_switch() {

	// }

	// public function test_wcs_synchronizer_customise_subscription_price_string() {

	// }

	// public function test_wcs_synchronizer_maybe_hide_free_trial() {

	// }

	// public function test_wcs_synchronizer_charge_shipping_up_front() {

	// }

	// public function test_wcs_synchronizer_get_first_payment_date() {

	// }

	// public function test_wcs_synchronizer_maybe_set_payment_date() {

	// }

	// public function test_wcs_synchronizer_order_contains_synced_subscription() {

	// }

	// public function test_wcs_synchronizer_add_order_meta() {

	// }

	// public function test_wcs_synchronizer_prefill_order_item_meta() {

	// }

	// public function test_wcs_synchronizer_get_sign_up_fee() {

	// }

	// public function test_wcs_synchronizer_cart_contains_prorated_subscription() {

	// }

	// public function test_wcs_admin_related_orders_meta_box() {

	// }

	// public function test_wcs_admin_clean_number() {

	// }

	// public function test_wcs_admin_() {

	// }

	// public function test_wcs_admin_() {

	// }

	// public function test_wcs_gateways_trigger_gateway_activated_subscription_hook() {

	// }

	// public function test_wcs_gateways_trigger_gateway_reactivated_subscription_hook() {

	// }

	// public function test_wcs_gateways_trigger_gateway_subscription_put_on_hold_hook() {

	// }

	// public function test_wcs_gateways_trigger_gateway_cancelled_subscription_hook() {

	// }

	// public function test_wcs_gateways_trigger_gateway_subscription_expired_hook() {

	// }

	// public function test_wcs_gateways_trigger_gateway_suspended_subscription_hook() {

	// }

	// public function test_wcs_paypal_cancel_subscription_with_paypal() {

	// }

	// public function test_wcs_paypal_suspend_subscription_with_paypal() {

	// }

	// public function test_wcs_paypal_reactivate_subscription_with_paypal() {

	// }

	// public function test_wcs_paypal_remove_renewal_order_meta() {

	// }

	// public function test_wcs_upgrader_is_user_upgraded_to_1_4() {

	// }
}
