# WooCommerce Subscriptions Core Initialization Sequence

This document explains how WooCommerce Subscriptions Core is loaded and initialized.

## Core Initialization Flow

Regardless of the context, WooCommerce Subscriptions Core follows this base initialization sequence:

1. The main plugin class `WC_Subscriptions_Core_Plugin` is instantiated
2. The autoloader is set up to handle class loading
3. Constants are defined via `define_constants()`
4. Required files are included via `includes()`
5. Core components are initialized via `init()`
6. Hooks are registered via `init_hooks()`

### Key Components Initialized

During the `WC_Subscriptions_Core_Plugin::init()` method all core components are initialized:

```php
// Key classes initialized immediately
WC_Subscriptions_Coupon::init();
WC_Subscriptions_Product::init();
WC_Subscriptions_Admin::init();
WC_Subscriptions_Manager::init();
WC_Subscriptions_Cart::init();
WC_Subscriptions_Cart_Validator::init();
WC_Subscriptions_Order::init();
WC_Subscriptions_Renewal_Order::init();
WC_Subscriptions_Checkout::init();
WC_Subscriptions_Email::init();
WC_Subscriptions_Email_Notifications::init();
WC_Subscriptions_Addresses::init();
WC_Subscriptions_Change_Payment_Gateway::init();
$payment_gateways_handler::init();
// ... and more core components

// Cart handlers are instantiated and stored
$this->add_cart_handler(new WCS_Cart_Renewal());
$this->add_cart_handler(new WCS_Cart_Resubscribe());
$this->add_cart_handler(new WCS_Cart_Initial_Payment());

// Some classes are initialized later in the request lifecycle
add_action('init', array('WC_Subscriptions_Synchroniser', 'init'));
add_action('after_setup_theme', array('WC_Subscriptions_Upgrader', 'init'), 11);
add_action('init', array('WC_PayPal_Standard_Subscriptions', 'init'), 11);
```

Some classess need to be initialized after all other plugins are loaded, this is handled by hooking `WC_Subscriptions_Core_Plugin::init_version_dependant_classes()` to `plugins_loaded` action.

### Hooks Registration

The `init_hooks()` method sets up the following key hooks:

1. Registration of custom order types and statuses
2. Loading of translations
3. Adding plugin action links
4. Activation/deactivation procedures
5. Action Scheduler batch size configuration
6. Initialization of notification batch processor

## Context-Specific Initialization

Core plugin doesn't have custom logic for it and relies on WordPress and WooCommerce logic here.
So some hooks might be skipped by WordPress or WooCommerce in various context (Admin, Store front end, REST_API or WP_Ajax).

However, WooCommerce Subscriptions Core provides global helper functions which allow to execute code within certain context only:

- `wcs_is_rest_api_request()`
- `wcs_is_checkout_blocks_api_request()`
- `wcs_doing_cron()`
- `wcs_doing_ajax()`
- `wcs_is_frontend_request()`

## Extending the Initialization Process

To extend WooCommerce Subscriptions initialization, you can:

1. Hook into the appropriate WordPress or WooCommerce action hooks
2. Use the plugin-specific filters provided by WC Subscriptions
3. Implement your initialization logic in the correct context (admin, frontend, etc.)
