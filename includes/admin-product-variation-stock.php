<?php
/** On-demand variation inventory in the Products list. */
if (!defined('ABSPATH')) {
    exit;
}

function meditrendy_variation_stock_action($actions, $post) {
    if ($post->post_type !== 'product' || !function_exists('wc_get_product') || !current_user_can('edit_post', $post->ID)) {
        return $actions;
    }
    $product = wc_get_product($post->ID);
    if ($product && $product->is_type('variable')) {
        $actions['meditrendy_variations'] = '<a href="#meditrendy-variations-' . absint($post->ID) . '" class="meditrendy-variation-stock-toggle" data-product-id="' . absint($post->ID) . '" aria-expanded="false" aria-controls="meditrendy-variations-' . absint($post->ID) . '">' . esc_html__('Warianty', 'meditrendy-core') . '</a>';
    }
    return $actions;
}
add_filter('post_row_actions', 'meditrendy_variation_stock_action', 30, 2);

function meditrendy_variation_stock_assets($hook) {
    $screen = get_current_screen();
    if ($hook !== 'edit.php' || !$screen || $screen->post_type !== 'product') {
        return;
    }
    foreach (['css' => 'style', 'js' => 'script'] as $extension => $type) {
        $path = 'assets/' . $extension . '/admin-product-variation-stock.' . $extension;
        if ($type === 'style') {
            wp_enqueue_style('meditrendy-variation-stock', MEDITRENDY_CORE_URL . $path, [], filemtime(MEDITRENDY_CORE_DIR . $path));
        } else {
            wp_enqueue_script('meditrendy-variation-stock', MEDITRENDY_CORE_URL . $path, [], filemtime(MEDITRENDY_CORE_DIR . $path), true);
        }
    }
    wp_localize_script('meditrendy-variation-stock', 'meditrendyVariationStock', [
        'url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('meditrendy_variation_stock'),
        'loading' => __('Ładowanie wariantów…', 'meditrendy-core'),
        'error' => __('Nie udało się pobrać wariantów. Kliknij Warianty, aby spróbować ponownie.', 'meditrendy-core'),
    ]);
}
add_action('admin_enqueue_scripts', 'meditrendy_variation_stock_assets');

function meditrendy_variation_stock_ajax() {
    check_ajax_referer('meditrendy_variation_stock', 'nonce');
    $id = isset($_POST['product_id']) && is_scalar($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    if (!current_user_can('edit_products') || !current_user_can('edit_post', $id)) {
        wp_send_json_error([], 403);
    }
    $product = function_exists('wc_get_product') ? wc_get_product($id) : false;
    if (!$product || !$product->is_type('variable')) {
        wp_send_json_error([], 400);
    }
    ob_start();
    echo '<div class="meditrendy-variation-stock-panel"><table><caption>' . esc_html(sprintf(__('Warianty: %s', 'meditrendy-core'), $product->get_name())) . '</caption><thead><tr>';
    foreach ([__('Wariant', 'meditrendy-core'), __('SKU', 'meditrendy-core'), __('Stan magazynowy', 'meditrendy-core'), __('Ilość', 'meditrendy-core'), __('Zarządzanie zapasem', 'meditrendy-core')] as $label) {
        echo '<th scope="col">' . esc_html($label) . '</th>';
    }
    echo '</tr></thead><tbody>';
    $count = 0;
    $statuses = wc_get_product_stock_status_options();
    foreach ($product->get_children() as $variation_id) {
        $variation = wc_get_product($variation_id);
        if (!$variation instanceof WC_Product_Variation || !current_user_can('edit_post', $variation_id)) {
            continue;
        }
        $count++;
        $attributes = wc_get_formatted_variation($variation, true, true, false);
        foreach ($variation->get_attributes() as $attribute => $value) {
            if ($value === '') {
                $attributes .= ($attributes ? ', ' : '') . sprintf(__('Dowolny: %s', 'meditrendy-core'), wc_attribute_label($attribute, $variation));
            }
        }
        $owner_id = $variation->get_stock_managed_by_id();
        $owner = $owner_id === $id ? $product : $variation;
        $quantity = $owner->managing_stock() ? $owner->get_stock_quantity() : null;
        $management = !$owner->managing_stock() ? __('Bez śledzenia ilości', 'meditrendy-core') : ($owner_id === $id ? __('Wspólny zapas produktu', 'meditrendy-core') : __('Zapas wariantu', 'meditrendy-core'));
        $status = $variation->get_stock_status();
        echo '<tr><td>' . esc_html($attributes ?: sprintf(__('Wariant #%d', 'meditrendy-core'), $variation_id)) . ' <small>#' . absint($variation_id) . '</small>';
        if ($variation->get_status() !== 'publish') {
            echo ' <small>(' . esc_html__('Wyłączony', 'meditrendy-core') . ')</small>';
        }
        echo '</td><td>' . esc_html($variation->get_sku() ?: '—') . '</td><td>' . esc_html($statuses[$status] ?? $status) . '</td><td>' . esc_html($quantity === null ? '—' : wc_format_localized_decimal($quantity)) . '</td><td>' . esc_html($management) . '</td></tr>';
    }
    if (!$count) {
        echo '<tr><td colspan="5">' . esc_html__('Brak wariantów.', 'meditrendy-core') . '</td></tr>';
    }
    echo '</tbody></table></div>';
    wp_send_json_success(['html' => ob_get_clean()]);
}
add_action('wp_ajax_meditrendy_variation_stock', 'meditrendy_variation_stock_ajax');
