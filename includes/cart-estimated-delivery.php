<?php

if (!defined('ABSPATH')) exit;

/**
 * Show the product realization time in cart and checkout item details.
 *
 * WooCommerce uses this filter for both classic templates and the Store API
 * consumed by Cart and Checkout blocks.
 */
function meditrendy_cart_estimated_delivery_item_data($item_data, $cart_item) {
    $product = isset($cart_item['data']) && $cart_item['data'] instanceof WC_Product
        ? $cart_item['data']
        : null;

    if (!$product && !empty($cart_item['variation_id'])) {
        $product = wc_get_product($cart_item['variation_id']);
    }

    if (!$product && !empty($cart_item['product_id'])) {
        $product = wc_get_product($cart_item['product_id']);
    }

    if (!$product || !function_exists('meditrendy_orders_pdf_product_realization_days')) {
        return $item_data;
    }

    $days = meditrendy_orders_pdf_product_realization_days($product);

    $item_data[] = [
        'key'   => __('Numatomas pristatymo laikas', 'meditrendy-core'),
        'value' => sprintf(__('%d d.', 'meditrendy-core'), $days),
    ];

    return $item_data;
}
add_filter('woocommerce_get_item_data', 'meditrendy_cart_estimated_delivery_item_data', 20, 2);
