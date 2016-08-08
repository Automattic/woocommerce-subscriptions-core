<?php
/**
 * Create settings and add meta boxes relating to retries
 *
 * @author   Prospress
 * @category Admin
 * @package  WooCommerce Subscriptions/Admin
 * @version  2.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WCS_Retry_Admin {

	/**
	 * Constructor
	 */
	public function __construct( $setting_id ) {

		$this->setting_id = $setting_id;

		add_filter( 'woocommerce_subscription_settings', array( $this, 'add_settings' ) );

		if ( WCS_Retry_Manager::is_retry_enabled() ) {
			add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 50 );

			add_filter( 'wcs_admin_display_date_type', array( $this, 'maybe_hide_date_type' ), 10, 3 );
		}
	}

	/**
	 * Add a meta box to the Edit Order screen to display the retries relating to that order
	 *
	 * @return null
	 */
	public function add_meta_boxes() {
		global $current_screen, $post_ID;

		// Only display the meta box if an order relates to a subscription
		if ( 'shop_order' === get_post_type( $post_ID ) && wcs_order_contains_renewal( $post_ID ) && WCS_Retry_Manager::store()->get_retry_count_for_order( $post_ID ) > 0 ) {
			add_meta_box( 'renewal_payment_retries', __( 'Automatic Failed Payment Retries', 'woocommerce-subscriptions' ), 'WCS_Meta_Box_Payment_Retries::output', 'shop_order', 'normal', 'low' );
		}
	}

	/**
	 * Only display the retry payment date on the Edit Subscription screen if the subscription has a pending retry
	 * and when that is the case, do not display the next payment date (because it will still be set to the original
	 * payment date, in the past).
	 *
	 * @param bool $show_date_type
	 * @param string $date_key
	 * @param WC_Subscription $the_subscription
	 * @return bool
	 */
	public function maybe_hide_date_type( $show_date_type, $date_key, $the_subscription ) {

		if ( 'payment_retry' === $date_key && 0 == $the_subscription->get_time( 'payment_retry' ) ) {
			$show_date_type = false;
		} elseif ( 'next_payment' === $date_key && $the_subscription->get_time( 'payment_retry' ) > 0 ) {
			$show_date_type = false;
		}

		return $show_date_type;
	}

	/**
	 * Add a setting to enable/disable the retry system
	 *
	 * @param array
	 * @return null
	 */
	public function add_settings( $settings ) {

		$misc_section_end = wp_list_filter( $settings, array( 'id' => 'woocommerce_subscriptions_miscellaneous', 'type' => 'sectionend' ) );

		$spliced_array = array_splice( $settings, key( $misc_section_end ), 0, array(
			array(
				'name'     => __( 'Enable Automatic Retry', 'woocommerce-subscriptions' ),
				'desc'     => __( 'Enable automatic retry of failed recurring payments.', 'woocommerce-subscriptions' ),
				'id'       => $this->setting_id,
				'default'  => 'no',
				'type'     => 'checkbox',
			),
		) );

		return $settings;
	}
}
