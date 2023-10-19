<?php

// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key

/**
 * Test suite for the WCS_Post_Meta_Cache_Manager class
 */
class WCS_Post_Meta_Cache_Manager_Test extends WP_UnitTestCase {

	/** @var string The post type to test with the cache manager. */
	protected $post_type = 'test_post';

	/** @var array The post meta keys to test with the cache manager. */
	protected $meta_keys = array(
		'test_key_one',
		'test_key_two',
	);

	/** @var int A mock post ID to test, used for validating post type matches */
	protected $known_post_id = 12358;

	/** @var string The hook triggered by WCS_Post_Meta_Cache_Manager for meta updates */
	protected $update_hook = 'wcs_update_post_meta_caches';

	/** @var string The hook triggered by WCS_Post_Meta_Cache_Manager for post meta deletion */
	protected $delete_all_hook = 'wcs_delete_all_post_meta_caches';

	/** @var array Store args expected to be passed to callbacks on a hook. Used by @see $this->check_callback_args() */
	protected $expected_args = array();

	/** @var string Shared value to use to check if expected args were processed by @see $this->check_callback_args() */
	protected $expected_args_processed = 'processed';

	/** @var array Store args to use to determine whether to filter get_post_meta return values for testing */
	protected $post_meta_filter_args = array();

	/** @var int Keep track of how many times a callback on a hook has been called. */
	protected $callback_check_count = 0;

	public static function set_up_before_class() {
		parent::set_up_before_class();
		// Create a dummy WP post for the $known_post_id used in these tests.
		$post_data = array(
			'post_title'   => 'Test Post',
			'post_content' => '',
			'import_id'    => 12358,
		);

		wp_insert_post( $post_data );
	}

	public static function tear_down_after_class() {
		parent::tear_down_after_class();
		wp_delete_post( 12358, true );
	}

	/**
	 * Check callbacks are attached correctly
	 */
	public function test_init() {

		$cache_manager = $this->get_mock_cache_manager();

		// the remove_action() function returns boolean true when the given method has been removed as a callback, and false when it was not
		$this->assertFalse( remove_action( 'before_delete_post', array( $cache_manager, 'post_deleted' ) ) );
		$this->assertFalse( remove_action( 'trashed_post', array( $cache_manager, 'post_deleted' ) ) );
		$this->assertFalse( remove_action( 'untrashed_post', array( $cache_manager, 'post_untrashed' ) ) );
		$this->assertFalse( remove_action( 'added_post_meta', array( $cache_manager, 'meta_added' ) ) );
		$this->assertFalse( remove_action( 'update_post_meta', array( $cache_manager, 'meta_updated' ) ) );
		$this->assertFalse( remove_action( 'deleted_post_meta', array( $cache_manager, 'meta_deleted' ) ) );
		$this->assertFalse( remove_action( 'update_post_metadata', array( $cache_manager, 'meta_updated_with_previous' ) ) );
		$this->assertFalse( remove_action( 'delete_post_metadata', array( $cache_manager, 'meta_deleted_all' ), 100 ) );

		$cache_manager->init();

		$this->assertTrue( remove_action( 'before_delete_post', array( $cache_manager, 'post_deleted' ) ) );
		$this->assertTrue( remove_action( 'trashed_post', array( $cache_manager, 'post_deleted' ) ) );
		$this->assertTrue( remove_action( 'untrashed_post', array( $cache_manager, 'post_untrashed' ) ) );
		$this->assertTrue( remove_action( 'added_post_meta', array( $cache_manager, 'meta_added' ) ) );
		$this->assertTrue( remove_action( 'update_post_meta', array( $cache_manager, 'meta_updated' ) ) );
		$this->assertTrue( remove_action( 'deleted_post_meta', array( $cache_manager, 'meta_deleted' ) ) );
		$this->assertTrue( remove_action( 'update_post_metadata', array( $cache_manager, 'meta_updated_with_previous' ) ) );
		$this->assertTrue( remove_action( 'delete_post_metadata', array( $cache_manager, 'meta_deleted_all' ), 100 ) );
	}

	/* Callbacks for post meta hooks */

	public function provider_meta() {

		$unknown_post_id  = 85321;
		$known_meta_key   = reset( $this->meta_keys );
		$unknown_meta_key = 'unknown_meta_key';
		$meta_value       = 'some_meta_value';

		return array(

			// Make sure the update hook is triggered for post IDs/type and meta key known to the manager
			array(
				true,
				$this->known_post_id,
				$known_meta_key,
				$meta_value,
			),

			// Make sure the update hook is not triggered for post IDs/type known to the manager, but not a meta key
			array(
				false,
				$this->known_post_id,
				$unknown_meta_key,
				$meta_value,
			),

			// Make sure the update hook is not triggered for post IDs/type not known to the manager, even with a meta key known to it
			array(
				false,
				$unknown_post_id,
				$known_meta_key,
				$meta_value,
			),

			// Make sure the update hook is not triggered for post IDs/type and meta key not known to the manager
			array(
				false,
				$unknown_post_id,
				$unknown_meta_key,
				$meta_value,
			),
		);
	}

	/**
	 * When the WCS_Post_Meta_Cache_Manager::meta_added() method is called, check if the update hook
	 * is fired under correct circumstances, and not fired under other circumstances
	 *
	 * @dataProvider provider_meta
	 */
	public function test_meta_added( $expected_to_run, $post_id, $meta_key, $meta_value ) {

		$cache_manager = $this->get_mock_cache_manager( $post_id );
		$cache_manager->init();
		$this->attach_callback_check( $this->update_hook );

		$run_count_original = did_action( $this->update_hook );

		$expected_args       = array(
			'add',
			$post_id,
			$meta_key,
			$meta_value,
		);
		$this->expected_args = $expected_args;

		$cache_manager->meta_added( 0, $post_id, $meta_key, $meta_value );

		$expected_run_count = $run_count_original;

		// Make sure the hook was triggered
		if ( $expected_to_run ) {
			$expected_run_count++;
			$expected_args = $this->expected_args_processed;
		}

		$this->assertEquals( $expected_run_count, did_action( $this->update_hook ) );
		$this->assertEquals( $this->expected_args, $expected_args );

		$this->detach_callback_check( $this->update_hook );
	}

	/**
	 * When the WCS_Post_Meta_Cache_Manager::meta_deleted() method is called, check if the update hook
	 * is fired under correct circumstances, and not fired under other circumstances
	 *
	 * @dataProvider provider_meta
	 */
	public function test_meta_deleted( $expected_to_run, $post_id, $meta_key, $meta_value ) {

		$cache_manager = $this->get_mock_cache_manager( $post_id );
		$cache_manager->init();
		$this->attach_callback_check( $this->update_hook );

		$run_count_original = did_action( $this->update_hook );

		$expected_args       = array(
			'delete',
			$post_id,
			$meta_key,
			$meta_value,
		);
		$this->expected_args = $expected_args;

		$cache_manager->meta_deleted( 0, $post_id, $meta_key, $meta_value );

		$expected_run_count = $run_count_original;

		// Make sure the hook was triggered
		if ( $expected_to_run ) {
			$expected_run_count++;
			$expected_args = $this->expected_args_processed;
		}

		$this->assertEquals( $expected_run_count, did_action( $this->update_hook ) );
		$this->assertEquals( $this->expected_args, $expected_args );

		$this->detach_callback_check( $this->update_hook );
	}

	public function provider_meta_with_previous() {

		$unknown_post_id  = 85321;
		$known_meta_key   = reset( $this->meta_keys );
		$unknown_meta_key = 'unknown_meta_key';
		$meta_value       = 'some_meta_value';
		$prev_meta_value  = 'previous_meta_value';

		return array(

			// Make sure the update hook is triggered for post IDs/type and meta key known to the manager when the prev meta value is different
			array(
				true,
				$this->known_post_id,
				$known_meta_key,
				$meta_value,
				$prev_meta_value,
			),

			// Make sure the update hook is not triggered for post IDs/type and meta key known to the manager when the prev meta value is the same
			array(
				false,
				$this->known_post_id,
				$known_meta_key,
				$meta_value,
				$meta_value,
			),

			// Make sure the update hook is not triggered for post IDs/type known to the manager, but not a meta key, regardless of meta values
			array(
				false,
				$this->known_post_id,
				$unknown_meta_key,
				$meta_value,
				$prev_meta_value,
			),
			array(
				false,
				$this->known_post_id,
				$unknown_meta_key,
				$meta_value,
				$meta_value,
			),

			// Make sure the update hook is not triggered for post IDs/type not known to the manager, even with a meta key known to it and different meta values
			array(
				false,
				$unknown_post_id,
				$known_meta_key,
				$meta_value,
				$prev_meta_value,
			),
			array(
				false,
				$unknown_post_id,
				$known_meta_key,
				$meta_value,
				$meta_value,
			),

			// Make sure the update hook is not triggered for post IDs/type and meta key not known to the manager regardless of meta values
			array(
				false,
				$unknown_post_id,
				$unknown_meta_key,
				$meta_value,
				$prev_meta_value,
			),
			array(
				false,
				$unknown_post_id,
				$unknown_meta_key,
				$meta_value,
				$meta_value,
			),
		);
	}

	/**
	 * When the WCS_Post_Meta_Cache_Manager::meta_updated_with_previous() method is called, check if the
	 * update hook is fired under correct circumstances, and not fired under other circumstances
	 *
	 * @dataProvider provider_meta_with_previous
	 */
	public function test_meta_updated_with_previous( $expected_to_run, $post_id, $meta_key, $meta_value, $prev_value ) {

		$cache_manager = $this->get_mock_cache_manager( $post_id );
		$cache_manager->init();
		$this->attach_callback_check( $this->update_hook );

		$run_count_original = did_action( $this->update_hook );

		$expected_args       = array(
			'update',
			$post_id,
			$meta_key,
			$meta_value,
		);
		$this->expected_args = $expected_args;

		$cache_manager->meta_updated_with_previous( null, $post_id, $meta_key, $meta_value, $prev_value );

		$expected_run_count = $run_count_original;

		// Make sure the hook was triggered
		if ( $expected_to_run ) {
			$expected_run_count++;
			$expected_args = $this->expected_args_processed;
		}

		$this->assertEquals( $expected_run_count, did_action( $this->update_hook ) );
		$this->assertEquals( $this->expected_args, $expected_args );

		$this->detach_callback_check( $this->update_hook );
	}

	/**
	 * When the WCS_Post_Meta_Cache_Manager::meta_updated() method is called, check if the
	 * update hook is fired under correct circumstances, and not fired under other circumstances
	 *
	 * @dataProvider provider_meta
	 */
	public function test_meta_updated( $expected_to_run, $post_id, $meta_key, $meta_value ) {

		$cache_manager = $this->get_mock_cache_manager( $post_id );
		$cache_manager->init();
		$this->attach_callback_check( $this->update_hook );

		$run_count_original = did_action( $this->update_hook );

		$expected_args       = array(
			'update',
			$post_id,
			$meta_key,
			$meta_value,
		);
		$this->expected_args = $expected_args;

		$cache_manager->meta_updated( 0, $post_id, $meta_key, $meta_value );

		$this->detach_get_post_meta_filter();

		$expected_run_count = $run_count_original;

		// Make sure the hook was triggered
		if ( $expected_to_run ) {
			$expected_run_count++;
			$expected_args = $this->expected_args_processed;
		}

		$this->assertEquals( $expected_run_count, did_action( $this->update_hook ) );
		$this->assertEquals( $this->expected_args, $expected_args );

		$this->detach_callback_check( $this->update_hook );
	}

	public function provider_meta_deleted_all() {

		$unknown_post_id  = 85321;
		$known_meta_key   = reset( $this->meta_keys );
		$unknown_meta_key = 'unknown_meta_key';
		$meta_value       = 'some_meta_value';

		return array(

			// Make sure the update hook is triggered for post IDs/type and meta key known to the manager
			array(
				true,
				$this->known_post_id,
				$known_meta_key,
				$meta_value,
			),

			// Make sure the update hook is not triggered for post IDs/type known to the manager, but not a meta key
			array(
				false,
				$this->known_post_id,
				$unknown_meta_key,
				$meta_value,
			),

			// Make sure the update hook is not triggered for post IDs/type not known to the manager, even with a meta key known to it
			array(
				false,
				$unknown_post_id,
				$known_meta_key,
				$meta_value,
			),

			// Make sure the update hook is not triggered for post IDs/type and meta key not known to the manager
			array(
				false,
				$unknown_post_id,
				$unknown_meta_key,
				$meta_value,
			),
		);
	}

	/**
	 * When the WCS_Post_Meta_Cache_Manager::meta_deleted_all() method is called, check if the delete hook
	 * is fired under correct circumstances, and not fired under other circumstances.
	 *
	 * @dataProvider provider_meta_deleted_all
	 */
	public function test_meta_deleted_all( $expected_to_run, $post_id, $meta_key ) {

		$cache_manager = $this->get_mock_cache_manager( $post_id );
		$cache_manager->init();
		$this->attach_callback_check( $this->delete_all_hook );

		$run_count_original = did_action( $this->delete_all_hook );

		$expected_args       = array(
			$meta_key,
		);
		$this->expected_args = $expected_args;

		$cache_manager->meta_deleted_all( null, $post_id, $meta_key, '', $expected_to_run );

		$expected_run_count = $run_count_original;

		// Make sure the hook was triggered
		if ( $expected_to_run ) {
			$expected_run_count++;
			$expected_args = $this->expected_args_processed;
		}

		$this->assertEquals( $expected_run_count, did_action( $this->delete_all_hook ) );
		$this->assertEquals( $this->expected_args, $expected_args );

		$this->detach_callback_check( $this->update_hook );
	}

	/* Callbacks for post hooks */

	/**
	 * When the WCS_Post_Meta_Cache_Manager::post_untrashed() method is called, check if the
	 * update hook is fired under correct circumstances, and not fired under other circumstances.
	 *
	 * @dataProvider provider_meta
	 */
	public function test_post_untrashed( $expected_to_run, $post_id, $meta_key, $meta_value ) {

		// We don't need to test meta keys outside of the known keys here
		if ( ! in_array( $meta_key, $this->meta_keys, true ) ) {
			$this->markTestSkipped( 'Test not required - unknown key' );
		}

		$cache_manager = $this->get_mock_cache_manager( $post_id );
		$cache_manager->init();
		$this->attach_callback_check( $this->update_hook );

		$run_count_original = did_action( $this->update_hook );

		$expected_args       = array(
			'add',
			$post_id,
			$this->meta_keys,
			$meta_value,
		);
		$this->expected_args = $expected_args;

		// Hack to make sure get_post_meta() returns the $prev_value
		$this->attach_get_post_meta_filter( $post_id, $meta_key, $meta_value );

		$cache_manager->post_untrashed( $post_id );

		$this->detach_get_post_meta_filter();

		$expected_run_count = $run_count_original;

		// Make sure the hook was triggered
		if ( $expected_to_run ) {
			$expected_run_count += count( $this->meta_keys );
			$expected_args       = $this->expected_args_processed;
		}

		$this->assertEquals( $expected_run_count, did_action( $this->update_hook ) );
		$this->assertEquals( $this->expected_args, $expected_args );

		$this->detach_callback_check( $this->update_hook );
	}

	/**
	 * When the WCS_Post_Meta_Cache_Manager::post_untrashed() method is called, check if the
	 * update hook is fired under correct circumstances, and not fired under other circumstances.
	 *
	 * @dataProvider provider_meta
	 */
	public function test_post_deleted( $expected_to_run, $post_id, $meta_key, $meta_value ) {

		// We don't need to test meta keys outside of the known keys here
		if ( ! in_array( $meta_key, $this->meta_keys, true ) ) {
			$this->markTestSkipped( 'Test not required - unknown key' );
		}

		$cache_manager = $this->get_mock_cache_manager( $post_id );
		$cache_manager->init();
		$this->attach_callback_check( $this->update_hook );

		$run_count_original = did_action( $this->update_hook );

		// Hack to make sure get_post_meta() returns the $prev_value
		$this->attach_get_post_meta_filter( $post_id, $meta_key, $meta_value );

		$expected_args       = array(
			'delete',
			$post_id,
			$this->meta_keys,
			'', // even if there is a meta value set, that shouldn't be used
		);
		$this->expected_args = $expected_args;

		$cache_manager->post_deleted( $post_id );

		$this->detach_get_post_meta_filter();

		$expected_run_count = $run_count_original;

		// Make sure the hook was triggered
		if ( $expected_to_run ) {
			$expected_run_count += count( $this->meta_keys );
			$expected_args       = $this->expected_args_processed;
		}

		$this->assertEquals( $expected_run_count, did_action( $this->update_hook ) );
		$this->assertEquals( $this->expected_args, $expected_args );

		$this->detach_callback_check( $this->update_hook );
	}

	/** Helper hacks */

	/**
	 * Attach code to return a custom value on calls to get_post_meta().
	 *
	 * @param $post_id
	 * @param $meta_key
	 * @param $return_value
	 */
	protected function attach_get_post_meta_filter( $post_id, $meta_key, $return_value ) {
		$this->post_meta_filter_args = array(
			'post_id'      => $post_id,
			'meta_key'     => $meta_key,
			'return_value' => $return_value,
		);
		add_action( 'get_post_metadata', array( $this, 'get_post_meta_filter' ), 10, 4 );
	}

	/**
	 * Detach the code we use to filter get_post_meta() calls.
	 */
	protected function detach_get_post_meta_filter() {
		$this->post_meta_filter_args = array();
		remove_action( 'get_post_metadata', array( $this, 'get_post_meta_filter' ) );
	}

	/**
	 * A callback for filtering post meta data.
	 *
	 * @param null $check
	 * @param int $post_id
	 * @param string $meta_key
	 * @return mixed
	 */
	public function get_post_meta_filter( $check, $post_id, $meta_key ) {
		if ( isset( $this->post_meta_filter_args['post_id'] ) && $post_id === $this->post_meta_filter_args['post_id'] && $meta_key === $this->post_meta_filter_args['meta_key'] ) {
			$check = $this->post_meta_filter_args['return_value'];
		}
		return $check;
	}

	/**
	 * Attach a callback to check if a hook ran with expected args, as set in @see $this->expected_args
	 */
	protected function attach_callback_check( $hook ) {
		$params = ( $this->update_hook === $hook ) ? 4 : 1;
		add_action( $hook, array( $this, 'check_callback_args' ), 10, $params );
	}

	/**
	 * Attach a callback to check if a hook ran with expected args, as set in @see $this->expected_args
	 */
	protected function detach_callback_check( $hook ) {
		remove_action( $hook, array( $this, 'check_callback_args' ), 10 );
	}

	/**
	 * A callback to check if a hook ran with expected args, as set in @see $this->expected_args
	 */
	public function check_callback_args() {

		$args = func_get_args();

		$this->assertEquals( $args[0], $this->expected_args[0] );

		if ( count( $this->expected_args ) > 1 ) {
			$this->assertEquals( $args[1], $this->expected_args[1] );
			if ( is_array( $this->expected_args[2] ) ) {
				$this->assertTrue( in_array( $args[2], $this->expected_args[2], true ) );
			} else {
				$this->assertEquals( $args[2], $this->expected_args[2] );
			}
			$this->assertEquals( $args[3], $this->expected_args[3] );
		}

		$this->callback_check_count++;

		// tell methods attaching this method to a hook that it ran successfully by updating the expected args on the last iteration
		if ( count( $this->expected_args ) <= 1 || ! is_array( $this->expected_args[2] ) || $this->callback_check_count >= count( $this->expected_args[2] ) ) {
			$this->expected_args        = $this->expected_args_processed;
			$this->callback_check_count = 0;
		} elseif ( is_array( $this->expected_args[2] ) ) {
			// Make sure the meta filter is updated to use the next meta key in the set
			$this->attach_get_post_meta_filter( $args[1], $this->expected_args[2][ $this->callback_check_count ], $args[3] );
		}

		// Return first param, when set, in case it's attached to a filter
		return $args[0] ?? null;
	}

	/**
	 * Get an instance of WCS_Post_Meta_Cache_Manager for testing against.
	 *
	 * @param mixed $post_id The post ID being tested, used to determine whether to mock the WCS_Post_Meta_Cache_Manager::is_managed_post_type() method to return true or false
	 * @return WCS_Post_Meta_Cache_Manager
	 */
	private function get_mock_cache_manager( $post_id = null ) {

		$post_type = $this->post_type;
		$meta_keys = $this->meta_keys;

		$mock_cache_manager = $this->getMockBuilder( 'WCS_Post_Meta_Cache_Manager' )
								->setConstructorArgs( array( $post_type, $meta_keys ) )
								->setMethods( array( 'is_managed_post_type' ) )
								->getMock();

		$is_managed_post_type = $post_id === $this->known_post_id;
		$mock_cache_manager->method( 'is_managed_post_type' )->will( $this->returnValue( $is_managed_post_type ) );

		return $mock_cache_manager;
	}
}
