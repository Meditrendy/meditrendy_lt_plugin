<?php
if (!defined('ABSPATH')) exit;

function meditrendy_side_cart_count() {
    if (!function_exists('WC') || !WC()->cart) {
        return 0;
    }

    $count = 0;

    foreach (WC()->cart->get_cart() as $cart_item) {
        if (meditrendy_side_cart_is_hidden_item($cart_item)) {
            continue;
        }

        $count += (int) ($cart_item['quantity'] ?? 0);
    }

    return $count;
}

function meditrendy_side_cart_is_hidden_item($cart_item) {
    $hidden = !empty($cart_item['woosb_parent_id']);

    return (bool) apply_filters('meditrendy_side_cart_is_hidden_item', $hidden, $cart_item);
}

function meditrendy_side_cart_item_attributes($cart_item) {
    $parts = [];

    if (!empty($cart_item['variation']) && is_array($cart_item['variation'])) {
        foreach ($cart_item['variation'] as $key => $value) {
            if ($value === '') {
                continue;
            }

            $taxonomy = str_replace('attribute_', '', (string) $key);
            $label = '';

            if (taxonomy_exists($taxonomy)) {
                $term = get_term_by('slug', (string) $value, $taxonomy);
                $label = $term && !is_wp_error($term) ? $term->name : '';
            }

            $parts[] = $label ?: wc_attribute_label($taxonomy) . ': ' . wc_clean($value);
        }
    }

    return implode(' · ', array_filter(array_map('wp_strip_all_tags', $parts)));
}

function meditrendy_side_cart_item_image($product) {
    if (!$product || !is_a($product, 'WC_Product')) {
        return '';
    }

    return $product->get_image('woocommerce_thumbnail', [
        'class' => 'mt-side-cart-item-image',
        'loading' => 'lazy',
    ]);
}

function meditrendy_side_cart_item_price_html($cart_item, $cart_item_key) {
    $product = $cart_item['data'] ?? null;

    if (!$product || !is_a($product, 'WC_Product') || !function_exists('WC') || !WC()->cart) {
        return '';
    }

    $quantity = max(1, (int) ($cart_item['quantity'] ?? 1));
    $subtotal = WC()->cart->get_product_subtotal($product, $quantity);

    return apply_filters('woocommerce_cart_item_subtotal', $subtotal, $cart_item, $cart_item_key);
}

function meditrendy_side_cart_items_html() {
    if (!function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) {
        return '<div class="mt-side-cart-empty">' . esc_html__('Jūsų krepšelis tuščias.', 'meditrendy-core') . '</div>';
    }

    ob_start();
    ?>
    <div class="mt-side-cart-items">
        <?php foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) :
            $product = $cart_item['data'] ?? null;

            if (meditrendy_side_cart_is_hidden_item($cart_item)) {
                continue;
            }

            if (!$product || !is_a($product, 'WC_Product') || !$product->exists() || (int) ($cart_item['quantity'] ?? 0) < 1) {
                continue;
            }

            $product_permalink = $product->is_visible() ? $product->get_permalink($cart_item) : '';
            $quantity = (int) $cart_item['quantity'];
            $max_quantity = $product->get_max_purchase_quantity();
            $max_attribute = $max_quantity > 0 ? $max_quantity : '';
            $attributes = meditrendy_side_cart_item_attributes($cart_item);
            ?>
            <article
                class="mt-side-cart-item"
                data-cart-item-key="<?php echo esc_attr($cart_item_key); ?>"
                data-product-id="<?php echo esc_attr((int) ($cart_item['product_id'] ?? 0)); ?>"
                data-variation-id="<?php echo esc_attr((int) ($cart_item['variation_id'] ?? 0)); ?>"
            >
                <div class="mt-side-cart-item-media">
                    <?php if ($product_permalink) : ?>
                        <a href="<?php echo esc_url($product_permalink); ?>">
                            <?php echo meditrendy_side_cart_item_image($product); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </a>
                    <?php else : ?>
                        <?php echo meditrendy_side_cart_item_image($product); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endif; ?>
                </div>

                <div class="mt-side-cart-item-body">
                    <div class="mt-side-cart-item-top">
                        <h3 class="mt-side-cart-item-title">
                            <?php if ($product_permalink) : ?>
                                <a href="<?php echo esc_url($product_permalink); ?>"><?php echo esc_html($product->get_name()); ?></a>
                            <?php else : ?>
                                <?php echo esc_html($product->get_name()); ?>
                            <?php endif; ?>
                        </h3>

                        <button class="mt-side-cart-remove" type="button" data-mt-side-cart-remove aria-label="<?php esc_attr_e('Pašalinti prekę', 'meditrendy-core'); ?>">
                            <span aria-hidden="true"></span>
                        </button>
                    </div>

                    <?php if ($attributes) : ?>
                        <div class="mt-side-cart-item-meta"><?php echo esc_html($attributes); ?></div>
                    <?php endif; ?>

                    <div class="mt-side-cart-item-bottom">
                        <?php if ($product->is_sold_individually()) : ?>
                            <span class="mt-side-cart-single-qty"><?php echo esc_html($quantity); ?></span>
                        <?php else : ?>
                            <div class="mt-side-cart-quantity" data-mt-side-cart-quantity>
                                <button type="button" data-mt-side-cart-qty="-1" aria-label="<?php esc_attr_e('Sumažinti kiekį', 'meditrendy-core'); ?>">-</button>
                                <input
                                    type="number"
                                    min="1"
                                    <?php if ($max_attribute !== '') : ?>max="<?php echo esc_attr($max_attribute); ?>"<?php endif; ?>
                                    step="1"
                                    value="<?php echo esc_attr($quantity); ?>"
                                    inputmode="numeric"
                                    aria-label="<?php esc_attr_e('Kiekis', 'meditrendy-core'); ?>"
                                >
                                <button type="button" data-mt-side-cart-qty="1" aria-label="<?php esc_attr_e('Padidinti kiekį', 'meditrendy-core'); ?>">+</button>
                            </div>
                        <?php endif; ?>

                        <div class="mt-side-cart-item-price">
                            <?php echo wp_kses_post(meditrendy_side_cart_item_price_html($cart_item, $cart_item_key)); ?>
                        </div>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <?php

    return ob_get_clean();
}

function meditrendy_side_cart_totals_html() {
    if (!function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) {
        return '';
    }

    ob_start();
    ?>
    <div class="mt-side-cart-totals">
        <div class="mt-side-cart-subtotal">
            <span><?php esc_html_e('Tarpinė suma:', 'meditrendy-core'); ?></span>
            <strong><?php echo wp_kses_post(WC()->cart->get_cart_subtotal()); ?></strong>
        </div>
        <p class="mt-side-cart-tax-note"><?php esc_html_e('Mokesčiai įskaičiuoti į kainą', 'meditrendy-core'); ?></p>
    </div>
    <?php

    return ob_get_clean();
}

function meditrendy_side_cart_content_html($include_upsells = false) {
    ob_start();
    ?>
    <div class="mt-side-cart-header">
        <h2 id="mt-side-cart-title"><?php echo esc_html(sprintf(__('Krepšelis — %d', 'meditrendy-core'), meditrendy_side_cart_count())); ?></h2>
        <button class="mt-side-cart-close" type="button" data-mt-side-cart-close aria-label="<?php esc_attr_e('Uždaryti krepšelį', 'meditrendy-core'); ?>"></button>
    </div>

    <div class="mt-side-cart-content">
        <?php echo meditrendy_side_cart_items_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php if ($include_upsells && function_exists('meditrendy_side_cart_upsells_html')) : ?>
            <?php echo meditrendy_side_cart_upsells_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php endif; ?>
    </div>

    <div class="mt-side-cart-footer">
        <?php if (function_exists('WC') && WC()->cart && !WC()->cart->is_empty()) : ?>
            <a class="mt-side-cart-checkout" href="<?php echo esc_url(wc_get_checkout_url()); ?>">
                <span><?php echo wp_kses_post(WC()->cart->get_cart_subtotal()); ?></span>
                <span><?php esc_html_e('Pereiti prie apmokėjimo', 'meditrendy-core'); ?></span>
            </a>
        <?php endif; ?>
    </div>
    <?php

    return ob_get_clean();
}

function meditrendy_side_cart_response($include_upsells = false, $tracking = []) {
    $response = [
        'count' => meditrendy_side_cart_count(),
        'html'  => meditrendy_side_cart_content_html($include_upsells),
    ];

    if (!empty($tracking)) {
        $response['tracking'] = $tracking;
    }

    return $response;
}

function meditrendy_side_cart_tracking_item_attributes($cart_item) {
    $attributes = [];

    if (!empty($cart_item['variation']) && is_array($cart_item['variation'])) {
        foreach ($cart_item['variation'] as $key => $value) {
            if ($value === '') {
                continue;
            }

            $taxonomy = str_replace('attribute_', '', (string) $key);
            $label = wc_attribute_label($taxonomy);

            if (taxonomy_exists($taxonomy)) {
                $term = get_term_by('slug', (string) $value, $taxonomy);
                $value = $term && !is_wp_error($term) ? $term->name : $value;
            }

            $attributes[] = trim($label . ': ' . wp_strip_all_tags((string) $value));
        }
    }

    return implode(' / ', array_filter($attributes));
}

function meditrendy_side_cart_tracking_line_value($cart_item) {
    if (!is_array($cart_item)) {
        return 0.0;
    }

    $line_total = isset($cart_item['line_total']) ? (float) $cart_item['line_total'] : 0.0;
    $line_tax = isset($cart_item['line_tax']) ? (float) $cart_item['line_tax'] : 0.0;
    $line_subtotal = isset($cart_item['line_subtotal']) ? (float) $cart_item['line_subtotal'] : 0.0;
    $line_subtotal_tax = isset($cart_item['line_subtotal_tax']) ? (float) $cart_item['line_subtotal_tax'] : 0.0;
    $line_value = $line_total + $line_tax;

    if ($line_value <= 0 && ($line_subtotal + $line_subtotal_tax) > 0) {
        $line_value = $line_subtotal + $line_subtotal_tax;
    }

    return max(0.0, $line_value);
}

function meditrendy_side_cart_tracking_cart_value($cart_item_key, $quantity, $product) {
    if (!function_exists('WC') || !WC()->cart || empty(WC()->cart->cart_contents[$cart_item_key])) {
        return 0.0;
    }

    $cart_item = WC()->cart->cart_contents[$cart_item_key];
    $value = meditrendy_side_cart_tracking_line_value($cart_item);

    if ($value <= 0) {
        $cart_product_id = (string) ($cart_item['product_id'] ?? '');

        foreach (WC()->cart->cart_contents as $child_item) {
            $child_parent_id = (string) ($child_item['woosb_parent_id'] ?? '');

            if ($child_parent_id !== (string) $cart_item_key && $child_parent_id !== $cart_product_id) {
                continue;
            }

            $value += meditrendy_side_cart_tracking_line_value($child_item);
        }
    }

    $product_value = 0.0;

    if ($product instanceof WC_Product) {
        $product_value = (float) wc_get_price_to_display($product) * max(1, (int) $quantity);
    }

    if ($value <= 0 && $product_value > 0) {
        $value = $product_value;
    }

    if ($value > 0 && $product_value > 0 && $value > ($product_value * 1.2)) {
        $value = $product_value;
    }

    return (float) wc_format_decimal($value, wc_get_price_decimals());
}

function meditrendy_side_cart_tracking_payload($cart_item_key, $product_id, $variation_id, $quantity) {
    if (!function_exists('WC') || !WC()->cart || empty(WC()->cart->cart_contents[$cart_item_key])) {
        return [];
    }

    $cart_item = WC()->cart->cart_contents[$cart_item_key];
    $product = !empty($cart_item['data']) && is_a($cart_item['data'], 'WC_Product') ? $cart_item['data'] : wc_get_product($variation_id ?: $product_id);

    if (!$product || !is_a($product, 'WC_Product')) {
        return [];
    }

    $quantity = max(1, (int) $quantity);
    $value = meditrendy_side_cart_tracking_cart_value($cart_item_key, $quantity, $product);
    $price = $value > 0 ? (float) wc_format_decimal($value / $quantity, wc_get_price_decimals()) : (float) wc_get_price_to_display($product);
    $item_id = $product->get_sku() ?: (string) ($variation_id ?: $product_id);
    $item_name = $product->get_name();
    $item_variant = meditrendy_side_cart_tracking_item_attributes($cart_item);

    $ga4_item = [
        'item_id'   => $item_id,
        'item_name' => $item_name,
        'price'     => $price,
        'quantity'  => $quantity,
    ];

    if ($item_variant !== '') {
        $ga4_item['item_variant'] = $item_variant;
    }

    return [
        'event'    => 'add_to_cart',
        'product_id' => (int) $product_id,
        'variation_id' => (int) $variation_id,
        'quantity' => $quantity,
        'currency' => get_woocommerce_currency(),
        'value'    => $value,
        'items'    => [$ga4_item],
        'meta'     => [
            'content_ids'  => [$item_id],
            'content_name' => $item_name,
            'content_type' => 'product',
            'currency'     => get_woocommerce_currency(),
            'value'        => $value,
        ],
    ];
}

function meditrendy_side_cart_render_shell() {
    if (is_admin() || !function_exists('WC')) {
        return;
    }

    if (function_exists('meditrendy_cart_module_enabled') && !meditrendy_cart_module_enabled()) {
        return;
    }
    ?>
    <div class="mt-side-cart" data-mt-side-cart aria-hidden="true">
        <button class="mt-side-cart-backdrop" type="button" data-mt-side-cart-close tabindex="-1" aria-label="<?php esc_attr_e('Uždaryti krepšelį', 'meditrendy-core'); ?>"></button>
        <aside class="mt-side-cart-panel" role="dialog" aria-modal="true" aria-labelledby="mt-side-cart-title">
            <div class="mt-side-cart-inner" data-mt-side-cart-inner>
                <?php echo meditrendy_side_cart_content_html(false); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
        </aside>
    </div>
    <?php
}

function meditrendy_side_cart_verify_request() {
    check_ajax_referer('meditrendy_side_cart', 'nonce');

    if (!function_exists('WC') || !WC()->cart) {
        wp_send_json_error(['message' => __('Krepšelis nepasiekiamas.', 'meditrendy-core')], 400);
    }
}

function meditrendy_side_cart_existing_quantity($product_id, $variation_id = 0) {
    if (!function_exists('WC') || !WC()->cart) {
        return 0;
    }

    $quantity = 0;
    $product_id = absint($product_id);
    $variation_id = absint($variation_id);

    foreach (WC()->cart->get_cart() as $cart_item) {
        if (!empty($cart_item['variation_id']) && $variation_id > 0) {
            if ((int) $cart_item['variation_id'] === $variation_id) {
                $quantity += (int) ($cart_item['quantity'] ?? 0);
            }

            continue;
        }

        if ($variation_id === 0 && (int) ($cart_item['product_id'] ?? 0) === $product_id) {
            $quantity += (int) ($cart_item['quantity'] ?? 0);
        }
    }

    return $quantity;
}

function meditrendy_side_cart_find_cart_item_key($product_id, $variation_id = 0) {
    if (!function_exists('WC') || !WC()->cart) {
        return '';
    }

    $product_id = absint($product_id);
    $variation_id = absint($variation_id);

    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        if ($variation_id > 0 && (int) ($cart_item['variation_id'] ?? 0) === $variation_id) {
            return $cart_item_key;
        }

        if ($variation_id === 0 && (int) ($cart_item['product_id'] ?? 0) === $product_id) {
            return $cart_item_key;
        }
    }

    return '';
}

function meditrendy_side_cart_send_existing_cart_response($message = '', $product_id = 0, $variation_id = 0, $quantity = 1) {
    WC()->cart->calculate_totals();
    wc_clear_notices();

    $tracking = [];
    $cart_item_key = meditrendy_side_cart_find_cart_item_key($product_id, $variation_id);

    if ($cart_item_key !== '') {
        $tracking = meditrendy_side_cart_tracking_payload($cart_item_key, $product_id, $variation_id, $quantity);
    }

    $response = meditrendy_side_cart_response(true, $tracking);

    if ($message !== '') {
        $response['message'] = wp_strip_all_tags($message);
    }

    wp_send_json_success($response);
}

function meditrendy_side_cart_ajax_get() {
    meditrendy_side_cart_verify_request();

    if (function_exists('meditrendy_cart_module_enabled') && !meditrendy_cart_module_enabled()) {
        wp_send_json_error(['message' => __('Cart module is disabled.', 'meditrendy-core')], 403);
    }

    $include_upsells = !empty($_POST['include_upsells']);

    wp_send_json_success(meditrendy_side_cart_response($include_upsells));
}

function meditrendy_side_cart_ajax_add() {
    meditrendy_side_cart_verify_request();

    if (function_exists('meditrendy_cart_module_enabled') && !meditrendy_cart_module_enabled()) {
        wp_send_json_error(['message' => __('Cart module is disabled.', 'meditrendy-core')], 403);
    }

    $product_id = 0;
    $has_bundle_ids = isset($_POST['woosb_ids']) && wc_clean(wp_unslash($_POST['woosb_ids'])) !== '';

    /*
     * Bundle/set forms can contain both product_id and add-to-cart fields.
     * In WPC Smart Bundles / WOOSB, add-to-cart may point to a bundled child item,
     * while product_id points to the purchasable parent set.
     * If we use the child ID here, WooCommerce returns: "{child product}" is un-purchasable.
     */
    if ($has_bundle_ids && isset($_POST['product_id'])) {
        $product_id = absint(wp_unslash($_POST['product_id']));
    } elseif (isset($_POST['add-to-cart'])) {
        $product_id = absint(wp_unslash($_POST['add-to-cart']));
    } elseif (isset($_POST['product_id'])) {
        $product_id = absint(wp_unslash($_POST['product_id']));
    }

    $product_id = apply_filters('woocommerce_add_to_cart_product_id', $product_id);
    $variation_id = isset($_POST['variation_id']) ? absint(wp_unslash($_POST['variation_id'])) : 0;
    $quantity = empty($_POST['quantity']) ? 1 : wc_stock_amount(wp_unslash($_POST['quantity']));
    $client_existing_quantity = isset($_POST['mt_side_cart_existing_quantity']) ? max(0, wc_stock_amount(wp_unslash($_POST['mt_side_cart_existing_quantity']))) : null;
    $variation = [];

    if (isset($_POST['woosb_ids']) && wc_clean(wp_unslash($_POST['woosb_ids'])) === '') {
        unset($_POST['woosb_ids'], $_REQUEST['woosb_ids']);
    }

    foreach ($_POST as $key => $value) {
        if (strpos((string) $key, 'attribute_') !== 0 || is_array($value)) {
            continue;
        }

        $variation[sanitize_title(wp_unslash($key))] = wp_unslash($value);
    }

    $product = wc_get_product($product_id);

    if (!$product) {
        wp_send_json_error(['message' => __('Prekė nerasta.', 'meditrendy-core')], 404);
    }

    $passed_validation = apply_filters('woocommerce_add_to_cart_validation', true, $product_id, $quantity, $variation_id, $variation);

    if (!$passed_validation) {
        meditrendy_side_cart_send_add_error($product_id, $variation_id, $has_bundle_ids);
    }

    if (empty($variation_id) && $product->is_type('variable')) {
        wc_add_notice(sprintf(__('Pasirinkite produkto „%s“ variantą.', 'meditrendy-core'), $product->get_name()), 'error');
        meditrendy_side_cart_send_add_error($product_id, $variation_id, $has_bundle_ids);
    }

    if ($client_existing_quantity !== null && meditrendy_side_cart_existing_quantity($product_id, $variation_id) > $client_existing_quantity) {
        meditrendy_side_cart_send_existing_cart_response('', $product_id, $variation_id, $quantity);
    }

    $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variation);

    if (false === $cart_item_key) {
        meditrendy_side_cart_send_add_error($product_id, $variation_id, $has_bundle_ids);
    }

    do_action('woocommerce_ajax_added_to_cart', $product_id);
    do_action('internal_woocommerce_cart_item_added_from_user_request', $variation_id ? $variation_id : $product_id, $quantity);

    WC()->cart->calculate_totals();
    wc_clear_notices();

    wp_send_json_success(meditrendy_side_cart_response(true, meditrendy_side_cart_tracking_payload($cart_item_key, $product_id, $variation_id, $quantity)));
}

function meditrendy_side_cart_is_soft_bundle_notice($message) {
    $message = function_exists('mb_strtolower') ? mb_strtolower((string) $message, 'UTF-8') : strtolower((string) $message);

    $needles = [
        'un-purchasable',
        'unpurchasable',
        'not purchasable',
        'cannot be purchased',
        'negalima įsigyti',
        'neparduodama',
    ];

    foreach ($needles as $needle) {
        if (strpos($message, $needle) !== false) {
            return true;
        }
    }

    return false;
}

function meditrendy_side_cart_is_existing_stock_notice($message) {
    $message = function_exists('mb_strtolower') ? mb_strtolower((string) $message, 'UTF-8') : strtolower((string) $message);

    $needles = [
        'jau turite',
        'jūs jau turite',
        'already have',
        'you already have',
        'cart',
    ];

    foreach ($needles as $needle) {
        if (strpos($message, $needle) !== false) {
            return true;
        }
    }

    return false;
}

function meditrendy_side_cart_send_add_error($product_id = 0, $variation_id = 0, $is_bundle_request = false) {
    $notices = wc_get_notices('error');
    $message = __('Nepavyko įdėti prekės į krepšelį.', 'meditrendy-core');

    if (!empty($notices)) {
        $first_notice = reset($notices);

        if (is_array($first_notice) && !empty($first_notice['notice'])) {
            $message = wp_strip_all_tags($first_notice['notice']);
        } elseif (is_string($first_notice)) {
            $message = wp_strip_all_tags($first_notice);
        }
    }

    if (meditrendy_side_cart_existing_quantity($product_id, $variation_id) > 0 && meditrendy_side_cart_is_existing_stock_notice($message)) {
        meditrendy_side_cart_send_existing_cart_response('', $product_id, $variation_id);
    }

    /*
     * Some WOOSB / set products add the parent set correctly, but still leave
     * a child-product notice like: "{child product}" is un-purchasable.
     * In that case the customer should see the refreshed side cart, not an alert.
     */
    if ($is_bundle_request && meditrendy_side_cart_is_soft_bundle_notice($message)) {
        meditrendy_side_cart_send_existing_cart_response('', $product_id, $variation_id);
    }

    wc_clear_notices();
    wp_send_json_error(['message' => $message], 400);
}

function meditrendy_side_cart_ajax_update() {
    meditrendy_side_cart_verify_request();

    if (function_exists('meditrendy_cart_module_enabled') && !meditrendy_cart_module_enabled()) {
        wp_send_json_error(['message' => __('Cart module is disabled.', 'meditrendy-core')], 403);
    }

    $cart_item_key = isset($_POST['cart_item_key']) ? sanitize_text_field(wp_unslash($_POST['cart_item_key'])) : '';
    $quantity = isset($_POST['quantity']) ? wc_stock_amount(wp_unslash($_POST['quantity'])) : 1;

    if ($cart_item_key === '' || !isset(WC()->cart->cart_contents[$cart_item_key])) {
        wp_send_json_error(['message' => __('Prekė nerasta krepšelyje.', 'meditrendy-core')], 404);
    }

    WC()->cart->set_quantity($cart_item_key, max(0, (int) $quantity), true);
    WC()->cart->calculate_totals();

    wp_send_json_success(meditrendy_side_cart_response(true));
}

function meditrendy_side_cart_enqueue_assets() {
    if (is_admin()) {
        return;
    }

    if (function_exists('meditrendy_cart_module_enabled') && !meditrendy_cart_module_enabled()) {
        return;
    }

    $script_path = MEDITRENDY_CORE_DIR . 'assets/js/side-cart.js';

    wp_enqueue_script(
        'meditrendy-side-cart',
        MEDITRENDY_CORE_URL . 'assets/js/side-cart.js',
        ['jquery'],
        file_exists($script_path) ? filemtime($script_path) : '1.0',
        true
    );

    wp_localize_script(
        'meditrendy-side-cart',
        'MeditrendySideCart',
        [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('meditrendy_side_cart'),
            'count' => meditrendy_side_cart_count(),
            'openOnLoad' => false,
            'cartTriggerSelector' => 'header .xoo-wsc-cart-trigger, header .custom-cart-icon, header .meditrendy-cart-trigger, header .meditrendy-cart-toggle, header a[href*="/cart"]',
        ]
    );
}

add_action('wp_footer', 'meditrendy_side_cart_render_shell', 30);
add_action('wp_enqueue_scripts', 'meditrendy_side_cart_enqueue_assets', 46);
add_action('wp_ajax_meditrendy_side_cart_get', 'meditrendy_side_cart_ajax_get');
add_action('wp_ajax_nopriv_meditrendy_side_cart_get', 'meditrendy_side_cart_ajax_get');
add_action('wp_ajax_meditrendy_side_cart_add', 'meditrendy_side_cart_ajax_add');
add_action('wp_ajax_nopriv_meditrendy_side_cart_add', 'meditrendy_side_cart_ajax_add');
add_action('wp_ajax_meditrendy_side_cart_update', 'meditrendy_side_cart_ajax_update');
add_action('wp_ajax_nopriv_meditrendy_side_cart_update', 'meditrendy_side_cart_ajax_update');
