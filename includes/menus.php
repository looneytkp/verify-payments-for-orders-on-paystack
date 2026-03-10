<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function baby_vp_maybe_add_fix_order_issues_menu_item( $page_id = 0 ) {
    if ( ! function_exists( 'wp_get_nav_menu_items' ) || ! baby_vp_menu_integration_enabled() ) {
        baby_vp_cleanup_fix_order_issues_menu_items();
        return;
    }

    $page_id = $page_id ? (int) $page_id : baby_vp_get_or_create_track_orders_page();
    if ( ! $page_id ) {
        return;
    }

    $page_url = get_permalink( $page_id );
    if ( ! $page_url ) {
        return;
    }

    $locations = get_nav_menu_locations();
    if ( empty( $locations ) || ! is_array( $locations ) ) {
        baby_vp_cleanup_fix_order_issues_menu_items();
        return;
    }

    $selected_locations = function_exists( 'baby_vp_get_selected_menu_locations' ) ? baby_vp_get_selected_menu_locations() : [];
    if ( empty( $selected_locations ) ) {
        baby_vp_cleanup_fix_order_issues_menu_items();
        return;
    }

    $created_menu_items = baby_vp_get_created_menu_items();
    $processed_menu_ids = [];

    baby_vp_cleanup_fix_order_issues_menu_items( $selected_locations, $locations, $created_menu_items );

    foreach ( $locations as $location_slug => $menu_id ) {
        $menu_id = (int) $menu_id;

        if ( ! $menu_id || ! in_array( $location_slug, $selected_locations, true ) || ! baby_vp_is_target_menu_location( $location_slug ) ) {
            continue;
        }

        if ( in_array( $menu_id, $processed_menu_ids, true ) ) {
            continue;
        }

        $processed_menu_ids[] = $menu_id;

        $items = wp_get_nav_menu_items( $menu_id );
        if ( ! is_array( $items ) ) {
            $items = [];
        }

        $existing_item_id = baby_vp_find_existing_fix_link_menu_item_id( $items, $page_id, $page_url, $menu_id, $created_menu_items );
        if ( $existing_item_id > 0 ) {
            baby_vp_sync_fix_order_issues_menu_item( $menu_id, $existing_item_id, $page_id );
            $created_menu_items[ $menu_id ] = $existing_item_id;
            continue;
        }

        $new_item_db_id = wp_update_nav_menu_item( $menu_id, 0, [
            'menu-item-title'     => baby_vp_get_menu_label(),
            'menu-item-object'    => 'page',
            'menu-item-object-id' => $page_id,
            'menu-item-type'      => 'post_type',
            'menu-item-status'    => 'publish',
            'menu-item-parent-id' => 0,
            'menu-item-position'  => 999,
        ] );

        if ( ! $new_item_db_id || is_wp_error( $new_item_db_id ) ) {
            continue;
        }

        $created_menu_items[ $menu_id ] = (int) $new_item_db_id;

        if ( function_exists( 'baby_vp_log' ) ) {
            baby_vp_log( 'menus', 'Fix Order Issues menu item added.', [
                'menu_id'      => $menu_id,
                'menu_item_id' => (int) $new_item_db_id,
                'location'     => (string) $location_slug,
                'page_id'      => $page_id,
                'type'         => baby_vp_get_menu_location_type( $location_slug ),
            ] );
        }
    }

    baby_vp_set_created_menu_items( $created_menu_items );
}


function baby_vp_sync_fix_order_issues_menu_item( $menu_id, $menu_item_id, $page_id ) {
    $menu_id      = (int) $menu_id;
    $menu_item_id = (int) $menu_item_id;
    $page_id      = (int) $page_id;

    if ( ! $menu_id || ! $menu_item_id || ! $page_id ) {
        return;
    }

    wp_update_nav_menu_item( $menu_id, $menu_item_id, [
        'menu-item-title'     => baby_vp_get_menu_label(),
        'menu-item-object'    => 'page',
        'menu-item-object-id' => $page_id,
        'menu-item-type'      => 'post_type',
        'menu-item-status'    => 'publish',
        'menu-item-parent-id' => 0,
    ] );
}

function baby_vp_find_existing_fix_link_menu_item_id( array $items, $page_id, $page_url, $menu_id, array $created_menu_items ) {
    $known_item_id = isset( $created_menu_items[ $menu_id ] ) ? (int) $created_menu_items[ $menu_id ] : 0;
    $menu_label    = strtolower( sanitize_text_field( baby_vp_get_menu_label() ) );
    $page_id       = (int) $page_id;
    $page_url      = untrailingslashit( (string) $page_url );

    foreach ( $items as $item ) {
        $item_id        = isset( $item->ID ) ? (int) $item->ID : 0;
        $item_object_id = isset( $item->object_id ) ? (int) $item->object_id : 0;
        $item_type      = isset( $item->type ) ? (string) $item->type : '';
        $item_object    = isset( $item->object ) ? (string) $item->object : '';
        $item_title     = isset( $item->title ) ? trim( strtolower( wp_strip_all_tags( $item->title ) ) ) : '';
        $item_url       = isset( $item->url ) ? untrailingslashit( (string) $item->url ) : '';

        if ( $known_item_id && $item_id === $known_item_id ) {
            return $item_id;
        }

        if ( $item_type === 'post_type' && $item_object === 'page' && $item_object_id === $page_id ) {
            return $item_id;
        }

        if ( $item_object_id === $page_id ) {
            return $item_id;
        }

        if ( $page_url && $item_url && $item_url === $page_url ) {
            return $item_id;
        }

        if ( $item_title === $menu_label && $page_url && $item_url && $item_url === $page_url ) {
            return $item_id;
        }
    }

    return 0;
}

function baby_vp_is_target_menu_location( $location_slug ) {
    return baby_vp_get_menu_location_type( $location_slug ) !== '';
}

function baby_vp_get_menu_location_type( $location_slug ) {
    $location_slug = strtolower( (string) $location_slug );

    if ( strpos( $location_slug, 'footer' ) === 0 || strpos( $location_slug, 'footer' ) !== false ) {
        return 'footer';
    }

    if ( strpos( $location_slug, 'mobile' ) === 0 || strpos( $location_slug, 'mobile' ) !== false ) {
        return 'mobile';
    }

    if ( strpos( $location_slug, 'primary' ) === 0 || strpos( $location_slug, 'primary' ) !== false ) {
        return 'primary';
    }

    $footer_targets = [ 'bottom-footer', 'footer-menu', 'footer_menu' ];
    foreach ( $footer_targets as $target ) {
        if ( $location_slug === $target || strpos( $location_slug, $target ) !== false ) {
            return 'footer';
        }
    }

    $mobile_targets = [ 'handheld', 'offcanvas', 'drawer', 'sidemenu', 'responsive', 'responsive-menu', 'mobile-menu', 'phone', 'tablet' ];
    foreach ( $mobile_targets as $target ) {
        if ( $location_slug === $target || strpos( $location_slug, $target ) !== false ) {
            return 'mobile';
        }
    }

    $primary_targets = [ 'main', 'main-menu', 'header', 'top', 'desktop', 'navigation', 'nav', 'menu-1' ];
    foreach ( $primary_targets as $target ) {
        if ( $location_slug === $target || strpos( $location_slug, $target ) !== false ) {
            return 'primary';
        }
    }

    return '';
}


function baby_vp_cleanup_fix_order_issues_menu_items( $selected_locations = null, $locations = null, $created_menu_items = null ) {
    if ( ! function_exists( 'wp_delete_post' ) ) {
        return;
    }

    if ( null === $selected_locations ) {
        $selected_locations = function_exists( 'baby_vp_get_selected_menu_locations' ) ? baby_vp_get_selected_menu_locations() : [];
    }

    if ( null === $locations ) {
        $locations = function_exists( 'get_nav_menu_locations' ) ? get_nav_menu_locations() : [];
    }

    if ( null === $created_menu_items ) {
        $created_menu_items = baby_vp_get_created_menu_items();
    }

    $selected_menu_ids = [];
    if ( is_array( $locations ) ) {
        foreach ( $selected_locations as $location_slug ) {
            if ( ! empty( $locations[ $location_slug ] ) ) {
                $selected_menu_ids[] = (int) $locations[ $location_slug ];
            }
        }
    }

    $selected_menu_ids = array_values( array_unique( array_filter( $selected_menu_ids ) ) );
    $updated_items      = is_array( $created_menu_items ) ? $created_menu_items : [];

    foreach ( $updated_items as $menu_id => $menu_item_id ) {
        $menu_id      = (int) $menu_id;
        $menu_item_id = (int) $menu_item_id;

        if ( ! $menu_id || ! $menu_item_id ) {
            unset( $updated_items[ $menu_id ] );
            continue;
        }

        if ( in_array( $menu_id, $selected_menu_ids, true ) ) {
            continue;
        }

        wp_delete_post( $menu_item_id, true );
        unset( $updated_items[ $menu_id ] );
    }

    baby_vp_set_created_menu_items( $updated_items );
}
