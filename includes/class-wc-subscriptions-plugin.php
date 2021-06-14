<?php
/**
 * WooCommerce Subscriptions setup
 *
 * @package WooCommerce Subscriptions
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;
class WC_Subscriptions_Plugin {

	/**
	 * The plugin's base file directory.
	 *
	 * @var string
	 */
	protected $file = '';

	/**
	 * Initialise class and attach callbacks.
	 */
	public function __construct( $file ) {
		$this->file = $file;
		$this->define_constants();
		$this->includes();
	}

	/**
	 * Defines WC Subscriptions contants.
	 */
	protected function define_constants() {
		define( 'WCS_INIT_TIMESTAMP', gmdate( 'U' ) );
	}

	/**
	 * Includes required files.
	 */
	protected function includes() {
		// Load function files.
		require_once dirname( $this->file ) . '/wcs-functions.php';
		require_once dirname( $this->file ) . '/includes/gateways/paypal/includes/wcs-paypal-functions.php';

		// Load libraries.
		require_once dirname( $this->file ) . '/includes/libraries/action-scheduler/action-scheduler.php';
	}
}
