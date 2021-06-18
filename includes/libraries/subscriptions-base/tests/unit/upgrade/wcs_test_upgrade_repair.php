<?php
/**
 *
 * @since 2.0
 */
class WCS_Repair_2_0_Tests extends WCS_Unit_Test_Case {
	public $date_display_format = 'Y-m-d H:i:s';
	public $old_subscription = null;
	public $item_id = null;
	public $item_meta = null;
	public $order_item = null;

	public function setUp() {
		parent::setUp();

		$this->old_subscription = WCS_Helper_Upgrade_Repair::fetch_old_subscription();

		$this->item_id = WCS_Helper_Upgrade_Repair::fetch_item_id();

		$this->item_meta = WCS_Helper_Upgrade_Repair::fetch_item_meta();

		$this->order_item = WCS_Helper_Upgrade_Repair::fetch_order_item();
	}

	public function test_can_repair_order_item() {
		unset( $this->order_item['qty'] );
		unset( $this->order_item['tax_class'] );
		unset( $this->order_item['product_id'] );
		unset( $this->order_item['variation_id'] );
		unset( $this->order_item['recurring_line_subtotal'] );
		unset( $this->order_item['recurring_line_total'] );
		unset( $this->order_item['recurring_line_tax'] );
		unset( $this->order_item['recurring_line_subtotal_tax'] );

		$repaired_order_item = WCS_Repair_2_0::maybe_repair_order_item( $this->order_item );

		$this->assertEquals( '', $repaired_order_item['qty'] );
		$this->assertEquals( '', $repaired_order_item['tax_class'] );
		$this->assertEquals( '', $repaired_order_item['product_id'] );
		$this->assertEquals( '', $repaired_order_item['variation_id'] );
		$this->assertEquals( '', $repaired_order_item['recurring_line_subtotal'] );
		$this->assertEquals( '', $repaired_order_item['recurring_line_total'] );
		$this->assertEquals( '', $repaired_order_item['recurring_line_tax'] );
		$this->assertEquals( '', $repaired_order_item['recurring_line_subtotal_tax'] );
	}

	public function test_integrity_check() {
		unset( $this->old_subscription['order_id'] );
		unset( $this->old_subscription['product_id'] );
		unset( $this->old_subscription['variation_id'] );
		unset( $this->old_subscription['subscription_key'] );
		unset( $this->old_subscription['status'] );
		unset( $this->old_subscription['period'] );
		unset( $this->old_subscription['interval'] );
		unset( $this->old_subscription['length'] );
		unset( $this->old_subscription['start_date'] );
		unset( $this->old_subscription['trial_expiry_date'] );
		unset( $this->old_subscription['expiry_date'] );
		unset( $this->old_subscription['end_date'] );

		$repairs_needed = WCS_Repair_2_0::integrity_check( $this->old_subscription );

		$test_for_repairs_needed = array(
			'order_id',
			'product_id',
			'variation_id',
			'subscription_key',
			'status',
			'period',
			'interval',
			'length',
			'start_date',
			'trial_expiry_date',
			'expiry_date',
			'end_date',
		);

		$this->assertEquals( $repairs_needed, $test_for_repairs_needed );
	}

	public function test_repair_order_id() {
		unset( $this->old_subscription['order_id'] );

		$repaired_subscription = WCS_Repair_2_0::repair_order_id( $this->old_subscription );

		$this->assertArrayNotHasKey( 'order_id', $repaired_subscription );
		$this->assertNotEquals( 'trash', $this->old_subscription['status'] );
		$this->assertEquals( 'trash', $repaired_subscription['status'] );
	}

	public function test_repair_from_item_meta() {
		// test what happens if item meta is not an array
		$repaired_subscription = WCS_Repair_2_0::repair_from_item_meta(
			$this->old_subscription,
			$this->item_id,
			null,
			'product_id',
			'_product_id',
			''
		);
		$this->assertEquals( $repaired_subscription, $this->old_subscription );

		// test what happens if item id is not numeric
		$repaired_subscription = WCS_Repair_2_0::repair_from_item_meta(
			$this->old_subscription,
			null,
			$this->item_meta,
			'product_id',
			'_product_id',
			''
		);
		$this->assertEquals( $repaired_subscription, $this->old_subscription );


		// test what happens if subscription meta key is not a string
		$repaired_subscription = WCS_Repair_2_0::repair_from_item_meta(
			$this->old_subscription,
			$this->item_id,
			$this->item_meta,
			null,
			'product_id',
			''
		);
		$this->assertEquals( $repaired_subscription, $this->old_subscription );

		// if item_meta_key is not a string
		$repaired_subscription = WCS_Repair_2_0::repair_from_item_meta(
			$this->old_subscription,
			$this->item_id,
			$this->item_meta,
			'product_id',
			null,
			''
		);
		$this->assertEquals( $repaired_subscription, $this->old_subscription );

		// if default value is not a string
		$repaired_subscription = WCS_Repair_2_0::repair_from_item_meta(
			$this->old_subscription,
			$this->item_id,
			$this->item_meta,
			'product_id',
			'_product_id',
			array( 4 )
		);
		$this->assertEquals( $repaired_subscription, $this->old_subscription );

		// if default value is a string, but not numeric
		$repaired_subscription = WCS_Repair_2_0::repair_from_item_meta(
			$this->old_subscription,
			$this->item_id,
			$this->item_meta,
			'product_id',
			'_product_id',
			'foo'
		);
		$this->assertEquals( $repaired_subscription, $this->old_subscription );

		/**
		 * Positive tests
		 */
		// when sub doesn't have the key, but item meta does
		$local_subscription = $this->old_subscription;
		unset( $local_subscription['product_id'] );

		$repaired_subscription = WCS_Repair_2_0::repair_from_item_meta(
			$local_subscription,
			$this->item_id,
			$this->item_meta,
			'product_id',
			'_product_id'
		);
		$this->assertEquals(
			$repaired_subscription['product_id'],
			$this->item_meta['_product_id'][0]
		);

		// when sub doesn't have the key, but item meta does, but default passed
		$local_subscription = $this->old_subscription;
		unset( $local_subscription['product_id'] );

		$repaired_subscription = WCS_Repair_2_0::repair_from_item_meta(
			$local_subscription,
			$this->item_id,
			$this->item_meta,
			'product_id',
			'_product_id',
			'9999999'
		);
		$this->assertEquals( $repaired_subscription['product_id'], $this->item_meta['_product_id'][0] );


		// when sub doesn't have the key, nor does the item meta, default not passed
		$local_subscription = $this->old_subscription;
		unset( $local_subscription['product_id'] );

		$repaired_subscription = WCS_Repair_2_0::repair_from_item_meta(
			$local_subscription,
			$this->item_id,
			$this->item_meta,
			'foo',
			'foo'
		);
		$this->assertEquals( $repaired_subscription['foo'], '' );

		// when sub doesn't have the key, nor does the item meta, default passed
		$local_subscription = $this->old_subscription;
		unset( $local_subscription['product_id'] );

		$repaired_subscription = WCS_Repair_2_0::repair_from_item_meta(
			$local_subscription,
			$this->item_id,
			$this->item_meta,
			'foo',
			'foo',
			'99999'
		);
		$this->assertEquals( $repaired_subscription['foo'], '99999' );
	}

	public function test_repair_product_id() {
		unset( $this->old_subscription['product_id'] );

		// test for successfull repair
		$repaired_subscription = WCS_Repair_2_0::repair_product_id( $this->old_subscription, $this->item_id, $this->item_meta );
		$this->assertEquals( $repaired_subscription['product_id'], $this->item_meta['_product_id'][0] );

		// test for failed repairs
		$repaired_subscription = WCS_Repair_2_0::repair_product_id( $this->old_subscription, null, $this->item_meta );
		$this->assertArrayNotHasKey( 'product_id', $repaired_subscription );

		$repaired_subscription = WCS_Repair_2_0::repair_product_id( $this->old_subscription, $this->item_id, null );
		$this->assertArrayNotHasKey( 'product_id', $repaired_subscription );

		$repaired_subscription = WCS_Repair_2_0::repair_product_id( $this->old_subscription, null, null );
		$this->assertArrayNotHasKey( 'product_id', $repaired_subscription );
	}

	public function test_repair_variation_id() {
		unset( $this->old_subscription['variation_id'] );

		// test for successfull repair
		$repaired_subscription = WCS_Repair_2_0::repair_variation_id( $this->old_subscription, $this->item_id, $this->item_meta );
		$this->assertEquals( $repaired_subscription['variation_id'], $this->item_meta['_variation_id'][0] );

		// test for failed repairs
		$repaired_subscription = WCS_Repair_2_0::repair_variation_id( $this->old_subscription, null, $this->item_meta );
		$this->assertArrayNotHasKey( 'variation_id', $repaired_subscription );

		$repaired_subscription = WCS_Repair_2_0::repair_variation_id( $this->old_subscription, $this->item_id, null );
		$this->assertArrayNotHasKey( 'variation_id', $repaired_subscription );

		$repaired_subscription = WCS_Repair_2_0::repair_variation_id( $this->old_subscription, null, null );
		$this->assertArrayNotHasKey( 'variation_id', $repaired_subscription );
	}

	public function test_repair_subscription_key() {
		unset( $this->old_subscription['subscription_key'] );

		// successfull repair. 28_12 comes from the mock data
		$repaired_subscription = WCS_Repair_2_0::repair_subscription_key( $this->old_subscription, $this->item_id, $this->item_meta );
		$this->assertEquals( '28_19', $repaired_subscription['subscription_key'], 'all input data is there' );

		$repaired_subscription = WCS_Repair_2_0::repair_subscription_key( $this->old_subscription, $this->item_id, null );
		$this->assertEquals( '28_19', $repaired_subscription['subscription_key'], 'item id is null' );

		// failed repairs
		$repaired_subscription = WCS_Repair_2_0::repair_subscription_key( $this->old_subscription, null, $this->item_meta );
		$this->assertEquals( '', $repaired_subscription['subscription_key'] );

		$repaired_subscription = WCS_Repair_2_0::repair_subscription_key( $this->old_subscription, null, null );
		$this->assertEquals( '', $repaired_subscription['subscription_key'] );
	}

	public function test_repair_status() {
		// let's test if order id is missing and have run repair order id
		$local_subscription = $this->old_subscription;
		unset( $local_subscription['order_id'] );
		$repaired_subscription = WCS_Repair_2_0::repair_order_id( $local_subscription, $this->item_id, $this->item_meta );
		$repaired_subscription = WCS_Repair_2_0::repair_status( $repaired_subscription, $this->item_id, $this->item_meta );
		$this->assertArrayNotHasKey( 'order_id', $repaired_subscription );
		$this->assertEquals( 'trash', $repaired_subscription['status'] );

		// let's test if order id is missing and have not run repair order id
		$local_subscription = $this->old_subscription;
		unset( $local_subscription['order_id'] );
		$repaired_subscription = WCS_Repair_2_0::repair_status( $local_subscription, $this->item_id, $this->item_meta );
		$this->assertArrayNotHasKey( 'order_id', $repaired_subscription );
		$this->assertEquals( 'on-hold', $repaired_subscription['status'] );

		// let's see if it turns to expired if expiry date is in the past and end date is within 4 minutes of it
		$local_subscription = $this->old_subscription;
		$past_expiry_date = date( $this->date_display_format, strtotime( '-5 minutes' ) );
		$past_end_date_close = date( $this->date_display_format, strtotime( '+4 minutes', strtotime( $past_expiry_date ) ) );
		$local_subscription['expiry_date'] = $past_expiry_date;
		$local_subscription['end_date'] = $past_end_date_close;
		$repaired_subscription = WCS_Repair_2_0::repair_status( $local_subscription, $this->item_id, $this->item_meta );
		$this->assertEquals( $past_expiry_date, $repaired_subscription['expiry_date'] );
		$this->assertEquals( $past_end_date_close, $repaired_subscription['end_date'] );
		$this->assertEquals( 'expired', $repaired_subscription['status'] );

		// let's see if it turns to expired if expiry date is in the past and end date is within 4 minutes of it
		$local_subscription = $this->old_subscription;
		$past_expiry_date = date( $this->date_display_format, strtotime( '-5 minutes' ) );
		$past_end_date_close = date( $this->date_display_format, strtotime( '-4 minutes', strtotime( $past_expiry_date ) ) );
		$local_subscription['expiry_date'] = $past_expiry_date;
		$local_subscription['end_date'] = $past_end_date_close;
		$repaired_subscription = WCS_Repair_2_0::repair_status( $local_subscription, $this->item_id, $this->item_meta );
		$this->assertEquals( $past_expiry_date, $repaired_subscription['expiry_date'] );
		$this->assertEquals( $past_end_date_close, $repaired_subscription['end_date'] );
		$this->assertEquals( 'expired', $repaired_subscription['status'] );

		// let's see if it turns to cancelled if expiry date is in the past and end date is within 4 minutes of it
		$local_subscription = $this->old_subscription;
		$past_expiry_date = date( $this->date_display_format, strtotime( '-6 minutes' ) );
		$past_end_date_far = date( $this->date_display_format, strtotime( '+5 minutes', strtotime( $past_expiry_date ) ) );
		$local_subscription['expiry_date'] = $past_expiry_date;
		$local_subscription['end_date'] = $past_end_date_far;
		$repaired_subscription = WCS_Repair_2_0::repair_status( $local_subscription, $this->item_id, $this->item_meta );
		$this->assertEquals( $past_expiry_date, $repaired_subscription['expiry_date'] );
		$this->assertEquals( $past_end_date_far, $repaired_subscription['end_date'] );
		$this->assertEquals( 'cancelled', $repaired_subscription['status'] );

		// let's see if it turns to cancelled if expiry date is in the past and end date is within 4 minutes of it
		$local_subscription = $this->old_subscription;
		$past_expiry_date = date( $this->date_display_format, strtotime( '-6 minutes' ) );
		$past_end_date_far = date( $this->date_display_format, strtotime( '-5 minutes', strtotime( $past_expiry_date ) ) );
		$local_subscription['expiry_date'] = $past_expiry_date;
		$local_subscription['end_date'] = $past_end_date_far;
		$repaired_subscription = WCS_Repair_2_0::repair_status( $local_subscription, $this->item_id, $this->item_meta );
		$this->assertEquals( $past_expiry_date, $repaired_subscription['expiry_date'] );
		$this->assertEquals( $past_end_date_far, $repaired_subscription['end_date'] );
		$this->assertEquals( 'cancelled', $repaired_subscription['status'] );

		// let's see if it turns to cancelled if there is an end date, but empty expiry date
		$local_subscription = $this->old_subscription;
		$past_end_date_far = date( $this->date_display_format, strtotime( '-5 minutes' ) );
		$local_subscription['end_date'] = $past_end_date_far;
		$repaired_subscription = WCS_Repair_2_0::repair_status( $local_subscription, $this->item_id, $this->item_meta );
		$this->assertEquals( $past_end_date_far, $repaired_subscription['end_date'] );
		$this->assertEmpty( $repaired_subscription['expiry_date'] );
		$this->assertEquals( 'cancelled', $repaired_subscription['status'] );

		// let's see if it turns to cancelled if there is an end date, but no expiry date
		$local_subscription = $this->old_subscription;
		unset( $local_subscription['expiry_date'] );
		$past_end_date_far = date( $this->date_display_format, strtotime( '-5 minutes' ) );
		$local_subscription['end_date'] = $past_end_date_far;
		$repaired_subscription = WCS_Repair_2_0::repair_status( $local_subscription, $this->item_id, $this->item_meta );
		$this->assertEquals( $past_end_date_far, $repaired_subscription['end_date'] );
		$this->assertArrayNotHasKey( 'expiry_date', $repaired_subscription );
		$this->assertEquals( 'cancelled', $repaired_subscription['status'] );

		// cancelled if there's an expiry date, but no end date
		$local_subscription = $this->old_subscription;
		unset( $local_subscription['end_date'] );
		$past_expiry_date = date( $this->date_display_format, strtotime( '-5 minutes' ) );
		$local_subscription['expiry_date'] = $past_expiry_date;
		$repaired_subscription = WCS_Repair_2_0::repair_status( $local_subscription, $this->item_id, $this->item_meta );
		$this->assertEquals( $past_expiry_date, $repaired_subscription['expiry_date'] );
		$this->assertArrayNotHasKey( 'end_date', $repaired_subscription );
		$this->assertEquals( 'cancelled', $repaired_subscription['status'] );

		// cancelled if there's an expiry date, but no end date
		$local_subscription = $this->old_subscription;
		$past_expiry_date = date( $this->date_display_format, strtotime( '-5 minutes' ) );
		$local_subscription['expiry_date'] = $past_expiry_date;
		$repaired_subscription = WCS_Repair_2_0::repair_status( $local_subscription, $this->item_id, $this->item_meta );
		$this->assertEquals( $past_expiry_date, $repaired_subscription['expiry_date'] );
		$this->assertEmpty( $repaired_subscription['end_date'] );
		$this->assertEquals( 'cancelled', $repaired_subscription['status'] );
	}

	public function test_repair_length() {
		// test from item meta
		$local_subscription = $this->old_subscription;
		$local_item_meta = $this->item_meta;
		unset( $local_subscription['length'] );
		$local_item_meta['_subscription_length'] = array( '4' );
		$repaired_subscription = WCS_Repair_2_0::repair_length( $local_subscription, $this->item_id, $local_item_meta );
		$this->assertEquals( '4', $repaired_subscription['length'] );

		// test with neither subscription nor item meta having that: defaults to 0
		$local_subscription = $this->old_subscription;
		$local_item_meta = $this->item_meta;
		unset( $local_subscription['length'] );
		unset( $local_item_meta['_subscription_length'] );
		$repaired_subscription = WCS_Repair_2_0::repair_length( $local_subscription, $this->item_id, $local_item_meta );
		$this->assertEquals( '0', $repaired_subscription['length'] );

		// yes being expired,
		// yes expiry date,
		// yes start date,
		// no trial expiry date
		// no trial period
		// no trial length
		// yes period,
		// yes interval
		$local_subscription = $this->old_subscription;
		$local_item_meta = $this->item_meta;
		unset( $local_item_meta['_subscription_length'] );
		$local_subscription['status'] = 'expired';
		$local_subscription['start_date'] = '2015-07-14 01:11:11';
		$local_subscription['expiry_date'] = '2015-07-21 01:11:11';
		$local_subscription['period'] = 'day';
		$local_subscription['interval'] = '1';
		unset( $local_subscription['length'] );
		unset( $local_subscription['trial_expiry_date'] );
		unset( $local_subscription['trial_period'] );
		unset( $local_subscription['trial_length'] );

		$repaired_subscription = WCS_Repair_2_0::repair_length( $local_subscription, $this->item_id, $local_item_meta );
		$this->assertEquals( '7', $repaired_subscription['length'] );

		// yes being expired,
		// yes expiry date,
		// yes start date,
		// yes trial expiry date (4 days and a bit)
		// no trial period
		// no trial length
		// yes period,
		// yes interval
		$local_subscription = $this->old_subscription;
		$local_item_meta = $this->item_meta;
		unset( $local_item_meta['_subscription_length'] );
		$local_subscription['status'] = 'expired';
		$local_subscription['start_date'] = '2015-07-14 01:11:11';
		$local_subscription['expiry_date'] = '2015-07-21 01:11:11';
		$local_subscription['period'] = 'day';
		$local_subscription['interval'] = '1';
		$local_subscription['trial_expiry_date'] = '2015-07-17 00:11:11';
		unset( $local_subscription['length'] );
		unset( $local_subscription['trial_period'] );
		unset( $local_subscription['trial_length'] );

		$repaired_subscription = WCS_Repair_2_0::repair_length( $local_subscription, $this->item_id, $local_item_meta );
		$this->assertEquals( '4', $repaired_subscription['length'] );

		// yes being expired,
		// yes expiry date,
		// yes start date,
		// yes trial expiry date (4 days and a bit)
		// yes trial period
		// no trial length
		// yes period,
		// yes interval
		$local_subscription = $this->old_subscription;
		$local_item_meta = $this->item_meta;
		unset( $local_item_meta['_subscription_length'] );
		$local_subscription['status'] = 'expired';
		$local_subscription['start_date'] = '2015-07-14 01:11:11';
		$local_subscription['expiry_date'] = '2015-07-21 01:11:11';
		$local_subscription['period'] = 'day';
		$local_subscription['interval'] = '1';
		$local_subscription['trial_expiry_date'] = '2015-07-17 00:11:11';
		unset( $local_subscription['length'] );
		unset( $local_subscription['trial_length'] );

		$repaired_subscription = WCS_Repair_2_0::repair_length( $local_subscription, $this->item_id, $local_item_meta );
		$this->assertEquals( '4', $repaired_subscription['length'] );

		// yes being expired,
		// yes expiry date,
		// yes start date,
		// yes trial expiry date (4 days and a bit)
		// no trial period
		// yes trial length
		// yes period,
		// yes interval
		$local_subscription = $this->old_subscription;
		$local_item_meta = $this->item_meta;
		unset( $local_item_meta['_subscription_length'] );
		$local_subscription['status'] = 'expired';
		$local_subscription['start_date'] = '2015-07-14 01:11:11';
		$local_subscription['expiry_date'] = '2015-07-21 01:11:11';
		$local_subscription['period'] = 'day';
		$local_subscription['interval'] = '1';
		$local_subscription['trial_expiry_date'] = '2015-07-17 00:11:11';
		unset( $local_subscription['length'] );
		unset( $local_subscription['trial_period'] );

		$repaired_subscription = WCS_Repair_2_0::repair_length( $local_subscription, $this->item_id, $local_item_meta );
		$this->assertEquals( '4', $repaired_subscription['length'] );

		// yes being expired,
		// yes expiry date,
		// yes start date,
		// yes trial expiry date (4 days and a bit)
		// yes trial period
		// yes trial length
		// yes period,
		// yes interval
		$local_subscription = $this->old_subscription;
		$local_item_meta = $this->item_meta;
		unset( $local_item_meta['_subscription_length'] );
		$local_subscription['status'] = 'expired';
		$local_subscription['start_date'] = '2015-07-14 01:11:11';
		$local_subscription['expiry_date'] = '2015-07-21 01:11:11';
		$local_subscription['period'] = 'day';
		$local_subscription['interval'] = '1';
		$local_subscription['trial_expiry_date'] = '2015-07-17 02:11:11';
		unset( $local_subscription['length'] );

		$repaired_subscription = WCS_Repair_2_0::repair_length( $local_subscription, $this->item_id, $local_item_meta );
		$this->assertEquals( '3', $repaired_subscription['length'] );

		// yes being expired,
		// yes expiry date,
		// yes start date,
		// no trial expiry date
		// yes trial period
		// no trial length
		// yes period,
		// yes interval
		$local_subscription = $this->old_subscription;
		$local_item_meta = $this->item_meta;
		unset( $local_item_meta['_subscription_length'] );
		$local_subscription['status'] = 'expired';
		$local_subscription['start_date'] = '2015-07-14 01:11:11';
		$local_subscription['expiry_date'] = '2015-07-21 01:11:11';
		$local_subscription['period'] = 'day';
		$local_subscription['interval'] = '1';
		$local_subscription['trial_period'] = 'day';
		unset( $local_subscription['trial_expiry_date'] );
		unset( $local_subscription['length'] );
		unset( $local_subscription['trial_length'] );

		$repaired_subscription = WCS_Repair_2_0::repair_length( $local_subscription, $this->item_id, $local_item_meta );
		$this->assertEquals( '7', $repaired_subscription['length'] );

		// yes being expired,
		// yes expiry date,
		// yes start date,
		// no trial expiry date
		// no trial period
		// yes trial length
		// yes period,
		// yes interval
		$local_subscription = $this->old_subscription;
		$local_item_meta = $this->item_meta;
		unset( $local_item_meta['_subscription_length'] );
		$local_subscription['status'] = 'expired';
		$local_subscription['start_date'] = '2015-07-14 01:11:11';
		$local_subscription['expiry_date'] = '2015-07-21 01:11:11';
		$local_subscription['period'] = 'day';
		$local_subscription['interval'] = '1';
		$local_subscription['trial_length'] = '4';
		unset( $local_subscription['trial_expiry_date'] );
		unset( $local_subscription['length'] );
		unset( $local_subscription['trial_period'] );

		$repaired_subscription = WCS_Repair_2_0::repair_length( $local_subscription, $this->item_id, $local_item_meta );
		$this->assertEquals( '7', $repaired_subscription['length'] );

		// yes being expired,
		// yes expiry date,
		// yes start date,
		// no trial expiry date
		// yes trial period
		// yes trial length
		// yes period,
		// yes interval
		$local_subscription = $this->old_subscription;
		$local_item_meta = $this->item_meta;
		unset( $local_item_meta['_subscription_length'] );
		$local_subscription['status'] = 'expired';
		$local_subscription['start_date'] = '2015-07-14 01:11:11';
		$local_subscription['expiry_date'] = '2015-07-21 01:11:11';
		$local_subscription['period'] = 'day';
		$local_subscription['interval'] = '1';
		$local_subscription['trial_length'] = '4';
		$local_subscription['trial_period'] = 'day';
		unset( $local_subscription['trial_expiry_date'] );
		unset( $local_subscription['length'] );

		$repaired_subscription = WCS_Repair_2_0::repair_length( $local_subscription, $this->item_id, $local_item_meta );
		$this->assertEquals( '3', $repaired_subscription['length'] );

		/**
		 * negative tests
		 */
		// no being expired,
		// yes expiry date,
		// yes start date,
		// yes trial expiry date
		// yes trial period
		// yes trial length
		// yes period,
		// yes interval
		$local_subscription = $this->old_subscription;
		$local_item_meta = $this->item_meta;
		unset( $local_item_meta['_subscription_length'] );
		$local_subscription['start_date'] = '2015-07-14 01:11:11';
		$local_subscription['expiry_date'] = '2015-07-21 01:11:11';
		$local_subscription['period'] = 'day';
		$local_subscription['interval'] = '1';
		$local_subscription['trial_length'] = '4';
		$local_subscription['trial_period'] = 'day';
		unset( $local_subscription['length'] );

		$repaired_subscription = WCS_Repair_2_0::repair_length( $local_subscription, $this->item_id, $local_item_meta );
		$this->assertEquals( '0', $repaired_subscription['length'] );

		// yes being expired,
		// no expiry date,
		// yes start date,
		// yes trial expiry date
		// yes trial period
		// yes trial length
		// yes period,
		// yes interval
		$local_subscription = $this->old_subscription;
		$local_item_meta = $this->item_meta;
		unset( $local_item_meta['_subscription_length'] );
		$local_subscription['status'] = 'expired';
		$local_subscription['start_date'] = '2015-07-14 01:11:11';
		$local_subscription['period'] = 'day';
		$local_subscription['interval'] = '1';
		$local_subscription['trial_length'] = '4';
		$local_subscription['trial_period'] = 'day';
		unset( $local_subscription['length'] );
		unset( $local_subscription['expiry_date'] );

		$repaired_subscription = WCS_Repair_2_0::repair_length( $local_subscription, $this->item_id, $local_item_meta );
		$this->assertEquals( '0', $repaired_subscription['length'] );

		// yes being expired,
		// yes expiry date,
		// yes start date,
		// yes trial expiry date
		// yes trial period
		// yes trial length
		// no period,
		// yes interval
		$local_subscription = $this->old_subscription;
		$local_item_meta = $this->item_meta;
		unset( $local_item_meta['_subscription_length'] );
		$local_subscription['status'] = 'expired';
		$local_subscription['start_date'] = '2015-07-14 01:11:11';
		$local_subscription['expiry_date'] = '2015-07-21 01:11:11';
		$local_subscription['interval'] = '1';
		$local_subscription['trial_length'] = '4';
		$local_subscription['trial_period'] = 'day';
		unset( $local_subscription['length'] );
		unset( $local_subscription['period'] );

		$repaired_subscription = WCS_Repair_2_0::repair_length( $local_subscription, $this->item_id, $local_item_meta );
		$this->assertEquals( '0', $repaired_subscription['length'] );

		// yes being expired,
		// yes expiry date,
		// yes start date,
		// yes trial expiry date
		// yes trial period
		// yes trial length
		// yes period,
		// no interval
		$local_subscription = $this->old_subscription;
		$local_item_meta = $this->item_meta;
		unset( $local_item_meta['_subscription_length'] );
		$local_subscription['status'] = 'expired';
		$local_subscription['start_date'] = '2015-07-14 01:11:11';
		$local_subscription['expiry_date'] = '2015-07-21 01:11:11';
		$local_subscription['period'] = 'day';
		$local_subscription['trial_length'] = '4';
		$local_subscription['trial_period'] = 'day';
		unset( $local_subscription['length'] );
		unset( $local_subscription['interval'] );

		$repaired_subscription = WCS_Repair_2_0::repair_length( $local_subscription, $this->item_id, $local_item_meta );
		$this->assertEquals( '0', $repaired_subscription['length'] );
	}

	public function test_get_effective_start_date() {
		// there's trial expiry date
		$local_subscription = $this->old_subscription;
		$this->assertEquals( '2015-07-17 02:26:29', WCS_Repair_2_0::get_effective_start_date( $local_subscription ) );

		// no trial expiry date, yes trial period, yes trial length, yes start date
		unset( $local_subscription['trial_expiry_date'] );
		$this->assertEquals( '2015-07-17 02:26:29', WCS_Repair_2_0::get_effective_start_date( $local_subscription ) );

		// no trial expiry date, no trial period, yes trial length, yes start date
		unset( $local_subscription['trial_period'] );
		$this->assertEquals( '2015-07-10 02:26:29', WCS_Repair_2_0::get_effective_start_date( $local_subscription ) );

		// no trial expiry date, yes trial period, no trial length, yes start date
		unset( $local_subscription['trial_length'] );
		$local_subscription['trial_period'] = 'day';
		$this->assertEquals( '2015-07-10 02:26:29', WCS_Repair_2_0::get_effective_start_date( $local_subscription ) );

		// no trial expiry date, no trial period, no trial length, yes start date
		unset( $local_subscription['trial_period'] );
		$this->assertEquals( '2015-07-10 02:26:29', WCS_Repair_2_0::get_effective_start_date( $local_subscription ) );

		// no trial expiry date, no trial period, no trial length, no start date
		unset( $local_subscription['start_date'] );
		$this->assertEquals( null, WCS_Repair_2_0::get_effective_start_date( $local_subscription ) );
	}

	public function test_repair_start_date() {
		// need to create a WP post for this, and change the date to something
		// we know (instead of relying on time())
		$post_id = $this->factory->post->create();
		$new_post_date = date( $this->date_display_format, strtotime( '-3 week' ) );
		wp_update_post( array(
			'ID' => $post_id,
			'post_date' => $new_post_date,
			'post_date_gmt' => $new_post_date
		) );

		$local_subscription = $this->old_subscription;
		unset( $local_subscription['start_date'] );
		$local_subscription['order_id'] = $post_id;

		// this checks for the post date gmt
		$repaired_subscription = WCS_Repair_2_0::repair_start_date( $local_subscription, $this->item_id, $this->item_meta );
		$this->assertEquals( $new_post_date, $repaired_subscription['start_date'] );

		// now let's add the paid date post meta
		add_post_meta( $post_id, '_paid_date', '2015-06-14 01:11:11', true );
		$this->assertEquals( '2015-06-14 01:11:11', get_post_meta( $post_id, '_paid_date', true ) );

		unset( $local_subscription['start_date'] );
		$repaired_subscription = WCS_Repair_2_0::repair_start_date( $local_subscription, $this->item_id, $this->item_meta );

		$this->assertEquals( '2015-06-14 01:11:11', $repaired_subscription['start_date'] );
	}

	/**
	 * This is untestable due to lack of action schedules
	 */
	public function test_repair_trial_expiry_date() {}

	/**
	 * This is untestable due to lack of action schedules
	 */
	public function test_repair_expiry_date() {}

	/**
	 * Part of this is untestable because we have no renewal orders. The conditions under which
	 * that would happen is:
	 * - if the subscription's status is cancelled
	 * - OR if there is no 'length' on the subscription
	 * - OR the 'length' of the subscription is empty
	 * - AND there is at least one renewal order.
	 *
	 * Because the last condition is something I can't mock without a bit of more setup
	 * currently it defaults to 0;
	 */
	public function test_repair_end_date() {
		$local_subscription = $this->old_subscription;
		$local_item_meta = $this->item_meta;

		// test copying from item meta
		unset( $local_subscription['end_date'] );
		$local_item_meta['_subscription_end_date'][0] = '2016-06-14 01:11:11';
		$repaired_subscription = WCS_Repair_2_0::repair_end_date( $local_subscription, $this->item_id, $local_item_meta );
		$this->assertEquals( '2016-06-14 01:11:11', $repaired_subscription['end_date'] );

		// test copying from expiry date if it's expired
		unset( $local_subscription['end_date'] );
		unset( $local_item_meta['_subscription_end_date'] );
		$local_subscription['expiry_date'] = '2017-06-14 01:11:11';
		$local_subscription['status'] = 'expired';
		$repaired_subscription = WCS_Repair_2_0::repair_end_date( $local_subscription, $this->item_id, $local_item_meta );
		$this->assertEquals( '2017-06-14 01:11:11', $repaired_subscription['end_date'] );

		// length is empty, not expired
		$local_subscription['status'] = 'on-hold';
		$local_subscription['expiry_date'] = '0';
		$repaired_subscription = WCS_Repair_2_0::repair_end_date( $local_subscription, $this->item_id, $local_item_meta );
		$this->assertEquals( '0', $repaired_subscription['end_date'] );

		// not expired, length is not empty, but there are no renewal orders
		$local_subscription['status'] = 'on-hold';
		$local_subscription['expiry_date'] = '0';
		$local_subscription['length'] = '7';
		$repaired_subscription = WCS_Repair_2_0::repair_end_date( $local_subscription, $this->item_id, $local_item_meta );
		$this->assertEquals( '0', $repaired_subscription['end_date'] );

		// not expired, length is not empty, but there are no renewal orders
		$local_subscription['status'] = 'cancelled';
		$local_subscription['expiry_date'] = '2017-06-14 01:11:11';
		$local_subscription['length'] = '0';
		$repaired_subscription = WCS_Repair_2_0::repair_end_date( $local_subscription, $this->item_id, $local_item_meta );
		$this->assertEquals( '0', $repaired_subscription['end_date'] );

		// not expired, length is not empty, but there are no renewal orders
		$local_subscription['status'] = 'on-hold';
		$local_subscription['expiry_date'] = '0';
		unseT( $local_subscription['length'] );
		$repaired_subscription = WCS_Repair_2_0::repair_end_date( $local_subscription, $this->item_id, $local_item_meta );
		$this->assertEquals( '0', $repaired_subscription['end_date'] );
	}

	public function test_repair_recurring_line_total() {
		$repaired_subscription = WCS_Repair_2_0::repair_recurring_line_total( $this->old_subscription, $this->item_id, $this->item_meta );
		$this->assertEquals( '11', $repaired_subscription['recurring_line_total'] );
	}

	public function test_repair_recurring_line_tax() {
		$this->item_meta['_line_tax'][0] = '42';
		$repaired_subscription = WCS_Repair_2_0::repair_recurring_line_tax( $this->old_subscription, $this->item_id, $this->item_meta );
		$this->assertEquals( '42', $repaired_subscription['recurring_line_tax'] );
	}

	public function test_repair_recurring_line_subtotal() {
		$this->item_meta['_line_subtotal'][0] = '999';
		$repaired_subscription = WCS_Repair_2_0::repair_recurring_line_subtotal( $this->old_subscription, $this->item_id, $this->item_meta );
		$this->assertEquals( '999', $repaired_subscription['recurring_line_subtotal'] );
	}

	public function test_repair_recurring_line_subtotal_tax() {
		$this->item_meta['_line_subtotal_tax'][0] = '666';

		$repaired_subscription = WCS_Repair_2_0::repair_recurring_line_subtotal_tax( $this->old_subscription, $this->item_id, $this->item_meta );
		$this->assertEquals( '666', $repaired_subscription['recurring_line_subtotal_tax'] );
	}

	/**
	 * Part of this is untestable since no renewal orders. For that part, I'm going to test
	 * wcs_estimate_period_between
	 */
	public function test_repair_period() {
		$local_subscription = $this->old_subscription;
		$local_item_meta = $this->item_meta;
		unset( $local_subscription['period'] );
		$repaired_subscription = WCS_Repair_2_0::repair_period( $local_subscription, $this->item_id, $local_item_meta );
		$this->assertEquals( 'week', $repaired_subscription['period'] );

		unset( $local_item_meta['_subscription_period'] );
		$repaired_subscription = WCS_Repair_2_0::repair_period( $local_subscription, $this->item_id, $local_item_meta );
		$this->assertEquals( 'month', $repaired_subscription['period'] );
		$this->assertEquals( 'cancelled', $repaired_subscription['status'] );
	}

	/**
	 * Part of this is untestable since there aren't renewal orders. That part is tested with
	 * wcs_estimate_periods_between.
	 */
	public function test_repair_interval() {
		$local_subscription = $this->old_subscription;
		$local_item_meta = $this->item_meta;
		unset( $local_subscription['interval'] );
		$local_item_meta['_subscription_interval'][0] = 9;
		$repaired_subscription = WCS_Repair_2_0::repair_interval( $local_subscription, $this->item_id, $local_item_meta );
		$this->assertEquals( '9', $repaired_subscription['interval'] );

		// the default
		$local_subscription = $this->old_subscription;
		unset( $local_subscription['interval'] );
		unset( $local_item_meta['_subscription_interval'] );
		$repaired_subscription = WCS_Repair_2_0::repair_interval( $local_subscription, $this->item_id, $local_item_meta );
		$this->assertEquals( '1', $repaired_subscription['interval'] );
		$this->assertEquals( 'cancelled', $repaired_subscription['status'] );
	}

}
