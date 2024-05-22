<?php
use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;

/**
 * Handles the change address via checkout flow.
 *
 * @package WooCommerce Subscriptions
 * @since 5.9.0
 */

defined( 'ABSPATH' ) || exit;

class WC_Subscriptions_Change_Address_Via_Checkout_Handler {



	/**
	 * The cart class instance responsible for handling the subscription through the cart.
	 *
	 * @var
	 */
	private static $edit_subscription_cart_instance;

	/**
	 * Initializes the class and hooks.
	 */
	public static function init() {
		add_action( 'wp_loaded', array( __CLASS__, 'maybe_add_subscription_to_cart_for_address_change' ) );
		add_action( 'woocommerce_checkout_before_customer_details', array( __CLASS__, 'maybe_add_hidden_input_for_address_change' ) );
		add_filter( 'woocommerce_update_order_review_fragments', array( __CLASS__, 'maybe_modify_checkout_template_for_address_change' ) );
		add_action( 'woocommerce_checkout_process', array( __CLASS__, 'process_address_change' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_navigated_away_from_change_address_page' ) );
		add_filter( 'woocommerce_cart_needs_payment', array( __CLASS__, 'cart_requires_payment' ) );
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'validate_add_to_cart' ), 10, 1 );

		if ( function_exists( 'is_checkout' ) && is_checkout() && self::cart_contains_change_address_request() ) {
			self::attach_callbacks_for_address_change_request();
		}

		add_filter( 'the_title', array( __CLASS__, 'title_for_address_change' ), 100 );

		// Applying coupons via this flow is not supported.
		add_filter( 'woocommerce_coupons_enabled', '__return_false' );

		// Add breadcrumbs for the address change request.
		add_filter( 'woocommerce_get_breadcrumb', array( __CLASS__, 'crumbs_for_address_change' ), 10, 1 );

		// Cart
		self::$edit_subscription_cart_instance = new WCS_Cart_Change_Address();

		// BLOCK CHECKOUT SUPPORT
		add_action( 'woocommerce_store_api_checkout_update_order_meta', array( __CLASS__, 'maybe_update_subscription_addresses_from_rest_api' ), 10, 2 );
		add_action( 'woocommerce_order_needs_payment', array( __CLASS__, 'order_does_not_need_payment' ), 10, 2 );
		add_action( 'woocommerce_get_checkout_order_received_url', array( __CLASS__, 'change_order_received_url' ), 10, 2 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'process_request' ) );

		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'register_scripts' ] );

		add_filter( 'woocommerce_order_button_text', array( __CLASS__, 'order_button_text' ) );
	}

	/**
	 * Overrides the "Place order" button text with "Sign up now" when the cart contains initial subscription purchases.
	 *
	 * @since 1.0.0 - Migrated from WooCommerce Subscriptions v4.0.0
	 *
	 * @param string  $button_text The place order button text.
	 * @return string $button_text
	 */
	public static function order_button_text( $button_text ) {
		if ( self::cart_contains_change_address_request() ) {
			$button_text = self::get_change_address_submit_button_text();
		}

		return $button_text;
	}

	/**
	 * Undocumented function
	 *
	 * @return string The submit button text.
	 */
	private static function get_change_address_submit_button_text() {
		return apply_filters( 'wc_subscription_update_address_button_text', __( 'Update address', 'woocommerce-subscriptions' ) );
	}

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	public static function register_scripts() {
		if ( ! self::cart_contains_change_address_request() ) {
			return;
		}

		wp_enqueue_script(
			'wc-change-subscription-shipping-scripts',
			WC_Subscriptions_Core_Plugin::instance()->get_subscriptions_core_directory_url( 'assets/js/frontend/change-address-checkout.js' ),
			array( 'jquery' ),
			WC_Subscriptions_Core_Plugin::instance()->get_library_version(),
			true
		);

		$script_data = array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'strings'  => array(
				'order_summary'                => __( 'Subscription totals', 'woocommerce-subscriptions' ),
				'contact_description'          => __( "We'll use this email to send you details and updates about your subscription.", 'woocommerce-subscriptions' ),
				'shipping_address_description' => __( 'Enter the address where you want your subscription orders delivered.', 'woocommerce-subscriptions' ),
				'change_address_submit'        => self::get_change_address_submit_button_text(),
				'is_block_checkout'            => has_block( 'woocommerce/checkout', wc_get_page_id( 'checkout' ) ),
			),
		);

		wp_localize_script( 'wc-change-subscription-shipping-scripts', 'wcs_change_subscription_shipping_data', $script_data );
	}

	/**
	 * Changes the default checkout page title for a the address change request.
	 *
	 * @param string $title The default page title.
	 * @return string The page title filtered if it's a update subscription change request.
	 */
	public static function title_for_address_change( $title ) {
		// Skip if not on checkout pay page or not a address change request.
		if ( ! self::cart_contains_change_address_request() || ! is_main_query() || ! in_the_loop() || ! is_page() || ! is_checkout() ) {
			return $title;
		}

		return __( 'Change subscription address', 'woocommerce-subscriptions' );
	}

	/**
	 * Loads a subscription into the cart enabling the customer to change their address and for shipping to be recalculated.
	 */
	public static function maybe_add_subscription_to_cart_for_address_change() {

		if ( ! isset( $_GET['update_subscription_address'], $_GET['wcs_nonce'] ) ) {
			return;
		}

		$subscription_id = absint( $_GET['update_subscription_address'] ?? null );

		if ( ! wp_verify_nonce( wc_clean( wp_unslash( $_GET['wcs_nonce'] ) ), 'wcs_edit_address_' . $subscription_id ) ) {
			wc_add_notice( __( 'There was a problem with that request. Please try again.', 'woocommerce-subscriptions' ), 'error' );
			return;
		}

		if ( ! WC_Subscriptions_Addresses::can_user_edit_subscription_address( $subscription_id ) ) {
			wc_add_notice( __( "You cannot change that subscription's address", 'woocommerce-subscriptions' ), 'error' );
			return;
		}

		$subscription = wcs_get_subscription( $subscription_id );

		if ( ! $subscription ) {
			wc_add_notice( __( 'There was a problem locating that subscription, please try again', 'woocommerce-subscriptions' ), 'error' );
			return;
		}

		self::$edit_subscription_cart_instance->add_subscription_to_cart( $subscription );
	}

	/**
	 * Changes the default bread crumb list for a the address change request.
	 *
	 * @param array $crumbs Bread crumbs for the current page request.
	 * @return array Bread crumbs for the current page request.
	 */
	public static function crumbs_for_address_change( $crumbs ) {

		if ( ! is_main_query() && ! is_page() && ! is_checkout() ) {
			return $crumbs;
		}

		if ( ! isset( $_GET['update_subscription_address'] ) && ! self::cart_contains_change_address_request() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $crumbs;
		}

		if ( isset( $_GET['update_subscription_address'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$subscription = wcs_get_subscription( absint( $_GET['update_subscription_address'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} else {
			$subscription = self::get_subscription_from_cart();
		}

		if ( ! $subscription ) {
			return $crumbs;
		}

		$crumbs[1] = array(
			get_the_title( wc_get_page_id( 'myaccount' ) ),
			get_permalink( wc_get_page_id( 'myaccount' ) ),
		);

		$crumbs[2] = array(
			// translators: %s: order number.
			sprintf( _x( 'Subscription #%s', 'hash before order number', 'woocommerce-subscriptions' ), $subscription->get_order_number() ),
			esc_url( $subscription->get_view_order_url() ),
		);

		$crumbs[3] = array(
			_x( 'Change subscription address', 'the page title of the change payment method form', 'woocommerce-subscriptions' ),
			'',
		);

		return $crumbs;
	}

	/**
	 * Adds a hidden input to the checkout form to indicate that the address is being changed.
	 */
	public static function maybe_add_hidden_input_for_address_change() {
		$subscription_id = absint( wc_clean( wp_unslash( $_GET['update_subscription_address'] ?? null ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $subscription_id ) {
			echo '<input type="hidden" name="update_subscription_address" value="' . esc_attr( $subscription_id ) . '">';
		}
	}

	/**
	 * Replaces the default checkout pay button with the address change button.
	 *
	 * @param array $fragments Fragments of the checkout page.
	 * @return array Fragments of the checkout page.
	 */
	public static function maybe_modify_checkout_template_for_address_change( $fragments ) {
		// Ignoring the nonce check here as it's already been verified in WC_AJAX::update_order_review().
		$form_data = wp_parse_args( wc_clean( wp_unslash( $_POST['post_data'] ?? null ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! isset( $form_data['update_subscription_address'] ) ) {
			return $fragments;
		}

		$subscription_id = absint( $form_data['update_subscription_address'] );

		if ( ! WC_Subscriptions_Addresses::can_user_edit_subscription_address( $subscription_id ) ) {
			return $fragments;
		}

		ob_start();

		wc_get_template(
			'checkout/update-address-for-subscription.php',
			array(
				'subscription'      => wcs_get_subscription( $subscription_id ),
				'order_button_text' => apply_filters( 'wcs_update_address_button_text', __( 'Update address', 'woocommerce-subscriptions' ) ),
			),
			'',
			WC_Subscriptions_Core_Plugin::instance()->get_subscriptions_core_directory( 'templates/' )
		);

		$new_fragment = ob_get_clean(); // @codingStandardsIgnoreLine
		$fragments['.woocommerce-checkout-payment'] = $new_fragment;

		return $fragments;
	}

	/**
	 * Processes the address change for a subscription.
	 */
	public static function process_address_change() {
		$subscription = self::get_subscription_from_cart();

		if ( ! $subscription ) {
			return;
		}

		if ( ! WC_Subscriptions_Addresses::can_user_edit_subscription_address( $subscription ) ) {
			self::handle_redirect( $subscription, 'failure' );
		}

		$checkout_data = WC()->checkout->get_posted_data();

		// Prepare the new address data for the subscription.
		foreach ( [ 'billing', 'shipping' ] as $address_type ) {
			$address_fields = WC()->countries->get_address_fields( wc_clean( wp_unslash( $checkout_data[ $address_type . '_country' ] ?? '' ) ), $address_type . '_' );
			$address        = array();

			foreach ( $address_fields as $key => $field ) {
				if ( isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
					$address[ str_replace( $address_type . '_', '', $key ) ] = wc_clean( wp_unslash( $checkout_data[ $key ] ) );
				}
			}

			$subscription->set_address( $address, $address_type );
		}

		// Remove existing subscription shipping items before updating.
		$subscription_shipping_items = (array) $subscription->get_items( 'shipping' );
		if ( count( $subscription_shipping_items ) > 0 ) {
			foreach ( $subscription_shipping_items as $item_id => $item ) {
				$subscription->remove_item( $item_id );
			}
		}

		$cart = WC()->cart;

		// Update the subscription shipping items.
		WC_Subscriptions_Checkout::add_shipping( $subscription, $cart );

		$subscription->set_shipping_total( $cart->shipping_total );
		$subscription->set_cart_tax( $cart->tax_total );
		$subscription->set_shipping_tax( $cart->shipping_tax_total );
		$subscription->set_total( $cart->total );
		$subscription->save();

		if ( count( WC()->cart->get_cart() ) > 0 ) {
			WC()->cart->empty_cart();
		}

		self::handle_redirect( $subscription, 'success' );
	}

	/**
	 * Redirects the user after the change address request has been handled.
	 *
	 * @param WC_Subscription $subscription The subscription.
	 * @param string          $result       The result type. Can be 'success' or 'failure'.
	 */
	private static function handle_redirect( $subscription, $result ) {

		if ( 'success' !== $result ) {
			wc_add_notice( __( 'There was a problem updating your address. Please try again.', 'woocommerce-subscriptions' ), 'error' );
		}

		// IF NOT AJAX
		if ( ! is_ajax() ) {
			wp_safe_redirect( $subscription->get_view_order_url() );
			exit();
		}

		// IF AJAX
		wp_send_json(
			array(
				'messages' => wc_print_notices( true ),
				'result'   => $result,
				'redirect' => $subscription->get_view_order_url(),
			)
		);
	}

	/**
	 * Empties the cart for a change address request when the user navigates away from the change address page.
	 */
	public static function maybe_navigated_away_from_change_address_page() {
		// We're only interested in carts that contain a change address request.
		if ( ! self::cart_contains_change_address_request() ) {
			return;
		}

		if ( ! isset( $_GET['update_subscription_address'] ) && ! is_admin() && ! is_ajax() && ! WC()->is_rest_api_request() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			// @todo Check if needed
			WC()->cart->empty_cart( true );
		}
	}

	/**
	 * Checks whether the cart contains a change address request.
	 *
	 * @return bool Whether the cart contains a change address request.
	 */
	public static function cart_contains_change_address_request() {
		if ( ! function_exists( 'WC' ) || ! isset( WC()->cart ) ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item[ self::$edit_subscription_cart_instance->cart_item_key ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Gets the subscription being changed from the cart, otherwise returns false.
	 *
	 * @return WC_Subscription|bool The subscription being changed, or false if none.
	 */
	public static function get_subscription_from_cart() {
		if ( ! function_exists( 'WC' ) || ! isset( WC()->cart ) ) {
			return false;
		}

		$subscription = false;

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item[ self::$edit_subscription_cart_instance->cart_item_key ] ) ) {
				$subscription = wcs_get_subscription( absint( $cart_item[ self::$edit_subscription_cart_instance->cart_item_key ]['subscription_id'] ) );
				break;
			}
		}

		return $subscription;
	}

	/**
	 * Checks if the cart needs payment.
	 *
	 * Carts that contain a change address request do not need payment.
	 *
	 * @param bool $requires_payment The default value for if the cart requires payment.
	 * @return bool The updated value for if the cart requires payment.
	 */
	public static function cart_requires_payment( $requires_payment ) {
		if ( ! $requires_payment ) {
			return $requires_payment;
		}

		return ! self::cart_contains_change_address_request();
	}

	/**
	 * Validates an add to cart request when the cart contains a change address request.
	 *
	 * @throws Exception When the cart contains a change address request and an item is being added to the cart.
	 *
	 * @param array $cart_item_data The cart item data for the item being added to the cart.
	 * @return array The cart item data.
	 */
	public static function validate_add_to_cart( $cart_item_data ) {
		if ( isset( $cart_item_data['update_subscription_address'] ) || ! self::cart_contains_change_address_request() ) {
			return $cart_item_data;
		}

		throw new Exception( __( 'You cannot add items to the cart while changing the address of a subscription.', 'woocommerce-subscriptions' ) );
	}

	/**
	 * BLOCK CHECKOUT INTEGRATION METHODS
	 */

	/**
	 * Filters the default value for the needs_payment property of an created during the checkout block.
	 *
	 * @param [type] $needs_payment
	 * @param [type] $order
	 * @return void
	 */
	public static function order_does_not_need_payment( $needs_payment, $order ) {
		if ( $order->meta_exists( '_subscription_address_change' ) ) {
			return false;
		}

		return $needs_payment;
	}

	public static function maybe_update_subscription_addresses_from_rest_api( $order ) {
		$subscription = self::get_subscription_from_cart();

		$e = new Exception();

		if ( ! $subscription ) {
			return;
		}

		$order->update_meta_data( '_subscription_address_change', $subscription->get_id() );
		$order->save();

		// Update the subscription from the order.
		// Set order meta so this order can be tied up later.
	}

	/**
	 * Undocumented function
	 *
	 * @param string $url
	 * @param [type] $order
	 * @return void
	 */
	public static function change_order_received_url( $url, $order ) {
		if ( ! $order->meta_exists( '_subscription_address_change' ) ) {
			return $url;
		}

		$subscription = wcs_get_subscription( $order->get_meta( '_subscription_address_change' ) );

		return $subscription->get_view_order_url();
	}

	/**
	 * Undocumented function
	 *
	 * @param [type] $order
	 * @return void
	 */
	public static function process_request( $order ) {
		if ( ! $order->meta_exists( '_subscription_address_change' ) ) {
			return;
		}

		$subscription = wcs_get_subscription( $order->get_meta( '_subscription_address_change' ) );

		wcs_copy_order_address( $order, $subscription );

		// Remove existing subscription shipping items before updating.
		foreach ( (array) $subscription->get_items( 'shipping' ) as $item_id => $item ) {
			$subscription->remove_item( $item_id );
		}

		// Update the subscription from the cart.
		$cart = WC()->cart;

		WC()->checkout->create_order_tax_lines( $subscription, $cart );

		// Update the subscription shipping items.
		WC_Subscriptions_Checkout::add_shipping( $subscription, $cart );

		$subscription->set_shipping_total( $cart->shipping_total );
		$subscription->set_cart_tax( $cart->tax_total );
		$subscription->set_shipping_tax( $cart->shipping_tax_total );
		$subscription->set_total( $cart->total );
		$subscription->save();

		if ( count( WC()->cart->get_cart() ) > 0 ) {
			WC()->cart->empty_cart();
		}
	}
}
