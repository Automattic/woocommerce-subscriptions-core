<?php
/**
 * WooCommerce Compatibility functions
 *
 * Functions to take advantage of APIs added to new versions of WooCommerce while maintaining backward compatibility.
 *
 * @author 		Prospress
 * @category 	Core
 * @package 	WooCommerce Subscriptions/Functions
 * @version     2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Display a tooltip in the WordPress administration area.
 *
 * Uses wc_help_tip() when WooCommerce 2.5+ is active, otherwise it manually prints the HTML for a tooltip.
 *
 * @param string $tip The content to display in the tooltip.
 * @since  2.1.0
 * @return string
 */
function wcs_help_tip( $tip, $allow_html = false ) {

	if ( function_exists( 'wc_help_tip' ) ) {

		$help_tip = wc_help_tip( $tip, $allow_html );

	} else {

		if ( $allow_html ) {
			$tip = wc_sanitize_tooltip( $tip );
		} else {
			$tip = esc_attr( $tip );
		}

		$help_tip = sprintf( '<img class="help_tip" data-tip="%s" src="%s/assets/images/help.png" height="16" width="16" />', $tip, esc_url( WC()->plugin_url() ) );
	}

	return $help_tip;
}

/**
 * Access an object's property in a way that is compatible with CRUD and non-CRUD APIs for different versions of WooCommerce.
 *
 * We don't want to force the use of a custom legacy class for orders, similar to WC_Subscription_Legacy, because 3rd party
 * code may expect the object type to be WC_Order with strict type checks.
 *
 * @param WC_Order|WC_Product|WC_Subscription $object The object whose property we want to access.
 * @param string $property The property name.
 * @param string $single Whether to return just the first piece of meta data with the given property key, or all meta data.
 * @param mixed $default The value to return if no value value is found.
 * @since  2.1.4
 * @return mixed
 */
function wcs_get_objects_property( $object, $property, $single = 'single', $default = null ) {

	$value = $default;

	switch ( $property ) {

		case 'name' : // the replacement for post_title added in 2.7
			if ( WC_Subscriptions::is_woocommerce_pre( '2.7' ) ) {
				$value = $object->post->post_title;
			} else { // WC 2.7+
				$value = $object->get_name();
			}
			break;

		case 'post' :
			if ( WC_Subscriptions::is_woocommerce_pre( '2.7' ) ) {
				$value = $object->post;
			} else { // WC 2.7+
				// In order to keep backwards compatibility it's required to use the parent data for variations.
				if ( method_exists( $object, 'is_type' ) && $object->is_type( 'variation' ) ) {
					$value = get_post( $object->get_parent_id() );
				} else {
					$value = get_post( $object->get_id() );
				}
			}
			break;

		case 'post_status' :
			$value = wcs_get_objects_property( $object, 'post' )->post_status;
			break;

		case 'parent' :
		case 'parent_id' :
			if ( method_exists( $object, 'get_parent_id' ) ) { // WC 2.7+
				$value = $object->get_parent_id();
			} else { // WC 2.1-2.6
				$value = $object->get_parent();
			}
			break;

		case 'variation_data' :
			if ( function_exists( 'wc_get_product_variation_attributes' ) ) { // WC 2.7+
				$value = wc_get_product_variation_attributes( $object->get_id() );
			} else {
				$value = $object->$property;
			}
			break;

		case 'downloads' :
			if ( method_exists( $object, 'get_downloads' ) ) { // WC 2.7+
				$value = $object->get_downloads();
			} else {
				$value = $object->get_files();
			}
			break;

		case 'order_version' :
		case 'version' :
			if ( method_exists( $object, 'get_version' ) ) { // WC 2.7+
				$value = $object->get_version();
			} else { // WC 2.1-2.6
				$value = $object->order_version;
			}
			break;

		case 'order_currency' :
		case 'currency' :
			if ( method_exists( $object, 'get_currency' ) ) { // WC 2.7+
				$value = $object->get_currency();
			} else { // WC 2.1-2.6
				$value = $object->get_order_currency();
			}
			break;

		case 'date_created' :
		case 'order_date' :
		case 'date' :
			if ( method_exists( $object, 'get_date_created' ) ) { // WC 2.7+

				$value = date( 'Y-m-d H:i:s', $object->get_date_created() );

			} else { // WC 2.1-2.6

				if ( '0000-00-00 00:00:00' == $object->post->post_date_gmt ) {
					$value = get_gmt_from_date( $object->post->post_date );
				} else {
					$value = $object->post->post_date_gmt;
				}
			}
			break;

		case 'date_paid' :
			if ( method_exists( $object, 'get_date_paid' ) ) { // WC 2.7+

				$value = date( 'Y-m-d H:i:s', $object->get_date_paid() );

			} else { // WC 2.1-2.6

				if ( isset( $object->paid_date ) ) {
					$value = get_gmt_from_date( $object->paid_date );
				} elseif ( '0000-00-00 00:00:00' == $object->post->post_date_gmt ) {
					$value = get_gmt_from_date( $object->post->post_date );
				} else {
					$value = $object->post->post_date_gmt;
				}
			}
			break;

		default :

			$function_name = 'get_' . $property;

			if ( method_exists( $object, $function_name ) ) {
				$value = $object->$function_name();
			} elseif ( isset( $object->$property ) ) {
				$value = $object->$property;
			} else {

				// If we don't have a method for this specific property, but we are using WC 2.7, it may be set as meta data on the object so check if we can use that
				if ( method_exists( $object, 'get_meta' ) ) {
					if ( 'single' === $single ) {
						$value = $object->get_meta( $property, true );
					} else {
						$value = $object->get_meta( $property, false );
					}
				} elseif ( metadata_exists( 'post', wcs_get_objects_property( $object, 'id' ), '_' . $property ) ) {
					// If we couldn't find a property or function, fallback to using post meta as that's what many __get() methods in WC < 2.7 did
					if ( 'single' === $single ) {
						$value = get_post_meta( wcs_get_objects_property( $object, 'id' ), '_' . $property, true );
					} else {
						// Get all the meta values
						$value = get_post_meta( wcs_get_objects_property( $object, 'id' ), '_' . $property, false );
					}
				}
			}
			break;
	}

	return $value;
}

/**
 * Set an object's property in a way that is compatible with CRUD and non-CRUD APIs for different versions of WooCommerce.
 *
 * @param WC_Order|WC_Product|WC_Subscription $object The object whose property we want to access.
 * @param string $key The meta key name without '_' prefix
 * @param mixed $value The data to set as the value of the meta
 * @param string $save Whether to write the data to the database or not. Use 'save' to write to the database, anything else to only update it in memory.
 * @param int $meta_id The meta ID of exiting meta data if you wish to overwrite an existing piece of meta.
 * @param bool|string $prefix An optional prefix to add to the $key. Default '_'. Set to boolean false to have no prefix added.
 * @since  2.1.4
 * @return mixed
 */
function wcs_set_objects_property( &$object, $key, $value, $save = 'save', $meta_id = '', $prefix = '_' ) {

	if ( 'name' === $key ) { // the replacement for post_title added in 2.7

		if ( method_exists( $object, 'set_name' ) ) { // WC 2.7+
			$object->set_name( $value );
		} else {
			$object->post->post_title = $value;
		}
	} elseif ( method_exists( $object, 'update_meta_data' ) ) { // WC 2.7+
		$object->update_meta_data( $key, $value, $meta_id );
	} else {
		$object->$key = $value;
	}

	// Save the data
	if ( 'save' === $save ) {
		if ( method_exists( $object, 'save' ) ) { // WC 2.7+
			$object->save();
		} elseif ( 'name' === $key ) { // the replacement for post_title added in 2.7, need to update post_title not post meta
			wp_update_post( array( 'ID' => wcs_get_objects_property( $object, 'id' ), 'post_title' => $value ) );
		} else {

			if ( false !== $prefix ) {
				$key = ( substr( $key, 0, strlen( $prefix ) ) != $prefix ) ? $prefix . $key : $key;
			}

			if ( ! empty( $meta_id ) ) {
				update_metadata_by_mid( 'post', $meta_id, $value, $key );
			} else {
				update_post_meta( wcs_get_objects_property( $object, 'id' ), $key, $value );
			}
		}
	}
}

/**
 * Delete an object's property in a way that is compatible with CRUD and non-CRUD APIs for different versions of WooCommerce.
 *
 * @param WC_Order|WC_Product|WC_Subscription $object The object whose property we want to access.
 * @param string $key The meta key name without '_' prefix
 * @param mixed $value The data to set as the value of the meta
 * @param string $save Whether to save the data or not, 'save' to save the data, otherwise it won't be saved.
 * @since  2.1.4
 * @return mixed
 */
function wcs_delete_objects_property( &$object, $key, $save = 'save', $meta_id = '' ) {

	if ( ! empty( $meta_id ) && method_exists( $object, 'delete_meta_data_by_mid' ) ) {
		$object->delete_meta_data_by_mid( $meta_id );
	} elseif ( method_exists( $object, 'delete_meta_data' ) ) {
		$object->delete_meta_data( $key );
	} elseif ( isset( $object->$key ) ) {
		unset( $object->$key );
	}

	// Save the data
	if ( 'save' === $save ) {
		if ( method_exists( $object, 'save' ) ) { // WC 2.7+
			$object->save();
		} elseif ( ! empty( $meta_id ) ) {
			delete_metadata_by_mid( 'post', $meta_id );
		} else {
			delete_post_meta( wcs_get_objects_property( $object, 'id' ), '_' . $key );
		}
	}
}

