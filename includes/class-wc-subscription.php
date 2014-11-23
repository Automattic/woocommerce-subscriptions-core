<?php
/**
 * Subscription Object
 *
 * Extends WC_Order because the Edit Order/Subscription interface requires some of the refund related methods
 * from WC_Order that don't exist in WC_Abstract_Order (which would seem the more appropriate choice)
 *
 * @class    WC_Subscription
 * @version  2.0
 * @package  WooCommerce Subscriptions/Classes
 * @category Class
 * @author   Brent Shepherd
 */

class WC_Subscription extends WC_Order {

	/** @protected WC_Order Stores order data for the order in which the subscription was purchased (if any) */
	protected $order;

	/** @protected Object Cache dates relating to the subscription */
	protected $schedule;

	/** @protected Object Stores an instance of the WC_Payment_Gateway used to process recurring payments (if any) */
	protected $payment_gateway = null;

	/**
	 * Initialize the subscription object.
	 *
	 * @param int|WC_Subscription $order
	 */
	public function __construct( $subscription ) {

		$this->order_type = 'shop_subscription';

		parent::__construct( $subscription );

		$this->schedule = new stdClass();;
	}

	/**
	 * Populates a subsciption from the loaded post data.
	 *
	 * @param mixed $result
	 */
	public function populate( $result ) {
		parent::populate( $result );
	}

	/**
	 * __get function.
	 *
	 * @param mixed $key
	 * @return mixed
	 */
	public function __get( $key ) {

		if ( in_array( $key, array( 'start_date', 'trial_end_date', 'next_payment_date', 'end_date', 'last_payment_date' ) ) ) {

			$value = $this->get_date( $key );

		} elseif ( 'order' == $key ) {

			if ( $this->post->post_parent > 0 ) {
				$this->order = wc_get_order( $this->post->post_parent );
			} else {
				$this->order = null;
			}

		} elseif ( 'payment_gateway' == $key ) {

			// Only set the payment gateway once and only when we first need it
			if ( ! isset( $this->payment_gateway ) ) {
				$payment_gateways = WC()->payment_gateways->payment_gateways();
				$this->payment_gateway = isset( $payment_gateways[ $this->payment_method ] ) ? $payment_gateways[ $this->payment_method ] : null;
			}

			$value = $this->payment_gateway;

		} else {

			$value = parent::__get( $key );

		}

		return $value;
	}

	/**
	 * Checks if the subscription has an unpaid order or renewal order (and therefore, needs payment).
	 *
	 * @param string $subscription_key A subscription key of the form created by @see self::get_subscription_key()
	 * @param int $user_id The ID of the user who owns the subscriptions. Although this parameter is optional, if you have the User ID you should pass it to improve performance.
	 * @return bool True if the subscription has an unpaid renewal order, false if the subscription has no unpaid renewal orders.
	 * @since 2.0
	 */
	public function needs_payment() {

		// Check if this subscription is pending or failed and has an order total > 0
		$needs_payment = parent::needs_payment();

		// Now check if the last renewal order needs payment
		if ( false == $needs_payment ) {

			$last_renewal_order_id = get_posts( array(
				'post_parent'    => $this->id,
				'post_type'      => 'shop_order',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'orderby'        => 'ID',
				'order'          => 'DESC',
				'fields'         => 'ids',
			));

			if ( ! empty( $last_renewal_order_id ) ) {
				$renewal_order = new WC_Order( $last_renewal_order_id[0] );
				$needs_payment = $renewal_order->needs_payment();
			}
		}

		return apply_filters( 'woocommerce_subscription_needs_payment', $needs_payment, $this );
	}

	/**
	 * Check if the subscription's payment method supports a certain feature, like date changes.
	 *
	 * If the subscription uses manual renewals as the payment method, it supports all features.
	 * Otherwise, the feature will only be supported if the payment gateway set as the payment
	 * method supports for the feature.
	 *
	 * @param string $payment_gateway_feature one of:
	 *		'subscription_suspension'
	 *		'subscription_reactivation'
	 *		'subscription_cancellation'
	 *		'subscription_date_changes'
	 *		'subscription_amount_changes'
	 * @since 2.0
	 */
	public function payment_method_supports( $payment_gateway_feature ) {

		if ( $this->is_manual() || ( ! empty( $this->payment_gateway ) && $this->payment_gateway->supports( $payment_gateway_feature ) ) ) {
			$payment_gateway_supports = true;
		} else {
			$payment_gateway_supports = false;
		}

		return apply_filters( 'woocommerce_subscription_payment_gateway_supports', $payment_gateway_supports, $this );
	}


	/** Formatted Totals Methods *******************************************************/

	/**
	 * Gets line subtotal - formatted for display.
	 *
	 * @param array  $item
	 * @param string $tax_display
	 * @return string
	 */
	public function get_formatted_line_subtotal( $item, $tax_display = '' ) {

		if ( ! $tax_display ) {
			$tax_display = $this->tax_display_cart;
		}

		if ( ! isset( $item['line_subtotal'] ) || ! isset( $item['line_subtotal_tax'] ) ) {
			return '';
		}

		if ( 'excl' == $tax_display ) {
			$display_ex_tax_label = $this->prices_include_tax ? 1 : 0;
			$subtotal = wcs_price_string( $this->get_price_string_details( $this->get_line_subtotal( $item ) ), $display_ex_tax_label );
		} else {
			$subtotal = wcs_price_string( $this->get_price_string_details( $this->get_line_subtotal( $item, true ) ) );
		}

		return apply_filters( 'woocommerce_order_formatted_line_subtotal', $subtotal, $item, $this );
	}

	/**
	 * Gets order total - formatted for display.
	 *
	 * @return string
	 */
	public function get_formatted_order_total() {
		if ( $this->get_total() > 0 && ! empty( $this->billing_period ) ) {
			$formatted_order_total = wcs_price_string( $this->get_price_string_details( $this->get_total() ) );
		} else {
			$formatted_order_total = parent::get_formatted_order_total();
		}
		return apply_filters( 'woocommerce_get_formatted_subscription_total', $formatted_order_total, $this );
	}

	/**
	 * Gets subtotal - subtotal is shown before discounts, but with localised taxes.
	 *
	 * @param bool $compound (default: false)
	 * @param string $tax_display (default: the tax_display_cart value)
	 * @return string
	 */
	public function get_subtotal_to_display( $compound = false, $tax_display = '' ) {

		if ( ! $tax_display ) {
			$tax_display = $this->tax_display_cart;
		}

		$subtotal = 0;

		if ( ! $compound ) {
			foreach ( $this->get_items() as $item ) {

				if ( ! isset( $item['line_subtotal'] ) || ! isset( $item['line_subtotal_tax'] ) ) {
					return '';
				}

				$subtotal += $item['line_subtotal'];

				if ( 'incl' == $tax_display ) {
					$subtotal += $item['line_subtotal_tax'];
				}
			}

			$subtotal = wc_price( $subtotal, array('currency' => $this->get_order_currency()) );

			if ( $tax_display == 'excl' && $this->prices_include_tax ) {
				$subtotal .= ' <small>' . WC()->countries->ex_tax_or_vat() . '</small>';
			}

		} else {

			if ( 'incl' == $tax_display ) {
				return '';
			}

			foreach ( $this->get_items() as $item ) {

				$subtotal += $item['line_subtotal'];

			}

			// Add Shipping Costs
			$subtotal += $this->get_total_shipping();

			// Remove non-compound taxes
			foreach ( $this->get_taxes() as $tax ) {

				if ( ! empty( $tax['compound'] ) ) {
					continue;
				}

				$subtotal = $subtotal + $tax['tax_amount'] + $tax['shipping_tax_amount'];

			}

			// Remove discounts
			$subtotal = $subtotal - $this->get_cart_discount();

			$subtotal = wc_price( $subtotal, array('currency' => $this->get_order_currency()) );
		}

		return apply_filters( 'woocommerce_order_subtotal_to_display', $subtotal, $compound, $this );
	}

	/**
	 * Get totals for display on pages and in emails.
	 *
	 * @return array
	 */
	public function get_order_item_totals( $tax_display = '' ) {

		if ( ! $tax_display ) {
			$tax_display = $this->tax_display_cart;
		}

		$total_rows = array();

		if ( $subtotal = $this->get_subtotal_to_display( false, $tax_display ) ) {
			$total_rows['cart_subtotal'] = array(
				'label' => __( 'Cart Subtotal:', 'woocommerce' ),
				'value'	=> $subtotal
			);
		}

		if ( $this->get_cart_discount() > 0 ) {
			$total_rows['cart_discount'] = array(
				'label' => __( 'Cart Discount:', 'woocommerce' ),
				'value'	=> '-' . $this->get_cart_discount_to_display()
			);
		}

		if ( $this->get_shipping_method() ) {
			$total_rows['shipping'] = array(
				'label' => __( 'Shipping:', 'woocommerce' ),
				'value'	=> $this->get_shipping_to_display()
			);
		}

		if ( $fees = $this->get_fees() )

			foreach( $fees as $id => $fee ) {

				if ( apply_filters( 'woocommerce_get_order_item_totals_excl_free_fees', $fee['line_total'] + $fee['line_tax'] == 0, $id ) ) {
					continue;
				}

				if ( 'excl' == $tax_display ) {

					$total_rows[ 'fee_' . $id ] = array(
						'label' => $fee['name'] . ':',
						'value'	=> wc_price( $fee['line_total'], array('currency' => $this->get_order_currency()) )
					);

				} else {

					$total_rows[ 'fee_' . $id ] = array(
						'label' => $fee['name'] . ':',
						'value'	=> wc_price( $fee['line_total'] + $fee['line_tax'], array('currency' => $this->get_order_currency()) )
					);
				}
			}

		// Tax for tax exclusive prices
		if ( 'excl' == $tax_display ) {

			if ( get_option( 'woocommerce_tax_total_display' ) == 'itemized' ) {

				foreach ( $this->get_tax_totals() as $code => $tax ) {

					$total_rows[ sanitize_title( $code ) ] = array(
						'label' => $tax->label . ':',
						'value'	=> $tax->formatted_amount
					);
				}

			} else {

				$total_rows['tax'] = array(
					'label' => WC()->countries->tax_or_vat() . ':',
					'value'	=> wc_price( $this->get_total_tax(), array('currency' => $this->get_order_currency()) )
				);
			}
		}

		if ( $this->get_order_discount() > 0 ) {
			$total_rows['order_discount'] = array(
				'label' => __( 'Subscription Discount:', 'woocommerce' ),
				'value'	=> '-' . $this->get_order_discount_to_display()
			);
		}

		if ( $this->get_total() > 0 ) {
			$total_rows['payment_method'] = array(
				'label' => __( 'Payment Method:', 'woocommerce' ),
				'value' => $this->payment_method_title
			);
		}

		$total_rows['order_total'] = array(
			'label' => __( 'Recurring Total:', 'woocommerce' ),
			'value'	=> $this->get_formatted_order_total()
		);

		// Tax for inclusive prices
		if ( 'yes' == get_option( 'woocommerce_calc_taxes' ) && 'incl' == $tax_display ) {

			$tax_string_array = array();

			if ( 'itemized' == get_option( 'woocommerce_tax_total_display' ) ) {

				foreach ( $this->get_tax_totals() as $code => $tax ) {
					$tax_string_array[] = sprintf( '%s %s', $tax->formatted_amount, $tax->label );
				}

			} else {
				$tax_string_array[] = sprintf( '%s %s', wc_price( $this->get_total_tax(), array('currency' => $this->get_order_currency()) ), WC()->countries->tax_or_vat() );
			}

			if ( ! empty( $tax_string_array ) ) {
				$total_rows['order_total']['value'] .= ' ' . sprintf( __( '(Includes %s)', 'woocommerce' ), implode( ', ', $tax_string_array ) );
			}
		}

		return apply_filters( 'woocommerce_get_order_item_totals', $total_rows, $this );
	}

	/**
	 * Get the details of the subscription for use with @see wcs_price_string()
	 *
	 * @return array
	 */
	protected function get_price_string_details( $amount = 0, $display_ex_tax_label = false ) {

		$subscription_details = array(
			'currency'              => $this->get_order_currency(),
			'recurring_amount'      => $amount,
			'subscription_period'   => $this->billing_period,
			'subscription_interval' => $this->billing_interval,
			'display_ex_tax_label'  => $display_ex_tax_label,
		);

		return apply_filters('woocommerce_subscription_price_string_details', $subscription_details, $this );
	}

	/**
	 * Cancel the order and restore the cart (before payment)
	 *
	 * @param string $note (default: '') Optional note to add
	 */
	public function cancel_order( $note = '' ) {

		$next_payment_timestamp = $this->get_time( 'next_payment' );
		$end_timestamp          = $this->get_time( 'end' );

		// Cancel for real if we're already pending cancellation
		if ( ! $this->has_status( 'pending-cancellation' ) && ( $next_payment_timestamp > 0 || $end_timestamp > 0 ) ) {

			if ( $next_payment_timestamp > current_time( 'timestamp', true ) ) {
				$end_time = $this->get_date( 'next_payment' );
			} else {
				$end_time = $this->get_date( 'end' );
			}

			$this->update_date( 'end', $end_time );
			$this->update_status( 'pending-cancellation', $note );

		// If the customer hasn't been through the pending cancellation period yet set the subscription to be pending cancellation
		} else {

			$this->update_date( 'end', current_time( 'mysql', true ) );
			$this->update_status( 'cancelled', $note );

		}
	}


	/*** Some of WC_Abstract_Order's methods should not be used on a WC_Subscription ***********/

	/**
	 * Generates a URL for the thanks page (order received)
	 *
	 * @return string
	 */
	public function get_checkout_order_received_url() {
		throw new Exception( __METHOD__ . '() is not available on an instance of ' . __CLASS__ );
	}

	/**
	 * Generates a URL so that a customer can pay for their (unpaid - pending) order. Pass 'true' for the checkout version which doesn't offer gateway choices.
	 *
	 * @param  boolean $on_checkout
	 * @return string
	 */
	public function get_checkout_payment_url( $on_checkout = false ) {
		throw new Exception( __METHOD__ . '() is not available on an instance of ' . __CLASS__ );
	}

	/**
	 * Get transaction id for the order
	 *
	 * @return string
	 */
	public function get_transaction_id() {
		throw new Exception( __METHOD__ . '() is not available on an instance of ' . __CLASS__ );
	}


	/*** Refund related functions are required for the Edit Order/Subscription screen, but they aren't used on a subscription ************/

	/**
	 * Get order refunds
	 *
	 * @since 2.2
	 * @return array
	 */
	public function get_refunds() {
		if ( ! is_array( $this->refunds ) ) {
			$this->refunds = array();
		}
		return $this->refunds;
	}

	/**
	 * Get amount already refunded
	 *
	 * @since 2.2
	 * @return int|float
	 */
	public function get_total_refunded() {
		return 0;
	}

	/**
	 * Get the refunded amount for a line item
	 *
	 * @param  int $item_id ID of the item we're checking
	 * @param  string $item_type type of the item we're checking, if not a line_item
	 * @return integer
	 */
	public function get_qty_refunded_for_item( $item_id, $item_type = 'line_item' ) {
		return 0;
	}

	/**
	 * Get the refunded amount for a line item
	 *
	 * @param  int $item_id ID of the item we're checking
	 * @param  string $item_type type of the item we're checking, if not a line_item
	 * @return integer
	 */
	public function get_total_refunded_for_item( $item_id, $item_type = 'line_item' ) {
		return 0;
	}

	/**
	 * Get the refunded amount for a line item
	 *
	 * @param  int $item_id ID of the item we're checking
	 * @param  int $tax_id ID of the tax we're checking
	 * @param  string $item_type type of the item we're checking, if not a line_item
	 * @return integer
	 */
	public function get_tax_refunded_for_item( $item_id, $tax_id, $item_type = 'line_item' ) {
		return 0;
	}
}
