<?php
if (!defined('ABSPATH')) exit;

/**
 * Show the product colour in the admin order line item details.
 *
 * Colour is often a parent-product attribute rather than a variation
 * attribute. WooCommerce therefore does not save it in the order item meta,
 * so it disappears once it is removed from the product title.
 */

function meditrendy_admin_order_item_color_taxonomies() {
    return apply_filters('meditrendy_admin_order_item_color_taxonomies', [
        'pa_color',
        'pa_kolor',
        'color',
        'colour',
        'kolor',
        'spalva',
    ]);
}

function meditrendy_admin_order_item_is_color_taxonomy($taxonomy) {
    return in_array(sanitize_title((string) $taxonomy), meditrendy_admin_order_item_color_taxonomies(), true);
}

function meditrendy_admin_order_item_color_value($taxonomy, $value) {
    $taxonomy = sanitize_title((string) $taxonomy);
    $value = wc_clean((string) $value);

    if ($value === '') {
        return '';
    }

    if (taxonomy_exists($taxonomy)) {
        $term = get_term_by('slug', $value, $taxonomy);

        if ($term && !is_wp_error($term)) {
            return $term->name;
        }
    }

    return $value;
}

function meditrendy_admin_order_item_color_from_item_meta($item) {
    foreach ($item->get_meta_data() as $meta) {
        $taxonomy = sanitize_title(str_replace('attribute_', '', (string) $meta->key));

        if (!meditrendy_admin_order_item_is_color_taxonomy($taxonomy) || !is_scalar($meta->value)) {
            continue;
        }

        $value = meditrendy_admin_order_item_color_value($taxonomy, $meta->value);

        if ($value !== '') {
            return [
                'taxonomy' => $taxonomy,
                'value'    => $value,
            ];
        }
    }

    return false;
}

function meditrendy_admin_order_item_color_from_variation($product) {
    if (!$product || !is_a($product, 'WC_Product_Variation')) {
        return false;
    }

    foreach ($product->get_variation_attributes() as $attribute => $value) {
        $taxonomy = sanitize_title(str_replace('attribute_', '', (string) $attribute));

        if (!meditrendy_admin_order_item_is_color_taxonomy($taxonomy)) {
            continue;
        }

        $value = meditrendy_admin_order_item_color_value($taxonomy, $value);

        if ($value !== '') {
            return [
                'taxonomy' => $taxonomy,
                'value'    => $value,
            ];
        }
    }

    return false;
}

function meditrendy_admin_order_item_color_from_parent_product($item, $variation_product = null) {
    $product_ids = array_filter([
        $item->get_product_id(),
        $variation_product && $variation_product->is_type('variation') ? $variation_product->get_parent_id() : 0,
    ]);

    foreach (array_unique(array_map('absint', $product_ids)) as $product_id) {
        $product = wc_get_product($product_id);

        if (!$product) {
            continue;
        }

        foreach ($product->get_attributes() as $attribute_key => $attribute) {
            $name = is_a($attribute, 'WC_Product_Attribute') ? $attribute->get_name() : $attribute_key;

            if (!meditrendy_admin_order_item_is_color_taxonomy($name)) {
                continue;
            }

            if (is_a($attribute, 'WC_Product_Attribute') && $attribute->is_taxonomy()) {
                $values = wc_get_product_terms($product_id, $name, ['fields' => 'names']);
            } else {
                $values = is_a($attribute, 'WC_Product_Attribute') ? $attribute->get_options() : (array) $attribute;
            }

            // A product with multiple colours cannot identify the purchased
            // colour without variation/order-item data, so do not guess.
            if (is_wp_error($values) || count($values) !== 1 || $values[0] === '') {
                continue;
            }

            return [
                'taxonomy' => $name,
                'value'    => (string) $values[0],
            ];
        }
    }

    return false;
}

function meditrendy_admin_order_item_formatted_meta_has_color($formatted_meta) {
    foreach ($formatted_meta as $meta) {
        $taxonomy = sanitize_title(str_replace('attribute_', '', (string) ($meta->key ?? '')));

        if (meditrendy_admin_order_item_is_color_taxonomy($taxonomy)) {
            return true;
        }
    }

    return false;
}

function meditrendy_admin_order_item_get_color($item, $product = null) {
    $color = meditrendy_admin_order_item_color_from_item_meta($item);

    if (!$color) {
        $color = meditrendy_admin_order_item_color_from_variation($product);
    }

    if (!$color) {
        $color = meditrendy_admin_order_item_color_from_parent_product($item, $product);
    }

    return $color;
}

function meditrendy_admin_order_item_render_color($item_id, $item, $product) {
    if (!$item instanceof WC_Order_Item_Product) {
        return;
    }

    $formatted_meta = $item->get_all_formatted_meta_data('');

    if (meditrendy_admin_order_item_formatted_meta_has_color($formatted_meta)) {
        return;
    }

    $color = meditrendy_admin_order_item_get_color($item, $product);

    if (!$color) {
        return;
    }

    $label = wc_attribute_label($color['taxonomy'], $product);
    ?>
    <div class="view meditrendy-admin-order-item-color">
        <table cellspacing="0" class="display_meta">
            <tr>
                <th><?php echo esc_html($label); ?>:</th>
                <td><?php echo esc_html($color['value']); ?></td>
            </tr>
        </table>
    </div>
    <?php
}
add_action('woocommerce_after_order_itemmeta', 'meditrendy_admin_order_item_render_color', 20, 3);

/**
 * Return terms from the product linked to an order item. Variations inherit
 * brand and model terms from their parent product.
 */
function meditrendy_admin_order_item_terms($item, $product, $taxonomies) {
    $product_ids = array_filter([
        $item->get_product_id(),
        $product && $product->is_type('variation') ? $product->get_parent_id() : 0,
    ]);

    foreach (array_unique(array_map('absint', $product_ids)) as $product_id) {
        foreach ((array) $taxonomies as $taxonomy) {
            if (!taxonomy_exists($taxonomy)) {
                continue;
            }

            $terms = wp_get_post_terms($product_id, $taxonomy);

            if (!is_wp_error($terms) && !empty($terms)) {
                return $terms;
            }
        }
    }

    return [];
}

function meditrendy_admin_order_item_term_names($terms) {
    return array_values(array_unique(array_filter(array_map(static function ($term) {
        return $term instanceof WP_Term ? $term->name : '';
    }, (array) $terms))));
}

function meditrendy_admin_order_item_collection($item, $product) {
    $collection = meditrendy_admin_order_item_term_names(
        meditrendy_admin_order_item_terms($item, $product, 'pa_brand')
    );

    return empty($collection) ? '' : implode(' ', $collection);
}

function meditrendy_admin_order_item_render_collection($item_id, $item, $product) {
    if (!$item instanceof WC_Order_Item_Product) {
        return;
    }

    $collection = meditrendy_admin_order_item_collection($item, $product);

    if ($collection === '') {
        return;
    }
    ?>
    <div class="view meditrendy-admin-order-item-collection">
        <table cellspacing="0" class="display_meta">
            <tr>
                <th><?php echo esc_html__('Kolekcija', 'meditrendy-core'); ?>:</th>
                <td><?php echo esc_html($collection); ?></td>
            </tr>
        </table>
    </div>
    <?php
}
add_action('woocommerce_after_order_itemmeta', 'meditrendy_admin_order_item_render_collection', 19, 3);

/**
 * SKU and variation ID are internal identifiers; collection and colour give
 * the fulfilment team the useful product context instead.
 */
function meditrendy_admin_order_item_hide_internal_identifiers() {
    if (!function_exists('meditrendy_admin_order_labels_is_order_screen') || !meditrendy_admin_order_labels_is_order_screen()) {
        return;
    }
    ?>
    <style>
        .woocommerce_order_items .wc-order-item-sku,
        .woocommerce_order_items .wc-order-item-variation {
            display: none !important;
        }
    </style>
    <?php
}
add_action('admin_head', 'meditrendy_admin_order_item_hide_internal_identifiers');
