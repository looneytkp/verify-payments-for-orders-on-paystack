=== Verify Payments for Orders on Paystack ===
Contributors: swiftstack
Tags: paystack, woocommerce, order tracking, payment verification
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 8.0
Stable tag: 1.1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Track WooCommerce orders using Paystack references and allow customers to verify payments for cancelled or pending Paystack orders.

== Description ==

Verify Payments for Orders on Paystack enhances the WooCommerce order tracking experience by allowing customers to confirm their Paystack payments directly from the order page.

If a Paystack payment succeeds but the order remains **Pending** or **Cancelled**, customers can verify the payment themselves without contacting support.

Key features include:

* Verify Paystack payments directly from the WooCommerce order details page
* Allow order tracking using **Paystack reference + billing email**
* Automatically update verified orders to **Processing**
* Automatically resend WooCommerce order confirmation email after verification
* Send an admin notification when a payment is successfully verified
* Supports **WordPress multisite networks**
* Automatically creates a **Track Orders page** during plugin activation
* Compatible with WooCommerce Paystack gateway settings
* Works for **logged-in and guest customers**

== Installation ==

1. Upload the plugin files to  
   `/wp-content/plugins/verify-payments-for-orders-on-paystack/`

2. Activate the plugin through the **Plugins** menu in WordPress.

3. If using **WordPress Multisite**, network activate the plugin.

4. A **Track Orders** page will automatically be created containing the WooCommerce tracking shortcode.

== Usage ==

Customers can verify payments in two ways:

**1. From the order details page**
* Open the order page
* Click **Verify payment**
* If Paystack confirms the payment, the order status updates automatically

**2. From the Track Orders page**
* Enter the **Paystack reference**
* Enter the **billing email**
* The plugin locates the correct order automatically

== Requirements ==

* WordPress 6.0 or higher
* WooCommerce installed and active
* WooCommerce Paystack payment gateway configured

== Frequently Asked Questions ==

= Does this plugin support multisite? =
Yes. The plugin automatically creates the tracking page on all subsites when network activated.

= Does it work for guest orders? =
Yes. Both logged-in and guest customers can verify payments.

= What happens after a payment is verified? =
The order status is changed to **Processing** and the WooCommerce order notification email is resent.

== Changelog ==

= 1.1.2 =
* Added GitHub updater branch support for reliable update detection.
* Fixed Paystack tracking form JavaScript handling.
* Added nonce protection to tracking verification endpoint.
* Added rate limiting to tracking requests to prevent abuse.
* Improved WooCommerce admin order action compatibility.
* Optimized frontend assets to only load when required.
* Fixed potential frontend form resubmission loop.
* Improved diagnostics and logging reliability.
* General stability improvements and internal cleanup.
