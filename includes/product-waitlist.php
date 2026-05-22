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

    if (get_option('meditrendy_stock_waitlist_db_version') === '1') {
        return;
    }

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table = meditrendy_stock_waitlist_table_name();
    $charset_collate = $wpdb->get_charset_collate();

    dbDelta(
        "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
            email varchar(190) NOT NULL,
            created_at datetime NOT NULL,
            notified_at datetime NULL DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY product_email (product_id,email),
            KEY product_id (product_id),
            KEY notified_at (notified_at)
        ) {$charset_collate};"
    );

    update_option('meditrendy_stock_waitlist_db_version', '1');
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
        'isInStock' => $product->is_in_stock(),
    ];
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
    $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    $product = $product_id && function_exists('wc_get_product') ? wc_get_product($product_id) : null;

    if (!$email || !is_email($email)) {
        wp_send_json_error(['message' => __('Įveskite teisingą el. pašto adresą.', 'meditrendy-core')], 400);
    }

    if (!$product) {
        wp_send_json_error(['message' => __('Prekė nerasta.', 'meditrendy-core')], 404);
    }

    if ($product->is_in_stock()) {
        wp_send_json_error(['message' => __('Ši prekė jau yra prekyboje.', 'meditrendy-core')], 409);
    }

    global $wpdb;

    $table = meditrendy_stock_waitlist_table_name();
    $parent_id = $product->is_type('variation') ? $product->get_parent_id() : 0;
    $now = current_time('mysql');

    $wpdb->query(
        $wpdb->prepare(
            "INSERT INTO {$table} (product_id, parent_id, email, created_at, notified_at)
            VALUES (%d, %d, %s, %s, NULL)
            ON DUPLICATE KEY UPDATE created_at = VALUES(created_at), notified_at = NULL",
            $product_id,
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

    if (get_transient($lock_key)) {
        return;
    }

    set_transient($lock_key, 1, 10 * MINUTE_IN_SECONDS);
    meditrendy_stock_waitlist_send_notifications($product_id);
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
            "SELECT id, email FROM {$table} WHERE product_id = %d AND notified_at IS NULL",
            $product_id
        )
    );

    if (!$rows) {
        return;
    }

    $subject = __('Prekė vėl prekyboje', 'meditrendy-core');
    $product_name = $product->get_name();
    $product_link = $product->get_permalink();
    $headers = ['Content-Type: text/html; charset=UTF-8'];

    foreach ($rows as $row) {
        $message = sprintf(
            '<p>%1$s</p><p>%2$s <strong>%3$s</strong> %4$s</p><p><a href="%5$s">%6$s</a></p>',
            esc_html__('Sveiki,', 'meditrendy-core'),
            esc_html__('Prekė', 'meditrendy-core'),
            esc_html($product_name),
            esc_html__('vėl yra prekyboje.', 'meditrendy-core'),
            esc_url($product_link),
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

add_action('init', 'meditrendy_stock_waitlist_install', 5);
add_action('wp_enqueue_scripts', 'meditrendy_waitlist_enqueue_assets', 30);
add_action('wp_ajax_meditrendy_stock_waitlist_subscribe', 'meditrendy_stock_waitlist_subscribe');
add_action('wp_ajax_nopriv_meditrendy_stock_waitlist_subscribe', 'meditrendy_stock_waitlist_subscribe');
add_action('woocommerce_product_set_stock_status', 'meditrendy_waitlist_trigger_back_in_stock_email', 10, 3);
add_action('woocommerce_variation_set_stock_status', 'meditrendy_waitlist_trigger_back_in_stock_email', 10, 3);
add_action('woocommerce_product_object_updated_props', 'meditrendy_waitlist_trigger_back_in_stock_email_from_props', 10, 2);
