<?php
/**
 * Main plugin singleton.
 *
 * @package WooMinOrderPayment
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin singleton. Boots all sub-components in dependency order.
 */
final class WMOP_Plugin {

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		new WMOP_Compat();
		new WMOP_Gateway_Filter();
		new WMOP_Notices();

		// Admin/REST-only classes are conditionally required by the main
		// plugin file — instantiate them only when they were loaded.
		if ( class_exists( 'WMOP_Settings' ) ) {
			new WMOP_Settings();
		}

		if ( class_exists( 'WMOP_Admin' ) ) {
			new WMOP_Admin();
		}

		// class-wmop-blocks-integration.php is only required when the interface exists (main plugin file).
		if ( class_exists( 'WMOP_Blocks_Integration' ) ) {
			new WMOP_Blocks_Integration();
		}
	}

}
