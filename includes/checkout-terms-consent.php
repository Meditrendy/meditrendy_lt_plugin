<?php

if (!defined('ABSPATH')) exit;

function meditrendy_checkout_terms_consent_get_page_url($option_name, $fallback_path) {
    $page_id = absint(get_option($option_name));

    if ($page_id) {
        $url = get_permalink($page_id);

        if ($url) {
            return $url;
        }
    }

    return home_url($fallback_path);
}

function meditrendy_checkout_terms_consent_labels() {
    if (function_exists('meditrendy_core_current_language') && meditrendy_core_current_language() === 'lv') {
        return [
            'prefix'      => 'Es piekrītu',
            'joiner'      => 'un',
            'terms'       => 'noteikumiem un nosacījumiem',
            'privacy'     => 'privātuma politikai',
            'required'    => 'Pirms pasūtījuma veikšanas jums jāpiekrīt noteikumiem un privātuma politikai.',
            'termsUrl'    => meditrendy_checkout_terms_consent_get_page_url('woocommerce_terms_page_id', '/taisykles/'),
            'privacyUrl'  => meditrendy_checkout_terms_consent_get_page_url('wp_page_for_privacy_policy', '/privacy-policy/'),
        ];
    }

    return [
        'prefix'      => __('Sutinku su', 'meditrendy-core'),
        'joiner'      => __('bei', 'meditrendy-core'),
        'terms'       => __('Taisyklėmis ir sąlygomis', 'meditrendy-core'),
        'privacy'     => __('Privatumo politika', 'meditrendy-core'),
        'required'    => __('Prieš pateikdami užsakymą turite sutikti su taisyklėmis ir privatumo politika.', 'meditrendy-core'),
        'termsUrl'    => meditrendy_checkout_terms_consent_get_page_url('woocommerce_terms_page_id', '/taisykles/'),
        'privacyUrl'  => meditrendy_checkout_terms_consent_get_page_url('wp_page_for_privacy_policy', '/privacy-policy/'),
    ];
}

function meditrendy_checkout_terms_consent_enqueue_assets() {
    if (is_admin() || !function_exists('is_checkout') || !is_checkout()) {
        return;
    }

    $style_path = MEDITRENDY_CORE_DIR . 'assets/css/checkout-terms-consent.css';
    $script_path = MEDITRENDY_CORE_DIR . 'assets/js/checkout-terms-consent.js';

    wp_enqueue_style(
        'meditrendy-checkout-terms-consent',
        MEDITRENDY_CORE_URL . 'assets/css/checkout-terms-consent.css',
        [],
        file_exists($style_path) ? filemtime($style_path) : '1.0'
    );

    wp_enqueue_script(
        'meditrendy-checkout-terms-consent',
        MEDITRENDY_CORE_URL . 'assets/js/checkout-terms-consent.js',
        [],
        file_exists($script_path) ? filemtime($script_path) : '1.0',
        true
    );

    wp_localize_script(
        'meditrendy-checkout-terms-consent',
        'meditrendyCheckoutTermsConsent',
        meditrendy_checkout_terms_consent_labels()
    );
}

add_action('wp_enqueue_scripts', 'meditrendy_checkout_terms_consent_enqueue_assets', 35);

function meditrendy_checkout_terms_consent_request_value($request = null) {
    if ($request instanceof WP_REST_Request) {
        $payment_data = (array) $request->get_param('payment_data');

        foreach ($payment_data as $entry) {
            if (!is_array($entry) || empty($entry['key'])) {
                continue;
            }

            if ('meditrendy_terms_accepted' === sanitize_key($entry['key'])) {
                return wc_string_to_bool($entry['value'] ?? false);
            }
        }
    }

    return !empty($_POST['meditrendy_terms_accepted']);
}

function meditrendy_checkout_terms_consent_error_message() {
    if (function_exists('meditrendy_core_current_language') && meditrendy_core_current_language() === 'lv') {
        return 'Pirms pasūtījuma veikšanas jums jāpiekrīt noteikumiem un privātuma politikai.';
    }

    return __('Prieš pateikdami užsakymą turite sutikti su taisyklėmis ir privatumo politika.', 'meditrendy-core');
}

function meditrendy_validate_classic_checkout_terms_consent($data, $errors) {
    if (meditrendy_checkout_terms_consent_request_value()) {
        return;
    }

    $errors->add('meditrendy_terms_consent_required', meditrendy_checkout_terms_consent_error_message());
}

add_action('woocommerce_after_checkout_validation', 'meditrendy_validate_classic_checkout_terms_consent', 20, 2);

function meditrendy_validate_store_api_checkout_terms_consent($order, $request) {
    if (meditrendy_checkout_terms_consent_request_value($request)) {
        return;
    }

    if (class_exists('\Automattic\WooCommerce\StoreApi\Exceptions\RouteException')) {
        throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
            'meditrendy_terms_consent_required',
            esc_html(meditrendy_checkout_terms_consent_error_message()),
            400
        );
    }

    throw new Exception(esc_html(meditrendy_checkout_terms_consent_error_message()));
}

add_action('woocommerce_store_api_checkout_update_order_from_request', 'meditrendy_validate_store_api_checkout_terms_consent', 5, 2);
