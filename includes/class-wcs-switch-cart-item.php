<?php
/**
 * WooCommerce Subscriptions Switch Cart Item.
 *
 * A class to assist in the calculations required to record a switch.
 *
 * @package WooCommerce Subscriptions
 * @author  Prospress
 * @since   2.6.0
 */
class WCS_Switch_Cart_Item {

	/**
	 * The cart item.
	 * @var array
	 */
	public $cart_item;

	/**
	 * The subscription being switched.
	 * @var WC_Subscription
	 */
	public $subscription;

	/**
	 * The existing subscription line item being switched.
	 * @var WC_Order_Item_Product
	 */
	public $existing_item;

	/**
	 * The instance of the new product in the cart.
	 * @var WC_Product
	 */
	public $product;

	/**
	 * The new product's variation or product ID.
	 * @var int
	 */
	public $canonical_product_id;

	/**
	 * The subscription's next payment timestamp.
	 * @var int
	 */
	public $next_payment_timestamp;

	/**
	 * The subscription's end timestamp.
	 * @var int
	 */
	public $end_timestamp;

	/**
	 * The subscription's last non-early renewal or parent order created timestamp.
	 * @var int
	 */
	public $last_order_created_time;

	/**
	 * The number of days since the @see $last_order_created_time.
	 * @var int
	 */
	public $days_since_last_payment;

	/**
	 * Constructor.
	 *
	 * @param array $cart_item      The cart item.
	 * @param WC_Subscription       The subscription being switched.
	 * @param WC_Order_Item_Product The subscription line item being switched.
	 *
	 * @throws Exception If $cart is invalid WC_Cart object.
	 * @since 2.6.0
	 */
	public function __construct( $cart_item, $subscription, $existing_item ) {
		$this->cart_item               = $cart_item;
		$this->subscription            = $subscription;
		$this->existing_item           = $existing_item;
		$this->canonical_product_id    = wcs_get_canonical_product_id( $cart_item );
		$this->product                 = $cart_item['data'];
		$this->next_payment_timestamp  = $cart_item['subscription_switch']['next_payment_timestamp'];
		$this->end_timestamp           = wcs_date_to_time( WC_Subscriptions_Product::get_expiration_date( $this->canonical_product_id, $this->subscription->get_date( 'last_order_date_created' ) ) );
	}

	/** Getters */

	/**
	 * Get the subscription's last order time.
	 *
	 * @return int The timestamp of the subscription's last non-early renewal or parent order. If none of those are present, the subscription's created time will be returned.
	 * @since 2.6.0
	 */
	public function get_last_order_created_time() {
		if ( ! isset( $this->last_order_created_time ) ) {
			$last_order = wcs_get_last_non_early_renewal_order( $this->subscription );

			// If there haven't been any non-early renewals yet, use the parent
			if ( ! $last_order ) {
				$last_order = $this->subscription->get_parent();
			}

			// If there aren't any renewals or a parent order, use the subscription's created date.
			if ( ! $last_order ) {
				$this->last_order_created_time = $this->subscription->get_date_created()->getTimestamp();
			} else {
				$this->last_order_created_time = $last_order->get_date_created()->getTimestamp();
			}
		}

		return $this->last_order_created_time;
	}

	/**
	 * Get the number of days since the last payment.
	 *
	 * @return int The number of days since the last non-early renewal or parent payment - rounded down.
	 */
	public function get_days_since_last_payment() {
		if ( ! isset( $this->days_since_last_payment ) ) {
			// Use the timestamp for the last non-early renewal order or parent order to avoid date miscalculations which early renewing creates.
			$this->days_since_last_payment = floor( ( gmdate( 'U' ) - $this->get_last_order_created_time() ) / DAY_IN_SECONDS );
		}

		return $this->days_since_last_payment;
	}

	/** Helper functions */

	/**
	 * Whether the new product is virtual or not.
	 *
	 * @return boolean
	 * @since 2.6.0
	 */
	public function is_virtual_product() {
		return $this->product->is_virtual();
	}
}
