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
		'wc_subscriptions_switcher',
		'wcs_cart_switch',
		'wcs_switch_totals_calculator',
		'wcs_switch_cart_item',
		'wcs_add_cart_item',
		'wc_order_item_pending_switch',
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

	/**
	 * Get the relative path for the class location.
	 *
	 * @param string $class The class name.
	 * @return string The relative path (from the plugin root) to the class file.
	 */
	protected function get_relative_class_path( $class ) {
		if ( in_array( $class, $this->classes ) ) {
			$path = '/includes';

			switch ( $class ) {
				case stripos( $class, 'switch') !== false:
					$path .= '/switching';
					break;
			}

			return trailingslashit( $path );
		}

		return parent::get_relative_class_path( $class );
	}
}