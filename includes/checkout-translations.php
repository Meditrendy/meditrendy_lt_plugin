<?php

if (!defined('ABSPATH')) exit;

/**
 * Meditrendy checkout translation overrides for WooCommerce Blocks.
 */

function meditrendy_checkout_translation_map() {
    return [
        'Coupon code "%s" has been applied to your cart.' => 'Nuolaidos kodas „%s“ pritaikytas jūsų krepšeliui.',
        'Coupon code "%s" has been removed from your cart.' => 'Nuolaidos kodas „%s“ pašalintas iš jūsų krepšelio.',
        'Including %s VAT' => 'Įskaitant %s PVM',
    ];
}

function meditrendy_translate_woocommerce_checkout_string($translation, $text, $domain) {
    $translations = meditrendy_checkout_translation_map();

    return $translations[$text] ?? $translation;
}

add_filter('gettext_woocommerce', 'meditrendy_translate_woocommerce_checkout_string', 20, 3);

function meditrendy_translate_price_display_suffix($value) {
    if (!is_string($value) || '' === trim($value)) {
        return $value;
    }

    $normalized = strtolower(trim($value));

    if ('including {price_including_tax} vat' === $normalized) {
        return 'Įskaitant {price_including_tax} PVM';
    }

    if ('including {price_excluding_tax} vat' === $normalized) {
        return 'Įskaitant {price_excluding_tax} PVM';
    }

    return $value;
}

add_filter('option_woocommerce_price_display_suffix', 'meditrendy_translate_price_display_suffix', 20);

function meditrendy_enqueue_checkout_block_translations() {
    if (is_admin()) {
        return;
    }

    $is_cart_or_checkout = (function_exists('is_cart') && is_cart()) || (function_exists('is_checkout') && is_checkout());

    if (!$is_cart_or_checkout) {
        return;
    }

    meditrendy_add_checkout_block_translation_script('wp-i18n');
}

add_action('wp_enqueue_scripts', 'meditrendy_enqueue_checkout_block_translations', 5);

function meditrendy_reapply_checkout_block_translations() {
    if (is_admin()) {
        return;
    }

    $is_cart_or_checkout = (function_exists('is_cart') && is_cart()) || (function_exists('is_checkout') && is_checkout());

    if (!$is_cart_or_checkout) {
        return;
    }

    foreach ([
        'wc-cart-checkout-base',
        'wc-blocks-checkout',
        'wc-cart-block-frontend',
        'wc-checkout-block-frontend',
    ] as $handle) {
        if (wp_script_is($handle, 'registered')) {
            meditrendy_add_checkout_block_translation_script($handle);
        }
    }
}

add_action('wp_enqueue_scripts', 'meditrendy_reapply_checkout_block_translations', 100);

function meditrendy_add_checkout_block_translation_script($handle) {
    static $fallback_added = false;

    wp_enqueue_script('wp-i18n');

    if (!wp_script_is($handle, 'registered') && !wp_script_is($handle, 'enqueued')) {
        return;
    }

    $translations = [
        'Coupon code "%s" has been applied to your cart.' => ['Nuolaidos kodas „%s“ pritaikytas jūsų krepšeliui.'],
        'Coupon code "%s" has been removed from your cart.' => ['Nuolaidos kodas „%s“ pašalintas iš jūsų krepšelio.'],
        'Including %s VAT' => ['Įskaitant %s PVM'],
    ];

    wp_add_inline_script(
        $handle,
        'window.wp && window.wp.i18n && window.wp.i18n.setLocaleData(' . wp_json_encode($translations) . ', "woocommerce");',
        'after'
    );

    if ($fallback_added) {
        return;
    }

    $fallback_added = true;

    wp_add_inline_script(
        $handle,
        <<<'JS'
(function () {
    if (window.meditrendyCheckoutVatTranslationReady) {
        return;
    }

    window.meditrendyCheckoutVatTranslationReady = true;

    const translateVatText = (root) => {
        if (!root) {
            return;
        }

        const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
        const nodes = [];

        while (walker.nextNode()) {
            nodes.push(walker.currentNode);
        }

        nodes.forEach((node) => {
            const text = node.nodeValue || '';

            if (/^Including\s+(.+?)\s+VAT$/i.test(text.trim())) {
                node.nodeValue = text.replace(/Including\s+(.+?)\s+VAT/i, '\u012eskaitant $1 PVM');
            }
        });
    };

    const run = () => translateVatText(document.body);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }

    const startObserver = () => {
        if (!document.body) {
            return;
        }

        let scheduled = false;
        const observer = new MutationObserver(() => {
            if (scheduled) {
                return;
            }

            scheduled = true;
            window.requestAnimationFrame(() => {
                scheduled = false;
                run();
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true,
            characterData: true
        });
    };

    if (document.body) {
        startObserver();
    } else {
        document.addEventListener('DOMContentLoaded', startObserver);
    }
})();
JS,
        'after'
    );
}
