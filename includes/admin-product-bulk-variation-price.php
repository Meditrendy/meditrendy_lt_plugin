<?php
/** Extend WooCommerce's existing Bulk Edit price controls to variations. */

if (!defined('ABSPATH')) {
    exit;
}

function meditrendy_bulk_variation_price_note() {
    echo '<p class="description">' . esc_html__('W produktach wariantowych zmiany cen obejmą wszystkie warianty. Zmiany procentowe i kwotowe są liczone osobno od ceny każdego wariantu.', 'meditrendy-core') . '</p>';
}
add_action('woocommerce_product_bulk_edit_start', 'meditrendy_bulk_variation_price_note');

/** Parse one WooCommerce Bulk Edit choice. Null means no valid change was requested. */
function meditrendy_bulk_variation_price_choice($mode, $raw, $is_sale) {
    $mode = absint($mode);
    if ($mode < 1 || $mode > ($is_sale ? 4 : 3) || !is_scalar($raw)) {
        return null;
    }

    $raw = trim((string) $raw);
    if ($raw === '') {
        return $is_sale && $mode === 1 ? ['mode' => 1, 'clear' => true] : null;
    }

    $percentage = substr($raw, -1) === '%';
    if ($percentage) {
        $raw = substr($raw, 0, -1);
    }
    if (($mode === 1 && $percentage) || !preg_match('/^\d+(?:[.,]\d+)?$/D', $raw)) {
        return null;
    }

    return [
        'mode'       => $mode,
        'amount'     => (float) wc_format_decimal(str_replace(',', '.', $raw)),
        'percentage' => $percentage,
    ];
}

/** Calculate one price using its own starting value, matching WooCommerce's modes. */
function meditrendy_bulk_variation_calculate_price($current, $regular, $choice) {
    if ($choice === null) {
        return null;
    }
    if (!empty($choice['clear'])) {
        return '';
    }

    $amount = $choice['amount'];
    $base = $current === '' ? (float) $regular : (float) $current;
    switch ($choice['mode']) {
        case 1:
            $result = $amount;
            break;
        case 2:
            $result = $base + ($choice['percentage'] ? $base * $amount / 100 : $amount);
            break;
        case 3:
            $result = max(0, $base - ($choice['percentage'] ? $base * $amount / 100 : $amount));
            break;
        case 4:
            $discount = $choice['percentage']
                ? round((float) $regular * $amount / 100, wc_get_price_decimals())
                : $amount;
            $result = max(0, (float) $regular - $discount);
            break;
        default:
            return null;
    }

    return wc_format_decimal($result, wc_get_price_decimals());
}

function meditrendy_bulk_variation_prices_match($a, $b) {
    return $a === $b || ($a !== '' && $b !== ''
        && wc_format_decimal($a, wc_get_price_decimals()) === wc_format_decimal($b, wc_get_price_decimals()));
}

/** WooCommerce calls this only after checking its Bulk Edit nonce and permissions. */
function meditrendy_bulk_edit_variation_prices($product) {
    if (!$product instanceof WC_Product || !current_user_can('edit_post', $product->get_id())) {
        return;
    }

    $is_variable = $product->is_type('variable');
    $is_fixed_set = $product->is_type('woosb') && method_exists($product, 'is_fixed_price') && $product->is_fixed_price();
    if (!$is_variable && !$is_fixed_set) {
        return;
    }

    $regular_mode = isset($_REQUEST['change_regular_price']) && is_scalar($_REQUEST['change_regular_price'])
        ? $_REQUEST['change_regular_price'] : 0;
    $sale_mode = isset($_REQUEST['change_sale_price']) && is_scalar($_REQUEST['change_sale_price'])
        ? $_REQUEST['change_sale_price'] : 0;
    $regular_raw = isset($_REQUEST['_regular_price']) ? wp_unslash($_REQUEST['_regular_price']) : '';
    $sale_raw = isset($_REQUEST['_sale_price']) ? wp_unslash($_REQUEST['_sale_price']) : '';
    $regular_choice = meditrendy_bulk_variation_price_choice($regular_mode, $regular_raw, false);
    $sale_choice = meditrendy_bulk_variation_price_choice($sale_mode, $sale_raw, true);
    if ($regular_choice === null && $sale_choice === null) {
        return;
    }

    $targets = [];
    if ($is_variable) {
        foreach ($product->get_children() as $variation_id) {
            $variation = wc_get_product($variation_id);
            if (!$variation instanceof WC_Product_Variation) {
                return;
            }
            $targets[] = $variation;
        }
    } else {
        $targets[] = $product;
    }

    // Calculate all results before the first save, so malformed input cannot
    // leave only some variations updated.
    $updates = [];
    foreach ($targets as $target) {
        $old_regular = (string) $target->get_regular_price('edit');
        $old_sale = (string) $target->get_sale_price('edit');
        $new_regular = meditrendy_bulk_variation_calculate_price($old_regular, $old_regular, $regular_choice);
        $regular = $new_regular === null ? $old_regular : $new_regular;
        $new_sale = meditrendy_bulk_variation_calculate_price($old_sale, $regular, $sale_choice);
        $sale = $new_sale === null ? $old_sale : $new_sale;
        if ($sale !== '' && ($regular === '' || (float) $sale >= (float) $regular)) {
            $sale = '';
        }
        if (meditrendy_bulk_variation_prices_match($regular, $old_regular)
            && meditrendy_bulk_variation_prices_match($sale, $old_sale)) {
            continue;
        }
        $updates[] = [$target, $regular, $sale];
    }
    if (!$updates) {
        return;
    }

    if ($is_variable) {
        $GLOBALS['meditrendy_bulk_price_deferred_parent'] = $product->get_id();
    }
    try {
        foreach ($updates as [$target, $regular, $sale]) {
            $target->set_regular_price($regular);
            $target->set_sale_price($sale);
            $target->set_date_on_sale_from('');
            $target->set_date_on_sale_to('');
            $target->save();
        }
        if ($is_variable) {
            WC_Product_Variable::sync($product->get_id());
        }
    } finally {
        if ($is_variable) {
            unset($GLOBALS['meditrendy_bulk_price_deferred_parent']);
        }
    }

    if ($is_variable && function_exists('meditrendy_product_set_refresh_containing_product')) {
        meditrendy_product_set_refresh_containing_product($product->get_id());
    }
}
add_action('woocommerce_product_bulk_edit_save', 'meditrendy_bulk_edit_variation_prices');
