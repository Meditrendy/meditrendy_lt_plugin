<?php
if (!defined('ABSPATH')) exit;

function meditrendy_account_domain_requested_url() {
    $scheme = is_ssl() ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
    $request_uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '/';

    if ($host === '') {
        return '';
    }

    return $scheme . '://' . $host . $request_uri;
}

function meditrendy_account_domain_request_path() {
    $request_uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '/';
    $path = (string) wp_parse_url($request_uri, PHP_URL_PATH);

    return trim($path, '/');
}

function meditrendy_account_domain_is_account_path() {
    $path = meditrendy_account_domain_request_path();

    return $path === 'account' || strpos($path, 'account/') === 0;
}

function meditrendy_account_domain_current_language() {
    $requested_url = meditrendy_account_domain_requested_url();

    if ($requested_url === '' || !function_exists('PLL')) {
        return function_exists('meditrendy_core_current_language') ? meditrendy_core_current_language() : '';
    }

    $requested_host = wp_parse_url($requested_url, PHP_URL_HOST);

    if (!$requested_host || empty(PLL()->model) || !method_exists(PLL()->model, 'get_languages_list')) {
        return '';
    }

    foreach (PLL()->model->get_languages_list() as $language) {
        if (!method_exists($language, 'get_home_url')) {
            continue;
        }

        $language_host = wp_parse_url($language->get_home_url(), PHP_URL_HOST);

        if ($language_host && strtolower($language_host) === strtolower($requested_host)) {
            return (string) $language->slug;
        }
    }

    return function_exists('pll_current_language') ? (string) pll_current_language('slug') : '';
}

function meditrendy_account_domain_page_id($language) {
    $account_page_id = (int) get_option('woocommerce_myaccount_page_id');

    if ($account_page_id <= 0) {
        return 0;
    }

    if ($language !== '' && function_exists('pll_get_post')) {
        $translated_id = (int) pll_get_post($account_page_id, $language);

        if ($translated_id > 0) {
            return $translated_id;
        }
    }

    return $account_page_id;
}

function meditrendy_account_domain_route_request($query_vars) {
    if (is_admin() || !meditrendy_account_domain_is_account_path()) {
        return $query_vars;
    }

    $language = meditrendy_account_domain_current_language();
    $account_page_id = meditrendy_account_domain_page_id($language);

    if ($account_page_id <= 0) {
        return $query_vars;
    }

    $query_vars['page_id'] = $account_page_id;
    $query_vars['pagename'] = get_page_uri($account_page_id);

    if (function_exists('WC') && WC()->query) {
        $path_parts = explode('/', meditrendy_account_domain_request_path());
        $endpoint = $path_parts[1] ?? '';

        if ($endpoint !== '') {
            foreach (WC()->query->get_query_vars() as $query_var => $endpoint_slug) {
                if ($endpoint_slug === $endpoint) {
                    $query_vars[$query_var] = isset($path_parts[2]) ? sanitize_text_field($path_parts[2]) : '';
                    break;
                }
            }
        }
    }

    return $query_vars;
}
add_filter('request', 'meditrendy_account_domain_route_request', 1);

function meditrendy_account_domain_keep_canonical_on_current_host($redirect_url) {
    if (!meditrendy_account_domain_is_account_path()) {
        return $redirect_url;
    }

    $requested_host = wp_parse_url(meditrendy_account_domain_requested_url(), PHP_URL_HOST);
    $redirect_host = is_string($redirect_url) ? wp_parse_url($redirect_url, PHP_URL_HOST) : '';

    if ($requested_host && $redirect_host && strtolower($requested_host) !== strtolower($redirect_host)) {
        return false;
    }

    return $redirect_url;
}
add_filter('redirect_canonical', 'meditrendy_account_domain_keep_canonical_on_current_host', 1);
add_filter('pll_check_canonical_url', 'meditrendy_account_domain_keep_canonical_on_current_host', 1);
