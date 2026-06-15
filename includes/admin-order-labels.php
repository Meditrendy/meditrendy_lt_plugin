<?php
if (!defined('ABSPATH')) exit;

/**
 * Admin-only order label adjustments.
 */

function meditrendy_admin_order_labels_is_order_screen() {
    if (!is_admin()) {
        return false;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    $screen_id = $screen ? (string) $screen->id : '';

    return in_array($screen_id, ['shop_order', 'woocommerce_page_wc-orders'], true);
}

function meditrendy_admin_order_labels_buyer_address($translation, $text, $domain) {
    if ($domain !== 'woocommerce' || !meditrendy_admin_order_labels_is_order_screen()) {
        return $translation;
    }

    $normalized_text = trim(wp_strip_all_tags((string) $text));
    $normalized_translation = trim(wp_strip_all_tags((string) $translation));
    $billing_labels = [
        'Billing address',
        'Dane do faktury',
        'Adresas sąskaitai',
        'Pirkėjo adresas',
    ];

    if (!in_array($normalized_text, $billing_labels, true) && !in_array($normalized_translation, $billing_labels, true)) {
        return $translation;
    }

    $locale = function_exists('determine_locale') ? determine_locale() : get_locale();

    if (strpos($locale, 'pl_') === 0) {
        return 'Adres kupującego';
    }

    if (strpos($locale, 'lt_') === 0) {
        return 'Pirkėjo adresas';
    }

    return 'Buyer address';
}
add_filter('gettext', 'meditrendy_admin_order_labels_buyer_address', 30, 3);
