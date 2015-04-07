<?php
/**
 * Subscriptions Remove Item
 *
 *
 * @author   Prospress
 * @since    2.0
 */
class WCS_Remove_Item {

	/**
	 * Initialise class hooks & filters when the file is loaded
	 *
	 * @since 2.0
	 */
	public static function init() {

		// Check if a user is requesting to remove or re-add an item to their subscription
		add_action( 'init', __CLASS__ . '::maybe_remove_or_add_item_to_subscription', 100 );
	}

	/**
	 * Returns the link used to remove an item from a subscription
	 *
	 * @param $subscription_id
	 * @param $order_item_id
	 * @param $return_url
	 * @since 2.0
	 */
	public static function get_remove_url( $subscription_id, $order_item_id ) {

		$remove_link = add_query_arg( array( 'subscription_id' => $subscription_id, 'remove_item' => $order_item_id ) );
		$remove_link = wp_nonce_url( $remove_link, $subscription_id );

		return $remove_link;
	}

	/**
	 * Returns the link to undo removing an item from a subscription
	 *
	 * @param $subscription_id
	 * @param $order_item_id
	 * @param $base_url
	 * @since 2.0
	 */
	public static function get_undo_remove_url( $subscription_id, $order_item_id, $base_url ) {

		$undo_link = add_query_arg( array( 'subscription_id' => $subscription_id, 'undo_remove_item' => $order_item_id ), $base_url );
		$undo_link = wp_nonce_url( $undo_link, $subscription_id );

		return $undo_link;
	}


}
WCS_Remove_Item::init();
