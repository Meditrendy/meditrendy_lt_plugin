<?php
if (!defined('ABSPATH')) exit;

function meditrendy_product_category_description_term($atts) {
    $atts = shortcode_atts(
        [
            'id'   => '',
            'slug' => '',
        ],
        $atts,
        'mt_product_category_description'
    );

    if ($atts['id'] !== '') {
        $term = get_term(absint($atts['id']), 'product_cat');
    } elseif ($atts['slug'] !== '') {
        $term = get_term_by('slug', sanitize_title($atts['slug']), 'product_cat');
    } elseif (function_exists('is_product_category') && is_product_category()) {
        $queried = get_queried_object();
        $term = ($queried instanceof WP_Term && $queried->taxonomy === 'product_cat') ? $queried : null;
    } else {
        $term = null;
    }

    return ($term instanceof WP_Term && !is_wp_error($term)) ? $term : null;
}

function meditrendy_product_category_description_is_first_page() {
    $paged = max(
        1,
        is_paged() ? 2 : 1,
        absint(get_query_var('paged')),
        absint(get_query_var('page')),
        absint(get_query_var('product-page'))
    );

    foreach (['paged', 'product-page', 'mt_filter_paged'] as $key) {
        if (isset($_GET[$key])) {
            $paged = max($paged, absint(wp_unslash($_GET[$key])));
        }
    }

    if (isset($_SERVER['REQUEST_URI'])) {
        global $wp_rewrite;

        $request_uri = esc_url_raw(wp_unslash($_SERVER['REQUEST_URI']));
        $path = wp_parse_url($request_uri, PHP_URL_PATH);
        $query = wp_parse_url($request_uri, PHP_URL_QUERY);
        $pagination_base = !empty($wp_rewrite->pagination_base) ? $wp_rewrite->pagination_base : 'page';

        if ($path && preg_match('~/' . preg_quote($pagination_base, '~') . '/([0-9]+)/?~', $path, $matches)) {
            $paged = max($paged, absint($matches[1]));
        }

        if ($query) {
            wp_parse_str($query, $request_query);

            foreach (['paged', 'product-page', 'mt_filter_paged'] as $key) {
                if (isset($request_query[$key])) {
                    $paged = max($paged, absint($request_query[$key]));
                }
            }
        }
    }

    return $paged <= 1;
}

function meditrendy_product_category_description_shortcode($atts = []) {
    if (!meditrendy_product_category_description_is_first_page()) {
        return '';
    }

    $term = meditrendy_product_category_description_term($atts);

    if (!$term) {
        return '';
    }

    $description = get_term_field('description', $term->term_id, 'product_cat', 'raw');
    $description = is_wp_error($description) ? '' : trim((string) $description);

    if ($description === '') {
        return '';
    }

    ob_start();
    ?>
    <section class="mt-product-category-description" aria-label="<?php echo esc_attr__('Kategorijos aprašymas', 'meditrendy-core'); ?>">
        <div class="mt-product-category-description-inner">
            <?php echo wp_kses_post(wpautop(do_shortcode($description))); ?>
        </div>
    </section>
    <?php

    return ob_get_clean();
}

function meditrendy_product_category_description_allow_html($description) {
    $taxonomy = isset($_POST['taxonomy']) ? sanitize_key(wp_unslash($_POST['taxonomy'])) : '';

    if ($taxonomy !== 'product_cat') {
        return wp_filter_kses($description);
    }

    if (!current_user_can('manage_product_terms')) {
        return wp_filter_kses($description);
    }

    return wp_kses_post((string) $description);
}

function meditrendy_product_category_description_replace_core_kses() {
    remove_filter('pre_term_description', 'wp_filter_kses');
    add_filter('pre_term_description', 'meditrendy_product_category_description_allow_html', 10);
}

add_shortcode('mt_product_category_description', 'meditrendy_product_category_description_shortcode');
add_action('init', 'meditrendy_product_category_description_replace_core_kses', 1);
