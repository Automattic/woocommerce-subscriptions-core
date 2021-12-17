jQuery( function( $ ) {

	/**
	 * If the WC core validation passes (errors removed), check our own validation.
	 */
	$( document.body ).on( 'wc_remove_error_tip', function( e, element, removed_error_type ) {
		var type_error = 'i18n_zero_subscription_error';

		// Exit early if it's the zero subscription error that has been removed.
		if ( removed_error_type === type_error ) {
			return;
		}

		var product_type = $( '#product-type' ).val();

		if ( 'subscription' !== product_type && 'variable-subscription' !== product_type ) {
			return;
		}

		// We're only interested in the product's recurring price and sale price input.
		if ( 'subscription' === product_type && ! $( element ).is( '#_subscription_price' ) && ! $( element ).is( '#_sale_price' ) ) {
			return;
		}

		if ( 'variable-subscription' === product_type && ! $( element ).hasClass( 'wc_input_subscription_price' ) && ! $( element ).is( '.wc_input_price[name^=variable_sale_price]' ) ) {
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
	 * Validate the recurring price or sale price field on element change event or when a validate event is triggered.
	 */
	$( document.body ).on( 'change wc_subscriptions_validate_zero_recurring_price', '#_subscription_price, #_sale_price, .wc_input_subscription_price, .wc_input_price[name^=variable_sale_price]', function() {
		var product_type = $( '#product-type' ).val();

		if ( 'subscription' !== product_type && 'variable-subscription' !== product_type ) {
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
	 * When the product type is changed to a subscription product type, validate generic product sale price elements.
	 */
	$( document.body ).on( 'change', '#product-type', function() {
		var product_type = $( '#product-type' ).val();

		if ( 'subscription' !== product_type && 'variable-subscription' !== product_type ) {
			return;
		}

		$( '#_sale_price, .wc_input_price[name^=variable_sale_price]' ).each( function() {
			$( this ).trigger( 'wc_subscriptions_validate_zero_recurring_price' );
		});
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
