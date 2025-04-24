# WooCommerce Subscriptions Payment Gateway Integration

This document outlines how payment gateways integrate with WooCommerce Subscriptions, covering both what Subscriptions provides for payment gateways and what is expected from payment gateways to ensure reliable recurring payments.

## Overview

WooCommerce Subscriptions extends the standard WooCommerce payment gateway system to handle recurring payments. Payment gateways that support subscriptions will need to implement several key features to properly handle initial and recurring payments.

## Payment Gateway Support Flags

At its most basic level, a payment gateway must declare support for subscriptions by adding the appropriate feature flags:

```php
public function __construct() {
    // Standard gateway setup
    $this->id = 'my_gateway';
    $this->method_title = 'My Payment Gateway';
    // ...

    // Add subscription support
    $this->supports = array(
        'products',
        'refunds',
        'subscriptions',                           // Basic subscription support
        'subscription_cancellation',               // Allow canceling subscriptions
        'subscription_suspension',                 // Allow suspending subscriptions
        'subscription_reactivation',               // Allow reactivating subscriptions
        'subscription_amount_changes',             // Allow changing subscription amounts
        'subscription_date_changes',               // Allow changing subscription dates
        'subscription_payment_method_change',      // Allow changing payment method
        'subscription_payment_method_change_customer', // Allow customers to change payment method
        'subscription_payment_method_change_admin',    // Allow admins to change payment method
        'multiple_subscriptions',                  // Allow multiple subscriptions in same checkout
    );
}
```

## Payment Gateway Integration Points

There are several key integration points a payment gateway needs to implement to fully support subscriptions:

### 1. Initial Payment Processing

This is standard WooCommerce payment processing that occurs during checkout. The gateway must:

- Process the initial payment (including any sign-up fees)
- Store any necessary payment tokens for future payments
- Handle subscriptions in the cart alongside regular products

### 2. Recurring Payment Processing

The most important aspect of subscription integration is handling recurring payments:

```php
public function __construct() {
    // Other setup code...

    // Register the hook for processing scheduled subscription payments
    add_action('woocommerce_scheduled_subscription_payment_' . $this->id, array($this, 'scheduled_subscription_payment'), 10, 2);
}

/**
 * Process the subscription payment and return the result
 *
 * @param float $amount The amount to charge
 * @param WC_Order $renewal_order The renewal order
 * @return void
 */
public function scheduled_subscription_payment($amount, $renewal_order) {
    // Retrieve the payment token/customer ID stored during initial payment
    $subscription_id = $renewal_order->get_meta('_subscription_renewal');
    $subscription = wcs_get_subscription($subscription_id);
    $customer_id = $subscription->get_meta('_my_gateway_customer_id');
    $token = $subscription->get_meta('_my_gateway_payment_token');

    // Process the payment using your gateway's API
    $response = $this->api->charge(array(
        'amount' => $amount,
        'currency' => $renewal_order->get_currency(),
        'customer_id' => $customer_id,
        'token' => $token,
        'description' => sprintf(__('Subscription Renewal Order %s', 'my-gateway'), $renewal_order->get_order_number())
    ));

    // Handle the response
    if ($response->is_success) {
        // Payment succeeded
        $renewal_order->payment_complete($response->transaction_id);
        $renewal_order->add_order_note(sprintf(__('Gateway payment successful (Transaction ID: %s)', 'my-gateway'), $response->transaction_id));
    } else {
        // Payment failed
        $renewal_order->update_status('failed', sprintf(__('Gateway payment failed: %s', 'my-gateway'), $response->error_message));
        
        // Optional: Set when to retry the payment
        if ($subscription->can_retry_failed_payment()) {
            $retry_date = gmdate('Y-m-d H:i:s', time() + DAY_IN_SECONDS); // Retry after 1 day
            $subscription->update_meta_data('_schedule_payment_retry', $retry_date);
            $subscription->save();
        }
    }
}
```

### 3. Subscription Status Change Handling

Payment gateways need to react to subscription status changes:

```php
public function __construct() {
    // Other setup code...

    // Register status change hooks
    add_action('woocommerce_subscription_cancelled_' . $this->id, array($this, 'cancel_subscription'));
    add_action('woocommerce_subscription_on-hold_' . $this->id, array($this, 'suspend_subscription'));
    add_action('woocommerce_subscription_activated_' . $this->id, array($this, 'reactivate_subscription'));
}

/**
 * When a subscription is cancelled, tell the payment gateway
 */
public function cancel_subscription($subscription) {
    $subscription_id = $subscription->get_meta('_my_gateway_subscription_id');
    
    if (!empty($subscription_id)) {
        $this->api->cancel_subscription($subscription_id);
        $subscription->add_order_note(__('Gateway subscription canceled.', 'my-gateway'));
    }
}

/**
 * When a subscription is suspended, tell the payment gateway
 */
public function suspend_subscription($subscription) {
    $subscription_id = $subscription->get_meta('_my_gateway_subscription_id');
    
    if (!empty($subscription_id)) {
        $this->api->suspend_subscription($subscription_id);
        $subscription->add_order_note(__('Gateway subscription suspended.', 'my-gateway'));
    }
}

/**
 * When a subscription is reactivated, tell the payment gateway
 */
public function reactivate_subscription($subscription) {
    $subscription_id = $subscription->get_meta('_my_gateway_subscription_id');
    
    if (!empty($subscription_id)) {
        $this->api->reactivate_subscription($subscription_id);
        $subscription->add_order_note(__('Gateway subscription reactivated.', 'my-gateway'));
    }
}
```

### 4. Payment Method Change

If your gateway supports changing payment methods, you need to implement:

```php
/**
 * Process a payment method change for a subscription
 */
public function change_payment_method($subscription, $new_payment_method_id) {
    // Get the new payment token
    $token = $this->get_token_from_payment_method_id($new_payment_method_id);
    
    // Update the payment method on your payment gateway
    $response = $this->api->update_payment_method(
        $subscription->get_meta('_my_gateway_subscription_id'),
        $token
    );
    
    if ($response->is_success) {
        // Store the new token
        $subscription->update_meta_data('_my_gateway_payment_token', $token);
        $subscription->save();
        return true;
    } else {
        throw new Exception($response->error_message);
    }
}
```

## Payment Processing Models

WooCommerce Subscriptions supports two major recurring payment models:

### 1. Gateway-Managed Subscriptions

Some payment gateways (like PayPal, Stripe) can handle the subscription lifecycle themselves. In this model:

- The gateway API is used to create a subscription during initial checkout
- The gateway handles charging customers on the scheduled dates
- The gateway notifies your site via webhooks when payments are made or failed
- Subscription dates are synchronized between the gateway and WooCommerce

To support this model, add the `gateway_scheduled_payments` support flag:

```php
$this->supports = array(
    'subscriptions',
    'gateway_scheduled_payments', // Let gateway handle scheduling
    // Other support flags...
);
```

### 2. Merchant-Managed Subscriptions

In this model, WooCommerce Subscriptions handles the subscription lifecycle:

- WooCommerce Subscriptions tracks when payments are due
- When a payment is due, WC Subscriptions creates a renewal order
- The gateway is called to process a one-time payment for the renewal order
- WC Subscriptions updates subscription dates based on successful payments

This is the default model if `gateway_scheduled_payments` is not supported.

## Complete Example Gateway

Here's a simplified but complete example of a payment gateway that supports subscriptions:

```php
/**
 * Example Subscription Payment Gateway
 *
 * Provides an example implementation of a payment gateway supporting subscriptions.
 * This is a simplified example for demonstration purposes only.
 */
class WC_Gateway_Example_Subscription extends WC_Payment_Gateway {

    /**
     * Constructor for the gateway.
     */
    public function __construct() {
        $this->id                 = 'example_subscription';
        $this->icon               = apply_filters('woocommerce_example_icon', '');
        $this->has_fields         = true;
        $this->method_title       = __('Example Subscription Gateway', 'example-subscription-gateway');
        $this->method_description = __('Processes subscription payments through the Example payment processor.', 'example-subscription-gateway');
        
        // Define supported features
        $this->supports = array(
            'products',
            'subscriptions',
            'subscription_cancellation',
            'subscription_suspension',
            'subscription_reactivation',
            'subscription_amount_changes',
            'subscription_date_changes',
            'subscription_payment_method_change',
            'subscription_payment_method_change_customer',
            'subscription_payment_method_change_admin',
            'multiple_subscriptions',
        );
        
        // Load the settings
        $this->init_form_fields();
        $this->init_settings();
        
        // Define user-facing settings
        $this->title       = $this->get_option('title');
        $this->description = $this->get_option('description');
        
        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        
        // Subscription actions
        add_action('woocommerce_scheduled_subscription_payment_' . $this->id, array($this, 'scheduled_subscription_payment'), 10, 2);
        add_action('woocommerce_subscription_cancelled_' . $this->id, array($this, 'cancel_subscription'));
        add_action('woocommerce_subscription_on-hold_' . $this->id, array($this, 'suspend_subscription'));
        add_action('woocommerce_subscription_activated_' . $this->id, array($this, 'reactivate_subscription'));
        
        // API class
        $this->api = new WC_Example_Subscription_API($this->get_option('api_key'));
    }

    /**
     * Initialize Gateway Settings Form Fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('Enable/Disable', 'example-subscription-gateway'),
                'type'    => 'checkbox',
                'label'   => __('Enable Example Gateway', 'example-subscription-gateway'),
                'default' => 'no'
            ),
            'title' => array(
                'title'       => __('Title', 'example-subscription-gateway'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'example-subscription-gateway'),
                'default'     => __('Example Subscription Gateway', 'example-subscription-gateway'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', 'example-subscription-gateway'),
                'type'        => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'example-subscription-gateway'),
                'default'     => __('Pay securely using your credit card.', 'example-subscription-gateway'),
                'desc_tip'    => true,
            ),
            'api_key' => array(
                'title'       => __('API Key', 'example-subscription-gateway'),
                'type'        => 'text',
                'description' => __('Enter your Example Gateway API key.', 'example-subscription-gateway'),
                'default'     => '',
                'desc_tip'    => true,
            ),
        );
    }

    /**
     * Payment form on checkout page
     */
    public function payment_fields() {
        echo '<p>' . $this->description . '</p>';
        
        // Simulated card fields
        ?>
        <div class="form-row form-row-wide">
            <label>Card Number <span class="required">*</span></label>
            <input type="text" class="input-text" name="example_card_number" placeholder="1234 1234 1234 1234" />
        </div>
        <div class="form-row form-row-first">
            <label>Expiry Date <span class="required">*</span></label>
            <input type="text" class="input-text" name="example_card_expiry" placeholder="MM/YY" />
        </div>
        <div class="form-row form-row-last">
            <label>Card Security Code <span class="required">*</span></label>
            <input type="text" class="input-text" name="example_card_cvc" placeholder="CVC" />
        </div>
        <div class="clear"></div>
        <?php
    }

    /**
     * Process the payment and return the result
     * 
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        
        // Collect payment information (in a real gateway, you'd do card validation)
        $card_number = isset($_POST['example_card_number']) ? wc_clean($_POST['example_card_number']) : '';
        $card_expiry = isset($_POST['example_card_expiry']) ? wc_clean($_POST['example_card_expiry']) : '';
        $card_cvc = isset($_POST['example_card_cvc']) ? wc_clean($_POST['example_card_cvc']) : '';
        
        // Check if this order contains a subscription
        $contains_subscription = wcs_order_contains_subscription($order);
        
        try {
            // First process the payment
            $response = $this->api->process_payment(array(
                'amount'      => $order->get_total(),
                'currency'    => $order->get_currency(),
                'card_number' => $card_number,
                'card_expiry' => $card_expiry,
                'card_cvc'    => $card_cvc,
                'order_id'    => $order->get_id()
            ));
            
            if (!$response->is_success) {
                throw new Exception($response->error_message);
            }
            
            // Save the token for future payments
            $order->update_meta_data('_example_payment_token', $response->token);
            $order->update_meta_data('_example_customer_id', $response->customer_id);
            
            // If there's a subscription in the order, save the token in the subscription too
            if ($contains_subscription) {
                $subscriptions = wcs_get_subscriptions_for_order($order);
                
                foreach ($subscriptions as $subscription) {
                    $subscription->update_meta_data('_example_payment_token', $response->token);
                    $subscription->update_meta_data('_example_customer_id', $response->customer_id);
                    $subscription->save();
                }
            }
            
            // Mark payment complete
            $order->payment_complete($response->transaction_id);
            
            // Add note to the order
            $order->add_order_note(
                sprintf(__('Example Gateway payment successful (Transaction ID: %s)', 'example-subscription-gateway'), 
                $response->transaction_id)
            );
            
            // Remove cart
            WC()->cart->empty_cart();
            
            // Return thank you page redirect
            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url($order)
            );
            
        } catch (Exception $e) {
            wc_add_notice($e->getMessage(), 'error');
            return array(
                'result'   => 'failure',
                'messages' => $e->getMessage(),
            );
        }
    }

    /**
     * Process a scheduled subscription payment
     * 
     * @param float $amount_to_charge The amount to charge.
     * @param WC_Order $renewal_order The renewal order.
     */
    public function scheduled_subscription_payment($amount_to_charge, $renewal_order) {
        // Get subscription from renewal
        $subscription_id = $renewal_order->get_meta('_subscription_renewal');
        if (!$subscription_id) {
            $renewal_order->update_status('failed', __('Subscription renewal failed: subscription ID not found.', 'example-subscription-gateway'));
            return;
        }
        
        $subscription = wcs_get_subscription($subscription_id);
        
        // Get the payment token and customer ID
        $token = $subscription->get_meta('_example_payment_token');
        $customer_id = $subscription->get_meta('_example_customer_id');
        
        if (empty($token) || empty($customer_id)) {
            $renewal_order->update_status('failed', __('Subscription renewal failed: payment information missing.', 'example-subscription-gateway'));
            return;
        }
        
        try {
            // Process the renewal payment
            $response = $this->api->process_subscription_payment(array(
                'amount'      => $amount_to_charge,
                'currency'    => $renewal_order->get_currency(),
                'token'       => $token,
                'customer_id' => $customer_id,
                'order_id'    => $renewal_order->get_id()
            ));
            
            if (!$response->is_success) {
                throw new Exception($response->error_message);
            }
            
            // Mark payment complete
            $renewal_order->payment_complete($response->transaction_id);
            
            // Add note to the renewal order
            $renewal_order->add_order_note(
                sprintf(__('Example Gateway subscription renewal payment successful (Transaction ID: %s)', 'example-subscription-gateway'), 
                $response->transaction_id)
            );
            
        } catch (Exception $e) {
            // Payment failed
            $renewal_order->update_status('failed', sprintf(__('Subscription renewal payment failed: %s', 'example-subscription-gateway'), $e->getMessage()));
        }
    }

    /**
     * When a subscription is cancelled, tell the payment gateway
     */
    public function cancel_subscription($subscription) {
        $token = $subscription->get_meta('_example_payment_token');
        $customer_id = $subscription->get_meta('_example_customer_id');
        
        if (!empty($token) && !empty($customer_id)) {
            $this->api->cancel_subscription(array(
                'token'       => $token,
                'customer_id' => $customer_id,
            ));
            
            $subscription->add_order_note(__('Example Gateway subscription cancelled.', 'example-subscription-gateway'));
        }
    }

    /**
     * When a subscription is suspended, tell the payment gateway
     */
    public function suspend_subscription($subscription) {
        $token = $subscription->get_meta('_example_payment_token');
        $customer_id = $subscription->get_meta('_example_customer_id');
        
        if (!empty($token) && !empty($customer_id)) {
            $this->api->suspend_subscription(array(
                'token'       => $token,
                'customer_id' => $customer_id,
            ));
            
            $subscription->add_order_note(__('Example Gateway subscription suspended.', 'example-subscription-gateway'));
        }
    }

    /**
     * When a subscription is reactivated, tell the payment gateway
     */
    public function reactivate_subscription($subscription) {
        $token = $subscription->get_meta('_example_payment_token');
        $customer_id = $subscription->get_meta('_example_customer_id');
        
        if (!empty($token) && !empty($customer_id)) {
            $this->api->reactivate_subscription(array(
                'token'       => $token,
                'customer_id' => $customer_id,
            ));
            
            $subscription->add_order_note(__('Example Gateway subscription reactivated.', 'example-subscription-gateway'));
        }
    }
}
```

## Webhook Integration

For payment gateways that offer webhooks/IPN notifications, it's important to handle subscription-related events:

```php
/**
 * Process incoming webhooks from the payment gateway
 */
function process_webhook() {
    // Verify webhook is authentic
    $payload = file_get_contents('php://input');
    $signature = $_SERVER['HTTP_X_EXAMPLE_SIGNATURE'];
    
    if (!$this->api->verify_webhook($payload, $signature)) {
        wp_die('Invalid webhook signature', 'Invalid Request', 403);
    }
    
    $event = json_decode($payload);
    
    switch ($event->type) {
        case 'payment_succeeded':
            $this->handle_successful_payment($event);
            break;
            
        case 'payment_failed':
            $this->handle_failed_payment($event);
            break;
            
        case 'subscription_cancelled':
            $this->handle_cancelled_subscription($event);
            break;
            
        // Other event types...
    }
    
    exit;
}

/**
 * Handle a successful payment from the gateway
 */
private function handle_successful_payment($event) {
    $subscription = $this->get_subscription_from_reference($event->data->subscription_id);
    
    if ($subscription) {
        // Find or create the associated renewal order
        $renewal_order = $this->get_or_create_renewal_order($subscription, $event);
        
        // Mark the renewal as paid
        $renewal_order->payment_complete($event->data->payment_id);
        $renewal_order->add_order_note(sprintf(__('Payment received via webhook (Transaction ID: %s)', 'example-subscription-gateway'), $event->data->payment_id));
        
        // Update subscription dates
        $subscription->update_dates(array(
            'next_payment' => date('Y-m-d H:i:s', $event->data->next_payment_timestamp),
            'last_payment' => date('Y-m-d H:i:s', $event->data->payment_timestamp),
        ));
        $subscription->save();
    }
}
```

## Testing Payment Gateway Integration

To ensure your gateway integration is working properly:

1. **Check Initial Checkout:**
   - Create a subscription product
   - Add it to cart and checkout with your gateway
   - Verify the subscription is created correctly

2. **Test Recurring Payments:**
   - Use tools like WooCommerce > Status > Tools > "Process scheduled subscription payments"
   - Manually trigger renewal orders and verify they're processed correctly

3. **Test Status Changes:**
   - Change subscription status (cancel, suspend, reactivate) from admin
   - Verify your gateway API received the appropriate calls
   
4. **Test Payment Method Changes:**
   - Change payment method on a subscription
   - Verify the new method is stored and used for future payments

## Common Issues and Troubleshooting

### Failed Recurring Payments
When a recurring payment fails:
1. Set the renewal order status to 'failed'
2. Add a note explaining why the payment failed
3. Consider implementing a retry mechanism via `$subscription->update_meta_data('_schedule_payment_retry', $next_retry_date);`

### Payment Method Changes
Common issues when implementing payment method changes:
- Not properly storing the new payment token
- Not updating the payment method on the gateway's side
- Not updating all related subscriptions when requested

### Multiple Subscriptions
When supporting multiple subscriptions:
- Ensure tokens are stored for each subscription
- Handle situations where some subscription payments may fail while others succeed

## Best Practices

1. **Store Tokens Securely:**
   - Always use the subscription's meta data to store payment tokens
   - Never store full credit card details in your database

2. **Implement Proper Error Handling:**
   - Provide clear error messages to customers
   - Log detailed errors for debugging
   - Handle API timeout/connection issues gracefully

3. **Respect Subscription Status:**
   - Don't process payments for cancelled/expired subscriptions
   - Honor suspension requests by pausing billing

4. **Implement Comprehensive Logging:**
   - Log all API interactions for debugging
   - Record transaction IDs with orders and subscriptions

5. **Support Amount Changes:**
   - If your gateway supports amount changes, properly update the amount on the gateway side

## WooCommerce Subscriptions API Reference

The most important methods for payment gateway integration:

| Method | Description |
|--------|-------------|
| `WC_Subscription::get_payment_method()` | Gets the ID of the payment method |
| `WC_Subscription::get_total()` | Gets the subscription's recurring total |
| `WC_Subscription::get_date('next_payment')` | Gets the date of the next payment |
| `WC_Subscription::payment_failed()` | Called when a payment fails |
| `wcs_get_subscriptions_for_order($order)` | Gets subscriptions associated with an order |
| `wcs_order_contains_subscription($order)` | Checks if an order contains a subscription |
| `wcs_is_subscription($order)` | Checks if an order is a subscription |

## Conclusion

Building a robust payment gateway integration for WooCommerce Subscriptions requires implementing several important integration points. By properly supporting the subscription lifecycle and correctly handling recurring payments, your gateway will provide a reliable experience for subscription merchants.

Remember that the most critical aspect is ensuring recurring payments continue to process reliably over the life of a subscription. 