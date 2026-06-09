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

function meditrendy_product_category_description_shortcode($atts = []) {
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
