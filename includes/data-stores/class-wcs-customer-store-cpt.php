<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Customer data store for subscriptions.
 *
 * This class is responsible for getting subscriptions for users.
 *
 * @version  1.0.0 - Migrated from WooCommerce Subscriptions v2.3.0
 */
class WCS_Customer_Store_CPT extends WCS_Customer_Store {

	/**
	 * The meta key used to link a customer with a subscription.
	 *
	 * @var string
	 */
	private $meta_key = '_customer_user';

	/**
	 * Get the meta key used to link a customer with a subscription.
	 *
	 * @return string
	 */
	protected function get_meta_key() {
		return $this->meta_key;
	}

	/**
	 * Get the IDs for a given user's subscriptions.
	 *
	 * @param int $user_id The id of the user whose subscriptions you want.
	 * @return array
	 */
	public function get_users_subscription_ids( $user_id ) {

		if ( 0 === $user_id ) {
			return array();
		}

		return wcs_get_orders_with_meta_query(
			[
				'type'        => 'shop_subscription',
				'customer_id' => $user_id,
				'limit'       => -1,
				'status'      => 'any',
				'return'      => 'ids',
				'orderby'     => 'ID',
				'order'       => 'DESC',
			]
		);
	}
}
