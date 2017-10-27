<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

/**
 * WooCommerce Subscriptions Autoloader.
 *
 * @class WCS_Autoloader
 */
class WCS_Autoloader {

	/**
	 * The base path for autoloading.
	 *
	 * @var string
	 */
	protected $base_path = '';

	/**
	 * WCS_Autoloader constructor.
	 *
	 * @param string $base_path
	 */
	public function __construct( $base_path ) {
		$this->base_path = untrailingslashit( $base_path );
	}

	/**
	 * Register the autoloader.
	 *
	 * @author Jeremy Pry
	 */
	public function register() {
		spl_autoload_register( array( $this, 'autoload' ) );
	}

	/**
	 * Autoload a class.
	 *
	 * @author Jeremy Pry
	 *
	 * @param string $class The class name to autoload.
	 */
	public function autoload( $class ) {
		$class = strtolower( $class );

		if ( ! $this->should_autoload( $class ) ) {
			return;
		}

		$full_path = $this->base_path . $this->get_relative_class_path( $class ) . $this->get_file_name( $class );
		if ( is_readable( $full_path ) ) {
			require_once( $full_path );
		}
	}

	/**
	 * Determine whether we should autoload a given class.
	 *
	 * @author Jeremy Pry
	 *
	 * @param string $class The class name.
	 *
	 * @return bool
	 */
	protected function should_autoload( $class ) {
		// We're not using namespaces, so if the class has namespace separators, skip.
		if ( false !== strpos( $class, '\\' ) ) {
			return false;
		}

		// There's one class without WCS or Subscriptions in its name.
		if ( 'wc_order_item_pending_switch' === $class ) {
			return true;
		}

		return false !== strpos( $class, 'wcs_' ) ||
		       0 === strpos( $class, 'wc_subscription' ) ||
		       ( false !== strpos( $class, 'wc_' ) && false !== strpos( $class, 'subscription' ) );
	}

	/**
	 * Convert the class name into an appropriate file name.
	 *
	 * @author Jeremy Pry
	 *
	 * @param string $class The class name.
	 *
	 * @return string The file name.
	 */
	protected function get_file_name( $class ) {
		return ( $this->is_class_abstract( $class ) ? 'abstract-' : 'class-' ) . str_replace( '_', '-', $class ) . '.php';
	}

	/**
	 * Determine if the class is one of our abstract classes.
	 *
	 * @author Jeremy Pry
	 *
	 * @param string $class The class name.
	 *
	 * @return bool
	 */
	protected function is_class_abstract( $class ) {
		static $abstracts = array(
			'wcs_cache_manager'           => true,
			'wcs_dynamic_hook_deprecator' => true,
			'wcs_hook_deprecator'         => true,
			'wcs_retry_store'             => true,
			'wcs_scheduler'               => true,
			'wcs_sv_api_base'             => true,
		);

		return isset( $abstracts[ $class ] );
	}

	/**
	 * Get the relative path for the class location.
	 *
	 * This handles all of the special class locations and exceptions.
	 *
	 * @author Jeremy Pry
	 *
	 * @param string $class The class name.
	 *
	 * @return string The relative path (from the plugin root) to the class file.
	 */
	protected function get_relative_class_path( $class ) {
		$path     = '/includes';
		$is_admin = false !== strpos( $class, 'admin' );

		if ( $this->is_class_abstract( $class ) ) {
			if ( 'wcs_sv_api_base' === $class ) {
				$path .= '/gateways/paypal/includes/abstracts';
			} else {
				$path .= '/abstracts';
			}
		} elseif ( false !== strpos( $class, 'paypal' ) ) {
			$path .= '/gateways/paypal';
			if ( 'wcs_paypal' === $class ) {
				$path .= '';
			} elseif ( $is_admin ) {
				$path .= '/includes/admin';
			} elseif ( 'wc_paypal_standard_subscriptions' === $class ) {
				$path .= '/includes/deprecated';
			} else {
				$path .= '/includes';
			}
		} elseif ( $is_admin && 'wcs_change_payment_method_admin' !== $class ) {
			$path .= '/admin';
		} elseif ( false !== strpos( $class, 'meta_box' ) ) {
			$path .= '/admin/meta-boxes';
		} elseif ( false !== strpos( $class, 'report' ) ) {
			$path .= '/admin/reports';
		} elseif ( false !== strpos( $class, 'rest' ) ) {
			$path .= WC_Subscriptions::is_woocommerce_pre( '3.0' ) ? '/api/legacy' : '/api';
		} elseif ( false !== strpos( $class, 'api' ) && 'wcs_api' !== $class ) {
			$path .= '/api/legacy';
		} elseif ( false !== strpos( $class, 'data_store' ) ) {
			$path .= '/data-stores';
		} elseif ( false !== strpos( $class, 'deprecat' ) ) {
			$path .= '/deprecated';
		} elseif ( false !== strpos( $class, 'email' ) && 'wc_subscriptions_email' !== $class ) {
			$path .= '/emails';
		} elseif ( false !== strpos( $class, 'gateway' ) && 'wc_subscriptions_change_payment_gateway' !== $class ) {
			$path .= '/gateways';
		} elseif ( false !== strpos( $class, 'legacy' ) || 'wcs_array_property_post_meta_black_magic' === $class ) {
			$path .= '/legacy';
		} elseif ( false !== strpos( $class, 'retry' ) && 'wcs_retry_manager' !== $class ) {
			$path .= '/payment-retry';
		} elseif ( false !== strpos( $class, 'upgrade' ) || false !== strpos( $class, 'repair' ) ) {
			$path .= '/upgrades';
		}

		return trailingslashit( $path );
	}
}
