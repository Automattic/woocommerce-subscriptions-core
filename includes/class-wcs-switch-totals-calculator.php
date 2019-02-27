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
}
