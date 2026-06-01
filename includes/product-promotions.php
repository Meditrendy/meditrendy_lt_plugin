<?php
if (!defined('ABSPATH')) exit;

function meditrendy_product_promotions_capability() {
    return current_user_can('manage_woocommerce') ? 'manage_woocommerce' : 'manage_options';
}

function meditrendy_product_promotions_settings() {
    $settings = get_option('meditrendy_product_promotions', []);

    return is_array($settings) ? $settings : [];
}

function meditrendy_product_promotions_published_coupons() {
    $coupons = get_posts([
        'post_type'      => 'shop_coupon',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'fields'         => 'ids',
    ]);

    if (!$coupons || !function_exists('wc_get_coupon_id_by_code')) {
        return [];
    }

    return array_map(function ($coupon_id) {
        return new WC_Coupon($coupon_id);
    }, $coupons);
}

function meditrendy_product_promotions_coupon_is_active($coupon) {
    if (!$coupon || !is_a($coupon, 'WC_Coupon')) {
        return false;
    }

    $expires = $coupon->get_date_expires();

    return !$expires || $expires->getTimestamp() >= time();
}

function meditrendy_product_promotions_active_coupons() {
    return array_values(array_filter(
        meditrendy_product_promotions_published_coupons(),
        'meditrendy_product_promotions_coupon_is_active'
    ));
}

function meditrendy_product_promotions_status_label($coupon) {
    return meditrendy_product_promotions_coupon_is_active($coupon)
        ? __('Active', 'meditrendy-core')
        : __('Expired', 'meditrendy-core');
}

function meditrendy_product_promotions_sanitize_ids($ids) {
    if (!is_array($ids)) {
        $ids = preg_split('/[\s,]+/', (string) $ids);
    }

    return array_values(array_unique(array_filter(array_map('absint', $ids))));
}

function meditrendy_product_promotions_sanitize($input) {
    $output = [];

    if (!is_array($input)) {
        return $output;
    }

    foreach ($input as $coupon_id => $rules) {
        $coupon_id = absint($coupon_id);

        if (!$coupon_id || !is_array($rules)) {
            continue;
        }

        $output[$coupon_id] = [
            'sitewide'     => !empty($rules['sitewide']) ? 1 : 0,
            'product_ids'  => meditrendy_product_promotions_sanitize_ids($rules['product_ids'] ?? []),
            'category_ids' => meditrendy_product_promotions_sanitize_ids($rules['category_ids'] ?? []),
            'brand_ids'    => meditrendy_product_promotions_sanitize_ids($rules['brand_ids'] ?? []),
        ];
    }

    return $output;
}

function meditrendy_product_promotions_search_products() {
    check_ajax_referer('search-products', 'security');

    if (!current_user_can(meditrendy_product_promotions_capability())) {
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
        LEFT JOIN {$wpdb->postmeta} sku_meta
            ON sku_meta.post_id = p.ID
            AND sku_meta.meta_key = '_sku'
        WHERE p.post_type = 'product'
            AND p.post_status IN ('publish', 'private')
            AND (
                p.post_title LIKE %s
                OR p.post_excerpt LIKE %s
                OR p.post_content LIKE %s
                OR sku_meta.meta_value LIKE %s
            )
        ORDER BY
            CASE
                WHEN p.post_title LIKE %s THEN 0
                WHEN sku_meta.meta_value LIKE %s THEN 1
                ELSE 2
            END,
            p.post_title ASC
        LIMIT %d
        ",
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

        if ($product && wc_products_array_filter_readable($product)) {
            $results[$product->get_id()] = rawurldecode(wp_strip_all_tags($product->get_formatted_name()));
        }
    }

    wp_send_json($results);
}
add_action('wp_ajax_meditrendy_product_promotions_search_products', 'meditrendy_product_promotions_search_products');

function meditrendy_product_promotions_admin_menu() {
    add_submenu_page(
        'meditrendy-settings',
        __('PDP promotions', 'meditrendy-core'),
        __('PDP promotions', 'meditrendy-core'),
        meditrendy_product_promotions_capability(),
        'meditrendy-product-promotions',
        'meditrendy_render_product_promotions_admin_page'
    );
}
add_action('admin_menu', 'meditrendy_product_promotions_admin_menu');

function meditrendy_product_promotions_admin_assets($hook) {
    if ($hook !== 'meditrendy_page_meditrendy-product-promotions') {
        return;
    }

    if (function_exists('wp_enqueue_select2')) {
        wp_enqueue_select2();
    }

    wp_enqueue_script('wc-enhanced-select');
    wp_enqueue_style('woocommerce_admin_styles');

    wp_register_style('meditrendy-product-promotions-admin', false, [], '1.0');
    wp_enqueue_style('meditrendy-product-promotions-admin');
    wp_add_inline_style('meditrendy-product-promotions-admin', '
        .meditrendy-product-promotions-admin .mt-promo-table-wrap {
            max-width: 100%;
            overflow-x: auto;
        }

        .meditrendy-product-promotions-admin .mt-promo-table {
            min-width: 1520px;
        }

        .meditrendy-product-promotions-admin .mt-promo-products {
            width: 340px;
            min-width: 340px;
        }

        .meditrendy-product-promotions-admin .mt-promo-products .select2-container,
        .meditrendy-product-promotions-admin .mt-promo-products .select2-selection {
            width: 100% !important;
            min-height: 38px;
        }

        .meditrendy-product-promotions-admin .mt-promo-products .select2-selection__rendered {
            display: flex !important;
            align-items: center;
            flex-wrap: wrap;
            gap: 4px;
            min-height: 36px;
            padding-top: 4px;
            padding-bottom: 4px;
        }

        .meditrendy-product-promotions-admin .mt-promo-products .select2-selection__choice {
            max-width: 100%;
            white-space: normal;
            line-height: 1.25;
        }

        .meditrendy-product-promotions-admin .mt-promo-products .select2-search__field {
            min-width: 100% !important;
            text-align: center;
        }

        .meditrendy-product-promotions-admin .mt-promo-terms {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 6px 12px;
            max-height: 180px;
            min-width: 340px;
            padding: 8px;
            border: 1px solid #8c8f94;
            border-radius: 4px;
            background: #ffffff;
            overflow: auto;
        }

        .meditrendy-product-promotions-admin .mt-promo-term {
            display: flex;
            align-items: flex-start;
            gap: 6px;
            min-width: 0;
            margin: 0;
            line-height: 1.25;
        }

        .meditrendy-product-promotions-admin .mt-promo-term span {
            overflow-wrap: anywhere;
            word-break: normal;
        }
    ');
}
add_action('admin_enqueue_scripts', 'meditrendy_product_promotions_admin_assets');

function meditrendy_render_product_promotions_admin_page() {
    if (!current_user_can(meditrendy_product_promotions_capability())) {
        wp_die(esc_html__('You do not have permission to view this page.', 'meditrendy-core'));
    }

    $coupons = meditrendy_product_promotions_published_coupons();
    $settings = meditrendy_product_promotions_settings();
    $categories = get_terms([
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ]);
    $brands = taxonomy_exists('pa_brand') ? get_terms([
        'taxonomy'   => 'pa_brand',
        'hide_empty' => false,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ]) : [];
    ?>
    <div class="wrap meditrendy-product-promotions-admin">
        <h1><?php esc_html_e('PDP promotions', 'meditrendy-core'); ?></h1>

        <?php if (isset($_GET['updated'])) : ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e('Promotion settings saved.', 'meditrendy-core'); ?></p>
            </div>
        <?php endif; ?>

        <?php if (!$coupons) : ?>
            <p><?php esc_html_e('No published WooCommerce coupons found.', 'meditrendy-core'); ?></p>
        <?php else : ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('meditrendy_save_product_promotions'); ?>
                <input type="hidden" name="action" value="meditrendy_save_product_promotions">

                <div class="mt-promo-table-wrap">
                <table class="widefat striped mt-promo-table">
                    <thead>
                        <tr>
                            <th style="width: 180px;"><?php esc_html_e('Coupon', 'meditrendy-core'); ?></th>
                            <th style="width: 130px;"><?php esc_html_e('Discount', 'meditrendy-core'); ?></th>
                            <th style="width: 140px;"><?php esc_html_e('Expires', 'meditrendy-core'); ?></th>
                            <th style="width: 110px;"><?php esc_html_e('Status', 'meditrendy-core'); ?></th>
                            <th style="width: 110px;"><?php esc_html_e('Site-wide', 'meditrendy-core'); ?></th>
                            <th><?php esc_html_e('Products', 'meditrendy-core'); ?></th>
                            <th><?php esc_html_e('Categories', 'meditrendy-core'); ?></th>
                            <th><?php esc_html_e('Brands', 'meditrendy-core'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($coupons as $coupon) : ?>
                            <?php
                            $coupon_id = $coupon->get_id();
                            $rules = $settings[$coupon_id] ?? [];
                            $product_ids = meditrendy_product_promotions_sanitize_ids($rules['product_ids'] ?? []);
                            $category_ids = meditrendy_product_promotions_sanitize_ids($rules['category_ids'] ?? []);
                            $brand_ids = meditrendy_product_promotions_sanitize_ids($rules['brand_ids'] ?? []);
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($coupon->get_code()); ?></strong>
                                </td>
                                <td><?php echo esc_html(meditrendy_product_promotions_discount_label($coupon)); ?></td>
                                <td><?php echo esc_html(meditrendy_product_promotions_expiry_label($coupon)); ?></td>
                                <td>
                                    <strong><?php echo esc_html(meditrendy_product_promotions_status_label($coupon)); ?></strong>
                                    <?php if (!meditrendy_product_promotions_coupon_is_active($coupon)) : ?>
                                        <br><small><?php esc_html_e('Won’t show on PDP until expiry is updated.', 'meditrendy-core'); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <label>
                                        <input type="checkbox" name="meditrendy_product_promotions[<?php echo esc_attr($coupon_id); ?>][sitewide]" value="1" <?php checked(!empty($rules['sitewide'])); ?>>
                                        <?php esc_html_e('Show', 'meditrendy-core'); ?>
                                    </label>
                                </td>
                                <td class="mt-promo-products">
                                    <select
                                        class="wc-product-search"
                                        multiple="multiple"
                                        style="width: 100%;"
                                        name="meditrendy_product_promotions[<?php echo esc_attr($coupon_id); ?>][product_ids][]"
                                        data-placeholder="<?php esc_attr_e('Search products...', 'meditrendy-core'); ?>"
                                        data-action="meditrendy_product_promotions_search_products"
                                        data-minimum_input_length="1"
                                        data-limit="30"
                                    >
                                        <?php foreach ($product_ids as $product_id) : ?>
                                            <?php $product = wc_get_product($product_id); ?>
                                            <?php if ($product) : ?>
                                                <option value="<?php echo esc_attr($product_id); ?>" selected><?php echo esc_html($product->get_formatted_name()); ?></option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <div class="mt-promo-terms">
                                        <?php if (!is_wp_error($categories)) : ?>
                                            <?php foreach ($categories as $category) : ?>
                                                <label class="mt-promo-term">
                                                    <input type="checkbox" name="meditrendy_product_promotions[<?php echo esc_attr($coupon_id); ?>][category_ids][]" value="<?php echo esc_attr($category->term_id); ?>" <?php checked(in_array((int) $category->term_id, $category_ids, true)); ?>>
                                                    <span><?php echo esc_html($category->name); ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="mt-promo-terms">
                                        <?php if (!is_wp_error($brands)) : ?>
                                            <?php foreach ($brands as $brand) : ?>
                                                <label class="mt-promo-term">
                                                    <input type="checkbox" name="meditrendy_product_promotions[<?php echo esc_attr($coupon_id); ?>][brand_ids][]" value="<?php echo esc_attr($brand->term_id); ?>" <?php checked(in_array((int) $brand->term_id, $brand_ids, true)); ?>>
                                                    <span><?php echo esc_html($brand->name); ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>

                <?php submit_button(__('Save promotions', 'meditrendy-core')); ?>
            </form>
        <?php endif; ?>
    </div>
    <?php
}

function meditrendy_save_product_promotions() {
    if (!current_user_can(meditrendy_product_promotions_capability())) {
        wp_die(esc_html__('You do not have permission to save these settings.', 'meditrendy-core'));
    }

    check_admin_referer('meditrendy_save_product_promotions');

    $input = isset($_POST['meditrendy_product_promotions'])
        ? wp_unslash($_POST['meditrendy_product_promotions'])
        : [];

    update_option('meditrendy_product_promotions', meditrendy_product_promotions_sanitize($input), false);

    wp_safe_redirect(add_query_arg('updated', '1', admin_url('admin.php?page=meditrendy-product-promotions')));
    exit;
}
add_action('admin_post_meditrendy_save_product_promotions', 'meditrendy_save_product_promotions');

function meditrendy_product_promotions_discount_label($coupon) {
    $amount = (float) $coupon->get_amount();
    $type = $coupon->get_discount_type();

    if (in_array($type, ['percent'], true)) {
        return wc_format_decimal($amount, 0) . '%';
    }

    if (in_array($type, ['fixed_cart', 'fixed_product'], true)) {
        return wp_strip_all_tags(wc_price($amount));
    }

    return $coupon->get_amount();
}

function meditrendy_product_promotions_expiry_label($coupon) {
    $expires = $coupon->get_date_expires();

    if (!$expires) {
        return __('Be galiojimo pabaigos', 'meditrendy-core');
    }

    return date_i18n(get_option('date_format'), $expires->getTimestamp());
}

function meditrendy_product_promotions_product_term_ids($product_id, $taxonomy) {
    $term_ids = wp_get_post_terms($product_id, $taxonomy, ['fields' => 'ids']);

    if (is_wp_error($term_ids) || !$term_ids) {
        return [];
    }

    $all = array_map('intval', $term_ids);

    foreach ($term_ids as $term_id) {
        $ancestors = get_ancestors($term_id, $taxonomy, 'taxonomy');
        $all = array_merge($all, array_map('intval', $ancestors));
    }

    return array_values(array_unique($all));
}

function meditrendy_product_promotions_coupon_matches_product($coupon_id, $product_id) {
    $settings = meditrendy_product_promotions_settings();
    $rules = $settings[$coupon_id] ?? null;

    if (!$rules) {
        return false;
    }

    if (!empty($rules['sitewide'])) {
        return true;
    }

    $product_ids = meditrendy_product_promotions_sanitize_ids($rules['product_ids'] ?? []);

    if (in_array((int) $product_id, $product_ids, true)) {
        return true;
    }

    $category_ids = meditrendy_product_promotions_sanitize_ids($rules['category_ids'] ?? []);

    if ($category_ids) {
        $product_category_ids = meditrendy_product_promotions_product_term_ids($product_id, 'product_cat');

        if (array_intersect($category_ids, $product_category_ids)) {
            return true;
        }
    }

    $brand_ids = meditrendy_product_promotions_sanitize_ids($rules['brand_ids'] ?? []);

    if ($brand_ids && taxonomy_exists('pa_brand')) {
        $product_brand_ids = meditrendy_product_promotions_product_term_ids($product_id, 'pa_brand');

        if (array_intersect($brand_ids, $product_brand_ids)) {
            return true;
        }
    }

    return false;
}

function meditrendy_product_promotions_for_product($product_id) {
    $matched = [];

    foreach (meditrendy_product_promotions_active_coupons() as $coupon) {
        if (meditrendy_product_promotions_coupon_matches_product($coupon->get_id(), $product_id)) {
            $matched[] = $coupon;
        }
    }

    return $matched;
}

function meditrendy_product_promotions_enqueue_assets() {
    $path = MEDITRENDY_CORE_DIR . 'assets/js/product-promotions.js';

    if (file_exists($path)) {
        wp_enqueue_script(
            'meditrendy-product-promotions',
            MEDITRENDY_CORE_URL . 'assets/js/product-promotions.js',
            [],
            filemtime($path),
            true
        );
    }
}

function meditrendy_product_promotions_render($product_id = 0, $display = '') {
    if (!$product_id) {
        $product_id = get_queried_object_id();
    }

    $coupons = meditrendy_product_promotions_for_product($product_id);

    if (!$coupons) {
        return '';
    }

    meditrendy_product_promotions_enqueue_assets();

    $classes = ['mt-pdp-promotions'];

    if ($display === 'mobile') {
        $classes[] = 'mt-pdp-promotions-mobile';
    } elseif ($display === 'desktop') {
        $classes[] = 'mt-pdp-promotions-desktop';
    }

    ob_start();
    ?>
    <div class="<?php echo esc_attr(implode(' ', $classes)); ?>" data-mt-pdp-promotions>
        <?php foreach ($coupons as $coupon) : ?>
            <div class="mt-pdp-promotion">
                <div class="mt-pdp-promotion-main">
                    <span class="mt-pdp-promotion-label"><?php esc_html_e('Nuolaidos kodas', 'meditrendy-core'); ?></span>
                    <span class="mt-pdp-promotion-discount"><?php echo esc_html(meditrendy_product_promotions_discount_label($coupon)); ?></span>
                    <span class="mt-pdp-promotion-code"><?php echo esc_html($coupon->get_code()); ?></span>
                </div>
                <div class="mt-pdp-promotion-meta">
                    <span><?php echo esc_html(sprintf(__('Galioja iki: %s', 'meditrendy-core'), meditrendy_product_promotions_expiry_label($coupon))); ?></span>
                    <button type="button" class="mt-pdp-promotion-copy" data-mt-copy-coupon="<?php echo esc_attr($coupon->get_code()); ?>">
                        <?php esc_html_e('Kopijuoti kodą', 'meditrendy-core'); ?>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php

    return ob_get_clean();
}

function meditrendy_product_promotions_shortcode($atts) {
    if (!function_exists('is_product') || !is_product()) {
        return '';
    }

    $atts = shortcode_atts([
        'display' => '',
    ], $atts, 'meditrendy_pdp_promotions');

    $display = sanitize_key($atts['display']);

    if (!in_array($display, ['', 'mobile', 'desktop'], true)) {
        $display = '';
    }

    return meditrendy_product_promotions_render(get_queried_object_id(), $display);
}
add_shortcode('meditrendy_pdp_promotions', 'meditrendy_product_promotions_shortcode');
