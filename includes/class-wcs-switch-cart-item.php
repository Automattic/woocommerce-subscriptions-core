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
	 * The number of days until the @see $next_payment_timestamp.
	 * @var int
	 */
	public $days_until_next_payment;

	/**
	 * The number of days in the old subscription's billing cycle.
	 * @var int
	 */
	public $days_in_old_cycle;

	/**
	 * The total paid for the existing item (@see $existing_item) in early renewals and switch orders since the last non-early renewal or parent order.
	 * @var float
	 */
	public $total_paid_for_current_period;

	/**
	 * The existing subscription item's price per day.
	 * @var float
	 */
	public $old_price_per_day;

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
	 * Get the number of days until the next payment.
	 *
	 * @return int
	 * @since 2.6.0
	 */
	public function get_days_until_next_payment() {
		if ( ! isset( $this->days_until_next_payment ) ) {
			$this->days_until_next_payment = ceil( ( $this->next_payment_timestamp - gmdate( 'U' ) ) / DAY_IN_SECONDS );
		}

		return $this->days_until_next_payment;
	}

	/**
	 * Get the number of days in the old billing cycle.
	 *
	 * @return int
	 * @since 2.6.0
	 */
	public function get_days_in_old_cycle() {
		if ( ! isset( $this->days_in_old_cycle ) ) {
			$this->days_in_old_cycle = $this->calculate_days_in_old_cycle();
		}

		return $this->days_in_old_cycle;
	}

	/**
	 * Get the old subscription's price per day.
	 *
	 * @return float
	 * @since 2.6.0
	 */
	public function get_old_price_per_day() {
		if ( ! isset( $this->old_price_per_day ) ) {
			$days_in_old_cycle = $this->get_days_in_old_cycle();

			$total_paid_for_current_period = $this->get_total_paid_for_current_period();

			$old_price_per_day       = $days_in_old_cycle > 0 ? $total_paid_for_current_period / $days_in_old_cycle : $total_paid_for_current_period;
			$this->old_price_per_day = apply_filters( 'wcs_switch_proration_old_price_per_day', $old_price_per_day, $this->subscription, $this->cart_item, $total_paid_for_current_period, $days_in_old_cycle );
		}

		return $this->old_price_per_day;
	}

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
	 * Get the total paid for the existing item (@see $this->existing_item) in early renewals and switch orders since the last non-early renewal or parent order.
	 *
	 * @return float
	 * @since 2.6.0
	 */
	public function get_total_paid_for_current_period() {
		if ( ! isset( $this->total_paid_for_current_period ) ) {
			$this->total_paid_for_current_period = WC_Subscriptions_Switcher::calculate_total_paid_since_last_order( $this->subscription, $this->existing_item, 'exclude_sign_up_fees' );
		}

		return $this->total_paid_for_current_period;
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

	/** Calculator functions */

	/**
	 * Calculate the number of days in the old cycle.
	 *
	 * @return int
	 * @since 2.6.0
	 */
	public function calculate_days_in_old_cycle() {
		$method_to_use = 'days_between_payments';

		// If the subscription contains a synced product and the next payment is actually the first payment, determine the days in the "old" cycle from the subscription object
		if ( WC_Subscriptions_Synchroniser::subscription_contains_synced_product( $this->subscription ) ) {
			$first_synced_payment = WC_Subscriptions_Synchroniser::calculate_first_payment_date( wc_get_product( $this->canonical_product_id ) , 'timestamp', $this->subscription->get_date( 'start' ) );

			if ( $first_synced_payment === $this->next_payment_timestamp ) {
				$method_to_use = 'days_in_billing_cycle';
			}
		}

		// We need the product's billing cycle, not the trial length if the customer hasn't paid anything and it's still on trial.
		if ( $this->is_switch_during_trial() && 0 === $this->get_total_paid_for_current_period() ) {
			$method_to_use = 'days_in_billing_cycle';
		}

		// Find the number of days between the last payment and the next
		if ( 'days_between_payments' === $method_to_use ) {
			$days_in_old_cycle = floor( ( $this->next_payment_timestamp - $this->get_last_order_created_time() ) / DAY_IN_SECONDS );
		} else {
			$days_in_old_cycle = wcs_get_days_in_cycle( $this->subscription->get_billing_period(), $this->subscription->get_billing_interval() );
		}

		return apply_filters( 'wcs_switch_proration_days_in_old_cycle', $days_in_old_cycle, $this->subscription, $this->cart_item );
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

	/**
	 * Whether the new product's trial period matches the old product's trial period.
	 *
	 * @return boolean
	 * @since 2.6.0
	 */
	public function trial_periods_match() {
		$existing_product = $this->existing_item->get_product();

		// We need to cast the returned trial lengths as sometimes they may be strings.
		$matching_length = (int) WC_Subscriptions_Product::get_trial_length( $this->product ) === (int) WC_Subscriptions_Product::get_trial_length( $existing_product );
		$matching_period = WC_Subscriptions_Product::get_trial_period( $this->product ) === WC_Subscriptions_Product::get_trial_period( $existing_product );

		return $matching_period && $matching_length;
	}

	/**
	 * Whether the switch is happening while the subscription is still on trial.
	 *
	 * @return boolean
	 * @since 2.6.0
	 */
	public function is_switch_during_trial() {
		return $this->subscription->get_time( 'trial_end' ) > gmdate( 'U' );
	}
}
