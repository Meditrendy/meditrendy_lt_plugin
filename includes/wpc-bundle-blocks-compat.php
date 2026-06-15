<?php

if (!defined('ABSPATH')) exit;

/**
 * WPC Product Bundles compatibility for WooCommerce cart/checkout blocks.
 *
 * WPC enriches Store API cart items with top-level woosb_* fields and then its
 * block script turns those fields into the classes that hide bundled child rows.
 * Some checkout refreshes can lose that enrichment, so this late fallback keeps
 * the Store API response consistent without editing the vendor plugin.
 */

function meditrendy_woosb_blocks_is_store_api_request($request) {
    if (!is_object($request) || !is_callable([$request, 'get_route'])) {
        return false;
    }

    return strpos((string) $request->get_route(), 'wc/store') !== false;
}

function meditrendy_woosb_blocks_get_setting($key, $default = 'no') {
    if (!function_exists('WPCleverWoosb_Helper')) {
        return $default;
    }

    $helper = WPCleverWoosb_Helper();

    if (!is_object($helper) || !is_callable([$helper, 'get_setting'])) {
        return $default;
    }

    return $helper->get_setting($key, $default);
}

function meditrendy_woosb_blocks_get_cart_contents() {
    if (!function_exists('WC')) {
        return [];
    }

    if (!WC()->cart && function_exists('wc_load_cart')) {
        wc_load_cart();
    }

    if (!WC()->cart || !is_callable([WC()->cart, 'get_cart'])) {
        return [];
    }

    return WC()->cart->get_cart();
}

function meditrendy_woosb_blocks_get_item_flags($cart_item) {
    if (!is_array($cart_item)) {
        return [];
    }

    $flags = [];

    if (!empty($cart_item['woosb_ids'])) {
        $flags['woosb_bundles'] = true;
    }

    if (!empty($cart_item['woosb_parent_id'])) {
        $flags['woosb_bundled'] = true;

        if (meditrendy_woosb_blocks_get_setting('hide_bundled', 'no') !== 'no') {
            $flags['woosb_hide_bundled'] = true;
        }
    }

    if (!empty($cart_item['woosb_fixed_price'])) {
        $flags['woosb_fixed_price'] = true;
    }

    if (!empty($cart_item['woosb_price'])) {
        $flags['woosb_price'] = (float) $cart_item['woosb_price'];
    }

    return $flags;
}

function meditrendy_woosb_blocks_set_quantity_not_editable(&$item_data) {
    if (!isset($item_data['quantity_limits'])) {
        return;
    }

    if (is_object($item_data['quantity_limits'])) {
        $item_data['quantity_limits']->editable = false;
        return;
    }

    if (is_array($item_data['quantity_limits'])) {
        $item_data['quantity_limits']['editable'] = false;
    }
}

function meditrendy_woosb_blocks_maybe_prefix_child_name($name, $cart_item) {
    if (empty($cart_item['woosb_parent_id'])) {
        return $name;
    }

    if (meditrendy_woosb_blocks_get_setting('hide_bundle_name', 'no') !== 'no') {
        return $name;
    }

    $parent_id = apply_filters('woosb_item_id', $cart_item['woosb_parent_id']);
    $parent_title = get_the_title($parent_id);

    if (!$parent_title) {
        return $name;
    }

    $plain_name = wp_strip_all_tags(html_entity_decode((string) $name, ENT_QUOTES, get_bloginfo('charset')));

    if (strpos($plain_name, $parent_title) !== false) {
        return $name;
    }

    return $parent_title . apply_filters('woosb_name_separator', ' &rarr; ') . $name;
}

function meditrendy_woosb_blocks_enrich_item_data($item_data, $cart_item) {
    if (!is_array($item_data) || !is_array($cart_item)) {
        return $item_data;
    }

    $flags = meditrendy_woosb_blocks_get_item_flags($cart_item);

    if (!$flags) {
        return $item_data;
    }

    foreach ($flags as $key => $value) {
        if (!isset($item_data[$key])) {
            $item_data[$key] = $value;
        }
    }

    if (!empty($cart_item['woosb_parent_id'])) {
        meditrendy_woosb_blocks_set_quantity_not_editable($item_data);

        if (isset($item_data['name'])) {
            $item_data['name'] = meditrendy_woosb_blocks_maybe_prefix_child_name($item_data['name'], $cart_item);
        }
    }

    return $item_data;
}

function meditrendy_woosb_blocks_enrich_items(&$items, $cart_contents) {
    if (!is_array($items) || !$cart_contents) {
        return;
    }

    foreach ($items as &$item_data) {
        if (!is_array($item_data) || empty($item_data['key'])) {
            continue;
        }

        $cart_item_key = (string) $item_data['key'];
        $cart_item = isset($cart_contents[$cart_item_key]) ? $cart_contents[$cart_item_key] : null;

        if (!$cart_item) {
            continue;
        }

        $item_data = meditrendy_woosb_blocks_enrich_item_data($item_data, $cart_item);
    }
}

function meditrendy_woosb_blocks_enrich_store_api_response($response, $server, $request) {
    if (is_wp_error($response) || !meditrendy_woosb_blocks_is_store_api_request($request)) {
        return $response;
    }

    if (!is_object($response) || !is_callable([$response, 'get_data']) || !is_callable([$response, 'set_data'])) {
        return $response;
    }

    $data = $response->get_data();

    if (!is_array($data)) {
        return $response;
    }

    $cart_contents = meditrendy_woosb_blocks_get_cart_contents();

    if (isset($data['items']) && is_array($data['items'])) {
        meditrendy_woosb_blocks_enrich_items($data['items'], $cart_contents);
    }

    if (isset($data['cart']['items']) && is_array($data['cart']['items'])) {
        meditrendy_woosb_blocks_enrich_items($data['cart']['items'], $cart_contents);
    }

    $response->set_data($data);

    return $response;
}
add_filter('rest_request_after_callbacks', 'meditrendy_woosb_blocks_enrich_store_api_response', 100, 3);
add_filter('woocommerce_hydration_request_after_callbacks', 'meditrendy_woosb_blocks_enrich_store_api_response', 100, 3);

function meditrendy_woosb_blocks_extension_schema() {
    return [
        'woosb_bundles' => [
            'description' => 'Whether this cart item is a WPC bundle parent.',
            'type'        => 'boolean',
            'readonly'    => true,
        ],
        'woosb_bundled' => [
            'description' => 'Whether this cart item is a bundled child item.',
            'type'        => 'boolean',
            'readonly'    => true,
        ],
        'woosb_hide_bundled' => [
            'description' => 'Whether this bundled child item should be hidden by WPC bundle display settings.',
            'type'        => 'boolean',
            'readonly'    => true,
        ],
        'woosb_fixed_price' => [
            'description' => 'Whether this bundle uses fixed-price child display handling.',
            'type'        => 'boolean',
            'readonly'    => true,
        ],
        'woosb_price' => [
            'description' => 'Bundle display price.',
            'type'        => ['number', 'null'],
            'readonly'    => true,
        ],
    ];
}

function meditrendy_woosb_blocks_extension_data($cart_item) {
    $flags = meditrendy_woosb_blocks_get_item_flags($cart_item);

    return [
        'woosb_bundles'      => !empty($flags['woosb_bundles']),
        'woosb_bundled'      => !empty($flags['woosb_bundled']),
        'woosb_hide_bundled' => !empty($flags['woosb_hide_bundled']),
        'woosb_fixed_price'  => !empty($flags['woosb_fixed_price']),
        'woosb_price'        => isset($flags['woosb_price']) ? (float) $flags['woosb_price'] : null,
    ];
}

add_action('woocommerce_blocks_loaded', function() {
    if (!function_exists('woocommerce_store_api_register_endpoint_data')) {
        return;
    }

    woocommerce_store_api_register_endpoint_data([
        'endpoint'        => 'cart-item',
        'namespace'       => 'meditrendy_woosb',
        'schema_callback' => 'meditrendy_woosb_blocks_extension_schema',
        'data_callback'   => 'meditrendy_woosb_blocks_extension_data',
        'schema_type'     => ARRAY_A,
    ]);
});

add_action('wp_enqueue_scripts', function() {
    if (is_admin()) {
        return;
    }

    $is_blocks_cart_context = (function_exists('is_cart') && is_cart())
        || (function_exists('is_checkout') && is_checkout());

    if (!$is_blocks_cart_context) {
        return;
    }

    if (function_exists('is_order_received_page') && is_order_received_page()) {
        return;
    }

    $asset_path = MEDITRENDY_CORE_DIR . 'assets/js/wpc-bundle-blocks-compat.js';

    wp_enqueue_script(
        'meditrendy-wpc-bundle-blocks-compat',
        MEDITRENDY_CORE_URL . 'assets/js/wpc-bundle-blocks-compat.js',
        ['wc-blocks-checkout'],
        file_exists($asset_path) ? filemtime($asset_path) : '1.0.0',
        true
    );
}, 20);
