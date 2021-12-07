<?php
/**
 * Subscriptions Core Payment Gateways
 * Hooks into the WooCommerce payment gateways class to add subscription specific functionality.
 *
 * @since 4.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * WC_Subscriptions_Gateway_Restrictions_Manager class
 */
class WC_Subscriptions_Gateway_Restrictions_Manager {

	/**
	 *
	 */
	const VALIDATE_SUBSCRIPTION_TOTAL_AJAX_ACTION = 'wc_subscriptions_validate_recurring_total';

	/**
	 * Initialize the class.
	 */
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );

		add_action( 'wp_ajax_' . self::VALIDATE_SUBSCRIPTION_TOTAL_AJAX_ACTION, array( __CLASS__, 'validate_subscription_recurring_total' ) );
	}

	/**
	 * Registers and enqueues payment gateway specific scripts.
	 */
	public static function enqueue_scripts() {
		$screen    = get_current_screen();
		$screen_id = isset( $screen->id ) ? $screen->id : '';

		if ( in_array( $screen_id, array( 'shop_subscription', 'product' ), true ) ) {
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
				'i18n_zero_subscription_error'    => sprintf( __( 'Please enter a price greater than %s.', 'woocommerce-subscriptions' ), $zero_price ),
				'i18n_zero_recurring_total_error' => sprintf( __( 'The subscription must have a recurring total greater than %s. The subscription status will be set to "pending".', 'woocommerce-subscriptions' ), $zero_price ),
				'number_of_decimal_places'        => $decimals,
				'decimal_point_separator'         => $decimal_separator,
				'ajax_url'                        => admin_url( 'admin-ajax.php' ),
				'validate_recurring_total_action' => self::VALIDATE_SUBSCRIPTION_TOTAL_AJAX_ACTION,
				'security'                        => wp_create_nonce( 'wc_subscriptions_gateway_restrictions' ),
			);

			wp_localize_script( 'woocommerce_subscriptions_payment_restrictions', 'wcs_gateway_restrictions', $script_data );
		}
	}

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	public static function validate_subscription_recurring_total() {
		check_admin_referer( 'wc_subscriptions_gateway_restrictions', 'security' );

		if ( ! isset( $_POST['subscription_id'] ) ) {
			return -1;
		}

		$subscription = wcs_get_subscription( absint( $_POST['subscription_id'] ) );

		if ( ! $subscription ) {
			return -1;
		}

		$subscription_total_is_zero = $subscription->get_total() <= 0;

		if ( $subscription_total_is_zero && $subscription->is_manual() && $subscription->can_be_updated_to( 'on-hold' ) ) {
			$subscription->update_status( 'on-hold' );
		}

		wp_send_json( array( 'has_zero_recurring_total' => wc_bool_to_string( $subscription_total_is_zero ) ) );
	}
}

