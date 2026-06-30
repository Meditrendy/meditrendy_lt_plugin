<?php
if (!defined('ABSPATH')) exit;

function meditrendy_set_variation_status_should_enqueue() {
    if (is_admin() || !function_exists('is_product') || !is_product() || !function_exists('wc_get_product')) {
        return false;
    }

    $product = wc_get_product(get_queried_object_id());

    return $product && ($product->is_type('variable') || $product->is_type('woosb'));
}

function meditrendy_set_variation_status_enqueue_assets() {
    if (!meditrendy_set_variation_status_should_enqueue()) {
        return;
    }

    $script_path = MEDITRENDY_CORE_DIR . 'assets/js/product-set-variation-status.js';
    $bundle_json_script_path = MEDITRENDY_CORE_DIR . 'assets/js/wpc-bundle-variation-json-compat.js';
    $style_path = MEDITRENDY_CORE_DIR . 'assets/css/product-set-variation-status.css';

    wp_enqueue_script(
        'meditrendy-wpc-bundle-variation-json-compat',
        MEDITRENDY_CORE_URL . 'assets/js/wpc-bundle-variation-json-compat.js',
        [],
        file_exists($bundle_json_script_path) ? filemtime($bundle_json_script_path) : '1.0',
        true
    );

    wp_localize_script(
        'meditrendy-wpc-bundle-variation-json-compat',
        'meditrendyWpcBundleVariations',
        [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'action'  => 'meditrendy_wpc_bundle_variations',
        ]
    );

    wp_enqueue_script(
        'meditrendy-product-set-variation-status',
        MEDITRENDY_CORE_URL . 'assets/js/product-set-variation-status.js',
        [],
        file_exists($script_path) ? filemtime($script_path) : '1.0',
        true
    );

    wp_enqueue_style(
        'meditrendy-product-set-variation-status',
        MEDITRENDY_CORE_URL . 'assets/css/product-set-variation-status.css',
        [],
        file_exists($style_path) ? filemtime($style_path) : '1.0'
    );
}

add_action('wp_enqueue_scripts', 'meditrendy_set_variation_status_enqueue_assets', 55);
