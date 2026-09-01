<?php
/**
 * Private product name used as an additional storefront search term.
 *
 * The value is deliberately stored as protected post meta and is never added
 * to product output. It applies to every product type, including product sets.
 */

if (!defined('ABSPATH')) {
    exit;
}

const MEDITRENDY_INTERNAL_PRODUCT_NAME_META_KEY = '_meditrendy_internal_product_name';

/**
 * Add the internal name to the General section of the product editor.
 */
function meditrendy_internal_product_name_field() {
    if (!function_exists('woocommerce_wp_text_input')) {
        return;
    }

    woocommerce_wp_text_input([
        'id'          => MEDITRENDY_INTERNAL_PRODUCT_NAME_META_KEY,
        'label'       => __('Internal search name', 'meditrendy-core'),
        'description' => __('An optional second name used only for product search. Customers never see this name.', 'meditrendy-core'),
        'desc_tip'    => true,
        'type'        => 'text',
    ]);
}
add_action('woocommerce_product_options_general_product_data', 'meditrendy_internal_product_name_field');

/**
 * Save or remove the internal name through the WooCommerce product object.
 *
 * @param WC_Product $product Product being saved.
 */
function meditrendy_save_internal_product_name($product) {
    if (
        !$product instanceof WC_Product
        || !array_key_exists(MEDITRENDY_INTERNAL_PRODUCT_NAME_META_KEY, $_POST)
    ) {
        return;
    }

    $value = sanitize_text_field(wp_unslash($_POST[MEDITRENDY_INTERNAL_PRODUCT_NAME_META_KEY]));

    if ($value === '') {
        $product->delete_meta_data(MEDITRENDY_INTERNAL_PRODUCT_NAME_META_KEY);
        return;
    }

    $product->update_meta_data(MEDITRENDY_INTERNAL_PRODUCT_NAME_META_KEY, $value);
}
add_action('woocommerce_admin_process_product_object', 'meditrendy_save_internal_product_name');

/**
 * Add the private name next to the public product name in the Products list.
 *
 * @param array $columns Product list columns.
 * @return array
 */
function meditrendy_internal_product_name_admin_column($columns) {
    $internal_name_column = [
        'meditrendy_internal_product_name' => __('Internal name', 'meditrendy-core'),
    ];

    $name_position = array_search('name', array_keys($columns), true);

    if ($name_position === false) {
        return $columns + $internal_name_column;
    }

    return array_slice($columns, 0, $name_position + 1, true)
        + $internal_name_column
        + array_slice($columns, $name_position + 1, null, true);
}
add_filter('manage_edit-product_columns', 'meditrendy_internal_product_name_admin_column', 20);

/**
 * Render the private name in the Products list.
 *
 * @param string $column  Current column key.
 * @param int    $post_id Product ID.
 */
function meditrendy_render_internal_product_name_admin_column($column, $post_id) {
    if ($column !== 'meditrendy_internal_product_name') {
        return;
    }

    $internal_name = get_post_meta($post_id, MEDITRENDY_INTERNAL_PRODUCT_NAME_META_KEY, true);

    echo $internal_name !== '' ? esc_html($internal_name) : '<span aria-hidden="true">&mdash;</span>';
}
add_action('manage_product_posts_custom_column', 'meditrendy_render_internal_product_name_admin_column', 10, 2);

/**
 * Keep the custom column from collapsing in WooCommerce's fixed-width table.
 */
function meditrendy_internal_product_name_admin_column_styles() {
    ?>
    <style>
        body.post-type-product table.wp-list-table th#meditrendy_internal_product_name,
        body.post-type-product table.wp-list-table .column-meditrendy_internal_product_name {
            width: 16ch !important;
            min-width: 16ch !important;
            box-sizing: border-box;
            white-space: normal;
            word-break: normal;
            overflow-wrap: break-word;
        }

        body.post-type-product table.wp-list-table th#meditrendy_internal_product_name {
            white-space: nowrap;
        }

        @media screen and (max-width: 782px) {
            body.post-type-product table.wp-list-table th#meditrendy_internal_product_name,
            body.post-type-product table.wp-list-table .column-meditrendy_internal_product_name {
                width: auto !important;
                min-width: 0 !important;
            }
        }
    </style>
    <?php
}
add_action('admin_head', 'meditrendy_internal_product_name_admin_column_styles', 100);

/**
 * Determine whether a WordPress search is scoped to WooCommerce products.
 *
 * @param WP_Query $query Current query.
 * @return bool
 */
function meditrendy_internal_product_name_is_product_search($query) {
    if (!$query instanceof WP_Query || trim((string) $query->get('s')) === '') {
        return false;
    }

    $post_type = $query->get('post_type');

    if (is_array($post_type)) {
        return in_array('product', $post_type, true);
    }

    return $post_type === 'product';
}

/**
 * Build the private-name side of a normal WordPress product search.
 *
 * All positive words must occur in the internal name. Excluded search words
 * retain WordPress's normal leading-minus behavior.
 *
 * @param WP_Query $query Current query.
 * @return string Prepared EXISTS expression, or an empty string.
 */
function meditrendy_internal_product_name_search_expression($query) {
    global $wpdb;

    $terms = (array) $query->get('search_terms');
    if (!$terms) {
        $terms = [(string) $query->get('s')];
    }

    $exact            = (bool) $query->get('exact');
    $wildcard         = $exact ? '' : '%';
    $exclusion_prefix = (string) apply_filters('wp_query_search_exclusion_prefix', '-');
    $conditions       = [];
    $has_positive     = false;

    foreach ($terms as $term) {
        $term    = (string) $term;
        $exclude = $exclusion_prefix !== '' && str_starts_with($term, $exclusion_prefix);

        if ($exclude) {
            $term = substr($term, strlen($exclusion_prefix));
        } else {
            $has_positive = true;
        }

        if ($term === '') {
            continue;
        }

        $like         = $wildcard . $wpdb->esc_like($term) . $wildcard;
        $conditions[] = $wpdb->prepare(
            'meditrendy_internal_name_search.meta_value ' . ($exclude ? 'NOT LIKE' : 'LIKE') . ' %s',
            $like
        );
    }

    if (!$has_positive || !$conditions) {
        return '';
    }

    return "EXISTS (
        SELECT 1
        FROM {$wpdb->postmeta} AS meditrendy_internal_name_search
        WHERE meditrendy_internal_name_search.post_id = {$wpdb->posts}.ID
        AND meditrendy_internal_name_search.meta_key = '" . esc_sql(MEDITRENDY_INTERNAL_PRODUCT_NAME_META_KEY) . "'
        AND " . implode(' AND ', $conditions) . '
    )';
}

/**
 * Add the internal name as a fallback to regular product searches.
 *
 * Wrapping the existing clause preserves search behavior added by WordPress,
 * WooCommerce, and other plugins instead of replacing it.
 *
 * @param string   $search Existing search SQL.
 * @param WP_Query $query  Current query.
 * @return string
 */
function meditrendy_search_products_by_internal_name($search, $query) {
    global $wpdb;

    if ($search === '' || !meditrendy_internal_product_name_is_product_search($query)) {
        return $search;
    }

    $internal_search = meditrendy_internal_product_name_search_expression($query);
    if ($internal_search === '') {
        return $search;
    }

    $password_clause = " AND ({$wpdb->posts}.post_password = '') ";
    $base_search     = str_replace($password_clause, '', $search);
    $base_search     = preg_replace('/^\s*AND\s+/i', '', trim($base_search));

    if ($base_search === null || $base_search === '') {
        return $search;
    }

    $search = " AND (({$base_search}) OR ({$internal_search})) ";

    if (strpos($search, $password_clause) === false && strpos($search, 'post_password') === false && !is_user_logged_in()) {
        $search .= $password_clause;
    }

    return $search;
}
add_filter('posts_search', 'meditrendy_search_products_by_internal_name', 100, 2);

/**
 * Join the private name for FiboSearch's free/native search engine.
 *
 * @param string $join Existing SQL joins.
 * @return string
 */
function meditrendy_fibosearch_internal_name_join($join) {
    global $wpdb;

    if (strpos($join, 'meditrendy_fibo_internal_name') !== false) {
        return $join;
    }

    return $join . $wpdb->prepare(
        " LEFT JOIN {$wpdb->postmeta} AS meditrendy_fibo_internal_name
        ON ({$wpdb->posts}.ID = meditrendy_fibo_internal_name.post_id
        AND meditrendy_fibo_internal_name.meta_key = %s)",
        MEDITRENDY_INTERNAL_PRODUCT_NAME_META_KEY
    );
}
add_filter('dgwt/wcas/native/search_query/join', 'meditrendy_fibosearch_internal_name_join');

/**
 * Match the private name in FiboSearch live suggestions and result IDs.
 *
 * @param string $search Existing FiboSearch expression.
 * @param string $like   Prepared wildcard value supplied by FiboSearch.
 * @return string
 */
function meditrendy_fibosearch_internal_name_condition($search, $like) {
    global $wpdb;

    return $search . $wpdb->prepare(
        ' OR (meditrendy_fibo_internal_name.meta_value LIKE %s)',
        $like
    );
}
add_filter('dgwt/wcas/native/search_query/search_or', 'meditrendy_fibosearch_internal_name_condition', 10, 2);
