<?php
if (!defined('ABSPATH')) exit;

function meditrendy_current_language_slug() {
    if (function_exists('meditrendy_core_current_language')) {
        return meditrendy_core_current_language();
    }

    if (function_exists('pll_current_language')) {
        $language = pll_current_language('slug');

        if ($language) {
            return $language === 'ee' ? 'et' : $language;
        }
    }

    $locale = function_exists('determine_locale') ? determine_locale() : get_locale();

    return substr((string) $locale, 0, 2);
}

function meditrendy_product_attribute_label_translations() {
    return [
        'lt' => [
            'color'     => 'Spalva',
            'pa_color'  => 'Spalva',
            'colour'    => 'Spalva',
            'size'      => 'Dydis',
            'pa_size'   => 'Dydis',
            'length'    => 'Ilgis',
            'pa_length' => 'Ilgis',
        ],
        'lv' => [
            'color'     => 'Krāsa',
            'pa_color'  => 'Krāsa',
            'colour'    => 'Krāsa',
            'size'      => 'Izmērs',
            'pa_size'   => 'Izmērs',
            'length'    => 'Garums',
            'pa_length' => 'Garums',
        ],
        'et' => [
            'color'     => 'Värv',
            'pa_color'  => 'Värv',
            'colour'    => 'Värv',
            'size'      => 'Suurus',
            'pa_size'   => 'Suurus',
            'length'    => 'Pikkus',
            'pa_length' => 'Pikkus',
        ],
    ];
}

function meditrendy_translate_product_attribute_label($label, $name = '', $product = null) {
    $language = meditrendy_current_language_slug();
    $translations = meditrendy_product_attribute_label_translations();

    if (!empty($translations[$language])) {
        $keys = array_unique(array_filter([
            sanitize_title($name),
            strpos((string) $name, 'attribute_') === 0 ? sanitize_title(substr((string) $name, 10)) : '',
            sanitize_title($label),
            sanitize_key($name),
            sanitize_key($label),
        ]));

        foreach ($keys as $key) {
            if (isset($translations[$language][$key])) {
                return $translations[$language][$key];
            }
        }
    }

    $translated_label = __($label, 'meditrendy-core');

    return $translated_label !== $label ? $translated_label : $label;
}
add_filter('woocommerce_attribute_label', 'meditrendy_translate_product_attribute_label', 20, 3);

/**
 * Check whether WooCommerce is rendering the classic product editor.
 *
 * Variation rows are loaded over AJAX, where the current screen is not
 * available, so those requests need to be recognized separately.
 */
function meditrendy_is_product_attribute_editor_request() {
    if (!is_admin()) {
        return false;
    }

    if (wp_doing_ajax()) {
        $action = isset($_REQUEST['action'])
            ? sanitize_key(wp_unslash($_REQUEST['action']))
            : '';

        return in_array($action, [
            'woocommerce_add_variation',
            'woocommerce_load_variations',
        ], true);
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;

    return $screen
        && isset($screen->post_type, $screen->base)
        && 'product' === $screen->post_type
        && in_array($screen->base, ['post', 'post-new'], true);
}

/**
 * Temporarily expose global attribute slugs while editing product variations.
 */
function meditrendy_add_product_attribute_slug_to_admin_label($label, $name = '', $product = null) {
    if (!meditrendy_is_product_attribute_editor_request()) {
        return $label;
    }

    $taxonomy = preg_replace('/^attribute_/', '', (string) $name);

    if (!$taxonomy || !function_exists('taxonomy_is_product_attribute') || !taxonomy_is_product_attribute($taxonomy)) {
        return $label;
    }

    $slug = function_exists('wc_attribute_taxonomy_slug')
        ? wc_attribute_taxonomy_slug($taxonomy)
        : preg_replace('/^pa_/', '', $taxonomy);

    if (!$slug) {
        return $label;
    }

    return sprintf('%1$s (%2$s)', $label, $slug);
}
add_filter('woocommerce_attribute_label', 'meditrendy_add_product_attribute_slug_to_admin_label', 100, 3);
