<?php
/**
 * Class WCS_Subscription_Data_Store_CPT_Test
 *
 * @package WooCommerce\SubscriptionsCore\Tests
 */

/**
 * Test suite for the WCS_Subscription_Data_Store_CPT class
 */
class WCS_Subscription_Data_Store_CPT_Test extends WP_UnitTestCase {

	public function test_new_subscription_data_hook() {
		if ( wcs_is_custom_order_tables_usage_enabled() ) {
			$this->markTestSkipped( "Filter 'woocommerce_new_subscription_data' does not run for HPOS." );
		}
		$hook_callback = function ( $args ) {
			$args['post_title'] = 'Test Title';
			return $args;
		};
		add_filter( 'woocommerce_new_subscription_data', $hook_callback );
		$subscription_object = WCS_Helper_Subscription::create_subscription( array( 'status' => WC_Subscription::STATUS_ACTIVE ) );
		remove_filter( 'woocommerce_new_subscription_data', $hook_callback );

		$this->assertStringContainsString( 'Test Title', get_the_title( $subscription_object->get_id() ) );
	}
}
