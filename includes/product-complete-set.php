<?php
if (!defined('ABSPATH')) exit;

function meditrendy_complete_set_product_matches($candidate, $product_id) {
    if (function_exists('meditrendy_waitlist_product_matches_id')) {
        return meditrendy_waitlist_product_matches_id($candidate, $product_id);
    }

    if (!$candidate || !is_a($candidate, 'WC_Product')) {
        return false;
    }

    $product_id = absint($product_id);

    return (int) $candidate->get_id() === $product_id
        || ($candidate->is_type('variation') && (int) $candidate->get_parent_id() === $product_id);
}

function meditrendy_complete_set_items($set_id) {
    if (function_exists('meditrendy_waitlist_set_items')) {
        return meditrendy_waitlist_set_items($set_id);
    }

    $set = function_exists('wc_get_product') ? wc_get_product($set_id) : null;

    if (!$set || !$set->is_type('woosb') || !method_exists($set, 'get_items')) {
        return [];
    }

    return (array) $set->get_items();
}

function meditrendy_complete_set_find_match($product_id) {
    $product_id = absint($product_id);

    if (!$product_id || !function_exists('wc_get_product')) {
        return null;
    }

    $cache_key = 'mt_complete_set_' . $product_id;
    $cached = get_transient($cache_key);

    if ($cached !== false) {
        return is_array($cached) && !empty($cached['product_id']) ? $cached : null;
    }

    $sets = new WP_Query([
        'post_type'              => 'product',
        'posts_per_page'         => 160,
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

    foreach ($sets->posts as $set_id) {
        $contains_product = false;
        $fallback_product = null;

        foreach (meditrendy_complete_set_items($set_id) as $item) {
            $item_product = !empty($item['id']) ? wc_get_product(absint($item['id'])) : null;

            if (!$item_product) {
                continue;
            }

            if (meditrendy_complete_set_product_matches($item_product, $product_id)) {
                $contains_product = true;
                continue;
            }

            if (!$fallback_product) {
                $fallback_product = $item_product;
            }
        }

        if ($contains_product && $fallback_product) {
            $match = [
                'set_id'     => absint($set_id),
                'product_id' => absint($fallback_product->is_type('variation') ? $fallback_product->get_parent_id() : $fallback_product->get_id()),
            ];

            set_transient($cache_key, $match, 6 * HOUR_IN_SECONDS);

            return $match;
        }
    }

    set_transient($cache_key, [], 6 * HOUR_IN_SECONDS);

    return null;
}

function meditrendy_complete_set_clear_cache($product_id) {
    global $wpdb;

    delete_transient('mt_complete_set_' . absint($product_id));

    if (!$wpdb) {
        return;
    }

    $transients = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like('_transient_mt_complete_set_') . '%'
        )
    );

    foreach ($transients as $transient) {
        delete_transient(str_replace('_transient_', '', $transient));
    }
}

function meditrendy_complete_set_color_name($product_id) {
    $terms = wc_get_product_terms($product_id, 'pa_color', ['fields' => 'names']);

    if (is_wp_error($terms) || empty($terms)) {
        return '';
    }

    return (string) reset($terms);
}

function meditrendy_complete_set_render_variation_form($product) {
    if (!$product || !is_a($product, 'WC_Product')) {
        return '';
    }

    if (!$product->is_type('variable')) {
        ob_start();
        ?>
        <form class="cart mt-complete-set-form" action="<?php echo esc_url($product->get_permalink()); ?>" method="post" enctype="multipart/form-data">
            <button type="submit" name="add-to-cart" value="<?php echo esc_attr($product->get_id()); ?>" class="single_add_to_cart_button button alt mt-complete-set-button">
                <?php if ($product->get_price_html()) : ?>
                    <span class="mt-complete-set-button-price"><?php echo wp_kses_post($product->get_price_html()); ?></span>
                <?php endif; ?>
                <span><?php echo esc_html__('Į krepšelį', 'meditrendy-core'); ?></span>
            </button>
        </form>
        <?php
        return ob_get_clean();
    }

    $attributes = $product->get_variation_attributes();
    $available_variations = $product->get_available_variations();
    $variations_json = wp_json_encode($available_variations);

    if (empty($available_variations) || !$variations_json) {
        return '';
    }

    ob_start();
    ?>
    <form class="variations_form cart mt-complete-set-form" action="<?php echo esc_url($product->get_permalink()); ?>" method="post" enctype="multipart/form-data" data-product_id="<?php echo esc_attr($product->get_id()); ?>" data-product_variations="<?php echo wc_esc_json($variations_json); ?>">
        <table class="variations" cellspacing="0" role="presentation">
            <tbody>
                <?php foreach ($attributes as $attribute_name => $options) : ?>
                    <?php $label = wc_attribute_label($attribute_name); ?>
                    <tr>
                        <th class="label">
                            <label for="<?php echo esc_attr(sanitize_title($attribute_name) . '-complete-set'); ?>"><?php echo esc_html($label); ?></label>
                        </th>
                        <td class="value">
                            <?php
                            wc_dropdown_variation_attribute_options([
                                'options'          => $options,
                                'attribute'        => $attribute_name,
                                'product'          => $product,
                                'id'               => sanitize_title($attribute_name) . '-complete-set',
                                'show_option_none' => sprintf(
                                    esc_html__('Pasirinkite %s', 'meditrendy-core'),
                                    function_exists('mb_strtolower') ? mb_strtolower($label) : strtolower($label)
                                ),
                            ]);
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="single_variation_wrap">
            <div class="woocommerce-variation single_variation"></div>
            <div class="woocommerce-variation-add-to-cart variations_button">
                <input type="hidden" name="add-to-cart" value="<?php echo esc_attr($product->get_id()); ?>">
                <input type="hidden" name="product_id" value="<?php echo esc_attr($product->get_id()); ?>">
                <input type="hidden" name="variation_id" class="variation_id" value="0">
                <button type="submit" class="single_add_to_cart_button button alt mt-complete-set-button disabled wc-variation-selection-needed">
                    <?php if ($product->get_price_html()) : ?>
                        <span class="mt-complete-set-button-price"><?php echo wp_kses_post($product->get_price_html()); ?></span>
                    <?php endif; ?>
                    <span><?php echo esc_html__('Į krepšelį', 'meditrendy-core'); ?></span>
                </button>
            </div>
        </div>
    </form>
    <?php

    return ob_get_clean();
}

function meditrendy_complete_set_shortcode() {
    if (!function_exists('is_product') || !is_product() || !function_exists('wc_get_product')) {
        return '';
    }

    global $product;

    if (!$product || !is_a($product, 'WC_Product') || $product->is_type('woosb')) {
        return '';
    }

    $match = meditrendy_complete_set_find_match($product->get_id());

    if (!$match || empty($match['product_id'])) {
        return '';
    }

    $matched_product = wc_get_product(absint($match['product_id']));

    if (!$matched_product || $matched_product->get_status() !== 'publish' || !$matched_product->is_purchasable()) {
        return '';
    }

    wp_enqueue_script('wc-add-to-cart-variation');

    $image = $matched_product->get_image('woocommerce_thumbnail', ['class' => 'mt-complete-set-image']);
    $color = meditrendy_complete_set_color_name($matched_product->get_id());
    $form = meditrendy_complete_set_render_variation_form($matched_product);

    if (!$form) {
        return '';
    }

    ob_start();
    ?>
    <section class="mt-complete-set" aria-labelledby="mt-complete-set-title">
        <h2 id="mt-complete-set-title" class="mt-complete-set-title"><?php echo esc_html__('Užbaikite komplektą', 'meditrendy-core'); ?></h2>
        <div class="mt-complete-set-product">
            <a class="mt-complete-set-media" href="<?php echo esc_url($matched_product->get_permalink()); ?>">
                <?php echo wp_kses_post($image); ?>
            </a>
            <div class="mt-complete-set-details">
                <a class="mt-complete-set-name" href="<?php echo esc_url($matched_product->get_permalink()); ?>">
                    <?php echo esc_html($matched_product->get_name()); ?>
                </a>
                <?php if ($color) : ?>
                    <div class="mt-complete-set-color"><?php echo esc_html($color); ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php echo $form; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </section>
    <?php

    return ob_get_clean();
}

add_action('save_post_product', 'meditrendy_complete_set_clear_cache');
add_shortcode('meditrendy_complete_set', 'meditrendy_complete_set_shortcode');
