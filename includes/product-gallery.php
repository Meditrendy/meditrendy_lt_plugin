<?php
if (!defined('ABSPATH')) exit;

function meditrendy_product_gallery_remove_zoom() {
    remove_theme_support('wc-product-gallery-zoom');
}

function meditrendy_product_gallery_carousel_options($options) {
    $options['animationLoop'] = true;
    $options['slideshow'] = false;
    $options['controlNav'] = 'thumbnails';
    $options['directionNav'] = true;
    $options['smoothHeight'] = true;
    $options['animation'] = 'slide';

    return $options;
}

function meditrendy_product_gallery_enqueue_assets() {
    if (is_admin() || !function_exists('is_product') || !is_product()) {
        return;
    }

    $script_path = MEDITRENDY_CORE_DIR . 'assets/js/product-gallery.js';

    wp_enqueue_script(
        'meditrendy-product-gallery',
        MEDITRENDY_CORE_URL . 'assets/js/product-gallery.js',
        [],
        file_exists($script_path) ? filemtime($script_path) : '1.0',
        true
    );
}

add_action('after_setup_theme', 'meditrendy_product_gallery_remove_zoom', 11);
add_filter('woocommerce_single_product_carousel_options', 'meditrendy_product_gallery_carousel_options');
add_action('wp_enqueue_scripts', 'meditrendy_product_gallery_enqueue_assets', 35);
