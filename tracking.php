<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ---------------------------------------------------------
ADD "TRACK USING PAYSTACK REFERENCE" FIELDS ON TRACK ORDER PAGE
Generic version for sites using: /track-orders/
----------------------------------------------------------*/

add_action('woocommerce_order_tracking_form', function () {

  if ( ! is_page('track-orders') ) return;

  echo '<div class="baby-ps-track" style="margin-top:18px; padding-top:14px; border-top:1px solid #e5e7eb;">';

  echo '<p style="margin:0 0 10px; font-weight:600;">Track order using Paystack reference</p>';

  echo '<p style="margin:0 0 12px; color:#6b7280;">
          If you paid and didn’t receive your order email, enter your Paystack reference and billing email. Paystack reference will be in the payment confirmation received from Paystack after making payment.
        </p>';

  echo '<p class="form-row form-row-first">
          <label>Paystack reference</label>
          <input type="text" class="input-text" id="baby_ps_reference" name="baby_ps_reference" placeholder="e.g. 24168_1772XXXXXX">
        </p>';

  echo '<p class="form-row form-row-last">
          <label>Billing email</label>
          <input type="email" class="input-text" id="baby_ps_email" name="baby_ps_email" placeholder="e.g. your_email@gmail.com">
        </p>';

  echo '<div class="clear"></div>';

  // no second button — reuse the default Woo "Track" button
  echo '<div class="baby-ps-track-error" style="margin-top:10px;color:#991b1b;"></div>';

  echo '</div>';

}, 30);


/* ---------------------------------------------------------
AJAX: TRACK BY PAYSTACK REFERENCE + BILLING EMAIL
- reference format: {orderId}_{something} e.g. 24168_1772173890
- extract orderId, load order, cross-check billing email
----------------------------------------------------------*/

add_action('wp_ajax_baby_track_by_paystack', 'baby_track_by_paystack');
add_action('wp_ajax_nopriv_baby_track_by_paystack', 'baby_track_by_paystack');

function baby_track_by_paystack(){

  $ref   = isset($_POST['reference']) ? sanitize_text_field(wp_unslash($_POST['reference'])) : '';
  $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';

  if(!$ref || !$email){
    wp_send_json_error(['message' => 'Enter Paystack reference and billing email.']);
  }

  $parts = explode('_', $ref);
  $order_id = isset($parts[0]) ? absint($parts[0]) : 0;

  if(!$order_id){
    wp_send_json_error(['message' => 'Invalid Paystack reference format. Example: 24168_1772173890']);
  }

  $order = wc_get_order($order_id);
  if(!$order){
    wp_send_json_error(['message' => 'Order not found for that reference.']);
  }

  $order_email = strtolower(trim($order->get_billing_email()));
  $email       = strtolower(trim($email));

  if(!$order_email || $order_email !== $email){
    wp_send_json_error(['message' => 'Billing email does not match this order.']);
  }

  wp_send_json_success([
    'orderid' => (string) $order->get_order_number(),
    'email'   => (string) $order_email,
  ]);
}