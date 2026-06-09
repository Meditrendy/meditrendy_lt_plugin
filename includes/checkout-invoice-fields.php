<?php

if (!defined('ABSPATH')) exit;

/**
 * Checkout invoice fields.
 *
 * These fields intentionally do not use WooCommerce Blocks Additional Checkout
 * Fields. Toggling an additional field refreshes checkout state on this site
 * and makes WP Clever bundle rows lose their original checkout summary shape.
 */

add_filter('pre_option_woocommerce_checkout_company_field', function() {
    return 'hidden';
});

add_filter('gettext', function($translation, $text, $domain) {
    if ($domain !== 'woocommerce' || !function_exists('is_checkout') || !is_checkout()) {
        return $translation;
    }

    if ($text === 'Billing address' || $translation === 'Pirkėjo adresas') {
        return 'Adresas sąskaitai';
    }

    return $translation;
}, 20, 3);

function meditrendy_ensure_checkout_invoice_session() {
    if (!function_exists('WC')) {
        return;
    }

    if (!WC()->session && function_exists('wc_load_cart')) {
        wc_load_cart();
    }
}

function meditrendy_get_checkout_invoice_session_data() {
    meditrendy_ensure_checkout_invoice_session();

    if (!function_exists('WC') || !WC()->session) {
        return [
            'invoiceRequired' => false,
            'companyName'     => '',
            'companyCode'     => '',
        ];
    }

    return [
        'invoiceRequired' => (bool) WC()->session->get('meditrendy_invoice_required', false),
        'companyName'     => (string) WC()->session->get('meditrendy_company_name', ''),
        'companyCode'     => (string) WC()->session->get('meditrendy_company_code', ''),
    ];
}

function meditrendy_set_checkout_invoice_session_data($invoice_required, $company_name, $company_code) {
    meditrendy_ensure_checkout_invoice_session();

    if (!function_exists('WC') || !WC()->session) {
        return;
    }

    WC()->session->set('meditrendy_invoice_required', (bool) $invoice_required);
    WC()->session->set('meditrendy_company_name', sanitize_text_field($company_name));
    WC()->session->set('meditrendy_company_code', sanitize_text_field($company_code));
}

function meditrendy_save_checkout_invoice_fields_ajax() {
    check_ajax_referer('meditrendy_checkout_invoice_fields', 'nonce');

    $invoice_required = !empty($_POST['invoice_required']);
    $company_name     = isset($_POST['company_name']) ? wp_unslash($_POST['company_name']) : '';
    $company_code     = isset($_POST['company_code']) ? wp_unslash($_POST['company_code']) : '';

    meditrendy_set_checkout_invoice_session_data($invoice_required, $company_name, $company_code);

    wp_send_json_success();
}
add_action('wp_ajax_meditrendy_save_checkout_invoice_fields', 'meditrendy_save_checkout_invoice_fields_ajax');
add_action('wp_ajax_nopriv_meditrendy_save_checkout_invoice_fields', 'meditrendy_save_checkout_invoice_fields_ajax');

function meditrendy_apply_checkout_invoice_fields_to_order($order) {
    if (!$order instanceof WC_Order) {
        return;
    }

    $data = meditrendy_get_checkout_invoice_session_data();

    if (!$data['invoiceRequired']) {
        $order->delete_meta_data('_meditrendy_invoice_required');
        $order->delete_meta_data('_meditrendy_company_name');
        $order->delete_meta_data('_meditrendy_company_code');
        return;
    }

    $order->update_meta_data('_meditrendy_invoice_required', 'yes');
    $order->update_meta_data('_meditrendy_company_name', $data['companyName']);
    $order->update_meta_data('_meditrendy_company_code', $data['companyCode']);
}
add_action('woocommerce_store_api_checkout_update_order_from_request', 'meditrendy_apply_checkout_invoice_fields_to_order', 20);

add_action('woocommerce_checkout_update_order_meta', function($order_id) {
    $order = wc_get_order($order_id);

    if (!$order) {
        return;
    }

    meditrendy_apply_checkout_invoice_fields_to_order($order);
    $order->save();
});

add_action('woocommerce_admin_order_data_after_billing_address', function($order) {
    if (!$order instanceof WC_Order || $order->get_meta('_meditrendy_invoice_required') !== 'yes') {
        return;
    }

    $company_name = $order->get_meta('_meditrendy_company_name');
    $company_code = $order->get_meta('_meditrendy_company_code');

    echo '<div class="meditrendy-admin-invoice-data">';
    echo '<p><strong>' . esc_html__('Invoice data', 'meditrendy-core') . '</strong></p>';

    if ($company_name) {
        echo '<p>' . esc_html__('Company name:', 'meditrendy-core') . ' ' . esc_html($company_name) . '</p>';
    }

    if ($company_code) {
        echo '<p>' . esc_html__('Company code:', 'meditrendy-core') . ' ' . esc_html($company_code) . '</p>';
    }

    echo '</div>';
});

add_action('wp_enqueue_scripts', function() {
    if (!function_exists('is_checkout') || !is_checkout()) {
        return;
    }

    if (function_exists('is_order_received_page') && is_order_received_page()) {
        return;
    }

    $asset_path = MEDITRENDY_CORE_DIR . 'assets/js/checkout-invoice-fields.js';
    $data       = meditrendy_get_checkout_invoice_session_data();

    wp_enqueue_script(
        'meditrendy-checkout-invoice-fields',
        MEDITRENDY_CORE_URL . 'assets/js/checkout-invoice-fields.js',
        [],
        file_exists($asset_path) ? filemtime($asset_path) : '1.0.0',
        true
    );

    wp_localize_script(
        'meditrendy-checkout-invoice-fields',
        'meditrendyCheckoutInvoice',
        [
            'ajaxUrl'         => admin_url('admin-ajax.php'),
            'nonce'           => wp_create_nonce('meditrendy_checkout_invoice_fields'),
            'invoiceRequired' => $data['invoiceRequired'],
            'companyName'     => $data['companyName'],
            'companyCode'     => $data['companyCode'],
            'labels'          => [
                'invoiceRequired' => 'Reikia sąskaitos faktūros įmonei',
                'companyName'     => 'Įmonės pavadinimas (nebūtina)',
                'companyCode'     => 'PVM mokėtojo kodas (nebūtina)',
                'invoiceAddress'  => 'Adresas sąskaitai',
            ],
        ]
    );
});
