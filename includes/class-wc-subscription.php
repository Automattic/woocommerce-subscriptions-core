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
	 * __isset function.
	 *
	 * @param mixed $key
	 * @return mixed
	 */
	public function __isset( $key ) {

		if ( in_array( $key, array( 'start_date', 'trial_end_date', 'next_payment_date', 'end_date', 'last_payment_date', 'order', 'payment_gateway' ) ) ) {

			$is_set = true;

		} else {

			$is_set = parent::__isset( $key );

		}

		return $is_set;
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

	/**
	 * Check if a the subscription can be changed to a new status or date
	 */
	public function can_be_updated_to( $new_status ) {

		$new_status = 'wc-' === substr( $new_status, 0, 3 ) ? substr( $new_status, 3 ) : $new_status;

		switch( $new_status ) {
			case 'pending' :
				if ( $this->has_status( array( 'auto-draft', 'draft' ) ) ) {
					$can_be_updated = true;
				} else {
					$can_be_updated = false;
				}
				break;
			case 'active' :
				if ( $this->payment_method_supports( 'subscription_reactivation' ) && $this->has_status( 'on-hold' ) ) {
					$can_be_updated = true;
				} elseif ( $this->has_status( array( 'pending', 'auto-draft', 'draft' ) ) ) {
					$can_be_updated = true;
				} else {
					$can_be_updated = false;
				}
				break;
			case 'on-hold' :
				if ( $this->payment_method_supports( 'subscription_suspension' ) && $this->has_status( array( 'active', 'pending' ) ) ) {
					$can_be_updated = true;
				} else {
					$can_be_updated = false;
				}
				break;
			case 'cancelled' :
				if ( $this->payment_method_supports( 'subscription_cancellation' ) && ( $this->has_status( 'pending-cancellation' ) || ! $this->has_ended() ) ) {
					$can_be_updated = true;
				} else {
					$can_be_updated = false;
				}
				break;
			case 'pending-cancellation' :
				// Only active subscriptions can be given the "pending cancellation" status, becuase it is used to account for a prepaid term
				if ( $this->payment_method_supports( 'subscription_cancellation' ) && $this->has_status( 'active' ) ) {
					$can_be_updated = true;
				} else {
					$can_be_updated = false;
				}
				break;
			case 'expired' :
				if ( ! $this->has_status( array( 'cancelled', 'trash' ) ) ) {
					$can_be_updated = true;
				} else {
					$can_be_updated = false;
				}
				break;
			case 'trash' :
				if ( $this->has_status( array( 'cancelled', 'expired' ) ) || $this->can_be_updated_to( 'cancelled' ) ) {
					$can_be_updated = true;
				} else {
					$can_be_updated = false;
				}
				break;
			case 'deleted' :
				if ( 'trash' == $this->get_status()  ) {
					$can_be_updated = true;
				} else {
					$can_be_updated = false;
				}
				break;
			default :
				$can_be_updated = false;
				break;
		}

		return apply_filters( 'woocommerce_can_subscription_be_updated_to_' . $new_status, $can_be_updated, $this );
	}

	/**
	 * Updates status of the subscription
	 *
	 * @param string $new_status Status to change the order to. No internal wc- prefix is required.
	 * @param string $note (default: '') Optional note to add
	 */
	public function update_status( $new_status, $note = '' ) {

		if ( ! $this->id ) {
			return;
		}

		// Standardise status names.
		$new_status     = 'wc-' === substr( $new_status, 0, 3 ) ? substr( $new_status, 3 ) : $new_status;
		$new_status_key = ( 'trash' == $new_status ) ? $new_status : 'wc-' . $new_status;
		$old_status     = $this->get_status();

		if ( $new_status !== $old_status || ! in_array( $this->post_status, array_keys( wcs_get_subscription_statuses() ) ) ) {

			// Only update is possible
			if ( ! $this->can_be_updated_to( $new_status ) ) {

				$message = sprintf( __( 'Unable to change subscription status to "%s".', 'woocommerce-subscriptions' ), $new_status );

				$this->add_order_note( $message );

				do_action( 'woocommerce_subscription_unable_to_update_status', $this->id, $new_status, $old_status );

				// Let plugins handle it if they tried to change to an invalid status
				throw new Exception( $message );

			}

			switch ( $new_status ) {

				case 'pending' :
					// Nothing to do here
				break;

				case 'pending-cancellation' :

					$end_date = $this->calculate_date( 'end_of_prepaid_term' );

					// If there is no future payment and no expiration date set, the customer has no prepaid term (this shouldn't be possible as only active subscriptions can be set to pending cancellation and an active subscription always has either an end date or next payment)
					if ( 0 == $end_date ) {
						$end_date = current_time( 'mysql', true );
					}

					$this->update_date( 'end', $end_date );
				break;

				case 'active' :
					// Recalculate and set next payment date
					$this->update_date( 'next_payment', $this->calculate_date( 'next_payment' ) );
					// Trial end date and end/expiration date don't change at all - they should be set when the subscription is first created
					wcs_make_user_active( $this->customer_user );
				break;

				case 'on-hold' :
					// Record date of suspension - 'post_modified' column?
					update_post_meta( $this->id, '_suspension_count', $this->suspension_count + 1 );
					wcs_maybe_make_user_inactive( $this->customer_user );
				break;
				case 'cancelled' :
				case 'switched' :
				case 'expired' :
					$this->update_date( 'end', current_time( 'mysql', true ) );
					wcs_maybe_make_user_inactive( $this->customer_user );
				break;

				case 'trash' :
					// Run all cancellation related functions on the subscription
					if ( ! $this->has_status( 'cancelled' ) ) {
						$this->update_status( 'cancelled' );
					}
					wp_trash_post( $this->id );
				break;
			}

			if ( 'trash' !== $new_status ) {
				wp_update_post( array( 'ID' => $this->id, 'post_status' => $new_status_key ) );
				$this->post_status = $new_status_key;
			}

			$this->add_order_note( trim( $note . ' ' . sprintf( __( 'Subscription status changed from %s to %s.', 'woocommerce-subscriptions' ), wc_get_order_status_name( $old_status ), wc_get_order_status_name( $new_status ) ) ) );

			// Status was changed
			do_action( 'woocommerce_subscription_updated_status', $this->id, $old_status, $new_status );
		}
	}

	/**
	 * Checks if the subscription requires manual renewal payments.
	 *
	 * @access public
	 * @return bool
	 */
	public function is_manual() {

		if ( WC_Subscriptions::is_duplicate_site() || empty( $this->payment_gateway ) || ( isset( $this->requires_manual_renewal ) && 'true' == $this->requires_manual_renewal ) ) {
			$is_manual = true;
		} else {
			$is_manual = false;
		}

		return $is_manual;
	}

	/**
	 * Checks if the subscription has ended.
	 *
	 * A subscription has ended if it is cancelled, trashed, switched, expired or pending cancellation.
	 */
	public function has_ended() {

		$ended_statuses = apply_filters( 'woocommerce_subscription_ended_statuses', array( 'cancelled', 'trash', 'expired', 'switched', 'pending-cancellation' ) );

		if ( $this->has_status( $ended_statuses ) ) {
			$has_ended = true;
		} else {
			$has_ended = false;
		}

		return apply_filters( 'woocommerce_subscription_has_ended', $has_ended, $this );
	}

	/**
	 * Get the number of payments completed for a subscription
	 *
	 * Completed payment include all renewal orders with a '_paid_date' set and potentially an
	 * initial order (if the subscription was created as a result of a purchase from the front
	 * end rather than manually by the store manager).
	 *
	 * @since 2.0
	 */
	public function get_completed_payment_count() {

		$completed_payment_count = ( ! empty( $this->order ) && isset( $this->order->paid_date ) ) ? 1 : 0;

		$paid_renewal_orders = get_posts( array(
			'posts_per_page' => -1,
			'post_parent'    => $this->id,
			'post_status'    => 'any',
			'post_type'      => 'shop_order',
			'orderby'        => 'date',
			'order'          => 'desc',
			'meta_key'       => '_paid_date',
			'meta_compare'   => 'EXISTS',
		) );

		if ( ! empty( $paid_renewal_orders ) ) {
			$completed_payment_count += count( $paid_renewal_orders );
		}

		return apply_filters( 'woocommerce_subscription_completed_payment_count', $completed_payment_count, $this );
	}

	/**
	 * Get the number of payments completed for a subscription
	 *
	 * Completed payment include all renewal orders with a '_paid_date' set and potentially an
	 * initial order (if the subscription was created as a result of a purchase from the front
	 * end rather than manually by the store manager).
	 *
	 * @since 2.0
	 */
	public function get_failed_payment_count() {

		$failed_payment_count = ( ! empty( $this->order ) && $this->order->has_status( 'wc-failed' ) ) ? 1 : 0;

		$failed_renewal_orders = get_posts( array(
			'posts_per_page' => -1,
			'post_parent'    => $this->id,
			'post_status'    => 'wc-failed',
			'post_type'      => 'shop_order',
			'orderby'        => 'date',
			'order'          => 'desc',
		) );

		if ( ! empty( $failed_renewal_orders ) ) {
			$failed_payment_count += count( $failed_renewal_orders );
		}

		return apply_filters( 'woocommerce_subscription_failed_payment_count', $failed_payment_count, $this );
	}

	/**
	 * Returns the total amount charged at the outset of the Subscription.
	 *
	 * This may return 0 if there is a free trial period or the subscription was synchronised, and no sign up fee,
	 * otherwise it will be the sum of the sign up fee and price per period.
	 *
	 * @return float The total initial amount charged when the subscription product in the order was first purchased, if any.
	 * @since 2.0
	 */
	public function get_total_initial_payment() {
		$initial_total = ( ! empty( $this->order ) ) ? $this->order->get_total() : 0;
		return apply_filters( 'woocommerce_subscription_total_initial_payment', $initial_total, $this );
	}

	/**
	 * Update the internal tally of suspensions on this subscription since the last payment.
	 *
	 * @return int The count of suspensions
	 * @since 2.0
	 */
	public function update_suspension_count( $new_count ) {
		$this->suspension_count = $new_count;
		update_post_meta( $this->id, '_suspension_count', $this->suspension_count );
		return $this->suspension_count;
	}

	/*** Date methods *****************************************************/

	/**
	 * Get the MySQL formatted date for a specific piece of the subscriptions schedule
	 *
	 * @param string $date_type 'start', 'trial_end', 'next_payment', 'last_payment' or 'end'
	 * @param string $timezone The timezone of the $datetime param, either 'gmt' or 'site'. Default 'gmt'.
	 */
	public function get_date( $date_type, $timezone = 'gmt' ) {

		// Accept dates with a '_date' suffix, like 'next_payment_date' or 'start_date'
		$date_type = str_replace( '_date', '', $date_type );

		if ( ! empty( $date_type ) && ! isset( $this->schedule->{$date_type} ) ) {
			switch ( $date_type ) {
				case 'start' :
					$this->schedule->{$date_type} = get_gmt_from_date( $this->post->post_date ); // why not just use post_date_gmt? Because when a post is first created, it has a post_date but not a post_date_gmt value
					break;
				case 'next_payment' :
				case 'trial_end' :
				case 'end' :
					$this->schedule->{$date_type} = get_post_meta( $this->id, wcs_get_date_meta_key( $date_type ), true );
					break;
				case 'last_payment' :
					$this->schedule->{$date_type} = $this->get_last_payment_date();
					break;
				default :
					$this->schedule->{$date_type} = 0;
					break;
			}

			if ( empty( $this->schedule->{$date_type} ) || false === $this->schedule->{$date_type} ) {
				$this->schedule->{$date_type} = 0;
			}
		}

		if ( empty( $date_type ) ) {
			$date = 0;
		} elseif ( 0 != $this->schedule->{$date_type} && 'gmt' != strtolower( $timezone ) ) {
			$date = get_date_from_gmt( $this->schedule->{$date_type} );
		} else {
			$date = $this->schedule->{$date_type};
		}

		return apply_filters( 'woocommerce_subscription_get_' . $date_type . '_date', $date, $timezone, $this );
	}

	/**
	 * Returns a string representation of a subscription date in the site's time (i.e. not GMT/UTC timezone).
	 *
	 * @param string $date_type 'start', 'trial_end', 'next_payment', 'last_payment', 'end' or 'end_of_prepaid_term'
	 */
	public function get_date_to_display( $date_type = 'next_payment' ) {

		$date_type = str_replace( '_date', '', $date_type );

		$timestamp_gmt = $this->get_time( $date_type, 'gmt' );

		// Don't display next payment date when the subscription is inactive
		if ( 'next_payment' == $date_type && ! $this->has_status( 'active' ) ) {
			$timestamp_gmt = 0;
		}

		if ( $timestamp_gmt > 0 ) {

			$time_diff = $timestamp_gmt - current_time( 'timestamp', true );

			if ( $time_diff > 0 && $time_diff < WEEK_IN_SECONDS ) {
				$date_to_display = sprintf( __( 'In %s', 'woocommerce-subscriptions' ), human_time_diff( current_time( 'timestamp', true ), $timestamp_gmt ) );
			} else {
				$date_to_display = date_i18n( wc_date_format(), $this->get_time( $date_type, 'site' ) );
			}

		} else {
			switch ( $date_type ) {
				case 'end' :
					$date_to_display = __( 'Not yet ended', 'woocommerce-subscriptions' );
					break;
				case 'next_payment' :
				case 'trial_end' :
				default :
					$date_to_display = __( '-', 'woocommerce-subscriptions' );
					break;
			}
		}

		return apply_filters( 'woocommerce_subscription_date_to_display', $date_to_display, $date_type, $this );
	}

	/**
	 * Get the timestamp for a specific piece of the subscriptions schedule
	 *
	 * @param string $date_type 'start', 'trial_end', 'next_payment', 'last_payment', 'end' or 'end_of_prepaid_term'
	 * @param string $timezone The timezone of the $datetime param. Default 'gmt'.
	 */
	public function get_time( $date_type, $timezone = 'gmt' ) {

		$datetime = $this->get_date( $date_type, $timezone );

		if ( 0 !== $datetime ) {
			$datetime = strtotime( $datetime );
		}

		return $datetime;
	}

	/**
	 * Set a date on the subscription.
	 *
	 * Setting one date may actually affect others. For example, if setting the next payment date,
	 * and there is a free trial and/or expiration date on the subscription, and the new payment date
	 * is after those dates, they will be pushed back.
	 *
	 * @param string $date_type 'start', 'trial_end', 'next_payment', 'last_payment' or 'end'
	 * @param mixed $datetime A MySQL formatted Date/time string.
	 * @param string $timezone The timezone of the $datetime param. Default 'gmt'.
	 */
	public function update_date( $date_type, $datetime, $timezone = 'gmt' ) {
		global $wpdb;

		// Do we actually need to delete the date?
		if ( 0 == $datetime ) {
			return $this->delete_date( $date_type );
		}

		if ( false === strptime( $datetime, '%Y-%m-%d %H:%M:%S' ) ) {
			throw new InvalidArgumentException( __( 'Invalid date. The date must be of the format: "Y-m-d H:i:s".', 'woocommerce-subscriptions' ) );
		}

		$is_updated = false;

		// Accept dates with a '_date' suffix, like 'next_payment_date' or 'start_date'
		$date_type = str_replace( '_date', '', $date_type );

		// Given date not in UTC, convert it
		if ( 'gmt' != strtolower( $timezone ) ) {
			$datetime = get_gmt_from_date( $datetime );
		}

		// Given date same as existing date, ignore it.
		if ( $datetime == $this->get_date( $date_type ) ) {
			return $is_updated;
		}

		// Make sure some dates are before next payment date
		if ( in_array( $date_type, array( 'start', 'trial_end' ) ) ) {
			$next_payment_timestamp = $this->get_time( 'next_payment' );
			if ( 0 != $next_payment_timestamp && strtotime( $datetime ) > $next_payment_timestamp ) {
				switch ( $date_type ) {
					case 'start' :
						$message = __( 'The start date must occur before the next payment date.', 'woocommerce-subscriptions' );
					break;
					case 'trial_end' :
						$message = __( 'The trial end date must occur before the next payment date.', 'woocommerce-subscriptions' );
					break;
				}
				throw new Exception( $message );
			}
		}

		// Make sure some dates are before the expiration date
		if ( in_array( $date_type, array( 'start', 'trial_end', 'next_payment' ) ) ) {
			$end_timestamp = $this->get_time( 'end' );
			if ( 0 != $end_timestamp && strtotime( $datetime ) > $end_timestamp ) {
				switch ( $date_type ) {
					case 'start' :
						$message = __( 'The start date must occur before the end date.', 'woocommerce-subscriptions' );
					break;
					case 'trial_end' :
						$message = __( 'The trial end date must occur before the end date.', 'woocommerce-subscriptions' );
					break;
					case 'next_payment' :
						$message = __( 'The next payment date must occur before the end date.', 'woocommerce-subscriptions' );
					break;
				}
				throw new Exception( $message );
			}
		}

		// Make sure next payment and expiration dates are after trial end date
		if ( in_array( $date_type, array( 'next_payment', 'end' ) ) ) {
			$trial_end_timestamp = $this->get_time( 'trial_end' );
			if ( 0 != $trial_end_timestamp && strtotime( $datetime ) < $trial_end_timestamp ) {
				switch ( $date_type ) {
					case 'next_payment' :
						$message = __( 'The next payment date must occur after the trial end date.', 'woocommerce-subscriptions' );
					break;
					case 'end' :
						$message = __( 'The end date must occur after the trial end date.', 'woocommerce-subscriptions' );
					break;
				}
				throw new Exception( $message );
			}
		}

		// Make sure expiration date is after next payment
		if ( in_array( $date_type, array( 'end' ) ) ) {
			$next_payment_timestamp = $this->get_time( 'next_payment' );
			if ( 0 != $next_payment_timestamp && strtotime( $datetime ) < $next_payment_timestamp ) {
				throw new Exception( __( 'The expiration date must occur after the next payment date.', 'woocommerce-subscriptions' ) );
			}
		}

		switch ( $date_type ) {
			case 'next_payment' :
			case 'trial_end' :
			case 'end' :
				$is_updated = update_post_meta( $this->id, wcs_get_date_meta_key( $date_type ), $datetime );
				break;
			case 'start' :
				$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->posts SET post_date = %s, post_date_gmt = %s WHERE ID = %s", get_date_from_gmt( $datetime ), $datetime, $this->id ) ); // Don't use wp_update_post() to avoid infinite loopshere array(
				$is_updated = true;
				break;
			case 'last_payment' :
				$this->update_last_payment_date( $datetime );
				$is_updated = true;
				break;
		}

		if ( $is_updated ) {
			$this->schedule->{$date_type} = $datetime;
			do_action( 'woocommerce_subscription_updated_date', $this->id, $date_type, $datetime );
		}

		return $is_updated;
	}

	/**
	 * Remove a date from a subscription.
	 *
	 * @param string $date_type 'trial_end', 'next_payment' or 'end'. The 'start' and 'last_payment' date types will throw an exception.
	 */
	public function delete_date( $date_type ) {

		// Accept dates with a '_date' suffix, like 'next_payment_date' or 'start_date'
		$date_type = str_replace( '_date', '', $date_type );

		// Make sure some dates are before next payment date
		if ( in_array( $date_type, array( 'start', 'last_payment' ) ) ) {
			switch ( $date_type ) {
				case 'start' :
					$message = __( 'The start date of a subscription can not be deleted, only updated.', 'woocommerce-subscriptions' );
				break;
				case 'last_payment' :
					$message = __( 'The last payment date of a subscription can not be deleted. You must delete the order.', 'woocommerce-subscriptions' );
				break;
			}
			throw new Exception( $message );
		}

		$this->schedule->{$date_type} = 0;
		update_post_meta( $this->id, wcs_get_date_meta_key( $date_type ), $this->schedule->{$date_type} );
		do_action( 'woocommerce_subscription_deleted_date', $this->id, $date_type );
	}

	/**
	 * Check if a given date type can be updated for this subscription.
	 *
	 * @param string $date_type 'start', 'trial_end', 'next_payment', 'last_payment' or 'end'
	 */
	public function can_date_be_updated( $date_type ) {

		switch( $date_type ) {
			case 'start' :
				if ( $this->has_status( 'pending' ) ) {
					$can_date_be_updated = true;
				} else {
					$can_date_be_updated = false;
				}
				break;
			case 'trial_end' :
				if ( $this->get_completed_payment_count() < 2 && ! $this->has_ended() && ( $this->has_status( 'pending' ) || $this->payment_method_supports( 'subscription_date_changes' ) ) ) {
					$can_date_be_updated = true;
				} else {
					$can_date_be_updated = false;
				}
				break;
			case 'next_payment' :
			case 'end' :
				if ( ! $this->has_ended() && ( $this->has_status( 'pending' ) || $this->payment_method_supports( 'subscription_date_changes' ) ) ) {
					$can_date_be_updated = true;
				} else {
					$can_date_be_updated = false;
				}
				break;
			case 'last_payment' :
				$can_date_be_updated = true;
				break;
			default :
				$can_date_be_updated = false;
				break;
		}

		return apply_filters( 'woocommerce_subscription_can_date_be_updated', $can_date_be_updated, $date_type, $this );
	}

	/**
	 * Calculate a given date for the subscription in GMT/UTC.
	 *
	 * @param string $date_type 'trial_end', 'next_payment', 'end_of_prepaid_term' or 'end'
	 */
	public function calculate_date( $date_type ) {

		switch ( $date_type ) {
			case 'next_payment' :
				$date = $this->calculate_next_payment_date();
				break;
			case 'trial_end' :
				if ( $this->get_completed_payment_count() >= 2 ) {
					$date = 0;
				} else {
					// By default, trial end is the same as the next payment date
					$date = $this->calculate_next_payment_date();
				}
				break;
			case 'end_of_prepaid_term' :

				$next_payment_time = $this->get_time( 'next_payment' );
				$end_time          = $this->get_time( 'end' );

				// If there was a future payment, the customer has paid up until that payment date
				if ( $this->get_time( 'next_payment' ) >= current_time( 'timestamp', true ) ) {
					$date = $this->get_date( 'next_payment' );
				// If there is no future payment and no expiration date set, the customer has no prepaid term (this shouldn't be possible as only active subscriptions can be set to pending cancellation and an active subscription always has either an end date or next payment)
				} elseif ( 0 == $next_payment_time || $end_time <= current_time( 'timestamp', true ) ) {
					$date = current_time( 'mysql', true );
				} else {
					$date = $this->get_date( 'end' );
				}
				break;
			default :
				$date = 0;
				break;
		}

		return apply_filters( 'woocommerce_subscription_calculated_' . $date_type . '_date', $date, $this );
	}

	/**
	 * Calculates the next payment date for a subscription
	 *
	 */
	protected function calculate_next_payment_date() {

		$next_payment_date = 0;

		// If the subscription is not active, there is no next payment date
		if ( $this->has_status( 'active' ) ) {

			$start_time        = $this->get_time( 'start' );
			$trial_end_time    = $this->get_time( 'trial_end' );
			$last_payment_time = $this->get_time( 'last_payment' );
			$end_time          = $this->get_time( 'end' );

			// If the subscription has a free trial period, and we're still in the free trial period, the next payment is due at the end of the free trial
			if ( $trial_end_time > current_time( 'timestamp', true ) ) {

				$next_payment_date = $this->get_date( 'trial_end' );

			// The next payment date is {interval} billing periods from the start date, trial end date or last payment date
			} else {

				if ( $last_payment_time > $trial_end_time ) {
					$from_timestamp = $last_payment_time;
				} elseif ( $trial_end_time > $start_time ) {
					$from_timestamp = $trial_end_time;
				} else {
					$from_timestamp = $start_time;
				}

				$next_payment_timestamp = wcs_add_time( $this->billing_interval, $this->billing_period, $from_timestamp );

				// Make sure the next payment is in the future
				$i = 1;
				while ( $next_payment_timestamp < current_time( 'timestamp', true ) && $i < 30 ) {
					$next_payment_timestamp = wcs_add_time( $this->billing_interval, $this->billing_period, $next_payment_timestamp );
					$i += 1;
				}

			}

			// If the subscription has an end date and the next billing period comes after that, return 0
			if ( 0 != $end_time && ( $next_payment_timestamp + 120 ) > $end_time ) {
				$next_payment_timestamp =  0;
			}

			if ( $next_payment_timestamp > 0 ) {
				$next_payment_date = date( 'Y-m-d H:i:s', $next_payment_timestamp );
			}
		}

		return apply_filters( 'woocommerce_subscription_calculated_next_payment_date', $next_payment_date, $this );
	}

	/**
	 * Find the last payment date, either based on the original order used to purchase the subscription or it's last paid renewal order
	 */
	protected function get_last_payment_date() {

		$last_paid_renewal_order = get_posts( array(
			'posts_per_page' => 1,
			'post_parent'    => $this->id,
			'post_status'    => 'any',
			'post_type'      => 'shop_order',
			'orderby'        => 'date',
			'order'          => 'desc',
			'meta_key'       => '_paid_date',
			'meta_compare'   => 'EXISTS',
		) );

		if ( ! empty( $last_paid_renewal_order ) ) {
			$date = get_post_meta( $last_paid_renewal_order->ID, '_paid_date', true );
		} elseif ( ! empty( $this->order ) && isset( $this->order->paid_date ) ) {
			$date = $this->order->paid_date;
		} else {
			$date = 0;
		}

		return $date;
	}

	/**
	 *
	 * @param string $datetime A MySQL formatted date/time string in GMT/UTC timezone.
	 */
	protected function update_last_payment_date( $datetime ) {

		$last_paid_renewal_order = get_posts( array(
			'posts_per_page' => 1,
			'post_parent'    => $this->id,
			'post_status'    => 'any',
			'post_type'      => 'shop_order',
			'orderby'        => 'date',
			'order'          => 'desc',
			'meta_key'       => '_paid_date',
			'meta_compare'   => 'EXISTS',
		) );

		if ( ! empty( $last_paid_renewal_order ) ) {
			update_post_meta( $last_paid_renewal_order->ID, '_paid_date', $datetime );
		} else {
			update_post_meta( $this->order->id, '_paid_date', $datetime );
		}

		return $date;
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

		// If the customer hasn't been through the pending cancellation period yet set the subscription to be pending cancellation
		if ( ! $this->has_status( array( 'pending-cancellation', 'cancelled' ) ) && $this->calculate_date( 'end_of_prepaid_term' ) > current_time( 'mysql', true ) ) {

			$this->update_status( 'pending-cancellation', $note );

		// Cancel for real if we're already pending cancellation
		} else {

			$this->update_status( 'cancelled', $note );

		}
	}

	/**
	 * Allow subscription amounts/items to bed edited if the gateway supports it.
	 *
	 * @access public
	 * @return bool
	 */
	public function is_editable() {

		if ( ! isset( $this->editable ) ) {

			if ( $this->has_status( array( 'pending', 'draft', 'auto-draft' ) ) ) {
				$this->editable = true;
			} elseif ( $this->is_manual() || $this->payment_method_supports( 'subscription_amount_changes' ) ) {
				$this->editable = true;
			} else {
				$this->editable = false;
			}
		}

		return apply_filters( 'wc_order_is_editable', $this->editable, $this );
	}


	/**
	 * When a payment fails, either for the original purchase or a renewal payment, this function processes it.
	 *
	 * @since 2.0
	 */
	public function payment_failed( $new_status = 'on-hold' ) {

		// Log payment failure on order
		$this->add_order_note( __( 'Payment failed.', 'woocommerce-subscriptions' ) );

		// Allow a short circuit for plugins & payment gateways to force max failed payments exceeded
		if ( 'cancelled' == $new_status || apply_filters( 'woocommerce_subscription_max_failed_payments_exceeded', false, $this ) ) {

			$this->update_status( 'cancelled' );

			$this->add_order_note( __( 'Subscription Cancelled: maximum number of failed payments reached.', 'woocommerce-subscriptions' ) );

		} else {

			// Place the subscription on-hold
			$this->update_status( $new_status );
		}

		do_action( 'woocommerce_processed_subscription_payment_failure', $this, $new_status );
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

	/**
	 * Get the related orders for a subscription, including renewal orders and the initial order (if any)
	 *
	 * @param string The columns to return, either 'all' or 'ids'
	 * @since 2.0
	 */
	public function get_related_orders( $return_fields = 'ids' ) {

		$return_fields = ( 'ids' == $return_fields ) ? $return_fields : 'all';

		$related_orders = array();

		$related_posts = get_posts( array(
			'posts_per_page' => -1,
			'post_parent'    => $this->id,
			'post_status'    => 'any',
			'post_type'      => 'shop_order',
			'fields'         => $return_fields,
		) );

		if ( 'all' == $return_fields ) {

			if ( ! empty( $this->order ) ) {
				$related_orders[] = $this->order;
			}

			foreach ( $related_orders as $post_id ) {
				$related_orders[] = wc_get_order( $post_id );
			}

		} else {

			// Return IDs only
			if ( isset( $this->order->id ) ) {
				$related_orders[] = $this->order->id;
			}

			$related_orders = array_merge( $related_orders, $related_posts );
		}

		return apply_filters( 'woocommerce_subscription_related_orders', $related_orders, $this );
	}

}
