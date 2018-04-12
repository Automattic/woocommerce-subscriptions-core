<?php
/**
 * Methods for adding Subscriptions Debug Tools
 *
 * Add tools for debugging and managing Subscriptions to the
 * WooCommerce > System Status > Tools administration screen.
 *
 * @author   Prospress
 * @category Admin
 * @package  WooCommerce Subscriptions/Admin
 * @version  2.3
 * @since    2.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * WCS_Debug_Tool_Factory Class
 *
 * Add debug tools to the WooCommerce > System Status > Tools page.
 */
final class WCS_Debug_Tool_Factory {

	/**
	 * Register cache eraser and generator tools for a specific cache type.
	 *
	 * @param mixed $data_store An instance of the data store which this tool relates. Passed to the constructor on the tool.
	 * @param stirng $cache_name A string representing the cache name, used for loading the debug tool file and instantiating the class. Use lowercase and dashes for spaces, e.g. a Related Order cache would use 'related-order' value.
	 * @param array $cache_tool_types The type of cache tools. If empty, an 'eraser' and 'generator' tool will be created.
	 */
	public static function cache_management_tools( $data_store, $cache_name, $cache_tool_types = array() ) {

		if ( ! is_admin() && ! defined( 'DOING_CRON' ) && ! defined( 'WP_CLI' ) ) {
			return;
		}

		if ( empty( $cache_tool_types ) ) {
			$cache_tool_types = array(
				'eraser',
				'generator',
			);
		}

		foreach ( $cache_tool_types as $cache_tool_type ) {
			$file_path = sprintf( 'includes/admin/debug-tools/class-wcs-debug-tool-%s-cache-%s.php', $cache_name, $cache_tool_type );
			$file_path = plugin_dir_path( WC_Subscriptions::$plugin_file ) . $file_path;

			if ( ! file_exists( $file_path ) ) {
				throw new InvalidArgumentException( sprintf( '%s() requires a cache name linked to a valid debug tool. File does not exist: %s', __METHOD__, $rel_file_path ) );
			}

			require_once( $file_path );

			$tool_class_name = sprintf( 'WCS_Debug_Tool_%s_Cache_%s', str_replace( '-', '_', ucwords( $cache_name, '-' ) ), ucwords( $cache_tool_type ) );

			if ( ! class_exists( $tool_class_name ) ) {
				throw new InvalidArgumentException( sprintf( '%s() requires a path to load %s. Class does not exist after loading %s.', __METHOD__, $class_name, $file_path ) );
			}

			$tool = new $tool_class_name( $data_store );
			$tool->init();
		}
	}
}
