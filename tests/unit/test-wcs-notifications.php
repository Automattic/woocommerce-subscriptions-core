<?php

use PHPUnit\Framework\TestCase;

class WCS_Subscription_Notification_Test extends WP_UnitTestCase {

	protected $notifications_as_group;

	protected $notification_types;

	protected $offset;
	protected $offset_for_settings;

	public function __construct() {
		parent::__construct();

		$this->init_protected_data();

		$this->offset              = '-3 days';
		$this->offset_for_settings = [
			'number' => '3',
			'unit'   => 'days',
		];
	}

	// Mock functions and helpers.

	public function setUp(): void {
		parent::setUp();

		// Start with a clean slate. Previous Subscription IDs are reused, so it can be a headache if we kept them.
		as_unschedule_all_actions( '', [], $this->notifications_as_group );

		// Setting the option to default is not necessary as it gets rolled back before each test by WP_UnitTestCase, but
		// the state of the notfication_scheduler class won't get rolled back with the transaction.
		WC_Subscriptions_Core_Plugin::instance()->notifications_scheduler->set_time_offset_from_option(
			'',
			[
				'number' => '3',
				'unit'   => 'days',
			]
		);
	}

	/**
	 * Perform a recursive array diff.
	 *
	 * Objects are compared just by their values, not if they are the same instance.
	 *
	 * Return value has the following format:
	 * [
	 *  subscription_ID_1 =>
	 *      [
	 *          'scheduled_action_hook_1' =>
	 *              [
	 *                  'change' => 'updated',
	 *                  'from'   => ActionScheduler_Action in the state before the change
	 *                  'to'     => ActionScheduler_Action in the state after the change
	 *                  'action' => ActionScheduler_Action in the final state
	 *              ],
	 *          'scheduled_action_hook_2' =>
	 *               [
	 *                   'change' => 'deleted',
	 *                   'action' => deleted ActionScheduler_Action
	 *               ],
	 *           'scheduled_action_hook_3' =>
	 *                [
	 *                    'change' => 'added',
	 *                    'action' => added ActionScheduler_Action
	 *                ],
	 *      ],
	 *  subscription_ID_2 =>
	 *      [
	 *          ...
	 *      ],
	 * ]
	 *
	 * #BlameGPT o1-preview if this has bugs.
	 *
	 * @param $array_1
	 * @param $array_2
	 *
	 * @return array
	 */
	protected static function recursive_array_diff( $array_1, $array_2 ) {
		$diff = [];

		// Get all keys from both arrays without reindexing numerical keys
		$all_keys = array_unique( array_merge( array_keys( $array_1 ), array_keys( $array_2 ) ) );

		foreach ( $all_keys as $key ) {
			$exists_in_array1 = array_key_exists( $key, $array_1 );
			$exists_in_array2 = array_key_exists( $key, $array_2 );

			if ( $exists_in_array1 && $exists_in_array2 ) {
				$value1 = $array_1[ $key ];
				$value2 = $array_2[ $key ];

				if ( is_array( $value1 ) && is_array( $value2 ) ) {
					// Recurse into subarrays
					$sub_diff = self::recursive_array_diff( $value1, $value2 );
					if ( ! empty( $sub_diff ) ) {
						$diff[ $key ] = $sub_diff;
					}
				} elseif ( $value1 != $value2 ) { // @phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
					// Values are different. Not doing strict comparison because we want to compare objects by their values.
					$diff[ $key ] = [
						'from'   => $value1,
						'to'     => $value2,
						'change' => 'updated',
						'action' => $value2,
					];
				}
				// If values are the same, do nothing
			} else {
				if ( $exists_in_array1 ) {
					// Key exists only in the first array (deleted)
					$value1 = $array_1[ $key ];
					if ( is_array( $value1 ) ) {
						// Handle subarrays consistently
						$sub_diff = self::recursive_array_diff( $value1, [] );
						if ( ! empty( $sub_diff ) ) {
							$diff[ $key ] = $sub_diff;
						}
					} else {
						$diff[ $key ] = [
							'action' => $value1,
							'change' => 'deleted',
						];
					}
				}

				if ( $exists_in_array2 ) {
					// Key exists only in the second array (added)
					$value2 = $array_2[ $key ];
					if ( is_array( $value2 ) ) {
						// Handle subarrays consistently
						$sub_diff = self::recursive_array_diff( [], $value2 );
						if ( ! empty( $sub_diff ) ) {
							if ( isset( $diff[ $key ] ) && is_array( $diff[ $key ] ) ) {
								// Merge with existing differences at this key
								$diff[ $key ] = array_merge_recursive( $diff[ $key ], $sub_diff );
							} else {
								$diff[ $key ] = $sub_diff;
							}
						}
					} else {
						$diff[ $key ] = [
							'action' => $value2,
							'change' => 'added',
						];
					}
				}
			}
		}

		return $diff;

	}


	/**
	 * Create a subscription with a free trial.
	 *
	 * @param array $args
	 * @param string $requires_manual_renewal
	 * @param bool $is_in_trial_period
	 * @return WC_Subscription
	 */
	protected static function create_free_trial_subscription( $args = [], $requires_manual_renewal = 'false', $is_in_trial_period = true ) {

		if ( $is_in_trial_period ) {
			$start_datetime     = new DateTime();
			$start_datetime_str = $start_datetime->format( 'Y-m-d H:i:s' );

			$trial_end_datetime = clone $start_datetime;
			$trial_end_datetime->modify( '+1 month' );
			$trial_end_datetime_str = $trial_end_datetime->format( 'Y-m-d H:i:s' );

			$default_args     = [
				'status'     => 'active',
				'start_date' => $start_datetime_str,
			];
			$additional_dates = [
				'date_created' => $start_datetime_str,
				'last_payment' => $start_datetime_str,
				'trial_end'    => $trial_end_datetime_str,
				'next_payment' => $trial_end_datetime_str,
			];
		} else {
			// Subscription started 40 days ago.
			$start_datetime = new DateTime();
			$start_datetime->modify( '-40 days' );
			$start_datetime_str = $start_datetime->format( 'Y-m-d H:i:s' );

			// Free trial was for one month, so subscription is about 10 days after free trial.
			$trial_end_datetime = clone $start_datetime;
			$trial_end_datetime->modify( '+1 month' );
			$trial_end_datetime_str = $trial_end_datetime->format( 'Y-m-d H:i:s' );

			// Next payment is coming up at the end of second month.
			$next_payment_datetime = clone $start_datetime;
			$next_payment_datetime->modify( '+2 months' );
			$next_payment_datetime_str = $next_payment_datetime->format( 'Y-m-d H:i:s' );

			$default_args = [
				'status'       => 'active',
				'start_date'   => $start_datetime_str,
				'date_created' => $start_datetime_str,
			];

			$additional_dates = [
				'last_payment' => $trial_end_datetime_str, // Customer paid after the free trial ended.
				'trial_end'    => $trial_end_datetime_str,
				'next_payment' => $next_payment_datetime_str,
			];
		}

		$subscription_args = array_merge(
			$default_args,
			$args
		);

		$subscription = WCS_Helper_Subscription::create_subscription(
			$subscription_args,
			[
				'requires_manual_renewal' => $requires_manual_renewal,
			]
		);

		$subscription->update_dates(
			$additional_dates
		);

		$subscription->save();

		return $subscription;
	}

	/**
	 * Create a simple subscription.
	 *
	 * @param string $requires_manual_renewal
	 * @return WC_Subscription
	 */
	protected static function create_simple_subscription( $requires_manual_renewal = 'false' ) {
		$start_datetime     = new DateTime();
		$start_datetime_str = $start_datetime->format( 'Y-m-d H:i:s' );

		$next_payment_datetime = clone $start_datetime;
		$next_payment_datetime->modify( '+1 month' );
		$next_payment_datetime_str = $next_payment_datetime->format( 'Y-m-d H:i:s' );

		$subscription = WCS_Helper_Subscription::create_subscription(
			[
				'status'       => 'active',
				'start_date'   => $start_datetime_str,
				'date_created' => $start_datetime_str,
			],
			[
				'requires_manual_renewal' => $requires_manual_renewal,
			]
		);

		$subscription->update_dates(
			[
				'last_payment' => $start_datetime_str,
				'next_payment' => $next_payment_datetime_str,
			]
		);

		$subscription->save();

		return $subscription;
	}

	/**
	 * Create an expiring subscription.
	 *
	 * @param array $args
	 * @return WC_Subscription
	 */
	protected static function create_expiring_subscription( $args = [] ) {
		$start_datetime     = new DateTime();
		$start_datetime_str = $start_datetime->format( 'Y-m-d H:i:s' );

		$end_datetime = clone $start_datetime;
		$end_datetime->modify( '+3 months' );
		$end_datetime_str = $end_datetime->format( 'Y-m-d H:i:s' );

		$next_payment_datetime = clone $start_datetime;
		$next_payment_datetime->modify( '+1 month' );
		$next_payment_datetime_str = $next_payment_datetime->format( 'Y-m-d H:i:s' );

		$subscription = WCS_Helper_Subscription::create_subscription(
			[
				'status'       => 'active',
				'start_date'   => $start_datetime_str,
				'date_created' => $start_datetime_str,
			]
		);
		$subscription->update_dates(
			[
				'last_payment' => $start_datetime_str,
				'next_payment' => $next_payment_datetime_str,
				'end_date'     => $end_datetime_str,
			]
		);

		$subscription->save();

		return $subscription;
	}

	/**
	 * Create an expiring subscription with a trial.
	 *
	 * @param array $args
	 * @param bool $is_in_trial_period
	 * @return WC_Subscription
	 */
	protected static function create_expiring_subscription_with_trial( $args = [], $is_in_trial_period = true ) {

		if ( $is_in_trial_period ) {

			$start_datetime     = new DateTime();
			$start_datetime_str = $start_datetime->format( 'Y-m-d H:i:s' );

			$end_datetime = clone $start_datetime;
			$end_datetime->modify( '+3 months' );
			$end_datetime_str = $end_datetime->format( 'Y-m-d H:i:s' );

			$next_payment_datetime = clone $start_datetime;
			$next_payment_datetime->modify( '+1 month' );
			$next_payment_datetime_str = $next_payment_datetime->format( 'Y-m-d H:i:s' );

			$default_args = [
				'status'       => 'active',
				'start_date'   => $start_datetime_str,
				'date_created' => $start_datetime_str,
			];

			$additional_dates = [
				'last_payment' => $start_datetime_str,
				'next_payment' => $next_payment_datetime_str,
				'trial_end'    => $next_payment_datetime_str,
				'end_date'     => $end_datetime_str,
			];
		} else {
			$start_datetime = new DateTime();
			$start_datetime->modify( '-40 days' );
			$start_datetime_str = $start_datetime->format( 'Y-m-d H:i:s' );

			// Free trial was for one month, so subscription is about 10 days after free trial.
			$trial_end_datetime = clone $start_datetime;
			$trial_end_datetime->modify( '+1 month' );
			$trial_end_datetime_str = $trial_end_datetime->format( 'Y-m-d H:i:s' );

			$end_datetime = clone $start_datetime;
			$end_datetime->modify( '+3 months' );
			$end_datetime_str = $end_datetime->format( 'Y-m-d H:i:s' );

			$next_payment_datetime = clone $start_datetime;
			$next_payment_datetime->modify( '+2 months' );
			$next_payment_datetime_str = $next_payment_datetime->format( 'Y-m-d H:i:s' );

			$default_args = [
				'status'       => 'active',
				'start_date'   => $start_datetime_str,
				'date_created' => $start_datetime_str,
			];

			$additional_dates = [
				'trial_end'    => $trial_end_datetime_str,
				'last_payment' => $trial_end_datetime_str,
				'next_payment' => $next_payment_datetime_str,
				'end_date'     => $end_datetime_str,
			];
		}

		$subscription_args = array_merge( $default_args, $args );

		$subscription = WCS_Helper_Subscription::create_subscription(
			$subscription_args
		);

		$subscription->update_dates(
			$additional_dates
		);

		$subscription->save();

		return $subscription;
	}

	/**
	 * Manually renew a simple subscription.
	 */
	protected static function manually_renew_simple_subscription( $subscription ) {
		$renewal_order = WCS_Helper_Subscription::create_renewal_order( $subscription );
		$renewal_order->set_payment_method( 'dummy_gateway' );

		// This next bit is extracted from WC_Gateway_Dummy::process_payment()
		$renewal_order->payment_complete();

		// Update dates on the subscription.
		self::wcs_update_dates_after_early_renewal( $subscription, $renewal_order );

		return $subscription;
	}

	/**
	 * Cancel a subscription.
	 */
	protected static function cancel_subscription( $subscription ) {
		\WCS_User_Change_Status_Handler::change_users_subscription( $subscription, 'cancelled' );
		return $subscription;
	}

	/**
	 * Reactivate a subscription.
	 */
	protected static function reactivate_subscription( $subscription ) {
		\WCS_User_Change_Status_Handler::change_users_subscription( $subscription, 'active' );
		return $subscription;
	}

	/**
	 * Disable notifications globally.
	 */
	protected static function disable_notifications_globally() {
		update_option( WC_Subscriptions_Admin::$option_prefix . WC_Subscriptions_Email_Notifications::$switch_setting_string, 'no' );
		delete_option( WC_Subscriptions_Admin::$option_prefix . WC_Subscriptions_Email_Notifications::$offset_setting_string );
	}

	/**
	 * Enable notifications globally.
	 *
	 * This is quite tightly coupled to settings in the plugin, and needs to change if the settings change.
	 */
	protected function enable_notifications_globally() {
		update_option( WC_Subscriptions_Admin::$option_prefix . WC_Subscriptions_Email_Notifications::$switch_setting_string, 'yes' );
		update_option(
			WC_Subscriptions_Admin::$option_prefix . WC_Subscriptions_Email_Notifications::$offset_setting_string,
			$this->offset_for_settings
		);
	}

	/**
	 * Load data from protected properties of the tested classes.
	 */
	protected function init_protected_data() {
		$reflection    = new ReflectionClass( 'WCS_Action_Scheduler_Customer_Notifications' );
		$as_group_name = $reflection->getProperty( 'notifications_as_group' );
		$as_group_name->setAccessible( true );

		$instance = new WCS_Action_Scheduler_Customer_Notifications();

		// Unhook the hooks from this extra instance that would mess up the tests.
		remove_action( 'woocommerce_before_subscription_object_save', [ $instance, 'update_notifications' ], 10, 2 );
		remove_action( 'woocommerce_subscription_date_updated', array( $instance, 'update_date' ), 10, 3 );
		remove_action( 'woocommerce_subscription_date_deleted', array( $instance, 'delete_date' ), 10, 2 );
		remove_action( 'woocommerce_subscription_status_updated', array( $instance, 'update_status' ), 10, 3 );

		remove_action( 'update_option_' . WC_Subscriptions_Admin::$option_prefix . WC_Subscriptions_Email_Notifications::$offset_setting_string, [ $instance, 'set_time_offset_from_option' ], 5, 3 );
		remove_action( 'add_option_' . WC_Subscriptions_Admin::$option_prefix . WC_Subscriptions_Email_Notifications::$offset_setting_string, [ $instance, 'set_time_offset_from_option' ], 5, 2 );

		$this->notifications_as_group = $as_group_name->getValue( $instance );

		$notification_types = $reflection->getProperty( 'notification_actions' );
		$notification_types->setAccessible( true );

		$this->notification_types = $notification_types->getValue( $instance );
	}

	/**
	 * Run all the test from the configuration/manual data provider.
	 */
	public function notifications_general_tester( $data_provided = [] ) {
		foreach ( $data_provided as $test_name => $data ) {
			$callback   = $data['callback'];
			$params     = $data['params'];
			$assertions = $data['assertions_config'];

			$this->notifications_general_execute_test( $callback, $params, $assertions, $test_name );
		}
	}

	/**
	 * Generic testing function that can compare scheduled actions before and after a callback gets called and checks all the assertions.
	 *
	 * What the function does:
	 * 1. Enables notifications globally.
	 * 2. Gets all scheduled actions before the callback gets called.
	 * 3. Calls the callback.
	 * 4. Gets all scheduled actions after the callback gets called.
	 * 5. Compares the actions before and after the callback.
	 * 6. Runs the assertions.
	 *
	 *
	 * @param callable $callback Callback to run to check if the notifications changed.
	 * @param array    $params Parameters to pass to the callback.
	 * @param array    $assertions_config Array of assertions to run.
	 * @param string   $test_name Name of the test to make it easier to identify in the report.
	 * @return void
	 */
	public function notifications_general_execute_test( callable $callback, array $params, array $assertions_config, string $test_name = '' ) {
		$this->enable_notifications_globally();

		$actions_before = [];
		foreach ( $this->notification_types as $notification_type ) {
			$actions_tmp = as_get_scheduled_actions(
				[
					'hook'     => $notification_type,
					'group'    => $this->notifications_as_group,
					'per_page' => 1000,
					// not interested in cancelled actions.
					'status'   => [
						0 => ActionScheduler_Store::STATUS_COMPLETE,
						1 => ActionScheduler_Store::STATUS_PENDING,
						2 => ActionScheduler_Store::STATUS_RUNNING,
						3 => ActionScheduler_Store::STATUS_FAILED,
					],
				]
			);

			foreach ( $actions_tmp as $action ) {
				$actions_before[ $action->get_args()['subscription_id'] ][ $action->get_hook() ] = $action;
			}
		}

		$subscription = $callback( ...$params );

		$actions_after = [];
		foreach ( $this->notification_types as $notification_type ) {
			$actions_tmp = as_get_scheduled_actions(
				[
					'hook'     => $notification_type,
					'group'    => $this->notifications_as_group,
					'per_page' => 1000,
					// not interested in cancelled actions.
					'status'   => [
						0 => ActionScheduler_Store::STATUS_COMPLETE,
						1 => ActionScheduler_Store::STATUS_PENDING,
						2 => ActionScheduler_Store::STATUS_RUNNING,
						3 => ActionScheduler_Store::STATUS_FAILED,
					],
				]
			);

			foreach ( $actions_tmp as $action ) {
				$actions_after[ $action->get_args()['subscription_id'] ][ $action->get_hook() ] = $action;
			}
		}

		$actions_diff = self::recursive_array_diff( $actions_before, $actions_after );

		foreach ( $assertions_config as $assertion ) {
			// Extract assertion type, expected value, and actual value from the config
			$assertion_type = $assertion['type'] ?? 'assertSame';

			if ( isset( $assertion['expected'] ) ) {
				if ( is_callable( $assertion['expected'] ) ) {
					$expected = $assertion['expected']( $subscription, $actions_diff ); // Invoke the callable with $result as an argument
				} else {
					$expected = $assertion['expected'] ?? $subscription; // Default to using the result of the callback
				}
			}

			if ( is_callable( $assertion['actual'] ) ) {
				$actual = $assertion['actual']( $subscription, $actions_diff ); // Invoke the callable with $result as an argument
			} else {
				$actual = $assertion['actual'] ?? $subscription; // Default to using the result of the callback
			}

			$msg = isset( $assertion['message'] ) ? $test_name . ':' . $assertion['message'] : $test_name;

			// Perform the assertion dynamically
			switch ( $assertion_type ) {
				case 'assertEquals':
					$this->assertEquals( $expected, $actual, $msg );
					break;
				case 'assertSame':
					$this->assertSame( $expected, $actual, $msg );
					break;
				case 'assertTrue':
					$this->assertTrue( $actual, $msg );
					break;
				case 'assertFalse':
					$this->assertFalse( $actual, $msg );
					break;
				default:
					throw new Exception( 'Unknown assertion type ' . $assertion_type );
			}
		}
	}

	// Test cases.

	/**
	 * Test that notifications are not created when the global setting is disabled.
	 *
	 * Tests both creation and updating of subscriptions.
	 */
	public function test_notification_not_created_when_disabled() {
		// Globally disabled -> don't create notifications when subscriptions are created.
		self::disable_notifications_globally();

		$actions_before = [];
		foreach ( $this->notification_types as $notification_type ) {
			$actions_before[ $notification_type ] = as_get_scheduled_actions(
				[
					'hook'  => $notification_type,
					'group' => $this->notifications_as_group,
				]
			);
		}

		// Create all kinds of subscriptions.
		$subscriptions = [
			$this->create_free_trial_subscription(),
			$this->create_free_trial_subscription( [], 'true' ),
			$this->create_free_trial_subscription( [], 'true', false ),
			$this->create_expiring_subscription(),
			$this->create_expiring_subscription_with_trial(),
			$this->create_simple_subscription(),
			$this->create_simple_subscription( 'true' ),

		];

		$actions_after_create = [];
		foreach ( $this->notification_types as $notification_type ) {
			$actions_after_create[ $notification_type ] = as_get_scheduled_actions(
				[
					'hook'  => $notification_type,
					'group' => $this->notifications_as_group,
				]
			);
		}

		// No additional or updated notifications.
		$this->assertEquals( $actions_after_create, $actions_before );

		// Globally disabled -> don't create notifications when subscriptions are updated.
		// Test for status update.
		foreach ( $subscriptions as $subscription ) {
			$subscription->update_status( 'active' ); // this also saves
		}

		// Test for change in next payment date.
		$next_payment_datetime = new DateTime();
		$next_payment_datetime->modify( '+2 months' );
		$next_payment_datetime_str = $next_payment_datetime->format( 'Y-m-d H:i:s' );
		foreach ( $subscriptions as $subscription ) {
			$subscription->update_dates(
				[
					'next_payment' => $next_payment_datetime_str,
				]
			);
			$subscription->save();
		}

		$actions_after_update = [];
		foreach ( $this->notification_types as $notification_type ) {
			$actions_after_update[ $notification_type ] = as_get_scheduled_actions(
				[
					'hook'  => $notification_type,
					'group' => $this->notifications_as_group,
				]
			);
		}

		// No additional or updated notifications after updating subscriptions.
		$this->assertEquals( $actions_after_update, $actions_before );
	}

	/**
	 * Checks that the expected number of notifications got created, updated and deleted for given subscription.
	 *
	 * @param array(WC_Subscription) $subscriptions
	 * @param array $actions_diff Diff of actions before and after the callback.
	 * @param int $expected_additions
	 * @param int $expected_updates
	 * @param int $expected_deletes
	 *
	 * @return bool
	 */
	protected function verify_notification_count( $subscriptions, $actions_diff, $expected_additions = 0, $expected_updates = 0, $expected_deletes = 0 ) {
		$added   = 0;
		$updated = 0;
		$deleted = 0;

		foreach ( $subscriptions as $subscription ) {
			foreach ( $this->notification_types as $notification_type ) {
				if ( ! isset( $actions_diff[ $subscription->get_id() ][ $notification_type ] ) ) {
					continue;
				}
				$added   += 'added' === $actions_diff[ $subscription->get_id() ][ $notification_type ]['change'] ? 1 : 0;
				$updated += 'updated' === $actions_diff[ $subscription->get_id() ][ $notification_type ]['change'] ? 1 : 0;
				$deleted += 'deleted' === $actions_diff[ $subscription->get_id() ][ $notification_type ]['change'] ? 1 : 0;
			}
		}

		return $expected_additions === $added && $expected_updates === $updated && $expected_deletes === $deleted;
	}

	/**
	 * Subfunction that returns the configuration for the test that checks the correct notifications are created
	 * correctly when different types of subscriptions get created.
	 */
	public function subscription_create_checks() {
		return [
			'Test 1: Simple subscription with automatic renewal.' =>
				[
					'callback'          => [ self::class, 'create_simple_subscription' ],
					'params'            => [],
					'assertions_config' => [
						[
							'message' => 'Check that exactly one notification is created.',
							'type'    => 'assertTrue',
							'actual'  => function ( $subscription, $actions_diff ) {
								return $this->verify_notification_count( [ $subscription ], $actions_diff, 1, 0, 0 );
							},
						],
						[
							'message' => 'Check that the correct hook is used.',
							'type'    => 'assertTrue',
							'actual'  => function ( $subscription, $actions_diff ) {
								return 'added' === $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_renewal']['change'];
							},
						],
						[
							'message'  => 'Check that the correct args are used.',
							'expected' => function ( $subscription, $actions_diff ) {
								return WCS_Action_Scheduler_Customer_Notifications::get_action_args( $subscription );
							},
							'actual'   => function ( $subscription, $actions_diff ) {
								$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_renewal']['action'];

								return $new_action->get_args();
							},
						],
						[
							'message'  => 'Check that the notification is in the correct group.',
							'expected' => $this->notifications_as_group,
							'actual'   => function ( $subscription, $actions_diff ) {
								$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_renewal']['action'];

								return $new_action->get_group();
							},
						],
						[
							'message'  => 'Check that the date is correct.',
							'type'     => 'assertEquals',
							'expected' => function ( $subscription, $actions_diff ) {
								$next_payment = new DateTime( $subscription->get_date( 'next_payment' ) );
								$next_payment->modify( $this->offset );

								return $next_payment;
							},
							'actual'   => function ( $subscription, $actions_diff ) {
								$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_renewal']['action'];

								return $new_action->get_schedule()->get_date();
							},
						],
					],
				],
			'Test 2: Simple subscription with manual renewal.' =>
				[
					'callback'          => [ self::class, 'create_simple_subscription' ],
					'params'            => [ 'true' ],
					'assertions_config' => [
						[
							'message' => 'Check that exactly one notification is created.',
							'type'    => 'assertTrue',
							'actual'  => function ( $subscription, $actions_diff ) {
								return $this->verify_notification_count( [ $subscription ], $actions_diff, 1, 0, 0 );
							},
						],
						[
							'message' => 'Check that the correct hook is used.',
							'type'    => 'assertTrue',
							'actual'  => function ( $subscription, $actions_diff ) {
								return 'added' === $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_renewal']['change'];
							},
						],
						[
							'message'  => 'Check that the correct args are used.',
							'expected' => function ( $subscription, $actions_diff ) {
								return WCS_Action_Scheduler_Customer_Notifications::get_action_args( $subscription );
							},
							'actual'   => function ( $subscription, $actions_diff ) {
								$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_renewal']['action'];

								return $new_action->get_args();
							},
						],
						[
							'message'  => 'Check that the notification is in the correct group.',
							'expected' => $this->notifications_as_group,
							'actual'   => function ( $subscription, $actions_diff ) {
								$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_renewal']['action'];

								return $new_action->get_group();
							},
						],
						[
							'message'  => 'Check that the date is correct.',
							'type'     => 'assertEquals',
							'expected' => function ( $subscription, $actions_diff ) {
								$next_payment = new DateTime( $subscription->get_date( 'next_payment' ) );
								$next_payment->modify( $this->offset );

								return $next_payment;
							},
							'actual'   => function ( $subscription, $actions_diff ) {
								$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_renewal']['action'];

								return $new_action->get_schedule()->get_date();
							},
						],
					],
				],
			'Test 3: Free trial with automatic renewal, within trial period.' =>
				[
					'callback'          => [ self::class, 'create_free_trial_subscription' ],
					'params'            => [],
					'assertions_config' => [
						[
							'message' => 'Check that exactly one notification is created.',
							'type'    => 'assertTrue',
							'actual'  => function ( $subscription, $actions_diff ) {
								return $this->verify_notification_count( [ $subscription ], $actions_diff, 1, 0, 0 );
							},
						],
						[
							'message'  => 'Check that the correct hook is used.',
							'expected' => 'woocommerce_scheduled_subscription_customer_notification_trial_expiration',
							'actual'   => function ( $subscription, $actions_diff ) {
								$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_trial_expiration']['action'];

								return $new_action->get_hook();
							},
						],
						[
							'message'  => 'Check that the correct args are used.',
							'expected' => function ( $subscription, $actions_diff ) {
								return WCS_Action_Scheduler_Customer_Notifications::get_action_args( $subscription );
							},
							'actual'   => function ( $subscription, $actions_diff ) {
								$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_trial_expiration']['action'];

								return $new_action->get_args();
							},
						],
						[
							'message'  => 'Check that the notification is in the correct group.',
							'expected' => $this->notifications_as_group,
							'actual'   => function ( $subscription, $actions_diff ) {
								$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_trial_expiration']['action'];

								return $new_action->get_group();
							},
						],
						[
							'message'  => 'Check that the date is correct.',
							'type'     => 'assertEquals',
							'expected' => function ( $subscription, $actions_diff ) {
								$next_payment = new DateTime( $subscription->get_date( 'trial_end' ) );
								$next_payment->modify( $this->offset );

								return $next_payment;
							},
							'actual'   => function ( $subscription, $actions_diff ) {
								$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_trial_expiration']['action'];

								return $new_action->get_schedule()->get_date();
							},
						],
					],
				],
			'Test 4: Free trial with manual renewal, within trial period.' =>
				[
					'callback'          => [ self::class, 'create_free_trial_subscription' ],
					'params'            => [ [], 'true' ],
					'assertions_config' => [
						[
							'message' => 'Check that exactly one notification is created.',
							'type'    => 'assertTrue',
							'actual'  => function ( $subscription, $actions_diff ) {
								return $this->verify_notification_count( [ $subscription ], $actions_diff, 1, 0, 0 );
							},
						],
						[
							'message' => 'Check that the correct hook is used.',
							'type'    => 'assertTrue',
							'actual'  => function ( $subscription, $actions_diff ) {
								return 'added' === $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_trial_expiration']['change'];
							},
						],
						[
							'message'  => 'Check that the correct args are used.',
							'expected' => function ( $subscription, $actions_diff ) {
								return WCS_Action_Scheduler_Customer_Notifications::get_action_args( $subscription );
							},
							'actual'   => function ( $subscription, $actions_diff ) {
								$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_trial_expiration']['action'];

								return $new_action->get_args();
							},
						],
						[
							'message'  => 'Check that the notification is in the correct group.',
							'expected' => $this->notifications_as_group,
							'actual'   => function ( $subscription, $actions_diff ) {
								$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_trial_expiration']['action'];

								return $new_action->get_group();
							},
						],
						[
							'message'  => 'Check that the date is correct.',
							'type'     => 'assertEquals',
							'expected' => function ( $subscription, $actions_diff ) {
								$next_payment = new DateTime( $subscription->get_date( 'trial_end' ) );
								$next_payment->modify( $this->offset );

								return $next_payment;
							},
							'actual'   => function ( $subscription, $actions_diff ) {
								$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_trial_expiration']['action'];

								return $new_action->get_schedule()->get_date();
							},
						],
					],
				],
			'Test 5: Free trial with automatic renewal, after trial period.' =>
				[
					'callback'          => [ self::class, 'create_free_trial_subscription' ],
					'params'            => [ [], 'false', false ],
					'assertions_config' => [
						[
							'message' => 'Check that exactly one notification is created.',
							'type'    => 'assertTrue',
							'actual'  => function ( $subscription, $actions_diff ) {
								return $this->verify_notification_count( [ $subscription ], $actions_diff, 1, 0, 0 );
							},
						],
						[
							'message' => 'Check that the correct hook is used.',
							'type'    => 'assertTrue',
							'actual'  => function ( $subscription, $actions_diff ) {
								return 'added' === $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_renewal']['change'];
							},
						],
						[
							'message'  => 'Check that the correct args are used.',
							'expected' => function ( $subscription, $actions_diff ) {
								return WCS_Action_Scheduler_Customer_Notifications::get_action_args( $subscription );
							},
							'actual'   => function ( $subscription, $actions_diff ) {
								$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_renewal']['action'];

								return $new_action->get_args();
							},
						],
						[
							'message'  => 'Check that the notification is in the correct group.',
							'expected' => $this->notifications_as_group,
							'actual'   => function ( $subscription, $actions_diff ) {
								$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_renewal']['action'];

								return $new_action->get_group();
							},
						],
						[
							'message'  => 'Check that the date is correct.',
							'type'     => 'assertEquals',
							'expected' => function ( $subscription, $actions_diff ) {
								$next_payment = new DateTime( $subscription->get_date( 'next_payment' ) );
								$next_payment->modify( $this->offset );

								return $next_payment;
							},
							'actual'   => function ( $subscription, $actions_diff ) {
								$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_renewal']['action'];

								return $new_action->get_schedule()->get_date();
							},
						],
					],
				],
			'Test 6: Free trial with manual renewal, after trial period.' =>
				[
					'callback'          => [ self::class, 'create_free_trial_subscription' ],
					'params'            => [ [], 'true', false ],
					'assertions_config' => [
						[
							'message' => 'Check that exactly one notification is created.',
							'type'    => 'assertTrue',
							'actual'  => function ( $subscription, $actions_diff ) {
								return $this->verify_notification_count( [ $subscription ], $actions_diff, 1, 0, 0 );
							},
						],
						[
							'message' => 'Check that the correct hook is used.',
							'type'    => 'assertTrue',
							'actual'  => function ( $subscription, $actions_diff ) {
								return 'added' === $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_renewal']['change'];
							},
						],
						[
							'message'  => 'Check that the correct args are used.',
							'expected' => function ( $subscription, $actions_diff ) {
								return WCS_Action_Scheduler_Customer_Notifications::get_action_args( $subscription );
							},
							'actual'   => function ( $subscription, $actions_diff ) {
								$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_renewal']['action'];

								return $new_action->get_args();
							},
						],
						[
							'message'  => 'Check that the notification is in the correct group.',
							'expected' => $this->notifications_as_group,
							'actual'   => function ( $subscription, $actions_diff ) {
								$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_renewal']['action'];

								return $new_action->get_group();
							},
						],
						[
							'message'  => 'Check that the date is correct.',
							'type'     => 'assertEquals',
							'expected' => function ( $subscription, $actions_diff ) {
								$next_payment = new DateTime( $subscription->get_date( 'next_payment' ) );
								$next_payment->modify( $this->offset );

								return $next_payment;
							},
							'actual'   => function ( $subscription, $actions_diff ) {
								$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_renewal']['action'];

								return $new_action->get_schedule()->get_date();
							},
						],
					],
				],
			'Test 7: Expiring subscription.' =>
				[
					'callback'          => [ self::class, 'create_expiring_subscription' ],
					'params'            => [],
					'assertions_config' => [
						[
							'message' => 'Check that exactly two notifications are created.',
							'type'    => 'assertTrue',
							'actual'  => function ( $subscription, $actions_diff ) {
								return $this->verify_notification_count( [ $subscription ], $actions_diff, 2, 0, 0 );
							},
						],
						[
							'message' => 'Check that one expiration and one renewal notifications are created.',
							'type'    => 'assertTrue',
							'actual'  => function ( $subscription, $actions_diff ) {
								return (
									'added' === $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_expiration']['change']
									&& 'added' === $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_renewal']['change']
								);
							},
						],
						[
							'message' => 'Check that the correct args are used.',
							'type'    => 'assertTrue',
							'actual'  => function ( $subscription, $actions_diff ) {
								$new_actions = [
									0 => $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_expiration']['action'],
									1 => $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_renewal']['action'],
								];

								foreach ( $new_actions as $new_action ) {
									$args          = $new_action->get_args();
									$expected_args = WCS_Action_Scheduler_Customer_Notifications::get_action_args( $subscription );

									if ( $args !== $expected_args ) {
										return false;
									}
								}

								return true;
							},
						],
						[
							'message' => 'Check that the notifications are in the correct group.',
							'type'    => 'assertTrue',
							'actual'  => function ( $subscription, $actions_diff ) {
								$new_actions = [
									0 => $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_expiration']['action'],
									1 => $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_renewal']['action'],
								];

								foreach ( $new_actions as $new_action ) {
									if ( $new_action->get_group() !== $this->notifications_as_group ) {
										return false;
									}
								}

								return true;
							},
						],
						[
							'message'  => 'Check the subscription expiry notification date is correct.',
							'type'     => 'assertEquals',
							'expected' => function ( $subscription, $actions_diff ) {
								$end_date = new DateTime( $subscription->get_date( 'end_date' ) );
								$end_date->modify( $this->offset );

								return $end_date;
							},
							'actual'   => function ( $subscription, $actions_diff ) {
								$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_expiration']['action'];

								return $new_action->get_schedule()->get_date();
							},
						],
						[
							'message'  => 'Check the next payment date notification is correct.',
							'type'     => 'assertEquals',
							'expected' => function ( $subscription, $actions_diff ) {
								$next_payment = new DateTime( $subscription->get_date( 'next_payment' ) );
								$next_payment->modify( $this->offset );

								return $next_payment;
							},
							'actual'   => function ( $subscription, $actions_diff ) {
								$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_renewal']['action'];

								return $new_action->get_schedule()->get_date();
							},
						],
					],
				],
			'Test 8: Expiring subscription with trial, within trial.' =>
				[
					'callback'          => [ self::class, 'create_expiring_subscription_with_trial' ],
					'params'            => [],
					'assertions_config' => [
						[
							'message' => 'Check that exactly two notifications are created.',
							'type'    => 'assertTrue',
							'actual'  => function ( $subscription, $actions_diff ) {
								return $this->verify_notification_count( [ $subscription ], $actions_diff, 2, 0, 0 );
							},
						],
						[
							'message' => 'Check that one expiration and one trial expiration notifications are created.',
							'type'    => 'assertTrue',
							'actual'  => function ( $subscription, $actions_diff ) {
								return (
									'added' === $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_expiration']['change']
									&& 'added' === $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_trial_expiration']['change']
								);
							},
						],
						[
							'message' => 'Check that the correct args are used.',
							'type'    => 'assertTrue',
							'actual'  => function ( $subscription, $actions_diff ) {
								$new_actions = [
									0 => $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_expiration']['action'],
									1 => $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_trial_expiration']['action'],
								];

								foreach ( $new_actions as $new_action ) {
									$args          = $new_action->get_args();
									$expected_args = WCS_Action_Scheduler_Customer_Notifications::get_action_args( $subscription );

									if ( $args !== $expected_args ) {
										return false;
									}
								}

								return true;
							},
						],
						[
							'message' => 'Check that the notifications have the correct group.',
							'type'    => 'assertTrue',
							'actual'  => function ( $subscription, $actions_diff ) {
								$new_actions = [
									0 => $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_expiration']['action'],
									1 => $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_trial_expiration']['action'],
								];

								foreach ( $new_actions as $new_action ) {
									if ( $new_action->get_group() !== $this->notifications_as_group ) {
										return false;
									}
								}

								return true;
							},
						],
						[
							'message'  => 'Check the subscription expiry notification date is correct.',
							'type'     => 'assertEquals',
							'expected' => function ( $subscription, $actions_diff ) {
								$end_date = new DateTime( $subscription->get_date( 'end_date' ) );
								$end_date->modify( $this->offset );

								return $end_date;
							},
							'actual'   => function ( $subscription, $actions_diff ) {
								$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_expiration']['action'];

								return $new_action->get_schedule()->get_date();
							},
						],
						[
							'message'  => 'Check the trial expiry notification date is correct.',
							'type'     => 'assertEquals',
							'expected' => function ( $subscription, $actions_diff ) {
								$next_payment = new DateTime( $subscription->get_date( 'next_payment' ) );
								$next_payment->modify( $this->offset );

								return $next_payment;
							},
							'actual'   => function ( $subscription, $actions_diff ) {
								$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_trial_expiration']['action'];

								return $new_action->get_schedule()->get_date();
							},
						],
					],
				],

			'Test 9: Expiring subscription with trial after trial.' =>
				[
					'callback'          => [ self::class, 'create_expiring_subscription_with_trial' ],
					'params'            => [ [], false ],
					'assertions_config' => [
						[
							'message' => 'Check that exactly two notifications are created.',
							'type'    => 'assertTrue',
							'actual'  => function ( $subscription, $actions_diff ) {
								return $this->verify_notification_count( [ $subscription ], $actions_diff, 2, 0, 0 );
							},
						],
						[
							'message' => 'Check that one expiration and one renewal notifications are created.',
							'type'    => 'assertTrue',
							'actual'  => function ( $subscription, $actions_diff ) {
								return (
									'added' === $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_expiration']['change']
									&& 'added' === $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_renewal']['change']
								);
							},
						],
						[
							'message' => 'Check that the correct args are used.',
							'type'    => 'assertTrue',
							'actual'  => function ( $subscription, $actions_diff ) {
								$new_actions = [
									0 => $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_expiration']['action'],
									1 => $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_renewal']['action'],
								];

								foreach ( $new_actions as $new_action ) {
									$args          = $new_action->get_args();
									$expected_args = WCS_Action_Scheduler_Customer_Notifications::get_action_args( $subscription );

									if ( $args !== $expected_args ) {
										return false;
									}
								}

								return true;
							},
						],
						[
							'message' => 'Check that the notifications are in the correct group.',
							'type'    => 'assertTrue',
							'actual'  => function ( $subscription, $actions_diff ) {
								$new_actions = [
									0 => $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_expiration']['action'],
									1 => $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_renewal']['action'],
								];

								foreach ( $new_actions as $new_action ) {
									if ( $new_action->get_group() !== $this->notifications_as_group ) {
										return false;
									}
								}

								return true;
							},
						],
						[
							'message'  => 'Check the subscription expiry notification date is correct.',
							'type'     => 'assertEquals',
							'expected' => function ( $subscription, $actions_diff ) {
								$end_date = new DateTime( $subscription->get_date( 'end_date' ) );
								$end_date->modify( $this->offset );

								return $end_date;
							},
							'actual'   => function ( $subscription, $actions_diff ) {
								$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_expiration']['action'];

								return $new_action->get_schedule()->get_date();
							},
						],
						[
							'message'  => 'Check the next payment date notification is correct.',
							'type'     => 'assertEquals',
							'expected' => function ( $subscription, $actions_diff ) {
								$next_payment = new DateTime( $subscription->get_date( 'next_payment' ) );
								$next_payment->modify( $this->offset );

								return $next_payment;
							},
							'actual'   => function ( $subscription, $actions_diff ) {
								$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_renewal']['action'];

								return $new_action->get_schedule()->get_date();
							},
						],
					],
				],
		];
	}

	/**
	 * Test that the correct notifications are created when different types of subscriptions are created.
	 *
	 * @return void
	 */
	public function test_subscription_create_checks() {
		$this->notifications_general_tester( $this->subscription_create_checks() );
	}

	/**
	 * Check that notification gets updated correctly when subscription is manually renewed.
	 *
	 * @return void
	 */
	public function test_notification_updated_when_subscription_early_renewed() {
		$this->enable_notifications_globally();

		// Create a simple subscription (notification for creating already checked before).
		$subscription = $this->create_simple_subscription();

		// Now do manual renewal and check the notification.
		$config = [
			'Test manual renewal updates notification(s)' =>
			[
				'callback'          => [ self::class, 'manually_renew_simple_subscription' ],
				'params'            => [ $subscription ],
				'assertions_config' => [
					[
						'message' => 'Check that exactly one notification was updated.',
						'type'    => 'assertTrue',
						'actual'  => function ( $subscription, $actions_diff ) {
							return $this->verify_notification_count( [ $subscription ], $actions_diff, 0, 1, 0 );
						},
					],
					[
						'message'  => 'Check that the correct hook is used.',
						'expected' => 'woocommerce_scheduled_subscription_customer_notification_renewal',
						'actual'   => function ( $subscription, $actions_diff ) {
							$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_renewal']['action'];

							return $new_action->get_hook();
						},
					],
					[
						'message'  => 'Check that the correct args are used.',
						'expected' => function ( $subscription, $actions_diff ) {
							return WCS_Action_Scheduler_Customer_Notifications::get_action_args( $subscription );
						},
						'actual'   => function ( $subscription, $actions_diff ) {
							$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_renewal']['action'];

							return $new_action->get_args();
						},
					],
					[
						'message'  => 'Check that the notification is in the correct group.',
						'expected' => $this->notifications_as_group,
						'actual'   => function ( $subscription, $actions_diff ) {
							$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_renewal']['action'];

							return $new_action->get_group();
						},
					],
					[
						'message'  => 'Check that the date is correct.',
						'type'     => 'assertEquals',
						'expected' => function ( $subscription, $actions_diff ) {
							$next_payment = new DateTime( $subscription->get_date( 'next_payment' ) );
							$next_payment->modify( $this->offset );

							return $next_payment;
						},
						'actual'   => function ( $subscription, $actions_diff ) {
							$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_renewal']['action'];

							return $new_action->get_schedule()->get_date();
						},
					],
				],
			],
		];

		$this->notifications_general_tester( $config );
	}

	protected function simple_subscription_updowngrade_checks( $subscription ) {

		$one_month_later_payment = new DateTime( $subscription->get_date( 'next_payment' ) );
		$one_month_later_payment->modify( '+1 month' );
		$one_month_later_payment_str = $one_month_later_payment->format( 'Y-m-d H:i:s' );

		$one_month_later_expiry = new DateTime( $subscription->get_date( 'end' ) );
		$one_month_later_expiry->modify( '+1 month' );
		$one_month_later_expiry_str = $one_month_later_expiry->format( 'Y-m-d H:i:s' );

		// Now switch and check the notification.
		return [
			'Test 1: Switch simple subscription from monthly to yearly' =>
				[
					'callback'          => [ self::class, 'update_billing_period' ],
					'params'            => [ $subscription ],
					'assertions_config' => [
						[
							'message' => 'Check that no notification was updated.',
							'type'    => 'assertTrue',
							'actual'  => function ( $subscription, $actions_diff ) {
								return $this->verify_notification_count( [ $subscription ], $actions_diff, 0, 0, 0 );
							},
						],
					],
				],
			'Test 2: Switch subscription: update next_payment' => [
				'callback'          => [ self::class, 'update_dates' ],
				'params'            => [
					$subscription,
					[
						'next_payment' => $one_month_later_payment_str,
					],
				],
				'assertions_config' => [
					[
						'message' => 'Check that exactly one notification was updated.',
						'type'    => 'assertTrue',
						'actual'  => function ( $subscription, $actions_diff ) {
							return $this->verify_notification_count( [ $subscription ], $actions_diff, 0, 1, 0 );
						},
					],
					[
						'message' => 'Check that the correct hook is used.',
						'type'    => 'assertTrue',
						'actual'  => function ( $subscription, $actions_diff ) {
							return 'woocommerce_scheduled_subscription_customer_notification_renewal' === $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_renewal']['action']->get_hook();
						},
					],
					[
						'message'  => 'Check that the correct args are used.',
						'expected' => function ( $subscription, $actions_diff ) {
							return WCS_Action_Scheduler_Customer_Notifications::get_action_args( $subscription );
						},
						'actual'   => function ( $subscription, $actions_diff ) {
							$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_renewal']['action'];

							return $new_action->get_args();
						},
					],
					[
						'message'  => 'Check that the notification is in the correct group.',
						'expected' => $this->notifications_as_group,
						'actual'   => function ( $subscription, $actions_diff ) {
							$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_renewal']['action'];

							return $new_action->get_group();
						},
					],
					[
						'message'  => 'Check that the date is correct.',
						'type'     => 'assertEquals',
						'expected' => function ( $subscription, $actions_diff ) {
							$next_payment = new DateTime( $subscription->get_date( 'next_payment' ) );
							$next_payment->modify( $this->offset );

							return $next_payment;
						},
						'actual'   => function ( $subscription, $actions_diff ) {
							$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_renewal']['action'];

							return $new_action->get_schedule()->get_date();
						},
					],
				],
			],
			'Test 3: Switch subscription: update expiry' => [
				'callback'          => [ self::class, 'update_dates' ],
				'params'            => [
					$subscription,
					[
						'end' => $one_month_later_expiry_str,
					],
				],
				'assertions_config' => [
					[
						'message' => 'Check that exactly one notification was updated.',
						'type'    => 'assertTrue',
						'actual'  => function ( $subscription, $actions_diff ) {
							return $this->verify_notification_count( [ $subscription ], $actions_diff, 0, 1, 0 );
						},
					],
					[
						'message' => 'Check that the correct hook is used.',
						'type'    => 'assertTrue',
						'actual'  => function ( $subscription, $actions_diff ) {
							return 'woocommerce_scheduled_subscription_customer_notification_expiration' === $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_expiration']['action']->get_hook();
						},
					],
					[
						'message'  => 'Check that the correct args are used.',
						'expected' => function ( $subscription, $actions_diff ) {
							return WCS_Action_Scheduler_Customer_Notifications::get_action_args( $subscription );
						},
						'actual'   => function ( $subscription, $actions_diff ) {
							$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_expiration']['action'];

							return $new_action->get_args();
						},
					],
					[
						'message'  => 'Check that the notification is in the correct group.',
						'expected' => $this->notifications_as_group,
						'actual'   => function ( $subscription, $actions_diff ) {
							$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_expiration']['action'];

							return $new_action->get_group();
						},
					],
					[
						'message'  => 'Check that the date is correct.',
						'type'     => 'assertEquals',
						'expected' => function ( $subscription, $actions_diff ) {
							$end = new DateTime( $subscription->get_date( 'end' ) );
							$end->modify( $this->offset );

							return $end;
						},
						'actual'   => function ( $subscription, $actions_diff ) {
							$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_expiration']['action'];

							return $new_action->get_schedule()->get_date();
						},
					],
				],

			],
			'Test 4: Switch subscription: delete next_payment' => [
				'callback'          => [ self::class, 'delete_subscription_date' ],
				'params'            => [ $subscription, 'next_payment' ],
				'assertions_config' => [
					[
						'message' => 'Check that exactly one notification was deleted.',
						'type'    => 'assertTrue',
						'actual'  => function ( $subscription, $actions_diff ) {
							return $this->verify_notification_count( [ $subscription ], $actions_diff, 0, 0, 1 );
						},
					],
					[
						'message' => 'Check that the correct hook is used.',
						'type'    => 'assertTrue',
						'actual'  => function ( $subscription, $actions_diff ) {
							return 'deleted' === $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_renewal']['change'];
						},
					],
				],
			],
			'Test 5: Switch subscription: delete expiry' => [
				'callback'          => [ self::class, 'delete_subscription_date' ],
				'params'            => [ $subscription, 'end' ],
				'assertions_config' => [
					[
						'message' => 'Check that exactly one notification was updated.',
						'type'    => 'assertTrue',
						'actual'  => function ( $subscription, $actions_diff ) {
							return $this->verify_notification_count( [ $subscription ], $actions_diff, 0, 0, 1 );
						},
					],
					[
						'message' => 'Check that the correct hook is used.',
						'type'    => 'assertTrue',
						'actual'  => function ( $subscription, $actions_diff ) {
							return 'deleted' === $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_expiration']['change'];
						},
					],
				],
			],
		];

	}

	/**
	 * Check that notification gets updated correctly when subscription is up- or downgraded.
	 *
	 * Based on WC_Subscriptions_Switcher::complete_subscription_switches, subscription switch
	 * can update or delete the dates of the subscription, update the billing period, interval, or address.
	 *
	 * The easiest way to test this is to create a subscription, then update all dates, then to delete them,
	 * as other changes won't affect the notification.
	 *
	 * Alternative would be to construct the switching order manually, but there's no easily usable helper
	 * (besides WCS_Helper_Subscription::create_switch_order, which just creates the order, but doesn't add any changes).
	 *
	 * @return void
	 */
	public function test_notification_updated_when_subscription_up_downgraded() {
		$this->enable_notifications_globally();

		// Create a simple subscription (notification for creating already checked before).
		$subscription = $this->create_expiring_subscription();

		$this->notifications_general_tester( $this->simple_subscription_updowngrade_checks( $subscription ) );
	}

	/**
	 * Check that all notifications except for expiry gets removed when subscription gets cancelled.
	 *
	 * TODO: test for expiring subscription with and without trial.
	 *
	 * @return void
	 */
	public function test_notifications_removed_when_simple_subscription_cancelled() {

		$this->enable_notifications_globally();

		// Create a simple subscription (notification for creating already checked before).
		$subscription = $this->create_simple_subscription();

		// Cancel subscription and check the notification.
		$config = [
			'Cancelling simple subscription leaves only expiry notification' =>
				[
					'callback'          => [ self::class, 'cancel_subscription' ], // this should update to pending-cancelled.
					'params'            => [ $subscription ],
					'assertions_config' => [
						[
							'message' => 'Check that one notification was deleted (next_payment) and one was added (expiry).',
							'type'    => 'assertTrue',
							'actual'  => function ( $subscription, $actions_diff ) {
								return $this->verify_notification_count( [ $subscription ], $actions_diff, 1, 0, 1 );
							},
						],
						[
							'message' => 'Check that the correct notification types got updated.',
							'type'    => 'assertTrue',
							'actual'  => function ( $subscription, $actions_diff ) {
								return (
									'added' === $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_expiration']['change']
									&& 'deleted' === $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_renewal']['change']
								);
							},
						],
						[
							'message'  => 'Check that the correct args are used.',
							'expected' => function ( $subscription, $actions_diff ) {
								return WCS_Action_Scheduler_Customer_Notifications::get_action_args( $subscription );
							},
							'actual'   => function ( $subscription, $actions_diff ) {
								$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_expiration']['action'];

								return $new_action->get_args();
							},
						],
						[
							'message'  => 'Check that the notification is in the correct group.',
							'expected' => $this->notifications_as_group,
							'actual'   => function ( $subscription, $actions_diff ) {
								$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_expiration']['action'];

								return $new_action->get_group();
							},
						],
						[
							'message'  => 'Check that the date is correct.',
							'type'     => 'assertEquals',
							'expected' => function ( $subscription, $actions_diff ) {
								$next_payment = new DateTime( $subscription->get_date( 'end' ) );
								$next_payment->modify( $this->offset );

								return $next_payment;
							},
							'actual'   => function ( $subscription, $actions_diff ) {
								$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_expiration']['action'];

								return $new_action->get_schedule()->get_date();
							},
						],
					],
				],
		];

		$this->notifications_general_tester( $config );
	}

	/**
	 * Check that all notifications except for expiry gets removed when expiring subscription
	 * without trial gets cancelled. Expiry notification's schedule should be updated.
	 *
	 * @return void
	 */
	public function test_notifications_removed_when_simple_expiring_subscription_without_trial_cancelled() {

		$this->enable_notifications_globally();

		// Create a simple subscription (notification for creating already checked before).
		$subscription = $this->create_expiring_subscription();

		// Cancel subscription and check the notification.
		$config = [
			'Cancelling simple subscription with expiry updates the expiry notification, removes the rest' =>
				[
					'callback'          => [ self::class, 'cancel_subscription' ], // this should update to pending-cancelled.
					'params'            => [ $subscription ],
					'assertions_config' => [
						[
							'message' => 'Check that one notification was deleted (next_payment) and one was updated (expiry).',
							'type'    => 'assertTrue',
							'actual'  => function ( $subscription, $actions_diff ) {
								return $this->verify_notification_count( [ $subscription ], $actions_diff, 0, 1, 1 );
							},
						],
						[
							'message' => 'Check that the correct notification types got updated.',
							'type'    => 'assertTrue',
							'actual'  => function ( $subscription, $actions_diff ) {
								return (
									'updated' === $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_expiration']['change']
									&& 'deleted' === $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_renewal']['change']
								);
							},
						],
						[
							'message'  => 'Check that the correct args are used.',
							'expected' => function ( $subscription, $actions_diff ) {
								return WCS_Action_Scheduler_Customer_Notifications::get_action_args( $subscription );
							},
							'actual'   => function ( $subscription, $actions_diff ) {
								$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_expiration']['action'];

								return $new_action->get_args();
							},
						],
						[
							'message'  => 'Check that the notification is in the correct group.',
							'expected' => $this->notifications_as_group,
							'actual'   => function ( $subscription, $actions_diff ) {
								$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_expiration']['action'];

								return $new_action->get_group();
							},
						],
						[
							'message'  => 'Check that the date is correct.', //i.e. the notification date matches the end date - offset.
							'type'     => 'assertEquals',
							'expected' => function ( $subscription, $actions_diff ) {
								$next_payment = new DateTime( $subscription->get_date( 'end' ) );
								$next_payment->modify( $this->offset );

								return $next_payment;
							},
							'actual'   => function ( $subscription, $actions_diff ) {
								$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_expiration']['action'];

								return $new_action->get_schedule()->get_date();
							},
						],
					],
				],
		];

		$this->notifications_general_tester( $config );
	}

	/**
	 * Check that all notifications except for expiry gets removed when expiring subscription
	 * without trial gets cancelled. Expiry notification's schedule should be updated.
	 *
	 * @return void
	 */
	public function test_notifications_removed_when_simple_expiring_subscription_with_trial_cancelled() {

		$this->enable_notifications_globally();

		// Create a simple subscription (notification for creating already checked before).
		$subscription = $this->create_expiring_subscription_with_trial();

		// Cancel subscription and check the notification.
		$config = [
			'Cancelling simple subscription with expiry and trial updates the expiry notification, removes the rest' =>
				[
					'callback'          => [ self::class, 'cancel_subscription' ], // this should update to pending-cancelled.
					'params'            => [ $subscription ],
					'assertions_config' => [
						[
							'message' => 'Check that 1 notification were deleted (trial_end) and one was updated (expiry).',
							'type'    => 'assertTrue',
							'actual'  => function ( $subscription, $actions_diff ) {
								return $this->verify_notification_count( [ $subscription ], $actions_diff, 0, 1, 1 );
							},
						],
						[
							'message' => 'Check that the correct notification types got updated.',
							'type'    => 'assertTrue',
							'actual'  => function ( $subscription, $actions_diff ) {
								return (
									'updated' === $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_expiration']['change']
									&& 'deleted' === $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_trial_expiration']['change']
								);
							},
						],
						[
							'message'  => 'Check that the correct args are used.',
							'expected' => function ( $subscription, $actions_diff ) {
								return WCS_Action_Scheduler_Customer_Notifications::get_action_args( $subscription );
							},
							'actual'   => function ( $subscription, $actions_diff ) {
								$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_expiration']['action'];

								return $new_action->get_args();
							},
						],
						[
							'message'  => 'Check that the notification is in the correct group.',
							'expected' => $this->notifications_as_group,
							'actual'   => function ( $subscription, $actions_diff ) {
								$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_expiration']['action'];

								return $new_action->get_group();
							},
						],
						[
							'message'  => 'Check that the date is correct.', //i.e. the notification date matches the end date - offset.
							'type'     => 'assertEquals',
							'expected' => function ( $subscription, $actions_diff ) {
								$next_payment = new DateTime( $subscription->get_date( 'end' ) );
								$next_payment->modify( $this->offset );

								return $next_payment;
							},
							'actual'   => function ( $subscription, $actions_diff ) {
								$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_expiration']['action'];

								return $new_action->get_schedule()->get_date();
							},
						],
					],
				],
		];

		$this->notifications_general_tester( $config );
	}

	/**
	 * Check that all notifications are created again when subscription gets reactivated.
	 *
	 * TODO: test for expiring subscription with and without trial?
	 *
	 * @return void
	 */
	public function test_notifications_added_when_simple_subscription_reactivated() {

		$this->enable_notifications_globally();

		// Create a simple subscription: next_payment notification should be created.
		$subscription = $this->create_simple_subscription();

		// Cancel the subscription.
		self::cancel_subscription( $subscription );

		// Reactivate subscription and check the notification.
		$config = [
			'Reactivating simple subscription creates notification as expected' =>
				[
					'callback'          => [ self::class, 'reactivate_subscription' ],
					'params'            => [ $subscription ],
					'assertions_config' => [
						[
							'message' => 'Check that one notification was deleted (expiry) and one was added (next_payment).',
							'type'    => 'assertTrue',
							'actual'  => function ( $subscription, $actions_diff ) {
								return $this->verify_notification_count( [ $subscription ], $actions_diff, 1, 0, 1 );
							},
						],
						[
							'message' => 'Check that the correct notification types got updated.',
							'type'    => 'assertTrue',
							'actual'  => function ( $subscription, $actions_diff ) {
								return (
									'added' === $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_renewal']['change']
									&& 'deleted' === $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_expiration']['change']
								);
							},
						],
						[
							'message'  => 'Check that the correct args are used.',
							'expected' => function ( $subscription, $actions_diff ) {
								return WCS_Action_Scheduler_Customer_Notifications::get_action_args( $subscription );
							},
							'actual'   => function ( $subscription, $actions_diff ) {
								$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_renewal']['action'];

								return $new_action->get_args();
							},
						],
						[
							'message'  => 'Check that the notification is in the correct group.',
							'expected' => $this->notifications_as_group,
							'actual'   => function ( $subscription, $actions_diff ) {
								$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_renewal']['action'];

								return $new_action->get_group();
							},
						],
						[
							'message'  => 'Check that the date is correct.',
							'type'     => 'assertEquals',
							'expected' => function ( $subscription, $actions_diff ) {
								$next_payment = new DateTime( $subscription->get_date( 'next_payment' ) );
								$next_payment->modify( $this->offset );

								return $next_payment;
							},
							'actual'   => function ( $subscription, $actions_diff ) {
								$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_renewal']['action'];

								return $new_action->get_schedule()->get_date();
							},
						],
					],
				],
		];

		$this->notifications_general_tester( $config );
	}

	/**
	 *
	 *
	 * When I change the next payment date while a free trial is ON, only the free-trial notification is added.
	 * If I then delete the free-trial date (without changing the next payment), I end up with no notification AS actions.
	 *
	 * @return void
	 */
	public function test_delete_trial_end_date_while_in_trial() {
		$this->enable_notifications_globally();

		// Create a subscription with free trial, within the trial period.
		$subscription = self::create_free_trial_subscription();

		// Now remove the trial_end date forcibly.
		$config = [
			'Deleting trial_end date schedules next_payment notification.' =>
				[
					'callback'          => [ self::class, 'delete_subscription_date' ], // this should update to pending-cancelled.
					'params'            => [ $subscription, 'trial_end' ],
					'assertions_config' => [
						[
							'message' => 'Check that exactly one notification was deleted and one added.',
							'type'    => 'assertTrue',
							'actual'  => function ( $subscription, $actions_diff ) {
								return $this->verify_notification_count( [ $subscription ], $actions_diff, 1, 0, 1 );
							},
						],
						[
							'message' => 'Check that the trial_end notification was deleted and next_payment notification was added.',
							'type'    => 'assertTrue',
							'actual'  => function ( $subscription, $actions_diff ) {
								return (
									'added' === $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_renewal']['change']
									&& 'deleted' === $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_trial_expiration']['change']
								);
							},
						],
						[
							'message'  => 'Check that the correct args are used.',
							'expected' => function ( $subscription, $actions_diff ) {
								return WCS_Action_Scheduler_Customer_Notifications::get_action_args( $subscription );
							},
							'actual'   => function ( $subscription, $actions_diff ) {
								$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_renewal']['action'];

								return $new_action->get_args();
							},
						],
						[
							'message'  => 'Check that the notification is in the correct group.',
							'expected' => $this->notifications_as_group,
							'actual'   => function ( $subscription, $actions_diff ) {
								$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_renewal']['action'];

								return $new_action->get_group();
							},
						],
						[
							'message'  => 'Check that the date is correct.',
							'type'     => 'assertEquals',
							'expected' => function ( $subscription, $actions_diff ) {
								$next_payment = new DateTime( $subscription->get_date( 'next_payment' ) );
								$next_payment->modify( $this->offset );

								return $next_payment;
							},
							'actual'   => function ( $subscription, $actions_diff ) {
								$new_action = $actions_diff[ $subscription->get_id() ]['woocommerce_scheduled_subscription_customer_notification_renewal']['action'];

								return $new_action->get_schedule()->get_date();
							},
						],
					],
				],
		];

		$this->notifications_general_tester( $config );
	}

	/**
	 * Check that store manager can change notification period and notifications are updated.
	 *
	 * @return void
	 */
	public function test_change_notification_period() {
		// Create all kinds of subscriptions.
		$subscriptions = [
			$this->create_free_trial_subscription(), // trial_expiration notification.
			$this->create_free_trial_subscription( [], 'true', false ), // next_payment notification.
			$this->create_expiring_subscription(), // next_payment and expiry notification.
			$this->create_expiring_subscription_with_trial(), // trial_expiration and expiry notification.
			$this->create_expiring_subscription_with_trial( [], false ), // next_payment and expiry notification.
			$this->create_simple_subscription(), // next_payment notification.
		];

		// Change the offset.
		$config = [
			'Change the notification period' =>
				[
					'callback'          => [ $this, 'change_notification_period_days' ],
					'params'            => [ $subscriptions ],
					'assertions_config' => [
						[
							'message' => 'Check that 9 notifications got updated.',
							'type'    => 'assertTrue',
							'actual'  => function ( $subscriptions, $actions_diff ) {
								return $this->verify_notification_count( $subscriptions, $actions_diff, 0, 9, 0 );
							},
						],
						[
							'message' => 'Check that all the dates have been updated correctly.',
							'type'    => 'assertTrue',
							'actual'  => function ( $subscriptions, $actions_diff ) {
								foreach ( $subscriptions as $subscription ) {
									$valid_notifications = WCS_Action_Scheduler_Customer_Notifications::get_valid_notifications( $subscription );

									foreach ( $valid_notifications as $notification_date ) {
										$expected_date = new DateTime( $subscription->get_date( $notification_date ) );
										$expected_date->modify( $this->offset );

										$updated_action = $actions_diff[ $subscription->get_id() ][ WCS_Action_Scheduler_Customer_Notifications::get_action_from_date_type( $notification_date ) ]['action'];
										$actual_date    = $updated_action->get_schedule()->get_date();

										if ( $expected_date->getTimestamp() !== $actual_date->getTimestamp() ) {
											return false;
										}
									}
								}

								return true;
							},
						],
					],
				],
		];

		// Check that the notifications got updated.
		$this->notifications_general_tester( $config );
	}

	/**
	 * This fn is in subscriptions, but not in subscriptions core.
	 *
	 * @param $subscription
	 * @param $early_renewal
	 *
	 * @return void
	 */
	protected static function wcs_update_dates_after_early_renewal( $subscription, $early_renewal ) {
		$dates_to_update = self::get_dates_to_update( $subscription );

		if ( ! empty( $dates_to_update ) ) {
			// translators: %s: order ID.
			$order_number = sprintf( _x( '#%s', 'hash before order number', 'woocommerce-subscriptions' ), $early_renewal->get_order_number() );
			$order_link   = sprintf( '<a href="%s">%s</a>', esc_url( wcs_get_edit_post_link( $early_renewal->get_id() ) ), $order_number );

			try {
				$subscription->update_dates( $dates_to_update );

				// translators: placeholder contains a link to the order's edit screen.
				$subscription->add_order_note( sprintf( __( 'Customer successfully renewed early with order %s.', 'woocommerce-subscriptions' ), $order_link ) );
			} catch ( Exception $e ) {
				// translators: placeholder contains a link to the order's edit screen.
				$subscription->add_order_note( sprintf( __( 'Failed to update subscription dates after customer renewed early with order %s.', 'woocommerce-subscriptions' ), $order_link ) );
			}
		}
	}

	/**
	 * This fn is in subscriptions as \WCS_Early_Renewal_Manager::get_dates_to_update, but not in subscriptions core.
	 *
	 * @param $subscription
	 *
	 * @return array
	 */
	public static function get_dates_to_update( $subscription ) {
		$next_payment_time = $subscription->get_time( 'next_payment' );
		$dates_to_update   = array();

		if ( $next_payment_time > 0 && $next_payment_time > time() ) {
			$next_payment_timestamp = wcs_add_time( $subscription->get_billing_interval(), $subscription->get_billing_period(), $next_payment_time );

			if ( $subscription->get_time( 'end' ) === 0 || $next_payment_timestamp < $subscription->get_time( 'end' ) ) {
				$dates_to_update['next_payment'] = gmdate( 'Y-m-d H:i:s', $next_payment_timestamp );
			} else {
				// Delete the next payment date if the calculated next payment date occurs after the end date.
				$dates_to_update['next_payment'] = 0;
			}
		} elseif ( $subscription->get_time( 'end' ) > 0 ) {
			$dates_to_update['end'] = gmdate( 'Y-m-d H:i:s', wcs_add_time( $subscription->get_billing_interval(), $subscription->get_billing_period(), $subscription->get_time( 'end' ) ) );
		}

		return $dates_to_update;
	}

	/**
	 * Wrapper around \WC_Subscription::delete_date that also returns the subscription (which we need in the tests).
	 *
	 * @param $subscription
	 * @param $date_type
	 *
	 * @return WC_Subscription
	 */
	protected static function delete_subscription_date( $subscription, $date_type ) {
		$subscription->delete_date( $date_type );
		$subscription->save();
		return $subscription;
	}

	/**
	 * Wrapper to update the subscription's billing period (and return the subscription back).
	 *
	 * @param $subscription
	 * @param $new_period
	 *
	 * @return WC_Subscription
	 */
	protected static function update_billing_period( $subscription, $new_period = 'year' ) {
		$subscription->set_billing_period( $new_period );
		$subscription->save();
		return $subscription;
	}

	/**
	 * Wrapper to update the subscription's billing period (and return the subscription back).
	 *
	 * @param $subscription
	 * @param $dates_to_update
	 *
	 * @return WC_Subscription
	 */
	protected static function update_dates( $subscription, $dates_to_update ) {
		if ( ! empty( $dates_to_update ) ) {
			$subscription->update_dates( $dates_to_update );
		}

		return $subscription;
	}

	protected function change_notification_period_days( $subscriptions, $new_offset = 4 ) {

		$this->offset              = '-' . $new_offset . ' days';
		$this->offset_for_settings = [
			'number' => "$new_offset",
			'unit'   => 'days',
		];
		update_option( WC_Subscriptions_Admin::$option_prefix . WC_Subscriptions_Email_Notifications::$offset_setting_string, $this->offset_for_settings );

		// Now the batch processing needs to process everything, otherwise comparison of actions will fail.
		$processor = new WCS_Notifications_Debug_Tool_Processor();
		while ( $processor->get_total_pending_count() > 0 ) {
			$batch = $processor->get_next_batch_to_process( 10 );
			$processor->process_batch( $batch );
		}

		return $subscriptions;
	}
}

