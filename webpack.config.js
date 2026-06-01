/**
 * Extends the default @wordpress/scripts webpack config to add WooCommerce
 * Blocks packages as externals.
 *
 * @woocommerce/* packages are NOT installed from npm — they are bundled with
 * WooCommerce and registered as WordPress script handles at runtime. We map
 * them to their global window.wc.* equivalents so webpack resolves the
 * imports at build time without bundling the code.
 */

const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	externals: {
		...defaultConfig.externals,
		'@woocommerce/blocks-checkout': [ 'wc', 'blocksCheckout' ],
		'@woocommerce/settings':        [ 'wc', 'wcSettings' ],
		'@woocommerce/block-data':      [ 'wc', 'blockData' ],
		'@woocommerce/blocks-registry': [ 'wc', 'blocksRegistry' ],
	},
};
