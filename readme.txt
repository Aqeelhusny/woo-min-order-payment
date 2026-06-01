=== WooCommerce Minimum Order Per Gateway ===
Contributors: yourname
Tags: woocommerce, payment gateway, minimum order, checkout
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.0
WC requires at least: 9.5
WC tested up to: 10.x
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Set a minimum cart value per payment gateway. Gateways not meeting the threshold are hidden or shown disabled at checkout.

== Description ==

**WooCommerce Minimum Order Per Gateway** lets store admins configure a minimum cart subtotal for each enabled payment gateway.

= Features =
* Per-gateway minimum order amount — configure directly on each gateway's settings screen.
* "No minimum required" checkbox — opt a gateway out of all minimum checks.
* **Hide** or **show disabled** when the minimum is not met.
* Customisable customer notice with `{min}`, `{currency}`, and `{gateway}` placeholders.
* Works with Classic (shortcode) checkout and the WooCommerce Blocks checkout.
* HPOS (High-Performance Order Storage) compatible.
* Works with Stripe, PayPal, Mintpay, COD, BACS, and any other WooCommerce-compatible gateway.
* Admin summary table at WooCommerce → Settings → Payments → Minimum Order Rules.
* Translation ready.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate through **Plugins → Installed Plugins**.
3. Go to **WooCommerce → Settings → Payments**, open any gateway, and scroll to the **Minimum Order Value** section.

== Frequently Asked Questions ==

= Does it work with the new Blocks checkout? =
Yes. Gateway filtering happens server-side via the WooCommerce Store API, so hidden gateways work with both the Classic shortcode and Blocks checkout. Customer notices in the Blocks checkout are rendered by the companion JS bundle.

= What does the cart amount compare against? =
By default the cart subtotal excluding tax and shipping. Use the `wmop_cart_amount_for_comparison` filter to switch to a different value (e.g. grand total).

= Will subscription renewals be blocked? =
No. Renewal payments bypass the minimum check by default. Use the `wmop_skip_minimum_checks` filter to customise this behaviour.

= What happens if the minimum amount is in a different currency? =
Thresholds are stored in the store base currency. If you use a multi-currency plugin, use the `wmop_cart_amount_for_comparison` filter to provide a converted amount.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
