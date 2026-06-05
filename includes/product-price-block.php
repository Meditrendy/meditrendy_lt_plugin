<?php
if (!defined('ABSPATH')) exit;

function meditrendy_product_price_block_product($product_id = 0) {
    if ($product_id) {
        return wc_get_product($product_id);
    }

    global $product;

    if ($product instanceof WC_Product) {
        return $product;
    }

    $loop_product_id = get_the_ID();

    if ($loop_product_id && get_post_type($loop_product_id) === 'product') {
        return wc_get_product($loop_product_id);
    }

    if (is_product()) {
        return wc_get_product(get_queried_object_id());
    }

    return false;
}

function meditrendy_product_price_block_raw_price($product, $price_type) {
    if (!$product instanceof WC_Product) {
        return '';
    }

    if ($product->is_type('variable')) {
        if ($price_type === 'regular') {
            return $product->get_variation_regular_price('min', false);
        }

        return $product->get_variation_price('min', false);
    }

    if ($price_type === 'regular') {
        return $product->get_regular_price();
    }

    return $product->get_price();
}

function meditrendy_product_price_block_price_html($product, $raw_price) {
    if (!$product instanceof WC_Product || $raw_price === '' || !is_numeric($raw_price)) {
        return '';
    }

    $display_price = wc_get_price_to_display($product, ['price' => (float) $raw_price]);

    return wc_price($display_price);
}

function meditrendy_product_price_block_sale_html_parts($product) {
    if (!$product instanceof WC_Product) {
        return [
            'regular' => '',
            'current' => '',
        ];
    }

    $price_html = $product->get_price_html();
    $parts = [
        'regular' => '',
        'current' => '',
    ];

    if (preg_match('/<del\b[^>]*>(.*?)<\/del>/is', $price_html, $regular_match)) {
        $parts['regular'] = $regular_match[1];
    }

    if (preg_match('/<ins\b[^>]*>(.*?)<\/ins>/is', $price_html, $current_match)) {
        $parts['current'] = $current_match[1];
    }

    return $parts;
}

function meditrendy_product_price_block_omnibus_html($product) {
    if (!$product instanceof WC_Product || !shortcode_exists('wc_price_history')) {
        return '';
    }

    $price_history = do_shortcode('[wc_price_history id="' . $product->get_id() . '" show_currency="1"]');

    if (
        trim(wp_strip_all_tags($price_history)) === ''
        || strpos($price_history, '[psfw_price]') !== false
    ) {
        return '';
    }

    return sprintf(
        '<div class="mt-pdp-price-omnibus"><span class="mt-pdp-price-omnibus-label">%1$s</span> %2$s</div>',
        esc_html__('Mažiausia kaina per 30 d.:', 'meditrendy-core'),
        wp_kses_post($price_history)
    );
}

function meditrendy_product_price_block_shortcode($atts) {
    $atts = shortcode_atts(
        [
            'id' => 0,
            'show_omnibus' => '1',
        ],
        $atts,
        'mt_product_price_block'
    );

    $product = meditrendy_product_price_block_product(absint($atts['id']));

    if (!$product instanceof WC_Product) {
        return '';
    }

    $current_price = meditrendy_product_price_block_raw_price($product, 'current');
    $regular_price = meditrendy_product_price_block_raw_price($product, 'regular');
    $current_html = meditrendy_product_price_block_price_html($product, $current_price);
    $regular_html = meditrendy_product_price_block_price_html($product, $regular_price);

    if ($current_html === '') {
        return '';
    }

    $has_numeric_sale = $regular_price !== ''
        && is_numeric($regular_price)
        && is_numeric($current_price)
        && (float) $regular_price > (float) $current_price;
    $sale_html_parts = meditrendy_product_price_block_sale_html_parts($product);
    $is_sale = $has_numeric_sale || ($product->is_on_sale() && $sale_html_parts['regular'] !== '');

    if ($is_sale && $regular_html === '' && $sale_html_parts['regular'] !== '') {
        $regular_html = $sale_html_parts['regular'];
    }

    if ($is_sale && $sale_html_parts['current'] !== '') {
        $current_html = $sale_html_parts['current'];
    }

    $omnibus_html = ($is_sale && $atts['show_omnibus'] === '1') ? meditrendy_product_price_block_omnibus_html($product) : '';
    $classes = 'mt-pdp-price-block' . ($is_sale ? ' is-sale' : '');

    ob_start();
    ?>
    <div class="<?php echo esc_attr($classes); ?>">
        <div class="mt-pdp-price-row">
            <span class="mt-pdp-price-current"><?php echo wp_kses_post($current_html); ?></span>
            <?php if ($is_sale && $regular_html !== '') : ?>
                <del class="mt-pdp-price-regular"><?php echo wp_kses_post($regular_html); ?></del>
            <?php endif; ?>
        </div>
        <?php echo wp_kses_post($omnibus_html); ?>
    </div>
    <?php

    return ob_get_clean();
}

add_shortcode('mt_product_price_block', 'meditrendy_product_price_block_shortcode');

function meditrendy_product_card_price_shortcode($atts) {
    $atts = shortcode_atts(
        [
            'id' => 0,
            'show_omnibus' => '1',
        ],
        $atts,
        'mt_product_card_price'
    );

    $product = meditrendy_product_price_block_product(absint($atts['id']));

    if (!$product instanceof WC_Product) {
        return '';
    }

    $current_price = meditrendy_product_price_block_raw_price($product, 'current');
    $regular_price = meditrendy_product_price_block_raw_price($product, 'regular');
    $current_html = meditrendy_product_price_block_price_html($product, $current_price);
    $regular_html = meditrendy_product_price_block_price_html($product, $regular_price);

    if ($current_html === '') {
        return '';
    }

    $has_numeric_sale = $regular_price !== ''
        && is_numeric($regular_price)
        && is_numeric($current_price)
        && (float) $regular_price > (float) $current_price;
    $sale_html_parts = meditrendy_product_price_block_sale_html_parts($product);
    $is_sale = $has_numeric_sale || ($product->is_on_sale() && $sale_html_parts['regular'] !== '');

    if ($is_sale && $regular_html === '' && $sale_html_parts['regular'] !== '') {
        $regular_html = $sale_html_parts['regular'];
    }

    if ($is_sale && $sale_html_parts['current'] !== '') {
        $current_html = $sale_html_parts['current'];
    }

    $omnibus_html = '';

    if ($is_sale && $atts['show_omnibus'] === '1') {
        $omnibus_html = meditrendy_product_price_block_omnibus_html($product);
        $omnibus_html = str_replace('mt-pdp-price-omnibus', 'mt-product-card-price-omnibus', $omnibus_html);
    }

    $classes = 'mt-product-card-price-shortcode' . ($is_sale ? ' is-sale' : '');

    ob_start();
    ?>
    <span class="<?php echo esc_attr($classes); ?>">
        <span class="mt-product-card-price-row">
            <?php if ($is_sale && $regular_html !== '') : ?>
                <del class="mt-product-card-price-regular"><?php echo wp_kses_post($regular_html); ?></del>
            <?php endif; ?>
            <span class="mt-product-card-price-current"><?php echo wp_kses_post($current_html); ?></span>
        </span>
        <?php echo wp_kses_post($omnibus_html); ?>
    </span>
    <?php

    return ob_get_clean();
}

add_shortcode('mt_product_card_price', 'meditrendy_product_card_price_shortcode');
