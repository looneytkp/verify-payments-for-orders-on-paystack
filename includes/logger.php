<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function baby_vp_get_log_site_context() {
    return [
        'site_id'        => function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 0,
        'site_url'       => function_exists( 'home_url' ) ? home_url( '/' ) : '',
        'plugin_version' => defined( 'BABY_VP_VERSION' ) ? BABY_VP_VERSION : '',
    ];
}

function baby_vp_get_log_dir() {
    if ( ! function_exists( 'wp_upload_dir' ) ) {
        return '';
    }

    $uploads = wp_upload_dir( null, false );
    if ( empty( $uploads['basedir'] ) ) {
        return '';
    }

    return trailingslashit( $uploads['basedir'] ) . 'baby-vp-logs';
}

function baby_vp_get_log_file_path() {
    $dir = baby_vp_get_log_dir();
    if ( ! $dir ) {
        return '';
    }

    return trailingslashit( $dir ) . BABY_VP_LOG_SOURCE . '.log';
}

function baby_vp_ensure_log_dir() {
    $dir = baby_vp_get_log_dir();
    if ( ! $dir ) {
        return false;
    }

    if ( file_exists( $dir ) ) {
        return is_dir( $dir ) && is_writable( $dir );
    }

    if ( ! function_exists( 'wp_mkdir_p' ) ) {
        return false;
    }

    return wp_mkdir_p( $dir );
}

function baby_vp_normalize_log_level( $level ) {
    $level = strtolower( sanitize_key( (string) $level ) );
    $allowed = [ 'debug', 'info', 'warning', 'error' ];

    return in_array( $level, $allowed, true ) ? $level : 'info';
}

function baby_vp_safe_log_data( $data ) {
    if ( is_scalar( $data ) || null === $data ) {
        return $data;
    }

    if ( is_array( $data ) ) {
        foreach ( $data as $key => $value ) {
            $data[ $key ] = baby_vp_safe_log_data( $value );
        }
        return $data;
    }

    if ( is_object( $data ) ) {
        if ( $data instanceof WC_Order ) {
            return [
                'order_id' => $data->get_id(),
                'status'   => $data->get_status(),
            ];
        }

        if ( $data instanceof WP_Error ) {
            return [
                'error_code'    => $data->get_error_code(),
                'error_message' => $data->get_error_message(),
                'error_data'    => $data->get_error_data(),
            ];
        }

        return baby_vp_safe_log_data( (array) $data );
    }

    return (string) $data;
}

function baby_vp_make_log_entry( $context, $message, array $data = [], $level = 'info' ) {
    $user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;

    return [
        'timestamp' => function_exists( 'current_time' ) ? current_time( 'mysql', true ) : gmdate( 'Y-m-d H:i:s' ),
        'level'     => baby_vp_normalize_log_level( $level ),
        'context'   => sanitize_key( (string) $context ),
        'message'   => (string) $message,
        'site'      => baby_vp_get_log_site_context(),
        'request'   => [
            'user_id' => $user_id,
            'uri'     => isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
            'method'  => isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '',
            'ajax'    => function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ? 1 : 0,
            'cron'    => function_exists( 'wp_doing_cron' ) && wp_doing_cron() ? 1 : 0,
            'cli'     => defined( 'WP_CLI' ) && WP_CLI ? 1 : 0,
            'ip'      => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
        ],
        'data'      => baby_vp_safe_log_data( $data ),
    ];
}

function baby_vp_log_to_file( array $entry ) {
    $file = baby_vp_get_log_file_path();
    if ( ! $file || ! baby_vp_ensure_log_dir() ) {
        return false;
    }

    return false !== @file_put_contents( $file, wp_json_encode( $entry ) . PHP_EOL, FILE_APPEND | LOCK_EX );
}

function baby_vp_log_to_wc_logger( array $entry ) {
    if ( ! function_exists( 'wc_get_logger' ) ) {
        return;
    }

    $logger = wc_get_logger();
    if ( ! $logger ) {
        return;
    }

    $method = $entry['level'];
    if ( ! method_exists( $logger, $method ) ) {
        $method = 'info';
    }

    $message = $entry['message'] . ' ' . wp_json_encode(
        [
            'context' => $entry['context'],
            'site'    => $entry['site'],
            'request' => $entry['request'],
            'data'    => $entry['data'],
        ]
    );

    $logger->{$method}( $message, [ 'source' => BABY_VP_LOG_SOURCE ] );
}

function baby_vp_log( $context, $message, array $data = [], $level = 'info' ) {
    $entry = baby_vp_make_log_entry( $context, $message, $data, $level );
    baby_vp_log_to_file( $entry );
    baby_vp_log_to_wc_logger( $entry );
}

function baby_vp_read_recent_log_entries( $limit = 50 ) {
    $file = baby_vp_get_log_file_path();
    if ( ! $file || ! file_exists( $file ) ) {
        return [];
    }

    $lines = @file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
    if ( ! is_array( $lines ) || empty( $lines ) ) {
        return [];
    }

    $lines   = array_slice( $lines, -1 * max( 1, (int) $limit ) );
    $entries = [];

    foreach ( $lines as $line ) {
        $decoded = json_decode( (string) $line, true );
        if ( is_array( $decoded ) ) {
            $entries[] = $decoded;
        }
    }

    return array_reverse( $entries );
}

function baby_vp_get_log_file_size() {
    $file = baby_vp_get_log_file_path();
    if ( ! $file || ! file_exists( $file ) ) {
        return 0;
    }

    return (int) filesize( $file );
}

function baby_vp_clear_log_file() {
    $file = baby_vp_get_log_file_path();
    if ( ! $file ) {
        return false;
    }

    if ( ! baby_vp_ensure_log_dir() ) {
        return false;
    }

    return false !== @file_put_contents( $file, '' );
}

function baby_vp_clear_log_file_with_marker() {
    if ( ! baby_vp_clear_log_file() ) {
        return false;
    }

    $entry = baby_vp_make_log_entry(
        'diagnostics',
        'Logs cleared from diagnostics page.',
        [
            'cleared_by_user_id' => function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0,
            'cleared_at_utc'     => function_exists( 'current_time' ) ? current_time( 'mysql', true ) : gmdate( 'Y-m-d H:i:s' ),
        ],
        'warning'
    );

    $file = baby_vp_get_log_file_path();
    if ( ! $file || ! baby_vp_ensure_log_dir() ) {
        return false;
    }

    $written = false !== @file_put_contents( $file, wp_json_encode( $entry ) . PHP_EOL, LOCK_EX );
    if ( $written ) {
        baby_vp_log_to_wc_logger( $entry );
    }

    return $written;
}

function baby_vp_delete_log_files() {
    $dir = baby_vp_get_log_dir();
    if ( ! $dir || ! file_exists( $dir ) || ! is_dir( $dir ) ) {
        return;
    }

    $files = glob( trailingslashit( $dir ) . '*' );
    if ( is_array( $files ) ) {
        foreach ( $files as $file ) {
            if ( is_file( $file ) ) {
                @unlink( $file );
            }
        }
    }

    @rmdir( $dir );
}
