<?php
if (!defined('ABSPATH')) exit;

const MEDITRENDY_SIZE_CHART_OPTION = 'meditrendy_size_chart_attribute';
const MEDITRENDY_SIZE_CHART_META_KEY = 'meditrendy_size_chart_html';

function meditrendy_size_charts_contexts() {
    return apply_filters('meditrendy_size_chart_contexts', [
        'women' => [
            'label'          => __('Women', 'meditrendy-core'),
            'category_slugs' => ['moterims', 'sievietem', 'naistele'],
        ],
        'men' => [
            'label'          => __('Men', 'meditrendy-core'),
            'category_slugs' => ['vyrams', 'viriesiem', 'meestele'],
        ],
    ]);
}

function meditrendy_size_charts_context_meta_key($context) {
    $context = sanitize_key((string) $context);

    return $context ? MEDITRENDY_SIZE_CHART_META_KEY . '_' . $context : MEDITRENDY_SIZE_CHART_META_KEY;
}

function meditrendy_size_charts_language() {
    if (function_exists('meditrendy_core_current_language')) {
        return meditrendy_core_current_language();
    }

    if (function_exists('pll_current_language')) {
        $language = strtolower((string) pll_current_language('slug'));

        if ($language !== '') {
            return $language === 'ee' ? 'et' : $language;
        }
    }

    $locale = function_exists('determine_locale') ? strtolower((string) determine_locale()) : strtolower((string) get_locale());

    if (strpos($locale, 'lv') === 0) {
        return 'lv';
    }

    if (strpos($locale, 'et') === 0) {
        return 'et';
    }

    return 'lt';
}

function meditrendy_size_charts_text($key) {
    $strings = [
        'lt' => [
            'link' => 'Dydžių lentelė',
            'note' => 'Amerikietiški dydžiai – rinkitės mažesnį dydį',
            'close' => 'Uždaryti',
        ],
        'lv' => [
            'link' => 'Izmēru tabula',
            'note' => 'Amerikāņu izmēri – izvēlieties mazāku izmēru',
            'close' => 'Aizvērt',
        ],
        'et' => [
            'link' => 'Suuruste tabel',
            'note' => 'Ameerika suurused – valige väiksem suurus',
            'close' => 'Sulge',
        ],
    ];
    $language = meditrendy_size_charts_language();

    return $strings[$language][$key] ?? $strings['lt'][$key] ?? '';
}

function meditrendy_size_charts_capability() {
    if (function_exists('meditrendy_filter_settings_capability')) {
        return meditrendy_filter_settings_capability();
    }

    return current_user_can('manage_woocommerce') ? 'manage_woocommerce' : 'manage_options';
}

function meditrendy_size_charts_allowed_html() {
    $global_attributes = [
        'class' => true,
        'style' => true,
        'id'    => true,
    ];

    return [
        'table'    => array_merge($global_attributes, ['border' => true, 'cellpadding' => true, 'cellspacing' => true]),
        'thead'    => $global_attributes,
        'tbody'    => $global_attributes,
        'tfoot'    => $global_attributes,
        'tr'       => $global_attributes,
        'th'       => array_merge($global_attributes, ['colspan' => true, 'rowspan' => true, 'scope' => true]),
        'td'       => array_merge($global_attributes, ['colspan' => true, 'rowspan' => true]),
        'caption'  => $global_attributes,
        'colgroup' => $global_attributes,
        'col'      => array_merge($global_attributes, ['span' => true]),
        'p'        => $global_attributes,
        'br'       => [],
        'strong'   => $global_attributes,
        'b'        => $global_attributes,
        'em'       => $global_attributes,
        'i'        => $global_attributes,
        'span'     => $global_attributes,
    ];
}

function meditrendy_size_charts_sanitize_html($html, $unslash = false) {
    $html = is_string($html) ? $html : '';
    $html = $unslash ? wp_unslash($html) : $html;
    $html = trim($html);

    if ($html === '') {
        return '';
    }

    return wp_kses($html, meditrendy_size_charts_allowed_html());
}

function meditrendy_size_charts_available_attributes() {
    $attributes = [];

    if (taxonomy_exists('product_brand')) {
        $taxonomy = get_taxonomy('product_brand');
        $attributes['product_brand'] = $taxonomy && !empty($taxonomy->labels->singular_name)
            ? $taxonomy->labels->singular_name
            : __('Brand', 'meditrendy-core');
    }

    if (!function_exists('wc_get_attribute_taxonomies') || !function_exists('wc_attribute_taxonomy_name')) {
        return $attributes;
    }

    foreach ((array) wc_get_attribute_taxonomies() as $attribute) {
        if (empty($attribute->attribute_name)) {
            continue;
        }

        $attribute_name = sanitize_title($attribute->attribute_name);
        $taxonomy = wc_attribute_taxonomy_name($attribute_name);

        if (!taxonomy_exists($taxonomy)) {
            continue;
        }

        $attributes[$taxonomy] = $attribute->attribute_label ?: $attribute_name;
    }

    uasort($attributes, 'strnatcasecmp');

    return $attributes;
}

function meditrendy_size_charts_default_taxonomy($attributes = null) {
    $attributes = $attributes === null ? meditrendy_size_charts_available_attributes() : $attributes;

    if (isset($attributes['product_brand'])) {
        return 'product_brand';
    }

    if (isset($attributes['pa_brand'])) {
        return 'pa_brand';
    }

    foreach ($attributes as $taxonomy => $label) {
        return $taxonomy;
    }

    return '';
}

function meditrendy_size_charts_active_taxonomy() {
    $attributes = meditrendy_size_charts_available_attributes();
    $saved = sanitize_key((string) get_option(MEDITRENDY_SIZE_CHART_OPTION, ''));

    if ($saved && isset($attributes[$saved])) {
        return $saved;
    }

    return meditrendy_size_charts_default_taxonomy($attributes);
}

function meditrendy_size_charts_candidate_taxonomies($preferred = '') {
    $attributes = meditrendy_size_charts_available_attributes();
    $candidates = array_filter([
        sanitize_key((string) $preferred),
        'product_brand',
        'pa_brand',
    ]);

    $candidates = array_merge($candidates, array_keys($attributes));
    $candidates = array_values(array_unique(array_filter($candidates, function ($taxonomy) use ($attributes) {
        return isset($attributes[$taxonomy]) && taxonomy_exists($taxonomy);
    })));

    return $candidates;
}

function meditrendy_size_charts_related_product_ids($product) {
    if (!$product || !is_a($product, 'WC_Product')) {
        return [];
    }

    $product_id = (int) $product->get_id();
    $product_ids = [$product_id];

    if (function_exists('pll_get_post_translations')) {
        $product_ids = array_merge($product_ids, array_map('absint', (array) pll_get_post_translations($product_id)));
    }

    $sku = (string) $product->get_sku();

    if ($sku !== '' && preg_match('/^(?:[a-z]{2}-)?PARENT-(\d+)$/i', $sku, $matches)) {
        $product_ids[] = absint($matches[1]);
    }

    return array_values(array_unique(array_filter($product_ids)));
}

function meditrendy_size_charts_brand_taxonomies() {
    return array_values(array_filter(['pa_brand', 'product_brand'], 'taxonomy_exists'));
}

function meditrendy_size_charts_matching_terms($source_terms, $target_taxonomy) {
    if (!$source_terms || !$target_taxonomy || !taxonomy_exists($target_taxonomy)) {
        return [];
    }

    $matches = [];

    foreach ((array) $source_terms as $source_term) {
        if (!$source_term || is_wp_error($source_term)) {
            continue;
        }

        $keys = array_unique(array_filter([
            isset($source_term->slug) ? sanitize_title($source_term->slug) : '',
            isset($source_term->name) ? sanitize_title($source_term->name) : '',
        ]));

        foreach ($keys as $key) {
            $term = get_term_by('slug', $key, $target_taxonomy);

            if ($term && !is_wp_error($term)) {
                $matches[(int) $term->term_id] = $term;
            }
        }

        foreach (meditrendy_size_charts_get_terms($target_taxonomy) as $candidate) {
            if (!$candidate || empty($candidate->term_id)) {
                continue;
            }

            $candidate_keys = array_unique(array_filter([
                isset($candidate->slug) ? sanitize_title($candidate->slug) : '',
                isset($candidate->name) ? sanitize_title($candidate->name) : '',
            ]));

            if (array_intersect($keys, $candidate_keys)) {
                $matches[(int) $candidate->term_id] = $candidate;
            }
        }
    }

    return array_values($matches);
}

function meditrendy_size_charts_product_terms($product, $taxonomy) {
    $terms = [];

    if (!$product || !is_a($product, 'WC_Product') || !$taxonomy || !taxonomy_exists($taxonomy)) {
        return $terms;
    }

    foreach (meditrendy_size_charts_related_product_ids($product) as $product_id) {
        $product_terms = wp_get_post_terms($product_id, $taxonomy);

        if (!is_wp_error($product_terms) && $product_terms) {
            $terms = array_merge($terms, $product_terms);
        }

        if (in_array($taxonomy, meditrendy_size_charts_brand_taxonomies(), true)) {
            foreach (meditrendy_size_charts_brand_taxonomies() as $brand_taxonomy) {
                if ($brand_taxonomy === $taxonomy) {
                    continue;
                }

                $brand_terms = wp_get_post_terms($product_id, $brand_taxonomy);

                if (!is_wp_error($brand_terms) && $brand_terms) {
                    $terms = array_merge($terms, meditrendy_size_charts_matching_terms($brand_terms, $taxonomy));
                }
            }
        }
    }

    $unique = [];

    foreach ($terms as $term) {
        if ($term && !is_wp_error($term) && !empty($term->term_id)) {
            $unique[(int) $term->term_id] = $term;
        }
    }

    return array_values($unique);
}

function meditrendy_size_charts_admin_taxonomy() {
    $attributes = meditrendy_size_charts_available_attributes();
    $selected = isset($_GET['attribute']) ? sanitize_key(wp_unslash($_GET['attribute'])) : '';

    if ($selected && isset($attributes[$selected])) {
        return $selected;
    }

    return meditrendy_size_charts_active_taxonomy();
}

function meditrendy_size_charts_get_terms($taxonomy) {
    if (!$taxonomy || !taxonomy_exists($taxonomy)) {
        return [];
    }

    $terms = get_terms([
        'taxonomy'   => $taxonomy,
        'hide_empty' => false,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ]);

    return is_wp_error($terms) ? [] : $terms;
}

function meditrendy_size_charts_languages() {
    if (!function_exists('pll_languages_list')) {
        return [];
    }

    $languages = pll_languages_list(['fields' => '']);
    $labels = [];

    foreach ((array) $languages as $language) {
        if (!is_object($language) || empty($language->slug)) {
            continue;
        }

        $labels[$language->slug] = !empty($language->name) ? $language->name : strtoupper($language->slug);
    }

    return $labels;
}

function meditrendy_size_charts_term_translations($term) {
    if (!$term || empty($term->term_id) || empty($term->taxonomy)) {
        return [];
    }

    $translations = [];

    if (function_exists('pll_get_term_translations')) {
        $translations = (array) pll_get_term_translations($term->term_id);
    }

    if (!$translations && function_exists('pll_get_term_language')) {
        $language = pll_get_term_language($term->term_id);

        if ($language) {
            $translations[$language] = $term->term_id;
        }
    }

    if (!$translations) {
        return [];
    }

    $languages = meditrendy_size_charts_languages();
    $rows = [];

    foreach ($translations as $language => $term_id) {
        $translated_term = get_term((int) $term_id, $term->taxonomy);

        if (!$translated_term || is_wp_error($translated_term)) {
            continue;
        }

        $language = (string) $language;
        $rows[$language] = [
            'language' => $language,
            'label'    => $languages[$language] ?? strtoupper($language),
            'term_id'  => (int) $translated_term->term_id,
            'name'     => $translated_term->name,
            'slug'     => $translated_term->slug,
            'current'  => (int) $translated_term->term_id === (int) $term->term_id,
        ];
    }

    return $rows;
}

function meditrendy_size_charts_render_term_translations($term) {
    $translations = meditrendy_size_charts_term_translations($term);

    if (!$translations) {
        return esc_html__('No translations linked.', 'meditrendy-core');
    }

    $languages = meditrendy_size_charts_languages();
    $missing = array_diff(array_keys($languages), array_keys($translations));

    ob_start();
    ?>
    <ul style="margin:0;">
        <?php foreach ($translations as $translation) : ?>
            <li style="margin:0 0 4px;">
                <strong><?php echo esc_html($translation['label']); ?>:</strong>
                <?php echo esc_html($translation['name']); ?>
                <small>(<?php echo esc_html($translation['slug']); ?><?php echo $translation['current'] ? esc_html__(', current', 'meditrendy-core') : ''; ?>)</small>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php if ($missing) : ?>
        <small>
            <?php
            printf(
                esc_html__('Missing: %s', 'meditrendy-core'),
                esc_html(implode(', ', array_map(function ($language) use ($languages) {
                    return $languages[$language] ?? strtoupper($language);
                }, $missing)))
            );
            ?>
        </small>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}

function meditrendy_size_charts_term_chart($term, $context = '') {
    if (!$term || empty($term->term_id)) {
        return '';
    }

    $term_ids = [(int) $term->term_id];
    $meta_keys = [];
    $context = sanitize_key((string) $context);

    foreach (meditrendy_size_charts_term_translations($term) as $translation) {
        if (!empty($translation['term_id'])) {
            $term_ids[] = (int) $translation['term_id'];
        }
    }

    foreach (meditrendy_size_charts_related_term_ids($term) as $term_id) {
        $term_ids[] = (int) $term_id;
    }

    if ($context !== '') {
        $meta_keys[] = meditrendy_size_charts_context_meta_key($context);
    }

    $meta_keys[] = MEDITRENDY_SIZE_CHART_META_KEY;

    foreach (array_unique($meta_keys) as $meta_key) {
        foreach (array_unique(array_filter($term_ids)) as $term_id) {
            $chart = meditrendy_size_charts_sanitize_html((string) get_term_meta($term_id, $meta_key, true));

            if ($chart !== '') {
                return $chart;
            }
        }
    }

    return '';
}

function meditrendy_size_charts_related_term_ids($term) {
    if (!$term || empty($term->term_id) || empty($term->taxonomy)) {
        return [];
    }

    $term_ids = [];
    $lookups = array_unique(array_filter([
        isset($term->slug) ? sanitize_title($term->slug) : '',
        isset($term->name) ? sanitize_title($term->name) : '',
    ]));

    foreach ($lookups as $lookup) {
        $matches = get_terms([
            'taxonomy'   => $term->taxonomy,
            'hide_empty' => false,
            'slug'       => $lookup,
            'fields'     => 'ids',
        ]);

        if (!is_wp_error($matches)) {
            $term_ids = array_merge($term_ids, array_map('absint', (array) $matches));
        }
    }

    foreach (meditrendy_size_charts_get_terms($term->taxonomy) as $candidate) {
        if (!$candidate || empty($candidate->term_id)) {
            continue;
        }

        $candidate_keys = array_unique(array_filter([
            isset($candidate->slug) ? sanitize_title($candidate->slug) : '',
            isset($candidate->name) ? sanitize_title($candidate->name) : '',
        ]));

        if (array_intersect($lookups, $candidate_keys)) {
            $term_ids[] = (int) $candidate->term_id;
        }
    }

    return array_values(array_diff(array_unique(array_filter($term_ids)), [(int) $term->term_id]));
}

function meditrendy_size_charts_attribute_product($product) {
    if (!$product || !is_a($product, 'WC_Product')) {
        return null;
    }

    if ($product->is_type('variation') && $product->get_parent_id()) {
        $parent = wc_get_product($product->get_parent_id());

        if ($parent) {
            return $parent;
        }
    }

    return $product;
}

function meditrendy_size_charts_product_context($product) {
    $attribute_product = meditrendy_size_charts_attribute_product($product);

    if (!$attribute_product) {
        return '';
    }

    static $cache = [];

    $product_id = (int) $attribute_product->get_id();

    if (isset($cache[$product_id])) {
        return $cache[$product_id];
    }

    $product_ids = meditrendy_size_charts_related_product_ids($attribute_product);
    $term_ids = [];

    foreach ($product_ids as $translated_product_id) {
        $translated_term_ids = wp_get_post_terms($translated_product_id, 'product_cat', ['fields' => 'ids']);

        if (!is_wp_error($translated_term_ids) && $translated_term_ids) {
            $term_ids = array_merge($term_ids, array_map('absint', $translated_term_ids));
        }
    }

    if (is_wp_error($term_ids) || !$term_ids) {
        $cache[$product_id] = '';
        return '';
    }

    $slugs = [];

    foreach ($term_ids as $term_id) {
        $term_id = (int) $term_id;
        $term = get_term($term_id, 'product_cat');

        if ($term && !is_wp_error($term) && !empty($term->slug)) {
            $slugs[] = $term->slug;
        }

        foreach (get_ancestors($term_id, 'product_cat', 'taxonomy') as $ancestor_id) {
            $ancestor = get_term((int) $ancestor_id, 'product_cat');

            if ($ancestor && !is_wp_error($ancestor) && !empty($ancestor->slug)) {
                $slugs[] = $ancestor->slug;
            }
        }
    }

    $slugs = array_unique(array_map('sanitize_title', $slugs));

    foreach (meditrendy_size_charts_contexts() as $context => $settings) {
        $category_slugs = array_map('sanitize_title', (array) ($settings['category_slugs'] ?? []));

        if (array_intersect($slugs, $category_slugs)) {
            $cache[$product_id] = sanitize_key($context);
            return $cache[$product_id];
        }
    }

    $cache[$product_id] = '';
    return '';
}

function meditrendy_size_charts_product_term_data($product, $taxonomy) {
    $attribute_product = meditrendy_size_charts_attribute_product($product);

    if (!$attribute_product) {
        return null;
    }

    $terms = meditrendy_size_charts_product_terms($attribute_product, $taxonomy);

    if (!$terms) {
        return null;
    }

    $context = meditrendy_size_charts_product_context($attribute_product);

    foreach ($terms as $term) {
        $chart = meditrendy_size_charts_term_chart($term, $context);

        if ($chart !== '') {
            return [
                'term'     => $term,
                'taxonomy' => $taxonomy,
                'chart'    => $chart,
                'context'  => $context,
            ];
        }
    }

    return null;
}

function meditrendy_size_charts_set_product_data($product, $taxonomy, $seen = []) {
    if (!$product || !is_a($product, 'WC_Product') || !$product->is_type('woosb')) {
        return null;
    }

    $set_id = (int) $product->get_id();

    if (isset($seen[$set_id])) {
        return null;
    }

    $seen[$set_id] = true;
    $items = function_exists('meditrendy_waitlist_set_items') ? meditrendy_waitlist_set_items($set_id) : [];

    if (!$items && method_exists($product, 'get_items')) {
        $items = (array) $product->get_items();
    }

    foreach ((array) $items as $item) {
        $item_product_id = !empty($item['id']) ? absint($item['id']) : 0;
        $item_product = $item_product_id ? wc_get_product($item_product_id) : null;

        if (!$item_product) {
            continue;
        }

        $data = meditrendy_size_charts_product_term_data($item_product, $taxonomy);

        if ($data) {
            return $data;
        }

        if ($item_product->is_type('woosb')) {
            $data = meditrendy_size_charts_set_product_data($item_product, $taxonomy, $seen);

            if ($data) {
                return $data;
            }
        }
    }

    return null;
}

function meditrendy_size_charts_admin_menu() {
    add_submenu_page(
        'meditrendy-settings',
        __('Size charts', 'meditrendy-core'),
        __('Size charts', 'meditrendy-core'),
        meditrendy_size_charts_capability(),
        'meditrendy-size-charts',
        'meditrendy_render_size_charts_admin_page'
    );
}

function meditrendy_render_size_charts_admin_page() {
    if (!current_user_can(meditrendy_size_charts_capability())) {
        wp_die(esc_html__('You do not have permission to view this page.', 'meditrendy-core'));
    }

    $attributes = meditrendy_size_charts_available_attributes();
    $selected_taxonomy = meditrendy_size_charts_admin_taxonomy();
    $terms = meditrendy_size_charts_get_terms($selected_taxonomy);
    $contexts = meditrendy_size_charts_contexts();
    ?>
    <div class="wrap meditrendy-size-charts-admin">
        <h1><?php esc_html_e('Size charts', 'meditrendy-core'); ?></h1>

        <?php if (isset($_GET['updated'])) : ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e('Size charts saved.', 'meditrendy-core'); ?></p>
            </div>
        <?php endif; ?>

        <?php if (!$attributes) : ?>
            <p><?php esc_html_e('No WooCommerce product attributes are available.', 'meditrendy-core'); ?></p>
        <?php else : ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('meditrendy_save_size_charts'); ?>
                <input type="hidden" name="action" value="meditrendy_save_size_charts">

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="meditrendy-size-chart-attribute"><?php esc_html_e('Product attribute', 'meditrendy-core'); ?></label>
                        </th>
                        <td>
                            <select id="meditrendy-size-chart-attribute" name="attribute_taxonomy">
                                <?php foreach ($attributes as $taxonomy => $label) : ?>
                                    <option value="<?php echo esc_attr($taxonomy); ?>" <?php selected($selected_taxonomy, $taxonomy); ?>>
                                        <?php echo esc_html($label . ' (' . $taxonomy . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Products using a selected attribute item with a chart will show a size chart link. Category-specific charts are used first, then the generic fallback chart.', 'meditrendy-core'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e('Attribute items', 'meditrendy-core'); ?></h2>

                <table class="widefat striped" style="max-width: 1180px;">
                    <thead>
                        <tr>
                            <th style="width: 240px;"><?php esc_html_e('Item', 'meditrendy-core'); ?></th>
                            <th style="width: 300px;"><?php esc_html_e('Translations', 'meditrendy-core'); ?></th>
                            <th><?php esc_html_e('Generic fallback chart', 'meditrendy-core'); ?></th>
                            <?php foreach ($contexts as $context) : ?>
                                <th><?php echo esc_html(sprintf(__('%s chart', 'meditrendy-core'), $context['label'] ?? '')); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($terms) : ?>
                            <?php foreach ($terms as $term) : ?>
                                <?php $chart = (string) get_term_meta($term->term_id, MEDITRENDY_SIZE_CHART_META_KEY, true); ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($term->name); ?></strong>
                                        <br><small><?php echo esc_html($term->slug); ?></small>
                                    </td>
                                    <td>
                                        <?php echo meditrendy_size_charts_render_term_translations($term); ?>
                                    </td>
                                    <td>
                                        <textarea
                                            name="size_charts[<?php echo esc_attr((int) $term->term_id); ?>]"
                                            rows="6"
                                            class="large-text code"
                                            placeholder="<table><tbody><tr><th>Size</th><th>...</th></tr></tbody></table>"
                                        ><?php echo esc_textarea($chart); ?></textarea>
                                    </td>
                                    <?php foreach ($contexts as $context_key => $context) : ?>
                                        <?php $context_chart = (string) get_term_meta($term->term_id, meditrendy_size_charts_context_meta_key($context_key), true); ?>
                                        <td>
                                            <textarea
                                                name="size_chart_contexts[<?php echo esc_attr($context_key); ?>][<?php echo esc_attr((int) $term->term_id); ?>]"
                                                rows="6"
                                                class="large-text code"
                                                placeholder="<table><tbody><tr><th>Size</th><th>...</th></tr></tbody></table>"
                                            ><?php echo esc_textarea($context_chart); ?></textarea>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="<?php echo esc_attr(3 + count($contexts)); ?>"><?php esc_html_e('No items found for this attribute.', 'meditrendy-core'); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php submit_button(__('Save size charts', 'meditrendy-core')); ?>
            </form>

            <script>
            document.getElementById('meditrendy-size-chart-attribute')?.addEventListener('change', function () {
                const url = new URL(window.location.href);
                url.searchParams.set('page', 'meditrendy-size-charts');
                url.searchParams.set('attribute', this.value);
                url.searchParams.delete('updated');
                window.location.href = url.toString();
            });
            </script>
        <?php endif; ?>
    </div>
    <?php
}

function meditrendy_save_size_charts() {
    if (!current_user_can(meditrendy_size_charts_capability())) {
        wp_die(esc_html__('You do not have permission to save size charts.', 'meditrendy-core'));
    }

    check_admin_referer('meditrendy_save_size_charts');

    $attributes = meditrendy_size_charts_available_attributes();
    $taxonomy = isset($_POST['attribute_taxonomy']) ? sanitize_key(wp_unslash($_POST['attribute_taxonomy'])) : '';

    if (!$taxonomy || !isset($attributes[$taxonomy])) {
        wp_die(esc_html__('Invalid product attribute.', 'meditrendy-core'));
    }

    update_option(MEDITRENDY_SIZE_CHART_OPTION, $taxonomy);

    $charts = isset($_POST['size_charts']) && is_array($_POST['size_charts']) ? $_POST['size_charts'] : [];
    $context_charts = isset($_POST['size_chart_contexts']) && is_array($_POST['size_chart_contexts']) ? $_POST['size_chart_contexts'] : [];
    $contexts = meditrendy_size_charts_contexts();

    foreach (meditrendy_size_charts_get_terms($taxonomy) as $term) {
        $term_id = (int) $term->term_id;
        $chart = meditrendy_size_charts_sanitize_html($charts[$term_id] ?? '', true);

        if ($chart === '') {
            delete_term_meta($term_id, MEDITRENDY_SIZE_CHART_META_KEY);
        } else {
            update_term_meta($term_id, MEDITRENDY_SIZE_CHART_META_KEY, $chart);
        }

        foreach ($contexts as $context_key => $context) {
            $context_key = sanitize_key($context_key);
            $context_chart = meditrendy_size_charts_sanitize_html($context_charts[$context_key][$term_id] ?? '', true);
            $meta_key = meditrendy_size_charts_context_meta_key($context_key);

            if ($context_chart === '') {
                delete_term_meta($term_id, $meta_key);
            } else {
                update_term_meta($term_id, $meta_key, $context_chart);
            }
        }
    }

    wp_safe_redirect(
        add_query_arg(
            [
                'page'      => 'meditrendy-size-charts',
                'attribute' => $taxonomy,
                'updated'   => 1,
            ],
            admin_url('admin.php')
        )
    );
    exit;
}

function meditrendy_product_size_chart_data($product = null, $seen = []) {
    if (!function_exists('wc_get_product')) {
        return null;
    }

    if (!$product) {
        $product_id = function_exists('is_product') && is_product() ? get_queried_object_id() : 0;
        $product = $product_id ? wc_get_product($product_id) : null;
    }

    if (!$product || !is_a($product, 'WC_Product')) {
        return null;
    }

    $taxonomies = meditrendy_size_charts_candidate_taxonomies(meditrendy_size_charts_active_taxonomy());

    if (!$taxonomies) {
        return null;
    }

    foreach ($taxonomies as $taxonomy) {
        $data = meditrendy_size_charts_product_term_data($product, $taxonomy);

        if ($data) {
            return $data;
        }
    }

    if ($product->is_type('woosb')) {
        foreach ($taxonomies as $taxonomy) {
            $data = meditrendy_size_charts_set_product_data($product, $taxonomy, $seen);

            if ($data) {
                return $data;
            }
        }
    }

    return null;
}

function meditrendy_size_charts_render_product_size_chart_html($product = null, $context = 'product') {
    $data = meditrendy_product_size_chart_data($product);

    if (!$data) {
        return '';
    }

    static $instance = 0;

    $instance++;
    $product_id = $product && is_a($product, 'WC_Product') ? (int) $product->get_id() : get_queried_object_id();
    $modal_id = 'mt-product-size-chart-' . sanitize_html_class($context) . '-' . absint($product_id) . '-' . absint($data['term']->term_id) . '-' . $instance;
    $sizing_note = __('Amerikietiški dydžiai – rinkitės mažesnį dydį', 'meditrendy-core');

    ob_start();
    ?>
    <div class="mt-product-size-chart">
        <button
            type="button"
            class="mt-product-size-chart-link"
            data-mt-size-chart-open
            aria-haspopup="dialog"
            aria-controls="<?php echo esc_attr($modal_id); ?>"
        >
            <?php esc_html_e('Dydžių lentelė', 'meditrendy-core'); ?>
        </button>
        <div id="<?php echo esc_attr($modal_id); ?>" class="mt-product-size-chart-modal" data-mt-size-chart-modal hidden>
            <div class="mt-product-size-chart-backdrop" data-mt-size-chart-close></div>
            <div class="mt-product-size-chart-dialog" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr($modal_id); ?>-heading" tabindex="-1">
                <div class="mt-product-size-chart-header">
                    <p class="mt-product-size-chart-header-note"><?php echo esc_html($sizing_note); ?></p>
                    <h2 id="<?php echo esc_attr($modal_id); ?>-heading"><?php esc_html_e('Dydžių lentelė', 'meditrendy-core'); ?></h2>
                    <button type="button" class="mt-product-size-chart-close" data-mt-size-chart-close aria-label="<?php esc_attr_e('Uždaryti', 'meditrendy-core'); ?>"></button>
                </div>
                <div class="mt-product-size-chart-content" data-mt-size-chart-content>
                    <?php echo wp_kses($data['chart'], meditrendy_size_charts_allowed_html()); ?>
                </div>
            </div>
        </div>
    </div>
    <?php
    $html = ob_get_clean();
    $link_label = esc_html(meditrendy_size_charts_text('link'));
    $note_label = esc_html(meditrendy_size_charts_text('note'));
    $close_label = esc_attr(meditrendy_size_charts_text('close'));

    $html = preg_replace('/(<button[^>]*class="mt-product-size-chart-link"[^>]*>).*?(<\/button>)/s', '$1' . $link_label . '$2', $html, 1);
    $html = preg_replace('/(<p[^>]*class="mt-product-size-chart-header-note"[^>]*>).*?(<\/p>)/s', '$1' . $note_label . '$2', $html, 1);
    $html = preg_replace('/(<h2[^>]*>).*?(<\/h2>)/s', '$1' . $link_label . '$2', $html, 1);
    $html = preg_replace('/(<button[^>]*class="mt-product-size-chart-close"[^>]*aria-label=")[^"]*(")/s', '$1' . $close_label . '$2', $html, 1);

    return $html;
}

function meditrendy_size_charts_debug_enabled() {
    return isset($_GET['mt_size_chart_debug'])
        && current_user_can('manage_woocommerce');
}

function meditrendy_size_charts_debug_product_rows($product, $taxonomy) {
    $rows = [];

    if (!$product || !is_a($product, 'WC_Product') || !$taxonomy || !taxonomy_exists($taxonomy)) {
        return $rows;
    }

    foreach (meditrendy_size_charts_related_product_ids($product) as $product_id) {
        $related_product = wc_get_product($product_id);
        $terms = $related_product ? meditrendy_size_charts_product_terms($related_product, $taxonomy) : [];

        if (!$terms) {
            $rows[] = sprintf('product %d (%s) / %s: no direct or matched terms', $product_id, $related_product ? $related_product->get_sku() : 'missing product', $taxonomy);
            continue;
        }

        $context = meditrendy_size_charts_product_context($related_product);
        $meta_keys = array_filter([
            $context ? meditrendy_size_charts_context_meta_key($context) : '',
            MEDITRENDY_SIZE_CHART_META_KEY,
        ]);

        foreach ($terms as $term) {
            foreach ($meta_keys as $meta_key) {
                $chart = (string) get_term_meta($term->term_id, $meta_key, true);
                $rows[] = sprintf(
                    'product %d (%s) / %s: term %d %s [%s], key %s, chart=%s',
                    $product_id,
                    $related_product ? $related_product->get_sku() : 'missing product',
                    $taxonomy,
                    (int) $term->term_id,
                    $term->name,
                    $term->slug,
                    $meta_key,
                    trim($chart) === '' ? 'no' : 'yes'
                );
            }
        }
    }

    return $rows;
}

function meditrendy_size_charts_render_debug() {
    if (!meditrendy_size_charts_debug_enabled() || !function_exists('is_product') || !is_product()) {
        return;
    }

    static $rendered = false;

    if ($rendered) {
        return;
    }

    $product = wc_get_product(get_queried_object_id());

    if (!$product) {
        return;
    }

    $rendered = true;

    $active_taxonomy = meditrendy_size_charts_active_taxonomy();
    $taxonomies = meditrendy_size_charts_candidate_taxonomies($active_taxonomy);
    $data = meditrendy_product_size_chart_data($product);
    $lines = [
        'Meditrendy size chart debug',
        'product_id=' . $product->get_id(),
        'product_type=' . $product->get_type(),
        'sku=' . $product->get_sku(),
        'active_taxonomy=' . $active_taxonomy,
        'candidate_taxonomies=' . implode(', ', $taxonomies),
        'related_product_ids=' . implode(', ', meditrendy_size_charts_related_product_ids($product)),
        'resolved_chart=' . ($data ? 'yes' : 'no'),
    ];

    if ($data) {
        $lines[] = sprintf(
            'resolved_term=%d %s [%s], taxonomy=%s, context=%s, chart_length=%d',
            (int) $data['term']->term_id,
            $data['term']->name,
            $data['term']->slug,
            $data['taxonomy'],
            $data['context'],
            strlen((string) $data['chart'])
        );
    }

    foreach ($taxonomies as $taxonomy) {
        $lines[] = '--- ' . $taxonomy;
        $rows = meditrendy_size_charts_debug_product_rows($product, $taxonomy);
        $lines = array_merge($lines, $rows ?: ['no rows']);
    }

    echo '<pre style="white-space:pre-wrap;margin:16px 0;padding:12px;border:1px solid #cc1818;background:#fff8f8;color:#111;font:12px/1.4 monospace;">' . esc_html(implode("\n", $lines)) . '</pre>';
}

function meditrendy_size_chart_shortcode() {
    return meditrendy_size_charts_render_product_size_chart_html();
}

function meditrendy_size_charts_enqueue_assets() {
    if (is_admin() || !function_exists('is_product') || !is_product() || !meditrendy_product_size_chart_data()) {
        return;
    }

    $script_path = MEDITRENDY_CORE_DIR . 'assets/js/product-size-charts.js';
    $style_path = MEDITRENDY_CORE_DIR . 'assets/css/product-size-charts.css';

    wp_enqueue_script(
        'meditrendy-product-size-charts',
        MEDITRENDY_CORE_URL . 'assets/js/product-size-charts.js',
        [],
        file_exists($script_path) ? filemtime($script_path) : '1.0',
        true
    );

    wp_enqueue_style(
        'meditrendy-product-size-charts',
        MEDITRENDY_CORE_URL . 'assets/css/product-size-charts.css',
        [],
        file_exists($style_path) ? filemtime($style_path) : '1.0'
    );
}

function meditrendy_render_product_size_chart_link() {
    if (!function_exists('is_product') || !is_product()) {
        return;
    }

    $product = wc_get_product(get_queried_object_id());

    if ($product && $product->is_type('woosb')) {
        return;
    }

    static $rendered = false;

    if ($rendered) {
        return;
    }

    $html = meditrendy_size_charts_render_product_size_chart_html();

    if ($html === '') {
        return;
    }

    $rendered = true;

    echo $html;
}

function meditrendy_render_set_item_size_chart_link($product) {
    if (!$product || !is_a($product, 'WC_Product')) {
        return;
    }

    echo meditrendy_size_charts_render_product_size_chart_html($product, 'set-item'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function meditrendy_render_fixed_set_item_size_chart_link($product) {
    if (!$product || !is_a($product, 'WC_Product') || $product->is_type('variable')) {
        return;
    }

    echo meditrendy_size_charts_render_product_size_chart_html($product, 'set-fixed-item'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

add_shortcode('meditrendy_size_chart', 'meditrendy_size_chart_shortcode');
add_action('admin_menu', 'meditrendy_size_charts_admin_menu', 35);
add_action('admin_post_meditrendy_save_size_charts', 'meditrendy_save_size_charts');
add_action('wp_enqueue_scripts', 'meditrendy_size_charts_enqueue_assets', 35);
add_action('woocommerce_single_product_summary', 'meditrendy_render_product_size_chart_link', 25);
add_action('woocommerce_before_add_to_cart_form', 'meditrendy_render_product_size_chart_link', 5);
add_action('woosb_after_item_variations', 'meditrendy_render_set_item_size_chart_link', 10);
add_action('woosb_after_item', 'meditrendy_render_fixed_set_item_size_chart_link', 10);
add_action('woocommerce_after_single_product_summary', 'meditrendy_size_charts_render_debug', 1);
add_action('wp_footer', 'meditrendy_size_charts_render_debug', 99);
