<?php

function meditrendy_product_card_badge_label($text) {
    if (function_exists('meditrendy_core_translate_ui_text')) {
        return meditrendy_core_translate_ui_text($text);
    }

    return __($text, 'meditrendy-core');
}

function meditrendy_product_card_badge_term_slugs($type) {
    $slugs = [
        'new' => ['naujienos-moterims', 'naujienos-vyrams'],
        'bestseller' => ['bestseleriai-moterims', 'bestseleriai'],
    ];

    return $slugs[$type] ?? [];
}

function meditrendy_product_card_badge_term_matches($term, $target_slugs) {
    if (!$term instanceof WP_Term || $term->taxonomy !== 'product_cat') {
        return false;
    }

    return in_array($term->slug, $target_slugs, true);
}

function meditrendy_product_card_product_has_badge_term($product_id, $target_slugs) {
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

    if ($atts['sale'] === '1' && $product->is_on_sale()) {
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
    <div class="mt-card-badges-shortcode" aria-label="<?php echo esc_attr__('Produktų žymos', 'meditrendy-core'); ?>">
        <?php foreach ($badges as $badge) : ?>
            <span class="<?php echo esc_attr('mt-card-badge ' . $badge['class']); ?>"><?php echo esc_html($badge['label']); ?></span>
        <?php endforeach; ?>
    </div>
    <?php

    return ob_get_clean();
}

add_shortcode('mt_product_card_badges', 'meditrendy_product_card_badges_shortcode');
