<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ---------------------------------------------------------
GET PAYSTACK SECRET KEY
----------------------------------------------------------*/

function baby_get_paystack_secret_key() {

    $settings = get_option('woocommerce_paystack_settings', []);

    // try all common LIVE key field names first
    $live_keys = [
        'secret_key',
        'live_secret_key',
        'live_secret',
        'paystack_secret_key',
    ];

    foreach ($live_keys as $k) {
        if (!empty($settings[$k])) {
            return trim($settings[$k]);
        }
    }

    // then try TEST keys (fallback)
    $test_keys = [
        'test_secret_key',
        'test_secret',
    ];

    foreach ($test_keys as $k) {
        if (!empty($settings[$k])) {
            return trim($settings[$k]);
        }
    }

    // some plugins store settings under different option name
    $settings2 = get_option('woocommerce_paystack_gateway_settings', []);

    foreach ($live_keys as $k) {
        if (!empty($settings2[$k])) {
            return trim($settings2[$k]);
        }
    }

    foreach ($test_keys as $k) {
        if (!empty($settings2[$k])) {
            return trim($settings2[$k]);
        }
    }

    if (defined('PAYSTACK_SECRET_KEY')) {
        return trim(PAYSTACK_SECRET_KEY);
    }

    baby_vp_log( 'paystack', 'Paystack secret key could not be found in configured settings.', [], 'warning' );

    return '';
}


/* ---------------------------------------------------------
GET PAYSTACK REFERENCE (from order meta)
----------------------------------------------------------*/

function baby_get_paystack_reference($order) {

    $refs = [
        $order->get_transaction_id(),
        $order->get_meta('_transaction_id'),
        $order->get_meta('_paystack_txn_ref'),
        $order->get_meta('paystack_txn_ref'),
        $order->get_meta('_paystack_reference'),
        $order->get_meta('paystack_reference')
    ];

    foreach ($refs as $ref) {
        if (!empty($ref)) {
            return trim($ref);
        }
    }

    return '';
}


/* ---------------------------------------------------------
PAYSTACK LOOKUP (Email + Amount + Reference OrderNo)
- Check order day, then next day
----------------------------------------------------------*/

function baby_ps_iso_z($ts) {
    return gmdate('Y-m-d\TH:i:s\Z', (int)$ts);
}

function baby_ps_day_range_utc($order_ts_gmt, $day_offset = 0) {
    $base = (int)$order_ts_gmt + ((int)$day_offset * 86400);
    $day_start = gmmktime(0, 0, 0, (int)gmdate('n', $base), (int)gmdate('j', $base), (int)gmdate('Y', $base));
    $day_end   = $day_start + 86400 - 1;
    return [baby_ps_iso_z($day_start), baby_ps_iso_z($day_end)];
}

function baby_ps_fetch_customer_id($secret, $email) {
    $url = "https://api.paystack.co/customer/" . rawurlencode($email);

    $res = wp_remote_get($url, [
        'headers' => [
            'Authorization' => "Bearer " . $secret,
            'Accept'        => "application/json",
        ],
        'timeout' => 20
    ]);

    if (is_wp_error($res)) {
        baby_vp_log( 'paystack', 'Paystack customer lookup failed.', [ 'email' => $email, 'error' => $res ], 'error' );
        return null;
    }
    if ((int)wp_remote_retrieve_response_code($res) !== 200) {
        baby_vp_log( 'paystack', 'Paystack customer lookup returned unexpected status.', [ 'email' => $email, 'status_code' => (int) wp_remote_retrieve_response_code($res) ], 'warning' );
        return null;
    }

    $j = json_decode((string)wp_remote_retrieve_body($res), true);
    $data = $j['data'] ?? [];
    $id = $data['id'] ?? null;

    if ( ! $id ) {
        baby_vp_log( 'paystack', 'Paystack customer lookup returned no customer ID.', [ 'email' => $email ], 'warning' );
    }

    return $id ? (int)$id : null;
}

function baby_ps_list_transactions($secret, $customer_id, $from_iso, $to_iso) {
    $url = "https://api.paystack.co/transaction";
    $headers = [
        'Authorization' => "Bearer " . $secret,
        'Accept'        => "application/json",
    ];

    $page = 1;
    $out = [];

    while (true) {
        $res = wp_remote_get(add_query_arg([
            'customer' => $customer_id,
            'from'     => $from_iso,
            'to'       => $to_iso,
            'perPage'  => 100,
            'page'     => $page,
        ], $url), [
            'headers' => $headers,
            'timeout' => 20
        ]);

        if (is_wp_error($res)) {
            baby_vp_log( 'paystack', 'Paystack transaction list request failed.', [ 'customer_id' => (int) $customer_id, 'page' => (int) $page, 'error' => $res ], 'error' );
            break;
        }
        if ((int)wp_remote_retrieve_response_code($res) !== 200) {
            baby_vp_log( 'paystack', 'Paystack transaction list returned unexpected status.', [ 'customer_id' => (int) $customer_id, 'page' => (int) $page, 'status_code' => (int) wp_remote_retrieve_response_code($res) ], 'warning' );
            break;
        }

        $j = json_decode((string)wp_remote_retrieve_body($res), true);
        $data = $j['data'] ?? [];
        $meta = $j['meta'] ?? [];

        if (is_array($data) && $data) $out = array_merge($out, $data);

        $pg = (int)($meta['page'] ?? $page);
        $pc = (int)($meta['pageCount'] ?? $pg);

        if ($pg >= $pc) break;

        $page++;
        usleep(200000); // 0.2s
    }

    return $out;
}

/**
 * Matching rule:
 * - status must be success
 * - amount must match
 * - reference must start with "{order_number}_"
 */
function baby_ps_pick_success_match_by_orderno($txs, $order_number, $amount_kobo) {
    $order_number = (string)$order_number;
    $want_prefix = $order_number . "_";

    foreach ($txs as $t) {
        $st = strtolower(trim((string)($t['status'] ?? '')));
        if ($st !== 'success') continue;

        $amt = (int)($t['amount'] ?? 0);
        if ($amt !== (int)$amount_kobo) continue;

        $ref = trim((string)($t['reference'] ?? ''));
        if (!$ref) continue;

        if (strpos($ref, $want_prefix) === 0) {
            return $t;
        }
    }

    return null;
}

function baby_ps_find_success_for_order($secret, WC_Order $order, $day_offset = 0) {

    $email = trim((string)$order->get_billing_email());
    if (!$email) return null;

    $order_number = (string)$order->get_order_number();
    $amount_kobo  = (int)round(((float)$order->get_total()) * 100);

    // Use GMT creation time
    $dt_gmt = $order->get_date_created('gmt');
    $order_ts_gmt = $dt_gmt ? (int)$dt_gmt->getTimestamp() : time();

    $cid = baby_ps_fetch_customer_id($secret, $email);
    if (!$cid) {
        baby_vp_log( 'paystack', 'No Paystack customer found for order lookup.', [ 'order_id' => $order->get_id(), 'email' => $email ], 'warning' );
        return null;
    }

    list($from_iso, $to_iso) = baby_ps_day_range_utc($order_ts_gmt, $day_offset);

    $txs = baby_ps_list_transactions($secret, $cid, $from_iso, $to_iso);
    $match = baby_ps_pick_success_match_by_orderno($txs, $order_number, $amount_kobo);

    baby_vp_log( 'paystack', 'Completed Paystack transaction lookup for order.', [
        'order_id'    => $order->get_id(),
        'customer_id' => (int) $cid,
        'day_offset'  => (int) $day_offset,
        'from'        => $from_iso,
        'to'          => $to_iso,
        'tx_count'    => is_array( $txs ) ? count( $txs ) : 0,
        'match_found' => $match ? 1 : 0,
    ] );

    return $match;
}