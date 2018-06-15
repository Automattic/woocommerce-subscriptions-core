<?php
/**
 * Store retry details in the WordPress custom table.
 *
 * @package        WooCommerce Subscriptions
 * @subpackage     WCS_Retry_Store
 * @category       Class
 * @author         Prospress
 */

class WCS_Retry_Database_Store extends WCS_Retry_Store {

	protected static $table = 'woocommerce_subscriptions_payment_retries';

	/**
	 * Init method.
	 *
	 * @return null|void
	 */
	public function init() {
	}

	/**
	 * Save the details of a retry to the database
	 *
	 * @param WCS_Retry $retry
	 *
	 * @return int the retry's ID
	 */
	public function save( WCS_Retry $retry ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . self::$table,
			array(
				'id'       => $retry->get_id(),
				'order_id' => $retry->get_order_id(),
				'status'   => $retry->get_status(),
				'date_gmt' => $retry->get_date_gmt(),
				'rule_raw' => wp_json_encode( $retry->get_rule()->get_raw_data() ),
			),
			array(
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
			)
		);

		return absint( $wpdb->insert_id );
	}

	/**
	 * Get the details of a retry from the database
	 *
	 * @param int $retry_id
	 *
	 * @return null|WCS_Retry
	 */
	public function get_retry( $retry_id ) {
		global $wpdb;

		$retry     = null;
		$raw_retry = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}{$this::$table} WHERE id = %d LIMIT 1",
				$retry_id
			)
		);

		if ( $raw_retry ) {
			$retry = new WCS_Retry( array(
				'id'       => $raw_retry->id,
				'order_id' => $raw_retry->order_id,
				'status'   => $raw_retry->status,
				'date_gmt' => $raw_retry->date_gmt,
				'rule_raw' => json_decode( $raw_retry->rule_raw ),
			) );
		}

		return $retry;
	}

	/**
	 *
	 */
	public function get_retries( $args ) {
		global $wpdb;
	}

	/**
	 * Get the IDs of all retries from the database for a given order
	 *
	 * @param int $order_id
	 *
	 * @return array
	 */
	public function get_retry_ids_for_order( $order_id ) {
		global $wpdb;
	}
}
