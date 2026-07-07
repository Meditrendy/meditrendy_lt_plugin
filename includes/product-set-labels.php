<?php
if (!defined('ABSPATH')) exit;

function meditrendy_product_set_language() {
    if (function_exists('pll_current_language')) {
        $language = strtolower((string) pll_current_language('slug'));

        if ($language !== '') {
            return $language === 'ee' ? 'et' : $language;
        }
    }

    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));

    if (strpos($host, 'meditrendy.ee') !== false) {
        return 'et';
    }

    if (strpos($host, 'meditrendy.lv') !== false) {
        return 'lv';
    }

    return 'lt';
}

function meditrendy_product_set_add_to_cart_label() {
    $labels = [
        'lt' => 'Į krepšelį',
        'lv' => 'Grozā',
        'et' => 'Ostukorvi',
    ];
    $language = meditrendy_product_set_language();

    return __($labels[$language] ?? $labels['lt'], 'meditrendy-core');
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
