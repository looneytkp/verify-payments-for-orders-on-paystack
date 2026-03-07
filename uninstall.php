<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$delete_for_blog = function() {
    $page_id    = (int) get_option( 'baby_vp_created_page_id', 0 );
    $page_owned = (bool) get_option( 'baby_vp_page_owned', 0 );
    $menu_items = get_option( 'baby_vp_created_menu_items', [] );

    if ( is_array( $menu_items ) ) {
        foreach ( $menu_items as $menu_id => $item_id ) {
            $item_id = (int) $item_id;
            if ( $item_id && wp_get_nav_menu_item( $item_id ) ) {
                wp_delete_post( $item_id, true );
            }
        }
    }

    if ( $page_id && $page_owned ) {
        $post = get_post( $page_id );
        if ( $post && $post->post_type === 'page' ) {
            wp_delete_post( $page_id, true );
        }
    }

    delete_option( 'baby_vp_settings' );
    delete_option( 'baby_vp_setup_done' );
    delete_option( 'baby_vp_created_page_id' );
    delete_option( 'baby_vp_created_menu_items' );
    delete_option( 'baby_vp_page_owned' );
    delete_option( 'baby_vp_last_healthcheck' );
};

if ( is_multisite() ) {
    $site_ids = get_sites( [ 'fields' => 'ids' ] );
    foreach ( $site_ids as $site_id ) {
        switch_to_blog( $site_id );
        $delete_for_blog();
        restore_current_blog();
    }
} else {
    $delete_for_blog();
}
