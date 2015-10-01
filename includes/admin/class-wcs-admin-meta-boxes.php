<?php
/**
 * WooCommerce Subscriptions Admin Meta Boxes
 *
 * Sets up the write panels used by the subscription custom order/post type
 *
 * @author   Prospress
 * @category Admin
 * @package  WooCommerce Subscriptions/Admin
 * @version  2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * WC_Admin_Meta_Boxes
 */
class WCS_Admin_Meta_Boxes {

	/**
	 * Constructor
	 */
	public function __construct() {

		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 25 );

		add_action( 'add_meta_boxes', array( $this, 'remove_meta_boxes' ), 35 );

		// We need to remove core WC save methods for meta boxes we don't use
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'remove_meta_box_save' ), -1, 2 );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles_scripts' ), 20 );

		// We need to hook to the 'shop_order' rather than 'shop_subscription' because we declared that the 'shop_susbcription' order type supports 'order-meta-boxes'
		add_action( 'woocommerce_process_shop_order_meta', 'WCS_Meta_Box_Schedule::save', 10, 2 );
		add_action( 'woocommerce_process_shop_order_meta', 'WCS_Meta_Box_Subscription_Data::save', 10, 2 );
	}

	/**
	 * Add WC Meta boxes
	 */
	public function add_meta_boxes() {
		global $current_screen, $post_ID;

		add_meta_box( 'woocommerce-subscription-data', _x( 'Subscription Data', 'meta box title', 'woocommerce-subscriptions' ), 'WCS_Meta_Box_Subscription_Data::output', 'shop_subscription', 'normal', 'high' );

		add_meta_box( 'woocommerce-subscription-schedule', _x( 'Billing Schedule', 'meta box title', 'woocommerce-subscriptions' ), 'WCS_Meta_Box_Schedule::output', 'shop_subscription', 'side', 'default' );

		remove_meta_box( 'woocommerce-order-data', 'shop_subscription', 'normal' );

		add_meta_box( 'subscription_renewal_orders', _x( 'Related Orders', 'meta box title', 'woocommerce-subscriptions' ), 'WCS_Meta_Box_Related_Orders::output', 'shop_subscription', 'normal', 'low' );

		// Only display the meta box if an order relates to a subscription
		if ( 'shop_order' === get_post_type( $post_ID ) && wcs_order_contains_subscription( $post_ID, 'any' ) ) {
			add_meta_box( 'subscription_renewal_orders', _x( 'Related Orders', 'meta box title', 'woocommerce-subscriptions' ), 'WCS_Meta_Box_Related_Orders::output', 'shop_order', 'normal', 'low' );
		}
	}

	/**
	 * Removes the core Order Data meta box as we add our own Subscription Data meta box
	 */
	public function remove_meta_boxes() {
		remove_meta_box( 'woocommerce-order-data', 'shop_subscription', 'normal' );
	}

	/**
	 * Don't save save some order related meta boxes
	 */
	public function remove_meta_box_save( $post_id, $post ) {

		if ( 'shop_subscription' == $post->post_type ) {
			remove_action( 'woocommerce_process_shop_order_meta', 'WC_Meta_Box_Order_Data::save', 40, 2 );
			remove_action( 'woocommerce_process_shop_order_meta', 'WC_Meta_Box_Order_Actions::save', 50, 2 );
		}
	}

	/**
	 * Print admin styles/scripts
	 */
	public function enqueue_styles_scripts() {
		global $post;

		// Get admin screen id
		$screen = get_current_screen();

		if ( 'shop_subscription' == $screen->id ) {

			wp_register_script( 'jstz', plugin_dir_url( WC_Subscriptions::$plugin_file ) . '/assets/js/admin/jstz.min.js' );

			wp_register_script( 'momentjs', plugin_dir_url( WC_Subscriptions::$plugin_file ) . '/assets/js/admin/moment.min.js' );

			wp_enqueue_script( 'wcs-admin-meta-boxes-subscription', plugin_dir_url( WC_Subscriptions::$plugin_file ) . '/assets/js/admin/meta-boxes-subscription.js', array( 'wc-admin-meta-boxes', 'jstz', 'momentjs' ), WC_VERSION );

			wp_localize_script( 'wcs-admin-meta-boxes-subscription', 'wcs_admin_meta_boxes', apply_filters( 'woocommerce_subscriptions_admin_meta_boxes_script_parameters', array(
				'i18n_start_date_notice'         => __( 'Please enter a start date in the past.', 'woocommerce-subscriptions' ),
				'i18n_past_date_notice'          => __( 'Please enter a date at least one hour into the future.', 'woocommerce-subscriptions' ),
				'i18n_next_payment_start_notice' => __( 'Please enter a date after the trial end.', 'woocommerce-subscriptions' ),
				'i18n_next_payment_trial_notice' => __( 'Please enter a date after the start date.', 'woocommerce-subscriptions' ),
				'i18n_trial_end_start_notice'    => __( 'Please enter a date after the start date.', 'woocommerce-subscriptions' ),
				'i18n_trial_end_next_notice'     => __( 'Please enter a date before the next payment.', 'woocommerce-subscriptions' ),
				'i18n_end_date_notice'           => __( 'Please enter a date after the next payment.', 'woocommerce-subscriptions' ),
				'search_customers_nonce'         => wp_create_nonce( 'search-customers' ),
			) ) );
		}
	}
}

new WCS_Admin_Meta_Boxes();
