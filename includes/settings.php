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
        'auto_create_page'         => 'Auto-create tracking page',
        'menu_integration_enabled' => 'Enable menu integration',
        'menu_label'               => 'Menu label',
        'page_title'               => 'Page title',
        'page_slug'                => 'Page slug',
        'enable_self_repair'       => 'Enable self-repair',
    ];
}

function baby_vp_get_settings_defaults() {
    return [
        'admin_email'              => '',
        'auto_create_page'         => 1,
        'menu_integration_enabled' => 0,
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
    } else {
        baby_vp_log( 'settings', 'Plugin settings saved with no detected changes.', [] );
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

    $output['auto_create_page']         = ! empty( $input['auto_create_page'] ) ? 1 : 0;
    $output['menu_integration_enabled'] = ! empty( $input['menu_integration_enabled'] ) ? 1 : 0;

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

    $old_settings = baby_vp_get_settings();

    baby_vp_reset_setup_done();
    baby_vp_log_settings_changes( $old_settings, $output );

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
        'enable_self_repair',
    ];

    if ( in_array( $key, $checkboxes, true ) ) {
        echo '<label><input type="checkbox" name="' . esc_attr( $name ) . '" value="1" ' . checked( ! empty( $value ), true, false ) . '> Enable</label>';

        if ( 'menu_integration_enabled' === $key ) {
            echo '<p class="description">Disabled by default. Fresh installs and plugin updates will not edit menus unless you enable this.</p>';
            echo baby_vp_get_menu_location_status_html();
        }

        return;
    }

    $type = $key === 'admin_email' ? 'email' : 'text';
    echo '<input type="' . esc_attr( $type ) . '" class="regular-text" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '">';

    if ( 'admin_email' === $key ) {
        echo '<p class="description">Leave blank to disable admin notification emails.</p>';
    }
}

function baby_vp_get_menu_location_status_html() {
    if ( ! function_exists( 'get_nav_menu_locations' ) ) {
        return '<p class="description">Menu status: navigation menus are not available on this site.</p>';
    }

    $locations = get_nav_menu_locations();
    $found = [
        'primary' => [],
        'mobile'  => [],
        'footer'  => [],
    ];

    if ( is_array( $locations ) ) {
        foreach ( $locations as $location_slug => $menu_id ) {
            if ( ! $menu_id ) {
                continue;
            }

            $type = function_exists( 'baby_vp_get_menu_location_type' ) ? baby_vp_get_menu_location_type( $location_slug ) : '';
            if ( $type && isset( $found[ $type ] ) ) {
                $found[ $type ][] = (string) $location_slug;
            }
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
    echo '<p>';
    echo '<a href="' . esc_url( admin_url( 'admin.php?page=baby-vp-diagnostics' ) ) . '" class="button" style="margin-right:8px;">Open Diagnostics</a>';
    echo '<a href="' . esc_url( admin_url( 'tools.php?page=baby-vp-setup' ) ) . '" class="button">Open Setup Page</a>';
    echo '</p>';
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
        <p>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=baby_vp' ) ); ?>" class="button">Open WooCommerce Settings Tab</a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=baby-vp-diagnostics' ) ); ?>" class="button">Open Diagnostics</a>
            <a href="<?php echo esc_url( admin_url( 'tools.php?page=baby-vp-setup' ) ); ?>" class="button">Open Setup Page</a>
        </p>
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
