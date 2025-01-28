<?php
/**
 * Plugin Name: WooCommerce Subscriptions Core
 * Plugin URI: https://github.com/automattic/woocommerce-subscriptions-core
 * Description: Adds core subscriptions functionality to your WooCommerce store.
 * Author: Automattic
 * Author URI: https://woocommerce.com/
 * Requires WP: 5.6
 * Version: 7.9.0
 */

// Tell WooCommerce Blocks that all shipping methods
// do not support local pickup because it originally does not
// support mixing pickup and shipping methods.
function disable_local_pickup_shipping_methods() {
  add_filter('woocommerce_shipping_method_supports', function ( $supports, $feature ) {
    if ( $feature === 'local-pickup' && WC_Subscriptions_Cart::cart_contains_subscription() ) {
      return false;
    }

    return $supports;
  }, 3, 10);
}

add_action( 'woocommerce_blocks_enqueue_checkout_block_scripts_before', 'disable_local_pickup_shipping_methods' );
