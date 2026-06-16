<?php
if (!defined('ABSPATH')) exit;

function meditrendy_marketing_banner_capability() {
    return current_user_can('manage_woocommerce') ? 'manage_woocommerce' : 'manage_options';
}

function meditrendy_marketing_banner_languages() {
    if (function_exists('pll_languages_list')) {
        $languages = pll_languages_list(['fields' => '']);
        $items = [];

        foreach ((array) $languages as $language) {
            if (!is_object($language) || empty($language->slug)) {
                continue;
            }

            $items[$language->slug] = !empty($language->name) ? $language->name : strtoupper($language->slug);
        }

        if ($items) {
            return $items;
        }
    }

    return [
        'lt' => 'Lithuanian',
        'en' => 'English',
        'pl' => 'Polish',
    ];
}

function meditrendy_marketing_banner_current_language() {
    if (function_exists('pll_current_language')) {
        $language = pll_current_language('slug');

        if ($language) {
            return $language;
        }
    }

    if (function_exists('meditrendy_current_language_slug')) {
        return meditrendy_current_language_slug();
    }

    $locale = function_exists('determine_locale') ? determine_locale() : get_locale();

    return substr((string) $locale, 0, 2);
}

function meditrendy_marketing_banner_normalize_language($language) {
    $language = strtolower(trim((string) $language));

    if ($language === '') {
        return '';
    }

    return str_replace('_', '-', $language);
}

function meditrendy_marketing_banner_find_banner(array $banners, $language) {
    if (empty($banners)) {
        return null;
    }

    $normalized = meditrendy_marketing_banner_normalize_language($language);
    $candidates = array_unique(array_filter([
        $normalized,
        substr($normalized, 0, 2),
    ]));

    foreach ($candidates as $candidate) {
        if (isset($banners[$candidate]) && is_array($banners[$candidate])) {
            return [$candidate, $banners[$candidate]];
        }
    }

    foreach ($banners as $key => $banner) {
        $banner_key = meditrendy_marketing_banner_normalize_language($key);

        foreach ($candidates as $candidate) {
            if ($banner_key === $candidate || strpos($banner_key, $candidate . '-') === 0) {
                return [$key, is_array($banner) ? $banner : []];
            }
        }
    }

    return null;
}

function meditrendy_marketing_banner_default_banner() {
    return [
        'enabled'           => 0,
        'background_color'  => '#0f5ea8',
        'starts_at'         => '',
        'ends_at'           => '',
        'message'           => '',
        'coupon_enabled'    => 0,
        'coupon_code'       => '',
        'countdown_enabled' => 0,
        'countdown_ends_at' => '',
        'cta_enabled'       => 0,
        'cta_label'         => '',
        'cta_url'           => '',
        'close_enabled'     => 0,
    ];
}

function meditrendy_marketing_banner_defaults() {
    $banners = [];

    foreach (meditrendy_marketing_banner_languages() as $language => $label) {
        $banners[$language] = meditrendy_marketing_banner_default_banner();
    }

    return [
        'banners' => $banners,
    ];
}

function meditrendy_marketing_banner_settings() {
    $settings = get_option('meditrendy_marketing_banner', []);

    if (!is_array($settings)) {
        $settings = [];
    }

    $settings = wp_parse_args($settings, meditrendy_marketing_banner_defaults());

    if (empty($settings['banners']) || !is_array($settings['banners'])) {
        $settings['banners'] = [];
    }

    foreach (meditrendy_marketing_banner_languages() as $language => $label) {
        $settings['banners'][$language] = wp_parse_args(
            isset($settings['banners'][$language]) && is_array($settings['banners'][$language]) ? $settings['banners'][$language] : [],
            meditrendy_marketing_banner_default_banner()
        );
    }

    return $settings;
}

function meditrendy_marketing_banner_sanitize_datetime($value) {
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value)) {
        return '';
    }

    return $value;
}

function meditrendy_marketing_banner_sanitize_color($value) {
    $value = sanitize_hex_color((string) $value);

    return $value ? $value : '#0f5ea8';
}

function meditrendy_marketing_banner_sanitize($input) {
    $input = is_array($input) ? $input : [];
    $output = ['banners' => []];

    foreach (meditrendy_marketing_banner_languages() as $language => $label) {
        $banner = isset($input['banners'][$language]) && is_array($input['banners'][$language])
            ? $input['banners'][$language]
            : [];

        $output['banners'][$language] = [
            'enabled'           => !empty($banner['enabled']) ? 1 : 0,
            'background_color'  => meditrendy_marketing_banner_sanitize_color($banner['background_color'] ?? '#0f5ea8'),
            'starts_at'         => meditrendy_marketing_banner_sanitize_datetime($banner['starts_at'] ?? ''),
            'ends_at'           => meditrendy_marketing_banner_sanitize_datetime($banner['ends_at'] ?? ''),
            'message'           => wp_kses_post($banner['message'] ?? ''),
            'coupon_enabled'    => !empty($banner['coupon_enabled']) ? 1 : 0,
            'coupon_code'       => strtoupper(sanitize_text_field($banner['coupon_code'] ?? '')),
            'countdown_enabled' => !empty($banner['countdown_enabled']) ? 1 : 0,
            'countdown_ends_at' => meditrendy_marketing_banner_sanitize_datetime($banner['countdown_ends_at'] ?? ''),
            'cta_enabled'       => !empty($banner['cta_enabled']) ? 1 : 0,
            'cta_label'         => sanitize_text_field($banner['cta_label'] ?? ''),
            'cta_url'           => esc_url_raw($banner['cta_url'] ?? ''),
            'close_enabled'     => !empty($banner['close_enabled']) ? 1 : 0,
        ];
    }

    return $output;
}

function meditrendy_marketing_banner_register_settings() {
    register_setting(
        'meditrendy_marketing_banner',
        'meditrendy_marketing_banner',
        [
            'sanitize_callback' => 'meditrendy_marketing_banner_sanitize',
            'default'           => meditrendy_marketing_banner_defaults(),
        ]
    );
}
add_action('admin_init', 'meditrendy_marketing_banner_register_settings');

add_filter('option_page_capability_meditrendy_marketing_banner', 'meditrendy_marketing_banner_capability');

function meditrendy_marketing_banner_admin_menu() {
    add_submenu_page(
        'meditrendy-settings',
        'Top marketing banner',
        'Top banner',
        meditrendy_marketing_banner_capability(),
        'meditrendy-marketing-banner',
        'meditrendy_marketing_banner_render_admin_page'
    );
}
add_action('admin_menu', 'meditrendy_marketing_banner_admin_menu', 18);

function meditrendy_marketing_banner_admin_assets($hook) {
    if ($hook !== 'meditrendy_page_meditrendy-marketing-banner') {
        return;
    }

    wp_register_style('meditrendy-marketing-banner-admin', false, [], '1.0');
    wp_enqueue_style('meditrendy-marketing-banner-admin');
    wp_add_inline_style('meditrendy-marketing-banner-admin', '
        .mt-marketing-banner-admin .mt-banner-language {
            margin: 0 0 18px;
            border: 1px solid #c3c4c7;
            background: #fff;
        }

        .mt-marketing-banner-admin .mt-banner-language h2 {
            margin: 0;
            padding: 14px 16px;
            border-bottom: 1px solid #c3c4c7;
            background: #f6f7f7;
        }

        .mt-marketing-banner-admin .mt-banner-language table {
            margin: 0;
        }

        .mt-marketing-banner-admin .regular-text,
        .mt-marketing-banner-admin .large-text {
            max-width: 620px;
        }
    ');
}
add_action('admin_enqueue_scripts', 'meditrendy_marketing_banner_admin_assets');

function meditrendy_marketing_banner_field_name($language, $field) {
    return 'meditrendy_marketing_banner[banners][' . esc_attr($language) . '][' . esc_attr($field) . ']';
}

function meditrendy_marketing_banner_render_admin_page() {
    if (!current_user_can(meditrendy_marketing_banner_capability())) {
        wp_die(esc_html__('You do not have permission to view this page.', 'meditrendy-core'));
    }

    $settings = meditrendy_marketing_banner_settings();
    $languages = meditrendy_marketing_banner_languages();
    ?>
    <div class="wrap mt-marketing-banner-admin">
        <h1>Top marketing banner</h1>
        <p>Configure one scheduled top stripe per language. The banner is rendered above the header.</p>

        <form method="post" action="options.php">
            <?php settings_fields('meditrendy_marketing_banner'); ?>

            <?php foreach ($languages as $language => $label) : ?>
                <?php $banner = $settings['banners'][$language] ?? meditrendy_marketing_banner_default_banner(); ?>
                <div class="mt-banner-language">
                    <h2><?php echo esc_html($label); ?> <code><?php echo esc_html($language); ?></code></h2>

                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row">Enabled</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo meditrendy_marketing_banner_field_name($language, 'enabled'); ?>" value="1" <?php checked(!empty($banner['enabled'])); ?>>
                                    Show this language banner
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Schedule</th>
                            <td>
                                <label>
                                    Start
                                    <input type="datetime-local" name="<?php echo meditrendy_marketing_banner_field_name($language, 'starts_at'); ?>" value="<?php echo esc_attr($banner['starts_at']); ?>">
                                </label>
                                &nbsp;
                                <label>
                                    End
                                    <input type="datetime-local" name="<?php echo meditrendy_marketing_banner_field_name($language, 'ends_at'); ?>" value="<?php echo esc_attr($banner['ends_at']); ?>">
                                </label>
                                <p class="description">Leave dates empty for no schedule boundary. Uses the site timezone.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Background color</th>
                            <td>
                                <input class="regular-text" type="text" name="<?php echo meditrendy_marketing_banner_field_name($language, 'background_color'); ?>" value="<?php echo esc_attr($banner['background_color'] ?? '#0f5ea8'); ?>" placeholder="#0f5ea8">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Information text</th>
                            <td>
                                <textarea class="large-text" rows="3" name="<?php echo meditrendy_marketing_banner_field_name($language, 'message'); ?>"><?php echo esc_textarea($banner['message']); ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Coupon code</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo meditrendy_marketing_banner_field_name($language, 'coupon_enabled'); ?>" value="1" <?php checked(!empty($banner['coupon_enabled'])); ?>>
                                    Show coupon
                                </label>
                                <br>
                                <input class="regular-text" type="text" name="<?php echo meditrendy_marketing_banner_field_name($language, 'coupon_code'); ?>" value="<?php echo esc_attr($banner['coupon_code']); ?>" placeholder="MEDITRENDY">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Countdown</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo meditrendy_marketing_banner_field_name($language, 'countdown_enabled'); ?>" value="1" <?php checked(!empty($banner['countdown_enabled'])); ?>>
                                    Show countdown
                                </label>
                                <br>
                                <input type="datetime-local" name="<?php echo meditrendy_marketing_banner_field_name($language, 'countdown_ends_at'); ?>" value="<?php echo esc_attr($banner['countdown_ends_at']); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">CTA</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo meditrendy_marketing_banner_field_name($language, 'cta_enabled'); ?>" value="1" <?php checked(!empty($banner['cta_enabled'])); ?>>
                                    Show CTA
                                </label>
                                <br>
                                <input class="regular-text" type="text" name="<?php echo meditrendy_marketing_banner_field_name($language, 'cta_label'); ?>" value="<?php echo esc_attr($banner['cta_label']); ?>" placeholder="Pirkti dabar">
                                <br>
                                <input class="large-text" type="url" name="<?php echo meditrendy_marketing_banner_field_name($language, 'cta_url'); ?>" value="<?php echo esc_url($banner['cta_url']); ?>" placeholder="https://meditrendy.lt/...">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Close button</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo meditrendy_marketing_banner_field_name($language, 'close_enabled'); ?>" value="1" <?php checked(!empty($banner['close_enabled'])); ?>>
                                    Allow closing for this page view only
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>
            <?php endforeach; ?>

            <?php submit_button('Save banner'); ?>
        </form>
    </div>
    <?php
}

function meditrendy_marketing_banner_datetime_to_timestamp($value) {
    $value = trim((string) $value);

    if ($value === '') {
        return 0;
    }

    try {
        $date = new DateTimeImmutable($value, wp_timezone());
        return $date->getTimestamp();
    } catch (Exception $exception) {
        return 0;
    }
}

function meditrendy_marketing_banner_labels($language) {
    $labels = [
        'lt' => [
            'coupon'   => 'Kodas',
            'copy'     => 'Kopijuoti kodą',
            'copied'   => 'Nukopijuota',
            'ends_in'  => 'Baigiasi už',
            'close'    => 'Uždaryti',
        ],
        'en' => [
            'coupon'   => 'Code',
            'copy'     => 'Copy code',
            'copied'   => 'Copied',
            'ends_in'  => 'Ends in',
            'close'    => 'Close',
        ],
        'pl' => [
            'coupon'   => 'Kod',
            'copy'     => 'Kopiuj kod',
            'copied'   => 'Skopiowano',
            'ends_in'  => 'Kończy się za',
            'close'    => 'Zamknij',
        ],
    ];

    return $labels[$language] ?? $labels['lt'];
}

function meditrendy_marketing_banner_display_labels($language) {
    $labels = [
        'lt' => [
            'coupon'  => 'Kodas',
            'copy'    => 'Kopijuoti koda',
            'copied'  => 'Nukopijuota',
            'ends_in' => 'Baigiasi uz',
            'close'   => 'Uzdaryti',
        ],
        'en' => [
            'coupon'  => 'Code',
            'copy'    => 'Copy code',
            'copied'  => 'Copied',
            'ends_in' => 'Ends in',
            'close'   => 'Close',
        ],
        'pl' => [
            'coupon'  => 'Kod',
            'copy'    => 'Kopiuj kod',
            'copied'  => 'Skopiowano',
            'ends_in' => 'Konczy sie za',
            'close'   => 'Zamknij',
        ],
    ];

    return $labels[$language] ?? $labels['lt'];
}

function meditrendy_marketing_banner_active() {
    $settings = meditrendy_marketing_banner_settings();
    $language = meditrendy_marketing_banner_current_language();
    $resolved = meditrendy_marketing_banner_find_banner($settings['banners'] ?? [], $language);
    $banner_language = $language;
    $banner = null;

    if (is_array($resolved) && isset($resolved[1]) && is_array($resolved[1])) {
        $banner_language = (string) $resolved[0];
        $banner = $resolved[1];
    }

    if (!$banner || empty($banner['enabled'])) {
        return null;
    }

    $now = current_time('timestamp');
    $starts_at = meditrendy_marketing_banner_datetime_to_timestamp($banner['starts_at'] ?? '');
    $ends_at = meditrendy_marketing_banner_datetime_to_timestamp($banner['ends_at'] ?? '');

    if ($starts_at && $now < $starts_at) {
        return null;
    }

    if ($ends_at && $now > $ends_at) {
        return null;
    }

    $has_message = trim(wp_strip_all_tags($banner['message'] ?? '')) !== '';
    $has_coupon = !empty($banner['coupon_enabled']) && trim((string) ($banner['coupon_code'] ?? '')) !== '';
    $has_countdown = !empty($banner['countdown_enabled']) && meditrendy_marketing_banner_datetime_to_timestamp($banner['countdown_ends_at'] ?? '') > $now;
    $has_cta = !empty($banner['cta_enabled']) && trim((string) ($banner['cta_label'] ?? '')) !== '' && trim((string) ($banner['cta_url'] ?? '')) !== '';

    if (!$has_message && !$has_coupon && !$has_countdown && !$has_cta) {
        return null;
    }

    $banner['language'] = $banner_language;
    $banner['labels'] = meditrendy_marketing_banner_display_labels(substr(meditrendy_marketing_banner_normalize_language($banner_language), 0, 2));

    return $banner;
}

function meditrendy_marketing_banner_is_visible() {
    return (bool) meditrendy_marketing_banner_active();
}

function meditrendy_marketing_banner_enqueue_assets() {
    if (!meditrendy_marketing_banner_is_visible()) {
        return;
    }

    $path = MEDITRENDY_CORE_DIR . 'assets/js/marketing-banner.js';

    if (file_exists($path)) {
        wp_enqueue_script(
            'meditrendy-marketing-banner',
            MEDITRENDY_CORE_URL . 'assets/js/marketing-banner.js',
            [],
            filemtime($path),
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'meditrendy_marketing_banner_enqueue_assets', 30);

function meditrendy_marketing_banner_render() {
    if (is_admin()) {
        return;
    }

    $banner = meditrendy_marketing_banner_active();

    if (!$banner) {
        return;
    }

    $labels = $banner['labels'];
    $coupon = strtoupper(trim((string) ($banner['coupon_code'] ?? '')));
    $countdown_timestamp = meditrendy_marketing_banner_datetime_to_timestamp($banner['countdown_ends_at'] ?? '');
    $background_color = meditrendy_marketing_banner_sanitize_color($banner['background_color'] ?? '#0f5ea8');
    ?>
    <div class="mt-marketing-banner" data-mt-marketing-banner style="--mt-marketing-banner-bg: <?php echo esc_attr($background_color); ?>;">
        <div class="mt-marketing-banner__inner">
            <?php if (trim(wp_strip_all_tags($banner['message'] ?? '')) !== '') : ?>
                <div class="mt-marketing-banner__message"><?php echo wp_kses_post($banner['message']); ?></div>
            <?php endif; ?>

            <?php if (!empty($banner['coupon_enabled']) && $coupon !== '') : ?>
                <div class="mt-marketing-banner__coupon">
                    <span class="mt-marketing-banner__coupon-label"><?php echo esc_html($labels['coupon']); ?></span>
                    <strong class="mt-marketing-banner__coupon-code"><?php echo esc_html($coupon); ?></strong>
                    <button
                        type="button"
                        class="mt-marketing-banner__copy"
                        data-mt-marketing-copy="<?php echo esc_attr($coupon); ?>"
                        data-copy-label="<?php echo esc_attr($labels['copy']); ?>"
                        data-copied-label="<?php echo esc_attr($labels['copied']); ?>"
                    >
                        <?php echo esc_html($labels['copy']); ?>
                    </button>
                </div>
            <?php endif; ?>

            <?php if (!empty($banner['countdown_enabled']) && $countdown_timestamp > current_time('timestamp')) : ?>
                <div class="mt-marketing-banner__countdown">
                    <span><?php echo esc_html($labels['ends_in']); ?></span>
                    <strong data-mt-marketing-countdown="<?php echo esc_attr($countdown_timestamp); ?>"></strong>
                </div>
            <?php endif; ?>

            <?php if (!empty($banner['cta_enabled']) && !empty($banner['cta_label']) && !empty($banner['cta_url'])) : ?>
                <a class="mt-marketing-banner__cta" href="<?php echo esc_url($banner['cta_url']); ?>">
                    <?php echo esc_html($banner['cta_label']); ?>
                </a>
            <?php endif; ?>

            <?php if (!empty($banner['close_enabled'])) : ?>
                <button type="button" class="mt-marketing-banner__close" data-mt-marketing-close aria-label="<?php echo esc_attr($labels['close']); ?>"></button>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
add_action('wp_body_open', 'meditrendy_marketing_banner_render', 1);
