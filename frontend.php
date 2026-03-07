<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'wp_enqueue_scripts', 'baby_vp_enqueue_frontend_assets' );

function baby_vp_enqueue_frontend_assets() {
    if ( ! baby_vp_should_enqueue_frontend_assets() ) {
        return;
    }

    wp_enqueue_style(
        'baby-vp-frontend',
        BABY_VP_URL . 'assets/css/frontend.css',
        [],
        BABY_VP_VERSION
    );

    wp_enqueue_script(
        'baby-vp-frontend',
        BABY_VP_URL . 'assets/js/frontend.js',
        [],
        BABY_VP_VERSION,
        true
    );

    wp_localize_script(
        'baby-vp-frontend',
        'babyVpFrontend',
        [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'trackNonce' => wp_create_nonce( 'baby_track_by_paystack' ),
            'messages' => [
                'verifying'              => 'Verifying...',
                'verified'               => 'Verified ✓',
                'statusChanged'          => 'Order changed to Processing ✓',
                'notificationSent'       => 'Order notification sent ✓',
                'verificationFailed'     => 'Verification failed.',
                'networkError'           => 'Network error',
                'checkingDetails'        => 'Checking details...',
                'trackingFormNotFound'   => 'Tracking form not found on page.',
                'trackingInputsNotFound' => 'Tracking inputs not found.',
                'noMatchingOrder'        => 'No matching order found.',
                'trackNetworkError'      => 'Network error. Please try again.',
                'missingTrackFields'     => 'Please enter your Paystack reference and billing email.',
            ],
            'isTrackPage' => baby_vp_is_current_track_orders_page() ? 1 : 0,
        ]
    );
}

function baby_vp_should_enqueue_frontend_assets() {
    if ( is_admin() ) {
        return false;
    }

    if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
        return true;
    }

    if ( function_exists( 'is_view_order_page' ) && is_view_order_page() ) {
        return true;
    }

    if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'view-order' ) ) {
        return true;
    }

    if ( baby_vp_is_current_track_orders_page() ) {
        return true;
    }

    return false;
}

function baby_vp_is_current_track_orders_page() {
    $page_id = baby_vp_get_created_page_id();

    if ( $page_id && is_page( $page_id ) ) {
        return true;
    }

    $slug = baby_vp_get_track_orders_page_slug();

    return $slug !== '' && is_page( $slug );
}

/* ---------------------------------------------------------
AUTO BADGE + EMAIL NOTICE
----------------------------------------------------------*/

function baby_render_paid_badge_and_email_notice($order) {

    if (!$order || !is_a($order, 'WC_Order')) {
        return;
    }

    $st = $order->get_status();

    if (in_array($st, ['processing', 'completed', 'pending', 'on-hold'], true)) {

        if ($st === 'completed')      $label = 'Order completed ✓';
        elseif ($st === 'processing') $label = 'Order is processing ✓';
        elseif ($st === 'pending')    $label = 'Awaiting payment (Pending)';
        else                          $label = 'On hold';

        echo '<div class="baby-vp-badge-row">';

        $cls = in_array($st, ['processing', 'completed'], true) ? 'is-success' : 'is-info';
        echo '<span class="baby-vp-badge ' . esc_attr($cls) . '"><span class="dot"></span> ' . esc_html($label) . '</span>';

        $ts = (int)$order->get_meta('_baby_vp_email_notice_ts');

        if ($ts > 0) {
            if (time() - $ts <= 600) {
                echo '<span class="baby-vp-badge is-info"><span class="dot"></span> Order notification sent to your email ✓</span>';
            }

            $order->delete_meta_data('_baby_vp_email_notice_ts');
            $order->save();
        }

        echo '</div>';
    }
}

/* ---------------------------------------------------------
SHOW VERIFY BUTTON (ORDER PAGE)
----------------------------------------------------------*/

add_action('woocommerce_order_details_after_order_table', function($order) {

    if (!$order) return;

    baby_render_paid_badge_and_email_notice($order);

    echo '<div class="baby-vp-action-row">';

    $page_id  = baby_vp_get_or_create_track_orders_page();
    $page_url = $page_id ? get_permalink( $page_id ) : home_url( '/' . trim( baby_vp_get_track_orders_page_slug(), '/' ) . '/' );

    echo '<a href="'.esc_url($page_url).'" class="button baby-vp-track-another">Track another order</a>';

    if (in_array($order->get_status(), ['cancelled','pending'], true) && $order->get_payment_method() === 'paystack') {

        $order_id  = $order->get_id();
        $order_key = $order->get_order_key();
        $nonce     = wp_create_nonce('baby_verify_' . $order_id . '_' . $order_key);

        echo '<button class="button baby_verify_payment baby-vp-verify-button"
            data-order="' . esc_attr($order_id) . '"
            data-key="' . esc_attr($order_key) . '"
            data-nonce="' . esc_attr($nonce) . '">
            Verify payment
        </button>';

        echo '<span class="baby-vp-badge"></span>';
    }

    echo '</div>';

}, 20);
