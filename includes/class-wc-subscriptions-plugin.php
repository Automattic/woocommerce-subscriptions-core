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
	}
}
