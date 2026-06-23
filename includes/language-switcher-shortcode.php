<?php
if (!defined('ABSPATH')) exit;

function meditrendy_language_switcher_bool($value) {
    return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true) ? 1 : 0;
}

function meditrendy_language_switcher_class_names($class_names) {
    $classes = ['mt-language-switcher'];
    $extra_classes = preg_split('/\s+/', (string) $class_names);

    foreach ($extra_classes as $class_name) {
        $class_name = sanitize_html_class($class_name);

        if ($class_name !== '') {
            $classes[] = $class_name;
        }
    }

    return implode(' ', array_unique($classes));
}

function meditrendy_language_switcher_shortcode($atts) {
    if (!function_exists('pll_the_languages')) {
        return '';
    }

    $atts = shortcode_atts(
        [
            'dropdown'               => '1',
            'hide_current'           => '0',
            'hide_if_no_translation' => '0',
            'show_flags'             => '0',
            'show_names'             => '1',
            'display_names_as'       => 'name',
            'force_home'             => '0',
            'hide_if_empty'          => '0',
            'class'                  => '',
            'aria_label'             => __('Kalbos pasirinkimas', 'meditrendy-core'),
        ],
        $atts,
        'meditrendy_language_switcher'
    );

    $display_names_as = in_array($atts['display_names_as'], ['name', 'slug'], true) ? $atts['display_names_as'] : 'name';
    $show_flags = meditrendy_language_switcher_bool($atts['show_flags']);
    $show_names = meditrendy_language_switcher_bool($atts['show_names']);
    $dropdown = meditrendy_language_switcher_bool($atts['dropdown']);

    if (!$show_flags && !$show_names) {
        $show_names = 1;
    }

    if ($dropdown) {
        static $dropdown_index = 0;
        $dropdown_index++;
        $dropdown = $dropdown_index;
    }

    $switcher = pll_the_languages([
        'dropdown'               => $dropdown,
        'hide_current'           => meditrendy_language_switcher_bool($atts['hide_current']),
        'hide_if_no_translation' => meditrendy_language_switcher_bool($atts['hide_if_no_translation']),
        'show_flags'             => $show_flags,
        'show_names'             => $show_names,
        'display_names_as'       => $display_names_as,
        'force_home'             => meditrendy_language_switcher_bool($atts['force_home']),
        'hide_if_empty'          => meditrendy_language_switcher_bool($atts['hide_if_empty']),
        'echo'                   => 0,
        'item_spacing'           => 'discard',
    ]);

    if (empty($switcher)) {
        return '';
    }

    $classes = meditrendy_language_switcher_class_names($atts['class']);
    $label = $atts['aria_label'] !== '' ? $atts['aria_label'] : __('Kalbos pasirinkimas', 'meditrendy-core');

    if ($dropdown) {
        return sprintf(
            '<nav class="%1$s" aria-label="%2$s">%3$s</nav>',
            esc_attr($classes),
            esc_attr($label),
            $switcher
        );
    }

    return sprintf(
        '<nav class="%1$s" aria-label="%2$s"><ul class="mt-language-switcher-list">%3$s</ul></nav>',
        esc_attr($classes),
        esc_attr($label),
        $switcher
    );
}
add_shortcode('meditrendy_language_switcher', 'meditrendy_language_switcher_shortcode');
add_shortcode('mt_language_switcher', 'meditrendy_language_switcher_shortcode');
