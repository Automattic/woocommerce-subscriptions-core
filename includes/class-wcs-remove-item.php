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
	 * @param int $subscription_id
	 * @param int $order_item_id
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
	 * @param int $subscription_id
	 * @param int $order_item_id
	 * @param string $base_url
	 * @since 2.0
	 */
	public static function get_undo_remove_url( $subscription_id, $order_item_id, $base_url ) {

		$undo_link = add_query_arg( array( 'subscription_id' => $subscription_id, 'undo_remove_item' => $order_item_id ), $base_url );
		$undo_link = wp_nonce_url( $undo_link, $subscription_id );

		return $undo_link;
	}

	/**
	 * Validate the incoming request to either remove an item or add and item back to a subscription that was previously removed.
	 * Add an descriptive notice to the page whether or not the request was validated or not.
	 *
	 * @since 2.0
	 * @param WC_Subscription $subscription
	 * @param int $order_item_id
	 * @param bool $undo_request bool
	 * @return bool
	 */
	private static function validate_remove_items_request( $subscription, $order_item_id, $undo_request = false ) {

		$subscription_items = $subscription->get_items();
		$user_id            = get_current_user_id();
		$response           = false;

		if ( ! wp_verify_nonce( $_GET['_wpnonce'], $_GET['subscription_id'] ) ) {

			wc_add_notice( __( 'Security error. Please contact us if you need assistance.', 'woocommerce-subscriptions' ), 'error' );

		} elseif ( $user_id !== $subscription->get_user_id() ) {

			wc_add_notice( __( 'You cannot modify a subscription that does not belong to you.', 'woocommerce-subscriptions' ), 'error' );

		} elseif ( ! $undo_request && ! isset( $subscription_items[ $order_item_id ] ) ) { // only need to validate the order item id when removing

			wc_add_notice( __( 'You cannot remove an item that does not exist. ', 'woocommerce-subscriptions' ), 'error' );

		} elseif ( ! $subscription->payment_method_supports( 'subscription_amount_changes' ) ) {

			wc_add_notice( __( 'The item was not removed because this Subscription\'s payment method does not support removing an item.', 'woocommerce-subscriptions' ) );

		} else {

			$response = true;
		}

		return $response;
	}

}
WCS_Remove_Item::init();
