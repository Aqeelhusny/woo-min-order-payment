<?php
/**
 * Per-gateway settings injection.
 *
 * @package WooMinOrderPayment
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Injects WMOP settings fields into every registered payment gateway's form.
 *
 * Uses the per-gateway woocommerce_settings_api_form_fields_{$id} filter so
 * settings appear directly on each gateway's own settings screen and are
 * saved through WooCommerce's existing nonce-protected settings flow â€”
 * no custom save handler or extra DB table needed.
 */
final class WMOP_Settings {

	public function __construct() {
		// woocommerce_init fires on WordPress 'init', after plugins_loaded has completed
		// and WC_Payment_Gateways::init() (plugins_loaded priority 99) has run.
		// This is the earliest safe point to enumerate all registered gateways.
		add_action( 'woocommerce_init', [ $this, 'register_form_field_filters' ] );
	}

	/**
	 * Walks every registered gateway and registers a form_fields filter for each.
	 */
	public function register_form_field_filters(): void {
		$gateways = WC()->payment_gateways()->payment_gateways();

		foreach ( $gateways as $gateway ) {
			add_filter(
				'woocommerce_settings_api_form_fields_' . $gateway->id,
				[ $this, 'inject_fields' ]
			);
		}
	}

	/**
	 * Appends WMOP fields to a gateway's form_fields array.
	 *
	 * @param  array $fields Existing gateway form fields.
	 * @return array
	 */
	public function inject_fields( array $fields ): array {
		$fields['wmop_section_title'] = [
			'title' => __( 'Minimum Order Value', 'aqeelhusny-min-order-gateway' ),
			'type'  => 'title',
			'desc'  => __( 'Restrict this payment method based on the cart subtotal (excl. tax and shipping).', 'aqeelhusny-min-order-gateway' ),
		];

		$fields['wmop_no_min_required'] = [
			'title'   => __( 'No minimum required', 'aqeelhusny-min-order-gateway' ),
			'type'    => 'checkbox',
			'label'   => __( 'Always allow this gateway regardless of cart value', 'aqeelhusny-min-order-gateway' ),
			'default' => 'no',
		];

		$fields['wmop_min_amount'] = [
			'title'             => __( 'Minimum order amount', 'aqeelhusny-min-order-gateway' ),
			'type'              => 'number',
			'description'       => sprintf(
				/* translators: %s: store currency code, e.g. USD */
				__( 'Amount in %s. Leave blank or 0 to disable.', 'aqeelhusny-min-order-gateway' ),
				get_woocommerce_currency()
			),
			'default'           => '',
			'desc_tip'          => true,
			'custom_attributes' => [
				'step' => '0.01',
				'min'  => '0',
			],
		];

		$fields['wmop_hide_or_disable'] = [
			'title'   => __( 'When minimum not met', 'aqeelhusny-min-order-gateway' ),
			'type'    => 'select',
			'options' => [
				'hide'    => __( 'Hide this gateway completely', 'aqeelhusny-min-order-gateway' ),
				'disable' => __( 'Show disabled with a notice', 'aqeelhusny-min-order-gateway' ),
			],
			'default' => 'hide',
		];

		$fields['wmop_notice_text'] = [
			'title'       => __( 'Customer notice', 'aqeelhusny-min-order-gateway' ),
			'type'        => 'textarea',
			'description' => __( 'Placeholders: {min} = formatted minimum, {currency} = currency code, {gateway} = gateway name.', 'aqeelhusny-min-order-gateway' ),
			'default'     => __( 'A minimum order of {min} is required to use {gateway}.', 'aqeelhusny-min-order-gateway' ),
			'desc_tip'    => true,
		];

		return $fields;
	}
}
