<?php

/**
 * Test suite for the WCS_Retry_Migrator_Test class
 */
class WCS_Retry_Migrator_Test extends WCS_Unit_Test_Case {
	protected static $retry_data;

	/**
	 * @var WCS_Retry_Store
	 */
	protected static $database_store;

	/**
	 * @var WCS_Retry_Store
	 */
	protected static $post_store;

	/**
	 * @var WCS_Retry_Migrator
	 */
	protected static $migrator;

	public static function setUpBeforeClass() {
		self::$database_store = new WCS_Retry_Database_Store();
		self::$post_store     = new WCS_Retry_Post_Store();
		self::$migrator       = new WCS_Retry_Migrator( self::$post_store, self::$database_store, new WC_Logger() );

		self::$retry_data = array(
			'order_id' => 1235,
			'status'   => 'unique_status',
			'date_gmt' => gmdate( 'Y-m-d H:i:s', strtotime( '-2 days' ) ),
			'rule_raw' => array(
				'retry_after_interval'            => DAY_IN_SECONDS / 2,
				'email_template_customer'         => 'WCS_Unit_Test_Email_Customer',
				'email_template_admin'            => 'WCS_Unit_Test_Email_Admin',
				'status_to_apply_to_order'        => 'unique_status',
				'status_to_apply_to_subscription' => 'unique_status',
			),
		);

		self::$database_store->init();
		self::$post_store->init();
	}

	/**
	 * Tests that a retry created on the database shouldn't be migrated.
	 */
	public function test_should_migrate_entry_database() {
		$retry_id = self::$database_store->save( new WCS_Retry( self::$retry_data ) );
		$this->assertFalse( self::$migrator->should_migrate_entry( $retry_id ) );
	}

	/**
	 * Tests that a retry created on the post store should be migrated.
	 */
	public function test_should_migrate_entry_post() {
		$retry_id = self::$post_store->save( new WCS_Retry( self::$retry_data ) );
		$this->assertTrue( self::$migrator->should_migrate_entry( $retry_id ) );
	}

	/**
	 * Tests that a retry created on the database store is not being migrated.
	 */
	public function test_migrate_retry_database() {
		$retry_id = self::$database_store->save( new WCS_Retry( self::$retry_data ) );
		$this->assertFalse( self::$migrator->migrate_entry( $retry_id ) );
	}

	/**
	 * Tests that a retry created on the post store is being migrated.
	 */
	public function test_migrate_retry_post() {
		$post_retry_id     = self::$post_store->save( new WCS_Retry( self::$retry_data ) );
		$database_retry_id = self::$migrator->migrate_entry( $post_retry_id );

		$this->assertNull( self::$post_store->get_retry( $post_retry_id ) );
		$this->assertInstanceOf( 'WCS_Retry', self::$database_store->get_retry( $database_retry_id ) );
	}
}
