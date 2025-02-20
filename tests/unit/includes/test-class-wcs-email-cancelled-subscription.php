<?php

/**
 * Confirms behaviors for the Cancelled Subscription email (as sent to admins).
 *
 * @see WC_Subscription_Test which covers the setting and clearing of the requires_manual_renewal flag.
 */
class WCS_Email_Cancelled_Subscription_Test extends WP_UnitTestCase {
	/**
	 * Holds the subject field of the last email to be dispatched during test execution.
	 *
	 * @var string
	 */
	private $email_subject = '';

	/**
	 * If an email was dispatched during test execution.
	 *
	 * @var bool
	 */
	private $email_sent = false;

	/**
	 * Cancelled subscription email (for admins).
	 *
	 * @var WCS_Email_Cancelled_Subscription
	 */
	private $sut;

	/**
	 * Initialize WC_Emails (required by our subject-under-test), and setup our email watcher.
	 *
	 * @return void
	 */
	public function set_up() {
		$this->email_subject = '';
		$this->email_sent    = false;

		new WC_Emails();
		$this->sut          = new WCS_Email_Cancelled_Subscription();
		$this->sut->enabled = 'yes';
		add_filter( 'woocommerce_mail_callback_params', [ $this, 'email_watcher' ] );

		parent::set_up();
	}

	/**
	 * Remove our email watcher.
	 *
	 * @return void
	 */
	public function tear_down() {
		remove_filter( 'woocommerce_mail_callback_params', [ $this, 'email_watcher' ] );
		parent::tear_down();
	}

	/**
	 * Set a flag to indicate that an email was dispatched, and capture the content.
	 *
	 * @see WC_Email::send()
	 *
	 * @param array $email_params
	 *
	 * @return array
	 */
	public function email_watcher( array $email_params ) {
		$this->email_subject = $email_params[1];
		$this->email_sent    = true;
		return $email_params;
	}

	private function reset_email_watcher() {
		$this->email_subject = '';
		$this->email_sent    = false;
	}

	/**
	 * Describes the default behavior for admin cancellation emails, which is that they should only be
	 * sent once per subscription.
	 *
	 * So, if a subscription is initially set to 'pending-cancel' (which typically happesn via customer action),
	 * we dispatch this email. If and when it subsequently is set to 'cancelled', a second email will not be
	 * sent.
	 *
	 * @return void
	 */
	public function test_email_is_not_sent_twice_by_default() {
		$subscription = WCS_Helper_Subscription::create_subscription(
			array(
				'status'                  => 'pending-cancel',
				'requires_manual_renewal' => true,
			)
		);

		$this->sut->trigger( $subscription );
		$this->assertTrue( $this->email_sent, 'An email was sent in relation to a pending-cancellation subscription.' );
		$this->assertStringContainsString( 'Subscription Cancelled', $this->email_subject, 'We are examining the Cancelled Subscription email.' );
		$this->reset_email_watcher();

		$subscription->update_status( 'cancelled' );
		$this->sut->trigger( $subscription );
		$this->assertFalse( $this->email_sent, 'When a pending-cancellation subscription was updated to cancelled, a second cancellation email was not sent.' );
	}

	/**
	 * The behavior described in self::test_email_is_not_sent_twice_by_default() is not always desirable,
	 * and so the Cancelled Subscription email can be configured such that an email is dispatched more than
	 * once per subscription.
	 *
	 * This can be useful for merchants if they need to get a notification when a customer self-cancels, and
	 * again when the subscription fully lapses.
	 *
	 * @return void
	 */
	public function test_email_can_be_sent_twice_when_required() {
		$subscription = WCS_Helper_Subscription::create_subscription(
			array(
				'status'                  => 'pending-cancel',
				'requires_manual_renewal' => true,
			)
		);

		$this->sut->update_option( 'always_send', 'yes' );
		$this->sut->trigger( $subscription );
		$this->assertTrue( $this->email_sent, 'An email was sent in relation to a pending-cancellation subscription.' );
		$this->assertStringContainsString( 'Subscription Cancelled', $this->email_subject, 'We are examining the Cancelled Subscription email.' );
		$this->reset_email_watcher();

		$subscription->update_status( 'cancelled' );
		$this->sut->trigger( $subscription );
		$this->assertTrue( $this->email_sent, 'When a pending-cancellation subscription was updated to cancelled, a second cancellation email will also be sent (if configured to do so).' );
		$this->assertStringContainsString( 'Subscription Cancelled', $this->email_subject, 'We are examining the Cancelled Subscription email.' );
	}
}
