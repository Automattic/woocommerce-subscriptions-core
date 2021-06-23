<?php

class WCS_Switch_Functions_Unit_Tests extends WCS_Unit_Test_Case {

	/**
	 * Make sure a specific product type is switchable or un-switchable.
	 *
	 * @param $product_type The product type arg to pass to @see wcs_is_product_switchable_type().
	 * @param $expected_results The expected result for each _allow_switching option. Optional default is all expected results are false.
	 * @group wcs-switch-functions
	 * @dataProvider wcs_is_product_switchable_type_provider
	 */
	public function test_wcs_is_product_switchable_type( $product_type, $expected_results = array() ) {
		// By default all the expected results are false unless specified.
		$expected_results = wp_parse_args( $expected_results, array(
			'no'               => false,
			'nope'             => false,
			'variable'         => false,
			'grouped'          => false,
			'variable_grouped' => false,
		) );

		// Create the product
		switch ( $product_type ) {
			case 'simple':
			case 'grouped':
				$product = call_user_func( array( 'WCS_Helper_Product', "create_{$product_type}_subscription_product" ) );
				break;
			case 'variable':
			case 'variation':
				$product = WCS_Helper_Product::create_variable_subscription_product( $product_type );
				break;
			default:
				$product = $product_type;
				break;
		}

		foreach ( $expected_results as $switch_setting => $expected ) {
			update_option( WC_Subscriptions_Admin::$option_prefix . '_allow_switching', $switch_setting );
			$this->assertEquals( $expected, wcs_is_product_switchable_type( $product ) );
		}
	}

	/**
	 * A data provider for @see test_wcs_is_product_switchable_type().
	 *
	 * @return array
	 */
	public function wcs_is_product_switchable_type_provider() {
		return array(
			array( 'simple' ), // simple products on their own are not switchable.
			array( 12342 ),    // invalid non-product arg
			array( 'grouped', array(
				'grouped'          => true,
				'variable_grouped' => true,
			) ),
			array( 'variable', array(
				'variable'         => true,
				'variable_grouped' => true,
			) ),
			array( 'variation', array(
				'variable'         => true,
				'variable_grouped' => true,
			) ),
		);
	}

	/**
	 * A data provider for @see test_wcs_is_product_switchable_with_unaccessible_parents().
	 *
	 * @return array
	 */
	public function wcs_is_product_switchable_with_unaccessible_parents_provider() {
		return array(
			// Trashed parents
			array( 'grouped', 'trash' ),
			array( 'variable', 'trash' ),
			array( 'variation', 'trash' ),

			// Deleted parents
			array( 'grouped', 'delete' ),
			array( 'variable', 'delete' ),
			array( 'variation', 'delete' ),

			// Private (unaccessible) parents
			array( 'grouped', 'make-private' ),
			array( 'variable', 'make-private' ),
			array( 'variation', 'make-private' ),

			// Unpublished (unaccessible) parents
			array( 'grouped', 'unpublish' ),
			array( 'variable', 'unpublish' ),
			array( 'variation', 'unpublish' ),
		);
	}

	/**
	 * Make sure a specific product type in not switchable after trashing, deleting, privatising, un-publishing it's parents.
	 *
	 * @param $product_type The product type arg to pass to @see wcs_is_product_switchable_type().
	 * @param $expected_results The expected result for each _allow_switching option. Optional default is all expected results are false.
	 * @group wcs-switch-functions
	 * @dataProvider wcs_is_product_switchable_with_unaccessible_parents_provider
	 */
	public function test_wcs_is_product_switchable_with_unaccessible_parents( $product_type, $action ) {
		// Create the product
		switch ( $product_type ) {
			case 'simple':
			case 'grouped':
				$product = call_user_func( array( 'WCS_Helper_Product', "create_{$product_type}_subscription_product" ) );
				break;
			case 'variable':
			case 'variation':
				$product = WCS_Helper_Product::create_variable_subscription_product( $product_type );
				break;
		}

		// For variable subscription product types, the product is the parent.
		if ( $product->get_type() === 'variable-subscription' ) {
			$parent = $product->get_id();
		} else {
			$parent = wcs_is_woocommerce_pre( '3.0' ) && $product->get_type() !== 'subscription_variation' ? $product->get_parent() : $product->get_parent_id();
		}

		$parents = wp_parse_id_list( array_merge( WC_Subscriptions_Product::get_parent_ids( $product ), array( $parent ) ) );

		foreach ( $parents as $product_id ) {
			switch ( $action ) {
				case 'trash':
					wp_delete_post( $product_id );
					break;
				case 'delete':
					wp_delete_post( $product_id, true );
					break;
				case 'make-private':
					wp_update_post( array( 'ID' => $product_id, 'post_status' => 'private' ) );
					break;
				case 'unpublish':
					wp_update_post( array( 'ID' => $product_id, 'post_status' => 'draft' ) );
					break;
			}
		}

		foreach ( array( 'no', 'variable', 'grouped', 'variable_grouped' ) as $switch_setting ) {
			update_option( WC_Subscriptions_Admin::$option_prefix . '_allow_switching', $switch_setting );
			$this->assertFalse( wcs_is_product_switchable_type( $product->get_id() ) );
		}
	}
}
