<?php
/**
 * Customer-facing checkout notices.
 *
 * @package WooMinOrderPayment
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Customer-facing notices for the Classic (shortcode) checkout.
 *
 * Block checkout notices are handled by the JS integration in
 * class-wmop-blocks-integration.php.
 *
 * Also overrides WooCommerce's generic "no payment methods available" text
 * when all gateways are hidden due to minimum order rules.
 */
final class WMOP_Notices {

	public function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_styles' ] );
		add_action( 'woocommerce_review_order_before_payment', [ $this, 'render_classic_notice' ] );
		add_filter( 'woocommerce_no_available_payment_methods_text', [ $this, 'no_gateways_text' ] );
	}

	/**
	 * Enqueues the plugin stylesheet on the checkout page.
	 */
	public function enqueue_styles(): void {
		if ( ! is_checkout() ) {
			return;
		}

		wp_enqueue_style(
			'wmop-checkout',
			WMOP_URL . 'assets/css/wmop-checkout.css',
			[],
			WMOP_VERSION
		);
	}

	/**
	 * Renders an info notice above the payment radio list showing which gateways
	 * were hidden and why (hidden mode only — disabled-mode gateways show inline).
	 */
	public function render_classic_notice(): void {
		if ( ! WC()->session ) {
			return;
		}

		$unavailable = WC()->session->get( 'wmop_unavailable_gateways', [] );

		if ( empty( $unavailable ) ) {
			return;
		}

		$hidden = array_filter(
			$unavailable,
			static fn( array $data ): bool => ( $data['mode'] ?? 'hide' ) === 'hide'
		);

		if ( empty( $hidden ) ) {
			return;
		}

		echo '<div class="woocommerce-info wmop-min-order-notice" role="alert">';
		echo '<p>' . esc_html__( 'Some payment methods require a higher cart total:', 'woo-min-order-payment' ) . '</p>';
		echo '<ul>';

		foreach ( $hidden as $data ) {
			echo '<li>' . wp_kses_post( $data['notice'] ) . '</li>';
		}

		echo '</ul>';
		echo '</div>';
	}

	/**
	 * Replaces WooCommerce's generic "no payment methods" message with a
	 * targeted notice showing how much more the customer needs to add.
	 *
	 * @param  string $message Original WC message.
	 * @return string
	 */
	public function no_gateways_text( string $message ): string {
		if ( ! WC()->session ) {
			return $message;
		}

		$unavailable = WC()->session->get( 'wmop_unavailable_gateways', [] );

		if ( empty( $unavailable ) ) {
			return $message;
		}

		$cart_amount = WMOP_Helpers::get_cart_amount();
		$minimums    = array_column( $unavailable, 'min' );

		if ( empty( $minimums ) ) {
			return $message;
		}

		// Tell the customer the least they need to add (the smallest configured threshold).
		$smallest_min    = (float) min( $minimums );
		$amount_to_add   = max( 0.0, $smallest_min - $cart_amount );
		$formatted_add   = WMOP_Helpers::format_amount( $amount_to_add );
		$formatted_min   = WMOP_Helpers::format_amount( $smallest_min );

		return wp_kses_post(
			sprintf(
				/* translators: 1: amount needed to add, 2: minimum order threshold */
				__( 'No payment methods are available. Add %1$s more to your cart to reach the %2$s minimum order required to check out.', 'woo-min-order-payment' ),
				$formatted_add,
				$formatted_min
			)
		);
	}
}
