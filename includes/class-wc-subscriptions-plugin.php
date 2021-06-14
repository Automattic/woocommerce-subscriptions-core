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

	/**
	 * Initialise the plugin.
	 */
	public function init() {
		WC_Subscriptions_Coupon::init();
		WC_Subscriptions_Product::init();
		WC_Subscriptions_Admin::init();
		WC_Subscriptions_Manager::init();
		WC_Subscriptions_Cart::init();
		WC_Subscriptions_Cart_Validator::init();
		WC_Subscriptions_Order::init();
		WC_Subscriptions_Renewal_Order::init();
		WC_Subscriptions_Checkout::init();
		WC_Subscriptions_Email::init();
		WC_Subscriptions_Addresses::init();
		WC_Subscriptions_Change_Payment_Gateway::init();
		WC_Subscriptions_Payment_Gateways::init();
		WCS_PayPal_Standard_Change_Payment_Method::init();
		WC_Subscriptions_Switcher::init();
		WC_Subscriptions_Tracker::init();
		WCS_Upgrade_Logger::init();
		new WCS_Cart_Renewal();
		new WCS_Cart_Resubscribe();
		new WCS_Cart_Initial_Payment();
		WCS_Download_Handler::init();
		WCS_Retry_Manager::init();
		new WCS_Cart_Switch();
		WCS_Limiter::init();
		WCS_Admin_System_Status::init();
		WCS_Upgrade_Notice_Manager::init();
		WCS_Staging::init();
		WCS_Permalink_Manager::init();
		WCS_Custom_Order_Item_Manager::init();
		WCS_Early_Renewal_Modal_Handler::init();
		WCS_Dependent_Hook_Manager::init();
		WCS_Admin_Product_Import_Export_Manager::init();
		WC_Subscriptions_Frontend_Scripts::init();
	}
}
