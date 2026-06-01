/**
 * WMOP Blocks Checkout Integration
 *
 * Shows a notice in the WooCommerce Blocks checkout listing payment gateways
 * that are unavailable due to minimum order requirements.
 *
 * Build → assets/js/wmop-checkout-blocks.js:
 *   npm run build
 */

import { registerPlugin } from '@wordpress/plugins';
import { useState, useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { getSetting } from '@woocommerce/settings';
import { ExperimentalOrderMeta } from '@woocommerce/blocks-checkout';

/**
 * Reads the data object passed from PHP via WMOP_Blocks_Integration::get_script_data().
 * getSetting key format: {integration_name}_data.
 */
const getWMOPSettings = () =>
	getSetting( 'wmop-min-order_data', { unavailable_gateways: [] } );

/**
 * Renders the list of hidden gateways and their minimum-order notices.
 * Data comes from PHP (session) at page-load time; the gateway filter itself
 * (woocommerce_available_payment_gateways) re-runs server-side on every
 * Store API request so hidden gateways stay hidden as the cart changes.
 */
const WMOPMinOrderNotice = () => {
	const [ gateways, setGateways ] = useState( () => getWMOPSettings().unavailable_gateways );

	// Watch the cart store so the notice updates if the customer changes quantities.
	const cartTotals = useSelect( ( select ) => {
		const cartStore = select( 'wc/store/cart' );
		return cartStore ? cartStore.getCartTotals() : null;
	} );

	useEffect( () => {
		// getSetting is static (set at page-load from PHP). When the cart changes
		// the Store API re-fetches available gateways; if a gateway reappears it
		// is simply gone from this list on the next full page load. For a real-time
		// update, the server would need a custom endpoint — flagged as v2.
		setGateways( getWMOPSettings().unavailable_gateways );
	}, [ cartTotals ] );

	if ( ! Array.isArray( gateways ) || gateways.length === 0 ) {
		return null;
	}

	return (
		<div className="wmop-blocks-notice" role="note" aria-live="polite">
			{ gateways.map( ( gw ) => (
				<p
					key={ gw.id }
					// Sanitised server-side via wp_kses_post before being passed to JS.
					dangerouslySetInnerHTML={ { __html: gw.notice } }
				/>
			) ) }
		</div>
	);
};

/**
 * Plugin wrapper — slots the notice into the order summary sidebar via
 * ExperimentalOrderMeta. Degrades gracefully if the slot is unavailable.
 */
const WMOPCheckoutPlugin = () => {
	if ( ! ExperimentalOrderMeta ) {
		return null;
	}

	return (
		<ExperimentalOrderMeta>
			<WMOPMinOrderNotice />
		</ExperimentalOrderMeta>
	);
};

registerPlugin( 'wmop-checkout-notices', {
	render: WMOPCheckoutPlugin,
	scope: 'woocommerce-checkout',
} );
