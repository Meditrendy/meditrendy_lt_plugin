<?php
/** Inline regular and sale price editing on the WooCommerce Products screen. */

if (!defined('ABSPATH')) {
    exit;
}

/** Keep WooCommerce's price column position and sorting while supplying our own renderer. */
function meditrendy_inline_price_column($columns) {
    if (!isset($columns['price'])) {
        return $columns;
    }

    $replacement = [];
    foreach ($columns as $key => $label) {
        $replacement[$key === 'price' ? 'meditrendy_inline_price' : $key] = $label;
    }
    return $replacement;
}
add_filter('manage_edit-product_columns', 'meditrendy_inline_price_column', 30);

function meditrendy_inline_price_sortable_column($columns) {
    $columns['meditrendy_inline_price'] = 'price';
    unset($columns['price']);
    return $columns;
}
add_filter('manage_edit-product_sortable_columns', 'meditrendy_inline_price_sortable_column', 30);

/** WooCommerce attaches its standard edit fields to the original price column key. */
function meditrendy_inline_price_preserve_edit_fields($column, $post_type) {
    if ($column === 'meditrendy_inline_price' && $post_type === 'product') {
        do_action(current_filter(), 'price', $post_type);
    }
}
add_action('quick_edit_custom_box', 'meditrendy_inline_price_preserve_edit_fields', 20, 2);
add_action('bulk_edit_custom_box', 'meditrendy_inline_price_preserve_edit_fields', 20, 2);

/**
 * Return one editable price pair, or null when a product has differing variation prices.
 * Checking every child avoids overwriting hidden or out-of-stock variations unnoticed.
 */
function meditrendy_inline_price_values($product) {
    if (!$product instanceof WC_Product) {
        return null;
    }

    if ($product->is_type('simple') || ($product->is_type('woosb') && method_exists($product, 'is_fixed_price') && $product->is_fixed_price())) {
        return [
            'regular' => (string) $product->get_regular_price('edit'),
            'sale'    => (string) $product->get_sale_price('edit'),
        ];
    }

    if (!$product->is_type('variable')) {
        return null;
    }

    $values = null;
    $current_price = null;
    $comparable = static function($value) {
        return $value === '' ? '' : wc_format_decimal($value, wc_get_price_decimals());
    };
    foreach ($product->get_children() as $variation_id) {
        $variation = wc_get_product($variation_id);
        if (!$variation instanceof WC_Product_Variation) {
            return null;
        }

        $candidate = [
            'regular' => (string) $variation->get_regular_price('edit'),
            'sale'    => (string) $variation->get_sale_price('edit'),
        ];
        $candidate_price = (string) $variation->get_price('edit');
        if ($values !== null && (
            $comparable($values['regular']) !== $comparable($candidate['regular'])
            || $comparable($values['sale']) !== $comparable($candidate['sale'])
            || $comparable($current_price) !== $comparable($candidate_price)
        )) {
            return null;
        }
        $values = $candidate;
        $current_price = $candidate_price;
    }

    return $values;
}

function meditrendy_render_inline_price_column($column, $product_id) {
    if ($column !== 'meditrendy_inline_price' || !function_exists('wc_get_product')) {
        return;
    }

    $product = wc_get_product($product_id);
    if (!$product) {
        return;
    }

    $html = $product->get_price_html();
    $display = $html ? wp_kses_post($html) : '<span class="na">&ndash;</span>';
    $values = meditrendy_inline_price_values($product);
    if ($values === null || !current_user_can('edit_post', $product_id)) {
        echo $display;
        return;
    }

    printf(
        '<button type="button" class="meditrendy-inline-price" data-product-id="%d" data-regular="%s" data-sale="%s" aria-label="%s" title="%s"><span class="meditrendy-inline-price-display">%s</span></button>',
        (int) $product_id,
        esc_attr($values['regular']),
        esc_attr($values['sale']),
        esc_attr__('Edit regular and sale prices', 'meditrendy-core'),
        esc_attr__('Click to edit regular and sale prices', 'meditrendy-core'),
        $display
    );
}
add_action('manage_product_posts_custom_column', 'meditrendy_render_inline_price_column', 20, 2);

function meditrendy_inline_price_assets($hook_suffix) {
    if ($hook_suffix !== 'edit.php' || !function_exists('wc_get_product')) {
        return;
    }
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'edit-product') {
        return;
    }

    wp_enqueue_style('meditrendy-inline-price', MEDITRENDY_CORE_URL . 'assets/css/admin-product-inline-price.css', [], filemtime(MEDITRENDY_CORE_DIR . 'assets/css/admin-product-inline-price.css'));
    wp_enqueue_script('meditrendy-inline-price', MEDITRENDY_CORE_URL . 'assets/js/admin-product-inline-price.js', [], filemtime(MEDITRENDY_CORE_DIR . 'assets/js/admin-product-inline-price.js'), true);
    wp_localize_script('meditrendy-inline-price', 'meditrendyInlinePrice', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('meditrendy_inline_price'),
        'regular' => __('Regular price', 'meditrendy-core'),
        'sale' => __('Sale price', 'meditrendy-core'),
        'save' => __('Save', 'meditrendy-core'),
        'cancel' => __('Cancel', 'meditrendy-core'),
        'saving' => __('Saving…', 'meditrendy-core'),
        'error' => __('Could not save the price. Please try again.', 'meditrendy-core'),
        'decimalSeparator' => wc_get_price_decimal_separator(),
    ]);
}
add_action('admin_enqueue_scripts', 'meditrendy_inline_price_assets');

/** Reject malformed prices rather than letting WooCommerce silently clean them. */
function meditrendy_inline_price_parse($raw, $allow_empty) {
    $raw = trim((string) wp_unslash($raw));
    if ($raw === '' && $allow_empty) {
        return '';
    }
    $decimals = wc_get_price_decimals();
    $pattern = $decimals > 0
        ? '/^\d+(?:[.,]\d{1,' . $decimals . '})?$/D'
        : '/^\d+$/D';
    if (!preg_match($pattern, $raw)) {
        return null;
    }
    return wc_format_decimal(str_replace(',', '.', $raw));
}

function meditrendy_ajax_save_inline_price() {
    check_ajax_referer('meditrendy_inline_price', 'nonce');

    $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    if (!$product_id || get_post_type($product_id) !== 'product' || !current_user_can('edit_post', $product_id)) {
        wp_send_json_error(['message' => __('You cannot edit this product.', 'meditrendy-core')], 403);
    }

    $product = wc_get_product($product_id);
    $previous = meditrendy_inline_price_values($product);
    if ($previous === null) {
        wp_send_json_error(['message' => __('This product no longer has one shared price across all variations. Refresh the list.', 'meditrendy-core')], 409);
    }

    $regular = meditrendy_inline_price_parse(isset($_POST['regular']) ? $_POST['regular'] : '', false);
    $sale = meditrendy_inline_price_parse(isset($_POST['sale']) ? $_POST['sale'] : '', true);
    if ($regular === null || $sale === null || ($sale !== '' && (float) $sale >= (float) $regular)) {
        wp_send_json_error(['message' => __('Enter a valid regular price and a sale price lower than it.', 'meditrendy-core')], 400);
    }

    try {
        if ($product->is_type('variable')) {
            $variations = [];
            foreach ($product->get_children() as $variation_id) {
                if (!current_user_can('edit_post', $variation_id)) {
                    wp_send_json_error(['message' => __('You cannot edit every variation of this product.', 'meditrendy-core')], 403);
                }
                $variation = wc_get_product($variation_id);
                if (!$variation instanceof WC_Product_Variation) {
                    wp_send_json_error(['message' => __('A variation could not be loaded. Refresh the list.', 'meditrendy-core')], 409);
                }
                $variations[] = $variation;
            }
            foreach ($variations as $variation) {
                $variation->set_regular_price($regular);
                $variation->set_sale_price($sale);
                $variation->save();
            }
            WC_Product_Variable::sync($product_id);
            $product = wc_get_product($product_id);
        } else {
            $product->set_regular_price($regular);
            $product->set_sale_price($sale);
            $product->save();
        }
        wc_delete_product_transients($product_id);
    } catch (Exception $error) {
        wp_send_json_error(['message' => __('Could not save the price. Please try again.', 'meditrendy-core')], 500);
    }

    wp_send_json_success([
        'regular' => (string) $regular,
        'sale' => (string) $sale,
        'html' => wp_kses_post($product->get_price_html()),
    ]);
}
add_action('wp_ajax_meditrendy_inline_price', 'meditrendy_ajax_save_inline_price');
