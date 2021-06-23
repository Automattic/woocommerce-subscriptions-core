<?php
/**
 * WC Subscription Unit Test Factory
 *
 * Provides WC Subscription specific factories
 *
 * @since 2.2
 */
class WC_Subscription_Unit_Test_Factory extends WC_Unit_Test_Factory {

	/** @var \WC_Unit_Test_Factory_For_Webhook */
	public $webhook;

	/** @var \WC_Unit_Test_Factory_For_Webhook_Delivery */
	public $webhook_delivery;

	/**
	 * Setup factories
	 */
	public function __construct() {

		parent::__construct();

	}

}