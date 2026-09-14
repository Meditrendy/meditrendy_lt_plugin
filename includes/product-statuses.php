<?php
/**
 * Draft-like workflow statuses for WooCommerce products.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return the custom product workflow statuses and their admin badge colors.
 *
 * @return array<string, array{label: string, color: string, background: string}>
 */
function meditrendy_product_workflow_statuses() {
    return [
        'med-archived' => [
            'label'      => __('Archiwum', 'meditrendy-core'),
            'color'      => '#5b3f8c',
            'background' => '#e7def3',
        ],
        'med-unavailable' => [
            'label'      => __('Chwilowo niedostępne', 'meditrendy-core'),
            'color'      => '#8a2424',
            'background' => '#f7d7d7',
        ],
        'med-ready' => [
            'label'      => __('Do publikacji', 'meditrendy-core'),
            'color'      => '#075f54',
            'background' => '#ccebe6',
        ],
    ];
}

/**
 * Return only the custom workflow status slugs.
 *
 * @return string[]
 */
function meditrendy_product_workflow_status_slugs() {
    return array_keys(meditrendy_product_workflow_statuses());
}

/**
 * Check a product (or a variation's parent product) for a workflow status.
 *
 * @param int $product_id Product or variation ID.
 * @return bool
 */
function meditrendy_product_has_workflow_status($product_id) {
    $product_id = absint($product_id);
    if (!$product_id) {
        return false;
    }

    if (get_post_type($product_id) === 'product_variation') {
        $product_id = wp_get_post_parent_id($product_id);
    }

    return in_array(get_post_status($product_id), meditrendy_product_workflow_status_slugs(), true);
}

/**
 * Register non-public statuses that can be counted and filtered in wp-admin.
 */
function meditrendy_register_product_workflow_statuses() {
    foreach (meditrendy_product_workflow_statuses() as $status => $settings) {
        register_post_status($status, [
            'label'                     => $settings['label'],
            'public'                    => false,
            'protected'                 => true,
            'publicly_queryable'        => false,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            /* translators: %s: number of products. */
            'label_count'               => _n_noop(
                $settings['label'] . ' <span class="count">(%s)</span>',
                $settings['label'] . ' <span class="count">(%s)</span>',
                'meditrendy-core'
            ),
        ]);
    }
}
add_action('init', 'meditrendy_register_product_workflow_statuses');

/**
 * Add custom statuses to the classic product editor's Status selector.
 *
 * WordPress does not render registered custom statuses in this selector, so
 * the options are added after its own post editor script has initialized.
 *
 * @param string $hook_suffix Current admin page.
 */
function meditrendy_product_workflow_status_editor_script($hook_suffix) {
    if (!in_array($hook_suffix, ['post.php', 'post-new.php'], true)) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'product') {
        return;
    }

    $labels = [];
    foreach (meditrendy_product_workflow_statuses() as $status => $settings) {
        $labels[$status] = $settings['label'];
    }

    $script = sprintf(
        <<<'JS'
(function () {
    const statuses = %s;
    const statusSelect = document.querySelector('#post_status');
    const hiddenStatus = document.querySelector('#hidden_post_status');
    const statusDisplay = document.querySelector('#post-status-display');

    if (!statusSelect) {
        return;
    }

    Object.entries(statuses).forEach(([value, label]) => {
        if (!statusSelect.querySelector(`option[value="${value}"]`)) {
            statusSelect.add(new Option(label, value));
        }
    });

    const currentStatus = hiddenStatus ? hiddenStatus.value : '';
    if (Object.prototype.hasOwnProperty.call(statuses, currentStatus)) {
        statusSelect.value = currentStatus;
        if (statusDisplay) {
            statusDisplay.textContent = statuses[currentStatus];
        }
    }
}());
JS,
        wp_json_encode($labels)
    );

    wp_add_inline_script('post', $script, 'after');
}
add_action('admin_enqueue_scripts', 'meditrendy_product_workflow_status_editor_script');

/**
 * Add custom statuses to both Quick Edit and Bulk Edit for products.
 *
 * @param array<string, string> $statuses  Existing editable statuses.
 * @param string                $post_type Current post type.
 * @return array<string, string>
 */
function meditrendy_product_workflow_quick_edit_statuses($statuses, $post_type) {
    if ($post_type !== 'product') {
        return $statuses;
    }

    foreach (meditrendy_product_workflow_statuses() as $status => $settings) {
        $statuses[$status] = $settings['label'];
    }

    return $statuses;
}
add_filter('quick_edit_statuses', 'meditrendy_product_workflow_quick_edit_statuses', 10, 2);

/**
 * Add a status badge column after the product names.
 *
 * @param array<string, string> $columns Product list columns.
 * @return array<string, string>
 */
function meditrendy_product_status_admin_column($columns) {
    $status_column = [
        'meditrendy_product_status' => __('Status', 'meditrendy-core'),
    ];

    $keys     = array_keys($columns);
    $position = array_search('meditrendy_internal_product_name', $keys, true);

    if ($position === false) {
        $position = array_search('name', $keys, true);
    }

    if ($position === false) {
        return $columns + $status_column;
    }

    return array_slice($columns, 0, $position + 1, true)
        + $status_column
        + array_slice($columns, $position + 1, null, true);
}
add_filter('manage_edit-product_columns', 'meditrendy_product_status_admin_column', 20);

/**
 * Return display settings for built-in and custom product statuses.
 *
 * @param string $status Post status slug.
 * @return array{label: string, color: string, background: string}
 */
function meditrendy_product_status_badge($status) {
    $badges = [
        'publish' => [
            'label'      => __('Opublikowano', 'meditrendy-core'),
            'color'      => '#146c2e',
            'background' => '#d7f0dd',
        ],
        'draft' => [
            'label'      => __('Szkic', 'meditrendy-core'),
            'color'      => '#3c434a',
            'background' => '#e2e4e7',
        ],
        'pending' => [
            'label'      => __('Oczekuje na przegląd', 'meditrendy-core'),
            'color'      => '#7a4b00',
            'background' => '#f6e5bd',
        ],
        'future' => [
            'label'      => __('Zaplanowano', 'meditrendy-core'),
            'color'      => '#135e96',
            'background' => '#d7e9f7',
        ],
        'private' => [
            'label'      => __('Prywatny', 'meditrendy-core'),
            'color'      => '#663399',
            'background' => '#eadcf5',
        ],
    ];

    $badges = array_merge($badges, meditrendy_product_workflow_statuses());

    if (isset($badges[$status])) {
        return $badges[$status];
    }

    $status_object = get_post_status_object($status);

    return [
        'label'      => $status_object ? $status_object->label : $status,
        'color'      => '#3c434a',
        'background' => '#e2e4e7',
    ];
}

/**
 * Render the status badge for a row in the Products table.
 *
 * @param string $column  Current column key.
 * @param int    $post_id Product ID.
 */
function meditrendy_render_product_status_admin_column($column, $post_id) {
    if ($column !== 'meditrendy_product_status') {
        return;
    }

    $badge = meditrendy_product_status_badge(get_post_status($post_id));

    printf(
        '<span class="meditrendy-product-status-badge" style="--med-status-color:%1$s;--med-status-background:%2$s">%3$s</span>',
        esc_attr($badge['color']),
        esc_attr($badge['background']),
        esc_html($badge['label'])
    );
}
add_action('manage_product_posts_custom_column', 'meditrendy_render_product_status_admin_column', 10, 2);

/**
 * Style the status column and its accessible, high-contrast badges.
 */
function meditrendy_product_status_admin_styles() {
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'edit-product') {
        return;
    }
    ?>
    <style>
        body.post-type-product table.wp-list-table th#meditrendy_product_status,
        body.post-type-product table.wp-list-table .column-meditrendy_product_status {
            width: 18ch !important;
            min-width: 18ch !important;
            box-sizing: border-box;
        }

        .meditrendy-product-status-badge {
            display: inline-block;
            max-width: 100%;
            padding: 3px 8px;
            border: 1px solid var(--med-status-color);
            border-radius: 999px;
            color: var(--med-status-color);
            background: var(--med-status-background);
            font-weight: 600;
            line-height: 1.35;
            overflow-wrap: anywhere;
        }

        @media screen and (max-width: 782px) {
            body.post-type-product table.wp-list-table th#meditrendy_product_status,
            body.post-type-product table.wp-list-table .column-meditrendy_product_status {
                width: auto !important;
                min-width: 0 !important;
            }
        }
    </style>
    <?php
}
add_action('admin_head', 'meditrendy_product_status_admin_styles', 100);

/**
 * Never expose a custom-workflow product through a direct storefront request.
 */
function meditrendy_block_workflow_products_on_storefront() {
    if (is_admin()) {
        return;
    }

    $product = get_queried_object();
    if (
        !$product instanceof WP_Post
        || $product->post_type !== 'product'
        || !in_array($product->post_status, meditrendy_product_workflow_status_slugs(), true)
    ) {
        return;
    }

    global $wp_query;

    $wp_query->set_404();
    status_header(404);
    nocache_headers();
}
add_action('template_redirect', 'meditrendy_block_workflow_products_on_storefront', 0);

/**
 * Keep custom-workflow products invisible even when another plugin loads them.
 *
 * @param bool       $visible Current visibility.
 * @param int        $product_id Product ID.
 * @return bool
 */
function meditrendy_hide_workflow_products($visible, $product_id) {
    if (meditrendy_product_has_workflow_status($product_id)) {
        return false;
    }

    return $visible;
}
add_filter('woocommerce_product_is_visible', 'meditrendy_hide_workflow_products', 10, 2);

/**
 * Prevent custom-workflow products from being purchased by direct requests.
 *
 * @param bool       $purchasable Current purchasability.
 * @param WC_Product $product     Product object.
 * @return bool
 */
function meditrendy_disable_workflow_product_purchases($purchasable, $product) {
    if (
        $product instanceof WC_Product
        && meditrendy_product_has_workflow_status($product->get_id())
    ) {
        return false;
    }

    return $purchasable;
}
add_filter('woocommerce_is_purchasable', 'meditrendy_disable_workflow_product_purchases', 10, 2);
