<?php
/**
 * Order Data
 *
 * Functions for displaying the order data meta box.
 *
 * @author   Prospress
 * @category Admin
 * @package  WooCommerce Subscriptions/Admin/Meta Boxes
 * @version  2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * WCS_Meta_Box_Subscription_Data Class
 */
class WCS_Meta_Box_Subscription_Data extends WC_Meta_Box_Order_Data {

	/**
	 * Output the metabox
	 */
	public static function output( $post ) {
		global $the_subscription;

		if ( ! is_object( $the_subscription ) || $the_subscription->id !== $post->ID ) {
			$the_subscription = wc_get_order( $post->ID );
		}

		$subscription = $the_subscription;

		self::init_address_fields();

		$payment_method = ! empty( $subscription->payment_method ) ? $subscription->payment_method : 'Manual';

		wp_nonce_field( 'woocommerce_save_data', 'woocommerce_meta_nonce' );
		?>
		<style type="text/css">
			#post-body-content, #titlediv, #major-publishing-actions, #minor-publishing-actions, #visibility, #submitdiv { display:none }
		</style>
		<div class="panel-wrap woocommerce">
			<input name="post_title" type="hidden" value="<?php echo empty( $post->post_title ) ? get_post_type_object( $subscription->post->post_type )->labels->singular_name : esc_attr( $post->post_title ); ?>" />
			<input name="post_status" type="hidden" value="<?php echo esc_attr( $subscription->get_status() ); ?>" />
			<div id="order_data" class="panel">

				<h2><?php printf( __( 'Subscription %s details', 'woocommerce' ), esc_html( $subscription->get_order_number() ) ); ?></h2>

				<div class="order_data_column_container">
					<div class="order_data_column">

						<p class="form-field form-field-wide">
							<label for="customer_user"><?php _e( 'Customer:', 'woocommerce' ) ?></label>
							<select id="customer_user" name="customer_user" class="ajax_chosen_select_customer">
								<option value=""><?php _e( 'Guest', 'woocommerce' ) ?></option>
								<?php
									if ( $subscription->get_user_id() ) {
										$user = get_user_by( 'id', $subscription->get_user_id() );
										echo '<option value="' . esc_attr( $user->ID ) . '" ' . selected( 1, 1, false ) . '>' . esc_html( $user->display_name ) . ' (#' . absint( $user->ID ) . ' &ndash; ' . esc_html( $user->user_email ) . ')</option>';
									}
								?>
							</select>
						</p>

						<p class="form-field form-field-wide">
							<label for="order_status"><?php _e( 'Subscription Status:', 'woocommerce' ); ?></label>
							<select id="order_status" name="order_status">
								<?php
									$statuses = wcs_get_subscription_statuses();
									foreach ( $statuses as $status => $status_name ) {
										if ( 'auto-draft' !== $subscription->post->post_status && 'draft' !== $subscription->post->post_status && ! $subscription->can_be_updated_to( $status ) && ! $subscription->has_status( str_replace( 'wc-', '', $status ) ) ) {
											continue;
										}
										echo '<option value="' . esc_attr( $status ) . '" ' . selected( $status, 'wc-' . $subscription->get_status(), false ) . '>' . esc_html( $status_name ) . '</option>';
									}
								?>
							</select>
						</p>

						<?php do_action( 'woocommerce_admin_order_data_after_order_details', $subscription ); ?>

					</div>
					<div class="order_data_column">
						<h4><?php _e( 'Billing Details', 'woocommerce' ); ?> <a class="edit_address" href="#"><img src="<?php echo WC()->plugin_url(); ?>/assets/images/icons/edit.png" alt="<?php _e( 'Edit', 'woocommerce' ); ?>" width="14" /></a></h4>
						<?php
							// Display values
							echo '<div class="address">';

								if ( $subscription->get_formatted_billing_address() ) {
									echo '<p><strong>' . __( 'Address', 'woocommerce' ) . ':</strong>' . wp_kses( $subscription->get_formatted_billing_address(), array( 'br' => array() ) ) . '</p>';
								} else {
									echo '<p class="none_set"><strong>' . __( 'Address', 'woocommerce' ) . ':</strong> ' . __( 'No billing address set.', 'woocommerce' ) . '</p>';
								}

								foreach ( self::$billing_fields as $key => $field ) {
									if ( isset( $field['show'] ) && false === $field['show'] ) {
										continue;
									}

									$field_name = 'billing_' . $key;

									if ( $subscription->$field_name ) {
										echo '<p><strong>' . esc_html( $field['label'] ) . ':</strong> ' . make_clickable( esc_html( $subscription->$field_name ) ) . '</p>';
									}
								}

							echo '</div>';

							// Display form
							echo '<div class="edit_address"><p><button class="button load_customer_billing">' . __( 'Load billing address', 'woocommerce' ) . '</button></p>';

							foreach ( self::$billing_fields as $key => $field ) {
								if ( ! isset( $field['type'] ) ) {
									$field['type'] = 'text';
								}

								switch ( $field['type'] ) {
									case 'select' :
										// allow for setting a default value programaticaly, and draw the selectbox
										woocommerce_wp_select( array( 'id' => '_billing_' . $key, 'label' => $field['label'], 'options' => $field['options'], 'value' => isset( $field['value'] ) ? $field['value'] : null ) );
									break;
									default :
										// allow for setting a default value programaticaly, and draw the textbox
										woocommerce_wp_text_input( array( 'id' => '_billing_' . $key, 'label' => $field['label'], 'value' => isset( $field['value'] ) ? $field['value'] : null ) );
									break;
								}
							}

							WCS_Change_Payment_Method_Admin::display_change_subscription_payment_gateway_fields( $subscription );

							echo '</div>';

							do_action( 'woocommerce_admin_order_data_after_billing_address', $subscription );
						?>
					</div>
					<div class="order_data_column">

						<h4><?php _e( 'Shipping Details', 'woocommerce' ); ?> <a class="edit_address" href="#"><img src="<?php echo WC()->plugin_url(); ?>/assets/images/icons/edit.png" alt="<?php _e( 'Edit', 'woocommerce' ); ?>" width="14" /></a></h4>
						<?php
							// Display values
							echo '<div class="address">';

								if ( $subscription->get_formatted_shipping_address() ) {
									echo '<p><strong>' . __( 'Address', 'woocommerce' ) . ':</strong>' . wp_kses( $subscription->get_formatted_shipping_address(), array( 'br' => array() ) ) . '</p>';
								} else {
									echo '<p class="none_set"><strong>' . __( 'Address', 'woocommerce' ) . ':</strong> ' . __( 'No shipping address set.', 'woocommerce' ) . '</p>';
								}

								if ( self::$shipping_fields ) {
									foreach ( self::$shipping_fields as $key => $field ) {
										if ( isset( $field['show'] ) && false === $field['show'] ) {
											continue;
										}

										$field_name = 'shipping_' . $key;

										if ( ! empty( $subscription->$field_name ) ) {
											echo '<p><strong>' . esc_html( $field['label'] ) . ':</strong> ' . make_clickable( esc_html( $subscription->$field_name ) ) . '</p>';
										}
									}
								}

							echo '</div>';

							// Display form
							echo '<div class="edit_address"><p><button class="button load_customer_shipping">' . __( 'Load shipping address', 'woocommerce' ) . '</button> <button class="button billing-same-as-shipping">' . __( 'Copy from billing', 'woocommerce' ) . '</button></p>';

							if ( self::$shipping_fields ) {
								foreach ( self::$shipping_fields as $key => $field ) {
									if ( ! isset( $field['type'] ) ) {
										$field['type'] = 'text';
									}

									switch ( $field['type'] ) {
										case 'select' :
											woocommerce_wp_select( array( 'id' => '_shipping_' . $key, 'label' => $field['label'], 'options' => $field['options'] ) );
										break;
										default :
											woocommerce_wp_text_input( array( 'id' => '_shipping_' . $key, 'label' => $field['label'] ) );
										break;
									}
								}
							}

							echo '</div>';

							do_action( 'woocommerce_admin_order_data_after_shipping_address', $subscription );
						?>
					</div>
				</div>
				<div class="clear"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Save meta box data
	 */
	public static function save( $post_id, $post ) {
		global $wpdb;

		self::init_address_fields();

		// Update meta
		update_post_meta( $post_id, '_customer_user', absint( $_POST['customer_user'] ) );

		if ( self::$billing_fields ) {
			foreach ( self::$billing_fields as $key => $field ) {
				update_post_meta( $post_id, '_billing_' . $key, wc_clean( $_POST[ '_billing_' . $key ] ) );
			}
		}

		if ( self::$shipping_fields ) {
			foreach ( self::$shipping_fields as $key => $field ) {
				update_post_meta( $post_id, '_shipping_' . $key, wc_clean( $_POST[ '_shipping_' . $key ] ) );
			}
		}

		$subscription   = wcs_get_subscription( $post_id );

		try {
			WCS_Change_Payment_Method_Admin::save_new_payment_data_from_post( $subscription );

			$subscription->update_status( $_POST['order_status'] );

		} catch ( Exception $e ) {
			wcs_add_admin_notice( $e->getMessage(), 'error' );
		}
	}

}
