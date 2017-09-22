<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Subscription Coupons.
 *
 * Extends the WC_Coupon class with Subscription-specific coupon functionality.
 *
 * @category Class
 * @author   Prospress
 */
class WCS_Coupon extends WC_Coupon {

	/**
	 * Custom properties to merge with native WC_Coupon data.
	 *
	 * @var array
	 */
	protected $extra_data = array(
		'wcs_number_renewals' => 0,
	);

	/**
	 * WCS_Coupon constructor.
	 *
	 * We're including the parent constructor code because we need to specify the correct
	 * data store type. If we were to call parent::__construct(), then we would end
	 * up with the 'coupon' type instead of the 'wcs_coupon' type.
	 *
	 * @param mixed $data Coupon data, object, ID or code.
	 */
	public function __construct( $data = '' ) {
		WC_Data::__construct( $data );

		if ( $data instanceof WC_Coupon ) {
			$this->set_id( absint( $data->get_id() ) );
		} elseif ( $coupon = apply_filters( 'woocommerce_get_shop_coupon_data', false, $data ) ) {
			$this->read_manual_coupon( $data, $coupon );

			return;
		} elseif ( is_int( $data ) && 'shop_coupon' === get_post_type( $data ) ) {
			$this->set_id( $data );
		} elseif ( ! empty( $data ) ) {
			$id = wc_get_coupon_id_by_code( $data );

			// Need to support numeric strings for backwards compatibility.
			if ( ! $id && 'shop_coupon' === get_post_type( $data ) ) {
				$this->set_id( $data );
			} else {
				$this->set_id( $id );
				$this->set_code( $data );
			}
		} else {
			$this->set_object_read( true );
		}

		$this->data_store = WC_Data_Store::load( 'wcs_coupon' );
		if ( $this->get_id() > 0 ) {
			$this->data_store->read( $this );
		}
	}

	/**
	 * Set the number of renewals for the coupon.
	 *
	 * @author Jeremy Pry
	 *
	 * @param int $value The number of renewals to set.
	 */
	public function set_wcs_number_renewals( $value ) {
		$this->set_prop( 'wcs_number_renewals', absint( $value ) );
	}

	/**
	 * Get the number of renewals for the coupon.
	 *
	 * @author Jeremy Pry
	 *
	 * @param string $context The context to retrieve.
	 *
	 * @return int
	 */
	public function get_wcs_number_renewals( $context = 'view' ) {
		return $this->get_prop( 'wcs_number_renewals', $context );
	}
}
