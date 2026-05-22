<?php
if (!defined('ABSPATH')) exit;

function meditrendy_subcategories_parent_term($atts) {
    if (!empty($atts['parent'])) {
        $parent = sanitize_title($atts['parent']);
        $term = get_term_by('slug', $parent, 'product_cat');

        if (!$term && is_numeric($atts['parent'])) {
            $term = get_term(absint($atts['parent']), 'product_cat');
        }

        return ($term && !is_wp_error($term)) ? $term : null;
    }

    if (function_exists('is_product_category') && is_product_category()) {
        $queried = get_queried_object();

        if ($queried && !empty($queried->term_id) && $queried->taxonomy === 'product_cat') {
            return $queried;
        }
    }

    return null;
}

function meditrendy_subcategories_get_children($parent_id, $hide_empty) {
    $children = get_terms([
        'taxonomy'   => 'product_cat',
        'parent'     => (int) $parent_id,
        'hide_empty' => $hide_empty,
        'orderby'    => 'menu_order',
        'order'      => 'ASC',
    ]);

    return is_wp_error($children) ? [] : $children;
}

function meditrendy_subcategories_shortcode($atts) {
    $atts = shortcode_atts(
        [
            'parent'     => '',
            'hide_empty' => '1',
            'depth'      => '1',
            'show_count' => '0',
            'class'      => '',
            'fallback'   => 'siblings',
        ],
        $atts,
        'meditrendy_subcategories'
    );

    $parent = meditrendy_subcategories_parent_term($atts);
    $current = null;

    if (!$parent) {
        return '';
    }

    if (function_exists('is_product_category') && is_product_category()) {
        $queried = get_queried_object();

        if ($queried && !empty($queried->term_id) && $queried->taxonomy === 'product_cat') {
            $current = $queried;
        }
    }

    $hide_empty = $atts['hide_empty'] !== '0';
    $children = meditrendy_subcategories_get_children($parent->term_id, $hide_empty);

    if (!$children && empty($atts['parent']) && $current && !empty($current->parent) && $atts['fallback'] !== '0') {
        $fallback_parent = get_term((int) $current->parent, 'product_cat');

        if ($fallback_parent && !is_wp_error($fallback_parent)) {
            $parent = $fallback_parent;
            $children = meditrendy_subcategories_get_children($parent->term_id, $hide_empty);
        }
    }

    if (!$children) {
        return '';
    }

    $classes = trim('mt-subcategories ' . sanitize_html_class($atts['class']));
    $show_count = $atts['show_count'] === '1';
    $depth = max(1, absint($atts['depth']));

    ob_start();
    ?>
    <nav class="<?php echo esc_attr($classes); ?>" aria-label="<?php echo esc_attr($parent->name); ?> subcategories">
        <ul class="mt-subcategories-list">
            <?php foreach ($children as $child) : ?>
                <?php
                $url = get_term_link($child);

                if (is_wp_error($url)) {
                    continue;
                }
                ?>
                <?php $is_current = $current && (int) $current->term_id === (int) $child->term_id; ?>
                <li class="mt-subcategory<?php echo $is_current ? ' is-current' : ''; ?>">
                    <a class="mt-subcategory-link" href="<?php echo esc_url($url); ?>"<?php echo $is_current ? ' aria-current="page"' : ''; ?>>
                        <span><?php echo esc_html($child->name); ?></span>
                        <?php if ($show_count) : ?>
                            <span class="mt-subcategory-count"><?php echo esc_html((int) $child->count); ?></span>
                        <?php endif; ?>
                    </a>

                    <?php if ($depth > 1) : ?>
                        <?php echo meditrendy_subcategories_shortcode([ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            'parent'     => $child->slug,
                            'hide_empty' => $atts['hide_empty'],
                            'depth'      => (string) ($depth - 1),
                            'show_count' => $atts['show_count'],
                            'class'      => 'mt-subcategories-nested',
                        ]); ?>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
    <?php

    return ob_get_clean();
}
add_shortcode('meditrendy_subcategories', 'meditrendy_subcategories_shortcode');

function meditrendy_subcategories_enqueue_assets() {
    if (is_admin()) {
        return;
    }

    $css_path = MEDITRENDY_CORE_DIR . 'assets/css/product-subcategories.css';

    if (file_exists($css_path)) {
        wp_enqueue_style(
            'meditrendy-subcategories',
            MEDITRENDY_CORE_URL . 'assets/css/product-subcategories.css',
            [],
            filemtime($css_path)
        );
    }
}
add_action('wp_enqueue_scripts', 'meditrendy_subcategories_enqueue_assets', 30);
