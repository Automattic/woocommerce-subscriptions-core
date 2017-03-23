<?php

class WC_Order_Item_Pending_Switch extends WC_Order_Item_Product {
	public function get_type() {
		return 'line_item_pending_switch';
	}

	public static function init( $stores ) {
		$stores['order-item-line_item_pending_switch'] = 'WC_Order_Item_Product_Data_Store';
		return $stores;
	}
}

add_filter( 'woocommerce_data_stores', 'WC_Order_Item_Pending_Switch::init' );
