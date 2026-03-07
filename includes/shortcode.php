<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function baby_vp_register_shortcode_hooks() {
    add_shortcode( 'baby_vp_fix_order_issues', 'baby_vp_render_fix_order_issues_shortcode' );
}

function baby_vp_render_fix_order_issues_shortcode() {
    return '<div class="baby-vp-shortcode-wrap">' . do_shortcode( '[woocommerce_order_tracking]' ) . '</div>';
}
