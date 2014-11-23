<?php
/**
 * Subscription Object
 *
 * Extends WC_Order because the Edit Order/Subscription interface requires some of the refund related methods
 * from WC_Order that don't exist in WC_Abstract_Order (which would seem the more appropriate choice)
 *
 * @class    WC_Subscription
 * @version  2.0
 * @package  WooCommerce Subscriptions/Classes
 * @category Class
 * @author   Brent Shepherd
 */

class WC_Subscription extends WC_Order {

	/**
	 * Initialize the subscription object.
	 *
	 * @param int|WC_Subscription $order
	 */
	public function __construct( $subscription ) {

		$this->order_type = 'shop_subscription';

		parent::__construct( $subscription );
	}

	/**
	 * Populates a subsciption from the loaded post data.
	 *
	 * @param mixed $result
	 */
	public function populate( $result ) {
		parent::populate( $result );
	}

}
