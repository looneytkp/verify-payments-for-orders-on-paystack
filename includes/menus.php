<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function baby_vp_maybe_add_fix_order_issues_menu_item( $page_id = 0 ) {
    if ( ! function_exists( 'wp_get_nav_menu_items' ) || ! baby_vp_menu_integration_enabled() ) {
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
        return;
    }

    $created_menu_items = baby_vp_get_created_menu_items();
    $processed_menu_ids = [];

    foreach ( $locations as $location_slug => $menu_id ) {
        $menu_id = (int) $menu_id;

        if ( ! $menu_id || ! baby_vp_is_target_menu_location( $location_slug ) ) {
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

        if ( baby_vp_menu_already_has_fix_link( $items, $page_id, $page_url, $menu_id, $created_menu_items ) ) {
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
                'menu_id' => $menu_id,
                'menu_item_id' => (int) $new_item_db_id,
                'location' => (string) $location_slug,
                'page_id' => $page_id,
            ] );
        }
    }

    baby_vp_set_created_menu_items( $created_menu_items );
}

function baby_vp_menu_already_has_fix_link( array $items, $page_id, $page_url, $menu_id, array $created_menu_items ) {
    return baby_vp_find_existing_fix_link_menu_item_id( $items, $page_id, $page_url, $menu_id, $created_menu_items ) > 0;
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
    $type = baby_vp_get_menu_location_type( $location_slug );

    if ( ! $type ) {
        return false;
    }

    if ( 'primary' === $type ) {
        return baby_vp_add_to_primary_menu_enabled();
    }

    if ( 'mobile' === $type ) {
        return baby_vp_add_to_mobile_menu_enabled();
    }

    if ( 'footer' === $type ) {
        return baby_vp_add_to_footer_menus_enabled();
    }

    return false;
}

function baby_vp_get_menu_location_type( $location_slug ) {
    $location_slug = strtolower( (string) $location_slug );

    $footer_targets = [ 'footer', 'bottom-footer', 'footer-menu', 'footer_menu' ];
    foreach ( $footer_targets as $target ) {
        if ( $location_slug === $target || strpos( $location_slug, $target ) !== false ) {
            return 'footer';
        }
    }

    $mobile_targets = [ 'mobile', 'handheld', 'offcanvas', 'drawer', 'sidemenu' ];
    foreach ( $mobile_targets as $target ) {
        if ( $location_slug === $target || strpos( $location_slug, $target ) !== false ) {
            return 'mobile';
        }
    }

    $primary_targets = [ 'primary', 'main', 'main-menu', 'header', 'top' ];
    foreach ( $primary_targets as $target ) {
        if ( $location_slug === $target || strpos( $location_slug, $target ) !== false ) {
            return 'primary';
        }
    }

    return '';
}
