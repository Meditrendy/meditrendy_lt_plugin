<?php
if (!defined('ABSPATH')) exit;

function meditrendy_waitlist_button_text() {
    return __('Informuokite mane, kai bus prekyboje', 'meditrendy-core');
}

function meditrendy_waitlist_language() {
    if (function_exists('meditrendy_core_current_language')) {
        return meditrendy_core_current_language();
    }

    if (function_exists('pll_current_language')) {
        $language = strtolower((string) pll_current_language('slug'));

        if ($language !== '') {
            return $language === 'ee' ? 'et' : $language;
        }
    }

    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));

    if (strpos($host, 'meditrendy.ee') !== false) {
        return 'et';
    }

    if (strpos($host, 'meditrendy.lv') !== false) {
        return 'lv';
    }

    return 'lt';
}

function meditrendy_waitlist_labels() {
    return [
        'link' => __('Informuokite mane, kai bus prekyboje', 'meditrendy-core'),
        'heading' => __('Pranešimas apie prekę', 'meditrendy-core'),
        'body' => __('Įveskite el. pašto adresą ir informuosime, kai pasirinkta prekė vėl bus prekyboje.', 'meditrendy-core'),
        'email' => __('El. pašto adresas', 'meditrendy-core'),
        'submit' => __('Informuokite mane', 'meditrendy-core'),
        'close' => __('Uždaryti', 'meditrendy-core'),
        'success' => __('Ačiū. Informuosime jus el. paštu, kai prekė vėl bus prekyboje.', 'meditrendy-core'),
        'error' => __('Nepavyko išsaugoti. Bandykite dar kartą.', 'meditrendy-core'),
        'invalidEmail' => __('Įveskite teisingą el. pašto adresą.', 'meditrendy-core'),
        'alreadyInStock' => __('Ši prekė jau yra prekyboje.', 'meditrendy-core'),
        'productNotFound' => __('Prekė nerasta.', 'meditrendy-core'),
    ];

    $labels = [
        'lt' => [
            'link' => 'Informuokite mane, kai bus prekyboje',
            'heading' => 'Pranešimas apie prekę',
            'body' => 'Įveskite el. pašto adresą ir informuosime, kai pasirinkta prekė vėl bus prekyboje.',
            'email' => 'El. pašto adresas',
            'submit' => 'Informuokite mane',
            'close' => 'Uždaryti',
            'success' => 'Ačiū. Informuosime jus el. paštu, kai prekė vėl bus prekyboje.',
            'error' => 'Nepavyko išsaugoti. Bandykite dar kartą.',
            'invalidEmail' => 'Įveskite teisingą el. pašto adresą.',
            'alreadyInStock' => 'Ši prekė jau yra prekyboje.',
            'productNotFound' => 'Prekė nerasta.',
        ],
        'lv' => [
            'link' => 'Paziņot man, kad būs pieejams',
            'heading' => 'Paziņojums par preci',
            'body' => 'Ievadiet e-pasta adresi, un mēs informēsim, kad izvēlētā prece atkal būs pieejama.',
            'email' => 'E-pasta adrese',
            'submit' => 'Paziņot man',
            'close' => 'Aizvērt',
            'success' => 'Paldies. Informēsim jūs e-pastā, kad prece atkal būs pieejama.',
            'error' => 'Neizdevās saglabāt. Mēģiniet vēlreiz.',
            'invalidEmail' => 'Ievadiet derīgu e-pasta adresi.',
            'alreadyInStock' => 'Šī prece jau ir pieejama.',
            'productNotFound' => 'Prece nav atrasta.',
        ],
        'et' => [
            'link' => 'Teavita mind, kui toode on saadaval',
            'heading' => 'Toote saadavuse teavitus',
            'body' => 'Sisesta e-posti aadress ja anname teada, kui valitud toode on jälle saadaval.',
            'email' => 'E-posti aadress',
            'submit' => 'Teavita mind',
            'close' => 'Sulge',
            'success' => 'Aitäh. Teavitame sind e-posti teel, kui toode on jälle saadaval.',
            'error' => 'Salvestamine ebaõnnestus. Proovi uuesti.',
            'invalidEmail' => 'Sisesta korrektne e-posti aadress.',
            'alreadyInStock' => 'See toode on juba saadaval.',
            'productNotFound' => 'Toodet ei leitud.',
        ],
    ];
    $language = meditrendy_waitlist_language();

    return $labels[$language] ?? $labels['lt'];
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
        'hasUnavailableVariation' => meditrendy_waitlist_product_has_unavailable_variation($product),
        'hasUnavailableSetItem' => meditrendy_waitlist_set_has_unavailable_item($product),
    ];
}

function meditrendy_waitlist_product_has_unavailable_variation($product) {
    if (!$product || !is_a($product, 'WC_Product') || !$product->is_type('variable')) {
        return false;
    }

    foreach ($product->get_children() as $variation_id) {
        $variation = wc_get_product($variation_id);

        if ($variation && $variation->exists() && $variation->is_purchasable() && !$variation->is_in_stock()) {
            return true;
        }
    }

    return false;
}

function meditrendy_waitlist_set_has_unavailable_item($set) {
    if (!$set || !is_a($set, 'WC_Product') || !$set->is_type('woosb')) {
        return false;
    }

    foreach (meditrendy_waitlist_set_items($set->get_id()) as $item) {
        $item_product = !empty($item['id']) ? wc_get_product(absint($item['id'])) : null;

        if (!$item_product || !is_a($item_product, 'WC_Product')) {
            continue;
        }

        if ($item_product->is_type('variable') && meditrendy_waitlist_product_has_unavailable_variation($item_product)) {
            return true;
        }

        if (!$item_product->is_type('variable') && $item_product->is_purchasable() && !$item_product->is_in_stock()) {
            return true;
        }
    }

    return false;
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
    $labels = meditrendy_waitlist_labels();

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
            'labels' => $labels,
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

        if (!meditrendy_waitlist_set_available($set_id, $set_items)) {
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
    $set = function_exists('wc_get_product') ? wc_get_product($set_id) : null;

    if (!$set || !meditrendy_waitlist_set_available($set_id, $set_items)) {
        return;
    }

    global $wpdb;

    $table = meditrendy_stock_waitlist_table_name();
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, email FROM {$table} WHERE set_id = %d AND set_hash = %s AND notified_at IS NULL",
            $set_id,
            $set_items ? md5($set_items) : ''
        )
    );

    if (!$rows) {
        return;
    }

    $subject = __('Prekė vėl prekyboje', 'meditrendy-core');
    $headers = ['Content-Type: text/html; charset=UTF-8'];

    foreach ($rows as $row) {
        $message = sprintf(
            '<p>%1$s</p><p>%2$s <strong>%3$s</strong> %4$s</p><p><a href="%5$s">%6$s</a></p>',
            esc_html__('Sveiki,', 'meditrendy-core'),
            esc_html__('Prekė', 'meditrendy-core'),
            esc_html($set->get_name()),
            esc_html__('vėl yra prekyboje.', 'meditrendy-core'),
            esc_url($set->get_permalink()),
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

function meditrendy_waitlist_admin_capability() {
    if (function_exists('meditrendy_filter_settings_capability')) {
        return meditrendy_filter_settings_capability();
    }

    return current_user_can('manage_woocommerce') ? 'manage_woocommerce' : 'manage_options';
}

function meditrendy_waitlist_admin_menu() {
    add_submenu_page(
        'meditrendy-settings',
        __('Waitlist', 'meditrendy-core'),
        __('Waitlist', 'meditrendy-core'),
        meditrendy_waitlist_admin_capability(),
        'meditrendy-waitlist',
        'meditrendy_render_waitlist_admin_page'
    );
}

function meditrendy_waitlist_admin_product_link($product_id) {
    $product_id = absint($product_id);
    $product = $product_id && function_exists('wc_get_product') ? wc_get_product($product_id) : null;
    $label = $product ? $product->get_name() : sprintf(__('Product #%d', 'meditrendy-core'), $product_id);
    $edit_link = $product_id ? get_edit_post_link($product_id) : '';

    if (!$edit_link) {
        return esc_html($label);
    }

    return sprintf(
        '<a href="%1$s">%2$s</a>',
        esc_url($edit_link),
        esc_html($label)
    );
}

function meditrendy_waitlist_admin_date($date) {
    if (!$date) {
        return '&mdash;';
    }

    return esc_html(
        mysql2date(
            get_option('date_format') . ' ' . get_option('time_format'),
            $date
        )
    );
}

function meditrendy_waitlist_admin_set_items($set_id, $set_items = '') {
    $labels = [];

    foreach (meditrendy_waitlist_set_items($set_id, $set_items) as $item) {
        $item_product = !empty($item['id']) && function_exists('wc_get_product') ? wc_get_product(absint($item['id'])) : null;

        if (!$item_product) {
            continue;
        }

        $qty = isset($item['qty']) ? (float) $item['qty'] : 1;
        $labels[] = $qty > 1 ? sprintf('%s x %s', wc_format_decimal($qty), $item_product->get_name()) : $item_product->get_name();
    }

    if (!$labels) {
        return '';
    }

    return implode(', ', $labels);
}

function meditrendy_render_waitlist_admin_page() {
    if (!current_user_can(meditrendy_waitlist_admin_capability())) {
        wp_die(esc_html__('You do not have permission to view this page.', 'meditrendy-core'));
    }

    meditrendy_stock_waitlist_install();

    global $wpdb;

    $table = meditrendy_stock_waitlist_table_name();
    $stats = $wpdb->get_row(
        "SELECT
            COUNT(*) AS total_count,
            SUM(CASE WHEN notified_at IS NULL THEN 1 ELSE 0 END) AS pending_count,
            COUNT(DISTINCT email) AS email_count
        FROM {$table}"
    );
    $summary_rows = $wpdb->get_results(
        "SELECT
            product_id,
            set_id,
            set_hash,
            set_items,
            COUNT(*) AS total_count,
            SUM(CASE WHEN notified_at IS NULL THEN 1 ELSE 0 END) AS pending_count,
            MAX(created_at) AS last_signup
        FROM {$table}
        GROUP BY product_id, set_id, set_hash
        ORDER BY pending_count DESC, last_signup DESC
        LIMIT 200"
    );
    $recent_rows = $wpdb->get_results(
        "SELECT id, email, product_id, set_id, set_items, created_at, notified_at
        FROM {$table}
        ORDER BY created_at DESC
        LIMIT 100"
    );
    ?>
    <div class="wrap meditrendy-waitlist-admin">
        <h1><?php esc_html_e('Waitlist', 'meditrendy-core'); ?></h1>

        <div class="meditrendy-waitlist-cards" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin:18px 0;">
            <div class="card">
                <h2><?php echo esc_html((int) ($stats->pending_count ?? 0)); ?></h2>
                <p><?php esc_html_e('Pending signups', 'meditrendy-core'); ?></p>
            </div>
            <div class="card">
                <h2><?php echo esc_html((int) ($stats->total_count ?? 0)); ?></h2>
                <p><?php esc_html_e('All signups', 'meditrendy-core'); ?></p>
            </div>
            <div class="card">
                <h2><?php echo esc_html((int) ($stats->email_count ?? 0)); ?></h2>
                <p><?php esc_html_e('Unique emails', 'meditrendy-core'); ?></p>
            </div>
        </div>

        <h2><?php esc_html_e('Products with signups', 'meditrendy-core'); ?></h2>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Product', 'meditrendy-core'); ?></th>
                    <th><?php esc_html_e('Type', 'meditrendy-core'); ?></th>
                    <th><?php esc_html_e('Pending', 'meditrendy-core'); ?></th>
                    <th><?php esc_html_e('Total', 'meditrendy-core'); ?></th>
                    <th><?php esc_html_e('Last signup', 'meditrendy-core'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($summary_rows) : ?>
                    <?php foreach ($summary_rows as $row) : ?>
                        <?php
                        $set_id = absint($row->set_id);
                        $display_product_id = $set_id ?: absint($row->product_id);
                        $set_items = $set_id ? meditrendy_waitlist_admin_set_items($set_id, $row->set_items) : '';
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo meditrendy_waitlist_admin_product_link($display_product_id); ?></strong>
                                <?php if ($set_items) : ?>
                                    <br><small><?php echo esc_html($set_items); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($set_id ? __('Set', 'meditrendy-core') : __('Product', 'meditrendy-core')); ?></td>
                            <td><?php echo esc_html((int) $row->pending_count); ?></td>
                            <td><?php echo esc_html((int) $row->total_count); ?></td>
                            <td><?php echo meditrendy_waitlist_admin_date($row->last_signup); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="5"><?php esc_html_e('No waitlist signups yet.', 'meditrendy-core'); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <h2 style="margin-top:28px;"><?php esc_html_e('Recent signups', 'meditrendy-core'); ?></h2>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Email', 'meditrendy-core'); ?></th>
                    <th><?php esc_html_e('Product', 'meditrendy-core'); ?></th>
                    <th><?php esc_html_e('Status', 'meditrendy-core'); ?></th>
                    <th><?php esc_html_e('Signup date', 'meditrendy-core'); ?></th>
                    <th><?php esc_html_e('Notified date', 'meditrendy-core'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($recent_rows) : ?>
                    <?php foreach ($recent_rows as $row) : ?>
                        <?php
                        $set_id = absint($row->set_id);
                        $display_product_id = $set_id ?: absint($row->product_id);
                        ?>
                        <tr>
                            <td><?php echo esc_html($row->email); ?></td>
                            <td>
                                <?php echo meditrendy_waitlist_admin_product_link($display_product_id); ?>
                                <?php if ($set_id) : ?>
                                    <?php $set_items = meditrendy_waitlist_admin_set_items($set_id, $row->set_items); ?>
                                    <?php if ($set_items) : ?>
                                        <br><small><?php echo esc_html($set_items); ?></small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($row->notified_at ? __('Notified', 'meditrendy-core') : __('Pending', 'meditrendy-core')); ?></td>
                            <td><?php echo meditrendy_waitlist_admin_date($row->created_at); ?></td>
                            <td><?php echo meditrendy_waitlist_admin_date($row->notified_at); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="5"><?php esc_html_e('No waitlist signups yet.', 'meditrendy-core'); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

if (defined('MEDITRENDY_CORE_FILE')) {
    register_activation_hook(MEDITRENDY_CORE_FILE, 'meditrendy_stock_waitlist_install');
}

add_action('admin_init', 'meditrendy_stock_waitlist_install');
add_action('admin_menu', 'meditrendy_waitlist_admin_menu', 30);
add_action('wp_enqueue_scripts', 'meditrendy_waitlist_enqueue_assets', 30);
add_action('wp_ajax_meditrendy_stock_waitlist_subscribe', 'meditrendy_stock_waitlist_subscribe');
add_action('wp_ajax_nopriv_meditrendy_stock_waitlist_subscribe', 'meditrendy_stock_waitlist_subscribe');
add_action('woocommerce_product_set_stock_status', 'meditrendy_waitlist_trigger_back_in_stock_email', 10, 3);
add_action('woocommerce_variation_set_stock_status', 'meditrendy_waitlist_trigger_back_in_stock_email', 10, 3);
add_action('woocommerce_product_object_updated_props', 'meditrendy_waitlist_trigger_back_in_stock_email_from_props', 10, 2);
