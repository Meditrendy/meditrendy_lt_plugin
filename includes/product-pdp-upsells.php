<?php
if (!defined('ABSPATH')) exit;

/**
 * Load the structural styles for product cards rendered by the PDP shortcode.
 */
function meditrendy_pdp_upsells_enqueue_assets() {
    if (is_admin()) {
        return;
    }

    $css_path = MEDITRENDY_CORE_DIR . 'assets/css/product-pdp-upsells.css';

    if (!file_exists($css_path)) {
        return;
    }

    wp_enqueue_style(
        'meditrendy-pdp-upsells',
        MEDITRENDY_CORE_URL . 'assets/css/product-pdp-upsells.css',
        [],
        filemtime($css_path)
    );
}
add_action('wp_enqueue_scripts', 'meditrendy_pdp_upsells_enqueue_assets', 30);

/**
 * Return the translated default heading for the PDP upsells section.
 */
function meditrendy_pdp_upsells_default_title() {
    $language = function_exists('meditrendy_core_current_language')
        ? meditrendy_core_current_language()
        : 'lt';

    $titles = [
        'lt' => __('Jums taip pat gali patikti…', 'meditrendy-core'),
        'lv' => __('Jums varētu patikt arī…', 'meditrendy-core'),
        'et' => __('Sulle võib meeldida ka…', 'meditrendy-core'),
        'pl' => __('Może Ci się spodobać…', 'meditrendy-core'),
        'en' => __('You may also like…', 'meditrendy-core'),
    ];

    return $titles[$language] ?? $titles['lt'];
}

/**
 * Get the visible, configured WooCommerce upsells in WooCommerce's own order.
 */
function meditrendy_pdp_upsells_products($product, $limit, $orderby, $order) {
    if (!$product instanceof WC_Product) {
        return [];
    }

    $upsell_ids = $product->get_upsell_ids();

    if (!$upsell_ids) {
        return [];
    }

    _prime_post_caches($upsell_ids);

    $upsells = array_filter(
        array_map('wc_get_product', $upsell_ids),
        'wc_products_array_filter_visible'
    );

    $upsells = wc_products_array_orderby($upsells, $orderby, $order);

    return $limit > 0 ? array_slice($upsells, 0, $limit) : $upsells;
}

/**
 * Render the current product's configured WooCommerce upsells with the shared
 * listing/filter product-card renderer. This shortcode is intentionally PDP-only.
 *
 * Usage: [meditrendy_pdp_upsells]
 */
function meditrendy_pdp_upsells_shortcode($atts = []) {
    if (!function_exists('is_product') || !is_product() ||
        !function_exists('meditrendy_render_product_card_grid')) {
        return '';
    }

    global $product;

    if (!$product instanceof WC_Product) {
        $product = wc_get_product(get_the_ID());
    }

    if (!$product instanceof WC_Product) {
        return '';
    }

    $atts = shortcode_atts(
        [
            'limit'      => 4,
            'orderby'    => 'rand',
            'order'      => 'desc',
            'title'      => '',
            'show_title' => '1',
        ],
        is_array($atts) ? $atts : [],
        'meditrendy_pdp_upsells'
    );

    $limit = max(1, absint($atts['limit']));
    $orderby = in_array($atts['orderby'], ['rand', 'date', 'title', 'id', 'modified', 'menu_order', 'price'], true)
        ? $atts['orderby']
        : 'rand';
    $order = strtoupper($atts['order']) === 'ASC' ? 'ASC' : 'DESC';
    $upsells = meditrendy_pdp_upsells_products($product, $limit, $orderby, $order);

    if (!$upsells) {
        return '';
    }

    $upsell_ids = array_map(static function($upsell) {
        return (int) $upsell->get_id();
    }, $upsells);
    $query = new WP_Query([
        'post_type'              => 'product',
        'post_status'            => 'publish',
        'posts_per_page'         => count($upsell_ids),
        'post__in'               => $upsell_ids,
        'orderby'                => 'post__in',
        'no_found_rows'          => true,
        'update_post_meta_cache' => true,
        'update_post_term_cache' => true,
    ]);

    if (!$query->have_posts()) {
        return '';
    }

    $title = trim((string) $atts['title']);

    if ($title === '') {
        $title = meditrendy_pdp_upsells_default_title();
    }

    $show_title = in_array(
        strtolower((string) $atts['show_title']),
        ['1', 'true', 'yes', 'on'],
        true
    );
    $section_label = $show_title && $title !== ''
        ? ' aria-labelledby="mt-pdp-upsells-title"'
        : ' aria-label="' . esc_attr(meditrendy_pdp_upsells_default_title()) . '"';

    ob_start();
    ?>
    <section class="mt-pdp-upsells"<?php echo $section_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
        <?php if ($show_title && $title !== '') : ?>
            <h2 id="mt-pdp-upsells-title" class="mt-pdp-upsells__title"><?php echo esc_html($title); ?></h2>
        <?php endif; ?>
        <?php echo meditrendy_render_product_card_grid($query, ['class' => 'mt-pdp-upsells__grid']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </section>
    <?php

    return ob_get_clean();
}
add_shortcode('meditrendy_pdp_upsells', 'meditrendy_pdp_upsells_shortcode');
