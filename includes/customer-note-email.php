<?php
if (!defined('ABSPATH')) exit;

/**
 * Use the storefront language for the WooCommerce customer-note email subject.
 *
 * WooCommerce translation coverage for the newer default subject varies by
 * language. Resolve it from the WordPress Site Language so an administrator's
 * profile language cannot leak into a customer email.
 */
function meditrendy_customer_note_email_subject($subject, $order, $email) {
    if (!function_exists('meditrendy_core_current_language')) {
        return $subject;
    }

    $language = meditrendy_core_current_language();
    $locales = [
        'en' => 'en_GB',
        'et' => 'et',
        'lt' => 'lt_LT',
        'lv' => 'lv',
        'pl' => 'pl_PL',
    ];

    if (!isset($locales[$language])) {
        return $subject;
    }

    $site_title = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
    $switched_locale = switch_to_locale($locales[$language]);

    $subject = sprintf(
        /* translators: %s: storefront name. */
        __('Prie Jūsų užsakymo pridėta pastaba iš %s', 'meditrendy-core'),
        $site_title
    );

    if ($switched_locale) {
        restore_previous_locale();
    }

    return $subject;
}
add_filter('woocommerce_email_subject_customer_note', 'meditrendy_customer_note_email_subject', 10, 3);
