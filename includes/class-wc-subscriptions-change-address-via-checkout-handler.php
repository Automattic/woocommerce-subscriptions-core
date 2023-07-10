<?php
/**
 * Handles the change address via checkout flow.
 *
 * @package WooCommerce Subscriptions
 * @since 5.9.0
 */

defined( 'ABSPATH' ) || exit;

class WC_Subscriptions_Change_Address_Via_Checkout_Handler {

	/**
	 * Initializes the class and hooks.
	 */
	public static function init() {
		add_action( 'wp_loaded', array( __CLASS__, 'maybe_add_subscription_to_cart_for_address_change' ) );
		add_action( 'woocommerce_checkout_before_customer_details', array( __CLASS__, 'maybe_add_hidden_input_for_address_change' ) );
		add_filter( 'woocommerce_update_order_review_fragments', array( __CLASS__, 'maybe_modify_checkout_template_for_address_change' ) );
		add_action( 'woocommerce_checkout_process', array( __CLASS__, 'process_address_change' ) );
		add_action( 'wp_loaded', array( __CLASS__, 'maybe_navigated_away_from_change_address_page' ) );
		add_filter( 'woocommerce_cart_needs_payment', array( __CLASS__, 'cart_requires_payment' ) );
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'validate_add_to_cart' ), 10, 1 );

		if ( function_exists( 'is_checkout' ) && is_checkout() && self::cart_contains_change_address_request() ) {
			self::attach_callbacks_for_address_change_request();
		}

		// BLOCK CHECKOUT SUPPORT
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'maybe_update_subscription_addresses_from_rest_api' ), 10, 2 );
		add_action( 'woocommerce_order_needs_payment', array( __CLASS__, 'order_does_not_need_payment' ), 10, 2 );
		///woocommerce_get_checkout_order_received_url --- change the order's redirect URL to send the customer back to the subscription page.
		// To filter the block checkout we need to use JS hook/filters. See examples here:
		//https://github.com/woocommerce/woocommerce-blocks/blob/trunk/docs/third-party-developers/extensibility/checkout-block/available-filters.md#changing-the-wording-and-the-link-on-the-proceed-to-checkout-button-when-a-specific-item-is-in-the-cart
	}

	/**
	 * Attaches callbacks necessary to filter the change address request flow.
	 */
	private static function attach_callbacks_for_address_change_request() {
		// Applying coupons via this flow is not supported.
		add_filter( 'woocommerce_coupons_enabled', '__return_false' );

		// Prevent WC showing separate shipping address fields on checkout.
		add_filter( 'woocommerce_cart_needs_shipping_address', '__return_false' );

		// Change the checkout order review heading and page title while the customer is changing their address.
		add_filter( 'woocommerce_checkout_before_order_review_heading', array( __CLASS__, 'attach_callback_to_change_order_review_heading' ) );
		add_filter( 'woocommerce_checkout_after_order_review_heading', array( __CLASS__, 'remove_callback_to_change_order_review_heading' ) );
		add_filter( 'the_title', array( __CLASS__, 'title_for_address_change' ), 100 );

		// Add breadcrumbs for the address change request.
		add_filter( 'woocommerce_get_breadcrumb', array( __CLASS__, 'crumbs_for_address_change' ), 10, 1 );
	}

	/**
	 * Loads a subscription into the cart enabling the customer to change their address and for shipping to be recalculated.
	 *
	 * This function also saves the current cart contents to the user's meta so that they can be restored after the address change is complete.
	 */
	public static function maybe_add_subscription_to_cart_for_address_change() {
		$subscription_id = absint( $_GET['update_subscription_address'] ?? null ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $subscription_id || ! WC_Subscriptions_Addresses::can_user_edit_subscription_address( $subscription_id ) ) {
			return;
		}

		$subscription = wcs_get_subscription( $subscription_id );

		if ( ! $subscription ) {
			return;
		}

		WC()->cart->empty_cart( true );

		self::setup_cart( $subscription );
		self::attach_callbacks_for_address_change_request();
	}

	/**
	 * Loads the subscription contents into the cart.
	 *
	 * @param WC_Subscription $subscription The subscription to load into the cart.
	 */
	private static function setup_cart( WC_Subscription $subscription ) {
		// Add all the subscription items to cart.
		foreach ( $subscription->get_items() as $subscription_item ) {
			WC()->cart->add_to_cart(
				$subscription_item->get_product_id(),
				$subscription_item->get_quantity(),
				$subscription_item->get_variation_id(),
				[],
				[
					'update_subscription_address' => [
						'subscription_id' => $subscription->get_id(),
					],
				]
			);
		}
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
	 * Changes the default bread crumb list for a the address change request.
	 *
	 * @param array $crumbs Bread crumbs for the current page request.
	 * @return array Bread crumbs for the current page request.
	 */
	public static function crumbs_for_address_change( $crumbs ) {

		if ( ! isset( $_GET['update_subscription_address'] ) && ! is_main_query() && ! is_page() && ! is_checkout() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $crumbs;
		}

		$subscription = wcs_get_subscription( absint( $_GET['update_subscription_address'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

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
	 * Attaches the callback to change the order review heading.
	 */
	public static function attach_callback_to_change_order_review_heading() {
		add_filter( 'gettext', [ __CLASS__, 'filter_default_review_order_details' ], 10, 2 );
	}

	/**
	 * Removes the attached callback which changes the order review heading.
	 */
	public static function remove_callback_to_change_order_review_heading() {
		remove_filter( 'gettext', [ __CLASS__, 'filter_default_review_order_details' ], 10, 2 );
	}

	/**
	 * Changes the default heading for the order review section of the checkout page.
	 *
	 * There is no specific filter so we use the gettext filter to change the text.
	 *
	 * @param string $translated_text The translated text.
	 * @param string $text            The plain text before translation.
	 * @return string The translated text.
	 */
	public static function filter_default_review_order_details( $translated_text, $text ) {
		if ( 'Your order' === $text ) {
			$translated_text = __( 'Subscription totals', 'woocommerce-subscriptions' );
		}

		return $translated_text;
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

		if ( ! is_admin() && ! isset( $_GET['update_subscription_address'] ) && ! is_ajax() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			WC()->cart->empty_cart( true );
		}
	}

	/**
	 * Checks whether the cart contains a change address request.
	 *
	 * @return bool Whether the cart contains a change address request.
	 */
	public static function cart_contains_change_address_request() {
		return (bool) self::get_subscription_from_cart();
	}

	/**
	 * Gets the subscription being changed from the cart, otherwise returns false.
	 *
	 * @return bool|WC_Subscription The subscription being changed, or false if none.
	 */
	public static function get_subscription_from_cart() {
		if ( ! function_exists( 'WC' ) || ! isset( WC()->cart ) ) {
			return false;
		}

		$subscription = false;

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item['update_subscription_address'] ) ) {
				$subscription = wcs_get_subscription( absint( $cart_item['update_subscription_address']['subscription_id'] ) );
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

		if ( ! $subscription ) {
			return;
		}

		$order->update_meta_data( '_subscription_address_change', $subscription->get_id() );
		$order->save();

		// Update the subscription from the order.
		// Set order meta so this order can be tied up later.
	}
}
