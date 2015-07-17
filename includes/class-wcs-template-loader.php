<?php
/**
 * WC Subscriptions Template Loader
 *
 * @version		2.0
 * @author 		Prospress
 */
class WCS_Template_Loader {

	public static function init() {
		add_filter( 'wc_get_template', __CLASS__ . '::add_view_subscription_template', 10, 5 );
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
	public static function add_view_subscription_template( $located, $template_name, $args, $template_path, $default_path ) {
		global $wp;

		if ( 'myaccount/my-account.php' == $template_name && ! empty( $wp->query_vars['view-subscription'] ) ) {
			$located = wc_locate_template( 'myaccount/view-subscription.php', $template_path, plugin_dir_path( WC_Subscriptions::$plugin_file ) . 'templates/' );
		}

		return $located;
	}
}
WCS_Template_Loader::init();
