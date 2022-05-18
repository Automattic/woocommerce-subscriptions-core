<?php
/**
 * Mock WC Subscriptions Relater Order Store.
 *
 * @package WooCommerce/Tests
 */

/**
 * Class WCS_Mock_Related_Order_Store.
 *
 * Related order data store for use in testing.
 */
class WCS_Mock_Related_Order_Store extends WCS_Related_Order_Data_Store_Cached {

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
