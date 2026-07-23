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

/**
 * Return the featured image and, when available, the first gallery image for a
 * product card. Keeping this to two images makes catalogue pages predictable
 * and keeps their image payload small.
 */
function meditrendy_product_card_gallery_image_ids($product) {
    if (!$product instanceof WC_Product) {
        return [];
    }

    $gallery_product = $product;

    if ($product->is_type('variation')) {
        $parent = wc_get_product($product->get_parent_id());

        if ($parent instanceof WC_Product) {
            $gallery_product = $parent;
        }
    }

    $image_ids = array_merge(
        [meditrendy_product_card_image_id($product)],
        $gallery_product->get_gallery_image_ids()
    );

    $image_ids = array_values(array_unique(array_filter(array_map('absint', $image_ids))));

    return array_slice($image_ids, 0, 2);
}

function meditrendy_product_card_gallery_image_alt($image_id, $product) {
    $alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);

    return $alt ?: ($product instanceof WC_Product ? $product->get_name() : '');
}

function meditrendy_product_card_gallery_html($product) {
    if (!$product instanceof WC_Product) {
        return '';
    }

    $image_ids = meditrendy_product_card_gallery_image_ids($product);
    $url = $product->get_permalink();

    if (!$image_ids) {
        $image_url = meditrendy_product_card_image_url($product);
        $image_alt = meditrendy_product_card_image_alt($product);

        return sprintf(
            '<a class="%1$s" data-x-effect="{&quot;durationBase&quot;:&quot;300ms&quot;}" href="%2$s"><img src="%3$s" alt="%4$s" loading="lazy"></a>',
            esc_attr(meditrendy_product_card_classes('link')),
            esc_url($url),
            esc_url($image_url),
            esc_attr($image_alt)
        );
    }

    $has_multiple_images = count($image_ids) > 1;

    ob_start();
    ?>
    <div class="mt-product-card-gallery" data-mt-product-card-gallery>
        <div class="mt-product-card-gallery__stage">
            <div class="mt-product-card-gallery__viewport" data-mt-product-card-gallery-viewport>
                <div class="mt-product-card-gallery__track">
                    <?php foreach ($image_ids as $index => $image_id) : ?>
                        <a class="<?php echo esc_attr(meditrendy_product_card_classes('link')); ?> mt-product-card-gallery__slide" data-x-effect="{&quot;durationBase&quot;:&quot;300ms&quot;}" href="<?php echo esc_url($url); ?>">
                            <?php
                            echo wp_kses_post(
                                wp_get_attachment_image(
                                    $image_id,
                                    'full',
                                    false,
                                    [
                                        'alt' => meditrendy_product_card_gallery_image_alt($image_id, $product),
                                        'loading' => 'lazy',
                                    ]
                                )
                            );
                            ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php if ($has_multiple_images) : ?>
                <button class="mt-product-card-gallery__arrow mt-product-card-gallery__arrow--previous" type="button" data-mt-product-card-gallery-direction="previous" aria-label="<?php echo esc_attr__('Ankstesnė produkto nuotrauka', 'meditrendy-core'); ?>">
                    <span aria-hidden="true">&#8249;</span>
                </button>
                <button class="mt-product-card-gallery__arrow mt-product-card-gallery__arrow--next" type="button" data-mt-product-card-gallery-direction="next" aria-label="<?php echo esc_attr__('Kita produkto nuotrauka', 'meditrendy-core'); ?>">
                    <span aria-hidden="true">&#8250;</span>
                </button>
            <?php endif; ?>
        </div>
        <?php if ($has_multiple_images) : ?>
            <div class="mt-product-card-gallery__dots" aria-label="<?php echo esc_attr__('Produkto nuotraukos', 'meditrendy-core'); ?>">
                <?php foreach ($image_ids as $index => $image_id) : ?>
                    <button class="mt-product-card-gallery__dot<?php echo $index === 0 ? ' is-active' : ''; ?>" type="button" data-mt-product-card-gallery-slide="<?php echo esc_attr($index); ?>" aria-label="<?php echo esc_attr(sprintf(__('Rodyti %d produkto nuotrauką', 'meditrendy-core'), $index + 1)); ?>" aria-current="<?php echo $index === 0 ? 'true' : 'false'; ?>"></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php

    return ob_get_clean();
}

function meditrendy_product_card_gallery_shortcode($atts = []) {
    $atts = shortcode_atts(
        [
            'id' => '',
        ],
        $atts,
        'mt_product_card_gallery'
    );

    $product_id_value = (string) $atts['id'];

    // Cornerstone evaluates shortcodes before its dynamic-content pass. Resolve
    // an ID tag here so a product Looper can supply the current product.
    if ($product_id_value !== '' && function_exists('cs_dynamic_content')) {
        $product_id_value = cs_dynamic_content($product_id_value);
    }

    $product_id = absint($product_id_value);

    if (!$product_id && get_post_type(get_the_ID()) === 'product') {
        $product_id = get_the_ID();
    }

    return meditrendy_product_card_gallery_html(wc_get_product($product_id));
}

add_shortcode('mt_product_card_gallery', 'meditrendy_product_card_gallery_shortcode');

/**
 * Load the small gallery assets only on product listings or pages that contain
 * one of the product-card shortcodes.
 */
function meditrendy_product_card_gallery_should_enqueue_assets() {
    if (is_admin()) {
        return false;
    }

    $is_product_archive =
        (function_exists('is_shop') && is_shop())
        || (function_exists('is_product_category') && is_product_category())
        || (function_exists('is_product_tag') && is_product_tag())
        || is_post_type_archive('product');

    if ($is_product_archive) {
        return true;
    }

    global $post;

    if (!$post instanceof WP_Post) {
        return false;
    }

    foreach (['mt_product_card_gallery', 'meditrendy_product_filters', 'meditrendy_brand_products'] as $shortcode) {
        if (has_shortcode($post->post_content, $shortcode)) {
            return true;
        }
    }

    return false;
}

function meditrendy_enqueue_product_card_gallery_assets() {
    if (!apply_filters('meditrendy_product_card_gallery_enqueue_assets', meditrendy_product_card_gallery_should_enqueue_assets())) {
        return;
    }

    $css_path = MEDITRENDY_CORE_DIR . 'assets/css/product-card-gallery.css';
    $js_path = MEDITRENDY_CORE_DIR . 'assets/js/product-card-gallery.js';

    if (file_exists($css_path)) {
        wp_enqueue_style(
            'meditrendy-product-card-gallery',
            MEDITRENDY_CORE_URL . 'assets/css/product-card-gallery.css',
            [],
            filemtime($css_path)
        );
    }

    if (file_exists($js_path)) {
        wp_enqueue_script(
            'meditrendy-product-card-gallery',
            MEDITRENDY_CORE_URL . 'assets/js/product-card-gallery.js',
            [],
            filemtime($js_path),
            true
        );
    }
}

add_action('wp_enqueue_scripts', 'meditrendy_enqueue_product_card_gallery_assets', 20);

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

function meditrendy_product_card_listing_color_swatches_html($product) {
    if (!$product || !function_exists('meditrendy_listing_color_swatches_shortcode')) {
        return '';
    }

    return meditrendy_listing_color_swatches_shortcode([
        'product_id' => absint($product->get_id()),
    ]);
}

function meditrendy_render_product_card($product) {
    if (!$product) {
        return '';
    }

    $url = $product->get_permalink();
    $price_html = meditrendy_product_card_price_html($product);
    $badges_html = meditrendy_product_card_badges_shortcode_html($product);
    $brand_html = function_exists('meditrendy_product_brand_html') ? meditrendy_product_brand_html($product) : '';
    $color_swatches_html = meditrendy_product_card_listing_color_swatches_html($product);

    ob_start();
    ?>
    <div class="<?php echo esc_attr(meditrendy_product_card_classes('card')); ?>">
        <div class="<?php echo esc_attr(meditrendy_product_card_classes('media')); ?>">
            <div class="x-bg" aria-hidden="true">
                <div class="x-bg-layer-upper-custom"></div>
            </div>
            <?php echo wp_kses_post($badges_html); ?>
            <?php echo meditrendy_product_card_gallery_html($product); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
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
        <?php echo $color_swatches_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
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
