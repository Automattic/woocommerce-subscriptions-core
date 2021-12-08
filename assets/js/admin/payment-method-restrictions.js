jQuery( function( $ ) {

	/**
	 * If the WC core validation passes (errors removed), check our own validation.
	 */
	$( document.body ).on( 'wc_remove_error_tip', function( e, element, removed_error_type ) {
		var type_error = 'i18n_zero_subscription_error';

		// Exit early if it's this error that has been removed.
		if ( removed_error_type === type_error ) {
			return;
		}

		// We're only interested in product price input.
		if ( ! $( element ).is( '#_subscription_price' ) && ! $( element ).hasClass( 'wc_input_subscription_price' ) ) {
			return;
		}

		if ( 'subscription' !== $( '#product-type' ).val() && 'variable-subscription' !== $( '#product-type' ).val() ) {
			return;
		}

		// Reformat the product price - remove the decimal place separator and remove excess decimal places.
		var price = accounting.unformat( $( element ).val(), wcs_gateway_restrictions.decimal_point_separator );
		price     = accounting.formatNumber( price, wcs_gateway_restrictions.number_of_decimal_places );

		if ( 0 >= price ) {
			$( document.body ).triggerHandler( 'wc_subscriptions_add_error_tip', [ element, type_error ] );
		} else {
			$( document.body ).triggerHandler( 'wc_remove_error_tip', [ element, type_error ] );
		}
	} );

	/**
	 * Clear the price field if it is invalid.
	 */
	$( document.body ).on( 'change', '.wc_input_price[type=text]', function() {
		var product_type = $( '#product-type' ).val();

		if ( 'subscription' !== product_type && 'variable-subscription' !== product_type ) {
			return;
		}

		// Sign up fees are exempt from zero price validation.
		if ( $( this ).hasClass( 'wc_input_subscription_initial_price' ) ) {
			return;
		}

		// Reformat the product price - remove the decimal place separator and remove excess decimal places.
		var price = accounting.unformat( $( this ).val(), wcs_gateway_restrictions.decimal_point_separator );
		price     = accounting.formatNumber( price, wcs_gateway_restrictions.number_of_decimal_places );

		if ( 0 >= price ) {
			$( this ).val( '' );
		}
	} );

	/**
	 * Displays a WC error tip against an element for a given error type.
	 *
	 * Based on the WC core `wc_add_error_tip` handler callback in woocommerce_admin.js.
	 */
	$( document.body ).on( 'wc_subscriptions_add_error_tip', function( e, element, error_type ) {
		var offset = element.position();

		if ( element.parent().find( '.wc_error_tip' ).length === 0 ) {
			element.after( '<div class="wc_error_tip ' + error_type + '">' + wcs_gateway_restrictions[ error_type ] + '</div>' );
			element.parent().find( '.wc_error_tip' )
				.css( 'left', offset.left + element.width() - ( element.width() / 2 ) - ( $( '.wc_error_tip' ).width() / 2 ) )
				.css( 'top', offset.top + element.height() )
				.fadeIn( '100' );
		}
	})
} );
