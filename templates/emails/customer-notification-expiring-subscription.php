<?php
/**
 * Customer Notification: Subscription is about to expire email.
 *
 * @package WooCommerce_Subscriptions/Templates/Emails
 * @version x.x.x
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * @hooked WC_Emails::email_header() Output the email header.
 *
 * @since x.x.x
 */
do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

	<p>
		<?php
		printf(
		/* translators: %s: Customer first name */
			esc_html__( 'Heads up, %s.', 'woocommerce-subscriptions' ),
			esc_html( $subscription->get_billing_first_name() )
		);
		?>
	</p>


	<p>
		<?php
		echo wp_kses(
			sprintf(
			// translators: %1$s: number of days until expiry, %2$s: date in local format.
				__( 'Your subscription expires in %1$s days — that’s <strong>%2$s</strong>.', 'woocommerce-subscriptions' ),
				(int) $subscription_days_til_event,
				esc_html( $subscription_event_date )
			),
			[ 'strong' => [] ]
		);
		?>
	</p>

	<table role="presentation" border="0" cellspacing="0" cellpadding="0" style="margin: 0 auto;">
		<tr>
			<td>
				<?php
				echo wp_kses(
					'<a href="' . esc_url( $subscription->get_checkout_payment_url() ) . '">' . esc_html__( 'Renew my subscription', 'woocommerce-subscriptions' ) . '</a>',
					[ 'a' => [ 'href' => true ] ]
				);
				?>
			</td>
		</tr>
	</table>

	<p>
		<?php
		esc_html_e( 'Here are the details:', 'woocommerce-subscriptions' );
		?>
	</p>


<?php

// Show subscription details.
\WC_Subscriptions_Order::add_sub_info_email( $order, $sent_to_admin, $plain_text );

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

/**
 * @hooked WC_Emails::email_footer() Output the email footer.
 *
 * @since x.x.x
 */
do_action( 'woocommerce_email_footer', $email );
