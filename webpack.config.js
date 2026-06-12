/**
 * Extends the default @wordpress/scripts webpack config with WooCommerce's
 * dependency-extraction plugin.
 *
 * @woocommerce/* packages are NOT installed from npm — they are bundled with
 * WooCommerce and exposed as window.wc.* globals at runtime. The WC
 * dependency-extraction plugin maps the imports to those globals AND records
 * the matching script handles (wc-settings, wc-blocks-checkout, …) in the
 * generated *.asset.php so WordPress loads them first.
 */

const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const WooCommerceDependencyExtractionWebpackPlugin = require( '@woocommerce/dependency-extraction-webpack-plugin' );

module.exports = {
	...defaultConfig,
	plugins: [
		...defaultConfig.plugins.filter(
			( plugin ) => plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
		),
		new WooCommerceDependencyExtractionWebpackPlugin(),
	],
};
