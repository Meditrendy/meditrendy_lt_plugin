<?php
if (!defined('ABSPATH')) exit;

function meditrendy_side_cart_upsells_capability() {
    return current_user_can('manage_woocommerce') ? 'manage_woocommerce' : 'manage_options';
}

function meditrendy_side_cart_upsells_settings() {
    $settings = get_option('meditrendy_side_cart_upsells', []);

    return is_array($settings) ? $settings : [];
}

function meditrendy_side_cart_upsells_cache_version() {
    return (string) get_option('meditrendy_side_cart_upsells_cache_version', '1');
}

function meditrendy_side_cart_upsells_flush_cache() {
    update_option('meditrendy_side_cart_upsells_cache_version', (string) time(), false);
}

function meditrendy_side_cart_upsells_ids($value) {
    if (!is_array($value)) {
        $value = preg_split('/[\s,]+/', (string) $value);
    }

    return array_values(array_unique(array_filter(array_map('absint', $value))));
}

function meditrendy_side_cart_upsells_sanitize($input) {
    $output = [];

    if (!is_array($input)) {
        return $output;
    }

    foreach ($input as $rule) {
        if (!is_array($rule) || empty($rule['enabled'])) {
            continue;
        }

        $scope = sanitize_key($rule['scope'] ?? 'sitewide');

        if (!in_array($scope, ['sitewide', 'product', 'category', 'brand'], true)) {
            $scope = 'sitewide';
        }

        $output[] = [
            'enabled'             => 1,
            'scope'               => $scope,
            'scope_product_ids'   => meditrendy_side_cart_upsells_ids($rule['scope_product_ids'] ?? []),
            'scope_category_ids'  => meditrendy_side_cart_upsells_ids($rule['scope_category_ids'] ?? []),
            'scope_brand_ids'     => meditrendy_side_cart_upsells_ids($rule['scope_brand_ids'] ?? []),
            'first_product_id'    => absint($rule['first_product_id'] ?? 0),
            'manual_product_ids'  => meditrendy_side_cart_upsells_ids($rule['manual_product_ids'] ?? []),
            'source_category_ids' => meditrendy_side_cart_upsells_ids($rule['source_category_ids'] ?? []),
            'source_brand_ids'    => meditrendy_side_cart_upsells_ids($rule['source_brand_ids'] ?? []),
        ];
    }

    return $output;
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
        .meditrendy-side-cart-upsells-admin .mt-upsell-rule {
            margin: 0 0 18px;
            padding: 0;
            border: 1px solid #c3c4c7;
            background: #fff;
        }

        .meditrendy-side-cart-upsells-admin .mt-upsell-rule-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 14px 16px;
            border-bottom: 1px solid #dcdcde;
            background: #f6f7f7;
        }

        .meditrendy-side-cart-upsells-admin .mt-upsell-rule-header h2 {
            margin: 0;
            font-size: 14px;
            line-height: 1.3;
        }

        .meditrendy-side-cart-upsells-admin .mt-upsell-sections {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: 0;
        }

        .meditrendy-side-cart-upsells-admin .mt-upsell-section {
            padding: 16px;
        }

        .meditrendy-side-cart-upsells-admin .mt-upsell-section + .mt-upsell-section {
            border-left: 1px solid #dcdcde;
        }

        .meditrendy-side-cart-upsells-admin .mt-upsell-section h3 {
            margin: 0 0 4px;
            font-size: 15px;
            line-height: 1.3;
        }

        .meditrendy-side-cart-upsells-admin .mt-upsell-section-description {
            margin: 0 0 14px;
            color: #646970;
        }

        .meditrendy-side-cart-upsells-admin .mt-upsell-rule-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 14px 18px;
        }

        .meditrendy-side-cart-upsells-admin .mt-upsell-field label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
        }

        .meditrendy-side-cart-upsells-admin .wc-product-search,
        .meditrendy-side-cart-upsells-admin .select2-container {
            width: 100% !important;
        }

        .meditrendy-side-cart-upsells-admin .mt-upsell-terms {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 6px 12px;
            max-height: 170px;
            padding: 8px;
            border: 1px solid #8c8f94;
            border-radius: 4px;
            overflow: auto;
        }

        .meditrendy-side-cart-upsells-admin .mt-upsell-term-search {
            width: 100%;
            margin: 0 0 8px;
        }

        .meditrendy-side-cart-upsells-admin .mt-upsell-term {
            display: flex;
            gap: 6px;
            line-height: 1.25;
        }

        .meditrendy-side-cart-upsells-admin .mt-upsell-term span {
            overflow-wrap: anywhere;
        }

        .meditrendy-side-cart-upsells-admin .mt-upsell-language {
            display: inline-flex;
            align-items: center;
            min-height: 18px;
            margin-left: 4px;
            padding: 0 5px;
            border-radius: 3px;
            background: #eef2f6;
            color: #50575e;
            font-size: 10px;
            font-weight: 700;
            line-height: 1;
            text-transform: uppercase;
        }

        .meditrendy-side-cart-upsells-admin .mt-upsell-field[hidden] {
            display: none !important;
        }

        @media (max-width: 1180px) {
            .meditrendy-side-cart-upsells-admin .mt-upsell-sections {
                grid-template-columns: 1fr;
            }

            .meditrendy-side-cart-upsells-admin .mt-upsell-section + .mt-upsell-section {
                border-top: 1px solid #dcdcde;
                border-left: 0;
            }
        }
    ');

    wp_register_script('meditrendy-side-cart-upsells-admin', false, ['jquery', 'wc-enhanced-select'], '1.0', true);
    wp_enqueue_script('meditrendy-side-cart-upsells-admin');
    wp_add_inline_script('meditrendy-side-cart-upsells-admin', "
        jQuery(function($) {
            var index = $('.mt-upsell-rule').length;

            function initSelects(context) {
                $(document.body).trigger('wc-enhanced-select-init');
            }

            function updateScopeFields(rule) {
                var scope = rule.find('[data-mt-upsell-scope]').val() || 'sitewide';

                rule.find('[data-mt-upsell-scope-field]').attr('hidden', 'hidden');

                if (scope !== 'sitewide') {
                    rule.find('[data-mt-upsell-scope-field=\"' + scope + '\"]').removeAttr('hidden');
                }
            }

            function updateAllScopeFields(context) {
                $(context).find('.mt-upsell-rule').each(function() {
                    updateScopeFields($(this));
                });
            }

            $('.mt-add-upsell-rule').on('click', function(e) {
                e.preventDefault();
                var template = $('#mt-upsell-rule-template').html().replace(/__INDEX__/g, index++);
                var rule = $(template).appendTo('.mt-upsell-rules');
                initSelects(rule);
                updateScopeFields(rule);
            });

            $(document).on('change', '[data-mt-upsell-scope]', function() {
                updateScopeFields($(this).closest('.mt-upsell-rule'));
            });

            $(document).on('input', '[data-mt-upsell-term-search]', function() {
                var query = ($(this).val() || '').toLowerCase();
                var list = $(this).closest('.mt-upsell-term-picker').find('.mt-upsell-term');

                list.each(function() {
                    var item = $(this);
                    var text = item.text().toLowerCase();
                    item.toggle(text.indexOf(query) !== -1);
                });
            });

            initSelects(document);
            updateAllScopeFields(document);
        });
    ");
}
add_action('admin_enqueue_scripts', 'meditrendy_side_cart_upsells_admin_assets');

function meditrendy_side_cart_upsells_terms($taxonomy) {
    if (!taxonomy_exists($taxonomy)) {
        return [];
    }

    $terms = get_terms([
        'taxonomy'   => $taxonomy,
        'hide_empty' => false,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ]);

    return is_wp_error($terms) ? [] : $terms;
}

function meditrendy_side_cart_upsells_term_language_label($term_id) {
    if (!function_exists('pll_get_term_language')) {
        return '';
    }

    $language = pll_get_term_language($term_id, 'slug');

    if (!$language) {
        $language = pll_get_term_language($term_id);
    }

    return $language ? strtoupper((string) $language) : '';
}

function meditrendy_side_cart_upsells_term_name($term, $show_language = false) {
    $name = esc_html($term->name);

    if ($show_language) {
        $language = meditrendy_side_cart_upsells_term_language_label($term->term_id);

        if ($language) {
            $name .= ' <span class="mt-upsell-language">' . esc_html($language) . '</span>';
        }
    }

    return $name;
}

function meditrendy_side_cart_upsells_product_select($name, $ids = []) {
    $ids = meditrendy_side_cart_upsells_ids($ids);
    ?>
    <select
        class="wc-product-search"
        multiple="multiple"
        name="<?php echo esc_attr($name); ?>[]"
        data-placeholder="<?php esc_attr_e('Search products...', 'meditrendy-core'); ?>"
        data-action="meditrendy_product_promotions_search_products"
        data-minimum_input_length="1"
        data-limit="30"
    >
        <?php foreach ($ids as $product_id) : ?>
            <?php $product = wc_get_product($product_id); ?>
            <?php if ($product) : ?>
                <option value="<?php echo esc_attr($product_id); ?>" selected><?php echo esc_html($product->get_formatted_name()); ?></option>
            <?php endif; ?>
        <?php endforeach; ?>
    </select>
    <?php
}

function meditrendy_side_cart_upsells_single_product_select($name, $product_id = 0) {
    $product_id = absint($product_id);
    ?>
    <select
        class="wc-product-search"
        name="<?php echo esc_attr($name); ?>"
        data-placeholder="<?php esc_attr_e('Search products...', 'meditrendy-core'); ?>"
        data-action="meditrendy_product_promotions_search_products"
        data-minimum_input_length="1"
        data-limit="30"
        data-allow_clear="true"
    >
        <?php if ($product_id) : ?>
            <?php $product = wc_get_product($product_id); ?>
            <?php if ($product) : ?>
                <option value="<?php echo esc_attr($product_id); ?>" selected><?php echo esc_html($product->get_formatted_name()); ?></option>
            <?php endif; ?>
        <?php endif; ?>
    </select>
    <?php
}

function meditrendy_side_cart_upsells_term_checkboxes($name, $taxonomy, $selected = [], $show_language = false) {
    $selected = meditrendy_side_cart_upsells_ids($selected);
    ?>
    <div class="mt-upsell-term-picker">
        <input type="search" class="mt-upsell-term-search" data-mt-upsell-term-search placeholder="<?php esc_attr_e('Search...', 'meditrendy-core'); ?>">
        <div class="mt-upsell-terms">
            <?php foreach (meditrendy_side_cart_upsells_terms($taxonomy) as $term) : ?>
                <label class="mt-upsell-term">
                    <input type="checkbox" name="<?php echo esc_attr($name); ?>[]" value="<?php echo esc_attr($term->term_id); ?>" <?php checked(in_array((int) $term->term_id, $selected, true)); ?>>
                    <span><?php echo meditrendy_side_cart_upsells_term_name($term, $show_language); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

function meditrendy_side_cart_upsells_rule_html($index, $rule = []) {
    $rule = wp_parse_args($rule, [
        'enabled'             => 1,
        'scope'               => 'sitewide',
        'scope_product_ids'   => [],
        'scope_category_ids'  => [],
        'scope_brand_ids'     => [],
        'first_product_id'    => 0,
        'manual_product_ids'  => [],
        'source_category_ids' => [],
        'source_brand_ids'    => [],
    ]);
    ?>
    <div class="mt-upsell-rule">
        <div class="mt-upsell-rule-header">
            <h2><?php echo esc_html(sprintf(__('Rule %s', 'meditrendy-core'), is_numeric($index) ? (int) $index + 1 : '')); ?></h2>
            <label>
                <input type="checkbox" name="meditrendy_side_cart_upsells[<?php echo esc_attr($index); ?>][enabled]" value="1" <?php checked(!empty($rule['enabled'])); ?>>
                <?php esc_html_e('Enabled', 'meditrendy-core'); ?>
            </label>
        </div>

        <div class="mt-upsell-sections">
            <section class="mt-upsell-section">
                <h3><?php esc_html_e('When to show', 'meditrendy-core'); ?></h3>
                <p class="mt-upsell-section-description"><?php esc_html_e('Choose which cart contents activate this rule.', 'meditrendy-core'); ?></p>

                <div class="mt-upsell-rule-grid">
                    <div class="mt-upsell-field">
                        <label><?php esc_html_e('Match type', 'meditrendy-core'); ?></label>
                        <select name="meditrendy_side_cart_upsells[<?php echo esc_attr($index); ?>][scope]" data-mt-upsell-scope>
                            <option value="sitewide" <?php selected($rule['scope'], 'sitewide'); ?>><?php esc_html_e('Any cart', 'meditrendy-core'); ?></option>
                            <option value="product" <?php selected($rule['scope'], 'product'); ?>><?php esc_html_e('Specific cart product', 'meditrendy-core'); ?></option>
                            <option value="category" <?php selected($rule['scope'], 'category'); ?>><?php esc_html_e('Product from category', 'meditrendy-core'); ?></option>
                            <option value="brand" <?php selected($rule['scope'], 'brand'); ?>><?php esc_html_e('Product from brand', 'meditrendy-core'); ?></option>
                        </select>
                    </div>

                    <div class="mt-upsell-field" data-mt-upsell-scope-field="product">
                        <label><?php esc_html_e('Cart products', 'meditrendy-core'); ?></label>
                        <?php meditrendy_side_cart_upsells_product_select("meditrendy_side_cart_upsells[$index][scope_product_ids]", $rule['scope_product_ids']); ?>
                    </div>

                    <div class="mt-upsell-field" data-mt-upsell-scope-field="category">
                        <label><?php esc_html_e('Cart categories', 'meditrendy-core'); ?></label>
                        <?php meditrendy_side_cart_upsells_term_checkboxes("meditrendy_side_cart_upsells[$index][scope_category_ids]", 'product_cat', $rule['scope_category_ids'], true); ?>
                    </div>

                    <div class="mt-upsell-field" data-mt-upsell-scope-field="brand">
                        <label><?php esc_html_e('Cart brands', 'meditrendy-core'); ?></label>
                        <?php meditrendy_side_cart_upsells_term_checkboxes("meditrendy_side_cart_upsells[$index][scope_brand_ids]", 'pa_brand', $rule['scope_brand_ids'], true); ?>
                    </div>
                </div>
            </section>

            <section class="mt-upsell-section">
                <h3><?php esc_html_e('What to show', 'meditrendy-core'); ?></h3>
                <p class="mt-upsell-section-description"><?php esc_html_e('Choose exact products, or let the side cart pull random products from categories and brands.', 'meditrendy-core'); ?></p>

                <div class="mt-upsell-rule-grid">
                    <div class="mt-upsell-field">
                        <label><?php esc_html_e('First product', 'meditrendy-core'); ?></label>
                        <?php meditrendy_side_cart_upsells_single_product_select("meditrendy_side_cart_upsells[$index][first_product_id]", $rule['first_product_id']); ?>
                        <p class="description"><?php esc_html_e('This product is shown first when this rule matches.', 'meditrendy-core'); ?></p>
                    </div>

                    <div class="mt-upsell-field">
                        <label><?php esc_html_e('Exact upsell products', 'meditrendy-core'); ?></label>
                        <?php meditrendy_side_cart_upsells_product_select("meditrendy_side_cart_upsells[$index][manual_product_ids]", $rule['manual_product_ids']); ?>
                    </div>

                    <div class="mt-upsell-field">
                        <label><?php esc_html_e('Random from categories', 'meditrendy-core'); ?></label>
                        <?php meditrendy_side_cart_upsells_term_checkboxes("meditrendy_side_cart_upsells[$index][source_category_ids]", 'product_cat', $rule['source_category_ids'], true); ?>
                    </div>

                    <div class="mt-upsell-field">
                        <label><?php esc_html_e('Random from brands', 'meditrendy-core'); ?></label>
                        <?php meditrendy_side_cart_upsells_term_checkboxes("meditrendy_side_cart_upsells[$index][source_brand_ids]", 'pa_brand', $rule['source_brand_ids'], true); ?>
                    </div>
                </div>
            </section>
        </div>
    </div>
    <?php
}

function meditrendy_side_cart_upsells_admin_page() {
    if (!current_user_can(meditrendy_side_cart_upsells_capability())) {
        wp_die(esc_html__('You do not have permission to view this page.', 'meditrendy-core'));
    }

    $rules = meditrendy_side_cart_upsells_settings();
    ?>
    <div class="wrap meditrendy-side-cart-upsells-admin">
        <h1><?php esc_html_e('Side cart upsells', 'meditrendy-core'); ?></h1>

        <?php if (isset($_GET['updated'])) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Upsells saved.', 'meditrendy-core'); ?></p></div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('meditrendy_save_side_cart_upsells'); ?>
            <input type="hidden" name="action" value="meditrendy_save_side_cart_upsells">

            <div class="mt-upsell-rules">
                <?php foreach ($rules as $index => $rule) : ?>
                    <?php meditrendy_side_cart_upsells_rule_html($index, $rule); ?>
                <?php endforeach; ?>
            </div>

            <p><button type="button" class="button mt-add-upsell-rule"><?php esc_html_e('Add rule', 'meditrendy-core'); ?></button></p>

            <?php submit_button(__('Save upsells', 'meditrendy-core')); ?>
        </form>

        <script type="text/html" id="mt-upsell-rule-template">
            <?php meditrendy_side_cart_upsells_rule_html('__INDEX__'); ?>
        </script>
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

    update_option('meditrendy_side_cart_upsells', meditrendy_side_cart_upsells_sanitize($input), false);
    meditrendy_side_cart_upsells_flush_cache();

    wp_safe_redirect(add_query_arg('updated', '1', admin_url('admin.php?page=meditrendy-side-cart-upsells')));
    exit;
}
add_action('admin_post_meditrendy_save_side_cart_upsells', 'meditrendy_save_side_cart_upsells');

function meditrendy_side_cart_upsells_cart_product_ids() {
    if (!function_exists('WC') || !WC()->cart) {
        return [];
    }

    $ids = [];

    foreach (WC()->cart->get_cart() as $item) {
        if (!empty($item['product_id'])) {
            $ids[] = (int) $item['product_id'];
        }
        if (!empty($item['variation_id'])) {
            $ids[] = (int) $item['variation_id'];
        }
    }

    return array_values(array_unique($ids));
}

function meditrendy_side_cart_upsells_product_term_ids($product_id, $taxonomy) {
    $term_ids = wp_get_post_terms($product_id, $taxonomy, ['fields' => 'ids']);

    if (is_wp_error($term_ids) || !$term_ids) {
        return [];
    }

    $all = array_map('intval', $term_ids);

    foreach ($term_ids as $term_id) {
        $all = array_merge($all, array_map('intval', get_ancestors($term_id, $taxonomy, 'taxonomy')));
    }

    return array_values(array_unique($all));
}

function meditrendy_side_cart_upsells_rule_matches($rule) {
    if (!function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) {
        return false;
    }

    $scope = $rule['scope'] ?? 'sitewide';

    if ($scope === 'sitewide') {
        return true;
    }

    $cart_product_ids = meditrendy_side_cart_upsells_cart_product_ids();

    if ($scope === 'product') {
        return (bool) array_intersect($cart_product_ids, meditrendy_side_cart_upsells_ids($rule['scope_product_ids'] ?? []));
    }

    foreach (WC()->cart->get_cart() as $item) {
        $product_id = (int) ($item['product_id'] ?? 0);

        if ($scope === 'category') {
            if (array_intersect(meditrendy_side_cart_upsells_ids($rule['scope_category_ids'] ?? []), meditrendy_side_cart_upsells_product_term_ids($product_id, 'product_cat'))) {
                return true;
            }
        }

        if ($scope === 'brand' && taxonomy_exists('pa_brand')) {
            if (array_intersect(meditrendy_side_cart_upsells_ids($rule['scope_brand_ids'] ?? []), meditrendy_side_cart_upsells_product_term_ids($product_id, 'pa_brand'))) {
                return true;
            }
        }
    }

    return false;
}

function meditrendy_side_cart_upsells_random_ids($category_ids, $brand_ids) {
    $category_ids = meditrendy_side_cart_upsells_ids($category_ids);
    $brand_ids = meditrendy_side_cart_upsells_ids($brand_ids);
    sort($category_ids);
    sort($brand_ids);

    $cache_key = 'mt_side_cart_upsells_random_' . md5(wp_json_encode([
        'v' => meditrendy_side_cart_upsells_cache_version(),
        'c' => $category_ids,
        'b' => $brand_ids,
    ]));
    $cached_ids = get_transient($cache_key);

    if (is_array($cached_ids)) {
        shuffle($cached_ids);

        return $cached_ids;
    }

    $tax_query = [];

    if ($category_ids) {
        $tax_query[] = [
            'taxonomy' => 'product_cat',
            'field'    => 'term_id',
            'terms'    => $category_ids,
        ];
    }

    if ($brand_ids && taxonomy_exists('pa_brand')) {
        $tax_query[] = [
            'taxonomy' => 'pa_brand',
            'field'    => 'term_id',
            'terms'    => $brand_ids,
        ];
    }

    if (count($tax_query) > 1) {
        $tax_query['relation'] = 'OR';
    }

    if (!$tax_query) {
        return [];
    }

    $ids = get_posts([
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'ID',
        'order'          => 'ASC',
        'fields'         => 'ids',
        'tax_query'      => $tax_query,
    ]);
    $ids = array_values(array_filter(array_map('absint', $ids)));

    set_transient($cache_key, $ids, 30 * MINUTE_IN_SECONDS);
    shuffle($ids);

    return $ids;
}

function meditrendy_side_cart_upsells_products() {
    $cart_ids = meditrendy_side_cart_upsells_cart_product_ids();
    $ids = [];

    foreach (meditrendy_side_cart_upsells_settings() as $rule) {
        if (empty($rule['enabled']) || !meditrendy_side_cart_upsells_rule_matches($rule)) {
            continue;
        }

        $first_product_id = absint($rule['first_product_id'] ?? 0);
        $rule_ids = $first_product_id ? [$first_product_id] : [];
        $rule_ids = array_merge($rule_ids, meditrendy_side_cart_upsells_ids($rule['manual_product_ids'] ?? []));
        $rule_ids = array_merge($rule_ids, meditrendy_side_cart_upsells_random_ids(
            meditrendy_side_cart_upsells_ids($rule['source_category_ids'] ?? []),
            meditrendy_side_cart_upsells_ids($rule['source_brand_ids'] ?? [])
        ));

        foreach ($rule_ids as $id) {
            $id = absint($id);

            if (!$id || in_array($id, $cart_ids, true) || in_array($id, $ids, true)) {
                continue;
            }

            $product = wc_get_product($id);

            if (meditrendy_side_cart_upsells_is_eligible_product($product)) {
                $ids[] = $id;
            }
        }
    }

    return array_map('wc_get_product', $ids);
}

function meditrendy_side_cart_upsells_variation_fields($product, $prefix = '') {
    if (!$product || !$product->is_type('variable')) {
        return;
    }

    $attributes = $product->get_variation_attributes();
    $available_variations = $product->get_available_variations();
    $variations_json = wp_json_encode($available_variations);
    ?>
    <div class="mt-side-cart-upsell-variations" data-product_variations="<?php echo function_exists('wc_esc_json') ? wc_esc_json($variations_json) : esc_attr($variations_json); ?>">
        <?php foreach ($attributes as $attribute_name => $options) : ?>
            <label>
                <span><?php echo esc_html(wc_attribute_label($attribute_name)); ?></span>
                <?php
                wc_dropdown_variation_attribute_options([
                    'options'          => $options,
                    'attribute'        => $attribute_name,
                    'product'          => $product,
                    'name'             => $prefix . 'attribute_' . sanitize_title($attribute_name),
                    'show_option_none' => sprintf(esc_html__('Pasirinkite %s', 'meditrendy-core'), function_exists('mb_strtolower') ? mb_strtolower(wc_attribute_label($attribute_name)) : strtolower(wc_attribute_label($attribute_name))),
                ]);
                ?>
            </label>
        <?php endforeach; ?>
        <input type="hidden" name="variation_id" class="variation_id" value="0">
    </div>
    <?php
}

function meditrendy_side_cart_upsells_single_variation($product) {
    if (!$product || !$product->is_type('variable')) {
        return null;
    }

    $variations = $product->get_available_variations();

    if (count($variations) !== 1 || empty($variations[0]['variation_id'])) {
        return null;
    }

    $variation = wc_get_product((int) $variations[0]['variation_id']);

    if (!$variation || !$variation->is_purchasable() || !$variation->is_in_stock()) {
        return null;
    }

    return $variations[0];
}

function meditrendy_side_cart_upsells_is_eligible_product($product) {
    if (!$product || !$product->exists() || !$product->is_visible()) {
        return false;
    }

    $product_id = $product->get_id();
    $cache_key = 'mt_side_cart_upsells_eligible_' . meditrendy_side_cart_upsells_cache_version() . '_' . $product_id;
    $cached = get_transient($cache_key);

    if ($cached === '1') {
        return true;
    }

    if ($cached === '0') {
        return false;
    }

    if ($product->is_type('simple')) {
        $eligible = $product->is_purchasable();
    } else {
        $eligible = $product->is_type('variable') && (bool) meditrendy_side_cart_upsells_single_variation($product);
    }

    set_transient($cache_key, $eligible ? '1' : '0', 30 * MINUTE_IN_SECONDS);

    return $eligible;
}

function meditrendy_side_cart_upsells_simple_or_variable_form($product) {
    $single_variation = meditrendy_side_cart_upsells_single_variation($product);
    $price_html = $product->get_price_html();
    ?>
    <form class="cart mt-side-cart-upsell-form" method="post" enctype="multipart/form-data">
        <?php if ($single_variation) : ?>
            <?php foreach (($single_variation['attributes'] ?? []) as $attribute_name => $attribute_value) : ?>
                <input type="hidden" name="<?php echo esc_attr($attribute_name); ?>" value="<?php echo esc_attr($attribute_value); ?>">
            <?php endforeach; ?>
            <input type="hidden" name="variation_id" class="variation_id" value="<?php echo esc_attr((int) $single_variation['variation_id']); ?>">
        <?php endif; ?>
        <input type="hidden" name="quantity" value="1">
        <input type="hidden" name="product_id" value="<?php echo esc_attr($product->get_id()); ?>">
        <input type="hidden" name="add-to-cart" value="<?php echo esc_attr($product->get_id()); ?>">
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

function meditrendy_side_cart_upsells_bundle_form($bundle_product) {
    global $product;
    $previous_product = $product;
    $product = $bundle_product;

    echo '<div class="mt-side-cart-upsell-bundle">';
    do_action('woocommerce_woosb_add_to_cart');
    echo '</div>';

    $product = $previous_product;
}

function meditrendy_side_cart_upsells_tile($product) {
    ?>
    <article class="mt-side-cart-upsell" data-mt-side-cart-upsell>
        <div class="mt-side-cart-upsell-image">
            <?php echo $product->get_image('woocommerce_thumbnail', ['loading' => 'lazy']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
        <div class="mt-side-cart-upsell-body">
            <h3><?php echo esc_html($product->get_name()); ?></h3>
            <div class="mt-side-cart-upsell-price"><?php echo wp_kses_post($product->get_price_html()); ?></div>
            <?php meditrendy_side_cart_upsells_simple_or_variable_form($product); ?>
        </div>
    </article>
    <?php
}

function meditrendy_side_cart_upsells_html() {
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
