=== Verify Payments for Orders on Paystack ===
Contributors: swiftstack
Tags: paystack, woocommerce, order tracking, payment verification
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 8.0
Stable tag: 1.1.4
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
* Send an optional admin notification when a payment is successfully verified
* Includes plugin diagnostics, recent log viewing, export, and clear-log tools
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
5. Menu integration is disabled by default and can be enabled from the plugin settings page.

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

= 1.1.4 =
* Added plugin-wide structured logging from activation onward, including setup, settings, menus, verification, email, Paystack API lookups, and diagnostics actions.
* Added a dedicated plugin log file with recent-log viewing directly on the diagnostics page.
* Added log export and improved clear-log handling so the log file is recreated immediately with a fresh "logs cleared" marker entry.
* Removed the old unused menu helper functions tied to the retired per-location toggles.
* Diagnostics and self-repair now use the same tracking-page content validation rules for consistent checks.
* Verification now supports pending, on-hold, and cancelled Paystack orders from the customer-facing flow for both logged-in users and guests.
* Verification now relies on WooCommerce payment completion flow to avoid duplicate processing/status/email handling.
* Frontend plugin assets remain limited to the order received page, view order page, and track page only.
* Uninstall cleanup now removes only the current plugin options, tracked items, and saved plugin log files for fresh-install setups.
* Diagnostics now shows plugin log file status in addition to WooCommerce log-source details.
* Moved diagnostics under WooCommerce for easier access.
* Removed separate Primary, Mobile, and Footer menu toggles.
* Menu integration now automatically targets detected primary, mobile, and footer locations.
* Added detected menu-location status under the menu integration setting.
* Added a Verify Paystack settings tab inside WooCommerce settings.
* Removed the WooCommerce admin order action so payment verification uses only the single customer-facing flow on the order received page, view order page, and track page.

= 1.1.3 =
* Added safer menu checks before creating any Fix Order Issues menu item.
* Plugin now confirms the target page already exists in the assigned menu before adding it.
* Added a duplicate-menu guard so the same assigned menu is only processed once even when reused across multiple theme locations.
* Removed risky menu reordering during automatic menu insertion to avoid damaging existing menu structure.
* Added a Settings link on the Plugins page for quicker access.
* Menu integration is now disabled by default on fresh installs and updates until enabled in settings.
* Added separate menu integration toggles for Primary, Mobile, and Footer menus.
* Existing menu items are detected by linked page/object to avoid duplicates even if the title changes.
* Admin notification email is now blank by default and only used after being set in settings.
* Added uninstall cleanup for plugin settings, tracked plugin-created menu items, tracked healthcheck data, and the plugin-owned Track Orders page.

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
