<?php
/**
 * Customer Notification: Free trial of a manually renewed subscription is about to expire email. Plain text version.
 *
 * @package WooCommerce_Subscriptions/Templates/Emails/Plain
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
do_action( 'woocommerce_email_header', $email_heading, $email );

echo "\n\n";

printf(
	/* translators: %s: Customer first name */
	esc_html__( 'Heads up, %s.', 'woocommerce-subscriptions' ),
	esc_html( $subscription->get_billing_first_name() )
);

echo "\n";

echo esc_html(
	sprintf(
		// translators: %1$s: number of days until expiry, %2$s: date in local format.
		__( 'Your free trial expires in %1$s days — that’s %2$s.', 'woocommerce-subscriptions' ),
		(int) $subscription_days_til_event,
		esc_html( $subscription_event_date ),
	)
);

echo "\n\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";

// Show subscription details.
\WC_Subscriptions_Order::add_sub_info_email( $order, $sent_to_admin, $plain_text );

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) );
	echo "\n\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
}

/**
 * @hooked WC_Emails::email_footer() Output the email footer.
 *
 * @since x.x.x
 */
do_action( 'woocommerce_email_footer', $email );
