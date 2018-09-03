<?php
/**
 * Updates the 'post_author' column for subscriptions on WC 3.5+.
 *
 * @author		Prospress
 * @category	Admin
 * @package		WooCommerce Subscriptions/Admin/Upgrades
 * @version		2.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WCS_Upgrade_Subscription_Post_Author extends WCS_Background_Upgrader {

	/**
	 * Constructor
	 *
	 * @param WC_Logger $logger The WC_Logger instance.
	 * @since 2.4.0
	 */
	public function __construct( WC_Logger $logger ) {
		$this->scheduled_hook = 'wcs_upgrade_subscription_post_author';
		$this->log_handle     = 'wcs-upgrade-subscription-post-author';
		$this->logger         = $logger;
	}

	/**
	 * Update a subscription, setting its post_author to its customer ID.
	 *
	 * @since 2.4.0
	 */
	protected function update_item( $subscription_id ) {
		global $wpdb;

		try {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
					SET p.post_author = pm.meta_value WHERE p.ID = %d AND pm.meta_key = '_customer_user'",
					$subscription_id
				)
			);

			$this->log( sprintf( 'Subscription ID %d post_author updated.', $subscription_id ) );
		} catch ( Exception $e ) {
			$this->log( sprintf( '--- Exception caught repairing subscription %d - exception message: %s ---', $subscription_id, $e->getMessage() ) );
		}
	}


	/**
	 * Get a batch of subscriptions which need to be updated.
	 *
	 * @since 2.4.0
	 * @return array A list of subscription ids which need to be updated.
	 */
	protected function get_items_to_update() {
		global $wpdb;

		$admin_subscriptions = WCS_Customer_Store::instance()->get_users_subscription_ids( 1 );

		return get_posts( array(
			'post_type'      => 'shop_subscription',
			'posts_per_page' => 20,
			'author'         => '1',
			'post_status'    => 'any',
			'post__not_in'   => $admin_subscriptions,
			'fields'         => 'ids',
		) );
	}

	/**
	 * Schedule the instance's hook to run in $this->time_limit seconds, if it's not already scheduled.
	 */
	protected function schedule_background_update() {
		parent::schedule_background_update();

		update_option( 'wcs_subscription_post_author_upgrade_is_scheduled', true );
	}

	/**
	 * Unschedule the instance's hook in Action Scheduler
	 */
	protected function unschedule_background_updates() {
		parent::unschedule_background_updates();

		delete_option( 'wcs_subscription_post_author_upgrade_is_scheduled' );
	}

	/**
	 * Hooks into WC's 3.5 update routine to add the subscription post type to the list of post types affected by this update.
	 *
	 * @since 2.4.0
	 */
	public static function hook_into_wc_350_update() {
		add_filter( 'woocommerce_update_350_order_customer_id_post_types', array( __CLASS__, 'add_post_type_to_wc_350_update' ) );
	}

	/**
	 * Callback for the `woocommerce_update_350_order_customer_id_post_types` hook. Makes sure `shop_subscription` is
	 * included in the post types array.
	 *
	 * @param  array $post_types
	 * @return array
	 * @since  2.4.0
	 */
	public static function add_post_type_to_wc_350_update( $post_types = array() ) {
		if ( ! in_array( 'shop_subscription', $post_types ) ) {
			$post_types[] = 'shop_subscription';
		}

		return $post_types;
	}

}

