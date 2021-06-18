<?php

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// load the base autoloader class - we can't rely on base plugin autoloader to load it as this class is loaded before the base plugin class
require_once dirname( __FILE__ ) . '/libraries/subscriptions-base/includes/class-wcs-base-autoloader.php';

/**
 * WooCommerce Subscriptions Autoloader.
 *
 * @class WCS_Autoloader
 */
class WCS_Autoloader extends WCS_Base_Autoloader {

	public $classes = array(
		'WC_Subscriptions_Plugin',
	);

	/**
	 * 
	 */
	public function autoload( $class ) {
		if ( ! in_array( $class, $this->classes ) ) {
			$this->base_path = dirname( __FILE__ ) . '/libraries/subscriptions-base';
		}

		parent::autoload( $class );

		$this->base_path = dirname( WC_Subscriptions::$plugin_file );
	}
}