<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ---------------------------------------------------------
ADD "TRACK USING PAYSTACK REFERENCE" FIELDS ON TRACK ORDER PAGE
Generic version for sites using: /track-orders/
----------------------------------------------------------*/

add_action('woocommerce_order_tracking_form', function () {

  if ( ! function_exists('baby_vp_is_current_track_orders_page') || ! baby_vp_is_current_track_orders_page() ) return;

  echo '<div class="baby-ps-track" data-baby-ps-track="1">';

  echo '<p class="baby-ps-track-title">Track order using Paystack reference</p>';

  echo '<p class="baby-ps-track-text">
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
  echo '<div class="baby-ps-track-error"></div>';

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
  $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';

  if ( ! wp_verify_nonce( $nonce, 'baby_track_by_paystack' ) ) {
    baby_vp_log( 'track', 'Tracking failed: nonce check failed.', [] );
    wp_send_json_error(['message' => 'Security failed. Please refresh and try again.']);
  }

  if ( function_exists( 'baby_vp_rate_limit_check' ) && ! baby_vp_rate_limit_check('track') ) {
    baby_vp_log( 'track', 'Tracking blocked by rate limit.', [] );
    wp_send_json_error(['message' => 'Too many tracking attempts. Please wait a few minutes before trying again.']);
  }

  if(!$ref || !$email){
    baby_vp_log( 'track', 'Tracking failed: missing reference or billing email.', [] );
    wp_send_json_error(['message' => 'Enter Paystack reference and billing email.']);
  }

  $parts = explode('_', $ref);
  $order_id = isset($parts[0]) ? absint($parts[0]) : 0;

  if(!$order_id){
    baby_vp_log( 'track', 'Tracking failed: invalid reference format.', [ 'reference' => $ref ] );
    wp_send_json_error(['message' => 'Invalid Paystack reference format. Example: 24168_1772173890']);
  }

  $order = wc_get_order($order_id);
  if(!$order){
    baby_vp_log( 'track', 'Tracking failed: order not found for reference.', [ 'order_id' => $order_id ] );
    wp_send_json_error(['message' => 'Order not found for that reference.']);
  }

  $order_email = strtolower(trim($order->get_billing_email()));
  $email       = strtolower(trim($email));

  if(!$order_email || $order_email !== $email){
    baby_vp_log( 'track', 'Tracking failed: billing email mismatch.', [ 'order_id' => $order_id ] );
    wp_send_json_error(['message' => 'Billing email does not match this order.']);
  }

  baby_vp_log( 'track', 'Tracking match found by Paystack reference helper.', [ 'order_id' => $order_id ] );

  wp_send_json_success([
    'orderid' => (string) $order->get_order_number(),
    'email'   => (string) $order_email,
  ]);
}