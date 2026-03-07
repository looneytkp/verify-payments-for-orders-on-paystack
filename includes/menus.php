<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function baby_vp_maybe_add_fix_order_issues_menu_item( $page_id = 0 ) {
    if ( ! function_exists( 'wp_get_nav_menu_items' ) ) {
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

    foreach ( $locations as $location_slug => $menu_id ) {
        $menu_id = (int) $menu_id;

        if ( ! $menu_id ) {
            continue;
        }

        if ( ! baby_vp_is_target_menu_location( $location_slug ) ) {
            continue;
        }

        $items = wp_get_nav_menu_items( $menu_id );
        if ( ! is_array( $items ) ) {
            $items = [];
        }

        if ( baby_vp_menu_already_has_fix_link( $items, $page_id, $page_url, $menu_id, $created_menu_items ) ) {
            continue;
        }

        $insert_after_menu_item_id = baby_vp_find_my_account_menu_item_id( $items );

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

        if ( $insert_after_menu_item_id ) {
            baby_vp_move_menu_item_after( $menu_id, $new_item_db_id, $insert_after_menu_item_id );
        }

        $created_menu_items[ $menu_id ] = (int) $new_item_db_id;
    }

    baby_vp_set_created_menu_items( $created_menu_items );
}

function baby_vp_menu_already_has_fix_link( array $items, $page_id, $page_url, $menu_id, array $created_menu_items ) {
    $known_item_id = isset( $created_menu_items[ $menu_id ] ) ? (int) $created_menu_items[ $menu_id ] : 0;

    foreach ( $items as $item ) {
        $item_id        = isset( $item->ID ) ? (int) $item->ID : 0;
        $item_object_id = isset( $item->object_id ) ? (int) $item->object_id : 0;
        $item_title     = isset( $item->title ) ? trim( strtolower( wp_strip_all_tags( $item->title ) ) ) : '';
        $item_url       = isset( $item->url ) ? untrailingslashit( $item->url ) : '';

        if ( $known_item_id && $item_id === $known_item_id ) {
            return true;
        }

        if ( $item_object_id && $item_object_id === (int) $page_id ) {
            return true;
        }

        if ( $item_title === strtolower( sanitize_text_field( baby_vp_get_menu_label() ) ) ) {
            return true;
        }

        if ( $item_url && $item_url === untrailingslashit( $page_url ) ) {
            return true;
        }
    }

    return false;
}

function baby_vp_find_my_account_menu_item_id( array $items ) {
    foreach ( $items as $item ) {
        $title    = isset( $item->title ) ? trim( wp_strip_all_tags( $item->title ) ) : '';
        $title_lc = strtolower( $title );

        if ( in_array( $title_lc, [ 'my account', 'account', 'my-account' ], true ) ) {
            return isset( $item->ID ) ? (int) $item->ID : 0;
        }
    }

    return 0;
}

function baby_vp_is_target_menu_location( $location_slug ) {
    $location_slug = strtolower( (string) $location_slug );

    $targets = [
        'primary',
        'footer',
        'mobile',
        'main',
        'main-menu',
        'header',
        'top',
        'handheld',
        'offcanvas',
    ];

    foreach ( $targets as $target ) {
        if ( $location_slug === $target || strpos( $location_slug, $target ) !== false ) {
            return true;
        }
    }

    return false;
}

function baby_vp_move_menu_item_after( $menu_id, $new_item_id, $after_item_id ) {
    $items = wp_get_nav_menu_items( $menu_id, [ 'orderby' => 'menu_order', 'order' => 'ASC' ] );
    if ( ! is_array( $items ) ) {
        return;
    }

    $ordered_ids = [];
    $inserted    = false;

    foreach ( $items as $item ) {
        $ordered_ids[] = (int) $item->ID;

        if ( (int) $item->ID === (int) $after_item_id ) {
            $ordered_ids[] = (int) $new_item_id;
            $inserted      = true;
        }
    }

    if ( ! $inserted ) {
        return;
    }

    $seen      = [];
    $final_ids = [];

    foreach ( $ordered_ids as $id ) {
        if ( isset( $seen[ $id ] ) ) {
            continue;
        }

        $seen[ $id ]  = true;
        $final_ids[] = $id;
    }

    $position = 1;

    foreach ( $final_ids as $id ) {
        wp_update_nav_menu_item( $menu_id, $id, [
            'menu-item-position' => $position,
        ] );
        $position++;
    }
}
