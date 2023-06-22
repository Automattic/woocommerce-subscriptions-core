<?php
/**
 *
 */
class WCS_Deprecated_Functions_Test extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		add_filter( 'woocommerce_order_item_get_subtotal', array( $this, 'return_0_if_empty' ) );
	}

	public function tear_down() {
		global $wpdb;

		remove_action( 'before_delete_post', 'WC_Subscriptions_Manager::maybe_cancel_subscription' );
		_delete_all_posts();

		// Delete line items
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}woocommerce_order_items" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}woocommerce_order_itemmeta" );

		$this->commit_transaction();
		parent::tear_down();
		add_action( 'before_delete_post', 'WC_Subscriptions_Manager::maybe_cancel_subscription', 10, 1 );
	}

	/**
	 * includes/wcs-deprecated-functions.php
	 */
	public function test_wcs_get_old_subscription_key() {
		$subscription = WCS_Helper_Subscription::create_subscription( array( 'status' => 'active' ) );

		$product = WCS_Helper_Product::create_simple_subscription_product();

		WCS_Helper_Subscription::add_product( $subscription, $product );
		$subscription->save();

		$key_should_be = $subscription->get_id() . '_' . $product->get_id();

		wcs_get_old_subscription_key( $subscription );

		$this->assertEquals( $key_should_be, wcs_get_old_subscription_key( $subscription ) );
	}

	public function wcs_get_singular_garbage_datas() {
		return array(
			array( false ),
			array( true ),
			array( null ),
			array( -1 ),
			array( new WP_Error( 'foo' ) ),
			array( 'foo' ),
			array( '' ),
			array( array( 4 ) ),
			array( new stdClass() ),
		);
	}

	/**
	 * includes/wcs_deprecated-functions.php
	 */
	public function test_wcs_get_subscription_id_from_key() {
		if ( wcs_is_custom_order_tables_usage_enabled() ) {
			$this->markTestSkipped( 'Deprecated function wcs_get_subscription_from_key does not work with HPOS enabled.' );
		}
		$product = WCS_Helper_Product::create_simple_subscription_product();

		$order = WCS_Helper_Subscription::create_order();
		WCS_Helper_Subscription::add_product( $order, $product );

		$subscription = WCS_Helper_Subscription::create_subscription(
			array(
				'status'   => 'active',
				'order_id' => wcs_get_objects_property( $order, 'id' ),
			)
		);
		WCS_Helper_Subscription::add_product( $subscription, $product );

		$subscription_key = wcs_get_objects_property( $order, 'id' ) . '_' . $product->get_id();

		$broken_subscription_key = wcs_get_objects_property( $order, 'id' );

		$this->assertEquals( $subscription->get_id(), wcs_get_subscription_id_from_key( $subscription_key ) );
		$this->assertEquals( $subscription->get_id(), wcs_get_subscription_id_from_key( $broken_subscription_key ) );
	}

	/**
	 * @dataProvider wcs_get_singular_garbage_datas
	 */
	public function test_wcs_get_subscription_id_from_key_fail( $input ) {
		$this->assertNull( wcs_get_subscription_id_from_key( $input ) );
	}


	/**
	 * Pretty much the same setup as the id from key
	 *
	 * includes/wcs_deprecated-functions.php
	 */
	public function test_wcs_get_subscription_from_key() {
		if ( wcs_is_custom_order_tables_usage_enabled() ) {
			$this->markTestSkipped( 'Deprecated function wcs_get_subscription_from_key does not work with HPOS enabled.' );
		}
		$product = WCS_Helper_Product::create_simple_subscription_product();

		$order = WCS_Helper_Subscription::create_order();
		WCS_Helper_Subscription::add_product( $order, $product );

		if ( is_callable( array( $order, 'save' ) ) ) {
			$order->save();
		}

		$subscription = WCS_Helper_Subscription::create_subscription(
			array(
				'status'   => 'active',
				'order_id' => wcs_get_objects_property( $order, 'id' ),
			)
		);
		WCS_Helper_Subscription::add_product( $subscription, $product );
		$subscription->save();
		$subscription = wcs_get_subscription( $subscription->get_id() );

		$subscription_key = wcs_get_objects_property( $order, 'id' ) . '_' . $product->get_id();

		$broken_subscription_key = wcs_get_objects_property( $order, 'id' );

		$this->assertEquals( $subscription, wcs_get_subscription_from_key( $subscription_key ) );
		$this->assertEquals( $subscription, wcs_get_subscription_from_key( $broken_subscription_key ) );
	}

	/**
	 * @dataProvider wcs_get_singular_garbage_datas
	 */
	public function test_wcs_get_subscription_from_key_fail( $input ) {
		// Warnings are issued in php8+ as is_object() is called on an undefined variable.
		$this->setExpectedException( '\PHPUnit\Framework\Error\Notice' );
		if ( PHP_MAJOR_VERSION >= 8 ) {
			$this->setExpectedException( '\PHPUnit\Framework\Error\Warning' );
		}
		$this->assertNull( wcs_get_subscription_from_key( $input ) );
	}

	/**
	 * includes/wcs_deprecated-functions.php
	 */
	public function test_wcs_get_subscription_in_deprecated_structure() {
		$product = WCS_Helper_Product::create_simple_subscription_product( array( 'price' => 10 ) );

		$order = WCS_Helper_Subscription::create_order();
		WCS_Helper_Subscription::add_product( $order, $product );

		$subscription = WCS_Helper_Subscription::create_subscription(
			array(
				'status'   => 'active',
				'order_id' => wcs_get_objects_property( $order, 'id' ),
			)
		);
		WCS_Helper_Subscription::add_product( $subscription, $product );

		$subscription->payment_complete();

		$deprecated_subscription = wcs_get_subscription_in_deprecated_structure( $subscription );

		$this->assertArrayHasKey( 'order_id', $deprecated_subscription );
		$this->assertEquals( wcs_get_objects_property( $order, 'id' ), $deprecated_subscription['order_id'] );

		$this->assertArrayHasKey( 'product_id', $deprecated_subscription );
		$this->assertEquals( $product->get_id(), $deprecated_subscription['product_id'] );

		$this->assertArrayHasKey( 'variation_id', $deprecated_subscription );
		$this->assertEquals( 0, $deprecated_subscription['variation_id'] );

		$this->assertArrayHasKey( 'status', $deprecated_subscription );
		$this->assertEquals( 'active', $deprecated_subscription['status'] );

		$this->assertArrayHasKey( 'period', $deprecated_subscription );
		$this->assertEquals( 'month', $deprecated_subscription['period'] );

		$this->assertArrayHasKey( 'interval', $deprecated_subscription );
		$this->assertEquals( '1', $deprecated_subscription['interval'] );

		$this->assertArrayHasKey( 'length', $deprecated_subscription );
		$this->assertEquals( 0, $deprecated_subscription['length'] );

		$this->assertArrayHasKey( 'expiry_date', $deprecated_subscription );
		$this->assertEquals( '0', $deprecated_subscription['expiry_date'] );

		$this->assertArrayHasKey( 'end_date', $deprecated_subscription );
		$this->assertEquals( '0', $deprecated_subscription['end_date'] );

		$this->assertArrayHasKey( 'failed_payments', $deprecated_subscription );
		$this->assertEquals( 0, $deprecated_subscription['failed_payments'] );

		$this->assertArrayHasKey( 'completed_payments', $deprecated_subscription );
		$this->assertCount( 1, $deprecated_subscription['completed_payments'] );

		$this->assertArrayHasKey( 'suspension_count', $deprecated_subscription );
		$this->assertEquals( '0', $deprecated_subscription['suspension_count'] );

		$this->assertArrayHasKey( 'last_payment_date', $deprecated_subscription );
		$this->assertEquals( $deprecated_subscription['completed_payments'][0], $deprecated_subscription['last_payment_date'] );
	}

	/**
	 * if the $value is empty, it returns 0.
	 *
	 * @param mixed $value
	 *
	 * @return mixed|int
	 */
	public function return_0_if_empty( $value ) {
		if ( empty( $value ) ) {
			return 0;
		}

		return $value;
	}
}
