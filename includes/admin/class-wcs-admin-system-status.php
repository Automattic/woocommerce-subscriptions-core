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

	const WCS_PRODUCT_ID = 27147;

	/**
	 * Attach callbacks
	 *
	 * @since 2.3.0
	 */
	public static function init() {
		add_filter( 'woocommerce_system_status_report', array( __CLASS__, 'render_system_status_items' ) );
	}

	/**
	 * Renders the Subscription information in the WC status page
	 *
	 * @since 2.3.0
	 */
	public static function render_system_status_items() {
		$debug_data = array();

		self::set_debug_mode( $debug_data );

		self::set_staging_mode( $debug_data );

		self::set_theme_overrides( $debug_data );

		self::set_subscription_statuses( $debug_data );

		self::set_woocommerce_account_data( $debug_data );

		$debug_data      = apply_filters( 'wcs_system_status', $debug_data );
		$section_title   = __( 'Subscriptions', 'woocommerce-subscriptions' );
		$section_tooltip = __( 'This section shows any information about Subscriptions.', 'woocommerce-subscriptions' );

		include( plugin_dir_path( WC_Subscriptions::$plugin_file ) . 'templates/admin/status.php' );

		// Subscriptions by Payment Gateway
		$debug_data = array();

		self::set_subscriptions_by_payment_gateway( $debug_data );

		$section_title   = __( 'Subscriptions by Payment Gateway', 'woocommerce-subscriptions' );
		$section_tooltip = __( 'This section shows information about Subscription payment methods.', 'woocommerce-subscriptions' );

		include( plugin_dir_path( WC_Subscriptions::$plugin_file ) . 'templates/admin/status.php' );

		// Payment gateway features
		$debug_data = array();

		self::set_subscriptions_payment_gateway_support( $debug_data );

		$section_title   = __( 'Payment Gateway Support', 'woocommerce-subscriptions' );
		$section_tooltip = __( 'This section shows information about payment gateway feature support.', 'woocommerce-subscriptions' );

		include( plugin_dir_path( WC_Subscriptions::$plugin_file ) . 'templates/admin/status.php' );
	}

	/**
	 * Include WCS_DEBUG flag
	 */
	private static function set_debug_mode( &$debug_data ) {
		$is_wcs_debug = defined( 'WCS_DEBUG' ) ? WCS_DEBUG : false;

		$debug_data['wcs_debug'] = array(
			'name'    => _x( 'WCS_DEBUG', 'label that indicates whether debugging is turned on for the plugin', 'woocommerce-subscriptions' ),
			'note'    => ( $is_wcs_debug ) ? __( 'Yes', 'woocommerce-subscriptions' ) :  __( 'No', 'woocommerce-subscriptions' ),
			'success' => $is_wcs_debug ? 0 : 1,
		);
	}

	/**
	 * Include the staging/live mode the store is running in.
	 */
	private static function set_staging_mode( &$debug_data ) {
		$debug_data['wcs_staging'] = array(
			'name'    => _x( 'Subscriptions Mode', 'Live or Staging, Label on WooCommerce -> System Status page', 'woocommerce-subscriptions' ),
			'note'    => '<strong>' . ( ( WC_Subscriptions::is_duplicate_site() ) ? _x( 'Staging', 'refers to staging site', 'woocommerce-subscriptions' ) :  _x( 'Live', 'refers to live site', 'woocommerce-subscriptions' ) ) . '</strong>',
			'success' => ( WC_Subscriptions::is_duplicate_site() ) ? 0 : 1,
		);
	}

	/**
	 * List any Subscriptions template overrides.
	 */
	private static function set_theme_overrides( &$debug_data ) {
		$theme_overrides = self::get_theme_overrides();

		$debug_data['wcs_theme_overrides'] = array(
			'name'      => _x( 'Subscriptions Template Theme Overrides', 'label for the system status page', 'woocommerce-subscriptions' ),
			'data'      => $theme_overrides,
		);
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
	 * Add a breakdown of Subscriptions per status.
	 */
	private static function set_subscription_statuses( &$debug_data ) {

		$subscriptions_by_status        = (array) wp_count_posts( 'shop_subscription' );
		$subscriptions_by_status_output = '';

		foreach ( $subscriptions_by_status as $status => $count ) {
			if ( ! empty( $count ) ) {
				$subscriptions_by_status_output[] = $status . ': ' . $count;
			}
		}

		$debug_data['wcs_subscriptions_by_status'] = array(
			'name'      => _x( 'Subscription Statuses', 'label for the system status page', 'woocommerce-subscriptions' ),
			'mark'      => '',
			'mark_icon' => '',
			'data'      => $subscriptions_by_status_output,
		);
	}

	/**
	 * Include information about whether the store is linked to a WooCommerce account and whether they have an active WCS product key.
	 */
	private static function set_woocommerce_account_data( &$debug_data ) {

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
					if ( isset( $subscription['product_id'] ) && self::WCS_PRODUCT_ID === $subscription['product_id'] ) {
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
	}

	/**
	 * Add a breakdown of subscriptions per payment gateway.
	 */
	private static function set_subscriptions_by_payment_gateway( &$debug_data ) {
		global $wpdb;

		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		$results  = $wpdb->get_results( "
			SELECT COUNT(subscriptions.ID) as count, post_meta.meta_value as payment_method, subscriptions.post_status
			FROM $wpdb->posts as subscriptions RIGHT JOIN $wpdb->postmeta as post_meta ON post_meta.post_id = subscriptions.ID
			WHERE subscriptions.post_type = 'shop_subscription' && post_meta.meta_key = '_payment_method'
			GROUP BY post_meta.meta_value, subscriptions.post_status", ARRAY_A );

		$subscriptions_payment_gateway_data = array();

		foreach ( $results as $result ) {
			$payment_method      = $result['payment_method'];
			$subscription_status = $result['post_status'];

			if ( isset( $gateways[ $payment_method ] ) ) {
				$payment_method_name = $gateways[ $payment_method ]->method_title;
			} else {
				$payment_method      = 'other';
				$payment_method_name = 'Other';
			}

			$key = 'wcs_payment_method_subscriptions_by' . $payment_method;

			if ( ! isset( $debug_data[ $key ] ) ) {
				$debug_data[ $key ] = array(
					'name' => $payment_method_name,
					'data' => array(),
				);
			}

			$debug_data[ $key ]['data'][] = $subscription_status . ': ' . $result['count'];
		}
	}

	/**
	 * List the enabled payment gateways and the features they support.
	 */
	private static function set_subscriptions_payment_gateway_support( &$debug_data ) {
		$gateways = WC()->payment_gateways->get_available_payment_gateways();

		foreach ( $gateways as $gateway_id => $gateway ) {
			$debug_data[ 'wcs_' . $gateway_id . '_feature_support' ] = array(
				'name' => $gateway->method_title,
				'data' => $gateway->supports,
			);

			if ( 'paypal' === $gateway_id ) {
				$are_reference_transactions_enabled = WCS_PayPal::are_reference_transactions_enabled();

				$debug_data['wcs_paypal_reference_transactions'] = array(
					'name'      => _x( 'PayPal Reference Transactions Enabled', 'label for the system status page', 'woocommerce-subscriptions' ),
					'mark_icon' => $are_reference_transactions_enabled ? 'yes' : 'warning',
					'note'      => $are_reference_transactions_enabled ? 'Yes' : 'No',
					'success'   => $are_reference_transactions_enabled,
				);
			}
		}
	}
}
WCS_Admin_System_Status::init();
