<?php
if (!defined('ABSPATH')) exit;

function meditrendy_preset_translation_languages() {
    return [
        'lt' => __('Lithuanian', 'meditrendy-core'),
        'lv' => __('Latvian', 'meditrendy-core'),
        'et' => __('Estonian', 'meditrendy-core'),
    ];
}

function meditrendy_preset_translation_fields() {
    return [
        'details_fit' => [
            'label' => __('Description and sizes', 'meditrendy-core'),
            'defaults' => [
                'lt' => 'Aprašymas ir dydžiai',
                'lv' => 'Apraksts un izmēri',
                'et' => 'Kirjeldus ja suurused',
            ],
        ],
        'fabric' => [
            'label' => __('Fabric and care', 'meditrendy-core'),
            'defaults' => [
                'lt' => 'Audinys ir priežiūra',
                'lv' => 'Audums un kopšana',
                'et' => 'Kangas ja hooldus',
            ],
        ],
        'delivery_info' => [
            'label' => __('Delivery information', 'meditrendy-core'),
            'defaults' => [
                'lt' => 'Pristatymo informacija',
                'lv' => 'Piegādes informācija',
                'et' => 'Tarneinfo',
            ],
        ],
    ];
}

function meditrendy_preset_translation_language() {
    if (function_exists('pll_current_language')) {
        $language = strtolower((string) pll_current_language('slug'));

        if ($language === 'ee') {
            return 'et';
        }

        if (isset(meditrendy_preset_translation_languages()[$language])) {
            return $language;
        }
    }

    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));

    if (strpos($host, 'meditrendy.lv') !== false) {
        return 'lv';
    }

    if (strpos($host, 'meditrendy.ee') !== false) {
        return 'et';
    }

    return 'lt';
}

function meditrendy_preset_translation_meta_key($language, $field, $part) {
    return '_mt_preset_translation_' . sanitize_key($language) . '_' . sanitize_key($field) . '_' . sanitize_key($part);
}

function meditrendy_preset_translation_value($preset_id, $language, $field, $part) {
    return (string) get_post_meta(
        $preset_id,
        meditrendy_preset_translation_meta_key($language, $field, $part),
        true
    );
}

function meditrendy_preset_translated_field($preset_id, $field, $field_object = null) {
    $fields = meditrendy_preset_translation_fields();

    if (empty($fields[$field])) {
        return [
            'label' => '',
            'value' => '',
        ];
    }

    if (!is_array($field_object) && function_exists('get_field_object')) {
        $field_object = get_field_object($field, $preset_id);
    }

    $language = meditrendy_preset_translation_language();
    $fallback_label = is_array($field_object) && !empty($field_object['label'])
        ? (string) $field_object['label']
        : ($fields[$field]['defaults']['lt'] ?? $fields[$field]['label']);
    $fallback_value = is_array($field_object) && isset($field_object['value'])
        ? (string) $field_object['value']
        : '';
    $default_label = $fields[$field]['defaults'][$language] ?? $fallback_label;
    $label = trim(meditrendy_preset_translation_value($preset_id, $language, $field, 'label'));
    $value = trim(meditrendy_preset_translation_value($preset_id, $language, $field, 'content'));

    if ($label === '') {
        $label = $language === 'lt' ? $fallback_label : $default_label;
    }

    if ($value === '') {
        $value = $fallback_value;
    }

    return [
        'label' => $label,
        'value' => $value,
    ];
}

function meditrendy_preset_post_has_translation_fields($post) {
    if (!$post || !function_exists('get_field_object')) {
        return false;
    }

    foreach (array_keys(meditrendy_preset_translation_fields()) as $field) {
        if (get_field_object($field, $post->ID)) {
            return true;
        }
    }

    return in_array($post->post_type, ['preset', 'presets', 'mt_preset'], true);
}

function meditrendy_preset_translations_add_meta_box($post_type, $post) {
    if (!meditrendy_preset_post_has_translation_fields($post)) {
        return;
    }

    add_meta_box(
        'meditrendy_preset_translations',
        __('Preset translations', 'meditrendy-core'),
        'meditrendy_preset_translations_render_meta_box',
        $post_type,
        'normal',
        'default'
    );
}
add_action('add_meta_boxes', 'meditrendy_preset_translations_add_meta_box', 20, 2);

function meditrendy_preset_translations_render_meta_box($post) {
    $languages = meditrendy_preset_translation_languages();
    $fields = meditrendy_preset_translation_fields();

    wp_nonce_field('meditrendy_preset_translations_save', 'meditrendy_preset_translations_nonce');
    ?>
    <div class="mt-preset-translations">
        <p class="description"><?php esc_html_e('Fill the translated preset text for each language. Empty content falls back to the original Lithuanian preset field.', 'meditrendy-core'); ?></p>

        <?php foreach ($languages as $language => $language_label) : ?>
            <section class="mt-preset-translations-language">
                <h3><?php echo esc_html($language_label); ?></h3>

                <?php foreach ($fields as $field => $config) :
                    $field_object = function_exists('get_field_object') ? get_field_object($field, $post->ID) : null;
                    $default_label = $config['defaults'][$language] ?? $config['label'];
                    $stored_label = meditrendy_preset_translation_value($post->ID, $language, $field, 'label');
                    $stored_content = meditrendy_preset_translation_value($post->ID, $language, $field, 'content');

                    if ($language === 'lt') {
                        if ($stored_label === '' && is_array($field_object) && !empty($field_object['label'])) {
                            $stored_label = (string) $field_object['label'];
                        }

                        if ($stored_content === '' && is_array($field_object) && isset($field_object['value'])) {
                            $stored_content = (string) $field_object['value'];
                        }
                    }
                    ?>
                    <div class="mt-preset-translations-field">
                        <label>
                            <span><?php echo esc_html($config['label']); ?> - <?php esc_html_e('accordion title', 'meditrendy-core'); ?></span>
                            <input
                                type="text"
                                class="widefat"
                                name="mt_preset_translations[<?php echo esc_attr($language); ?>][<?php echo esc_attr($field); ?>][label]"
                                value="<?php echo esc_attr($stored_label ?: $default_label); ?>"
                            >
                        </label>

                        <label class="mt-preset-translations-content-label" for="mt_preset_<?php echo esc_attr($language . '_' . $field); ?>">
                            <?php echo esc_html($config['label']); ?> - <?php esc_html_e('content', 'meditrendy-core'); ?>
                        </label>
                        <?php
                        wp_editor(
                            $stored_content,
                            'mt_preset_' . $language . '_' . $field,
                            [
                                'textarea_name' => 'mt_preset_translations[' . $language . '][' . $field . '][content]',
                                'textarea_rows' => 6,
                                'media_buttons' => false,
                                'teeny' => true,
                            ]
                        );
                        ?>
                    </div>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>
    </div>
    <style>
        .mt-preset-translations-language {
            border: 1px solid #dcdcde;
            margin: 16px 0;
            padding: 12px 14px 4px;
            background: #fff;
        }
        .mt-preset-translations-language h3 {
            margin: 0 0 12px;
        }
        .mt-preset-translations-field {
            margin: 0 0 18px;
        }
        .mt-preset-translations-field label span,
        .mt-preset-translations-content-label {
            display: block;
            font-weight: 600;
            margin: 0 0 6px;
        }
        .mt-preset-translations-field .wp-editor-wrap {
            margin-top: 6px;
        }
    </style>
    <?php
}

function meditrendy_preset_translations_save($post_id) {
    if (
        empty($_POST['meditrendy_preset_translations_nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['meditrendy_preset_translations_nonce'])), 'meditrendy_preset_translations_save')
    ) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $posted = isset($_POST['mt_preset_translations']) && is_array($_POST['mt_preset_translations'])
        ? wp_unslash($_POST['mt_preset_translations'])
        : [];
    $languages = meditrendy_preset_translation_languages();
    $fields = meditrendy_preset_translation_fields();

    foreach ($languages as $language => $_language_label) {
        foreach ($fields as $field => $_config) {
            foreach (['label', 'content'] as $part) {
                $key = meditrendy_preset_translation_meta_key($language, $field, $part);
                $value = $posted[$language][$field][$part] ?? '';
                $value = $part === 'content' ? wp_kses_post((string) $value) : sanitize_text_field((string) $value);

                if ($value === '') {
                    delete_post_meta($post_id, $key);
                } else {
                    update_post_meta($post_id, $key, $value);
                }
            }
        }
    }
}
add_action('save_post', 'meditrendy_preset_translations_save');
