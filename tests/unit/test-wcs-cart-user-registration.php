<?php
/**
 * Unit tests for requireing user registration on the checkout.
 */

class WCS_Cart_User_Registration_Test extends WP_UnitTestCase {

	/**
	 * Data Provider
	 *
	 * @return array
	 */
	public static function data_provider() {
		return array(
			// More details at https://github.com/Prospress/woocommerce-subscriptions/issues/1651#issuecomment-302155374
			array(
				array(
					'enable_guest_checkout' => true,
					'enable_signup'         => true,
					'users_can_register'    => true,
				),
				true,
			),
			array(
				array(
					'enable_guest_checkout' => true,
					'enable_signup'         => true,
					'users_can_register'    => false,
				),
				true,
			),
			array(
				array(
					'enable_guest_checkout' => false,
					'enable_signup'         => true,
					'users_can_register'    => true,
				),
				true,
			),
			array(
				array(
					'enable_guest_checkout' => true,
					'enable_signup'         => false,
					'users_can_register'    => true,
				),
				false,
			),
			array(
				array(
					'enable_guest_checkout' => true,
					'enable_signup'         => false,
					'users_can_register'    => false,
				),
				false,
			),
			array(
				array(
					'enable_guest_checkout' => false,
					'enable_signup'         => true,
					'users_can_register'    => false,
				),
				true,
			),
			array(
				array(
					'enable_guest_checkout' => false,
					'enable_signup'         => false,
					'users_can_register'    => true,
				),
				false,
			),
			array(
				array(
					'enable_guest_checkout' => false,
					'enable_signup'         => false,
					'users_can_register'    => false,
				),
				false,
			),

			// Registration enabled/disabled for subscriptions specifically
			array(
				array(
					'enable_guest_checkout'               => true,
					'enable_signup'                       => false,
					'users_can_register'                  => true,
					'subscription_customers_can_register' => true,
				),
				true,
			),
			array(
				array(
					'enable_guest_checkout'               => true,
					'enable_signup'                       => false,
					'users_can_register'                  => false,
					'subscription_customers_can_register' => true,
				),
				true,
			),
			array(
				array(
					'enable_guest_checkout'               => false,
					'enable_signup'                       => false,
					'users_can_register'                  => true,
					'subscription_customers_can_register' => true,
				),
				true,
			),
			array(
				array(
					'enable_guest_checkout'               => false,
					'enable_signup'                       => false,
					'users_can_register'                  => false,
					'subscription_customers_can_register' => true,
				),
				true,
			),
			array(
				array(
					'enable_guest_checkout'               => true,
					'enable_signup'                       => false,
					'users_can_register'                  => true,
					'subscription_customers_can_register' => false,
				),
				false,
			),
			array(
				array(
					'enable_guest_checkout'               => true,
					'enable_signup'                       => false,
					'users_can_register'                  => false,
					'subscription_customers_can_register' => false,
				),
				false,
			),
			array(
				array(
					'enable_guest_checkout'               => false,
					'enable_signup'                       => false,
					'users_can_register'                  => true,
					'subscription_customers_can_register' => false,
				),
				false,
			),
			array(
				array(
					'enable_guest_checkout'               => false,
					'enable_signup'                       => false,
					'users_can_register'                  => false,
					'subscription_customers_can_register' => false,
				),
				false,
			),
		);
	}

	/**
	 * @dataProvider data_provider
	 */
	public function test_user_registration( array $settings, $expected ) {
		// Create a global WC_Cart
		wc_empty_cart();

		// Add a subscription product to the cart
		add_filter( 'woocommerce_subscription_is_purchasable', '__return_true' );
		$product = WCS_Helper_Product::create_simple_subscription_product();
		WC()->cart->add_to_cart( $product->get_id() );
		remove_filter( 'woocommerce_subscription_is_purchasable', '__return_true' );

		$setting_map = array(
			'enable_guest_checkout'               => 'woocommerce_enable_guest_checkout',
			'enable_signup'                       => 'woocommerce_enable_signup_and_login_from_checkout',
			'users_can_register'                  => 'users_can_register',
			'subscription_customers_can_register' => 'woocommerce_enable_signup_from_checkout_for_subscriptions',
		);

		// Also set the values on update_option.
		foreach ( $settings as $key => $value ) {
			$value = 'users_can_register' === $key ? $value : wc_bool_to_string( $value );
			update_option( $setting_map[ $key ], $value );
		}

		// If not setting is passed in for subscription registration, disable it.
		if ( ! isset( $settings['subscription_customers_can_register'] ) ) {
			update_option( 'woocommerce_enable_signup_from_checkout_for_subscriptions', 'no' );
		}

		// Make sure there is no current user
		$GLOBALS['current_user'] = '';
		$this->assertFalse( is_user_logged_in(), 'Make sure there is no user logged in' );

		$this->assertTrue( WC()->checkout()->is_registration_required() );

		// Test the behaviour
		$this->assertEquals( $expected, WC()->checkout()->is_registration_enabled(), 'enable_signup' );
	}
}
