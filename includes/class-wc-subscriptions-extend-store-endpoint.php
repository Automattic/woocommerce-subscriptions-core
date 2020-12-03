<?php
/**
 * WooCommerce Subscriptions Extend Store API.
 *
 * A class to extend the store public API with subscription related data
 * for each subscription item
 *
 * @package WooCommerce Subscriptions
 * @author  WooCommerce
 * @since   WCBLOCKS-DEV
 */

use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\Blocks\Domain\Services\ExtendRestApi;
use Automattic\WooCommerce\Blocks\StoreApi\Schemas\CartItemSchema;
class WC_Subscriptions_Extend_Store_Endpoint {

	/**
	 * Stores Rest Extending instance.
	 *
	 * @var ExtendRestApi
	 */
	private static $extend;

	/**
	 * Plugin Identifier, unique to each plugin.
	 *
	 * @var string
	 */
	const IDENTIFIER = 'subscriptions';

	/**
	 * Bootstraps the class and hooks required data.
	 *
	 * @since WCBLOCKS-DEV
	 */
	public static function init() {

		self::$extend = Package::container()->get( ExtendRestApi::class );
		self::extend_store();
	}

	/**
	 * Registers the actual data into each endpoint.
	 */
	public static function extend_store() {

		self::$extend->register_endpoint_data(
			array(
				'endpoint'        => CartItemSchema::IDENTIFIER,
				'namespace'       => self::IDENTIFIER,
				'data_callback'   => array( 'WC_Subscriptions_Extend_Store_Endpoint', 'extend_cart_item_data' ),
				'schema_callback' => array( 'WC_Subscriptions_Extend_Store_Endpoint', 'extend_cart_item_schema' ),
			)
		);
	}

	/**
	 * Register subscription product data into cart/items endpoint.
	 *
	 * @param array $cart_item Current cart item data.
	 *
	 * @return array $item_data Registered data or empty array if condition is not satisfied.
	 */
	public static function extend_cart_item_data( $cart_item ) {

		$product   = $cart_item['data'];
		$item_data = array();

		if ( $product->get_type() === 'subscription' || $product->get_type() === 'subscription_variation' ) {
			$item_data = array(
				'billing_period'      => WC_Subscriptions_Product::get_period( $product ),
				'billing_interval'    => (int) WC_Subscriptions_Product::get_interval( $product ),
				'subscription_length' => (int) WC_Subscriptions_Product::get_length( $product ),
				'trial_length'        => (int) WC_Subscriptions_Product::get_trial_length( $product ),
				'trial_period'        => WC_Subscriptions_Product::get_trial_period( $product ),
				'sign_up_fees'        => self::prepare_money_response( WC_Subscriptions_Product::get_sign_up_fee( $product ), wc_get_price_decimals() ),
			);
		}

		return $item_data;
	}

	/**
	 * Register subscription product schema into cart/items endpoint.
	 *
	 *
	 * @return array $item_data Registered schema.
	 */
	public static function extend_cart_item_schema() {
		return array(
			'billing_period'      => array(
				'description' => __( 'Billing period for the subscription.', 'woocommerce-subscriptions' ),
				'type'        => 'string',
				'enum'        => array_keys( wcs_get_subscription_period_strings() ),
				'context'     => array( 'view', 'edit' ),
			),
			'billing_interval'    => array(
				'description' => __( 'The number of billing periods between subscription renewals.', 'woocommerce-subscriptions' ),
				'type'        => 'integer',
				'context'     => array( 'view', 'edit' ),
			),
			'subscription_length' => array(
				'description' => __( 'Subscription Product length.', 'woocommerce-subscriptions' ),
				'type'        => 'integer',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'trial_period'        => array(
				'description' => __( 'Subscription Product trial period.', 'woocommerce-subscriptions' ),
				'type'        => 'string',
				'enum'        => array_keys( wcs_get_subscription_period_strings() ),
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'trial_length'        => array(
				'description' => __( 'Subscription Product trial interval.', 'woocommerce-subscriptions' ),
				'type'        => 'integer',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
			'sign_up_fees'        => array(
				'description' => __( 'Subscription Product Signup fees.', 'woocommerce-subscriptions' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
		);
	}

	/**
	 * Convert monetary values from WooCommerce to string based integers, using
	 * the smallest unit of a currency.
	 *
	 * TODO: This function is copied from WooCommerce Blocks, remove it once https://github.com/woocommerce/woocommerce-gutenberg-products-block/issues/3264 is closed.
	 *
	 * @param string|float $amount Monetary amount with decimals.
	 * @param int          $decimals Number of decimals the amount is formatted with.
	 * @param int          $rounding_mode Defaults to the PHP_ROUND_HALF_UP constant.
	 * @return string      The new amount.
	 */
	protected static function prepare_money_response( $amount, $decimals = 2, $rounding_mode = PHP_ROUND_HALF_UP ) {
		return (string) intval(
			round(
				( (float) wc_format_decimal( $amount ) ) * ( 10 ** $decimals ),
				0,
				absint( $rounding_mode )
			)
		);
	}
}
