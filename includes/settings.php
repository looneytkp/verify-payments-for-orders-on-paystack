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
    add_action( 'update_option_baby_vp_settings', 'baby_vp_after_settings_update', 10, 3 );
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


function baby_vp_render_settings_page() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return;
    }

    echo '<div class="wrap">';
    echo '<h1>Verify Paystack Settings</h1>';

    if ( isset( $_GET['settings-updated'] ) && 'true' === sanitize_text_field( wp_unslash( $_GET['settings-updated'] ) ) ) {
        echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
    }

    echo '<form method="post" action="options.php">';
    settings_fields( 'baby_vp_settings_group' );
    do_settings_sections( 'baby-vp-settings' );
    submit_button( 'Save Changes' );
    echo '</form>';
    echo '</div>';
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

function baby_vp_get_default_email_notice_text() {
    return 'If you have any payment issues with your orders, check and fix them here:';
}

function baby_vp_get_default_menu_item_text() {
    return 'Fix Order Issues';
}

function baby_vp_get_settings_fields() {
    return [
        'admin_email'       => 'Admin notification email',
        'email_notice_text' => 'Email notice text',
        'menu_item_text'    => 'Menu item text',
        'menu_locations'    => 'Menu integration',
    ];
}

function baby_vp_get_settings_defaults() {
    return [
        'admin_email'       => '',
        'email_notice_text' => baby_vp_get_default_email_notice_text(),
        'menu_item_text'    => baby_vp_get_default_menu_item_text(),
        'menu_locations'    => [],
    ];
}

function baby_vp_get_settings() {
    $defaults = baby_vp_get_settings_defaults();
    $settings = get_option( 'baby_vp_settings', [] );

    if ( ! is_array( $settings ) ) {
        $settings = [];
    }

    $settings = wp_parse_args( $settings, $defaults );
    $settings['menu_locations'] = baby_vp_normalize_menu_locations( isset( $settings['menu_locations'] ) ? $settings['menu_locations'] : [] );

    return $settings;
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

function baby_vp_normalize_menu_locations( $locations ) {
    if ( ! is_array( $locations ) ) {
        return [];
    }

    $locations = array_map( 'sanitize_key', $locations );
    $locations = array_filter( $locations, 'strlen' );

    return array_values( array_unique( $locations ) );
}

function baby_vp_get_detected_menu_locations() {
    $detected = [];

    if ( ! function_exists( 'get_registered_nav_menus' ) ) {
        return $detected;
    }

    $registered = get_registered_nav_menus();
    $assigned   = function_exists( 'get_nav_menu_locations' ) ? get_nav_menu_locations() : [];

    if ( ! is_array( $registered ) ) {
        return $detected;
    }

    foreach ( $registered as $location_slug => $description ) {
        if ( ! function_exists( 'baby_vp_get_menu_location_type' ) || '' === baby_vp_get_menu_location_type( $location_slug ) ) {
            continue;
        }

        $location_slug = sanitize_key( $location_slug );
        $menu_name     = '';

        if ( ! empty( $assigned[ $location_slug ] ) ) {
            $menu_obj = wp_get_nav_menu_object( (int) $assigned[ $location_slug ] );
            if ( $menu_obj && ! is_wp_error( $menu_obj ) && ! empty( $menu_obj->name ) ) {
                $menu_name = (string) $menu_obj->name;
            }
        }

        $detected[ $location_slug ] = [
            'slug'        => $location_slug,
            'menu_name'   => $menu_name,
            'description' => (string) $description,
            'assigned'    => ! empty( $assigned[ $location_slug ] ),
        ];
    }

    return $detected;
}

function baby_vp_get_selected_menu_locations() {
    $saved    = baby_vp_normalize_menu_locations( baby_vp_get_setting( 'menu_locations', [] ) );
    $detected = baby_vp_get_detected_menu_locations();

    if ( empty( $detected ) ) {
        return [];
    }

    return array_values( array_intersect( $saved, array_keys( $detected ) ) );
}

function baby_vp_sanitize_settings( $input ) {
    $defaults = baby_vp_get_settings_defaults();
    $output   = [];

    $input = is_array( $input ) ? $input : [];

    $output['admin_email'] = isset( $input['admin_email'] ) ? sanitize_email( $input['admin_email'] ) : $defaults['admin_email'];
    if ( ! is_email( $output['admin_email'] ) ) {
        $output['admin_email'] = '';
    }

    $output['email_notice_text'] = isset( $input['email_notice_text'] ) ? sanitize_textarea_field( $input['email_notice_text'] ) : $defaults['email_notice_text'];
    if ( '' === trim( $output['email_notice_text'] ) ) {
        $output['email_notice_text'] = $defaults['email_notice_text'];
    }

    $output['menu_item_text'] = isset( $input['menu_item_text'] ) ? sanitize_text_field( $input['menu_item_text'] ) : $defaults['menu_item_text'];
    if ( '' === trim( $output['menu_item_text'] ) ) {
        $output['menu_item_text'] = $defaults['menu_item_text'];
    }

    $detected_locations = array_keys( baby_vp_get_detected_menu_locations() );
    $requested_locations = isset( $input['menu_locations'] ) ? baby_vp_normalize_menu_locations( $input['menu_locations'] ) : [];
    $output['menu_locations'] = array_values( array_intersect( $requested_locations, $detected_locations ) );

    $old_settings = baby_vp_get_settings();
    baby_vp_log_settings_changes( $old_settings, $output );

    return $output;
}

function baby_vp_render_settings_field( $args ) {
    $key      = isset( $args['key'] ) ? $args['key'] : '';
    $settings = baby_vp_get_settings();
    $value    = isset( $settings[ $key ] ) ? $settings[ $key ] : '';
    $name     = 'baby_vp_settings[' . $key . ']';

    if ( 'menu_locations' === $key ) {
        $selected = baby_vp_get_selected_menu_locations();
        $detected = baby_vp_get_detected_menu_locations();

        if ( ! empty( $detected ) ) {
            foreach ( $detected as $location ) {
                $label = $location['slug'];
                if ( '' !== $location['menu_name'] ) {
                    $label .= ' (' . $location['menu_name'] . ')';
                }

                echo '<label style="display:block; margin-bottom:6px;"><input type="checkbox" name="' . esc_attr( $name ) . '[]" value="' . esc_attr( $location['slug'] ) . '" ' . checked( in_array( $location['slug'], $selected, true ), true, false ) . '> ' . esc_html( $label ) . '</label>';
            }
        } else {
            echo '<p class="description">No matching menu locations were found.</p>';
        }

        echo '<p class="description">If other menus do not show here, add it manually to your menu.</p>';
        return;
    }

    if ( 'email_notice_text' === $key ) {
        echo '<textarea class="large-text" rows="3" name="' . esc_attr( $name ) . '">' . esc_textarea( (string) $value ) . '</textarea>';
        echo '<p class="description">This text appears in WooCommerce customer emails.</p>';
        return;
    }

    $type = $key === 'admin_email' ? 'email' : 'text';
    echo '<input type="' . esc_attr( $type ) . '" class="regular-text" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '">';

    if ( 'admin_email' === $key ) {
        echo '<p class="description">Leave blank to disable admin notification emails.</p>';
    } elseif ( 'menu_item_text' === $key ) {
        echo '<p class="description">This text is used for the menu item added by the plugin.</p>';
    }
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

function baby_vp_after_settings_update( $old_value, $value, $option ) {
    if ( function_exists( 'baby_vp_reset_setup_done' ) ) {
        baby_vp_reset_setup_done();
    }

    if ( function_exists( 'baby_vp_get_or_create_track_orders_page' ) && function_exists( 'baby_vp_maybe_add_fix_order_issues_menu_item' ) ) {
        $page_id = baby_vp_get_or_create_track_orders_page();
        if ( $page_id ) {
            baby_vp_maybe_add_fix_order_issues_menu_item( $page_id );
        }
    }

    if ( function_exists( 'baby_vp_run_setup' ) ) {
        baby_vp_run_setup( 'settings_update' );
    }
}

function baby_vp_save_wc_settings_tab() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return;
    }

    $raw_settings = isset( $_POST['baby_vp_settings'] ) ? wp_unslash( $_POST['baby_vp_settings'] ) : [];
    $settings     = baby_vp_sanitize_settings( $raw_settings );

    update_option( 'baby_vp_settings', $settings, false );
}
