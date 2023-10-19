jQuery( function ( $ ) {

	/**
	 * Subscription shipping select modal.
	 * @type {{init: function, add_class_to_save_button: function, fetch_modal_content: function}}
	 */
	var wc_subscription_shipping_select_modal = {
		/**
		 * Initialize actions.
		 */
		init: function () {
			this.add_class_to_save_button();
			$( '.wc_change_subscription_address' ).on( 'click', this.fetch_modal_content );
		},

		/**
		 * Move the renewal form field in the DOM to a better location.
		 */
		add_class_to_save_button: function () {
			$( ".button[name='save_address']" ).addClass( wcs_change_subscription_shipping_data.submit_button_class );
		},

		/**
		 *
		 * @param {*} e
		 */
		fetch_modal_content: function ( e ) {
			$.ajax( {
				url: wcs_change_subscription_shipping_data.ajax_url,
				type: 'POST',
				data: {
					action: wcs_change_subscription_shipping_data.ajax_action,
					address_form_data: $( 'form' ).serializeArray(),
					subscription: $( '#update_subscription_address' ).val(),
					nonce: wcs_change_subscription_shipping_data.nonce,
				},
				success: function ( results ) {
					console.log( results );
					$( '.wcs_early_renew_modal_totals_table' ).html( results.content );
				},
				error: function ( results, status, errorThrown ) {

				},
			} );
		}
	};

	wc_subscription_shipping_select_modal.init();
} );
