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

function meditrendy_product_set_related_product_ids($product_id) {
    $product_id = absint($product_id);

    if (!$product_id || !function_exists('wc_get_product')) {
        return [];
    }

    $ids = [$product_id];
    $product = wc_get_product($product_id);

    if ($product && $product->is_type('variation')) {
        $ids[] = (int) $product->get_parent_id();
    } elseif ($product && $product->is_type('variable')) {
        $ids = array_merge($ids, array_map('absint', $product->get_children()));
    }

    return array_values(array_unique(array_filter(array_map('absint', $ids))));
}

function meditrendy_product_set_item_matches_product($item_product_id, $related_ids) {
    $item_product_id = absint($item_product_id);

    if (!$item_product_id || in_array($item_product_id, $related_ids, true)) {
        return (bool) $item_product_id;
    }

    if (!function_exists('wc_get_product')) {
        return false;
    }

    $item_product = wc_get_product($item_product_id);

    return $item_product
        && $item_product->is_type('variation')
        && in_array((int) $item_product->get_parent_id(), $related_ids, true);
}

function meditrendy_product_set_contains_product($set, $related_ids) {
    if (!$set || !is_a($set, 'WC_Product') || !$set->is_type('woosb') || !method_exists($set, 'get_items')) {
        return false;
    }

    foreach ((array) $set->get_items() as $item) {
        if (!empty($item['id']) && meditrendy_product_set_item_matches_product((int) $item['id'], $related_ids)) {
            return true;
        }
    }

    return false;
}

function meditrendy_product_set_find_containing_product($product_id) {
    $related_ids = meditrendy_product_set_related_product_ids($product_id);

    if (!$related_ids || !function_exists('wc_get_product')) {
        return [];
    }

    $meta_query = ['relation' => 'OR'];

    foreach ($related_ids as $related_id) {
        $meta_query[] = [
            'key'     => 'woosb_ids',
            'value'   => (string) $related_id,
            'compare' => 'LIKE',
        ];
    }

    $query = new WP_Query([
        'post_type'              => 'product',
        'post_status'            => 'any',
        'posts_per_page'         => 100,
        'fields'                 => 'ids',
        'no_found_rows'          => true,
        'update_post_meta_cache' => true,
        'update_post_term_cache' => false,
        'tax_query'              => [
            [
                'taxonomy' => 'product_type',
                'field'    => 'slug',
                'terms'    => ['woosb'],
            ],
        ],
        'meta_query'             => $meta_query,
    ]);

    $set_ids = [];

    foreach ($query->posts as $set_id) {
        $set = wc_get_product($set_id);

        if (meditrendy_product_set_contains_product($set, $related_ids)) {
            $set_ids[] = absint($set_id);
        }
    }

    return array_values(array_unique($set_ids));
}

function meditrendy_product_set_refresh_set($set_id) {
    $set_id = absint($set_id);

    if (!$set_id || !function_exists('wc_get_product')) {
        return;
    }

    $set = wc_get_product($set_id);

    if (!$set || !$set->is_type('woosb')) {
        return;
    }

    if (method_exists($set, 'build_items')) {
        $set->build_items();
    }

    meditrendy_product_set_sync_auto_price($set);
    meditrendy_product_set_clear_display_caches($set_id);
    clean_post_cache($set_id);

    if (function_exists('wc_update_product_lookup_tables')) {
        wc_update_product_lookup_tables($set_id);
    }

    $set->save();
}

function meditrendy_product_set_price_helper() {
    return function_exists('WPCleverWoosb_Helper') ? WPCleverWoosb_Helper() : null;
}

function meditrendy_product_set_round_price($price) {
    $helper = meditrendy_product_set_price_helper();

    if ($helper && method_exists($helper, 'round_price')) {
        return (float) $helper->round_price($price);
    }

    return (float) wc_format_decimal((float) $price, wc_get_price_decimals());
}

function meditrendy_product_set_item_price($product, $qty = 1, $min_or_max = 'min') {
    $helper = meditrendy_product_set_price_helper();

    if ($helper && method_exists($helper, 'get_price_to_display')) {
        return (float) $helper->get_price_to_display($product, $qty, $min_or_max);
    }

    return (float) wc_get_price_to_display($product, ['qty' => $qty]);
}

function meditrendy_product_set_sync_auto_price($set) {
    if (!$set || !is_a($set, 'WC_Product') || !$set->is_type('woosb') || !method_exists($set, 'get_items')) {
        return;
    }

    if (method_exists($set, 'is_fixed_price') && $set->is_fixed_price()) {
        return;
    }

    $items = (array) $set->get_items();

    if (!$items) {
        return;
    }

    $regular_price = 0.0;
    $sale_price = 0.0;

    foreach ($items as $item) {
        if (empty($item['id'])) {
            continue;
        }

        $item_product = wc_get_product(absint($item['id']));

        if (!$item_product || !$item_product->exists()) {
            continue;
        }

        $qty = isset($item['qty']) ? (float) $item['qty'] : 1.0;

        if ($qty <= 0) {
            continue;
        }

        $regular_price += meditrendy_product_set_item_price($item_product, $qty);
        $sale_price += meditrendy_product_set_item_price($item_product, $qty);
    }

    $discount_amount = method_exists($set, 'get_discount_amount') ? (float) $set->get_discount_amount() : 0.0;
    $discount_percentage = method_exists($set, 'get_discount_percentage') ? (float) $set->get_discount_percentage() : 0.0;

    if ($discount_amount > 0) {
        $sale_price -= $discount_amount;
    } elseif ($discount_percentage > 0 && $discount_percentage < 100) {
        $sale_price = meditrendy_product_set_round_price($sale_price * (100 - $discount_percentage) / 100);
    }

    $regular_price = max(0, meditrendy_product_set_round_price($regular_price));
    $sale_price = max(0, meditrendy_product_set_round_price($sale_price));
    $active_price = $sale_price > 0 && $sale_price < $regular_price ? $sale_price : $regular_price;

    $set->set_regular_price((string) $regular_price);
    $set->set_sale_price($active_price < $regular_price ? (string) $sale_price : '');
    $set->set_price((string) $active_price);
}

function meditrendy_product_set_refresh_containing_product($product_id = 0) {
    static $refreshing = false;

    if ($refreshing) {
        return;
    }

    $product_id = absint($product_id);

    if (!$product_id || !function_exists('wc_get_product')) {
        return;
    }

    $product = wc_get_product($product_id);

    if (!$product || $product->is_type('woosb')) {
        return;
    }

    $set_ids = meditrendy_product_set_find_containing_product($product_id);

    if (!$set_ids) {
        return;
    }

    $refreshing = true;

    foreach ($set_ids as $set_id) {
        meditrendy_product_set_refresh_set($set_id);
    }

    $refreshing = false;
}

add_action('woocommerce_process_product_meta_woosb', 'meditrendy_product_set_clear_display_caches', 30);
add_action('woocommerce_update_product', 'meditrendy_product_set_clear_display_caches', 30);
add_action('woocommerce_update_product', 'meditrendy_product_set_refresh_containing_product', 40);
add_action('woocommerce_update_product_variation', 'meditrendy_product_set_refresh_containing_product', 40);
add_action('woocommerce_save_product_variation', 'meditrendy_product_set_refresh_containing_product', 40);
