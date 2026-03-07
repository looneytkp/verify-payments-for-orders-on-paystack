<?php
/*
Plugin Name: Verify Payments for Orders on Paystack
Description: Track WooCommerce orders using Paystack reference and verify payments for cancelled or pending Paystack orders.
Version: 1.1.2
Author: Swiftstack Innovations
Text Domain: verify-payments-for-orders-on-paystack
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'BABY_VP_VERSION', '1.1.2' );
define( 'BABY_VP_FILE', __FILE__ );
define( 'BABY_VP_PATH', plugin_dir_path( __FILE__ ) );
define( 'BABY_VP_URL', plugin_dir_url( __FILE__ ) );
define( 'BABY_VP_OPTION_SETUP_DONE', 'baby_vp_setup_done' );
define( 'BABY_VP_OPTION_CREATED_PAGE_ID', 'baby_vp_created_page_id' );
define( 'BABY_VP_OPTION_PAGE_OWNED', 'baby_vp_page_owned' );
define( 'BABY_VP_OPTION_CREATED_MENU_ITEMS', 'baby_vp_created_menu_items' );
define( 'BABY_VP_OPTION_LAST_HEALTHCHECK', 'baby_vp_last_healthcheck' );

define( 'BABY_VP_LOG_SOURCE', 'verify-payments-paystack' );

require_once BABY_VP_PATH . 'includes/setup.php';
require_once BABY_VP_PATH . 'includes/menus.php';
require_once BABY_VP_PATH . 'includes/admin-page.php';
require_once BABY_VP_PATH . 'includes/settings.php';
require_once BABY_VP_PATH . 'includes/logger.php';
require_once BABY_VP_PATH . 'includes/diagnostics.php';
require_once BABY_VP_PATH . 'includes/shortcode.php';
require_once BABY_VP_PATH . 'paystack.php';
require_once BABY_VP_PATH . 'verify-payment.php';
require_once BABY_VP_PATH . 'tracking.php';
require_once BABY_VP_PATH . 'frontend.php';

baby_vp_register_hooks();
baby_vp_register_admin_page_hooks();
baby_vp_register_settings_hooks();
baby_vp_register_diagnostics_hooks();
baby_vp_register_shortcode_hooks();

require_once __DIR__ . '/plugin-update-checker/plugin-update-checker.php';

$updateChecker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://github.com/looneytkp/verify-payments-for-orders-on-paystack/',
    __FILE__,
    'verify-payments-for-orders-on-paystack'
);

$updateChecker->setBranch('main');
$updateChecker->getVcsApi()->enableReleaseAssets();
