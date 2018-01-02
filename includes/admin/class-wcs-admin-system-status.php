<?php
/**
 * Subscriptions System Status
 *
 * Adds additional Subscriptions related information to the WooCommerce System Status.
 *
 * @package    WooCommerce Subscriptions
 * @subpackage WC_Subscriptions_Admin
 * @category   Class
 * @author     Prospress
 * @since      2.3.0
 */
class WCS_Admin_System_Status {

	/**
	 * Attach callbacks
	 *
	 * @since 2.3.0
	 */
	public static function init() {
		add_filter( 'woocommerce_system_status_report', __CLASS__ . '::render_system_status_items' );
	}

	/**
	 * Renders the Subscription information in the WC status page
	 *
	 * @since 2.3.0
	 */
	public static function render_system_status_items() {
		$debug_data   = array();
		$is_wcs_debug = defined( 'WCS_DEBUG' ) ? WCS_DEBUG : false;

		$debug_data['wcs_debug'] = array(
			'name'    => _x( 'WCS_DEBUG', 'label that indicates whether debugging is turned on for the plugin', 'woocommerce-subscriptions' ),
			'note'    => ( $is_wcs_debug ) ? __( 'Yes', 'woocommerce-subscriptions' ) :  __( 'No', 'woocommerce-subscriptions' ),
			'success' => $is_wcs_debug ? 0 : 1,
		);

		$debug_data['wcs_staging'] = array(
			'name'    => _x( 'Subscriptions Mode', 'Live or Staging, Label on WooCommerce -> System Status page', 'woocommerce-subscriptions' ),
			'note'    => '<strong>' . ( ( WC_Subscriptions::is_duplicate_site() ) ? _x( 'Staging', 'refers to staging site', 'woocommerce-subscriptions' ) :  _x( 'Live', 'refers to live site', 'woocommerce-subscriptions' ) ) . '</strong>',
			'success' => ( WC_Subscriptions::is_duplicate_site() ) ? 0 : 1,
		);

		$theme_overrides = self::get_theme_overrides();
		$debug_data['wcs_theme_overrides'] = array(
			'name'      => _x( 'Subscriptions Template Theme Overrides', 'label for the system status page', 'woocommerce-subscriptions' ),
			'data'      => $theme_overrides,
		);

		$debug_data['wcs_subscriptions_by_status'] = array(
			'name'      => _x( 'Subscription Statuses', 'label for the system status page', 'woocommerce-subscriptions' ),
			'mark'      => '',
			'mark_icon' => '',
			'note'      => self::get_subscriptions_statuses(),
		);

		// Check for a connected WooCommerce account and active Subscriptions product key
		if ( class_exists( 'WC_Helper' ) ) {
			$woocommerce_account_auth      = WC_Helper_Options::get( 'auth' );
			$woocommerce_account_connected = ! empty( $woocommerce_account_auth );

			$debug_data['wcs_woocommerce_account_connected'] = array(
				'name'      => _x( 'WooCommerce Account Connected', 'label for the system status page', 'woocommerce-subscriptions' ),
				'mark_icon' => $woocommerce_account_connected ? 'yes' : 'warning',
				'note'      => $woocommerce_account_connected ? 'Yes' : 'No',
				'success'   => $woocommerce_account_connected,
			);

			if ( $woocommerce_account_connected ) {
				$woocommerce_account_subscriptions = WC_Helper::get_subscriptions();
				$site_id                           = absint( $woocommerce_account_auth['site_id'] );

				foreach ( $woocommerce_account_subscriptions as $subscription ) {
					if ( isset( $subscription['product_id'] ) && 27147 === $subscription['product_id'] ) {
						$active = in_array( $site_id, $subscription['connections'] );

						$debug_data['wcs_active_product_key'] = array(
							'name'      => _x( 'Active Product Key', 'label for the system status page', 'woocommerce-subscriptions' ),
							'mark_icon' => $active ? 'yes' : 'no',
							'note'      => $active ? 'Yes' : 'No',
							'success'   => $active,
						);
						break;
					}
				}
			}
		}

		$debug_data      = apply_filters( 'wcs_system_status', $debug_data );
		$section_title   = __( 'Subscriptions', 'woocommerce-subscriptions' );
		$section_tooltip = __( 'This section shows any information about Subscriptions.', 'woocommerce-subscriptions' );

		include( plugin_dir_path( WC_Subscriptions::$plugin_file ) . 'templates/admin/status.php' );
	}

	/**
	 * Determine which of our files have been overridden by the theme.
	 *
	 * @author Jeremy Pry
	 * @return array Theme override data.
	 */
	private static function get_theme_overrides() {
		$wcs_template_dir = dirname( WC_Subscriptions::$plugin_file ) . '/templates/';
		$wc_template_path = trailingslashit( wc()->template_path() );
		$theme_root       = trailingslashit( get_theme_root() );
		$overridden       = array();
		$outdated         = false;
		$templates        = WC_Admin_Status::scan_template_files( $wcs_template_dir );

		foreach ( $templates as $file ) {
			$theme_file = $is_outdated = false;
			$locations  = array(
				get_stylesheet_directory() . "/{$file}",
				get_stylesheet_directory() . "/{$wc_template_path}{$file}",
				get_template_directory() . "/{$file}",
				get_template_directory() . "/{$wc_template_path}{$file}",
			);

			foreach ( $locations as $location ) {
				if ( is_readable( $location ) ) {
					$theme_file = $location;
					break;
				}
			}

			if ( ! empty( $theme_file ) ) {
				$core_version  = WC_Admin_Status::get_file_version( $wcs_template_dir . $file );
				$theme_version = WC_Admin_Status::get_file_version( $theme_file );

				$overridden_template_output = sprintf( '<code>%s</code>', esc_html( str_replace( $theme_root, '', $theme_file ) ) );

				if ( $core_version && ( empty( $theme_version ) || version_compare( $theme_version, $core_version, '<' ) ) ) {
					$outdated = true;
					$overridden_template_output .= sprintf(
						/* translators: %1$s is the file version, %2$s is the core version */
						esc_html__( 'version %1$s is out of date. The core version is %2$s', 'woocommerce-subscriptions' ),
						'<strong style="color:red">' . esc_html( $theme_version ) . '</strong>',
						'<strong>' . esc_html( $core_version ) . '</strong>'
					);
				}

				$overridden[] = $overridden_template_output;
			}
		}

		if ( $outdated ) {
			ob_start(); ?>
			<br />
			<mark class="error"><span class="dashicons dashicons-warning"></span></mark>
			<a href="https://docs.woocommerce.com/document/fix-outdated-templates-woocommerce/" target="_blank">
				<?php esc_html_e( 'Learn how to update', 'woocommerce-subscriptions' ) ?>
			</a>
			<?php
			$overridden['has_outdated_templates'] = ob_get_clean();
		}

		return $overridden;
	}

	/**
	 * Get a breakdown of Subscriptions per status.
	 *
	 * @return string
	 */
	private static function get_subscriptions_statuses() {
		global $wpdb;

		$subscriptions_by_status = $wpdb->get_results( "
			SELECT COUNT(ID), post_status
			FROM $wpdb->posts
			WHERE post_type = 'shop_subscription'
			GROUP BY post_status
			ORDER BY COUNT(ID) DESC", ARRAY_A );

		$subscriptions_by_status_output = '';

		foreach ( $subscriptions_by_status as $result ) {
			$subscriptions_by_status_output .= $result['post_status'] . ': ' . $result['COUNT(ID)'] . ' </br>';
		}

		return $subscriptions_by_status_output;
	}
}
WCS_Admin_System_Status::init();
