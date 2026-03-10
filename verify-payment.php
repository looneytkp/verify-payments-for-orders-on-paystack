<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ---------------------------------------------------------
SEND EMAIL WHEN PAYMENT VERIFIED
----------------------------------------------------------*/

function baby_send_verify_admin_email( WC_Order $order, array $payment = [] ) {

    $to = baby_vp_admin_email();
    if ( empty( $to ) ) {
        baby_vp_log( 'email', 'Admin verification email skipped because no admin email is configured.', [ 'order_id' => $order->get_id() ] );
        return;
    }

    $subject = 'Paystack Verified: Order #' . $order->get_order_number();

    $order_id   = $order->get_id();
    $name       = $order->get_formatted_billing_full_name();
    $email      = $order->get_billing_email();
    $phone      = $order->get_billing_phone();
    $total      = $order->get_total() . ' ' . $order->get_currency();

    $ref = trim( (string) ( $payment['reference'] ?? '' ) ) ?: baby_get_paystack_reference( $order );

    $paid_kobo  = intval( $payment['amount'] ?? 0 );
    $paid_amt   = $paid_kobo ? number_format( $paid_kobo / 100, 2 ) : '';
    $paid_at    = $payment['paid_at'] ?? '';
    $channel    = $payment['channel'] ?? '';

    $lines = [];
    $lines[] = 'PAYSTACK PAYMENT VERIFIED';
    $lines[] = '';
    $lines[] = "Order ID: #{$order_id}";
    $lines[] = "Customer: {$name}";
    $lines[] = "Email: {$email}";
    $lines[] = "Phone: {$phone}";
    $lines[] = "Order Total: {$total}";
    $lines[] = "Reference: {$ref}";

    if ( $paid_amt ) {
        $lines[] = 'Paid Amount: ' . $paid_amt . ' ' . $order->get_currency();
    }
    if ( $paid_at ) {
        $lines[] = 'Paid At: ' . $paid_at;
    }
    if ( $channel ) {
        $lines[] = 'Payment Channel: ' . $channel;
    }

    $lines[] = '';
    $lines[] = 'Items Purchased:';

    foreach ( $order->get_items() as $item ) {
        $lines[] = '- ' . $item->get_name() . ' x' . $item->get_quantity();
    }

    $lines[] = '';
    $lines[] = 'Admin Link:';
    $lines[] = admin_url( 'post.php?post=' . $order_id . '&action=edit' );

    $sent = wp_mail( $to, $subject, implode( "\n", $lines ) );

    baby_vp_log( 'email', 'Admin verification email processed.', [
        'order_id' => $order_id,
        'to'       => $to,
        'sent'     => $sent ? 1 : 0,
        'subject'  => $subject,
    ], $sent ? 'info' : 'error' );
}



/* ---------------------------------------------------------
EMAIL MESSAGE: FIX ORDER ISSUES LINK
Shown inside WooCommerce customer emails before the order table.
----------------------------------------------------------*/

add_action( 'woocommerce_email_before_order_table', 'baby_vp_render_email_fix_order_issues_notice', 25, 4 );

function baby_vp_get_track_orders_page_url() {
    $page_id = function_exists( 'baby_vp_get_or_create_track_orders_page' ) ? baby_vp_get_or_create_track_orders_page() : 0;

    if ( $page_id ) {
        $url = get_permalink( $page_id );
        if ( $url ) {
            return $url;
        }
    }

    $slug = function_exists( 'baby_vp_get_track_orders_page_slug' ) ? baby_vp_get_track_orders_page_slug() : 'track-orders';
    return home_url( '/' . trim( $slug, '/' ) . '/' );
}

function baby_vp_render_email_fix_order_issues_notice( $order, $sent_to_admin, $plain_text, $email ) {
    if ( $sent_to_admin || ! $order || ! is_a( $order, 'WC_Order' ) ) {
        return;
    }

    if ( ! is_object( $email ) || empty( $email->id ) || strpos( (string) $email->id, 'customer_' ) !== 0 ) {
        return;
    }

    $url = baby_vp_get_track_orders_page_url();
    if ( ! $url ) {
        return;
    }

    $message = function_exists( 'baby_vp_get_setting' )
        ? baby_vp_get_setting( 'email_notice_text', function_exists( 'baby_vp_get_default_email_notice_text' ) ? baby_vp_get_default_email_notice_text() : '' )
        : '';
    $message = sanitize_textarea_field( (string) $message );

    if ( '' === trim( $message ) ) {
        return;
    }

    if ( $plain_text ) {
        echo "
" . $message . "

";
        return;
    }

    echo '<p style="margin:0 0 16px;">' . esc_html( $message ) . '</p>';
}

/* ---------------------------------------------------------
RATE LIMIT VERIFICATION ATTEMPTS
Max 5 attempts per IP + order every 10 minutes
----------------------------------------------------------*/

function baby_vp_rate_limit_check( $action = 'verify' ) {
    $ip        = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $action    = sanitize_key( $action );
    $order_id  = $action === 'verify' ? intval( $_POST['order_id'] ?? 0 ) : 0;
    $key       = 'baby_vp_rate_' . md5( $action . '|' . $ip . '|' . $order_id );
    $attempts  = get_transient( $key );

    if ( $attempts === false ) {
        set_transient( $key, 1, 10 * MINUTE_IN_SECONDS );
        return true;
    }

    if ( $attempts >= 5 ) {
        baby_vp_log( 'security', 'Rate limit blocked request.', [ 'action' => $action, 'order_id' => $order_id, 'attempts' => (int) $attempts ], 'warning' );
        return false;
    }

    set_transient( $key, $attempts + 1, 10 * MINUTE_IN_SECONDS );
    return true;
}

/* ---------------------------------------------------------
SHARED VERIFICATION LOGIC
----------------------------------------------------------*/

function baby_vp_attempt_verify_payment( $order, $initiated_by = 'customer' ) {
    if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
        return new WP_Error( 'invalid_order', 'Order not found' );
    }

    $order_id = $order->get_id();

    baby_vp_log( 'verify', 'Verification attempt started.', [
        'order_id'     => $order_id,
        'initiated_by' => $initiated_by,
        'status'       => $order->get_status(),
        'payment_method' => $order->get_payment_method(),
    ] );

    if ( ! in_array( $order->get_status(), [ 'cancelled', 'pending', 'on-hold' ], true ) ) {
        baby_vp_log( 'verify', 'Verification skipped: order status not allowed.', [ 'order_id' => $order_id, 'status' => $order->get_status() ] );
        return new WP_Error( 'bad_status', 'Order cannot be verified' );
    }

    if ( $order->get_payment_method() !== 'paystack' ) {
        baby_vp_log( 'verify', 'Verification skipped: not a Paystack order.', [ 'order_id' => $order_id ] );
        return new WP_Error( 'not_paystack', 'Not a Paystack order' );
    }

    $secret = baby_get_paystack_secret_key();
    if ( ! $secret ) {
        baby_vp_log( 'verify', 'Verification failed: missing Paystack secret key.', [ 'order_id' => $order_id ] );
        return new WP_Error( 'missing_secret', 'Missing Paystack secret key' );
    }

    $match = baby_ps_find_success_for_order( $secret, $order, 0 );
    if ( ! $match ) {
        baby_vp_log( 'verify', 'No Paystack match found on order day. Trying next day.', [ 'order_id' => $order_id ] );
        $match = baby_ps_find_success_for_order( $secret, $order, 1 );
    }

    if ( ! $match ) {
        baby_vp_log( 'verify', 'Verification failed: no successful payment found.', [ 'order_id' => $order_id ] );
        return new WP_Error( 'no_payment', 'No successful payment found.' );
    }

    $payment = $match;
    $ref     = trim( (string) ( $match['reference'] ?? '' ) );

    if ( ! $ref ) {
        baby_vp_log( 'verify', 'Verification failed: payment found but reference missing.', [ 'order_id' => $order_id ] );
        return new WP_Error( 'missing_reference', 'Successful payment found but missing reference.' );
    }

    $order->payment_complete( $ref );
    $order->add_order_note( 'Paystack payment verified by plugin (' . $initiated_by . '). Reference: ' . $ref );
    $order->update_meta_data( '_baby_vp_email_notice_ts', time() );
    $order->save();

    $new_status = $order->get_status();
    $email_sent = in_array( $new_status, [ 'processing', 'completed' ], true );

    baby_vp_log( 'verify', 'Payment verified successfully.', [
        'order_id'     => $order_id,
        'reference'    => $ref,
        'new_status'   => $new_status,
        'email_sent'   => $email_sent ? 1 : 0,
        'initiated_by' => $initiated_by,
    ] );

    baby_send_verify_admin_email( $order, $payment );

    baby_vp_log( 'verify', 'WooCommerce payment completion flow finished after verification.', [
        'order_id'     => $order_id,
        'new_status'   => $new_status,
        'email_sent'   => $email_sent ? 1 : 0,
        'initiated_by' => $initiated_by,
    ] );

    return [
        'message'    => 'Payment verified successfully',
        'new_status' => $new_status,
        'email_sent' => $email_sent,
        'reference'  => $ref,
    ];
}

/* ---------------------------------------------------------
AJAX VERIFY PAYMENT (email/day/next-day only)
----------------------------------------------------------*/

add_action( 'wp_ajax_baby_verify_payment', 'baby_verify_payment' );
add_action( 'wp_ajax_nopriv_baby_verify_payment', 'baby_verify_payment' );

function baby_verify_payment() {
    $order_id  = intval( $_POST['order_id'] ?? 0 );
    $order_key = sanitize_text_field( $_POST['order_key'] ?? '' );
    $nonce     = sanitize_text_field( $_POST['nonce'] ?? '' );

    if ( ! $order_id || ! $order_key ) {
        baby_vp_log( 'verify', 'Verification failed: missing order data.', [ 'order_id' => $order_id ] );
        wp_send_json_error( [ 'message' => 'Missing order data' ] );
    }

    if ( ! wp_verify_nonce( $nonce, 'baby_verify_' . $order_id . '_' . $order_key ) ) {
        baby_vp_log( 'verify', 'Verification failed: nonce check failed.', [ 'order_id' => $order_id ] );
        wp_send_json_error( [ 'message' => 'Security failed' ] );
    }

    if ( ! baby_vp_rate_limit_check() ) {
        baby_vp_log( 'verify', 'Verification blocked by rate limit.', [ 'order_id' => $order_id ] );
        wp_send_json_error( [ 'message' => 'Too many verification attempts. Please wait a few minutes before trying again.' ] );
    }

    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        baby_vp_log( 'verify', 'Verification failed: order not found.', [ 'order_id' => $order_id ] );
        wp_send_json_error( [ 'message' => 'Order not found' ] );
    }

    if ( $order->get_order_key() !== $order_key ) {
        baby_vp_log( 'verify', 'Verification failed: invalid order key.', [ 'order_id' => $order_id ] );
        wp_send_json_error( [ 'message' => 'Invalid order key' ] );
    }

    $result = baby_vp_attempt_verify_payment( $order, 'customer' );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( [ 'message' => $result->get_error_message() ] );
    }

    wp_send_json_success( $result );
}
