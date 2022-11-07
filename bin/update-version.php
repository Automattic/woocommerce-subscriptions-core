<?php
/*
 * This script bumps version strings across the codebase for the given version.
 * It is executed in the "Create release PR" GitHub workflow.
 */

use Bramus\Monolog\Formatter\ColoredLineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use WR\Tools\Version_Bump;
use WR\Tools\Version_Replace;

$version          = $argv[1];
$plugin_slug      = 'woocommerce-subscriptions-core';
$plugin_folder    = dirname( __DIR__ );
$main_plugin_file = $plugin_folder . '/' . $plugin_slug . '.php';

// Load the Woorelease autoloader.
require_once dirname( __DIR__, 2 ) . '/woorelease/vendor/autoload.php';

// Set up the logger.
$time_format   = 'H:i:s';
$output_format = "WR [%datetime%] [%level_name%] %message%\n";

$logger  = new Logger( 'Woorelease' );
$handler = new StreamHandler( 'php://stdout', Logger::INFO );
$handler->pushProcessor( new PsrLogMessageProcessor() );
$handler->setFormatter( new ColoredLineFormatter( null, $output_format, $time_format ) );
$logger->pushHandler( $handler );

Monolog\Registry::addLogger( $logger, 'default' );

// Bump versions across the codebase.
Version_Bump::maybe_bump(
	$plugin_slug,
	$plugin_folder,
	$main_plugin_file,
	$version,
	true
);

// Replace "@since x.x.x" tags with the new version. Also works with @version.
Version_Replace::maybe_replace(
	$plugin_slug,
	$plugin_folder,
	$version
);

// We need to bump the version in the main plugin class-file as well (includes/class-wc-subscriptions-core.php).
// This can't be done without hacking on WooRelease as it only supports the main plugin file (woocommerce-subscriptions-core.php), so we'll haven't to manually replace the version for \WC_Subscriptions_Core_Plugin::$library_version
$core_plugin_file = $plugin_folder . '/includes/class-wc-subscriptions-core-plugin.php';
update_library_version_constant( $core_plugin_file, $version );



/**
 * Update the library version constant in the given file.
 *
 * @param string $file    The file to update.
 * @param string $version The version to set.
 */
function update_library_version_constant( $file, $version ) {
	$contents = file_get_contents( $file ); //phpcs:ignore

	/*
	* Constant declaration version bump.
	* We're looking for a comment with this
	* format // WRCS: DEFINED_VERSION.
	*/

	// Group the matches into 3 separate values.
	preg_match( '/\/\/\sWRCS:\sDEFINED_VERSION\./', $contents, $matches );

	// Proceed if we have found the version.
	if ( ! $matches ) {
		throw new \Exception( 'Could not find the subscriptions-core library version in the file.' );
	}

	$replacement_string = "'" . $version . "' ); // WRCS: DEFINED_VERSION.";
	$contents           = preg_replace( "/'[0-9]+\.[0-9]+\.[0-9]+-?([a-z]+)?\.?[0-9]*'\s\);\s\/\/\sWRCS:\sDEFINED_VERSION.*/", $replacement_string, $contents, 1 );

	$replacement_string = "= '{$version}'; // WRCS: DEFINED_VERSION.";
	$contents           = preg_replace( "/=\s'[0-9]+\.[0-9]+\.[0-9]+-?([a-z]+)?\.?[0-9]*';\s\/\/\sWRCS:\sDEFINED_VERSION.*/", $replacement_string, $contents, 1 );

	file_put_contents( $file, $contents ); //phpcs:ignore
}
