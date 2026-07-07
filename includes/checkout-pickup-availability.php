<?php
if (!defined('ABSPATH')) exit;

/**
 * Domain/language based visibility for pickup shipping methods.
 */

function meditrendy_checkout_pickup_current_language() {
    if (function_exists('pll_current_language')) {
        $language = sanitize_key((string) pll_current_language('slug'));

        if ($language !== '') {
            return $language;
        }
    }

    $locale = function_exists('determine_locale') ? determine_locale() : get_locale();
    $locale = strtolower((string) $locale);

    if (strpos($locale, 'lv') === 0) {
        return 'lv';
    }

    if (strpos($locale, 'et') === 0 || strpos($locale, 'ee') === 0) {
        return 'et';
    }

    if (strpos($locale, 'lt') === 0) {
        return 'lt';
    }

    return '';
}

function meditrendy_checkout_pickup_current_host() {
    $host = '';

    if (!empty($_SERVER['HTTP_HOST'])) {
        $host = (string) wp_unslash($_SERVER['HTTP_HOST']);
    } else {
        $host = (string) wp_parse_url(home_url('/'), PHP_URL_HOST);
    }

    return strtolower(preg_replace('/:\d+$/', '', $host));
}

function meditrendy_checkout_pickup_allowed_for_current_context() {
    $language = meditrendy_checkout_pickup_current_language();

    if (in_array($language, ['lv', 'et', 'ee'], true)) {
        return false;
    }

    $host = meditrendy_checkout_pickup_current_host();

    if (preg_match('/(^|\.)meditrendy\.(lv|ee)$/', $host)) {
        return false;
    }

    return true;
}

function meditrendy_checkout_pickup_rate_is_pickup($rate) {
    if (!$rate instanceof WC_Shipping_Rate) {
        return false;
    }

    $method_id = is_callable([$rate, 'get_method_id']) ? $rate->get_method_id() : '';
    $rate_id = is_callable([$rate, 'get_id']) ? $rate->get_id() : '';
    $label = is_callable([$rate, 'get_label']) ? $rate->get_label() : '';

    if (function_exists('meditrendy_checkout_shipping_identifier_is_pickup')) {
        return meditrendy_checkout_shipping_identifier_is_pickup($method_id)
            || meditrendy_checkout_shipping_identifier_is_pickup($rate_id)
            || meditrendy_checkout_shipping_identifier_is_pickup($label);
    }

    $haystack = strtolower($method_id . ' ' . $rate_id . ' ' . $label);

    return strpos($haystack, 'local_pickup') !== false
        || strpos($haystack, 'pickup') !== false
        || strpos($haystack, 'collection') !== false
        || strpos($haystack, 'atsi') !== false;
}

function meditrendy_checkout_hide_pickup_rates_by_language($rates, $package) {
    if (meditrendy_checkout_pickup_allowed_for_current_context()) {
        return $rates;
    }

    foreach ((array) $rates as $rate_id => $rate) {
        if (meditrendy_checkout_pickup_rate_is_pickup($rate)) {
            unset($rates[$rate_id]);
        }
    }

    return $rates;
}
add_filter('woocommerce_package_rates', 'meditrendy_checkout_hide_pickup_rates_by_language', 100, 2);

function meditrendy_checkout_disable_blocks_pickup_by_language($settings) {
    if (meditrendy_checkout_pickup_allowed_for_current_context() || !is_array($settings)) {
        return $settings;
    }

    $settings['localPickupEnabled'] = false;
    $settings['localPickupCost'] = '';
    $settings['localPickupLocations'] = [];
    $settings['collectableMethodIds'] = [];

    return $settings;
}
add_filter('woocommerce_shared_settings', 'meditrendy_checkout_disable_blocks_pickup_by_language', 100);

function meditrendy_checkout_pickup_visibility_class($classes) {
    if (!meditrendy_checkout_pickup_allowed_for_current_context()) {
        $classes[] = 'meditrendy-hide-local-pickup';
    }

    return $classes;
}
add_filter('body_class', 'meditrendy_checkout_pickup_visibility_class');

function meditrendy_checkout_pickup_visibility_css() {
    if (is_admin() || meditrendy_checkout_pickup_allowed_for_current_context()) {
        return;
    }
    ?>
    <style>
        .meditrendy-hide-local-pickup .wp-block-woocommerce-checkout-pickup-options-block,
        .meditrendy-hide-local-pickup [class*="pickup-options"],
        .meditrendy-hide-local-pickup [class*="local-pickup"] {
            display: none !important;
        }
    </style>
    <?php
}
add_action('wp_head', 'meditrendy_checkout_pickup_visibility_css', 30);
