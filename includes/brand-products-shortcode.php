<?php
if (!defined('ABSPATH')) exit;

function meditrendy_brand_products_cache_version() {
    return (string) get_option('meditrendy_brand_products_cache_version', '1');
}

function meditrendy_brand_products_bump_cache_version() {
    update_option('meditrendy_brand_products_cache_version', (string) time(), false);
}
add_action('save_post_product', 'meditrendy_brand_products_bump_cache_version');
add_action('woocommerce_product_set_stock_status', 'meditrendy_brand_products_bump_cache_version');
add_action('woocommerce_variation_set_stock_status', 'meditrendy_brand_products_bump_cache_version');
add_action('edited_pa_brand', 'meditrendy_brand_products_bump_cache_version');
add_action('created_pa_brand', 'meditrendy_brand_products_bump_cache_version');
add_action('delete_pa_brand', 'meditrendy_brand_products_bump_cache_version');
add_action('edited_product_cat', 'meditrendy_brand_products_bump_cache_version');
add_action('created_product_cat', 'meditrendy_brand_products_bump_cache_version');
add_action('delete_product_cat', 'meditrendy_brand_products_bump_cache_version');

function meditrendy_brand_products_language_key() {
    if (function_exists('pll_current_language')) {
        $language = pll_current_language('slug');

        if ($language) {
            return $language;
        }
    }

    return determine_locale();
}

function meditrendy_brand_products_visibility_tax_query($hide_out_of_stock) {
    if (!function_exists('wc_get_product_visibility_term_ids')) {
        return [];
    }

    $visibility_terms = wc_get_product_visibility_term_ids();
    $excluded_terms = [];

    foreach (['exclude-from-catalog', 'exclude-from-search'] as $key) {
        if (!empty($visibility_terms[$key])) {
            $excluded_terms[] = (int) $visibility_terms[$key];
        }
    }

    if ($hide_out_of_stock && !empty($visibility_terms['outofstock'])) {
        $excluded_terms[] = (int) $visibility_terms['outofstock'];
    }

    if (!$excluded_terms) {
        return [];
    }

    return [
        [
            'taxonomy' => 'product_visibility',
            'field'    => 'term_taxonomy_id',
            'terms'    => array_values(array_unique($excluded_terms)),
            'operator' => 'NOT IN',
        ],
    ];
}

function meditrendy_brand_products_terms($brand_slugs) {
    if ($brand_slugs === '') {
        $terms = get_terms([
            'taxonomy'   => 'pa_brand',
            'hide_empty' => true,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ]);

        return is_wp_error($terms) ? [] : $terms;
    }

    $terms = [];

    foreach (array_filter(array_map('sanitize_title', explode(',', $brand_slugs))) as $slug) {
        $term = get_term_by('slug', $slug, 'pa_brand');

        if ($term && !is_wp_error($term)) {
            $terms[] = $term;
        }
    }

    return $terms;
}

function meditrendy_brand_products_category_tax_query($category_slugs) {
    $slugs = array_filter(array_map('sanitize_title', explode(',', (string) $category_slugs)));

    if (!$slugs) {
        return [];
    }

    return [
        [
            'taxonomy'         => 'product_cat',
            'field'            => 'slug',
            'terms'            => $slugs,
            'include_children' => true,
        ],
    ];
}

function meditrendy_brand_products_selected_ids($atts) {
    $limit = max(1, min(12, absint($atts['limit'])));
    $terms = meditrendy_brand_products_terms($atts['brands']);

    if (!$terms) {
        return [];
    }

    if ($atts['orderby'] === 'rand') {
        shuffle($terms);
    }

    $selected_product_ids = [];
    $selected_brand_ids = [];
    $visibility_tax_query = meditrendy_brand_products_visibility_tax_query($atts['hide_out_of_stock'] === '1');
    $category_tax_query = meditrendy_brand_products_category_tax_query($atts['parent_category']);

    foreach ($terms as $term) {
        if (count($selected_product_ids) >= $limit) {
            break;
        }

        if (in_array((int) $term->term_id, $selected_brand_ids, true)) {
            continue;
        }

        $tax_query = array_merge(
            [
                [
                    'taxonomy' => 'pa_brand',
                    'field'    => 'term_id',
                    'terms'    => [(int) $term->term_id],
                ],
            ],
            $category_tax_query,
            $visibility_tax_query
        );

        if (count($tax_query) > 1) {
            $tax_query['relation'] = 'AND';
        }

        $query = new WP_Query([
            'post_type'              => 'product',
            'post_status'            => 'publish',
            'posts_per_page'         => 8,
            'post__not_in'           => $selected_product_ids,
            'fields'                 => 'ids',
            'orderby'                => $atts['orderby'] === 'rand' ? 'rand' : 'date',
            'order'                  => 'DESC',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => true,
            'tax_query'              => $tax_query,
        ]);

        foreach ($query->posts as $product_id) {
            $brand_ids = wp_get_post_terms($product_id, 'pa_brand', ['fields' => 'ids']);
            $brand_ids = is_wp_error($brand_ids) ? [] : array_map('intval', $brand_ids);

            if (array_intersect($brand_ids, $selected_brand_ids)) {
                continue;
            }

            $selected_product_ids[] = (int) $product_id;
            $selected_brand_ids = array_values(array_unique(array_merge($selected_brand_ids, $brand_ids)));
            break;
        }
    }

    return $selected_product_ids;
}

function meditrendy_brand_products_render($product_ids) {
    if (!$product_ids) {
        return '';
    }

    $query = new WP_Query([
        'post_type'              => 'product',
        'post_status'            => 'publish',
        'posts_per_page'         => count($product_ids),
        'post__in'               => $product_ids,
        'orderby'                => 'post__in',
        'no_found_rows'          => true,
        'update_post_meta_cache' => true,
        'update_post_term_cache' => true,
    ]);

    if (!$query->have_posts()) {
        return '';
    }

    return meditrendy_render_product_card_grid($query, ['class' => 'mt-brand-products']);
}

function meditrendy_brand_products_shortcode($atts) {
    if (!taxonomy_exists('pa_brand')) {
        return '';
    }

    $atts = shortcode_atts(
        [
            'limit'             => '4',
            'columns'           => '4',
            'brands'            => '',
            'parent_category'   => '',
            'orderby'           => 'rand',
            'hide_out_of_stock' => '1',
            'cache'             => '1',
        ],
        $atts,
        'meditrendy_brand_products'
    );

    $atts['orderby'] = $atts['orderby'] === 'rand' ? 'rand' : 'date';
    $cache_enabled = $atts['cache'] !== '0';
    $cache_key = 'mt_brand_products_' . md5(wp_json_encode([
        'atts'     => $atts,
        'language' => meditrendy_brand_products_language_key(),
        'markup'   => 'product-card-v1',
        'version'  => meditrendy_brand_products_cache_version(),
    ]));

    if ($cache_enabled) {
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }
    }

    $product_ids = meditrendy_brand_products_selected_ids($atts);
    $html = meditrendy_brand_products_render($product_ids);

    if ($cache_enabled) {
        set_transient($cache_key, $html, 6 * HOUR_IN_SECONDS);
    }

    return $html;
}
add_shortcode('meditrendy_brand_products', 'meditrendy_brand_products_shortcode');
