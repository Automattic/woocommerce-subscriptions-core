# WooCommerce subscriptions-core

This package is a code library used to power core subscriptions related functionality in other products:

 - [WooCommerce Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/), a paid extension 
 - [WooCommerce Payments](https://woocommerce.com/products/woocommerce-payments/), a free payment gateway (with transaction fees)

## Dependencies

 - [WooCommerce](https://woocommerce.com/download/)

## Usage

The `/Automattic/woocommerce-subscriptions-core/` repository is treated as a _development_ repository and includes development assets, like unit tests and configuration files.

This package should be loaded as follows:

*composer.json*
```
"repositories": [
    {
        "type": "git",
        "url": "https://github.com/Automattic/woocommerce-subscriptions-core.git"
    }
],
"require": {
    "woocommerce/subscriptions-core": "1.0.0",
},
```

*my-main-plugin-file.php*
```
require_once PATH_TO_WOOCOMMERCE_SUBSCRIPTIONS_CORE . 'includes/class-wc-subscriptions-core-plugin.php';
new WC_Subscriptions_Core_Plugin();
```

## Development

This repository can also be loaded as standalone plugin, for development purposes only.

After cloning the repo, install dependencies and build:

```
npm install && composer install
npm run build
```

## Features provided in core

- Simple & Variable Subscriptions Product Types
  - Virtual and Downloadable
  - Limited Subscriptions
  - All standard product features: trials period, sign-up fees, synced, one-time shipping
- Manage Subscriptions (Update the status of subscriptions)
- All subscription global helper functions (eg. `wcs_get_subscriptions_for_order()`)
- Subscription Coupons (Sign-up fee and Recurring coupons)
- Subscriptions REST API endpoints
- Checkout Blocks + Subscriptions support
- Support for the WooCommerce Payments gateway
- Privacy/GDPR exporters for Subscriptions
