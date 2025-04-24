# WooCommerce Subscriptions Core Overview

This document provides an overview of the WooCommerce Subscriptions Core codebase, designed to help developers understand the architecture, key components, and flows.

## Introduction

WooCommerce Subscriptions Core is a library that provides subscription functionality for WooCommerce. It allows store owners to sell products with recurring payments, handling the full subscription lifecycle including creation, renewals, cancellations, and more.

## Architecture

The codebase follows a modular, object-oriented architecture that integrates with WooCommerce. Here's the high-level structure:

1. **Core Subscription Object**: `WC_Subscription` extends `WC_Order` and represents a subscription with all its properties and methods.

2. **Main Plugin Class**: `WC_Subscriptions_Core_Plugin` handles initialization, hooks, and manages the plugin's lifecycle.

3. **Functional Organization**: Most functionality is organized into classes by feature with supporting functions in dedicated files.

## Key Components

### 1. Subscription Data Model

- `WC_Subscription` - Core class that represents a subscription
- `WC_Subscriptions_Order` - Manages the relationship between orders and subscriptions
- Data is stored in the `wp_posts` table as a custom `shop_subscription` post type with meta data in `wp_postmeta`

### 2. Product Types

- `WC_Product_Subscription` - Simple subscription product
- `WC_Product_Variable_Subscription` - Variable subscription product
- `WC_Product_Subscription_Variation` - Variation of a variable subscription

### 3. Cart and Checkout Handling

- `WC_Subscriptions_Cart` - Manages subscription products in the cart
- `WC_Subscriptions_Checkout` - Handles the checkout process for subscriptions
- Special cart handlers for different scenarios: renewals, resubscribes, switches, etc.

### 4. Payment Processing

- `WC_Subscriptions_Payment_Gateways` - Manages supported payment gateways
- `WC_Subscriptions_Change_Payment_Gateway` - Handles payment method changes
- Has integrations with specific gateways (e.g., PayPal)

### 5. Subscription Management

- `WC_Subscriptions_Manager` - Core management functionality
- Handles activation, suspension, cancellation, etc.
- Date calculation and management is a significant part

### 6. Admin Interface

- Various classes in the `admin/` directory handle the admin UI
- Adds meta boxes, settings pages, list tables, etc.

### 7. Scheduling and Renewals

- `WCS_Action_Scheduler` - Handles scheduling of subscription events
- `WC_Subscriptions_Renewal_Order` - Manages renewal orders
- Uses WordPress's Action Scheduler for handling future events

## Key Flows and Entry Points

### 1. Subscription Creation

Flow typically starts when a customer purchases a subscription product:
1. Customer adds a subscription product to cart
2. Checkout process creates a parent order
3. After payment, a subscription is created via `wcs_create_subscription()`
4. Subscription is populated with data from the order

### 2. Subscription Renewals

1. Scheduled via Action Scheduler
2. When the renewal date arrives, `WC_Subscriptions_Renewal_Order` creates a renewal order
3. Payment is processed automatically for the renewal order
4. On success, dates are updated for the next renewal

### 3. Status Changes

Subscriptions can have various statuses:
- active: Currently active subscription
- on-hold: Temporarily suspended
- cancelled: Cancelled by customer or admin
- expired: Reached its end date
- pending-cancel: Will be cancelled at the end of the billing period

Status changes trigger actions that update dates, send emails, etc.

### 4. Customer-Initiated Actions

Customers can:
- Cancel subscriptions
- Change payment methods
- Update subscription items (if allowed)
- Pause/resume subscriptions (if enabled)

## Integration with WooCommerce

- Extends WooCommerce's product types, order system, and admin
- Uses WooCommerce templates and overrides them when needed
- Hooks into WooCommerce actions and filters to modify behavior

## Important Entry Points for New Features

1. `wcs-functions.php` - Core functions for working with subscriptions
2. `WC_Subscriptions_Core_Plugin::init()` - Main initialization 
3. `WC_Subscription` class - Core subscription object with main methods
4. Action hooks that are triggered on subscription events

## Key Files and Their Purpose

- `woocommerce-subscriptions-core.php` - Main plugin file with plugin information
- `wcs-functions.php` - Core helper functions for working with subscriptions
- `includes/class-wc-subscriptions-core-plugin.php` - Main plugin class that initializes everything
- `includes/class-wc-subscription.php` - Core subscription object model
- `includes/class-wc-subscriptions-order.php` - Manages the relationship between orders and subscriptions
- `includes/class-wc-subscriptions-manager.php` - Handles overall subscription management
- `includes/class-wc-subscriptions-cart.php` - Manages subscriptions in the cart
- `includes/class-wc-subscriptions-checkout.php` - Handles checkout process for subscriptions

## Directory Structure

- `/includes/` - Main PHP classes for the plugin
  - `/admin/` - Admin-specific functionality
  - `/gateways/` - Payment gateway integrations
  - `/emails/` - Email notifications
  - `/data-stores/` - Custom data store implementations
- `/templates/` - Template files for frontend display
- `/assets/` - JavaScript, CSS, and image files
- `/languages/` - Translation files

## Hooks and Extension Points

The plugin provides numerous action and filter hooks for extending its functionality. Some important ones include:

- `woocommerce_subscription_status_updated`
- `woocommerce_subscription_payment_complete`
- `woocommerce_subscription_renewal_payment_failed`
- `woocommerce_subscription_date_updated`
