<?php
/**
 *
 */

defined( 'ABSPATH' ) || exit;

class WCS_Admin_Empty_State_Manager {

	/**
	 * Undocumented function
	 */
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts_and_styles' ) );
	}

	/**
	 * Undocumented function
	 *
	 * @return string
	 */
	public static function get_list_table_html() {
		$description = apply_filters( 'woocommerce_subscriptions_not_found_description', __( "This is where you'll see and manage all subscriptions in your store. Create a subscription product to turn one-time purchases into a steady income.", 'woocommerce-subscriptions' ) );
		$html        = wc_get_template_html(
			'html-admin-empty-list-table.php',
			array( 'description' => $description ),
			'',
			WC_Subscriptions_Core_Plugin::instance()->get_subscriptions_core_directory( 'templates/admin/' )
		);

		if ( has_action( 'woocommerce_subscriptions_not_found_label' ) ) {
			wcs_deprecated_hook( 'woocommerce_subscriptions_not_found_label', 'x.x.x', 'woocommerce_subscriptions_not_found_html', 'Use the woocommerce_subscriptions_not_found_html filter instead.' );
			$html = apply_filters( 'woocommerce_subscriptions_not_found_label', $html );
		}

		return apply_filters( 'woocommerce_subscriptions_not_found_html', $html );
	}

	/**
	 * Undocumented function
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
