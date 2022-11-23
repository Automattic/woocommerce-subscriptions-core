<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for managing caches of post meta
 *
 * @version  1.0.0 - Migrated from WooCommerce Subscriptions v2.3.0
 * @category Class
 * @author   Prospress
 */
class WCS_Post_Meta_Cache_Manager extends WCS_Object_Data_Cache_Manager {

	/** @var string The post type this cache manage acts on. */
	protected $post_type;

	/** @var array The post meta keys this cache manager should act on. */
	protected $meta_keys;

	/**
	 * Constructor
	 *
	 * @param string The post type this cache manage acts on.
	 * @param array The post meta keys this cache manager should act on.
	 */
	public function __construct( $post_type, $meta_keys ) {
		wcs_deprecated_function( 'WCS_Post_Meta_Cache_Manager::__construct( $post_type, $meta_keys )', '5.2.0', 'WCS_Object_Data_Cache_Manager::__construct( $type, $meta_keys )' );

		parent::__construct( $post_type, $meta_keys );
	}
}
