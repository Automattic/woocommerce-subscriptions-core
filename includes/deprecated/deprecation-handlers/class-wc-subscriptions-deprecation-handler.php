<?php
/**
 * A class for handling the WC_Subscriptions class's deprecated functions.
 *
 * @package  WooCommerce Subscriptions
 * @category Class
 * @author   WooCommerce
 * @since    4.0.0
 */

defined( 'ABSPATH' ) || exit;

class WC_Subscriptions_Deprecation_Handler extends WCS_Deprecated_Functions_Handler {

	/**
	 * This class handles WC_Subscriptions.
	 *
	 * @var string
	 */
	protected $class = 'WC_Subscriptions';

	/**
	 * Deprecated WC_Subscriptions functions.
	 *
	 * @var array[]
	 */
	protected $deprecated_functions = array();

}
