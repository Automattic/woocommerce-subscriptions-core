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

			add_filter( 'wcs_display_date_type', array( $this, 'maybe_hide_date_type' ), 10, 3 );

			// Display the number of retries in the Orders list table
			add_action( 'manage_shop_order_posts_custom_column', __CLASS__ . '::add_column_content', 20, 2 );
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
	 * Dispay the number of retries on a renewal order in the Orders list table.
	 *
	 * @param string $column The string of the current column
	 * @param int $post_id The ID of the order
	 * @since 2.1
	 */
	public static function add_column_content( $column, $post_id ) {

		if ( 'subscription_relationship' == $column && wcs_order_contains_subscription( $post_id, 'renewal' ) && ( $retry_count = WCS_Retry_Manager::store()->get_retry_count_for_order( $post_id ) ) > 0 ) {
			echo '<br /><span class="payment_retry tips" data-tip="' . esc_attr( sprintf( __( '%d Failed Payment Retries', 'woocommerce-subscriptions' ), $retry_count ) ) . '"></span>';
		}
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
				'name'     => __( 'Retry Failed Payments', 'woocommerce-subscriptions' ),
				'desc'     => __( 'Enable automatic retry of failed recurring payments', 'woocommerce-subscriptions' ),
				'id'       => $this->setting_id,
				'default'  => 'no',
				'type'     => 'checkbox',
				'desc_tip' => sprintf( __( 'Attempt to recover recurring revenue that would otherwise be lost due to payment methods being declined only temporarily. %sLearn more%s.', 'woocommerce-subscriptions' ), '<a href="https://docs.woocommerce.com/document/subscriptions/failed-payment-retry/">', '</a>' ),
			),
		) );

		return $settings;
	}
}
