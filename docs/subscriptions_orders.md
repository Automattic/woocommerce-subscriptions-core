# WooCommerce Subscriptions Order Types

This document outlines the various order types in WooCommerce Subscriptions, their relationships to each other, and specific characteristics of each type.

## Overview

WooCommerce Subscriptions extends the standard WooCommerce order system by adding a subscription object and creating special relationships between orders. The core object types are:

1. **Subscriptions** (`shop_subscription`): Represents an ongoing subscription agreement
2. **Parent Orders** (`shop_order`): The initial order that created the subscription
3. **Renewal Orders** (`shop_order`): Orders generated for recurring payments
4. **Switch Orders** (`shop_order`): Orders generated when a customer changes their subscription
5. **Resubscribe Orders** (`shop_order`): Orders generated when a customer reactivates a cancelled subscription

## The `WC_Subscription` Class

### Object Type

The `WC_Subscription` class extends `WC_Order` and represents an ongoing subscription. It has a unique order type identifier:

```php
public $order_type = 'shop_subscription';
```

### Key Properties

Subscriptions have additional data beyond standard orders:

```php
protected $extra_data = array(
    'billing_period'          => '',
    'billing_interval'        => 1,
    'suspension_count'        => 0,
    'requires_manual_renewal' => true,
    'cancelled_email_sent'    => false,
    'trial_period'            => '',
    'last_order_date_created' => null,
    'schedule_trial_end'      => null,
    'schedule_next_payment'   => null,
    'schedule_cancelled'      => null,
    'schedule_end'            => null,
    'schedule_payment_retry'  => null,
    'schedule_start'          => null,
    'switch_data'             => array(),
);
```

### Important Dates

Subscriptions have several important dates that control their lifecycle:

| Date Type | Description | 
|-----------|-------------|
| `start` | When the subscription begins |
| `trial_end` | When the trial period ends |
| `next_payment` | When the next payment is due |
| `last_payment` | When the last payment was processed |
| `end` | When the subscription is set to end |
| `cancelled` | When the subscription was cancelled |
| `payment_retry` | When the system will retry a failed payment |

Each of these dates can be accessed via:

```php
$subscription->get_date('next_payment');
```

Or set via:

```php
$subscription->update_dates(array('next_payment' => '2023-12-01'));
```

## Order Relationships

### Relationship Types

WooCommerce Subscriptions defines several types of relationships between orders and subscriptions:

```php
private static $relation_types = array(
    'renewal',
    'switch',
    'resubscribe',
);
```

These relationship types define how orders relate to each other and to subscriptions.

### How Relationships Are Stored

The relationships between orders and subscriptions are stored using meta data on the orders:

| Relationship | Meta Key | Description |
|--------------|----------|-------------|
| Parent Order → Subscription | `_subscription_id` | Links a parent order to its subscription(s) |
| Renewal Order → Subscription | `_subscription_renewal` | Links a renewal order to its subscription |
| Switch Order → Subscription | `_subscription_switch` | Links a switch order to its subscription |
| Resubscribe Order → Subscription | `_subscription_resubscribe` | Links a resubscribe order to its subscription |

### Relationship Management

Relationships are managed through the `WCS_Related_Order_Store` class and its implementations. The primary methods for managing these relationships are:

```php
// Get related orders for a subscription
$order_ids = WCS_Related_Order_Store::instance()->get_related_order_ids($subscription, 'renewal');

// Get subscriptions related to an order
$subscription_ids = WCS_Related_Order_Store::instance()->get_related_subscription_ids($order, 'renewal');

// Add a relationship
WCS_Related_Order_Store::instance()->add_relation($order, $subscription, 'renewal');

// Remove a relationship
WCS_Related_Order_Store::instance()->delete_relation($order, $subscription, 'renewal');
```

For performance, there's a cached implementation (`WCS_Related_Order_Store_Cached_CPT`) that maintains cache data on the subscription object.

## Order Types in Detail

### Parent Order

**Definition**: The initial order that created the subscription.

**Characteristics**:
- Standard `shop_order` type
- Contains subscription products that triggered subscription creation
- May contain both subscription and non-subscription products
- Processed like a normal WooCommerce order

**How to identify**:
```php
// Check if an order is a parent order
$is_parent = wcs_order_contains_subscription($order);

// Get a subscription's parent order
$parent_order = $subscription->get_parent();
```

**Key meta data**:
- `_subscription_id`: Array of subscription IDs created from this order
- `_contains_subscription`: Flag indicating the order created subscriptions

### Renewal Order

**Definition**: An order generated for a recurring payment.

**Characteristics**:
- Standard `shop_order` type
- Generated automatically based on subscription schedule
- Contains the same line items as the subscription
- Used to process payments for subscription renewals
- Can be created manually or automatically

**How to identify**:
```php
// Check if an order is a renewal order
$is_renewal = wcs_order_contains_renewal($order);

// Get a subscription's renewal orders
$renewal_orders = $subscription->get_related_orders('ids', array('renewal'));
```

**Key meta data**:
- `_subscription_renewal`: ID of the subscription this renewal is for
- `_failed_renewal_order`: Flag indicating if the renewal initially failed (set on the order itself)

### Switch Order

**Definition**: An order generated when a customer changes their subscription.

**Characteristics**:
- Standard `shop_order` type
- Created when a customer upgrades, downgrades, or otherwise modifies their subscription
- Contains the price difference between the old and new subscription terms
- Subscription is updated after the switch order is processed

**How to identify**:
```php
// Check if an order is a switch order
$is_switch = wcs_order_contains_switch($order);

// Get a subscription's switch orders
$switch_orders = $subscription->get_related_orders('ids', array('switch'));
```

**Key meta data**:
- `_subscription_switch`: ID of the subscription being switched
- `_switched_subscription_item_id`: Original order item ID that was switched
- `_switched_subscription_new_item_id`: New order item ID after switch
- `_switched_subscription_sign_up_fee_prorated`: Prorated sign up fee if applicable

### Resubscribe Order

**Definition**: An order generated when a customer reactivates a cancelled subscription.

**Characteristics**:
- Standard `shop_order` type
- Created when a customer resubscribes to a cancelled subscription
- Contains the same line items as the original subscription
- Creates a new subscription with the same terms as the previous one

**How to identify**:
```php
// Check if an order is a resubscribe order
$is_resubscribe = wcs_order_contains_resubscribe($order);

// Get a subscription's resubscribe orders
$resubscribe_orders = $subscription->get_related_orders('ids', array('resubscribe'));
```

**Key meta data**:
- `_subscription_resubscribe`: ID of the subscription being resubscribed to
- `_resubscribe_from_subscription_id`: Original subscription ID before resubscribe

## Order Status Flow

Orders and subscriptions have different status flows:

### Subscription Status Flow

- `pending` → `active` → `cancelled`/`expired`/`on-hold`
- `on-hold` → `active` or `cancelled`
- `cancelled` (terminal state)
- `expired` (terminal state)

### Order Status Flow

- `pending` → `processing`/`completed`/`failed`/`cancelled`
- `failed` → `processing` (after successful payment retry)
- `processing` → `completed`
- `completed` (terminal state)
- `cancelled` (terminal state)

## Order Generation Process

### Renewal Order Creation

1. Scheduled via Action Scheduler based on the `next_payment` date
2. Generated via `WCS_Create_Renewal_Order::create_order()`
3. Initial status is `pending`
4. Payment is processed automatically for auto-renewal subscriptions
5. Subscription dates are updated after successful payment

```php
// Manually create a renewal order
$order_id = WCS_Create_Renewal_Order::create_order($subscription);
$order = wc_get_order($order_id);
```

### Switch Order Creation

1. Initiated by customer switching products/variations
2. Calculated via `WC_Subscriptions_Switcher`
3. Prorated amounts calculated if configured
4. Processed immediately during checkout
5. Subscription updated after successful payment

### Resubscribe Order Creation

1. Initiated by customer resubscribing to canceled subscription
2. Generated via `WCS_Resubscribe_Order::create_order()`
3. Creates new subscription based on the previous one
4. Processed immediately during checkout

## Getting Related Orders

There are several ways to get related orders for a subscription:

```php
// Get all related orders (parents, renewals, switches, resubscribes)
$all_related_orders = $subscription->get_related_orders();

// Get just renewal orders
$renewal_orders = $subscription->get_related_orders('ids', array('renewal'));

// Get the parent order
$parent_order = $subscription->get_parent();

// Get the last order (most recent renewal, parent, etc.)
$last_order = $subscription->get_last_order();
```

## Implementation Details

### Order Data Store

Subscriptions use a specialized data store:

```php
protected $data_store_name = 'subscription';
```

And the implementation is:
- `WCS_Subscription_Data_Store_CPT` for traditional post storage
- `WCS_Orders_Table_Subscription_Data_Store` for HPOS (custom order tables)

### Related Order Cache

For performance, the relationships between orders are cached in subscription meta:

- `_related_orders_cache_renewal`: Array of renewal order IDs
- `_related_orders_cache_switch`: Array of switch order IDs
- `_related_orders_cache_resubscribe`: Array of resubscribe order IDs

These caches are managed by `WCS_Related_Order_Store_Cached_CPT`.

## Common Development Tasks

### Creating a Renewal Order Programmatically

```php
// Create a renewal order for a subscription
$order_id = WCS_Create_Renewal_Order::create_order($subscription);

// Set the status to processing
$order = wc_get_order($order_id);
$order->update_status('processing');

// Record payment if needed
$subscription->payment_complete_for_order($order);
```

### Adding Custom Order Relationships

The order relationship system can be extended with custom relationship types:

```php
// Add a custom relationship type
add_filter('wcs_additional_related_order_relation_types', function($relation_types) {
    $relation_types[] = 'custom_relation_type';
    return $relation_types;
});

// Use the custom relationship
WCS_Related_Order_Store::instance()->add_relation($order, $subscription, 'custom_relation_type');
```

### Detecting Order Types

```php
// Check if an order contains a subscription
if (wcs_order_contains_subscription($order)) {
    // This is a parent order
}

// Check if an order is a renewal
if (wcs_order_contains_renewal($order)) {
    // This is a renewal order
}

// Check if an order is related to a subscription
if (wcs_is_order_related_to_subscription($order)) {
    // This order is related to a subscription somehow
}
```

## Important Hooks and Filters

### Order Creation Hooks

- `woocommerce_checkout_subscription_created`: Fired when a subscription is created during checkout
- `wcs_renewal_order_created`: Fired when a renewal order is created
- `wcs_resubscribe_order_created`: Fired when a resubscribe order is created
- `wcs_switch_order_created`: Fired when a switch order is created

### Order Status Change Hooks

- `woocommerce_subscription_status_changed`: Fired when a subscription status changes
- `woocommerce_subscription_payment_complete`: Fired when a subscription payment is complete
- `woocommerce_subscription_payment_failed`: Fired when a subscription payment fails

### Order Relationship Filters

- `wcs_orders_related_subscription_ids`: Filter the subscription IDs related to an order
- `wcs_additional_related_order_relation_types`: Add custom relationship types

## Common Issues and Solutions

### Stuck Renewal Orders

If a renewal order gets stuck in `pending` status:
- Check payment gateway logs for failed transactions
- Verify the subscription status is `active`
- Manually process the payment or cancel the order

### Missing Relationships

If relationships between orders and subscriptions are missing:
- Regenerate caches using Tools > Generate Related Order Cache
- Verify order meta using `$order->get_meta('_subscription_renewal')`
- Re-establish relationships manually if needed

### Manual Renewal Link Missing

If the manual renewal link is missing for customers:
- Verify the subscription requires manual renewal (`$subscription->get_requires_manual_renewal()`)
- Check if the renewal order exists and is in `pending` status
- Ensure the subscription status is `on-hold` 