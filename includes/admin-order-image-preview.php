<?php
if (!defined('ABSPATH')) exit;

/**
 * Let fulfilment staff inspect a larger product image from an order line item.
 */

function meditrendy_admin_order_image_preview_is_order_screen() {
    if (!is_admin()) {
        return false;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    $screen_id = $screen ? (string) $screen->id : '';

    return in_array($screen_id, ['shop_order', 'woocommerce_page_wc-orders'], true);
}

function meditrendy_admin_order_image_preview_thumbnail($thumbnail, $item_id, $item) {
    if (!is_admin() || !$item instanceof WC_Order_Item_Product) {
        return $thumbnail;
    }

    $product = $item->get_product();

    if (!$product) {
        return $thumbnail;
    }

    $image_id = $product->get_image_id();
    $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'full') : '';

    if (!$image_url) {
        return $thumbnail;
    }

    $product_name = $item->get_name();
    $label = sprintf(
        /* translators: %s: product name. */
        __('Padidinti produkto „%s“ nuotrauką', 'meditrendy-core'),
        $product_name
    );

    return sprintf(
        '<a href="%1$s" class="meditrendy-order-item-image-preview" data-meditrendy-order-image-preview data-image-title="%2$s" aria-label="%3$s">%4$s</a>',
        esc_url($image_url),
        esc_attr($product_name),
        esc_attr($label),
        $thumbnail
    );
}
add_filter('woocommerce_admin_order_item_thumbnail', 'meditrendy_admin_order_image_preview_thumbnail', 20, 3);

function meditrendy_admin_order_image_preview_order_id() {
    if (!empty($_GET['post'])) {
        return absint($_GET['post']);
    }

    if (!empty($_GET['id'])) {
        return absint($_GET['id']);
    }

    return 0;
}

function meditrendy_admin_order_image_preview_data() {
    if (!function_exists('wc_get_order')) {
        return [];
    }

    $order_id = meditrendy_admin_order_image_preview_order_id();
    $order = $order_id ? wc_get_order($order_id) : false;

    if (!$order instanceof WC_Order) {
        return [];
    }

    $data = [];

    foreach ($order->get_items('line_item') as $item_id => $item) {
        $product = $item->get_product();

        if (!$product) {
            continue;
        }

        $image_id = $product->get_image_id();

        if (!$image_id && $product->is_type('variation')) {
            $parent = wc_get_product($product->get_parent_id());
            $image_id = $parent ? $parent->get_image_id() : 0;
        }

        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'full') : '';

        if (!$image_url) {
            continue;
        }

        $product_name = wp_strip_all_tags($item->get_name());
        $data[(string) $item_id] = [
            'url'   => esc_url_raw($image_url),
            'title' => $product_name,
            'label' => sprintf(
                /* translators: %s: product name. */
                __('Padidinti produkto „%s“ nuotrauką', 'meditrendy-core'),
                $product_name
            ),
        ];
    }

    return $data;
}

function meditrendy_admin_order_image_preview_assets() {
    if (!meditrendy_admin_order_image_preview_is_order_screen()) {
        return;
    }

    $css_path = MEDITRENDY_CORE_DIR . 'assets/css/admin-order-image-preview.css';
    $js_path = MEDITRENDY_CORE_DIR . 'assets/js/admin-order-image-preview.js';

    wp_enqueue_style(
        'meditrendy-admin-order-image-preview',
        MEDITRENDY_CORE_URL . 'assets/css/admin-order-image-preview.css',
        [],
        file_exists($css_path) ? (string) filemtime($css_path) : '1.0'
    );

    wp_enqueue_script(
        'meditrendy-admin-order-image-preview',
        MEDITRENDY_CORE_URL . 'assets/js/admin-order-image-preview.js',
        [],
        file_exists($js_path) ? (string) filemtime($js_path) : '1.0',
        true
    );

    wp_add_inline_script(
        'meditrendy-admin-order-image-preview',
        'window.meditrendyOrderImagePreview = ' . wp_json_encode([
            'items' => meditrendy_admin_order_image_preview_data(),
        ]) . ';',
        'before'
    );
}
add_action('admin_enqueue_scripts', 'meditrendy_admin_order_image_preview_assets');

function meditrendy_admin_order_image_preview_modal() {
    if (!meditrendy_admin_order_image_preview_is_order_screen()) {
        return;
    }
    ?>
    <div
        class="meditrendy-order-image-modal"
        data-meditrendy-order-image-modal
        role="dialog"
        aria-modal="true"
        aria-labelledby="meditrendy-order-image-modal-title"
        hidden
    >
        <button
            type="button"
            class="meditrendy-order-image-modal__backdrop"
            data-meditrendy-order-image-close
            aria-label="<?php esc_attr_e('Uždaryti peržiūrą', 'meditrendy-core'); ?>"
        ></button>
        <div class="meditrendy-order-image-modal__panel">
            <h2 id="meditrendy-order-image-modal-title" class="screen-reader-text">
                <?php esc_html_e('Produkto nuotraukos peržiūra', 'meditrendy-core'); ?>
            </h2>
            <button
                type="button"
                class="meditrendy-order-image-modal__close"
                data-meditrendy-order-image-close
                aria-label="<?php esc_attr_e('Uždaryti peržiūrą', 'meditrendy-core'); ?>"
            >
                <span aria-hidden="true">&times;</span>
            </button>
            <img class="meditrendy-order-image-modal__image" src="" alt="">
            <p class="meditrendy-order-image-modal__caption"></p>
        </div>
    </div>
    <?php
}
add_action('admin_footer', 'meditrendy_admin_order_image_preview_modal', 30);
