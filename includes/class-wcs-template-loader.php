<?php
/**
 * WC Subscriptions Template Loader
 *
 * @version 2.0
 * @author  Prospress
 */
class WCS_Template_Loader {

	public static function init() {
		add_action( 'woocommerce_account_view-subscription_endpoint', array( __CLASS__, 'get_view_subscription_template' ) );
		add_action( 'woocommerce_subscription_details_table', array( __CLASS__, 'get_subscription_details_template' ) );
		add_action( 'woocommerce_subscription_totals_table', array( __CLASS__, 'get_subscription_totals_template' ) );
		add_action( 'woocommerce_subscription_totals_table', array( __CLASS__, 'get_order_downloads_template' ), 20 );
		add_action( 'woocommerce_subscription_totals', array( __CLASS__, 'get_subscription_totals_table_template' ), 10, 4 );
		add_action( 'woocommerce_subscriptions_recurring_totals_subtotals', array( __CLASS__, 'get_recurring_cart_subtotals' ) );
		add_action( 'woocommerce_subscriptions_recurring_totals_coupons', array( __CLASS__, 'get_recurring_cart_coupons' ) );
		add_action( 'woocommerce_subscriptions_recurring_totals_shipping', array( __CLASS__, 'get_recurring_cart_shipping' ) );
		add_action( 'woocommerce_subscriptions_recurring_totals_fees', array( __CLASS__, 'get_recurring_cart_fees' ) );
		add_action( 'woocommerce_subscriptions_recurring_totals_taxes', array( __CLASS__, 'get_recurring_cart_taxes' ) );
		add_action( 'woocommerce_subscriptions_recurring_subscription_totals', array( __CLASS__, 'get_recurring_subscription_totals' ) );

	}

	/**
	 * Get the view subscription template.
	 *
	 * @param int $subscription_id Subscription ID.
	 * @since 2.0.17
	 */
	public static function get_view_subscription_template( $subscription_id ) {
		$subscription = wcs_get_subscription( absint( $subscription_id ) );

		if ( ! $subscription || ! current_user_can( 'view_order', $subscription->get_id() ) ) {
			echo '<div class="woocommerce-error">' . esc_html__( 'Invalid Subscription.', 'woocommerce-subscriptions' ) . ' <a href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '" class="wc-forward">' . esc_html__( 'My Account', 'woocommerce-subscriptions' ) . '</a>' . '</div>';
			return;
		}

		wc_get_template( 'myaccount/view-subscription.php', compact( 'subscription' ), '', plugin_dir_path( WC_Subscriptions::$plugin_file ) . 'templates/' );
	}

	/**
	 * Get the subscription details template, which is part of the view subscription page.
	 *
	 * @param WC_Subscription $subscription Subscription object
	 * @since 2.2.19
	 */
	public static function get_subscription_details_template( $subscription ) {
		wc_get_template( 'myaccount/subscription-details.php', array( 'subscription' => $subscription ), '', plugin_dir_path( WC_Subscriptions::$plugin_file ) . 'templates/' );
	}

	/**
	 * Get the subscription totals template, which is part of the view subscription page.
	 *
	 * @param WC_Subscription $subscription Subscription object
	 * @since 2.2.19
	 */
	public static function get_subscription_totals_template( $subscription ) {
		wc_get_template( 'myaccount/subscription-totals.php', array( 'subscription' => $subscription ), '', plugin_dir_path( WC_Subscriptions::$plugin_file ) . 'templates/' );
	}

	/**
	 * Get the order downloads template, which is part of the view subscription page.
	 *
	 * @param WC_Subscription $subscription Subscription object
	 * @since 2.5.0
	 */
	public static function get_order_downloads_template( $subscription ) {
		if ( $subscription->has_downloadable_item() && $subscription->is_download_permitted() ) {
			wc_get_template(
				'order/order-downloads.php',
				array(
					'downloads'  => $subscription->get_downloadable_items(),
					'show_title' => true,
				)
			);
		}
	}

	/**
	 * Gets the subscription totals table.
	 *
	 * @since 2.6.0
	 *
	 * @param WC_Subscription $subscription     The subscription to print the totals table for.
	 * @param bool  $include_item_removal_links Whether the remove line item links should be included.
	 * @param array $totals                     The subscription totals rows to be displayed.
	 * @param bool  $include_switch_links       Whether the line item switch links should be included.
	 */
	public static function get_subscription_totals_table_template( $subscription, $include_item_removal_links, $totals, $include_switch_links = true ) {

		// If the switch links shouldn't be printed, remove the callback which prints them.
		if ( false === $include_switch_links ) {
			$callback_detached = remove_action( 'woocommerce_order_item_meta_end', 'WC_Subscriptions_Switcher::print_switch_link' );
		}

		wc_get_template(
			'myaccount/subscription-totals-table.php',
			array(
				'subscription'       => $subscription,
				'allow_item_removal' => $include_item_removal_links,
				'totals'             => $totals,
			),
			'',
			plugin_dir_path( WC_Subscriptions::$plugin_file ) . 'templates/'
		);

		// Reattach the callback if it was successfully removed.
		if ( false === $include_switch_links && $callback_detached ) {
			add_action( 'woocommerce_order_item_meta_end', 'WC_Subscriptions_Switcher::print_switch_link', 10, 3 );
		}
	}

	/**
	 * Gets the subscription receipt template content.
	 *
	 * @since 3.0.0
	 *
	 * @param WC_Subscription $subscription The subscription to display the receipt for.
	 */
	public static function get_subscription_receipt_template( $subscription ) {
		wc_get_template( 'checkout/subscription-receipt.php', array( 'subscription' => $subscription ), '', plugin_dir_path( WC_Subscriptions::$plugin_file ) . 'templates/' );
	}

	/**
	 * Gets the recurring totals subtotal rows content.
	 *
	 * @since 3.1.0
	 *
	 * @param array $recurring_carts The recurring carts.
	 */
	public static function get_recurring_cart_subtotals( $recurring_carts ) {
		$recurring_carts = wcs_apply_array_filter( 'woocommerce_subscriptions_display_recurring_subtotals', $recurring_carts, 'next_payment_date' );
		wc_get_template( 'checkout/recurring-subtotals.php', array( 'recurring_carts' => $recurring_carts ), '', plugin_dir_path( WC_Subscriptions::$plugin_file ) . 'templates/' );
	}

	/**
	 * Gets the recurring totals coupon rows content.
	 *
	 * @since 3.1.0
	 *
	 * @param array $recurring_carts The recurring carts.
	 */
	public static function get_recurring_cart_coupons( $recurring_carts ) {
		// Filter out all recurring carts without a next payment date.
		$recurring_carts = wcs_apply_array_filter( 'woocommerce_subscriptions_display_recurring_coupon_totals', $recurring_carts, 'next_payment_date' );
		wc_get_template( 'checkout/recurring-coupon-totals.php', array( 'recurring_carts' => $recurring_carts ), '', plugin_dir_path( WC_Subscriptions::$plugin_file ) . 'templates/' );
	}

	/**
	 * Gets the recurring totals shipping rows content.
	 *
	 * @since 3.1.0
	 */
	public static function get_recurring_cart_shipping() {
		if ( WC()->cart->show_shipping() && WC_Subscriptions_Cart::cart_contains_subscriptions_needing_shipping() ) {
			wcs_cart_totals_shipping_html();
		}
	}

	/**
	 * Gets the recurring totals fee rows content.
	 *
	 * @since 3.1.0
	 *
	 * @param array $recurring_carts The recurring carts.
	 */
	public static function get_recurring_cart_fees( $recurring_carts ) {
		// Filter out all recurring carts without a next payment date.
		$recurring_carts = wcs_apply_array_filter( 'woocommerce_subscriptions_display_recurring_fee_totals', $recurring_carts, 'next_payment_date' );
		wc_get_template( 'checkout/recurring-fee-totals.php', array( 'recurring_carts' => $recurring_carts ), '', plugin_dir_path( WC_Subscriptions::$plugin_file ) . 'templates/' );
	}

	/**
	 * Gets the recurring totals tax rows content.
	 *
	 * @since 3.1.0
	 *
	 * @param array $recurring_carts The recurring carts.
	 */
	public static function get_recurring_cart_taxes( $recurring_carts ) {
		$tax_display_mode = WC_Subscriptions::is_woocommerce_pre( '4.4' ) ? WC()->cart->tax_display_cart : WC()->cart->get_tax_price_display_mode();

		if ( ! wc_tax_enabled() || 'excl' !== $tax_display_mode ) {
			return;
		}

		// Filter out all recurring carts without a next payment date.
		$recurring_carts = wcs_apply_array_filter( 'woocommerce_subscriptions_display_recurring_tax_totals', $recurring_carts, 'next_payment_date' );

		if ( 'itemized' === get_option( 'woocommerce_tax_total_display' ) ) {
			wc_get_template( 'checkout/recurring-itemized-tax-totals.php', array( 'recurring_carts' => $recurring_carts ), '', plugin_dir_path( WC_Subscriptions::$plugin_file ) . 'templates/' );
		} else {
			wc_get_template( 'checkout/recurring-tax-totals.php', array( 'recurring_carts' => $recurring_carts ), '', plugin_dir_path( WC_Subscriptions::$plugin_file ) . 'templates/' );
		}
	}

	/**
	 * Gets the recurring subscription total rows content.
	 *
	 * @since 3.1.0
	 *
	 * @param array $recurring_carts The recurring carts.
	 */
	public static function get_recurring_subscription_totals( $recurring_carts ) {
		// Filter out all recurring carts without a next payment date.
		$recurring_carts = wcs_apply_array_filter( 'woocommerce_subscriptions_display_recurring_subscription_totals', $recurring_carts, 'next_payment_date' );
		wc_get_template( 'checkout/recurring-subscription-totals.php', array( 'recurring_carts' => $recurring_carts ), '', plugin_dir_path( WC_Subscriptions::$plugin_file ) . 'templates/' );
	}
}
