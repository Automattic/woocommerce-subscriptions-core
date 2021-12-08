<?php
/**
 * WC Subscriptions Helper Upgrade Repair class.
 *
 * @package WooCommerce/Tests
 */
class WCS_Helper_Upgrade_Repair {

	public static function fetch_old_subscription() {
		return [
			'order_id'           => '28',
			'name'               => 'variable subs',
			'user_id'            => '2',
			'subscription_key'   => '28_12',
			'product_id'         => '12',
			'variation_id'       => '14',
			'period'             => 'week',
			'interval'           => '1',
			'length'             => '0',
			'trial_length'       => '7',
			'trial_period'       => 'day',
			'recurring_amount'   => '11',
			'sign_up_fee'        => '20',
			'start_date'         => '2015-07-10 02:26:29',
			'expiry_date'        => '0',
			'trial_expiry_date'  => '2015-07-17 02:26:29',
			'failed_payments'    => '0',
			'completed_payments' => [
				'2015-07-10 02:26:29',
				'2015-07-17 02:26:29',
				'2015-07-24 02:26:29',
				'2015-07-31 02:26:29',
			],
			'status'             => 'on-hold',
			'end_date'           => '',
			'suspension_count'   => '1',
		];
	}

	public static function fetch_item_id() {
		return 19;
	}

	public static function fetch_item_meta() {
		return [
            '_qty'                             => [ '1' ],
            '_tax_class'                       => [ '' ],
            '_product_id'                      => [ '12' ],
            '_variation_id'                    => [ '14' ],
            '_subscription_period'             => [ 'week' ],
            '_subscription_interval'           => [ '1' ],
            '_subscription_length'             => [ '0' ],
            '_subscription_trial_length'       => [ '7' ],
            '_subscription_trial_period'       => [ 'day' ],
            '_subscription_recurring_amount'   => [ '11' ],
            '_subscription_sign_up_fee'        => [ '20' ],
            '_recurring_line_total'            => [ '11' ],
            '_recurring_line_tax'              => [ '0' ],
            '_recurring_line_subtotal'         => [ '11' ],
            '_recurring_line_subtotal_tax'     => [ '0' ],
            '_subscription_start_date'         => [ '2015-07-10 02:26:29' ],
            '_subscription_expiry_date'        => [ '0' ],
            '_subscription_trial_expiry_date'  => [ '2015-07-17 02:26:29' ],
            '_subscription_failed_payments'    => [ '0' ],
            '_subscription_completed_payments' => [ 'a:4:{i:0;s:19:"2015-07-10 02:26:29";i:1;s:19:"2015-07-17 02:26:29";i:2;s:19:"2015-07-24 02:26:29";i:3;s:19:"2015-07-31 02:26:29";}' ],
            '_subscription_status'             => [ 'on-hold' ],
            '_subscription_end_date'           => [ '' ],
            '_subscription_suspension_count'   => [ '1' ],
            '_line_subtotal'                   => [ '11' ],
            '_line_total'                      => [ '11' ],
            '_line_tax'                        => [ '0' ],
            '_line_subtotal_tax'               => [ '0' ],
		];
	}

	public static function fetch_order_item() {
		return [
			'name'      => 'variable subs',
			'type'      => 'line_item',
			'item_meta' => [
				'_qty'                             => [ '1' ],
				'_tax_class'                       => [ '' ],
				'_product_id'                      => [ '12' ],
				'_variation_id'                    => [ '14' ],
				'_subscription_period'             => [ 'week' ],
				'_subscription_interval'           => [ '1' ],
				'_subscription_length'             => [ '0' ],
				'_subscription_trial_length'       => [ '7' ],
				'_subscription_trial_period'       => [ 'day' ],
				'_subscription_recurring_amount'   => [ '11' ],
				'_subscription_sign_up_fee'        => [ '20' ],
				'_recurring_line_total'            => [ '11' ],
				'_recurring_line_tax'              => [ '0' ],
				'_recurring_line_subtotal'         => [ '11' ],
				'_recurring_line_subtotal_tax'     => [ '0' ],
				'_subscription_start_date'         => [ '2015-07-10 02:26:29' ],
				'_subscription_expiry_date'        => [ '0' ],
				'_subscription_trial_expiry_date'  => [ '2015-07-17 02:26:29' ],
				'_subscription_failed_payments'    => [ '0' ],
				'_subscription_completed_payments' => [ 'a:4:{i:0;s:19:"2015-07-10 02:26:29";i:1;s:19:"2015-07-17 2:26:29";i:2;s:19:"2015-07-24 02:26:29";i:3;s:19:"2015-07-31 02:26:29";}' ],
				'_subscription_status'             => [ 'on-hold' ],
				'_subscription_end_date'           => [ '' ],
				'_subscription_suspension_count'   => [ '1' ],
				'_line_subtotal'                   => [ '11' ],
				'_line_total'                      => [ '11' ],
				'_line_tax'                        => [ '0' ],
				'_line_subtotal_tax'               => [ '0' ],
			],
			'item_meta_array' => [
				(object) [
					'key'   => '_qty',
					'value' => '1'
				],
				(object) [
					'key'   => '_tax_class',
					'value' => ''
				],
				(object) [
					'key'   => '_product_id',
					'value' => '12'
				],
				(object) [
					'key'   => '_variation_id',
					'value' => '14'
				],
				(object) [
					'key'   => '_subscription_period',
					'value' => 'week'
				],
				(object) [
					'key'   => '_subscription_interval',
					'value' => '1'
				],
				(object) [
					'key'   => '_subscription_length',
					'value' => '0'
				],
				(object) [
					'key'   => '_subscription_trial_length',
					'value' => '7'
				],
				(object) [
					'key'   => '_subscription_trial_period',
					'value' => 'day'
				],
				(object) [
					'key'   => '_subscription_recurring_amount',
					'value' => '11'
				],
				(object) [
					'key'   => '_subscription_sign_up_fee',
					'value' => '20'
				],
				(object) [
					'key'   => '_recurring_line_total',
					'value' => '11'
				],
				(object) [
					'key'   => '_recurring_line_tax',
					'value' => '0'
				],
				(object) [
					'key'   => '_recurring_line_subtotal',
					'value' => '11'
				],
				(object) [
					'key'   => '_recurring_line_subtotal_tax',
					'value' => '0'
				],
				(object) [
					'key'   => '_subscription_start_date',
					'value' => '2015-07-10 02:26:29'
				],
				(object) [
					'key'   => '_subscription_expiry_date',
					'value' => '0'
				],
				(object) [
					'key'   => '_subscription_trial_expiry_date',
					'value' => '2015-07-17 02:26:29'
				],
				(object) [
					'key'   => '_subscription_failed_payments',
					'value' => '0'
				],
				(object) [
						  	'key' => '_subscription_completed_payments',
					'value' => 'a:4:{i:0;s:19:"2015-07-10 02:26:29";i:1;s:19:"2015-07-17 02:26:29";i:2;s:19:"2015-07-24 02:26:29";i:3;s:19:"2015-07-31 02:26:29";}'
				],
				(object) [
					'key'   => '_subscription_status',
					'value' => 'on-hold'
				],
				(object) [
					'key'   => '_subscription_end_date',
					'value' =>''
				],
				(object) [
					'key'   => '_subscription_suspension_count',
					'value' => '1'
				],
				(object) [
					'key'   => '_line_subtotal',
					'value' => '11'
				],
				(object) [
					'key'   => '_line_total',
					'value' => '11'
				],
				(object) [
					'key'   => '_line_tax',
					'value' => '0'
				],
				(object) [
					'key'   => '_line_subtotal_tax',
					'value' => '0'
				],
			],
			'qty'                             => '1',
			'tax_class'                       =>'',
			'product_id'                      => '12',
			'variation_id'                    => '14',
			'subscription_period'             => 'week',
			'subscription_interval'           => '1',
			'subscription_length'             => '0',
			'subscription_trial_length'       => '7',
			'subscription_trial_period'       => 'day',
			'subscription_recurring_amount'   => '11',
			'subscription_sign_up_fee'        => '20',
			'recurring_line_total'            => '11',
			'recurring_line_tax'              => '0',
			'recurring_line_subtotal'         => '11',
			'recurring_line_subtotal_tax'     => '0',
			'subscription_start_date'         => '2015-07-10 02:26:29',
			'subscription_expiry_date'        => '0',
			'subscription_trial_expiry_date'  => '2015-07-17 02:26:29',
			'subscription_failed_payments'    => '0',
			'subscription_completed_payments' => 'a:4:{i:0;s:19:"2015-07-10 02:26:29";i:1;s:19:"2015-07-17 02:26:29";i:2;s:19:"2015-07-24 02:26:29";i:3;s:19:"2015-07-31 02:26:29";}',
			'subscription_status'             => 'on-hold',
			'subscription_end_date'           =>'',
			'subscription_suspension_count'   => '1',
			'line_subtotal'                   => '11',
			'line_total'                      => '11',
			'line_tax'                        => '0',
			'line_subtotal_tax'               => '0',
		];
	}
}
