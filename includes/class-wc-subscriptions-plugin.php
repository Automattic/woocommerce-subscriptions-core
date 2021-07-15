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
		WCS_Zero_Initial_Payment_Checkout_Manager::init();
		WCS_Retry_Manager::init();
	}

	/**
	 * Initialises classes which need to be loaded after other plugins have loaded.
	 *
	 * Hooked onto 'plugins_loaded' by @see WC_Subscriptions_Base_Plugin::init()
	 *
	 * @since 4.0.0
	 */
	public function init_version_dependant_classes() {
		parent::init_version_dependant_classes();
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

	/**
	 * Gets the activation transient name.
	 *
	 * @since 4.0.0
	 * @return string The transient name used to record when the plugin was activated.
	*/
	public function get_activation_transient() {
		return WC_Subscriptions::$activation_transient;
	}

	/**
	 * Gets the product type name.
	 *
	 * @since 4.0.0
	 * @return string The product type name.
	 */
	public function get_product_type_name() {
		return WC_Subscriptions::$name;
	}

	/**
	 * Gets the plugin's version
	 *
	 * @since 4.0.0
	 * @return string The plugin version.
	 */
	public function get_plugin_version() {
		return WC_Subscriptions::$version;
	}

	/**
	 * Gets the plugin file name
	 *
	 * @since 4.0.0
	 * @return string The plugin file
	 */
	public function get_plugin_file() {
		return WC_Subscriptions::$plugin_file;
	}
}
