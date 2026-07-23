<?php
if (!defined('ABSPATH')) exit;

/**
 * Base assets for the listing-only product colour swatches shortcode.
 */
function meditrendy_listing_color_swatches_enqueue_assets() {
    if (is_admin()) {
        return;
    }

    $css_path = MEDITRENDY_CORE_DIR . 'assets/css/product-listing-color-swatches.css';

    if (!file_exists($css_path)) {
        return;
    }

    wp_enqueue_style(
        'meditrendy-listing-color-swatches',
        MEDITRENDY_CORE_URL . 'assets/css/product-listing-color-swatches.css',
        [],
        filemtime($css_path)
    );
}
add_action('wp_enqueue_scripts', 'meditrendy_listing_color_swatches_enqueue_assets');

/**
 * Get immediately available, translation-ready storefront copy for a language.
 */
function meditrendy_listing_color_swatches_copy($key) {
    $language = function_exists('meditrendy_core_current_language')
        ? meditrendy_core_current_language()
        : (function_exists('pll_current_language') ? pll_current_language('slug') : 'lt');

    if ('ee' === $language) {
        $language = 'et';
    }

    $copy = [
        'lt' => [
            'color_label' => __('Spalva', 'meditrendy-core'),
            'more_label'  => __('+%d daugiau', 'meditrendy-core'),
            'more_title'  => __('Rodyti dar %d spalvas', 'meditrendy-core'),
        ],
        'lv' => [
            'color_label' => __('Krāsa', 'meditrendy-core'),
            'more_label'  => __('+%d vairāk', 'meditrendy-core'),
            'more_title'  => __('Rādīt vēl %d krāsas', 'meditrendy-core'),
        ],
        'et' => [
            'color_label' => __('Värv', 'meditrendy-core'),
            'more_label'  => __('+%d veel', 'meditrendy-core'),
            'more_title'  => __('Näita veel %d värvi', 'meditrendy-core'),
        ],
        'pl' => [
            'color_label' => __('Kolor', 'meditrendy-core'),
            'more_label'  => __('+%d więcej', 'meditrendy-core'),
            'more_title'  => __('Pokaż jeszcze %d kolorów', 'meditrendy-core'),
        ],
    ];

    $copy = isset($copy[$language]) ? $copy[$language] : $copy['lt'];

    return isset($copy[$key]) ? $copy[$key] : '';
}

function meditrendy_listing_color_swatches_bool($value) {
    if (is_bool($value)) {
        return $value;
    }

    return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
}

/**
 * Render colour links for product cards without changing the PDP swatch module.
 *
 * Usage: [meditrendy_listing_colors product_id="123"]
 */
function meditrendy_listing_color_swatches_shortcode($atts = []) {
    if (!function_exists('wc_get_product') ||
        !function_exists('meditrendy_color_swatches_product_terms') ||
        !function_exists('meditrendy_color_swatches_related_product_ids') ||
        !function_exists('meditrendy_color_swatches_color_terms') ||
        !function_exists('meditrendy_color_term_hex')) {
        return '';
    }

    $atts = shortcode_atts(
        [
            'id'         => 0,
            'product_id' => 0,
            'limit'      => 4,
            'show_more'  => 1,
        ],
        is_array($atts) ? $atts : [],
        'meditrendy_listing_colors'
    );

    $product_id = absint($atts['product_id']);

    if (!$product_id) {
        $product_id = absint($atts['id']);
    }

    if (!$product_id) {
        return '';
    }

    $product = wc_get_product($product_id);

    if (!$product || !is_a($product, 'WC_Product')) {
        return '';
    }

    $limit     = max(0, absint($atts['limit']));
    $show_more = meditrendy_listing_color_swatches_bool($atts['show_more']);
    $language  = function_exists('meditrendy_core_current_language')
        ? meditrendy_core_current_language()
        : 'lt';
    $cache_key = 'mt_listing_swatches_v1_' . $product_id . '_' . $language . '_' . $limit . '_' . (int) $show_more;
    $cached    = get_transient($cache_key);

    if (false !== $cached) {
        return $cached;
    }

    $model_terms = meditrendy_color_swatches_product_terms($product, 'pa_model');

    if (empty($model_terms)) {
        return '';
    }

    $model_slugs = meditrendy_color_swatches_term_slugs($model_terms);
    $related_ids = meditrendy_color_swatches_related_product_ids($product, $model_slugs);
    $swatches    = [];

    foreach ((array) $related_ids as $related_id) {
        $related_product = wc_get_product($related_id);

        if (!$related_product || !$related_product->is_in_stock()) {
            continue;
        }

        $color_data  = meditrendy_color_swatches_color_terms($related_product);
        $color_terms = $color_data['terms'];

        if (empty($color_terms)) {
            continue;
        }

        $color_term = $color_terms[0];

        $swatches[] = [
            'active' => (int) $related_id === $product_id,
            'hex'    => meditrendy_color_term_hex($color_term),
            'name'   => $color_term->name,
            'url'    => get_permalink($related_id),
        ];
    }

    if (empty($swatches)) {
        return '';
    }

    $active   = array_values(array_filter($swatches, static function($swatch) {
        return !empty($swatch['active']);
    }));
    $inactive = array_values(array_filter($swatches, static function($swatch) {
        return empty($swatch['active']);
    }));
    $swatches = array_merge($active, $inactive);
    $visible  = $limit > 0 ? array_slice($swatches, 0, $limit) : $swatches;
    $hidden   = max(0, count($swatches) - count($visible));

    ob_start();
    ?>
    <div class="mt-listing-color-swatches">
        <div class="mt-listing-color-swatches__label">
            <?php echo esc_html(meditrendy_listing_color_swatches_copy('color_label')); ?>:
            <span><?php echo esc_html($active[0]['name'] ?? $visible[0]['name']); ?></span>
        </div>
        <div class="mt-listing-color-swatches__items">
            <?php foreach ($visible as $swatch) : ?>
                <a
                    class="mt-listing-color-swatches__swatch<?php echo $swatch['active'] ? ' is-active' : ''; ?>"
                    href="<?php echo esc_url($swatch['url']); ?>"
                    style="--mt-listing-swatch-color: <?php echo esc_attr($swatch['hex']); ?>"
                    title="<?php echo esc_attr($swatch['name']); ?>"
                    aria-label="<?php echo esc_attr($swatch['name']); ?>"
                ></a>
            <?php endforeach; ?>

            <?php if ($show_more && $hidden > 0) : ?>
                <a
                    class="mt-listing-color-swatches__more"
                    href="<?php echo esc_url($product->get_permalink()); ?>"
                    title="<?php echo esc_attr(sprintf(meditrendy_listing_color_swatches_copy('more_title'), $hidden)); ?>"
                    aria-label="<?php echo esc_attr(sprintf(meditrendy_listing_color_swatches_copy('more_title'), $hidden)); ?>"
                ><?php echo esc_html(sprintf(meditrendy_listing_color_swatches_copy('more_label'), $hidden)); ?></a>
            <?php endif; ?>
        </div>
    </div>
    <?php
    $output = ob_get_clean();

    set_transient($cache_key, $output, 12 * HOUR_IN_SECONDS);

    return $output;
}
add_shortcode('meditrendy_listing_colors', 'meditrendy_listing_color_swatches_shortcode');

function meditrendy_listing_color_swatches_clear_cache_for_product($product_id) {
    $product_id = absint($product_id);

    if (!$product_id) {
        return;
    }

    global $wpdb;

    $transients = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like('_transient_mt_listing_swatches_v1_' . $product_id . '_') . '%'
        )
    );

    foreach ($transients as $transient) {
        delete_transient(str_replace('_transient_', '', $transient));
    }
}

function meditrendy_listing_color_swatches_clear_related_cache($product_id) {
    if (!function_exists('wc_get_product') ||
        !function_exists('meditrendy_color_swatches_product_terms') ||
        !function_exists('meditrendy_color_swatches_related_product_ids')) {
        return;
    }

    $product = wc_get_product($product_id);

    if (!$product) {
        return;
    }

    meditrendy_listing_color_swatches_clear_cache_for_product($product_id);
    $model_terms = meditrendy_color_swatches_product_terms($product, 'pa_model');

    if (empty($model_terms)) {
        return;
    }

    foreach (meditrendy_color_swatches_related_product_ids($product, meditrendy_color_swatches_term_slugs($model_terms)) as $related_id) {
        meditrendy_listing_color_swatches_clear_cache_for_product($related_id);
    }
}

function meditrendy_listing_color_swatches_clear_all_cache() {
    global $wpdb;

    $transients = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like('_transient_mt_listing_swatches_v1_') . '%'
        )
    );

    foreach ($transients as $transient) {
        delete_transient(str_replace('_transient_', '', $transient));
    }
}

add_action('save_post_product', 'meditrendy_listing_color_swatches_clear_related_cache');
add_action('edited_pa_color', 'meditrendy_listing_color_swatches_clear_all_cache');
add_action('edited_pa_kolor', 'meditrendy_listing_color_swatches_clear_all_cache');
