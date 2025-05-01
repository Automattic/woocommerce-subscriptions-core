<?php
/**
 * Tests for the WC_Subscriptions_Addresses class.
 */
class WC_Subscriptions_Addresses_Test extends WP_UnitTestCase {

	/**
	 * Customer user ID
	 * 
	 * @var int
	 */
	private $customer_id;

	/**
	 * Set up the test class.
	 */
	public function set_up() {
		parent::set_up();
		
		// Reset global variables
		global $wp;
		$wp->query_vars = array();
		
		// Set up the test environment - create a test customer
		$this->customer_id = wp_create_user('testuser', 'password', 'testuser@example.com');
		wp_set_current_user( $this->customer_id );
	}

	/**
	 * Tear down the test class.
	 */
	public function tear_down() {
		parent::tear_down();
		
		global $wp;
		$wp->query_vars = array();
		
		// Clean up the test customer
		if ( $this->customer_id ) {
			wp_delete_user( $this->customer_id );
		}
	}

	/**
	 * Test maybe_add_edit_address_checkbox when editing a specific subscription's shipping address.
	 */
	public function test_maybe_add_edit_address_checkbox_for_specific_subscription() {
		// Create a subscription
		$subscription = WCS_Helper_Subscription::create_subscription( array(
			'customer_id' => $this->customer_id,
		) );
		
		// Mock the $_GET request parameter
		$_GET['subscription'] = $subscription->get_id();

        // Capture the output
		ob_start();
		WC_Subscriptions_Addresses::maybe_add_edit_address_checkbox();
		$output = ob_get_clean();
		
		// Assert that the output contains the hidden input field
		$this->assertStringContainsString('<input type="hidden" name="update_subscription_address"', $output);
		$this->assertStringContainsString('value="' . $subscription->get_id() . '"', $output);
		$this->assertStringContainsString('Both the shipping address used for the subscription and your default shipping address', $output);
		
		// Clean up
		unset($_GET['subscription']);
	}
	
	/**
	 * Test maybe_add_edit_address_checkbox when editing a default address with edit-address query var.
	 */
	public function test_maybe_add_edit_address_checkbox_for_all_subscriptions_with_query_var() {
		// Create a subscription to ensure the user has a subscription
		WCS_Helper_Subscription::create_subscription( array(
			'customer_id' => $this->customer_id,
		) );
		
		// Set up the query vars
		global $wp;
		$wp->query_vars['edit-address'] = 'shipping';
		
		// Capture the output
		ob_start();
		WC_Subscriptions_Addresses::maybe_add_edit_address_checkbox();
		$output = ob_get_clean();
		
		// Assert that the output contains the checkbox
		$this->assertStringContainsString('<input type="checkbox" name="update_all_subscriptions_addresses"', $output);
		$this->assertStringContainsString('Update the Shipping Address used for <strong>all</strong> future renewals of my active subscriptions', $output);
		
		// Clean up
		unset($wp->query_vars['edit-address']);
	}
	
	/**
	 * Test maybe_add_edit_address_checkbox when editing a default address with address GET parameter.
	 */
	public function test_maybe_add_edit_address_checkbox_for_all_subscriptions_with_get_param() {
		// Create a subscription to ensure the user has a subscription
		WCS_Helper_Subscription::create_subscription( array(
			'customer_id' => $this->customer_id,
		) );
		
		// Mock $_GET parameter
		$_GET['address'] = 'billing';
        	
		// Capture the output
		ob_start();
		WC_Subscriptions_Addresses::maybe_add_edit_address_checkbox();
		$output = ob_get_clean();
		
		// Assert that the output contains the checkbox
        $this->assertStringContainsString('<input type="checkbox" name="update_all_subscriptions_addresses"', $output);
		$this->assertStringContainsString('Update the Billing Address used for <strong>all</strong> future renewals of my active subscriptions', $output);
		
		// Clean up
		unset($_GET['address']);
	}
	
	/**
	 * Test maybe_add_edit_address_checkbox when user has no subscriptions.
	 */
	public function test_maybe_add_edit_address_checkbox_no_subscriptions() {
		// Create a new user with no subscriptions
		$user_id = wp_create_user('testuser_no_subs', 'password', 'testuser_no_subs@example.com');
		wp_set_current_user($user_id);
		
		// Set up query vars
		global $wp;
		$wp->query_vars['edit-address'] = 'shipping';
		
		// Capture the output
		ob_start();
		WC_Subscriptions_Addresses::maybe_add_edit_address_checkbox();
		$output = ob_get_clean();
		
		// Assert that there is no output
		$this->assertEmpty($output);
		
		// Clean up
		wp_delete_user($user_id);
	}
	
	/**
	 * Test maybe_add_edit_address_checkbox when editing a subscription that doesn't belong to the user.
	 */
	public function test_maybe_add_edit_address_checkbox_unauthorized_subscription() {
		// Create a subscription for a different user
		$other_user_id = wp_create_user('testuser_other', 'password', 'testuser_other@example.com');
		$subscription = WCS_Helper_Subscription::create_subscription( array(
			'customer_id' => $other_user_id,
		) );
		
		// Current user is trying to edit a different user's subscription
		$_GET['subscription'] = $subscription->get_id();
		
		// Capture the output
		ob_start();
		WC_Subscriptions_Addresses::maybe_add_edit_address_checkbox();
		$output = ob_get_clean();
		
		// Assert that the output does not contain the expected fields
        $this->assertEmpty($output);
		$this->assertStringNotContainsString('update_subscription_address', $output);
		
		// Clean up
		unset($_GET['subscription']);
		wp_delete_user($other_user_id);
	}
} 