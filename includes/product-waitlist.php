<?php
if (!defined('ABSPATH')) exit;

function meditrendy_waitlist_button_text() {
    return __('Informuokite mane, kai bus prekyboje', 'meditrendy-core');
}

function meditrendy_stock_waitlist_table_name() {
    global $wpdb;

    return $wpdb->prefix . 'meditrendy_stock_waitlist';
}

function meditrendy_stock_waitlist_install() {
    global $wpdb;

    if (get_option('meditrendy_stock_waitlist_db_version') === '3') {
        return;
    }

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table = meditrendy_stock_waitlist_table_name();
    $charset_collate = $wpdb->get_charset_collate();
    $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));

    if ($table_exists) {
        foreach (['product_email', 'product_email_set'] as $index) {
            $index_exists = $wpdb->get_var($wpdb->prepare("SHOW INDEX FROM {$table} WHERE Key_name = %s", $index));

            if ($index_exists) {
                $wpdb->query("ALTER TABLE {$table} DROP INDEX {$index}");
            }
        }
    }

    dbDelta(
        "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            set_id bigint(20) unsigned NOT NULL DEFAULT 0,
            set_hash varchar(32) NOT NULL DEFAULT '',
            set_items longtext NULL,
            parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
            email varchar(190) NOT NULL,
            created_at datetime NOT NULL,
            notified_at datetime NULL DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY product_email_set (product_id,email,set_id,set_hash),
            KEY product_id (product_id),
            KEY set_id (set_id),
            KEY notified_at (notified_at)
        ) {$charset_collate};"
    );

    update_option('meditrendy_stock_waitlist_db_version', '3');
}

function meditrendy_stock_waitlist_product_data() {
    if (!function_exists('is_product') || !is_product() || !function_exists('wc_get_product')) {
        return null;
    }

    $product_id = get_queried_object_id();
    $product = $product_id ? wc_get_product($product_id) : null;

    if (!$product) {
        return null;
    }

    return [
        'productId' => $product->get_id(),
        'isVariable' => $product->is_type('variable'),
        'isSet' => $product->is_type('woosb'),
        'isInStock' => $product->is_in_stock(),
    ];
}

function meditrendy_waitlist_product_available($product, $qty = 1) {
    if (!$product || !is_a($product, 'WC_Product') || !$product->is_purchasable()) {
        return false;
    }

    if ($product->is_type('variable')) {
        if (!$product->child_is_in_stock() && !$product->child_is_on_backorder()) {
            return false;
        }

        if ($qty <= 0) {
            return true;
        }

        foreach ($product->get_available_variations('objects') as $variation) {
            if ($variation->is_purchasable() && $variation->is_in_stock() && $variation->has_enough_stock($qty)) {
                return true;
            }
        }

        return false;
    }

    if (!$product->is_in_stock()) {
        return false;
    }

    return $qty <= 0 || $product->has_enough_stock($qty);
}

function meditrendy_waitlist_set_items($set_id, $set_items = '') {
    $set = $set_id && function_exists('wc_get_product') ? wc_get_product($set_id) : null;

    if (!$set || !$set->is_type('woosb') || !method_exists($set, 'get_items')) {
        return [];
    }

    if ($set_items && method_exists($set, 'build_items')) {
        $set = clone $set;
        $set->build_items($set_items);
    }

    return (array) $set->get_items();
}

function meditrendy_waitlist_product_matches_id($product, $product_id) {
    if (!$product || !is_a($product, 'WC_Product') || !function_exists('wc_get_product')) {
        return false;
    }

    $product_id = absint($product_id);

    if ((int) $product->get_id() === $product_id) {
        return true;
    }

    if ($product->is_type('variation') && (int) $product->get_parent_id() === $product_id) {
        return true;
    }

    $changed_product = wc_get_product($product_id);

    return $changed_product
        && $changed_product->is_type('variation')
        && (int) $changed_product->get_parent_id() === (int) $product->get_id();
}

function meditrendy_waitlist_set_contains_product($set_id, $product_id, $set_items = '') {
    foreach (meditrendy_waitlist_set_items($set_id, $set_items) as $item) {
        $item_product = !empty($item['id']) ? wc_get_product(absint($item['id'])) : null;

        if (meditrendy_waitlist_product_matches_id($item_product, $product_id)) {
            return true;
        }
    }

    return false;
}

function meditrendy_waitlist_set_available($set_id, $set_items = '') {
    $set = $set_id && function_exists('wc_get_product') ? wc_get_product($set_id) : null;

    if (!$set || !$set->is_type('woosb') || !$set->is_purchasable()) {
        return false;
    }

    foreach (meditrendy_waitlist_set_items($set_id, $set_items) as $item) {
        $item_product = !empty($item['id']) ? wc_get_product(absint($item['id'])) : null;

        if (!$item_product) {
            return false;
        }

        $qty = isset($item['qty']) ? (float) $item['qty'] : 1;

        if (!empty($item['optional'])) {
            $qty = !empty($item['min']) ? (float) $item['min'] : 0;
        }

        if ($qty <= 0) {
            continue;
        }

        if (!meditrendy_waitlist_product_available($item_product, $qty)) {
            return false;
        }
    }

    return true;
}

function meditrendy_waitlist_row_ready($row, $product) {
    $set_id = isset($row->set_id) ? absint($row->set_id) : 0;

    if ($set_id) {
        $set_items = isset($row->set_items) ? (string) $row->set_items : '';

        return meditrendy_waitlist_set_available($set_id, $set_items);
    }

    return meditrendy_waitlist_product_available($product, 1);
}

function meditrendy_waitlist_enqueue_assets() {
    if (is_admin() || !function_exists('is_product') || !is_product()) {
        return;
    }

    $product_data = meditrendy_stock_waitlist_product_data();

    if (!$product_data) {
        return;
    }

    $script_path = MEDITRENDY_CORE_DIR . 'assets/js/product-waitlist.js';
    $style_path = MEDITRENDY_CORE_DIR . 'assets/css/product-waitlist.css';

    wp_enqueue_script(
        'meditrendy-product-waitlist',
        MEDITRENDY_CORE_URL . 'assets/js/product-waitlist.js',
        ['jquery', 'wc-add-to-cart-variation'],
        file_exists($script_path) ? filemtime($script_path) : '1.0',
        true
    );

    wp_localize_script(
        'meditrendy-product-waitlist',
        'MeditrendyProductWaitlist',
        [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('meditrendy_stock_waitlist'),
            'product' => $product_data,
            'labels' => [
                'link' => meditrendy_waitlist_button_text(),
                'heading' => __('Pranešimas apie prekę', 'meditrendy-core'),
                'body' => __('Įveskite el. pašto adresą ir informuosime, kai pasirinkta prekė vėl bus prekyboje.', 'meditrendy-core'),
                'email' => __('El. pašto adresas', 'meditrendy-core'),
                'submit' => __('Informuokite mane', 'meditrendy-core'),
                'close' => __('Uždaryti', 'meditrendy-core'),
                'success' => __('Ačiū. Informuosime jus el. paštu, kai prekė vėl bus prekyboje.', 'meditrendy-core'),
                'error' => __('Nepavyko išsaugoti. Bandykite dar kartą.', 'meditrendy-core'),
                'invalidEmail' => __('Įveskite teisingą el. pašto adresą.', 'meditrendy-core'),
            ],
        ]
    );

    wp_enqueue_style(
        'meditrendy-product-waitlist',
        MEDITRENDY_CORE_URL . 'assets/css/product-waitlist.css',
        [],
        file_exists($style_path) ? filemtime($style_path) : '1.0'
    );
}

function meditrendy_stock_waitlist_subscribe() {
    check_ajax_referer('meditrendy_stock_waitlist', 'nonce');

    meditrendy_stock_waitlist_install();

    $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    $set_id = isset($_POST['set_id']) ? absint($_POST['set_id']) : 0;
    $set_items = isset($_POST['set_items']) ? substr(wp_strip_all_tags((string) wp_unslash($_POST['set_items'])), 0, 5000) : '';
    $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    $product = $product_id && function_exists('wc_get_product') ? wc_get_product($product_id) : null;
    $set = $set_id && function_exists('wc_get_product') ? wc_get_product($set_id) : null;

    if (!$email || !is_email($email)) {
        wp_send_json_error(['message' => __('Įveskite teisingą el. pašto adresą.', 'meditrendy-core')], 400);
    }

    if (!$product) {
        wp_send_json_error(['message' => __('Prekė nerasta.', 'meditrendy-core')], 404);
    }

    if ($set_id && (!$set || !$set->is_type('woosb'))) {
        $set_id = 0;
        $set_items = '';
    }

    if (!$set_id && $product->is_in_stock()) {
        wp_send_json_error(['message' => __('Ši prekė jau yra prekyboje.', 'meditrendy-core')], 409);
    }

    if ($set_id && meditrendy_waitlist_set_available($set_id, $set_items)) {
        wp_send_json_error(['message' => __('Ši prekė jau yra prekyboje.', 'meditrendy-core')], 409);
    }

    global $wpdb;

    $table = meditrendy_stock_waitlist_table_name();
    $parent_id = $product->is_type('variation') ? $product->get_parent_id() : 0;
    $set_hash = $set_id && $set_items ? md5($set_items) : '';
    $now = current_time('mysql');

    $wpdb->query(
        $wpdb->prepare(
            "INSERT INTO {$table} (product_id, set_id, set_hash, set_items, parent_id, email, created_at, notified_at)
            VALUES (%d, %d, %s, %s, %d, %s, %s, NULL)
            ON DUPLICATE KEY UPDATE created_at = VALUES(created_at), notified_at = NULL",
            $product_id,
            $set_id,
            $set_hash,
            $set_items,
            $parent_id,
            $email,
            $now
        )
    );

    wp_send_json_success(['message' => __('Ačiū. Informuosime jus el. paštu, kai prekė vėl bus prekyboje.', 'meditrendy-core')]);
}

function meditrendy_waitlist_trigger_back_in_stock_email($product_id, $stock_status = '', $product = null) {
    $product_id = absint($product_id);

    if (!$product_id || $stock_status !== 'instock') {
        return;
    }

    $lock_key = 'meditrendy_waitlist_stock_email_' . $product_id;

    if (!get_transient($lock_key)) {
        set_transient($lock_key, 1, 10 * MINUTE_IN_SECONDS);
        meditrendy_stock_waitlist_send_notifications($product_id);
    }

    meditrendy_stock_waitlist_send_set_notifications_for_changed_product($product_id);
}

function meditrendy_waitlist_trigger_back_in_stock_email_from_props($product, $updated_props) {
    if (!class_exists('WC_Product') || !$product instanceof WC_Product || !in_array('stock_status', (array) $updated_props, true)) {
        return;
    }

    meditrendy_waitlist_trigger_back_in_stock_email($product->get_id(), $product->get_stock_status(), $product);
}

function meditrendy_stock_waitlist_send_notifications($product_id) {
    meditrendy_stock_waitlist_install();

    $product = function_exists('wc_get_product') ? wc_get_product($product_id) : null;

    if (!$product || !$product->is_in_stock()) {
        return;
    }

    global $wpdb;

    $table = meditrendy_stock_waitlist_table_name();
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, email, set_id, set_items FROM {$table} WHERE product_id = %d AND notified_at IS NULL",
            $product_id
        )
    );

    if (!$rows) {
        return;
    }

    $subject = __('Prekė vėl prekyboje', 'meditrendy-core');
    $headers = ['Content-Type: text/html; charset=UTF-8'];

    foreach ($rows as $row) {
        if (!meditrendy_waitlist_row_ready($row, $product)) {
            continue;
        }

        $mail_product = !empty($row->set_id) ? wc_get_product(absint($row->set_id)) : $product;
        $mail_product = $mail_product ?: $product;

        $message = sprintf(
            '<p>%1$s</p><p>%2$s <strong>%3$s</strong> %4$s</p><p><a href="%5$s">%6$s</a></p>',
            esc_html__('Sveiki,', 'meditrendy-core'),
            esc_html__('Prekė', 'meditrendy-core'),
            esc_html($mail_product->get_name()),
            esc_html__('vėl yra prekyboje.', 'meditrendy-core'),
            esc_url($mail_product->get_permalink()),
            esc_html__('Peržiūrėti prekę', 'meditrendy-core')
        );

        wp_mail($row->email, $subject, $message, $headers);

        $wpdb->update(
            $table,
            ['notified_at' => current_time('mysql')],
            ['id' => (int) $row->id],
            ['%s'],
            ['%d']
        );
    }
}

function meditrendy_stock_waitlist_send_set_notifications_for_changed_product($product_id) {
    meditrendy_stock_waitlist_install();

    global $wpdb;

    $table = meditrendy_stock_waitlist_table_name();
    $sets = $wpdb->get_results("SELECT DISTINCT set_id, set_items FROM {$table} WHERE set_id > 0 AND notified_at IS NULL");

    if (!$sets) {
        return;
    }

    foreach ($sets as $set_row) {
        $set_id = absint($set_row->set_id);
        $set_items = isset($set_row->set_items) ? (string) $set_row->set_items : '';

        if (!$set_id || !meditrendy_waitlist_set_contains_product($set_id, $product_id, $set_items)) {
            continue;
        }

        $set_lock_key = 'meditrendy_waitlist_set_email_' . $set_id . '_' . md5($set_items);

        if (get_transient($set_lock_key)) {
            continue;
        }

        set_transient($set_lock_key, 1, 10 * MINUTE_IN_SECONDS);
        meditrendy_stock_waitlist_send_notifications_for_set($set_id, $set_items);
    }
}

function meditrendy_stock_waitlist_send_notifications_for_set($set_id, $set_items = '') {
    if (!meditrendy_waitlist_set_available($set_id, $set_items)) {
        return;
    }

    global $wpdb;

    $table = meditrendy_stock_waitlist_table_name();
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT DISTINCT product_id FROM {$table} WHERE set_id = %d AND set_hash = %s AND notified_at IS NULL",
            $set_id,
            $set_items ? md5($set_items) : ''
        )
    );

    foreach ($rows as $row) {
        meditrendy_stock_waitlist_send_notifications(absint($row->product_id));
    }
}

add_action('init', 'meditrendy_stock_waitlist_install', 5);
add_action('wp_enqueue_scripts', 'meditrendy_waitlist_enqueue_assets', 30);
add_action('wp_ajax_meditrendy_stock_waitlist_subscribe', 'meditrendy_stock_waitlist_subscribe');
add_action('wp_ajax_nopriv_meditrendy_stock_waitlist_subscribe', 'meditrendy_stock_waitlist_subscribe');
add_action('woocommerce_product_set_stock_status', 'meditrendy_waitlist_trigger_back_in_stock_email', 10, 3);
add_action('woocommerce_variation_set_stock_status', 'meditrendy_waitlist_trigger_back_in_stock_email', 10, 3);
add_action('woocommerce_product_object_updated_props', 'meditrendy_waitlist_trigger_back_in_stock_email_from_props', 10, 2);
