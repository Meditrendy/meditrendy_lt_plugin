<?php
if (!defined('ABSPATH')) exit;

function meditrendy_popups_capability() {
    return current_user_can('manage_woocommerce') ? 'manage_woocommerce' : 'manage_options';
}

function meditrendy_popups_languages() {
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

function meditrendy_popups_current_language() {
    if (function_exists('meditrendy_core_current_language')) {
        return meditrendy_core_current_language();
    }

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

function meditrendy_popups_normalize_language($language) {
    return str_replace('_', '-', strtolower(trim((string) $language)));
}

function meditrendy_popups_language_matches($popup_language, $current_language) {
    $popup_language = meditrendy_popups_normalize_language($popup_language);

    if ($popup_language === '' || $popup_language === 'all') {
        return true;
    }

    $current_language = meditrendy_popups_normalize_language($current_language);

    return $popup_language === $current_language || $popup_language === substr($current_language, 0, 2);
}

function meditrendy_popups_default_popup() {
    return [
        'enabled'          => 0,
        'language'         => 'all',
        'label'            => '',
        'desktop_image_id' => 0,
        'mobile_image_id'  => 0,
        'cta_url'          => '',
        'starts_at'        => '',
        'ends_at'          => '',
        'target_rule'      => 'all',
        'url_contains'     => '',
        'exact_url'        => '',
        'url_excludes'     => '',
        'exclude_homepage' => 0,
        'display_rule'     => 'delay',
        'delay_seconds'    => 5,
    ];
}

function meditrendy_popups_settings() {
    $settings = get_option('meditrendy_configurable_popups', []);

    if (!is_array($settings)) {
        $settings = [];
    }

    if (!isset($settings['popups']) || !is_array($settings['popups'])) {
        $settings['popups'] = [];
    }

    $settings['popups'] = array_map(
        static function($popup) {
            return wp_parse_args(is_array($popup) ? $popup : [], meditrendy_popups_default_popup());
        },
        $settings['popups']
    );

    if (!$settings['popups']) {
        $settings['popups'][] = meditrendy_popups_default_popup();
    }

    return $settings;
}

function meditrendy_popups_sanitize_datetime($value) {
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    return preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value) ? $value : '';
}

function meditrendy_popups_sanitize($input) {
    $input = is_array($input) ? $input : [];
    $popups = isset($input['popups']) && is_array($input['popups']) ? $input['popups'] : [];
    $output = ['popups' => []];

    foreach ($popups as $popup) {
        if (!is_array($popup)) {
            continue;
        }

        $display_rule = sanitize_key($popup['display_rule'] ?? 'delay');
        $target_rule = sanitize_key($popup['target_rule'] ?? 'all');
        $language = sanitize_key($popup['language'] ?? 'all');
        $allowed_languages = array_merge(['all'], array_keys(meditrendy_popups_languages()));

        if (!in_array($display_rule, ['delay', 'scroll', 'immediate'], true)) {
            $display_rule = 'delay';
        }

        if (!in_array($target_rule, ['all', 'product', 'url_contains', 'exact_url'], true)) {
            $target_rule = 'all';
        }

        if (!in_array($language, $allowed_languages, true)) {
            $language = 'all';
        }

        $clean = [
            'enabled'          => !empty($popup['enabled']) ? 1 : 0,
            'language'         => $language,
            'label'            => sanitize_text_field($popup['label'] ?? ''),
            'desktop_image_id' => absint($popup['desktop_image_id'] ?? 0),
            'mobile_image_id'  => absint($popup['mobile_image_id'] ?? 0),
            'cta_url'          => esc_url_raw($popup['cta_url'] ?? ''),
            'starts_at'        => meditrendy_popups_sanitize_datetime($popup['starts_at'] ?? ''),
            'ends_at'          => meditrendy_popups_sanitize_datetime($popup['ends_at'] ?? ''),
            'target_rule'      => $target_rule,
            'url_contains'     => sanitize_text_field($popup['url_contains'] ?? ''),
            'exact_url'        => sanitize_text_field($popup['exact_url'] ?? ''),
            'url_excludes'     => sanitize_text_field($popup['url_excludes'] ?? ''),
            'exclude_homepage' => !empty($popup['exclude_homepage']) ? 1 : 0,
            'display_rule'     => $display_rule,
            'delay_seconds'    => max(0, absint($popup['delay_seconds'] ?? 0)),
        ];

        $has_content = ($clean['desktop_image_id'] || $clean['mobile_image_id']) && $clean['cta_url'] !== '';

        if ($has_content || !empty($clean['enabled']) || $clean['label'] !== '') {
            $output['popups'][] = $clean;
        }
    }

    if (!$output['popups']) {
        $output['popups'][] = meditrendy_popups_default_popup();
    }

    return $output;
}

function meditrendy_popups_register_settings() {
    register_setting(
        'meditrendy_configurable_popups',
        'meditrendy_configurable_popups',
        [
            'sanitize_callback' => 'meditrendy_popups_sanitize',
            'default'           => ['popups' => [meditrendy_popups_default_popup()]],
        ]
    );
}
add_action('admin_init', 'meditrendy_popups_register_settings');

add_filter('option_page_capability_meditrendy_configurable_popups', 'meditrendy_popups_capability');

function meditrendy_popups_admin_menu() {
    add_submenu_page(
        'meditrendy-settings',
        'Popups',
        'Popups',
        meditrendy_popups_capability(),
        'meditrendy-popups',
        'meditrendy_popups_render_admin_page'
    );
}
add_action('admin_menu', 'meditrendy_popups_admin_menu', 19);

function meditrendy_popups_admin_assets($hook) {
    if ($hook !== 'meditrendy_page_meditrendy-popups') {
        return;
    }

    wp_enqueue_media();

    $path = MEDITRENDY_CORE_DIR . 'assets/js/configurable-popups-admin.js';

    if (file_exists($path)) {
        wp_enqueue_script(
            'meditrendy-configurable-popups-admin',
            MEDITRENDY_CORE_URL . 'assets/js/configurable-popups-admin.js',
            ['jquery'],
            filemtime($path),
            true
        );
    }

    wp_register_style('meditrendy-configurable-popups-admin', false, [], '1.0');
    wp_enqueue_style('meditrendy-configurable-popups-admin');
    wp_add_inline_style('meditrendy-configurable-popups-admin', '
        .mt-popups-admin .mt-popup-rule {
            margin: 0 0 18px;
            border: 1px solid #c3c4c7;
            background: #fff;
        }

        .mt-popups-admin .mt-popup-rule__header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 12px 16px;
            border-bottom: 1px solid #dcdcde;
            background: #f6f7f7;
        }

        .mt-popups-admin .mt-popup-rule__header h2 {
            margin: 0;
            font-size: 14px;
        }

        .mt-popups-admin .mt-popup-rule__body {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
            padding: 16px;
        }

        .mt-popups-admin .mt-popup-field {
            margin: 0 0 14px;
        }

        .mt-popups-admin .mt-popup-field label {
            display: block;
            margin: 0 0 5px;
            font-weight: 600;
        }

        .mt-popups-admin .regular-text,
        .mt-popups-admin .large-text {
            max-width: 100%;
        }

        .mt-popups-admin .mt-popup-image-preview {
            display: block;
            width: 160px;
            max-width: 100%;
            height: 160px;
            margin: 8px 0;
            object-fit: cover;
            border: 1px solid #dcdcde;
            background: #f6f7f7;
        }

        .mt-popups-admin .mt-popup-image-preview[src=""] {
            display: none;
        }

        @media (max-width: 960px) {
            .mt-popups-admin .mt-popup-rule__body {
                grid-template-columns: 1fr;
            }
        }
    ');
}
add_action('admin_enqueue_scripts', 'meditrendy_popups_admin_assets');

function meditrendy_popups_field_name($index, $field) {
    return 'meditrendy_configurable_popups[popups][' . esc_attr($index) . '][' . esc_attr($field) . ']';
}

function meditrendy_popups_image_preview_url($image_id) {
    $image_id = absint($image_id);

    if (!$image_id) {
        return '';
    }

    $image = wp_get_attachment_image_url($image_id, 'thumbnail');

    return $image ? $image : '';
}

function meditrendy_popups_render_image_control($index, $field, $label, $popup) {
    $image_id = absint($popup[$field] ?? 0);
    $preview = meditrendy_popups_image_preview_url($image_id);
    ?>
    <div class="mt-popup-field">
        <label><?php echo esc_html($label); ?></label>
        <input type="hidden" class="mt-popup-image-id" name="<?php echo meditrendy_popups_field_name($index, $field); ?>" value="<?php echo esc_attr($image_id); ?>">
        <img class="mt-popup-image-preview" src="<?php echo esc_url($preview); ?>" alt="">
        <button type="button" class="button mt-popup-select-image">Choose image</button>
        <button type="button" class="button-link-delete mt-popup-remove-image" <?php echo $image_id ? '' : 'hidden'; ?>>Remove</button>
    </div>
    <?php
}

function meditrendy_popups_render_rule($popup, $index) {
    $popup = wp_parse_args(is_array($popup) ? $popup : [], meditrendy_popups_default_popup());
    $languages = meditrendy_popups_languages();
    $starts_at = meditrendy_popups_datetime_to_timestamp($popup['starts_at'] ?? '');
    $ends_at = meditrendy_popups_datetime_to_timestamp($popup['ends_at'] ?? '');
    $now = meditrendy_popups_current_timestamp();
    $status = 'Active now';

    if (empty($popup['enabled'])) {
        $status = 'Disabled';
    } elseif ($starts_at && $now < $starts_at) {
        $status = 'Scheduled for later';
    } elseif ($ends_at && $now > $ends_at) {
        $status = 'Expired';
    }
    ?>
    <div class="mt-popup-rule" data-mt-popup-rule>
        <div class="mt-popup-rule__header">
            <h2>Popup <?php echo esc_html((int) $index + 1); ?> <span class="description">- <?php echo esc_html($status); ?></span></h2>
            <button type="button" class="button-link-delete" data-mt-popup-remove>Remove popup</button>
        </div>

        <div class="mt-popup-rule__body">
            <div>
                <div class="mt-popup-field">
                    <label>
                        <input type="checkbox" name="<?php echo meditrendy_popups_field_name($index, 'enabled'); ?>" value="1" <?php checked(!empty($popup['enabled'])); ?>>
                        Enabled
                    </label>
                </div>

                <div class="mt-popup-field">
                    <label>Language</label>
                    <select name="<?php echo meditrendy_popups_field_name($index, 'language'); ?>">
                        <option value="all" <?php selected($popup['language'], 'all'); ?>>All languages</option>
                        <?php foreach ($languages as $language => $label) : ?>
                            <option value="<?php echo esc_attr($language); ?>" <?php selected($popup['language'], $language); ?>>
                                <?php echo esc_html($label); ?> (<?php echo esc_html($language); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mt-popup-field">
                    <label>Admin label</label>
                    <input class="regular-text" type="text" name="<?php echo meditrendy_popups_field_name($index, 'label'); ?>" value="<?php echo esc_attr($popup['label']); ?>">
                </div>

                <?php meditrendy_popups_render_image_control($index, 'desktop_image_id', 'Desktop square graphic', $popup); ?>
                <?php meditrendy_popups_render_image_control($index, 'mobile_image_id', 'Mobile square graphic', $popup); ?>
                <p class="description">Use square 1000 x 1000 px graphics. If the mobile graphic is empty, the desktop graphic is used.</p>
            </div>

            <div>
                <div class="mt-popup-field">
                    <label>Popup link</label>
                    <input class="large-text" type="url" name="<?php echo meditrendy_popups_field_name($index, 'cta_url'); ?>" value="<?php echo esc_url($popup['cta_url']); ?>" placeholder="https://meditrendy.lt/...">
                    <p class="description">The whole popup graphic links to this URL.</p>
                </div>

                <div class="mt-popup-field">
                    <label>Schedule</label>
                    <input type="datetime-local" name="<?php echo meditrendy_popups_field_name($index, 'starts_at'); ?>" value="<?php echo esc_attr($popup['starts_at']); ?>">
                    &nbsp;
                    <input type="datetime-local" name="<?php echo meditrendy_popups_field_name($index, 'ends_at'); ?>" value="<?php echo esc_attr($popup['ends_at']); ?>">
                    <p class="description">Leave dates empty for no schedule boundary. Uses the site timezone. Current site time: <?php echo esc_html(current_time('Y-m-d H:i')); ?>.</p>
                </div>

                <div class="mt-popup-field">
                    <label>Page targeting</label>
                    <select name="<?php echo meditrendy_popups_field_name($index, 'target_rule'); ?>">
                        <option value="all" <?php selected($popup['target_rule'], 'all'); ?>>All storefront pages</option>
                        <option value="product" <?php selected($popup['target_rule'], 'product'); ?>>Product pages only</option>
                        <option value="url_contains" <?php selected($popup['target_rule'], 'url_contains'); ?>>Only URLs containing string</option>
                        <option value="exact_url" <?php selected($popup['target_rule'], 'exact_url'); ?>>Exact URL only</option>
                    </select>
                </div>

                <div class="mt-popup-field">
                    <label>URL contains</label>
                    <input class="large-text" type="text" name="<?php echo meditrendy_popups_field_name($index, 'url_contains'); ?>" value="<?php echo esc_attr($popup['url_contains']); ?>" placeholder="/akcijos/">
                    <p class="description">Used when page targeting is set to “Only URLs containing string”. Matches the full URL, path, and query.</p>
                </div>

                <div class="mt-popup-field">
                    <label>Exact URL</label>
                    <input class="large-text" type="text" name="<?php echo meditrendy_popups_field_name($index, 'exact_url'); ?>" value="<?php echo esc_attr($popup['exact_url']); ?>" placeholder="https://meditrendy.lt/...">
                    <p class="description">Used when page targeting is set to “Exact URL only”. You can use a full URL or a path like /shop/example/.</p>
                </div>

                <div class="mt-popup-field">
                    <label>Exclude URLs containing</label>
                    <input class="large-text" type="text" name="<?php echo meditrendy_popups_field_name($index, 'url_excludes'); ?>" value="<?php echo esc_attr($popup['url_excludes']); ?>" placeholder="/checkout/">
                    <p class="description">Optional. If this string appears in the current URL, the popup will not show.</p>
                </div>

                <div class="mt-popup-field">
                    <label>
                        <input type="checkbox" name="<?php echo meditrendy_popups_field_name($index, 'exclude_homepage'); ?>" value="1" <?php checked(!empty($popup['exclude_homepage'])); ?>>
                        Exclude homepage
                    </label>
                </div>

                <div class="mt-popup-field">
                    <label>Display rule</label>
                    <select name="<?php echo meditrendy_popups_field_name($index, 'display_rule'); ?>" data-mt-popup-display-rule>
                        <option value="delay" <?php selected($popup['display_rule'], 'delay'); ?>>After delay</option>
                        <option value="scroll" <?php selected($popup['display_rule'], 'scroll'); ?>>After user starts scrolling</option>
                        <option value="immediate" <?php selected($popup['display_rule'], 'immediate'); ?>>Immediately</option>
                    </select>
                </div>

                <div class="mt-popup-field" data-mt-popup-delay-field>
                    <label>Delay in seconds</label>
                    <input type="number" min="0" step="1" name="<?php echo meditrendy_popups_field_name($index, 'delay_seconds'); ?>" value="<?php echo esc_attr($popup['delay_seconds']); ?>">
                </div>
            </div>
        </div>
    </div>
    <?php
}

function meditrendy_popups_render_admin_page() {
    if (!current_user_can(meditrendy_popups_capability())) {
        wp_die(esc_html__('You do not have permission to view this page.', 'meditrendy-core'));
    }

    $settings = meditrendy_popups_settings();
    ?>
    <div class="wrap mt-popups-admin">
        <h1>Configurable popups</h1>
        <p>Configure scheduled marketing popups. If several popups are active at the same time, the first enabled popup in this list is shown.</p>

        <form method="post" action="options.php">
            <?php settings_fields('meditrendy_configurable_popups'); ?>

            <div data-mt-popups-list>
                <?php foreach ($settings['popups'] as $index => $popup) : ?>
                    <?php meditrendy_popups_render_rule($popup, $index); ?>
                <?php endforeach; ?>
            </div>

            <button type="button" class="button" data-mt-popup-add>Add popup</button>
            <?php submit_button('Save popups'); ?>
        </form>

        <script type="text/html" id="tmpl-meditrendy-popup-rule">
            <?php meditrendy_popups_render_rule(meditrendy_popups_default_popup(), '__INDEX__'); ?>
        </script>
    </div>
    <?php
}

function meditrendy_popups_datetime_to_timestamp($value) {
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

function meditrendy_popups_current_timestamp() {
    return function_exists('current_datetime') ? current_datetime()->getTimestamp() : time();
}

function meditrendy_popups_current_url() {
    $scheme = is_ssl() ? 'https://' : 'http://';
    $host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : wp_parse_url(home_url(), PHP_URL_HOST);
    $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '/';

    return $scheme . $host . $request_uri;
}

function meditrendy_popups_normalize_url_text($value) {
    $value = rawurldecode(strtolower(trim((string) $value)));

    return untrailingslashit($value);
}

function meditrendy_popups_current_url_parts() {
    $current_url = meditrendy_popups_current_url();
    $path = (string) wp_parse_url($current_url, PHP_URL_PATH);
    $query = (string) wp_parse_url($current_url, PHP_URL_QUERY);
    $path_query = $path . ($query !== '' ? '?' . $query : '');

    return array_unique(array_filter([
        meditrendy_popups_normalize_url_text($current_url),
        meditrendy_popups_normalize_url_text($path),
        meditrendy_popups_normalize_url_text($path_query),
    ]));
}

function meditrendy_popups_url_contains($needle) {
    $needle = meditrendy_popups_normalize_url_text($needle);

    if ($needle === '') {
        return false;
    }

    foreach (meditrendy_popups_current_url_parts() as $haystack) {
        if (strpos($haystack, $needle) !== false) {
            return true;
        }
    }

    return false;
}

function meditrendy_popups_exact_url_matches($configured_url) {
    $configured_url = meditrendy_popups_normalize_url_text($configured_url);

    if ($configured_url === '') {
        return false;
    }

    $configured_parts = [$configured_url];
    $configured_path = (string) wp_parse_url($configured_url, PHP_URL_PATH);
    $configured_query = (string) wp_parse_url($configured_url, PHP_URL_QUERY);

    if ($configured_path !== '') {
        $configured_parts[] = meditrendy_popups_normalize_url_text($configured_path . ($configured_query !== '' ? '?' . $configured_query : ''));
    }

    $configured_parts = array_unique(array_filter($configured_parts));

    foreach (meditrendy_popups_current_url_parts() as $current_part) {
        if (in_array($current_part, $configured_parts, true)) {
            return true;
        }
    }

    return false;
}

function meditrendy_popups_target_matches($popup) {
    if (!empty($popup['exclude_homepage']) && (is_front_page() || is_home())) {
        return false;
    }

    if (!empty($popup['url_excludes']) && meditrendy_popups_url_contains($popup['url_excludes'])) {
        return false;
    }

    $target_rule = $popup['target_rule'] ?? 'all';

    if ($target_rule === 'product') {
        return function_exists('is_product') && is_product();
    }

    if ($target_rule === 'url_contains') {
        return meditrendy_popups_url_contains($popup['url_contains'] ?? '');
    }

    if ($target_rule === 'exact_url') {
        return meditrendy_popups_exact_url_matches($popup['exact_url'] ?? '');
    }

    return true;
}

function meditrendy_popups_active_popup() {
    if (is_admin()) {
        return null;
    }

    if (
        (function_exists('is_checkout') && is_checkout()) ||
        (function_exists('is_cart') && is_cart()) ||
        (function_exists('is_account_page') && is_account_page())
    ) {
        return null;
    }

    $settings = meditrendy_popups_settings();
    $now = meditrendy_popups_current_timestamp();
    $current_language = meditrendy_popups_current_language();

    foreach ($settings['popups'] as $index => $popup) {
        if (empty($popup['enabled'])) {
            continue;
        }

        if (!meditrendy_popups_language_matches($popup['language'] ?? 'all', $current_language)) {
            continue;
        }

        $starts_at = meditrendy_popups_datetime_to_timestamp($popup['starts_at'] ?? '');
        $ends_at = meditrendy_popups_datetime_to_timestamp($popup['ends_at'] ?? '');

        if ($starts_at && $now < $starts_at) {
            continue;
        }

        if ($ends_at && $now > $ends_at) {
            continue;
        }

        if (!meditrendy_popups_target_matches($popup)) {
            continue;
        }

        $has_image = !empty($popup['desktop_image_id']) || !empty($popup['mobile_image_id']);
        $has_link = !empty($popup['cta_url']);

        if (!$has_image || !$has_link) {
            continue;
        }

        $popup['index'] = (int) $index;

        return $popup;
    }

    return null;
}

function meditrendy_popups_is_visible() {
    return (bool) meditrendy_popups_active_popup();
}

function meditrendy_popups_enqueue_assets() {
    if (!meditrendy_popups_is_visible()) {
        return;
    }

    $css_path = MEDITRENDY_CORE_DIR . 'assets/css/configurable-popups.css';

    if (file_exists($css_path)) {
        wp_enqueue_style(
            'meditrendy-configurable-popups',
            MEDITRENDY_CORE_URL . 'assets/css/configurable-popups.css',
            [],
            filemtime($css_path)
        );
    }

    $js_path = MEDITRENDY_CORE_DIR . 'assets/js/configurable-popups.js';

    if (file_exists($js_path)) {
        wp_enqueue_script(
            'meditrendy-configurable-popups',
            MEDITRENDY_CORE_URL . 'assets/js/configurable-popups.js',
            [],
            filemtime($js_path),
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'meditrendy_popups_enqueue_assets', 30);

function meditrendy_popups_render() {
    $popup = meditrendy_popups_active_popup();

    if (!$popup) {
        return;
    }

    $desktop_image = !empty($popup['desktop_image_id']) ? wp_get_attachment_image_url(absint($popup['desktop_image_id']), 'large') : '';
    $mobile_image = !empty($popup['mobile_image_id']) ? wp_get_attachment_image_url(absint($popup['mobile_image_id']), 'large') : '';
    $link_url = esc_url($popup['cta_url'] ?? '');
    $display_rule = in_array($popup['display_rule'], ['delay', 'scroll', 'immediate'], true) ? $popup['display_rule'] : 'delay';
    $delay = max(0, absint($popup['delay_seconds'] ?? 0));
    $desktop_image = $desktop_image ?: $mobile_image;
    $mobile_image = $mobile_image ?: $desktop_image;
    ?>
    <div
        class="mt-configurable-popup"
        data-mt-configurable-popup
        data-popup-id="<?php echo esc_attr(md5(wp_json_encode([
            'index'     => (int) ($popup['index'] ?? 0),
            'language'  => $popup['language'] ?? 'all',
            'starts_at' => $popup['starts_at'] ?? '',
            'ends_at'   => $popup['ends_at'] ?? '',
            'target'    => $popup['target_rule'] ?? 'all',
            'contains'  => $popup['url_contains'] ?? '',
            'exact'     => $popup['exact_url'] ?? '',
            'excludes'  => $popup['url_excludes'] ?? '',
            'home'      => !empty($popup['exclude_homepage']) ? 1 : 0,
            'cta_url'   => $popup['cta_url'] ?? '',
            'desktop'   => $popup['desktop_image_id'] ?? 0,
            'mobile'    => $popup['mobile_image_id'] ?? 0,
        ]))); ?>"
        data-display-rule="<?php echo esc_attr($display_rule); ?>"
        data-delay-seconds="<?php echo esc_attr($delay); ?>"
        hidden
    >
        <div class="mt-configurable-popup__overlay" data-mt-popup-close></div>
        <div class="mt-configurable-popup__dialog" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr__('Akcija', 'meditrendy-core'); ?>">
            <button type="button" class="mt-configurable-popup__close" data-mt-popup-close aria-label="<?php echo esc_attr__('Uzdaryti', 'meditrendy-core'); ?>"></button>
            <a class="mt-configurable-popup__link" href="<?php echo $link_url; ?>">
                <picture class="mt-configurable-popup__picture">
                    <source media="(max-width: 767px)" srcset="<?php echo esc_url($mobile_image); ?>">
                    <img src="<?php echo esc_url($desktop_image); ?>" alt="" loading="eager">
                </picture>
            </a>
        </div>
    </div>
    <?php
}
add_action('wp_footer', 'meditrendy_popups_render', 5);
