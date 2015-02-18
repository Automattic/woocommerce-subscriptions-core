<?php
/**
 * Implement resubscribing to a subscription via the cart.
 *
 * Resubscribing is a similar process to renewal via checkout (which is why this class extends WCS_Cart_Renewal), only it:
 * - creates a new subscription with similar terms to the existing subscription, where as a renewal resumes the existing subscription
 * - is for an expired or cancelled subscription only.
 *
 * @package		WooCommerce Subscriptions
 * @subpackage	WCS_Cart_Resubscribe
 * @category	Class
 * @author		Prospress
 * @since		2.0
 */

class WCS_Cart_Resubscribe extends WCS_Cart_Renewal {

	/**
	 * Bootstraps the class and hooks required actions & filters.
	 *
	 * @since 2.0
	 */
	public function __construct() {
	}

}
new WCS_Cart_Resubscribe();
