<?php
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
		$this->load_textdomain();

		new WMOP_Compat();
		new WMOP_Settings();
		new WMOP_Gateway_Filter();
		new WMOP_Notices();

		if ( is_admin() ) {
			new WMOP_Admin();
		}

		// class-wmop-blocks-integration.php is only required when the interface exists (main plugin file).
		if ( class_exists( 'WMOP_Blocks_Integration' ) ) {
			new WMOP_Blocks_Integration();
		}
	}

	private function load_textdomain(): void {
		load_plugin_textdomain(
			'woo-min-order-payment',
			false,
			dirname( WMOP_BASENAME ) . '/languages'
		);
	}
}
