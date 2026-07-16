<?php
if (!defined('ABSPATH')) exit;

function meditrendy_size_option_sort_weight($option) {
    $size = strtolower((string) $option);
    $size = preg_replace('/[^a-z0-9]+/', '', $size);

    $weights = [
        'xxs' => 10,
        'xs'  => 20,
        's'   => 30,
        'm'   => 40,
        'l'   => 50,
        'xl'  => 60,
        'xxl' => 70,
        '2xl' => 70,
        '3xl' => 80,
        'xxxl' => 80,
        '4xl' => 90,
        '5xl' => 100,
    ];

    return $weights[$size] ?? 1000;
}

function meditrendy_sort_variation_size_options($args) {
    $taxonomy = isset($args['attribute']) ? sanitize_title((string) $args['attribute']) : '';

    if (!in_array($taxonomy, ['pa_rozmiar', 'pa_size', 'pa_dydis'], true) || empty($args['options']) || !is_array($args['options'])) {
        return $args;
    }

    $options = array_values($args['options']);

    usort($options, function($left, $right) {
        $left_weight = meditrendy_size_option_sort_weight($left);
        $right_weight = meditrendy_size_option_sort_weight($right);

        if ($left_weight === $right_weight) {
            return 0;
        }

        return $left_weight <=> $right_weight;
    });

    $args['options'] = $options;

    return $args;
}
add_filter('woocommerce_dropdown_variation_attribute_options_args', 'meditrendy_sort_variation_size_options', 5);
