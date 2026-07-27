<?php
if (!defined('ABSPATH')) exit;

/**
 * Bulk PDF summary for selected WooCommerce orders.
 */

function meditrendy_orders_pdf_default_realization_days() {
    return max(0, absint(apply_filters('meditrendy_orders_pdf_default_realization_days', 2)));
}

function meditrendy_orders_pdf_realization_field() {
    global $post;

    $value = $post ? get_post_meta($post->ID, '_meditrendy_realization_days', true) : '';

    woocommerce_wp_text_input([
        'id'                => '_meditrendy_realization_days',
        'label'             => __('Czas realizacji (dni)', 'meditrendy-core'),
        'description'       => __('Liczba dni dodawana do daty zamówienia przy wyliczaniu terminu dostawy.', 'meditrendy-core'),
        'desc_tip'          => true,
        'type'              => 'number',
        'value'             => $value === '' ? meditrendy_orders_pdf_default_realization_days() : $value,
        'custom_attributes' => [
            'min'  => '0',
            'max'  => '365',
            'step' => '1',
        ],
    ]);
}
add_action('woocommerce_product_options_general_product_data', 'meditrendy_orders_pdf_realization_field');

function meditrendy_orders_pdf_save_realization_field($product) {
    if (!isset($_POST['_meditrendy_realization_days'])) {
        return;
    }

    $days = absint(wp_unslash($_POST['_meditrendy_realization_days']));
    $product->update_meta_data('_meditrendy_realization_days', min(365, $days));
}
add_action('woocommerce_admin_process_product_object', 'meditrendy_orders_pdf_save_realization_field');

function meditrendy_orders_pdf_product_realization_days($product) {
    if (!$product || !is_a($product, 'WC_Product')) {
        return meditrendy_orders_pdf_default_realization_days();
    }

    $product_ids = [(int) $product->get_id()];

    if ($product->is_type('variation')) {
        $product_ids[] = (int) $product->get_parent_id();
    }

    foreach (array_filter(array_unique($product_ids)) as $product_id) {
        $value = get_post_meta($product_id, '_meditrendy_realization_days', true);

        if ($value !== '') {
            return min(365, max(0, absint($value)));
        }
    }

    return meditrendy_orders_pdf_default_realization_days();
}

function meditrendy_orders_pdf_order_realization_days($order) {
    $longest = null;

    foreach ($order->get_items('line_item') as $item) {
        if (!$item instanceof WC_Order_Item_Product) {
            continue;
        }

        $product = $item->get_product();

        if (!$product && $item->get_product_id()) {
            $product = wc_get_product($item->get_product_id());
        }

        $days = meditrendy_orders_pdf_product_realization_days($product);
        $longest = $longest === null ? $days : max($longest, $days);
    }

    return $longest === null ? meditrendy_orders_pdf_default_realization_days() : $longest;
}

function meditrendy_orders_pdf_delivery_date($order, $realization_days) {
    $created = $order->get_date_created();

    if (!$created) {
        return '';
    }

    $delivery = clone $created;
    $delivery->modify('+' . absint($realization_days) . ' days');

    return $delivery->date_i18n('d-m-Y');
}

function meditrendy_orders_pdf_add_bulk_action($actions) {
    $actions[__('Meditrendy', 'meditrendy-core')]['meditrendy_download_orders_pdf'] =
        __('Drukuj zamówienia (PDF)', 'meditrendy-core');

    return $actions;
}
add_filter('bulk_actions-edit-shop_order', 'meditrendy_orders_pdf_add_bulk_action');
add_filter('bulk_actions-woocommerce_page_wc-orders', 'meditrendy_orders_pdf_add_bulk_action');
add_filter('bulk_actions-admin_page_wc-orders', 'meditrendy_orders_pdf_add_bulk_action');

function meditrendy_orders_pdf_clean_meta_value($value) {
    return trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags(html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8'))));
}

function meditrendy_orders_pdf_item_attribute($item, $keys) {
    $keys = array_map('sanitize_title', (array) $keys);

    foreach ($item->get_all_formatted_meta_data('') as $meta) {
        $key = sanitize_title(str_replace('attribute_', '', (string) $meta->key));

        if (in_array($key, $keys, true)) {
            return meditrendy_orders_pdf_clean_meta_value($meta->display_value);
        }
    }

    return '';
}

function meditrendy_orders_pdf_item_details($item, $product) {
    $details = [];
    $collection = function_exists('meditrendy_admin_order_item_collection')
        ? meditrendy_admin_order_item_collection($item, $product)
        : '';
    $color = function_exists('meditrendy_admin_order_item_get_color')
        ? meditrendy_admin_order_item_get_color($item, $product)
        : false;
    $size = meditrendy_orders_pdf_item_attribute($item, ['pa_size', 'pa_dydis', 'pa_rozmiar', 'size', 'dydis', 'rozmiar']);
    $length = meditrendy_orders_pdf_item_attribute($item, ['pa_length', 'pa_dlugosc', 'pa_ilgis', 'pa_kelniu-ilgis', 'pa_pants-length', 'length', 'dlugosc', 'ilgis']);

    if ($collection !== '') {
        $details[] = __('Kolekcja', 'meditrendy-core') . ': ' . $collection;
    }

    if ($color && !empty($color['value'])) {
        $details[] = __('Kolor', 'meditrendy-core') . ': ' . $color['value'];
    }

    if ($size !== '') {
        $details[] = __('Rozmiar', 'meditrendy-core') . ': ' . $size;
    }

    if ($length !== '') {
        $details[] = __('Długość', 'meditrendy-core') . ': ' . $length;
    }

    return $details;
}

function meditrendy_orders_pdf_item_gross($item) {
    $taxes = $item->get_taxes();
    $total_taxes = !empty($taxes['total']) && is_array($taxes['total'])
        ? array_sum(array_map('floatval', $taxes['total']))
        : (float) $item->get_total_tax();

    return (float) $item->get_total() + (float) $total_taxes;
}

function meditrendy_orders_pdf_money($amount, $order) {
    return meditrendy_orders_pdf_clean_meta_value(wc_price($amount, [
        'currency' => $order->get_currency(),
    ]));
}

function meditrendy_orders_pdf_order_item_groups($order) {
    $items = $order->get_items('line_item');
    $children = [];
    $used_children = [];

    foreach ($items as $item_id => $item) {
        $parent_reference = $item->get_meta('_woosb_parent_id', true);

        if ($parent_reference !== '') {
            $children[$item_id] = [
                'reference' => (string) $parent_reference,
                'item'      => $item,
            ];
        }
    }

    $groups = [];

    foreach ($items as $item_id => $item) {
        if (isset($children[$item_id])) {
            continue;
        }

        $group_children = [];
        $product_id = (string) $item->get_product_id();

        foreach ($children as $child_id => $child) {
            if (isset($used_children[$child_id])) {
                continue;
            }

            if ($child['reference'] === $product_id || $child['reference'] === (string) $item_id) {
                $group_children[$child_id] = $child['item'];
                $used_children[$child_id] = true;
            }
        }

        $groups[] = [
            'item'     => $item,
            'children' => $group_children,
        ];
    }

    foreach ($children as $child_id => $child) {
        if (!isset($used_children[$child_id])) {
            $groups[] = [
                'item'     => $child['item'],
                'children' => [],
            ];
        }
    }

    return $groups;
}

function meditrendy_orders_pdf_escape($value) {
    return esc_html((string) $value);
}

function meditrendy_orders_pdf_product_cell($item, $children) {
    $product = $item->get_product();
    $html = '<div class="product-name">' . meditrendy_orders_pdf_escape($item->get_name()) . '</div>';
    $details = meditrendy_orders_pdf_item_details($item, $product);

    if (!empty($details)) {
        $html .= '<div class="product-details">' . meditrendy_orders_pdf_escape(implode(' | ', $details)) . '</div>';
    }

    foreach ($children as $child) {
        $child_product = $child->get_product();
        $child_details = meditrendy_orders_pdf_item_details($child, $child_product);
        $html .= '<div class="bundle-child">- ' . meditrendy_orders_pdf_escape($child->get_quantity()) . ' × ' . meditrendy_orders_pdf_escape($child->get_name()) . '</div>';

        if (!empty($child_details)) {
            $html .= '<div class="bundle-details">' . meditrendy_orders_pdf_escape(implode(' | ', $child_details)) . '</div>';
        }
    }

    return $html;
}

function meditrendy_orders_pdf_order_html($order) {
    $created = $order->get_date_created();
    $order_date = $created ? $created->date_i18n('d-m-Y') : '';
    $customer = trim($order->get_formatted_billing_full_name());

    if ($customer === '') {
        $customer = trim($order->get_formatted_shipping_full_name());
    }

    $phone = trim((string) $order->get_billing_phone());
    $realization_days = meditrendy_orders_pdf_order_realization_days($order);
    $delivery_date = meditrendy_orders_pdf_delivery_date($order, $realization_days);
    $days_text = sprintf(
        _n('%d dzień', '%d dni', $realization_days, 'meditrendy-core'),
        $realization_days
    );
    $heading_parts = array_filter([
        $order->get_order_number(),
        $order_date,
        $customer,
        $phone,
    ]);

    $html = '<div class="order">';
    $html .= '<h2>' . meditrendy_orders_pdf_escape(implode(' - ', $heading_parts)) . '</h2>';
    $html .= '<table class="items" cellpadding="0" cellspacing="0">';
    $html .= '<thead><tr>';
    $html .= '<th class="product">' . esc_html__('Produkt', 'meditrendy-core') . '</th>';
    $html .= '<th class="qty">' . esc_html__('Ilość', 'meditrendy-core') . '</th>';
    $html .= '<th class="gross">' . esc_html__('Brutto', 'meditrendy-core') . '</th>';
    $html .= '<th class="delivery">' . esc_html__('Termin dostawy', 'meditrendy-core') . '</th>';
    $html .= '</tr></thead><tbody>';

    foreach (meditrendy_orders_pdf_order_item_groups($order) as $group) {
        $item = $group['item'];
        $gross = meditrendy_orders_pdf_item_gross($item);

        foreach ($group['children'] as $child) {
            $gross += meditrendy_orders_pdf_item_gross($child);
        }

        $html .= '<tr>';
        $html .= '<td class="product">' . meditrendy_orders_pdf_product_cell($item, $group['children']) . '</td>';
        $html .= '<td class="qty">' . meditrendy_orders_pdf_escape($item->get_quantity()) . '</td>';
        $html .= '<td class="gross">' . meditrendy_orders_pdf_escape(meditrendy_orders_pdf_money($gross, $order)) . '</td>';
        $html .= '<td class="delivery">' . meditrendy_orders_pdf_escape($delivery_date . ' (' . $days_text . ')') . '</td>';
        $html .= '</tr>';
    }

    $html .= '</tbody></table>';

    $customer_note = meditrendy_orders_pdf_clean_meta_value($order->get_customer_note());
    $html .= '<div class="notes"><b>' . esc_html__('Uwagi klienta', 'meditrendy-core') . ':</b>';

    if ($customer_note !== '') {
        $html .= ' ' . meditrendy_orders_pdf_escape($customer_note);
    }

    $html .= '</div></div>';

    return $html;
}

function meditrendy_orders_pdf_document_html($orders) {
    $html = '<style>
        body { color: #111111; font-family: dejavusans; font-size: 8.7pt; }
        .generated { margin: 0 0 10px; text-align: right; font-size: 8pt; }
        .order { margin: 0 0 13px; }
        h2 { margin: 0 0 7px; font-size: 12pt; line-height: 1.2; }
        table.items { width: 100%; border-collapse: collapse; }
        table.items th { border-bottom: 0.6px solid #9a9a9a; font-size: 7.7pt; font-weight: bold; padding: 2px 3px; text-align: left; }
        table.items td { border-bottom: 0.45px solid #bdbdbd; padding: 3px; vertical-align: top; }
        .product { width: 56%; }
        .qty { width: 8%; text-align: center; }
        .gross { width: 15%; text-align: right; white-space: nowrap; }
        .delivery { width: 21%; text-align: right; white-space: nowrap; }
        .product-name { font-size: 9pt; }
        .product-details, .bundle-details { font-size: 7.2pt; color: #303030; }
        .bundle-child { margin-top: 3px; font-size: 8pt; }
        .bundle-details { margin-left: 8px; }
        .notes { margin-top: 5px; min-height: 11px; font-size: 7.5pt; }
    </style>';
    $html .= '<div class="generated">' . meditrendy_orders_pdf_escape(wp_date('d-m-Y H:i:s')) . '</div>';

    foreach ($orders as $order) {
        $html .= meditrendy_orders_pdf_order_html($order);
    }

    return $html;
}

function meditrendy_orders_pdf_load_tcpdf() {
    if (class_exists('TCPDF')) {
        return true;
    }

    $tcpdf_path = WP_PLUGIN_DIR . '/omniva-woocommerce/vendor/tecnickcom/tcpdf/tcpdf.php';

    if (!is_readable($tcpdf_path)) {
        return false;
    }

    require_once $tcpdf_path;

    return class_exists('TCPDF');
}

function meditrendy_orders_pdf_download($order_ids) {
    if (!meditrendy_orders_pdf_load_tcpdf()) {
        wp_die(esc_html__('Nie znaleziono biblioteki PDF. Sprawdź instalację wtyczki Omniva.', 'meditrendy-core'));
    }

    $orders = [];

    foreach (array_unique(array_filter(array_map('absint', (array) $order_ids))) as $order_id) {
        $order = wc_get_order($order_id);

        if ($order instanceof WC_Order) {
            $orders[] = $order;
        }
    }

    if (empty($orders)) {
        wp_die(esc_html__('Nie wybrano prawidłowych zamówień.', 'meditrendy-core'));
    }

    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Meditrendy');
    $pdf->SetAuthor('Meditrendy');
    $pdf->SetTitle(__('Zestawienie zamówień', 'meditrendy-core'));
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(14, 13, 14);
    $pdf->SetAutoPageBreak(true, 13);
    $pdf->SetFont('dejavusans', '', 9);
    $pdf->AddPage();
    $pdf->writeHTML(meditrendy_orders_pdf_document_html($orders), true, false, true, false, '');

    while (ob_get_level()) {
        ob_end_clean();
    }

    $filename = 'zamowienia-' . wp_date('Y-m-d-His') . '.pdf';
    $pdf->Output($filename, 'D');
    exit;
}

function meditrendy_orders_pdf_handle_bulk_action($redirect_url, $action, $order_ids) {
    if ($action !== 'meditrendy_download_orders_pdf') {
        return $redirect_url;
    }

    if (!current_user_can('manage_woocommerce') && !current_user_can('edit_shop_orders')) {
        wp_die(esc_html__('Nie masz uprawnień do eksportowania zamówień.', 'meditrendy-core'));
    }

    meditrendy_orders_pdf_download($order_ids);

    return $redirect_url;
}
add_filter('handle_bulk_actions-edit-shop_order', 'meditrendy_orders_pdf_handle_bulk_action', 10, 3);
add_filter('handle_bulk_actions-woocommerce_page_wc-orders', 'meditrendy_orders_pdf_handle_bulk_action', 10, 3);
add_filter('handle_bulk_actions-admin_page_wc-orders', 'meditrendy_orders_pdf_handle_bulk_action', 10, 3);

/**
 * WooCommerce can use a role-dependent screen prefix for the HPOS orders
 * page. Register against the actual runtime ID as well as the known IDs above.
 */
function meditrendy_orders_pdf_register_current_screen_actions($screen) {
    if (!$screen instanceof WP_Screen) {
        return;
    }

    $is_orders_screen = $screen->id === 'edit-shop_order'
        || strpos($screen->id, 'wc-orders') !== false;

    if (!$is_orders_screen) {
        return;
    }

    add_filter('bulk_actions-' . $screen->id, 'meditrendy_orders_pdf_add_bulk_action');
    add_filter('handle_bulk_actions-' . $screen->id, 'meditrendy_orders_pdf_handle_bulk_action', 10, 3);
}
add_action('current_screen', 'meditrendy_orders_pdf_register_current_screen_actions', 5);
