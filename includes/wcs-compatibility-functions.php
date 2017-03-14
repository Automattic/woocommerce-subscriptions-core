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
 * A note on dates: in WC 2.7+, dates are returned a timestamps in the site's timezone :upside_down_face:. In WC < 2.7, they were
 * returned as MySQL strings in the site's timezone. We return them from here as MySQL strings in UTC timezone because that's how
 * dates are used in Subscriptions in almost all cases, for sanity's sake.
 *
 * @param WC_Order|WC_Product|WC_Subscription $object The object whose property we want to access.
 * @param string $property The property name.
 * @param string $single Whether to return just the first piece of meta data with the given property key, or all meta data.
 * @param mixed $default (optional) The value to return if no value is found - defaults to single -> null, multiple -> array()
 * @since  2.1.4
 * @return mixed
 */
function wcs_get_objects_property( $object, $property, $single = 'single', $default = null ) {

	$prefixed_key = wcs_maybe_prefix_key( $property );

	$value = ! is_null( $default ) ? $default : ( ( 'single' == $single ) ? null : array() );

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

		case 'parent_id' :
			if ( method_exists( $object, 'get_parent_id' ) ) { // WC 2.7+ or an instance of WC_Product_Subscription_Variation_Legacy with WC < 2.7
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
				$value = date( 'Y-m-d H:i:s', $object->get_date_created() ); // This is in site time as a timestamp :sob:
			} else { // WC 2.1-2.6
				$value = $object->post->post_date; // Because $object->get_date_created() is in site time, we also need to use site time here :sob::sob::sob:
			}
			break;

		case 'date_paid' :
			if ( method_exists( $object, 'get_date_paid' ) ) { // WC 2.7+
				$value = date( 'Y-m-d H:i:s', $object->get_date_paid() ); // This is in site time as a timestamp :sob:
			} elseif ( isset( $object->paid_date ) ) { // WC 2.1-2.6
				$value = $object->paid_date;
			}
			break;

		default :

			$function_name = 'get_' . $property;

			if ( is_callable( array( $object, $function_name ) ) ) {
				$value = $object->$function_name();
			} else {

				// If we don't have a method for this specific property, but we are using WC 2.7, it may be set as meta data on the object so check if we can use that
				if ( method_exists( $object, 'get_meta' ) ) {
					if ( $object->has_meta( $prefixed_key ) ) {
						if ( 'single' === $single ) {
							$value = $object->get_meta( $prefixed_key, true );
						} else {
							// WC_Data::get_meta() returns an array of stdClass objects with id, key & value properties when meta is available
							$value = wp_list_pluck( $object->get_meta( $prefixed_key, false ), 'value' );
						}
					}
				} elseif ( isset( $object->$property ) ) { // WC < 2.7
					$value = $object->$property;
				} elseif ( metadata_exists( 'post', wcs_get_objects_property( $object, 'id' ), $prefixed_key ) ) {
					// If we couldn't find a property or function, fallback to using post meta as that's what many __get() methods in WC < 2.7 did
					if ( 'single' === $single ) {
						$value = get_post_meta( wcs_get_objects_property( $object, 'id' ), $prefixed_key, true );
					} else {
						// Get all the meta values
						$value = get_post_meta( wcs_get_objects_property( $object, 'id' ), $prefixed_key, false );
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
function wcs_set_objects_property( &$object, $key, $value, $save = 'save', $meta_id = '' ) {

	$prefixed_key = wcs_maybe_prefix_key( $key );

	if ( 'name' === $key ) { // the replacement for post_title added in 2.7

		if ( method_exists( $object, 'set_name' ) ) { // WC 2.7+
			$object->set_name( $value );
		} else {
			$object->post->post_title = $value;
		}
	} elseif ( method_exists( $object, 'update_meta_data' ) ) { // WC 2.7+
		$object->update_meta_data( $prefixed_key, $value, $meta_id );
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

			if ( ! empty( $meta_id ) ) {
				update_metadata_by_mid( 'post', $meta_id, $value, $prefixed_key );
			} else {
				update_post_meta( wcs_get_objects_property( $object, 'id' ), $prefixed_key, $value );
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

	$prefixed_key = wcs_maybe_prefix_key( $key );

	if ( ! empty( $meta_id ) && method_exists( $object, 'delete_meta_data_by_mid' ) ) {
		$object->delete_meta_data_by_mid( $meta_id );
	} elseif ( method_exists( $object, 'delete_meta_data' ) ) {
		$object->delete_meta_data( $prefixed_key );
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
			delete_post_meta( wcs_get_objects_property( $object, 'id' ), $prefixed_key );
		}
	}
}

/**
 * Check whether an order is a standard order (i.e. not a refund or subscription) in version compatible way.
 *
 * WC 2.7 has the $order->get_type() API which returns 'shop_order', while WC < 2.7 provided the $order->order_type
 * property which returned 'simple', so we need to check for both.
 *
 * @param WC_Order $order
 * @since  2.1.4
 * @return bool
 */
function wcs_is_order( $order ) {

	if ( method_exists( $order, 'get_type' ) ) {
		$is_order = ( 'shop_order' === $order->get_type() );
	} else {
		$is_order = ( 'simple' === $order->order_type );
	}

	return $is_order;
}

/**
 * Find and return the value for a deprecated property property.
 *
 * Product properties should not be accessed directly with WooCommerce 2.7+, because of that, a lot of properties
 * have been depreacted/removed in the subscription product type classes. This function centralises the handling
 * of deriving depreacted properties. This saves duplicating the __get() method in WC_Product_Subscription,
 * WC_Product_Variable_Subscription and WC_Product_Subscription_Variation.
 *
 * @param string $property
 * @param WC_Product $product
 * @since  2.1.4
 * @return mixed
 */
function wcs_product_deprecated_property_handler( $property, $product ) {

	$message_prefix = 'Product properties should not be accessed directly with WooCommerce 2.7+.';
	$function_name  = 'get_' . str_replace( 'subscription_', '', str_replace( 'subscription_period_', '', $property ) );
	$class_name     = get_class( $product );
	$value          = null;

	if ( in_array( $property, array( 'product_type', 'parent_product_type', 'limit_subscriptions', 'subscription_limit', 'subscription_payment_sync_date', 'subscription_one_time_shipping' ) ) || ( is_callable( array( 'WC_Subscriptions_Product', $function_name ) ) && false !== strpos( $property, 'subscription' ) ) ) {

		switch ( $property ) {
			case 'product_type':
				$value       = $product->get_type();
				$alternative = $class_name . '::get_type()';
				break;

			case 'parent_product_type':
				if ( $product->is_type( 'subscription_variation' ) ) {
					$value       = 'variation';
					$alternative = 'WC_Product_Variation::get_type()';
				} else {
					$value       = 'variable';
					$alternative = 'WC_Product_Variable::get_type()';
				}
				break;

			case 'limit_subscriptions':
			case 'subscription_limit':
				$value       = wcs_get_product_limitation( $product );
				$alternative = 'wcs_get_product_limitation( $product )';
				break;

			case 'subscription_one_time_shipping':
				$value       = WC_Subscriptions_Product::needs_one_time_shipping( $product );
				$alternative = 'WC_Subscriptions_Product::needs_one_time_shipping( $product )';
				break;

			case 'subscription_payment_sync_date':
				$value       = WC_Subscriptions_Synchroniser::get_products_payment_day( $product );
				$alternative = 'WC_Subscriptions_Synchroniser::get_products_payment_day( $product )';
				break;

			case 'max_variation_period':
			case 'max_variation_period_interval':
				$meta_key = '_' . $property;
				if ( '' === $product->get_meta( $meta_key ) ) {
					WC_Product_Variable::sync( $product->get_id() );
				}
				$value       = $product->get_meta( $meta_key );
				$alternative = $class_name . '::get_meta( ' . $meta_key . ' ) or wcs_get_min_max_variation_data( $product )';
				break;

			default:
				$value       = call_user_func( array( 'WC_Subscriptions_Product', $function_name ), $product );
				$alternative = sprintf( 'WC_Subscriptions_Product::%s( $product )', $function_name );
				break;
		}

		_deprecated_argument( $class_name . '::$' . $property, '2.1.4', sprintf( '%s Use %s', $message_prefix, $alternative ) );
	}

	return $value;
}
