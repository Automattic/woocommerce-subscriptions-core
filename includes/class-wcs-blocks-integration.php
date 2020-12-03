<?php
/**
 * Class for integrating with WooCommerce Blocks
 *
 * @author   Rubik
 * @category Class
 * @package  WooCommerce Subscriptions
 * @since    2.2.0
 */

use Automattic\WooCommerce\Blocks\Assets\AssetDataRegistry;
class WCS_Blocks_Integration {

	protected $registry;

	public function __construct( $registry ) {
		$this->registry = $registry;
		$this->add_data();
	}

	public function add_data() {
		$this->registry->add(
			'woocommerce-subscriptions-blocks',
			'active'
		);
	}
}
