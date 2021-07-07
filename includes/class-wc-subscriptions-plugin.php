<?php
/**
 * WooCommerce Subscriptions setup
 *
 * @package WooCommerce Subscriptions
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;

class WC_Subscriptions_Plugin extends WC_Subscriptions_Base_Plugin {

	/**
	 * Initialise the WC Subscriptions plugin.
	 *
	 * @since 4.0.0
	 */
	public function init() {
		parent::init();
		WC_Subscriptions_Switcher::init();
		new WCS_Cart_Switch();
		WCS_Manual_Renewal_Manager::init();
		WCS_Customer_Suspension_Manager::init();
		WCS_Drip_Downloads_Manager::init();
		new WCS_Admin_Reports();
		new WCS_Report_Cache_Manager();
	}

	/**
	 * Gets the plugin's directory url.
	 *
	 * @since 4.0.0
	 * @param string $path Optional. The path to append.
	 * @return string
	 */
	public function get_plugin_directory_url( $path = '' ) {
		return plugin_dir_url( WC_Subscriptions::$plugin_file ) . $path;
	}

	/**
	 * Gets the plugin's directory.
	 *
	 * @since 4.0.0
	 * @param string $path Optional. The path to append.
	 * @return string
	 */
	public function get_plugin_directory( $path = '' ) {
		return plugin_dir_path( WC_Subscriptions::$plugin_file ) . $path;
	}
}
