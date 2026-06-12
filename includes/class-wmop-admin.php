<?php
/**
 * Admin summary section inside WooCommerce Settings > Payments.
 *
 * @package WooMinOrderPayment
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds a "Minimum Order Rules" sub-section to WooCommerce > Settings > Payments.
 *
 * Read-only summary of every gateway's WMOP configuration. Editing is done
 * directly on each gateway's own settings screen.
 */
final class WMOP_Admin {

	public function __construct() {
		add_filter( 'woocommerce_get_sections_checkout', [ $this, 'add_section' ] );
		add_filter( 'woocommerce_get_settings_checkout', [ $this, 'get_settings' ], 10, 2 );
		add_action( 'woocommerce_admin_field_wmop_summary_table', [ $this, 'render_summary_table' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_styles' ] );
	}

	/**
	 * Enqueues plugin styles on our summary section only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_styles( string $hook ): void {
		if ( 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}

		// Read-only routing check on a screen WC already capability-gates.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : '';

		if ( 'wmop_rules' !== $section ) {
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
	 * Adds the section link inside the Payments settings tab nav.
	 */
	public function add_section( array $sections ): array {
		$sections['wmop_rules'] = __( 'Minimum Order Rules', 'woo-min-order-payment' );
		return $sections;
	}

	/**
	 * Returns field definitions for our custom section.
	 */
	public function get_settings( array $settings, string $current_section ): array {
		if ( 'wmop_rules' !== $current_section ) {
			return $settings;
		}

		return [
			[
				'title' => __( 'Minimum Order Rules', 'woo-min-order-payment' ),
				'type'  => 'title',
				'desc'  => __( "Overview of per-gateway minimum order thresholds. Edit each gateway's threshold on its own settings screen.", 'woo-min-order-payment' ),
				'id'    => 'wmop_rules_section_title',
			],
			[
				'type' => 'wmop_summary_table',
				'id'   => 'wmop_summary_table',
			],
			[
				'type' => 'sectionend',
				'id'   => 'wmop_rules_section_title',
			],
		];
	}

	/**
	 * Renders the custom wmop_summary_table field type.
	 */
	public function render_summary_table(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$all_gateways = WC()->payment_gateways()->payment_gateways();

		if ( empty( $all_gateways ) ) {
			echo '<p>' . esc_html__( 'No payment gateways are registered.', 'woo-min-order-payment' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped wmop-summary-table">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Gateway', 'woo-min-order-payment' ) . '</th>';
		echo '<th>' . esc_html__( 'Enabled', 'woo-min-order-payment' ) . '</th>';
		echo '<th>' . esc_html__( 'Min Required', 'woo-min-order-payment' ) . '</th>';
		// Currency code is output-escaped at the point of output — no intermediate variable needed.
		echo '<th>' . esc_html__( 'Threshold', 'woo-min-order-payment' ) . ' (' . esc_html( get_woocommerce_currency() ) . ')</th>';
		echo '<th>' . esc_html__( 'On Fail', 'woo-min-order-payment' ) . '</th>';
		echo '<th>' . esc_html__( 'Settings', 'woo-min-order-payment' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $all_gateways as $gateway ) {
			$is_enabled   = 'yes' === $gateway->get_option( 'enabled', 'no' );
			$no_min       = 'yes' === $gateway->get_option( 'wmop_no_min_required', 'no' );
			$min          = WMOP_Helpers::parse_min_amount( (string) $gateway->get_option( 'wmop_min_amount', '' ) );
			$mode         = $gateway->get_option( 'wmop_hide_or_disable', 'hide' );
			$settings_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . rawurlencode( $gateway->id ) );

			echo '<tr>';

			echo '<td><strong>' . esc_html( $gateway->get_title() ) . '</strong></td>';

			echo '<td>';
			if ( $is_enabled ) {
				echo '<span class="dashicons dashicons-yes-alt" style="color:#46b450;" aria-label="' . esc_attr__( 'Enabled', 'woo-min-order-payment' ) . '"></span>';
			} else {
				echo '<span class="dashicons dashicons-dismiss" style="color:#dc3232;" aria-label="' . esc_attr__( 'Disabled', 'woo-min-order-payment' ) . '"></span>';
			}
			echo '</td>';

			echo '<td>';
			if ( $no_min ) {
				echo '<em>' . esc_html__( 'No minimum', 'woo-min-order-payment' ) . '</em>';
			} elseif ( $min > 0.0 ) {
				echo '<span class="dashicons dashicons-yes" style="color:#46b450;" aria-label="' . esc_attr__( 'Minimum enforced', 'woo-min-order-payment' ) . '"></span>';
			} else {
				echo '<em>' . esc_html__( 'Not set', 'woo-min-order-payment' ) . '</em>';
			}
			echo '</td>';

			echo '<td>';
			if ( $no_min ) {
				echo '&mdash;';
			} elseif ( $min > 0.0 ) {
				echo esc_html( wc_format_localized_price( number_format( $min, wc_get_price_decimals(), '.', '' ) ) );
			} else {
				echo '<em>' . esc_html__( 'Not set', 'woo-min-order-payment' ) . '</em>';
			}
			echo '</td>';

			echo '<td>';
			if ( $no_min || $min <= 0.0 ) {
				echo '&mdash;';
			} elseif ( 'disable' === $mode ) {
				echo esc_html__( 'Show disabled', 'woo-min-order-payment' );
			} else {
				echo esc_html__( 'Hide', 'woo-min-order-payment' );
			}
			echo '</td>';

			echo '<td><a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Edit', 'woo-min-order-payment' ) . '</a></td>';

			echo '</tr>';
		}

		echo '</tbody></table>';
	}
}
