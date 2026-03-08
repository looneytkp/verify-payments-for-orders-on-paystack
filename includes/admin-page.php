<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function baby_vp_register_admin_page_hooks() {
    add_action( 'admin_menu', 'baby_vp_register_admin_page' );
    add_action( 'admin_post_baby_vp_run_setup_again', 'baby_vp_handle_run_setup_again' );
}

function baby_vp_register_admin_page() {
    add_submenu_page(
        'tools.php',
        'Verify Paystack Setup',
        'Verify Paystack Setup',
        'manage_woocommerce',
        'baby-vp-setup',
        'baby_vp_render_admin_page'
    );
}

function baby_vp_render_admin_page() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return;
    }

    $page_id            = baby_vp_get_created_page_id();
    $created_menu_items = baby_vp_get_created_menu_items();
    $status             = isset( $_GET['baby_vp_status'] ) ? sanitize_key( wp_unslash( $_GET['baby_vp_status'] ) ) : '';
    ?>
    <div class="wrap">
        <h1>Verify Paystack Setup</h1>
        <?php if ( $status === 'setup-ran' ) : ?>
            <div class="notice notice-success is-dismissible"><p>Plugin setup has been run again for this site.</p></div>
        <?php endif; ?>

        <p>Use this page when the theme changes, menu locations change, or you want the plugin to check setup again.</p>
        <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=baby-vp-settings' ) ); ?>" class="button">Open Settings</a></p>

        <table class="widefat striped" style="max-width:900px; margin-top:16px;">
            <tbody>
                <tr>
                    <td style="width:220px;"><strong>Setup done</strong></td>
                    <td><?php echo baby_vp_is_setup_done() ? 'Yes' : 'No'; ?></td>
                </tr>
                <tr>
                    <td><strong>Tracked page ID</strong></td>
                    <td><?php echo $page_id ? esc_html( (string) $page_id ) : 'Not recorded'; ?></td>
                </tr>
                <tr>
                    <td><strong>Tracked menu items</strong></td>
                    <td><?php echo ! empty( $created_menu_items ) ? esc_html( wp_json_encode( $created_menu_items ) ) : 'None recorded'; ?></td>
                </tr>
            </tbody>
        </table>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:20px;">
            <?php wp_nonce_field( 'baby_vp_run_setup_again' ); ?>
            <input type="hidden" name="action" value="baby_vp_run_setup_again">
            <?php submit_button( 'Run Plugin Setup Again', 'primary', 'submit', false ); ?>
        </form>
    </div>
    <?php
}

function baby_vp_handle_run_setup_again() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( 'Unauthorized.' );
    }

    check_admin_referer( 'baby_vp_run_setup_again' );

    baby_vp_log( 'setup', 'Manual setup rerun requested from admin page.', [] );
    baby_vp_reset_setup_done();
    baby_vp_run_setup( 'manual_setup_page' );

    wp_safe_redirect(
        add_query_arg(
            [
                'page'           => 'baby-vp-setup',
                'baby_vp_status' => 'setup-ran',
            ],
            admin_url( 'tools.php' )
        )
    );
    exit;
}
