<?php
defined( 'ABSPATH' ) || exit;

/**
 * Subscription Data Store: Stored in Custom Order Tables.
 *
 * Extends OrdersTableDataStore to make sure subscription related meta data is read/updated.
 */
class WCS_Orders_Table_Subscription_Data_Store extends \Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore {

	/**
	 * Define subscription specific data which augments the meta of an order.
	 *
	 * The meta keys here determine the prop data that needs to be manually set. We can't use
	 * the $internal_meta_keys property from OrdersTableDataStore because we want its value
	 * too, so instead we create our own and merge it into $internal_meta_keys in __construct.
	 *
	 * @var array
	 */
	protected $subscription_internal_meta_keys = array(
		'_schedule_trial_end',
		'_schedule_next_payment',
		'_schedule_cancelled',
		'_schedule_end',
		'_schedule_payment_retry',
		'_subscription_switch_data',
		'_schedule_start',
	);

	/**
	 * Array of subscription specific data which augments the meta of an order in the form meta_key => prop_key
	 *
	 * Used to read/update props on the subscription.
	 *
	 * @var array
	 */
	protected $subscription_meta_keys_to_props = array(
		'_billing_period'           => 'billing_period',
		'_billing_interval'         => 'billing_interval',
		'_suspension_count'         => 'suspension_count',
		'_cancelled_email_sent'     => 'cancelled_email_sent',
		'_requires_manual_renewal'  => 'requires_manual_renewal',
		'_trial_period'             => 'trial_period',

		'_schedule_trial_end'       => 'schedule_trial_end',
		'_schedule_next_payment'    => 'schedule_next_payment',
		'_schedule_cancelled'       => 'schedule_cancelled',
		'_schedule_end'             => 'schedule_end',
		'_schedule_payment_retry'   => 'schedule_payment_retry',
		'_schedule_start'           => 'schedule_start',

		'_subscription_switch_data' => 'switch_data',
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Register any custom date types as internal meta keys and props.
		foreach ( wcs_get_subscription_date_types() as $date_type => $date_name ) {
			// The last payment date is derived from other sources and shouldn't be stored on a subscription.
			if ( 'last_payment' === $date_type ) {
				continue;
			}

			$meta_key = wcs_get_date_meta_key( $date_type );

			// Skip any dates which are already core date types. We don't want custom date types to override them.
			if ( isset( $this->subscription_meta_keys_to_props[ $meta_key ] ) ) {
				continue;
			}

			$this->subscription_meta_keys_to_props[ $meta_key ] = wcs_maybe_prefix_key( $date_type, 'schedule_' );
			$this->subscription_internal_meta_keys[]            = $meta_key;
		}

		// Exclude the subscription related meta data we set and manage manually from the objects "meta" data
		$this->internal_meta_keys = array_merge( $this->internal_meta_keys, $this->subscription_internal_meta_keys );

		// foreach ( $this->get_props_to_ignore() as $prop ) {
		// if transaction ID remove from order column mapping array
		// else remove from operation meta data column mapping array
		// }
	}

	/**
	 * Get the props set on a subscription which we don't want used on a subscription, which may be
	 * inherited order meta data, or other values using the post meta data store but not as props.
	 *
	 * @return array A mapping of meta keys => prop names
	 */
	protected function get_props_to_ignore() {
		$props_to_ignore = array(
			'_transaction_id' => 'transaction_id',
			'_date_completed' => 'date_completed',
			'_date_paid'      => 'date_paid',
			'_cart_hash'      => 'cart_hash',
		);

		return apply_filters( 'wcs_subscription_data_store_props_to_ignore', $props_to_ignore, $this );
	}

	/**
	 * Returns data store object to use backfilling.
	 *
	 * @return \WCS_Subscription_Data_Store_CPT
	 */
	protected function get_post_data_store_for_backfill() {
		return new \WCS_Subscription_Data_Store_CPT();
	}

	/**
	 * Get amount refunded for all related orders.
	 *
	 * @param \WC_Subscription $subscription
	 *
	 * @return string
	 */
	public function get_total_refunded( $subscription ) {

		$total = 0;

		foreach ( $subscription->get_related_orders( 'all' ) as $order ) {
			$total += parent::get_total_refunded( $order );
		}

		return $total;
	}

	/**
	 * Get the total tax refunded for all related orders.
	 *
	 * @param \WC_Subscription $subscription
	 *
	 * @return float
	 */
	public function get_total_tax_refunded( $subscription ) {

		$total = 0;

		foreach ( $subscription->get_related_orders() as $order ) {
			$total += parent::get_total_tax_refunded( $order );
		}

		return abs( $total );
	}

	/**
	 * Get the total shipping refunded for all related orders.
	 *
	 * @param \WC_Subscription $subscription
	 *
	 * @return float
	 */
	public function get_total_shipping_refunded( $subscription ) {

		$total = 0;

		foreach ( $subscription->get_related_orders( 'all' ) as $order ) {
			$total += parent::get_total_shipping_refunded( $order );
		}

		return abs( $total );
	}

	/**
	 * Return count of orders with a specific status.
	 *
	 * @param string $status Order status. Function wc_get_order_statuses() returns a list of valid statuses.
	 *
	 * @return int
	 */
	public function get_order_count( $status ) {
		global $wpdb;

		$orders_table = self::get_orders_table_name();

		return absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$orders_table} WHERE type = %s AND status = %s", 'shop_subscription', $status ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Get all subscriptions matching the passed in args.
	 *
	 * @param array $args
	 *
	 * @return array of orders
	 */
	public function get_orders( $args = [] ) {
		$args        = wp_parse_args(
			$args,
			[
				'type'   => 'shop_subscription',
				'return' => 'objects',
			]
		);
		$parent_args = $args;

		// We only want IDs from the parent method
		$parent_args['return'] = 'ids';

		$subscriptions = wc_get_orders( $parent_args );

		if ( isset( $args['paginate'] ) && $args['paginate'] ) {

			if ( 'objects' === $args['return'] ) {
				$return = array_map( 'wcs_get_subscription', $subscriptions->orders );
			} else {
				$return = $subscriptions->orders;
			}

			return (object) [
				'orders'        => $return,
				'total'         => $subscriptions->total,
				'max_num_pages' => $subscriptions->max_num_pages,
			];

		} else {

			if ( 'objects' === $args['return'] ) {
				$return = array_map( 'wcs_get_subscription', $subscriptions );
			} else {
				$return = $subscriptions;
			}

			return $return;
		}
	}

	/**
	 * Create a new subscription in the database.
	 *
	 * @param \WC_Subscription $subscription
	 */
	public function create( &$subscription ) {
		parent::create( $subscription );
		do_action( 'woocommerce_new_subscription', $subscription->get_id() );
	}

	/**
	 * Read a subscription object from custom tables.
	 *
	 * @param \WC_Subscription $subscription Subscription object.
	 *
	 * @return void
	 */
	public function read( &$subscription ) {
		parent::read( $subscription );
		$this->set_subscription_props( $subscription );
	}

	/**
	 * Read multiple subscription objects from custom tables.
	 *
	 * @param \WC_Order $subscriptions Subscription objects.
	 *
	 * @return void
	 */
	public function read_multiple( &$subscriptions ) {
		parent::read_multiple( $subscriptions );
		foreach ( $subscriptions as $subscription ) {
			$this->set_subscription_props( $subscription );
		}
	}

	/**
	 * Update subscription in the database.
	 *
	 * @param \WC_Subscription $subscription
	 *
	 * @return void
	 */
	public function update( &$subscription ) {
		// TODO: check date paid logic at top of parent::update function
		// Old comment: We don't want to call parent here becuase WC_Order_Data_Store_CPT includes a JIT setting of the paid date which is not needed for subscriptions, and also very resource intensive
		parent::update( $subscription );

		// We used to call parent::update() above, which triggered this hook, so we trigger it manually here for backward compatibilty (and to improve compatibility with 3rd party code which may run validation or additional operations on it which should also be applied to a subscription)
		do_action( 'woocommerce_update_order', $subscription->get_id() );

		do_action( 'woocommerce_update_subscription', $subscription->get_id() );
	}

	/**
	 * Helper method to set subscription props.
	 *
	 * @param \WC_Order $subscription Subscription object.
	 *
	 * @return void
	 */
	private function set_subscription_props( $subscription ) {
		$props_to_set = [];
		$dates_to_set = [];

		foreach ( $this->subscription_meta_keys_to_props as $meta_key => $prop_key ) {
			if ( 0 === strpos( $prop_key, 'schedule' ) || in_array( $meta_key, $this->subscription_internal_meta_keys, true ) ) {

				$meta_value = $subscription->get_meta( $meta_key, true );

				// Dates are set via update_dates() to make sure relationships between dates are validated
				if ( 0 === strpos( $prop_key, 'schedule' ) ) {
					$date_type = str_replace( 'schedule_', '', $prop_key );

					if ( 'start' === $date_type && ! $meta_value ) {
						$meta_value = $subscription->get_date( 'date_created' );
					}

					$dates_to_set[ $date_type ] = ( false === $meta_value ) ? 0 : $meta_value;
				} else {
					$props_to_set[ $prop_key ] = $meta_value;
				}
			}
		}

		$subscription->update_dates( $dates_to_set );
		$subscription->set_props( $props_to_set );
	}

	/**
	 * Read subscription data.
	 *
	 * @param \WC_Subscription $subscription
	 * @param object $post_object
	 *
	 * @return void
	 */
	protected function read_order_data( &$subscription, $post_object ) {
		$props_to_set = [];
		$dates_to_set = [];

		// Set all order meta data, as well as data defined by WC_Subscription::$extra_keys which has corresponding setter methods
		parent::read_order_data( $subscription, $post_object );

		foreach ( $this->subscription_meta_keys_to_props as $meta_key => $prop_key ) {
			if ( 0 === strpos( $prop_key, 'schedule' ) || in_array( $meta_key, $this->subscription_internal_meta_keys, true ) ) {

				$meta_value = wcs_get_objects_property( $subscription, $meta_key );

				// Dates are set via update_dates() to make sure relationships between dates are validated
				if ( 0 === strpos( $prop_key, 'schedule' ) ) {
					$date_type = str_replace( 'schedule_', '', $prop_key );

					if ( 'start' === $date_type && ! $meta_value ) {
						$meta_value = $subscription->get_date( 'date_created' );
					}

					$dates_to_set[ $date_type ] = ( false === $meta_value ) ? 0 : $meta_value;
				} else {
					$props_to_set[ $prop_key ] = $meta_value;
				}
			}
		}

		$subscription->update_dates( $dates_to_set );
		$subscription->set_props( $props_to_set );
	}

	/**
	 * Helper method that updates post meta based on an order object.
	 *
	 * @param \WC_Subscription $subscription Order object.
	 *
	 * @return void
	 */
	public function update_order_meta( &$subscription ) {
		$updated_props = [];

		foreach ( $this->get_props_to_update( $subscription, $this->subscription_meta_keys_to_props ) as $meta_key => $prop ) {
			$meta_value = ( 'schedule_' === substr( $prop, 0, 9 ) ) ? $subscription->get_date( $prop ) : $subscription->{"get_$prop"}( 'edit' );

			// Store as a string of the boolean for backward compatibility (yep, it's gross)
			if ( 'requires_manual_renewal' === $prop ) {
				$meta_value = $meta_value ? 'true' : 'false';
			}

			$subscription->update_meta_data( $meta_key, $meta_value );
			$updated_props[] = $prop;
		}

		do_action( 'woocommerce_subscription_object_updated_props', $subscription, $updated_props );

		parent::update_order_meta( $subscription );
	}

	/**
	 * Update subscription dates in the database.
	 * Returns the date properties saved to the database in array format: [ $prop_name => DateTime Object ]
	 *
	 * @param \WC_Subscription $subscription
	 *
	 * @return array
	 */
	public function save_dates( $subscription ) {
		global $wpdb;

		$saved_dates    = [];
		$changes        = $subscription->get_changes();
		$date_meta_keys = [
			'_schedule_trial_end',
			'_schedule_next_payment',
			'_schedule_cancelled',
			'_schedule_end',
			'_schedule_payment_retry',
			'_schedule_start',
		];

		// Add any custom date types to the date meta keys we need to save.
		foreach ( wcs_get_subscription_date_types() as $date_type => $date_name ) {
			if ( 'last_payment' === $date_type ) {
				continue;
			}

			$date_meta_key = wcs_get_date_meta_key( $date_type );

			if ( ! in_array( $date_meta_key, $date_meta_keys, true ) ) {
				$date_meta_keys[] = $date_meta_key;
			}
		}

		$date_meta_keys_to_props = array_intersect_key( $this->subscription_meta_keys_to_props, array_flip( $date_meta_keys ) );
		$subscription_meta_data  = $this->data_store_meta->read_meta( $subscription );

		// Save the changes to scheduled dates
		foreach ( $this->get_props_to_update( $subscription, $date_meta_keys_to_props ) as $meta_key => $prop ) {
			$existing_meta_data = array_column( $subscription_meta_data, null, 'meta_key' )[ $meta_key ] ?? false;
			$new_meta_data      = [
				'key'   => $meta_key,
				'value' => $subscription->get_date( $prop ),
			];

			if ( ! empty( $existing_meta_data ) ) {
				$new_meta_data['id'] = $existing_meta_data->meta_id;
				$this->data_store_meta->update_meta( $subscription, (object) $new_meta_data );
			} else {
				$this->data_store_meta->add_meta( $subscription, (object) $new_meta_data );
			}

			$saved_dates[ $prop ] = wcs_get_datetime_from( $subscription->get_time( $prop ) );
		}

		$order_update_query = [];

		// Record any changes to the created date.
		if ( isset( $changes['date_created'] ) ) {
			$order_update_query[]        = '`date_created_gmt` = ' . gmdate( 'Y-m-d H:i:s', $subscription->get_date_created( 'edit' )->getTimestamp() );
			$saved_dates['date_created'] = $subscription->get_date_created();
		}

		// Record any changes to the modified date.
		if ( isset( $changes['date_modified'] ) ) {
			$order_update_query[]         = '`date_updated_gmt` = ' . gmdate( 'Y-m-d H:i:s', $subscription->get_date_modified( 'edit' )->getTimestamp() );
			$saved_dates['date_modified'] = $subscription->get_date_modified();
		}

		// Manually update the order's created and/or modified date if it has changed.
		if ( ! empty( $order_update_query ) ) {
			$table_name = self::get_orders_table_name();
			$wpdb->query(
				$wpdb->prepare(
					'UPDATE %s SET %s WHERE order_id = %d',
					$table_name,
					implode( ', ', $order_update_query ),
					$subscription->get_id()
				)
			);
		}

		return $saved_dates;
	}

	/**
	 * Search subscription data for a term and returns subscription ids
	 *
	 * @param string $term Term to search.
	 *
	 * @return array
	 */
	public function search_subscriptions( $term ) {
		add_filter( 'woocommerce_order_table_search_query_meta_keys', [ $this, 'get_subscription_order_table_search_fields' ] );

		$subscription_ids = wc_get_orders(
			[
				's'      => $term,
				'type'   => 'shop_subscription',
				'status' => array_keys( wcs_get_subscription_statuses() ),
				'return' => 'ids',
				'limit'  => -1,
			]
		);

		remove_filter( 'woocommerce_order_table_search_query_meta_keys', [ $this, 'get_subscription_order_table_search_fields' ] );

		return apply_filters( 'woocommerce_shop_subscription_search_results', $subscription_ids, $term, $this->get_subscription_search_fields() );
	}

	/**
	 * Get the subscription search fields.
	 *
	 * @param array $search_fields
	 *
	 * @return array
	 */
	public function get_subscription_order_table_search_fields( $search_fields = [] ) {
		return array_map(
			'wc_clean',
			apply_filters(
				'woocommerce_shop_subscription_search_fields',
				[
					'_billing_address_index',
					'_shipping_address_index',
				]
			)
		);
	}

	/**
	 * Get the user IDs for customers who have a subscription.
	 *
	 * @return array
	 */
	public function get_subscription_customer_ids() {
		global $wpdb;
		return [];
	}
}
