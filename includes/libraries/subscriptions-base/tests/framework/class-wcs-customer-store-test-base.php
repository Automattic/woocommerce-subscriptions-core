<?php

/**
 * Share some properties across different WCS_Customer_Store test classes
 */
class WCS_Customer_Store_Test_Base extends WCS_Unit_Test_Case {

	/**
	 * @var int user ID set as the customer on mock subscriptions.
	 */
	protected $customer_id = 1123;

	/**
	 * @var int user ID set as the customer on mock subscriptions.
	 */
	protected $customer_id_without_subscriptions = 11235;

	/**
	 * WCS_Customer_Store is a singleton that only instantiates itself once. We want to be able
	 * to test different instantiation scenarios, so we can use some reflection black magic to set
	 * the WCS_Customer_Store::$instance property to null, forcing it to be instantiated again.
	 */
	protected function clear_instance() {
		$this->clear_singleton_instance( 'WCS_Customer_Store' );
	}

}
