<?php

if (!defined('ABSPATH')) exit;

/**
 * Cash on Delivery fee for Meditrendy Lithuania.
 */

define('MEDITRENDY_COD_FEE_AMOUNT', 2.00);
define('MEDITRENDY_COD_FEE_TAXABLE', false);

function meditrendy_cod_fee_label() {
    return 'Apmokėjimo pristatymo metu mokestis';
}

function meditrendy_cod_fee_ensure_session() {
    if (!function_exists('WC')) {
        return false;
    }

    if (!WC()->session && function_exists('wc_load_cart')) {
        wc_load_cart();
    }

    return WC()->session ? true : false;
}

function meditrendy_cod_fee_set_payment_method($payment_method) {
    if (!meditrendy_cod_fee_ensure_session()) {
        return;
    }

    $payment_method = sanitize_text_field((string) $payment_method);

    WC()->session->set('chosen_payment_method', $payment_method);
}

function meditrendy_cod_fee_get_payment_method() {
    if (!meditrendy_cod_fee_ensure_session()) {
        return '';
    }

    return (string) WC()->session->get('chosen_payment_method', '');
}

/**
 * Classic checkout support.
 */
add_action('woocommerce_checkout_update_order_review', function($post_data) {
    parse_str($post_data, $data);

    if (!isset($data['payment_method'])) {
        return;
    }

    meditrendy_cod_fee_set_payment_method(wp_unslash($data['payment_method']));
});

/**
 * Blocks checkout support.
 */
function meditrendy_cod_fee_save_payment_method_ajax() {
    check_ajax_referer('meditrendy_checkout_cod_fee', 'nonce');

    $payment_method = isset($_POST['payment_method'])
        ? wp_unslash($_POST['payment_method'])
        : '';

    meditrendy_cod_fee_set_payment_method($payment_method);

    wp_send_json_success([
        'paymentMethod' => $payment_method,
    ]);
}
add_action('wp_ajax_meditrendy_save_cod_payment_method', 'meditrendy_cod_fee_save_payment_method_ajax');
add_action('wp_ajax_nopriv_meditrendy_save_cod_payment_method', 'meditrendy_cod_fee_save_payment_method_ajax');

/**
 * WooCommerce Blocks Store API support.
 */
add_action('woocommerce_blocks_loaded', function() {
    if (!function_exists('woocommerce_store_api_register_update_callback')) {
        return;
    }

    woocommerce_store_api_register_update_callback([
        'namespace' => 'meditrendy-cod-fee',
        'callback'  => function($data) {
            $payment_method = isset($data['payment_method'])
                ? wp_unslash($data['payment_method'])
                : '';

            meditrendy_cod_fee_set_payment_method($payment_method);
        },
    ]);
});

/**
 * Add fee when COD is selected.
 */
add_action('woocommerce_cart_calculate_fees', function($cart) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }

    if (!$cart || $cart->is_empty()) {
        return;
    }

    if (function_exists('get_woocommerce_currency') && get_woocommerce_currency() !== 'EUR') {
        return;
    }

    $payment_method = meditrendy_cod_fee_get_payment_method();

    if ($payment_method !== 'cod') {
        return;
    }

    $cart->add_fee(
        meditrendy_cod_fee_label(),
        MEDITRENDY_COD_FEE_AMOUNT,
        MEDITRENDY_COD_FEE_TAXABLE
    );
}, 20);

/**
 * Show fee information in COD title on frontend.
 */
add_filter('woocommerce_available_payment_gateways', function($gateways) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return $gateways;
    }

    if (!isset($gateways['cod'])) {
        return $gateways;
    }

    if (strpos($gateways['cod']->title, '+2') === false) {
        $gateways['cod']->title .= ' (+2 €)';
    }

    return $gateways;
});

/**
 * Checkout script for WooCommerce Blocks and instant refresh.
 */
add_action('wp_enqueue_scripts', function() {
    if (!function_exists('is_checkout') || !is_checkout()) {
        return;
    }

    if (function_exists('is_order_received_page') && is_order_received_page()) {
        return;
    }

    $asset_path = MEDITRENDY_CORE_DIR . 'assets/js/checkout-cod-fee.js';

    wp_enqueue_script(
        'meditrendy-checkout-cod-fee',
        MEDITRENDY_CORE_URL . 'assets/js/checkout-cod-fee.js',
        [],
        file_exists($asset_path) ? filemtime($asset_path) : '1.0.0',
        true
    );

    wp_localize_script(
        'meditrendy-checkout-cod-fee',
        'meditrendyCheckoutCodFee',
        [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('meditrendy_checkout_cod_fee'),
        ]
    );
});
