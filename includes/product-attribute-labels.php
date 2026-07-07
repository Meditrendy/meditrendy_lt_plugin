<?php
if (!defined('ABSPATH')) exit;

function meditrendy_current_language_slug() {
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
            'color'    => 'Spalva',
            'pa_color' => 'Spalva',
            'size'     => 'Dydis',
            'pa_size'  => 'Dydis',
            'length'   => 'Ilgis',
            'pa_length' => 'Ilgis',
        ],
        'lv' => [
            'color'    => 'Krāsa',
            'pa_color' => 'Krāsa',
            'size'     => 'Izmērs',
            'pa_size'  => 'Izmērs',
            'length'   => 'Garums',
            'pa_length' => 'Garums',
        ],
        'et' => [
            'color'    => 'Värv',
            'pa_color' => 'Värv',
            'size'     => 'Suurus',
            'pa_size'  => 'Suurus',
            'length'   => 'Pikkus',
            'pa_length' => 'Pikkus',
        ],
    ];
}

function meditrendy_translate_product_attribute_label($label, $name = '', $product = null) {
    $language = meditrendy_current_language_slug();
    $translations = meditrendy_product_attribute_label_translations();
    $translated_label = __($label, 'meditrendy-core');

    if ($translated_label !== $label) {
        return $translated_label;
    }

    if (empty($translations[$language])) {
        return $label;
    }

    $keys = array_unique(array_filter([
        sanitize_title($name),
        strpos((string) $name, 'attribute_') === 0 ? sanitize_title(substr((string) $name, 10)) : '',
        sanitize_title($label),
    ]));

    foreach ($keys as $key) {
        if (isset($translations[$language][$key])) {
            return $translations[$language][$key];
        }
    }

    return $label;
}
add_filter('woocommerce_attribute_label', 'meditrendy_translate_product_attribute_label', 20, 3);
