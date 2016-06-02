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
	 * Register the routes for orders.
	 */
	public function register_routes() {
		parent::register_routes();

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/orders', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_subscription_orders' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => $this->get_collection_params(),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/statuses', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_statuses' ),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );
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

	/**
	 * Get subscription orders
	 *
	 * @since 2.1
	 * @param WP_REST_Request $request
	 * @return WP_Error|WP_REST_Response $response
	 */
	public function get_subscription_orders( $request ) {
		$id = (int) $request['id'];

		if ( empty( $id ) || ! wcs_is_subscription( $id ) ) {
			return new WP_Error( 'woocommerce_rest_invalid_shop_subscription_id', __( 'Invalid subscription id.', 'woocommerce-subscriptions' ), array( 'status' => 404 ) );
		}

		$this->post_type     = 'shop_order';
		$subscription        = wcs_get_subscription( $id );
		$subscription_orders = $subscription->get_related_orders();

		$orders = array();

		foreach ( $subscription_orders as $order_id ) {
			$post = get_post( $order_id );
			if ( ! wc_rest_check_post_permissions( $this->post_type, 'read', $post->ID ) ) {
				continue;
			}

			$response = $this->prepare_item_for_response( $post, $request );

			foreach ( array( 'parent', 'renewal', 'switch', 'resubscribe' ) as $order_type ) {
				if ( wcs_order_contains_subscription( $order_id, $order_type ) ) {
					$response->data['order_type'] = $order_type . '_order';
					break;
				}
			}

			$orders[] = $this->prepare_response_for_collection( $response );
		}

		$response = rest_ensure_response( $orders );
		$response->header( 'X-WP-Total', count( $orders ) );
		$response->header( 'X-WP-TotalPages', 1 );

		return apply_filters( 'wcs_rest_subscription_orders_response', $response, $request );
	}

	/**
	 * Get subscription statuses
	 *
	 * @since 2.1
	 */
	public function get_statuses() {
		return rest_ensure_response( wcs_get_subscription_statuses() );
	}

	/**
	 * Overrides WC_REST_Orders_Controller::get_order_statuses() so that subscription statuses are
	 * validated correctly in WC_REST_Orders_Controller::get_collection_params()
	 *
	 * @since 2.1
	 */
	protected function get_order_statuses() {
		return array_keys( wcs_get_subscription_statuses() );
	}
}