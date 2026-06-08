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

function meditrendy_is_product_archive_context() {
    return function_exists('is_shop')
        && (is_shop() || is_product_taxonomy() || is_product_category() || is_product_tag());
}

function meditrendy_is_cart_or_checkout_context() {
    return (function_exists('is_cart') && is_cart()) || (function_exists('is_checkout') && is_checkout());
}

function meditrendy_dequeue_unused_frontend_assets() {
    if (is_admin()) {
        return;
    }

    if (!meditrendy_page_has_woocommerce_block()) {
        wp_dequeue_style('wc-blocks-style');
        wp_deregister_style('wc-blocks-style');
    }

    if ((!function_exists('is_product') || !is_product()) && !meditrendy_is_cart_or_checkout_context()) {
        wp_dequeue_style('woosb-blocks');
        wp_deregister_style('woosb-blocks');
    }

    if (!meditrendy_is_product_archive_context() && (!function_exists('is_product') || !is_product())) {
        wp_dequeue_style('tp-product-image-flipper-for-woocommerce');
        wp_deregister_style('tp-product-image-flipper-for-woocommerce');
    }
}
add_action('wp_enqueue_scripts', 'meditrendy_dequeue_unused_frontend_assets', 9999);

function meditrendy_defer_noncritical_plugin_scripts($tag, $handle, $src) {
    if (is_admin()) {
        return $tag;
    }

    $defer_handles = [
        'jquery-blockui',
        'wp-optimize-send-command',
        'wpo-lazy-load',
        'wpo-lazy-load-js',
    ];

    $defer_sources = [
        '/jquery-blockui/jquery.blockUI',
        '/js/send-command',
        '/js/wpo-lazy-load',
    ];

    $should_defer = in_array($handle, $defer_handles, true);

    foreach ($defer_sources as $source_part) {
        if (strpos($src, $source_part) !== false) {
            $should_defer = true;
            break;
        }
    }

    if (!$should_defer) {
        return $tag;
    }

    if (strpos($tag, ' defer') !== false || strpos($tag, ' async') !== false) {
        return $tag;
    }

    return str_replace(' src=', ' defer src=', $tag);
}
add_filter('script_loader_tag', 'meditrendy_defer_noncritical_plugin_scripts', 20, 3);
