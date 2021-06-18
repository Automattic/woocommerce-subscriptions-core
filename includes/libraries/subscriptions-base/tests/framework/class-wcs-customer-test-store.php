<?php
/**
 * Customer data store for use in testing 'wcs_customer_store_class' filter
 * and providing init() call counts.
 *
 * @version  2.3.0
 * @category Class
 * @author   Prospress
 */
class WCS_Customer_Test_Store extends WCS_Customer_Store_Cached_CPT {

	/**
	 * Record how many times this method is called.
	 */
	protected $init_call_count = 0;

	/**
	 * Count how many times this method is called.
	 */
	protected function init() {
		$this->init_call_count++;
	}

	/**
	 * Find out how many times the $this->init() method was called.
	 */
	public function get_init_call_count() {
		return $this->init_call_count;
	}
}