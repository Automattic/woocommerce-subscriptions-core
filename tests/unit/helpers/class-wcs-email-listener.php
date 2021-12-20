<?php

class WCS_Email_Listener {

	/**
	 * The email class name to listen for.
	 *
	 * @var string
	 */
	private $email_class_name = '';


	/**
	 * The number of times this email was sent.
	 *
	 * @var array
	 */
	private $sent_counts = array();

	/**
	 * The number of times an email not being tracked was sent.
	 *
	 * @var array
	 */
	private $untracked_sent_counts = array();

	/**
	 * Constructor.
	 *
	 * @param string $email_class_name The email to listen for.
	 */
	public function __construct( $email_class_name ) {
		if ( isset( WC()->mailer()->emails[ $email_class_name ] ) ) {
			$this->email_class_name = $email_class_name;
		}

		return $this;
	}

	/**
	 * Begins listening to outgoing emails.
	 */
	public function start_listening() {
		// WC 3.6 introduced better filters we can use to track outgoing emails. Pre 3.6 we need a slightly hacky approach.
		if ( wcs_is_woocommerce_pre( '3.6' ) ) {
			add_filter( 'woocommerce_email_from_address', array( $this, 'record_outgoing_email_pre_3_6' ), 10, 2 );
		} else {
			add_filter( 'woocommerce_mail_callback_params', array( $this, 'record_outgoing_email' ), 10, 2 );
		}
	}

	/**
	 * Records outgoing email.
	 *
	 * @param array $params The email params.
	 * @param WC_Email $email
	 * @return array The email params.
	 */
	public function record_outgoing_email( $params, $email ) {
		$email_recipient = $params[0];

		if ( get_class( $email ) === $this->email_class_name ) {
			$this->sent_counts[ $email_recipient ] = ! isset( $this->sent_counts[ $email_recipient ] ) ? 1 : $this->sent_counts[ $email_recipient ] + 1;
		} else {
			$this->untracked_sent_counts[ $email_recipient ] = ! isset( $this->untracked_sent_counts[ $email_recipient ] ) ? 1 : $this->untracked_sent_counts[ $email_recipient ] + 1;
		}

		return $params;
	}

	/**
	 * Record outgoing emails on WC pre 3.6.0.
	 *
	 * Hooked onto a slightly less reliant hook (woocommerce_email_from_address) used right before an email is sent.
	 *
	 * @param string $from_address
	 * @param WC_Email $email The email being sent.
	 * @return $from_address
	 */
	public function record_outgoing_email_pre_3_6( $from_address, $email ) {
		$this->record_outgoing_email( array( $email->get_recipient() ), $email );
		return $from_address;
	}

	/**
	 * Determines if the tracked email has been sent.
	 *
	 * @param string $email_recipient The recipient of email sent. Optional. Can be 'customer', 'admin', 'any' or a specific email address. Default is 'any'.
	 * @param int $sent_count The number of times to check the email was sent. Optional. Default is 1.
	 * @return boolean
	 */
	public function has_sent( $email_recipient = 'any', $sent_count = 1 ) {
		return $this->get_sent_count_for_recipient( $this->sent_counts, $email_recipient ) === $sent_count;
	}

	/**
	 * Determines if any untracked email has been sent to a given recipient.
	 *
	 * @param string $email_recipient The recipient of email sent. Optional. Can be 'customer', 'admin', 'any' or a specific email address. Default is 'any'.
	 * @return boolean
	 */
	public function has_sent_untracked( $email_recipient = 'any' ) {
		return $this->get_sent_count_for_recipient( $this->untracked_sent_counts, $email_recipient ) > 0;
	}

	/**
	 * Gets the count for a given recipient string.
	 *
	 * Helps map 'any', 'customer' and 'admin' general strings to a sent count.
	 *
	 * @param array $counts
	 * @param string $recipient
	 * @return int
	 */
	private function get_sent_count_for_recipient( $counts, $recipient ) {
		switch ( $recipient ) {
			case 'any':
				// Map all the sent emails to the 'any' recipient key.
				$counts = array( 'any' => array_sum( $counts ) );
				break;
			case 'admin':
				// By default the admin email is admin@woocommerce.com.
				$recipient = 'admin@woocommerce.com';
				break;
			case 'customer':
				// Remove any emails sent to the admin.
				unset( $counts['admin@woocommerce.com'] );

				// Map all the remaining sent emails to the customer recipient.
				$counts = array( 'customer' => array_sum( $counts ) );
				break;
		}

		return $counts[ $recipient ] ?? 0;
	}

	/**
	 * Resets the sent counts.
	 */
	public function reset() {
		$this->untracked_sent_counts = array();
		$this->sent_counts           = array();
	}
}
