<?php
/**
 * WooCommerce Subscriptions staging mode handler.
 *
 * @package WooCommerce Subscriptions
 * @author  Prospress
 * @since   2.3.0
 */
class WCS_Staging {

	/**
	 * Attach callbacks.
	 */
	public static function init() {
		add_action( 'woocommerce_generated_manual_renewal_order', array( __CLASS__, 'maybe_record_staging_site_renewal' ) );
	}

	/**
	 * Add an order note to a renewal order to record when it was created under staging site conditions.
	 *
	 * @param int $renewal_order_id The renewal order ID.
	 * @since 2.3.0
	 */
	public static function maybe_record_staging_site_renewal( $renewal_order_id ) {

		if ( ! WC_Subscriptions::is_duplicate_site() ) {
			return;
		}

		$renewal_order = wc_get_order( $renewal_order_id );

		if ( $renewal_order ) {
			$renewal_order->add_order_note( __( 'Payment processing skipped - renewal order created under staging site lock.', 'woocommerce-subscriptions' ) );
		}
	}
}
