# When and how to deprecate hooks and non-private functions in subscriptions-core

When breaking changes are made to a filter, action or non-private function in subscriptions-core, we need to continue to support the current behaviour of it while encouraging downstream extensions or themes to use the updated version.

When we mark a function or hook as deprecated, a deprecation notice warning will display when the function or hook is used.

-   [Filters](#filters)
-   [Actions](#actions)
-   [Non-private functions](#non-private-functions)

## Identifying functions needing deprecation

-   The type of one or more function parameters is changing.
-   A function parameter has been added or removed.
-   The method or function is non-private (e.g. `public function ...`).

## Filters

The deprecation of filters is handled where the filter is triggered, aka `apply_filter`.

In the example below, the `$post` parameter is being replaced with `$order`.

**Before**

```php
$orders_to_display = apply_filters( 'woocommerce_subscriptions_admin_related_orders_to_display', $orders_to_display, $subscriptions, $post );
```

**After**

```php
// Wrap the legacy call to apply_filter in a has_filter statement
if ( has_filter( 'woocommerce_subscriptions_admin_related_orders_to_display' ) ) {
	// $hook, $version, $replacement
	wcs_deprecated_hook( 'woocommerce_subscriptions_admin_related_orders_to_display', 'subscriptions-core 5.0.0', 'wcs_admin_subscription_related_orders_to_display' );

	/**
	 * Filters the orders to display in the Related Orders meta box.
	 *
	 * This filter is deprecated in favour of 'wcs_admin_subscription_related_orders_to_display'.
	 *
	 * @deprecated subscriptions-core 5.0.0
	 *
	 * @param array   $orders_to_display An array of orders to display in the Related Orders meta box.
	 * @param array   $subscriptions An array of subscriptions related to the order.
	 * @param WP_Post $post The order post object.
	 */
	$orders_to_display = apply_filters( 'woocommerce_subscriptions_admin_related_orders_to_display', $orders_to_display, $subscriptions, get_post( $order->get_id() ) );
}

/**
 * Filters the orders to display in the Related Orders meta box.
 *
 * @since subscriptions-core 5.0.0
 *
 * @param array    $orders_to_display An array of orders to display in the Related Orders meta box.
 * @param array    $subscriptions An array of subscriptions related to the order.
 * @param WC_Order $order The order object.
 */
$orders_to_display = apply_filters( 'wcs_admin_subscription_related_orders_to_display', $orders_to_display, $subscriptions, $order );
```

## Actions

The deprecation of actions is handled where the action is triggered, aka `do_action`.

In the example below, the `$order` parameter is being removed from the action.

**Before**

```php
do_action( 'woocommerce_subscriptions_related_orders_meta_box', $order, $post );
```

**After**

```php
if ( has_action( 'woocommerce_subscriptions_related_orders_meta_box' ) ) {
	// $hook, $version, $replacement
  wcs_deprecated_hook( 'woocommerce_subscriptions_related_orders_meta_box', 'subscriptions-core 5.1.0', 'wcs_related_orders_meta_box' );

  /**
   * Fires after the Related Orders meta box has been displayed.
   *
   * This action is deprecated in favour of 'wcs_related_orders_meta_box'.
   *
   * @deprecated subscriptions-core 5.1.0
   *
   * @param WC_Order|WC_Subscription $order The order or subscription that is being displayed.
   * @param WP_Post $post The post object that is being displayed.
   */
  do_action( 'woocommerce_subscriptions_related_orders_meta_box', $order, $post );
}

/**
 * Fires after the Related Orders meta box has been displayed.
 *
 * @since subscriptions-core 5.1.0
 *
 * @param WC_Order|WC_Subscription $order The order or subscription that is being displayed.
 */
do_action( 'wcs_related_orders_meta_box', $order );
```

## Non-private functions

In the example below, we are deprecating a `get_sign_up_fee` method and replacing it with `WC_Subscriptions_Product::get_sign_up_fee`.

**Before**

```php
/**
   * Return the sign-up fee for this product
   *
   * @return string
   */
  public function get_sign_up_fee() {
    $fee = do_some_sign_up_fee_calculation( $this );
    return $fee;
  }
```

**After**

```php
/**
   * Return the sign-up fee for this product
   *
   * @deprecated subscription-core 2.2.0 - Description of the reason for deprecation goes here.
   *
   * @return string
   */
  public function get_sign_up_fee() {
		// $function, $version, $replacement
		wcs_deprecated_function( __METHOD__, '2.2.0', 'WC_Subscriptions_Product::get_sign_up_fee( $this )' );

    return WC_Subscriptions_Product::get_sign_up_fee( $this );
  }
```
