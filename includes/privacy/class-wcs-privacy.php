<?php
/**
 * Privacy/GDPR related functionality which ties into WordPress functionality.
 *
 * @author   Prospress
 * @category Class
 * @package  WooCommerce Subscriptions\Privacy
 * @version  2.2.20
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WCS_Privacy extends WC_Abstract_Privacy {


	/**
	 * WCS_Privacy constructor.
	 */
	public function __construct() {
		parent::__construct( __( 'WooCommerce Subscriptions', 'woocommerce-subscriptions' ) );

		// include our exporters and erasers.
		include_once 'class-wcs-privacy-erasers.php';
		include_once 'class-wcs-privacy-exporters.php';

		$this->add_exporter( 'woocommerce-subscriptions-data', __( 'Subscriptions Data', 'woocommerce-subscriptions' ), array( 'WCS_Privacy_Exporters', 'subscription_data_exporter' ) );
		$this->add_eraser( 'woocommerce-subscriptions-data', __( 'Subscriptions Data', 'woocommerce-subscriptions' ), array( 'WCS_Privacy_Erasers', 'subscription_data_eraser' ) );
	}
}
