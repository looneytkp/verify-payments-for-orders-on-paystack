<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function baby_vp_get_default_admin_email() {
    $defaults = function_exists( 'baby_vp_get_settings_defaults' ) ? baby_vp_get_settings_defaults() : [];
    return isset( $defaults['admin_email'] ) ? $defaults['admin_email'] : 'verifypaystack@lollarodenterprise.com';
}

function baby_vp_admin_email() {
    $email = function_exists( 'baby_vp_get_setting' ) ? baby_vp_get_setting( 'admin_email', baby_vp_get_default_admin_email() ) : baby_vp_get_default_admin_email();
    $email = sanitize_email( $email );

    return ! empty( $email ) ? $email : baby_vp_get_default_admin_email();
}

function baby_vp_auto_create_page_enabled() {
    return (bool) ( function_exists( 'baby_vp_get_setting' ) ? baby_vp_get_setting( 'auto_create_page', 1 ) : 1 );
}

function baby_vp_auto_create_menu_enabled() {
    return (bool) ( function_exists( 'baby_vp_get_setting' ) ? baby_vp_get_setting( 'auto_create_menu', 1 ) : 1 );
}

function baby_vp_get_menu_label() {
    $label = function_exists( 'baby_vp_get_setting' ) ? baby_vp_get_setting( 'menu_label', 'Fix Order Issues' ) : 'Fix Order Issues';
    $label = sanitize_text_field( $label );

    return $label !== '' ? $label : 'Fix Order Issues';
}

function baby_vp_self_repair_enabled() {
    return (bool) ( function_exists( 'baby_vp_get_setting' ) ? baby_vp_get_setting( 'enable_self_repair', 1 ) : 1 );
}

function baby_vp_get_track_orders_page_content() {
    $content = '[baby_vp_fix_order_issues]';

    return (string) apply_filters( 'baby_vp_track_orders_page_content', $content );
}

function baby_vp_get_track_orders_page_slug() {
    $slug = function_exists( 'baby_vp_get_setting' ) ? baby_vp_get_setting( 'page_slug', 'track-orders' ) : 'track-orders';
    $slug = sanitize_title( $slug );

    return $slug !== '' ? $slug : 'track-orders';
}

function baby_vp_get_track_orders_page_title() {
    $title = function_exists( 'baby_vp_get_setting' ) ? baby_vp_get_setting( 'page_title', 'Track Orders' ) : 'Track Orders';
    $title = sanitize_text_field( $title );

    return $title !== '' ? $title : 'Track Orders';
}

function baby_vp_get_option( $key, $default = null ) {
    return get_option( $key, $default );
}

function baby_vp_update_option( $key, $value ) {
    return update_option( $key, $value, false );
}

function baby_vp_mark_setup_done() {
    baby_vp_update_option( BABY_VP_OPTION_SETUP_DONE, 1 );
}

function baby_vp_reset_setup_done() {
    delete_option( BABY_VP_OPTION_SETUP_DONE );
}

function baby_vp_is_setup_done() {
    return (bool) baby_vp_get_option( BABY_VP_OPTION_SETUP_DONE, 0 );
}

function baby_vp_get_created_page_id() {
    return (int) baby_vp_get_option( BABY_VP_OPTION_CREATED_PAGE_ID, 0 );
}

function baby_vp_set_created_page_id( $page_id ) {
    baby_vp_update_option( BABY_VP_OPTION_CREATED_PAGE_ID, (int) $page_id );
}

function baby_vp_is_track_page_owned() {
    return (bool) baby_vp_get_option( BABY_VP_OPTION_PAGE_OWNED, 0 );
}

function baby_vp_set_track_page_owned( $owned ) {
    baby_vp_update_option( BABY_VP_OPTION_PAGE_OWNED, $owned ? 1 : 0 );
}

function baby_vp_get_created_menu_items() {
    $items = baby_vp_get_option( BABY_VP_OPTION_CREATED_MENU_ITEMS, [] );
    return is_array( $items ) ? $items : [];
}

function baby_vp_set_created_menu_items( array $items ) {
    baby_vp_update_option( BABY_VP_OPTION_CREATED_MENU_ITEMS, $items );
}

function baby_vp_register_hooks() {
    register_activation_hook( BABY_VP_FILE, 'baby_vp_on_activate' );
    add_action( 'wp_initialize_site', 'baby_vp_on_new_site', 10, 2 );
    add_action( 'init', 'baby_vp_maybe_run_setup', 20 );
    add_action( 'init', 'baby_vp_maybe_self_repair', 21 );
    add_action( 'after_switch_theme', 'baby_vp_flag_setup_for_rerun' );
    add_action( 'wp_update_nav_menu', 'baby_vp_flag_setup_for_rerun_on_menu_update', 10, 2 );
    add_action( 'customize_save_after', 'baby_vp_flag_setup_for_rerun' );
}

function baby_vp_flag_setup_for_rerun() {
    baby_vp_reset_setup_done();
}

function baby_vp_flag_setup_for_rerun_on_menu_update( $menu_id = 0, $menu_data = [] ) {
    baby_vp_reset_setup_done();
}

function baby_vp_on_activate( $network_wide ) {
    if ( is_multisite() && $network_wide ) {
        $site_ids = get_sites( [ 'fields' => 'ids' ] );

        foreach ( $site_ids as $site_id ) {
            switch_to_blog( $site_id );
            baby_vp_reset_setup_done();
            baby_vp_run_setup();
            restore_current_blog();
        }

        return;
    }

    baby_vp_reset_setup_done();
    baby_vp_run_setup();
}

function baby_vp_on_new_site( $new_site, $args ) {
    if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    if ( ! is_plugin_active_for_network( plugin_basename( BABY_VP_FILE ) ) ) {
        return;
    }

    switch_to_blog( $new_site->blog_id );
    baby_vp_reset_setup_done();
    baby_vp_run_setup();
    restore_current_blog();
}

function baby_vp_maybe_run_setup() {
    if ( baby_vp_is_setup_done() ) {
        return;
    }

    baby_vp_run_setup();
}

function baby_vp_run_setup() {
    $page_id = baby_vp_get_or_create_track_orders_page();

    if ( baby_vp_auto_create_menu_enabled() && $page_id ) {
        baby_vp_maybe_add_fix_order_issues_menu_item( $page_id );
    }

    if ( $page_id || ! baby_vp_auto_create_page_enabled() ) {
        baby_vp_mark_setup_done();
    }
}

function baby_vp_get_or_create_track_orders_page() {
    $stored_page_id = baby_vp_get_created_page_id();
    $page           = $stored_page_id ? get_post( $stored_page_id ) : null;

    if ( $page && $page->post_type === 'page' ) {
        baby_vp_maybe_update_plugin_page( $page->ID );
        return (int) $page->ID;
    }

    $slug = baby_vp_get_track_orders_page_slug();
    $page = get_page_by_path( $slug, OBJECT, 'page' );

    if ( $page && ! empty( $page->ID ) ) {
        baby_vp_set_created_page_id( (int) $page->ID );
        baby_vp_set_track_page_owned( 0 );
        baby_vp_maybe_update_plugin_page( $page->ID );
        return (int) $page->ID;
    }

    if ( ! baby_vp_auto_create_page_enabled() ) {
        return 0;
    }

    $page_id = wp_insert_post( [
        'post_title'   => baby_vp_get_track_orders_page_title(),
        'post_name'    => $slug,
        'post_content' => baby_vp_get_track_orders_page_content(),
        'post_status'  => 'publish',
        'post_type'    => 'page',
    ] );

    if ( is_wp_error( $page_id ) || ! $page_id ) {
        return 0;
    }

    baby_vp_set_created_page_id( (int) $page_id );
    baby_vp_set_track_page_owned( 1 );
    baby_vp_log( 'setup', 'Tracking page created.', [ 'page_id' => (int) $page_id ] );

    return (int) $page_id;
}

function baby_vp_maybe_update_plugin_page( $page_id ) {
    $page_id = (int) $page_id;
    if ( ! $page_id ) {
        return;
    }

    if ( ! baby_vp_is_track_page_owned() ) {
        return;
    }

    $post = get_post( $page_id );
    if ( ! $post || $post->post_type !== 'page' ) {
        return;
    }

    $new_title   = baby_vp_get_track_orders_page_title();
    $new_slug    = baby_vp_get_track_orders_page_slug();
    $new_content = baby_vp_get_track_orders_page_content();

    $updates = [ 'ID' => $page_id ];
    $changed = false;

    if ( $post->post_title !== $new_title ) {
        $updates['post_title'] = $new_title;
        $changed = true;
    }

    if ( $post->post_name !== $new_slug ) {
        $updates['post_name'] = $new_slug;
        $changed = true;
    }

    if ( trim( (string) $post->post_content ) !== trim( (string) $new_content ) ) {
        $updates['post_content'] = $new_content;
        $changed = true;
    }

    if ( $changed ) {
        wp_update_post( $updates );
        baby_vp_log( 'setup', 'Tracking page updated.', [ 'page_id' => $page_id ] );
    }
}

function baby_vp_maybe_self_repair() {
    if ( ! baby_vp_self_repair_enabled() ) {
        return;
    }

    if ( is_admin() && ! wp_doing_ajax() ) {
        return;
    }

    $now  = time();
    $last = (int) baby_vp_get_option( BABY_VP_OPTION_LAST_HEALTHCHECK, 0 );

    if ( $last && ( $now - $last ) < 6 * HOUR_IN_SECONDS ) {
        return;
    }

    baby_vp_update_option( BABY_VP_OPTION_LAST_HEALTHCHECK, $now );

    $needs_repair = false;
    $page_id      = baby_vp_get_created_page_id();

    if ( baby_vp_auto_create_page_enabled() ) {
        if ( ! $page_id || ! get_post( $page_id ) ) {
            $needs_repair = true;
        } else {
            $post = get_post( $page_id );
            if ( baby_vp_is_track_page_owned() && $post && strpos( (string) $post->post_content, 'baby_vp_fix_order_issues' ) === false && strpos( (string) $post->post_content, 'woocommerce_order_tracking' ) === false ) {
                $needs_repair = true;
            }
        }
    }

    if ( ! $needs_repair && baby_vp_auto_create_menu_enabled() ) {
        foreach ( baby_vp_get_created_menu_items() as $menu_id => $item_id ) {
            if ( ! wp_get_nav_menu_item( (int) $item_id ) ) {
                $needs_repair = true;
                break;
            }
        }
    }

    if ( ! $needs_repair ) {
        return;
    }

    baby_vp_log( 'setup', 'Self-repair triggered.', [
        'page_id' => $page_id,
    ] );

    baby_vp_reset_setup_done();
    baby_vp_run_setup();
}

if ( function_exists( 'add_action' ) ) {
    add_action( 'wpmu_new_blog', 'baby_vp_on_new_blog_legacy', 10, 6 );
}

function baby_vp_on_new_blog_legacy( $blog_id, $user_id, $domain, $path, $site_id, $meta ) {
    if ( ! is_multisite() ) {
        return;
    }

    if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    if ( ! is_plugin_active_for_network( plugin_basename( BABY_VP_FILE ) ) ) {
        return;
    }

    switch_to_blog( (int) $blog_id );
    baby_vp_reset_setup_done();
    baby_vp_run_setup();
    restore_current_blog();
}
