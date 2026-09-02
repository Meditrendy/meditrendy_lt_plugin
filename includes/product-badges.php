<?php

function meditrendy_product_card_badge_language() {
    if (function_exists('meditrendy_core_current_language')) {
        $language = meditrendy_core_current_language();

        return $language === 'ee' ? 'et' : $language;
    }

    if (function_exists('pll_current_language')) {
        $language = strtolower((string) pll_current_language('slug'));

        if ($language) {
            if ($language === 'ee') {
                return 'et';
            }

            return $language;
        }
    }

    $locale = function_exists('determine_locale') ? determine_locale() : get_locale();
    $locale = strtolower((string) $locale);

    if (strpos($locale, 'et') === 0) {
        return 'et';
    }

    if (strpos($locale, 'lv') === 0) {
        return 'lv';
    }

    if (strpos($locale, 'pl') === 0) {
        return 'pl';
    }

    if (strpos($locale, 'en') === 0) {
        return 'en';
    }

    return 'lt';
}

function meditrendy_product_card_badge_label($text) {
    $language = meditrendy_product_card_badge_language();

    $translations = [
        'lt' => [
            'AKCIJA' => 'AKCIJA',
            'NAUJIENA' => 'NAUJIENA',
            'BESTSELLER' => 'BESTSELLER',
            'Produktų žymos' => 'Produktų žymos',
        ],
        'en' => [
            'AKCIJA' => 'SALE',
            'NAUJIENA' => 'NEW',
            'BESTSELLER' => 'BESTSELLER',
            'Produktów žymos' => 'Product badges',
            'Produktų žymos' => 'Product badges',
        ],
        'lv' => [
            'AKCIJA' => 'AKCIJA',
            'NAUJIENA' => 'JAUNUMS',
            'BESTSELLER' => 'BESTSELLER',
            'Produktų žymos' => 'Produktu atzīmes',
        ],
        'pl' => [
            'AKCIJA' => 'PROMOCJA',
            'NAUJIENA' => 'NOWOŚĆ',
            'BESTSELLER' => 'BESTSELLER',
            'Produktów žymos' => 'Etykiety produktu',
            'Produktų žymos' => 'Etykiety produktu',
        ],
        'et' => [
            'AKCIJA' => 'SOODUS',
            'NAUJIENA' => 'UUS',
            'BESTSELLER' => 'ENIMMÜÜDUD',
            'Produktų žymos' => 'Tootesildid',
            'ProduktĹł Ĺľymos' => 'Tootesildid',
        ],
    ];

    // Country storefronts are separate installations. Their resolved storefront
    // language must take precedence over a text domain loaded from an administrator
    // profile locale (for example Polish gettext strings leaking onto the EE shop).
    if (isset($translations[$language][$text])) {
        return $translations[$language][$text];
    }

    $gettext = __($text, 'meditrendy-core');

    if ($gettext !== $text) {
        return $gettext;
    }

    if (function_exists('meditrendy_core_translate_ui_text')) {
        return meditrendy_core_translate_ui_text($text);
    }

    return __($text, 'meditrendy-core');
}

function meditrendy_product_card_badge_term_slugs($type) {
    $slugs = [
        'sale' => [
            'akcijos-moterims',
            'akcijos-vyrams',
            'akcijas',
            'akcijas-viriesiem',
            'promocje',
            'promocje-meskie',
            'sale',
            'sale-women',
            'sale-men',
            'sooduspakkumised',
            'sooduspakkumised-meestele',
        ],
        'new' => [
            'naujienos-moterims',
            'naujienos-vyrams',
            'jaunumi',
            'jaunumi-viriesiem',
            'nowosci',
            'nowosci-meskie',
            'new-arrivals',
            'new-arrivals-women',
            'new-arrivals-men',
            'uudised',
            'uudised-meestele',
        ],
        'bestseller' => [
            'bestseleriai-moterims',
            'bestseleriai',
            'popularakie-produkti',
            'popularakie-produkti-viriesiem',
            'bestsellery',
            'bestsellery-meskie',
            'bestsellers',
            'bestsellers-women',
            'bestsellers-men',
            'enimmuudud',
            'enimmuudud-meestele',
        ],
    ];

    return $slugs[$type] ?? [];
}

function meditrendy_product_card_badge_expanded_term_slugs($target_slugs) {
    static $cache = [];

    $target_slugs = array_values(array_unique(array_filter(array_map('sanitize_title', (array) $target_slugs))));
    $cache_key = implode('|', $target_slugs);

    if (isset($cache[$cache_key])) {
        return $cache[$cache_key];
    }

    $slugs = $target_slugs;

    foreach ($target_slugs as $slug) {
        $term = get_term_by('slug', $slug, 'product_cat');

        if (!$term instanceof WP_Term || is_wp_error($term)) {
            continue;
        }

        if (function_exists('pll_get_term_translations')) {
            $translation_ids = pll_get_term_translations($term->term_id);

            if (is_array($translation_ids)) {
                foreach ($translation_ids as $translation_id) {
                    $translation = get_term((int) $translation_id, 'product_cat');

                    if ($translation instanceof WP_Term && !is_wp_error($translation)) {
                        $slugs[] = $translation->slug;
                    }
                }
            }
        }
    }

    $cache[$cache_key] = array_values(array_unique(array_filter($slugs)));

    return $cache[$cache_key];
}

function meditrendy_product_card_badge_term_matches($term, $target_slugs) {
    if (!$term instanceof WP_Term || $term->taxonomy !== 'product_cat') {
        return false;
    }

    return in_array($term->slug, meditrendy_product_card_badge_expanded_term_slugs($target_slugs), true);
}

function meditrendy_product_card_product_has_badge_term($product_id, $target_slugs) {
    $target_slugs = meditrendy_product_card_badge_expanded_term_slugs($target_slugs);

    if (has_term($target_slugs, 'product_cat', $product_id)) {
        return true;
    }

    $terms = get_the_terms($product_id, 'product_cat');

    if (!is_array($terms)) {
        return false;
    }

    foreach ($terms as $term) {
        if (meditrendy_product_card_badge_term_matches($term, $target_slugs)) {
            return true;
        }
    }

    return false;
}

function meditrendy_product_card_badge_product_ids($product) {
    if (!$product instanceof WC_Product) {
        return [];
    }

    $product_ids = [absint($product->get_id())];

    if ($product->is_type('variation')) {
        $parent_id = absint($product->get_parent_id());

        if ($parent_id) {
            $product_ids[] = $parent_id;
        }
    }

    return array_values(array_unique(array_filter($product_ids)));
}

function meditrendy_product_card_product_has_any_badge_term($product, $target_slugs) {
    foreach (meditrendy_product_card_badge_product_ids($product) as $product_id) {
        if (meditrendy_product_card_product_has_badge_term($product_id, $target_slugs)) {
            return true;
        }
    }

    return false;
}

function meditrendy_product_card_badges_shortcode($atts) {
    $atts = shortcode_atts(
        [
            'id' => 0,
            'sale' => '1',
            'new' => '1',
            'bestseller' => '1',
        ],
        $atts,
        'mt_product_card_badges'
    );

    $product = meditrendy_product_price_block_product(absint($atts['id']));

    if (!$product instanceof WC_Product) {
        return '';
    }

    $badges = [];

    $sale_slugs = meditrendy_product_card_badge_term_slugs('sale');

    if (
        $atts['sale'] === '1'
        && (
            $product->is_on_sale()
            || meditrendy_product_card_product_has_any_badge_term($product, $sale_slugs)
        )
    ) {
        $badges[] = [
            'class' => 'mt-card-badge-sale',
            'label' => meditrendy_product_card_badge_label('AKCIJA'),
        ];
    }

    $new_slugs = meditrendy_product_card_badge_term_slugs('new');
    $bestseller_slugs = meditrendy_product_card_badge_term_slugs('bestseller');

    if (
        $atts['new'] === '1'
        && meditrendy_product_card_product_has_any_badge_term($product, $new_slugs)
    ) {
        $badges[] = [
            'class' => 'mt-card-badge-new',
            'label' => meditrendy_product_card_badge_label('NAUJIENA'),
        ];
    }

    if (
        $atts['bestseller'] === '1'
        && meditrendy_product_card_product_has_any_badge_term($product, $bestseller_slugs)
    ) {
        $badges[] = [
            'class' => 'mt-card-badge-bestseller',
            'label' => meditrendy_product_card_badge_label('BESTSELLER'),
        ];
    }

    if (!$badges) {
        return '';
    }

    ob_start();
    ?>
    <div class="mt-card-badges-shortcode" aria-label="<?php echo esc_attr(meditrendy_product_card_badge_label('Produktų žymos')); ?>">
        <?php foreach ($badges as $badge) : ?>
            <span class="<?php echo esc_attr('mt-card-badge ' . $badge['class']); ?>"><?php echo esc_html($badge['label']); ?></span>
        <?php endforeach; ?>
    </div>
    <?php

    return ob_get_clean();
}

add_shortcode('mt_product_card_badges', 'meditrendy_product_card_badges_shortcode');
