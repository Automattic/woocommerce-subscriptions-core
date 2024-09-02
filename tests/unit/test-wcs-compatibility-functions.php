<?php

class WCS_Compatibility_Functions_Test extends WP_UnitTestCase {
	/**
	 * @covers ::wcs_get_objects_property
	 */
	public function test_wcs_get_objects_property() {
		$subscription      = WCS_Helper_Subscription::create_subscription();
		$order             = WC_Helper_Order::create_order();
		$simple_product    = WC_Helper_Product::create_simple_product();
		$variation_product = WC_Helper_Product::create_variation_product()->get_children();
		$variation_product = wc_get_product( $variation_product[0] );

		// name.
		$this->assertNull( wcs_get_objects_property( $order, 'name' ) );
		$this->assertNull( wcs_get_objects_property( $subscription, 'name' ) );
		$this->assertEquals( $simple_product->get_name(), wcs_get_objects_property( $simple_product, 'name' ) );
		$this->assertEquals( $variation_product->get_name(), wcs_get_objects_property( $variation_product, 'name' ) );

		// post.
		$this->assertEquals( get_post( $subscription->get_id() ), wcs_get_objects_property( $subscription, 'post' ) );
		$this->assertEquals( get_post( $order->get_id() ), wcs_get_objects_property( $order, 'post' ) );
		$this->assertEquals( get_post( $simple_product->get_id() ), wcs_get_objects_property( $simple_product, 'post' ) );
		$this->assertEquals( get_post( $variation_product->get_parent_id() ), wcs_get_objects_property( $variation_product, 'post' ) );

		// post_status
		$this->assertEquals( get_post( $subscription->get_id() )->post_status, wcs_get_objects_property( $subscription, 'post_status' ) );
		$this->assertEquals( get_post( $order->get_id() )->post_status, wcs_get_objects_property( $order, 'post_status' ) );
		$this->assertEquals( get_post( $simple_product->get_id() )->post_status, wcs_get_objects_property( $simple_product, 'post_status' ) );
		$this->assertEquals( get_post( $variation_product->get_parent_id() )->post_status, wcs_get_objects_property( $variation_product, 'post_status' ) );

		// status.
		$this->assertEquals( $subscription->get_status(), wcs_get_objects_property( $subscription, 'status' ) );
		$this->assertEquals( $order->get_status(), wcs_get_objects_property( $order, 'status' ) );
		$this->assertEquals( $simple_product->get_status(), wcs_get_objects_property( $simple_product, 'status' ) );
		$this->assertEquals( $variation_product->get_status(), wcs_get_objects_property( $variation_product, 'status' ) );

		// parent_id.
		$this->assertEquals( $subscription->get_parent_id(), wcs_get_objects_property( $subscription, 'parent_id' ) );
		$this->assertEquals( $order->get_parent_id(), wcs_get_objects_property( $order, 'parent_id' ) );
		$this->assertEquals( $simple_product->get_parent_id(), wcs_get_objects_property( $simple_product, 'parent_id' ) );
		$this->assertEquals( $variation_product->get_parent_id(), wcs_get_objects_property( $variation_product, 'parent_id' ) );

		// variation_data.
		$this->assertEquals( wc_get_product_variation_attributes( $subscription->get_id() ), wcs_get_objects_property( $subscription, 'variation_data' ) );
		$this->assertEquals( wc_get_product_variation_attributes( $order->get_id() ), wcs_get_objects_property( $order, 'variation_data' ) );
		$this->assertEquals( wc_get_product_variation_attributes( $simple_product->get_id() ), wcs_get_objects_property( $simple_product, 'variation_data' ) );
		$this->assertEquals( wc_get_product_variation_attributes( $variation_product->get_id() ), wcs_get_objects_property( $variation_product, 'variation_data' ) );

		// downloads.
		$this->assertNull( wcs_get_objects_property( $subscription, 'downloads' ) );
		$this->assertNull( wcs_get_objects_property( $order, 'downloads' ) );
		$this->assertEquals( $simple_product->get_downloads(), wcs_get_objects_property( $simple_product, 'downloads' ) );
		$this->assertEquals( $variation_product->get_downloads(), wcs_get_objects_property( $variation_product, 'downloads' ) );

		// order_version | version.
		$this->assertEquals( $subscription->get_version(), wcs_get_objects_property( $subscription, 'order_version' ) );
		$this->assertEquals( $subscription->get_version(), wcs_get_objects_property( $subscription, 'version' ) );
		$this->assertEquals( $order->get_version(), wcs_get_objects_property( $order, 'order_version' ) );
		$this->assertEquals( $order->get_version(), wcs_get_objects_property( $order, 'version' ) );
		$this->assertNull( wcs_get_objects_property( $simple_product, 'order_version' ) );
		$this->assertNull( wcs_get_objects_property( $simple_product, 'version' ) );
		$this->assertNull( wcs_get_objects_property( $variation_product, 'order_version' ) );
		$this->assertNull( wcs_get_objects_property( $variation_product, 'version' ) );

		// order_currency | currency.
		$this->assertEquals( $subscription->get_currency(), wcs_get_objects_property( $subscription, 'order_currency' ) );
		$this->assertEquals( $subscription->get_currency(), wcs_get_objects_property( $subscription, 'currency' ) );
		$this->assertEquals( $order->get_currency(), wcs_get_objects_property( $order, 'order_currency' ) );
		$this->assertEquals( $order->get_currency(), wcs_get_objects_property( $order, 'currency' ) );
		$this->assertNull( wcs_get_objects_property( $simple_product, 'order_currency' ) );
		$this->assertNull( wcs_get_objects_property( $simple_product, 'currency' ) );
		$this->assertNull( wcs_get_objects_property( $variation_product, 'order_currency' ) );
		$this->assertNull( wcs_get_objects_property( $variation_product, 'currency' ) );

		// date_created | order_date | date.
		$this->assertEquals( $subscription->get_date_created(), wcs_get_objects_property( $subscription, 'date_created' ) );
		$this->assertEquals( $subscription->get_date_created(), wcs_get_objects_property( $subscription, 'order_date' ) );
		$this->assertEquals( $subscription->get_date_created(), wcs_get_objects_property( $subscription, 'date' ) );
		$this->assertEquals( $order->get_date_created(), wcs_get_objects_property( $order, 'date_created' ) );
		$this->assertEquals( $order->get_date_created(), wcs_get_objects_property( $order, 'order_date' ) );
		$this->assertEquals( $order->get_date_created(), wcs_get_objects_property( $order, 'date' ) );
		$this->assertEquals( $simple_product->get_date_created(), wcs_get_objects_property( $simple_product, 'date_created' ) );
		$this->assertEquals( $simple_product->get_date_created(), wcs_get_objects_property( $simple_product, 'order_date' ) );
		$this->assertEquals( $simple_product->get_date_created(), wcs_get_objects_property( $simple_product, 'date' ) );
		$this->assertEquals( $variation_product->get_date_created(), wcs_get_objects_property( $variation_product, 'date_created' ) );
		$this->assertEquals( $variation_product->get_date_created(), wcs_get_objects_property( $variation_product, 'order_date' ) );
		$this->assertEquals( $variation_product->get_date_created(), wcs_get_objects_property( $variation_product, 'date' ) );

		// date_paid.
		$this->assertEquals( $subscription->get_date_paid(), wcs_get_objects_property( $subscription, 'date_paid' ) );
		$this->assertEquals( $order->get_date_paid(), wcs_get_objects_property( $order, 'date_paid' ) );
		$this->assertNull( wcs_get_objects_property( $simple_product, 'date_paid' ) );
		$this->assertNull( wcs_get_objects_property( $variation_product, 'date_paid' ) );

		// cart_discount.
		$this->assertEquals( $subscription->get_total_discount(), wcs_get_objects_property( $subscription, 'cart_discount' ) );
		$this->assertEquals( $order->get_total_discount(), wcs_get_objects_property( $order, 'cart_discount' ) );
		$this->assertNull( wcs_get_objects_property( $simple_product, 'cart_discount' ) );
		$this->assertNull( wcs_get_objects_property( $variation_product, 'cart_discount' ) );

		// default.
		$this->assertEquals( $subscription->get_type(), wcs_get_objects_property( $subscription, 'type' ) );
		$this->assertNull( wcs_get_objects_property( $subscription, 'FAKE_PROPERTY' ) );
		$this->assertEquals( $order->get_changes(), wcs_get_objects_property( $order, 'changes' ) );
		$this->assertEquals( $order->get_changes(), wcs_get_objects_property( $order, 'changes' ) );
		$this->assertEquals( $simple_product->get_slug(), wcs_get_objects_property( $simple_product, 'slug' ) );
		$this->assertEquals( $variation_product->get_date_modified(), wcs_get_objects_property( $variation_product, 'date_modified' ) );

		// Invalid/non-object input
		$this->assertNull( wcs_get_objects_property( false, 'FAKE_PROPERTY' ) );
		$this->assertNull( wcs_get_objects_property( '', 'name' ) );
		$this->assertNull( wcs_get_objects_property( null, 'ID' ) );
	}

	public function test_wcs_set_objects_property() {
		$simple_product      = WC_Helper_Product::create_simple_product();
		$meta_key            = uniqid( 'meta_key_' );
		$original_name       = 'Original Name';
		$original_meta_value = 'original_meta_value';
		$updated_name        = 'Test Product';
		$updated_meta_value  = 'updated_meta_value';

		$simple_product->set_name( $original_name );
		$simple_product->update_meta_data( $meta_key, $original_meta_value );
		$simple_product->save();

		// property without saving
		wcs_set_objects_property( $simple_product, 'name', $updated_name, 'no save' );
		$this->assertEquals( $updated_name, $simple_product->get_name() );

		// meta without saving
		wcs_set_objects_property( $simple_product, $meta_key, $updated_meta_value, 'no save', '', 'no prefix' );
		$this->assertEquals( $updated_meta_value, $simple_product->get_meta( $meta_key ) );

		// verify reloaded object has original values
		$reloaded_product = wc_get_product( $simple_product->get_id() );
		$this->assertEquals( $original_name, $reloaded_product->get_name() );
		$this->assertEquals( $original_meta_value, $reloaded_product->get_meta( $meta_key ) );

		$simple_product->set_name( $original_name );
		$simple_product->update_meta_data( $meta_key, $original_meta_value );

		// property with saving
		wcs_set_objects_property( $simple_product, 'name', $updated_name );
		$this->assertEquals( $updated_name, $simple_product->get_name() );

		// meta with saving
		wcs_set_objects_property( $simple_product, $meta_key, $updated_meta_value, 'save', '', 'no prefix' );
		$this->assertEquals( $updated_meta_value, $simple_product->get_meta( $meta_key ) );

		// verify reloaded object has updated values
		$reloaded_product = wc_get_product( $simple_product->get_id() );
		$this->assertEquals( $updated_name, $reloaded_product->get_name() );
		$this->assertEquals( $updated_meta_value, $reloaded_product->get_meta( $meta_key ) );
	}
}
