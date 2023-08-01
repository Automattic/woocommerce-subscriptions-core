<?php
/**
 * Class WCS_Admin_Empty_State_Manager
 *
 * @package WooCommerce Subscriptions
 * @since 6.1.0
 */

defined( 'ABSPATH' ) || exit;

class WCS_Admin_Empty_State_Manager {

	/**
	 * Initialize the class and attach callbacks.
	 */
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts_and_styles' ) );
	}

	/**
	 * Gets the Admin Subscriptions list table HTML for the empty state.
	 *
	 * @return string The HTML for the empty state.
	 */
	public static function get_list_table_html() {
		$html = wc_get_template_html(
			'html-admin-empty-list-table.php',
			[],
			'',
			WC_Subscriptions_Core_Plugin::instance()->get_subscriptions_core_directory( 'templates/admin/' )
		);

		// Backwards compatibility for the woocommerce_subscriptions_not_found_label filter.
		if ( has_action( 'woocommerce_subscriptions_not_found_label' ) ) {
			wcs_deprecated_hook( 'woocommerce_subscriptions_not_found_label', '6.1.0', 'woocommerce_subscriptions_not_found_html' );

			/**
			 * Filters the HTML for the empty state.
			 *
			 * The woocommerce_subscriptions_not_found_label filter no longer makes sense as the HTML is now
			 * more complex - it is no longer just a string. For backwards compatibility we still filter the
			 * full content shown in the empty state.
			 *
			 * @deprecated 6.1.0 Use the woocommerce_subscriptions_not_found_html filter instead.
			 * @param string $html The HTML for the empty state.
			 */
			$html = apply_filters( 'woocommerce_subscriptions_not_found_label', $html );
		}

		/**
		 * Filters the HTML for the empty state.
		 *
		 * @since 6.1.0
		 * @param string $html The HTML for the empty state.
		 */
		return apply_filters( 'woocommerce_subscriptions_not_found_html', $html );
	}

	/**
	 * Enqueues the scripts and styles for the empty state.
	 */
	public static function enqueue_scripts_and_styles() {
		$screen = get_current_screen();

		// Only enqueue the scripts on the admin subscriptions screen.
		if ( ! $screen || 'edit-shop_subscription' !== $screen->id || wcs_do_subscriptions_exist() ) {
			return;
		}

		wp_register_style(
			'Woo-Subscriptions-Empty-State',
			WC_Subscriptions_Core_Plugin::instance()->get_subscriptions_core_directory_url( 'assets/css/admin-empty-state.css' ),
			[],
			WC_Subscriptions_Core_Plugin::instance()->get_library_version()
		);

		wp_enqueue_style( 'Woo-Subscriptions-Empty-State' );
	}
}
