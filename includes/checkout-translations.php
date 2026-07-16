<?php

if (!defined('ABSPATH')) exit;

/**
 * Meditrendy checkout and order email translation overrides for WooCommerce.
 */

function meditrendy_checkout_current_language() {
    if (function_exists('meditrendy_core_current_language')) {
        return meditrendy_core_current_language();
    }

    $locale = function_exists('determine_locale') ? determine_locale() : get_locale();

    return substr(strtolower((string) $locale), 0, 2);
}

function meditrendy_checkout_latvian_translation_map() {
    return [
        'Checkout' => 'Norēķināšanās',
        'Order summary' => 'Pasūtījuma kopsavilkums',
        'Add a discount code' => 'Pievienot atlaides kodu',
        'Subtotal' => 'Starpsumma',
        'Delivery' => 'Piegāde',
        'Total' => 'Kopā',
        'Including %s VAT' => 'Ieskaitot %s PVN',
        'Contact information' => 'Kontaktinformācija',
        'Log in' => 'Pieslēgties',
        'Email address' => 'E-pasta adrese',
        'Phone' => 'Tālrunis',
        'You are currently checking out as a guest.' => 'Jūs pašlaik noformējat pasūtījumu kā viesis.',
        'Create an account with %s' => 'Izveidot kontu vietnē %s',
        'Shipping address' => 'Piegādes adrese',
        'Country/region' => 'Valsts/reģions',
        'Select a country / region' => 'Izvēlieties valsti/reģionu',
        'First name' => 'Vārds',
        'Last name' => 'Uzvārds',
        'Address' => 'Adrese',
        'City' => 'Pilsēta',
        'Postcode' => 'Pasta indekss',
        'Shipping options' => 'Piegādes iespējas',
        'Enter an address to see your shipping options.' => 'Ievadiet adresi, lai skatītu piegādes iespējas.',
        'Payment options' => 'Maksājuma iespējas',
        'Choose a payment method' => 'Izvēlieties maksājuma veidu',
        'Please select the country' => 'Lūdzu, izvēlieties valsti',
        'Add a note to your order' => 'Pievienot piezīmi pasūtījumam',
        'Place order' => 'Veikt pasūtījumu',
        'Product' => 'Prece',
        'Quantity' => 'Daudzums',
        'Price' => 'Cena',
    ];
}

function meditrendy_checkout_translation_map() {
    if ('lv' === meditrendy_checkout_current_language()) {
        return meditrendy_checkout_latvian_translation_map();
    }

    if ('lt' !== meditrendy_checkout_current_language()) {
        return [];
    }

    return [
        'Coupon code "%s" has been applied to your cart.' => 'Nuolaidos kodas „%s“ pritaikytas jūsų krepšeliui.',
        'Coupon code "%s" has been removed from your cart.' => 'Nuolaidos kodas „%s“ pašalintas iš jūsų krepšelio.',
        'Including %s VAT' => 'Įskaitant %s PVM',
        'VAT' => 'PVM',
        '(includes %s)' => '(įskaičiuota %s)',
        'Collection from <strong>%s</strong>:' => 'Atsiėmimas iš <strong>%s</strong>:',
        'Collection from %s:' => 'Atsiėmimas iš %s:',
        'Thank you for your order' => 'Dėkojame už užsakymą',
        'Thanks for shopping with us.' => 'Ačiū, kad perkate pas mus.',
        'Thanks again! If you need any help with your order, please contact us at %s.' => 'Dar kartą dėkojame! Jei reikia pagalbos dėl užsakymo, susisiekite su mumis: %s.',
        'Thanks again! If you need any help with your order, please contact us at {store_email}.' => 'Dar kartą dėkojame! Jei reikia pagalbos dėl užsakymo, susisiekite su mumis: {store_email}.',
        'If you need any help with your order, please contact us at {store_email}.' => 'Jei reikia pagalbos dėl užsakymo, susisiekite su mumis: {store_email}.',
        'We look forward to fulfilling your order soon.' => 'Netrukus pradėsime vykdyti jūsų užsakymą.',
        'Hi %s,' => 'Sveiki, %s,',
        'Just to let you know &mdash; we’ve received your order, and it is now being processed.' => 'Informuojame, kad gavome jūsų užsakymą ir šiuo metu jį apdorojame.',
        'Just to let you know — we’ve received your order, and it is now being processed.' => 'Informuojame, kad gavome jūsų užsakymą ir šiuo metu jį apdorojame.',
        'Just to let you know &mdash; we\'ve received your order #%s, and it is now being processed:' => 'Informuojame, kad gavome jūsų užsakymą #%s ir šiuo metu jį apdorojame:',
        'Just to let you know &mdash; we’ve received your order #%s, and it is now being processed:' => 'Informuojame, kad gavome jūsų užsakymą #%s ir šiuo metu jį apdorojame:',
        'Just to let you know &mdash; we\'ve received your order #%s:' => 'Informuojame, kad gavome jūsų užsakymą #%s:',
        'Just to let you know &mdash; we’ve received your order #%s:' => 'Informuojame, kad gavome jūsų užsakymą #%s:',
        'Your order has been received and is now being processed. Your order details are shown below for your reference:' => 'Jūsų užsakymas gautas ir šiuo metu apdorojamas. Užsakymo informacija pateikta žemiau:',
        'Order summary' => 'Užsakymo suvestinė',
        'Order details' => 'Užsakymo informacija',
        'Order #%s' => 'Užsakymas #%s',
        'Order number:' => 'Užsakymo numeris:',
        'Date:' => 'Data:',
        'Product' => 'Produktas',
        'Quantity' => 'Kiekis',
        'Price' => 'Kaina',
        'Subtotal:' => 'Suma:',
        'Shipping:' => 'Pristatymas:',
        'Payment method:' => 'Mokėjimo būdas:',
        'Total:' => 'Viso:',
        'Billing address' => 'Adresas sąskaitai',
        'Shipping address' => 'Pristatymo adresas',
        'Customer details' => 'Pirkėjo informacija',
        'Email:' => 'El. paštas:',
        'Telephone:' => 'Telefonas:',
        'Note:' => 'Pastaba:',
        'Customer note' => 'Pirkėjo pastaba',
        'View order' => 'Peržiūrėti užsakymą',
    ];
}

function meditrendy_translate_woocommerce_checkout_string($translation, $text, $domain) {
    $translations = meditrendy_checkout_translation_map();

    return $translations[$text] ?? $translation;
}

add_filter('gettext_woocommerce', 'meditrendy_translate_woocommerce_checkout_string', 20, 3);

function meditrendy_translate_woocommerce_email_content($content) {
    if (!is_string($content) || '' === $content) {
        return $content;
    }

    $content = str_replace(
        [
            'Just to let you know — we’ve received your order, and it is now being processed.',
            'Just to let you know &mdash; we’ve received your order, and it is now being processed.',
        ],
        'Informuojame, kad gavome jūsų užsakymą ir šiuo metu jį apdorojame.',
        $content
    );

    $content = preg_replace(
        '/Thanks again!\s*If you need any help with your order,\s*please contact us at ([^<.]+(?:\.[^<.]+)*?)\./u',
        'Dar kartą dėkojame! Jei reikia pagalbos dėl užsakymo, susisiekite su mumis: $1.',
        $content
    );

    $content = preg_replace('/Collection from\s+(<strong>)?(.+?)(<\/strong>)?:/u', 'Atsiėmimas iš $1$2$3:', $content);
    $content = str_replace([' VAT)', ' VAT<', ' VAT '], [' PVM)', ' PVM<', ' PVM '], $content);

    return $content;
}

add_filter('woocommerce_mail_content', 'meditrendy_translate_woocommerce_email_content', 30);

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

    $translations = meditrendy_checkout_translation_map();

    if (empty($translations)) {
        return;
    }

    wp_enqueue_script('wp-i18n');

    if (!wp_script_is($handle, 'registered') && !wp_script_is($handle, 'enqueued')) {
        return;
    }

    $translations = [
        'Coupon code "%s" has been applied to your cart.' => ['Nuolaidos kodas „%s“ pritaikytas jūsų krepšeliui.'],
        'Coupon code "%s" has been removed from your cart.' => ['Nuolaidos kodas „%s“ pašalintas iš jūsų krepšelio.'],
        'Including %s VAT' => ['Įskaitant %s PVM'],
    ];

    $translations = array_map(
        static function ($translation) {
            return [$translation];
        },
        $translations
    );

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
