<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$page_id    = (int) get_option( 'baby_vp_created_page_id', 0 );
$page_owned = (bool) get_option( 'baby_vp_page_owned', 0 );

if ( $page_id && $page_owned ) {
    $post = get_post( $page_id );
    if ( $post && $post->post_type === 'page' ) {
        wp_delete_post( $page_id, true );
    }
}

delete_option( 'baby_vp_created_page_id' );
delete_option( 'baby_vp_page_owned' );
delete_option( 'baby_vp_created_menu_items' );
delete_option( 'baby_vp_setup_done' );
delete_option( 'baby_vp_settings' );
