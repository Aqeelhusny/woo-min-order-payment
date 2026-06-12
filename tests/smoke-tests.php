<?php
/**
 * Standalone smoke tests — no WordPress install required.
 *
 * Stubs the WP/WC surface the plugin touches, loads the real plugin classes,
 * and asserts every behavioral branch. Run: php tests/smoke-tests.php
 *
 * Exit code 0 = all pass, 1 = failures.
 *
 * @package WooMinOrderPayment
 */

error_reporting( E_ALL );
ini_set( 'display_errors', '1' );

define( 'ABSPATH', __DIR__ . '/' );
define( 'WMOP_VERSION', '1.0.0' );
define( 'WMOP_PATH', dirname( __DIR__ ) . '/' );
define( 'WMOP_URL', 'https://example.test/wp-content/plugins/woo-min-order-payment/' );

/* ---------------------------------------------------------------- *
 * Minimal hook system
 * ---------------------------------------------------------------- */
$GLOBALS['wmop_test_filters'] = [];

function add_filter( $tag, $cb, $priority = 10, $args = 1 ) {
	$GLOBALS['wmop_test_filters'][ $tag ][ $priority ][] = [ $cb, $args ];
	return true;
}
function add_action( $tag, $cb, $priority = 10, $args = 1 ) {
	return add_filter( $tag, $cb, $priority, $args );
}
function apply_filters( $tag, $value, ...$extra ) {
	if ( empty( $GLOBALS['wmop_test_filters'][ $tag ] ) ) {
		return $value;
	}
	$hooks = $GLOBALS['wmop_test_filters'][ $tag ];
	ksort( $hooks );
	foreach ( $hooks as $callbacks ) {
		foreach ( $callbacks as [ $cb, $args ] ) {
			$value = $cb( ...array_slice( array_merge( [ $value ], $extra ), 0, $args ) );
		}
	}
	return $value;
}
function do_action( $tag, ...$args ) {
	if ( empty( $GLOBALS['wmop_test_filters'][ $tag ] ) ) {
		return;
	}
	$hooks = $GLOBALS['wmop_test_filters'][ $tag ];
	ksort( $hooks );
	foreach ( $hooks as $callbacks ) {
		foreach ( $callbacks as [ $cb, $n ] ) {
			$cb( ...array_slice( $args, 0, $n ) );
		}
	}
}
function remove_all_filters( $tag ) {
	unset( $GLOBALS['wmop_test_filters'][ $tag ] );
}

/* ---------------------------------------------------------------- *
 * WP function stubs
 * ---------------------------------------------------------------- */
function __( $text, $domain = '' ) { return $text; }
function esc_html__( $text, $domain = '' ) { return htmlspecialchars( $text, ENT_QUOTES ); }
function esc_attr__( $text, $domain = '' ) { return htmlspecialchars( $text, ENT_QUOTES ); }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function esc_url( $url ) { return $url; }
function wp_kses_post( $text ) { return preg_replace( '/<script\b[^>]*>.*?<\/script>/is', '', (string) $text ); }
function wp_strip_all_tags( $text ) { return trim( strip_tags( (string) $text ) ); }
function sanitize_text_field( $text ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $text ) ) ); }
function wp_unslash( $value ) { return $value; }
function checked( $checked, $current = true, $display = true ) { return $checked == $current ? 'checked="checked"' : ''; }
function selected( $selected, $current = true, $display = true ) { return $selected == $current ? 'selected="selected"' : ''; }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
function get_bloginfo( $key ) { return 'UTF-8'; }
function wp_doing_ajax() { return ! empty( $GLOBALS['wmop_test_doing_ajax'] ); }
function is_admin() { return ! empty( $GLOBALS['wmop_test_is_admin'] ); }
function current_user_can( $cap ) { return $GLOBALS['wmop_test_user_can'] ?? true; }
function check_admin_referer( $action = -1 ) { $GLOBALS['wmop_test_nonce_checked'] = $action; return 1; }
function update_option( $name, $value ) { $GLOBALS['wmop_test_options'][ $name ] = $value; return true; }
function get_option( $name, $default = false ) { return $GLOBALS['wmop_test_options'][ $name ] ?? $default; }
function wc_add_notice( $message, $type = 'success' ) { $GLOBALS['wmop_test_notices'][] = [ $type, $message ]; }
function is_checkout() { return true; }
function wp_enqueue_style( ...$a ) {}
function wp_register_script( ...$a ) {}
function wp_register_style( ...$a ) {}
function wp_script_is( ...$a ) { return true; }
function wp_style_is( ...$a ) { return true; }

/* ---------------------------------------------------------------- *
 * WC function stubs
 * ---------------------------------------------------------------- */
function get_woocommerce_currency() { return 'USD'; }
function wc_get_price_decimals() { return 2; }
function wc_price( $amount ) { return '<span class="amount">&#36;' . number_format( (float) $amount, 2 ) . '</span>'; }
function wc_format_decimal( $number ) {
	$number = trim( (string) $number );
	if ( '' === $number || ! is_numeric( $number ) ) {
		return '';
	}
	return (string) (float) $number;
}
function wc_format_localized_price( $value ) { return (string) $value; }

class WP_Error {
	public array $errors = [];
	public function add( $code, $message ) { $this->errors[ $code ][] = $message; }
	public function has_errors() { return ! empty( $this->errors ); }
	public function get_error_messages() { return array_merge( ...array_values( $this->errors ) ?: [ [] ] ); }
}

class WC_Payment_Gateway {
	public string $id;
	public ?string $description = null;
	public array $settings = [];
	private string $title;

	public function __construct( string $id, array $settings = [], string $title = '' ) {
		$this->id       = $id;
		$this->settings = $settings;
		$this->title    = $title ?: ucfirst( $id );
	}
	public function get_title() { return $this->title; }
	public function get_option( $key, $default = '' ) { return $this->settings[ $key ] ?? $default; }
	public function init_settings() {}
	public function get_option_key() { return 'woocommerce_' . $this->id . '_settings'; }
}

class WC_Cart {
	public function __construct( private float $subtotal, private bool $empty = false ) {}
	public function get_subtotal() { return $this->subtotal; }
	public function is_empty() { return $this->empty; }
}

class WC_Order {
	public function __construct( private string $payment_method = '' ) {}
	public function get_payment_method() { return $this->payment_method; }
}

class WC_Payment_Gateways_Stub {
	public array $gateways = [];
	public function payment_gateways() { return $this->gateways; }
	public function get_available_payment_gateways() {
		return apply_filters( 'woocommerce_available_payment_gateways', $this->gateways );
	}
}

class WC_Stub {
	public ?WC_Cart $cart = null;
	public $session = null;
	public WC_Payment_Gateways_Stub $gateways_container;
	public function __construct() { $this->gateways_container = new WC_Payment_Gateways_Stub(); }
	public function payment_gateways() { return $this->gateways_container; }
	public function is_rest_api_request() { return false; }
}

function WC() {
	return $GLOBALS['wmop_test_wc'];
}

/* ---------------------------------------------------------------- *
 * Blocks interface stub, then load the real plugin code
 * ---------------------------------------------------------------- */
// Match the real interface so class-wmop-blocks-integration.php parses.
// phpcs:ignore
eval( 'namespace Automattic\WooCommerce\Blocks\Integrations; interface IntegrationInterface { public function get_name(); public function get_version(); public function initialize(); public function get_script_handles(); public function get_editor_script_handles(); public function get_script_data(); }' );

require_once WMOP_PATH . 'includes/class-wmop-helpers.php';
require_once WMOP_PATH . 'includes/class-wmop-compat.php';
require_once WMOP_PATH . 'includes/class-wmop-gateway-filter.php';
require_once WMOP_PATH . 'includes/class-wmop-notices.php';
require_once WMOP_PATH . 'includes/class-wmop-settings.php';
require_once WMOP_PATH . 'includes/class-wmop-admin.php';
require_once WMOP_PATH . 'includes/class-wmop-blocks-integration.php';

/* ---------------------------------------------------------------- *
 * Tiny assertion runner
 * ---------------------------------------------------------------- */
$pass = 0;
$fail = 0;

function check( string $name, bool $condition, string $detail = '' ): void {
	global $pass, $fail;
	if ( $condition ) {
		$pass++;
		echo "  ok    $name\n";
	} else {
		$fail++;
		echo "  FAIL  $name" . ( $detail ? " — $detail" : '' ) . "\n";
	}
}

function fresh_env( float $subtotal = 30.0 ): WC_Stub {
	$GLOBALS['wmop_test_wc']            = new WC_Stub();
	$GLOBALS['wmop_test_wc']->cart      = new WC_Cart( $subtotal );
	$GLOBALS['wmop_test_is_admin']      = false;
	$GLOBALS['wmop_test_doing_ajax']    = false;
	$GLOBALS['wmop_test_notices']       = [];
	$GLOBALS['wmop_test_options']       = [];
	remove_all_filters( 'wmop_skip_minimum_checks' );
	return $GLOBALS['wmop_test_wc'];
}

function make_gateways( WC_Stub $wc, array $defs ): array {
	$gateways = [];
	foreach ( $defs as $id => $settings ) {
		$gateways[ $id ] = new WC_Payment_Gateway( $id, $settings );
	}
	$wc->gateways_container->gateways = $gateways;
	return $gateways;
}

$filter = new WMOP_Gateway_Filter();

/* ================================================================ *
 * 1. Helpers
 * ================================================================ */
echo "Helpers:\n";
fresh_env( 42.5 );
check( 'get_cart_amount returns subtotal', 42.5 === WMOP_Helpers::get_cart_amount() );
check( 'parse_min_amount: empty string', 0.0 === WMOP_Helpers::parse_min_amount( '' ) );
check( 'parse_min_amount: zero', 0.0 === WMOP_Helpers::parse_min_amount( '0' ) );
check( 'parse_min_amount: negative clamped', 0.0 === WMOP_Helpers::parse_min_amount( '-5' ) );
check( 'parse_min_amount: garbage', 0.0 === WMOP_Helpers::parse_min_amount( 'abc' ) );
check( 'parse_min_amount: decimal', 10.5 === WMOP_Helpers::parse_min_amount( '10.5' ) );

$gw     = new WC_Payment_Gateway( 'stripe', [], 'Stripe' );
$notice = WMOP_Helpers::build_notice( $gw, 50.0 );
check( 'build_notice substitutes {min}', str_contains( $notice, '50.00' ), $notice );
check( 'build_notice substitutes {gateway}', str_contains( $notice, 'Stripe' ), $notice );

$gw2     = new WC_Payment_Gateway( 'cod', [ 'wmop_notice_text' => 'Need {min} ({currency}) for {gateway}!' ], 'COD' );
$notice2 = WMOP_Helpers::build_notice( $gw2, 25.0 );
check( 'build_notice custom template + {currency}', str_contains( $notice2, 'USD' ) && str_contains( $notice2, 'COD' ), $notice2 );

/* ================================================================ *
 * 2. Gateway filtering
 * ================================================================ */
echo "Gateway filter:\n";

// No rules configured → untouched.
$wc       = fresh_env( 30.0 );
$gateways = make_gateways( $wc, [ 'stripe' => [], 'cod' => [] ] );
$result   = $filter->filter_gateways( $gateways );
check( 'no rules: all gateways kept', 2 === count( $result ) );
check( 'no rules: nothing recorded', [] === WMOP_Gateway_Filter::get_unavailable() );

// Min met → kept.
$wc       = fresh_env( 100.0 );
$gateways = make_gateways( $wc, [ 'stripe' => [ 'wmop_min_amount' => '50' ] ] );
$result   = $filter->filter_gateways( $gateways );
check( 'min met: gateway kept', isset( $result['stripe'] ) );

// Min unmet + hide → removed.
$wc       = fresh_env( 30.0 );
$gateways = make_gateways( $wc, [ 'stripe' => [ 'wmop_min_amount' => '50' ], 'cod' => [] ] );
$result   = $filter->filter_gateways( $gateways );
check( 'min unmet hide: gateway removed', ! isset( $result['stripe'] ) && isset( $result['cod'] ) );
check( 'min unmet hide: recorded for notices', isset( WMOP_Gateway_Filter::get_unavailable()['stripe'] ) );

// Min unmet + disable → kept with notice, appended ONCE across repeated runs.
$wc       = fresh_env( 30.0 );
$gateways = make_gateways( $wc, [ 'stripe' => [ 'wmop_min_amount' => '50', 'wmop_hide_or_disable' => 'disable' ] ] );
$filter->filter_gateways( $gateways );
$filter->filter_gateways( $gateways );
$result = $filter->filter_gateways( $gateways );
check( 'disable: gateway kept', isset( $result['stripe'] ) );
check( 'disable: notice in description', str_contains( (string) $result['stripe']->description, 'wmop-disabled-notice' ) );
check( 'disable: notice appended exactly once', 1 === substr_count( (string) $result['stripe']->description, 'wmop-disabled-notice' ) );

// no_min_required → exempt.
$wc       = fresh_env( 1.0 );
$gateways = make_gateways( $wc, [ 'cod' => [ 'wmop_min_amount' => '999', 'wmop_no_min_required' => 'yes' ] ] );
$result   = $filter->filter_gateways( $gateways );
check( 'no_min_required: gateway exempt', isset( $result['cod'] ) );

// Skip filter override.
$wc       = fresh_env( 1.0 );
$gateways = make_gateways( $wc, [ 'stripe' => [ 'wmop_min_amount' => '999' ] ] );
add_filter( 'wmop_skip_minimum_checks', fn() => true );
$result = $filter->filter_gateways( $gateways );
check( 'wmop_skip_minimum_checks: all kept', isset( $result['stripe'] ) );

// Empty cart → untouched.
$wc       = fresh_env( 0.0 );
$wc->cart = new WC_Cart( 0.0, true );
$gateways = make_gateways( $wc, [ 'stripe' => [ 'wmop_min_amount' => '50' ] ] );
$result   = $filter->filter_gateways( $gateways );
check( 'empty cart: untouched', isset( $result['stripe'] ) );

// Admin (non-AJAX) → untouched.
$wc                            = fresh_env( 1.0 );
$GLOBALS['wmop_test_is_admin'] = true;
$gateways                      = make_gateways( $wc, [ 'stripe' => [ 'wmop_min_amount' => '50' ] ] );
$result                        = $filter->filter_gateways( $gateways );
check( 'admin screen: untouched', isset( $result['stripe'] ) );

// Cart amount filter override.
$wc       = fresh_env( 10.0 );
$gateways = make_gateways( $wc, [ 'stripe' => [ 'wmop_min_amount' => '50' ] ] );
add_filter( 'wmop_cart_amount_for_comparison', fn( $amount ) => 60.0 );
$result = $filter->filter_gateways( $gateways );
remove_all_filters( 'wmop_cart_amount_for_comparison' );
new WMOP_Compat(); // re-register its stub filter for later tests
check( 'wmop_cart_amount_for_comparison override respected', isset( $result['stripe'] ) );

/* ================================================================ *
 * 3. Classic checkout validation
 * ================================================================ */
echo "Classic checkout validation:\n";
$wc       = fresh_env( 30.0 );
$gateways = make_gateways( $wc, [ 'stripe' => [ 'wmop_min_amount' => '50' ] ] );
$_POST['payment_method'] = 'stripe';
$filter->validate_on_checkout();
check( 'below min: error notice added', ! empty( $GLOBALS['wmop_test_notices'] ) && 'error' === $GLOBALS['wmop_test_notices'][0][0] );

$wc       = fresh_env( 100.0 );
$gateways = make_gateways( $wc, [ 'stripe' => [ 'wmop_min_amount' => '50' ] ] );
$_POST['payment_method'] = 'stripe';
$filter->validate_on_checkout();
check( 'min met: no notice', empty( $GLOBALS['wmop_test_notices'] ) );

$wc = fresh_env( 1.0 );
make_gateways( $wc, [ 'stripe' => [ 'wmop_min_amount' => '50' ] ] );
$_POST['payment_method'] = 'unknown_gateway';
$filter->validate_on_checkout();
check( 'unknown gateway id: ignored safely', empty( $GLOBALS['wmop_test_notices'] ) );
unset( $_POST['payment_method'] );

/* ================================================================ *
 * 4. Store API (Blocks) validation
 * ================================================================ */
echo "Store API validation:\n";
$wc = fresh_env( 30.0 );
make_gateways( $wc, [ 'stripe' => [ 'wmop_min_amount' => '50' ] ] );
$errors = new WP_Error();
$filter->validate_before_payment( new WC_Order( 'stripe' ), $errors );
check( 'below min: error added to WP_Error', $errors->has_errors() );
check( 'error message is plain text', ! str_contains( implode( '', $errors->get_error_messages() ), '<' ) );

$wc = fresh_env( 100.0 );
make_gateways( $wc, [ 'stripe' => [ 'wmop_min_amount' => '50' ] ] );
$errors = new WP_Error();
$filter->validate_before_payment( new WC_Order( 'stripe' ), $errors );
check( 'min met: no error', ! $errors->has_errors() );

$wc       = fresh_env( 30.0 );
$wc->cart = null; // pay-for-order context: no cart.
make_gateways( $wc, [ 'stripe' => [ 'wmop_min_amount' => '50' ] ] );
$errors = new WP_Error();
$filter->validate_before_payment( new WC_Order( 'stripe' ), $errors );
check( 'pay-for-order (no cart): skipped', ! $errors->has_errors() );

/* ================================================================ *
 * 5. Notices
 * ================================================================ */
echo "Notices:\n";
$notices = new WMOP_Notices();

$wc       = fresh_env( 30.0 );
$gateways = make_gateways( $wc, [ 'stripe' => [ 'wmop_min_amount' => '50' ], 'paypal' => [ 'wmop_min_amount' => '80' ] ] );
$filter->filter_gateways( $gateways );
ob_start();
$notices->render_classic_notice();
$html = ob_get_clean();
check( 'classic notice lists hidden gateways', str_contains( $html, 'Stripe' ) && str_contains( $html, 'Paypal' ) );

$text = $notices->no_gateways_text( 'default message' );
check( 'no-gateways text uses smallest min (50)', str_contains( $text, '50.00' ), $text );
check( 'no-gateways text shows amount to add (20)', str_contains( $text, '20.00' ), $text );

$wc = fresh_env( 100.0 );
make_gateways( $wc, [ 'stripe' => [ 'wmop_min_amount' => '50' ] ] );
$filter->filter_gateways( $wc->gateways_container->gateways );
check( 'all minimums met: original message untouched', 'default message' === $notices->no_gateways_text( 'default message' ) );

/* ================================================================ *
 * 6. Blocks integration script data
 * ================================================================ */
echo "Blocks integration:\n";
$blocks = new WMOP_Blocks_Integration();

$wc = fresh_env( 30.0 );
make_gateways( $wc, [
	'stripe' => [ 'wmop_min_amount' => '50' ],
	'paypal' => [ 'wmop_min_amount' => '80', 'wmop_hide_or_disable' => 'disable' ],
] );
$data = $blocks->get_script_data();
check( 'script data computes availability on demand', 1 === count( $data['unavailable_gateways'] ) );
check( 'script data: only hide-mode gateways', 'stripe' === $data['unavailable_gateways'][0]['id'] );

$wc       = fresh_env( 30.0 );
$wc->cart = null;
$data     = $blocks->get_script_data();
check( 'script data: no cart → empty, no fatal', [] === $data['unavailable_gateways'] );

/* ================================================================ *
 * 7. Settings injection
 * ================================================================ */
echo "Settings injection:\n";
$settings = new WMOP_Settings();
$fields   = $settings->inject_fields( [ 'enabled' => [ 'type' => 'checkbox' ] ] );
$expected = [ 'wmop_section_title', 'wmop_no_min_required', 'wmop_min_amount', 'wmop_hide_or_disable', 'wmop_notice_text' ];
check( 'all five fields injected', ! array_diff( $expected, array_keys( $fields ) ) );
check( 'existing gateway fields preserved', isset( $fields['enabled'] ) );
check( 'defaults match runtime fallbacks', 'no' === $fields['wmop_no_min_required']['default'] && 'hide' === $fields['wmop_hide_or_disable']['default'] );

/* ================================================================ *
 * 8. Admin rules editor
 * ================================================================ */
echo "Admin rules editor:\n";
$GLOBALS['wmop_test_is_admin'] = true;
$admin                         = new WMOP_Admin();

$wc = fresh_env( 0.0 );
$GLOBALS['wmop_test_is_admin'] = true;
make_gateways( $wc, [ 'stripe' => [ 'enabled' => 'yes' ], 'cod' => [] ] );
ob_start();
$admin->render_rules_table();
$table = ob_get_clean();
check( 'editor renders a row per gateway', str_contains( $table, 'wmop_rules[stripe]' ) && str_contains( $table, 'wmop_rules[cod]' ) );
check( 'editor renders amount + mode + notice inputs', str_contains( $table, '[min_amount]' ) && str_contains( $table, '[mode]' ) && str_contains( $table, '[notice]' ) );

// Save: sanitization and forged-ID rejection.
global $current_section;
$current_section = 'wmop_rules';
$wc              = fresh_env( 0.0 );
$GLOBALS['wmop_test_is_admin'] = true;
make_gateways( $wc, [ 'stripe' => [], 'cod' => [] ] );
$_POST['wmop_rules'] = [
	'stripe' => [
		'min_amount' => '50.555',
		'mode'       => 'evil_mode',
		'notice'     => 'Min {min}<script>alert(1)</script>',
	],
	'cod'    => [ 'no_min' => '1', 'min_amount' => '-10' ],
	'forged' => [ 'min_amount' => '1' ],
];
$admin->save_rules();
$stripe_saved = $GLOBALS['wmop_test_options']['woocommerce_stripe_settings'] ?? [];
$cod_saved    = $GLOBALS['wmop_test_options']['woocommerce_cod_settings'] ?? [];
check( 'nonce verified on save', 'woocommerce-settings' === ( $GLOBALS['wmop_test_nonce_checked'] ?? null ) );
check( 'amount saved via wc_format_decimal', '50.555' === $stripe_saved['wmop_min_amount'] );
check( 'invalid mode coerced to hide', 'hide' === $stripe_saved['wmop_hide_or_disable'] );
check( 'script tags stripped from notice', ! str_contains( $stripe_saved['wmop_notice_text'], '<script>' ) );
check( 'placeholders survive sanitization', str_contains( $stripe_saved['wmop_notice_text'], '{min}' ) );
check( 'no_min checkbox saved', 'yes' === $cod_saved['wmop_no_min_required'] );
check( 'negative amount rejected', '' === $cod_saved['wmop_min_amount'] );
check( 'forged gateway id ignored', ! isset( $GLOBALS['wmop_test_options']['woocommerce_forged_settings'] ) );

// Save: wrong section → no-op. (Static once-guard means save_rules already ran;
// re-instantiate to get a fresh guard.)
$current_section = 'stripe';
$GLOBALS['wmop_test_options'] = [];
( new WMOP_Admin() )->save_rules();
check( 'other sections: save is a no-op', [] === $GLOBALS['wmop_test_options'] );
unset( $_POST['wmop_rules'] );

/* ================================================================ *
 * 9. Renewal skip (WooCommerce Subscriptions)
 * ================================================================ */
echo "Subscriptions:\n";
// Conditional declaration: PHP hoists unconditional top-level functions, which
// would put every earlier test into renewal-bypass mode.
if ( ! function_exists( 'wcs_cart_contains_renewal' ) ) {
	function wcs_cart_contains_renewal() { return [ 'fake renewal item' ]; }
}
$wc = fresh_env( 1.0 );
$gateways = make_gateways( $wc, [ 'stripe' => [ 'wmop_min_amount' => '999' ] ] );
$result   = $filter->filter_gateways( $gateways );
check( 'renewal cart: checks skipped by default', isset( $result['stripe'] ) );

echo "\n" . str_repeat( '-', 50 ) . "\n";
echo "Passed: $pass   Failed: $fail\n";
exit( $fail > 0 ? 1 : 0 );
