<?php
/**
 * Subscriptions Custom Order Item Manager
 *
 * @author   Prospress
 * @since    2.6.0
 */
class WCS_Custom_Order_Item_Manager {

	/**
	 * Initialise class hooks & filters when the file is loaded
	 *
	 * @since 2.6.0
	 */
	public static function init() {

		// Add extra (say, removed_line_items ) group
		add_filter( 'woocommerce_order_type_to_group', array( __CLASS__, 'add_extra_groups' ) );

		// Map the classname for newer items (say, for a removed line item as WC_Order_Item_Product )
		add_filter( 'woocommerce_get_order_item_classname', array( __CLASS__, 'map_classname_for_extra_items' ), 10, 2 );
	}

	/**
	 * Adds extra groups
	 * - 'removed_line_items group' for the type 'line_item_removed'
	 *
	 * @param array $type_to_group_list Existing list of types and their groups
	 * @return array $type_to_group_list
	 * @since 2.6.0
	 */
	public static function add_extra_groups( $type_to_group_list ) {
		$type_to_group_list['line_item_removed'] = 'removed_line_items';
		return $type_to_group_list;
	}

	/**
	 * Maps the classname for extra items
	 *  - a removed line item as WC_Order_Item_Product
	 *
	 * @param string $classname
	 * @param string $item_type
	 * @return string $classname
	 * @since 2.6.0
	 */
	public static function map_classname_for_extra_items( $classname, $item_type ) {
		if ( 'line_item_removed' === $item_type ) {
			return 'WC_Order_Item_Product';
		}
		return $classname;
	}
}
