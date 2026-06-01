<?php
/**
 * Plugin Name:       WooCommerce Minimum Order Per Gateway
 * Plugin URI:        https://github.com/your-repo/woo-min-order-payment
 * Description:       Set a minimum cart value per payment gateway. Gateways not meeting the threshold are hidden or shown disabled at checkout.
 * Version:           1.0.0
 * Author:            Your Name
 * Author URI:        https://yoursite.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       woo-min-order-payment
 * Domain Path:       /languages
 * Requires at least: 7.0
 * Requires PHP:      8.1
 * WC requires at least: 9.5
 * WC tested up to:   10.x
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WMOP_VERSION', '1.0.0' );
define( 'WMOP_PATH', plugin_dir_path( __FILE__ ) );
define( 'WMOP_URL', plugin_dir_url( __FILE__ ) );
define( 'WMOP_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Declare HPOS and Cart/Checkout Blocks compatibility before WooCommerce initialises.
 * Must run on before_woocommerce_init — cannot be deferred.
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p>'
						. esc_html__( 'WooCommerce Minimum Order Per Gateway requires WooCommerce to be active.', 'woo-min-order-payment' )
						. '</p></div>';
				}
			);
			return;
		}

		require_once WMOP_PATH . 'includes/class-wmop-helpers.php';
		require_once WMOP_PATH . 'includes/class-wmop-compat.php';
		require_once WMOP_PATH . 'includes/class-wmop-settings.php';
		require_once WMOP_PATH . 'includes/class-wmop-gateway-filter.php';
		require_once WMOP_PATH . 'includes/class-wmop-notices.php';
		require_once WMOP_PATH . 'includes/class-wmop-admin.php';

		// Guard: only load the Blocks integration when the interface is actually available.
		// Implementing a missing interface causes a PHP fatal at class-parse time.
		if ( interface_exists( 'Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface' ) ) {
			require_once WMOP_PATH . 'includes/class-wmop-blocks-integration.php';
		}

		require_once WMOP_PATH . 'includes/class-wmop-plugin.php';

		WMOP_Plugin::instance();
	}
);
