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

function meditrendy_wpc_bundle_variation_json_request_context($refresh = false) {
    static $context = null;

    if (!$refresh && $context !== null) {
        return $context;
    }

    $context = [
        'set_id' => 0,
        'items'  => [],
    ];

    if (empty($_REQUEST['woosb_ids'])) {
        return $context;
    }

    $set_id = 0;

    if (!empty($_REQUEST['product_id'])) {
        $set_id = absint(wp_unslash($_REQUEST['product_id']));
    }

    if (!$set_id && !empty($_REQUEST['add-to-cart'])) {
        $set_id = absint(wp_unslash($_REQUEST['add-to-cart']));
    }

    $context['set_id'] = $set_id;
    $context['items'] = meditrendy_wpc_bundle_variation_json_parse_ids(wc_clean(wp_unslash($_REQUEST['woosb_ids'])));

    return $context;
}

function meditrendy_wpc_bundle_variation_json_parse_ids($ids) {
    $items = [];

    foreach (array_filter(array_map('trim', explode(',', (string) $ids))) as $ids_item) {
        $data = explode('/', $ids_item);
        $id = isset($data[0]) ? absint(rawurldecode((string) $data[0])) : 0;

        if (!$id) {
            continue;
        }

        $items[] = [
            'id'  => $id,
            'key' => isset($data[1]) ? sanitize_text_field(wp_unslash((string) $data[1])) : '',
            'qty' => isset($data[2]) ? max(0, (float) $data[2]) : 1.0,
        ];
    }

    return $items;
}

function meditrendy_wpc_bundle_variation_json_product_raw_price($product) {
    if (!$product || !is_a($product, 'WC_Product')) {
        return 0.0;
    }

    $helper = function_exists('WPCleverWoosb_Helper') ? WPCleverWoosb_Helper() : null;

    if ($helper && method_exists($helper, 'get_price')) {
        return (float) $helper->get_price($product);
    }

    return (float) $product->get_price();
}

function meditrendy_wpc_bundle_variation_json_selected_items_for_set($set_id, $selected_items = []) {
    $set = $set_id && function_exists('wc_get_product') ? wc_get_product($set_id) : null;

    if (!$set || !is_a($set, 'WC_Product') || !$set->is_type('woosb') || !method_exists($set, 'get_items')) {
        return [];
    }

    if ($selected_items) {
        return $selected_items;
    }

    $items = [];

    foreach ((array) $set->get_items() as $item) {
        if (empty($item['id'])) {
            continue;
        }

        $items[] = [
            'id'  => absint($item['id']),
            'key' => '',
            'qty' => isset($item['qty']) ? max(0, (float) $item['qty']) : 1.0,
        ];
    }

    return $items;
}

function meditrendy_wpc_bundle_variation_json_fallback_price($set_id, $variation_id, $selected_items = []) {
    $set_id = absint($set_id);
    $variation_id = absint($variation_id);

    if (!$set_id || !$variation_id || !function_exists('wc_get_product')) {
        return 0.0;
    }

    $set = wc_get_product($set_id);
    $variation = wc_get_product($variation_id);

    if (!$set || !$variation || !is_a($variation, 'WC_Product_Variation') || !$set->is_type('woosb')) {
        return 0.0;
    }

    if (method_exists($set, 'is_fixed_price') && $set->is_fixed_price()) {
        return 0.0;
    }

    $items = meditrendy_wpc_bundle_variation_json_selected_items_for_set($set_id, $selected_items);

    if (!$items) {
        return 0.0;
    }

    $target_total = (float) $set->get_price();

    if ($target_total <= 0) {
        return 0.0;
    }

    $known_total = 0.0;
    $missing_qty = 0.0;
    $target_missing_qty = 0.0;

    foreach ($items as $item) {
        $item_id = absint($item['id'] ?? 0);
        $qty = max(0.0, (float) ($item['qty'] ?? 1));

        if (!$item_id || $qty <= 0) {
            continue;
        }

        $item_product = wc_get_product($item_id);

        if (!$item_product || !is_a($item_product, 'WC_Product')) {
            continue;
        }

        $price = meditrendy_wpc_bundle_variation_json_product_raw_price($item_product);

        $is_target_item = $item_id === $variation_id
            || ($item_product->is_type('variable') && (int) $variation->get_parent_id() === $item_id);

        if ($price > 0) {
            $known_total += $price * $qty;
            continue;
        }

        $missing_qty += $qty;

        if ($is_target_item) {
            $target_missing_qty += $qty;
        }
    }

    if ($target_missing_qty <= 0 || $missing_qty <= 0) {
        return 0.0;
    }

    $remaining = $target_total - $known_total;

    if ($remaining <= 0) {
        return 0.0;
    }

    return (float) wc_format_decimal($remaining / $missing_qty, wc_get_price_decimals());
}

function meditrendy_wpc_bundle_variation_json_current_fallback_price($variation_id) {
    $context = meditrendy_wpc_bundle_variation_json_request_context();
    $variation = $variation_id && function_exists('wc_get_product') ? wc_get_product($variation_id) : null;
    $parent_id = $variation && is_a($variation, 'WC_Product_Variation') ? absint($variation->get_parent_id()) : 0;

    if (empty($context['set_id']) || empty($context['items'])) {
        return 0.0;
    }

    foreach ($context['items'] as $item) {
        $item_id = absint($item['id'] ?? 0);

        if ($item_id === absint($variation_id) || ($parent_id && $item_id === $parent_id)) {
            return meditrendy_wpc_bundle_variation_json_fallback_price($context['set_id'], $variation_id, $context['items']);
        }
    }

    return 0.0;
}

function meditrendy_wpc_bundle_variation_json_fallback_variations($product_id, $set_id = 0) {
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

        $variation_data = $product->get_available_variation($variation);

        if (!is_array($variation_data)) {
            $variation_data = meditrendy_wpc_bundle_variation_json_build_variation_data($product, $variation);
        }

        $variation_data = meditrendy_wpc_bundle_variation_json_fill_missing_attributes(
            $variation_data,
            $product,
            $variation
        );

        $fallback_price = meditrendy_wpc_bundle_variation_json_fallback_price($set_id, $variation->get_id());

        if ($fallback_price > 0 && !$variation_data['is_purchasable'] && $variation->is_in_stock()) {
            $variation_data['display_price'] = wc_get_price_to_display($variation, ['price' => $fallback_price]);
            $variation_data['display_regular_price'] = wc_get_price_to_display($variation, ['price' => $fallback_price]);
            $variation_data['is_purchasable'] = true;
            $variation_data['variation_is_visible'] = true;
            $variation_data['price_html'] = '<span class="price">' . wc_price($variation_data['display_price']) . '</span>';
        }

        $variations[] = $variation_data;
    }

    return array_values($variations);
}

function meditrendy_wpc_bundle_variation_json_build_variation_data($product, $variation) {
    if (!$product || !$variation || !is_a($product, 'WC_Product_Variable') || !is_a($variation, 'WC_Product_Variation')) {
        return [];
    }

    $show_variation_price = apply_filters(
        'woocommerce_show_variation_price',
        $variation->get_price() === '' || $product->get_variation_sale_price('min') !== $product->get_variation_sale_price('max') || $product->get_variation_regular_price('min') !== $product->get_variation_regular_price('max'),
        $product,
        $variation
    );

    return [
        'attributes'            => $variation->get_variation_attributes(),
        'availability_html'     => wc_get_stock_html($variation),
        'backorders_allowed'    => $variation->backorders_allowed(),
        'dimensions'            => $variation->get_dimensions(false),
        'dimensions_html'       => wc_format_dimensions($variation->get_dimensions(false)),
        'display_price'         => wc_get_price_to_display($variation),
        'display_regular_price' => wc_get_price_to_display($variation, ['price' => $variation->get_regular_price()]),
        'image'                 => wc_get_product_attachment_props($variation->get_image_id()),
        'image_id'              => $variation->get_image_id(),
        'is_downloadable'       => $variation->is_downloadable(),
        'is_in_stock'           => $variation->is_in_stock(),
        'is_purchasable'        => $variation->is_purchasable(),
        'is_sold_individually'  => $variation->is_sold_individually() ? 'yes' : 'no',
        'is_virtual'            => $variation->is_virtual(),
        'max_qty'               => 0 < $variation->get_max_purchase_quantity() ? $variation->get_max_purchase_quantity() : '',
        'min_qty'               => $variation->get_min_purchase_quantity(),
        'price_html'            => $show_variation_price ? '<span class="price">' . $variation->get_price_html() . '</span>' : '',
        'sku'                   => $variation->get_sku(),
        'variation_description' => wc_format_content($variation->get_description()),
        'variation_id'          => $variation->get_id(),
        'variation_is_active'   => $variation->variation_is_active(),
        'variation_is_visible'  => $variation->variation_is_visible(),
        'weight'                => $variation->get_weight(),
        'weight_html'           => wc_format_weight($variation->get_weight()),
    ];
}

function meditrendy_wpc_bundle_variation_json_ajax_variations() {
    $product_id = isset($_REQUEST['product_id']) ? absint(wp_unslash($_REQUEST['product_id'])) : 0;
    $set_id = isset($_REQUEST['set_id']) ? absint(wp_unslash($_REQUEST['set_id'])) : 0;

    wp_send_json_success([
        'variations' => meditrendy_wpc_bundle_variation_json_fallback_variations($product_id, $set_id),
    ]);
}
add_action('wp_ajax_meditrendy_wpc_bundle_variations', 'meditrendy_wpc_bundle_variation_json_ajax_variations');
add_action('wp_ajax_nopriv_meditrendy_wpc_bundle_variations', 'meditrendy_wpc_bundle_variation_json_ajax_variations');

function meditrendy_wpc_bundle_variation_json_capture_validation_context($passed, $product_id) {
    meditrendy_wpc_bundle_variation_json_request_context(true);

    return $passed;
}
add_filter('woocommerce_add_to_cart_validation', 'meditrendy_wpc_bundle_variation_json_capture_validation_context', 1, 2);

function meditrendy_wpc_bundle_variation_json_allow_bundle_variation_purchase($purchasable, $product) {
    if ($purchasable || !$product || !is_a($product, 'WC_Product_Variation')) {
        return $purchasable;
    }

    if (!$product->is_in_stock()) {
        return $purchasable;
    }

    return meditrendy_wpc_bundle_variation_json_current_fallback_price($product->get_id()) > 0 ? true : $purchasable;
}
add_filter('woocommerce_is_purchasable', 'meditrendy_wpc_bundle_variation_json_allow_bundle_variation_purchase', 20, 2);

function meditrendy_wpc_bundle_variation_json_allow_bundle_variation_visibility($visible, $variation_id, $product_id, $variation) {
    if ($visible || !$variation || !is_a($variation, 'WC_Product_Variation')) {
        return $visible;
    }

    if (!$variation->is_in_stock()) {
        return $visible;
    }

    return meditrendy_wpc_bundle_variation_json_current_fallback_price($variation_id) > 0 ? true : $visible;
}
add_filter('woocommerce_variation_is_visible', 'meditrendy_wpc_bundle_variation_json_allow_bundle_variation_visibility', 20, 4);

function meditrendy_wpc_bundle_variation_json_allow_bundle_variation_purchasable($purchasable, $variation) {
    if ($purchasable || !$variation || !is_a($variation, 'WC_Product_Variation')) {
        return $purchasable;
    }

    if (!$variation->is_in_stock()) {
        return $purchasable;
    }

    return meditrendy_wpc_bundle_variation_json_current_fallback_price($variation->get_id()) > 0 ? true : $purchasable;
}
add_filter('woocommerce_variation_is_purchasable', 'meditrendy_wpc_bundle_variation_json_allow_bundle_variation_purchasable', 20, 2);

function meditrendy_wpc_bundle_variation_json_cart_item_price_fallback($price, $cart_item) {
    if ((float) $price > 0 || empty($cart_item['woosb_parent_id']) || empty($cart_item['variation_id'])) {
        return $price;
    }

    $parent_key = isset($cart_item['woosb_parent_key']) ? (string) $cart_item['woosb_parent_key'] : '';
    $selected_items = [];

    if ($parent_key && !empty(WC()->cart->cart_contents[$parent_key]['woosb_ids'])) {
        $selected_items = meditrendy_wpc_bundle_variation_json_parse_ids(WC()->cart->cart_contents[$parent_key]['woosb_ids']);
    }

    $fallback_price = meditrendy_wpc_bundle_variation_json_fallback_price(
        absint($cart_item['woosb_parent_id']),
        absint($cart_item['variation_id']),
        $selected_items
    );

    return $fallback_price > 0 ? $fallback_price : $price;
}
add_filter('woosb_item_price_add_to_cart', 'meditrendy_wpc_bundle_variation_json_cart_item_price_fallback', 20, 2);

function meditrendy_wpc_bundle_variation_json_bundle_has_usable_children($product) {
    if (!$product || !is_a($product, 'WC_Product') || !$product->is_type('woosb') || !method_exists($product, 'get_items')) {
        return false;
    }

    $items = (array) $product->get_items();

    if (!$items) {
        return false;
    }

    foreach ($items as $item) {
        $child_id = !empty($item['id']) ? absint($item['id']) : 0;
        $child = $child_id ? wc_get_product($child_id) : false;

        if (!$child || $child->is_type('woosb')) {
            return false;
        }

        if ($child->is_type('variable')) {
            if (empty(meditrendy_wpc_bundle_variation_json_fallback_variations($child->get_id()))) {
                return false;
            }

            continue;
        }

        $qty = !empty($item['qty']) ? (float) $item['qty'] : 1;

        if (!$child->is_in_stock() || !$child->is_purchasable() || !$child->has_enough_stock($qty)) {
            return false;
        }
    }

    return true;
}

function meditrendy_wpc_bundle_variation_json_parent_stock_fallback($is_in_stock, $product) {
    if ($is_in_stock || is_admin() || !$product || !is_a($product, 'WC_Product') || !$product->is_type('woosb')) {
        return $is_in_stock;
    }

    return meditrendy_wpc_bundle_variation_json_bundle_has_usable_children($product) ? true : $is_in_stock;
}
add_filter('woocommerce_product_is_in_stock', 'meditrendy_wpc_bundle_variation_json_parent_stock_fallback', 20, 2);
