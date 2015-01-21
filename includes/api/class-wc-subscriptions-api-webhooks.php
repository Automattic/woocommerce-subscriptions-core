<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * TODO: Might not need the API for this. It should be able to just use the WC_API
 * to create the webhooks related to Subscriptions.
 */
class WC_API_Subscription_Webhook extends WC_API_Webhook {

	/* @var string $base the route base */
	protected $base = '/webhooks';


	/**
	 * Register the routes for this class
	 *
	 * GET|POST /webhooks
	 * GET /webhooks/count
	 * GET|PUT|DELETE /webhooks/<id>
	 * GET /subscriptions/<subscription_id>/notes/<id>
	 * GET /subscriptions/<subscription_id>/orders
	 *
	 * @since 2.0
	 * @param array $routes
	 * @return array
	 */
	public function register_routes( $routes ) {
		// grab all existing webhook routes registed in WC_API_Webhook
		$routes = parent::register_routes( $routes );

		return $routes;
	}

	/**
	 * Create an webhook
	 *
	 * @since 2.0
	 * @param array $data parsed webhook data
	 * @return array
	 */
	public function create_webhook( $data ) {
		
	}



}
