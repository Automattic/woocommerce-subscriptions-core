<?php
use Automattic\WooCommerce\Blocks\Assets\AssetDataRegistry;

/**
 * Class for integrating with WooCommerce Blocks
 *
 * @package WooCommerce Subscriptions
 * @author  WooCommerce
 * @since   WCBLOCKS-DEV
 */
class WCS_Blocks_Integration {

	/**
	 * The AssetDataRegistry that holds all of the data that should be output to the front-end.
	 * We can insert items here using the add_data method of this class.
	 *
	 * @var AssetDataRegistry Holds the data we want to output onto the front-end.
	 */
	protected $registry;

	/**
	 * WCS_Blocks_Integration constructor.
	 *
	 * @param AssetDataRegistry $registry The registry that data from this plugin should be added to.
	 */
	public function __construct( AssetDataRegistry $registry ) {
		$this->registry = $registry;
	}

	/**
	 * Populate the registry with the predefined data.
	 */
	public function add_data() {
		$this->registry->add(
			'woocommerce-subscriptions-blocks',
			'active'
		);
	}
}
