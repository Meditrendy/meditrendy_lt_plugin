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
            'color'      => '#6b4f00',
            'background' => '#fff0a6',
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
 * Return statuses available in the inline Products-list status picker.
 *
 * @return array<string, string>
 */
function meditrendy_product_inline_edit_statuses() {
    $statuses = [
        'publish' => __('Opublikowany', 'meditrendy-core'),
        'pending' => __('Oczekuje na przegląd', 'meditrendy-core'),
        'draft'   => __('Szkic', 'meditrendy-core'),
    ];

    foreach (meditrendy_product_workflow_statuses() as $status => $settings) {
        $statuses[$status] = $settings['label'];
    }

    return $statuses;
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
            'color'      => '#8a3b00',
            'background' => '#f8dcc5',
        ],
        'future' => [
            'label'      => __('Zaplanowano', 'meditrendy-core'),
            'color'      => '#135e96',
            'background' => '#d7e9f7',
        ],
        'private' => [
            'label'      => __('Prywatny', 'meditrendy-core'),
            'color'      => '#8a245d',
            'background' => '#f3dce9',
        ],
        'trash' => [
            'label'      => __('Kosz', 'meditrendy-core'),
            'color'      => '#7c2d12',
            'background' => '#f4ded4',
        ],
        'auto-draft' => [
            'label'      => __('Automatyczny szkic', 'meditrendy-core'),
            'color'      => '#4d5055',
            'background' => '#d9dee3',
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

    $current_status = get_post_status($post_id);
    $badge          = meditrendy_product_status_badge($current_status);

    printf(
        '<button type="button" class="meditrendy-product-status-badge" data-product-id="%1$d" data-current-status="%2$s" style="--med-status-color:%3$s;--med-status-background:%4$s" aria-label="%5$s" title="%6$s">%7$s</button>',
        absint($post_id),
        esc_attr($current_status),
        esc_attr($badge['color']),
        esc_attr($badge['background']),
        esc_attr(sprintf(
            /* translators: %s: current product status. */
            __('Status: %s. Kliknij, aby szybko zmienić.', 'meditrendy-core'),
            $badge['label']
        )),
        esc_attr__('Kliknij, aby szybko zmienić status', 'meditrendy-core'),
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
            appearance: none;
            display: inline-block;
            max-width: 100%;
            padding: 3px 8px;
            border: 1px solid var(--med-status-color);
            border-radius: 999px;
            color: var(--med-status-color);
            background: var(--med-status-background);
            cursor: pointer;
            font-family: inherit;
            font-size: inherit;
            font-weight: 600;
            line-height: 1.35;
            overflow-wrap: anywhere;
        }

        .meditrendy-product-status-badge:hover {
            box-shadow: 0 0 0 1px var(--med-status-color);
        }

        .meditrendy-product-status-badge:focus-visible {
            outline: 2px solid var(--med-status-color);
            outline-offset: 2px;
        }

        .meditrendy-product-status-select {
            width: 100%;
            min-width: 16ch;
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
 * Replace a clicked badge with the compact inline status selector.
 *
 * @param string $hook_suffix Current admin page.
 */
function meditrendy_product_status_quick_edit_script($hook_suffix) {
    if ($hook_suffix !== 'edit.php') {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'edit-product') {
        return;
    }

    $statuses = [];
    foreach (meditrendy_product_inline_edit_statuses() as $status => $label) {
        $statuses[$status] = [
            'label' => $label,
        ];
    }

    $config = [
        'ajaxUrl'       => admin_url('admin-ajax.php'),
        'nonce'         => wp_create_nonce('meditrendy_update_product_status'),
        'statuses'      => $statuses,
        'selectLabel'   => __('Wybierz nowy status produktu', 'meditrendy-core'),
        'badgeTitle'    => __('Kliknij, aby szybko zmienić status', 'meditrendy-core'),
        'badgeAria'     => __('Status: %s. Kliknij, aby szybko zmienić.', 'meditrendy-core'),
        'errorMessage'  => __('Nie udało się zmienić statusu produktu. Odśwież stronę i spróbuj ponownie.', 'meditrendy-core'),
    ];

    $script = sprintf(<<<'JS'
(function () {
const config = %s;

document.addEventListener('click', (event) => {
    const badge = event.target.closest('.meditrendy-product-status-badge');
    if (!badge) {
        return;
    }

    event.preventDefault();
    const select = document.createElement('select');
    select.className = 'meditrendy-product-status-select';
    select.setAttribute('aria-label', config.selectLabel);

    Object.entries(config.statuses).forEach(([status, settings]) => {
        select.add(new Option(settings.label, status, false, status === badge.dataset.currentStatus));
    });

    if (!Object.prototype.hasOwnProperty.call(config.statuses, badge.dataset.currentStatus)) {
        select.add(new Option(badge.textContent, badge.dataset.currentStatus, true, true), 0);
    }

    badge.replaceWith(select);
    select.focus({ preventScroll: true });

    let saving = false;
    const restoreBadge = () => {
        if (select.isConnected) {
            select.replaceWith(badge);
        }
    };

    select.addEventListener('blur', () => {
        window.setTimeout(() => {
            if (!saving) {
                restoreBadge();
            }
        }, 0);
    });

    select.addEventListener('keydown', (keyEvent) => {
        if (keyEvent.key === 'Escape') {
            keyEvent.preventDefault();
            restoreBadge();
            badge.focus();
        }
    });

    select.addEventListener('change', async () => {
        const newStatus = select.value;
        if (newStatus === badge.dataset.currentStatus) {
            restoreBadge();
            return;
        }

        saving = true;
        select.disabled = true;

        const request = new URLSearchParams({
            action: 'meditrendy_update_product_status',
            nonce: config.nonce,
            product_id: badge.dataset.productId,
            status: newStatus,
        });

        try {
            const response = await fetch(config.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                body: request.toString(),
            });
            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error('Status update failed');
            }

            const updated = result.data;
            badge.dataset.currentStatus = updated.status;
            badge.textContent = updated.label;
            badge.style.setProperty('--med-status-color', updated.color);
            badge.style.setProperty('--med-status-background', updated.background);
            badge.title = config.badgeTitle;
            badge.setAttribute('aria-label', config.badgeAria.replace('%%s', updated.label));
            select.replaceWith(badge);
            badge.focus();
        } catch (error) {
            restoreBadge();
            window.alert(config.errorMessage);
        }
    });

    if (typeof select.showPicker === 'function') {
        try {
            select.showPicker();
        } catch (error) {
            // The focused select remains usable when the browser blocks showPicker().
        }
    }
});
}());
JS,
        wp_json_encode($config)
    );

    wp_add_inline_script('inline-edit-post', $script, 'after');
}
add_action('admin_enqueue_scripts', 'meditrendy_product_status_quick_edit_script');

/**
 * Save a product status selected from the inline Products-list picker.
 */
function meditrendy_ajax_update_product_status() {
    check_ajax_referer('meditrendy_update_product_status', 'nonce');

    $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    $status     = isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : '';
    $allowed    = meditrendy_product_inline_edit_statuses();

    if (
        !$product_id
        || get_post_type($product_id) !== 'product'
        || !current_user_can('edit_post', $product_id)
        || !isset($allowed[$status])
    ) {
        wp_send_json_error(['message' => __('Nieprawidłowa zmiana statusu produktu.', 'meditrendy-core')], 403);
    }

    $product_type = get_post_type_object('product');
    if (
        $status === 'publish'
        && (!$product_type || !current_user_can($product_type->cap->publish_posts))
    ) {
        wp_send_json_error(['message' => __('Nie masz uprawnień do publikowania produktów.', 'meditrendy-core')], 403);
    }

    $result = wp_update_post([
        'ID'          => $product_id,
        'post_status' => $status,
    ], true);

    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()], 500);
    }

    $badge = meditrendy_product_status_badge($status);

    wp_send_json_success([
        'status'     => $status,
        'label'      => $badge['label'],
        'color'      => $badge['color'],
        'background' => $badge['background'],
    ]);
}
add_action('wp_ajax_meditrendy_update_product_status', 'meditrendy_ajax_update_product_status');

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
