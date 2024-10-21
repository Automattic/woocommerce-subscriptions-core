<?php

class WCS_Email_Customer_Notification extends WC_Email {

	public function __construct() {
		// These values are only available later, but it's an available placeholder.
		$this->placeholders = array_merge(
			[
				'{customers_first_name}' => '',
				'{days_until_renewal}'   => '',
			],
			$this->placeholders
		);

		parent::__construct();
	}

	/**
	 * Initialise Settings Form Fields - these are generic email options most will use.
	 */
	public function init_form_fields() {
		/* translators: %s: list of placeholders */
		$placeholder_text  = sprintf( __( 'Available placeholders: %s', 'woocommerce-subscriptions' ), '<code>' . esc_html( implode( '</code>, <code>', array_keys( $this->placeholders ) ) ) . '</code>' );
		$this->form_fields = array(
			'enabled'            => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-subscriptions' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable this email notification. Disabled automatically on staging sites.', 'woocommerce-subscriptions' ),
				'default' => 'yes',
			),
			'subject'            => array(
				'title'       => __( 'Subject', 'woocommerce-subscriptions' ),
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => $placeholder_text,
				'placeholder' => $this->get_default_subject(),
				'default'     => '',
			),
			'heading'            => array(
				'title'       => __( 'Email heading', 'woocommerce-subscriptions' ),
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => $placeholder_text,
				'placeholder' => $this->get_default_heading(),
				'default'     => '',
			),
			'additional_content' => array(
				'title'       => __( 'Additional content', 'woocommerce-subscriptions' ),
				'description' => __( 'Text to appear below the main email content.', 'woocommerce-subscriptions' ) . ' ' . $placeholder_text,
				'css'         => 'width:400px; height: 75px;',
				'placeholder' => __( 'N/A', 'woocommerce-subscriptions' ),
				'type'        => 'textarea',
				'default'     => $this->get_default_additional_content(),
				'desc_tip'    => true,
			),
			'email_type'         => array(
				'title'       => __( 'Email type', 'woocommerce-subscriptions' ),
				'type'        => 'select',
				'description' => __( 'Choose which format of email to send.', 'woocommerce-subscriptions' ),
				'default'     => 'html',
				'class'       => 'email_type wc-enhanced-select',
				'options'     => $this->get_email_type_options(),
				'desc_tip'    => true,
			),
		);
	}

	/**
	 * Trigger function.
	 *
	 * @return void
	 */
	public function trigger( $subscription_id ) {
		$subscription    = wcs_get_subscription( $subscription_id );
		$this->object    = $subscription;
		$this->recipient = $subscription->get_billing_email();

		if ( ! $this->is_enabled()
			|| ! $this->get_recipient()
			|| ! WC_Subscriptions_Email_Notifications::should_send_notification()
			|| WCS_Action_Scheduler_Customer_Notifications::is_subscription_period_too_short( $subscription )
		) {
			// TODO: add admin notice here if in admin
			return;
		}

		$this->setup_locale();

		try {
			$this->placeholders['{customers_first_name}'] = $subscription->get_billing_first_name();
			$this->placeholders['{days_until_renewal}']   = $this->get_time_until_date( $subscription, 'next_payment' );

			$result = $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );

			if ( $result ) {
				/* translators: 1: Notification type, 2: customer's email. */
				$order_note_msg = sprintf( __( '%1$s was successfully sent to %2$s.', 'woocommerce-subscriptions' ), $this->title, $this->recipient );
			} else {
				/* translators: 1: Notification type, 2: customer's email. */
				$order_note_msg = sprintf( __( 'Attempt to send %1$s to %2$s failed successfully.', 'woocommerce-subscriptions' ), $this->title, $this->recipient );
			}

			$subscription->add_order_note( $order_note_msg );
		} finally {
			$this->restore_locale();
		}
	}

	/**
	 * get_content_html function.
	 *
	 * @return string
	 */
	public function get_content_html() {
		$subscription = $this->object;

		if ( wcs_can_user_renew_early( $subscription )
			&& $subscription->payment_method_supports( 'subscription_date_changes' )
			&& WCS_Early_Renewal_Manager::is_early_renewal_enabled()
			&& WCS_Manual_Renewal_Manager::is_manual_renewal_enabled()
		) {
			$url_for_renewal = wcs_get_early_renewal_url( $subscription );
			$can_renew_early = true;
		} else {
			$url_for_renewal = $subscription->get_view_order_url();
			$can_renew_early = false;
		}

		return wc_get_template_html(
			$this->template_html,
			[
				'subscription'                => $subscription,
				'order'                       => $subscription->get_parent(),
				'email_heading'               => $this->get_heading(),
				'subscription_days_til_event' => $this->get_time_until_date( $subscription, $this->get_relevant_date_type() ),
				'subscription_event_date'     => $this->get_formatted_date( $subscription, $this->get_relevant_date_type() ),
				'url_for_renewal'             => $url_for_renewal,
				'can_renew_early'             => $can_renew_early,
				'additional_content'          => is_callable(
					[
						$this,
						'get_additional_content',
					]
				) ? $this->get_additional_content() : '',
				// WC 3.7 introduced an additional content field for all emails.
				'sent_to_admin'               => false,
				'plain_text'                  => false,
				'email'                       => $this,
			],
			'',
			$this->template_base
		);
	}

	/**
	 * get_content_plain function.
	 *
	 * @return string
	 */
	public function get_content_plain() {
		$subscription = $this->object;

		if ( wcs_can_user_renew_early( $subscription )
			&& $subscription->payment_method_supports( 'subscription_date_changes' )
			&& WCS_Early_Renewal_Manager::is_early_renewal_enabled()
			&& WCS_Manual_Renewal_Manager::is_manual_renewal_enabled()
		) {
			$url_for_renewal = wcs_get_early_renewal_url( $subscription );
			$can_renew_early = true;
		} else {
			$url_for_renewal = $subscription->get_view_order_url();
			$can_renew_early = false;
		}

		return wc_get_template_html(
			$this->template_plain,
			[
				'subscription'                => $subscription,
				'order'                       => $subscription->get_parent(),
				'email_heading'               => $this->get_heading(),
				'subscription_days_til_event' => $this->get_time_until_date( $subscription, $this->get_relevant_date_type() ),
				'subscription_event_date'     => $this->get_formatted_date( $subscription, $this->get_relevant_date_type() ),
				'url_for_renewal'             => $url_for_renewal,
				'can_renew_early'             => $can_renew_early,
				'additional_content'          => is_callable(
					[
						$this,
						'get_additional_content',
					]
				) ? $this->get_additional_content() : '',
				// WC 3.7 introduced an additional content field for all emails.
				'sent_to_admin'               => false,
				'plain_text'                  => true,
				'email'                       => $this,
			],
			'',
			$this->template_base
		);
	}

	public function get_time_until_date( $subscription, $date_type ) {
		$next_event = $subscription->get_date( $date_type );
		if ( ! $next_event ) {
			return '';
		}

		$next_event_dt = new DateTime( $next_event, new DateTimeZone( 'UTC' ) );
		$now           = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
		// Add some buffer, otherwise it will claim that only 2 full days are left when in reality it's 2 days, 23 hours and 59 minutes.
		$now->modify( '+1 hour' );
		$interval = $next_event_dt->diff( $now );
		return $interval->days;
	}

	public function get_formatted_date( $subscription, $date_type ) {
		return date_i18n( wc_date_format(), $subscription->get_time( $date_type, 'site' ) );
	}

	/**
	 * Default content to show below main email content.
	 *
	 * @return string
	 */
	public function get_default_additional_content() {
		return __( 'Thank you for choosing {site_title}!', 'woocommerce-subscriptions' );
	}
}
