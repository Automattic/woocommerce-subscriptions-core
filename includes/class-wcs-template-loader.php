<?php
/**
 * WC Subscriptions Template Loader
 *
 * @version		2.0
 * @author 		Prospress
 */
class WCS_Template_Loader {

	public static function init() {

		add_action( 'init', __CLASS__ . '::wcs_add_view_subscription_endpoint' );

		add_action( 'query_vars', __CLASS__ . '::wcs_add_query_vars' );

		add_filter( 'wc_get_template', __CLASS__ . '::wcs_add_view_subscription_template', 10, 5 );

		add_filter( 'the_title', __CLASS__ . '::wcs_subscription_endpoint_title', 11, 1 );

	}

	/**
	 * Show the subscription template when view a subscription instead of loading the default order template.
	 *
	 * @param $located
	 * @param $template_name
	 * @param $args
	 * @param $template_path
	 * @param $default_path
	 * @since 2.0
	 */
	public static function wcs_add_view_subscription_template( $located, $template_name, $args, $template_path, $default_path ) {
		global $wp;

 		if ( 'myaccount/my-account.php' == $template_name && ! empty( $wp->query_vars['view-subscription'] ) ) {

			if ( locate_template( 'view-subscription.php' ) != '' ) {

				$located = locate_template( 'view-subscription.php' );

			} else {
				$located = dirname( dirname( __FILE__ ) ) . '/templates/myaccount/view-subscription.php';

			}

		}

		return $located;
	}

	/**
	 * Set the subscription page title when viewing a subscription.
	 *
	 * @since 2.0
	 * @param $title
	 */
	public static function wcs_subscription_endpoint_title( $title ) {
		global $wp;

		if ( is_main_query() && in_the_loop() && is_page() && isset( $wp->query_vars['view-subscription'] ) ) {

			$subscription_id = $wp->query_vars['view-subscription'];

			if ( 'shop_subscription' == get_post_type( $subscription_id ) ) {
				//remove_filter( 'the_title', __CLASS__ . '::wcs_subscription_endpoint_title' );
				$title = sprintf( __( 'Subscription #%s', 'woocommerce-subscriptions' ), $subscription_id );
			}

		}

		return $title;
	}

	/**
	 *
	 *
	 * @since 2.0
	 */
	public static function wcs_add_query_vars( $vars ) {
		$vars[] = 'view-subscription';
		return $vars;
	}

	/**
	 *
	 *
	 * @since 2.0
	 */
	public static function wcs_add_view_subscription_endpoint() {
		add_rewrite_endpoint( 'view-subscription', EP_ROOT | EP_PAGES );
	}
}
WCS_Template_Loader::init();