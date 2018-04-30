<?php
/**
 * Class for managing and displaying an admin notice displayed after upgrading Subscriptions.
 *
 * @author   Prospress
 * @category Admin
 * @package  WooCommerce Subscriptions/Admin/Upgrades
 * @version  2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WCS_Upgrade_Notice_Manager {

	/**
	 * The version this notice relates to.
	 *
	 * @var string
	 */
	protected static $version = '2.3.0';

	/**
	 * Attach callbacks.
	 *
	 * @since 2.3.0
	 */
	public static function init() {
		add_action( 'woocommerce_subscriptions_upgraded', array( __CLASS__, 'maybe_record_upgrade' ), 10, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'maybe_show_admin_notice' ) );
	}

	/**
	 * Store an option to display an upgrade notice when the store is upgraded.
	 *
	 * @param  string $current_version           The new version the site has been updated to.
	 * @param  string $previously_active_version The version of Subscriptions the store was running prior to upgrading.
	 * @since 2.3.0
	 */
	public static function maybe_record_upgrade( $current_version, $previously_active_version ) {
		if ( version_compare( $previously_active_version, self::$version, '<' ) && version_compare( $current_version, self::$version, '>=' ) ) {
			update_option( 'wcs_display_upgrade_notice', self::$version );
		}
	}

	/**
	 * Display the upgrade notice including details about the update if it hasn't been dismissed.
	 *
	 * @since 2.3.0
	 */
	public static function maybe_show_admin_notice() {

		if ( isset( $_GET['_wcsnonce'] ) && wp_verify_nonce( $_GET['_wcsnonce'], 'dismiss_upgrade_notice' ) && ! empty( $_GET['dismiss_upgrade_notice'] ) && self::$version === $_GET['dismiss_upgrade_notice'] ) {
			delete_option( 'wcs_display_upgrade_notice' );
			return;
		}

		if ( get_option( 'wcs_display_upgrade_notice' ) !== self::$version ) {
			return;
		}

		$version     = _x( '2.3', 'plugin version number used in admin notice', 'woocommerce-subscription' );
		$dismiss_url = wp_nonce_url( add_query_arg( 'dismiss_upgrade_notice', self::$version ), 'dismiss_upgrade_notice', '_wcsnonce' );
		$notice      = new WCS_Admin_Notice( 'notice notice-info', array(), $dismiss_url );
		$features    = array(
			array(
				'title'       => __( 'New Subscription Coupon Features', 'woocommerce-subscription' ),
				'description' => __( 'Want to offer customers coupons which apply for 6 months? You can now define the number of cycles discounts would be applied.', 'woocommerce-subscription' ),
			),
			array(
				'title'       => __( 'New Signup Pricing Options for Synchronized Subscriptions', 'woocommerce-subscription' ),
				'description' => __( 'Charge the full recurring price at the time of sign up for synchronized subscriptions. Your customers can now receive their products straight away.', 'woocommerce-subscription' ),
			),
			array(
				'title'       => __( 'Link Parent Orders to Subscriptions', 'woocommerce-subscription' ),
				// translators: placeholders are opening and closing <a> tags linking to documentation.
				'description' => sprintf( __( 'For subscriptions with no parent order, shop managers can now choose a parent order via the Edit Subscription screen. This makes it possible to set a parent order on %smanually created subscriptions%s. The order can also be sent to customers to act as an invoice that needs to be paid to activate the subscription.', 'woocommerce-subscription' ), '<a href="https://docs.woocommerce.com/document/subscriptions/add-or-modify-a-subscription/">', '</a>' ),
			),
			array(
				'title'       => __( 'Early Renewal', 'woocommerce-subscription' ),
				'description' => __( 'Customers can now renew their subscriptions before the scheduled next payment date. Why not use this to email your customers a coupon a month before their annual subscription renewals to get access to that revenue sooner?', 'woocommerce-subscription' ),
			),
		);

		// translators: placeholder is Subscription version string ('2.3')
		$notice->set_heading( sprintf( __( 'Welcome to Subscriptions %s', 'woocommerce-subscriptions' ), $version ) );
		$notice->set_content_template( 'update-welcome-notice.php', plugin_dir_path( WC_Subscriptions::$plugin_file ) . 'includes/upgrades/templates/', array(
			'version'  => $version,
			'features' => $features,
		) );
		$notice->set_actions( array(
			array(
				'name' => __( 'Learn More', 'woocommerce-subscriptions' ),
				'url'  => 'http://docs.woocommerce.com/document/subscriptions/',
			),
		) );

		$notice->display();
	}
}
