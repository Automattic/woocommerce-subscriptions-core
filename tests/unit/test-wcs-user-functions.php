<?php

class WCS_User_Functions_Test extends WP_UnitTestCase {

	public $admin_user_id;
	public $user_id = 1;

	public function set_up() {
		parent::set_up();

		// setup a shop_manager and admin for testing
		$this->admin_user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
	}

	/**
	 * Function: wcs_make_user_active
	 *
	 */
	public function test_wcs_make_user_active() {
		// Set default subscriber role
		update_option( WC_Subscriptions_Admin::$option_prefix . '_subscriber_role', 'subscriber' );

		// Test Default subscriber role for non-admins
		$user = new WP_User( $this->user_id );
		$this->assertFalse( in_array( 'subscriber', $user->roles, true ) ); // test the user is not a subscriber

	}

	/**
	* Function: wcs_user_has_subscription()
	*
	*
	*/
	public function test_wcs_user_has_subscription() {

		// Create a test user
		$user_id1 = wp_create_user( 'susan', 'testuser', 'susan@example.com' );

		// Create a test subscription with 'pending' status
		$subscription1 = WCS_Helper_Subscription::create_subscription(
			array(
				'status'      => 'pending',
				'start_date'  => '2015-07-14 00:00:00',
				'customer_id' => $user_id1,
			)
		);

		// Test for non-existent user
		$this->assertFalse( wcs_user_has_subscription( $user_id1 + 1, '', '' ) );

		// Test for the default for status
		$this->assertTrue( wcs_user_has_subscription( $user_id1, '', '' ) );

		// Test for any status
		$this->assertTrue( wcs_user_has_subscription( $user_id1, '', 'any' ) );

		// Test for pending status
		$this->assertTrue( wcs_user_has_subscription( $user_id1, '', 'pending' ) );

		// Test for active status
		$this->assertFalse( wcs_user_has_subscription( $user_id1, '', 'active' ) );

		// Create another test subscription with 'active' status
		$subscription2 = WCS_Helper_Subscription::create_subscription(
			array(
				'status'      => 'active',
				'start_date'  => '2017-06-01 00:00:00',
				'customer_id' => $user_id1,
			)
		);

		// Test for active status
		$this->assertTrue( wcs_user_has_subscription( $user_id1, '', 'active' ) );

		// Test for array of statuses - one of them present
		$this->assertTrue( wcs_user_has_subscription( $user_id1, '', array( 'active', 'pending-cancel' ) ) );

		// Test for array of statuses - one of them present
		$this->assertTrue( wcs_user_has_subscription( $user_id1, '', array( 'active', 'pending-cancel', 'switched' ) ) );

		// Test for array of statuses - all not present
		$this->assertFalse( wcs_user_has_subscription( $user_id1, '', array( 'cancelled', 'switched', 'expired' ) ) );

		// Link first subscription to an order
		$order1 = WCS_Helper_Subscription::create_order();
		$subscription1->set_parent_id( wcs_get_objects_property( $order1, 'id' ) );

		$subscription1->save();

		// Add product to first subscription
		$product1 = WCS_Helper_Product::create_simple_subscription_product();
		WCS_Helper_Subscription::add_product( $order1, $product1 );
		WCS_Helper_Subscription::add_product( $subscription1, $product1 );
		$product_id1 = $product1->get_id();

		// Link second subscription to an order
		$order2 = WCS_Helper_Subscription::create_order();
		$subscription2->set_parent_id( wcs_get_objects_property( $order2, 'id' ) );

		$subscription2->save();

		// Add product to second subscription
		$product2 = WCS_Helper_Product::create_simple_subscription_product();
		WCS_Helper_Subscription::add_product( $order2, $product2 );
		WCS_Helper_Subscription::add_product( $subscription2, $product2 );
		$product_id2 = $product2->get_id();

		// Create a second test user
		$user_id2 = wp_create_user( 'daisy', 'testuser', 'daisy@example.com' );

		// Test for user with no subscription + product and default status
		$this->assertFalse( wcs_user_has_subscription( $user_id2, $product1, '' ) );

		// Create a third test subscription with 'expired' status
		$subscription3 = WCS_Helper_Subscription::create_subscription(
			array(
				'status'      => 'expired',
				'start_date'  => '2017-06-01 00:00:00',
				'customer_id' => $user_id2,
			)
		);

		// Link third subscription to an order
		$order3 = WCS_Helper_Subscription::create_order();
		$subscription3->set_parent_id( wcs_get_objects_property( $order3, 'id' ) );

		$subscription3->save();

		// Add second product to third subscription
		WCS_Helper_Subscription::add_product( $order3, $product2 );
		WCS_Helper_Subscription::add_product( $subscription3, $product2 );

		// Test for user + product and the default status
		$this->assertTrue( wcs_user_has_subscription( $user_id1, $product_id1, '' ) );

		// Test for user + product and any status
		$this->assertTrue( wcs_user_has_subscription( $user_id1, $product_id1, 'any' ) );

		// Test for user + product and specific status - all present
		$this->assertTrue( wcs_user_has_subscription( $user_id1, $product_id2, 'active' ) );

		// Test for user + product and specific status not present
		$this->assertFalse( wcs_user_has_subscription( $user_id1, $product_id2, 'cancelled' ) );

		// Test for user + product and specific status - status present but not for product
		$this->assertFalse( wcs_user_has_subscription( $user_id1, $product_id2, 'pending' ) );

		// Test for user + product and specific status - status not present for product
		$this->assertFalse( wcs_user_has_subscription( $user_id1, $product_id1, array( 'expired', 'cancelled' ) ) );

		// Test for user + product and specific status - status not present for product
		$this->assertTrue( wcs_user_has_subscription( $user_id1, $product_id2, array( 'expired', 'active' ) ) );

		// Test for user + product and specific status - status present for product
		$this->assertTrue( wcs_user_has_subscription( $user_id2, $product_id2, array( 'expired', 'active' ) ) );

		// Test for user + product and specific status - status not present for product
		$this->assertFalse( wcs_user_has_subscription( $user_id2, $product_id2, array( 'pending', 'active' ) ) );
	}
}
