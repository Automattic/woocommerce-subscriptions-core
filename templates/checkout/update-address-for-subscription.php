<?php

defined( 'ABSPATH' ) || exit;

?>
<div id="payment" class="woocommerce-checkout-payment">

	<div class="form-row place-order" style="margin-top:0;">

		<?php echo apply_filters( 'wcs_update_address_button_html', '<button type="submit" class="button alt" name="woocommerce_checkout_place_order" id="place_order" value="' . esc_attr( $order_button_text ) . '" data-value="' . esc_attr( $order_button_text ) . '">' . esc_html( $order_button_text ) . '</button>' ); // @codingStandardsIgnoreLine ?>

		<?php wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce' ); ?>

	</div>

</div>
