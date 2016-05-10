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
		add_action( 'template_redirect', __CLASS__ . '::maybe_setup_cart' , 99 );
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
				WC()->cart->empty_cart( true );

				$switch_order_data = get_post_meta( $order_id, '_subscription_switch_data', true );

				foreach ( $order->get_items() as $item_id => $line_item ) {

					unset( $_GET['switch-subscription'] );
					unset( $_GET['item'] );

					foreach ( $switch_order_data as $subscription_id => $switch_data ) {
						// check if this order item is for a switch
						if ( isset( $switch_data['add_order_items'] ) && in_array( $item_id, array_keys( $switch_data['add_order_items'] ) ) ) {
							$_GET['switch-subscription'] = $subscription_id;
							$_GET['item']                = $switch_data['add_order_items'][ $item_id ]['meta']['_switched_subscription_item_id'][0];
							break;
						}
					}

					$order_item = wcs_get_order_item( $item_id, $order );
					$product    = WC_Subscriptions::get_product( wcs_get_canonical_product_id( $order_item ) );

					$order_product_data = array(
						'_qty'          => 0,
						'_variation_id' => '',
					);

					$variations = array();

					foreach ( $order_item['item_meta'] as $meta_key => $meta_value ) {

						if ( taxonomy_is_product_attribute( $meta_key ) || meta_is_product_attribute( $meta_key, $meta_value[0], $product->id ) ) {
							$variations[ $meta_key ] = $meta_value[0];
							$_POST[ 'attribute_' . $meta_key ] = $meta_value[0];
						} else if ( array_key_exists( $meta_key, $order_product_data ) ) {
							$order_product_data[ $meta_key ] = (int) $meta_value[0];
						}
					}

					$passed_validation = apply_filters( 'woocommerce_add_to_cart_validation', true, $product->id, $order_product_data['_qty'], $order_product_data['_variation_id'] );

					if ( $passed_validation ) {
						$cart_item_key = WC()->cart->add_to_cart( $product->id, $order_product_data['_qty'], $order_product_data['_variation_id'], $variations, array() );
					}
				}
			}

			WC()->session->set( 'order_awaiting_payment', $order_id );
			wp_safe_redirect( WC()->cart->get_checkout_url() );
			exit;
		}
	}
}
WCS_Switching_Cart::init();
