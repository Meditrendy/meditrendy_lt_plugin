<?php
if (!defined('ABSPATH')) exit;

function meditrendy_page_has_woocommerce_block() {
    if (!is_singular()) {
        return false;
    }

    $post = get_post();

    if (!$post || !has_blocks($post)) {
        return false;
    }

    return has_block('woocommerce/cart', $post) ||
        has_block('woocommerce/checkout', $post) ||
        has_block('woocommerce/mini-cart', $post) ||
        has_block('woocommerce/product-collection', $post) ||
        has_block('woocommerce/all-products', $post);
}

function meditrendy_dequeue_unused_frontend_assets() {
    if (is_admin()) {
        return;
    }

    if (!meditrendy_page_has_woocommerce_block()) {
        wp_dequeue_style('wc-blocks-style');
    }

    if (!is_singular()) {
        wp_dequeue_style('woosb-blocks');
    }
}
add_action('wp_enqueue_scripts', 'meditrendy_dequeue_unused_frontend_assets', 100);
