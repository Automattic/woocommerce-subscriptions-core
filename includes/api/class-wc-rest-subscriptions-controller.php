<?php
/**
 * REST API Subscriptions controller
 *
 * Handles requests to the /subscriptions endpoint.
 *
 * @package WooCommerce Subscriptions\Rest Api
 * @since   3.1.0
 */

defined( 'ABSPATH' ) || exit;

class WC_REST_Subscriptions_Controller extends WC_REST_Orders_Controller {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'subscriptions';

	/**
	 * The post type.
	 *
	 * @var string
	 */
	protected $post_type = 'shop_subscription';

	/**
	 * Register the routes for the subscriptions endpoint.
	 *
	 * -- Inherited --
	 * GET|POST /subscriptions
	 * GET|PUT|DELETE /subscriptions/<subscription_id>
	 *
	 * -- Subscription specific --
	 * GET /subscriptions/status
	 * GET /subscriptions/<subscription_id>/orders
	 *
	 * @since 3.1.0
	 */
	public function register_routes() {
		parent::register_routes();

		register_rest_route( $this->namespace, "/{$this->rest_base}/statuses", array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_statuses' ),
				'permission_callback' => '__return_true',
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );

		register_rest_route( $this->namespace, "/{$this->rest_base}/(?P<id>[\d]+)/orders", array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_subscription_orders' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => $this->get_collection_params(),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );
	}

	/**
	 * Prepare a single subscription output for response.
	 *
	 * @since  3.1.0
	 *
	 * @param  WC_Data         $object  Subscription object.
	 * @param  WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function prepare_object_for_response( $object, $request ) {
		$response = parent::prepare_object_for_response( $object, $request );

		// When generating the `/subscriptions/[id]/orders` response this function is called to generate related-order data so exit early if this isn't a subscription.
		if ( ! wcs_is_subscription( $object ) ) {
			return $response;
		}

		// Add subscription specific data to the base order response data.
		$response->data['billing_period']   = $object->get_billing_period();
		$response->data['billing_interval'] = $object->get_billing_interval();

		foreach ( wcs_get_subscription_date_types() as $date_type => $date_name ) {
			$date = $object->get_date( $date_type );
			$response->data[ $date_type . '_date_gmt' ] = ( ! empty( $date ) ) ? wc_rest_prepare_date_response( $date ) : '';
		}

		// Include resubscribe data.
		$resubscribed_subscriptions                  = array_filter( $object->get_related_orders( 'ids', 'resubscribe' ), 'wcs_is_subscription' );
		$response->data['resubscribed_from']         = strval( $object->get_meta( '_subscription_resubscribe' ) );
		$response->data['resubscribed_subscription'] = strval( reset( $resubscribed_subscriptions ) ); // Subscriptions can only be resubscribed to once so return the first and only element.

		// Include the removed line items.
		$response->data['removed_line_items'] = array();

		foreach ( $object->get_items( 'line_item_removed' ) as $item ) {
			$response->data['removed_line_items'][] = $this->get_order_item_data( $item );
		}

		return $response;
	}

	/**
	 * Gets the /subscriptions/statuses response.
	 *
	 * @since 3.1.0
	 * @return WP_REST_Response The response object.
	 */
	public function get_statuses() {
		return rest_ensure_response( wcs_get_subscription_statuses() );
	}

	/**
	 * Gets the /subscriptions/[id]/orders response.
	 *
	 * @since 3.1.0
	 *
	 * @param WP_REST_Request            $request  The request object.
	 * @return WP_Error|WP_REST_Response $response The response or an error if one occurs.
	 */
	public function get_subscription_orders( $request ) {
		$id = absint( $request['id'] );

		if ( empty( $id ) || ! wcs_is_subscription( $id ) ) {
			return new WP_Error( 'woocommerce_rest_invalid_shop_subscription_id', __( 'Invalid subscription ID.', 'woocommerce-subscriptions' ), array( 'status' => 404 ) );
		}

		$subscription = wcs_get_subscription( $id );

		if ( ! $subscription ) {
			return new WP_Error( 'woocommerce_rest_invalid_shop_subscription_id', sprintf( __( 'Failed to load subscription object with the ID %d.', 'woocommerce-subscriptions' ), $id ), array( 'status' => 404 ) );
		}

		$orders = array();

		foreach ( array( 'parent', 'renewal', 'switch' ) as $order_type ) {
			foreach ( $subscription->get_related_orders( 'ids', $order_type ) as $order_id ) {

				if ( ! wc_rest_check_post_permissions( 'shop_order', 'read', $order_id ) ) {
					continue;
				}

				$order    = wc_get_order( $order_id );
				$response = $this->prepare_object_for_response( $order, $request );

				// Add the order's relationship to the response.
				$response->data['order_type'] = $order_type . '_order';

				$orders[] = $this->prepare_response_for_collection( $response );
			}
		}

		$response = rest_ensure_response( $orders );
		$response->header( 'X-WP-Total', count( $orders ) );
		$response->header( 'X-WP-TotalPages', 1 );

		return apply_filters( 'wcs_rest_subscription_orders_response', $response, $request );
	}

	/**
	 * Overrides WC_REST_Orders_Controller::get_order_statuses() so that subscription statuses are
	 * validated correctly.
	 *
	 * @since 3.1.0
	 * @return array An array of valid subscription statuses.
	 */
	protected function get_order_statuses() {
		$subscription_statuses = array();

		foreach ( wcs_get_subscription_statuses() as $status => $status_name ) {
			$subscription_statuses[] = str_replace( 'wc-', '', $status );
		}

		return $subscription_statuses;
	}

	/**
	 * Get the query params for collections.
	 *
	 * @since 3.1.0
	 * @return array
	 */
	public function get_collection_params() {
		$params = parent::get_collection_params();

		// Override the base order status description to be subscription specific.
		$params['status']['description'] = __( 'Limit result set to subscriptions which have specific statuses.', 'woocommerce-subscriptions' );
		return $params;
	}

	/**
	 * Gets an object's links to include in the response.
	 *
	 * Because this class also handles retreiving order data, we need
	 * to edit the links generated so the correct REST API href is included
	 * when its generated for an order.
	 *
	 * @since 3.1.0
	 *
	 * @param WC_Data         $object  Object data.
	 * @param WP_REST_Request $request Request object.
	 * @return array                   Links for the given object.
	 */
	protected function prepare_links( $object, $request ) {
		$links = parent::prepare_links( $object, $request );

		if ( isset( $links['self'] ) && wcs_is_order( $object ) ) {
			$links['self'] = array(
				'href' => rest_url( sprintf( '/%s/%s/%d', $this->namespace, 'orders', $object->get_id() ) ),
			);
		}

		return $links;
	}
}
