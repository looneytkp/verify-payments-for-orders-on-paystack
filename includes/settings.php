<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function baby_vp_register_settings_hooks() {
    add_action( 'admin_menu', 'baby_vp_register_settings_page' );
    add_action( 'admin_init', 'baby_vp_register_settings' );
}

function baby_vp_register_settings_page() {
    add_submenu_page(
        'woocommerce',
        'Verify Paystack Settings',
        'Verify Paystack',
        'manage_woocommerce',
        'baby-vp-settings',
        'baby_vp_render_settings_page'
    );
}

function baby_vp_register_settings() {
    register_setting( 'baby_vp_settings_group', 'baby_vp_settings', 'baby_vp_sanitize_settings' );

    add_settings_section(
        'baby_vp_main_settings',
        'Plugin Settings',
        '__return_false',
        'baby-vp-settings'
    );

    $fields = [
        'admin_email'              => 'Admin notification email',
        'auto_create_page'         => 'Auto-create tracking page',
        'menu_integration_enabled' => 'Enable menu integration',
        'add_to_primary_menu'      => 'Add to Primary menu',
        'add_to_mobile_menu'       => 'Add to Mobile menu',
        'add_to_footer_menus'      => 'Add to Footer menus',
        'menu_label'               => 'Menu label',
        'page_title'               => 'Page title',
        'page_slug'                => 'Page slug',
        'enable_self_repair'       => 'Enable self-repair',
    ];

    foreach ( $fields as $key => $label ) {
        add_settings_field(
            'baby_vp_' . $key,
            $label,
            'baby_vp_render_settings_field',
            'baby-vp-settings',
            'baby_vp_main_settings',
            [ 'key' => $key ]
        );
    }
}

function baby_vp_get_settings_defaults() {
    return [
        'admin_email'              => '',
        'auto_create_page'         => 1,
        'menu_integration_enabled' => 0,
        'add_to_primary_menu'      => 0,
        'add_to_mobile_menu'       => 0,
        'add_to_footer_menus'      => 0,
        'menu_label'               => 'Fix Order Issues',
        'page_title'               => 'Track Orders',
        'page_slug'                => 'track-orders',
        'enable_self_repair'       => 1,
    ];
}

function baby_vp_get_settings() {
    $defaults = baby_vp_get_settings_defaults();
    $settings = get_option( 'baby_vp_settings', [] );

    if ( ! is_array( $settings ) ) {
        $settings = [];
    }

    return wp_parse_args( $settings, $defaults );
}

function baby_vp_get_setting( $key, $default = null ) {
    $settings = baby_vp_get_settings();
    return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
}

function baby_vp_sanitize_settings( $input ) {
    $defaults = baby_vp_get_settings_defaults();
    $output   = [];

    $input = is_array( $input ) ? $input : [];

    $output['admin_email'] = isset( $input['admin_email'] ) ? sanitize_email( $input['admin_email'] ) : $defaults['admin_email'];
    if ( ! is_email( $output['admin_email'] ) ) {
        $output['admin_email'] = '';
    }

    $output['auto_create_page']         = ! empty( $input['auto_create_page'] ) ? 1 : 0;
    $output['menu_integration_enabled'] = ! empty( $input['menu_integration_enabled'] ) ? 1 : 0;
    $output['add_to_primary_menu']      = ! empty( $input['add_to_primary_menu'] ) ? 1 : 0;
    $output['add_to_mobile_menu']       = ! empty( $input['add_to_mobile_menu'] ) ? 1 : 0;
    $output['add_to_footer_menus']      = ! empty( $input['add_to_footer_menus'] ) ? 1 : 0;

    $output['menu_label'] = isset( $input['menu_label'] ) ? sanitize_text_field( $input['menu_label'] ) : $defaults['menu_label'];
    if ( $output['menu_label'] === '' ) {
        $output['menu_label'] = $defaults['menu_label'];
    }

    $output['page_title'] = isset( $input['page_title'] ) ? sanitize_text_field( $input['page_title'] ) : $defaults['page_title'];
    if ( $output['page_title'] === '' ) {
        $output['page_title'] = $defaults['page_title'];
    }

    $slug = isset( $input['page_slug'] ) ? sanitize_title( $input['page_slug'] ) : $defaults['page_slug'];
    $output['page_slug'] = $slug !== '' ? $slug : $defaults['page_slug'];

    $output['enable_self_repair'] = ! empty( $input['enable_self_repair'] ) ? 1 : 0;

    baby_vp_reset_setup_done();

    return $output;
}

function baby_vp_render_settings_field( $args ) {
    $key      = isset( $args['key'] ) ? $args['key'] : '';
    $settings = baby_vp_get_settings();
    $value    = isset( $settings[ $key ] ) ? $settings[ $key ] : '';
    $name     = 'baby_vp_settings[' . $key . ']';

    $checkboxes = [
        'auto_create_page',
        'menu_integration_enabled',
        'add_to_primary_menu',
        'add_to_mobile_menu',
        'add_to_footer_menus',
        'enable_self_repair',
    ];

    if ( in_array( $key, $checkboxes, true ) ) {
        echo '<label><input type="checkbox" name="' . esc_attr( $name ) . '" value="1" ' . checked( ! empty( $value ), true, false ) . '> Enable</label>';

        if ( 'menu_integration_enabled' === $key ) {
            echo '<p class="description">Disabled by default. Fresh installs and plugin updates will not edit menus unless you enable this.</p>';
        } elseif ( 'add_to_footer_menus' === $key ) {
            echo '<p class="description">Adds the page to all assigned footer-like menu locations when menu integration is enabled.</p>';
        }

        return;
    }

    $type = $key === 'admin_email' ? 'email' : 'text';
    echo '<input type="' . esc_attr( $type ) . '" class="regular-text" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '">';

    if ( 'admin_email' === $key ) {
        echo '<p class="description">Leave blank to disable admin notification emails.</p>';
    }
}

function baby_vp_render_settings_page() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return;
    }
    ?>
    <div class="wrap">
        <h1>Verify Paystack Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'baby_vp_settings_group' );
            do_settings_sections( 'baby-vp-settings' );
            submit_button();
            ?>
        </form>
    </div>
    <?php
}
