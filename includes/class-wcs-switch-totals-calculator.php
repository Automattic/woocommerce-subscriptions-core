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
}
