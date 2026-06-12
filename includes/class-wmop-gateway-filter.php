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

	/**
	 * Gateways unavailable in the current request, keyed by gateway ID.
	 * Request-local on purpose: the notice layer consumes this in the same
	 * request, so persisting it to the WC session would only add DB writes.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private static array $unavailable = [];

	/**
	 * Returns the gateways hidden/disabled by the last filter run this request.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_unavailable(): array {
		return self::$unavailable;
	}

	public function __construct() {
		add_filter( 'woocommerce_available_payment_gateways', [ $this, 'filter_gateways' ], 20 );
		add_action( 'woocommerce_checkout_process', [ $this, 'validate_on_checkout' ] );
		// Blocks checkout (Store API) never fires woocommerce_checkout_process —
		// without this hook a "disabled" (still selectable) gateway could take
		// a below-minimum payment on the block checkout. This is WC's official
		// pre-payment validation point (since 9.9): add errors to the WP_Error
		// and WC raises the checkout error response itself.
		add_action( 'woocommerce_checkout_validate_order_before_payment', [ $this, 'validate_before_payment' ], 10, 2 );
	}

	/**
	 * Whether all minimum checks should be skipped for the current request.
	 *
	 * Subscription renewal carts are skipped by default so saved-card renewal
	 * payments are never blocked by a threshold added after sign-up.
	 */
	private function should_skip_checks(): bool {
		$skip = function_exists( 'wcs_cart_contains_renewal' ) && false !== wcs_cart_contains_renewal();

		/**
		 * Allow code to bypass all minimum checks.
		 *
		 * @param bool $skip Whether to skip. Default true for subscription renewal carts, false otherwise.
		 */
		return (bool) apply_filters( 'wmop_skip_minimum_checks', $skip );
	}

	/**
	 * Returns the gateway's minimum amount, or 0.0 when the gateway is exempt
	 * (no-minimum flag set, or no/invalid threshold configured).
	 */
	private function get_effective_min( WC_Payment_Gateway $gateway ): float {
		if ( 'yes' === $gateway->get_option( 'wmop_no_min_required', 'no' ) ) {
			return 0.0;
		}

		return WMOP_Helpers::parse_min_amount( (string) $gateway->get_option( 'wmop_min_amount', '' ) );
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

		if ( $this->should_skip_checks() ) {
			return $gateways;
		}

		$cart_amount  = WMOP_Helpers::get_cart_amount();
		$unavailable  = [];

		foreach ( $gateways as $id => $gateway ) {
			$min = $this->get_effective_min( $gateway );

			if ( $min <= 0.0 || $cart_amount >= $min ) {
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
			} elseif ( ! str_contains( (string) ( $gateway->description ?? '' ), 'wmop-disabled-notice' ) ) {
				// Disable mode: keep in list, append inline notice to description.
				// Gateway objects are shared singletons and this filter runs
				// several times per request, so only append once.
				$existing             = $gateway->description ?? '';
				$gateway->description = trim(
					( $existing ? $existing . '<br>' : '' ) .
					'<span class="wmop-disabled-notice">' . wp_kses_post( $notice ) . '</span>'
				);
			}
		}

		self::$unavailable = $unavailable;

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

		if ( $this->should_skip_checks() ) {
			return;
		}

		$gateway = $gateways[ $chosen ];
		$min     = $this->get_effective_min( $gateway );

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

	/**
	 * Server-side validation for the Blocks checkout (Store API).
	 *
	 * Fires before payment is processed. Adding an error to $errors makes
	 * WooCommerce reject the checkout request with our message.
	 *
	 * @param WC_Order $order  Order about to be paid.
	 * @param WP_Error $errors Error bag WC checks after this action.
	 */
	public function validate_before_payment( WC_Order $order, WP_Error $errors ): void {
		// This hook also fires when paying for a previously placed order
		// (pay-for-order); minimums only apply to live cart checkouts.
		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}

		if ( $this->should_skip_checks() ) {
			return;
		}

		$chosen = $order->get_payment_method();

		if ( '' === $chosen ) {
			return;
		}

		$gateways = WC()->payment_gateways()->payment_gateways();

		if ( ! isset( $gateways[ $chosen ] ) ) {
			return;
		}

		$gateway = $gateways[ $chosen ];
		$min     = $this->get_effective_min( $gateway );

		if ( $min <= 0.0 || WMOP_Helpers::get_cart_amount() >= $min ) {
			return;
		}

		// Plain text: the message travels as JSON, so strip markup and
		// decode entities (wc_price outputs e.g. &pound;).
		$message = html_entity_decode(
			wp_strip_all_tags( WMOP_Helpers::build_notice( $gateway, $min ) ),
			ENT_QUOTES,
			get_bloginfo( 'charset' )
		);

		$errors->add( 'wmop_minimum_not_met', $message );
	}
}
