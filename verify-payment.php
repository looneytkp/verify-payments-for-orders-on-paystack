<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ---------------------------------------------------------
SEND EMAIL WHEN PAYMENT VERIFIED
----------------------------------------------------------*/

function baby_send_verify_admin_email(WC_Order $order, array $payment = []) {

    $to = baby_vp_admin_email();
    $subject = 'Paystack Verified: Order #' . $order->get_order_number();

    $order_id   = $order->get_id();
    $name       = $order->get_formatted_billing_full_name();
    $email      = $order->get_billing_email();
    $phone      = $order->get_billing_phone();
    $total      = $order->get_total() . ' ' . $order->get_currency();

    // Prefer Paystack payment reference (from matched transaction)
    $ref = trim((string)($payment['reference'] ?? '')) ?: baby_get_paystack_reference($order);

    $paid_kobo  = intval($payment['amount'] ?? 0);
    $paid_amt   = $paid_kobo ? number_format($paid_kobo / 100, 2) : '';
    $paid_at    = $payment['paid_at'] ?? '';
    $channel    = $payment['channel'] ?? '';

    $lines = [];
    $lines[] = "PAYSTACK PAYMENT VERIFIED";
    $lines[] = "";
    $lines[] = "Order ID: #{$order_id}";
    $lines[] = "Customer: {$name}";
    $lines[] = "Email: {$email}";
    $lines[] = "Phone: {$phone}";
    $lines[] = "Order Total: {$total}";
    $lines[] = "Reference: {$ref}";

    if ($paid_amt) $lines[] = "Paid Amount: {$paid_amt} {$order->get_currency()}";
    if ($paid_at)  $lines[] = "Paid At: {$paid_at}";
    if ($channel)  $lines[] = "Payment Channel: {$channel}";

    $lines[] = "";
    $lines[] = "Items Purchased:";

    foreach ($order->get_items() as $item) {
        $lines[] = "- " . $item->get_name() . " x" . $item->get_quantity();
    }

    $lines[] = "";
    $lines[] = "Admin Link:";
    $lines[] = admin_url('post.php?post=' . $order_id . '&action=edit');

    $message = implode("\n", $lines);

    wp_mail($to, $subject, $message);
}


/* ---------------------------------------------------------
AJAX VERIFY PAYMENT (email/day/next-day only)
----------------------------------------------------------*/

add_action('wp_ajax_baby_verify_payment', 'baby_verify_payment');
add_action('wp_ajax_nopriv_baby_verify_payment', 'baby_verify_payment');

function baby_verify_payment() {

    $order_id  = intval($_POST['order_id'] ?? 0);
    $order_key = sanitize_text_field($_POST['order_key'] ?? '');
    $nonce     = sanitize_text_field($_POST['nonce'] ?? '');

    if (!$order_id || !$order_key) {
        wp_send_json_error(['message' => 'Missing order data']);
    }

    if (!wp_verify_nonce($nonce, 'baby_verify_' . $order_id . '_' . $order_key)) {
        wp_send_json_error(['message' => 'Security failed']);
    }

    $order = wc_get_order($order_id);

    if (!$order) {
        wp_send_json_error(['message' => 'Order not found']);
    }

    if ($order->get_order_key() !== $order_key) {
        wp_send_json_error(['message' => 'Invalid order key']);
    }

    if (!in_array($order->get_status(), ['cancelled','pending'], true)) {
        wp_send_json_error(['message' => 'Order cannot be verified']);
    }

    if ($order->get_payment_method() !== 'paystack') {
        wp_send_json_error(['message' => 'Not a Paystack order']);
    }

    $secret = baby_get_paystack_secret_key();

    if (!$secret) {
        wp_send_json_error(['message' => 'Missing Paystack secret key']);
    }

    // Find payment: order day, else next day
    $match = baby_ps_find_success_for_order($secret, $order, 0);
    if (!$match) {
        $match = baby_ps_find_success_for_order($secret, $order, 1);
    }

    if (!$match) {
        wp_send_json_error(['message' => 'No successful payment found.']);
    }

    $payment = $match;
    $ref = trim((string)($match['reference'] ?? ''));

    if (!$ref) {
        wp_send_json_error(['message' => 'Successful payment found but missing reference.']);
    }

    /* PAYMENT VERIFIED */
    $order->payment_complete($ref);
    $order->update_status('processing', 'Payment verified by customer');

    /* SEND ADMIN EMAIL */
    baby_send_verify_admin_email($order, $payment);

    /* resend customer email */
    $mailer = WC()->mailer();
    $emails = $mailer->get_emails();

    if (!empty($emails['WC_Email_Customer_Processing_Order'])) {
        $emails['WC_Email_Customer_Processing_Order']->trigger($order_id);

        // store notice for frontend badge
        $order->update_meta_data('_baby_vp_email_notice_ts', time());
        $order->save();
    }

    wp_send_json_success([
        'message'    => 'Payment verified successfully',
        'new_status' => 'processing',
        'email_sent' => !empty($emails['WC_Email_Customer_Processing_Order'])
    ]);
}