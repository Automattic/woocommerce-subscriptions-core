<?php
/**
 * WC Subscriptions Related Store Base Test.
 *
 * @package WooCommerce/Tests
 */

/**
 * Class WCS_Base_Related_Order_Store_Test_Case.
 * 
 * Share data providers across different WCS_Related_Order_Store test classes.
 */
class WCS_Base_Related_Order_Store_Test_Case extends WP_UnitTestCase {

	/**
	 * @var array $relation_types List of order relationship types.
	 */
	private $relation_types = [
		'renewal',
		'switch',
		'resubscribe',
	];

	/**
	 * Pass individual relation types to callbacks.
	 *
	 * @return array
	 */
	public function provider_relation_type() {
		$relation_types_as_separate_params = [];

		foreach( $this->relation_types as $relation_type ) {
			$relation_types_as_separate_params[] = [ $relation_type ];
		}

		return $relation_types_as_separate_params;
	}

	/**
	 * Get an array of all relation types.
	 *
	 * @return array
	 */
	public function provider_relation_types() {
		return [ [ $this->relation_types ] ];
	}

	/**
	 * Get the subscriptions post meta key for the given order relationship type.
	 *
	 * @param string $relation_type
	 *
	 * @return string
	 */
	protected function get_meta_key( $relation_type ) {
		return sprintf( '_subscription_%s', $relation_type );
	}

	/**
	 * WCS_Related_Order_Store is a singleton that only instantiates itself once. We want to be able
	 * to test different instantiation scenarios, so we can use some reflection black magic to set
	 * the WCS_Related_Order_Store::$instance property to null, forcing it to be instantiated again.
	 *
	 * @return void
	 */
	protected function clear_related_order_store_instance() {
		$reflected_related_order_store = new ReflectionClass( 'WCS_Related_Order_Store' );
		$reflected_instance_property   = $reflected_related_order_store->getProperty( 'instance' );
		$reflected_instance_property->setAccessible( true );
		$reflected_instance_property->setValue( null, null );
	}
}
