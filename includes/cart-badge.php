<?php
if (!defined('ABSPATH')) exit;

function meditrendy_cart_badge_count() {
    if (function_exists('xoo_wsc_cart') && function_exists('WC') && WC()->cart) {
        return (int) xoo_wsc_cart()->get_cart_count();
    }

    if (function_exists('WC') && WC()->cart) {
        return (int) WC()->cart->get_cart_contents_count();
    }

    return 0;
}

function meditrendy_cart_badge_enqueue_assets() {
    if (is_admin()) {
        return;
    }

    $script_path = MEDITRENDY_CORE_DIR . 'assets/js/cart-badge.js';
    $style_path = MEDITRENDY_CORE_DIR . 'assets/css/cart-badge.css';

    wp_enqueue_script(
        'meditrendy-cart-badge',
        MEDITRENDY_CORE_URL . 'assets/js/cart-badge.js',
        [],
        file_exists($script_path) ? filemtime($script_path) : '1.0',
        true
    );

    wp_localize_script(
        'meditrendy-cart-badge',
        'MeditrendyCartBadge',
        [
            'count' => meditrendy_cart_badge_count(),
            'cartTriggerSelector' => '.x-anchor.xoo-wsc-cart-trigger',
            'sourceCountSelector' => '.xoo-wsc-items-count,.xoo-wsch-items-count,.xoo-wscb-count,.xoo-wsc-sc-count,[data-csdc-wc="cart-items"]',
        ]
    );

    wp_enqueue_style(
        'meditrendy-cart-badge',
        MEDITRENDY_CORE_URL . 'assets/css/cart-badge.css',
        [],
        file_exists($style_path) ? filemtime($style_path) : '1.0'
    );
}

add_action('wp_enqueue_scripts', 'meditrendy_cart_badge_enqueue_assets', 45);
