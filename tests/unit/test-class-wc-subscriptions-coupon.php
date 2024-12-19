<?php
/**
 * Class: WC_Subscription_Payment_Count_Test
 */
class WC_Subscriptions_Coupon_Test extends WP_UnitTestCase {

	private $cart;

	private $simple_subscription_product;
	private $variable_subscription_product;

	public function set_up() {
		parent::set_up();

		$this->cart = WC()->cart;

		// Create a simple subscription product.
		$simple = WCS_Helper_Product::create_simple_subscription_product(
			[
				'price'               => 25,
				'subscription_period' => 'month',
			]
		);
		$simple->update_meta_data( '_subscription_sign_up_fee', 10 );
		$simple->update_meta_data( '_subscription_trial_length', 0 );
		$this->simple_subscription_product = $simple;

		// Create a variable subscription product.
		$variable = WCS_Helper_Product::create_variable_subscription_product(
			[
				'subscription_period' => 'month',
			]
		);
		$variable->update_meta_data( '_subscription_sign_up_fee', 10 );
		$variable->update_meta_data( '_subscription_trial_length', 0 );
		$this->variable_subscription_product = $variable;
	}

	public function tear_down() {
		$this->cart->empty_cart();

		parent::tear_down();
	}

	/**
	 * Tests for the WC_Subscriptions_Coupon::get_discount_amount_for_cart_item method,
	 * specifically for sign-up fee and sign-up fee percent coupons.
	 */
	public function test_get_discount_amount_for_cart_item_sign_up_fee_coupons() {
		$coupon_sign_up_fee_percent = new WC_Coupon();
		$coupon_sign_up_fee_percent->set_amount( 10 );
		$coupon_sign_up_fee_percent->set_discount_type( 'sign_up_fee_percent' );

		$coupon_sign_up_fee = new WC_Coupon();
		$coupon_sign_up_fee->set_amount( 2 );
		$coupon_sign_up_fee->set_discount_type( 'sign_up_fee' );

		$coupon_sign_up_fee_large = new WC_Coupon();
		$coupon_sign_up_fee_large->set_amount( 100 );
		$coupon_sign_up_fee_large->set_discount_type( 'sign_up_fee' );

		$discount           = 0;
		$discounting_amount = 30;
		$single             = true;

		// Not a subscription switch
		$cart_item = array(
			'data'                => $this->simple_subscription_product,
			'quantity'            => 1,
			'subscription_switch' => false,
		);
		$this->cart->empty_cart();
		$this->cart->add_to_cart( $cart_item['data']->get_id() );

		$this->assertEquals(
			1,
			WC_Subscriptions_Coupon::get_discount_amount_for_cart_item(
				$cart_item,
				$discount,
				$discounting_amount,
				$single,
				$coupon_sign_up_fee_percent
			)
		);

		$this->assertEquals(
			2,
			WC_Subscriptions_Coupon::get_discount_amount_for_cart_item(
				$cart_item,
				$discount,
				$discounting_amount,
				$single,
				$coupon_sign_up_fee
			)
		);

		// Subscription switch, with extra upgrade costs. Discount should be
		// applied to the sign-up fee before other costs.
		$cart_item = array(
			'data'                => $this->variable_subscription_product,
			'quantity'            => 1,
			'subscription_switch' => [
				'subscription_id'        => 123,
				'upgraded_or_downgraded' => 'upgraded',
			],
		);
		$this->cart->empty_cart();
		$this->cart->add_to_cart( $cart_item['data']->get_id() );
		$cart_item['data']->update_meta_data( '_subscription_sign_up_fee', 30 );
		$cart_item['data']->update_meta_data( '_subscription_sign_up_fee_prorated', 10 );
		$this->assertEquals(
			1,
			WC_Subscriptions_Coupon::get_discount_amount_for_cart_item(
				$cart_item,
				$discount,
				$discounting_amount,
				$single,
				$coupon_sign_up_fee_percent
			)
		);

		$this->assertEquals(
			2,
			WC_Subscriptions_Coupon::get_discount_amount_for_cart_item(
				$cart_item,
				$discount,
				$discounting_amount,
				$single,
				$coupon_sign_up_fee
			)
		);

		$this->assertEquals(
			10, // Discount should never be more than the sign-up fee
			WC_Subscriptions_Coupon::get_discount_amount_for_cart_item(
				$cart_item,
				$discount,
				$discounting_amount,
				$single,
				$coupon_sign_up_fee_large
			)
		);

		// Subscription switch -- no sign up fee, no discount
		$cart_item['data']->update_meta_data( '_subscription_sign_up_fee_prorated', 0 );
		$this->assertEquals(
			0,
			WC_Subscriptions_Coupon::get_discount_amount_for_cart_item(
				$cart_item,
				$discount,
				$discounting_amount,
				$single,
				$coupon_sign_up_fee_percent
			)
		);

		$this->assertEquals(
			0,
			WC_Subscriptions_Coupon::get_discount_amount_for_cart_item(
				$cart_item,
				$discount,
				$discounting_amount,
				$single,
				$coupon_sign_up_fee
			)
		);

		$this->assertEquals(
			0,
			WC_Subscriptions_Coupon::get_discount_amount_for_cart_item(
				$cart_item,
				$discount,
				$discounting_amount,
				$single,
				$coupon_sign_up_fee_large
			)
		);

		// Subscription switch -- downgrade
		$cart_item['data']->update_meta_data( '_subscription_sign_up_fee', 10 );
		$cart_item['data']->update_meta_data( '_subscription_sign_up_fee_prorated', 0 );
		$cart_item['data']->update_meta_data( '_subscription_price_prorated', 0 );
		$cart_item['subscription_switch']['upgraded_or_downgraded'] = 'downgraded';
		$this->assertEquals(
			1,
			WC_Subscriptions_Coupon::get_discount_amount_for_cart_item(
				$cart_item,
				$discount,
				$discounting_amount,
				$single,
				$coupon_sign_up_fee_percent
			)
		);
	}


	/**
	 * Tests for the WC_Subscriptions_Coupon::get_discount_amount_for_cart_item method,
	 * specifically for recurring fee and recurring fee percent coupons.
	 */
	public function test_get_discount_amount_for_cart_item_recurring_fee_coupons() {
		$coupon_recurring_percent = new WC_Coupon();
		$coupon_recurring_percent->set_amount( 10 );
		$coupon_recurring_percent->set_discount_type( 'recurring_percent' );

		$coupon_recurring_fee = new WC_Coupon();
		$coupon_recurring_fee->set_amount( 5 );
		$coupon_recurring_fee->set_discount_type( 'recurring_fee' );

		$coupon_recurring_fee_large = new WC_Coupon();
		$coupon_recurring_fee_large->set_amount( 100 );
		$coupon_recurring_fee_large->set_discount_type( 'recurring_fee' );

		$discount           = 0;
		$discounting_amount = 30;
		$single             = true;

		// Not a subscription switch
		$cart_item = array(
			'data'                => $this->simple_subscription_product,
			'quantity'            => 1,
			'subscription_switch' => false,
		);
		$this->cart->empty_cart();
		$this->cart->add_to_cart( $cart_item['data']->get_id() );

		// 10% off recurring fee (20.00)
		$this->assertEquals(
			2,
			WC_Subscriptions_Coupon::get_discount_amount_for_cart_item(
				$cart_item,
				$discount,
				$discounting_amount,
				$single,
				$coupon_recurring_percent
			)
		);

		// 5.00 off recurring fee (20.00)
		$this->assertEquals(
			5,
			WC_Subscriptions_Coupon::get_discount_amount_for_cart_item(
				$cart_item,
				$discount,
				$discounting_amount,
				$single,
				$coupon_recurring_fee
			)
		);

		// 100.00 off recurring fee (20.00)
		$this->assertEquals(
			20, // Discount should never be more than the recurring fee
			WC_Subscriptions_Coupon::get_discount_amount_for_cart_item(
				$cart_item,
				$discount,
				$discounting_amount,
				$single,
				$coupon_recurring_fee_large
			)
		);

		// Subscription switch
		$cart_item = array(
			'data'                => $this->variable_subscription_product,
			'quantity'            => 1,
			'subscription_switch' => [
				'subscription_id'        => 123,
				'upgraded_or_downgraded' => 'upgraded',
			],
		);
		$this->cart->empty_cart();
		$this->cart->add_to_cart( $cart_item['data']->get_id() );
		$cart_item['data']->update_meta_data( '_subscription_sign_up_fee', 30 );
		$cart_item['data']->update_meta_data( '_subscription_sign_up_fee_prorated', 10 );
		$this->assertEquals(
			2,
			WC_Subscriptions_Coupon::get_discount_amount_for_cart_item(
				$cart_item,
				$discount,
				$discounting_amount,
				$single,
				$coupon_recurring_percent
			)
		);

		$this->assertEquals(
			5,
			WC_Subscriptions_Coupon::get_discount_amount_for_cart_item(
				$cart_item,
				$discount,
				$discounting_amount,
				$single,
				$coupon_recurring_fee
			)
		);
	}
}
