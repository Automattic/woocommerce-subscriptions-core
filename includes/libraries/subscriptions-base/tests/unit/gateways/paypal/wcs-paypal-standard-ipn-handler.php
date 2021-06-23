<?php

/**
 * Class WCS_PayPal_Standard_IPN_Handler_Override
 */
class WCS_PayPal_Standard_IPN_Handler_Override extends WCS_PayPal_Standard_IPN_Handler {
	public function process_ipn_request( $transaction_details ) {
		return parent::process_ipn_request( $transaction_details );
	}
}

/**
 * Class WCS_PayPal_Standard_IPN_Handler_Test
 */
class WCS_PayPal_Standard_IPN_Handler_Test extends WCS_Unit_Test_Case {
	/**
	 * @var WCS_PayPal_Standard_IPN_Handler_Override
	 */
	protected static $instance;

	/**
	 * Possible keys:
	 *  - txn_id
	 *  - txn_type
	 *  - subscr_id
	 *  - invoice
	 *
	 * @var array
	 */
	protected $transaction_details = array(
		'invoice',
	);

	/**
	 * @var string
	 */
	public $uniqid;

	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();

		self::$instance = new WCS_PayPal_Standard_IPN_Handler_Override();

		add_filter( 'wooocommerce_paypal_credentials_are_set', '__return_true' );
		add_filter( 'woocommerce_subscriptions_is_duplicate_site', '__return_false' );
	}

	public function setUp() {
		parent::setUp();

		$this->uniqid = uniqid();

		$this->transaction_details['invoice'] = 'INV-' . $this->uniqid;
		$this->transaction_details['txn_id']  = 'txn_id_' . $this->uniqid;
	}

	/**
	 * This tests the fix for issue #2549.
	 *
	 * This test is being ignore since WCS_PayPal_Standard_IPN_Handler::process_ipn_request() will kill the PHP process.
	 *
	 * @see   WCS_PayPal_Standard_IPN_Handler::process_ipn_request()
	 * @link  https://github.com/Prospress/woocommerce-subscriptions/issues/2549
	 * @group ignore
	 * @group issue_2549
	 */
	public function test_process_ipn_request_issue_2549_fix() {
		$that = $this;
		// Add identity token.
		add_filter( 'option_woocommerce_paypal_settings', function ( $settings ) use ( $that ) {
			$settings['identity_token']         = 'identity_token_' . $that->uniqid;
			$settings['sandbox_identity_token'] = 'sandbox_identity_token_' . $that->uniqid;

			return $settings;
		} );

		// Setup the txn_type.
		$this->transaction_details['txn_type']       = 'subscr_payment';
		$this->transaction_details['subscr_id']      = 'subscr_id_' . $this->uniqid;
		$this->transaction_details['payment_status'] = 'completed';

		// Create the subscription.
		$subscription = WCS_Helper_Subscription::create_subscription();
		$subscription->add_meta_data( '_paypal_subscription_id', $this->transaction_details['subscr_id'], true );
		$subscription->set_order_key( 'wc_order_' . $this->uniqid );
		$subscription->set_payment_method( 'paypal' );
		$subscription->save();

		$this->transaction_details['order_id'] = $subscription->get_id();

		// Create parent order.
		$parent_order = WCS_Helper_Subscription::create_parent_order( $subscription );
		// When PP redirects to website with identity_token enabled, the order will be set as processing.
		$parent_order->set_status( 'processing' );
		$parent_order->set_transaction_id( $this->transaction_details['txn_id'] );
		$parent_order->save();

		$this->transaction_details['mc_gross'] = $parent_order->get_total();

		// Call process_ipn_request.
		self::$instance->process_ipn_request( $this->transaction_details );

		// Now we should have a new renewal order, plus the parent order.
		$this->assertCount( 0, $subscription->get_related_orders( 'ids', 'renewal' ) );
		$this->assertCount( 1, $subscription->get_related_orders( 'ids' ) );
		$this->assertCount( 1, $subscription->get_related_orders( 'ids', 'parent' ) );

		// Reload the subscription object because a different instance would have been updated via process_ipn_request().
		$subscription = wcs_get_subscription( $subscription->get_id() );

		// Make sure the subscription is active. @see https://github.com/Prospress/woocommerce-subscriptions/issues/3096
		$this->assertTrue( $subscription->has_status( 'active' ) );
	}
}
