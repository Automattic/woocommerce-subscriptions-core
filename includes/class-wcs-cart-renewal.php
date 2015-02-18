<?php
/**
 * Implement renewing to a subscription via the cart.
 *
 * For manual renewals and the renewal of a subscription after a failed automatic payment, the customer must complete
 * the renewal via checkout in order to pay for the renewal. This class handles that.
 *
 * @package		WooCommerce Subscriptions
 * @subpackage	WCS_Cart_Renewal
 * @category	Class
 * @author		Prospress
 * @since		2.0
 */

class WCS_Cart_Renewal {

	/**
	 * Bootstraps the class and hooks required actions & filters.
	 *
	 * @since 2.0
	 */
	public function __construct() {
	}

}
new WCS_Cart_Renewal();
