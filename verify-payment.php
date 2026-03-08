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

    wp_mail( $to, $subject, implode( "\n", $lines ) );
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
        return false;
    }

    set_transient( $key, $attempts + 1, 10 * MINUTE_IN_SECONDS );
    return true;
}

/* ---------------------------------------------------------
ADMIN ORDER ACTION + NOTICES
----------------------------------------------------------*/

add_filter( 'woocommerce_order_actions', 'baby_vp_add_admin_order_action' );
add_action( 'woocommerce_order_action_baby_vp_verify_paystack', 'baby_vp_handle_admin_order_action' );
add_action( 'admin_notices', 'baby_vp_render_admin_notice' );

function baby_vp_add_admin_order_action( $actions ) {
    $actions['baby_vp_verify_paystack'] = 'Verify Paystack Payment';
    return $actions;
}

function baby_vp_set_admin_notice( $message, $type = 'success' ) {
    $user_id = get_current_user_id();
    if ( ! $user_id ) {
        return;
    }
    set_transient( 'baby_vp_admin_notice_' . $user_id, [
        'message' => wp_strip_all_tags( $message ),
        'type'    => $type === 'error' ? 'error' : 'success',
    ], 60 );
}

function baby_vp_render_admin_notice() {
    if ( ! is_admin() ) {
        return;
    }

    $screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    $screen_id = $screen && ! empty( $screen->id ) ? (string) $screen->id : '';

    if ( ! in_array( $screen_id, [ 'shop_order', 'woocommerce_page_wc-orders' ], true ) ) {
        return;
    }

    $user_id = get_current_user_id();
    $notice  = $user_id ? get_transient( 'baby_vp_admin_notice_' . $user_id ) : false;
    if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
        return;
    }

    delete_transient( 'baby_vp_admin_notice_' . $user_id );
    $class = ! empty( $notice['type'] ) && $notice['type'] === 'error' ? 'notice notice-error' : 'notice notice-success';
    echo '<div class="' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $notice['message'] ) . '</p></div>';
}

function baby_vp_handle_admin_order_action( $order ) {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        baby_vp_set_admin_notice( 'You do not have permission to verify this payment.', 'error' );
        return;
    }

    $result = baby_vp_attempt_verify_payment( $order, 'admin' );

    if ( is_wp_error( $result ) ) {
        baby_vp_set_admin_notice( $result->get_error_message(), 'error' );
        return;
    }

    baby_vp_set_admin_notice( 'Paystack payment verified successfully.' );
}

/* ---------------------------------------------------------
SHARED VERIFICATION LOGIC
----------------------------------------------------------*/

function baby_vp_attempt_verify_payment( $order, $initiated_by = 'customer' ) {
    if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
        return new WP_Error( 'invalid_order', 'Order not found' );
    }

    $order_id = $order->get_id();

    if ( ! in_array( $order->get_status(), [ 'cancelled', 'pending' ], true ) ) {
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
    $order->update_status( 'processing', 'Payment verified by ' . $initiated_by );
    $order->add_order_note( 'Paystack payment verified by plugin (' . $initiated_by . '). Reference: ' . $ref );

    baby_vp_log( 'verify', 'Payment verified successfully.', [
        'order_id'   => $order_id,
        'reference'  => $ref,
        'new_status' => 'processing',
        'initiated_by' => $initiated_by,
    ] );

    baby_send_verify_admin_email( $order, $payment );

    $mailer = WC()->mailer();
    $emails = $mailer->get_emails();

    if ( ! empty( $emails['WC_Email_Customer_Processing_Order'] ) ) {
        $emails['WC_Email_Customer_Processing_Order']->trigger( $order_id );
        $order->update_meta_data( '_baby_vp_email_notice_ts', time() );
        $order->save();
    }

    baby_vp_log( 'verify', 'Processing email triggered after verification.', [
        'order_id'   => $order_id,
        'email_sent' => ! empty( $emails['WC_Email_Customer_Processing_Order'] ),
        'initiated_by' => $initiated_by,
    ] );

    return [
        'message'    => 'Payment verified successfully',
        'new_status' => 'processing',
        'email_sent' => ! empty( $emails['WC_Email_Customer_Processing_Order'] ),
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
