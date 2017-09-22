<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Subscription Coupon Data Store: Stored in CPT.
 *
 * Extends WC_Coupon_Data_Store_CPT to make sure the subscription-specific meta data is read/updated.
 *
 * @category Class
 * @author   Prospress
 */
class WCS_Coupon_Data_Store_CPT extends WC_Coupon_Data_Store_CPT implements WC_Coupon_Data_Store_Interface, WC_Object_Data_Store_Interface {

	/**
	 * Subscription-specific coupon meta keys.
	 *
	 * @var array
	 */
	protected $wcs_internal_meta_keys = array(
		'wcs_number_renewals',
	);

	/**
	 * Subscription-specific coupon data mapping.
	 *
	 * Takes the form of meta_key => prop_key.
	 *
	 * @var array
	 */
	protected $wcs_meta_keys_to_props = array(
		'_wcs_number_renewals' => 'wcs_number_renewals',
	);

	/**
	 * WCS_Coupon_Data_Store_CPT constructor.
	 */
	public function __construct() {
		$this->internal_meta_keys = array_merge( $this->internal_meta_keys, $this->wcs_internal_meta_keys );
	}

	/**
	 * Read a coupon.
	 *
	 * @author Jeremy Pry
	 *
	 * @param WC_Data $coupon
	 */
	public function read( &$coupon ) {
		// We have to let the parent method go first, ore else our data will be reset.
		parent::read( $coupon );

		$coupon->set_object_read( false );
		$coupon->set_props( array(
			'wcs_number_renewals' => get_post_meta( $coupon->get_id(), '_wcs_number_renewals', true ),
		) );
		$coupon->set_object_read( true );
	}

	/**
	 * Update a coupon in the database.
	 *
	 * @author Jeremy Pry
	 *
	 * @param WCS_Coupon $coupon
	 */
	public function update( &$coupon ) {
		$coupon->save_meta_data();
		$this->update_post_meta( $coupon );
		$coupon->apply_changes();
		parent::update( $coupon );
	}

	/**
	 *
	 *
	 * @author Jeremy Pry
	 *
	 * @param WCS_Coupon $coupon
	 */
	private function update_post_meta( $coupon ) {
		$updated_props   = array();
		$props_to_update = $this->get_props_to_update( $coupon, $this->wcs_meta_keys_to_props );
		foreach ( $props_to_update as $meta_key => $prop ) {
			$value   = $coupon->{"get_$prop"}( 'edit' );
			$updated = false;
			switch ( $prop ) {
				case 'wcs_number_renewals':
					$updated = update_post_meta( $coupon->get_id(), $meta_key, $value );
					break;
			}

			if ( $updated ) {
				$updated_props[] = $prop;
			}
		}

		do_action( 'woocommerce_coupon_object_updated_props', $coupon, $updated_props );
	}
}
