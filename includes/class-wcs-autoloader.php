<?php
/**
 * WooCommerce Subscriptions Autoloader.
 *
 * @package WC_Subscriptions
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// load the base autoloader class - we can't rely on base plugin autoloader to load it as this class is loaded before the base plugin class
require_once dirname( __FILE__ ) . '/libraries/subscriptions-base/includes/class-wcs-base-autoloader.php';
class WCS_Autoloader extends WCS_Base_Autoloader {

	/**
	 * The classes the Subscritions plugin has ownership of.
	 *
	 * Note: needs to be lowercase.
	 *
	 * @var array
	 */
	private $classes = array(
		'wc_subscriptions_plugin'                   => true,
		'wc_subscriptions_switcher'                 => true,
		'wcs_cart_switch'                           => true,
		'wcs_switch_totals_calculator'              => true,
		'wcs_switch_cart_item'                      => true,
		'wcs_add_cart_item'                         => true,
		'wc_order_item_pending_switch'              => true,
		'wcs_manual_renewal_manager'                => true,
		'wcs_customer_suspension_manager'           => true,
		'wcs_drip_downloads_manager'                => true,
		'wcs_zero_initial_payment_checkout_manager' => true,
		'wcs_meta_box_payment_retries'              => true
	);

	/**
	 * The substrings of the classes that the Subscriptions plugin has ownership of.
	 *
	 * @var array
	 */
	private $class_substrings = array(
		'wc_reports',
		'report',
		'retry',
	);

	/**
	 * Gets the class's base path.
	 *
	 * If the a class is one the plugin is responsible for, we return the plugin's path. Otherwise we let the library handle it.
	 *
	 * @since 4.0.0
	 * @return string
	 */
	public function get_class_base_path( $class ) {
		if ( $this->is_plugin_class( $class ) ) {
			return dirname( WC_Subscriptions::$plugin_file );
		}

		return parent::get_class_base_path( $class );
	}

	/**
	 * Get the relative path for the class location.
	 *
	 * @param string $class The class name.
	 * @return string The relative path (from the plugin root) to the class file.
	 */
	protected function get_relative_class_path( $class ) {
		if ( ! $this->is_plugin_class( $class ) ) {
			return parent::get_relative_class_path( $class );
		}

		$path = '/includes';

		if ( stripos( $class, 'switch') !== false || 'wcs_add_cart_item' === $class ) {
			$path .= '/switching';
		} elseif ( false !== strpos( $class, 'retry' ) || false !== strpos( $class, 'retries' ) ) {
			$path .= $this->get_retry_relative_path( $class );
		} elseif ( false !== strpos( $class, 'admin' ) ) {
			$path .= '/admin';
		} elseif ( false !== strpos( $class, 'wc_report' ) ) {
			$path .= '/admin/reports/deprecated';
		} elseif ( false !== strpos( $class, 'report' ) ) {
			$path .= '/admin/reports';
		}

		return trailingslashit( $path );
	}

	/**
	 * Determine whether we should autoload a given class.
	 *
	 * @param string $class The class name.
	 * @return bool
	 */
	protected function should_autoload( $class ) {
		static $legacy = array(
			'wc_order_item_pending_switch'         => 1,
			'wc_report_retention_rate'             => 1,
			'wc_report_upcoming_recurring_revenue' => 1,
		);

		return isset( $legacy[ $class ] ) ? true : parent::should_autoload( $class );

	}

	/**
	 * Is the given class found in the Subscriptions plugin
	 *
	 * @since 4.0.0
	 * @param string $class
	 * @return bool
	 */
	private function is_plugin_class( $class ) {
		if ( isset( $this->classes[ $class ] ) ) {
			return true;
		}

		foreach ( $this->class_substrings as $substring ) {
			if ( false !== stripos( $class, $substring ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Gets a retry class's relative path.
	 *
	 * @param string $class The retry class being loaded.
	 * @return string The relative path to the retry class.
	 */
	private function get_retry_relative_path( $class ) {
		$relative_path = '/payment-retry';

		if ( false !== strpos( $class, 'admin' ) || false !== strpos( $class, 'meta_box' )) {
			$relative_path .= '/admin';
		} elseif ( false !== strpos( $class, 'email' ) ) {
			$relative_path .= '/emails';
		} elseif ( false !== strpos( $class, 'store' ) ) {
			$relative_path .= '/data-stores';
		}

		return $relative_path;
	}

	/**
	 * Determine if the class is one of our abstract classes.
	 *
	 * @param string $class The class name.
	 * @return bool
	 */
	protected function is_class_abstract( $class ) {
		static $abstracts = array(
			'wcs_retry_store' => true,
		);

		return isset( $abstracts[ $class ] ) || parent::is_class_abstract( $class );
	}
}
