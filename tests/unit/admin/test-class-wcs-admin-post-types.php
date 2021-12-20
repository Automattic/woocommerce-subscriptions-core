<?php
/**
 * Class WCS_Admin_Post_Types_Test
 *
 * @package WooCommerce\SubscriptionsCore\Tests
 */

/**
 * Test suite for the WCS_Admin_Post_Types class
 */
class WCS_Admin_Post_Types_Test extends WP_UnitTestCase {

	/**
	 * Data provider for @see test_set_post__in_query_var();
	 *
	 * @return array
	 */
	public function provider_post__in_query_var() {

		$post_ids = [
			123,
			5813,
			2134,
			5589,
		];

		return [
			// Make sure an empty $post_ids array returns the WCS_Admin_Post_Types::$post__in_none value of array( 0 )
			[ [], [], [ 'post__in' => [ 0 ] ] ],

			// Make sure a non-empty $post_ids array is set as the entire value when 'post__in' not already set
			[ [], $post_ids, [ 'post__in' => $post_ids ] ],

			// Make sure intersection of two values is returned when both set
			[
				[
					'post__in' => [
						111,
						123,
						2000,
						2134,
						5555,
						9999,
					],
				],
				$post_ids,
				[
					'post__in' => [
						1 => 123,
						3 => 2134,
					],
				],
			],

			// Make sure a non-empty $post_ids array is set as the entire value when 'post__in' not already set
			[
				[],
				$post_ids,
				[
					'post__in' => $post_ids,
				],
			],

			// Make sure 'post__in' value of array( 0 ) is always preserved
			[
				[
					'post__in' => [ 0 ],
				],
				[],
				[
					'post__in' => [ 0 ],
				],
			],
			[
				[
					'post__in' => [ 0 ],
				],
				$post_ids,
				[
					'post__in' => [ 0 ],
				],
			],
		];
	}

	/**
	 * Test all conditions for WCS_Admin_Post_Types::set_post__in_query_var()
	 *
	 * @dataProvider provider_post__in_query_var
	 */
	public function test_set_post__in_query_var( $query_vars, $post_ids, $expected_result ) {
		$actual = WCS_Admin_Post_Types::set_post__in_query_var( $query_vars, $post_ids );
		$this->assertEquals( $expected_result, $actual );
	}
}
