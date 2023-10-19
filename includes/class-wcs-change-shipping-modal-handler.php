<?php
/**
 * A class to display and handle early renewal requests via the modal.
 *
 * @package    WooCommerce Subscriptions
 * @subpackage WCS_Early_Renewal
 * @category   Class
 * @since      2.6.0
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WCS_Change_Shipping_Modal_Handler {

	/**
	 * Attach callbacks.
	 *
	 * @since 2.6.0
	 */
	public static function init() {
		add_action( 'woocommerce_subscription_details_table', array( __CLASS__, 'maybe_print_early_renewal_modal' ) );
	}

	/**
	 * Prints the early renewal modal for a specific subscription. If eligible.
	 *
	 * @since 2.6.0
	 *
	 * @param WC_Subscription $subscription The subscription to print the modal for.
	 */
	public static function maybe_print_early_renewal_modal( $subscription ) {
		$place_order_action = array(
			'text'       => __( 'Change shipping method', 'woocommerce-subscriptions' ),
			'attributes' => array(
				'id'    => 'early_renewal_modal_submit',
				'class' => 'button alt ',
				'href'  => add_query_arg( array(
					'subscription_id'       => $subscription->get_id(),
					'process_early_renewal' => true,
					'wcs_nonce'             => wp_create_nonce( 'wcs-renew-early-modal-' . $subscription->get_id() ),
				) ),
				'data-payment-method' => $subscription->get_payment_method(),
			),
		);

		$callback_args = array(
			'callback'   => array( __CLASS__, 'output_early_renewal_modal' ),
			'parameters' => array( 'subscription' => $subscription ),
		);

		$modal = new WCS_Modal( $callback_args, '.change_address', 'callback', __( 'Renew early', 'woocommerce-subscriptions' ) );
		$modal->add_action( $place_order_action );
		$modal->print_html();
	}

	/**
	 * Prints the early renewal modal HTML.
	 *
	 * @since 2.6.0
	 * @param WC_Subscription $subscription The subscription to print the modal for.
	 */
	public static function output_early_renewal_modal( $subscription ) {
		$totals       = $subscription->get_order_item_totals();
		$date_changes = WCS_Early_Renewal_Manager::get_dates_to_update( $subscription );

		if ( isset( $totals['payment_method'] ) ) {
			$totals['payment_method']['label'] = __( 'Payment:', 'woocommerce-subscriptions' );
		}

		// Convert the new next payment date into the site's timezone.
		if ( ! empty( $date_changes['next_payment'] ) ) {
			$new_next_payment_date = new WC_DateTime( $date_changes['next_payment'], new DateTimeZone( 'UTC' ) );
			$new_next_payment_date->setTimezone( new DateTimeZone( wc_timezone_string() ) );
		} else {
			$new_next_payment_date = null;
		}

		wc_get_template(
			'html-change-shipping-method-modal-content.php',
			array(
				'subscription'          => $subscription,
				'totals'                => $totals,
				'new_next_payment_date' => $new_next_payment_date,
			),
			'',
			WC_Subscriptions_Core_Plugin::instance()->get_subscriptions_core_directory( 'templates/myaccount/' )
		);
	}
}
