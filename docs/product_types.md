# WooCommerce Subscriptions Product Types

This document provides detailed information about the product types available in WooCommerce Subscriptions Core, their characteristics, configuration options, and how they integrate with the rest of the WooCommerce ecosystem.

## Overview

WooCommerce Subscriptions extends the standard WooCommerce product system by adding three subscription-specific product types:

1. **Simple Subscription** - A subscription product with no variations
2. **Variable Subscription** - A subscription product with multiple variations
3. **Subscription Variation** - Individual variations of a variable subscription product

These product types inherit from their WooCommerce counterparts (`WC_Product_Simple`, `WC_Product_Variable`, and `WC_Product_Variation` respectively) while adding subscription-specific functionality.

## Core Subscription Properties

All subscription product types share these fundamental properties:

| Property | Description | Default | Field Name |
|----------|-------------|---------|------------|
| Subscription Price | The recurring amount charged for each billing cycle | 0 | `_subscription_price` |
| Billing Period | The frequency of subscription payments (day, week, month, year) | month | `_subscription_period` |
| Billing Interval | The number of periods between billing cycles | 1 | `_subscription_period_interval` |
| Subscription Length | The total number of payments/billing cycles | 0 (indefinite) | `_subscription_length` |
| Sign-up Fee | One-time fee charged at the beginning of the subscription | 0 | `_subscription_sign_up_fee` |
| Free Trial | Period before the first payment is charged | 0 | `_subscription_trial_length` |
| Trial Period | Unit for the free trial period (day, week, month, year) | | `_subscription_trial_period` |
| One-time Shipping | Whether shipping costs apply only to the first payment | no | `_subscription_one_time_shipping` |

## Simple Subscription Product

The simplest form of subscription product - a single item with fixed subscription terms.

### Class Details

- **Class Name**: `WC_Product_Subscription`
- **Extends**: `WC_Product_Simple`
- **Product Type**: `subscription`

### Example Usage

```php
// Create a simple subscription product
$product = new WC_Product_Subscription();

// Set subscription-specific properties
$product->set_props(array(
    'name'                      => 'Monthly Coffee Subscription',
    'regular_price'             => 19.99,
    'subscription_price'        => 19.99, // Same as regular_price
    'subscription_period'       => 'month',
    'subscription_period_interval' => 1,
    'subscription_length'       => 12, // 12 month subscription
    'subscription_sign_up_fee'  => 5.00,
    'subscription_trial_length' => 0,
));

$product->save();
```

### Unique Characteristics

- All orders created with this product will generate a subscription
- Inherits all simple product capabilities (can be virtual, downloadable, etc.)
- No variations or options available to the customer

## Variable Subscription Product

A subscription product that offers multiple variations, each potentially with different subscription terms.

### Class Details

- **Class Name**: `WC_Product_Variable_Subscription`
- **Extends**: `WC_Product_Variable`
- **Product Type**: `variable-subscription`

### Example Usage

```php
// Create a variable subscription product
$product = new WC_Product_Variable_Subscription();

$product->set_props(array(
    'name' => 'Coffee Subscription (Multiple Options)',
    'subscription_one_time_shipping' => 'yes',
));

$product->save();

// Then create variations for this product with different subscription terms
```

### Unique Characteristics

- Functions as a container for subscription variations
- Each variation can have different subscription terms
- Individual variations can have different prices, intervals, and other subscription details
- Supports all standard variable product attributes
- Display includes a "From: $X / period" price format when variations have different prices

## Subscription Variation

An individual variation within a variable subscription product.

### Class Details

- **Class Name**: `WC_Product_Subscription_Variation`
- **Extends**: `WC_Product_Variation`
- **Product Type**: `subscription_variation`

### Example Usage

```php
// Create a subscription variation for a variable subscription product
$variation = new WC_Product_Subscription_Variation();

$variation->set_props(array(
    'parent_id'                 => $variable_product_id,
    'regular_price'             => 29.99,
    'subscription_price'        => 29.99,
    'subscription_period'       => 'month',
    'subscription_period_interval' => 3, // Quarterly
    'subscription_length'       => 4, // 4 payments (1 year)
    'subscription_sign_up_fee'  => 0,
    'attributes'                => array('size' => 'large', 'grind' => 'whole-bean'),
));

$variation->save();
```

### Unique Characteristics

- Each variation can have completely different subscription settings
- Variations can differ in price, billing interval, length, trial period, etc.
- Must be associated with a parent Variable Subscription product
- Displays specific subscription terms in the variation dropdown

## Internal Product Data Structure

Subscription data is stored as post meta on the product. The main API for interacting with subscription products is through the `WC_Subscriptions_Product` class, which provides static methods for retrieving and calculating subscription-related data:

```php
// Examples of retrieving subscription data
$price = WC_Subscriptions_Product::get_price($product);
$period = WC_Subscriptions_Product::get_period($product);
$interval = WC_Subscriptions_Product::get_interval($product);
$length = WC_Subscriptions_Product::get_length($product);
$sign_up_fee = WC_Subscriptions_Product::get_sign_up_fee($product);
```

## Advanced Configuration

### Synchronized Renewals

Subscriptions can be configured to renew on specific days of the week, month, or year, regardless of the purchase date.

```php
// Set a product to renew on the 1st of every month
update_post_meta($product_id, '_subscription_payment_sync_date', 1);
```

### Free Trials

Free trials delay the first payment while still giving the customer immediate access.

- Length and period are set via `_subscription_trial_length` and `_subscription_trial_period`
- During a free trial, only the sign-up fee (if any) is charged

### Sign-up Fees

One-time fees charged at the beginning of a subscription:

- Can be used alongside free trials
- Added to first payment if no trial is set
- Stored in `_subscription_sign_up_fee` meta field

### Subscription Limits

Subscriptions can be limited to one per customer using the subscription limits feature:

```php
// Limit subscription purchase to one per customer
update_post_meta($product_id, '_subscription_limit', 'any');
```

## Payment Calculations

The actual payment amounts for subscriptions follow these rules:

1. **First Payment**:
   - Without trial: Subscription price + Sign-up fee
   - With trial: Sign-up fee only

2. **Renewal Payments**:
   - Subscription price only

3. **Shipping**:
   - If `_subscription_one_time_shipping` is "yes": First payment only
   - Otherwise: All payments include shipping

## Integration with WooCommerce

Subscription products extend core WooCommerce functionality with:

1. Custom product types registered via `WC_Subscriptions_Core_Plugin::register_order_types()`
2. Custom data stores for handling subscription-specific data
3. Extended admin UI for setting subscription parameters
4. Special cart and checkout processes for handling subscription creation

## Product Display

The product display includes subscription-specific information:

- Price strings show the recurring nature (e.g., "$19.99 / month")
- Free trial information (e.g., "with a 14-day free trial")
- Sign-up fee details (e.g., "and a $5.00 sign-up fee")
- Subscription length (e.g., "for 12 months")

## Common Development Tasks

### Creating a Subscription Product Programmatically

```php
$product = new WC_Product_Subscription();
$product->set_props(array(
    'name'                       => 'Monthly Service',
    'regular_price'              => 29.99,
    'subscription_price'         => 29.99,
    'subscription_period'        => 'month',
    'subscription_period_interval' => 1,
    'subscription_length'        => 0, // Ongoing
    'subscription_trial_length'  => 14,
    'subscription_trial_period'  => 'day',
));
$product->save();
```

### Checking if a Product is a Subscription

```php
if (WC_Subscriptions_Product::is_subscription($product)) {
    // This is a subscription product
}
```

### Getting Subscription Details from a Product

```php
$price = WC_Subscriptions_Product::get_price($product);
$period = WC_Subscriptions_Product::get_period($product);
$interval = WC_Subscriptions_Product::get_interval($product);
$length = WC_Subscriptions_Product::get_length($product);
$trial_length = WC_Subscriptions_Product::get_trial_length($product);
$trial_period = WC_Subscriptions_Product::get_trial_period($product);
$sign_up_fee = WC_Subscriptions_Product::get_sign_up_fee($product);
```

### Generating a Formatted Price String

```php
$price_string = WC_Subscriptions_Product::get_price_string($product, array(
    'price' => wc_price($price),
    'subscription_length' => true,
    'sign_up_fee' => true,
    'trial_length' => true,
));
```

## Limitations and Considerations

1. **Deletion Restrictions**: Subscription products are protected from accidental deletion since they may have active subscriptions.

2. **Variation Changes**: Be cautious when changing variation attributes as it can impact ongoing subscriptions.

3. **Product Type Changes**: Converting a subscription product to a non-subscription product type is restricted if active subscriptions exist.

4. **Performance**: Variable subscription products with many variations may impact performance during price calculations.

5. **Cart Limitations**: Some combinations of subscription products can cause checkout issues (e.g., multiple variable subscriptions with trials). 