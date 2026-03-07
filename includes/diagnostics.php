<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function baby_vp_register_diagnostics_hooks() {
    add_action( 'admin_menu', 'baby_vp_register_diagnostics_page' );
}

function baby_vp_register_diagnostics_page() {
    add_submenu_page(
        'tools.php',
        'Verify Paystack Diagnostics',
        'Verify Paystack Diagnostics',
        'manage_woocommerce',
        'baby-vp-diagnostics',
        'baby_vp_render_diagnostics_page'
    );
}

function baby_vp_render_diagnostics_page() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return;
    }

    $page_id          = baby_vp_get_created_page_id();
    $page_owned       = function_exists( 'baby_vp_is_track_page_owned' ) ? baby_vp_is_track_page_owned() : false;
    $page             = $page_id ? get_post( $page_id ) : null;
    $page_ok          = $page && $page->post_type === 'page';
    $page_content_ok  = $page_ok && strpos( (string) $page->post_content, '[baby_vp_fix_order_issues]' ) !== false;
    $menu_items       = baby_vp_get_created_menu_items();
    $tracked_count    = is_array( $menu_items ) ? count( $menu_items ) : 0;
    $missing_count    = 0;

    if ( is_array( $menu_items ) ) {
        foreach ( $menu_items as $menu_id => $item_id ) {
            $item = wp_setup_nav_menu_item( get_post( (int) $item_id ) );
            if ( ! $item || empty( $item->ID ) ) {
                $missing_count++;
            }
        }
    }

    $gateways = function_exists( 'WC' ) && WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : [];
    $paystack_enabled = isset( $gateways['paystack'] ) && ! empty( $gateways['paystack']->enabled ) && $gateways['paystack']->enabled === 'yes';
    ?>
    <div class="wrap">
        <h1>Verify Paystack Diagnostics</h1>
        <table class="widefat striped" style="max-width:900px; margin-top:16px;">
            <tbody>
                <tr><td style="width:280px;"><strong>Plugin version</strong></td><td><?php echo esc_html( BABY_VP_VERSION ); ?></td></tr>
                <tr><td><strong>WooCommerce active</strong></td><td><?php echo class_exists( 'WooCommerce' ) ? 'Yes' : 'No'; ?></td></tr>
                <tr><td><strong>Paystack gateway enabled</strong></td><td><?php echo $paystack_enabled ? 'Yes' : 'No'; ?></td></tr>
                <tr><td><strong>Setup done</strong></td><td><?php echo baby_vp_is_setup_done() ? 'Yes' : 'No'; ?></td></tr>
                <tr><td><strong>Tracking page found</strong></td><td><?php echo $page_ok ? 'Yes' : 'No'; ?></td></tr>
                <tr><td><strong>Tracking page ID</strong></td><td><?php echo $page_id ? esc_html( (string) $page_id ) : 'Not recorded'; ?></td></tr>
                <tr><td><strong>Tracking page owned by plugin</strong></td><td><?php echo $page_owned ? 'Yes' : 'No'; ?></td></tr>
                <tr><td><strong>Tracking page content valid</strong></td><td><?php echo $page_content_ok ? 'Yes' : 'No'; ?></td></tr>
                <tr><td><strong>Tracked menu items count</strong></td><td><?php echo esc_html( (string) $tracked_count ); ?></td></tr>
                <tr><td><strong>Missing tracked menu items</strong></td><td><?php echo esc_html( (string) $missing_count ); ?></td></tr>
                <tr><td><strong>Self-repair enabled</strong></td><td><?php echo baby_vp_self_repair_enabled() ? 'Yes' : 'No'; ?></td></tr>
                <tr><td><strong>Admin email</strong></td><td><?php echo esc_html( baby_vp_admin_email() ); ?></td></tr>
                <tr><td><strong>Logs</strong></td><td>WooCommerce → Status → Logs → <?php echo esc_html( BABY_VP_LOG_SOURCE ); ?></td></tr>
            </tbody>
        </table>
    </div>
    <?php
}
