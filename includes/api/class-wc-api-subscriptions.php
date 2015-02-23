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
		$routes[ $this->base ] = array(
			array( array( $this, 'get_subscriptions' ), WC_API_Server::READABLE ),
			array( array( $this, 'create_subscription' ),   WC_API_Server::CREATABLE | WC_API_Server::ACCEPT_DATA ),
		);

		# GET /subscriptions/count
		$routes[ $this->base . '/count' ] = array(
			array( array( $this, 'get_subscription_count' ), WC_API_Server::READABLE ),
		);

		# GET /subscriptions/statuses
		$routes[ $this->base . '/statuses' ] = array(
			array( array( $this, 'get_statuses' ), WC_API_Server::READABLE ),
		);

		# GET|PUT|DELETE /subscriptions/<subscription_id>
		$routes[ $this->base . '/(?P<subscription_id>\d+)' ] = array(
			array( array( $this, 'get_subscription' ), WC_API_Server::READABLE ),
			array( array( $this, 'edit_subscription' ), WC_API_Server::EDITABLE | WC_API_Server::ACCEPT_DATA ),
			array( array( $this, 'delete_subscription' ), WC_API_Server::DELETABLE ),
		);

		# GET /subscriptions/<subscription_id>/notes
		$routes[ $this->base . '/(?P<subscription_id>\d+)/notes' ] = array(
			array( array( $this, 'get_subscription_notes' ), WC_API_Server::READABLE ),
			array( array( $this, 'create_subscription_note' ), WC_API_Server:: CREATABLE | WC_API_Server::ACCEPT_DATA ),
		);

		# GET /subscriptions/<subscription_id>/notes/<id>
		$routes[ $this->base . '/(?P<subscription_id>\d+)/notes/(?P<id>\d+)' ] = array(
			array( array( $this, 'get_subscription_note' ), WC_API_Server::READABLE ),
			array( array( $this, 'edit_subscription_note' ), WC_API_SERVER::EDITABLE | WC_API_Server::ACCEPT_DATA ),
			array( array( $this, 'delete_subscription_note' ), WC_API_SERVER::DELETABLE ),
		);

		# GET /subscriptions/<subscription_id>/orders
		$routes[ $this->base . '/(?P<subscription_id>\d+)/orders' ] = array(
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

		return array( 'subscriptions' => apply_filters( 'wcs_api_get_subscriptions_response', $subscriptions, $fields, $filter, $status, $page, $this->server ) );
	}

	/**
	 * Creating Subscription.
	 *
	 * @since 2.0
	 * @param array data raw order data
	 * @return array
	 */
	public function create_subscription( $data ) {

		$data = isset( $data['subscription'] ) ? $data['subscription'] : array();

		try {

			if ( ! current_user_can( 'publish_shop_orders' ) ) {
				throw new WC_API_Exception( 'wcs_api_user_cannot_create_subscription', __( 'You do not have permission to create subscriptions', 'woocommerce-subscriptions' ), 401 );
			}

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

			if ( isset( $data['payment_details'] ) && is_array( $data['payment_details'] ) ) {

				if ( empty( $data['payment_details']['method_id'] ) || empty( $data['payment_details']['method_title'] ) ) {
					throw new WC_API_Exception( 'wcs_api_invalid_payment_details', __( 'Recurring payment method ID and title are required', 'woocommerce' ), array( 'status' => 400 ) );
				}

				update_post_meta( $subscription->id, '_payment_method', $data['payment_details']['method_id'] );
				update_post_meta( $subscription->id, '_payment_method_title', $data['payment_details']['method_title'] );

				if ( isset( $data['payment_details']['paid'] ) && 'true' === $data['payment_details']['paid'] ) {
					$order->payment_complete( isset( $data['payment_details']['transaction_id'] ) ? $data['payment_details']['transaction_id'] : '' );
				}

			}

			do_action( 'wcs_api_subscription_created', $subscription->id, $this );

			return array( 'creating_subscription', wcs_get_subscription( $subscription->id ) );

		} catch ( WC_API_Exception $e ) {
			return new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) );
		} catch ( Exception $e ) {
			return new WP_Error( 'wcs_api_cannot_create_subscription', $e->getMessage(), array( 'status' => $e->getCode() ) );
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

			if ( isset( $data['payment_details'] ) && is_array( $data['payment_details'] ) ) {

				if ( ! $this->validate_payment_method_data( $data['payment_details'] ) ) {
					throw new WC_API_Exception( 'wcs_api_invalid_subscription_payment_data', __( 'Recurring Payment method meta data is invalid', 'woocommerce-subscriptiosn'), 400 );
				}

				$data['payment_details']['post_meta'] = ( ! empty( $data['payment_details']['post_meta'] ) && is_array( $data['payment_details']['post_meta'] ) ) ? $data['payment_details']['post_meta'] : array();
				$data['payment_details']['user_meta'] = ( ! empty( $data['payment_details']['user_meta'] ) && is_array( $data['payment_details']['user_meta'] ) ) ? $data['payment_details']['user_meta'] : array();

				foreach ( $data['payment_details']['post_meta'] as $meta_key => $meta_value ) {
					update_post_meta( $subscription->id, $meta_key, $meta_value );
				}

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

			$this->update_subscription_schedule( $subscription, $data );

			do_action( 'wcs_api_subscription_updated', $subscription_id, $data, $this );

			return $this->get_subscription( $subscription_id );

		} catch( WC_API_Excpetion $e ) {
			return new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) );

		} catch( Exception $e ) {
			return new WP_Error( 'wcs_api_cannot_edit_subscription', $e->getMessage(), array( 'status' => $e->getCode() ) );

		}

	}

	/**
	 * Override WC_API_Order::create_base_order() to create a subscription
	 * instead of a WC_Order when calling WC_API_Order::create_order().
	 *
	 * @since 2.0
	 * @param $array
	 * @return WC_Subscription
	 */
	protected function create_base_order( $args, $data ) {

		$args['billing_interval'] = ( ! empty( $data['billing_interval'] ) ) ? $data['billing_interval'] : '';
		$args['billing_period'] = ( ! empty( $data['billing_period'] ) ) ? $data['billing_period'] : '';

		return wcs_create_subscription( $args );
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

		if ( ! empty( $data['billing_interval'] ) ) {

			$interval = absint( $data['billing_interval'] );

			if ( 0 == $interval ) {
				throw new WC_API_Exception( 'wcs_api_invalid_subscription_meta', __( 'Invalid subscription billing interval given. Must be an integer greater than 0.', 'woocommerce-subscriptions' ) );
			}

			update_post_meta( $subscription->id, '_billing_interval', $interval );

		}

		if ( ! empty( $data['billing_period'] ) ) {

			$period = strtolower( $data['billing_period'] );

			if ( ! in_array( strtolower( $period, array_keys( wcs_get_subscription_period_strings() ) ) ) ) {
				throw new WC_API_Exception( 'wcs_api_invalid_subscription_meta', __( 'Invalid subscription billing period given.', 'woocommerce-subscriptions' ) );
			}

			update_post_meta( $subscription->id, '_billing_period', $period );
		}

		foreach( array( 'start', 'trial_end', 'end', 'next_payment' ) as $date ) {

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

		$subscription_id = $this->validate_request( $subscription_id, 'shop_subscription', 'read' );

		if ( is_wp_error( $subscription_id ) ) {
			return $subscription_id;
		}

		$subscription      = wcs_get_subscription( $subscription_id );
		$order_data        = $this->get_order( $subscription_id );
		$subscription_data = $order_data['order'];

		// Not all order meta relates to a subscription (a subscription doesn't "complete")
		if ( isset( $subscription_data['completed_at'] ) ) {
			unset( $subscription_data['completed_at'] );
		}

		$subscription_data['billing_schedule'] = array(
			'period'          => $subscription->billing_period,
			'interval'        => $subscription->billing_interval,
			'start_at'        => $this->get_formatted_datetime( $subscription, 'start' ),
			'trial_end_at'    => $this->get_formatted_datetime( $subscription, 'trial_end' ),
			'next_payment_at' => $this->get_formatted_datetime( $subscription, 'next_payment' ),
			'end_at'          => $this->get_formatted_datetime( $subscription, 'end' ),
		);

		if ( ! empty( $subscription->order ) ) {
			$subscription_data['parent_order_id'] = $subscription->order->id;
		} else {
			$subscription_data['parent_order_id'] = array();
		}

		return array( 'subscription' => apply_filters( 'wcs_api_get_subscription_response', $subscription_data, $fields, $filter, $this->server ) );
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

		$subscription_id = $this->validate_request( $subscription_id, 'shop_subscription', 'read' );

		if ( is_wp_error( $subscription_id ) ) {
			return $subscription_id;
		}

		$subscription = wcs_get_subscription( $subscription_id );

		$subscription_orders = $subscription->get_related_orders();

		$formatted_orders = array();

		if ( ! empty( $subscription_orders ) ) {

			// set post_type back to shop order so that get_orders doesn't try return a subscription.
			$this->post_type = 'shop_order';

			foreach ( $subscription_orders as $order_id ) {
				$formatted_orders[] = $this->get_order( $order_id );
			}

			$this->post_type = 'shop_subscription';

		}

		return array( 'subscription_orders' => apply_filters( 'wcs_api_subscription_orders_response', $formatted_orders, $subscription_id, $filters, $this->server ) );
	}

	/**
	 * Get a certain date for a subscription, if it exists, formatted for return
	 *
	 * @since 2.0
	 * @param $subscription
	 * @param $date_type
	 */
	protected function get_formatted_datetime( $subscription, $date_type ) {

		$timestamp = $subscription->get_time( $date_type );

		if ( $timestamp > 0 ) {
			$formatted_datetime = $this->server->format_datetime( $timestamp );
		} else {
			$formatted_datetime = '';
		}

		return $formatted_datetime;
	}

}
