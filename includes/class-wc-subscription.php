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

	/** @protected WC_Order Stores order data for the order in which the subscription was purchased (if any) */
	protected $order;

	/** @protected Object Stores an instance of the WC_Payment_Gateway used to process recurring payments (if any) */
	protected $payment_gateway = null;

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

	/**
	 * __get function.
	 *
	 * @param mixed $key
	 * @return mixed
	 */
	public function __get( $key ) {

		if ( in_array( $key, array( 'start_date', 'trial_end_date', 'next_payment_date', 'end_date', 'last_payment_date' ) ) ) {

			$value = $this->get_date( $key );

		} elseif ( 'order' == $key ) {

			if ( $this->post->post_parent > 0 ) {
				$this->order = wc_get_order( $this->post->post_parent );
			} else {
				$this->order = null;
			}

		} elseif ( 'payment_gateway' == $key ) {

			// Only set the payment gateway once and only when we first need it
			if ( ! isset( $this->payment_gateway ) ) {
				$payment_gateways = WC()->payment_gateways->payment_gateways();
				$this->payment_gateway = isset( $payment_gateways[ $this->payment_method ] ) ? $payment_gateways[ $this->payment_method ] : null;
			}

			$value = $this->payment_gateway;

		} else {

			$value = parent::__get( $key );

		}

		return $value;
	}

}
