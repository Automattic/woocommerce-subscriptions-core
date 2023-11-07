jQuery( function ( $ ) {

	/**
	 * Subscription change shipping via Checkout.
	 * @type {{init: function, replace_strings: function, replace_order_button: function, submit_change_address: function, trigger_event_on_loading_complete: function, loading_complete_event: string, change_address_button_submit: string}}
	 */
	var wc_subscription_change_address_checkout = {

		/**
		 * A custom JS event that is triggered when the Block Checkout is fully loaded.
		 */
		loading_complete_event: 'wcs_change_address_checkout_loading_complete',

		/**
		 * The class name of the button that will submit the change address form.
		 */
		change_address_button_submit: 'wcs-change-address-button',

		/**
		 * Initialize actions.
		 */
		init: function () {
			$( document ).on( this.loading_complete_event, this.replace_strings );
			$( document ).on( this.loading_complete_event, this.replace_order_button );
			$( document ).on( 'click', '.' + this.change_address_button_submit, this.submit_change_address );
			$( document ).on( 'click', '.wc-block-components-checkout-place-order-button', this.submit_change_address );

			this.trigger_event_on_loading_complete();
			this.track_place_order_button_status();
		},

		/**
		 * Replace strings on the checkout page to more correctly reflect the subscription change shipping process.
		 */
		replace_strings: function () {
			// Block Checkout.
			$( '.wc-block-checkout__contact-fields .wc-block-components-checkout-step__description' ).html( wcs_change_subscription_shipping_data.strings.contact_description );
			$( '.wc-block-checkout__shipping-fields .wc-block-components-checkout-step__description' ).html( wcs_change_subscription_shipping_data.strings.shipping_address_description );
			$( '.wc-block-components-order-summary__button-text' ).html( wcs_change_subscription_shipping_data.strings.order_summary );

			// Shortcode Checkout.
			$( '#order_review_heading' ).html( wcs_change_subscription_shipping_data.strings.order_summary );
		},

		/**
		 * Replace the default Place Order button with a button that will process an address change instead of submit the checkout for payment.
		 */
		replace_order_button: function () {
			var order_button = $( '.wc-block-components-checkout-place-order-button' );

			// The submit change address button is a clone of the order button.
			var submit_button = order_button.clone();

			// Remove the default place order button.
			order_button.hide();

			// Update the change address button.
			submit_button.find( 'span' ).text( wcs_change_subscription_shipping_data.strings.change_address_submit );
			submit_button.addClass( wc_subscription_change_address_checkout.change_address_button_submit );
			submit_button.addClass( 'wc-block-components-button--loading' );

			// Looking into adding loading graphic to button when it's clicked. May need to use a react component.
			//submit_button.prop( 'disabled', true );
			//submit_button.html( '<span className="wc-block-components-spinner" aria-hidden="true" />' );

			// Insert the Change Address button into the DOM.
			submit_button.appendTo( '.wc-block-checkout__actions_row' );
		},

		/**
		 *
		 * @param {*} e
		 */
		submit_change_address: function ( e ) {
			if ( wc_subscription_change_address_checkout.is_place_order_button_disabled() ) {
				return;
			}

			e.preventDefault();

			console.log('process address change');
		},

		/**
		 * Trigger an event when the checkout page is fully loaded.
		 *
		 * @param {*} attempt
		 */
		trigger_event_on_loading_complete: function ( attempt = 0 ) {
			if ( attempt > 10000 ) {
				return;
			}

			var block_checkout     = '.wp-block-woocommerce-checkout-order-summary-subtotal-block';
			var shortcode_checkout = '#order_review_heading';

			if ( $( block_checkout ).length || $( shortcode_checkout ).length ) {
				$( document ).trigger( wc_subscription_change_address_checkout.loading_complete_event );
			} else {
				setTimeout( function () {
					wc_subscription_change_address_checkout.trigger_event_on_loading_complete( attempt + 1 );
				}, 50 );
			}
		},

		/**
		 *
		 */
		track_place_order_button_status: function () {
			var change_address_button = $( '.' + wc_subscription_change_address_checkout.change_address_button_submit );

			// If the default place order button is disabled, disable our change address button.
			if ( wc_subscription_change_address_checkout.is_place_order_button_disabled() ) {
				change_address_button.prop( 'disabled', true );
			} else {
				change_address_button.prop( 'disabled', false );
			}

			setTimeout( function () {
				wc_subscription_change_address_checkout.track_place_order_button_status();
			}, 200 );
		},

		/**
		 *
		 * @returns {boolean|*}
		 */
		is_place_order_button_disabled: function () {
			var submit_buttons     = $( '.wc-block-components-checkout-place-order-button' );
			var place_order_button = false;

			// Find the place order button.
			submit_buttons.each( function() {
				if ( ! $( this ).hasClass( wc_subscription_change_address_checkout.change_address_button_submit ) ) {
					place_order_button = this;
					return;
				}
			} );

			return place_order_button && $( place_order_button ).is( ':disabled' );
		},
	};

	wc_subscription_change_address_checkout.init();
} );