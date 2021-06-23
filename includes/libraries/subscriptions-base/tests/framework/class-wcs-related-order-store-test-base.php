<?php

/**
 * Share data providers across different WCS_Related_Order_Store test classes
 */
class WCS_Related_Order_Store_Test_Base extends WCS_Unit_Test_Case {

	private $relation_types = array(
		'renewal',
		'switch',
		'resubscribe',
	);

	/**
	 * Pass individual relation types to callbacks
	 *
	 * @return array
	 */
	public function provider_relation_type() {

		$relation_types_as_separate_params = array();

		foreach( $this->relation_types as $relation_type ) {
			$relation_types_as_separate_params[] = array( $relation_type );
		}

		return $relation_types_as_separate_params;
	}

	/**
	 * Get an array of all relation types
	 *
	 * @return array
	 */
	public function provider_relation_types() {
		return array(
			array(
				$this->relation_types,
			),
		);
	}

	/**
	 * @return string
	 */
	protected function get_meta_key( $relation_type ) {
		return sprintf( '_subscription_%s', $relation_type );
	}

	/**
	 * WCS_Related_Order_Store is a singleton that only instantiates itself once. We want to be able
	 * to test different instantiation scenarios, so we can use some reflection black magic to set
	 * the WCS_Related_Order_Store::$instance property to null, forcing it to be instantiated again.
	 */
	protected function clear_related_order_store_instance() {
		$reflected_related_order_store = new ReflectionClass( 'WCS_Related_Order_Store' );
		$reflected_instance_property   = $reflected_related_order_store->getProperty( 'instance' );
		$reflected_instance_property->setAccessible( true );
		$reflected_instance_property->setValue( null, null );
	}
}
