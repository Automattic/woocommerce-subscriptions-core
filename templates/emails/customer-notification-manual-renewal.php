<?php
/**
 * Customer Notification: Nudge for the customer to renew their subscription manually.
 *
 * @package WooCommerce_Subscriptions/Templates/Emails
 * @version 1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

	<p>
		<?php
			printf(
				/* translators: %s: Customer first name */
				esc_html__( 'Hi %s,', 'woocommerce-subscriptions' ),
				esc_html( $order->get_billing_first_name() )
			);
			?>
	</p>


	<p>
		<?php
			echo wp_kses(
				sprintf(
					// translators: %1$s: name of the blog, %2$s: link to checkout payment url, note: no full stop due to url at the end
					_x( 'Your subscription for XYZ on %1$s is about to expire. To keep receiving the goodies, renew it manually over here: %2$s', 'In customer renewal invoice email', 'woocommerce-subscriptions' ),
					esc_html( get_bloginfo( 'name' ) ),
					'<a href="' . esc_url( $order->get_checkout_payment_url() ) . '">' . esc_html__( 'Pay Now &raquo;', 'woocommerce-subscriptions' ) . '</a>'
				),
				array( 'a' => array( 'href' => true ) )
			);
			?>
	</p>

<?php
do_action( 'woocommerce_subscriptions_email_order_details', $order, $sent_to_admin, $plain_text, $email );

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
