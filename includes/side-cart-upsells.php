<?php
if (!defined('ABSPATH')) exit;

function meditrendy_side_cart_upsells_capability() {
    return current_user_can('manage_woocommerce') ? 'manage_woocommerce' : 'manage_options';
}

function meditrendy_side_cart_upsells_default_languages() {
    $languages = [];

    if (function_exists('pll_languages_list')) {
        $slugs = pll_languages_list(['fields' => 'slug']);

        if (is_array($slugs)) {
            foreach ($slugs as $slug) {
                $slug = sanitize_key($slug);

                if ($slug !== '') {
                    $languages[] = $slug;
                }
            }
        }
    }

    if (!$languages) {
        $languages = ['lt', 'lv', 'et', 'pl', 'en'];
    }

    return array_values(array_unique($languages));
}

function meditrendy_side_cart_upsells_ids($value) {
    if (!is_array($value)) {
        $value = preg_split('/[\s,]+/', (string) $value);
    }

    $ids = [];

    foreach ($value as $id) {
        $id = absint($id);

        if ($id && !in_array($id, $ids, true)) {
            $ids[] = $id;
        }

        if (count($ids) >= 10) {
            break;
        }
    }

    return $ids;
}

function meditrendy_side_cart_upsells_sanitize($input) {
    $input = is_array($input) ? $input : [];
    $output = [];

    foreach (meditrendy_side_cart_upsells_default_languages() as $language) {
        $output[$language] = meditrendy_side_cart_upsells_ids($input[$language] ?? []);
    }

    foreach ($input as $language => $ids) {
        $language = sanitize_key($language);

        if ($language === '' || isset($output[$language])) {
            continue;
        }

        $output[$language] = meditrendy_side_cart_upsells_ids($ids);
    }

    return $output;
}

function meditrendy_side_cart_upsells_migrate_legacy_settings($settings) {
    if (!$settings || !is_array($settings) || array_key_exists('lt', $settings)) {
        return is_array($settings) ? $settings : [];
    }

    $ids = [];

    foreach ($settings as $rule) {
        if (!is_array($rule) || empty($rule['enabled'])) {
            continue;
        }

        $ids = array_merge($ids, meditrendy_side_cart_upsells_ids($rule['first_product_id'] ?? 0));
        $ids = array_merge($ids, meditrendy_side_cart_upsells_ids($rule['manual_product_ids'] ?? []));

        if (count(array_unique(array_filter($ids))) >= 10) {
            break;
        }
    }

    return [
        'lt' => meditrendy_side_cart_upsells_ids($ids),
    ];
}

function meditrendy_side_cart_upsells_settings() {
    $settings = get_option('meditrendy_side_cart_upsells', []);
    $settings = meditrendy_side_cart_upsells_migrate_legacy_settings($settings);

    return meditrendy_side_cart_upsells_sanitize($settings);
}

function meditrendy_side_cart_upsells_enabled() {
    return get_option('meditrendy_side_cart_upsells_enabled', 'yes') === 'yes';
}

function meditrendy_side_cart_upsells_has_configured_products($language = '') {
    if (!meditrendy_side_cart_upsells_enabled()) {
        return false;
    }

    $language = $language !== '' ? sanitize_key($language) : meditrendy_side_cart_upsells_active_language();
    $settings = meditrendy_side_cart_upsells_settings();
    $ids = $settings[$language] ?? [];

    return !empty(meditrendy_side_cart_upsells_ids($ids));
}

function meditrendy_side_cart_upsells_cache_version() {
    return (string) get_option('meditrendy_side_cart_upsells_cache_version', '1');
}

function meditrendy_side_cart_upsells_flush_cache() {
    update_option('meditrendy_side_cart_upsells_cache_version', (string) time(), false);
}

function meditrendy_side_cart_upsells_language_label($language) {
    if (function_exists('pll_languages_list')) {
        $slugs = pll_languages_list(['fields' => 'slug']);
        $names = pll_languages_list(['fields' => 'name']);

        if (is_array($slugs) && is_array($names)) {
            foreach ($slugs as $index => $slug) {
                $slug = (string) $slug;

                if ($slug === (string) $language) {
                    return !empty($names[$index]) ? (string) $names[$index] : strtoupper((string) $language);
                }
            }
        }
    }

    return strtoupper((string) $language);
}

function meditrendy_side_cart_upsells_admin_menu() {
    add_submenu_page(
        'meditrendy-settings',
        __('Side cart upsells', 'meditrendy-core'),
        __('Side cart upsells', 'meditrendy-core'),
        meditrendy_side_cart_upsells_capability(),
        'meditrendy-side-cart-upsells',
        'meditrendy_side_cart_upsells_admin_page'
    );
}
add_action('admin_menu', 'meditrendy_side_cart_upsells_admin_menu', 20);

function meditrendy_side_cart_upsells_admin_assets($hook) {
    if ($hook !== 'meditrendy_page_meditrendy-side-cart-upsells') {
        return;
    }

    if (function_exists('wp_enqueue_select2')) {
        wp_enqueue_select2();
    }

    wp_enqueue_script('wc-enhanced-select');
    wp_enqueue_style('woocommerce_admin_styles');

    wp_register_style('meditrendy-side-cart-upsells-admin', false, [], '1.0');
    wp_enqueue_style('meditrendy-side-cart-upsells-admin');
    wp_add_inline_style('meditrendy-side-cart-upsells-admin', '
        .meditrendy-side-cart-upsells-admin .mt-upsell-language {
            max-width: 760px;
            margin: 0 0 18px;
            padding: 16px;
            border: 1px solid #c3c4c7;
            background: #fff;
        }

        .meditrendy-side-cart-upsells-admin .mt-upsell-language h2 {
            margin: 0 0 10px;
            font-size: 16px;
            line-height: 1.3;
        }

        .meditrendy-side-cart-upsells-admin .wc-product-search,
        .meditrendy-side-cart-upsells-admin .select2-container {
            width: 100% !important;
        }

        .meditrendy-side-cart-upsells-admin .mt-upsell-products {
            margin: 10px 0 0;
            padding-left: 20px;
        }

        .meditrendy-side-cart-upsells-admin .mt-upsell-products li {
            margin: 3px 0;
        }

        .meditrendy-side-cart-upsells-admin .mt-upsell-missing {
            color: #b32d2e;
        }

        .meditrendy-side-cart-upsells-admin .mt-upsell-global {
            max-width: 760px;
            margin: 16px 0;
            padding: 14px 16px;
            border: 1px solid #c3c4c7;
            background: #fff;
        }

        .meditrendy-side-cart-upsells-admin .mt-upsell-global label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
        }
    ');

    wp_register_script('meditrendy-side-cart-upsells-admin', false, ['jquery', 'wc-enhanced-select'], '1.0', true);
    wp_enqueue_script('meditrendy-side-cart-upsells-admin');
    wp_add_inline_script('meditrendy-side-cart-upsells-admin', "
        jQuery(function($) {
            $(document.body).trigger('wc-enhanced-select-init');

            $(document).on('select2:selecting', '.meditrendy-side-cart-upsells-admin .wc-product-search', function(e) {
                var selected = $(this).val() || [];

                if (selected.length >= 10) {
                    e.preventDefault();
                    window.alert('" . esc_js(__('You can select up to 10 products per language.', 'meditrendy-core')) . "');
                }
            });
        });
    ");
}
add_action('admin_enqueue_scripts', 'meditrendy_side_cart_upsells_admin_assets');

function meditrendy_side_cart_upsells_search_products() {
    check_ajax_referer('search-products', 'security');

    if (!current_user_can(meditrendy_side_cart_upsells_capability())) {
        wp_send_json([]);
    }

    $term = isset($_GET['term']) ? wc_clean(wp_unslash($_GET['term'])) : '';
    $term = trim((string) $term);

    if ($term === '') {
        wp_send_json([]);
    }

    global $wpdb;

    $limit = !empty($_GET['limit']) ? absint($_GET['limit']) : 30;
    $limit = max(1, min(50, $limit));
    $like = '%' . $wpdb->esc_like($term) . '%';

    $product_ids = $wpdb->get_col($wpdb->prepare(
        "
        SELECT DISTINCT p.ID
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->posts} parent_post
            ON parent_post.ID = p.post_parent
        LEFT JOIN {$wpdb->postmeta} sku_meta
            ON sku_meta.post_id = p.ID
            AND sku_meta.meta_key = '_sku'
        LEFT JOIN {$wpdb->postmeta} parent_sku_meta
            ON parent_sku_meta.post_id = parent_post.ID
            AND parent_sku_meta.meta_key = '_sku'
        WHERE p.post_type IN ('product', 'product_variation')
            AND p.post_status IN ('publish', 'private')
            AND (
                p.post_title LIKE %s
                OR parent_post.post_title LIKE %s
                OR sku_meta.meta_value LIKE %s
                OR parent_sku_meta.meta_value LIKE %s
            )
        ORDER BY
            CASE
                WHEN p.post_title LIKE %s THEN 0
                WHEN parent_post.post_title LIKE %s THEN 1
                WHEN sku_meta.meta_value LIKE %s THEN 2
                WHEN parent_sku_meta.meta_value LIKE %s THEN 3
                ELSE 4
            END,
            COALESCE(parent_post.post_title, p.post_title) ASC,
            p.ID ASC
        LIMIT %d
        ",
        $like,
        $like,
        $like,
        $like,
        $like,
        $like,
        $like,
        $like,
        $limit
    ));

    $results = [];

    foreach ($product_ids as $product_id) {
        $product = wc_get_product($product_id);

        if (!$product || !wc_products_array_filter_readable($product)) {
            continue;
        }

        if (!$product->is_type('simple') && !$product->is_type('variation')) {
            continue;
        }

        $label = rawurldecode(wp_strip_all_tags($product->get_formatted_name()));

        if ($product->is_type('variation')) {
            $label = sprintf(__('Variation #%1$d - %2$s', 'meditrendy-core'), $product->get_id(), $label);
        } else {
            $label = sprintf(__('Product #%1$d - %2$s', 'meditrendy-core'), $product->get_id(), $label);
        }

        $results[$product->get_id()] = $label;
    }

    wp_send_json($results);
}
add_action('wp_ajax_meditrendy_side_cart_upsells_search_products', 'meditrendy_side_cart_upsells_search_products');

function meditrendy_side_cart_upsells_product_search_field($language, $ids) {
    ?>
    <select
        class="wc-product-search"
        multiple="multiple"
        name="meditrendy_side_cart_upsells[<?php echo esc_attr($language); ?>][]"
        data-placeholder="<?php esc_attr_e('Search products by name or SKU...', 'meditrendy-core'); ?>"
        data-action="meditrendy_side_cart_upsells_search_products"
        data-minimum_input_length="1"
        data-limit="30"
    >
        <?php foreach (meditrendy_side_cart_upsells_ids($ids) as $product_id) : ?>
            <?php $product = wc_get_product($product_id); ?>
            <?php if ($product) : ?>
                <option value="<?php echo esc_attr($product_id); ?>" selected><?php echo esc_html(sprintf('#%1$d - %2$s', $product_id, $product->get_formatted_name())); ?></option>
            <?php endif; ?>
        <?php endforeach; ?>
    </select>
    <?php
}

function meditrendy_side_cart_upsells_admin_product_list($ids) {
    if (!$ids) {
        echo '<p class="description">' . esc_html__('No products selected.', 'meditrendy-core') . '</p>';
        return;
    }

    echo '<ol class="mt-upsell-products">';

    foreach ($ids as $id) {
        $product = function_exists('wc_get_product') ? wc_get_product($id) : null;

        if (!$product) {
            echo '<li class="mt-upsell-missing">' . esc_html(sprintf(__('Product ID %d was not found.', 'meditrendy-core'), $id)) . '</li>';
            continue;
        }

        echo '<li>' . esc_html(sprintf('#%d - %s', $id, $product->get_formatted_name())) . '</li>';
    }

    echo '</ol>';
}

function meditrendy_side_cart_upsells_admin_page() {
    if (!current_user_can(meditrendy_side_cart_upsells_capability())) {
        wp_die(esc_html__('You do not have permission to view this page.', 'meditrendy-core'));
    }

    $settings = meditrendy_side_cart_upsells_settings();
    $languages = meditrendy_side_cart_upsells_default_languages();
    $enabled = meditrendy_side_cart_upsells_enabled();
    ?>
    <div class="wrap meditrendy-side-cart-upsells-admin">
        <h1><?php esc_html_e('Side cart upsells', 'meditrendy-core'); ?></h1>

        <?php if (isset($_GET['updated'])) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Upsells saved and cache cleared.', 'meditrendy-core'); ?></p></div>
        <?php endif; ?>

        <p><?php esc_html_e('Enter up to 10 exact product IDs per language. The side cart uses only these IDs and does not run cart matching, taxonomy searches, random queries, or cart-item exclusions.', 'meditrendy-core'); ?></p>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('meditrendy_save_side_cart_upsells'); ?>
            <input type="hidden" name="action" value="meditrendy_save_side_cart_upsells">

            <section class="mt-upsell-global">
                <label>
                    <input type="checkbox" name="meditrendy_side_cart_upsells_enabled" value="yes" <?php checked($enabled); ?>>
                    <?php esc_html_e('Enable side cart upsells', 'meditrendy-core'); ?>
                </label>
                <p class="description"><?php esc_html_e('When disabled, the side cart will not request or display the upsell section.', 'meditrendy-core'); ?></p>
            </section>

            <?php foreach ($languages as $language) : ?>
                <?php $ids = $settings[$language] ?? []; ?>
                <section class="mt-upsell-language">
                    <h2><?php echo esc_html(meditrendy_side_cart_upsells_language_label($language)); ?> <code><?php echo esc_html($language); ?></code></h2>
                    <?php meditrendy_side_cart_upsells_product_search_field($language, $ids); ?>
                    <p class="description"><?php esc_html_e('Search products by name or SKU. Use exact simple products or variation IDs; only the first 10 unique products are saved.', 'meditrendy-core'); ?></p>
                    <?php meditrendy_side_cart_upsells_admin_product_list($ids); ?>
                </section>
            <?php endforeach; ?>

            <?php submit_button(__('Save upsells', 'meditrendy-core')); ?>
        </form>
    </div>
    <?php
}

function meditrendy_save_side_cart_upsells() {
    if (!current_user_can(meditrendy_side_cart_upsells_capability())) {
        wp_die(esc_html__('You do not have permission to save these settings.', 'meditrendy-core'));
    }

    check_admin_referer('meditrendy_save_side_cart_upsells');

    $input = isset($_POST['meditrendy_side_cart_upsells'])
        ? wp_unslash($_POST['meditrendy_side_cart_upsells'])
        : [];

    update_option(
        'meditrendy_side_cart_upsells_enabled',
        isset($_POST['meditrendy_side_cart_upsells_enabled']) && wp_unslash($_POST['meditrendy_side_cart_upsells_enabled']) === 'yes' ? 'yes' : 'no',
        false
    );
    update_option('meditrendy_side_cart_upsells', meditrendy_side_cart_upsells_sanitize($input), false);
    meditrendy_side_cart_upsells_flush_cache();

    wp_safe_redirect(add_query_arg('updated', '1', admin_url('admin.php?page=meditrendy-side-cart-upsells')));
    exit;
}
add_action('admin_post_meditrendy_save_side_cart_upsells', 'meditrendy_save_side_cart_upsells');

function meditrendy_side_cart_upsells_active_language() {
    if (function_exists('meditrendy_side_cart_language')) {
        return sanitize_key(meditrendy_side_cart_language());
    }

    if (function_exists('pll_current_language')) {
        return sanitize_key((string) pll_current_language('slug'));
    }

    return 'lt';
}

function meditrendy_side_cart_upsells_products() {
    if (!meditrendy_side_cart_upsells_has_configured_products()) {
        return [];
    }

    $settings = meditrendy_side_cart_upsells_settings();
    $language = meditrendy_side_cart_upsells_active_language();
    $ids = $settings[$language] ?? [];

    $products = [];

    foreach (meditrendy_side_cart_upsells_ids($ids) as $id) {
        $product = wc_get_product($id);

        if (!$product || !$product->exists() || !$product->is_purchasable()) {
            continue;
        }

        if (!$product->is_type('simple') && !$product->is_type('variation')) {
            continue;
        }

        if ($product->is_type('variation')) {
            $parent = wc_get_product($product->get_parent_id());

            if (!$parent || !$parent->exists() || !$parent->is_visible()) {
                continue;
            }
        } elseif (!$product->is_visible()) {
            continue;
        }

        $products[] = $product;
    }

    return $products;
}

function meditrendy_side_cart_upsells_simple_or_variable_form($product) {
    $price_html = $product->get_price_html();
    $is_variation = $product->is_type('variation');
    $product_id = $is_variation ? $product->get_parent_id() : $product->get_id();
    $variation_id = $is_variation ? $product->get_id() : 0;
    ?>
    <form class="cart mt-side-cart-upsell-form" method="post" enctype="multipart/form-data">
        <?php if ($is_variation) : ?>
            <?php foreach ($product->get_variation_attributes() as $attribute_name => $attribute_value) : ?>
                <input type="hidden" name="<?php echo esc_attr('attribute_' . sanitize_title(str_replace('attribute_', '', $attribute_name))); ?>" value="<?php echo esc_attr($attribute_value); ?>">
            <?php endforeach; ?>
            <input type="hidden" name="variation_id" class="variation_id" value="<?php echo esc_attr($variation_id); ?>">
        <?php endif; ?>
        <input type="hidden" name="quantity" value="1">
        <input type="hidden" name="product_id" value="<?php echo esc_attr($product_id); ?>">
        <input type="hidden" name="add-to-cart" value="<?php echo esc_attr($product_id); ?>">
        <button
            type="button"
            class="mt-side-cart-upsell-add"
            data-mt-side-cart-upsell-add
            data-mt-add-label="<?php esc_attr_e('Į krepšelį', 'meditrendy-core'); ?>"
        >
            <?php if ($price_html) : ?>
                <span><?php echo wp_kses_post($price_html); ?></span>
            <?php endif; ?>
        </button>
    </form>
    <?php
}

function meditrendy_side_cart_upsells_tile($product) {
    $permalink = $product->is_type('variation')
        ? get_permalink($product->get_parent_id())
        : $product->get_permalink();
    ?>
    <article class="mt-side-cart-upsell" data-mt-side-cart-upsell>
        <div class="mt-side-cart-upsell-image">
            <?php if ($permalink) : ?>
                <a href="<?php echo esc_url($permalink); ?>">
                    <?php echo $product->get_image('woocommerce_single', ['loading' => 'lazy']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </a>
            <?php else : ?>
                <?php echo $product->get_image('woocommerce_single', ['loading' => 'lazy']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endif; ?>
        </div>
        <div class="mt-side-cart-upsell-body">
            <h3><?php echo esc_html($product->get_name()); ?></h3>
            <div class="mt-side-cart-upsell-price"><?php echo wp_kses_post($product->get_price_html()); ?></div>
            <?php meditrendy_side_cart_upsells_simple_or_variable_form($product); ?>
        </div>
    </article>
    <?php
}

function meditrendy_side_cart_upsells_render_html() {
    $products = meditrendy_side_cart_upsells_products();

    if (!$products) {
        return '';
    }

    ob_start();
    ?>
    <section class="mt-side-cart-upsells" data-mt-side-cart-upsells>
        <div class="mt-side-cart-upsells-header">
            <h2><?php esc_html_e('Jums taip pat gali patikti', 'meditrendy-core'); ?></h2>
            <div class="mt-side-cart-upsells-controls">
                <button type="button" data-mt-upsell-prev aria-label="<?php esc_attr_e('Ankstesnės prekės', 'meditrendy-core'); ?>">‹</button>
                <button type="button" data-mt-upsell-next aria-label="<?php esc_attr_e('Kitos prekės', 'meditrendy-core'); ?>">›</button>
            </div>
        </div>
        <div class="mt-side-cart-upsells-track" data-mt-upsell-track>
            <?php foreach ($products as $product) : ?>
                <?php meditrendy_side_cart_upsells_tile($product); ?>
            <?php endforeach; ?>
        </div>
    </section>
    <?php

    return ob_get_clean();
}

function meditrendy_side_cart_upsells_html() {
    $language = meditrendy_side_cart_upsells_active_language();
    $cache_key = 'mt_side_cart_upsells_html_' . md5(wp_json_encode([
        'v' => meditrendy_side_cart_upsells_cache_version(),
        'l' => $language,
    ]));
    $cached = get_transient($cache_key);

    if (is_string($cached)) {
        return $cached;
    }

    $html = meditrendy_side_cart_upsells_render_html();

    set_transient($cache_key, $html, 12 * HOUR_IN_SECONDS);

    return $html;
}

add_action('save_post_product', 'meditrendy_side_cart_upsells_flush_cache');
add_action('woocommerce_update_product', 'meditrendy_side_cart_upsells_flush_cache');
