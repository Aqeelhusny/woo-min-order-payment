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
 * clean context. We use $wpdb directly with prefixed variable names to
 * satisfy WordPress coding standards for global scope.
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
$wmop_rows = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT option_id, option_name, option_value
		 FROM {$wpdb->options}
		 WHERE option_name LIKE %s",
		$wpdb->esc_like( 'woocommerce_' ) . '%' . $wpdb->esc_like( '_settings' )
	)
);
// phpcs:enable

if ( empty( $wmop_rows ) ) {
	return;
}

foreach ( $wmop_rows as $wmop_row ) {
	$wmop_settings = maybe_unserialize( $wmop_row->option_value );

	if ( ! is_array( $wmop_settings ) ) {
		continue;
	}

	$wmop_changed = false;
	foreach ( $wmop_keys as $wmop_key ) {
		if ( array_key_exists( $wmop_key, $wmop_settings ) ) {
			unset( $wmop_settings[ $wmop_key ] );
			$wmop_changed = true;
		}
	}

	if ( $wmop_changed ) {
		update_option( $wmop_row->option_name, $wmop_settings );
	}
}
