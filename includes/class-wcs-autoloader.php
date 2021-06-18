<?php
/**
 * WooCommerce Subscriptions Autoloader.
 *
 * @package WC_Subscriptions
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// load the base autoloader class - we can't rely on base plugin autoloader to load it as this class is loaded before the base plugin class
require_once dirname( __FILE__ ) . '/libraries/subscriptions-base/includes/class-wcs-base-autoloader.php';
class WCS_Autoloader extends WCS_Base_Autoloader {

	/**
	 * The classes the Subscritions plugin has ownership of.
	 *
	 * Note: needs to be lowercase.
	 *
	 * @var array
	 */
	private $classes = array(
		'wc_subscriptions_plugin',
	 );

	/**
	 * Gets the class's base path.
	 *
	 * If the a class is one the plugin is responsible for, we return the plugin's path. Otherwise we let the library handle it.
	 *
	 * @since 4.0.0
	 * @return string
	 */
	public function get_class_base_path( $class ) {
		if ( in_array( $class, $this->classes ) ) {
			return dirname( WC_Subscriptions::$plugin_file );
		}

		return parent::get_class_base_path( $class );
	}
}