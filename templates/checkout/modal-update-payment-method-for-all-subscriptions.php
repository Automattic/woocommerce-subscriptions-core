<?php
/**
 * This template is for the modal that gets displayed after a customer has clicked the "Change payment method" button.
 * This modael is used to confirm whether the customer wants to update all of their subscriptions to use the new payment method.
 *
 * @package WooCommerce/Templates
 * @version 6.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<span class="update-all-subscriptions-payment-method-wrap">
	<p>
		<?php
		echo sprintf(
			// translators: $1: opening <strong> tag, $2: closing </strong> tag
			esc_html__( 'You are about to update subscription #%1$d to use a new payment method.%2$sIf you would like to update %3$sall%4$s of your current subscriptions to use this new payment method please check the box below before continuing.', 'woocommerce-subscriptions' ),
			esc_html( $subscription->get_id() ),
			'<br>',
			'<strong>',
			'</strong>'
		);
		?>
	</p>

	<?php
	woocommerce_form_field(
		'update_all_subscriptions_payment_method',
		array(
			'type'    => 'checkbox',
			'class'   => [ 'form-row-wide' ],
			'label'   => esc_html__( 'Use this new payment method for all of my current subscriptions', 'woocommerce-subscriptions' ),
			'default' => apply_filters( 'wcs_update_all_subscriptions_payment_method_checked', false ),
		)
	);
	?>
</span>
<input type="submit" class="button alt" id="place_order" value="<?php esc_attr_e( 'Update payment method', 'woocommerce-subscriptions' ); ?>" data-value="<?php esc_attr_e( 'Update payment method', 'woocommerce-subscriptions' ); ?>" />
