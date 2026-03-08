<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function baby_vp_register_diagnostics_hooks() {
    add_action( 'admin_menu', 'baby_vp_register_diagnostics_page' );
    add_action( 'admin_post_baby_vp_export_logs', 'baby_vp_handle_export_logs' );
    add_action( 'admin_post_baby_vp_clear_logs', 'baby_vp_handle_clear_logs' );
}

function baby_vp_register_diagnostics_page() {
    add_submenu_page(
        'woocommerce',
        'Verify Paystack Diagnostics',
        'Verify Paystack Diagnostics',
        'manage_woocommerce',
        'baby-vp-diagnostics',
        'baby_vp_render_diagnostics_page'
    );
}

function baby_vp_handle_export_logs() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( 'Unauthorized.' );
    }

    check_admin_referer( 'baby_vp_export_logs' );

    $file = baby_vp_get_log_file_path();
    if ( ! $file || ! file_exists( $file ) ) {
        wp_die( 'No log file found.' );
    }

    baby_vp_log( 'diagnostics', 'Log export requested.', [
        'file' => basename( $file ),
        'size' => baby_vp_get_log_file_size(),
    ] );

    nocache_headers();
    header( 'Content-Type: application/x-ndjson; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( BABY_VP_LOG_SOURCE . '-' . gmdate( 'Ymd-His' ) . '.log' ) . '"' );
    header( 'Content-Length: ' . filesize( $file ) );
    readfile( $file );
    exit;
}

function baby_vp_handle_clear_logs() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( 'Unauthorized.' );
    }

    check_admin_referer( 'baby_vp_clear_logs' );

    $ok = baby_vp_clear_log_file_with_marker();


    wp_safe_redirect(
        add_query_arg(
            [
                'page'           => 'baby-vp-diagnostics',
                'baby_vp_status' => $ok ? 'logs-cleared' : 'logs-clear-failed',
            ],
            admin_url( 'admin.php' )
        )
    );
    exit;
}

function baby_vp_render_diagnostics_page() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return;
    }

    $page_id          = baby_vp_get_created_page_id();
    $page_owned       = function_exists( 'baby_vp_is_track_page_owned' ) ? baby_vp_is_track_page_owned() : false;
    $page             = $page_id ? get_post( $page_id ) : null;
    $page_ok          = $page && $page->post_type === 'page';
    $page_content_ok  = $page_ok && baby_vp_is_valid_track_orders_page_content( $page->post_content );
    $menu_items       = baby_vp_get_created_menu_items();
    $tracked_count    = is_array( $menu_items ) ? count( $menu_items ) : 0;
    $missing_count    = 0;
    $status           = isset( $_GET['baby_vp_status'] ) ? sanitize_key( wp_unslash( $_GET['baby_vp_status'] ) ) : '';
    $recent_logs      = baby_vp_read_recent_log_entries( 50 );
    $log_file         = baby_vp_get_log_file_path();
    $log_file_exists  = $log_file && file_exists( $log_file );
    $log_file_size    = baby_vp_get_log_file_size();

    if ( is_array( $menu_items ) ) {
        foreach ( $menu_items as $menu_id => $item_id ) {
            $item = wp_setup_nav_menu_item( get_post( (int) $item_id ) );
            if ( ! $item || empty( $item->ID ) ) {
                $missing_count++;
            }
        }
    }

    $gateways         = function_exists( 'WC' ) && WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : [];
    $paystack_enabled = isset( $gateways['paystack'] ) && ! empty( $gateways['paystack']->enabled ) && $gateways['paystack']->enabled === 'yes';
    ?>
    <div class="wrap">
        <h1>Verify Paystack Diagnostics</h1>
        <?php if ( $status === 'logs-cleared' ) : ?>
            <div class="notice notice-success is-dismissible"><p>Logs cleared.</p></div>
        <?php elseif ( $status === 'logs-clear-failed' ) : ?>
            <div class="notice notice-error is-dismissible"><p>Could not clear logs.</p></div>
        <?php endif; ?>

        <p>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=baby_vp' ) ); ?>" class="button">Open WooCommerce Settings Tab</a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=baby-vp-settings' ) ); ?>" class="button">Open Settings</a>
            <a href="<?php echo esc_url( admin_url( 'tools.php?page=baby-vp-setup' ) ); ?>" class="button">Open Setup Page</a>
        </p>

        <table class="widefat striped" style="max-width:980px; margin-top:16px;">
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
                <tr><td><strong>Plugin log file</strong></td><td><?php echo $log_file_exists ? esc_html( $log_file ) : 'Not created yet'; ?></td></tr>
                <tr><td><strong>Plugin log size</strong></td><td><?php echo esc_html( size_format( $log_file_size ) ); ?></td></tr>
                <tr><td><strong>WooCommerce log source</strong></td><td><?php echo esc_html( BABY_VP_LOG_SOURCE ); ?></td></tr>
            </tbody>
        </table>

        <h2 style="margin-top:28px;">Log Tools</h2>
        <p>These buttons manage the plugin log file used by diagnostics export and recent-log viewing.</p>
        <p>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-status&tab=logs' ) ); ?>" class="button">Open WooCommerce Logs</a>
        </p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block; margin-right:10px;">
            <?php wp_nonce_field( 'baby_vp_export_logs' ); ?>
            <input type="hidden" name="action" value="baby_vp_export_logs">
            <?php submit_button( 'Export Logs', 'secondary', 'submit', false ); ?>
        </form>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
            <?php wp_nonce_field( 'baby_vp_clear_logs' ); ?>
            <input type="hidden" name="action" value="baby_vp_clear_logs">
            <?php submit_button( 'Clear Logs', 'delete', 'submit', false, [ 'onclick' => "return confirm('Clear the plugin log file?');" ] ); ?>
        </form>

        <h2 style="margin-top:28px;">Recent Plugin Logs</h2>
        <?php if ( empty( $recent_logs ) ) : ?>
            <p>No plugin log entries yet.</p>
        <?php else : ?>
            <table class="widefat striped" style="margin-top:12px;">
                <thead>
                    <tr>
                        <th style="width:170px;">Time (UTC)</th>
                        <th style="width:90px;">Level</th>
                        <th style="width:120px;">Context</th>
                        <th>Message</th>
                        <th style="width:36%;">Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $recent_logs as $entry ) : ?>
                        <tr>
                            <td><?php echo esc_html( isset( $entry['timestamp'] ) ? (string) $entry['timestamp'] : '' ); ?></td>
                            <td><?php echo esc_html( strtoupper( isset( $entry['level'] ) ? (string) $entry['level'] : 'INFO' ) ); ?></td>
                            <td><?php echo esc_html( isset( $entry['context'] ) ? (string) $entry['context'] : '' ); ?></td>
                            <td><?php echo esc_html( isset( $entry['message'] ) ? (string) $entry['message'] : '' ); ?></td>
                            <td><pre style="white-space:pre-wrap; margin:0;"><?php echo esc_html( wp_json_encode( isset( $entry['data'] ) ? $entry['data'] : [] ) ); ?></pre></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}
