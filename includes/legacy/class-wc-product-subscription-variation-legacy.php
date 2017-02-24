<?php
/**
 * Subscription Product Variation Legacy Class
 *
 * Extends WC_Product_Subscription_Variation to provide compatibility methods when running WooCommerce < 2.7.
 *
 * @class 		WC_Product_Subscription_Variation_Legacy
 * @package		WooCommerce Subscriptions
 * @category	Class
 * @since		2.1.4
 *
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Product_Subscription_Variation_Legacy extends WC_Product_Subscription_Variation {

	/**
	 * Set default array value for WC 2.7's data property.
	 * @var array
	 */
	protected $data = array();

	/**
	 * Create a simple subscription product object.
	 *
	 * @access public
	 * @param mixed $product
	 */
	public function __construct( $product, $args = array() ) {

		parent::__construct( $product, $args = array() );

		$this->parent_product_type = $this->product_type;

		$this->product_type = 'subscription_variation';

		$this->subscription_variation_level_meta_data = array(
			'subscription_price'             => 0,
			'subscription_period'            => '',
			'subscription_period_interval'   => 'day',
			'subscription_length'            => 0,
			'subscription_trial_length'      => 0,
			'subscription_trial_period'      => 'day',
			'subscription_sign_up_fee'       => 0,
			'subscription_payment_sync_date' => 0,
		);

		$this->variation_level_meta_data = array_merge( $this->variation_level_meta_data, $this->subscription_variation_level_meta_data );
	}

	/* Copied from WC 2.6 WC_Product_Variation */

	/**
	 * __isset function.
	 *
	 * @param mixed $key
	 * @return bool
	 */
	public function __isset( $key ) {
		if ( in_array( $key, array( 'variation_data', 'variation_has_stock' ) ) ) {
			return true;
		} elseif ( in_array( $key, array_keys( $this->variation_level_meta_data ) ) ) {
			return metadata_exists( 'post', $this->variation_id, '_' . $key );
		} elseif ( in_array( $key, array_keys( $this->variation_inherited_meta_data ) ) ) {
			return metadata_exists( 'post', $this->variation_id, '_' . $key ) || metadata_exists( 'post', $this->id, '_' . $key );
		} else {
			return metadata_exists( 'post', $this->id, '_' . $key );
		}
	}

	/**
	 * Get method returns variation meta data if set, otherwise in most cases the data from the parent.
	 *
	 * @param string $key
	 * @return mixed
	 */
	public function __get( $key ) {
		if ( in_array( $key, array_keys( $this->variation_level_meta_data ) ) ) {

			$value = get_post_meta( $this->variation_id, '_' . $key, true );

			if ( '' === $value ) {
				$value = $this->variation_level_meta_data[ $key ];
			}
		} elseif ( in_array( $key, array_keys( $this->variation_inherited_meta_data ) ) ) {

			$value = metadata_exists( 'post', $this->variation_id, '_' . $key ) ? get_post_meta( $this->variation_id, '_' . $key, true ) : get_post_meta( $this->id, '_' . $key, true );

			// Handle meta data keys which can be empty at variation level to cause inheritance
			if ( '' === $value && in_array( $key, array( 'sku', 'weight', 'length', 'width', 'height' ) ) ) {
				$value = get_post_meta( $this->id, '_' . $key, true );
			}

			if ( '' === $value ) {
				$value = $this->variation_inherited_meta_data[ $key ];
			}
		} elseif ( 'variation_data' === $key ) {
			return $this->variation_data = wc_get_product_variation_attributes( $this->variation_id );

		} elseif ( 'variation_has_stock' === $key ) {
			return $this->managing_stock();

		} else {
			$value = metadata_exists( 'post', $this->variation_id, '_' . $key ) ? get_post_meta( $this->variation_id, '_' . $key, true ) : parent::__get( $key );
		}

		return $value;
	}

	/**
	 * Provide a WC 2.7 method for variations.
	 *
	 * WC < 2.7 products have a get_parent() method, but this is not equivalent to the get_parent_id() method
	 * introduced in WC 2.7, because it derives the parent from $this->post->post_parent, but for variations,
	 * $this->post refers to the parent variable object's post, so $this->post->post_parent will be 0 under
	 * normal circumstances. Becuase of that, we can rely on wcs_get_objects_property( $this, 'parent_id' )
	 * and define this get_parent_id() method for variations even when WC 2.7 is not active.
	 *
	 * @param string $key
	 * @return mixed
	 */
	public function get_parent_id() {
		return $this->id; // When WC < 2.7 is active, the ID property is the parent variable product's ID
	}
}
