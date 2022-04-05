<?php
/**
 * Plugin Name: Subscriptions Core Plugin Runner
 * Plugin URI: https://github.com/automattic/woocommerce-subscriptions-core
 * Description: Inits Subscriptions Core without needing WC Subscriptions or WC Payments - for testing purposes.
 * Author: Automattic
 * Author URI: https://woocommerce.com/
 * Requires WP: 5.6
 * Version: 1.0.0
 */
require_once WP_PLUGIN_DIR . '/woocommerce-subscriptions-core/includes/class-wc-subscriptions-core-plugin.php';
new WC_Subscriptions_Core_Plugin();
