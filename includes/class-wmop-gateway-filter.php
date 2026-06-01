<?php
/**
 * Gateway availability filter and checkout validation.
 *
 * @package WooMinOrderPayment
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Filters the available payment gateways at checkout and validates the chosen
 * gateway on checkout submission.
 */
final class WMOP_Gateway_Filter {

	public function __construct() {
		add_filter( 'woocommerce_available_payment_gateways', [ $this, 'filter_gateways' ], 20 );
		add_action( 'woocommerce_checkout_process', [ $this, 'validate_on_checkout' ] );
	}

	/**
	 * Hides or visually disables gateways whose minimum order amount is not met.
	 *
	 * @param WC_Payment_Gateway[] $gateways Keyed by gateway ID.
	 * @return WC_Payment_Gateway[]
	 */
	public function filter_gateways( array $gateways ): array {
		// Skip in admin (except AJAX order-review requests).
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $gateways;
		}

		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			return $gateways;
		}

		/**
		 * Allow code to bypass all minimum checks (e.g. subscription renewals).
		 *
		 * @param bool $skip  Whether to skip. Default false.
		 */
		if ( apply_filters( 'wmop_skip_minimum_checks', false ) ) {
			return $gateways;
		}

		$cart_amount  = WMOP_Helpers::get_cart_amount();
		$unavailable  = [];

		foreach ( $gateways as $id => $gateway ) {
			if ( 'yes' === $gateway->get_option( 'wmop_no_min_required', 'no' ) ) {
				continue;
			}

			$min = WMOP_Helpers::parse_min_amount( $gateway->get_option( 'wmop_min_amount', '' ) );

			if ( $min <= 0.0 ) {
				continue;
			}

			if ( $cart_amount >= $min ) {
				continue;
			}

			$notice = WMOP_Helpers::build_notice( $gateway, $min );
			$mode   = $gateway->get_option( 'wmop_hide_or_disable', 'hide' );

			$unavailable[ $id ] = [
				'id'        => $id,
				'label'     => $gateway->get_title(),
				'min'       => $min,
				'formatted' => WMOP_Helpers::format_amount( $min ),
				'notice'    => $notice,
				'mode'      => $mode,
			];

			if ( 'hide' === $mode ) {
				unset( $gateways[ $id ] );
			} else {
				// Disable mode: keep in list, append inline notice to description.
				$existing         = $gateway->description ?? '';
				$gateway->description = trim(
					( $existing ? $existing . '<br>' : '' ) .
					'<span class="wmop-disabled-notice">' . wp_kses_post( $notice ) . '</span>'
				);
			}
		}

		// Store unavailable gateway data in the WC session for the notice layer.
		if ( WC()->session ) {
			WC()->session->set( 'wmop_unavailable_gateways', $unavailable );
		}

		return $gateways;
	}

	/**
	 * Server-side validation: prevents checkout if the chosen gateway's minimum
	 * is not met (guards against frontend manipulation).
	 */
	public function validate_on_checkout(): void {
		// WooCommerce verifies its own nonce on woocommerce_checkout_process before this action fires.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$chosen = isset( $_POST['payment_method'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_method'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( '' === $chosen ) {
			return;
		}

		$gateways = WC()->payment_gateways()->payment_gateways();

		if ( ! isset( $gateways[ $chosen ] ) ) {
			return;
		}

		$gateway = $gateways[ $chosen ];

		if ( 'yes' === $gateway->get_option( 'wmop_no_min_required', 'no' ) ) {
			return;
		}

		$min = WMOP_Helpers::parse_min_amount( $gateway->get_option( 'wmop_min_amount', '' ) );

		if ( $min <= 0.0 ) {
			return;
		}

		if ( WMOP_Helpers::get_cart_amount() < $min ) {
			wc_add_notice(
				wp_kses_post( WMOP_Helpers::build_notice( $gateway, $min ) ),
				'error'
			);
		}
	}
}
