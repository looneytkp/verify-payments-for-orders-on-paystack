<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function baby_vp_register_settings_hooks() {
    add_action( 'admin_menu', 'baby_vp_register_settings_page' );
    add_action( 'admin_init', 'baby_vp_register_settings' );
    add_filter( 'woocommerce_settings_tabs_array', 'baby_vp_add_wc_settings_tab', 50 );
    add_action( 'woocommerce_settings_tabs_baby_vp', 'baby_vp_render_wc_settings_tab' );
    add_action( 'woocommerce_update_options_baby_vp', 'baby_vp_save_wc_settings_tab' );
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

    foreach ( baby_vp_get_settings_fields() as $key => $label ) {
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

function baby_vp_get_settings_fields() {
    return [
        'admin_email'              => 'Admin notification email',
        'menu_integration_enabled' => 'Enable menu integration',
    ];
}

function baby_vp_get_settings_defaults() {
    return [
        'admin_email'              => '',
        'menu_integration_enabled' => 0,
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

function baby_vp_get_settings_labels() {
    return baby_vp_get_settings_fields();
}

function baby_vp_log_settings_changes( array $old_settings, array $new_settings ) {
    $labels  = baby_vp_get_settings_labels();
    $changes = [];

    foreach ( $new_settings as $key => $new_value ) {
        $old_value = isset( $old_settings[ $key ] ) ? $old_settings[ $key ] : null;
        if ( $old_value === $new_value ) {
            continue;
        }

        $changes[ $key ] = [
            'label' => isset( $labels[ $key ] ) ? $labels[ $key ] : $key,
            'from'  => $old_value,
            'to'    => $new_value,
        ];
    }

    if ( ! empty( $changes ) ) {
        baby_vp_log( 'settings', 'Plugin settings saved.', [
            'changes' => $changes,
        ] );
    }
}

function baby_vp_sanitize_settings( $input ) {
    $defaults = baby_vp_get_settings_defaults();
    $output   = [];

    $input = is_array( $input ) ? $input : [];

    $output['admin_email'] = isset( $input['admin_email'] ) ? sanitize_email( $input['admin_email'] ) : $defaults['admin_email'];
    if ( ! is_email( $output['admin_email'] ) ) {
        $output['admin_email'] = '';
    }

    $output['menu_integration_enabled'] = ! empty( $input['menu_integration_enabled'] ) ? 1 : 0;

    $old_settings = baby_vp_get_settings();
    baby_vp_log_settings_changes( $old_settings, $output );

    return $output;
}

function baby_vp_render_settings_field( $args ) {
    $key      = isset( $args['key'] ) ? $args['key'] : '';
    $settings = baby_vp_get_settings();
    $value    = isset( $settings[ $key ] ) ? $settings[ $key ] : '';
    $name     = 'baby_vp_settings[' . $key . ']';

    if ( 'menu_integration_enabled' === $key ) {
        echo '<label><input type="checkbox" name="' . esc_attr( $name ) . '" value="1" ' . checked( ! empty( $value ), true, false ) . '> Enable</label>';
        echo '<p class="description">Disabled by default. The plugin will only add the Fix Order Issues link to assigned menus after you enable this.</p>';
        echo baby_vp_get_menu_location_status_html();
        return;
    }

    $type = $key === 'admin_email' ? 'email' : 'text';
    echo '<input type="' . esc_attr( $type ) . '" class="regular-text" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '">';

    if ( 'admin_email' === $key ) {
        echo '<p class="description">Leave blank to disable admin notification emails.</p>';
    }
}

function baby_vp_get_menu_location_status_html() {
    if ( ! function_exists( 'get_registered_nav_menus' ) ) {
        return '<p class="description">Menu status: navigation menus are not available on this site.</p>';
    }

    $registered = get_registered_nav_menus();
    $assigned   = function_exists( 'get_nav_menu_locations' ) ? get_nav_menu_locations() : [];
    $found      = [
        'primary' => [],
        'mobile'  => [],
        'footer'  => [],
    ];

    if ( is_array( $registered ) ) {
        foreach ( $registered as $location_slug => $description ) {
            $type = function_exists( 'baby_vp_get_menu_location_type' ) ? baby_vp_get_menu_location_type( $location_slug ) : '';
            if ( ! $type || ! isset( $found[ $type ] ) ) {
                continue;
            }

            $label = (string) $location_slug;
            if ( empty( $assigned[ $location_slug ] ) ) {
                $label .= ' (unassigned)';
            }

            $found[ $type ][] = $label;
        }
    }

    $out  = '<div class="description" style="margin-top:8px;">';
    $out .= '<strong>Detected menu locations</strong><br>';
    $out .= 'Primary: ' . ( ! empty( $found['primary'] ) ? esc_html( implode( ', ', $found['primary'] ) ) : 'Not found' ) . '<br>';
    $out .= 'Mobile: ' . ( ! empty( $found['mobile'] ) ? esc_html( implode( ', ', $found['mobile'] ) ) : 'Not found' ) . '<br>';
    $out .= 'Footer: ' . ( ! empty( $found['footer'] ) ? esc_html( implode( ', ', $found['footer'] ) ) : 'Not found' );
    $out .= '</div>';

    return $out;
}

function baby_vp_add_wc_settings_tab( $tabs ) {
    $tabs['baby_vp'] = 'Verify Paystack';
    return $tabs;
}

function baby_vp_render_wc_settings_tab() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return;
    }

    echo '<h2>Verify Paystack</h2>';
    echo '<table class="form-table" role="presentation">';

    foreach ( baby_vp_get_settings_fields() as $key => $label ) {
        echo '<tr>';
        echo '<th scope="row"><label for="baby_vp_' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th>';
        echo '<td id="baby_vp_' . esc_attr( $key ) . '">';
        baby_vp_render_settings_field( [ 'key' => $key ] );
        echo '</td>';
        echo '</tr>';
    }

    echo '</table>';
}

function baby_vp_save_wc_settings_tab() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return;
    }

    $raw_settings = isset( $_POST['baby_vp_settings'] ) ? wp_unslash( $_POST['baby_vp_settings'] ) : [];
    $settings     = baby_vp_sanitize_settings( $raw_settings );

    update_option( 'baby_vp_settings', $settings, false );

    if ( class_exists( 'WC_Admin_Settings' ) ) {
        WC_Admin_Settings::add_message( __( 'Settings saved.', 'verify-payments-for-orders-on-paystack' ) );
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
