<?php
/**
 * Class WC_Subscriptions_Admin_Test
 *
 * @package WooCommerce\SubscriptionsCore\Tests
 */
class WC_Subscriptions_Admin_Test extends WP_UnitTestCase {
	/**
	 * @inheritDoc
	 */
	public function tear_down() {
		parent::tear_down();

		unset( $GLOBALS['current_screen'] );
		wcs_hpos_update( true );
	}
	/**
	 * Test for `maybe_attach_gettext_callback` method.
	 *
	 * @param bool        $is_admin     Whether the user is an admin or not.
	 * @param string      $screen_id    Screen ID.
	 * @param bool        $hpos_enabled Whether HPOS is enabled or not.
	 * @param int|boolean $expected     Expected result.
	 * @return void
	 * @dataProvider provide_test_maybe_attach_and_unattach_gettext_callback
	 */
	public function test_maybe_attach_and_unattach_gettext_callback( $is_admin, $screen_id, $hpos_enabled, $expected ) {
		if ( $is_admin ) {
			$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
			wp_set_current_user( $user_id );
		}

		set_current_screen( $screen_id );

		wcs_hpos_update( $hpos_enabled );

		$admin = new WC_Subscriptions_Admin();

		$admin->maybe_attach_gettext_callback();
		$this->assertSame( $expected, has_filter( 'gettext', [ WC_Subscriptions_Admin::class, 'change_order_item_editable_text' ] ) );

		$admin->maybe_unattach_gettext_callback();
		$this->assertSame( false, has_filter( 'gettext', [ WC_Subscriptions_Admin::class, 'change_order_item_editable_text' ] ) );
	}

	/**
	 * Generic data provider for `test_maybe_attach_gettext_callback` values.
	 *
	 * @return array
	 */
	public function provide_test_maybe_attach_and_unattach_gettext_callback() {
		return array(
			'not an admin'                               => array(
				'is admin'     => false,
				'screen id'    => '',
				'hpos enabled' => false,
				'expected'     => false,
			),
			'invalid screen'                             => array(
				'is admin'     => true,
				'screen id'    => '',
				'hpos enabled' => false,
				'expected'     => false,
			),
			'hpos enabled, edit subscriptions page'      => array(
				'is admin'     => true,
				'screen id'    => 'woocommerce_page_wc-orders--shop_subscription',
				'hpos enabled' => true,
				'expected'     => 10,
			),
			'hpos enabled, not edit subscriptions page'  => array(
				'is admin'     => true,
				'screen id'    => '',
				'hpos enabled' => true,
				'expected'     => false,
			),
			'hpos disabled, edit subscriptions page'     => array(
				'is admin'     => true,
				'screen id'    => 'shop_subscription',
				'hpos enabled' => false,
				'expected'     => 10,
			),
			'hpos disabled, not edit subscriptions page' => array(
				'is admin'     => true,
				'screen id'    => '',
				'hpos enabled' => false,
				'expected'     => false,
			),
		);
	}
}
