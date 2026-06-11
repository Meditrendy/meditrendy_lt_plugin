<?php

if (!defined('ABSPATH')) exit;

/**
 * Checkout contact and invoice fields.
 *
 * These fields intentionally do not use WooCommerce Blocks Additional Checkout
 * Fields. Toggling an additional field refreshes checkout state on this site
 * and makes WP Clever bundle rows lose their original checkout summary shape.
 */

add_filter('pre_option_woocommerce_checkout_company_field', function() {
    return 'hidden';
});

add_filter('pre_option_woocommerce_checkout_phone_field', function() {
    return 'hidden';
});

function meditrendy_hide_checkout_state_field($locale) {
    if (!is_array($locale)) {
        return $locale;
    }

    if (isset($locale['state']) && is_array($locale['state'])) {
        $locale['state']['required'] = false;
        $locale['state']['hidden']   = true;
    }

    return $locale;
}
add_filter('woocommerce_get_country_locale_default', 'meditrendy_hide_checkout_state_field', 20);
add_filter('woocommerce_get_country_locale_base', 'meditrendy_hide_checkout_state_field', 20);

add_filter('woocommerce_get_country_locale', function($locales) {
    if (!is_array($locales)) {
        return $locales;
    }

    foreach ($locales as $country => $locale) {
        $locales[$country] = meditrendy_hide_checkout_state_field($locale);
    }

    return $locales;
}, 20);

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
            'contactPhone'    => '',
            'companyName'     => '',
            'companyCode'     => '',
            'invoiceStreet'   => '',
            'invoiceCity'     => '',
            'invoicePostcode' => '',
        ];
    }

    return [
        'invoiceRequired' => (bool) WC()->session->get('meditrendy_invoice_required', false),
        'contactPhone'    => (string) WC()->session->get('meditrendy_contact_phone', ''),
        'companyName'     => (string) WC()->session->get('meditrendy_company_name', ''),
        'companyCode'     => (string) WC()->session->get('meditrendy_company_code', ''),
        'invoiceStreet'   => (string) WC()->session->get('meditrendy_invoice_street', ''),
        'invoiceCity'     => (string) WC()->session->get('meditrendy_invoice_city', ''),
        'invoicePostcode' => (string) WC()->session->get('meditrendy_invoice_postcode', ''),
    ];
}

function meditrendy_set_checkout_invoice_session_data($invoice_required, $contact_phone, $company_name, $company_code, $invoice_street, $invoice_city, $invoice_postcode) {
    meditrendy_ensure_checkout_invoice_session();

    if (!function_exists('WC') || !WC()->session) {
        return;
    }

    WC()->session->set('meditrendy_invoice_required', (bool) $invoice_required);
    WC()->session->set('meditrendy_contact_phone', sanitize_text_field($contact_phone));
    WC()->session->set('meditrendy_company_name', sanitize_text_field($company_name));
    WC()->session->set('meditrendy_company_code', sanitize_text_field($company_code));
    WC()->session->set('meditrendy_invoice_street', sanitize_text_field($invoice_street));
    WC()->session->set('meditrendy_invoice_city', sanitize_text_field($invoice_city));
    WC()->session->set('meditrendy_invoice_postcode', sanitize_text_field($invoice_postcode));
}

function meditrendy_get_checkout_pickup_address() {
    $country_state = (string) get_option('woocommerce_default_country', 'LT');
    $country_parts = explode(':', $country_state);
    $address_1 = (string) get_option('woocommerce_store_address', '');
    $city = (string) get_option('woocommerce_store_city', '');
    $postcode = (string) get_option('woocommerce_store_postcode', '');

    return [
        'address_1' => $address_1 ?: 'Verkių g. 42, D81',
        'address_2' => (string) get_option('woocommerce_store_address_2', ''),
        'city'      => $city ?: 'Vilnius',
        'postcode'  => $postcode ?: 'LT-09117',
        'country'   => $country_parts[0] ?: 'LT',
        'state'     => $country_parts[1] ?? '',
    ];
}

function meditrendy_checkout_shipping_identifier_is_pickup($value) {
    $value = strtolower((string) $value);

    return strpos($value, 'pickup') !== false
        || strpos($value, 'local_pickup') !== false
        || strpos($value, 'collection') !== false
        || strpos($value, 'atsi') !== false;
}

function meditrendy_checkout_session_uses_pickup() {
    if (!function_exists('WC') || !WC()->session) {
        return false;
    }

    $chosen_methods = WC()->session->get('chosen_shipping_methods', []);

    if (!is_array($chosen_methods)) {
        $chosen_methods = [$chosen_methods];
    }

    foreach ($chosen_methods as $chosen_method) {
        if (meditrendy_checkout_shipping_identifier_is_pickup($chosen_method)) {
            return true;
        }
    }

    if (!WC()->shipping()) {
        return false;
    }

    foreach (WC()->shipping()->get_packages() as $index => $package) {
        $chosen_method = $chosen_methods[$index] ?? '';
        $rates = isset($package['rates']) && is_array($package['rates']) ? $package['rates'] : [];

        if (!$chosen_method || !isset($rates[$chosen_method])) {
            continue;
        }

        $rate = $rates[$chosen_method];
        $method_id = is_object($rate) && is_callable([$rate, 'get_method_id']) ? $rate->get_method_id() : '';
        $label = is_object($rate) && is_callable([$rate, 'get_label']) ? $rate->get_label() : '';

        if (meditrendy_checkout_shipping_identifier_is_pickup($method_id) || meditrendy_checkout_shipping_identifier_is_pickup($label)) {
            return true;
        }
    }

    return false;
}

function meditrendy_order_uses_pickup($order) {
    if (!$order instanceof WC_Order) {
        return meditrendy_checkout_session_uses_pickup();
    }

    foreach ($order->get_shipping_methods() as $method) {
        $method_id = is_callable([$method, 'get_method_id']) ? $method->get_method_id() : '';
        $method_title = is_callable([$method, 'get_method_title']) ? $method->get_method_title() : '';

        if (meditrendy_checkout_shipping_identifier_is_pickup($method_id) || meditrendy_checkout_shipping_identifier_is_pickup($method_title)) {
            return true;
        }
    }

    return meditrendy_checkout_session_uses_pickup();
}

function meditrendy_apply_pickup_address_to_order($order, $contact_phone = '') {
    if (!$order instanceof WC_Order) {
        return;
    }

    $pickup_address = meditrendy_get_checkout_pickup_address();
    $first_name = $order->get_shipping_first_name() ?: $order->get_billing_first_name();
    $last_name = $order->get_shipping_last_name() ?: $order->get_billing_last_name();

    if ($first_name && !$order->get_shipping_first_name()) {
        $order->set_shipping_first_name($first_name);
    }

    if ($last_name && !$order->get_shipping_last_name()) {
        $order->set_shipping_last_name($last_name);
    }

    if (!$order->get_shipping_country()) {
        $order->set_shipping_country($pickup_address['country']);
    }

    if (!$order->get_shipping_state()) {
        $order->set_shipping_state($pickup_address['state']);
    }

    if (!$order->get_shipping_address_1()) {
        $order->set_shipping_address_1($pickup_address['address_1']);
    }

    if (!$order->get_shipping_address_2()) {
        $order->set_shipping_address_2($pickup_address['address_2']);
    }

    if (!$order->get_shipping_city()) {
        $order->set_shipping_city($pickup_address['city']);
    }

    if (!$order->get_shipping_postcode()) {
        $order->set_shipping_postcode($pickup_address['postcode']);
    }

    if ($contact_phone && is_callable([$order, 'set_shipping_phone'])) {
        $order->set_shipping_phone($contact_phone);
    }
}

function meditrendy_save_checkout_invoice_fields_ajax() {
    check_ajax_referer('meditrendy_checkout_invoice_fields', 'nonce');

    $invoice_required = !empty($_POST['invoice_required']);
    $contact_phone    = isset($_POST['contact_phone']) ? wp_unslash($_POST['contact_phone']) : '';
    $company_name     = isset($_POST['company_name']) ? wp_unslash($_POST['company_name']) : '';
    $company_code     = isset($_POST['company_code']) ? wp_unslash($_POST['company_code']) : '';
    $invoice_street   = isset($_POST['invoice_street']) ? wp_unslash($_POST['invoice_street']) : '';
    $invoice_city     = isset($_POST['invoice_city']) ? wp_unslash($_POST['invoice_city']) : '';
    $invoice_postcode = isset($_POST['invoice_postcode']) ? wp_unslash($_POST['invoice_postcode']) : '';

    meditrendy_set_checkout_invoice_session_data($invoice_required, $contact_phone, $company_name, $company_code, $invoice_street, $invoice_city, $invoice_postcode);

    wp_send_json_success();
}
add_action('wp_ajax_meditrendy_save_checkout_invoice_fields', 'meditrendy_save_checkout_invoice_fields_ajax');
add_action('wp_ajax_nopriv_meditrendy_save_checkout_invoice_fields', 'meditrendy_save_checkout_invoice_fields_ajax');

function meditrendy_apply_checkout_invoice_fields_to_order($order, $request = null) {
    if (!$order instanceof WC_Order) {
        return;
    }

    $data = meditrendy_get_checkout_invoice_session_data();
    $uses_pickup = meditrendy_order_uses_pickup($order);

    if ($uses_pickup) {
        meditrendy_apply_pickup_address_to_order($order, $data['contactPhone']);
    }

    if ($data['contactPhone']) {
        $order->set_billing_phone($data['contactPhone']);
        $order->set_shipping_phone($data['contactPhone']);
    }

    if (!$data['invoiceRequired']) {
        $order->set_billing_company('');

        if (!$uses_pickup) {
            if ($order->get_shipping_first_name()) {
                $order->set_billing_first_name($order->get_shipping_first_name());
            }

            if ($order->get_shipping_last_name()) {
                $order->set_billing_last_name($order->get_shipping_last_name());
            }

            if ($order->get_shipping_country()) {
                $order->set_billing_country($order->get_shipping_country());
            }

            if ($order->get_shipping_address_1()) {
                $order->set_billing_address_1($order->get_shipping_address_1());
            }

            if ($order->get_shipping_city()) {
                $order->set_billing_city($order->get_shipping_city());
            }

            if ($order->get_shipping_postcode()) {
                $order->set_billing_postcode($order->get_shipping_postcode());
            }
        }

        if ($uses_pickup) {
            $pickup_address = meditrendy_get_checkout_pickup_address();

            if (!$order->get_billing_country()) {
                $order->set_billing_country($pickup_address['country']);
            }

            if (!$order->get_billing_state()) {
                $order->set_billing_state($pickup_address['state']);
            }
        }

        $order->delete_meta_data('_meditrendy_invoice_required');
        $order->delete_meta_data('_meditrendy_company_name');
        $order->delete_meta_data('_meditrendy_company_code');
        $order->delete_meta_data('_meditrendy_invoice_street');
        $order->delete_meta_data('_meditrendy_invoice_city');
        $order->delete_meta_data('_meditrendy_invoice_postcode');
        return;
    }

    $order->update_meta_data('_meditrendy_invoice_required', 'yes');
    $order->update_meta_data('_meditrendy_company_name', $data['companyName']);
    $order->update_meta_data('_meditrendy_company_code', $data['companyCode']);
    $order->update_meta_data('_meditrendy_invoice_street', $data['invoiceStreet']);
    $order->update_meta_data('_meditrendy_invoice_city', $data['invoiceCity']);
    $order->update_meta_data('_meditrendy_invoice_postcode', $data['invoicePostcode']);

    if ($data['companyName']) {
        $order->set_billing_company($data['companyName']);
    }

    if ($data['invoiceStreet']) {
        $order->set_billing_address_1($data['invoiceStreet']);
    }

    if ($data['invoiceCity']) {
        $order->set_billing_city($data['invoiceCity']);
    }

    if ($data['invoicePostcode']) {
        $order->set_billing_postcode($data['invoicePostcode']);
    }

    if ($uses_pickup) {
        $pickup_address = meditrendy_get_checkout_pickup_address();

        if (!$order->get_billing_country()) {
            $order->set_billing_country($pickup_address['country']);
        }

        if (!$order->get_billing_state()) {
            $order->set_billing_state($pickup_address['state']);
        }

        if (!$order->get_billing_address_1()) {
            $order->set_billing_address_1($pickup_address['address_1']);
        }

        if (!$order->get_billing_address_2()) {
            $order->set_billing_address_2($pickup_address['address_2']);
        }

        if (!$order->get_billing_city()) {
            $order->set_billing_city($pickup_address['city']);
        }

        if (!$order->get_billing_postcode()) {
            $order->set_billing_postcode($pickup_address['postcode']);
        }
    }
}
add_action('woocommerce_store_api_checkout_update_order_from_request', 'meditrendy_apply_checkout_invoice_fields_to_order', 20, 2);

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

    $company_name     = $order->get_meta('_meditrendy_company_name');
    $company_code     = $order->get_meta('_meditrendy_company_code');
    $invoice_street   = $order->get_meta('_meditrendy_invoice_street');
    $invoice_city     = $order->get_meta('_meditrendy_invoice_city');
    $invoice_postcode = $order->get_meta('_meditrendy_invoice_postcode');
    $invoice_address  = trim($invoice_street . ', ' . $invoice_city . ' ' . $invoice_postcode, ' ,');

    echo '<div class="meditrendy-admin-invoice-data">';
    echo '<p><strong>' . esc_html__('Invoice data', 'meditrendy-core') . '</strong></p>';

    if ($company_name) {
        echo '<p>' . esc_html__('Company name:', 'meditrendy-core') . ' ' . esc_html($company_name) . '</p>';
    }

    if ($company_code) {
        echo '<p>' . esc_html__('EU VAT number:', 'meditrendy-core') . ' ' . esc_html($company_code) . '</p>';
    }

    if ($invoice_address) {
        echo '<p>' . esc_html__('Invoice address:', 'meditrendy-core') . ' ' . esc_html($invoice_address) . '</p>';
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
    $pickup_address = meditrendy_get_checkout_pickup_address();

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
            'contactPhone'    => $data['contactPhone'],
            'companyName'     => $data['companyName'],
            'companyCode'     => $data['companyCode'],
            'invoiceStreet'   => $data['invoiceStreet'],
            'invoiceCity'     => $data['invoiceCity'],
            'invoicePostcode' => $data['invoicePostcode'],
            'pickupAddress'   => $pickup_address,
            'labels'          => [
                'contactPhone'    => 'Telefonas',
                'firstName'       => 'Vardas',
                'lastName'        => 'Pavardė',
                'invoiceRequired' => 'Reikia sąskaitos faktūros įmonei',
                'companyName'     => 'Įmonės pavadinimas',
                'companyCode'     => 'PVM mokėtojo kodas',
                'invoiceAddress'  => 'Adresas sąskaitai',
                'invoiceStreet'   => 'Gatvė, namo numeris',
                'invoiceCity'     => 'Miestas',
                'invoicePostcode' => 'Pašto kodas',
            ],
        ]
    );
});
