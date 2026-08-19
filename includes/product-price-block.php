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

function meditrendy_product_price_block_dynamic_set_price($product, $min_or_max = 'min') {
    if (
        !$product instanceof WC_Product
        || !$product->is_type('woosb')
        || !method_exists($product, 'get_items')
        || (method_exists($product, 'is_fixed_price') && $product->is_fixed_price())
    ) {
        return '';
    }

    $items = (array) $product->get_items();

    if (!$items) {
        return '';
    }

    $helper = function_exists('WPCleverWoosb_Helper') ? WPCleverWoosb_Helper() : null;
    $price = 0.0;
    $has_priced_item = false;

    foreach ($items as $item) {
        if (empty($item['id'])) {
            continue;
        }

        $item_product = wc_get_product(absint($item['id']));

        if (!$item_product instanceof WC_Product || !$item_product->exists() || $item_product->is_type('woosb')) {
            continue;
        }

        if (
            method_exists($product, 'exclude_unpurchasable')
            && $product->exclude_unpurchasable()
            && (!$item_product->is_purchasable() || !$item_product->is_in_stock())
        ) {
            continue;
        }

        $qty = isset($item['qty']) ? (float) $item['qty'] : 1.0;

        if (!empty($item['optional'])) {
            $qty = isset($item['min']) ? (float) $item['min'] : 0.0;
        }

        if ($qty <= 0) {
            continue;
        }

        if ($helper && method_exists($helper, 'get_price')) {
            $item_price = (float) $helper->get_price($item_product, $min_or_max, false);
        } elseif ($item_product->is_type('variable')) {
            $item_price = (float) $item_product->get_variation_price($min_or_max, false);
        } else {
            $item_price = (float) $item_product->get_price();
        }

        $price += $item_price * $qty;
        $has_priced_item = true;
    }

    if (!$has_priced_item) {
        return '';
    }

    $discount_amount = method_exists($product, 'get_discount_amount') ? (float) $product->get_discount_amount() : 0.0;
    $discount_percentage = method_exists($product, 'get_discount_percentage') ? (float) $product->get_discount_percentage() : 0.0;

    if ($discount_amount > 0) {
        $price -= $discount_amount;
    } elseif ($discount_percentage > 0 && $discount_percentage < 100) {
        $price *= (100 - $discount_percentage) / 100;
    }

    if ($helper && method_exists($helper, 'round_price')) {
        $price = $helper->round_price($price);
    } else {
        $price = (float) wc_format_decimal($price, wc_get_price_decimals());
    }

    return max(0, (float) $price);
}

function meditrendy_product_price_block_raw_price($product, $price_type) {
    if (!$product instanceof WC_Product) {
        return '';
    }

    if ($price_type !== 'regular') {
        $dynamic_set_price = meditrendy_product_price_block_dynamic_set_price($product);

        if ($dynamic_set_price !== '') {
            return $dynamic_set_price;
        }
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

function meditrendy_product_price_block_price_history_raw_price($price, $product) {
    $dynamic_set_price = meditrendy_product_price_block_dynamic_set_price($product);

    return $dynamic_set_price !== '' ? $dynamic_set_price : $price;
}
add_filter('wc_price_history_price_raw_non_taxed', 'meditrendy_product_price_block_price_history_raw_price', 20, 2);

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

function meditrendy_product_price_block_history_fallback_html($product) {
    if (
        !$product instanceof WC_Product
        || !class_exists('\\PriorPrice\\HistoryStorage')
        || !class_exists('\\PriorPrice\\SettingsData')
        || !class_exists('\\PriorPrice\\Taxes')
    ) {
        return '';
    }

    $storage = new \PriorPrice\HistoryStorage();
    $settings = new \PriorPrice\SettingsData();
    $history = $storage->get_history($product->get_id(), false);

    if (empty($history)) {
        return '';
    }

    ksort($history, SORT_NUMERIC);

    $days = max(1, $settings->get_days_number());
    $period_end = current_time('timestamp');

    if (
        in_array($settings->get_count_from(), ['sale_start', 'sale_start_inclusive'], true)
        && $product->is_on_sale()
        && $product->get_date_on_sale_from()
    ) {
        $period_end = $product->get_date_on_sale_from()->getOffsetTimestamp();

        if ($settings->get_count_from() === 'sale_start_inclusive') {
            $period_end += DAY_IN_SECONDS;
        } else {
            $period_end--;
        }
    }

    $period_start = $period_end - ($days * DAY_IN_SECONDS);
    $prices = [];
    $price_at_start = 0.0;

    foreach ($history as $timestamp => $price) {
        $price = (float) $price;

        if ($price <= 0) {
            continue;
        }

        if ((int) $timestamp < $period_start) {
            $price_at_start = $price;
            continue;
        }

        if ((int) $timestamp <= $period_end) {
            $prices[] = $price;
        }
    }

    if ($price_at_start > 0) {
        $prices[] = $price_at_start;
    }

    if (empty($prices)) {
        return '';
    }

    $lowest = (new \PriorPrice\Taxes())->apply_taxes((float) min($prices), $product);
    $price_format = str_replace(
        '%2$s',
        '<span class="wc-price-history-lowest-raw-value">%2$s</span>',
        get_woocommerce_price_format()
    );

    return sprintf(
        '<div class="wc-price-history-shortcode" data-product-id="%1$s" data-original-price="%2$s">%3$s</div>',
        $product->get_id(),
        esc_attr($lowest),
        wc_price($lowest, ['price_format' => $price_format])
    );
}

function meditrendy_product_price_block_omnibus_html($product) {
    if (!$product instanceof WC_Product || !shortcode_exists('wc_price_history')) {
        return '';
    }

    $price_history = do_shortcode('[wc_price_history id="' . $product->get_id() . '" show_currency="1"]');

    // The plugin shortcode returns an empty string when it cannot find an entry
    // inside its configured date window. Its normal price renderer handles that
    // situation through the plugin's configured "old history" fallback, so use
    // the same public API for product cards as well.
    if (
        trim(wp_strip_all_tags($price_history)) === ''
        && class_exists('\\PriorPrice\\Prices')
        && class_exists('\\PriorPrice\\HistoryStorage')
        && class_exists('\\PriorPrice\\SettingsData')
        && class_exists('\\PriorPrice\\Taxes')
    ) {
        $prices = new \PriorPrice\Prices(
            new \PriorPrice\HistoryStorage(),
            new \PriorPrice\SettingsData(),
            new \PriorPrice\Taxes()
        );
        $price_history = $prices->lowest_price_html($product);
    }

    if (trim(wp_strip_all_tags($price_history)) === '') {
        $price_history = meditrendy_product_price_block_history_fallback_html($product);
    }

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
