<?php

/**
 *
 * @method static WC_Coupon create_sign_up_fee_coupon( string $code = 'dummycoupon', int $amount = 10, array $meta = array() )
 * @method static WC_Coupon create_sign_up_fee_percent_coupon( string $code = 'dummycoupon', int $amount = 10, array $meta = array() )
 * @method static WC_Coupon create_recurring_fee_coupon( string $code = 'dummycoupon', int $amount = 10, array $meta = array() )
 * @method static WC_Coupon create_recurring_percent_coupon( string $code = 'dummycoupon', int $amount = 10, array $meta = array() )
 * @method static WC_Coupon create_renewal_percent_coupon( string $code = 'dummycoupon', int $amount = 10, array $meta = array() )
 * @method static WC_Coupon create_renewal_fee_coupon( string $code = 'dummycoupon', int $amount = 10, array $meta = array() )
 * @method static WC_Coupon create_renewal_cart_coupon( string $code = 'dummycoupon', int $amount = 10, array $meta = array() )
 */
class WCS_Helper_Coupon extends WC_Helper_Coupon {

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
		$type   = str_replace( array( 'create_', '_coupon' ), '', $name );
		$code   = isset( $arguments[0] ) ? $arguments[0] : 'dummycoupon';
		$amount = isset( $arguments[1] ) ? $arguments[1] : 10;
		$meta   = isset( $arguments[2] ) ? (array) $arguments[2] : array();
		$meta   = wp_parse_args( $meta, array(
			'discount_type' => $type,
			'coupon_amount' => $amount,
		) );

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
	public static function create_coupon( $coupon_code = 'dummycoupon', $meta = array() ) {
		// Insert post.
		$coupon_id = wp_insert_post( array(
			'post_title'   => $coupon_code,
			'post_type'    => 'shop_coupon',
			'post_status'  => 'publish',
			'post_excerpt' => 'This is a dummy coupon',
		) );

		$meta = wp_parse_args( $meta, array(
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
			'product_categories'         => array(),
			'exclude_product_categories' => array(),
			'minimum_amount'             => '',
			'maximum_amount'             => '',
			'customer_email'             => array(),
			'usage_count'                => '0',
		) );

		// Update meta.
		foreach ( $meta as $key => $value ) {
			update_post_meta( $coupon_id, $key, $value );
		}

		return new WC_Coupon( $coupon_code );
	}
}
