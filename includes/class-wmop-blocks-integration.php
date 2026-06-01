<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

/**
 * Registers the plugin with the WooCommerce Blocks package.
 *
 * This file is only require_once'd from the main plugin file when
 * IntegrationInterface exists — PHP resolves `implements` at class-parse
 * time, so the guard must happen before the file is loaded.
 *
 * Responsibilities:
 * - Enqueues the companion JS for the Checkout and Cart blocks.
 * - Passes hidden-gateway data (built during the PHP gateway filter) to JS
 *   via get_script_data(), so the Blocks checkout can render a notice.
 */
final class WMOP_Blocks_Integration implements IntegrationInterface {

	public function __construct() {
		add_action( 'woocommerce_blocks_loaded', [ $this, 'register' ] );
	}

	/**
	 * Registers this integration with the WC Blocks integration registry.
	 */
	public function register(): void {
		add_action(
			'woocommerce_blocks_checkout_block_registration',
			function ( $integration_registry ) {
				$integration_registry->register( $this );
			}
		);

		add_action(
			'woocommerce_blocks_cart_block_registration',
			function ( $integration_registry ) {
				$integration_registry->register( $this );
			}
		);
	}

	/** @inheritDoc */
	public function get_name(): string {
		return 'wmop-min-order';
	}

	/** @inheritDoc */
	public function get_version(): string {
		return WMOP_VERSION;
	}

	/**
	 * Called by the integration registry when the block is rendered.
	 * Registers the JS bundle and the shared stylesheet.
	 */
	public function initialize(): void {
		$script_path = WMOP_PATH . 'assets/js/wmop-checkout-blocks.js';

		// Gracefully skip registration if the JS hasn't been built yet (dev environment).
		if ( ! file_exists( $script_path ) ) {
			return;
		}

		$asset_file = WMOP_PATH . 'assets/js/wmop-checkout-blocks.asset.php';
		$asset      = file_exists( $asset_file )
			? require $asset_file
			: [ 'dependencies' => [], 'version' => WMOP_VERSION ];

		wp_register_script(
			'wmop-checkout-blocks',
			WMOP_URL . 'assets/js/wmop-checkout-blocks.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_register_style(
			'wmop-checkout',
			WMOP_URL . 'assets/css/wmop-checkout.css',
			[],
			WMOP_VERSION
		);
	}

	/** @inheritDoc */
	public function get_script_handles(): array {
		// Return empty if the script was never registered (JS not built).
		return wp_script_is( 'wmop-checkout-blocks', 'registered' ) ? [ 'wmop-checkout-blocks' ] : [];
	}

	/** @inheritDoc */
	public function get_editor_script_handles(): array {
		return [];
	}

	/**
	 * Passes hidden-gateway data to the JS bundle.
	 *
	 * Only 'hide' mode gateways are sent — 'disable' mode gateways already
	 * show their notice inline via the gateway description in PHP.
	 *
	 * @return array<string, mixed>
	 */
	public function get_script_data(): array {
		$unavailable = [];

		if ( WC()->session ) {
			$raw = WC()->session->get( 'wmop_unavailable_gateways', [] );

			if ( is_array( $raw ) ) {
				foreach ( $raw as $data ) {
					if ( ( $data['mode'] ?? 'hide' ) !== 'hide' ) {
						continue;
					}

					$unavailable[] = [
						'id'        => esc_attr( $data['id'] ?? '' ),
						'label'     => esc_html( $data['label'] ?? '' ),
						'formatted' => wp_strip_all_tags( $data['formatted'] ?? '' ),
						'notice'    => wp_kses_post( $data['notice'] ?? '' ),
					];
				}
			}
		}

		return [ 'unavailable_gateways' => $unavailable ];
	}
}
