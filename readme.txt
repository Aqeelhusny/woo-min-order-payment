=== Aqeel Husny – Minimum Order Per Gateway for WooCommerce ===
Contributors: aqeelhusny
Tags: woocommerce, payment gateway, minimum order, checkout, stripe
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.0
WC requires at least: 9.5
WC tested up to: 10.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Set a minimum cart value per payment gateway. Gateways not meeting the threshold are hidden or shown disabled at checkout.

== Description ==

**Aqeel Husny – Minimum Order Per Gateway for WooCommerce** lets store admins configure a minimum cart subtotal for each enabled payment gateway independently.

= How it works =

1. Go to **WooCommerce → Settings → Payments** and open any gateway.
2. Scroll to the **Minimum Order Value** section.
3. Set a threshold, choose whether to hide or disable the gateway when the minimum is not met, and optionally customise the customer notice.

= Features =

* Per-gateway minimum order amount — configure directly inside each gateway's settings screen.
* **No minimum required** checkbox — opt any gateway out of all minimum checks (always show it).
* **Hide** or **show disabled** mode when the minimum is not met.
* Customisable customer notice with `{min}`, `{currency}`, and `{gateway}` placeholders.
* When *all* gateways are unavailable the checkout shows an actionable message: *"Add £X more to your cart to reach the £Y minimum required to check out."*
* Works with Classic (shortcode) checkout and the WooCommerce Blocks checkout.
* HPOS (High-Performance Order Storage) fully compatible.
* Works with Stripe, PayPal, Mintpay, COD, BACS, and any other WooCommerce-compatible gateway.
* Admin overview table at **WooCommerce → Settings → Payments → Minimum Order Rules**.
* Translation ready.

= Use the `wmop_cart_amount_for_comparison` filter to change what is compared =

By default the plugin compares the cart **subtotal** (excl. tax and shipping) against the threshold. Use the filter to switch to a different value:

`
add_filter( 'wmop_cart_amount_for_comparison', function( $amount, $cart ) {
    // Compare against grand total including tax.
    return (float) $cart->get_total( 'edit' );
}, 10, 2 );
`

= Skipping minimum checks for subscription renewals =

Renewal payments bypass the check by default via the `wmop_skip_minimum_checks` filter. Override this:

`
add_filter( 'wmop_skip_minimum_checks', '__return_false' );
`

== Installation ==

**From the WordPress admin:**

1. Go to **Plugins → Add New Plugin**.
2. Search for *Minimum Order Per Gateway*.
3. Click **Install Now**, then **Activate**.

**Manual:**

1. Upload the `woo-min-order-payment` folder to `/wp-content/plugins/`.
2. Activate through **Plugins → Installed Plugins**.

**After activation:**

Go to **WooCommerce → Settings → Payments**, open any gateway, and scroll to the **Minimum Order Value** section.

== Frequently Asked Questions ==

= Does it work with the WooCommerce Blocks checkout? =
Yes. Gateway filtering is handled server-side via the WooCommerce Store API, so hidden gateways work correctly with both the Classic shortcode and the Blocks checkout. A JS notice is also rendered inside the Blocks checkout sidebar.

= What does the plugin compare against? =
By default: cart subtotal excluding tax and shipping. Use the `wmop_cart_amount_for_comparison` filter to change this.

= Will subscription renewals be blocked? =
No. Renewals bypass the minimum check by default. Use the `wmop_skip_minimum_checks` filter to change this behaviour.

= What if my store uses multiple currencies? =
Thresholds are stored in the store's base currency. If you use a multi-currency plugin, use the `wmop_cart_amount_for_comparison` filter to provide a converted cart amount before comparison.

= What happens if no gateway meets the minimum? =
The checkout shows a clear actionable message telling the customer exactly how much more they need to add.

= Does it work with Dokan? =
The plugin supports a `wmop_vendor_cart_amount` filter for per-vendor minimum overrides — use this to build a Dokan-specific extension.

== Screenshots ==

1. Per-gateway Minimum Order Value section inside the gateway settings screen.
2. Admin summary table at WooCommerce → Settings → Payments → Minimum Order Rules.
3. Classic checkout customer notice when gateways are hidden.
4. Blocks checkout notice in the order summary sidebar.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release. No upgrade steps required.
