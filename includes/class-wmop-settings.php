<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Injects WMOP settings fields into every registered payment gateway's form.
 *
 * Uses the per-gateway woocommerce_settings_api_form_fields_{$id} filter so
 * settings appear directly on each gateway's own settings screen and are
 * saved through WooCommerce's existing nonce-protected settings flow —
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
			'title' => __( 'Minimum Order Value', 'woo-min-order-payment' ),
			'type'  => 'title',
			'desc'  => __( 'Restrict this payment method based on the cart subtotal (excl. tax and shipping).', 'woo-min-order-payment' ),
		];

		$fields['wmop_no_min_required'] = [
			'title'   => __( 'No minimum required', 'woo-min-order-payment' ),
			'type'    => 'checkbox',
			'label'   => __( 'Always allow this gateway regardless of cart value', 'woo-min-order-payment' ),
			'default' => 'no',
		];

		$fields['wmop_min_amount'] = [
			'title'             => __( 'Minimum order amount', 'woo-min-order-payment' ),
			'type'              => 'number',
			/* translators: %s: store currency code, e.g. USD */
			'description'       => sprintf(
				__( 'Amount in %s. Leave blank or 0 to disable.', 'woo-min-order-payment' ),
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
			'title'   => __( 'When minimum not met', 'woo-min-order-payment' ),
			'type'    => 'select',
			'options' => [
				'hide'    => __( 'Hide this gateway completely', 'woo-min-order-payment' ),
				'disable' => __( 'Show disabled with a notice', 'woo-min-order-payment' ),
			],
			'default' => 'hide',
		];

		$fields['wmop_notice_text'] = [
			'title'       => __( 'Customer notice', 'woo-min-order-payment' ),
			'type'        => 'textarea',
			'description' => __( 'Placeholders: {min} = formatted minimum, {currency} = currency code, {gateway} = gateway name.', 'woo-min-order-payment' ),
			'default'     => __( 'A minimum order of {min} is required to use {gateway}.', 'woo-min-order-payment' ),
			'desc_tip'    => true,
		];

		return $fields;
	}
}
