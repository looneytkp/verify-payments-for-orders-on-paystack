=== Verify Payments for Orders on Paystack ===
Contributors: swiftstack
Tags: woocommerce, paystack, payments, verification, orders
Requires at least: 6.3
Requires PHP: 8.0
Tested up to: 6.5
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically verify WooCommerce orders paid via Paystack, fix payment issues, and allow customers to track orders using Order ID or Paystack reference.

== Description ==

Verify Payments for Orders on Paystack improves WooCommerce payment reliability by allowing customers and store owners to verify Paystack payments and fix orders that were incorrectly marked as cancelled or pending.

It also enhances the order tracking experience by allowing customers to track orders using either their Order ID or Paystack reference.

=== Key Features ===

* Verify Paystack payments directly from WooCommerce orders
* Automatically match Paystack transactions using:
  - billing email
  - order date (with next-day fallback)
  - transaction amount
  - reference prefix (order number)
* Update order status to processing when payment is confirmed
* Automatically resend order confirmation email after successful verification

=== Track Orders Enhancements ===

Customers can track their orders using two methods:

1. Order ID + Billing Email (standard WooCommerce flow)
2. Paystack Reference + Billing Email (for customers without order ID)

The plugin intelligently detects which method is used and processes accordingly.

=== Menu Integration ===

Optionally add a “Fix Order Issues” link to selected menu locations:
* Primary menu
* Mobile menu
* Footer menu

Menu locations are automatically detected and can be enabled from settings.

=== Email Notice ===

You can customize a message shown in WooCommerce emails, for example:

"If you have any payment issues with your orders, check and fix them here:"

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/verify-payments-for-orders-on-paystack/`
2. Activate the plugin through the WordPress admin
3. Go to WooCommerce → Verify Paystack Settings
4. Configure your options

== Frequently Asked Questions ==

= Can customers track orders without an Order ID? =
Yes. Customers can use their Paystack reference along with their billing email to locate their order.

= Does this plugin modify WooCommerce core tracking? =
No. It extends the tracking process without breaking the default WooCommerce behavior.

= What happens when a payment is verified? =
The order is updated to processing and the order confirmation email is resent.

= Does it work with multisite? =
Yes. Each site can have its own tracking page and settings.

== Changelog ==

= 1.2.0 =
* Improved Track Orders page flow so customers can use either:
  - Order ID + billing email, or
  - Paystack reference + billing email
* Fixed tracking form so entering Order ID + billing email no longer forces Paystack reference lookup
* Added strict validation on Track Orders submit:
  - Order ID requires billing email
  - Paystack reference requires billing email
  - users must use either Order ID or Paystack reference, not both

= 1.1.9 =
* Minor fixes and improvements

= 1.1.8 =
* Improvements to menu integration and tracking page behavior

== Upgrade Notice ==

= 1.2.0 =
Improves tracking logic and fixes incorrect Paystack reference prompts.