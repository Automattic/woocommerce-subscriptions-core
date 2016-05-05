<?php
/**
 * Subscriptions switching cart
 *
 *
 * @author   Prospress
 * @since    2.0
 */
class WCS_Switching_Cart {

	/**
	 * Initialise class hooks & filters when the file is loaded
	 *
	 * @since 2.1
	 */
	public static function init() {

		// Set URL parameter for manual subscription renewals
		add_filter( 'woocommerce_get_checkout_payment_url', __CLASS__ . '::get_checkout_payment_url', 10, 2 );

		// Check if a user is requesting to create a renewal order for a subscription, needs to happen after $wp->query_vars are set
		add_action( 'template_redirect', __CLASS__ . '::maybe_setup_cart' , 100 );
	}

	public static function get_checkout_payment_url( $pay_url, $order ) {

		if ( wcs_order_contains_switch( $order ) ) {
			$pay_url = add_query_arg( array(
				'subscription_switch' => 'true',
				'_wcsnonce' => wp_create_nonce( 'wcs_switch_request' ),
			 ), $pay_url );
		}

		return $pay_url;
	}

	public static function maybe_setup_cart() {

		global $wp;

		if ( isset( $_GET['pay_for_order'] ) && isset( $_GET['key'] ) && isset( $wp->query_vars['order-pay'] ) ) {

			// Pay for existing order
			$order_key = $_GET['key'];
			$order_id  = ( isset( $wp->query_vars['order-pay'] ) ) ? $wp->query_vars['order-pay'] : absint( $_GET['order_id'] );
			$order     = wc_get_order( $wp->query_vars['order-pay'] );

			if ( $order->order_key == $order_key && $order->has_status( array( 'pending', 'failed' ) ) && wcs_order_contains_switch( $order ) ) {

				$switch_order_data = get_post_meta( $order_id, '_subscription_switch_data', true );

				foreach ( $switch_order_data  as $subscription_id => $switch_data ) {
					$_GET['switch-subscription'] = $subscription_id;

					foreach ( $switch_data['add_order_items'] as $order_item_id => $order_item_data ) {

						$order_item = wcs_get_order_item( $order_item_id, $order );
						$product    = WC_Subscriptions::get_product( wcs_get_canonical_product_id( $order_item ) );

						$switch_product_data = array(
							'_qty'          => 0,
							'_variation_id' => '',
							'_switched_subscription_item_id' => 0,
						 );

						$variations = array();

						foreach ( $order_item_data['meta'] as $meta_key => $meta_value ) {

							if ( taxonomy_is_product_attribute( $meta_key ) || meta_is_product_attribute( $meta_key, $meta_value[0], $product->id ) ) {
								$variations[ $meta_key ] = $meta_value[0];
								$_POST['attribute_' . $meta_key ] = $meta_value[0];
							} else if ( array_key_exists( $meta_key, $switch_product_data ) ) {
								$switch_product_data[ $meta_key ] = (int)$meta_value[0];
							}
						}

						$_GET['item'] = $switch_product_data['_switched_subscription_item_id'];

						$passed_validation = apply_filters( 'woocommerce_add_to_cart_validation', true, $product->id, $switch_product_data['_qty'], $switch_product_data['_variation_id'] );

						if ( $passed_validation ) {
							$cart_item_key = WC()->cart->add_to_cart( $product->id, $switch_product_data['_qty'], $switch_product_data['_variation_id'], $variations, array() );
						}
					}
				}
			}
		}
	}
}
WCS_Switching_Cart::init();
