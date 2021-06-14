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
	 * The subscription scheduler instance.
	 *
	 * @var WCS_Action_Scheduler
	 */
	protected $scheduler = null;

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

		add_action( 'init', array( 'WC_Subscriptions_Synchroniser', 'init' ) );
		add_action( 'after_setup_theme', array( 'WC_Subscriptions_Upgrader', 'init' ), 11 );
		add_action( 'init', array( 'WC_PayPal_Standard_Subscriptions', 'init' ), 11 );
		add_action( 'init', array( 'WCS_WC_Admin_Manager', 'init' ), 11 );

		// Attach the callback to load version dependant classes.
		add_action( 'plugins_loaded', array( $this, 'init_version_dependant_classes' ) );

		// Initialised the related order and customter data store instances.
		add_action( 'plugins_loaded', 'WCS_Related_Order_Store::instance' );
		add_action( 'plugins_loaded', 'WCS_Customer_Store::instance' );

		// Initialise the scheduler.
		$scheduler_class = apply_filters( 'woocommerce_subscriptions_scheduler', 'WCS_Action_Scheduler' );
		$this->scheduler = new $scheduler_class();
	}

	/**
	 * Initialises classes which need to be loaded after other plugins have loaded.
	 *
	 * Hooked onto 'plugins_loaded' by @see WC_Subscriptions_Plugin::init()
	 */
	public function init_version_dependant_classes() {
		new WCS_Admin_Post_Types();
		new WCS_Admin_Meta_Boxes();
		new WCS_Admin_Reports();
		new WCS_Report_Cache_Manager();
		WCS_Webhooks::init();
		new WCS_Auth();
		WCS_API::init();
		WCS_Template_Loader::init();
		WCS_Remove_Item::init();
		WCS_User_Change_Status_Handler::init();
		WCS_My_Account_Payment_Methods::init();
		WCS_My_Account_Auto_Renew_Toggle::init();
		new WCS_Deprecated_Filter_Hooks();

		// On some loads the WC_Query doesn't exist. To avoid a fatal, only load the WCS_Query class when it exists.
		if ( class_exists( 'WC_Query' ) ) {
			new WCS_Query();
		}

		$failed_scheduled_action_manager = new WCS_Failed_Scheduled_Action_Manager( new WC_Logger() );
		$failed_scheduled_action_manager->init();

		/**
		 * Allow third-party code to enable running v2.0 hook deprecation handling for stores that might want to check for deprecated code.
		 *
		 * @param bool Whether the hook deprecation handlers should be loaded. False by default.
		 */
		if ( apply_filters( 'woocommerce_subscriptions_load_deprecation_handlers', false ) ) {
			new WCS_Action_Deprecator();
			new WCS_Filter_Deprecator();
			new WCS_Dynamic_Action_Deprecator();
			new WCS_Dynamic_Filter_Deprecator();
		}

		// Only load privacy handling on WC applicable versions.
		if ( class_exists( 'WC_Abstract_Privacy' ) ) {
			new WCS_Privacy();
		}

		if ( class_exists( 'WCS_Early_Renewal' ) ) {
			$notice = new WCS_Admin_Notice( 'error' );

			// translators: 1-2: opening/closing <b> tags, 3: Subscriptions version.
			$notice->set_simple_content( sprintf( __( '%1$sWarning!%2$s We can see the %1$sWooCommerce Subscriptions Early Renewal%2$s plugin is active. Version %3$s of %1$sWooCommerce Subscriptions%2$s comes with that plugin\'s functionality packaged into the core plugin. Please deactivate WooCommerce Subscriptions Early Renewal to avoid any conflicts.', 'woocommerce-subscriptions' ), '<b>', '</b>', self::$version ) );
			$notice->set_actions(
				array(
					array(
						'name' => __( 'Installed Plugins', 'woocommerce-subscriptions' ),
						'url'  => admin_url( 'plugins.php' ),
					),
				)
			);

			$notice->display();
		} else {
			WCS_Early_Renewal_Manager::init();

			require_once dirname( $this->file ) . '/includes/early-renewal/wcs-early-renewal-functions.php';

			if ( WCS_Early_Renewal_Manager::is_early_renewal_enabled() ) {
				new WCS_Cart_Early_Renewal();
			}
		}
	}
}
