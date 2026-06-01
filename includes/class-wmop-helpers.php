<?php
/**
 * Shared utility helpers.
 *
 * @package WooMinOrderPayment
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stateless utility methods shared across the plugin.
 */
final class WMOP_Helpers {

	/**
	 * Returns the cart amount used for gateway threshold comparisons.
	 *
	 * Defaults to the cart subtotal (excl. tax and shipping).
	 * Override via the wmop_cart_amount_for_comparison filter.
	 */
	public static function get_cart_amount(): float {
		if ( ! WC()->cart ) {
			return 0.0;
		}

		$amount = (float) WC()->cart->get_subtotal();

		/**
		 * Filter the cart amount compared against each gateway's minimum.
		 *
		 * @param float    $amount   Cart subtotal excl. tax/shipping.
		 * @param WC_Cart  $cart     The current cart object.
		 */
		return (float) apply_filters( 'wmop_cart_amount_for_comparison', $amount, WC()->cart );
	}

	/**
	 * Returns an HTML-formatted price string, safe for direct output.
	 */
	public static function format_amount( float $amount ): string {
		return wp_kses_post( wc_price( $amount ) );
	}

	/**
	 * Parses a raw gateway option value into a positive float, or 0.0 if
	 * the value is empty / invalid / not positive.
	 */
	public static function parse_min_amount( string $raw ): float {
		if ( '' === trim( $raw ) ) {
			return 0.0;
		}

		$formatted = wc_format_decimal( $raw );

		if ( false === $formatted ) {
			return 0.0;
		}

		$value = (float) $formatted;
		return $value > 0.0 ? $value : 0.0;
	}

	/**
	 * Builds the customer-facing notice string for a gateway, substituting
	 * {min} and {currency} placeholders.
	 */
	public static function build_notice( WC_Payment_Gateway $gateway, float $min ): string {
		$template = $gateway->get_option( 'wmop_notice_text', '' );

		if ( '' === trim( $template ) ) {
			/* translators: %1$s gateway title, %2$s formatted minimum amount */
			$template = __( 'A minimum order of {min} is required to use {gateway}.', 'woo-min-order-payment' );
		}

		return str_replace(
			[ '{min}', '{currency}', '{gateway}' ],
			[ self::format_amount( $min ), esc_html( get_woocommerce_currency() ), esc_html( $gateway->get_title() ) ],
			$template
		);
	}
}
