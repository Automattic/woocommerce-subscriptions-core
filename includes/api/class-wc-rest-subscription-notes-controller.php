<?php
/**
 * REST API subscription notes controller
 *
 * Handles requests to the /subscription/<id>/notes endpoint.
 *
 * @author   Prospres, Inc.
 * @since    2.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * REST API Subscription Notes controller class.
 *
 */
class WC_REST_Subscription_Notes_Controller extends WC_REST_Order_Notes_Controller {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'subscriptions/(?P<order_id>[\d]+)/notes';

	/**
	 * Post type.
	 *
	 * @var string
	 */
	protected $post_type = 'shop_subscription';

}