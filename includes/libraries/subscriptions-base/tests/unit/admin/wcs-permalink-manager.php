<?php

class WCS_Permalink_Manager_Test extends WCS_Unit_Test_Case {
	public function test_maybe_allow_permalink_update() {
		$options = array(
			'woocommerce_myaccount_subscriptions_endpoint',
			'woocommerce_myaccount_view_subscription_endpoint',
			'woocommerce_myaccount_subscription_payment_method_endpoint',
		);

		$repeated_value = 'REPEATED';
		$that           = $this;

		// Assign a default option value.
		array_map( function ( $option ) {
			update_option( $option, $option );
		}, $options );

		// Set post value.
		array_map( function ( $option ) use ( $repeated_value ) {
			$_POST[ $option ] = $repeated_value;
		}, $options );

		// Try to update the option.
		array_map( function ( $option ) use ( $repeated_value, $that ) {
			update_option( $option, $repeated_value );
			$that->assertEquals( get_option( $option ), $option );
		}, $options );
	}
}
