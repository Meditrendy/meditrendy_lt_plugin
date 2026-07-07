<?php
if (!defined('ABSPATH')) exit;

function meditrendy_product_set_add_to_cart_label() {
    return __('Į krepšelį', 'meditrendy-core');
}

function meditrendy_product_set_is_bundle($product) {
    return $product && is_a($product, 'WC_Product') && $product->is_type('woosb');
}

function meditrendy_product_set_single_add_to_cart_text($text, $product = null) {
    if (meditrendy_product_set_is_bundle($product)) {
        return meditrendy_product_set_add_to_cart_label();
    }

    return $text;
}

add_filter('woocommerce_product_single_add_to_cart_text', 'meditrendy_product_set_single_add_to_cart_text', 20, 2);
add_filter('woosb_product_single_add_to_cart_text', 'meditrendy_product_set_single_add_to_cart_text', 20, 2);
