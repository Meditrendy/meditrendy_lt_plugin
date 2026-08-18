<?php
if (!defined('ABSPATH')) exit;

/**
 * Use the storefront language for the WooCommerce customer-note email subject.
 *
 * WooCommerce's Lithuanian catalog does not yet contain the newer default
 * subject introduced with the email improvements, so it otherwise falls back
 * to English. Other storefronts can continue using WooCommerce's own localized
 * subject.
 */
function meditrendy_customer_note_email_subject($subject, $order, $email) {
    if (!function_exists('meditrendy_core_current_language')) {
        return $subject;
    }

    if ('lt' !== meditrendy_core_current_language()) {
        return $subject;
    }

    $site_title = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);

    return sprintf(
        /* translators: %s: storefront name. */
        __('Prie Jūsų užsakymo pridėta pastaba iš %s', 'meditrendy-core'),
        $site_title
    );
}
add_filter('woocommerce_email_subject_customer_note', 'meditrendy_customer_note_email_subject', 10, 3);
