<?php
/**
* Test related order data store.
*
* @version  2.3.0
* @category Class
* @author   Prospress
*/
class WCS_Related_Order_Test_Store extends WCS_Related_Order_Store_Cached_CPT {

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