<?php
if (!defined('ABSPATH')) exit;

function meditrendy_product_card_classes($key) {
    $classes = [
        'grid'       => 'x-row e246-e9 m6u-5 m6u-7 m6u-8 m6u-b m6u-g product-card',
        'inner'      => 'x-row-inner',
        'card'       => 'x-col e246-e10 m6u-i m6u-j product-loop',
        'media'      => 'x-div e246-e11 m6u-n product-cart ',
        'link'       => 'x-image e246-e12 m6u-o m6u-3',
        'title_wrap' => 'x-text x-text-headline e246-e13 m6u-p m6u-q',
        'price_wrap' => 'x-text x-text-headline e246-e14 m6u-q m6u-r',
    ];

    return apply_filters('meditrendy_product_card_class', $classes[$key] ?? '', $key);
}

function meditrendy_product_card_image_id($product) {
    if (!$product) {
        return 0;
    }

    $image_id = $product->get_image_id();

    if (!$image_id && $product->is_type('variation')) {
        $parent = wc_get_product($product->get_parent_id());
        $image_id = $parent ? $parent->get_image_id() : 0;
    }

    return (int) $image_id;
}

function meditrendy_product_card_image_url($product) {
    $image_id = meditrendy_product_card_image_id($product);

    if ($image_id) {
        return wp_get_attachment_image_url($image_id, 'full');
    }

    return function_exists('wc_placeholder_img_src') ? wc_placeholder_img_src('full') : '';
}

function meditrendy_product_card_image_alt($product) {
    $image_id = meditrendy_product_card_image_id($product);
    $alt = $image_id ? get_post_meta($image_id, '_wp_attachment_image_alt', true) : '';

    return $alt ?: ($product ? $product->get_name() : '');
}

function meditrendy_product_card_price_text($product) {
    if (!$product) {
        return '';
    }

    return trim(wp_strip_all_tags($product->get_price_html()));
}

function meditrendy_product_card_price_html($product) {
    if (!$product) {
        return '';
    }

    if (shortcode_exists('mt_product_card_price')) {
        return do_shortcode('[mt_product_card_price id="' . absint($product->get_id()) . '"]');
    }

    return wp_kses_post($product->get_price_html());
}

function meditrendy_product_card_badges_shortcode_html($product) {
    if (!$product || !shortcode_exists('mt_product_card_badges')) {
        return '';
    }

    return do_shortcode('[mt_product_card_badges id="' . absint($product->get_id()) . '"]');
}

function meditrendy_render_product_card($product) {
    if (!$product) {
        return '';
    }

    $url = $product->get_permalink();
    $image_url = meditrendy_product_card_image_url($product);
    $image_alt = meditrendy_product_card_image_alt($product);
    $price_html = meditrendy_product_card_price_html($product);
    $badges_html = meditrendy_product_card_badges_shortcode_html($product);
    $brand_html = function_exists('meditrendy_product_brand_html') ? meditrendy_product_brand_html($product) : '';

    ob_start();
    ?>
    <div class="<?php echo esc_attr(meditrendy_product_card_classes('card')); ?>">
        <div class="<?php echo esc_attr(meditrendy_product_card_classes('media')); ?>">
            <div class="x-bg" aria-hidden="true">
                <div class="x-bg-layer-upper-custom"></div>
            </div>
            <?php echo wp_kses_post($badges_html); ?>
            <a class="<?php echo esc_attr(meditrendy_product_card_classes('link')); ?>" data-x-effect="{&quot;durationBase&quot;:&quot;300ms&quot;}" href="<?php echo esc_url($url); ?>">
                <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($image_alt); ?>" loading="lazy">
            </a>
        </div>
        <div class="<?php echo esc_attr(meditrendy_product_card_classes('title_wrap')); ?>">
            <div class="x-text-content">
                <div class="x-text-content-text">
                    <?php echo wp_kses_post($brand_html); ?>
                    <h3 class="x-text-content-text-primary"><?php echo esc_html($product->get_name()); ?></h3>
                </div>
            </div>
        </div>
        <div class="<?php echo esc_attr(meditrendy_product_card_classes('price_wrap')); ?>">
            <div class="x-text-content">
                <div class="x-text-content-text">
                    <span class="x-text-content-text-primary"><?php echo wp_kses_post($price_html); ?></span>
                </div>
            </div>
        </div>
    </div>
    <?php

    return ob_get_clean();
}

function meditrendy_render_product_card_grid($query, $args = []) {
    if (!$query instanceof WP_Query) {
        return '';
    }

    $args = wp_parse_args(
        $args,
        [
            'id'         => '',
            'class'      => '',
            'empty_text' => '',
        ]
    );

    $extra_classes = array_filter(array_map('sanitize_html_class', preg_split('/\s+/', (string) $args['class'])));
    $grid_classes = trim(meditrendy_product_card_classes('grid') . ' ' . implode(' ', $extra_classes));
    $id_attribute = $args['id'] !== '' ? ' id="' . esc_attr($args['id']) . '"' : '';

    $query->rewind_posts();

    ob_start();
    ?>
    <div<?php echo $id_attribute; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> class="<?php echo esc_attr($grid_classes); ?>">
        <div class="<?php echo esc_attr(meditrendy_product_card_classes('inner')); ?>">
            <?php if ($query->have_posts()) : ?>
                <?php while ($query->have_posts()) : ?>
                    <?php
                    $query->the_post();
                    echo meditrendy_render_product_card(wc_get_product(get_the_ID())); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    ?>
                <?php endwhile; ?>
            <?php elseif ($args['empty_text'] !== '') : ?>
                <div class="x-col product-loop mt-native-no-products"><?php echo esc_html($args['empty_text']); ?></div>
            <?php endif; ?>
        </div>
    </div>
    <?php

    wp_reset_postdata();

    return ob_get_clean();
}
