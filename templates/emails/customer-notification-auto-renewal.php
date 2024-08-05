<?php
/**
 * Customer Notification: Notify the customer that an automated renewal their subscription is about to happen.
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
				// translators: %1$s: name of the blog.
				_x( 'Your subscription for XYZ on %1$s will be renewed automatically. Thanks for being a loyal customer with us!', 'In customer renewal invoice email', 'woocommerce-subscriptions' ),
				esc_html( get_bloginfo( 'name' ) )
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
