<?php

if (!defined('ABSPATH')) exit;

/**
 * Cash on Delivery fee for Meditrendy Lithuania.
 */

define('MEDITRENDY_COD_FEE_AMOUNT', 2.00);
define('MEDITRENDY_COD_FEE_TAXABLE', false);

function meditrendy_cod_fee_label() {
    $locale = strtolower((string) (function_exists('determine_locale') ? determine_locale() : get_locale()));

    if (strpos($locale, 'lv') === 0) {
        return 'Maksa par apmaksu saņemšanas brīdī';
    }

    if (strpos($locale, 'et') === 0) {
        return 'Kättesaamisel tasumise tasu';
    }

    if (strpos($locale, 'pl') === 0) {
        return 'Opłata za pobranie';
    }

    return __('Apmokėjimo pristatymo metu mokestis', 'meditrendy-core');
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

function meditrendy_cod_fee_pickup_markers() {
    return apply_filters('meditrendy_cod_fee_pickup_markers', [
        'local_pickup',
        'pickup',
        'collection',
        'atsiimimas',
        'atsiemimas',
        'atsiimti',
        'sanemsana',
        'iznemsana',
        'pasizvesana',
        'odbior',
    ]);
}

function meditrendy_cod_fee_normalize_pickup_value($value) {
    $value = wp_strip_all_tags((string) $value);
    $value = strtolower(remove_accents($value));

    return preg_replace('/[^a-z0-9_:-]+/', ' ', $value);
}

function meditrendy_cod_fee_is_pickup_method($method_id) {
    $method_id = meditrendy_cod_fee_normalize_pickup_value($method_id);

    if ($method_id === '') {
        return false;
    }

    foreach (meditrendy_cod_fee_pickup_markers() as $marker) {
        $marker = meditrendy_cod_fee_normalize_pickup_value($marker);

        if ($marker !== '' && strpos($method_id, $marker) !== false) {
            return true;
        }
    }

    foreach (['local_pickup', 'pickup', 'collection', 'atsiėmimas', 'atsiimimas', 'atsiemimas', 'atsiimti'] as $marker) {
        if (strpos($method_id, $marker) !== false) {
            return true;
        }
    }

    return false;
}

function meditrendy_cod_fee_is_pickup_selected() {
    if (!meditrendy_cod_fee_ensure_session()) {
        return false;
    }

    $chosen_methods = (array) WC()->session->get('chosen_shipping_methods', []);

    foreach ($chosen_methods as $method_id) {
        if (meditrendy_cod_fee_is_pickup_method($method_id)) {
            return true;
        }
    }

    if (WC()->shipping()) {
        foreach ((array) WC()->shipping()->get_packages() as $package_index => $package) {
            $chosen_method = (string) ($chosen_methods[$package_index] ?? '');

            if ($chosen_method === '' || empty($package['rates'][$chosen_method])) {
                continue;
            }

            $rate = $package['rates'][$chosen_method];
            $label = is_object($rate) && method_exists($rate, 'get_label') ? $rate->get_label() : '';

            if (meditrendy_cod_fee_is_pickup_method($label)) {
                return true;
            }
        }
    }

    return false;
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

    if (meditrendy_cod_fee_is_pickup_selected()) {
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

    if (meditrendy_cod_fee_is_pickup_selected()) {
        $gateways['cod']->title = preg_replace('/\s*\(\+2\s*(€|&euro;|EUR|â‚¬)\)\s*$/u', '', (string) $gateways['cod']->title);
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
            'ajaxUrl'       => admin_url('admin-ajax.php'),
            'nonce'         => wp_create_nonce('meditrendy_checkout_cod_fee'),
            'pickupMarkers' => meditrendy_cod_fee_pickup_markers(),
        ]
    );
});
