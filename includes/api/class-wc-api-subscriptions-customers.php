<?php

/**
 * Class: WC_Subscription_API_Customers
 * extends @see WC_API_Customer to provide functionality to subscriptions
 *
 * @since 2.0
 */
class WC_API_Subscriptions_Customers extends WC_API_Customers {


	/**
	 * Register the routes for this class
	 *
	 * GET /customers/<id>/subscriptions
	 *
	 * @since 2.0
	 * @param array $routes
	 * @return array
	 */
	public function register_routes( $routes ) {
		$routes = parent::register_routes( $routes );

		# GET /customers/<id>/subscriptions
		$routes[ $this->base . '/(?P<id>\d+)/subscriptions' ] = array(
			array( array( $this, 'get_customer_susbcriptions' ), WC_API_SERVER::READABLE ),
		);

		return $routes;
	}

	/**
	 * WCS API function to get all the subscriptions tied to a particular customer.
	 *
	 * @since 2.0
	 * @param $id int
	 * @param $fields array
	 */
	public function get_customer_susbcriptions( $id, $fields = null ) {
		global $wpdb;

		// check the customer id given is a valid customer in the store. We're able to leech off WC-API for this.
		$id = $this->validate_request( $id, 'customer', 'read' );

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$subscription_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id
						FROM $wpdb->posts AS posts
						LEFT JOIN {$wpdb->postmeta} AS meta on posts.ID = meta.post_id
						WHERE meta.meta_key = '_customer_user'
						AND   meta.meta_value = '%s'
						AND   posts.post_type = 'shop_subscription'
						AND   posts.post_status IN ( '" . implode( "','", array_keys( wcs_get_subscription_statuses() ) ) . "' )
					", $id ) );

		$subscriptions = array();

		foreach( $subscription_ids as $subscription_id ) {
			$subscriptions[] = wcs_get_subscription( $subscription_id );
		}

		return array( 'customer_subscriptions' => apply_filters( 'wc_subscriptions_api_customer_subscriptions', $subscriptions, $id, $fields, $subscription_ids, $this->server ) );
	}
}
