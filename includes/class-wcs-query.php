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

		add_filter( 'the_title', array( $this, 'change_endpoint_title' ), 11, 1 );

		if ( ! is_admin() ) {
			add_filter( 'query_vars', array( $this, 'add_query_vars' ), 0 );
			add_filter( 'woocommerce_get_breadcrumb', array( $this, 'add_breadcrumb' ), 10, 2 );
		}

		$this->init_query_vars();
	}

	/**
	 * Init query vars by loading options.
	 *
	 * @since 2.0
	 */
	public function init_query_vars() {
		WC()->query->query_vars['view-subscription'] = get_option( 'woocommerce_myaccount_view_subscriptions_endpoint', 'view-subscription' );
	}

	/**
	 * Adds endpoint breadcrumb when viewing subscription
	 *
	 * @param  array         $crumbs     already assembled breadcrumb data
	 * @param  WC_Breadcrumb $breadcrumb object responsible for assembling said data
	 * @return array         $crumbs     if we're on a view-subscription page, then augmented breadcrumb data
	 */
	public function add_breadcrumb( $crumbs, $breadcrumb ) {
		global $wp;

		if ( $this->is_query( 'view-subscription' ) ) {
			$crumbs[] = array( $this->get_endpoint_title( 'view-subscription' ) );
		}
		return $crumbs;
	}

	/**
	 * Changes page title on view subscription page
	 *
	 * @param  string $title original title
	 * @return string        changed title
	 */
	public function change_endpoint_title( $title ) {
		global $wp;

		if ( $this->is_query( 'view-subscription' ) && in_the_loop() ) {
			$title = $this->get_endpoint_title( 'view-subscription' );
		}
		return $title;
	}

	/**
	 * Set the subscription page title when viewing a subscription.
	 *
	 * @since 2.0
	 * @param $title
	 */
	public function get_endpoint_title( $endpoint ) {
		global $wp;

		switch ( $endpoint ) {
			case 'view-subscription':
				$subscription = wcs_get_subscription( $wp->query_vars['view-subscription'] );
				$title        = ( $subscription ) ? sprintf( _x( 'Subscription #%s', 'hash before order number', 'woocommerce-subscriptions' ), $subscription->get_order_number() ) : '';
				break;
			default:
				$title = '';
				break;
		}

		return $title;
	}

	/**
	 * Check if the current query is for a type we want to override.
	 *
	 * @param  string $query_var the string for a query to check for
	 * @return bool
	 */
	protected function is_query( $query_var ) {
		global $wp;

		if ( is_main_query() && is_page() && ! empty( $wp->query_vars[ $query_var ] ) ) {
			$is_view_subscription_query = true;
		} else {
			$is_view_subscription_query = false;
		}

		return apply_filters( 'wcs_query_is_query', $is_view_subscription_query, $query_var );
	}
}
new WCS_Query();
