<?php
/**
 * WC Subscriptions Helper Subscription class.
 *
 * @package WooCommerce/SubscriptionsCore/Tests/Helper
 */

/**
 * Class WCS_Helper_Subscription
 *
 * This helper class should ONLY be used for unit tests!
 */
class WCS_Helper_Subscription {

	/**
	 * Create an array of a simple subscription for every valid status
	 *
	 * @param array $data Data to use when creating subscriptions
	 *
	 * @return array
	 */
	public static function create_subscriptions( $data = [] ) {
		$statuses      = wcs_get_subscription_statuses();
		$subscriptions = [];
		$username      = 'testCustomer';
		$counter       = 0;

		while ( username_exists( $username ) ) {
			$username .= $counter;
			$counter++;
		}

		$customer_id = wp_insert_user(
			[
				'user_login' => $username,
				'user_pass'  => 'password',
				'user_email' => $username . '@example.com',
				'role'       => 'customer',
			]
		);

		foreach ( $statuses as $status => $name ) {
			$status = substr( $status, 3 );
			$args   = [
				'status'           => $status,
				'customer_id'      => $customer_id,
				'billing_period'   => 'month',
				'billing_interval' => 1,
			];

			if ( ! empty( $data[ $status ] ) ) {
				$args = wp_parse_args( $data[ $status ], $args );
			} elseif ( ! empty( $data ) ) { // Allow passing one set of args for all statuses
				$args = wp_parse_args( $data, $args );
			}

			$subscriptions[ $status ] = self::create_subscription( $args );
		}

		return $subscriptions;
	}

	/**
	 * Create a list of subscription in the format such that they can be read in as a DataProvider
	 *
	 * @param array $data Data to use when creating subscriptions
	 *
	 * @return array
	 */
	public static function subscriptions_data_provider( $data = [] ) {
		$statuses      = wcs_get_subscription_statuses();
		$subscriptions = [];

		foreach ( $statuses as $status => $name ) {
			$status = substr( $status, 3 );
			$args   = [
				'status'           => $status,
				'customer_id'      => 1,
				'billing_period'   => 'month',
				'billing_interval' => 1,
			];

			if ( ! empty( $data[ $status ] ) ) {
				$args = wp_parse_args( $data[ $status ], $args );
			}

			$subscriptions[] = [ $status, wcs_create_subscription( $args ) ];
		}

		return $subscriptions;
	}

	/**
	 * Create mock WC_Subcription for testing.
	 *
	 * @param array $post_meta         Custom susbcription post meta (i.e. billing period, interval)
	 * @param array $subscription_meta Custom subscription meta (i.e payment method/title, requires manual renewal)
	 *
	 * @return WC_Subscription|void
	 */
	public static function create_subscription( $post_meta = null, $subscription_meta = null ) {
		$default_args = [
			'status'           => '',
			'customer_id'      => 1,
			'start_date'       => current_time( 'mysql' ),
			'billing_period'   => 'month',
			'billing_interval' => 1,
		];

		$args              = wp_parse_args( $post_meta, $default_args );
		$default_meta_args = [
			'order_shipping'          => 0,
			'order_total'             => 10,
			'order_tax'               => 0,
			'order_shipping_tax'      => 0,
			'order_currency'          => 'GBP',
			'schedule_trial_end'      => 0,
			'schedule_end'            => 0,
			'schedule_next_payment'   => 0,
			'payment_method'          => '',
			'payment_method_title'    => '',
			'requires_manual_renewal' => 'true',
		];

		$subscription_meta_data = wp_parse_args( $subscription_meta, $default_meta_args );
		$subscription           = wcs_create_subscription( $args );

		if ( is_wp_error( $subscription ) ) {
			return;
		}

		$subscription->set_meta_data( $subscription_meta_data );
		$subscription->save();

		return $subscription;
	}

	/**
	 * An exact mirror of WC_Helper_Order::create_order, minus adding the product, because we're testing
	 * against WC versions that don't yet have that helper function in.
	 *
	 * @param array $order_data   Data to apply to the order object.
	 * @param array $billing_data Billing fields.
	 * @param bool  $simple       Whether to create a simple order, with no discounts
	 *
	 * @return WC_Order
	 */
	public static function create_order( $order_data = [], $billing_data = [], $simple = false ) {
		WC_Helper_Shipping::create_simple_flat_rate();

		$default_order_data = [
			'status'        => 'pending',
			'customer_id'   => 1,
			'customer_note' => '',
			'parent'        => null,
			'created_via'   => null,
			'cart_hash'     => null,
			'order_id'      => 0,
		];

		$_SERVER['REMOTE_ADDR'] = '127.0.0.1'; // Required, else wc_create_order throws an exception

		$order_data = wp_parse_args( $order_data, $default_order_data );
		$order      = wc_create_order( $order_data );

		// Set billing address
		$billing_address = array_merge(
			[
				'country'    => 'US',
				'first_name' => 'Jeroen',
				'last_name'  => 'Sormani',
				'company'    => 'WooCompany',
				'address_1'  => 'WooAddress',
				'address_2'  => '',
				'postcode'   => '123456',
				'city'       => 'WooCity',
				'state'      => 'NY',
				'email'      => 'admin@example.org',
				'phone'      => '555-32123',
			],
			$billing_data
		);
		$order->set_address( $billing_address, 'billing' );

		// Add shipping costs
		self::add_shipping( $order );

		// Set payment gateway
		$payment_gateways = WC()->payment_gateways->payment_gateways();
		$order->set_payment_method( $payment_gateways['bacs'] );

		if ( ! $simple ) {
			$order->set_shipping_total( 10 );
			$order->set_shipping_tax( 10 );
			$order->set_discount_total( 10 );
			$order->set_discount_tax( 10 );
			$order->set_cart_tax( 10 );
		}

		$order->set_total( 40 );
		$order->save();

		return $order;
	}

	/**
	 * Create a new order and mark it as a renewal order.
	 *
	 * @param int|WC_Subscription
	 *
	 * @return WC_Order a new order object
	 */
	public static function create_renewal_order( $subscription ) {
		return self::create_related_order( $subscription, 'renewal' );
	}

	/**
	 * Create a new order and mark it as a switch order.
	 *
	 * @param int|WC_Subscription
	 *
	 * @return WC_Order a new order object
	 */
	public static function create_switch_order( $subscription ) {
		return self::create_related_order( $subscription, 'switch' );
	}

	/**
	 * Create a new order and mark it as given related subscription order.
	 *
	 * @param WC_Subscription|int $subscription The subscription ID or object.
	 * @param string              $relation     The relation
	 *
	 * @return WC_Order
	 */
	public static function create_related_order( $subscription, $relation ) {
		if ( is_int( $subscription ) ) {
			$subscription = wcs_get_subscription( $subscription );
		}

		$order = self::create_order();

		WCS_Related_Order_Store::instance()->add_relation( $order, $subscription, $relation );
		return $order;
	}

	/**
	 * Create a new order and mark it as a parent order.
	 *
	 * @param int|WC_Subscription $subscription
	 *
	 * @return WC_Order a new order object
	 */
	public static function create_parent_order( $subscription ) {
		if ( is_int( $subscription ) ) {
			$subscription = wcs_get_subscription( $subscription );
		}
		$order = self::create_order( [ 'customer_id' => $subscription->get_customer_id() ] );

		$subscription->set_parent_id( $order->get_id() );
		$subscription->save();

		return $order;
	}

	/**
	 * Add shipping to an order in a version compatible way.
	 *
	 * @param WC_Order              An order object to add shipping to.
	 * @param WC_Shipping_Rate|null The shipping rate to add, if any.
	 * @param array|null            Array of taxes on the shipping rate, if any.
	 */
	protected static function add_shipping( $order, $shipping_rate = null, $shipping_taxes = null ) {

		if ( is_null( $shipping_taxes ) ) {
			$shipping_taxes = WC_Tax::calc_shipping_tax( '10', WC_Tax::get_shipping_tax_rates() );
		}

		if ( is_null( $shipping_rate ) ) {
			$shipping_rate = new WC_Shipping_Rate( 'flat_rate_shipping', 'Flat rate shipping', '10', $shipping_taxes, 'flat_rate' );
		}

		$item = new WC_Order_Item_Shipping();
		$item->set_props(
			[
				'method_id'    => $shipping_rate->id,
				'method_title' => $shipping_rate->label,
				'total'        => wc_format_decimal( $shipping_rate->cost ),
				'taxes'        => $shipping_rate->taxes,
				'order_id'     => $order->get_id(),
			]
		);

		foreach ( $shipping_rate->get_meta_data() as $key => $value ) {
			$item->add_meta_data( $key, $value, true );
		}

		$order->add_item( $item );
	}

	/**
	 * Add a product to an order and returns the new order item ID.
	 *
	 * @param WC_Order   $order   Order object to add the product to.
	 * @param WC_Product $product Product object being added to order.
	 * @param int        $qty     Quantity.
	 * @param array      $args    Additional line item properties.
	 *
	 * @return int
	 */
	public static function add_product( $order, $product, $qty = 1, $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'name'         => $product->get_name(),
				'tax_class'    => $product->get_tax_class(),
				'product_id'   => $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id(),
				'variation_id' => $product->is_type( 'variation' ) ? $product->get_id() : 0,
				'variation'    => $product->is_type( 'variation' ) ? $product->get_attributes() : [],
				'subtotal'     => wc_get_price_excluding_tax( $product, [ 'qty' => $qty ] ),
				'total'        => wc_get_price_excluding_tax( $product, [ 'qty' => $qty ] ),
				'quantity'     => $qty,
			]
		);

		// BW compatibility with old args
		if ( isset( $args['totals'] ) ) {
			foreach ( $args['totals'] as $key => $value ) {
				if ( 'tax' === $key ) {
					$args['total_tax'] = $value;
				} elseif ( 'tax_data' === $key ) {
					$args['taxes'] = $value;
				} else {
					$args[ $key ] = $value;
				}
			}
		}

		$item = new WC_Order_Item_Product();
		$item->set_props( $args );
		$item->set_backorder_meta();
		$item->set_order_id( $order->get_id() );
		$item->save();

		$order->add_item( $item );

		return $item->get_id();
	}
}
