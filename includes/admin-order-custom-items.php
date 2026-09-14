<?php
defined('ABSPATH') || exit;

/** Custom, product-free order lines. All feature metadata is private. */
function meditrendy_custom_item_is_custom($item) {
    return $item instanceof WC_Order_Item_Product && $item->get_meta('_meditrendy_custom_item') === 'yes';
}

function meditrendy_custom_item_fields($key, $values = []) {
    $fields = [
        'name' => ['Nazwa', 'text', '', ''],
        'gross' => ['Cena jednostkowa brutto', 'number', '0', '0.01'],
        'rate' => ['VAT (%) — wpisz 0 dla braku VAT', 'number', '0', '0.0001'],
        'days' => ['Dostawa (dni kalendarzowe, tylko administracja)', 'number', '0', '1'],
        'qty' => ['Ilość', 'number', '1', '1'],
    ];
    echo '<div class="med-custom-fields">';
    foreach ($fields as $field => $settings) {
        [$label, $type, $min, $step] = $settings;
        printf('<label>%s<input required type="%s" name="med_custom[%s][%s]" value="%s"%s%s /></label>',
            esc_html($label), esc_attr($type), esc_attr($key), esc_attr($field), esc_attr($values[$field] ?? ($field === 'qty' ? '1' : '')),
            $min !== '' ? ' min="' . esc_attr($min) . '"' : '', $step !== '' ? ' step="' . esc_attr($step) . '"' : '');
    }
    echo '</div>';
}

add_action('woocommerce_order_item_add_line_buttons', function($order) {
    if (!$order->is_editable()) return;
    echo '<button type="button" class="button med-add-custom-item">Dodaj własną pozycję</button>';
    echo '<template class="med-custom-template"><tr class="med-custom-draft"><td>';
    meditrendy_custom_item_fields('__KEY__');
    echo '<button type="button" class="button med-remove-custom-draft">Usuń pozycję</button></td></tr></template>';
});

add_filter('woocommerce_admin_html_order_item_class', function($class, $item) {
    return meditrendy_custom_item_is_custom($item) ? $class . ' med-custom-item' : $class;
}, 10, 2);

add_action('woocommerce_before_order_itemmeta', function($id, $item) {
    if (!meditrendy_custom_item_is_custom($item)) return;
    $order = $item->get_order();
    $currency = ['currency' => $order->get_currency()];
    printf('<div class="view med-custom-details" data-unit-gross="%s" data-total-gross="%s">',
        esc_attr(wc_price(((float) $item->get_subtotal() + (float) $item->get_subtotal_tax()) / max(1, $item->get_quantity()), $currency)),
        esc_attr(wc_price((float) $item->get_total() + (float) $item->get_total_tax(), $currency)));
    printf('Dostawa: %s dni kalendarzowych (tylko administracja); VAT: %s%%',
        esc_html($item->get_meta('_meditrendy_delivery_days')), esc_html($item->get_meta('_meditrendy_tax_percent')));
    echo '</div><div class="edit" style="display:none">';
    meditrendy_custom_item_fields($id, [
        'name' => $item->get_name(), 'gross' => $item->get_meta('_meditrendy_unit_gross'),
        'rate' => $item->get_meta('_meditrendy_tax_percent'), 'days' => $item->get_meta('_meditrendy_delivery_days'),
        'qty' => $item->get_quantity(),
    ]);
    echo '</div>';
}, 10, 2);

add_filter('woocommerce_hidden_order_itemmeta', function($keys) {
    return array_merge($keys, ['_meditrendy_custom_item', '_meditrendy_unit_gross', '_meditrendy_tax_percent', '_meditrendy_tax_rate_id', '_meditrendy_delivery_days']);
});

function meditrendy_custom_item_validate($raw, $order) {
    if (!is_array($raw)) throw new Exception('Nieprawidłowa pozycja zamówienia.');
    if (isset($raw['name']) && !is_scalar($raw['name'])) throw new Exception('Nieprawidłowa nazwa pozycji.');
    $data = ['name' => sanitize_text_field(wp_unslash($raw['name'] ?? ''))];
    if ($data['name'] === '') throw new Exception('Wpisz nazwę własnej pozycji.');
    foreach (['gross', 'rate', 'days', 'qty'] as $field) {
        $value = $raw[$field] ?? '';
        if (!is_scalar($value) || trim((string) $value) === '') throw new Exception('Wypełnij cenę brutto, VAT, dni dostawy i ilość.');
        $value = str_replace(',', '.', trim((string) $value));
        if (!is_numeric($value) || !is_finite((float) $value) || (float) $value < 0) throw new Exception('Wpisz poprawne, nieujemne wartości.');
        $data[$field] = (float) $value;
    }
    if ($data['rate'] > 100 || $data['qty'] < 1 || $data['qty'] > 1000000 || $data['gross'] > 1000000000 || $data['days'] > 1000000 || floor($data['qty']) !== $data['qty'] || floor($data['days']) !== $data['days']) {
        throw new Exception('VAT musi mieścić się w zakresie 0–100, ilość musi być dodatnią liczbą całkowitą, a dni liczbą całkowitą.');
    }
    $data['rate_id'] = 0;
    if ($data['rate'] > 0) {
        if (!wc_tax_enabled()) throw new Exception('Włącz podatki WooCommerce przed dodaniem pozycji z VAT.');
        // Prefer a matching rate already used by this order, then its country.
        $order_rates = array_map(function($tax) { return $tax->get_rate_id(); }, $order->get_items('tax'));
        $candidates = [];
        foreach (array_merge([''], WC_Tax::get_tax_class_slugs()) as $class) {
            foreach ((array) WC_Tax::get_rates_for_tax_class($class) as $id => $rate) {
                if ((int) $rate->tax_rate_compound || abs((float) $rate->tax_rate - $data['rate']) > 0.00001) continue;
                $base = wc_get_base_location();
                $country = $order->get_shipping_country() ?: ($order->get_billing_country() ?: $base['country']);
                if (!in_array((int) $id, $order_rates, true) && $rate->tax_rate_country !== '' && $rate->tax_rate_country !== $country) continue;
                $candidates[in_array((int) $id, $order_rates, true) ? 0 : 1][] = (int) $id;
            }
        }
        ksort($candidates);
        if (!$candidates) throw new Exception('Brak pasującej stawki VAT. Skonfiguruj tę stawkę w WooCommerce → Ustawienia → Podatek.');
        $data['rate_id'] = reset($candidates)[0];
    }
    return $data;
}

function meditrendy_custom_item_apply($item, $data) {
    $gross = $data['gross'] * $data['qty'];
    $net = $gross / (1 + $data['rate'] / 100);
    $taxes = $data['rate_id'] ? [$data['rate_id'] => wc_format_decimal($gross - $net, wc_get_rounding_precision())] : [];
    $item->set_name($data['name']);
    $item->set_quantity($data['qty']);
    if (!isset($data['price_changed']) || $data['price_changed']) {
        $item->set_subtotal(wc_format_decimal($net, wc_get_rounding_precision()));
        $item->set_total(wc_format_decimal($net, wc_get_rounding_precision()));
        $item->set_taxes(['subtotal' => $taxes, 'total' => $taxes]);
    }
    $item->update_meta_data('_meditrendy_custom_item', 'yes');
    $item->update_meta_data('_meditrendy_unit_gross', wc_format_decimal($data['gross']));
    $item->update_meta_data('_meditrendy_tax_percent', wc_format_decimal($data['rate']));
    $item->update_meta_data('_meditrendy_tax_rate_id', $data['rate_id']);
    $item->update_meta_data('_meditrendy_delivery_days', (int) $data['days']);
}

// Validate the entire custom payload before WooCommerce writes any items.
add_action('woocommerce_before_save_order_items', function($order_id, $items) {
    $GLOBALS['meditrendy_custom_item_save'] = [];
    if (empty($items['med_custom'])) return;
    try {
        $order = wc_get_order($order_id);
        if (!$order || !$order->is_editable() || !current_user_can('edit_shop_order', $order_id)) throw new Exception('Nie można edytować tego zamówienia.');
        if (!is_array($items['med_custom'])) throw new Exception('Nieprawidłowe dane pozycji.');
        $validated = [];
        foreach ($items['med_custom'] as $key => $raw) {
            if (!preg_match('/^(?:[1-9][0-9]*|new_[a-zA-Z0-9_]+)$/', (string) $key)) throw new Exception('Nieprawidłowy identyfikator pozycji.');
            if (is_numeric($key)) {
                $item = $order->get_item((int) $key);
                if (!meditrendy_custom_item_is_custom($item) || (int) $item->get_order_id() !== (int) $order_id) throw new Exception('Pozycja nie należy do tego zamówienia.');
            }
            $values = meditrendy_custom_item_validate($raw, $order);
            $values['price_changed'] = !is_numeric($key) || $values['qty'] !== (float) $item->get_quantity()
                || $values['gross'] !== (float) $item->get_meta('_meditrendy_unit_gross')
                || $values['rate'] !== (float) $item->get_meta('_meditrendy_tax_percent');
            $values['changed'] = $values['price_changed'] || $values['name'] !== $item->get_name()
                || $values['days'] !== (float) $item->get_meta('_meditrendy_delivery_days');
            $validated[$key] = $values;
        }
        $GLOBALS['meditrendy_custom_item_save'] = $validated;
    } catch (Exception $error) {
        if (wp_doing_ajax()) wp_send_json_error(['error' => $error->getMessage()]);
        wp_die(esc_html($error->getMessage()), '', ['back_link' => true]);
    }
}, 5, 2);

add_action('woocommerce_before_save_order_item', function($item) {
    $data = $GLOBALS['meditrendy_custom_item_save'][$item->get_id()] ?? null;
    // Unchanged custom fields must not overwrite coupons/discounts on a normal save.
    if ($data && $data['changed'] && meditrendy_custom_item_is_custom($item)) meditrendy_custom_item_apply($item, $data);
});

add_action('woocommerce_saved_order_items', function($order_id) {
    $data = $GLOBALS['meditrendy_custom_item_save'] ?? [];
    $GLOBALS['meditrendy_custom_item_save'] = [];
    $order = wc_get_order($order_id);
    $added = false;
    foreach ($data as $key => $values) {
        if (strpos((string) $key, 'new_') !== 0) continue;
        $item = new WC_Order_Item_Product();
        meditrendy_custom_item_apply($item, $values);
        $order->add_item($item);
        $added = true;
    }
    if ($added) {
        $order->save();
        $order->update_taxes();
        $order->calculate_totals(false);
    }
});

// Recalculate must retain the explicit manual rate, not infer it from a product.
add_action('woocommerce_order_item_after_calculate_taxes', function($item) {
    if (!meditrendy_custom_item_is_custom($item)) return;
    $rate_id = (int) $item->get_meta('_meditrendy_tax_rate_id');
    $rate = (float) $item->get_meta('_meditrendy_tax_percent') / 100;
    $item->set_taxes([
        'subtotal' => $rate_id ? [$rate_id => $item->get_subtotal() * $rate] : [],
        'total' => $rate_id ? [$rate_id => $item->get_total() * $rate] : [],
    ]);
});

add_action('admin_enqueue_scripts', function() {
    $screen = get_current_screen();
    if (!$screen || !in_array($screen->id, ['shop_order', 'woocommerce_page_wc-orders'], true)) return;
    wp_enqueue_script('meditrendy-custom-order-items', MEDITRENDY_CORE_URL . 'assets/js/admin-order-custom-items.js', [], filemtime(MEDITRENDY_CORE_DIR . 'assets/js/admin-order-custom-items.js'), true);
    wp_enqueue_style('meditrendy-custom-order-items', MEDITRENDY_CORE_URL . 'assets/css/admin-order-custom-items.css', [], filemtime(MEDITRENDY_CORE_DIR . 'assets/css/admin-order-custom-items.css'));
});
