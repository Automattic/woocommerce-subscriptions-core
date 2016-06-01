<?php
/**
 * REST API Subscriptions controller
 *
 * Handles requests to the /subscription endpoint.
 *
 * @author   Prospres, Inc.
 * @since    2.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API Orders controller class.
 *
 * @package WooCommerce/API
 * @extends WC_REST_Posts_Controller
 */
class WC_REST_Subscriptions_Controller extends WC_REST_Orders_Controller {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'subscriptions';

	/**
	 * Post type.
	 *
	 * @var string
	 */
	protected $post_type = 'shop_subscription';

	/**
	 * Initialize orders actions and filters
	 */
	public function __construct() {
		add_filter( 'woocommerce_rest_prepare_shop_subscription', array( $this, 'filter_get_subscription_response' ), 10, 3 );
	}

	/**
	 * Filter WC_REST_Orders_Controller::get_item response for subscription post types
	 *
	 * @since 2.1
	 * @param WP_REST_Response $response
	 * @param WP_POST $post
	 */
	public function filter_get_subscription_response( $response, $post, $request ) {

		if ( ! empty( $post->post_type ) && ! empty( $post->ID ) && 'shop_subscription' == $post->post_type ) {
			$subscription = wcs_get_subscription( $post->ID );

			$response->data['billing_period']   = $subscription->billing_period;
			$response->data['billing_interval'] = $subscription->billing_interval;

			$response->data['schedule'] = array (
				'start_date'        => wc_rest_prepare_date_response( $subscription->get_date( 'start' ) ),
				'trial_end_date'    => wc_rest_prepare_date_response( $subscription->get_date( 'trial_end' ) ),
				'next_payment_date' => wc_rest_prepare_date_response( $subscription->get_date( 'next_payment' ) ),
				'end_date'          => wc_rest_prepare_date_response( $subscription->get_date( 'end' ) ),
			);
		}

		return $response;
	}

}