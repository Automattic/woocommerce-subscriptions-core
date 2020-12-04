<?php
/**
 * Class for integrating with WooCommerce Blocks
 *
 * @package WooCommerce Subscriptions
 * @author  WooCommerce
 * @since   WCBLOCKS-DEV
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
