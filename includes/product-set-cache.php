<?php
if (!defined('ABSPATH')) exit;

function meditrendy_product_set_clear_display_caches($product_id = 0) {
    $product_id = absint($product_id);

    if ($product_id && function_exists('wc_delete_product_transients')) {
        wc_delete_product_transients($product_id);
    }

    if (function_exists('meditrendy_brand_products_bump_cache_version')) {
        meditrendy_brand_products_bump_cache_version();
    }

    if (function_exists('meditrendy_native_filters_bump_cache_for_product')) {
        meditrendy_native_filters_bump_cache_for_product($product_id);
    }

    if (function_exists('meditrendy_complete_set_clear_cache')) {
        meditrendy_complete_set_clear_cache($product_id);
    }

    if (function_exists('meditrendy_side_cart_upsells_flush_cache')) {
        meditrendy_side_cart_upsells_flush_cache();
    }
}

add_action('woocommerce_process_product_meta_woosb', 'meditrendy_product_set_clear_display_caches', 30);
add_action('woocommerce_update_product', 'meditrendy_product_set_clear_display_caches', 30);
