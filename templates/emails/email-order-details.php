<?php
/**
 * Order/Subscription details table shown in emails.
 *
 * @package WooCommerce_Subscriptions/Templates/Emails
 * @version 1.0.0 - Migrated from WooCommerce Subscriptions v3.0.0
 */

defined( 'ABSPATH' ) || exit;

$text_align = is_rtl() ? 'right' : 'left';

$email_improvements_enabled = wcs_is_wc_feature_enabled( 'email_improvements' );
$heading_class              = $email_improvements_enabled ? 'email-order-detail-heading' : '';
$order_table_class          = $email_improvements_enabled ? 'email-order-details' : '';
$order_total_text_align     = $email_improvements_enabled ? 'right' : 'left';

if ( $email_improvements_enabled ) {
	add_filter( 'woocommerce_order_shipping_to_display_shipped_via', '__return_false' );
}

do_action( 'woocommerce_email_before_' . $order_type . '_table', $order, $sent_to_admin, $plain_text, $email );

if ( 'cancelled_subscription' !== $email->id ) {
	echo '<h2 class="' . esc_attr( $heading_class ) . '">';

	$url    = ( $sent_to_admin ) ? wcs_get_edit_post_link( $order->get_id() ) : $order->get_view_order_url();
	$before = '<a class="link" href="' . esc_url( $url ) . '">';
	$after  = '</a>';

	if ( 'order' === $order_type ) {

		if ( $email_improvements_enabled ) {
			echo wp_kses_post( __( 'Order summary', 'woocommerce-subscriptions' ) );
		}

		if ( $email_improvements_enabled ) {
			echo '<span>';
		}

		// translators: %s: Order ID.
		$order_number_string = $email_improvements_enabled ? __( 'Order #%s', 'woocommerce-subscriptions' ) : __( '[Order #%s]', 'woocommerce-subscriptions' );

		echo wp_kses_post( $before . sprintf( $order_number_string . $after . ' (<time datetime="%s">%s</time>)', $order->get_order_number(), $order->get_date_created()->format( 'c' ), wcs_format_datetime( $order->get_date_created() ) ) );
		if ( $email_improvements_enabled ) {
			echo '</span>';
		}
	} else {
		// TODO: do the equivalent of the above for subscriptions here.
		// translators: $1-$3: opening and closing <a> tags $2: subscription's order number
		printf( esc_html_x( 'Subscription %1$s#%2$s%3$s', 'Used in email notification', 'woocommerce-subscriptions' ), '<a href="' . esc_url( $url ) . '">', esc_html( $order->get_order_number() ), '</a>' );
	}
	echo '</h2>';
}
?>
<div style="margin-bottom: <?php echo $email_improvements_enabled ? '24px' : '40px'; ?>;">
	<table class="td font-family <?php echo esc_attr( $order_table_class ); ?>" cellspacing="0" cellpadding="6" style="width: 100%;" border="1">
		<?php if ( ! $email_improvements_enabled ) { ?>
		<thead>
			<tr>
				<th class="td" scope="col" style="text-align:<?php echo esc_attr( $text_align ); ?>;"><?php echo esc_html_x( 'Product', 'table headings in notification email', 'woocommerce-subscriptions' ); ?></th>
				<th class="td" scope="col" style="text-align:<?php echo esc_attr( $text_align ); ?>;"><?php echo esc_html_x( 'Quantity', 'table headings in notification email', 'woocommerce-subscriptions' ); ?></th>
				<th class="td" scope="col" style="text-align:<?php echo esc_attr( $text_align ); ?>;"><?php echo esc_html_x( 'Price', 'table headings in notification email', 'woocommerce-subscriptions' ); ?></th>
			</tr>
		</thead>
		<?php } ?>
		<tbody>
			<?php
			echo wp_kses_post( WC_Subscriptions_Email::email_order_items_table( $order, $order_items_table_args ) );
			?>
		</tbody>
		<tfoot>
			<?php
			$item_totals       = $order->get_order_item_totals();
			$item_totals_count = count( $item_totals );

			if ( $item_totals ) {
				$i = 0;
				foreach ( $item_totals as $total ) {
					$i++;
					$last_class = ( $i === $item_totals_count ) ? ' order-totals-last' : '';
					?>
					<tr class="order-totals order-totals-<?php echo esc_attr( $total['type'] ?? 'unknown' ); ?><?php echo esc_attr( $last_class ); ?>">
						<th class="td text-align-left" scope="row" colspan="2" style="<?php echo ( 1 === $i ) ? 'border-top-width: 4px;' : ''; ?>">
						<?php
						echo wp_kses_post( $total['label'] ) . ' ';
						if ( $email_improvements_enabled ) {
							echo isset( $total['meta'] ) ? wp_kses_post( $total['meta'] ) : '';
						}
						?>
						</th>
						<td class="td text-align-<?php echo esc_attr( $order_total_text_align ); ?>" style="<?php echo ( 1 === $i ) ? 'border-top-width: 4px;' : ''; ?>"><?php echo wp_kses_post( $total['value'] ); ?></td>
					</tr>
					<?php
				}
			}
			if ( $order->get_customer_note() ) {
				if ( $email_improvements_enabled ) {
					?>
					<tr class="order-customer-note">
						<td class="td text-align-left" colspan="3">
							<b><?php esc_html_e( 'Customer note', 'woocommerce-subscriptions' ); ?></b><br>
							<?php echo wp_kses( nl2br( wptexturize( $order->get_customer_note() ) ), array( 'br' => array() ) ); ?>
						</td>
					</tr>
					<?php
				} else {
					?>
					<tr>
						<th class="td text-align-left" scope="row" colspan="2"><?php esc_html_e( 'Note:', 'woocommerce-subscriptions' ); ?></th>
						<td class="td text-align-left"><?php echo wp_kses( nl2br( wptexturize( $order->get_customer_note() ) ), array() ); ?></td>
					</tr>
					<?php
				}
			}
			?>
		</tfoot>
	</table>
</div>

<?php do_action( 'woocommerce_email_after_' . $order_type . '_table', $order, $sent_to_admin, $plain_text, $email ); ?>
