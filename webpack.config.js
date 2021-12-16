const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const WooCommerceDependencyExtractionWebpackPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );

const wcDepMap = {
	'@woocommerce/blocks-checkout': [ 'wc', 'blocksCheckout' ],
	'@woocommerce/price-format': [ 'wc', 'priceFormat' ],
	'@woocommerce/settings': [ 'wc', 'wcSettings' ],
};

const wcHandleMap = {
	'@woocommerce/blocks-checkout': 'wc-blocks-checkout',
	'@woocommerce/price-format': 'wc-price-format',
	'@woocommerce/settings': 'wc-settings',
};

const requestToExternal = ( request ) => {
	if ( wcDepMap[ request ] ) {
		return wcDepMap[ request ];
	}
};

const requestToHandle = ( request ) => {
	if ( wcHandleMap[ request ] ) {
		return wcHandleMap[ request ];
	}
};

module.exports = {
	...defaultConfig,
	plugins: [
		...defaultConfig.plugins.filter(
			( plugin ) =>
				'DependencyExtractionWebpackPlugin' !== plugin.constructor.name
		),
		new WooCommerceDependencyExtractionWebpackPlugin( {
			requestToExternal,
			requestToHandle,
		} ),
	],
};
