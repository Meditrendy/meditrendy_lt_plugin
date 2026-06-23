<?php
if (!defined('ABSPATH')) exit;

function meditrendy_core_current_language() {
    if (function_exists('pll_current_language')) {
        $language = strtolower((string) pll_current_language('slug'));

        if ($language) {
            return $language;
        }
    }

    $locale = function_exists('determine_locale') ? determine_locale() : get_locale();
    $locale = strtolower((string) $locale);

    if (strpos($locale, 'pl') === 0) {
        return 'pl';
    }

    if (strpos($locale, 'lv') === 0) {
        return 'lv';
    }

    if (strpos($locale, 'en') === 0) {
        return 'en';
    }

    return 'lt';
}

function meditrendy_core_translate_ui_text($text) {
    $text = (string) $text;
    $language = meditrendy_core_current_language();

    if ($language === 'lt' || $text === '') {
        return $text;
    }

    $translations = [
        'en' => [
            'AKCIJA' => 'SALE',
            'Akcija' => 'Sale',
            'Atgal' => 'Back',
            'DYDIS' => 'SIZE',
            'Filtrai' => 'Filters',
            'GAMINTOJAS' => 'BRAND',
            'ILGIS' => 'LENGTH',
            'Išvalyti' => 'Clear',
            'KAINA' => 'PRICE',
            'NAUJIENA' => 'NEW',
            'Produktų nerasta' => 'No products found',
            'Reset' => 'Reset',
            'Rodyti produktus' => 'Show products',
            'Rodyti rezultatus' => 'Show results',
            'SPALVA' => 'COLOUR',
            'Skaičiuojama...' => 'Counting...',
            'Uzdaryti filtrus' => 'Close filters',
            'Aktyvus filtrai' => 'Active filters',
        ],
        'lv' => [
            'AKCIJA' => 'AKCIJA',
            'Akcija' => 'Akcija',
            'Atgal' => 'Atpakaļ',
            'DYDIS' => 'IZMĒRS',
            'Filtrai' => 'Filtri',
            'GAMINTOJAS' => 'ZĪMOLS',
            'ILGIS' => 'GARUMS',
            'Išvalyti' => 'Notīrīt',
            'KAINA' => 'CENA',
            'NAUJIENA' => 'JAUNUMS',
            'Produktų nerasta' => 'Preces nav atrastas',
            'Reset' => 'Atiestatīt',
            'Rodyti produktus' => 'Rādīt preces',
            'Rodyti rezultatus' => 'Rādīt rezultātus',
            'SPALVA' => 'KRĀSA',
            'Skaičiuojama...' => 'Skaita...',
            'Uzdaryti filtrus' => 'Aizvērt filtrus',
            'Aktyvus filtrai' => 'Aktīvie filtri',
        ],
        'pl' => [
            'AKCIJA' => 'PROMOCJA',
            'Akcija' => 'Promocja',
            'Atgal' => 'Wstecz',
            'DYDIS' => 'ROZMIAR',
            'Filtrai' => 'Filtry',
            'GAMINTOJAS' => 'MARKA',
            'ILGIS' => 'DŁUGOŚĆ',
            'Išvalyti' => 'Wyczyść',
            'KAINA' => 'CENA',
            'NAUJIENA' => 'NOWOŚĆ',
            'Produktų nerasta' => 'Nie znaleziono produktów',
            'Reset' => 'Resetuj',
            'Rodyti produktus' => 'Pokaż produkty',
            'Rodyti rezultatus' => 'Pokaż wyniki',
            'SPALVA' => 'KOLOR',
            'Skaičiuojama...' => 'Liczenie...',
            'Uzdaryti filtrus' => 'Zamknij filtry',
            'Aktyvus filtrai' => 'Aktywne filtry',
        ],
    ];

    return $translations[$language][$text] ?? $text;
}

function meditrendy_filter_settings_available_filters() {
    $filters = [
        'color' => [
            'name'    => 'Color group',
            'label'   => 'SPALVA',
            'order'   => 10,
            'enabled' => 1,
            'core'    => true,
        ],
        'size' => [
            'name'    => 'Size',
            'label'   => 'DYDIS',
            'order'   => 20,
            'enabled' => 1,
            'core'    => true,
        ],
        'length' => [
            'name'    => 'Length',
            'label'   => 'ILGIS',
            'order'   => 30,
            'enabled' => 1,
            'core'    => true,
        ],
        'brand' => [
            'name'    => 'Brand',
            'label'   => 'GAMINTOJAS',
            'order'   => 40,
            'enabled' => 1,
            'core'    => true,
        ],
        'price' => [
            'name'    => 'Price',
            'label'   => 'KAINA',
            'order'   => 50,
            'enabled' => 1,
            'core'    => true,
        ],
    ];

    $core_attribute_names = [
        'color-group',
        'size',
        'length',
        'ilgis',
        'kelniu-ilgis',
        'pants-length',
        'brand',
    ];

    if (function_exists('wc_get_attribute_taxonomies')) {
        foreach ((array) wc_get_attribute_taxonomies() as $attribute) {
            if (empty($attribute->attribute_name)) {
                continue;
            }

            $attribute_name = sanitize_title($attribute->attribute_name);

            if (in_array($attribute_name, $core_attribute_names, true)) {
                continue;
            }

            $key = 'attr_' . sanitize_key($attribute_name);

            $filters[$key] = [
                'name'      => $attribute->attribute_label ?: $attribute_name,
                'label'     => strtoupper($attribute->attribute_label ?: $attribute_name),
                'order'     => 100,
                'enabled'   => 0,
                'core'      => false,
                'attribute' => $attribute_name,
                'taxonomy'  => function_exists('wc_attribute_taxonomy_name') ? wc_attribute_taxonomy_name($attribute_name) : 'pa_' . $attribute_name,
            ];
        }
    }

    return $filters;
}

function meditrendy_filter_settings_defaults() {
    $filters = [];

    foreach (meditrendy_filter_settings_available_filters() as $key => $filter) {
        $filters[$key] = [
            'enabled' => (int) $filter['enabled'],
            'label'   => $filter['label'],
            'order'   => (int) $filter['order'],
        ];
    }

    return [
        'filters' => $filters,
        'labels' => [
            'trigger'      => 'Filtrai',
            'panel'        => 'Filtrai',
            'submit'       => 'Rodyti rezultatus',
            'show_products' => 'Rodyti produktus',
            'reset'        => 'Išvalyti',
            'active_reset' => 'Reset',
            'loading'      => 'Skaičiuojama...',
            'no_products'  => 'Produktų nerasta',
        ],
        'show_counts'        => 1,
        'disable_unavailable' => 1,
        'hide_empty_initial' => 1,
    ];
}

function meditrendy_filter_settings() {
    $saved = get_option('meditrendy_filter_settings', []);
    $settings = wp_parse_args(is_array($saved) ? $saved : [], meditrendy_filter_settings_defaults());
    $settings['filters'] = wp_parse_args($settings['filters'], meditrendy_filter_settings_defaults()['filters']);
    $settings['labels'] = wp_parse_args($settings['labels'], meditrendy_filter_settings_defaults()['labels']);

    foreach (meditrendy_filter_settings_defaults()['filters'] as $key => $defaults) {
        $settings['filters'][$key] = wp_parse_args($settings['filters'][$key] ?? [], $defaults);
    }

    return $settings;
}

function meditrendy_filter_settings_sanitize($input) {
    $input = is_array($input) ? $input : [];
    $defaults = meditrendy_filter_settings_defaults();
    $output = $defaults;

    foreach ($defaults['filters'] as $key => $filter_defaults) {
        $filter_input = $input['filters'][$key] ?? [];
        $output['filters'][$key]['enabled'] = !empty($filter_input['enabled']) ? 1 : 0;
        $output['filters'][$key]['label'] = sanitize_text_field($filter_input['label'] ?? $filter_defaults['label']);
        $output['filters'][$key]['order'] = absint($filter_input['order'] ?? $filter_defaults['order']);
    }

    foreach ($defaults['labels'] as $key => $label_default) {
        $output['labels'][$key] = sanitize_text_field($input['labels'][$key] ?? $label_default);
    }

    $output['show_counts'] = !empty($input['show_counts']) ? 1 : 0;
    $output['disable_unavailable'] = !empty($input['disable_unavailable']) ? 1 : 0;
    $output['hide_empty_initial'] = !empty($input['hide_empty_initial']) ? 1 : 0;

    return $output;
}

function meditrendy_filter_setting_label($key) {
    $settings = meditrendy_filter_settings();
    $label = $settings['labels'][$key] ?? meditrendy_filter_settings_defaults()['labels'][$key] ?? '';

    return meditrendy_core_translate_ui_text($label);
}

function meditrendy_register_filter_settings() {
    register_setting(
        'meditrendy_filter_settings',
        'meditrendy_filter_settings',
        [
            'sanitize_callback' => 'meditrendy_filter_settings_sanitize',
            'default'           => meditrendy_filter_settings_defaults(),
        ]
    );
}
add_action('admin_init', 'meditrendy_register_filter_settings');

add_filter('option_page_capability_meditrendy_filter_settings', 'meditrendy_filter_settings_capability');

function meditrendy_filter_settings_capability() {
    return current_user_can('manage_woocommerce') ? 'manage_woocommerce' : 'manage_options';
}

function meditrendy_filter_settings_admin_menu() {
    add_menu_page(
        'Meditrendy',
        'Meditrendy',
        meditrendy_filter_settings_capability(),
        'meditrendy-settings',
        'meditrendy_render_filter_settings_page',
        'dashicons-filter',
        56
    );

    add_submenu_page(
        'meditrendy-settings',
        'Filters',
        'Filters',
        meditrendy_filter_settings_capability(),
        'meditrendy-settings',
        'meditrendy_render_filter_settings_page'
    );
}
add_action('admin_menu', 'meditrendy_filter_settings_admin_menu');

function meditrendy_render_filter_settings_page() {
    if (!current_user_can(meditrendy_filter_settings_capability())) {
        return;
    }

    $settings = meditrendy_filter_settings();
    $available_filters = meditrendy_filter_settings_available_filters();
    ?>
    <div class="wrap">
        <h1>Meditrendy filters</h1>
        <form method="post" action="options.php">
            <?php settings_fields('meditrendy_filter_settings'); ?>

            <h2>Filters</h2>
            <table class="widefat striped" style="max-width: 900px;">
                <thead>
                    <tr>
                        <th>Filters</th>
                        <th>Enabled</th>
                        <th>Label</th>
                        <th>Order</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($available_filters as $key => $filter_info) : ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($filter_info['name']); ?></strong>
                                <?php if (empty($filter_info['core'])) : ?>
                                    <br><small><?php echo esc_html($filter_info['taxonomy']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <label>
                                    <input
                                        type="checkbox"
                                        name="meditrendy_filter_settings[filters][<?php echo esc_attr($key); ?>][enabled]"
                                        value="1"
                                        <?php checked(!empty($settings['filters'][$key]['enabled'])); ?>
                                    >
                                    Show
                                </label>
                            </td>
                            <td>
                                <input
                                    type="text"
                                    class="regular-text"
                                    name="meditrendy_filter_settings[filters][<?php echo esc_attr($key); ?>][label]"
                                    value="<?php echo esc_attr($settings['filters'][$key]['label']); ?>"
                                >
                            </td>
                            <td>
                                <input
                                    type="number"
                                    min="0"
                                    step="1"
                                    style="width: 90px;"
                                    name="meditrendy_filter_settings[filters][<?php echo esc_attr($key); ?>][order]"
                                    value="<?php echo esc_attr((int) $settings['filters'][$key]['order']); ?>"
                                >
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h2>Texts</h2>
            <table class="form-table" role="presentation">
                <?php
                $labels = [
                    'trigger'      => 'Mobile button',
                    'panel'        => 'Panel title',
                    'submit'       => 'Show results',
                    'show_products' => 'Loaded button',
                    'reset'        => 'Clear',
                    'active_reset' => 'Active filters reset',
                    'loading'      => 'Count',
                    'no_products'  => 'No products',
                ];
                ?>
                <?php foreach ($labels as $key => $label) : ?>
                    <tr>
                        <th scope="row"><label for="meditrendy-filter-label-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
                        <td>
                            <input
                                id="meditrendy-filter-label-<?php echo esc_attr($key); ?>"
                                type="text"
                                class="regular-text"
                                name="meditrendy_filter_settings[labels][<?php echo esc_attr($key); ?>]"
                                value="<?php echo esc_attr($settings['labels'][$key]); ?>"
                            >
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <h2>Behavior</h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Numbers next to options</th>
                    <td>
                        <label>
                            <input type="checkbox" name="meditrendy_filter_settings[show_counts]" value="1" <?php checked(!empty($settings['show_counts'])); ?>>
                            Display product counts next to each filter option
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Options with no results</th>
                    <td>
                        <label>
                            <input type="checkbox" name="meditrendy_filter_settings[disable_unavailable]" value="1" <?php checked(!empty($settings['disable_unavailable'])); ?>>
                            Fade and disable options with no products for the current filter selection
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Empty filters</th>
                    <td>
                        <label>
                            <input type="checkbox" name="meditrendy_filter_settings[hide_empty_initial]" value="1" <?php checked(!empty($settings['hide_empty_initial'])); ?>>
                            Hide filter options with no products in the current category
                        </label>
                    </td>
                </tr>
            </table>

            <?php submit_button('Save filter settings'); ?>
        </form>
    </div>
    <?php
}
