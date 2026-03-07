<?php
/*
Plugin Name: Verify Payments for Orders on Paystack
Description: Track WooCommerce orders using Paystack reference and verify payments for cancelled or pending Paystack orders.
Version: 1.0.3
Author: Swiftstack Innovations
Text Domain: verify-payments-for-orders-on-paystack
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

register_activation_hook(__FILE__, 'baby_vp_on_activate');

function baby_vp_on_activate($network_wide) {

    if (is_multisite() && $network_wide) {
        $site_ids = get_sites(['fields' => 'ids']);

        foreach ($site_ids as $site_id) {
            switch_to_blog($site_id);
            baby_vp_create_track_orders_page_on_activation();
            restore_current_blog();
        }

        return;
    }

    baby_vp_create_track_orders_page_on_activation();
}

function baby_vp_create_track_orders_page_on_activation() {
    $slug = 'track-orders';

    $existing = get_page_by_path($slug);
    if ($existing) {
        return;
    }

    wp_insert_post([
        'post_title'   => 'Track Orders',
        'post_name'    => $slug,
        'post_content' => '<div style="margin-top:40px;"></div>[woocommerce_order_tracking]',
        'post_status'  => 'publish',
        'post_type'    => 'page',
    ]);
}

if ( ! function_exists('is_plugin_active_for_network') ) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

/*
Create the tracking page automatically for new subsites too
*/
add_action('wp_initialize_site', 'baby_vp_on_new_site', 10, 2);

function baby_vp_on_new_site($new_site, $args) {
    if ( ! is_plugin_active_for_network(plugin_basename(__FILE__)) ) {
        return;
    }

    switch_to_blog($new_site->blog_id);
    baby_vp_create_track_orders_page_on_activation();
    restore_current_blog();
}

function baby_vp_admin_email() {
    return 'verifypaystack@lollarodenterprise.com';
}

require_once plugin_dir_path(__FILE__) . 'paystack.php';
require_once plugin_dir_path(__FILE__) . 'verify-payment.php';
require_once plugin_dir_path(__FILE__) . 'tracking.php';
require_once plugin_dir_path(__FILE__) . 'frontend.php';

require_once __DIR__ . '/plugin-update-checker/plugin-update-checker.php';

$updateChecker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://github.com/looneytkp/verify-payments-for-orders-on-paystack/',
    __FILE__,
    'verify-payments-for-orders-on-paystack'
);

$updateChecker->setBranch('main');