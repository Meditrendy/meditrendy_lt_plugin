<?php
if (!defined('ABSPATH')) exit;

function meditrendy_wpc_bundle_variation_json_bundled_child_ids() {
    static $child_ids = null;

    if ($child_ids !== null) {
        return $child_ids;
    }

    $child_ids = [];

    if (!function_exists('wc_get_product')) {
        return $child_ids;
    }

    $query = new WP_Query([
        'post_type'              => 'product',
        'post_status'            => 'any',
        'posts_per_page'         => -1,
        'fields'                 => 'ids',
        'no_found_rows'          => true,
        'update_post_meta_cache' => true,
        'update_post_term_cache' => false,
        'lang'                   => '',
        'tax_query'              => [
            [
                'taxonomy' => 'product_type',
                'field'    => 'slug',
                'terms'    => ['woosb'],
            ],
        ],
    ]);

    foreach ((array) $query->posts as $set_id) {
        $set = wc_get_product($set_id);

        if ($set && is_a($set, 'WC_Product') && $set->is_type('woosb') && method_exists($set, 'get_items')) {
            foreach ((array) $set->get_items() as $item) {
                if (!empty($item['id'])) {
                    $child_ids[] = meditrendy_wpc_bundle_variation_json_normalize_child_id($item['id']);
                }
            }
        }

        foreach (meditrendy_wpc_bundle_variation_json_raw_bundle_item_ids($set_id) as $raw_product_id) {
            $child_ids[] = meditrendy_wpc_bundle_variation_json_normalize_child_id($raw_product_id);
        }
    }

    $child_ids = array_values(array_unique(array_filter(array_map('absint', $child_ids))));

    return $child_ids;
}

function meditrendy_wpc_bundle_variation_json_normalize_child_id($product_id) {
    $product_id = absint($product_id);

    if (!$product_id || !function_exists('wc_get_product')) {
        return 0;
    }

    $product = wc_get_product($product_id);

    if (!$product || !is_a($product, 'WC_Product')) {
        return 0;
    }

    if ($product->is_type('variation')) {
        return absint($product->get_parent_id());
    }

    return $product->is_type('variable') ? $product_id : 0;
}

function meditrendy_wpc_bundle_variation_json_raw_bundle_item_ids($set_id) {
    $raw_ids = get_post_meta(absint($set_id), 'woosb_ids', true);
    $items = [];

    if (is_array($raw_ids)) {
        $items = $raw_ids;
    } elseif (is_string($raw_ids) && $raw_ids !== '') {
        $items = array_filter(array_map('trim', explode(',', $raw_ids)));
    }

    $product_ids = [];

    foreach ($items as $item) {
        if (is_array($item)) {
            $item_id = !empty($item['id']) ? $item['id'] : (!empty($item['sku']) ? $item['sku'] : 0);
        } else {
            $parts = explode('/', (string) $item);
            $item_id = rawurldecode((string) ($parts[0] ?? ''));
        }

        if ($item_id === '' || $item_id === 0 || $item_id === '0') {
            continue;
        }

        if (is_numeric($item_id)) {
            $product_ids[] = absint($item_id);
            continue;
        }

        if (function_exists('wc_get_product_id_by_sku')) {
            $product_ids[] = absint(wc_get_product_id_by_sku(ltrim((string) $item_id, 'sku-')));
        }
    }

    return array_values(array_unique(array_filter(array_map('absint', $product_ids))));
}

function meditrendy_wpc_bundle_variation_json_sorted_children($product) {
    if (!$product || !is_a($product, 'WC_Product_Variable')) {
        return [];
    }

    $children = array_values(array_filter(array_map('absint', (array) $product->get_children())));

    usort($children, function($left, $right) {
        $left_order = (int) get_post_field('menu_order', $left);
        $right_order = (int) get_post_field('menu_order', $right);

        if ($left_order === $right_order) {
            return $left <=> $right;
        }

        return $left_order <=> $right_order;
    });

    return $children;
}

function meditrendy_wpc_bundle_variation_json_expected_map($variable) {
    static $maps = [];

    if (!$variable || !is_a($variable, 'WC_Product_Variable')) {
        return [];
    }

    $product_id = absint($variable->get_id());

    if (isset($maps[$product_id])) {
        return $maps[$product_id];
    }

    $attributes = [];

    foreach ((array) $variable->get_variation_attributes() as $attribute_name => $options) {
        $key = sanitize_title((string) $attribute_name);
        $options = array_values(array_filter(array_map('strval', (array) $options), 'strlen'));

        if ($key && $options) {
            $attributes[$key] = $options;
        }
    }

    $multi_attributes = array_filter($attributes, function($options) {
        return count($options) > 1;
    });

    if (count($multi_attributes) !== 1) {
        $maps[$product_id] = [];
        return $maps[$product_id];
    }

    reset($multi_attributes);
    $primary_attribute = (string) key($multi_attributes);
    $primary_options = array_values($multi_attributes[$primary_attribute]);
    $single_attributes = [];

    foreach ($attributes as $attribute_name => $options) {
        if ($attribute_name !== $primary_attribute && count($options) === 1) {
            $single_attributes[$attribute_name] = $options[0];
        }
    }

    $children = meditrendy_wpc_bundle_variation_json_sorted_children($variable);

    if (count($children) !== count($primary_options)) {
        $maps[$product_id] = [];
        return $maps[$product_id];
    }

    $map = [];

    foreach ($children as $index => $variation_id) {
        $expected = [
            'attribute_' . $primary_attribute => $primary_options[$index] ?? '',
        ];

        foreach ($single_attributes as $attribute_name => $value) {
            $expected['attribute_' . $attribute_name] = $value;
        }

        $map[(int) $variation_id] = array_filter($expected, 'strlen');
    }

    $maps[$product_id] = $map;

    return $maps[$product_id];
}

function meditrendy_wpc_bundle_variation_json_attributes_from_variation($variation) {
    if (!$variation || !is_a($variation, 'WC_Product_Variation')) {
        return [];
    }

    $attributes = [];

    foreach ((array) $variation->get_attributes() as $attribute_name => $value) {
        $attribute_name = sanitize_title((string) $attribute_name);
        $value = (string) $value;

        if ($attribute_name === '' || $value === '') {
            continue;
        }

        $attributes['attribute_' . $attribute_name] = $value;
    }

    return $attributes;
}

function meditrendy_wpc_bundle_variation_json_merge_missing_attributes($data, $expected_attributes) {
    if (empty($expected_attributes)) {
        return $data;
    }

    if (empty($data['attributes']) || !is_array($data['attributes'])) {
        $data['attributes'] = [];
    }

    foreach ($expected_attributes as $attribute_name => $expected_value) {
        $current_value = isset($data['attributes'][$attribute_name]) ? (string) $data['attributes'][$attribute_name] : '';

        if ($current_value !== '' && $current_value !== $expected_value) {
            return $data;
        }
    }

    foreach ($expected_attributes as $attribute_name => $expected_value) {
        if (!isset($data['attributes'][$attribute_name]) || (string) $data['attributes'][$attribute_name] === '') {
            $data['attributes'][$attribute_name] = $expected_value;
        }
    }

    return $data;
}

function meditrendy_wpc_bundle_variation_json_fill_missing_attributes($data, $variable, $variation) {
    if (!$variable || !$variation || !is_a($variable, 'WC_Product_Variable') || !is_a($variation, 'WC_Product_Variation')) {
        return $data;
    }

    $data = meditrendy_wpc_bundle_variation_json_merge_missing_attributes(
        $data,
        meditrendy_wpc_bundle_variation_json_attributes_from_variation($variation)
    );

    $bundled_child_ids = meditrendy_wpc_bundle_variation_json_bundled_child_ids();

    if (!in_array(absint($variable->get_id()), $bundled_child_ids, true)) {
        return $data;
    }

    $map = meditrendy_wpc_bundle_variation_json_expected_map($variable);
    $variation_id = absint($variation->get_id());

    if (empty($map[$variation_id])) {
        return $data;
    }

    return meditrendy_wpc_bundle_variation_json_merge_missing_attributes($data, $map[$variation_id]);
}
add_filter('woocommerce_available_variation', 'meditrendy_wpc_bundle_variation_json_fill_missing_attributes', 9999, 3);

function meditrendy_wpc_bundle_variation_json_fallback_variations($product_id) {
    $product_id = absint($product_id);

    if (!$product_id || !function_exists('wc_get_product') || get_post_status($product_id) !== 'publish') {
        return [];
    }

    $product = wc_get_product($product_id);

    if (!$product || !is_a($product, 'WC_Product_Variable')) {
        return [];
    }

    $variations = [];
    $children = meditrendy_wpc_bundle_variation_json_sorted_children($product);
    $hide_out_of_stock = 'yes' === get_option('woocommerce_hide_out_of_stock_items');

    foreach ($children as $variation_id) {
        $variation = wc_get_product($variation_id);

        if (!$variation || !is_a($variation, 'WC_Product_Variation')) {
            continue;
        }

        if ($hide_out_of_stock && !$variation->is_in_stock()) {
            continue;
        }

        if (!$variation->is_purchasable()) {
            continue;
        }

        $variation_data = $product->get_available_variation($variation);

        if (!is_array($variation_data)) {
            continue;
        }

        $variations[] = meditrendy_wpc_bundle_variation_json_fill_missing_attributes(
            $variation_data,
            $product,
            $variation
        );
    }

    return array_values($variations);
}

function meditrendy_wpc_bundle_variation_json_ajax_variations() {
    $product_id = isset($_REQUEST['product_id']) ? absint(wp_unslash($_REQUEST['product_id'])) : 0;

    wp_send_json_success([
        'variations' => meditrendy_wpc_bundle_variation_json_fallback_variations($product_id),
    ]);
}
add_action('wp_ajax_meditrendy_wpc_bundle_variations', 'meditrendy_wpc_bundle_variation_json_ajax_variations');
add_action('wp_ajax_nopriv_meditrendy_wpc_bundle_variations', 'meditrendy_wpc_bundle_variation_json_ajax_variations');
