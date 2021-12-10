<?php

class Test_Autoloader extends WP_UnitTestCase {

	/** @var WCS_Autoloader */
	protected $autoloader;

	/**
	 * Set up the test case suite for the autoloader.
	 *
	 */
	public function setUp() {
		$this->autoloader = new WCS_Core_Autoloader( dirname( __DIR__, 2 ) );
	}

	/**
	 * Test the should_autoload() method.
	 *
	 * @param string $class_name
	 * @param string $expected
	 *
	 * @dataProvider should_autoload_provider
	 */
	public function test_should_autoload( $class_name, $expected ) {
		$should_autoload = $this->get_accessible_protected_method( $this->autoloader, 'should_autoload' );
		$result          = $should_autoload->invoke( $this->autoloader, strtolower( $class_name ) );
		$this->assertEquals( $expected, $result, "{$class_name} should_autoload() test" );
	}

	/**
	 * Data provider for the test_should_autoload() method.
	 *
	 * @return array
	 */
	public function should_autoload_provider() {
		return array(
			array( 'WC_Order', false ),
			array( 'WCS_Retry_Post_Store', true ),
			array( 'Some\\WCS\\Namespaced\\Class', false ),
		);
	}

	/**
	 * Test that our path mapping provides the correct location for our classes.
	 *
	 *
	 * @param string $class_name
	 * @param string $expected_path
	 *
	 * @dataProvider relative_class_path_provider
	 */
	public function test_relative_class_path( $class_name, $expected_path ) {
		$get_relative_class_path = $this->get_accessible_protected_method( $this->autoloader, 'get_relative_class_path' );
		$path                    = $get_relative_class_path->invoke( $this->autoloader, strtolower( $class_name ) );
		$this->assertEquals( $expected_path, $path, "{$class_name} get_relative_class_path() test" );
	}

	/**
	 * Data provider for the test_relative_class_path() method.
	 *
	 * As of the time this was written, this includes all of the classes in our codebase.
	 *
	 * @return array
	 */
	public function relative_class_path_provider() {
		return array(
			array( 'WCS_Background_Updater', '/includes/abstracts/' ),
			array( 'WCS_Background_Upgrader', '/includes/abstracts/' ),
			array( 'WCS_Cache_Manager', '/includes/abstracts/' ),
			array( 'WCS_Customer_Store', '/includes/abstracts/' ),
			array( 'WCS_Debug_Tool_Cache_Updater', '/includes/abstracts/' ),
			array( 'WCS_Debug_Tool', '/includes/abstracts/' ),
			array( 'WCS_Dynamic_Hook_Deprecator', '/includes/abstracts/' ),
			array( 'WCS_Hook_Deprecator', '/includes/abstracts/' ),
			array( 'WCS_Related_Order_Store', '/includes/abstracts/' ),
			array( 'WCS_Scheduler', '/includes/abstracts/' ),
			array( 'WC_Subscriptions_Admin', '/includes/admin/' ),
			array( 'WCS_Admin_Meta_Boxes', '/includes/admin/' ),
			array( 'WCS_Admin_Notice', '/includes/admin/' ),
			array( 'WCS_Admin_Post_Types', '/includes/admin/' ),
			array( 'WCS_Admin_Reports', '/includes/admin/' ),
			array( 'WCS_Admin_System_Status', '/includes/admin/' ),
			array( 'WCS_Debug_Tool_Cache_Background_Updater', '/includes/admin/debug-tools/' ),
			array( 'WCS_Debug_Tool_Cache_Eraser', '/includes/admin/debug-tools/' ),
			array( 'WCS_Debug_Tool_Cache_Generator', '/includes/admin/debug-tools/' ),
			array( 'WCS_Debug_Tool_Factory', '/includes/admin/debug-tools/' ),
			array( 'WCS_Meta_Box_Payment_Retries', '/includes/admin/meta-boxes/' ),
			array( 'WCS_Meta_Box_Related_Orders', '/includes/admin/meta-boxes/' ),
			array( 'WCS_Meta_Box_Schedule', '/includes/admin/meta-boxes/' ),
			array( 'WCS_Meta_Box_Subscription_Data', '/includes/admin/meta-boxes/' ),
			array( 'WC_Order_Item_Pending_Switch', '/includes/' ),
			array( 'WC_Product_Subscription_Variation', '/includes/' ),
			array( 'WC_Product_Subscription', '/includes/' ),
			array( 'WC_Product_Variable_Subscription', '/includes/' ),
			array( 'WC_Subscription', '/includes/' ),
			array( 'WC_Subscriptions_Addresses', '/includes/' ),
			array( 'WC_Subscriptions_Cart', '/includes/' ),
			array( 'WC_Subscriptions_Change_Payment_Gateway', '/includes/' ),
			array( 'WC_Subscriptions_Checkout', '/includes/' ),
			array( 'WC_Subscriptions_Coupon', '/includes/' ),
			array( 'WC_Subscriptions_Email', '/includes/' ),
			array( 'WC_Subscriptions_Manager', '/includes/' ),
			array( 'WC_Subscriptions_Order', '/includes/' ),
			array( 'WC_Subscriptions_Product', '/includes/' ),
			array( 'WC_Subscriptions_Renewal_Order', '/includes/' ),
			array( 'WC_Subscriptions_Switcher', '/includes/' ),
			array( 'WC_Subscriptions_Synchroniser', '/includes/' ),
			array( 'WCS_Action_Scheduler', '/includes/' ),
			array( 'WCS_API', '/includes/' ),
			array( 'WCS_Auth', '/includes/' ),
			array( 'WCS_Cached_Data_Manager', '/includes/' ),
			array( 'WCS_Cart_Initial_Payment', '/includes/' ),
			array( 'WCS_Cart_Renewal', '/includes/' ),
			array( 'WCS_Cart_Resubscribe', '/includes/' ),
			array( 'WCS_Cart_Switch', '/includes/' ),
			array( 'WCS_Change_Payment_Method_Admin', '/includes/' ),
			array( 'WCS_Download_Handler', '/includes/' ),
			array( 'WCS_Failed_Scheduled_Action_Manager', '/includes/' ),
			array( 'WCS_Limiter', '/includes/' ),
			array( 'WCS_My_Account_Payment_Methods', '/includes/' ),
			array( 'WCS_Post_Meta_Cache_Manager_Many_To_One', '/includes/' ),
			array( 'WCS_Post_Meta_Cache_Manager', '/includes/' ),
			array( 'WCS_Query', '/includes/' ),
			array( 'WCS_Remove_Item', '/includes/' ),
			array( 'WCS_Retry_Manager', '/includes/' ),
			array( 'WCS_Select2', '/includes/' ),
			array( 'WCS_Staging', '/includes/' ),
			array( 'WCS_Template_Loader', '/includes/' ),
			array( 'WCS_User_Change_Status_Handler', '/includes/' ),
			array( 'WCS_Webhooks', '/includes/' ),
			array( 'WCS_Customer_Store_Cached_CPT', '/includes/data-stores/' ),
			array( 'WCS_Customer_Store_CPT', '/includes/data-stores/' ),
			array( 'WCS_Product_Variable_Data_Store_CPT', '/includes/data-stores/' ),
			array( 'WCS_Related_Order_Store_Cached_CPT', '/includes/data-stores/' ),
			array( 'WCS_Related_Order_Store_CPT', '/includes/data-stores/' ),
			array( 'WCS_Subscription_Data_Store_CPT', '/includes/data-stores/' ),
			array( 'WCS_Action_Deprecator', '/includes/deprecated/' ),
			array( 'WCS_Deprecated_Filter_Hooks', '/includes/deprecated/' ),
			array( 'WCS_Dynamic_Action_Deprecator', '/includes/deprecated/' ),
			array( 'WCS_Dynamic_Filter_Deprecator', '/includes/deprecated/' ),
			array( 'WCS_Filter_Deprecator', '/includes/deprecated/' ),
			array( 'WCS_Email_Cancelled_Subscription', '/includes/emails/' ),
			array( 'WCS_Email_Completed_Renewal_Order', '/includes/emails/' ),
			array( 'WCS_Email_Completed_Switch_Order', '/includes/emails/' ),
			array( 'WCS_Email_Customer_Payment_Retry', '/includes/emails/' ),
			array( 'WCS_Email_Customer_Renewal_Invoice', '/includes/emails/' ),
			array( 'WCS_Email_Expired_Subscription', '/includes/emails/' ),
			array( 'WCS_Email_New_Renewal_Order', '/includes/emails/' ),
			array( 'WCS_Email_New_Switch_Order', '/includes/emails/' ),
			array( 'WCS_Email_On_Hold_Subscription', '/includes/emails/' ),
			array( 'WCS_Email_Payment_Retry', '/includes/emails/' ),
			array( 'WCS_Email_Processing_Renewal_Order', '/includes/emails/' ),
			array( 'WC_Subscriptions_Payment_Gateways', '/includes/gateways/' ),
			array( 'WCS_PayPal', '/includes/gateways/paypal/' ),
			array( 'WCS_SV_API_Base', '/includes/gateways/paypal/includes/abstracts/' ),
			array( 'WCS_PayPal_Admin', '/includes/gateways/paypal/includes/admin/' ),
			array( 'WCS_PayPal_Change_Payment_Method_Admin', '/includes/gateways/paypal/includes/admin/' ),
			array( 'WCS_PayPal_Reference_Transaction_API_Request', '/includes/gateways/paypal/includes/' ),
			array( 'WCS_PayPal_Reference_Transaction_API_Response_Billing_Agreement', '/includes/gateways/paypal/includes/' ),
			array( 'WCS_PayPal_Reference_Transaction_API_Response_Checkout', '/includes/gateways/paypal/includes/' ),
			array( 'WCS_PayPal_Reference_Transaction_API_Response_Payment', '/includes/gateways/paypal/includes/' ),
			array( 'WCS_PayPal_Reference_Transaction_API_Response_Recurring_Payment', '/includes/gateways/paypal/includes/' ),
			array( 'WCS_PayPal_Reference_Transaction_API_Response', '/includes/gateways/paypal/includes/' ),
			array( 'WCS_PayPal_Reference_Transaction_API', '/includes/gateways/paypal/includes/' ),
			array( 'WCS_PayPal_Reference_Transaction_IPN_Handler', '/includes/gateways/paypal/includes/' ),
			array( 'WCS_PayPal_Standard_Change_Payment_Method', '/includes/gateways/paypal/includes/' ),
			array( 'WCS_PayPal_Standard_IPN_Failure_Handler', '/includes/gateways/paypal/includes/' ),
			array( 'WCS_PayPal_Standard_IPN_Handler', '/includes/gateways/paypal/includes/' ),
			array( 'WCS_PayPal_Standard_Request', '/includes/gateways/paypal/includes/' ),
			array( 'WCS_PayPal_Standard_Switcher', '/includes/gateways/paypal/includes/' ),
			array( 'WCS_PayPal_Status_Manager', '/includes/gateways/paypal/includes/' ),
			array( 'WCS_PayPal_Supports', '/includes/gateways/paypal/includes/' ),
			array( 'WC_PayPal_Standard_Subscriptions', '/includes/gateways/paypal/includes/deprecated/' ),
			array( 'WCS_Cache_Updater', '/includes/interfaces/' ),
			array( 'WC_Product_Subscription_Legacy', '/includes/legacy/' ),
			array( 'WC_Product_Subscription_Variation_Legacy', '/includes/legacy/' ),
			array( 'WC_Product_Variable_Subscription_Legacy', '/includes/legacy/' ),
			array( 'WC_Subscription_Legacy', '/includes/legacy/' ),
			array( 'WCS_Array_Property_Post_Meta_Black_Magic', '/includes/legacy/' ),
			array( 'WCS_Product_Legacy', '/includes/legacy/' ),
			array( 'WCS_Privacy_Background_Updater', '/includes/privacy/' ),
			array( 'WCS_Privacy_Erasers', '/includes/privacy/' ),
			array( 'WCS_Privacy_Exporters', '/includes/privacy/' ),
			array( 'WCS_Privacy', '/includes/privacy/' ),
			array( 'WC_Subscriptions_Upgrader', '/includes/upgrades/' ),
			array( 'WCS_Repair_2_0_2', '/includes/upgrades/' ),
			array( 'WCS_Repair_2_0', '/includes/upgrades/' ),
			array( 'WCS_Repair_Subscription_Address_Indexes', '/includes/upgrades/' ),
			array( 'WCS_Repair_Suspended_PayPal_Subscriptions', '/includes/upgrades/' ),
			array( 'WCS_Upgrade_1_2', '/includes/upgrades/' ),
			array( 'WCS_Upgrade_1_3', '/includes/upgrades/' ),
			array( 'WCS_Upgrade_1_4', '/includes/upgrades/' ),
			array( 'WCS_Upgrade_1_5', '/includes/upgrades/' ),
			array( 'WCS_Upgrade_2_0', '/includes/upgrades/' ),
			array( 'WCS_Upgrade_2_1', '/includes/upgrades/' ),
			array( 'WCS_Upgrade_2_2_7', '/includes/upgrades/' ),
			array( 'WCS_Upgrade_2_2_9', '/includes/upgrades/' ),
			array( 'WCS_Upgrade_Logger', '/includes/upgrades/' ),
			array( 'WCS_Upgrade_Notice_Manager', '/includes/upgrades/' ),
		);
	}

	/**
	 * Test whether the file name determined by the autoloader matches the actual file name.
	 *
	 * @param string $class_name
	 * @param string $expected
	 *
	 * @dataProvider get_file_name_provider
	 */
	public function test_get_file_name( $class_name, $expected ) {
		$get_file_name = $this->get_accessible_protected_method( $this->autoloader, 'get_file_name' );
		$name          = $get_file_name->invoke( $this->autoloader, strtolower( $class_name ) );
		$this->assertEquals( $expected, $name, "{$class_name} get_file_name() test" );
	}

	/**
	 * Data provider for the test_get_file_name() method.
	 *
	 * @return array
	 */
	public function get_file_name_provider() {
		return array(
			array( 'WCS_Background_Updater', 'abstract-wcs-background-updater.php' ),
			array( 'WCS_Background_Upgrader', 'abstract-wcs-background-upgrader.php' ),
			array( 'WCS_Cache_Manager', 'abstract-wcs-cache-manager.php' ),
			array( 'WCS_Customer_Store', 'abstract-wcs-customer-store.php' ),
			array( 'WCS_Debug_Tool_Cache_Updater', 'abstract-wcs-debug-tool-cache-updater.php' ),
			array( 'WCS_Debug_Tool', 'abstract-wcs-debug-tool.php' ),
			array( 'WCS_Dynamic_Hook_Deprecator', 'abstract-wcs-dynamic-hook-deprecator.php' ),
			array( 'WCS_Hook_Deprecator', 'abstract-wcs-hook-deprecator.php' ),
			array( 'WCS_Related_Order_Store', 'abstract-wcs-related-order-store.php' ),
			array( 'WCS_Retry_Store', 'class-wcs-retry-store.php' ),
			array( 'WCS_Scheduler', 'abstract-wcs-scheduler.php' ),
			array( 'WC_Subscriptions_Admin', 'class-wc-subscriptions-admin.php' ),
			array( 'WCS_Admin_Meta_Boxes', 'class-wcs-admin-meta-boxes.php' ),
			array( 'WCS_Admin_Notice', 'class-wcs-admin-notice.php' ),
			array( 'WCS_Admin_Post_Types', 'class-wcs-admin-post-types.php' ),
			array( 'WCS_Admin_Reports', 'class-wcs-admin-reports.php' ),
			array( 'WCS_Admin_System_Status', 'class-wcs-admin-system-status.php' ),
			array( 'WCS_Debug_Tool_Cache_Background_Updater', 'class-wcs-debug-tool-cache-background-updater.php' ),
			array( 'WCS_Debug_Tool_Cache_Eraser', 'class-wcs-debug-tool-cache-eraser.php' ),
			array( 'WCS_Debug_Tool_Cache_Generator', 'class-wcs-debug-tool-cache-generator.php' ),
			array( 'WCS_Debug_Tool_Factory', 'class-wcs-debug-tool-factory.php' ),
			array( 'WCS_Meta_Box_Payment_Retries', 'class-wcs-meta-box-payment-retries.php' ),
			array( 'WCS_Meta_Box_Related_Orders', 'class-wcs-meta-box-related-orders.php' ),
			array( 'WCS_Meta_Box_Schedule', 'class-wcs-meta-box-schedule.php' ),
			array( 'WCS_Meta_Box_Subscription_Data', 'class-wcs-meta-box-subscription-data.php' ),
			array( 'WCS_Report_Cache_Manager', 'class-wcs-report-cache-manager.php' ),
			array( 'WCS_Report_Dashboard', 'class-wcs-report-dashboard.php' ),
			array( 'WCS_Report_Subscription_By_Customer', 'class-wcs-report-subscription-by-customer.php' ),
			array( 'WCS_Report_Subscription_By_Product', 'class-wcs-report-subscription-by-product.php' ),
			array( 'WCS_Report_Subscription_Events_By_Date', 'class-wcs-report-subscription-events-by-date.php' ),
			array( 'WCS_Report_Subscription_Payment_Retry', 'class-wcs-report-subscription-payment-retry.php' ),
			array( 'WC_Report_Subscription_By_Customer', 'class-wc-report-subscription-by-customer.php' ),
			array( 'WC_Report_Subscription_By_Product', 'class-wc-report-subscription-by-product.php' ),
			array( 'WC_Report_Subscription_Events_By_Date', 'class-wc-report-subscription-events-by-date.php' ),
			array( 'WC_Report_Subscription_Payment_Retry', 'class-wc-report-subscription-payment-retry.php' ),
			array( 'WC_REST_Subscription_Notes_Controller', 'class-wc-rest-subscription-notes-controller.php' ),
			array( 'WC_REST_Subscriptions_Controller', 'class-wc-rest-subscriptions-controller.php' ),
			array( 'WC_Product_Subscription_Variation', 'class-wc-product-subscription-variation.php' ),
			array( 'WC_Product_Subscription', 'class-wc-product-subscription.php' ),
			array( 'WC_Product_Variable_Subscription', 'class-wc-product-variable-subscription.php' ),
			array( 'WC_Subscription', 'class-wc-subscription.php' ),
			array( 'WC_Subscriptions_Addresses', 'class-wc-subscriptions-addresses.php' ),
			array( 'WC_Subscriptions_Cart', 'class-wc-subscriptions-cart.php' ),
			array( 'WC_Subscriptions_Change_Payment_Gateway', 'class-wc-subscriptions-change-payment-gateway.php' ),
			array( 'WC_Subscriptions_Checkout', 'class-wc-subscriptions-checkout.php' ),
			array( 'WC_Subscriptions_Coupon', 'class-wc-subscriptions-coupon.php' ),
			array( 'WC_Subscriptions_Email', 'class-wc-subscriptions-email.php' ),
			array( 'WC_Subscriptions_Manager', 'class-wc-subscriptions-manager.php' ),
			array( 'WC_Subscriptions_Order', 'class-wc-subscriptions-order.php' ),
			array( 'WC_Subscriptions_Product', 'class-wc-subscriptions-product.php' ),
			array( 'WC_Subscriptions_Renewal_Order', 'class-wc-subscriptions-renewal-order.php' ),
			array( 'WC_Subscriptions_Switcher', 'class-wc-subscriptions-switcher.php' ),
			array( 'WC_Subscriptions_Synchroniser', 'class-wc-subscriptions-synchroniser.php' ),
			array( 'WCS_Action_Scheduler', 'class-wcs-action-scheduler.php' ),
			array( 'WCS_API', 'class-wcs-api.php' ),
			array( 'WCS_Auth', 'class-wcs-auth.php' ),
			array( 'WCS_Cached_Data_Manager', 'class-wcs-cached-data-manager.php' ),
			array( 'WCS_Cart_Initial_Payment', 'class-wcs-cart-initial-payment.php' ),
			array( 'WCS_Cart_Renewal', 'class-wcs-cart-renewal.php' ),
			array( 'WCS_Cart_Resubscribe', 'class-wcs-cart-resubscribe.php' ),
			array( 'WCS_Cart_Switch', 'class-wcs-cart-switch.php' ),
			array( 'WCS_Change_Payment_Method_Admin', 'class-wcs-change-payment-method-admin.php' ),
			array( 'WCS_Download_Handler', 'class-wcs-download-handler.php' ),
			array( 'WCS_Failed_Scheduled_Action_Manager', 'class-wcs-failed-scheduled-action-manager.php' ),
			array( 'WCS_Limiter', 'class-wcs-limiter.php' ),
			array( 'WCS_My_Account_Payment_Methods', 'class-wcs-my-account-payment-methods.php' ),
			array( 'WCS_Post_Meta_Cache_Manager_Many_To_One', 'class-wcs-post-meta-cache-manager-many-to-one.php' ),
			array( 'WCS_Post_Meta_Cache_Manager', 'class-wcs-post-meta-cache-manager.php' ),
			array( 'WCS_Query', 'class-wcs-query.php' ),
			array( 'WCS_Remove_Item', 'class-wcs-remove-item.php' ),
			array( 'WCS_Retry_Manager', 'class-wcs-retry-manager.php' ),
			array( 'WCS_Select2', 'class-wcs-select2.php' ),
			array( 'WCS_Staging', 'class-wcs-staging.php' ),
			array( 'WCS_Template_Loader', 'class-wcs-template-loader.php' ),
			array( 'WCS_User_Change_Status_Handler', 'class-wcs-user-change-status-handler.php' ),
			array( 'WCS_Webhooks', 'class-wcs-webhooks.php' ),
			array( 'WCS_Customer_Store_Cached_CPT', 'class-wcs-customer-store-cached-cpt.php' ),
			array( 'WCS_Customer_Store_CPT', 'class-wcs-customer-store-cpt.php' ),
			array( 'WCS_Product_Variable_Data_Store_CPT', 'class-wcs-product-variable-data-store-cpt.php' ),
			array( 'WCS_Related_Order_Store_Cached_CPT', 'class-wcs-related-order-store-cached-cpt.php' ),
			array( 'WCS_Related_Order_Store_CPT', 'class-wcs-related-order-store-cpt.php' ),
			array( 'WCS_Subscription_Data_Store_CPT', 'class-wcs-subscription-data-store-cpt.php' ),
			array( 'WCS_Action_Deprecator', 'class-wcs-action-deprecator.php' ),
			array( 'WCS_Deprecated_Filter_Hooks', 'class-wcs-deprecated-filter-hooks.php' ),
			array( 'WCS_Dynamic_Action_Deprecator', 'class-wcs-dynamic-action-deprecator.php' ),
			array( 'WCS_Dynamic_Filter_Deprecator', 'class-wcs-dynamic-filter-deprecator.php' ),
			array( 'WCS_Filter_Deprecator', 'class-wcs-filter-deprecator.php' ),
			array( 'WCS_Email_Cancelled_Subscription', 'class-wcs-email-cancelled-subscription.php' ),
			array( 'WCS_Email_Completed_Renewal_Order', 'class-wcs-email-completed-renewal-order.php' ),
			array( 'WCS_Email_Completed_Switch_Order', 'class-wcs-email-completed-switch-order.php' ),
			array( 'WCS_Email_Customer_Payment_Retry', 'class-wcs-email-customer-payment-retry.php' ),
			array( 'WCS_Email_Customer_Renewal_Invoice', 'class-wcs-email-customer-renewal-invoice.php' ),
			array( 'WCS_Email_Expired_Subscription', 'class-wcs-email-expired-subscription.php' ),
			array( 'WCS_Email_New_Renewal_Order', 'class-wcs-email-new-renewal-order.php' ),
			array( 'WCS_Email_New_Switch_Order', 'class-wcs-email-new-switch-order.php' ),
			array( 'WCS_Email_On_Hold_Subscription', 'class-wcs-email-on-hold-subscription.php' ),
			array( 'WCS_Email_Payment_Retry', 'class-wcs-email-payment-retry.php' ),
			array( 'WCS_Email_Processing_Renewal_Order', 'class-wcs-email-processing-renewal-order.php' ),
			array( 'WC_Subscriptions_Payment_Gateways', 'class-wc-subscriptions-payment-gateways.php' ),
			array( 'WCS_PayPal', 'class-wcs-paypal.php' ),
			array( 'WCS_SV_API_Base', 'abstract-wcs-sv-api-base.php' ),
			array( 'WCS_PayPal_Admin', 'class-wcs-paypal-admin.php' ),
			array( 'WCS_PayPal_Change_Payment_Method_Admin', 'class-wcs-paypal-change-payment-method-admin.php' ),
			array( 'WCS_PayPal_Reference_Transaction_API_Request', 'class-wcs-paypal-reference-transaction-api-request.php' ),
			array( 'WCS_PayPal_Reference_Transaction_API_Response_Billing_Agreement', 'class-wcs-paypal-reference-transaction-api-response-billing-agreement.php' ),
			array( 'WCS_PayPal_Reference_Transaction_API_Response_Checkout', 'class-wcs-paypal-reference-transaction-api-response-checkout.php' ),
			array( 'WCS_PayPal_Reference_Transaction_API_Response_Payment', 'class-wcs-paypal-reference-transaction-api-response-payment.php' ),
			array( 'WCS_PayPal_Reference_Transaction_API_Response_Recurring_Payment', 'class-wcs-paypal-reference-transaction-api-response-recurring-payment.php' ),
			array( 'WCS_PayPal_Reference_Transaction_API_Response', 'class-wcs-paypal-reference-transaction-api-response.php' ),
			array( 'WCS_PayPal_Reference_Transaction_API', 'class-wcs-paypal-reference-transaction-api.php' ),
			array( 'WCS_PayPal_Reference_Transaction_IPN_Handler', 'class-wcs-paypal-reference-transaction-ipn-handler.php' ),
			array( 'WCS_PayPal_Standard_Change_Payment_Method', 'class-wcs-paypal-standard-change-payment-method.php' ),
			array( 'WCS_PayPal_Standard_IPN_Failure_Handler', 'class-wcs-paypal-standard-ipn-failure-handler.php' ),
			array( 'WCS_PayPal_Standard_IPN_Handler', 'class-wcs-paypal-standard-ipn-handler.php' ),
			array( 'WCS_PayPal_Standard_Request', 'class-wcs-paypal-standard-request.php' ),
			array( 'WCS_PayPal_Standard_Switcher', 'class-wcs-paypal-standard-switcher.php' ),
			array( 'WCS_PayPal_Status_Manager', 'class-wcs-paypal-status-manager.php' ),
			array( 'WCS_PayPal_Supports', 'class-wcs-paypal-supports.php' ),
			array( 'WC_PayPal_Standard_Subscriptions', 'class-wc-paypal-standard-subscriptions.php' ),
			array( 'WCS_Cache_Updater', 'interface-wcs-cache-updater.php' ),
			array( 'WC_Product_Subscription_Legacy', 'class-wc-product-subscription-legacy.php' ),
			array( 'WC_Product_Subscription_Variation_Legacy', 'class-wc-product-subscription-variation-legacy.php' ),
			array( 'WC_Product_Variable_Subscription_Legacy', 'class-wc-product-variable-subscription-legacy.php' ),
			array( 'WC_Subscription_Legacy', 'class-wc-subscription-legacy.php' ),
			array( 'WCS_Array_Property_Post_Meta_Black_Magic', 'class-wcs-array-property-post-meta-black-magic.php' ),
			array( 'WCS_Product_Legacy', 'class-wcs-product-legacy.php' ),
			array( 'WCS_Retry_Admin', 'class-wcs-retry-admin.php' ),
			array( 'WCS_Retry_Email', 'class-wcs-retry-email.php' ),
			array( 'WCS_Retry_Post_Store', 'class-wcs-retry-post-store.php' ),
			array( 'WCS_Retry_Rule', 'class-wcs-retry-rule.php' ),
			array( 'WCS_Retry_Rules', 'class-wcs-retry-rules.php' ),
			array( 'WCS_Retry', 'class-wcs-retry.php' ),
			array( 'WCS_Privacy_Background_Updater', 'class-wcs-privacy-background-updater.php' ),
			array( 'WCS_Privacy_Erasers', 'class-wcs-privacy-erasers.php' ),
			array( 'WCS_Privacy_Exporters', 'class-wcs-privacy-exporters.php' ),
			array( 'WCS_Privacy', 'class-wcs-privacy.php' ),
			array( 'WC_Subscriptions_Upgrader', 'class-wc-subscriptions-upgrader.php' ),
			array( 'WCS_Repair_2_0_2', 'class-wcs-repair-2-0-2.php' ),
			array( 'WCS_Repair_2_0', 'class-wcs-repair-2-0.php' ),
			array( 'WCS_Repair_Subscription_Address_Indexes', 'class-wcs-repair-subscription-address-indexes.php' ),
			array( 'WCS_Repair_Suspended_PayPal_Subscriptions', 'class-wcs-repair-suspended-paypal-subscriptions.php' ),
			array( 'WCS_Upgrade_1_2', 'class-wcs-upgrade-1-2.php' ),
			array( 'WCS_Upgrade_1_3', 'class-wcs-upgrade-1-3.php' ),
			array( 'WCS_Upgrade_1_4', 'class-wcs-upgrade-1-4.php' ),
			array( 'WCS_Upgrade_1_5', 'class-wcs-upgrade-1-5.php' ),
			array( 'WCS_Upgrade_2_0', 'class-wcs-upgrade-2-0.php' ),
			array( 'WCS_Upgrade_2_1', 'class-wcs-upgrade-2-1.php' ),
			array( 'WCS_Upgrade_2_2_7', 'class-wcs-upgrade-2-2-7.php' ),
			array( 'WCS_Upgrade_2_2_9', 'class-wcs-upgrade-2-2-9.php' ),
			array( 'WCS_Upgrade_Logger', 'class-wcs-upgrade-logger.php' ),
			array( 'WCS_Upgrade_Notice_Manager', 'class-wcs-upgrade-notice-manager.php' ),
		);
	}

	/**
	 * A utility function to make certain methods public, useful for testing protected methods
	 * that affect public APIs, but are not public to avoid use due to potential confusion, like
	 * like @see WCS_Retry_Manager::get_store_class() & WCS_Retry_Manager::get_rules_class(), both
	 * of which are important to test to ensure that @see WCS_Retry_Manager::get_store() and @see
	 * WCS_Retry_Manager::get_rules() return correct custom classes when filtered, which can not
	 * be tested due to the use of static properties to store them.
	 *
	 * @return ReflectionMethod
	 */
	protected function get_accessible_protected_method( $object, $method_name ) {

		$reflected_object = new ReflectionClass( $object );
		$reflected_method = $reflected_object->getMethod( $method_name );

		$reflected_method->setAccessible( true );

		return $reflected_method;
	}
}
