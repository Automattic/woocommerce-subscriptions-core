<?php
/**
 * WC Subscriptions Helper Coupon.
 *
 * @package WooCommerce/Tests
 */

/**
 * Class WCS_Helper_Coupon.
 *
 * This helper class should ONLY be used for unit tests!.
*/
class WCS_Helper_Coupon {

	/**
	 * Allow creating coupons by type.
	 *
	 * Use this method by calling WCS_Helper_Coupon::create_<coupon type>_coupon().
	 *
	 * @param string $name      The static method name.
	 * @param mixed  $arguments Arguments passed to the method.
	 *
	 * @return WC_Coupon
	 */
	public static function __callStatic( $name, $arguments ) {
		$type   = str_replace( [ 'create_', '_coupon' ], '', $name );
		$code   = $arguments[0] ?? 'dummycoupon';
		$amount = $arguments[1] ?? 10;
		$meta   = isset( $arguments[2] ) ? (array) $arguments[2] : [];
		$meta   = wp_parse_args(
			$meta,
			[
				'discount_type' => $type,
				'coupon_amount' => $amount,
			]
		);

		return self::create_coupon( $code, $meta );
	}

	/**
	 * Create a dummy coupon.
	 *
	 * @param string $coupon_code
	 * @param array  $meta
	 *
	 * @return WC_Coupon
	 */
	public static function create_coupon( $coupon_code = 'dummycoupon', $meta = [] ) {
		// Insert post.
		$coupon_id = wp_insert_post(
			[
				'post_title'   => $coupon_code,
				'post_type'    => 'shop_coupon',
				'post_status'  => 'publish',
				'post_excerpt' => 'This is a dummy coupon',
			]
		);

		$meta = wp_parse_args(
			$meta,
			[
				'discount_type'              => 'fixed_cart',
				'coupon_amount'              => '1',
				'individual_use'             => 'no',
				'product_ids'                => '',
				'exclude_product_ids'        => '',
				'usage_limit'                => '',
				'usage_limit_per_user'       => '',
				'limit_usage_to_x_items'     => '',
				'expiry_date'                => '',
				'free_shipping'              => 'no',
				'exclude_sale_items'         => 'no',
				'product_categories'         => [],
				'exclude_product_categories' => [],
				'minimum_amount'             => '',
				'maximum_amount'             => '',
				'customer_email'             => [],
				'usage_count'                => '0',
			]
		);

		// Update meta.
		foreach ( $meta as $key => $value ) {
			update_post_meta( $coupon_id, $key, $value );
		}

		return new WC_Coupon( $coupon_code );
	}
}
