<?php
/**
 * Customer Notification: Manual renewal needed. Plain text version.
 *
 * @package WooCommerce_Subscriptions/Templates/Emails/Plain
 * @version x.x.x
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

echo esc_html( $email_heading . "\n" );

echo "\n\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";

echo esc_html(
	sprintf(
		/* translators: %s: Customer first name */
		__( 'Hi %s.', 'woocommerce-subscriptions' ),
		$subscription->get_billing_first_name()
	)
);

echo "\n\n";

echo esc_html(
	sprintf(
	// translators: %1$s: number of days until expiry, %2$s: date in local format.
		__( 'Your subscription is up for renewal in %1$s days — that’s %2$s.', 'woocommerce-subscriptions' ),
		(int) $subscription_days_til_event,
		$subscription_event_date
	)
);

echo "\n\n";

esc_html_e(
	'This subscription will not automatically renew, but you can renew it manually in a few short steps via the Subscriptions tab in your account dashboard.',
	'woocommerce-subscriptions'
);

echo "\n\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";

// translators: %s: link to checkout with the subscription.
esc_html_e( 'Renew my subscription: ', 'woocommerce-subscriptions' );
echo esc_url( wcs_get_early_renewal_url( $subscription ) );

echo "\n\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";

esc_html_e( 'Here are the details:', 'woocommerce-subscriptions' );

// Show subscription details.
\WC_Subscriptions_Order::add_sub_info_email( $order, $sent_to_admin, $plain_text );

echo "\n\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) );
	echo "\n\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
}

echo esc_html( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
