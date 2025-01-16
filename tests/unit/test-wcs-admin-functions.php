<?php

/**
 * Tests for the functions contained in includes/admin/wcs-admin-functions.php.
 */
class WCS_Admin_Functions_Test extends WP_UnitTestCase {
	/**
	 * User ID of an administrator-level test user.
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * User ID of a contributor-level test user.
	 *
	 * @var int
	 */
	private static $contributor_id;

	/**
	 * Ensure the admin functions are loaded in preparation for our tests.
	 *
	 * @return void
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		if ( ! function_exists( 'wcs_admin_notice' ) ) {
			require_once __DIR__ . '/../../includes/admin/wcs-admin-functions.php';
		}

		self::$admin_id       = self::factory()->user->create( array( 'role' => 'administrator' ) );
		self::$contributor_id = self::factory()->user->create( array( 'role' => 'contributor' ) );
	}

	/**
	 * Admin notices should target a specific user, and should not be visible to other users.
	 *
	 * @see wcs_add_admin_notice()
	 * @see wcs_clear_admin_notices()
	 * @see wcs_display_admin_notices()
	 *
	 * @return void
	 */
	public function test_wcs_admin_notice_visibility_by_user() {
		$message_text = 'The first rule of subscription club, is you do not talk about subscription club.';

		wp_set_current_user( self::$admin_id );
		wcs_add_admin_notice( $message_text );

		wp_set_current_user( self::$contributor_id );
		$this->assertEquals(
			'',
			$this->capture_wcs_admin_notice_text(),
			'The message was not exposed to the wrong user.'
		);

		wp_set_current_user( self::$admin_id );
		$this->assertStringContainsString(
			$message_text,
			$this->capture_wcs_admin_notice_text(),
			'The expected message was shared with the user.'
		);

		wcs_add_admin_notice( $message_text, 'success', self::$contributor_id );
		$this->assertEquals(
			'',
			$this->capture_wcs_admin_notice_text(),
			'The message (which does not target the current user) is not inadvertently shown to the current user.'
		);

		wp_set_current_user( self::$contributor_id );
		$this->assertStringContainsString(
			$message_text,
			$this->capture_wcs_admin_notice_text(),
			'The expected message was shared with the correct user.'
		);
	}

	/**
	 * Admin notices can target a specific admin screen, and should not render outside of that context.
	 *
	 * @see wcs_add_admin_notice()
	 * @see wcs_clear_admin_notices()
	 * @see wcs_display_admin_notices()
	 *
	 * @return void
	 */
	public function test_wcs_admin_notice_visibility_by_screen() {
		global $current_screen;

		$original_screen = $current_screen;
		$message_text    = 'The second rule of subscription club, is you DO NOT talk about subscription club.';

		wp_set_current_user( self::$admin_id );
		wcs_add_admin_notice( $message_text, 'error', null, 'subscriptions-dashboard' );

		$this->assertEquals(
			'',
			$this->capture_wcs_admin_notice_text(),
			'The message was not exposed outside of the specified screen.'
		);

		$test_screen = WP_Screen::get( 'subscriptions-dashboard' );
		set_current_screen( $test_screen );

		$this->assertStringContainsString(
			$message_text,
			$this->capture_wcs_admin_notice_text(),
			'The message was displayed in the context of the specified screen.'
		);

		set_current_screen( $original_screen );
	}

	/**
	 * Admin notices generally act as 'flash messages' and are removed from the queue after they
	 * have rendered. However, the system also allows for them to stay in the queue.
	 *
	 * @return void
	 */
	public function test_wcs_admin_notice_queue_clearance() {
		$message_text = "That's no moon, it's a subscription notice.";
		wcs_add_admin_notice( $message_text, 'error' );

		$this->assertStringContainsString(
			esc_html( $message_text ),
			$this->capture_wcs_admin_notice_text( false ),
			'The admin notice is displayed as expected.'
		);

		$this->assertStringContainsString(
			esc_html( $message_text ),
			$this->capture_wcs_admin_notice_text(),
			'The admin notice is displayed a second time, because it was not cleared last time.'
		);

		$this->assertEquals(
			'',
			$this->capture_wcs_admin_notice_text(),
			'The admin notice does not display, because it was cleared from the queue.'
		);
	}

	/**
	 * Equivalent to calling wcs_display_admin_notices() directly, except the function output is
	 * captured and returned in a string.
	 *
	 * @param bool $clear If the message queue should be cleared after getting/displaying the messages.
	 *
	 * @return string
	 */
	private function capture_wcs_admin_notice_text( $clear = true ) {
		ob_start();
		wcs_display_admin_notices( $clear );
		return (string) ob_get_clean();
	}
}
