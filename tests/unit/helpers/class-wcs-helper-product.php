<?php
/**
 * WC Subscriptions Helper Product.
 *
 * @package WooCommerce/SubscriptionsCore/Tests/Helper
 */

/**
 * Class WCS_Helper_Product
 *
 * This helper class should ONLY be used for unit tests!
 */
class WCS_Helper_Product {

	/**
	 * Create a simple subscription product
	 *
	 * @param array $meta_filter
	 * @param array $post_meta
	 *
	 * @return WC_Product_Subscription|false
	 */
	public static function create_simple_subscription_product( $meta_filters = [], $post_filters = [] ) {
		$default_meta_args = [
			'stock_status'                   => 'instock',
			'downloadable'                   => 'no',
			'virtual'                        => 'no',
			'sold_individually'              => 'no',
			'back_orders'                    => 'no',
			'subscription_payment_sync_date' => 0,
			'subscription_price'             => 10,
			'subscription_period'            => 'month',
			'subscription_period_interval'   => 1,
			'subscription_trial_period'      => 'day',
			'subscription_length'            => 0,
			'subscription_trial_length'      => 0,
			'subscription_limit'             => 'no',
		];

		$meta_data         = wp_parse_args( $meta_filters, $default_meta_args );
		$default_post_args = [
			'post_status' => 'publish',
			'post_type'   => 'product',
			'post_title'  => 'Monthly WooNinja Goodies',
		];

		$post_data  = wp_parse_args( $post_filters, $default_post_args );
		$product_id = wp_insert_post( $post_data );

		if ( is_wp_error( $product_id ) ) {
			return false;
		}

		foreach ( $meta_data as $meta_key => $meta_value ) {
			update_post_meta( $product_id, '_' . $meta_key, $meta_value );
		}

		wp_set_object_terms( $product_id, 'subscription', 'product_type' );

		self::clear_product_cache( $product_id );

		return wc_get_product( $product_id );
	}

	/**
	 * Create a variable subscription product
	 *
	 * @param string $return_product The product object to return. Default is 'variable', but can also be 'variation' to return the variation.
	 *
	 * @return WC_Abstract_Product
	 */
	public static function create_variable_subscription_product( $return_product = 'variable' ) {
		global $wpdb;

		// Create all attribute related things and a product
		$attribute_data = self::create_attribute();
		$product_id     = wp_insert_post(
			[
				'post_title'  => 'Dummy Product',
				'post_type'   => 'product',
				'post_status' => 'publish',
			]
		);

		// Set it as variable.
		wp_set_object_terms( $product_id, 'variable-subscription', 'product_type' );

		// Price related meta
		update_post_meta( $product_id, '_price', '10' );
		update_post_meta( $product_id, '_min_variation_price', '10' );
		update_post_meta( $product_id, '_max_variation_price', '15' );
		update_post_meta( $product_id, '_min_variation_regular_price', '10' );
		update_post_meta( $product_id, '_max_variation_regular_price', '15' );

		// General meta
		update_post_meta( $product_id, '_sku', 'DUMMY SKU' );
		update_post_meta( $product_id, '_manage_stock', 'no' );
		update_post_meta( $product_id, '_tax_status', 'taxable' );
		update_post_meta( $product_id, '_downloadable', 'no' );
		update_post_meta( $product_id, '_virtual', 'no' );
		update_post_meta( $product_id, '_stock_status', 'instock' );

		// Attributes
		update_post_meta( $product_id, '_default_attributes', [] );
		update_post_meta(
			$product_id,
			'_product_attributes',
			[
				'pa_size' => [
					'name'         => 'pa_size',
					'value'        => '',
					'position'     => '1',
					'is_visible'   => 0,
					'is_variation' => 1,
					'is_taxonomy'  => 1,
				],
			]
		);

		// Link the product to the attribute
		if ( isset( $attribute_data['term_ids'] ) ) {
			wp_set_object_terms( $product_id, $attribute_data['term_ids'], $attribute_data['attribute_taxonomy'] );
		} else {
			$wpdb->insert(
				$wpdb->prefix . 'term_relationships',
				[
					'object_id'        => $product_id,
					'term_taxonomy_id' => $attribute_data['term_taxonomy_id'],
					'term_order'       => 0,
				]
			);
		}

		// Create the variation
		$variation_id = wp_insert_post(
			[
				'post_title'  => 'Variation #' . ( $product_id + 1 ) . ' of Dummy Product',
				'post_type'   => 'product_variation',
				'post_parent' => $product_id,
				'post_status' => 'publish',
				'menu_order'  => 1,
				'post_date'   => gmdate( 'Y-m-d H:i:s', time() - 30 ), // Makes sure post dates differ if super quick.
			]
		);

		// Price related meta
		update_post_meta( $variation_id, '_price', '10' );
		update_post_meta( $variation_id, '_regular_price', '10' );

		// General meta
		update_post_meta( $variation_id, '_sku', 'DUMMY SKU VARIABLE SMALL' );
		update_post_meta( $variation_id, '_manage_stock', 'no' );
		update_post_meta( $variation_id, '_downloadable', 'no' );
		update_post_meta( $variation_id, '_virtual', 'no' );
		update_post_meta( $variation_id, '_stock_status', 'instock' );

		wp_set_object_terms( $variation_id, 'variation', 'product_type' );

		// Attribute meta
		update_post_meta( $variation_id, 'attribute_pa_size', 'small' );
		self::clear_product_cache( $variation_id );

		// Create the variation
		$variation_id = wp_insert_post(
			[
				'post_title'  => 'Variation #' . ( $product_id + 2 ) . ' of Dummy Product',
				'post_type'   => 'product_variation',
				'post_parent' => $product_id,
				'post_status' => 'publish',
				'menu_order'  => 2,
			]
		);

		// Price related meta
		update_post_meta( $variation_id, '_price', '15' );
		update_post_meta( $variation_id, '_regular_price', '15' );

		// General meta
		update_post_meta( $variation_id, '_sku', 'DUMMY SKU VARIABLE LARGE' );
		update_post_meta( $variation_id, '_manage_stock', 'no' );
		update_post_meta( $variation_id, '_downloadable', 'no' );
		update_post_meta( $variation_id, '_virtual', 'no' );
		update_post_meta( $variation_id, '_stock_status', 'instock' );

		// Attribute meta
		update_post_meta( $variation_id, 'attribute_pa_size', 'large' );

		// Add the variation meta to the main product
		update_post_meta( $product_id, '_max_price_variation_id', $variation_id );
		wp_set_object_terms( $variation_id, 'variation', 'product_type' );

		self::clear_product_cache( $product_id );
		self::clear_product_cache( $variation_id );

		return 'variable' === $return_product ? wc_get_product( $product_id ) : wc_get_product( $variation_id );
	}

	/**
	 * Create a simple subscription product and group it within a group product.
	 *
	 * @param string $return_product The product object to return. Default is 'simple' to return the simple product, but can also be 'group' to return the group.
	 *
	 * @return WC_Abstract_Product
	 */
	public static function create_grouped_subscription_product( $return_product = 'simple' ) {
		// Create the product
		$product = wp_insert_post(
			[
				'post_title'  => 'Dummy Grouped Product',
				'post_type'   => 'product',
				'post_status' => 'publish',
			]
		);

		$simple_product_1 = self::create_simple_subscription_product();
		$simple_product_2 = self::create_simple_subscription_product();

		update_post_meta( $product, '_sku', 'DUMMY GROUPED SKU' );
		update_post_meta( $product, '_manage_stock', 'no' );
		update_post_meta( $product, '_tax_status', 'taxable' );
		update_post_meta( $product, '_downloadable', 'no' );
		update_post_meta( $product, '_virtual', 'no' );
		update_post_meta( $product, '_stock_status', 'instock' );

		// Set the subscription product grouped relationship in a version compatible way.
		update_post_meta( $product, '_children', [ $simple_product_1->get_id(), $simple_product_2->get_id() ] );

		wp_set_object_terms( $product, 'grouped', 'product_type' );

		self::clear_product_cache( $product );
		self::clear_product_cache( $simple_product_1->get_id() );

		if ( 'simple' === $return_product ) {
			return wc_get_product( $simple_product_1 );
		} else {
			return wc_get_product( $product );
		}
	}

	/**
	 * Clear product caches after artificially creating the record.
	 * This would normally be done by `WC_Product_Data_Store_CPT::create` and `WC_Product_Data_Store_CPT::update`.
	 *
	 * @param int $product_id The product post ID.
	 */
	private static function clear_product_cache( $product_id ) {
		wc_delete_product_transients( $product_id );

		if ( class_exists( 'WC_Cache_Helper' ) && method_exists( 'WC_Cache_Helper', 'invalidate_cache_group' ) ) {
			WC_Cache_Helper::invalidate_cache_group( 'product_' . $product_id );
		}
	}

	/**
	 * Create a dummy attribute.
	 *
	 * @param string $raw_name Name of attribute to create.
	 * @param array  $terms    Terms to create for the attribute.
	 *
	 * @return array
	 */
	public static function create_attribute( $raw_name = 'size', $terms = [ 'small' ] ) {
		global $wc_product_attributes;

		// Make sure caches are clean.
		delete_transient( 'wc_attribute_taxonomies' );
		WC_Cache_Helper::invalidate_cache_group( 'woocommerce-attributes' );

		// These are exported as labels, so convert the label to a name if possible first.
		$attribute_labels = wp_list_pluck( wc_get_attribute_taxonomies(), 'attribute_label', 'attribute_name' );
		$attribute_name   = array_search( $raw_name, $attribute_labels, true );

		if ( ! $attribute_name ) {
			$attribute_name = wc_sanitize_taxonomy_name( $raw_name );
		}

		$attribute_id = wc_attribute_taxonomy_id_by_name( $attribute_name );

		if ( ! $attribute_id ) {
			$taxonomy_name = wc_attribute_taxonomy_name( $attribute_name );

			// Degister taxonomy which other tests may have created...
			unregister_taxonomy( $taxonomy_name );

			$attribute_id = wc_create_attribute(
				[
					'name'         => $raw_name,
					'slug'         => $attribute_name,
					'type'         => 'select',
					'order_by'     => 'menu_order',
					'has_archives' => 0,
				]
			);

			// Register as taxonomy.
			register_taxonomy(
				$taxonomy_name,
				apply_filters( 'woocommerce_taxonomy_objects_' . $taxonomy_name, [ 'product' ] ),
				apply_filters(
					'woocommerce_taxonomy_args_' . $taxonomy_name,
					[
						'labels'       => [
							'name' => $raw_name,
						],
						'hierarchical' => false,
						'show_ui'      => false,
						'query_var'    => true,
						'rewrite'      => false,
					]
				)
			);

			// Set product attributes global.
			$wc_product_attributes = [];

			foreach ( wc_get_attribute_taxonomies() as $taxonomy ) {
				$wc_product_attributes[ wc_attribute_taxonomy_name( $taxonomy->attribute_name ) ] = $taxonomy;
			}
		}

		$attribute = wc_get_attribute( $attribute_id );
		$return    = [
			'attribute_name'     => $attribute->name,
			'attribute_taxonomy' => $attribute->slug,
			'attribute_id'       => $attribute_id,
			'term_ids'           => [],
		];

		foreach ( $terms as $term ) {
			$result = term_exists( $term, $attribute->slug );

			if ( ! $result ) {
				$result = wp_insert_term( $term, $attribute->slug );
			}
			$return['term_ids'][] = $result['term_id'];
		}

		return $return;
	}
}
