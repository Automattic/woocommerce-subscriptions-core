<?php
/**
 * Class WCS_Permalink_Manager_Test
 *
 * @package WooCommerce\SubscriptionsCore\Tests
 */

/**
 * Test suite for the WCS_Permalink_Manager class.
 */
class WCS_Permalink_Manager_Test extends WP_UnitTestCase {

	/**
	 * Make sure updating a subscriptions-related permalink option will WCS_Permalink_Manager::maybe_allow_permalink_update()
	 */
	public function test_maybe_allow_permalink_update() {
		$options = [
			'woocommerce_myaccount_subscriptions_endpoint',
			'woocommerce_myaccount_view_subscription_endpoint',
			'woocommerce_myaccount_subscription_payment_method_endpoint',
		];

		$repeated_value = 'REPEATED';

		// Set up - set the permalink option value to all permalink values in $_POST.
		foreach ( $options as $option ) {
			$_POST[ $option ] = $option;
		}

		// Confirm the permalink options can all be set to different values.
		foreach ( $options as $option ) {
			// Assign a default option value.
			update_option( $option, $option );

			// Confirm it's still set to the old value.
			$this->assertEquals( get_option( $option ), $option );
		}

		// Set up - set the same value to all option values in $_POST.
		foreach ( $options as $option ) {
			// Set post value.
			$_POST[ $option ] = $repeated_value;
		}

		// Confirm the permalink options cannot be set to all the same value.
		foreach ( $options as $option ) {
			// Try to update the option.
			update_option( $option, $repeated_value );

			// Confirm it's still set to the old value.
			$this->assertEquals( get_option( $option ), $option );
		}
	}
}
