<?php
/**
 * Subscription Legacy Object
 *
 * Extends WC_Subscription to provide WC 2.7 methods when running WooCommerce < 2.7.
 *
 * @class    WC_Subscription_Legacy
 * @version  2.1
 * @package  WooCommerce Subscriptions/Classes
 * @category Class
 * @author   Brent Shepherd
 */

class WC_Subscription_Legacy extends WC_Subscription {

	protected $schedule;

	protected $status_transition = false;

	/**
	 * Initialize the subscription object.
	 *
	 * @param int|WC_Subscription $order
	 */
	public function __construct( $subscription ) {

		parent::__construct( $subscription );

		$this->order_type = 'shop_subscription';

		$this->schedule = new stdClass();
	}

	/**
	 * Populates a subscription from the loaded post data.
	 *
	 * @param mixed $result
	 */
	public function populate( $result ) {
		parent::populate( $result );

		if ( $this->post->post_parent > 0 ) {
			$this->order = wc_get_order( $this->post->post_parent );
		}
	}

	/**
	 * Returns the unique ID for this object.
	 *
	 * @return int
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Get parent order ID.
	 *
	 * @since 2.1.4
	 * @return int
	 */
	public function get_parent_id() {
		return $this->post->post_parent;
	}

	/**
	 * Gets order currency.
	 *
	 * @return string
	 */
	public function get_currency() {
		return $this->get_order_currency();
	}

	/**
	 * Get customer_note.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_customer_note( $context = 'view' ) {
		return $this->customer_note;
	}

	/**
	 * Get prices_include_tax.
	 *
	 * @param  string $context
	 * @return bool
	 */
	public function get_prices_include_tax( $context = 'view' ) {
		return $this->prices_include_tax;
	}

	/**
	 * Get the payment method.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_payment_method( $context = 'view' ) {
		return $this->payment_method;
	}

	/**
	 * Get the payment method's title.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_payment_method_title( $context = 'view' ) {
		return $this->payment_method_title;
	}

	/** Address Getters **/

	/**
	 * Get billing_first_name.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_billing_first_name( $context = 'view' ) {
		return $this->billing_first_name;
	}

	/**
	 * Get billing_last_name.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_billing_last_name( $context = 'view' ) {
		return $this->billing_last_name;
	}

	/**
	 * Get billing_company.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_billing_company( $context = 'view' ) {
		return $this->billing_company;
	}

	/**
	 * Get billing_address_1.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_billing_address_1( $context = 'view' ) {
		return $this->billing_address_1;
	}

	/**
	 * Get billing_address_2.
	 *
	 * @param  string $context
	 * @return string $value
	 */
	public function get_billing_address_2( $context = 'view' ) {
		return $this->billing_address_2;
	}

	/**
	 * Get billing_city.
	 *
	 * @param  string $context
	 * @return string $value
	 */
	public function get_billing_city( $context = 'view' ) {
		return $this->billing_city;
	}

	/**
	 * Get billing_state.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_billing_state( $context = 'view' ) {
		return $this->billing_state;
	}

	/**
	 * Get billing_postcode.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_billing_postcode( $context = 'view' ) {
		return $this->billing_postcode;
	}

	/**
	 * Get billing_country.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_billing_country( $context = 'view' ) {
		return $this->billing_country;
	}

	/**
	 * Get billing_email.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_billing_email( $context = 'view' ) {
		return $this->billing_email;
	}

	/**
	 * Get billing_phone.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_billing_phone( $context = 'view' ) {
		return $this->billing_phone;
	}

	/**
	 * Get shipping_first_name.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_shipping_first_name( $context = 'view' ) {
		return $this->shipping_first_name;
	}

	/**
	 * Get shipping_last_name.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_shipping_last_name( $context = 'view' ) {
		return $this->shipping_last_name;
	}

	/**
	 * Get shipping_company.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_shipping_company( $context = 'view' ) {
		return $this->shipping_company;
	}

	/**
	 * Get shipping_address_1.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_shipping_address_1( $context = 'view' ) {
		return $this->shipping_address_1;
	}

	/**
	 * Get shipping_address_2.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_shipping_address_2( $context = 'view' ) {
		return $this->shipping_address_2;
	}

	/**
	 * Get shipping_city.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_shipping_city( $context = 'view' ) {
		return $this->shipping_city;
	}

	/**
	 * Get shipping_state.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_shipping_state( $context = 'view' ) {
		return $this->shipping_state;
	}

	/**
	 * Get shipping_postcode.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_shipping_postcode( $context = 'view' ) {
		return $this->shipping_postcode;
	}

	/**
	 * Get shipping_country.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_shipping_country( $context = 'view' ) {
		return $this->shipping_country;
	}

	/**
	 * Get order key.
	 *
	 * @since  2.7.0
	 * @param  string $context
	 * @return string
	 */
	public function get_order_key( $context = 'view' ) {
		return $this->order_key;
	}

	/**
	 * Check if a given line item on the subscription had a sign-up fee, and if so, return the value of the sign-up fee.
	 *
	 * The single quantity sign-up fee will be returned instead of the total sign-up fee paid. For example, if 3 x a product
	 * with a 10 BTC sign-up fee was purchased, a total 30 BTC was paid as the sign-up fee but this function will return 10 BTC.
	 *
	 * @param array|int Either an order item (in the array format returned by self::get_items()) or the ID of an order item.
	 * @param  string $tax_inclusive_or_exclusive Whether or not to adjust sign up fee if prices inc tax - ensures that the sign up fee paid amount includes the paid tax if inc
	 * @return bool
	 * @since 2.0
	 */
	public function get_items_sign_up_fee( $line_item, $tax_inclusive_or_exclusive = 'exclusive_of_tax' ) {

		if ( ! is_array( $line_item ) ) {
			$line_item = wcs_get_order_item( $line_item, $this );
		}

		$parent_order = $this->get_parent();

		// If there was no original order, nothing was paid up-front which means no sign-up fee
		if ( false == $parent_order ) {

			$sign_up_fee = 0;

		} else {

			$original_order_item = '';

			// Find the matching item on the order
			foreach ( $parent_order->get_items() as $order_item ) {
				if ( wcs_get_canonical_product_id( $line_item ) == wcs_get_canonical_product_id( $order_item ) ) {
					$original_order_item = $order_item;
					break;
				}
			}

			// No matching order item, so this item wasn't purchased in the original order
			if ( empty( $original_order_item ) ) {

				$sign_up_fee = 0;

			} elseif ( isset( $line_item['item_meta']['_has_trial'] ) ) {

				// Sign up is total amount paid for this item on original order when item has a free trial
				$sign_up_fee = $original_order_item['line_total'] / $original_order_item['qty'];

			} else {

				// Sign-up fee is any amount on top of recurring amount
				$sign_up_fee = max( $original_order_item['line_total'] / $original_order_item['qty'] - $line_item['line_total'] / $line_item['qty'], 0 );
			}

			// If prices inc tax, ensure that the sign up fee amount includes the tax
			if ( 'inclusive_of_tax' === $tax_inclusive_or_exclusive && ! empty( $original_order_item ) && $this->get_prices_include_tax() ) {
				$proportion   = $sign_up_fee / ( $original_order_item['line_total'] / $original_order_item['qty'] );
				$sign_up_fee += round( $original_order_item['line_tax'] * $proportion, 2 );
			}
		}

		return apply_filters( 'woocommerce_subscription_items_sign_up_fee', $sign_up_fee, $line_item, $this, $tax_inclusive_or_exclusive );
	}

	/**
	 * Helper function to make sure when WC_Subscription calls get_prop() from
	 * it's new getters that the property is both retreived from the legacy class
	 * property and done so from post meta.
	 *
	 * @return string
	 */
	protected function get_prop( $prop ) {

		if ( 'switch_data' == $prop ) {
			$prop = 'subscription_switch_data';
		}

		if ( ! isset( $this->$prop ) || empty( $this->$prop ) ) {
			$value = get_post_meta( $this->get_id(), '_' . $prop, true );
		} else {
			$value = $this->$prop;
		}

		return $value;
	}

	/*** Setters *****************************************************/

	/**
	 * Returns the unique ID for this object.
	 *
	 * @return int
	 */
	public function set_id( $id ) {
		$this->id = absint( $id );
	}

	/**
	 * Set parent order ID. We don't use WC_Abstract_Order::set_parent_id() because we want to allow false
	 * parent IDs, like 0.
	 *
	 * @since 2.1.4
	 * @param int $value
	 */
	public function set_parent_id( $value ) {
		// Update the parent in the database
		wp_update_post(  array(
			'ID'          => $this->id,
			'post_parent' => $value,
		) );

		// And update the parent in memory
		$this->post->post_parent = $value;
		$this->order = null;
	}

	/**
	 * Set order status.
	 *
	 * @param string $new_status Status to change the order to. No internal wc- prefix is required.
	 * @return array details of change
	 */
	public function set_status( $new_status, $note = '', $manual_update = false ) {

		$old_status = $this->get_status();

		wp_update_post( array( 'ID' => $this->get_id(), 'post_status' => 'wc-' . $new_status ) );
		$this->post_status = $this->post->post_status = 'wc-' . $new_status;

		$this->status_transition = array(
			'from'   => $old_status,
			'to'     => $new_status,
			'note'   => $note,
			'manual' => (bool) $manual_update,
		);

		return array(
			'from' => $old_status,
			'to'   => $new_status,
		);
	}

	/**
	 * Helper function to make sure when WC_Subscription calls set_prop() that property is
	 * both set in the legacy class property and saved in post meta immediately.
	 *
	 * @return string
	 */
	protected function set_prop( $prop, $value ) {

		if ( 'switch_data' == $prop ) {
			$prop = 'subscription_switch_data';
		}

		$this->$prop = $value;
		update_post_meta( $this->get_id(), '_' . $prop, $value );
	}

	/**
	 * Get the stored date for a specific schedule.
	 *
	 * @param string $date_type 'start', 'trial_end', 'next_payment', 'last_payment' or 'end'
	 */
	protected function get_date_prop( $date_type ) {
		if ( ! isset( $this->schedule->{$date_type} ) ) {
			$this->schedule->{$date_type} = $this->get_prop( sprintf( 'schedule_%s', $date_type ) );
		}
		return $this->schedule->{$date_type};
	}

	/*** Setters *****************************************************/

	/**
	 * Set the stored date for a specific schedule.
	 *
	 * @param string $date_type 'start', 'trial_end', 'next_payment', 'last_payment' or 'end'
	 * @param string $value MySQL date/time string in GMT/UTC timezone.
	 */
	protected function set_date_prop( $date_type, $value ) {
		parent::set_date_prop( $date_type, $value ); // calls WC_Subscription_Legacy::set_prop() which calls update_post_meta() with the meta key 'schedule_{$date_type}'
		$this->schedule->{$date_type} = $value;
	}

	/**
	 * Set discount_total.
	 *
	 * @param string $value
	 * @throws WC_Data_Exception
	 */
	public function set_discount_total( $value ) {
		$this->set_total( $value, 'cart_discount' );
	}

	/**
	 * Set discount_tax.
	 *
	 * @param string $value
	 * @throws WC_Data_Exception
	 */
	public function set_discount_tax( $value ) {
		$this->set_total( $value, 'cart_discount_tax' );
	}

	/**
	 * Set shipping_total.
	 *
	 * @param string $value
	 * @throws WC_Data_Exception
	 */
	public function set_shipping_total( $value ) {
		$this->set_total( $value, 'shipping' );
	}

	/**
	 * Set shipping_tax.
	 *
	 * @param string $value
	 * @throws WC_Data_Exception
	 */
	public function set_shipping_tax( $value ) {
		$this->set_total( $value, 'shipping_tax' );
	}

	/**
	 * Set cart tax.
	 *
	 * @param string $value
	 * @throws WC_Data_Exception
	 */
	public function set_cart_tax( $value ) {
		$this->set_total( $value, 'tax' );
	}

	/**
	 * Save data to the database. Nothing to do here as it's all done separately when calling @see this->set_prop().
	 *
	 * @return int order ID
	 */
	public function save() {
		$this->status_transition();
		return $this->get_id();
	}

	/**
	 * Update meta data by key or ID, if provided.
	 *
	 * @since  2.1.4
	 * @param  string $key
	 * @param  string $value
	 * @param  int $meta_id
	 */
	public function update_meta_data( $key, $value, $meta_id = '' ) {
		if ( ! empty( $meta_id ) ) {
			update_metadata_by_mid( 'post', $meta_id, $value, $key );
		} else {
			update_post_meta( $this->get_id(), $key, $value );
		}
	}
}
