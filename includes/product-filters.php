<?php
if (!defined('ABSPATH')) exit;

const MEDITRENDY_NATIVE_FILTERS_CACHE_VERSION_OPTION = 'meditrendy_native_filters_cache_version';
const MEDITRENDY_NATIVE_FILTERS_CACHE_TTL = 30 * MINUTE_IN_SECONDS;
const MEDITRENDY_NATIVE_FILTERS_RESPONSE_VERSION = '20260529-counts-fix';

function meditrendy_native_filters_cache_version() {
    $version = (string) get_option(MEDITRENDY_NATIVE_FILTERS_CACHE_VERSION_OPTION, '');

    if ($version === '') {
        $version = (string) time();
        update_option(MEDITRENDY_NATIVE_FILTERS_CACHE_VERSION_OPTION, $version, false);
    }

    return $version;
}

function meditrendy_native_filters_bump_cache_version() {
    update_option(MEDITRENDY_NATIVE_FILTERS_CACHE_VERSION_OPTION, (string) microtime(true), false);
}

function meditrendy_native_filters_cache_normalize($value) {
    if (is_array($value)) {
        $normalized = [];
        $is_list = !$value || array_keys($value) === range(0, count($value) - 1);

        foreach ($value as $key => $item) {
            $normalized[(string) $key] = meditrendy_native_filters_cache_normalize($item);
        }

        if ($is_list) {
            sort($normalized);
        } else {
            ksort($normalized);
        }

        return $normalized;
    }

    if (is_scalar($value) || $value === null) {
        return sanitize_text_field((string) wp_unslash($value));
    }

    return '';
}

function meditrendy_native_filters_cache_source($source, $extra = []) {
    $source = is_array($source) ? $source : [];
    $allowed = [
        'mt_filter_context_taxonomy',
        'mt_filter_context_term',
        'mt_filter_paged',
        'mt_min_price',
        'mt_max_price',
    ];

    foreach (meditrendy_native_filter_config() as $filter) {
        if (!empty($filter['param'])) {
            $allowed[] = $filter['param'];
            $allowed[] = $filter['param'] . '[]';
        }
    }

    $cache_source = [];

    foreach (array_unique($allowed) as $key) {
        if (isset($source[$key])) {
            $cache_source[$key] = meditrendy_native_filters_cache_normalize($source[$key]);
        }
    }

    foreach ($extra as $key => $value) {
        $cache_source[(string) $key] = meditrendy_native_filters_cache_normalize($value);
    }

    ksort($cache_source);

    return $cache_source;
}

function meditrendy_native_filters_cache_key($group, $source, $extra = []) {
    $payload = [
        'version' => meditrendy_native_filters_cache_version(),
        'response_version' => MEDITRENDY_NATIVE_FILTERS_RESPONSE_VERSION,
        'group'   => (string) $group,
        'source'  => meditrendy_native_filters_cache_source($source, $extra),
    ];

    return 'mt_nf_' . md5(wp_json_encode($payload));
}

function meditrendy_native_filter_config() {
    $config = [
        'color' => [
            'label'    => 'SPALVA',
            'taxonomy' => 'pa_color-group',
            'param'    => 'mt_color',
            'type'     => 'checkbox',
        ],
        'size' => [
            'label'    => 'DYDIS',
            'taxonomy' => 'pa_size',
            'param'    => 'mt_size',
            'type'     => 'checkbox',
        ],
        'length' => [
            'label'    => 'ILGIS',
            'taxonomy' => ['pa_length', 'pa_ilgis', 'pa_kelniu-ilgis', 'pa_pants-length'],
            'param'    => 'mt_length',
            'type'     => 'checkbox',
        ],
        'brand' => [
            'label'    => 'GAMINTOJAS',
            'taxonomy' => 'pa_brand',
            'param'    => 'mt_brand',
            'type'     => 'checkbox',
        ],
        'price' => [
            'label'    => 'KAINA',
            'taxonomy' => '',
            'param'    => 'mt_price',
            'type'     => 'price',
        ],
    ];

    if (!function_exists('meditrendy_filter_settings')) {
        return $config;
    }

    $settings = meditrendy_filter_settings();

    if (function_exists('meditrendy_filter_settings_available_filters')) {
        foreach (meditrendy_filter_settings_available_filters() as $key => $filter) {
            if (!empty($filter['core']) || empty($filter['taxonomy'])) {
                continue;
            }

            $config[$key] = [
                'label'    => $filter['label'],
                'taxonomy' => $filter['taxonomy'],
                'param'    => 'mt_' . sanitize_key(str_replace('attr_', '', $key)),
                'type'     => 'checkbox',
            ];
        }
    }

    foreach ($config as $key => $filter) {
        $filter_settings = $settings['filters'][$key] ?? [];

        if (empty($filter_settings['enabled'])) {
            unset($config[$key]);
            continue;
        }

        if (!empty($filter_settings['label'])) {
            $config[$key]['label'] = $filter_settings['label'];
        }

        $config[$key]['order'] = absint($filter_settings['order'] ?? 0);
    }

    uasort($config, function ($a, $b) {
        return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
    });

    return $config;
}

function meditrendy_native_filter_taxonomy($filter) {
    $taxonomies = (array) $filter['taxonomy'];

    foreach ($taxonomies as $taxonomy) {
        if (taxonomy_exists($taxonomy)) {
            return $taxonomy;
        }
    }

    return '';
}

function meditrendy_native_filters_should_render() {
    return function_exists('is_shop')
        && (is_shop() || is_product_taxonomy() || is_product_category());
}

function meditrendy_native_filter_values($param, $source = null) {
    $source = $source === null ? $_GET : $source;
    $raw = meditrendy_native_filter_raw_value($param, $source);

    if ($raw === null || $raw === '') {
        return [];
    }

    $raw = is_array($raw) ? implode(',', $raw) : $raw;
    $values = array_map('sanitize_title', explode(',', $raw));

    return array_values(array_filter(array_unique($values)));
}

function meditrendy_native_price_value($param, $source = null) {
    $source = $source === null ? $_GET : $source;

    if (!isset($source[$param])) {
        return '';
    }

    $raw = wp_unslash($source[$param]);

    if (is_array($raw)) {
        $raw = reset($raw);
    }

    $raw = str_replace(',', '.', (string) $raw);

    return is_numeric($raw) ? wc_format_decimal($raw) : '';
}

function meditrendy_native_price_meta_query($source = null) {
    $min = meditrendy_native_price_value('mt_min_price', $source);
    $max = meditrendy_native_price_value('mt_max_price', $source);

    if ($min === '' && $max === '') {
        return [];
    }

    $clause = [
        'key'  => '_price',
        'type' => 'DECIMAL(10,2)',
    ];

    if ($min !== '' && $max !== '') {
        $clause['value'] = [(float) $min, (float) $max];
        $clause['compare'] = 'BETWEEN';
        return [$clause];
    }

    if ($min !== '') {
        $clause['value'] = (float) $min;
        $clause['compare'] = '>=';
        return [$clause];
    }

    $clause['value'] = (float) $max;
    $clause['compare'] = '<=';

    return [$clause];
}

function meditrendy_native_filters_source_without_price($source = null, $include_current_context = true) {
    $source = $source === null ? $_GET : (array) $source;
    unset($source['mt_min_price'], $source['mt_max_price']);

    if ($include_current_context && empty($source['mt_filter_context_taxonomy']) && empty($source['mt_filter_context_term'])) {
        $context = meditrendy_native_filter_context();

        if (!empty($context['taxonomy']) && !empty($context['term'])) {
            $source['mt_filter_context_taxonomy'] = $context['taxonomy'];
            $source['mt_filter_context_term'] = $context['term'];
        }
    }

    return $source;
}

function meditrendy_native_filters_price_placeholder($price) {
    if ($price === '' || $price === null || !is_numeric($price)) {
        return '';
    }

    return wc_format_decimal((float) $price, 2);
}

function meditrendy_native_filters_price_range($source = null) {
    global $wpdb;

    $source = meditrendy_native_filters_source_without_price($source);
    $cache_key = meditrendy_native_filters_cache_key('price_range', $source);
    $cached_range = get_transient($cache_key);

    if (is_array($cached_range)) {
        return $cached_range;
    }

    $args = [
        'post_type'              => 'product',
        'post_status'            => 'publish',
        'posts_per_page'         => -1,
        'fields'                 => 'ids',
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    ];

    $tax_query = meditrendy_native_filters_tax_query($source, true);

    if ($tax_query) {
        $args['tax_query'] = $tax_query;
    }

    $query = new WP_Query(meditrendy_native_filters_apply_stock_visibility_to_args($args));
    $product_ids = array_map('absint', $query->posts);

    if (!$product_ids) {
        $range = [
            'min' => '',
            'max' => '',
        ];

        set_transient($cache_key, $range, MEDITRENDY_NATIVE_FILTERS_CACHE_TTL);
        return $range;
    }

    $placeholders = implode(',', array_fill(0, count($product_ids), '%d'));
    $sql = "
        SELECT MIN(CAST(meta_value AS DECIMAL(10,2))) AS min_price,
               MAX(CAST(meta_value AS DECIMAL(10,2))) AS max_price
        FROM {$wpdb->postmeta}
        WHERE meta_key = '_price'
          AND meta_value <> ''
          AND post_id IN ($placeholders)
    ";
    $row = $wpdb->get_row($wpdb->prepare($sql, $product_ids));

    $range = [
        'min' => $row ? meditrendy_native_filters_price_placeholder($row->min_price) : '',
        'max' => $row ? meditrendy_native_filters_price_placeholder($row->max_price) : '',
    ];

    set_transient($cache_key, $range, MEDITRENDY_NATIVE_FILTERS_CACHE_TTL);

    return $range;
}

function meditrendy_native_filter_raw_value($param, $source = null) {
    $source = $source === null ? $_GET : $source;

    if (isset($source[$param])) {
        return wp_unslash($source[$param]);
    }

    foreach (meditrendy_native_filter_config() as $filter) {
        if ($filter['param'] !== $param) {
            continue;
        }

        $taxonomy = meditrendy_native_filter_taxonomy($filter);

        if (!$taxonomy || strpos($taxonomy, 'pa_') !== 0) {
            return null;
        }

        $woo_param = 'filter_' . substr($taxonomy, 3);

        if (isset($source[$woo_param])) {
            return wp_unslash($source[$woo_param]);
        }

        if ($taxonomy === 'pa_color-group' && isset($source['filter_group_color'])) {
            return wp_unslash($source['filter_group_color']);
        }

        return null;
    }

    return null;
}

function meditrendy_native_filter_base_url() {
    if (is_shop()) {
        $url = get_permalink(wc_get_page_id('shop'));
    } else {
        $queried = get_queried_object();
        $url = !empty($queried->term_id) ? get_term_link($queried) : home_url(add_query_arg([], $GLOBALS['wp']->request));
    }

    if (is_wp_error($url)) {
        $url = home_url('/');
    }

    return $url;
}

function meditrendy_native_filter_terms($taxonomy) {
    if (!taxonomy_exists($taxonomy)) {
        return [];
    }

    $terms = get_terms([
        'taxonomy'   => $taxonomy,
        'hide_empty' => true,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ]);

    return is_wp_error($terms) ? [] : $terms;
}

function meditrendy_native_filters_in_stock_tax_clause() {
    if (!taxonomy_exists('product_visibility')) {
        return [];
    }

    if (function_exists('wc_get_product_visibility_term_ids')) {
        $visibility_terms = wc_get_product_visibility_term_ids();

        if (!empty($visibility_terms['outofstock'])) {
            return [
                'taxonomy' => 'product_visibility',
                'field'    => 'term_taxonomy_id',
                'terms'    => [(int) $visibility_terms['outofstock']],
                'operator' => 'NOT IN',
            ];
        }
    }

    $term = get_term_by('name', 'outofstock', 'product_visibility');

    if (!$term) {
        $term = get_term_by('slug', 'outofstock', 'product_visibility');
    }

    return $term ? [
        'taxonomy' => 'product_visibility',
        'field'    => 'term_taxonomy_id',
        'terms'    => [(int) $term->term_taxonomy_id],
        'operator' => 'NOT IN',
    ] : [];
}

function meditrendy_native_filters_apply_stock_visibility_to_args($args) {
    $stock_clause = meditrendy_native_filters_in_stock_tax_clause();

    if (!$stock_clause) {
        return $args;
    }

    $tax_query = !empty($args['tax_query']) ? (array) $args['tax_query'] : [];

    if (empty($tax_query['relation'])) {
        $tax_query['relation'] = 'AND';
    }

    $tax_query[] = $stock_clause;
    $args['tax_query'] = $tax_query;

    return $args;
}

function meditrendy_native_filter_context() {
    $context = [
        'taxonomy' => '',
        'term'     => '',
    ];

    if (!is_product_taxonomy() && !is_product_category()) {
        return $context;
    }

    $queried = get_queried_object();

    if (!empty($queried->taxonomy) && !empty($queried->slug)) {
        $context['taxonomy'] = $queried->taxonomy;
        $context['term'] = $queried->slug;
    }

    return $context;
}

function meditrendy_native_filter_term_product_count($filter, $term, $context = null) {
    $taxonomy = meditrendy_native_filter_taxonomy($filter);

    if (!$taxonomy || empty($term->slug)) {
        return 0;
    }

    $context = $context === null ? meditrendy_native_filter_context() : $context;
    $tax_query = [
        'relation' => 'AND',
        [
            'taxonomy' => $taxonomy,
            'field'    => 'slug',
            'terms'    => [$term->slug],
            'operator' => 'IN',
        ],
    ];

    if (!empty($context['taxonomy']) && !empty($context['term']) && taxonomy_exists($context['taxonomy'])) {
        $tax_query[] = [
            'taxonomy' => $context['taxonomy'],
            'field'    => 'slug',
            'terms'    => [$context['term']],
            'operator' => 'IN',
        ];
    }

    $args = [
        'post_type'              => 'product',
        'post_status'            => 'publish',
        'posts_per_page'         => 1,
        'fields'                 => 'ids',
        'no_found_rows'          => false,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
        'tax_query'              => $tax_query,
    ];

    $meta_query = meditrendy_native_price_meta_query();

    if ($meta_query) {
        $args['meta_query'] = $meta_query;
    }

    $query = new WP_Query(meditrendy_native_filters_apply_stock_visibility_to_args($args));

    return (int) $query->found_posts;
}

function meditrendy_native_filter_term_product_counts_query($filter, $terms, $context = null) {
    global $wpdb;

    $taxonomy = meditrendy_native_filter_taxonomy($filter);

    if (!$wpdb || !$taxonomy || !$terms) {
        return [];
    }

    $term_taxonomy_ids = [];
    $term_taxonomy_id_to_term_id = [];

    foreach ($terms as $term) {
        if (empty($term->term_id) || empty($term->term_taxonomy_id)) {
            continue;
        }

        $term_taxonomy_id = absint($term->term_taxonomy_id);
        $term_taxonomy_ids[] = $term_taxonomy_id;
        $term_taxonomy_id_to_term_id[$term_taxonomy_id] = absint($term->term_id);
    }

    if (!$term_taxonomy_ids) {
        return [];
    }

    $context = $context === null ? meditrendy_native_filter_context() : $context;
    $joins = [
        "INNER JOIN {$wpdb->term_relationships} filter_rel ON filter_rel.object_id = p.ID",
    ];
    $where = [
        "p.post_type = 'product'",
        "p.post_status = 'publish'",
        'filter_rel.term_taxonomy_id IN (' . implode(',', array_fill(0, count($term_taxonomy_ids), '%d')) . ')',
    ];

    $join_values = [];
    $where_values = $term_taxonomy_ids;
    $price_values = [];

    if (!empty($context['taxonomy']) && !empty($context['term']) && taxonomy_exists($context['taxonomy'])) {
        $context_term = get_term_by('slug', $context['term'], $context['taxonomy']);

        if ($context_term && !is_wp_error($context_term) && !empty($context_term->term_taxonomy_id)) {
            $joins[] = "INNER JOIN {$wpdb->term_relationships} context_rel ON context_rel.object_id = p.ID AND context_rel.term_taxonomy_id = %d";
            $join_values[] = absint($context_term->term_taxonomy_id);
        }
    }

    if (function_exists('wc_get_product_visibility_term_ids')) {
        $visibility_terms = wc_get_product_visibility_term_ids();

        if (!empty($visibility_terms['outofstock'])) {
            $joins[] = "LEFT JOIN {$wpdb->term_relationships} stock_rel ON stock_rel.object_id = p.ID AND stock_rel.term_taxonomy_id = %d";
            $where[] = 'stock_rel.object_id IS NULL';
            $join_values[] = absint($visibility_terms['outofstock']);
        }
    }

    $min_price = meditrendy_native_price_value('mt_min_price');
    $max_price = meditrendy_native_price_value('mt_max_price');

    if ($min_price !== '' || $max_price !== '') {
        $joins[] = "INNER JOIN {$wpdb->postmeta} price_meta ON price_meta.post_id = p.ID AND price_meta.meta_key = '_price' AND price_meta.meta_value <> ''";

        if ($min_price !== '') {
            $where[] = 'CAST(price_meta.meta_value AS DECIMAL(10,2)) >= %f';
            $price_values[] = (float) $min_price;
        }

        if ($max_price !== '') {
            $where[] = 'CAST(price_meta.meta_value AS DECIMAL(10,2)) <= %f';
            $price_values[] = (float) $max_price;
        }
    }

    $sql = "
        SELECT filter_rel.term_taxonomy_id, COUNT(DISTINCT p.ID) AS product_count
        FROM {$wpdb->posts} p
        " . implode("\n", $joins) . "
        WHERE " . implode("\nAND ", $where) . "
        GROUP BY filter_rel.term_taxonomy_id
    ";

    $values = array_merge($join_values, $where_values, $price_values);
    $rows = $wpdb->get_results($wpdb->prepare($sql, $values));
    $counts = array_fill_keys(array_map('absint', array_values($term_taxonomy_id_to_term_id)), 0);

    foreach ((array) $rows as $row) {
        $term_taxonomy_id = absint($row->term_taxonomy_id ?? 0);
        $term_id = $term_taxonomy_id_to_term_id[$term_taxonomy_id] ?? 0;

        if ($term_id) {
            $counts[$term_id] = absint($row->product_count ?? 0);
        }
    }

    return $counts;
}

function meditrendy_native_filter_term_product_counts($filter, $terms, $context = null) {
    $taxonomy = meditrendy_native_filter_taxonomy($filter);

    if (!$taxonomy || !$terms) {
        return [];
    }

    $context = $context === null ? meditrendy_native_filter_context() : $context;
    $count_source = [];

    foreach (['mt_min_price', 'mt_max_price'] as $price_param) {
        if (isset($_GET[$price_param])) {
            $count_source[$price_param] = wp_unslash($_GET[$price_param]);
        }
    }

    $cache_key = meditrendy_native_filters_cache_key(
        'term_counts',
        $count_source,
        [
            'taxonomy'         => $taxonomy,
            'context_taxonomy' => $context['taxonomy'] ?? '',
            'context_term'     => $context['term'] ?? '',
            'language'         => function_exists('meditrendy_current_language_slug') ? meditrendy_current_language_slug() : '',
        ]
    );
    $cached_counts = get_transient($cache_key);

    if (is_array($cached_counts)) {
        return array_map('absint', $cached_counts);
    }

    $counts = meditrendy_native_filter_term_product_counts_query($filter, $terms, $context);

    set_transient($cache_key, $counts, MEDITRENDY_NATIVE_FILTERS_CACHE_TTL);

    return $counts;
}

function meditrendy_native_color_group_hex($term) {
    $hex = get_term_meta($term->term_id, 'color_hex', true);

    if ($hex) {
        return $hex;
    }

    $key = strtolower(str_replace('group-', '', $term->slug));
    $fallbacks = [
        'beige'    => '#d8c7aa',
        'black'    => '#111111',
        'blue'     => '#2f6fbd',
        'brown'    => '#7a4a2b',
        'burgundy' => '#7b1f35',
        'green'    => '#3f7a4d',
        'grey'     => '#8e8e8e',
        'gray'     => '#8e8e8e',
        'navy'     => '#142f59',
        'orange'   => '#ef7d2d',
        'pink'     => '#e98ab0',
        'purple'   => '#7c4aa6',
        'red'      => '#d53b36',
        'white'    => '#ffffff',
        'yellow'   => '#f3c94a',
    ];

    foreach ($fallbacks as $needle => $color) {
        if (strpos($key, $needle) !== false || stripos($term->name, $needle) !== false) {
            return $color;
        }
    }

    return '#cccccc';
}

function meditrendy_native_color_group_class($term) {
    $key = strtolower(str_replace('group-', '', $term->slug));

    return $key === 'white' || stripos($term->name, 'white') !== false ? ' is-light-swatch' : '';
}

function meditrendy_native_filters_count_products($source) {
    $tax_query = meditrendy_native_filters_tax_query($source, true);
    $args = [
        'post_type'              => 'product',
        'post_status'            => 'publish',
        'posts_per_page'         => 1,
        'fields'                 => 'ids',
        'no_found_rows'          => false,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    ];

    if ($tax_query) {
        $args['tax_query'] = $tax_query;
    }

    $meta_query = meditrendy_native_price_meta_query($source);

    if ($meta_query) {
        $args['meta_query'] = $meta_query;
    }

    $query = new WP_Query(meditrendy_native_filters_apply_stock_visibility_to_args($args));

    return (int) $query->found_posts;
}

function meditrendy_native_filters_option_counts($source) {
    $source = is_array($source) ? $source : [];
    $cache_key = meditrendy_native_filters_cache_key('option_counts', $source);
    $cached_counts = get_transient($cache_key);

    if (is_array($cached_counts)) {
        return $cached_counts;
    }

    $counts = [];

    foreach (meditrendy_native_filter_config() as $filter) {
        if (($filter['type'] ?? '') === 'price') {
            continue;
        }

        $taxonomy = meditrendy_native_filter_taxonomy($filter);

        if (!$taxonomy) {
            continue;
        }

        $param = $filter['param'];
        $counts[$param] = [];
        $selected = meditrendy_native_filter_values($param, $source);
        $terms = meditrendy_native_filter_terms($taxonomy);

        foreach ($terms as $term) {
            $option_source = $source;
            $option_values = $selected;

            if (!in_array($term->slug, $option_values, true)) {
                $option_values[] = $term->slug;
            }

            unset($option_source[$param], $option_source[$param . '[]']);
            $option_source[$param] = $option_values;

            $counts[$param][$term->slug] = meditrendy_native_filters_count_products($option_source);
        }
    }

    set_transient($cache_key, $counts, MEDITRENDY_NATIVE_FILTERS_CACHE_TTL);

    return $counts;
}

function meditrendy_native_filter_reset_url() {
    $url = meditrendy_native_filter_base_url();
    $remove = ['paged', 'product-page', 'mt_min_price', 'mt_max_price'];

    foreach (meditrendy_native_filter_config() as $filter) {
        $remove[] = $filter['param'];

        $taxonomy = meditrendy_native_filter_taxonomy($filter);

        if ($taxonomy && strpos($taxonomy, 'pa_') === 0) {
            $remove[] = 'filter_' . substr($taxonomy, 3);
        }
    }

    $remove[] = 'filter_group_color';

    return remove_query_arg($remove, $url);
}

function meditrendy_native_filter_remove_url($param, $slug) {
    $url = remove_query_arg(['paged', 'product-page']);
    $values = meditrendy_native_filter_values($param);
    $values = array_values(array_diff($values, [$slug]));

    $url = remove_query_arg($param, $url);

    if ($values) {
        $url = add_query_arg($param, $values, $url);
    }

    return $url;
}

function meditrendy_native_active_filters_html($visible_filters) {
    $chips = [];

    foreach ($visible_filters as $filter) {
        if (empty($filter['active'])) {
            continue;
        }

        if (($filter['type'] ?? '') === 'price') {
            if (!empty($filter['min_price'])) {
                $chips[] = [
                    'label' => $filter['label'] . ' min: ' . $filter['min_price'],
                    'url'   => remove_query_arg(['mt_min_price', 'paged', 'product-page']),
                ];
            }

            if (!empty($filter['max_price'])) {
                $chips[] = [
                    'label' => $filter['label'] . ' max: ' . $filter['max_price'],
                    'url'   => remove_query_arg(['mt_max_price', 'paged', 'product-page']),
                ];
            }

            continue;
        }

        foreach ($filter['terms'] as $term) {
            if (!in_array($term->slug, $filter['active'], true)) {
                continue;
            }

            $chips[] = [
                'label' => $filter['label'] . ': ' . $term->name,
                'url'   => meditrendy_native_filter_remove_url($filter['param'], $term->slug),
            ];
        }
    }

    if (!$chips) {
        return '';
    }

    ob_start();
    ?>
    <div class="mt-native-active-filters" aria-label="Aktyvus filtrai">
        <?php foreach ($chips as $chip) : ?>
            <a class="mt-native-active-filter" href="<?php echo esc_url($chip['url']); ?>">
                <span><?php echo esc_html($chip['label']); ?></span>
                <span class="mt-native-active-filter-remove" aria-hidden="true"></span>
            </a>
        <?php endforeach; ?>
        <a class="mt-native-active-filter mt-native-active-filter-reset" href="<?php echo esc_url(meditrendy_native_filter_reset_url()); ?>"><?php echo esc_html(meditrendy_filter_setting_label('active_reset')); ?></a>
    </div>
    <?php

    return ob_get_clean();
}

function meditrendy_get_native_product_filters_html() {
    static $rendered = false;

    if ($rendered) {
        return '';
    }

    if (!meditrendy_native_filters_should_render()) {
        return '';
    }

    $filters = meditrendy_native_filter_config();
    $visible_filters = [];
    $context = meditrendy_native_filter_context();
    $settings = function_exists('meditrendy_filter_settings') ? meditrendy_filter_settings() : [
        'show_counts'        => 1,
        'hide_empty_initial' => 1,
    ];
    $show_counts = !empty($settings['show_counts']);
    $hide_empty_initial = !empty($settings['hide_empty_initial']);

    foreach ($filters as $key => $filter) {
        if (($filter['type'] ?? '') === 'price') {
            $min_price = meditrendy_native_price_value('mt_min_price');
            $max_price = meditrendy_native_price_value('mt_max_price');
            $price_range = meditrendy_native_filters_price_range();
            $active = [];

            if ($min_price !== '') {
                $active[] = $min_price;
            }

            if ($max_price !== '') {
                $active[] = $max_price;
            }

            $filter['key'] = $key;
            $filter['terms'] = [];
            $filter['term_counts'] = [];
            $filter['active'] = $active;
            $filter['min_price'] = $min_price;
            $filter['max_price'] = $max_price;
            $filter['min_placeholder'] = $price_range['min'] ?? '';
            $filter['max_placeholder'] = $price_range['max'] ?? '';
            $visible_filters[] = $filter;
            continue;
        }

        $taxonomy = meditrendy_native_filter_taxonomy($filter);
        $terms = $taxonomy ? meditrendy_native_filter_terms($taxonomy) : [];

        if (!$terms) {
            continue;
        }

        $term_counts = meditrendy_native_filter_term_product_counts($filter, $terms, $context);
        $terms = array_values(array_filter($terms, function ($term) use ($term_counts, $hide_empty_initial) {
            $count = (int) ($term_counts[$term->term_id] ?? 0);

            return !$hide_empty_initial || $count > 0;
        }));

        if (!$terms) {
            continue;
        }

        $filter['key'] = $key;
        $filter['resolved_taxonomy'] = $taxonomy;
        $filter['terms'] = $terms;
        $filter['term_counts'] = $term_counts;
        $filter['active'] = meditrendy_native_filter_values($filter['param']);
        $visible_filters[] = $filter;
    }

    if (!$visible_filters) {
        return '';
    }

    $rendered = true;
    $filter_params = ['mt_min_price', 'mt_max_price'];

    foreach ($visible_filters as $filter) {
        if (!empty($filter['param']) && ($filter['type'] ?? '') !== 'price') {
            $filter_params[] = $filter['param'];
        }
    }

    ob_start();
    ?>
    <?php $context = meditrendy_native_filter_context(); ?>
    <form
        class="mt-native-filters"
        method="get"
        action="<?php echo esc_url(meditrendy_native_filter_base_url()); ?>"
        data-mt-native-filters
        data-context-taxonomy="<?php echo esc_attr($context['taxonomy']); ?>"
        data-context-term="<?php echo esc_attr($context['term']); ?>"
        data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
        data-nonce="<?php echo esc_attr(wp_create_nonce('meditrendy_native_filters')); ?>"
        data-filter-params="<?php echo esc_attr(implode(',', array_unique($filter_params))); ?>"
    >
        <button type="button" class="mt-native-filters-trigger" aria-expanded="false">
            <span class="mt-native-filters-trigger-icon" aria-hidden="true"></span>
            <span><?php echo esc_html(meditrendy_filter_setting_label('trigger')); ?></span>
        </button>

        <div class="mt-native-filters-panel">
            <div class="mt-native-filters-header">
                <span><?php echo esc_html(meditrendy_filter_setting_label('panel')); ?></span>
                <button type="button" class="mt-native-filters-close" aria-label="Uzdaryti filtrus"></button>
            </div>

            <div class="mt-native-filters-items">
                <?php foreach ($visible_filters as $filter) : ?>
                    <div class="mt-native-filter" data-filter="<?php echo esc_attr($filter['key']); ?>">
                        <button type="button" class="mt-native-filter-heading" aria-expanded="false">
                            <span><?php echo esc_html($filter['label']); ?></span>
                            <span class="mt-native-filter-toggle" aria-hidden="true"></span>
                        </button>

                        <div class="mt-native-filter-body">
                            <?php if (($filter['type'] ?? '') === 'price') : ?>
                                <div class="mt-native-price-filter">
                                    <label>
                                        <span>Min</span>
                                        <input type="number" min="0" step="0.01" name="mt_min_price" value="<?php echo esc_attr($filter['min_price']); ?>" placeholder="<?php echo esc_attr($filter['min_placeholder'] ?? ''); ?>">
                                    </label>
                                    <label>
                                        <span>Max</span>
                                        <input type="number" min="0" step="0.01" name="mt_max_price" value="<?php echo esc_attr($filter['max_price']); ?>" placeholder="<?php echo esc_attr($filter['max_placeholder'] ?? ''); ?>">
                                    </label>
                                </div>
                            <?php else : ?>
                                <ul>
                                    <?php foreach ($filter['terms'] as $term) : ?>
                                        <?php $input_id = 'mt-native-' . $filter['key'] . '-' . $term->term_id; ?>
                                        <li
                                            class="<?php echo in_array($term->slug, $filter['active'], true) ? 'is-active' : ''; ?>"
                                            data-filter-param="<?php echo esc_attr($filter['param']); ?>"
                                            data-filter-value="<?php echo esc_attr($term->slug); ?>"
                                            data-filter-count="<?php echo esc_attr((int) ($filter['term_counts'][$term->term_id] ?? 0)); ?>"
                                        >
                                            <input
                                                id="<?php echo esc_attr($input_id); ?>"
                                                type="checkbox"
                                                name="<?php echo esc_attr($filter['param']); ?>[]"
                                                value="<?php echo esc_attr($term->slug); ?>"
                                                <?php checked(in_array($term->slug, $filter['active'], true)); ?>
                                            >
                                            <label for="<?php echo esc_attr($input_id); ?>">
                                                <?php if ($filter['key'] === 'color') : ?>
                                                    <span
                                                        class="mt-native-color-dot<?php echo esc_attr(meditrendy_native_color_group_class($term)); ?>"
                                                        style="background: <?php echo esc_attr(meditrendy_native_color_group_hex($term)); ?>"
                                                    ></span>
                                                <?php endif; ?>
                                                <span><?php echo esc_html($term->name); ?></span>
                                                <?php if ($show_counts) : ?>
                                                    <span class="mt-native-filter-option-count">(<?php echo esc_html((int) ($filter['term_counts'][$term->term_id] ?? 0)); ?>)</span>
                                                <?php endif; ?>
                                            </label>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-native-filters-footer">
                <a class="mt-native-filters-reset" href="<?php echo esc_url(meditrendy_native_filter_reset_url()); ?>"><?php echo esc_html(meditrendy_filter_setting_label('reset')); ?></a>
                <button type="submit" class="mt-native-filters-submit"><?php echo esc_html(meditrendy_filter_setting_label('submit')); ?></button>
            </div>
        </div>

        <a class="mt-native-filters-reset mt-native-filters-reset-desktop" href="<?php echo esc_url(meditrendy_native_filter_reset_url()); ?>"><?php echo esc_html(meditrendy_filter_setting_label('reset')); ?></a>
    </form>
    <?php echo meditrendy_native_active_filters_html($visible_filters); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    <?php

    return ob_get_clean();
}

function meditrendy_render_native_product_filters() {
    echo meditrendy_get_native_product_filters_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function meditrendy_native_filters_have_values() {
    if (meditrendy_native_price_value('mt_min_price') !== '' || meditrendy_native_price_value('mt_max_price') !== '') {
        return true;
    }

    foreach (meditrendy_native_filter_config() as $filter) {
        if (($filter['type'] ?? '') === 'price') {
            continue;
        }

        if (meditrendy_native_filter_values($filter['param'])) {
            return true;
        }
    }

    return false;
}

function meditrendy_native_filters_tax_query($source = null, $include_context = false) {
    $source = $source === null ? $_GET : $source;
    $tax_query = [
        'relation' => 'AND',
    ];

    if ($include_context && !empty($source['mt_filter_context_taxonomy']) && !empty($source['mt_filter_context_term'])) {
        $taxonomy = sanitize_key(wp_unslash($source['mt_filter_context_taxonomy']));
        $term = sanitize_title(wp_unslash($source['mt_filter_context_term']));

        if (taxonomy_exists($taxonomy) && $term) {
            $tax_query[] = [
                'taxonomy' => $taxonomy,
                'field'    => 'slug',
                'terms'    => [$term],
                'operator' => 'IN',
            ];
        }
    }

    foreach (meditrendy_native_filter_config() as $filter) {
        if (($filter['type'] ?? '') === 'price') {
            continue;
        }

        $taxonomy = meditrendy_native_filter_taxonomy($filter);

        if (!$taxonomy) {
            continue;
        }

        $values = meditrendy_native_filter_values($filter['param'], $source);

        if (!$values) {
            continue;
        }

        $tax_query[] = [
            'taxonomy' => $taxonomy,
            'field'    => 'slug',
            'terms'    => $values,
            'operator' => 'IN',
        ];
    }

    return count($tax_query) > 1 ? $tax_query : [];
}

function meditrendy_native_filters_request_source($source) {
    $source = is_array($source) ? $source : [];

    if (empty($source['mt_filter_url'])) {
        return $source;
    }

    $url = esc_url_raw(wp_unslash($source['mt_filter_url']));
    $parts = wp_parse_url($url);

    if (empty($parts['query'])) {
        return $source;
    }

    parse_str($parts['query'], $query_args);

    foreach ($query_args as $key => $value) {
        if (!isset($source[$key])) {
            $source[$key] = $value;
        }
    }

    return $source;
}

function meditrendy_apply_native_product_filters_to_query($query) {
    if (is_admin() || !meditrendy_native_filters_should_render() || !meditrendy_native_filters_have_values()) {
        return;
    }

    if ($query->get('_meditrendy_native_filters_applied')) {
        return;
    }

    $tax_query = (array) $query->get('tax_query');

    if (empty($tax_query['relation'])) {
        $tax_query['relation'] = 'AND';
    }

    $filter_tax_query = meditrendy_native_filters_tax_query();

    foreach ($filter_tax_query as $key => $clause) {
        if ($key === 'relation') {
            continue;
        }

        $tax_query[] = $clause;
    }

    $stock_clause = meditrendy_native_filters_in_stock_tax_clause();

    if ($stock_clause) {
        $tax_query[] = $stock_clause;
    }

    $query->set('tax_query', $tax_query);

    $price_meta_query = meditrendy_native_price_meta_query();

    if ($price_meta_query) {
        $meta_query = (array) $query->get('meta_query');

        if (empty($meta_query['relation'])) {
            $meta_query['relation'] = 'AND';
        }

        foreach ($price_meta_query as $clause) {
            $meta_query[] = $clause;
        }

        $query->set('meta_query', $meta_query);
    }

    $query->set('_meditrendy_native_filters_applied', true);
}

function meditrendy_apply_native_product_filters($query) {
    meditrendy_apply_native_product_filters_to_query($query);
}

function meditrendy_apply_native_product_filters_to_wp_query($query) {
    if (!$query instanceof WP_Query) {
        return;
    }

    $post_type = $query->get('post_type');
    $is_product_query = $post_type === 'product' || (is_array($post_type) && in_array('product', $post_type, true));

    if (!$is_product_query && !$query->is_main_query()) {
        return;
    }

    meditrendy_apply_native_product_filters_to_query($query);
}

function meditrendy_native_filters_enqueue_assets() {
    if (is_admin() || !meditrendy_native_filters_should_render()) {
        return;
    }

    $css_path = MEDITRENDY_CORE_DIR . 'assets/css/product-filters.css';
    $js_path = MEDITRENDY_CORE_DIR . 'assets/js/product-filters.js';

    if (file_exists($css_path)) {
        wp_enqueue_style(
            'meditrendy-native-filters',
            MEDITRENDY_CORE_URL . 'assets/css/product-filters.css',
            [],
            filemtime($css_path)
        );
    }

    if (file_exists($js_path)) {
        wp_enqueue_script(
            'meditrendy-native-filters',
            MEDITRENDY_CORE_URL . 'assets/js/product-filters.js',
            [],
            filemtime($js_path),
            true
        );

        wp_localize_script(
            'meditrendy-native-filters',
            'MeditrendyNativeFilters',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('meditrendy_native_filters'),
                'labels'  => [
                    'submit'  => 'Rodyti rezultatus',
                    'showProducts' => 'Rodyti produktus',
                    'loading' => 'Skaičiuojama...',
                ],
            ]
        );

        wp_scripts()->add_data('meditrendy-native-filters', 'data', '');

        wp_localize_script(
            'meditrendy-native-filters',
            'MeditrendyNativeFilters',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('meditrendy_native_filters'),
                'labels'  => [
                    'submit'     => meditrendy_filter_setting_label('submit'),
                    'showProducts' => meditrendy_filter_setting_label('show_products'),
                    'loading'    => meditrendy_filter_setting_label('loading'),
                    'reset'      => meditrendy_filter_setting_label('active_reset'),
                    'noProducts' => meditrendy_filter_setting_label('no_products'),
                ],
                'settings' => [
                    'showCounts'         => !empty(meditrendy_filter_settings()['show_counts']),
                    'disableUnavailable' => !empty(meditrendy_filter_settings()['disable_unavailable']),
                ],
            ]
        );
    }
}

function meditrendy_native_filters_ajax_count() {
    check_ajax_referer('meditrendy_native_filters', 'nonce');

    $source = meditrendy_native_filters_request_source($_POST);
    $cache_key = meditrendy_native_filters_cache_key('ajax_count', $source);
    $cached_response = get_transient($cache_key);

    if (is_array($cached_response)) {
        wp_send_json_success($cached_response);
    }

    $response = [
        'count'        => meditrendy_native_filters_count_products($source),
        'optionCounts' => meditrendy_native_filters_option_counts($source),
        'priceRange'   => meditrendy_native_filters_price_range($source),
    ];

    set_transient($cache_key, $response, MEDITRENDY_NATIVE_FILTERS_CACHE_TTL);

    wp_send_json_success($response);
}

function meditrendy_native_filters_products_per_page() {
    if (function_exists('wc_get_default_products_per_row') && function_exists('wc_get_default_product_rows_per_page')) {
        return (int) apply_filters(
            'loop_shop_per_page',
            wc_get_default_products_per_row() * wc_get_default_product_rows_per_page()
        );
    }

    return (int) get_option('posts_per_page', 12);
}

function meditrendy_native_filters_render_products($query) {
    ob_start();

    if ($query->have_posts()) {
        woocommerce_product_loop_start();

        while ($query->have_posts()) {
            $query->the_post();
            wc_get_template_part('content', 'product');
        }

        woocommerce_product_loop_end();
    } else {
        woocommerce_product_loop_start();
        echo '<li class="product mt-native-no-products">' . esc_html(meditrendy_filter_setting_label('no_products')) . '</li>';
        woocommerce_product_loop_end();
    }

    wp_reset_postdata();

    return ob_get_clean();
}

function meditrendy_native_filters_pagination_html($query, $current_url, $paged) {
    if ((int) $query->max_num_pages <= 1) {
        return '';
    }

    $current_url = $current_url ? esc_url_raw(wp_unslash($current_url)) : meditrendy_native_filter_base_url();
    $current_url = remove_query_arg(['paged', 'product-page'], $current_url);

    $links = paginate_links([
        'base'      => esc_url_raw(add_query_arg('paged', '%#%', $current_url)),
        'format'    => '',
        'current'   => max(1, (int) $paged),
        'total'     => (int) $query->max_num_pages,
        'type'      => 'list',
        'prev_next' => true,
        'prev_text' => '&larr;',
        'next_text' => '&rarr;',
        'end_size'  => 1,
        'mid_size'  => 1,
    ]);

    return $links ? '<nav class="woocommerce-pagination" aria-label="' . esc_attr__('Product Pagination', 'woocommerce') . '">' . $links . '</nav>' : '';
}

function meditrendy_native_filters_woocommerce_pagination_args($args) {
    $args['prev_next'] = true;
    $args['prev_text'] = '&larr;';
    $args['next_text'] = '&rarr;';
    $args['end_size'] = 1;
    $args['mid_size'] = 1;

    return $args;
}

add_filter('woocommerce_pagination_args', 'meditrendy_native_filters_woocommerce_pagination_args');

function meditrendy_native_filters_result_count_html($query, $paged, $per_page) {
    $total = (int) $query->found_posts;

    if (!$total) {
        return '';
    }

    $first = ($per_page * ($paged - 1)) + 1;
    $last = min($total, $per_page * $paged);

    if ($total <= $per_page) {
        $text = sprintf(_n('Showing the single result', 'Showing all %d results', $total, 'woocommerce'), $total);
    } else {
        $text = sprintf(
            _nx('Showing %1$d&ndash;%2$d of %3$d result', 'Showing %1$d&ndash;%2$d of %3$d results', $total, 'with first and last result', 'woocommerce'),
            $first,
            $last,
            $total
        );
    }

    return '<p class="woocommerce-result-count">' . esc_html($text) . '</p>';
}

function meditrendy_native_filters_ajax_products() {
    check_ajax_referer('meditrendy_native_filters', 'nonce');

    $source = meditrendy_native_filters_request_source($_POST);
    $paged = !empty($source['mt_filter_paged']) ? max(1, absint($source['mt_filter_paged'])) : 1;
    $per_page = meditrendy_native_filters_products_per_page();
    $cache_key = meditrendy_native_filters_cache_key('ajax_products', $source, [
        'paged'          => $paged,
        'per_page'       => $per_page,
        'product_cards'  => 'v1',
    ]);
    $cached_response = get_transient($cache_key);

    if (is_array($cached_response)) {
        wp_send_json_success($cached_response);
    }

    $tax_query = meditrendy_native_filters_tax_query($source, true);

    $args = [
        'post_type'              => 'product',
        'post_status'            => 'publish',
        'posts_per_page'         => $per_page,
        'paged'                  => $paged,
        'no_found_rows'          => false,
        'update_post_meta_cache' => true,
        'update_post_term_cache' => true,
    ];

    if ($tax_query) {
        $args['tax_query'] = $tax_query;
    }

    $meta_query = meditrendy_native_price_meta_query($source);

    if ($meta_query) {
        $args['meta_query'] = $meta_query;
    }

    $query = new WP_Query(meditrendy_native_filters_apply_stock_visibility_to_args($args));

    if (function_exists('wc_set_loop_prop')) {
        wc_set_loop_prop('total', (int) $query->found_posts);
        wc_set_loop_prop('per_page', (int) $per_page);
        wc_set_loop_prop('current_page', (int) $paged);
        wc_set_loop_prop('is_paginated', (int) $query->max_num_pages > 1);
    }

    $response = [
        'productsHtml'    => meditrendy_native_filters_render_products($query),
        'productCardsHtml' => meditrendy_render_product_card_grid(
            $query,
            [
                'id'         => 'products-wrapper',
                'empty_text' => meditrendy_filter_setting_label('no_products'),
            ]
        ),
        'paginationHtml'   => meditrendy_native_filters_pagination_html($query, $source['mt_filter_url'] ?? '', $paged),
        'resultCountHtml' => meditrendy_native_filters_result_count_html($query, $paged, $per_page),
        'count'           => (int) $query->found_posts,
        'currentPage'     => (int) $paged,
        'maxPages'        => (int) $query->max_num_pages,
        'optionCounts'    => meditrendy_native_filters_option_counts($source),
        'priceRange'      => meditrendy_native_filters_price_range($source),
    ];

    set_transient($cache_key, $response, MEDITRENDY_NATIVE_FILTERS_CACHE_TTL);

    wp_send_json_success($response);
}

function meditrendy_native_filters_cache_relevant_taxonomy($taxonomy) {
    $taxonomy = (string) $taxonomy;

    return $taxonomy === 'product_cat'
        || $taxonomy === 'product_tag'
        || $taxonomy === 'product_visibility'
        || strpos($taxonomy, 'pa_') === 0;
}

function meditrendy_native_filters_bump_cache_for_product($product_id = 0) {
    $post_type = $product_id ? get_post_type($product_id) : '';

    if ($product_id && $post_type && !in_array($post_type, ['product', 'product_variation'], true)) {
        return;
    }

    meditrendy_native_filters_bump_cache_version();
}

function meditrendy_native_filters_bump_cache_for_terms($object_id, $terms, $tt_ids, $taxonomy) {
    if (!meditrendy_native_filters_cache_relevant_taxonomy($taxonomy)) {
        return;
    }

    meditrendy_native_filters_bump_cache_for_product(absint($object_id));
}

function meditrendy_native_filters_bump_cache_for_term_change($term_id, $tt_id = null, $taxonomy = '') {
    if (meditrendy_native_filters_cache_relevant_taxonomy($taxonomy)) {
        meditrendy_native_filters_bump_cache_version();
    }
}

add_shortcode('meditrendy_product_filters', 'meditrendy_get_native_product_filters_html');
add_action('woocommerce_product_query', 'meditrendy_apply_native_product_filters', 20);
add_action('pre_get_posts', 'meditrendy_apply_native_product_filters_to_wp_query', 20);
add_action('wp_enqueue_scripts', 'meditrendy_native_filters_enqueue_assets', 30);
add_action('wp_ajax_meditrendy_native_filters_count', 'meditrendy_native_filters_ajax_count');
add_action('wp_ajax_nopriv_meditrendy_native_filters_count', 'meditrendy_native_filters_ajax_count');
add_action('wp_ajax_meditrendy_native_filters_products', 'meditrendy_native_filters_ajax_products');
add_action('wp_ajax_nopriv_meditrendy_native_filters_products', 'meditrendy_native_filters_ajax_products');
add_action('save_post_product', 'meditrendy_native_filters_bump_cache_for_product');
add_action('save_post_product_variation', 'meditrendy_native_filters_bump_cache_for_product');
add_action('deleted_post', 'meditrendy_native_filters_bump_cache_for_product');
add_action('trashed_post', 'meditrendy_native_filters_bump_cache_for_product');
add_action('untrashed_post', 'meditrendy_native_filters_bump_cache_for_product');
add_action('woocommerce_update_product', 'meditrendy_native_filters_bump_cache_for_product');
add_action('woocommerce_update_product_variation', 'meditrendy_native_filters_bump_cache_for_product');
add_action('woocommerce_product_set_stock', 'meditrendy_native_filters_bump_cache_version');
add_action('woocommerce_variation_set_stock', 'meditrendy_native_filters_bump_cache_version');
add_action('woocommerce_product_set_stock_status', 'meditrendy_native_filters_bump_cache_for_product', 20);
add_action('set_object_terms', 'meditrendy_native_filters_bump_cache_for_terms', 10, 4);
add_action('created_term', 'meditrendy_native_filters_bump_cache_for_term_change', 10, 3);
add_action('edited_term', 'meditrendy_native_filters_bump_cache_for_term_change', 10, 3);
add_action('delete_term', 'meditrendy_native_filters_bump_cache_for_term_change', 10, 3);
add_action('update_option_meditrendy_filter_settings', 'meditrendy_native_filters_bump_cache_version');
