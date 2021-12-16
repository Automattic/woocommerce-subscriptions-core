<?php
/**
 * A class to manage Subscriptions gateway restrictions.
 *
 * @since 4.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * WC_Subscriptions_Gateway_Restrictions_Manager class
 */
class WC_Subscriptions_Gateway_Restrictions_Manager {

	/**
	 * Initialize the class.
	 */
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
	}

	/**
	 * Registers and enqueues payment gateway specific scripts.
	 */
	public static function enqueue_scripts() {
		$screen    = get_current_screen();
		$screen_id = isset( $screen->id ) ? $screen->id : '';

		if ( 'product' === $screen_id ) {
			wp_enqueue_script(
				'woocommerce_subscriptions_payment_restrictions',
				WC_Subscriptions_Core_Plugin::instance()->get_subscriptions_core_directory_url( 'assets/js/admin/payment-method-restrictions.js' ),
				array( 'jquery', 'woocommerce_admin' ),
				filemtime( WC_Subscriptions_Core_Plugin::instance()->get_subscriptions_core_directory( 'assets/js/admin/payment-method-restrictions.js' ) ),
				true // Load in footer.
			);

			$decimals          = wc_get_price_decimals();
			$decimal_separator = wc_get_price_decimal_separator();
			$zero_price        = sprintf( get_woocommerce_price_format(), get_woocommerce_currency_symbol(), number_format( 0, $decimals, $decimal_separator, '' ) );

			$script_data = array(
				// Translators: placeholder is a 0 price formatted with the the store's currency and decimal settings.
				'i18n_zero_subscription_error' => sprintf( __( 'Please enter a price greater than %s.', 'woocommerce-subscriptions' ), $zero_price ),
				'number_of_decimal_places'     => $decimals,
				'decimal_point_separator'      => $decimal_separator,
			);

			wp_localize_script( 'woocommerce_subscriptions_payment_restrictions', 'wcs_gateway_restrictions', $script_data );
		}
	}
}

