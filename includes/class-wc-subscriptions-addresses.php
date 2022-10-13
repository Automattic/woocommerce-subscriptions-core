<?php
/**
 * Subscriptions Address Class
 *
 * Hooks into WooCommerce to handle editing addresses for subscriptions (by editing the original order for the subscription)
 *
 * @package    WooCommerce Subscriptions
 * @subpackage WC_Subscriptions_Addresses
 * @category   Class
 * @since      1.3
 */
class WC_Subscriptions_Addresses {

	/**
	 * Bootstraps the class and hooks required actions & filters.
	 *
	 * @since 1.3
	 */
	public static function init() {

		add_filter( 'wcs_view_subscription_actions', __CLASS__ . '::add_edit_address_subscription_action', 10, 2 );

		add_action( 'template_redirect', array( __CLASS__, 'maybe_restrict_edit_address_endpoint' ) );

		add_action( 'woocommerce_after_edit_address_form_billing', __CLASS__ . '::maybe_add_edit_address_checkbox', 10 );
		add_action( 'woocommerce_after_edit_address_form_shipping', __CLASS__ . '::maybe_add_edit_address_checkbox', 10 );

		add_action( 'woocommerce_customer_save_address', __CLASS__ . '::maybe_update_subscription_addresses', 10, 2 );

		add_filter( 'woocommerce_address_to_edit', __CLASS__ . '::maybe_populate_subscription_addresses', 10 );

		add_filter( 'woocommerce_get_breadcrumb', __CLASS__ . '::change_addresses_breadcrumb', 10, 1 );

		add_action( 'wp_loaded', array( __CLASS__, 'maybe_add_subscription_to_cart_for_address_change' ) );
		add_action( 'woocommerce_checkout_before_customer_details', array( __CLASS__, 'maybe_add_hidden_input_for_address_change' ) );
		add_filter( 'woocommerce_update_order_review_fragments', array( __CLASS__, 'maybe_modify_checkout_template_for_address_change' ) );
		add_action( 'woocommerce_checkout_process', array( __CLASS__, 'process_address_change' ) );
		add_action( 'wp_loaded', array( __CLASS__, 'maybe_navigated_away_from_change_address_page' ) );
	}

	/**
	 * Checks if a user can edit a subscription's address.
	 *
	 * @param int|WC_Subscription $subscription Post ID of a 'shop_subscription' post, or instance of a WC_Subscription object.
	 * @param int                 $user_id      The ID of a user.
	 * @return bool Whether the user can edit the subscription's address.
	 * @since 3.0.15
	 */
	private static function can_user_edit_subscription_address( $subscription, $user_id = 0 ) {
		$subscription = wcs_get_subscription( $subscription );
		$user_id      = empty( $user_id ) ? get_current_user_id() : absint( $user_id );

		return $subscription ? user_can( $user_id, 'view_order', $subscription->get_id() ) : false;
	}

	/**
	 * Add a "Change Shipping Address" button to the "My Subscriptions" table for those subscriptions
	 * which require shipping.
	 *
	 * @param array $all_actions The $subscription_id => $actions array with all actions that will be displayed for a subscription on the "My Subscriptions" table
	 * @param array $subscriptions All of a given users subscriptions that will be displayed on the "My Subscriptions" table
	 * @since 1.3
	 */
	public static function add_edit_address_subscription_action( $actions, $subscription ) {

		if ( $subscription->needs_shipping_address() && $subscription->has_status( array( 'active', 'on-hold' ) ) ) {
			$actions['change_address'] = array(
				'url'  => add_query_arg( array( 'update_subscription_address' => $subscription->get_id() ), wc_get_checkout_url() ),
				'name' => __( 'Change address', 'woocommerce-subscriptions' ),
			);
		}

		return $actions;
	}

	/**
	 * Redirects to "My Account" when attempting to edit the address on a subscription that doesn't belong to the user.
	 *
	 * @since 3.0.15
	 */
	public static function maybe_restrict_edit_address_endpoint() {
		if ( ! is_wc_endpoint_url() || 'edit-address' !== WC()->query->get_current_endpoint() || ! isset( $_GET['subscription'] ) ) {
			return;
		}

		if ( ! self::can_user_edit_subscription_address( absint( $_GET['subscription'] ) ) ) {
			wc_add_notice( 'Invalid subscription.', 'error' );
			wp_safe_redirect( wc_get_account_endpoint_url( 'dashboard' ) );
			exit();
		}
	}

	/**
	 * Outputs the necessary markup on the "My Account" > "Edit Address" page for editing a single subscription's
	 * address or to check if the customer wants to update the addresses for all of their subscriptions.
	 *
	 * If editing their default shipping address, this function adds a checkbox to the to allow subscribers to
	 * also update the address on their active subscriptions. If editing a single subscription's address, the
	 * subscription key is added as a hidden field.
	 *
	 * @since 1.3
	 */
	public static function maybe_add_edit_address_checkbox() {
		global $wp;

		if ( wcs_user_has_subscription() ) {
			$subscription_id = isset( $_GET['subscription'] ) ? absint( $_GET['subscription'] ) : 0;

			if ( $subscription_id && self::can_user_edit_subscription_address( $subscription_id ) ) {

				echo '<p>' . esc_html__( 'Both the shipping address used for the subscription and your default shipping address for future purchases will be updated.', 'woocommerce-subscriptions' ) . '</p>';

				echo '<input type="hidden" name="update_subscription_address" value="' . esc_attr( $subscription_id ) . '" id="update_subscription_address" />';

			} elseif ( ( ( isset( $wp->query_vars['edit-address'] ) && ! empty( $wp->query_vars['edit-address'] ) ) || isset( $_GET['address'] ) ) ) {

				if ( isset( $wp->query_vars['edit-address'] ) ) {
					$address_type = esc_attr( $wp->query_vars['edit-address'] );
				} else {
					$address_type = ( ! isset( $_GET['address'] ) ) ? esc_attr( $_GET['address'] ) : '';
				}

				// translators: $1: address type (Shipping Address / Billing Address), $2: opening <strong> tag, $3: closing </strong> tag
				$label = sprintf( esc_html__( 'Update the %1$s used for %2$sall%3$s future renewals of my active subscriptions', 'woocommerce-subscriptions' ), wcs_get_address_type_to_display( $address_type ), '<strong>', '</strong>' );

				woocommerce_form_field(
					'update_all_subscriptions_addresses',
					array(
						'type'    => 'checkbox',
						'class'   => array( 'form-row-wide' ),
						'label'   => $label,
						'default' => apply_filters( 'wcs_update_all_subscriptions_addresses_checked', false ),
					)
				);
			}

			wp_nonce_field( 'wcs_edit_address', '_wcsnonce' );

		}
	}

	/**
	 * When a subscriber's billing or shipping address is successfully updated, check if the subscriber
	 * has also requested to update the addresses on existing subscriptions and if so, go ahead and update
	 * the addresses on the initial order for each subscription.
	 *
	 * @param int $user_id The ID of a user who own's the subscription (and address)
	 * @since 1.3
	 */
	public static function maybe_update_subscription_addresses( $user_id, $address_type ) {

		if ( ! wcs_user_has_subscription( $user_id ) || wc_notice_count( 'error' ) > 0 || empty( $_POST['_wcsnonce'] ) || ! wp_verify_nonce( $_POST['_wcsnonce'], 'wcs_edit_address' ) ) {
			return;
		}

		$address_type   = ( 'billing' == $address_type || 'shipping' == $address_type ) ? $address_type : '';
		$address_fields = WC()->countries->get_address_fields( esc_attr( $_POST[ $address_type . '_country' ] ), $address_type . '_' );
		$address        = array();

		foreach ( $address_fields as $key => $field ) {
			if ( isset( $_POST[ $key ] ) ) {
				$address[ str_replace( $address_type . '_', '', $key ) ] = wc_clean( $_POST[ $key ] );
			}
		}

		if ( isset( $_POST['update_all_subscriptions_addresses'] ) ) {

			$users_subscriptions = wcs_get_users_subscriptions( $user_id );

			foreach ( $users_subscriptions as $subscription ) {
				if ( $subscription->has_status( array( 'active', 'on-hold' ) ) ) {
					$subscription->set_address( $address, $address_type );
				}
			}
		} elseif ( isset( $_POST['update_subscription_address'] ) ) {

			$subscription = wcs_get_subscription( absint( $_POST['update_subscription_address'] ) );

			if ( $subscription && self::can_user_edit_subscription_address( $subscription->get_id() ) ) {
				// Update the address only if the user actually owns the subscription
				$subscription->set_address( $address, $address_type );

				wp_safe_redirect( $subscription->get_view_order_url() );
				exit();
			}
		}
	}

	/**
	 * Prepopulate the address fields on a subscription item
	 *
	 * @param array $address A WooCommerce address array
	 * @since 1.5
	 */
	public static function maybe_populate_subscription_addresses( $address ) {
		$subscription_id = isset( $_GET['subscription'] ) ? absint( $_GET['subscription'] ) : 0;

		if ( $subscription_id && self::can_user_edit_subscription_address( $subscription_id ) ) {
			$subscription = wcs_get_subscription( $subscription_id );

			foreach ( array_keys( $address ) as $key ) {

				$function_name = 'get_' . $key;

				if ( is_callable( array( $subscription, $function_name ) ) ) {
					$address[ $key ]['value'] = $subscription->$function_name();
				}
			}
		}

		return $address;
	}

	/**
	 * Update the address fields on an order
	 *
	 * @param array $subscription A WooCommerce Subscription array
	 * @param array $address_fields Locale aware address fields of the form returned by WC_Countries->get_address_fields() for a given country
	 * @since 1.3
	 */
	public static function maybe_update_order_address( $subscription, $address_fields ) {
		_deprecated_function( __METHOD__, '2.0', 'WC_Order::set_address() or WC_Subscription::set_address()' );
	}

	/**
	 * Replace the change address breadcrumbs structure to include a link back to the subscription.
	 *
	 * @param  array $crumbs
	 * @return array
	 * @since 2.4.2
	 */
	public static function change_addresses_breadcrumb( $crumbs ) {
		if ( isset( $_GET['subscription'] ) && is_wc_endpoint_url() && 'edit-address' === WC()->query->get_current_endpoint() ) {
			global $wp_query;
			$subscription = wcs_get_subscription( absint( $_GET['subscription'] ) );

			if ( ! $subscription ) {
				return $crumbs;
			}

			$crumbs[1] = array(
				get_the_title( wc_get_page_id( 'myaccount' ) ),
				get_permalink( wc_get_page_id( 'myaccount' ) ),
			);

			$crumbs[2] = array(
				// translators: %s: subscription ID.
				sprintf( _x( 'Subscription #%s', 'hash before order number', 'woocommerce-subscriptions' ), $subscription->get_order_number() ),
				esc_url( $subscription->get_view_order_url() ),
			);

			$crumbs[3] = array(
				// translators: %s: address type (eg. 'billing' or 'shipping').
				sprintf( _x( 'Change %s address', 'change billing or shipping address', 'woocommerce-subscriptions' ), $wp_query->query_vars['edit-address'] ),
				'',
			);
		}

		return $crumbs;
	}

	public static function maybe_add_subscription_to_cart_for_address_change() {
		$subscription_id = absint( $_GET['update_subscription_address'] ?? null ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $subscription_id ) {
			$subscription       = wcs_get_subscription( $subscription_id );
			$subscription_items = $subscription->get_items();

			// Save current cart contents and empty the cart.
			if ( count( WC()->cart->get_cart() ) > 0 ) {
				update_user_meta(
					get_current_user_id(),
					'_wcs_address_change_cart_' . get_current_blog_id(),
					WC()->cart->get_cart_contents()
				);

				WC()->cart->empty_cart();
			}

			// Add all the subscription items to cart temporarily.
			foreach ( $subscription_items as $subscription_item ) {
				WC()->cart->add_to_cart( $subscription_item['product_id'], $subscription_item['qty'] );
			}

			add_filter( 'woocommerce_coupons_enabled', '__return_false' );
			add_filter( 'woocommerce_cart_needs_shipping_address', '__return_false' );
			add_filter( 'gettext', array( __CLASS__, 'fields_for_address_change' ), 20, 3 );
			add_filter( 'the_title', array( __CLASS__, 'title_for_address_change' ), 100 );
			add_filter( 'woocommerce_get_breadcrumb', array( __CLASS__, 'crumbs_for_address_change' ), 10, 1 );

		}
	}

	// Change the 'Billing details' checkout label to 'Shipping Details'
	public static function fields_for_address_change( $translated_text, $text, $domain ) {

		switch ( $translated_text ) {
			case 'Billing details':
				$translated_text = __( 'Shipping details', 'woocommerce-subscriptions' );
				break;
			case 'Your order':
				$translated_text = __( 'Subscription totals', 'woocommerce-subscriptions' );
				break;
		}

		return $translated_text;
	}

	public static function title_for_address_change( $title ) {

		// Skip if not on checkout pay page or not a address change request.
		if ( ! isset( $_GET['update_subscription_address'] ) || ! is_main_query() || ! in_the_loop() || ! is_page() || ! is_checkout() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $title;
		}

		$title = 'Change subscription address';
		return $title;
	}

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

	public static function maybe_add_hidden_input_for_address_change() {
		$subscription_id = absint( wc_clean( wp_unslash( $_GET['update_subscription_address'] ?? null ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $subscription_id ) {
			echo '<input type="hidden" name="update_subscription_address" value="' . esc_attr( $subscription_id ) . '">';
		}
	}

	public static function maybe_modify_checkout_template_for_address_change( $fragments ) {
		// Ignoring the nonce check here as it's already been verified in WC_AJAX::update_order_review().
		$form_data = wp_parse_args( wc_clean( wp_unslash( $_POST['post_data'] ?? null ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( isset( $form_data['update_subscription_address'] ) ) {
			ob_start();

			wc_get_template(
				'checkout/update-address-for-subscription.php',
				array(
					'subscription'      => wcs_get_subscription( absint( $form_data['update_subscription_address'] ) ),
					'order_button_text' => apply_filters( 'wcs_update_address_button_text', __( 'Update Address', 'woocommerce-subscriptions' ) ),
				),
				'',
				WC_Subscriptions_Core_Plugin::instance()->get_subscriptions_core_directory( 'templates/' )
			);

			$new_fragment = ob_get_clean(); // @codingStandardsIgnoreLine
			$fragments['.woocommerce-checkout-payment'] = $new_fragment;
		}

		return $fragments;
	}

	public static function process_address_change() {
		// Ignoring the nonce check here as it's already been verified in WC_Checkout::process_checkout().
		$subscription_id = absint( wc_clean( wp_unslash( $_POST['update_subscription_address'] ?? null ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! $subscription_id ) {
			return;
		}

		$subscription = wcs_get_subscription( $subscription_id );

		// Update shipping address of the subscription.
		$address_type   = 'billing';
		$address_fields = WC()->countries->get_address_fields( wc_clean( wp_unslash( $_POST[ $address_type . '_country' ] ?? '' ) ), $address_type . '_' );// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$address        = array();

		foreach ( $address_fields as $key => $field ) {
			if ( isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$address[ str_replace( $address_type . '_', '', $key ) ] = wc_clean( wp_unslash( $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}
		}

		$subscription->set_address( $address, 'shipping' );

		// Get all the subscription shipping items.
		$subscription_shipping_items = (array) $subscription->get_items( 'shipping' );

		// Remove those shipping items from the subscription.
		if ( count( $subscription_shipping_items ) > 0 ) {
			foreach ( $subscription_shipping_items as $item_id => $item ) {
				$subscription->remove_item( $item_id );
			}
		}

		$cart = WC()->cart;
		WC_Subscriptions_Checkout::add_shipping( $subscription, $cart );
		$subscription->set_shipping_total( $cart->shipping_total );
		$subscription->set_cart_tax( $cart->tax_total );
		$subscription->set_shipping_tax( $cart->shipping_tax_total );
		$subscription->set_total( $cart->total );
		$subscription->save();

		if ( count( WC()->cart->get_cart() ) > 0 ) {
			WC()->cart->empty_cart();
		}

		self::maybe_restore_saved_cart_contents();

		// IF NOT AJAX
		if ( ! is_ajax() ) {
			wp_safe_redirect( $subscription->get_view_order_url() );
			exit();
		}

		// IF AJAX
		wp_send_json(
			array(
				'result'   => 'success',
				'redirect' => $subscription->get_view_order_url(),
			)
		);
	}

	public static function maybe_navigated_away_from_change_address_page() {
		if ( ! is_admin() && ! isset( $_GET['update_subscription_address'] ) && ! is_ajax() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			self::maybe_restore_saved_cart_contents();
		}
	}

	public static function maybe_restore_saved_cart_contents() {
		if ( ! get_current_user_id() ) {
			return;
		}

		$saved_cart_contents = get_user_meta( get_current_user_id(), '_wcs_address_change_cart_' . get_current_blog_id() );

		if ( $saved_cart_contents ) {
			// Empty the cart.
			if ( count( WC()->cart->get_cart() ) > 0 ) {
				WC()->cart->empty_cart();
			}

			// Restore saved cart contents.
			foreach ( $saved_cart_contents[0] as $key => $value ) {
				WC()->cart->add_to_cart( $value['product_id'], $value['quantity'] );
			}

			// Delete saved cart contents.
			delete_user_meta( get_current_user_id(), '_wcs_address_change_cart_' . get_current_blog_id() );
		}
	}
}
