<?php
/**
 * WooCommerce Subscriptions Switch Totals Calculator.
 *
 * A class to assist in calculating the upgrade cost, and next payment dates for switch items in the cart.
 *
 * @package WooCommerce Subscriptions
 * @author  Prospress
 * @since   2.6.0
 */
class WCS_Switch_Totals_Calculator {

	/**
	 * Reference to the cart object.
	 *
	 * @var WC_Cart
	 */
	protected $cart = null;

	/**
	 * Whether to prorate the recurring price for all product types ('yes', 'yes-upgrade') or only for virtual products ('virtual', 'virtual-upgrade').
	 *
	 * @var string
	 */
	protected $apportion_recurring_price = '';

	/**
	 * Whether to charge the full sign-up fee, a prorated sign-up fee or no sign-up fee.
	 *
	 * @var string Can be 'full', 'yes', or 'no'.
	 */
	protected $apportion_sign_up_fee = '';

	/**
	 * Whether to take into account the number of payments completed when determining how many payments the subscriber needs to make for the new subscription.
	 *
	 * @var string Can be 'virtual' (for virtual products only), 'yes', or 'no'
	 */
	protected $apportion_length = '';

	/**
	 * Whether store prices include tax.
	 *
	 * @var bool
	 */
	protected $prices_include_tax;

	/**
	 * Constructor.
	 *
	 * @param WC_Cart $cart Cart object to calculate totals for.
	 * @throws Exception If $cart is invalid WC_Cart object.
	 * @since 2.6.0
	 */
	public function __construct( &$cart = null ) {
		if ( ! is_a( $cart, 'WC_Cart' ) ) {
			throw new InvalidArgumentException( 'A valid WC_Cart object parameter is required for ' . __METHOD__ );
		}

		$this->cart = $cart;
		$this->load_settings();
	}

	/**
	 * Load the store's switch settings.
	 *
	 * @since 2.6.0
	 */
	protected function load_settings() {
		$this->apportion_recurring_price = get_option( WC_Subscriptions_Admin::$option_prefix . '_apportion_recurring_price', 'no' );
		$this->apportion_sign_up_fee     = get_option( WC_Subscriptions_Admin::$option_prefix . '_apportion_sign_up_fee', 'no' );
		$this->apportion_length          = get_option( WC_Subscriptions_Admin::$option_prefix . '_apportion_length', 'no' );
		$this->prices_include_tax        = 'yes' === get_option( 'woocommerce_prices_include_tax' );
	}

	/**
	 * Calculate the upgrade cost, and next payment dates for switch cart items.
	 *
	 * @since 2.6.0
	 */
	public function calculate_prorated_totals() {
		foreach ( $this->get_switches_from_cart() as $cart_item_key => $switch_item ) {
			$this->set_first_payment_timestamp( $cart_item_key, $switch_item->next_payment_timestamp );
			$this->set_end_timestamp( $cart_item_key, $switch_item->end_timestamp );

			$this->apportion_sign_up_fees( $switch_item );

			$switch_type = $switch_item->get_switch_type();
			$this->set_switch_type_in_cart( $cart_item_key, $switch_type );

			if ( $this->should_prorate_recurring_price( $switch_item ) ) {
				if ( 'upgrade' === $switch_type ) {
					if ( $this->should_reduce_prepaid_term( $switch_item ) ) {
						$this->reduce_prepaid_term( $cart_item_key, $switch_item );
					} else {
						// Reset any previously calculated prorated price so we don't double the amounts
						$this->reset_prorated_price( $switch_item );

						$upgrade_cost = $this->calculate_upgrade_cost( $switch_item );
						$this->set_upgrade_cost( $switch_item, $upgrade_cost );
					}
				} elseif ( 'downgrade' === $switch_type && $this->should_extend_prepaid_term() ) {

				}
			}
		}
	}

	/**
	 * Get all the switch items in the cart as instances of @see WCS_Switch_Cart_Item.
	 *
	 * @return WCS_Switch_Cart_Item[]
	 * @since 2.6.0
	 */
	protected function get_switches_from_cart() {
		$switches = array();

		foreach ( $this->cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( ! isset( $cart_item['subscription_switch']['subscription_id'] ) ) {
				continue;
			}

			$subscription  = wcs_get_subscription( $cart_item['subscription_switch']['subscription_id'] );
			$existing_item = wcs_get_order_item( $cart_item['subscription_switch']['item_id'], $subscription );

			if ( empty( $subscription ) || empty( $existing_item ) ) {
				$this->cart->remove_cart_item( $cart_item_key );
				continue;
			}

			$switches[ $cart_item_key ] = new WCS_Switch_Cart_Item( $cart_item, $subscription, $existing_item );
		}

		return $switches;
	}

	/** Logic Functions */

	/**
	 * Whether the recurring price should be prorated based on the store's switch settings.
	 *
	 * @param WCS_Switch_Cart_Item $switch_item
	 * @return bool
	 * @since 2.6.0
	 */
	protected function should_prorate_recurring_price( $switch_item ) {
		$prorate_all     = in_array( $this->apportion_recurring_price, array( 'yes', 'yes-upgrade' ) );
		$prorate_virtual = in_array( $this->apportion_recurring_price, array( 'virtual', 'virtual-upgrade' ) );

		return $prorate_all || ( $prorate_virtual && $switch_item->is_virtual_product() );
	}

	/**
	 * Whether the current subscription's prepaid term should reduced.
	 *
	 * @param WCS_Switch_Cart_Item $switch_item
	 * @return bool
	 * @since 2.6.0
	 */
	protected function should_reduce_prepaid_term( $switch_item ) {
		$days_in_old_cycle = $switch_item->get_days_in_old_cycle();
		$days_in_new_cycle = $switch_item->get_days_in_new_cycle();

		$is_switch_out_of_trial = 0 == $switch_item->get_total_paid_for_current_period() && ! $switch_item->trial_periods_match() && $switch_item->is_switch_during_trial();

		/**
		 * By default, reduce the prepaid term if:
		 *  - The customer is leaving a free trial, this is determined by:
		 *     - The subscription is still on trial,
		 *     - They haven't paid anything in sign-up fees or early renewals since sign-up.
		 *     - The old trial period and length doesn't match the new one.
		 *  - Or there are more days in the in old cycle as there are in the in new cycle (switching from yearly to monthly)
		 */
		return apply_filters( 'wcs_switch_proration_reduce_pre_paid_term', $is_switch_out_of_trial || $days_in_old_cycle > $days_in_new_cycle, $switch_item->subscription, $switch_item->cart_item, $days_in_old_cycle, $days_in_new_cycle, $switch_item->get_old_price_per_day(), $switch_item->get_new_price_per_day() );
	}

	/**
	 * Whether the current subscription's prepaid term should extended based on the store's switch settings.
	 *
	 * @return bool
	 * @since 2.6.0
	 */
	protected function should_extend_prepaid_term() {
		return in_array( $this->apportion_recurring_price, array( 'virtual', 'yes' ) );
	}

	/** Total Calculators */

	/**
	 * Apportion any sign-up fees if required.
	 *
	 * Implements the store's apportion sign-up fee setting (@see $this->apportion_sign_up_fee).
	 *
	 * @param WCS_Switch_Cart_Item $switch_item
	 * @since 2.6.0
	 */
	protected function apportion_sign_up_fees( $switch_item ) {
		if ( 'no' === $this->apportion_sign_up_fee ) {
			$switch_item->product->update_meta_data( '_subscription_sign_up_fee', 0 );
		} elseif ( 'yes' === $this->apportion_sign_up_fee ) {
			$product = wc_get_product( $switch_item->canonical_product_id );

			// Make sure we get a fresh copy of the product's meta to avoid prorating an already prorated sign-up fee
			$product->read_meta_data( true );

			// Because product add-ons etc. don't apply to sign-up fees, it's safe to use the product's sign-up fee value rather than the cart item's
			$sign_up_fee_due  = WC_Subscriptions_Product::get_sign_up_fee( $product );
			$sign_up_fee_paid = $switch_item->subscription->get_items_sign_up_fee( $switch_item->existing_item, $this->prices_include_tax ? 'inclusive_of_tax' : 'exclusive_of_tax' );

			// Make sure total prorated sign-up fee is prorated across total amount of sign-up fee so that customer doesn't get extra discounts
			if ( $switch_item->cart_item['quantity'] > $switch_item->existing_item['qty'] ) {
				$sign_up_fee_paid = ( $sign_up_fee_paid * $switch_item->existing_item['qty'] ) / $switch_item->cart_item['quantity'];
			}

			$switch_item->product->update_meta_data( '_subscription_sign_up_fee', max( $sign_up_fee_due - $sign_up_fee_paid, 0 ) );
			$switch_item->product->update_meta_data( '_subscription_sign_up_fee_prorated', WC_Subscriptions_Product::get_sign_up_fee( $switch_item->product ) );
		}
	}

	/**
	 * Calculate the number of days the customer is entitled to at the new product's price per day
	 * and reduce the subscription's prepaid term to match.
	 *
	 * @param string $cart_item_key
	 * @param WCS_Switch_Cart_Item $switch_item
	 * @since 2.6.0
	 */
	protected function reduce_prepaid_term( $cart_item_key, $switch_item ) {
		// Find out how many days at the new price per day the customer would receive for the total amount already paid
		// (e.g. if the customer paid $10 / month previously, and was switching to a $5 / week subscription, she has pre-paid 14 days at the new price)
		$pre_paid_days = $this->calculate_pre_paid_days( $switch_item->get_total_paid_for_current_period(), $switch_item->get_new_price_per_day() );

		// If the total amount the customer has paid entitles her to more days at the new price than she has received, there is no gap payment, just shorten the pre-paid term the appropriate number of days
		if ( $switch_item->get_days_since_last_payment() < $pre_paid_days ) {
			$this->cart->cart_contents[ $cart_item_key ]['subscription_switch']['first_payment_timestamp'] = $switch_item->get_last_order_created_time() + ( $pre_paid_days * DAY_IN_SECONDS );
		} else {
			// If the total amount the customer has paid entitles her to the same or fewer days at the new price then start the new subscription from today
			$this->cart->cart_contents[ $cart_item_key ]['subscription_switch']['first_payment_timestamp'] = 0;
		}
	}

	/**
	 * Calculate the upgrade cost for a given switch.
	 *
	 * @param WCS_Switch_Cart_Item $switch_item
	 * @return float The amount to pay for the upgrade.
	 * @since 2.6.0
	 */
	protected function calculate_upgrade_cost( $switch_item ) {
		$extra_to_pay = $switch_item->get_days_until_next_payment() * ( $switch_item->get_new_price_per_day() - $switch_item->get_old_price_per_day() );

		// When calculating a subscription with one length (no more next payment date and the end date may have been pushed back) we need to pay for those extra days at the new price per day between the old next payment date and new end date
		if ( ! $switch_item->is_switch_during_trial() && 1 == WC_Subscriptions_Product::get_length( $switch_item->product ) ) {
			$days_to_new_end = floor( ( $switch_item->get_end_timestamp() - $switch_item->next_payment_timestamp ) / DAY_IN_SECONDS );

			if ( $days_to_new_end > 0 ) {
				$extra_to_pay += $days_to_new_end * $switch_item->get_new_price_per_day();
			}
		}

		// We need to find the per item extra to pay so we can set it as the sign-up fee (WC will then multiply it by the quantity)
		$extra_to_pay = $extra_to_pay / $switch_item->cart_item['quantity'];
		return apply_filters( 'wcs_switch_proration_extra_to_pay', $extra_to_pay, $switch_item->subscription, $switch_item->cart_item, $switch_item->get_days_in_old_cycle() );
	}

	/** Setters */

	/**
	 * Set the first payment timestamp on the cart item.
	 *
	 * @param string $cart_item_key The cart item key.
	 * @param int $first_payment_timestamp The first payment timestamp.
	 * @since 2.6.0
	 */
	public function set_first_payment_timestamp( $cart_item_key, $first_payment_timestamp ) {
		$this->cart->cart_contents[ $cart_item_key ]['subscription_switch']['first_payment_timestamp'] = $first_payment_timestamp;
	}

	/**
	 * Set the end timestamp on the cart item.
	 *
	 * @param string $cart_item_key The cart item key.
	 * @param int $end_timestamp The subscription's end date timestamp.
	 * @since 2.6.0
	 */
	public function set_end_timestamp( $cart_item_key, $end_timestamp ) {
		$this->cart->cart_contents[ $cart_item_key ]['subscription_switch']['end_timestamp'] = $end_timestamp;
	}

	/**
	 * Set the switch type on the cart item.
	 *
	 * To preserve past tense for backward compatibility 'd' will be appended to the $switch_type.
	 *
	 * @param string $cart_item_key The cart item's key.
	 * @param string $switch_type Can be upgrade, downgrade or crossgrade.
	 * @since 2.6.0
	 */
	public function set_switch_type_in_cart( $cart_item_key, $switch_type ) {
		$this->cart->cart_contents[ $cart_item_key ]['subscription_switch']['upgraded_or_downgraded'] = sprintf( '%sd', $switch_type );
	}

	/**
	 * Reset any previously calculated prorated price.
	 *
	 * @param WCS_Switch_Cart_Item $switch_item
	 * @since 2.6.0
	 */
	public function reset_prorated_price( $switch_item ) {
		if ( $switch_item->product->meta_exists( '_subscription_price_prorated' ) ) {
			$prorated_sign_up_fee = $switch_item->product->get_meta( '_subscription_sign_up_fee_prorated' );
			$switch_item->product->update_meta_data( '_subscription_sign_up_fee', $prorated_sign_up_fee );
		}
	}

	/**
	 * Set the upgrade cost on the cart item product instance as a sign up fee.
	 *
	 * @param WCS_Switch_Cart_Item $switch_item
	 * @param float $extra_to_pay The upgrade cost.
	 * @since 2.6.0
	 */
	public function set_upgrade_cost( $switch_item, $extra_to_pay ) {
		// Keep a record of the original sign-up fees
		$existing_sign_up_fee = WC_Subscriptions_Product::get_sign_up_fee( $switch_item->product );
		$switch_item->product->update_meta_data( '_subscription_sign_up_fee_prorated', $existing_sign_up_fee );

		$switch_item->product->update_meta_data( '_subscription_price_prorated', $extra_to_pay );
		$switch_item->product->update_meta_data( '_subscription_sign_up_fee', $existing_sign_up_fee + $extra_to_pay );
	}
}
