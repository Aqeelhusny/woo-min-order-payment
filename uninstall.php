<?php
/**
 * Plugin uninstall handler.
 *
 * @package WooMinOrderPayment
 * @since   1.0.0
 */

/**
 * Runs on plugin deletion. Removes the wmop_* keys we injected into each
 * gateway's woocommerce_<id>_settings option. All other gateway settings
 * are preserved.
 *
 * WooCommerce is NOT available here — WordPress loads uninstall.php in a
 * clean context before activating the plugin's own bootstrap. We use $wpdb
 * directly.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$wmop_keys = [
	'wmop_no_min_required',
	'wmop_min_amount',
	'wmop_hide_or_disable',
	'wmop_notice_text',
];

// Fetch all options whose name matches the WC gateway settings pattern.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$rows = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT option_id, option_name, option_value
		 FROM {$wpdb->options}
		 WHERE option_name LIKE %s",
		$wpdb->esc_like( 'woocommerce_' ) . '%' . $wpdb->esc_like( '_settings' )
	)
);
// phpcs:enable

if ( empty( $rows ) ) {
	return;
}

foreach ( $rows as $row ) {
	$settings = maybe_unserialize( $row->option_value );

	if ( ! is_array( $settings ) ) {
		continue;
	}

	$changed = false;
	foreach ( $wmop_keys as $key ) {
		if ( array_key_exists( $key, $settings ) ) {
			unset( $settings[ $key ] );
			$changed = true;
		}
	}

	if ( $changed ) {
		update_option( $row->option_name, $settings );
	}
}
