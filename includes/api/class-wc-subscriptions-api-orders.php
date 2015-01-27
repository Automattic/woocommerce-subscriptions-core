<?php
/**
 * WooCommerce Subscriptions API Orders Class
 *
 * Handles requests to the /subscriptions endpoint
 *
 * @author      Prospress
 * @category    API
 * @since       2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_API_Subscriptions extends WC_API_Orders {

	/* @var string $base the route base */
	protected $base = '/subscriptions';

	/**
	 * Register the routes for this class
	 *
	 * GET|POST /subscriptions
	 * GET /subscriptions/count
	 * GET|PUT|DELETE /subscriptions/<subscription_id>
	 * GET /subscriptions/<subscription_id>/notes
	 * GET /subscriptions/<subscription_id>/notes/<id>
	 * GET /subscriptions/<subscription_id>/orders
	 *
	 * @since 2.0
	 * @param array $routes
	 * @return array $routes
	 */
	public function register_routes( $routes ) {

		$this->post_type = 'shop_subscription';

		# GET /subscriptions
		$routes[$this->base] = array(
			array( array( $this, 'get_subscriptions' ), WC_API_Server::READABLE ),
			array( array( $this, 'create_subscription' ),   WC_API_Server::CREATABLE | WC_API_Server::ACCEPT_DATA ),
		);

		# GET /subscriptions/count
		$routes[$this->base . '/count'] = array(
			array( array( $this, 'get_subscription_count' ), WC_API_Server::READABLE ),
		);

		# GET /subscriptions/statuses
		$routes[$this->base . '/statuses'] = array(
			array( array( $this, 'get_statuses' ), WC_API_Server::READABLE ),
		);

		# GET|PUT|DELETE /subscriptions/<subscription_id>
		$routes[$this->base . '/(?P<subscription_id>\d+)'] = array(
			array( array( $this, 'get_subscription' ), WC_API_Server::READABLE ),
			array( array( $this, 'edit_subscription' ), WC_API_Server::EDITABLE | WC_API_Server::ACCEPT_DATA ),
			array( array( $this, 'delete_subscription' ), WC_API_Server::DELETABLE ),
		);

		# GET /subscriptions/<subscription_id>/notes
		$routes[$this->base . '/(?P<subscription_id>\d+)/notes'] = array(
			array( array( $this, 'get_subscription_notes' ), WC_API_Server::READABLE ),
			array( array( $this, 'create_subscription_note' ), WC_API_Server:: CREATABLE | WC_API_Server::ACCEPT_DATA ),
		);

		# GET /subscriptions/<subscription_id>/notes/<id>
		$routes[$this->base . '/(?P<subscription_id>\d+)/notes/(?P<id>\d+)'] = array(
			array( array( $this, 'get_subscription_note' ), WC_API_Server::READABLE ),
			array( array( $this, 'edit_subscription_note' ), WC_API_SERVER::EDITABLE | WC_API_Server::ACCEPT_DATA ),
			array( array( $this, 'delete_subscription_note' ), WC_API_SERVER::DELETABLE ),
		);

		# GET /subscriptions/<subscription_id>/orders
		$routes[$this->base . '/(?P<subscription_id>\d+)/orders'] = array(
			array( array( $this, 'get_all_subscription_orders' ), WC_API_Server::READABLE ),
		);

		return $routes;
	}

	/**
	 * Ensures the statuses are in the correct format and are valid subscription statues.
	 *
	 * @since 2.0
	 * @param $status string | array
	 */
	protected function format_statuses( $status = null ) {
		$statuses = 'any';

		if ( ! empty( $status ) ) {
			// get list of statuses and check each on is in the correct format and is valid
			$statuses = explode( ',', $status );

			// attach the wc- prefix to those statuses that have not specified it
			foreach ( $statuses as &$status ) {
				if ( 'wc-' != substr( $status, 0, 3 ) ) {
					$status = 'wc-' . $status;

					if ( ! array_key_exists( $status, wcs_get_subscription_statuses() ) ) {
						return new WP_Error( 'wcs_api_invalid_subscription_status', __( 'Invalid subscription status given.', 'woocommerce-subscription' ) );
					}
				}
			}
		}

		return $statuses;
	}

	/**
	 * Gets all subscriptions
	 *
	 * @since 2.0
	 * @param null $fields
	 * @param array $filter
	 * @param null $status
	 * @param null $page
	 * @return array
	 */
	public function get_subscriptions( $fields = null, $filter = array(), $status = null, $page = 1 ) {
		// check user permissions
		if ( ! current_user_can( 'read_private_shop_orders' ) ) {
			return new WP_Error( 'wcs_api_user_cannot_read_susbcription_count', __( 'You do not have permission to read the subscriptions count', 'woocommerce-subscriptions' ), array( 'status' => 401 ) );
		}

		// add wc- prefix to status if it does not exist
		$status = $this->format_statuses( $status );

		if ( is_wp_error( $status ) ) {
			return $status;
		}

		$filter['page'] = $page;

		$base_args = array(
			'post_status' => $status,
			'post_type'   => 'shop_subscription',
			'fields'      => 'ids',
		);

		// @see WC_API_Resource::merge_query_args()
		$query_args = $this->merge_query_args( $base_args, $filter );

		$query = $this->query_orders( $query_args );

		$subscriptions = array();

		foreach( $query->posts as $subscription_id ) {

			if ( ! $this->is_readable( $subscription_id ) ) {
				continue;
			}

			$subscriptions[] = current( $this->get_subscription( $subscription_id, $fields, $filter ) );
		}

		$this->server->add_pagination_headers( $query );

		return array( 'subscriptions' => apply_filters( 'wcs_api_get_subscriptions_response', $subscriptions, $fields, $filters, $status, $page, $this->server ) );
	}

	/**
	 * Override wc_create_order when calling WC_API_Orders:create_order();
	 *
	 * @since 2.0
	 */
	public function override_wc_create_order( $wc_order, $post_type, $data ) {

		if ( 'shop_subscription' == $post_type ) {
			return wcs_create_subscription( $data );
		}

		return $wc_order;
	}

	/**
	 * Look at WC-API for creating orders - it's going to be very similar in that regard.
	 *
	 * @since 2.0
	 * @param array data raw order data
	 * @return array
	 */
	public function create_subscription( $data ) {

		$data = isset( $data['subscription'] ) ? $data['subscription'] : array();

		try {
			// permission check
			if ( ! current_user_can( 'publish_shop_orders' ) ) {
				throw new WC_API_Exception( 'wcs_api_user_cannot_create_subscription', __( 'You do not have permission to create subscriptions', 'woocommerce-subscriptions' ), 401 );
			}

			add_filter( 'woocommerce_api_custom_create_order_method', array( $this, 'override_wc_create_order' ), 3, 10 );

			$data['order'] = $data;
			$subscription = $this->create_order( $data );
			unset( $data['order'] );

			if ( is_wp_error( $subscription ) ) {
				throw new WC_API_Exception( $subscription->get_error_code(), $subscription->get_error_message(), 401 );
			}

			$subscription = wcs_get_subscription( $subscription['order']['id'] );
			unset( $data['billing_period'] );
			unset( $data['billing_interval'] );

			$this->update_subscription_schedule( $subscription, $data );

			// allow order total to be manually set, especially for those cases where there's no line items added to the subscription
			if ( isset( $data['order_total'] ) ) {
				update_post_meta( $subscription->id, '_order_total', wc_format_decimal( $data['order_total'], get_option( 'woocommerce_price_num_decimals' ) ) );
			}

			// payment method
			if ( isset( $data['payment_details'] ) && is_array( $data['payment_details'] ) ) {

				// payment method and title are required
				if ( empty( $data['payment_details']['method_id'] ) || empty( $data['payment_details']['method_title'] ) ) {
					throw new WC_API_Exception( 'wcs_api_invalid_payment_details', __( 'Recurring payment method ID and title are required', 'woocommerce' ), array( 'status' => 400 ) );
				}

				update_post_meta( $subscription->id, '_payment_method', $data['payment_details']['method_id'] );
				update_post_meta( $subscription->id, '_payment_method_title', $data['payment_details']['method_title'] );

				// set paid_date
				if ( isset( $data['payment_details']['paid'] ) && 'true' === $data['payment_details']['paid'] ) {
					$order->payment_complete( isset( $data['payment_details']['transaction_id'] ) ? $data['payment_details']['transaction_id'] : '' );
				}

			}

			// Trigger action after subscription has been created - used by the WC_Subscriptions_Webhook class.
			do_action( 'wcs_api_subscription_created', $subscription->id, $this );

			return array( 'creating_subscription', wcs_get_subscription( $subscription->id ) );

		} catch ( WC_API_Exception $e ) {
			return new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) );
		}
	}

	/**
	 * Edit Subscription
	 *
	 * @since 2.0
	 * @return array
	 */
	public function edit_subscription( $subscription_id, $data, $fields = null ) {

		$data = apply_filters( 'wcs_api_edit_subscription_data', isset( $data['subscription'] ) ? $data['subscription'] : array(), $subscription_id, $fields );

		try {

			$subscription = wcs_get_subscription( $subscription_id );

			if ( ! $subscription->is_editable() ) {
				throw new WC_API_Exception( 'wcs_api_cannot_edit_subscription', __( 'The requested subscription cannot be edited.', 'woocommerce-subscriptions' ), 400 );
			}

			// validate payment method before calling edit_order()
			if ( isset( $data['payment_details'] ) && is_array( $data['payment_details'] ) ) {

				// check payment method meta data exists in $data['payment_details']
				if ( ! $this->validate_payment_method_data( $data['payment_details'] ) ) {
					throw new WC_API_Exception( 'wcs_api_invalid_subscription_payment_data', __( 'Recurring Payment method meta data is invalid', 'woocommerce-subscriptiosn'), 400 );
				}

				// validate payment meta data if required
				$data['payment_details']['post_meta'] = ( ! empty( $data['payment_details']['post_meta'] ) && is_array( $data['payment_details']['post_meta'] ) ) ? $data['payment_details']['post_meta'] : array();
				$data['payment_details']['user_meta'] = ( ! empty( $data['payment_details']['user_meta'] ) && is_array( $data['payment_details']['user_meta'] ) ) ? $data['payment_details']['user_meta'] : array();

				// update payment method post meta if necessary
				foreach ( $data['payment_details']['post_meta'] as $meta_key => $meta_value ) {
					update_post_meta( $subscription->id, $meta_key, $meta_value );
				}

				// update payment method user meta if set
				foreach ( $data['payment_details']['user_meta'] as $meta_key => $meta_value ) {
					update_user_meta( $subscription->customer_user, $meta_key, $meta_value );
				}
			}

			// set $data['order'] = $data['subscription'] so that edit_order can read in the request
			$data['order'] = $data;
			// edit subscription by calling WC_API_Orders::edit_order()
			$edited = $this->edit_order( $subscription_id, $data, $fields );
			// remove part of the array that isn't being used
			unset( $data['order'] );

			if ( is_wp_error( $edited ) ) {
				throw new WC_API_Exception( 'wcs_api_cannot_edit_subscription', sprintf( __( 'Edit subscription failed with error: %s', 'woocommerce-subscriptions' ), $edited->get_error_message() ), $edited->get_error_code() );
			}

			// update subscription specific meta (i.e. billing period/interval and date fields)
			$this->update_subscription_schedule( $subscription, $data );

			// action called after successfully editing subscription
			do_action( 'wcs_api_subscription_updated', $subscription_id, $data, $this );

			return $this->get_subscription( $subscription_id );

		} catch( WC_API_Exception $e ) {

			return new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) );
		}

	}

	/**
	 *
	 *
	 * @since 2.0
	 */
	public function validate_payment_method_data( $payment_details ) {
		return true;
	}

	/**
	 * Update all subscription specific meta (i.e. Billing interval/period and date fields )
	 *
	 * @since 2.0
	 * @param $data array
	 * @param $subscription WC_Subscription
	 */
	protected function update_subscription_schedule( $subscription, $data ) {

		// update billing interval
		if ( ! empty( $data['billing_interval'] ) ) {

			$interval = absint( $data['billing_interval'] );

			if ( 0 == $interval ) {
				throw new WC_API_Exception( 'wcs_api_invalid_subscription_meta', __( 'Invalid subscription billing interval given. Must be an integer greater than 0.', 'woocommerce-subscriptions' ) );
			}

			update_post_meta( $subscription->id, '_billing_interval', $interval );

		}

		// update billing period
		if ( ! empty( $data['billing_period'] ) ) {

			$period = strtolower( $data['billing_period'] );

			// validate period
			if ( ! in_array( strtolower( $period, array_keys( wcs_get_subscription_period_strings() ) ) ) ) {
				throw new WC_API_Exception( 'wcs_api_invalid_subscription_meta', __( 'Invalid subscription billing period given.', 'woocommerce-subscriptions' ) );
			}

			update_post_meta( $subscription->id, '_billing_period', $period );
		}

		// date
		foreach( array( 'start', 'trial_end', 'end', 'next_payment' ) as $date ) {
			// check format
			if ( empty( $data[ $date . '_date' ] ) ) {
				continue;
			}

			if ( ! $subscription->can_date_be_updated( $date ) ) {
				throw new WC_API_Exception( 'wcs_api_cannot_update_subscription_date', __( 'Cannot update subscription {$date} date', 'woocommerce-subscriptions' ) );
			}

			$subscription->update_date( $date, $data[ $date . '_date' ] );


		}

	}

	/**
	 * Delete subscription
	 *
	 * @since 2.0
	 */
	public function delete_subscription( $subscription_id, $fields = array() ) {

		$subscription_id = $this->validate_request( $subscription_id, 'shop_subscription', 'delete' );

		if ( is_wp_error( $subscription_id ) ) {
			return $subscription_id;
		}

		$subscription = wcs_get_subscription( $subscription_id );

		// check the subscription can be deleted (moved to the trash)
		try {

			$subscription->update_status( 'trash' );

			// Log deletion on order
			$subscription->add_order_note( sprintf( __( 'Deleted Subscription "%s".', 'woocommerce-subscriptions' ), '[subscription_name]' ) );

		} catch ( Exception $e ) {

			return new WP_Error( 'wcs_api_subscription_cannot_be_deleted', $e->getMessage(), array( 'status' => 401 ) );
		}

		do_action( 'wcs_api_subscription_deleted', $subscription, $item, $fields );
		return array( 'subscription_deleted', apply_filters( 'wcs_api_delete_subscription_response', $subscription, $fields, $this->server ) );

	}

	/**
	 * Retrieves the subscription by the given id.
	 *
	 * Called by: /subscriptions/<subscription_id>
	 *
	 * @since 2.0
	 * @param int $subscription_id
	 * @param array $fields
	 * @param array $filter
	 * @return array
	 */
	public function get_subscription( $subscription_id, $fields = null, $filter = array() ) {
		// check the subscription id
		$subscription_id = $this->validate_request( $subscription_id, 'shop_subscription', 'read' );

		if ( is_wp_error( $subscription_id ) ) {
			return $subscription_id;
		}

		$subscription = wcs_get_subscription( $subscription_id );

		return array( 'subscription' => apply_filters( 'wcs_api_get_subscription_response', $subscription, $fields, $filter, $this->server ) );
	}

	/**
	 * Returns a list of all the available subscription statuses.
	 *
	 * @see wcs_get_subscription_statuses() in wcs-functions.php
	 * @since 2.0
	 * @return array
	 *
	 */
	public function get_statuses() {
		return array( 'subscription_statuses' => wcs_get_subscription_statuses() );
	}


	/**
	 * Get the total number of subscriptions
	 *
	 * Called by: /subscriptions/count
	 * @since 2.0
	 * @param $status string
	 * @param $filter array
	 * @return int | WP_Error
	 */
	public function get_subscription_count( $status = NULL, $filter = array() ) {

		if ( empty( $status ) ) {
			$status = array_keys( wcs_get_subscription_statuses() );
		}

		return $this->get_orders_count( $status, $filter );

	}

	/**
	 * Returns all the notes tied to the subscription
	 *
	 * Called by: subscription/<subscription_id>/notes
	 * @since 2.0
	 * @param $subscription_id
	 * @param $fields
	 * @return WP_Error|array
	 */
	public function get_subscription_notes( $subscription_id, $fields = null ) {
		$notes = $this->get_order_notes( $subscription_id, $fields );

		if ( is_wp_error( $notes ) ) {
			return $notes;
		}

		return array( 'subscription_notes' => apply_filters( 'wcs_api_subscription_notes_response', $notes['order_notes'], $subscription_id, $fields ) );
	}

	/**
	 * Get information about a subscription note.
	 *
	 * @since 2.0
	 * @param int $subscription_id
	 * @param int $id
	 * @param array $fields
	 *
	 * @return array Subscription note
	 */
	public function get_subscription_note( $subscription_id, $id, $fields = null ) {

		$note = $this->get_order_note( $subscription_id, $id, $fields );

		if ( is_wp_error( $note ) ) {
			return $note;
		}

		return array( 'subscription_note' => apply_filters( 'wcs_api_subscription_note_response', $note['order_note'], $subscription_id, $id, $fields ) );

	}

	/**
	 * Get information about a subscription note.
	 *
	 * @param int $subscription_id
	 * @param int $id
	 * @param array $fields
	 *
	 * @return WP_Error|array Subscription note
	 */
	public function create_subscription_note( $subscription_id, $data ) {

		$note = $this->create_order_note( $subscription_id, $data );

		if ( is_wp_error( $note ) ) {
			return $note;
		}

		do_action( 'wcs_api_created_subscription_note', $subscription_id, $note['order_note'], $this );

		return array( 'subscription_note' => $note['order_note'] );
	}

	/**
	 * Verify and edit subscription note.
	 *
	 * @since 2.0
	 * @param int $subscription_id
	 * @param int $id
	 *
	 * @return WP_Error|array Subscription note edited
	 */
	public function edit_subscription_note( $subscription_id, $id, $data ) {

		$note = $this->edit_order_note( $subscription_id, $id, $data );

		if ( is_wp_error( $note ) ) {
			return $note;
		}

		do_action( 'wcs_api_edit_subscription_note', $subscription_id, $id, $note['order_note'], $this );

		return array( 'subscription_note' => $note['order_note'] );
	}


	/**
	 * Verify and delete subscription note.
	 *
	 * @since 2.0
	 * @param int $subscription_id
	 * @param int $id
	 * @return WP_Error|array deleted subscription note status
	 */
	public function delete_subscription_note( $subscription_id, $id ) {

		$deleted_note = $this->delete_order_note( $subscription_id, $id );

		if ( is_wp_error( $deleted_note ) ) {
			return $deleted_note;
		}

		do_action( 'wcs_api_subscription_note_status', $subscription_id, $id, $this );

		return array( 'message' => __( 'Permanently deleted subscription note', 'woocommerce-subscriptions' ) );

	}

	/**
	 * Get information about the initial order and renewal orders of a subscription.
	 *
	 * Called by: /subscriptions/<subscription_id>/orders
	 * @since 2.0
	 * @param $subscription_id
	 * @param $fields
	 */
	public function get_all_subscription_orders( $subscription_id, $filters = null ) {

		// validate the subscription_id given @see WC_API_Resource::validate_request()
		$subscription_id = $this->validate_request( $subscription_id, 'shop_subscription', 'read' );

		if ( is_wp_error( $subscription_id ) ) {
			return $subscription_id;
		}

		$subscription = wcs_get_subscription( $subscription_id );

		$subscription_orders = $subscription->get_related_orders();

		if ( ! empty( $subscription_orders ) ) {
			$orders = array();

			foreach ( $subscription_orders as $order_id ) {
				$orders[] = $this->get_order( $order_id );
			}

		} else {
			$orders = sprintf( __( 'Subscription %s has no related orders.', 'woocommerce-subscriptions' ), '#' . $subscription_id );
		}

		return array( 'subscription_orders' => apply_filters( 'wcs_api_subscription_orders_response', $orders, $subscription_id, $filters, $this->server ) );
	}

}
