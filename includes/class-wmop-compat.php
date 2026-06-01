<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compatibility shims: HPOS, Cart/Checkout Blocks, Dokan.
 *
 * HPOS and Blocks declarations are handled early on before_woocommerce_init
 * in the main plugin file. This class covers runtime checks and Dokan stubs.
 */
final class WMOP_Compat {

	public function __construct() {
		// Expose a filter stub so a future Dokan v2 add-on can override per-vendor minimums.
		add_filter( 'wmop_cart_amount_for_comparison', [ $this, 'maybe_apply_vendor_override' ], 20, 2 );
	}

	/**
	 * Stub for per-vendor minimum overrides (Dokan v2).
	 *
	 * Third-party code (or a future add-on) can hook wmop_vendor_min_amount
	 * to return a vendor-specific amount for the current cart.
	 *
	 * @param float   $amount  Resolved cart amount.
	 * @param WC_Cart $cart    Current cart.
	 * @return float
	 */
	public function maybe_apply_vendor_override( float $amount, WC_Cart $cart ): float {
		if ( ! function_exists( 'dokan' ) ) {
			return $amount;
		}

		/**
		 * Allow Dokan-aware code to return a different comparison amount.
		 *
		 * @param float   $amount  Default cart subtotal.
		 * @param WC_Cart $cart
		 */
		return (float) apply_filters( 'wmop_vendor_cart_amount', $amount, $cart );
	}

	/**
	 * Returns true if the WooCommerce Blocks package is available.
	 */
	public static function has_blocks(): bool {
		return class_exists( 'Automattic\WooCommerce\Blocks\Package' );
	}
}
