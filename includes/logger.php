<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function baby_vp_get_log_site_context() {
    return [
        'site_id'   => function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 0,
        'site_url'  => function_exists( 'home_url' ) ? home_url( '/' ) : '',
        'plugin_version' => defined( 'BABY_VP_VERSION' ) ? BABY_VP_VERSION : '',
    ];
}

function baby_vp_log( $context, $message, array $data = [] ) {
    if ( ! function_exists( 'wc_get_logger' ) ) {
        return;
    }

    $logger  = wc_get_logger();
    $payload = [
        'context' => (string) $context,
        'site'    => baby_vp_get_log_site_context(),
        'data'    => $data,
    ];

    $logger->info( $message . ' ' . wp_json_encode( $payload ), [ 'source' => BABY_VP_LOG_SOURCE ] );
}
