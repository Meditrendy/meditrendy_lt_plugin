<?php
if (!defined('ABSPATH')) exit;

function meditrendy_color_term_hex($term) {
    if(!$term || !isset($term->term_id)) {
        return '';
    }

    $name = isset($term->name) ? strtolower(remove_accents($term->name)) : '';
    $slug = isset($term->slug) ? strtolower(remove_accents($term->slug)) : '';
    $key = $slug . ' ' . $name;

    if(strpos($key, 'black') !== false || strpos($key, 'juoda') !== false || strpos($key, 'juodas') !== false) {
        return '#111111';
    }

    $hex = get_term_meta(
        $term->term_id,
        'color_hex',
        true
    );

    if(is_string($hex)) {
        $hex = trim($hex);
    }

    if(is_string($hex) && preg_match('/^#(?:[0-9a-f]{3}){1,2}$/i', $hex)) {
        return $hex;
    }

    $fallbacks = [
        'balta' => '#ffffff',
        'white' => '#ffffff',
        'navy' => '#001f3f',
        'blue' => '#1f5f9f',
        'melyna' => '#1f5f9f',
        'red' => '#b42318',
        'raudona' => '#b42318',
        'green' => '#5f7f5f',
        'zalia' => '#5f7f5f',
        'grey' => '#6b7280',
        'gray' => '#6b7280',
        'pilka' => '#6b7280',
        'pink' => '#d63384',
        'rozin' => '#d63384',
        'purple' => '#7c3aed',
        'violet' => '#7c3aed',
        'brown' => '#7c4a2d',
        'ruda' => '#7c4a2d',
        'orange' => '#c75a24',
        'oranz' => '#c75a24',
        'yellow' => '#eab308',
        'geltona' => '#eab308',
        'beige' => '#d6c3a1',
        'smelio' => '#d6c3a1',
    ];

    foreach($fallbacks as $needle => $color) {
        if(strpos($key, $needle) !== false) {
            return $color;
        }
    }

    return '#cccccc';
}

function meditrendy_clear_color_swatches_cache_for_product($product_id) {
    $product_id = absint($product_id);

    if(!$product_id) {
        return;
    }

    delete_transient('mt_swatches_' . $product_id);
    delete_transient('mt_swatches_v2_' . $product_id);
    delete_transient('mt_swatches_v3_' . $product_id);
}

function meditrendy_clear_related_color_swatches_cache($product_id) {
    $product_id = absint($product_id);

    if(!$product_id) {
        return;
    }

    meditrendy_clear_color_swatches_cache_for_product($product_id);

    $model_terms = wp_get_post_terms(
        $product_id,
        'pa_model',
        ['fields' => 'slugs']
    );

    if(empty($model_terms) || is_wp_error($model_terms)) {
        return;
    }

    $related = new WP_Query([
        'post_type'              => 'product',
        'posts_per_page'         => 100,
        'post_status'            => 'any',
        'fields'                 => 'ids',
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
        'tax_query'              => [
            [
                'taxonomy' => 'pa_model',
                'field'    => 'slug',
                'terms'    => $model_terms,
            ],
        ],
    ]);

    foreach($related->posts as $related_id) {
        meditrendy_clear_color_swatches_cache_for_product($related_id);
    }
}

function meditrendy_color_swatches_set_items($product) {
    if(!$product || !is_a($product, 'WC_Product') || !$product->is_type('woosb')) {
        return [];
    }

    $product_id = (int) $product->get_id();
    $items = function_exists('meditrendy_waitlist_set_items') ? meditrendy_waitlist_set_items($product_id) : [];

    if(!$items && method_exists($product, 'get_items')) {
        $items = (array) $product->get_items();
    }

    return (array) $items;
}

function meditrendy_color_swatches_product_terms($product, $taxonomy, $seen = []) {
    if(!$product || !is_a($product, 'WC_Product') || !taxonomy_exists($taxonomy)) {
        return [];
    }

    $product_id = (int) $product->get_id();

    if(isset($seen[$product_id])) {
        return [];
    }

    $seen[$product_id] = true;
    $terms = wp_get_post_terms($product_id, $taxonomy);

    if(!is_wp_error($terms) && !empty($terms)) {
        return $terms;
    }

    if(!$product->is_type('woosb')) {
        return [];
    }

    foreach(meditrendy_color_swatches_set_items($product) as $item) {
        $item_product_id = !empty($item['id']) ? absint($item['id']) : 0;
        $item_product = $item_product_id ? wc_get_product($item_product_id) : null;

        if(!$item_product) {
            continue;
        }

        $terms = meditrendy_color_swatches_product_terms($item_product, $taxonomy, $seen);

        if(!empty($terms)) {
            return $terms;
        }
    }

    return [];
}

function meditrendy_color_swatches_term_slugs($terms) {
    $slugs = [];

    foreach((array) $terms as $term) {
        if(isset($term->slug) && $term->slug !== '') {
            $slugs[] = $term->slug;
        }
    }

    return array_values(array_unique($slugs));
}

function meditrendy_color_swatches_related_set_ids($model_slugs) {
    $model_slugs = array_filter((array) $model_slugs);

    if(empty($model_slugs)) {
        return [];
    }

    $query = new WP_Query([
        'post_type'              => 'product',
        'posts_per_page'         => 120,
        'post_status'            => 'publish',
        'fields'                 => 'ids',
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
        'tax_query'              => [
            [
                'taxonomy' => 'product_type',
                'field'    => 'slug',
                'terms'    => ['woosb'],
            ],
        ],
    ]);

    $ids = [];

    foreach($query->posts as $candidate_id) {
        $candidate = wc_get_product($candidate_id);

        if(!$candidate || !$candidate->is_type('woosb')) {
            continue;
        }

        $candidate_model_slugs = meditrendy_color_swatches_term_slugs(
            meditrendy_color_swatches_product_terms($candidate, 'pa_model')
        );

        if(array_intersect($model_slugs, $candidate_model_slugs)) {
            $ids[] = (int) $candidate_id;
        }
    }

    return $ids;
}

function meditrendy_color_swatches_related_product_ids($product, $model_slugs) {
    $model_slugs = array_filter((array) $model_slugs);

    if(!$product || empty($model_slugs)) {
        return [];
    }

    if($product->is_type('woosb')) {
        return meditrendy_color_swatches_related_set_ids($model_slugs);
    }

    $query = new WP_Query([
        'post_type'              => 'product',
        'posts_per_page'         => 28,
        'post_status'            => 'publish',
        'fields'                 => 'ids',
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
        'tax_query'              => [
            [
                'taxonomy' => 'pa_model',
                'field'    => 'slug',
                'terms'    => $model_slugs,
            ],
        ],
    ]);

    return array_map('absint', $query->posts);
}

function meditrendy_color_swatches_shortcode() {
    if(!function_exists('is_product') || !is_product()) {
        return '';
    }

    global $product;

    if(!$product) {
        return '';
    }

    $product_id = $product->get_id();
    $cache_key = 'mt_swatches_v3_' . $product_id;
    $cached = get_transient($cache_key);

    if($cached !== false) {
        return $cached;
    }

    $model_terms = meditrendy_color_swatches_product_terms($product, 'pa_model');

    if(empty($model_terms)) {
        return '';
    }

    $model_slugs = meditrendy_color_swatches_term_slugs($model_terms);
    $related_product_ids = meditrendy_color_swatches_related_product_ids($product, $model_slugs);

    if(empty($related_product_ids)) {
        return '';
    }

    $current_color_terms = meditrendy_color_swatches_product_terms($product, 'pa_color');
    $current_color_name = !empty($current_color_terms) ? $current_color_terms[0]->name : '';
    $swatches_html = '';

    foreach($related_product_ids as $p_id){
        $related_product = wc_get_product($p_id);

        if(!$related_product || !$related_product->is_in_stock()) {
            continue;
        }

        $color_terms = meditrendy_color_swatches_product_terms($related_product, 'pa_color');

        if(empty($color_terms)) {
            continue;
        }

        $color_term = $color_terms[0];
        $hex = meditrendy_color_term_hex($color_term);
        $is_active = ($p_id == $product_id) ? 'active' : '';

        $swatches_html .= '<a href="' . esc_url(get_permalink($p_id)) . '"
        class="mt-swatch ' . esc_attr($is_active) . '"
        style="background:' . esc_attr($hex) . '"
        title="' . esc_attr($color_term->name) . '"
        aria-label="' . esc_attr($color_term->name) . '"></a>';
    }

    if($swatches_html === '') {
        return '';
    }

    ob_start();

    echo '<div class="mt-color-wrapper">';
    echo '<div class="mt-color-label">';
    echo 'Color: <span class="mt-current-color">' . esc_html($current_color_name) . '</span>';
    echo '</div>';
    echo '<div class="mt-color-swatches">';
    echo $swatches_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo '</div>';
    echo '</div>';

    $output = ob_get_clean();

    set_transient($cache_key, $output, 12 * HOUR_IN_SECONDS);

    return $output;
}

function meditrendy_add_colors_to_loop() {
    echo do_shortcode('[meditrendy_colors]');
}

add_action('save_post_product', 'meditrendy_clear_related_color_swatches_cache');
add_action('edited_pa_color', function() {
    global $wpdb;

    $transients = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like('_transient_mt_swatches_') . '%'
        )
    );

    foreach($transients as $transient) {
        delete_transient(str_replace('_transient_', '', $transient));
    }
});
add_shortcode('meditrendy_colors', 'meditrendy_color_swatches_shortcode');
add_action('woocommerce_after_shop_loop_item_title', 'meditrendy_add_colors_to_loop', 15);
