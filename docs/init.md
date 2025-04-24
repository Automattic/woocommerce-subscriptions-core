# WooCommerce Subscriptions Core Initialization Sequence

This document explains how WooCommerce Subscriptions Core is loaded and initialized under different contexts including admin pages, customer-facing pages, Action Scheduler events, REST API requests, and AJAX calls.

## Core Initialization Flow

Regardless of the context, WooCommerce Subscriptions Core follows this base initialization sequence:

1. The main plugin class `WC_Subscriptions_Core_Plugin` is instantiated
2. The autoloader is set up to handle class loading
3. Constants are defined via `define_constants()`
4. Required files are included via `includes()`
5. Core components are initialized via `init()`
6. Hooks are registered via `init_hooks()`

### Key Components Initialized

During the `init()` method, the following core components are initialized:

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

### Hooks Registration

The `init_hooks()` method sets up the following key hooks:

1. Registration of custom order types and statuses
2. Loading of translations
3. Adding plugin action links
4. Activation/deactivation procedures
5. Action Scheduler batch size configuration
6. Initialization of notification batch processor

## Context-Specific Initialization

### Admin Pages

When WooCommerce Subscriptions Core is loaded in the WordPress admin:

1. The core initialization flow runs first
2. `WC_Subscriptions_Admin::init()` is called, which:
   - Loads admin-specific scripts and styles
   - Registers admin menus and settings
   - Sets up meta boxes for subscription editing
   - Configures admin notices

3. Version-dependent admin classes are initialized via `init_version_dependant_classes()`:
   ```php
   new WCS_Admin_Post_Types();
   new WCS_Admin_Meta_Boxes();
   // ... other admin-specific components
   ```

4. Admin-specific hooks are registered for:
   - Order management screens
   - Product management screens
   - Payment gateway configuration
   - Reporting and analytics

### Customer-Facing Pages

For front-end pages visible to customers:

1. The core initialization flow runs
2. Front-end specific components are initialized:
   - `WC_Subscriptions_Cart` - manages subscription products in the cart
   - `WC_Subscriptions_Checkout` - handles checkout flow for subscriptions
   - `WC_Subscriptions_Frontend_Scripts` - loads necessary JS/CSS

3. No admin-specific components are loaded, which keeps front-end pages lean
4. `WCS_Template_Loader` manages template overrides and customizations

### Action Scheduler Events

When an Action Scheduler event runs for subscriptions:

1. WordPress core loads and the request is identified as a `cron` request (Action Scheduler uses WP Cron)
2. The core initialization flow runs, but admin UI components are not loaded
3. `WCS_Action_Scheduler` (initialized during core initialization) processes the scheduled event:
   - Retrieves the correct subscription by ID from the action arguments
   - Performs the scheduled action (payment processing, trial end, etc.)
   - Updates subscription dates and statuses as appropriate

4. The subscription status may change based on the event, which then triggers:
   - Email notifications
   - Payment processing
   - Status changes and associated actions

### REST API Requests

For REST API requests related to subscriptions:

1. WordPress identifies the request as a REST API request via `WC()->is_rest_api_request()`
2. The core initialization flow runs
3. WC Subscriptions checks if it's a REST API request using `wcs_is_rest_api_request()`
4. Admin UI components are not loaded to keep the request lightweight
5. Data handling remains consistent with standard requests, but presentation layers are skipped

### AJAX Requests

For AJAX requests related to subscriptions:

1. WordPress identifies the request as an AJAX request via `wp_doing_ajax()`
2. The core initialization flow runs
3. WC Subscriptions checks if it's an AJAX request using `wcs_doing_ajax()`
4. Depending on the specific AJAX action:
   - Admin-specific components may be loaded for admin AJAX requests
   - Front-end components for customer AJAX requests
   - The response is typically JSON formatted data rather than rendered HTML

## Key Differences Between Contexts

| Context | Admin UI Components | Frontend Components | Template Loading | Performance Considerations |
|---------|--------------------|--------------------|-----------------|---------------------------|
| Admin Pages | ✅ All loaded | ❌ Not loaded | ✅ Admin templates only | Loads more components for full functionality |
| Customer Pages | ❌ Not loaded | ✅ All loaded | ✅ Frontend templates | Focuses on cart, checkout, and my-account functionality |
| Action Scheduler | ❌ Not loaded | ❌ Not loaded | ❌ Not needed | Lightweight, focuses on data processing only |
| REST API | ❌ Not loaded | ⚠️ Partial loading | ❌ Not needed | Data-focused, returns structured data |
| AJAX | ⚠️ Context-dependent | ⚠️ Context-dependent | ❌ Not needed | Minimal loading for fast responses |

## Initialization Detection Functions

WC Subscriptions provides helper functions to determine the current request context:

```php
// Check if current request is AJAX
wcs_doing_ajax()

// Check if current request is wp-cron (includes Action Scheduler)
wcs_doing_cron()

// Check if current request is REST API
wcs_is_rest_api_request()

// Check if current request is frontend (not admin, not AJAX, not cron, not REST)
wcs_is_frontend_request()
```

## Performance Considerations

- Admin pages load the most components and have the highest overhead
- Action Scheduler events are optimized for performance with minimal component loading
- REST API and AJAX requests are designed to be lightweight for responsiveness
- Frontend pages load only what's needed for customer interactions

## Common Issues and Troubleshooting

1. **Action Scheduler Events Not Running**: Check if cron is working correctly on your server and that the action is properly scheduled in the Action Scheduler tables.

2. **Admin vs Frontend Loading Conflicts**: If you're building a custom integration, be aware that some components aren't available in all contexts.

3. **REST API Authentication**: REST API requests require proper authentication; errors often occur due to permission issues rather than initialization problems.

4. **AJAX Nonce Verification**: AJAX requests include nonce verification that can fail if the user session expires.

## Extending the Initialization Process

To extend WooCommerce Subscriptions initialization, you can:

1. Hook into the appropriate WordPress or WooCommerce action hooks
2. Use the plugin-specific filters provided by WC Subscriptions
3. Implement your initialization logic in the correct context (admin, frontend, etc.)

Example:

```php
// Add custom admin initialization
add_action('woocommerce_subscriptions_initialized', function() {
    if (is_admin() && !wcs_doing_ajax()) {
        // Admin-specific initialization
    } elseif (wcs_is_frontend_request()) {
        // Frontend-specific initialization
    }
});
``` 