<?php
/**
 * WCS_Unit_Tests_Bootstrap
 *
 * @since 2.0
 */
class WCS_Unit_Tests_Bootstrap {

	/** @var \WCS_Unit_Tests_Bootstrap instance */
	protected static $instance = null;

	/** @var string directory where wordpress-tests-lib is installed */
	public $wp_tests_dir;

	/** @var string testing directory */
	public $tests_dir;

	/** @var string plugin directory */
	public $plugin_dir;

	// directory storing dependency plugins
	public $modules_dir;

	/**
	 * Setup the unit testing environment
	 *
	 * @since 2.0
	 */
	function __construct() {

		ini_set( 'display_errors','on' );
		error_reporting( E_ALL );

		$this->tests_dir    = dirname( __FILE__ );
		$this->plugin_dir   = dirname( $this->tests_dir );
		$this->modules_dir  = dirname( $this->plugin_dir );
		$this->wp_tests_dir = getenv( 'WP_TESTS_DIR' ) ? getenv( 'WP_TESTS_DIR' ) : $this->plugin_dir . '/tmp/wordpress-tests-lib';

		$_SERVER['REMOTE_ADDR'] = ( isset( $_SERVER['REMOTE_ADDR'] ) ) ? $_SERVER['REMOTE_ADDR'] : '';
		$_SERVER['SERVER_NAME'] = ( isset( $_SERVER['SERVER_NAME'] ) ) ? $_SERVER['SERVER_NAME'] : 'wcs_test';

		// load test function so tests_add_filter() is available
		require_once( $this->wp_tests_dir  . '/includes/functions.php' );

		// pre-register the WCS Action Scheduler
		tests_add_filter( 'muplugins_loaded', array( $this, 'register_wcs_action_scheduler' ) );

		// load WC
		tests_add_filter( 'muplugins_loaded', array( $this, 'load_wc' ) );

		// load Subscriptions after $this->load_wc() finishes and calls 'woocommerce_init'
		tests_add_filter( 'woocommerce_init', array( $this, 'load_wcs' ) );

		// install WC
		tests_add_filter( 'setup_theme', array( $this, 'install_wc' ) );

		// install WCS
		tests_add_filter( 'setup_theme', array( $this, 'install_wcs' ) );

		// load retry system for testing
		tests_add_filter( 'wcs_is_retry_enabled', '__return_true' );

		// don't connect Action Scheduler
		tests_add_filter( 'woocommerce_subscriptions_scheduler', array( $this, 'get_mock_scheduler' ) );

		// manually add coupon hooks.
		tests_add_filter( 'init', array( 'WC_Subscriptions_Coupon', 'maybe_add_recurring_coupon_hooks' ) );

		// load the WP testing environment
		require_once( $this->wp_tests_dir . '/includes/bootstrap.php' );

		// load testing framework
		$this->includes();
	}

	/**
	 * Load WooCommerce
	 *
	 * @since 2.0
	 */
	public function load_wc() {
		require_once( $this->modules_dir . '/woocommerce/woocommerce.php' );
	}

	/**
	 * Register the WCS Action Scheduler
	 *
	 * @since 2.4.3
	 */
	public function register_wcs_action_scheduler() {
		require_once( $this->plugin_dir . '/includes/libraries/action-scheduler/action-scheduler.php' );
	}

	/**
	 * Load Subscriptions
	 *
	 * @since  2.0
	 */
	public function load_wcs() {

		require_once( $this->plugin_dir . '/includes/abstracts/abstract-wcs-scheduler.php' );
		require_once( 'framework/class-wcs-mock-scheduler.php' );

		require_once( $this->plugin_dir . '/woocommerce-subscriptions.php' );
	}

	/**
	 * Load WooCommerce for testing
	 *
	 * @since 2.0
	 */
	public function install_wc() {

		echo "Installing WooCommerce..." . PHP_EOL;

		define( 'WP_UNINSTALL_PLUGIN', true );

		include( $this->modules_dir . '/woocommerce/uninstall.php' );

		WC_Install::install();

		// reload capabilities after install, see https://core.trac.wordpress.org/ticket/28374
		if ( version_compare( $GLOBALS['wp_version'], '4.9', '>=' ) && method_exists( $GLOBALS['wp_roles'], 'for_site' ) ) {
			/** @see: https://core.trac.wordpress.org/ticket/38645 */
			$GLOBALS['wp_roles']->for_site();
		} elseif ( version_compare( $GLOBALS['wp_version'], '4.7', '>=' ) ) {
			// Do the right thing based on https://core.trac.wordpress.org/ticket/23016
			$GLOBALS['wp_roles'] = new WP_Roles();
		} else {
			// Fall back to the old method.
			$GLOBALS['wp_roles']->reinit();
		}

		WC()->init();
		WC()->payment_gateways();

		echo "WooCommerce Finished Installing..." . PHP_EOL;
	}

	/**
	 * Set default values on subscriptions
	 *
	 * @since  2.0
	 */
	public function install_wcs() {

		echo "Installing Subscriptions..." . PHP_EOL;

		WC_Subscriptions::maybe_activate_woocommerce_subscriptions();

		WC_Subscriptions::load_dependant_classes();

		echo "Subscriptions Finished Installing..." . PHP_EOL;
	}

	/**
	 * Load test cases and factories
	 *
	 * @since 2.0
	 */
	public function includes() {
		$wc_tests_framework_base_dir = $this->modules_dir . '/woocommerce/tests';

		if ( ! is_dir( $wc_tests_framework_base_dir . '/framework' ) ) {
			$wc_tests_framework_base_dir .= '/legacy';
		}

		// Kick off the autoloader.
		require_once $this->plugin_dir . '/includes/class-wcs-autoloader.php';
		$autoloader = new WCS_Autoloader( $this->plugin_dir );
		$autoloader->register();

		// Load WC Helper functions/Frameworks and Factories
		if ( version_compare( WC_VERSION, '3.3.0', '<' ) ) {
			require_once( $wc_tests_framework_base_dir . '/framework/factories/class-wc-unit-test-factory-for-webhook.php' );
			require_once( $wc_tests_framework_base_dir . '/framework/factories/class-wc-unit-test-factory-for-webhook-delivery.php' );
		}

		if ( version_compare( WC_VERSION, '3.4.5', '>=' ) ) {
			require_once( $wc_tests_framework_base_dir . '/includes/wp-http-testcase.php' );
		}
		// Load WC Framework
		require_once( $wc_tests_framework_base_dir . '/framework/class-wc-unit-test-factory.php' );
		require_once( $wc_tests_framework_base_dir . '/framework/class-wc-mock-session-handler.php' );
		require_once( $wc_tests_framework_base_dir . '/framework/class-wc-unit-test-case.php' );
		require_once( $wc_tests_framework_base_dir . '/framework/class-wc-api-unit-test-case.php' );

		// LOAD WC-API Files
		if ( version_compare( WC_VERSION, '2.6', '<' ) ) {
			require_once( $this->modules_dir . '/woocommerce/includes/api/class-wc-api-server.php' );
			require_once( $this->modules_dir . '/woocommerce/includes/api/class-wc-api-resource.php' );
			require_once( $this->modules_dir . '/woocommerce/includes/api/class-wc-api-orders.php' );
		}

		// Load WCS Frameworks
		require_once( 'framework/class-wcs-unit-test-case.php' );
		require_once( 'framework/class-wcs-unit-test-factory.php' );
		require_once( 'framework/class-wcs-api-unit-test-case.php' );
		require_once( 'framework/class-wcs-customer-test-store.php' );
		require_once( 'framework/class-wcs-customer-store-test-base.php' );
		require_once( 'framework/class-wcs-related-order-test-store.php' );
		require_once( 'framework/class-wcs-related-order-store-test-base.php' );
		require_once( 'framework/class-wcs-email-listener.php' );

		// Load WC Helper Functions
		require_once( $wc_tests_framework_base_dir . '/framework/helpers/class-wc-helper-product.php' );
		require_once( $wc_tests_framework_base_dir . '/framework/helpers/class-wc-helper-coupon.php' );
		require_once( $wc_tests_framework_base_dir . '/framework/helpers/class-wc-helper-fee.php' );
		require_once( $wc_tests_framework_base_dir . '/framework/helpers/class-wc-helper-shipping.php' );
		require_once( $wc_tests_framework_base_dir . '/framework/helpers/class-wc-helper-customer.php' );
		require_once( $wc_tests_framework_base_dir . '/framework/helpers/class-wc-helper-order.php' );

		// Load WCS Helper Functions
		require_once( 'framework/helpers/class-wcs-helper-subscription.php' );
		require_once( 'framework/helpers/class-wcs-helper-product.php' );
		require_once( 'framework/helpers/class-wcs-helper-upgrade-repair.php' );
		require_once( 'framework/helpers/class-wcs-helper-coupon.php' );

		// Kill this autoloader instance.
		unset( $autoloader );
	}

	/**
	 * Get the single class instance
	 *
	 * @since 2.0
	 * @return WCS_Unit_Tests_Bootstrap
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Override the default scheduler to prevents events being scheduled in Action Scheduler (unless that's what we're testing)
	 *
	 * @since 2.1.1
	 * @return string
	 */
	public function get_mock_scheduler() {
		return 'WCS_Mock_Scheduler';
	}

}

WCS_Unit_Tests_Bootstrap::instance();

/**
 * Override woothemes_queue_update() and is_active_woocommerce() so that the woocommerce_subscriptions.php
 * will import most of the necessary files without exiting early.
 *
 * @since 2.0
 */
function is_woocommerce_active() {
	return true;
}

function woothemes_queue_update($file, $file_id, $product_id) {
	return true;
}
