<?php

function always_return_true() {
	return true;
}

class WCS_Emails_Unit_Tests extends WCS_Unit_Test_Case {
	protected $emails;

	public function setUp() {
		$this->emails = array();
		add_filter( 'wp_mail', array( $this, 'email_catcher' ), 1, 1 );

		if ( ! defined( 'WCS_FORCE_EMAIL' ) ) {
			define( 'WCS_FORCE_EMAIL', 1 );
		}

		WC_Emails::init_transactional_emails();
		WC_Subscriptions_Email::hook_transactional_emails();
	}

	public function email_catcher(Array $email_args) {
		$this->emails[] = $email_args;
		return $email_args;
	}

	public function test_email_tests() {
		wp_mail( 'foo@prospress.com', 'hi there', 'lol' );
		$this->assertEquals( 1, count( $this->emails ) );
		$this->assertEquals( 'foo@prospress.com', $this->emails[0]['to'] );
		$this->assertEquals( 'hi there', $this->emails[0]['subject'] );
		$this->assertEquals( 'lol', $this->emails[0]['message'] );
	}

	public function test_send_email_on_complete() {
		$email = uniqid( true ) . '@gmail.com';
		$order_1 = WCS_Helper_Subscription::create_order( array(), compact( 'email' ) );
		$order_id = wcs_get_objects_property( $order_1, 'id' );
		$subscription_1 = WCS_Helper_Subscription::create_subscription( array( 'order_id' => $order_id, 'status' => 'active' ) );

		$order_1->update_status( 'completed' );

		$this->assertEquals( 2, count( $this->emails ), 'Make sure two emails are sent (to the buyer and to the site admin)' );

		$admin = $this->emails[0];
		$user  = $this->emails[1];
		if ( $this->emails[0]['to'] === $email ) {
			$user  = $this->emails[0];
			$admin = $this->emails[1];
		}

		// Check the subjects
		$this->assertRegexp( '@Your.+order.+complete@', $user['subject'] );
		$this->assertRegexp( '@New.+order@',  $admin['subject'] );
	}

	public function test_send_email_on_switch() {

		$email = uniqid( true ) . '@gmail.com';

		$variable_product   = WC_Helper_Product::create_variation_product();
		$variations          = $variable_product->get_available_variations();
		$variation_1         = array_shift( $variations );
		$variation_product_1 = wc_get_product( $variation_1['variation_id'] );
		$variation_2         = array_shift( $variations );
		$variation_product_2 = wc_get_product( $variation_2['variation_id'] );

		$order_1 = WCS_Helper_Subscription::create_order( array(), compact( 'email' ) );
		$order_id = wcs_get_objects_property( $order_1, 'id' );
		$subscription_1 = WCS_Helper_Subscription::create_subscription( array( 'order_id' => $order_id, 'status' => 'active' ) );
		$item_1 = $subscription_1->add_product( $variation_product_1, 1, array( 'variation' => $variation_1 ) );

		$order_1->update_status( 'completed' );

		$this->assertEquals( 2, count( $this->emails ), 'Make sure two emails are sent (to the buyer and to the site admin)' );
		$this->emails = array();

		$order_2 = WCS_Helper_Subscription::create_order( array(), compact( 'email' ) );
		$order_id = wcs_get_objects_property( $order_2, 'id' );
		$subscription_2 = WCS_Helper_Subscription::create_subscription( array( 'order_id' => $order_id, 'status' => 'active' ) );
		$item_2 = $subscription_2->add_product( $variation_product_2, 1, array(
			'variation' => $variation_2,
			'switched_subscription_item_id' => $item_1,
		));

		wc_add_order_item_meta( $item_2, '_switched_subscription_item_id', $item_1 );
		WCS_Related_Order_Store::instance()->add_relation( $order_2, $subscription_2, 'switch' );

		$this->assertTrue( wcs_order_contains_switch( $order_2 ) );

		$order_2->update_status( 'completed' );

		$this->assertEquals( 2, count( $this->emails ), 'Test number of sent emails (2)' );

		$to_admin = $this->emails[0];
		$to_user  = $this->emails[1];
		if ( $this->emails[0]['to'] === $email ) {
			$to_user  = $this->emails[0];
			$to_admin = $this->emails[1];
		}

		$to_user['text'] = strip_tags($to_user['message']);

		$this->assertTrue( (bool) preg_match( '@(variation_id|id)\s*:\s*' . $variation_2['variation_id'] .  '@', $to_user['text'] ), 'Make sure the new variation is mentioned in the email' );
		$this->assertFalse( (bool) preg_match( '@(variation_id|id)\s*:\s*' . $variation_1['variation_id'] .  '@', $to_user['text'] ), 'Make sure the old variation is not mentioned in the email' );
	}

	/**
	 * Test Customer Emails are sent for on-hold renewal orders.
	 *
	 * @group WCS_On_Hold_Renewal_Email
	 */
	public function test_renewal_order_transactional_emails() {
		$subscription = WCS_Helper_Subscription::create_subscription();
		$renewal      = WCS_Helper_Subscription::create_renewal_order( $subscription );

		$renewal_emails = array(
			'WCS_Email_Customer_On_Hold_Renewal_Order' => array(
				'to_status'                  => 'on-hold',
				'email_expected_from_status' => array(
					'pending'      => true,
					'failed'       => true,
					'cancelled'    => true,
					'on-hold'      => false,
					'refunded'     => false,
					'processing'   => false,
					'completed'    => false,
				),
			),
			'WCS_Email_Completed_Renewal_Order' => array(
				'to_status'                  => 'completed',
				'email_expected_from_status' => array(
					'failed'       => true,
					'cancelled'    => true,
					'on-hold'      => true,
					'pending'      => true,
					'refunded'     => true,
					'processing'   => true,
					'completed'    => false,
				),
			),
			'WCS_Email_Processing_Renewal_Order' => array(
				'to_status'                  => 'processing',
				'email_expected_from_status' => array(
					'cancelled'    => true,
					'failed'       => true,
					'on-hold'      => true,
					'pending'      => true,
					'refunded'     => false,
					'completed'    => false,
					'processing'   => false,
				),
			),
		);

		foreach ( $renewal_emails as $email => $test_data ) {
			$listener = new WCS_Email_Listener( $email );
			$listener->start_listening();

			foreach( $test_data['email_expected_from_status'] as $from_status => $email_expected ) {
				$renewal->set_status( $from_status );
				$renewal->save();

				// Reset the listener. We don't want to check the set up status transition.
				$listener->reset();

				$renewal->set_status( $test_data['to_status'] );
				$renewal->save();

				// Check if the On-hold email was sent or not sent.
				$this->assertEquals( $email_expected, $listener->has_sent( $renewal->get_billing_email() ) );

				// Ensure no other customer emails were sent.
				$this->assertFalse( $listener->has_sent_untracked( 'customer' ) );
			}
		}
	}

	/**
	 * Test Admin Emails are sent for failed renewal orders.
	 *
	 * @group WC_Email_Failed_Order
	 */
	public function test_failed_renewal_order_admin_email() {
		$subscription = WCS_Helper_Subscription::create_subscription();
		$renewal      = WCS_Helper_Subscription::create_renewal_order( $subscription );

		// For renewal failed emails, we use the default admin WC order email.
		$listener = new WCS_Email_Listener( 'WC_Email_Failed_Order' );
		$listener->start_listening();

		// Remove the functions the Retry system uses to block failed renewal order admin emails.
		// We cannot simply disable the retry system because it has already been initialised and hooks have been attached.
		$reattach_retry_functionality = remove_action( 'woocommerce_order_status_failed', 'WCS_Retry_Email::maybe_detach_email', 9 );
		remove_action( 'woocommerce_order_status_failed', 'WCS_Retry_Email::maybe_reattach_email', 100 );

		// The WC_Email_Failed_Order email is sent on pending or on-hold status transitions to failed.
		$status_changes_from = array(
			'pending'      => true,
			'on-hold'      => true,
			'failed'       => false,
			'cancelled'    => false,
			'refunded'     => false,
			'processing'   => false,
			'completed'    => false,
		);

		foreach ( $status_changes_from as $transition_from => $email_expected ) {
			$renewal->set_status( $transition_from );
			$renewal->save();

			// Reset the listener. We don't want to check the set up status transition.
			$listener->reset();

			$renewal->set_status( 'failed' );
			$renewal->save();

			// Check if the failed email was sent or not sent.
			$this->assertEquals( $email_expected, $listener->has_sent( 'admin' ) );

			// Ensure no other admin email was sent.
			$this->assertFalse( $listener->has_sent_untracked( 'admin' ) );
		}

		// Reattach the retry hooks if they were previously removed.
		if ( $reattach_retry_functionality ) {
			add_action( 'woocommerce_order_status_failed', 'WCS_Retry_Email::maybe_detach_email', 9 );
			add_action( 'woocommerce_order_status_changed', 'WCS_Retry_Email::maybe_reattach_email', 100, 3 );
		}
	}
}
