<?php
/**
 * WooCommerce Subscriptions Query Handler
 *
 * @version		2.0
 * @author 		Prospress
 */
class WCS_Query extends WC_Query {

	public function __construct() {

		add_action( 'init', array( $this, 'add_endpoints' ) );

		add_filter( 'the_title', array( $this, 'get_endpoint_title' ), 11, 1 );

		if ( ! is_admin() ) {
			add_filter( 'query_vars', array( $this, 'add_query_vars'), 0 );
		}

		$this->init_query_vars();
	}

	/**
	 * Init query vars by loading options.
	 *
	 * @since 2.0
	 */
	public function init_query_vars() {
		$this->query_vars = array(
			'view-subscription' => get_option( 'woocommerce_myaccount_view_subscriptions_endpoint', 'view-subscription' ),
		);
	}

	/**
	 * Set the subscription page title when viewing a subscription.
	 *
	 * @since 2.0
	 * @param $title
	 */
	public function get_endpoint_title( $title ) {
		global $wp;

		if ( is_main_query() && in_the_loop() && is_page() && ! empty( $wp->query_vars['view-subscription'] ) ) {
			$subscription = wcs_get_subscription( $wp->query_vars['view-subscription'] );
			$title        = ( $subscription ) ? sprintf( __( 'Subscription %s', 'woocommerce' ), _x( '#', 'hash before order number', 'woocommerce' ) . $subscription->get_order_number() ) : '';
		}

		return $title;
	}
}
new WCS_Query();