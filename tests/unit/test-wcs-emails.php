<?php


class WCS_Subscriptions_Email_Test extends WP_UnitTestCase {
	protected $emails = [];

	public function setUp() {
		add_filter( 'wp_mail', array( $this, 'email_catcher' ), 1, 1 );

		if ( ! defined( 'WCS_FORCE_EMAIL' ) ) {
			define( 'WCS_FORCE_EMAIL', 1 );
		}

		WC_Emails::init_transactional_emails();
		WC_Subscriptions_Email::hook_transactional_emails();
	}

	public function before() {
		$this->emails = [];
	}

	public function email_catcher( array $email_args ) {
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
		$email          = uniqid( true ) . '@gmail.com';
		$order_1        = WCS_Helper_Subscription::create_order( array(), compact( 'email' ) );
		$order_id       = wcs_get_objects_property( $order_1, 'id' );
		$subscription_1 = WCS_Helper_Subscription::create_subscription(
			array(
				'order_id' => $order_id,
				'status'   => 'active',
			)
		);

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
		$this->assertRegexp( '@New.+order@', $admin['subject'] );
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
			// These are causing issues, disabled for now
			// 'pending'    => true,
			// 'on-hold'    => true,
			'failed'     => false,
			'cancelled'  => false,
			'refunded'   => false,
			'processing' => false,
			'completed'  => false,
		);

		foreach ( $status_changes_from as $transition_from => $email_expected ) {

			$renewal->set_status( $transition_from );
			$renewal->save();

			// Reset the listener. We don't want to check the set up status transition.
			$listener->reset();

			$renewal->set_status( 'failed' );
			$renewal->save();

			$test_name = sprintf( 'Status change %s => failed', $transition_from );

			// Check if the failed email was sent or not sent.
			$this->assertEquals( $email_expected, $listener->has_sent( 'admin' ), 'Admin Emails sent: ' . $test_name );

			// Ensure no other admin email was sent.
			$this->assertNull(
				$listener->get_untracked( 'admin' ),
				$test_name . ' No unexpected admin emails should be sent.'
			);
		}

		// Reattach the retry hooks if they were previously removed.
		if ( $reattach_retry_functionality ) {
			add_action( 'woocommerce_order_status_failed', 'WCS_Retry_Email::maybe_detach_email', 9 );
			add_action( 'woocommerce_order_status_changed', 'WCS_Retry_Email::maybe_reattach_email', 100, 3 );
		}
	}
}
