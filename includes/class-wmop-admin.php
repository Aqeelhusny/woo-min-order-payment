<?php
/**
 * Central rules editor inside WooCommerce Settings > Payments.
 *
 * @package WooMinOrderPayment
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds a "Minimum Order Rules" section to WooCommerce > Settings > Payments
 * where every gateway's minimum-order configuration can be edited in one
 * table. Values are stored in each gateway's own settings option — the same
 * keys the per-gateway settings screen writes — so both UIs stay in sync.
 *
 * Saving rides WooCommerce's settings flow: WC verifies the
 * woocommerce-settings nonce and the manage_woocommerce capability before
 * firing the update hook; both are re-checked here for defence in depth.
 */
final class WMOP_Admin {

	public function __construct() {
		add_filter( 'woocommerce_get_sections_checkout', [ $this, 'add_section' ] );
		add_filter( 'woocommerce_get_settings_checkout', [ $this, 'get_settings' ], 10, 2 );
		add_action( 'woocommerce_admin_field_wmop_rules_table', [ $this, 'render_rules_table' ] );
		add_action( 'woocommerce_update_options_checkout_wmop_rules', [ $this, 'save_rules' ] );
		// Older WC fires only the unsectioned hook; save_rules guards on section.
		add_action( 'woocommerce_update_options_checkout', [ $this, 'save_rules' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_styles' ] );
	}

	/**
	 * Enqueues plugin styles on our rules section only.
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
				'desc'  => __( 'Set a minimum cart subtotal (excl. tax and shipping) per payment gateway. The same settings are also available on each gateway\'s own screen.', 'woo-min-order-payment' ),
				'id'    => 'wmop_rules_section_title',
			],
			[
				'type' => 'wmop_rules_table',
				'id'   => 'wmop_rules_table',
			],
			[
				'type' => 'sectionend',
				'id'   => 'wmop_rules_section_title',
			],
		];
	}

	/**
	 * Renders the editable rules table (custom wmop_rules_table field type).
	 *
	 * Wrapped in a tr/td because WC renders custom field types inside the
	 * surrounding form-table.
	 */
	public function render_rules_table(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$all_gateways = WC()->payment_gateways()->payment_gateways();

		echo '<tr><td colspan="2" class="wmop-rules-cell">';

		if ( empty( $all_gateways ) ) {
			echo '<p>' . esc_html__( 'No payment gateways are registered.', 'woo-min-order-payment' ) . '</p></td></tr>';
			return;
		}

		echo '<table class="widefat striped wmop-rules-table">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Gateway', 'woo-min-order-payment' ) . '</th>';
		echo '<th>' . esc_html__( 'Enabled', 'woo-min-order-payment' ) . '</th>';
		echo '<th>' . esc_html__( 'No minimum', 'woo-min-order-payment' ) . '</th>';
		echo '<th>' . esc_html__( 'Minimum amount', 'woo-min-order-payment' ) . ' (' . esc_html( get_woocommerce_currency() ) . ')</th>';
		echo '<th>' . esc_html__( 'When minimum not met', 'woo-min-order-payment' ) . '</th>';
		echo '<th>' . esc_html__( 'Customer notice', 'woo-min-order-payment' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $all_gateways as $gateway ) {
			$id         = $gateway->id;
			$is_enabled = 'yes' === $gateway->get_option( 'enabled', 'no' );
			$no_min     = 'yes' === $gateway->get_option( 'wmop_no_min_required', 'no' );
			$min        = (string) $gateway->get_option( 'wmop_min_amount', '' );
			$mode       = $gateway->get_option( 'wmop_hide_or_disable', 'hide' );
			$notice     = (string) $gateway->get_option( 'wmop_notice_text', '' );
			$field      = static fn( string $key ): string => 'wmop_rules[' . esc_attr( $id ) . '][' . $key . ']';
			$gw_url     = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . rawurlencode( $id ) );

			echo '<tr>';

			echo '<td class="wmop-col-gateway"><strong><a href="' . esc_url( $gw_url ) . '">' . esc_html( $gateway->get_title() ) . '</a></strong></td>';

			echo '<td>';
			if ( $is_enabled ) {
				echo '<span class="dashicons dashicons-yes-alt" style="color:#46b450;" aria-label="' . esc_attr__( 'Enabled', 'woo-min-order-payment' ) . '"></span>';
			} else {
				echo '<span class="dashicons dashicons-dismiss" style="color:#dc3232;" aria-label="' . esc_attr__( 'Disabled', 'woo-min-order-payment' ) . '"></span>';
			}
			echo '</td>';

			/* translators: %s: payment gateway title. */
			$no_min_label = sprintf( __( 'No minimum required for %s', 'woo-min-order-payment' ), $gateway->get_title() );
			echo '<td><input type="checkbox" name="' . $field( 'no_min' ) . '" value="1" ' . checked( $no_min, true, false ) . ' aria-label="' . esc_attr( $no_min_label ) . '"></td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $field() esc_attr()s the gateway ID.

			/* translators: %s: payment gateway title. */
			$min_label = sprintf( __( 'Minimum order amount for %s', 'woo-min-order-payment' ), $gateway->get_title() );
			echo '<td><input type="number" step="0.01" min="0" name="' . $field( 'min_amount' ) . '" value="' . esc_attr( $min ) . '" class="wmop-input-amount" placeholder="' . esc_attr__( 'No minimum', 'woo-min-order-payment' ) . '" aria-label="' . esc_attr( $min_label ) . '"></td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			/* translators: %s: payment gateway title. */
			$mode_label = sprintf( __( 'Behaviour when minimum not met for %s', 'woo-min-order-payment' ), $gateway->get_title() );
			echo '<td><select name="' . $field( 'mode' ) . '" aria-label="' . esc_attr( $mode_label ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<option value="hide" ' . selected( $mode, 'hide', false ) . '>' . esc_html__( 'Hide gateway', 'woo-min-order-payment' ) . '</option>';
			echo '<option value="disable" ' . selected( $mode, 'disable', false ) . '>' . esc_html__( 'Show disabled with notice', 'woo-min-order-payment' ) . '</option>';
			echo '</select></td>';

			/* translators: %s: payment gateway title. */
			$notice_label = sprintf( __( 'Customer notice for %s', 'woo-min-order-payment' ), $gateway->get_title() );
			echo '<td><input type="text" name="' . $field( 'notice' ) . '" value="' . esc_attr( $notice ) . '" class="wmop-input-notice" placeholder="' . esc_attr__( 'A minimum order of {min} is required to use {gateway}.', 'woo-min-order-payment' ) . '" aria-label="' . esc_attr( $notice_label ) . '"></td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '<p class="description">' . esc_html__( 'Notice placeholders: {min} = formatted minimum, {currency} = currency code, {gateway} = gateway name. Leave the notice blank to use the default message.', 'woo-min-order-payment' ) . '</p>';
		echo '</td></tr>';
	}

	/**
	 * Persists the rules table into each gateway's settings option.
	 *
	 * Fires on WooCommerce's settings-save hook, after WC has verified the
	 * woocommerce-settings nonce. One option write per gateway, and only
	 * when something actually changed.
	 */
	public function save_rules(): void {
		global $current_section;

		if ( 'wmop_rules' !== $current_section ) {
			return;
		}

		// Both update hooks can fire for the same request on some WC versions.
		static $saved = false;
		if ( $saved ) {
			return;
		}
		$saved = true;

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		check_admin_referer( 'woocommerce-settings' );

		$raw = isset( $_POST['wmop_rules'] ) && is_array( $_POST['wmop_rules'] )
			? wp_unslash( $_POST['wmop_rules'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field is sanitized below.
			: [];

		// Iterate registered gateways, never POST keys, so unknown IDs are ignored.
		foreach ( WC()->payment_gateways()->payment_gateways() as $gateway ) {
			$row = isset( $raw[ $gateway->id ] ) && is_array( $raw[ $gateway->id ] ) ? $raw[ $gateway->id ] : [];

			$min = isset( $row['min_amount'] ) ? trim( sanitize_text_field( $row['min_amount'] ) ) : '';
			if ( '' !== $min ) {
				$min = wc_format_decimal( $min );
				if ( '' === $min || (float) $min < 0 ) {
					$min = '';
				}
			}

			$mode = isset( $row['mode'] ) && in_array( $row['mode'], [ 'hide', 'disable' ], true )
				? $row['mode']
				: 'hide';

			$gateway->init_settings();
			$settings = $gateway->settings;

			$updated = array_merge(
				$settings,
				[
					'wmop_no_min_required' => empty( $row['no_min'] ) ? 'no' : 'yes',
					'wmop_min_amount'      => (string) $min,
					'wmop_hide_or_disable' => $mode,
					'wmop_notice_text'     => isset( $row['notice'] ) ? trim( wp_kses_post( $row['notice'] ) ) : '',
				]
			);

			if ( $updated !== $settings ) {
				update_option( $gateway->get_option_key(), $updated );
				$gateway->settings = $updated; // Keep the in-memory copy fresh for the re-render.
			}
		}
	}
}
