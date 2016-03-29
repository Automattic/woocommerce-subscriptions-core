<?php
/**
 * Setup the rules for retrying failed automatic renewal payments and provide methods for working with them.
 *
 * @package		WooCommerce Subscriptions
 * @subpackage	WCS_Retry_Rules
 * @category	Class
 * @author		Prospress
 * @since		2.1
 */

class WCS_Retry_Rules {

	/* the class used to instantiate an individual retry rule */
	protected $retry_rule_class;

	/* the rules that control the retry schedule and behaviour of each retry */
	protected $retry_rule_data = array();

	/**
	 * Set up the retry rules
	 *
	 * @since 2.1
	 */
	public function __construct() {

		$this->retry_rule_class = apply_filters( 'wcs_retry_rule_class', 'WCS_Retry_Rule' );

		$this->retry_rule_data = apply_filters( 'woocommerce_subscriptions_retry_rules', array(
			array(
				'retry_after_interval'            => DAY_IN_SECONDS / 2, // how long to wait before retrying
				'email_template_customer'         => '', // don't bother the customer yet
				'email_template_admin'            => 'WCS_Email_Payment_Retry',
				'status_to_apply_to_order'        => 'pending',
				'status_to_apply_to_subscription' => 'on-hold',
			),
			array(
				'retry_after_interval'            => DAY_IN_SECONDS / 2,
				'email_template_customer'         => 'WCS_Email_Customer_Payment_Retry',
				'email_template_admin'            => 'WCS_Email_Payment_Retry',
				'status_to_apply_to_order'        => 'pending',
				'status_to_apply_to_subscription' => 'on-hold',
			),
			array(
				'retry_after_interval'            => DAY_IN_SECONDS,
				'email_template_customer'         => '', // avoid spamming the customer by not sending them an email this time either
				'email_template_admin'            => 'WCS_Email_Payment_Retry',
				'status_to_apply_to_order'        => 'pending',
				'status_to_apply_to_subscription' => 'on-hold',
			),
			array(
				'retry_after_interval'            => DAY_IN_SECONDS * 2,
				'email_template_customer'         => 'WCS_Email_Customer_Payment_Retry',
				'email_template_admin'            => 'WCS_Email_Payment_Retry',
				'status_to_apply_to_order'        => 'pending',
				'status_to_apply_to_subscription' => 'on-hold',
			),
			array(
				'retry_after_interval'            => DAY_IN_SECONDS * 3,
				'email_template_customer'         => 'WCS_Email_Customer_Payment_Retry',
				'email_template_admin'            => 'WCS_Email_Payment_Retry',
				'status_to_apply_to_order'        => 'pending',
				'status_to_apply_to_subscription' => 'on-hold',
			),
		) );
	}

	/**
	 * Check if a retry rule exists for a certain stage of the retry process.
	 *
	 * @since 2.1
	 */
	public function has_rule( $retry_number ) {
		return ( isset( $this->retry_rule_data[ $retry_number ] ) ) ? true : false;;
	}

	/**
	 * Get
	 *
	 * @since 2.1
	 */
	public function get_rule( $retry_number ) {

		if ( $this->has_rule( $retry_number ) ) {
			$rule = new $this->retry_rule_class( $this->retry_rule_data[ $retry_number ] );
		} else {
			$rule = array();
		}

		return $rule;
	}

	/**
	 * Get
	 *
	 * @since 2.1
	 */
	public function get_rule_class() {
		return $this->retry_rule_class;
	}
}
