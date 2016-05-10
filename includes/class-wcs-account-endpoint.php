<?php
/**
 * WooCommerce Subscriptions Account Endpoint
 *
 * Add a Subscriptions tab and page to the Account page in WooCommerce 2.6+
 *
 * @since	2.0.14
 * @author 	Prospress
 */

class WCS_Account_Endpoint {

	/**
	 * Custom endpoint name.
	 *
	 * @var string
	 */
	protected $endpoint = 'subscriptions';

	/**
	 * Plugin actions.
	 */
	public function __construct() {
		// Actions used to insert a new endpoint in the WordPress.
		add_action( 'init', array( $this, 'add_rewrite_endpoint' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ), 0 );

		// Change the My Accout page title.
		add_filter( 'the_title', array( $this, 'get_title' ) );

		// Insering your new tab/page into the My Account page.
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_menu_items' ) );
		add_action( 'woocommerce_account_' . $this->endpoint .  '_endpoint', array( $this, 'content' ) );
	}

	/**
	 * Register new endpoint to use inside My Account page.
	 *
	 * @see https://developer.wordpress.org/reference/functions/add_rewrite_endpoint/
	 */
	public function add_rewrite_endpoint() {
		add_rewrite_endpoint( $this->endpoint, EP_ROOT | EP_PAGES );
	}

	/**
	 * Add new query var.
	 *
	 * @param array $vars
	 * @return array
	 */
	public function add_query_vars( $vars ) {
		$vars[] = $this->endpoint;

		return $vars;
	}

	/**
	 * Set endpoint title.
	 *
	 * @param string $title
	 * @return string
	 */
	public function get_title( $title ) {
		global $wp_query;

		$is_endpoint = isset( $wp_query->query_vars[ $this->endpoint ] );

		if ( $is_endpoint && ! is_admin() && is_main_query() && in_the_loop() && is_account_page() ) {

			$title = __( 'Subscriptions', 'woocommerce-subscriptions' );

			remove_filter( 'the_title', array( $this, 'endpoint_title' ) );
		}

		return $title;
	}

	/**
	 * Insert the new endpoint into the My Account menu.
	 *
	 * @param array $items
	 * @return array
	 */
	public function add_menu_items( $menu_items ) {

		// Add our menu item after the Orders tab if it exists, otherwise just add it to the end
		if ( array_key_exists( 'orders', $menu_items ) ) {
			$menu_items = wcs_array_insert_after( 'orders', $menu_items, $this->endpoint, __( 'Subscriptions', 'woocommerce-subscriptions' ) );
		} else {
			$menu_items[ $this->endpoint ] = __( 'Subscriptions', 'woocommerce-subscriptions' );
		}

		return $menu_items;
	}

	/**
	 * Endpoint HTML content.
	 */
	public function content() {
		wc_print_notices();
		wc_get_template( 'myaccount/navigation.php' );
		WC_Subscriptions::get_my_subscriptions_template();
	}
}
new WCS_Account_Endpoint();
