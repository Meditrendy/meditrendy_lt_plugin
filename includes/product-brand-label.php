<?php
/**
 * Brand labels above product names on product pages and listings.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gets the terms from the product's brand attribute.
 *
 * Variations inherit the brand from their variable parent.
 *
 * @param WC_Product $product WooCommerce product.
 * @return WP_Term[]
 */
function meditrendy_product_brand_terms($product) {
    if (!$product || !is_a($product, 'WC_Product')) {
        return [];
    }

    $product_id = (int) $product->get_id();

    if ($product->is_type('variation')) {
        $product_id = (int) $product->get_parent_id();
    }

    foreach (['pa_brand', 'product_brand'] as $taxonomy) {
        if (!taxonomy_exists($taxonomy)) {
            continue;
        }

        $terms = wp_get_post_terms($product_id, $taxonomy);

        if (!is_wp_error($terms) && !empty($terms)) {
            return $terms;
        }
    }

    return [];
}

/**
 * Builds the brand label markup for a product.
 *
 * @param WC_Product|null $source_product WooCommerce product, or current product when omitted.
 * @return string
 */
function meditrendy_product_brand_html($source_product = null) {
    if (!$source_product && function_exists('wc_get_product')) {
        global $product;

        $source_product = $product ?: wc_get_product(get_the_ID());
    }

    $terms = meditrendy_product_brand_terms($source_product);

    if (empty($terms)) {
        return '';
    }

    $names = array_values(array_unique(array_filter(array_map(static function ($term) {
        return $term->name;
    }, $terms))));

    if (empty($names)) {
        return '';
    }

    return '<div class="mt-product-brand">' . esc_html(implode(', ', $names)) . '</div>';
}
/**
 * Renders the brand label in a Cornerstone Shortcode element.
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function meditrendy_product_brand_shortcode($atts = []) {
    $atts = shortcode_atts(
        [
            'id' => 0,
        ],
        $atts,
        'meditrendy_product_brand'
    );

    $product = !empty($atts['id']) && function_exists('wc_get_product')
        ? wc_get_product(absint($atts['id']))
        : null;

    return meditrendy_product_brand_html($product);
}
add_shortcode('meditrendy_product_brand', 'meditrendy_product_brand_shortcode');

/**
 * Returns the public archive URL for a WooCommerce product brand.
 *
 * @param WP_Term $brand Product brand term.
 * @return string
 */
function meditrendy_product_brand_archive_url($brand) {
    if (!$brand instanceof WP_Term || $brand->taxonomy !== 'product_brand') {
        return '';
    }

    /**
     * Filters the storefront URL base used for product brand archives.
     *
     * @param string  $base  Brand archive URL base.
     * @param WP_Term $brand Product brand term.
     */
    $base = apply_filters('meditrendy_product_brand_archive_base', 'marka', $brand);
    $base = trim((string) $base, '/');

    if ($base === '') {
        return '';
    }

    $path = user_trailingslashit($base . '/' . $brand->slug, 'category');

    return home_url('/' . ltrim($path, '/'));
}

/**
 * Renders the current product's linked brand logo.
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function meditrendy_product_brand_logo_shortcode($atts = []) {
    $atts = shortcode_atts(
        [
            'id' => 0,
        ],
        $atts,
        'product_brand_logo'
    );

    if (!function_exists('wc_get_product') || !taxonomy_exists('product_brand')) {
        return '';
    }

    global $product;

    $source_product = !empty($atts['id'])
        ? wc_get_product(absint($atts['id']))
        : ($product ?: wc_get_product(get_the_ID()));

    if (!$source_product instanceof WC_Product) {
        return '';
    }

    $product_id = $source_product->is_type('variation')
        ? (int) $source_product->get_parent_id()
        : (int) $source_product->get_id();
    $brands = wp_get_post_terms($product_id, 'product_brand');

    if (is_wp_error($brands) || empty($brands)) {
        return '';
    }

    $brand = reset($brands);
    $image_id = absint(get_term_meta($brand->term_id, 'thumbnail_id', true));
    $brand_url = meditrendy_product_brand_archive_url($brand);

    if (!$image_id || $brand_url === '') {
        return '';
    }

    return sprintf(
        '<a href="%s" class="product-brand-logo">%s</a>',
        esc_url($brand_url),
        wp_get_attachment_image(
            $image_id,
            'full',
            false,
            [
                'class' => 'brand-logo',
            ]
        )
    );
}
add_shortcode('product_brand_logo', 'meditrendy_product_brand_logo_shortcode');

/**
 * Prints the brand directly before the single-product title.
 *
 * @return void
 */
function meditrendy_render_single_product_brand() {
    echo wp_kses_post(meditrendy_product_brand_html());
}
add_action('woocommerce_single_product_summary', 'meditrendy_render_single_product_brand', 4);

/**
 * Prints the brand directly before a standard WooCommerce loop title.
 *
 * @return void
 */
function meditrendy_render_loop_product_brand() {
    echo wp_kses_post(meditrendy_product_brand_html());
}
add_action('woocommerce_shop_loop_item_title', 'meditrendy_render_loop_product_brand', 9);

/**
 * Loads the small shared visual treatment for product-page and card labels.
 *
 * @return void
 */
function meditrendy_enqueue_product_brand_label_styles() {
    wp_enqueue_style(
        'meditrendy-product-brand-label',
        MEDITRENDY_CORE_URL . 'assets/css/product-brand-label.css',
        [],
        '1.0.0'
    );
}
add_action('wp_enqueue_scripts', 'meditrendy_enqueue_product_brand_label_styles');
