<?php
/**
 * Mock WC Subscriptions Customer Store.
 *
 * @package WooCommerce/Tests
 */

/**
 * Class WCS_Mock_Customer_Data_Store.
 *
 * Customer data store for use in testing 'wcs_customer_store_class' filter and providing init() call counts.
 */
class WCS_Mock_Customer_Data_Store extends WCS_Customer_Store_Cached_CPT {

	/**
	 * @var int $init_call_count Record how many times this method is called.
	 */
	protected $init_call_count = 0;

	/**
	 * Count how many times this method is called.
	 *
	 * @return void
	 */
	protected function init() {
		$this->init_call_count++;
	}

	/**
	 * Find out how many times the $this->init() method was called.
	 *
	 * @return int
	 */
	public function get_init_call_count() {
		return $this->init_call_count;
	}
}
