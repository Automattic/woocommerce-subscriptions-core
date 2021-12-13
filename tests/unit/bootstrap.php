<?php
/**
 * PHPUnit bootstrap file
 *
 * @package WooCommerce\Subscriptions
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find $_tests_dir/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

if ( PHP_VERSION_ID >= 80000 && file_exists( $_tests_dir . '/includes/phpunit7/MockObject' ) ) {
	// WP Core test library includes patches for PHPUnit 7 to make it compatible with PHP8.
	require_once $_tests_dir . '/includes/phpunit7/MockObject/Builder/NamespaceMatch.php';
	require_once $_tests_dir . '/includes/phpunit7/MockObject/Builder/ParametersMatch.php';
	require_once $_tests_dir . '/includes/phpunit7/MockObject/InvocationMocker.php';
	require_once $_tests_dir . '/includes/phpunit7/MockObject/MockMethod.php';
}


// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';



/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	// Load the WooCommerce plugin so we can use its classes in our WooCommerce Payments plugin.
	require_once WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';

	// Set a default currency to be used for the multi-currency tests because the default
	// is not loaded even though it's set during the tests setup.
	update_option( 'woocommerce_currency', 'USD' );

	$_plugin_dir = dirname( __FILE__ ) . '/../../';

	require $_plugin_dir . '/includes/class-wc-subscriptions-core-plugin.php';
	new WC_Subscriptions_Core_Plugin();
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Need those polyfills to run tests in CI.
require_once dirname( __FILE__ ) . '/../../vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';

// We use outdated PHPUnit version, which emits deprecation errors in PHP 7.4 (deprecated reflection APIs).
if ( defined( 'PHP_VERSION_ID' ) && PHP_VERSION_ID >= 70400 ) {
	error_reporting( error_reporting() ^ E_DEPRECATED ); // phpcs:ignore
}


